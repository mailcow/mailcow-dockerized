<?php

declare(strict_types=1);

namespace Mailcow\Scim\Service;

use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Scim\Legacy\LegacyMailboxes;
use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\PatchOp;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Model\User;
use Mailcow\Scim\Repository\UserRepository;
use Mailcow\Scim\ScimError;

/**
 * SCIM Users (RFC 7644 3.3-3.6) on mailboxes with authsource 'scim'. Offboarding follows the IdP sync of mailcow:
 *
 * - active=false disallows the login (mailbox.active 2), mail is still delivered. active=true allows it again,
 *   but only when SCIM disallowed it: a state set by an admin wins.
 * - DELETE disallows the login too and removes the user from its SCIM groups and from SCIM: afterwards the id
 *   is unknown (RFC 7644 3.6). The mailbox and its mails are kept, deleting them is up to the admin.
 * - POST adopts a mailbox with authsource 'scim' that is no SCIM user: the admin handed it over by choosing the
 *   authsource. A user deleted through SCIM is linked again only for the same person (same token and externalId),
 *   like the Keycloak/LDAP sync keeps its IdP link: for someone else the admin releases the address first, by
 *   switching its authsource to another one and back to SCIM.
 *
 * userName changes rename the mailbox within its domain if the token allows it (like Edit mailbox > Rename
 * mailbox, the old address optionally kept as alias); otherwise userName is immutable.
 *
 * PUT and PATCH validate everything before the first write. The writes themselves are legacy mailbox() calls
 * that commit one by one (the rename also moves the maildir), so they cannot be rolled back together: they run
 * in the order rename, template, name/active, SCIM row, and repeating the request after a failure converges.
 */
class UserService
{
    /** @var string[] problems that did not fail the request (ScimController logs them) */
    private array $notices = [];

    /** @param ?TemplateService $templates group templates, which win over the mapped template like at IdP logins */
    public function __construct(private UserRepository $users, private LegacyMailboxes $legacy, private GroupService $groups,
        private ?TemplateService $templates = null)
    {
    }

    /** @return string[] the notices since the last call */
    public function notices(): array
    {
        [$notices, $this->notices] = [$this->notices, []];
        return $notices;
    }

    /**
     * @param ?Filter $filter narrows the users by SQL; the caller still applies it
     * @return User[]
     */
    public function list(Token $token, ?Filter $filter = null, int $offset = 0, ?int $limit = null): array
    {
        $users = $this->users->all($token->domainRestriction, $filter, $offset, $limit);
        // up to a page's worth the groups of these users, else of all (one query instead of a huge IN list)
        return $this->withGroups($token, $users, (count($users) <= 1000) ? array_map(fn(User $u): string => $u->username, $users) : null);
    }

    /** @return array{0: User[], 1: int} one page of the users and the number of all */
    public function page(Token $token, int $offset, int $limit): array
    {
        $users = $this->users->all($token->domainRestriction, null, $offset, $limit);
        return [$this->withGroups($token, $users, array_map(fn(User $u): string => $u->username, $users)), $this->users->count($token->domainRestriction)];
    }

    /** @throws ScimError 404, also for users outside the token's domain */
    public function get(Token $token, string $id): User
    {
        $user = $this->users->find($id, $token->domainRestriction) ?? throw ScimError::notFound('User not found');
        return $this->withGroups($token, [$user], [$user->username])[0];
    }

