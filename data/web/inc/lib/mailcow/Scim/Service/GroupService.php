<?php

declare(strict_types=1);

namespace Mailcow\Scim\Service;

use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Service\GroupService as MailboxGroupService;
use Mailcow\MailboxGroup\Service\IdpSyncService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Scim\Legacy\LegacyMailboxes;
use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\Group;
use Mailcow\Scim\Model\PatchOp;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Repository\GroupRepository;
use Mailcow\Scim\Repository\UserRepository;
use Mailcow\Scim\ScimError;
use PDOException;

/**
 * SCIM Groups (RFC 7643 4.2) on mailbox groups, like the Keycloak/LDAP group sync:
 *
 * - A group is created in the token's group domain; name and members follow the IdP. Members are SCIM users,
 *   stored as IdP members (read-only in mailcow); manual members stay, but SCIM shows only the members the IdP
 *   set (Group). Address, send-as, shares and template are maintained in mailcow.
 * - DELETE removes the IdP members and the group from SCIM (404 afterwards, RFC 7644 3.6); the mailbox group and
 *   its manual members stay, like a group that disappears from the IdP. Like the Keycloak/LDAP sync, which links an
 *   orphaned group only to the same IdP group again, a POST with its name links it again (with a new id) only for
 *   the same group (same token and externalId); another one gets 409 until an admin renames or deletes it.
 * - Groups cannot be nested, and any other mailbox group with the same name is not taken over (409).
 */
class GroupService
{
    /** $legacy, $templates: the mapped template comes back when a user leaves a group (reapplyTemplates()) */
    public function __construct(private GroupRepository $groups, private UserRepository $users,
        private MailboxGroupService $mailboxGroups, private IdpSyncService $idp, private ?LegacyMailboxes $legacy = null,
        private ?TemplateService $templates = null)
    {
    }

    /**
     * @param ?Filter $filter narrows the groups by SQL; the caller still applies it
     * @return Group[]
     */
    public function list(Token $token, ?Filter $filter = null, int $offset = 0, ?int $limit = null): array
    {
        return self::scoped($token, $this->groups->all($token->domainRestriction, $filter, $offset, $limit));
    }

    /** @return array{0: Group[], 1: int} one page of the groups and the number of all */
    public function page(Token $token, int $offset, int $limit): array
    {
        return [self::scoped($token, $this->groups->all($token->domainRestriction, null, $offset, $limit)), $this->groups->count($token->domainRestriction)];
    }

    /** @throws ScimError 404, also for groups outside the token's domain */
    public function get(Token $token, string $id): Group
    {
        return self::scoped($token, [$this->groups->find($id, $token->domainRestriction) ?? throw ScimError::notFound('Group not found')])[0];
    }

    /**
     * externalId is scoped to the provisioning client (RFC 7643 3.1): only the token that set it sees it
     *
     * @param Group[] $groups
     * @return Group[]
     */
    private static function scoped(Token $token, array $groups): array
    {
        foreach ($groups as $group) {
            if ($group->tokenId !== $token->id) {
                $group->externalId = null;
            }
        }
        return $groups;
    }

