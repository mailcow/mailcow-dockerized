<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;

/**
 * Use cases and rules for mailbox groups.
 *
 * Read: admins all groups (also read-only API keys); domain admins with the `mailbox_groups` ACL the groups of
 *       their domains; users only their own memberships (membershipsOf()).
 * Edit: admins; domain admins with the ACL for groups of their domains, as long as every send-as grant of the
 *       group is inside their domains. Template, priority and IdP link are set by admins only.
 * Members: regular mailboxes the editor may access. IdP members and members outside the editor's scope are kept
 *       when the member list is replaced; IdP members can only be removed by the IdP sync.
 */
class GroupService
{
    /** Postfix expands at most this many recipients per address (virtual_alias_expansion_limit) */
    private const MAX_RECIPIENTS = 1000;

    public function __construct(private GroupRepository $repo, private LookupRepository $lookup, private TemplateService $templates,
        private ChangeEffects $effects, private SharingService $sharing, private Context $context)
    {
    }

    public function canRead(): bool
    {
        return $this->context->hasAdminRights()
            || ($this->context->isDomainAdmin() && $this->context->hasAcl('mailbox_groups'));
    }

    /** @throws AccessDenied unless the caller may edit groups at all */
    public function requireEditor(): void
    {
        if (!$this->canRead() || $this->context->isReadOnlyApi()) {
            throw new AccessDenied();
        }
    }

    public function get(int $id): ?Group
    {
        return $this->canRead() ? $this->repo->findVisible($id, $this->context) : null;
    }

    /** @return int[] */
    public function visibleIds(array $domains = []): array
    {
        return $this->canRead() ? $this->repo->visibleIds($this->context, $domains) : [];
    }

    public function canEdit(Group $group): bool
    {
        if ($this->context->isReadOnlyApi() || !$this->canRead()) {
            return false;
        }
        if ($this->context->hasAdminRights()) {
            return true;
        }
        if (!$this->context->canAccessDomain($group->domain)) {
            return false;
        }
        // Grants on foreign domains or mailboxes make the group read-only for a domain admin
        foreach ($group->sendAs as $address) {
            if (!$this->context->canAccessDomain(self::domainOf($address))) {
                return false;
            }
        }
        foreach (array_merge($group->imapGrants, $group->sogoGrants) as $grant) {
            if (!$this->context->canAccessMailbox((string)$grant['owner'])) {
                return false;
            }
        }
        return true;
    }

    public function toArray(Group $group): array
    {
        return $group->toArray($this->canEdit($group));
    }

    /** @throws UserError */
    public function create(array $input): Group
    {
        $this->requireEditor();
        $group = new Group();
        $group->domain = $this->domainFor($input['domain'] ?? null);
        $this->apply($group, $input, null);
        $snapshot = $this->effects->snapshot($group->usernames());
        $sogo = $this->repo->insert($group);
        $group->warnings = array_merge($this->sizeWarnings($group), $this->effects->apply($sogo, $snapshot),
            $this->sharing->applyImap((int)$group->id), $this->sharing->applySogo((int)$group->id));
        return $group;
    }

    /**
     * Updates a group; attributes missing in $input keep their value. `reapply_template` (admin) applies the
     * effective templates to all members again.
     *
     * @throws UserError
     */
    public function update(int $id, array $input): Group
    {
        $this->requireEditor();
        $current = $this->editable($id);
        $group = clone $current;
        $this->apply($group, $input, $current);
        $snapshot = $this->effects->snapshot(array_merge($current->usernames(), $group->usernames()));
        $sogo = $this->repo->update($group);
        $group->warnings = array_merge($this->sizeWarnings($group), $this->effects->apply($sogo, $snapshot),
            $this->sharing->applyImap((int)$group->id), $this->sharing->applySogo((int)$group->id));
        if ($this->context->hasAdminRights() && self::flag($input['reapply_template'] ?? 0) === 1) {
            $group->warnings = array_merge($group->warnings, $this->templates->applyTo($group->usernames()));
        }
        return $group;
    }

    /**
     * Deletes a group and returns it. Members keep their settings; another group's template may now apply.
     *
     * @throws UserError
     */
    public function delete(int $id): Group
    {
        $this->requireEditor();
        $group = $this->editable($id);
        $snapshot = $this->effects->snapshot($group->usernames());
        $this->sharing->removeImap($group);
        $sogo = $this->repo->delete($id);
        $group->warnings = $this->effects->apply($sogo, $snapshot);
        return $group;
    }

