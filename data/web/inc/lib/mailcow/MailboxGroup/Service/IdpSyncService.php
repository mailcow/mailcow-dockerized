<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Service;

use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;

/**
 * Entry points for the IdP sync: system caller, no permission checks, never routed in json_api.
 * Only memberships with source 'idp' are managed here; manual memberships stay.
 */
class IdpSyncService
{
    public function __construct(private GroupRepository $repo, private LookupRepository $lookup, private ChangeEffects $effects)
    {
    }

    public function findByIdpRef(string $ref): ?Group
    {
        return $this->repo->findByIdpRef($ref);
    }

    /** @return array[] every group with its members, see GroupRepository::syncState() */
    public function groups(): array
    {
        return $this->repo->syncState();
    }

    /**
     * Creates the group of an IdP group, members follow with syncGroupMembers(). The group mail becomes the
     * distribution address when it is in the group's domain and free.
     *
     * @return array[] warnings (msg values)
     * @throws UserError
     */
    public function createFromIdp(string $ref, string $name, string $domain, ?string $mail): array
    {
        $name = $this->validName($domain, $name, null);
        if (!$this->lookup->isDomain($domain)) {
            throw new UserError('domain_not_found', [htmlspecialchars($domain)]);
        }
        $group = new Group();
        $group->domain = $domain;
        $group->name = $name;
        $group->idpRef = $ref;
        $warnings = [];
        if ($mail !== null && filter_var($mail, FILTER_VALIDATE_EMAIL) && str_ends_with($mail, '@' . $domain)) {
            if ($this->lookup->addressTaken($mail, null) || $this->lookup->aliasesLeft($domain) < 1) {
                $warnings[] = ['group_idp_address_skipped', htmlspecialchars($mail), htmlspecialchars($name)];
            } else {
                $group->address = $mail;
            }
        }
        $this->repo->insert($group);
        return $warnings;
    }

    /** @throws UserError */
    public function renameFromIdp(int $groupId, string $name): void
    {
        $group = $this->repo->find($groupId);
        if ($group === null) {
            return;
        }
        $group->name = $this->validName($group->domain, $name, $groupId);
        $this->repo->update($group);
    }

    /**
     * Sets the IdP members of a group. Usernames that are no regular mailbox are skipped; a manual member that
     * the IdP also lists stays manual.
     *
     * @return array{added: string[], removed: string[], skipped: string[], warnings: array[]}
     */
    public function syncGroupMembers(int $groupId, array $usernames): array
    {
        $result = ['added' => [], 'removed' => [], 'skipped' => [], 'warnings' => []];
        $group = $this->repo->find($groupId);
        if ($group === null) {
            return $result;
        }
        $wanted = array_values(array_unique(array_map(fn(mixed $u): string => strtolower(trim((string)$u)), $usernames)));
        $regular = $this->lookup->regularMailboxes($wanted);
        $result['skipped'] = array_values(array_diff($wanted, $regular));
        $members = $group->members;
        foreach ($members as $username => $source) {
            if ($source === Group::SOURCE_IDP && !in_array((string)$username, $regular, true)) {
                unset($members[$username]);
                $result['removed'][] = (string)$username;
            }
        }
        foreach ($regular as $username) {
            if (!isset($members[$username])) {
                $members[$username] = Group::SOURCE_IDP;
                $result['added'][] = $username;
            }
        }
        if (empty($result['added']) && empty($result['removed'])) {
            return $result;
        }
        ksort($members);
        $snapshot = $this->effects->snapshot(array_merge($group->usernames(), array_keys($members)));
        $group->members = $members;
        $result['warnings'] = $this->effects->apply($this->repo->update($group), $snapshot);
        return $result;
    }

    /** @throws UserError */
    private function validName(string $domain, string $name, ?int $groupId): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new UserError('group_name_invalid');
        }
        if ($this->repo->nameExists($domain, $name, $groupId)) {
            throw new UserError('object_exists', [htmlspecialchars($name)]);
        }
        return $name;
    }
}