    /** @throws ScimError */
    public function create(Token $token, array $body): Group
    {
        Schema::requireSchema($body, Schema::GROUP);
        $domain = $token->groupDomain() ?? throw ScimError::invalidValue('This SCIM token has no group domain: set one in mailcow to provision groups');
        $name = self::displayName($body['displayname'] ?? null);
        $externalId = self::externalId($body['externalid'] ?? null);
        $members = $this->members($token, $body['members'] ?? null);
        $id = Schema::uuid();
        // a group deleted through SCIM comes back for the same group (token, externalId), also under a new name,
        // which it then gets; another group with the name of a deleted one is a conflict until an admin renames or
        // deletes it
        $deleted = ($externalId === null) ? null : $this->groups->deletedGroupOf($token->id, $externalId, $domain);
        if ($deleted === null) {
            $tombstone = $this->groups->deletedGroup($domain, $name);
            if ($tombstone !== null) {
                if ($tombstone['external_id'] === null || $tombstone['token_id'] !== $token->id || $tombstone['external_id'] !== $externalId) {
                    throw ScimError::uniqueness("A group '$name' deleted through SCIM exists in $domain: it is linked again only for the same "
                        . 'group (externalId); rename or delete it in mailcow to create another one');
                }
                $deleted = $tombstone['id'];
            }
        }
        if ($deleted !== null) {
            $this->checkUnique($token, $domain, null, $externalId, $deleted);
            return $this->groups->locked($deleted, function () use ($token, $deleted, $id, $name, $externalId, $members): Group {
                // the row is taken over only while it is a tombstone: of two concurrent POSTs one wins
                if (!$this->groups->relink($deleted, $id, $token->id)) {
                    throw ScimError::uniqueness("A group '$name' already exists");
                }
                return $this->save($token, $this->get($token, $id), $name, $externalId, $members);
            });
        }
        $this->checkUnique($token, $domain, $name, $externalId, 0);
        try {
            $groupId = (int)$this->mailboxGroups->create(['domain' => $domain, 'name' => $name])->id;
        } catch (UserError $e) {
            throw self::rejected($e);
        } catch (PDOException $e) {
            // a concurrent POST created the name first (unique key domain + name)
            throw self::duplicate($e) ? ScimError::uniqueness("A group '$name' already exists") : $e;
        }
        // no transaction around the MailboxGroup writes, they commit and then run their effects: if linking or the
        // members fail, the new group goes again, so no group the IdP never got an id for blocks a retry
        try {
            $this->groups->link($id, $groupId, $externalId, $token->id);
            if ($members !== []) {
                $this->groups->locked($groupId, fn() => $this->writeMembers($groupId, array_values($members)));
            }
        } catch (\Throwable $e) {
            try {
                $this->mailboxGroups->delete($groupId);
            } catch (\Throwable) {
                // the original error is the one to report
            }
            throw $e;
        }
        return $this->get($token, $id);
    }

    /**
     * PUT: replaces name, externalId and members
     *
     * @param ?callable(Group): void $precondition runs on the group read under its lock, before anything is written
     */
    public function replace(Token $token, string $id, array $body, ?callable $precondition = null): Group
    {
        Schema::requireSchema($body, Schema::GROUP);
        $name = self::displayName($body['displayname'] ?? null);
        $externalId = self::externalId($body['externalid'] ?? null);
        $members = $this->members($token, $body['members'] ?? null);
        return $this->groups->locked($id, function () use ($token, $id, $name, $externalId, $members, $precondition): Group {
            $group = $this->get($token, $id);
            if ($precondition !== null) {
                $precondition($group);
            }
            return $this->save($token, $group, $name, $externalId, $members);
        });
    }

    /**
     * PATCH: all operations are applied to a copy and validated before anything is written. Besides RFC 7644,
     * `remove` of `members` with a value list removes only those members (default format of Entra ID).
     *
     * @param ?callable(Group): void $precondition runs on the group read under its lock, before anything is written
     */
    public function patch(Token $token, string $id, array $body, ?callable $precondition = null): Group
    {
        $operations = PatchOp::parse($body, 'Group');
        // the group is read under its lock: concurrent changes of its members apply one after the other
        return $this->groups->locked($id, function () use ($token, $id, $operations, $precondition): Group {
            $group = $this->get($token, $id);
            if ($precondition !== null) {
                $precondition($group);
            }
            $state = ['id' => $group->scimId, 'name' => $group->name, 'externalId' => $group->externalId, 'members' => $group->members];
            foreach ($operations as $op) {
                if ($op->path === null) {
                    $this->patchObject($token, $state, $op->op, $op->value);
                } else {
                    $this->apply($token, $state, $op->op, $op->path, $op->value, $op->hasValue);
                }
            }
            return $this->save($token, $group, $state['name'], $state['externalId'], $state['members']);
        });
    }