    /**
     * Groups of a mailbox, its effective group template and the addresses it may send as through groups.
     * For the mailbox itself and for admins/domain admins with access to it.
     */
    public function membershipsOf(string $username): array
    {
        $username = strtolower(trim($username));
        $own = ($this->context->isUser() && $this->context->username === $username);
        if (!$own && !($this->canRead() && $this->context->canAccessMailbox($username))) {
            return [];
        }
        return [
            'groups' => $this->lookup->membershipsOf($username),
            'template' => $this->templates->effective([$username])[$username] ?? null,
            'send_as' => $this->lookup->sendAsOf($username),
        ];
    }

    /** @throws AccessDenied when the caller cannot see or edit the group */
    private function editable(int $id): Group
    {
        $group = $this->get($id);
        if ($group === null || !$this->canEdit($group)) {
            throw new AccessDenied();
        }
        return $group;
    }

    /** @throws UserError domain_not_found, access_denied */
    private function domainFor(mixed $value): string
    {
        $domain = Input::text($value);
        $domain = ($domain === null || $domain === '') ? '' : (string)idn_to_ascii(strtolower($domain), 0, INTL_IDNA_VARIANT_UTS46);
        if ($domain === '' || !$this->lookup->isDomain($domain)) {
            throw new UserError('domain_not_found', [htmlspecialchars((string)Input::text($value))]);
        }
        if (!$this->context->hasAdminRights() && !$this->context->canAccessDomain($domain)) {
            throw new AccessDenied();
        }
        return $domain;
    }

    /**
     * Add/edit rules shared by create() and update(): attributes missing in $input keep the value of $group,
     * a new group starts with the model defaults. Everything is validated before anything is written.
     *
     * @throws UserError
     */
    private function apply(Group $group, array $input, ?Group $current): void
    {
        $name = isset($input['name']) ? Input::text($input['name']) : $group->name;
        if ($name === null || $name === '' || mb_strlen($name) > 100) {
            throw new UserError('group_name_invalid');
        }
        $description = isset($input['description']) ? Input::text($input['description']) : $group->description;
        if ($description === null || mb_strlen($description) > 255) {
            throw new UserError('group_description_invalid');
        }
        if ($this->repo->nameExists($group->domain, $name, $group->id)) {
            throw new UserError('object_exists', [htmlspecialchars($name)]);
        }
        $group->name = $name;
        $group->description = $description;
        foreach (['sender_allowed' => 'senderAllowed', 'sogo_visible' => 'sogoVisible', 'internal' => 'internal', 'active' => 'active'] as $key => $property) {
            if (isset($input[$key])) {
                $group->$property = self::flag($input[$key]);
            }
        }
        $this->applyAddress($group, $input, $current);
        $this->applyMembers($group, $input);
        $this->applySendAs($group, $input);
        $this->applyGrants($group, $input);
        if ($this->context->hasAdminRights()) {
            $this->applyAdminFields($group, $input);
        }
    }

    /** @throws UserError */
    private function applyAddress(Group $group, array $input, ?Group $current): void
    {
        if (array_key_exists('address', $input)) {
            $value = Input::text($input['address']);
        } elseif (array_key_exists('local_part', $input)) {
            $local = Input::text($input['local_part']);
            $value = ($local === null || $local === '') ? $local : $local . '@' . $group->domain;
        } else {
            return;
        }
        if ($value === '') {
            $group->address = null;
            return;
        }
        $address = ($value === null) ? null : self::normalizeAddress($value);
        if ($address === null || self::domainOf($address) !== $group->domain) {
            throw new UserError('group_address_invalid', [htmlspecialchars((string)$value)]);
        }
        $group->address = $address;
        if ($address === $current?->address) {
            return;
        }
        if ($this->lookup->addressTaken($address, $group->id)) {
            throw new UserError('is_alias_or_mailbox', [htmlspecialchars($address)]);
        }
        // A group with an address counts like an alias of the domain
        if ($current?->address === null && $this->lookup->aliasesLeft($group->domain) < 1) {
            throw new UserError('max_alias_exceeded');
        }
    }