    /** @throws ScimError */
    public function create(Token $token, array $body): User
    {
        Schema::requireSchema($body, Schema::USER);
        [$local, $domain] = self::userName($body['username'] ?? null);
        $username = $local . '@' . $domain;
        // checked first, so a restricted token cannot probe other domains
        if (!$token->covers($domain)) {
            throw new ScimError(403, "The token is restricted to the domain '{$token->domainRestriction}'");
        }
        $mailbox = $this->users->mailbox($username);
        // mailbox() and the legacy create are not atomic: a concurrent POST of a new address gets 409
        // from object_exists or the duplicate key below
        if ($mailbox !== null && $mailbox['authsource'] !== 'scim') {
            throw ScimError::uniqueness("The mailbox '$username' uses the authentication source '{$mailbox['authsource']}': set it to SCIM in mailcow to hand it over");
        }
        if ($mailbox !== null && $mailbox['linked']) {
            throw ScimError::uniqueness("User '$username' already exists");
        }
        $externalId = self::externalId($body);
        if ($externalId !== null && $this->users->externalIdTaken($externalId, $token->id)) {
            throw ScimError::uniqueness("externalId '$externalId' belongs to another user");
        }
        $name = self::name($body, $local);
        [$given, $family] = self::nameParts($body);
        // null is unassigned (RFC 7643 2.5)
        $active = isset($body['active']) ? Schema::bool($body['active']) : true;
        $template = $this->existing($username, $token->templateFor(self::templateAttribute($body)), false);
        $disabled = $active ? 0 : 1;
        if ($mailbox === null) {
            try {
                $this->check($this->legacy->create($local, $domain, $name, $active ? 1 : 2, $template));
            } catch (\PDOException $e) {
                throw (($e->errorInfo[1] ?? null) === 1062) ? ScimError::uniqueness("User '$username' already exists") : $e;
            }
        } else {
            // a user deleted through SCIM gets the mailbox (with its mail and credentials) back only when it is the same
            // person, by token and externalId; an IdP without externalId cannot tell persons apart. Someone else needs the
            // admin to release the address
            if ($mailbox['tombstone'] && ($mailbox['external_id'] === null || $mailbox['token_id'] !== $token->id
                || $mailbox['external_id'] !== $externalId)) {
                throw ScimError::uniqueness("The mailbox '$username' belongs to a user deleted through SCIM: to give it with its mail and"
                    . ' credentials to someone else, set its authentication source in mailcow to another one and back to SCIM'
                    . ' and enable its login');
            }
            // adoption: the mailbox gets the template (a group template wins, as at IdP logins), then the name and state
            // of the IdP. The state only where SCIM decides it: an active mailbox or one SCIM disabled; a state an admin
            // set (inactive, login disabled) stays, also against the template's own active
            if ($template !== null) {
                $this->check($this->legacy->applyTemplate($username, $name, $this->effective($username, $template)));
            }
            $fields = ['name' => $name];
            if ($mailbox['active'] === 1 || ($mailbox['active'] === 2 && $mailbox['disabled'] === 1)) {
                $fields['active'] = $active ? 1 : 2;
            } else {
                $fields['active'] = $mailbox['active'];
                $disabled = 0;
            }
            $this->check($this->legacy->edit($username, $fields));
        }
        $id = Schema::uuid();
        if (!$this->users->link($id, $username, $externalId, $token->id, $template, $disabled, $given, $family)) {
            throw ScimError::uniqueness("User '$username' already exists");
        }
        return $this->get($token, $id);
    }

    /**
     * PUT: replaces the user; omitted attributes are cleared (externalId, name parts) or keep their value
     * (active: a PUT without it must not lift an offboarding, RFC 7644 3.5.1 leaves the default to the provider)
     *
     * @param ?callable(User): void $precondition runs on the user read under its lock, before anything is written
     */
    public function replace(Token $token, string $id, array $body, ?callable $precondition = null): User
    {
        return $this->users->locked($id, fn(): User => $this->replaceLocked($token, $id, $body, $precondition));
    }

    private function replaceLocked(Token $token, string $id, array $body, ?callable $precondition): User
    {
        $user = $this->get($token, $id);
        if ($precondition !== null) {
            $precondition($user);
        }
        Schema::requireSchema($body, Schema::USER);
        if (!is_string($body['username'] ?? null) || trim($body['username']) === '') {
            throw ScimError::invalidValue('userName is required');
        }
        $username = $this->renameTarget($token, $user, $body['username']);
        $template = $this->templateChange($token, $user, self::templateAttribute($body), false);
        $template = $this->existing($user->username, $template, true);
        $externalId = self::externalId($body);
        // null or omitted: unchanged
        $active = isset($body['active']) ? Schema::bool($body['active']) : null;
        $local = strstr($username ?? $user->username, '@', true);
        $this->save($token, $user, self::name($body, $local), $active, $externalId, $template, $username, self::nameParts($body));
        return $this->get($token, $id);
    }

    /**
     * PATCH: all operations are validated before anything is written (see the class description for the writes)
     *
     * @param ?callable(User): void $precondition runs on the user read under its lock, before anything is written
     */
    public function patch(Token $token, string $id, array $body, ?callable $precondition = null): User
    {
        return $this->users->locked($id, fn(): User => $this->patchLocked($token, $id, $body, $precondition));
    }