    /**
     * DELETE: see the class description
     *
     * @param ?callable(Group): void $precondition runs on the group read under its lock, before anything is written
     */
    public function delete(Token $token, string $id, ?callable $precondition = null): void
    {
        $this->groups->locked($id, function () use ($token, $id, $precondition): void {
            $group = $this->get($token, $id);
            if ($precondition !== null) {
                $precondition($group);
            }
            $this->idp->syncGroupMembers($group->groupId, []);
            $this->groups->unlink($group->groupId);
        });
    }

    /**
     * The SCIM groups visible to the token whose members include the users (User `groups`, RFC 7643 4.1.2)
     *
     * @param ?string[] $usernames null: all
     * @return array<string, array<string, string>> username => SCIM id => displayName
     */
    public function membershipsOf(Token $token, ?array $usernames): array
    {
        return $this->groups->membershipsOf($usernames, $token->domainRestriction);
    }

    /**
     * Follows a mailbox renamed in mailcow (mailbox('edit', 'mailbox_rename')) in the member lists the IdP set.
     * Runs after the MailboxGroup hook, so the member rows already carry the new name.
     */
    public function onMailboxRenamed(string $old, string $new): void
    {
        // No advisory lock here, it would wait on the rows the rename transaction holds. Renames through SCIM
        // hold the group locks (UserService::rename); for a rename in the mailcow UI racing a SCIM group write of the
        // same group, that write still uses the old name and drops the renamed mailbox from the group until the IdP
        // sends the membership again. Taking the locks in the legacy rename before its transaction would close it
        foreach ($this->groups->groupsAssigning($old, $new, null, true) as $groupId => $assigned) {
            $this->groups->setMembers($groupId, array_map(fn(string $username): string => ($username === $old) ? $new : $username, $assigned));
        }
    }

    /**
     * Removes a mailbox from all SCIM groups (user deleted through SCIM, or handed back to another authsource).
     * Each group is re-read under its lock, and its list is reduced to the current members: entries of mailboxes
     * removed in mailcow in the meantime must not be added again.
     */
    public function removeMember(string $username): void
    {
        // all locks first: a busy group fails the call before any group changed
        $groupIds = array_keys($this->groups->groupsAssigning($username));
        $this->groups->lockedAll($groupIds, function () use ($groupIds, $username): void {
            foreach ($groupIds as $groupId) {
                $assigned = $this->groups->groupsAssigning($username, null, $groupId)[$groupId] ?? null;
                if ($assigned !== null) {
                    $current = $this->groups->memberUsernames($groupId);
                    $this->writeMembers($groupId, array_diff(array_intersect($assigned, $current), [$username]));
                }
            }
        });
    }

    /** Runs $work holding the locks of the SCIM groups of the mailbox (a rename through SCIM) */
    public function withMemberLocks(string $username, callable $work): mixed
    {
        return $this->groups->lockedAll(array_keys($this->groups->groupsAssigning($username)), $work);
    }

    /** The IdP members of a group: in mailcow (manual members stay) and as the list SCIM shows */
    private function writeMembers(int $groupId, array $usernames): void
    {
        $usernames = array_values(array_unique($usernames));
        $result = $this->idp->syncGroupMembers($groupId, $usernames);
        // a username that is no mailbox (anymore, e.g. renamed meanwhile) is skipped there and not kept in the list
        $this->groups->setMembers($groupId, array_values(array_diff($usernames, $result['skipped'] ?? [])));
        $this->reapplyTemplates($result['removed'] ?? []);
    }

    /**
     * SCIM users that left a group get the template they should have again: a remaining group template, else the
     * one mapped from their IdP attribute. The MailboxGroup module keeps a member's settings when it leaves a group;
     * the Keycloak/LDAP sync converges such mailboxes on its next run. SCIM has no runs, so this does it instead.
     * Failures do not fail the membership change (like the group template warnings of MailboxGroup).
     *
     * @param string[] $usernames
     */
    private function reapplyTemplates(array $usernames): void
    {
        if ($usernames === [] || $this->legacy === null) {
            return;
        }
        foreach ($this->users->templateStates($usernames) as $username => $state) {
            $template = $this->templates?->templateForLogin($username, $state['mapped']) ?? $state['mapped'];
            if ($template !== null && $template !== $state['applied'] && $this->users->templateExists($template)) {
                $this->legacy->applyTemplate($username, $state['name'], $template);
            }
        }
    }