    /**
     * `members` replaces the manual members the caller may access, `add_members` / `remove_members` change them.
     *
     * @throws UserError group_member_invalid, group_member_idp, access_denied
     */
    private function applyMembers(Group $group, array $input): void
    {
        $members = $group->members;
        $add = [];
        if (array_key_exists('members', $input)) {
            $add = self::cleanList($input['members']);
            foreach ($members as $username => $source) {
                if ($source === Group::SOURCE_MANUAL && !in_array((string)$username, $add, true) && $this->context->canAccessMailbox((string)$username)) {
                    unset($members[$username]);
                }
            }
        }
        $add = array_merge($add, self::cleanList($input['add_members'] ?? []));
        foreach (self::cleanList($input['remove_members'] ?? []) as $username) {
            if (!isset($members[$username])) {
                continue;
            }
            if ($members[$username] === Group::SOURCE_IDP) {
                throw new UserError('group_member_idp', [htmlspecialchars($username)]);
            }
            if (!$this->context->canAccessMailbox($username)) {
                throw new AccessDenied();
            }
            unset($members[$username]);
        }
        $new = array_values(array_diff(array_unique($add), array_map('strval', array_keys($members))));
        $regular = $this->lookup->regularMailboxes($new);
        foreach ($new as $username) {
            if (!in_array($username, $regular, true)) {
                throw new UserError('group_member_invalid', [htmlspecialchars($username)]);
            }
            if (!$this->context->canAccessMailbox($username)) {
                throw new AccessDenied();
            }
            $members[$username] = Group::SOURCE_MANUAL;
        }
        ksort($members);
        $group->members = $members;
    }

    /**
     * Addresses (mailboxes or aliases) and @domain the members may send as; the editor needs access to their domains.
     *
     * @throws UserError group_send_as_invalid, access_denied
     */
    private function applySendAs(Group $group, array $input): void
    {
        if (!array_key_exists('send_as', $input)) {
            return;
        }
        $sendAs = [];
        foreach (self::cleanList($input['send_as']) as $value) {
            if (str_starts_with($value, '@')) {
                $domain = (string)idn_to_ascii(substr($value, 1), 0, INTL_IDNA_VARIANT_UTS46);
                $address = '@' . $domain;
                $valid = ($domain !== '' && $this->lookup->isDomainOrAliasDomain($domain));
            } else {
                $address = self::normalizeAddress($value);
                // The group's own address is covered by sender_allowed
                $valid = ($address !== null && $address !== $group->address && $this->lookup->aliasExists($address));
            }
            if (!$valid) {
                throw new UserError('group_send_as_invalid', [htmlspecialchars($value)]);
            }
            if (!$this->context->hasAdminRights() && !$this->context->canAccessDomain(self::domainOf((string)$address))) {
                throw new AccessDenied();
            }
            $sendAs[] = (string)$address;
        }
        $sendAs = array_values(array_unique($sendAs));
        sort($sendAs);
        $group->sendAs = $sendAs;
    }

    /**
     * `imap_grants` [{owner, rights, subscribe}] and `sogo_grants` [{owner, type, folder, access, subscribe}] replace
     * the grants; the editor needs access to every owner mailbox.
     *
     * @throws UserError group_grant_invalid, access_denied
     */
    private function applyGrants(Group $group, array $input): void
    {
        if (array_key_exists('imap_grants', $input)) {
            $grants = [];
            foreach (self::grantList($input['imap_grants']) as $grant) {
                $owner = $this->grantOwner($grant);
                $rights = Input::text($grant['rights'] ?? 'read');
                if (!isset(SharingService::IMAP_RIGHTS[$rights])) {
                    throw new UserError('group_grant_invalid', [htmlspecialchars($owner)]);
                }
                $grants[$owner] = ['owner' => $owner, 'rights' => $rights, 'subscribe' => self::flag($grant['subscribe'] ?? 0)];
            }
            ksort($grants);
            $group->imapGrants = array_values($grants);
        }
        if (array_key_exists('sogo_grants', $input)) {
            $grants = [];
            foreach (self::grantList($input['sogo_grants']) as $grant) {
                $owner = $this->grantOwner($grant);
                $type = Input::text($grant['type'] ?? 'Calendar');
                $folder = Input::text($grant['folder'] ?? '') ?: 'personal';
                $access = Input::text($grant['access'] ?? 'read');
                if (!isset(GroupRepository::SOGO_ROLES[$type][$access]) || !preg_match('/^[A-Za-z0-9._-]{1,100}$/', $folder)) {
                    throw new UserError('group_grant_invalid', [htmlspecialchars($owner)]);
                }
                $grants[$owner . '|' . $type . '|' . $folder] = ['owner' => $owner, 'type' => $type, 'folder' => $folder, 'access' => $access,
                    'subscribe' => self::flag($grant['subscribe'] ?? 0)];
            }
            ksort($grants);
            $group->sogoGrants = array_values($grants);
        }
    }