    private function patchLocked(Token $token, string $id, array $body, ?callable $precondition): User
    {
        $user = $this->get($token, $id);
        if ($precondition !== null) {
            $precondition($user);
        }
        [$given, $family] = $user->nameParts();
        // name: an explicit new name (displayName, name.formatted); parts: givenName/familyName changed
        // template: the last template operation, [remove, value], resolved once after all operations
        $state = ['given' => $given, 'family' => $family, 'parts' => false, 'set' => [], 'name' => null, 'active' => null,
            'externalId' => $user->externalId, 'template' => null, 'username' => null, 'emails' => []];
        foreach (PatchOp::parse($body, 'User') as $op) {
            if ($op->ignored) {
                continue;
            }
            if ($op->path === null) {
                $this->patchObject($state, $op->op, $op->value, $user, $token);
            } else {
                $this->patchPath($state, $op->op, $op->path, $op->value, $user, $token);
            }
        }
        // emails follow userName, also one renamed by the same request
        foreach ($state['emails'] as [$op, $value, $sub]) {
            self::checkEmails($state['username'] ?? $user->username, $op, $value, $sub);
        }
        $name = $state['name'];
        // the name follows the parts when it was built from them (no displayName of its own). Without parts the IdP
        // sent earlier, the split of the name is a guess: only a request with both parts replaces it
        [$oldGiven, $oldFamily] = $user->nameParts();
        $known = ($user->givenName !== null || $user->familyName !== null) || count($state['set']) === 2;
        if ($name === null && $state['parts'] && $known && trim($user->name) === trim($oldGiven . ' ' . $oldFamily)) {
            $name = trim($state['given'] . ' ' . $state['family']);
        }
        if ($name === '') {
            // mailbox('edit') keeps the name for '', so a cleared name becomes the local part like at creation
            $name = strstr($state['username'] ?? $user->username, '@', true);
        }
        if ($name !== null) {
            self::checkLength($name, 'name');
        }
        // parts are stored when they are known: sent before, or both sent now (a guessed half would stick)
        $parts = ($state['parts'] && $known) ? self::partsOrNull($state['given'], $state['family']) : null;
        $template = ($state['template'] === null) ? null : $this->templateChange($token, $user, $state['template'][1], $state['template'][0]);
        $template = $this->existing($user->username, $template, true);
        $this->save($token, $user, $name, $state['active'], $state['externalId'], $template, $state['username'], $parts);
        return $this->get($token, $id);
    }

    /**
     * DELETE: see the class description. Nothing of the mailbox is removed; its row keeps who the user was, so only
     * that person gets it back. The login goes first, so a failure changes nothing.
     *
     * @param ?callable(User): void $precondition runs on the user read under its lock, before anything is written
     */
    public function delete(Token $token, string $id, ?callable $precondition = null): void
    {
        $this->users->locked($id, fn() => $this->deleteLocked($token, $id, $precondition));
    }

    private function deleteLocked(Token $token, string $id, ?callable $precondition): void
    {
        $user = $this->get($token, $id);
        if ($precondition !== null) {
            $precondition($user);
        }
        if ($user->active === 1) {
            $this->check($this->legacy->edit($user->username, ['active' => 2]));
            // recorded at once: a retry after a later failure still knows SCIM disabled the login (template guard)
            $this->users->update($user->username, ['disabled' => 1]);
        }
        $this->groups->removeMember($user->username);
        $this->users->unlink($user->username, ($user->active === 1 || $user->disabled === 1) ? 1 : 0);
    }

    /**
     * The authentication source of a mailbox changed from SCIM to another one in mailcow (mailbox('edit')): it
     * leaves SCIM and is released (its SCIM row and IdP link go). Set
     * back to SCIM, any SCIM user may get it, with its mail and credentials; the admin keeps its login state.
     */
    public function onAuthsourceLeft(string $username): void
    {
        $this->groups->removeMember($username);
        $this->users->release($username);
    }