    /** Path-less operation: an object of attributes, also nested in the core schema URN key */
    private function patchObject(Token $token, array &$state, string $op, array $value): void
    {
        foreach ($value as $key => $v) {
            if ($key === strtolower(Schema::GROUP) && is_array($v)) {
                $this->patchObject($token, $state, $op, $v);
                continue;
            }
            $attr = Schema::attribute('Group', (string)$key) ?? throw new ScimError(400, "Unknown attribute '$key'", 'invalidPath');
            // Okta and Authentik repeat id and schemas in path-less replaces; changes are not applied
            if (in_array($attr['parts'], [['schemas'], ['meta']], true) || ($attr['parts'] === ['id'] && $v === $state['id'])) {
                continue;
            }
            $this->apply($token, $state, $op, ['attr' => $attr, 'filter' => null, 'sub' => null], $v, true);
        }
    }

    /** One operation on a parsed path */
    private function apply(Token $token, array &$state, string $op, array $path, mixed $value, bool $hasValue): void
    {
        $remove = ($op === 'remove');
        switch ($path['attr']['parts'][0]) {
            case 'displayname':
                if ($remove || $value === null) {
                    // RFC 7644 3.5.2.2: a required attribute removed or unassigned (null, RFC 7643 2.5)
                    throw ScimError::mutability("'displayName' is required");
                }
                $state['name'] = self::displayName($value);
                return;
            case 'externalid':
                $state['externalId'] = $remove ? null : self::externalId($value);
                return;
            case 'members':
                if (count($path['attr']['parts']) > 1 || $path['sub'] !== null) {
                    throw ScimError::mutability('Sub-attributes of members are immutable');
                }
                $filter = $path['filter'];
                if ($filter !== null) {
                    $matched = array_filter($state['members'], fn(string $username, string|int $id): bool =>
                        $filter->matches(['value' => (string)$id, 'display' => $username, 'type' => 'User']), ARRAY_FILTER_USE_BOTH);
                    // RFC 7644 3.5.2.3; a remove without match is a no-op (3.5.2.2 example)
                    if ($matched === [] && $op === 'replace') {
                        throw new ScimError(400, 'No member matches the filter', 'noTarget');
                    }
                    // remove and replace drop the matched members, replace and add add the value then
                    if ($op !== 'add') {
                        $state['members'] = array_diff_key($state['members'], $matched);
                    }
                    if ($remove) {
                        return;
                    }
                } elseif ($op === 'replace' || ($remove && !$hasValue)) {
                    $state['members'] = [];
                } elseif ($remove) {
                    $state['members'] = array_diff_key($state['members'], array_flip(self::memberIds($value)));
                    return;
                }
                if (!$remove) {
                    $state['members'] = $this->members($token, $value) + $state['members'];
                }
                return;
            default:
                // id, schemas, meta
                throw ScimError::mutability("'{$path['attr']['parts'][0]}' is read-only");
        }
    }