    /** @throws UserError group_grant_invalid, access_denied */
    private function grantOwner(array $grant): string
    {
        $owner = strtolower((string)Input::text($grant['owner'] ?? ''));
        if ($owner === '' || empty($this->lookup->regularMailboxes([$owner]))) {
            throw new UserError('group_grant_invalid', [htmlspecialchars($owner)]);
        }
        if (!$this->context->canAccessMailbox($owner)) {
            throw new AccessDenied();
        }
        return $owner;
    }

    /**
     * Grant objects of a JSON body or of a form field (JSON string)
     *
     * @throws UserError group_grant_invalid
     */
    private static function grantList(mixed $value): array
    {
        if (is_string($value)) {
            $value = ($value === '') ? [] : json_decode($value, true);
        }
        if (!is_array($value)) {
            throw new UserError('group_grant_invalid', ['']);
        }
        foreach ($value as $grant) {
            if (!is_array($grant)) {
                throw new UserError('group_grant_invalid', ['']);
            }
        }
        return array_values($value);
    }

    /** @throws UserError template_id_invalid, group_priority_invalid */
    private function applyAdminFields(Group $group, array $input): void
    {
        if (array_key_exists('template_id', $input)) {
            $id = $input['template_id'];
            if ($id === null || $id === '' || $id === '0' || $id === 0) {
                $group->templateId = null;
                $group->template = null;
            } else {
                $templateId = Input::intInRange($id, 1, PHP_INT_MAX);
                $name = ($templateId === null) ? null : $this->lookup->templateName($templateId);
                if ($name === null) {
                    throw new UserError('template_id_invalid', [htmlspecialchars((string)Input::text($id))]);
                }
                $group->templateId = $templateId;
                $group->template = $name;
            }
        }
        if (isset($input['priority'])) {
            $priority = Input::intInRange($input['priority'], -1000000, 1000000);
            if ($priority === null) {
                throw new UserError('group_priority_invalid');
            }
            $group->priority = $priority;
        }
        if (array_key_exists('idp_ref', $input)) {
            $ref = Input::text($input['idp_ref']);
            if ($ref === null || mb_strlen($ref) > 255) {
                throw new AccessDenied();
            }
            $group->idpRef = ($ref === '') ? null : $ref;
        }
    }

    /** @return array[] */
    private function sizeWarnings(Group $group): array
    {
        if ($group->address !== null && count($group->members) > self::MAX_RECIPIENTS) {
            return [['group_too_many_members', htmlspecialchars($group->name), self::MAX_RECIPIENTS]];
        }
        return [];
    }

    /** Checkbox or API flag; a hidden "0" + checkbox pair arrives as ["0", "1"] when checked */
    private static function flag(mixed $value): int
    {
        if (is_array($value)) {
            $value = end($value);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    /** @return string[] trimmed, lowercase, unique entries of an array or of a string separated by spaces, commas, semicolons or newlines */
    private static function cleanList(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\s,;]+/', is_scalar($value) ? (string)$value : '');
        $clean = [];
        foreach ($values as $item) {
            if (is_scalar($item) && trim((string)$item) !== '') {
                $clean[] = strtolower(trim((string)$item));
            }
        }
        return array_values(array_unique($clean));
    }

    /** Lowercase address with ASCII (IDN) domain, null if invalid */
    private static function normalizeAddress(string $value): ?string
    {
        $value = strtolower(trim($value));
        $at = strrpos($value, '@');
        if ($at === false || $at === 0) {
            return null;
        }
        $domain = idn_to_ascii(substr($value, $at + 1), 0, INTL_IDNA_VARIANT_UTS46);
        $address = substr($value, 0, $at) . '@' . $domain;
        return ($domain !== false && filter_var($address, FILTER_VALIDATE_EMAIL)) ? $address : null;
    }

    private static function domainOf(string $address): string
    {
        return substr($address, strrpos($address, '@') + 1);
    }
}