    /**
     * @param User[] $users
     * @param ?string[] $usernames the users' usernames, null: all users
     * @return User[]
     */
    private function withGroups(Token $token, array $users, ?array $usernames): array
    {
        $memberships = $this->groups->membershipsOf($token, $usernames);
        foreach ($users as $user) {
            $user->groups = $memberships[$user->username] ?? [];
            // externalId is scoped to the provisioning client (RFC 7643 3.1): only the token that set it sees it
            if ($user->tokenId !== $token->id) {
                $user->externalId = null;
            }
        }
        return $users;
    }

    /**
     * Writes the changes of PUT and PATCH, after all checks. $template: newly mapped template to apply (a group
     * template wins), null = unchanged. $active null = unchanged. $username: new userName (renameTarget()), null = unchanged.
     * $parts: [givenName, familyName] to store, null = unchanged.
     *
     * @throws ScimError
     */
    private function save(Token $token, User $user, ?string $name, ?bool $active, ?string $externalId, ?string $template,
        ?string $username, ?array $parts = null): void
    {
        if ($externalId !== null && $externalId !== $user->externalId && $this->users->externalIdTaken($externalId, $token->id, $user->username)) {
            throw ScimError::uniqueness("externalId '$externalId' belongs to another user");
        }
        if ($username !== null) {
            $this->rename($token, $user->username, $username);
            $user->username = $username;
        }
        $fields = [];
        // the token that writes externalId owns it; $user->externalId is null when another token set it
        $map = ($externalId !== $user->externalId) ? ['external_id' => $externalId, 'token_id' => $token->id] : [];
        if ($parts !== null && $parts !== [$user->givenName, $user->familyName]) {
            [$map['given_name'], $map['family_name']] = $parts;
        }
        if ($template !== null) {
            // the newly mapped template, unless a group template wins (as at IdP logins and syncs)
            $this->check($this->legacy->applyTemplate($user->username, $name ?? $user->name, $this->effective($user->username, $template)));
            $map['template'] = $template;
            // a new template can set its own active: the state change below starts from the state after it
            $user->active = $this->users->mailbox($user->username)['active'] ?? $user->active;
        }
        if ($name !== null && $name !== $user->name) {
            $fields['name'] = $name;
        }
        // an active mailbox is disabled, also when an admin re-enabled one SCIM had disabled (the flag is still set)
        if ($active === false && $user->active === 1) {
            $fields['active'] = 2;
            $map['disabled'] = 1;
        } elseif ($active === true && $user->disabled === 1) {
            if ($user->active === 2) {
                $fields['active'] = 1;
            }
            $map['disabled'] = 0;
        }
        if ($fields !== []) {
            $this->check($this->legacy->edit($user->username, $fields));
        }
        $this->users->update($user->username, $map);
    }

    /** One operation without path: its value is an object of attributes (keys lowercased) */
    private function patchObject(array &$state, string $op, array $value, User $user, Token $token): void
    {
        foreach ($value as $key => $v) {
            $key = (string)$key;
            if ($key === strtolower(Schema::USER)) {
                $this->patchObject($state, $op, self::object($v, 'Schema value'), $user, $token);
            } elseif ($key === strtolower(Schema::EXT)) {
                self::templateOp($state, $op, ($v === null) ? [] : self::object($v, 'Extension value'), true);
            } elseif (in_array($key, Schema::IGNORED, true) || $key === strtolower(Schema::ENTERPRISE)) {
                continue;
            } elseif ($key === 'schemas' || $key === 'meta' || $key === 'groups') {
                // echoed by IdPs that send the whole resource; changes are not applied
                continue;
            } elseif ($key === 'id') {
                if (!is_string($v) || $v !== $user->scimId) {
                    throw ScimError::mutability("'id' is read-only");
                }
            } elseif (str_contains($key, '.') || str_contains($key, '[') || str_contains($key, ':')) {
                // Entra ID sends path-less operations with paths as keys: {"name.givenName": "x"}
                $path = Filter::path($key, 'User');
                if ($path !== null) {
                    $this->patchPath($state, $op, $path, $v, $user, $token);
                }
            } elseif (Schema::attribute('User', $key) !== null) {
                $this->patchPath($state, $op, ['attr' => Schema::attribute('User', $key), 'filter' => null, 'sub' => null], $v, $user, $token);
            } else {
                throw new ScimError(400, "Unknown attribute '$key'", 'invalidPath');
            }
        }
    }