    /**
     * Writes name, externalId and members after the checks.
     *
     * @param array<string, string> $members SCIM id => username of the members visible to the token
     */
    private function save(Token $token, Group $group, string $name, ?string $externalId, array $members): Group
    {
        $this->checkUnique($token, $group->domain, ($name !== $group->name) ? $name : null, ($externalId !== $group->externalId) ? $externalId : null, $group->groupId);
        if ($name !== $group->name) {
            try {
                $this->idp->renameFromIdp($group->groupId, $name);
            } catch (UserError $e) {
                throw self::rejected($e);
            } catch (PDOException $e) {
                // another group got the name meanwhile
                throw self::duplicate($e) ? ScimError::uniqueness("A group '$name' already exists in {$group->domain}") : $e;
            }
        }
        if ($externalId !== $group->externalId) {
            // the token that writes externalId owns it; $group->externalId is null when another token set it
            $this->groups->setExternalId($group->groupId, $externalId, $token->id);
        }
        ksort($members);
        $current = $group->members;
        ksort($current);
        if ($members !== $current) {
            // members of other domains, which a restricted token cannot see, stay
            $hidden = ($token->domainRestriction === null) ? []
                : array_filter($group->assigned, fn(string $username): bool => !str_ends_with($username, '@' . $token->domainRestriction));
            $this->writeMembers($group->groupId, array_merge($hidden, array_values($members)));
        }
        return $this->get($token, $group->scimId);
    }

    /** @throws ScimError uniqueness ($name / $externalId null: not checked) */
    private function checkUnique(Token $token, string $domain, ?string $name, ?string $externalId, int $groupId): void
    {
        if ($name !== null && $this->groups->nameTaken($domain, $name, $groupId)) {
            throw ScimError::uniqueness("A group '$name' already exists in $domain");
        }
        if ($externalId !== null && $this->groups->externalIdTaken($externalId, $token->id, $groupId)) {
            throw ScimError::uniqueness("externalId '$externalId' belongs to another group");
        }
    }

    /**
     * Members of a request: users of this provider, visible to the token.
     *
     * @return array<string, string> SCIM id => username
     * @throws ScimError invalidValue
     */
    private function members(Token $token, mixed $items): array
    {
        $items = self::memberItems($items);
        foreach ($items as $item) {
            if (isset($item['type']) && (!is_string($item['type']) || strcasecmp($item['type'], 'User') !== 0)) {
                throw ScimError::invalidValue('Groups cannot be nested, members must be users');
            }
        }
        // one query for all members instead of one per member
        $ids = array_map(fn(array $item): string => (string)$item['value'], $items);
        $users = $this->users->findMany($ids, $token->domainRestriction);
        $members = [];
        foreach ($ids as $id) {
            $user = $users[$id] ?? throw ScimError::invalidValue("Member '$id' is not a user of this provider");
            $members[$user->scimId] = $user->username;
        }
        return $members;
    }

    /** @return string[] values of member items (for removal; unknown ids are no error) */
    private static function memberIds(mixed $items): array
    {
        return array_map(fn(array $item): string => (string)($item['value'] ?? ''), self::memberItems($items));
    }

    /** @return array[] member objects of a value: a list, a single object or null */
    private static function memberItems(mixed $items): array
    {
        if ($items === null) {
            return [];
        }
        $items = (is_array($items) && !array_is_list($items)) ? [$items] : $items;
        if (!is_array($items) || array_filter($items, fn(mixed $item): bool => !is_array($item) || !is_scalar($item['value'] ?? null))) {
            throw ScimError::invalidValue("'members' must be a list of objects with a value");
        }
        return $items;
    }

    private static function displayName(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '' || mb_strlen($name) > 100) {
            throw ScimError::invalidValue("'displayName' is required, at most 100 characters");
        }
        return $name;
    }

    /** externalId of a request; empty = none (RFC 7643 2.5) */
    private static function externalId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw ScimError::invalidValue("'externalId' must be a string");
        }
        $value = trim((string)$value);
        if (mb_strlen($value) > 255) {
            throw ScimError::invalidValue("'externalId' must not exceed 255 characters");
        }
        return ($value === '') ? null : $value;
    }

    /** A duplicate key (MySQL error 1062), not any integrity error of SQLSTATE 23000 */
    private static function duplicate(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /** A rejected mailbox group change as SCIM error */
    private static function rejected(UserError $e): ScimError
    {
        return ($e->key === 'object_exists') ? ScimError::uniqueness('A group with this name already exists')
            : ScimError::invalidValue('mailcow rejected the group: ' . $e->key);
    }
}