    /** One operation on a parsed path */
    private function patchPath(array &$state, string $op, array $path, mixed $value, User $user, Token $token): void
    {
        $attr = $path['attr'];
        if ($attr['ext']) {
            if ($attr['parts'] === ['template']) {
                $state['template'] = ($op === 'remove' || $value === null || trim((string)self::text($value, 'template')) === '')
                    ? [true, null] : [false, self::text($value, 'template')];
            } else {
                self::templateOp($state, $op, ($op === 'remove' || $value === null) ? null : self::object($value, 'Extension value'), false);
            }
            return;
        }
        $sub = $attr['parts'][1] ?? $path['sub'];
        switch ($attr['parts'][0]) {
            case 'username':
                if ($op === 'remove' || $value === null) {
                    // required: removed or unassigned (RFC 7644 3.5.2.2)
                    throw ScimError::mutability('userName cannot be removed');
                }
                $state['username'] = $this->renameTarget($token, $user, $value);
                return;
            case 'displayname':
                // remove and null unassign (RFC 7643 2.5): the name becomes the local part
                $state['name'] = ($op === 'remove' || $value === null) ? '' : self::text($value, 'displayName');
                return;
            case 'name':
                if ($sub === null && $op !== 'remove' && $value !== null) {
                    $this->patchObject($state, $op, self::prefixed(self::object($value, "'name'")), $user, $token);
                    return;
                }
                $text = ($op === 'remove' || $value === null) ? '' : self::checkLength(self::text($value, 'name'), 'name');
                if ($sub === null) {
                    [$state['name'], $state['given'], $state['family'], $state['parts']] = ['', '', '', true];
                    $state['set'] = ['givenname' => true, 'familyname' => true];
                } elseif ($sub === 'formatted') {
                    $state['name'] = $text;
                } else {
                    $state[($sub === 'givenname') ? 'given' : 'family'] = $text;
                    $state['set'][$sub] = true;
                    $state['parts'] = true;
                }
                return;
            case 'active':
                // remove and null unassign (RFC 7643 2.5): unassigned keeps the login state, like a PUT without it
                $state['active'] = ($op === 'remove' || $value === null) ? null : Schema::bool($value);
                return;
            case 'externalid':
                $text = ($op === 'remove' || $value === null) ? '' : self::checkLength(self::text($value, 'externalId'), 'externalId');
                $state['externalId'] = ($text === '') ? null : $text;
                return;
            case 'emails':
                // RFC 7644 3.5.2.3: a replace whose value filter matches nothing has no target
                if ($op === 'replace' && $path['filter'] !== null && !$path['filter']->matches($user->toResource('')['emails'][0])) {
                    throw new ScimError(400, 'No email matches the filter', 'noTarget');
                }
                if ($op === 'remove') {
                    throw ScimError::mutability("'emails' is read-only (derived from userName)");
                }
                $state['emails'][] = [$op, $value, $sub];
                return;
            default:
                // id, schemas, meta, groups (membership changes through the Group, RFC 7643 4.1.2)
                throw ScimError::mutability("'{$attr['parts'][0]}' is read-only");
        }
    }

    /**
     * Records an operation on the whole extension object: its `template` key (null or "" = unassigned, the default
     * template), the object removed (unassigned), or nothing when the key is absent from a path-less object
     *
     * @param ?array $ext the (lowercased) extension object, null when removed
     */
    private static function templateOp(array &$state, string $op, ?array $ext, bool $pathless): void
    {
        if ($ext === null || ($op === 'remove' && !$pathless)) {
            $state['template'] = [true, null];
        } elseif (array_key_exists('template', $ext)) {
            $value = ($ext['template'] === null) ? '' : trim(self::text($ext['template'], 'template'));
            $state['template'] = ($value === '') ? [true, null] : [false, $value];
        }
    }

    /** The template applied for a mapped one: a group template wins, as at IdP logins and syncs */
    private function effective(string $username, string $template): string
    {
        return $this->templates?->templateForLogin($username, $template) ?? $template;
    }

    /**
     * $template when the template that would be applied for it exists, else null and a notice. A missing template
     * must not block the rest of a request, e.g. the deactivation of an offboarding PUT
     */
    private function existing(string $username, ?string $template, bool $exists): ?string
    {
        if ($template === null) {
            return null;
        }
        $effective = $exists ? $this->effective($username, $template) : $template;
        if (!$this->users->templateExists($effective)) {
            $this->notices[] = "Template '$effective' of $username does not exist: not applied";
            return null;
        }
        return $template;
    }

    /**
     * The mapped template to apply when the template attribute changes, null when nothing changes. A removed
     * attribute maps to the default template.
     */
    private function templateChange(Token $token, User $user, ?string $attribute, bool $remove): ?string
    {
        if (!$remove && $attribute === null) {
            return null;
        }
        // GET-modify-PUT round trips send back the recorded template name, not the mapped value
        if (!$remove && $attribute === $user->template) {
            return null;
        }
        $resolved = $token->templateFor($remove ? null : $attribute);
        return ($resolved !== null && $resolved !== $user->template) ? $resolved : null;
    }

    /**
     * Renames the mailbox (scim_maps and the SCIM member lists follow through mailbox('edit', 'mailbox_rename')).
     * The old address is kept as alias in a step of its own: if mailcow refuses it (e.g. no aliases left in the
     * domain), the rename stands and a notice is logged.
     */
    private function rename(Token $token, string $old, string $new): void
    {
        // an alias kept by an earlier rename of this mailbox to $new is in the way (renameTarget() allowed it)
        $alias = $this->users->aliasTo($new, $old);
        if ($alias !== null) {
            $this->check($this->legacy->deleteAlias($alias));
        }
        // the user's SCIM groups are locked meanwhile: a concurrent group write of the IdP must not write the old name
        $error = $this->groups->withMemberLocks($old, fn(): ?string => $this->legacy->rename($old, $new));
        if ($error !== null && $alias !== null) {
            // the rename failed: the alias deleted for it comes back, mail to it keeps arriving
            if ($this->legacy->addAlias($new, $old, self::aliasComment($old)) !== null) {
                $this->notices[] = "The alias $new of $old could not be restored after the failed rename";
            }
        }
        $this->check($error);
        if ($token->renameAlias === 1) {
            $error = $this->legacy->addAlias($old, $new, self::aliasComment($new));
            if ($error !== null) {
                $this->notices[] = "Renamed $old to $new, but the old address was not kept as alias: $error";
            }
        }
    }

    /** mailcow limits alias comments to 160 characters */
    private static function aliasComment(string $address): string
    {
        return mb_strimwidth('SCIM rename to ' . $address, 0, 160, '…');
    }

    /** @throws ScimError for a mailcow error; 409 for an existing object (e.g. a concurrent create) */
    private function check(?string $error): void
    {
        if ($error === 'object_exists' || $error === 'is_alias') {
            throw ScimError::uniqueness('mailcow rejected the operation: the object already exists');
        }
        if ($error !== null) {
            throw ScimError::invalidValue('mailcow rejected the operation: ' . $error);
        }
    }

    /**
     * Local part and domain of a userName, normalized like mailbox('add') does (IDN domains in punycode).
     *
     * @return array{0: string, 1: string}
     */
    private static function userName(mixed $value): array
    {
        $value = is_string($value) ? trim($value) : '';
        $at = strrpos($value, '@');
        $local = ($at === false) ? '' : strtolower(substr($value, 0, $at));
        $domain = ($at === false) ? false : idn_to_ascii(strtolower(substr($value, $at + 1)), 0, INTL_IDNA_VARIANT_UTS46);
        if ($local === '' || !is_string($domain) || !filter_var($local . '@' . $domain, FILTER_VALIDATE_EMAIL)) {
            throw ScimError::invalidValue('userName must be a valid email address');
        }
        return [$local, $domain];
    }

    /**
     * The new userName of a request, null when it does not change
     *
     * @throws ScimError mutability unless the token allows renames within the domain, uniqueness for a taken address
     */
    private function renameTarget(Token $token, User $user, mixed $value): ?string
    {
        [$local, $domain] = self::userName($value);
        $username = $local . '@' . $domain;
        if ($username === $user->username) {
            return null;
        }
        if ($token->allowRename !== 1) {
            throw ScimError::mutability('userName is immutable: renaming mailboxes is not enabled for this SCIM token');
        }
        // mailbox('edit', 'mailbox_rename') only renames active or login-disabled mailboxes: checked before anything is written
        if ($user->active !== 1 && $user->active !== 2) {
            throw ScimError::invalidValue('An inactive mailbox cannot be renamed');
        }
        if ($domain !== $user->domain) {
            throw ScimError::mutability('userName can only change within its domain: mailcow cannot move mailboxes between domains');
        }
        if ($this->users->addressTaken($username, $user->username)) {
            throw ScimError::uniqueness("The address '$username' is already taken by a mailbox or alias");
        }
        return $username;
    }

    /** Name of a User body: displayName, else name.formatted, else givenName + familyName, else $default */
    private static function name(array $body, string $default): string
    {
        $name = is_array($body['name'] ?? null) ? $body['name'] : [];
        foreach ([$body['displayname'] ?? null, $name['formatted'] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return self::checkLength(trim($value), 'displayName');
            }
        }
        $full = trim(self::text($name['givenname'] ?? '', 'givenName') . ' ' . self::text($name['familyname'] ?? '', 'familyName'));
        return ($full !== '') ? self::checkLength($full, 'name') : $default;
    }

    /** @return array{0: ?string, 1: ?string} givenName and familyName of a User body, null when it has none */
    private static function nameParts(array $body): array
    {
        $name = is_array($body['name'] ?? null) ? $body['name'] : [];
        $given = isset($name['givenname']) ? self::checkLength(self::text($name['givenname'], 'givenName'), 'givenName') : '';
        $family = isset($name['familyname']) ? self::checkLength(self::text($name['familyname'], 'familyName'), 'familyName') : '';
        return self::partsOrNull($given, $family);
    }

    /** Both parts empty: none stored, the parts are derived from the name */
    private static function partsOrNull(string $given, string $family): array
    {
        return ($given === '' && $family === '') ? [null, null] : [$given, $family];
    }

    /** @throws ScimError for values longer than their column (255) */
    private static function checkLength(string $value, string $attribute): string
    {
        if (mb_strlen($value) > 255) {
            throw ScimError::invalidValue("'$attribute' must not exceed 255 characters");
        }
        return $value;
    }

    /** externalId of a User body; empty = none (RFC 7643 2.5) */
    private static function externalId(array $body): ?string
    {
        $value = $body['externalid'] ?? null;
        $value = ($value === null) ? '' : self::checkLength(self::text($value, 'externalId'), 'externalId');
        return ($value === '') ? null : $value;
    }

    /** The extension attribute `template` of a User body */
    private static function templateAttribute(array $body): ?string
    {
        $ext = $body[strtolower(Schema::EXT)] ?? null;
        return is_array($ext) ? self::templateValue($ext) : null;
    }

    private static function templateValue(?array $ext): ?string
    {
        $value = $ext['template'] ?? null;
        return (is_string($value) && trim($value) !== '') ? trim($value) : null;
    }

    /** emails are derived from userName: only a modification that keeps the address is accepted */
    private static function checkEmails(string $username, string $op, mixed $value, ?string $sub): void
    {
        if ($sub !== null && $sub !== 'value') {
            return; // type/primary of the single address, nothing to store
        }
        $items = (is_array($value) && array_is_list($value)) ? $value : [$value];
        foreach ($items as $item) {
            $address = is_array($item) ? ($item['value'] ?? null) : $item;
            if ($address === null && is_array($item)) {
                continue;
            }
            if (!is_string($address) || strcasecmp(trim($address), $username) !== 0) {
                throw ScimError::mutability("'emails' is read-only (derived from userName): change userName instead");
            }
        }
    }

    /** Scalar as trimmed string */
    private static function text(mixed $value, string $attribute): string
    {
        if (!is_scalar($value)) {
            throw ScimError::invalidValue("'$attribute' must be a string");
        }
        return trim((string)$value);
    }

    /** @throws ScimError unless $value is a JSON object */
    private static function object(mixed $value, string $what): array
    {
        if (is_array($value) && ($value === [] || !array_is_list($value))) {
            return $value;
        }
        throw ScimError::invalidValue("$what must be a complex object");
    }

    /** {"givenname": x} => {"name.givenname": x}: the stored sub-attributes of `name` as paths, others are ignored */
    private static function prefixed(array $name): array
    {
        $out = [];
        foreach (['formatted', 'givenname', 'familyname'] as $key) {
            if (array_key_exists($key, $name)) {
                $out['name.' . $key] = $name[$key];
            }
        }
        return $out;
    }
}
