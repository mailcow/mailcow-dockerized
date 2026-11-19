<?php

declare(strict_types=1);

namespace Mailcow\Scim\Repository;

use Mailcow\Core\Repository;
use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\Group;
use Mailcow\Scim\ScimError;
use PDO;

/**
 * SQL for the SCIM groups: `scim_group_maps` joined with their mailbox groups. Writes to the mailbox group
 * tables go through the MailboxGroup module.
 *
 * A SCIM group is a mapped group with scim_id (NULL: deleted through SCIM) that the Keycloak/LDAP identity sync
 * does not manage (idp_ref NULL). Its members are those in `scim_group_maps.members` (the IdP's list) that are
 * still members of the mailbox group: a member removed in mailcow (mailbox deleted, removed by an admin) drops out.
 */
class GroupRepository extends Repository
{
    // lastModified: the later change of the mailbox group and of its SCIM data, the creation when there is none
    private const SELECT = "SELECT g.`id`, g.`domain`, g.`name`, g.`created`,
        GREATEST(COALESCE(g.`modified`, g.`created`), COALESCE(sg.`modified`, sg.`created`)) AS `modified`,
        sg.`scim_id`, sg.`external_id`, sg.`token_id`, sg.`members` AS `scim_members`
      FROM `scim_group_maps` sg JOIN `mailbox_group` g ON g.`id` = sg.`group_id` AND g.`idp_ref` IS NULL";

    /** Group attributes stored in a column, for FilterSql */
    private const COLUMNS = ['id' => 'sg.`scim_id`', 'externalid' => 'sg.`external_id`', 'displayname' => 'g.`name`'];

    /** $domain: only a group of this domain, with members of this domain */
    public function find(string $scimId, ?string $domain): ?Group
    {
        $row = $this->query(self::SELECT . " WHERE sg.`scim_id` = :id AND (:domain IS NULL OR g.`domain` = :domain2)",
            ['id' => $scimId, 'domain' => $domain, 'domain2' => $domain])->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->withMembers([Group::fromRow($row)], $domain)[0] : null;
    }

    /**
     * Groups ordered by name, of one domain or all, with their members
     *
     * @param ?Filter $filter narrows the groups by SQL (FilterSql): the result can still contain non-matches
     * @param ?int $limit with $offset: one page, null = all
     * @return Group[]
     */
    public function all(?string $domain, ?Filter $filter = null, int $offset = 0, ?int $limit = null): array
    {
        [$where, $params] = FilterSql::where($filter, self::COLUMNS);
        $page = ($limit === null) ? '' : ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        $rows = $this->query(self::SELECT . " WHERE sg.`scim_id` IS NOT NULL AND (:domain IS NULL OR g.`domain` = :domain2)$where
            ORDER BY g.`name`, g.`id`$page", ['domain' => $domain, 'domain2' => $domain] + $params)->fetchAll(PDO::FETCH_ASSOC);
        return $this->withMembers(array_map([Group::class, 'fromRow'], $rows), $domain);
    }

    public function count(?string $domain): int
    {
        return (int)$this->query("SELECT COUNT(*) FROM `scim_group_maps` sg JOIN `mailbox_group` g ON g.`id` = sg.`group_id` AND g.`idp_ref` IS NULL
            WHERE sg.`scim_id` IS NOT NULL AND (:domain IS NULL OR g.`domain` = :domain2)", ['domain' => $domain, 'domain2' => $domain])->fetchColumn();
    }

    public function nameTaken(string $domain, string $name, int $exceptGroupId = 0): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mailbox_group` WHERE `domain` = :domain AND `name` = :name AND `id` != :id",
            ['domain' => $domain, 'name' => $name, 'id' => $exceptGroupId])->fetchColumn();
    }

    /** externalId is caseExact and scoped to the provisioning client, the token (RFC 7643 3.1) */
    public function externalIdTaken(string $externalId, ?int $tokenId, int $exceptGroupId = 0): bool
    {
        return (bool)$this->query("SELECT 1 FROM `scim_group_maps` WHERE `external_id` = BINARY :eid AND `token_id` <=> :token
            AND `group_id` != :id AND `scim_id` IS NOT NULL", ['eid' => $externalId, 'token' => $tokenId, 'id' => $exceptGroupId])->fetchColumn();
    }

    public function link(string $scimId, int $groupId, ?string $externalId, ?int $tokenId): void
    {
        $this->insertRow('scim_group_maps', ['scim_id' => $scimId, 'group_id' => $groupId, 'external_id' => $externalId,
            'token_id' => $tokenId]);
    }

    /** externalId of the token that sets it, which becomes the owner of the value */
    public function setExternalId(int $groupId, ?string $externalId, ?int $tokenId): void
    {
        $this->query("UPDATE `scim_group_maps` SET `external_id` = :eid, `token_id` = :token WHERE `group_id` = :id",
            ['eid' => $externalId, 'token' => $tokenId, 'id' => $groupId]);
    }

    /** @param string[] $usernames the members the IdP set */
    public function setMembers(int $groupId, array $usernames): void
    {
        $this->query("UPDATE `scim_group_maps` SET `members` = :members WHERE `group_id` = :id",
            ['members' => json_encode(array_values($usernames)), 'id' => $groupId]);
    }

    /**
     * Removes the group from SCIM; the row stays without scim_id and keeps who the group was (token, externalId),
     * like an orphaned group of the Keycloak/LDAP sync keeps its idp_ref: only that group is linked again
     */
    public function unlink(int $groupId): void
    {
        $this->query("UPDATE `scim_group_maps` SET `scim_id` = NULL, `members` = '[]' WHERE `group_id` = :id", ['id' => $groupId]);
    }

    /**
     * The group of this token and externalId deleted through SCIM, wherever its name went: like the Keycloak/LDAP
     * sync finds an orphaned group by idp_ref
     */
    public function deletedGroupOf(?int $tokenId, string $externalId, string $domain): ?int
    {
        $id = $this->query("SELECT sg.`group_id` FROM `scim_group_maps` sg JOIN `mailbox_group` g ON g.`id` = sg.`group_id` AND g.`idp_ref` IS NULL
            WHERE sg.`scim_id` IS NULL AND sg.`token_id` = :token AND sg.`external_id` = BINARY :eid AND g.`domain` = :domain",
            ['token' => $tokenId, 'eid' => $externalId, 'domain' => $domain])->fetchColumn();
        return ($id === false) ? null : (int)$id;
    }

    /** @return ?array{id: int, external_id: ?string, token_id: ?int} the group with this name deleted through SCIM */
    public function deletedGroup(string $domain, string $name): ?array
    {
        $row = $this->query("SELECT g.`id`, sg.`external_id`, sg.`token_id` FROM `mailbox_group` g JOIN `scim_group_maps` sg ON sg.`group_id` = g.`id`
            WHERE g.`domain` = :domain AND g.`name` = :name AND sg.`scim_id` IS NULL AND g.`idp_ref` IS NULL",
            ['domain' => $domain, 'name' => $name])->fetch(PDO::FETCH_ASSOC);
        return $row ? ['id' => (int)$row['id'], 'external_id' => ($row['external_id'] ?? null) !== null ? (string)$row['external_id'] : null,
            'token_id' => ($row['token_id'] ?? null) !== null ? (int)$row['token_id'] : null] : null;
    }

    /** Takes over the row of a group deleted through SCIM; false when another request took it over first */
    public function relink(int $groupId, string $scimId, ?int $tokenId): bool
    {
        return $this->query("UPDATE `scim_group_maps` SET `scim_id` = :scim_id, `token_id` = :token_id WHERE `group_id` = :id AND `scim_id` IS NULL",
            ['scim_id' => $scimId, 'token_id' => $tokenId, 'id' => $groupId])->rowCount() > 0;
    }

    /**
     * Runs $work holding the advisory lock of the group (by group id, or the SCIM id of a SCIM group), so
     * concurrent membership changes of the group run one after the other instead of overwriting each other.
     * Not a transaction: the MailboxGroup writes inside commit on their own and then run their effects (SOGo,
     * Dovecot, templates), as they are designed to. Read the group inside $work.
     *
     * @throws ScimError 503 when the lock is not free within 10 seconds
     */
    public function locked(int|string $group, callable $work): mixed
    {
        $groupId = is_int($group) ? $group
            : $this->query("SELECT `group_id` FROM `scim_group_maps` WHERE `scim_id` = :id", ['id' => $group])->fetchColumn();
        if ($groupId === false) {
            // unknown SCIM id: nothing to lock, $work answers 404
            return $work();
        }
        return $this->lockedAll([(int)$groupId], $work);
    }

    /**
     * Runs $work holding the advisory locks of all these groups, taken in id order (no deadlock between two callers)
     * before anything is written: a busy group fails the call before any change
     *
     * @param int[] $groupIds
     * @throws ScimError 503 when a lock is not free within 10 seconds
     */
    public function lockedAll(array $groupIds, callable $work): mixed
    {
        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
        sort($groupIds);
        $held = [];
        try {
            foreach ($groupIds as $groupId) {
                $name = 'scim_group_' . $groupId;
                if ((int)$this->query("SELECT GET_LOCK(:name, 10)", ['name' => $name])->fetchColumn() !== 1) {
                    throw new ScimError(503, 'The group is being changed by another request, retry later', null, ['Retry-After: 5']);
                }
                $held[] = $name;
            }
            return $work();
        } finally {
            foreach (array_reverse($held) as $name) {
                $this->query("SELECT RELEASE_LOCK(:name)", ['name' => $name]);
            }
        }
    }

    /**
     * SCIM groups whose IdP list contains the mailbox and that it is still a member of
     *
     * @param ?string $memberAs its username in mailbox_group_member, if that already changed (rename hook)
     * @param ?int $groupId only this group
     * @param bool $forUpdate lock the rows (rename hook: inside the legacy rename transaction, the list must be the
     *     latest one, not the one of the transaction's snapshot)
     * @return array<int, string[]> group id => the IdP list of the group (raw)
     */
    public function groupsAssigning(string $username, ?string $memberAs = null, ?int $groupId = null, bool $forUpdate = false): array
    {
        $params = ['member' => $memberAs ?? $username, 'username' => $username];
        $only = '';
        if ($groupId !== null) {
            $only = ' AND sg.`group_id` = :group';
            $params['group'] = $groupId;
        }
        // through the member index first, so only the user's own groups have their JSON parsed
        $rows = $this->query("SELECT sg.`group_id`, sg.`members` FROM `mailbox_group_member` gm
            JOIN `scim_group_maps` sg ON sg.`group_id` = gm.`group_id` AND sg.`scim_id` IS NOT NULL
            JOIN `mailbox_group` g ON g.`id` = gm.`group_id` AND g.`idp_ref` IS NULL
          WHERE gm.`username` = :member AND JSON_CONTAINS(sg.`members`, JSON_QUOTE(:username))$only" . ($forUpdate ? ' FOR UPDATE' : ''),
            $params)->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map(fn(string $json): array => json_decode($json, true) ?: [], $rows);
    }

    /** @return string[] all members of a mailbox group (any source) */
    public function memberUsernames(int $groupId): array
    {
        return $this->query("SELECT `username` FROM `mailbox_group_member` WHERE `group_id` = :id", ['id' => $groupId])
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * SCIM groups (of $domain, when set) whose members include the mailboxes, by the same rule as Group::$members
     *
     * @param ?string[] $usernames null: all
     * @return array<string, array<string, string>> username => SCIM id => displayName, ordered by name
     */
    public function membershipsOf(?array $usernames, ?string $domain): array
    {
        if ($usernames === []) {
            return [];
        }
        $params = ['domain' => $domain, 'domain2' => $domain];
        $in = '';
        if ($usernames !== null) {
            foreach (array_values($usernames) as $i => $username) {
                $params['u' . $i] = $username;
            }
            $in = ' AND gm.`username` IN (' . implode(', ', array_map(fn(string $key): string => ':' . $key, array_slice(array_keys($params), 2))) . ')';
        }
        // the member rows without the IdP list, which is read once per group below
        $rows = $this->query("SELECT gm.`group_id`, gm.`username` FROM `mailbox_group_member` gm
            JOIN `scim_group_maps` sg ON sg.`group_id` = gm.`group_id` AND sg.`scim_id` IS NOT NULL
            JOIN `mailbox_group` g ON g.`id` = gm.`group_id` AND g.`idp_ref` IS NULL
          WHERE (:domain IS NULL OR g.`domain` = :domain2)$in", $params)->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }
        $groups = $this->groupsById(array_values(array_unique(array_map(fn(array $row): int => (int)$row['group_id'], $rows))));
        $out = [];
        foreach ($rows as $row) {
            $group = $groups[(int)$row['group_id']] ?? null;
            if ($group !== null && isset($group['assigned'][$row['username']])) {
                $out[$row['username']][$group['scim_id']] = $group['name'];
            }
        }
        // ordered by group name, like the groups list
        foreach ($out as $username => $memberships) {
            asort($out[$username], SORT_NATURAL | SORT_FLAG_CASE);
        }
        return $out;
    }

    /** @return array<int, array{scim_id: string, name: string, assigned: array<string, true>}> */
    private function groupsById(array $ids): array
    {
        $groups = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $params = [];
            foreach ($chunk as $i => $id) {
                $params['g' . $i] = $id;
            }
            $in = implode(', ', array_map(fn(string $key): string => ':' . $key, array_keys($params)));
            foreach ($this->query("SELECT sg.`group_id`, sg.`scim_id`, g.`name`, sg.`members` FROM `scim_group_maps` sg
                JOIN `mailbox_group` g ON g.`id` = sg.`group_id` WHERE sg.`group_id` IN ($in)", $params)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $groups[(int)$row['group_id']] = ['scim_id' => (string)$row['scim_id'], 'name' => (string)$row['name'],
                    'assigned' => array_fill_keys(json_decode((string)$row['members'], true) ?: [], true)];
            }
        }
        return $groups;
    }

    /**
     * Loads the members into the groups: the IdP list is reduced to the current members of each mailbox group
     * (Group::$assigned), and the members shown are those of them that are SCIM users (of $domain, when set).
     *
     * @param Group[] $groups
     * @return Group[]
     */
    private function withMembers(array $groups, ?string $domain): array
    {
        if ($groups === []) {
            return [];
        }
        $byId = [];
        foreach ($groups as $group) {
            $byId[$group->groupId] = $group;
        }
        // one pass over the member rows: the current members, and the SCIM id and domain of those that are SCIM users
        $current = [];
        $scimUsers = [];
        foreach (array_chunk(array_keys($byId), 1000) as $chunk) {
            $params = [];
            foreach ($chunk as $i => $id) {
                $params['g' . $i] = $id;
            }
            $in = implode(', ', array_map(fn(string $key): string => ':' . $key, array_keys($params)));
            $stmt = $this->query("SELECT gm.`group_id`, gm.`username`, s.`scim_id`, m.`domain` FROM `mailbox_group_member` gm
                LEFT JOIN `scim_maps` s ON s.`username` = gm.`username` AND s.`scim_id` IS NOT NULL
                LEFT JOIN `mailbox` m ON m.`username` = gm.`username` AND m.`authsource` = 'scim'
              WHERE gm.`group_id` IN ($in) ORDER BY gm.`username`", $params);
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $current[(int)$row['group_id']][$row['username']] = true;
                if ($row['scim_id'] !== null && $row['domain'] !== null && ($domain === null || $row['domain'] === $domain)) {
                    $scimUsers[(int)$row['group_id']][$row['username']] = (string)$row['scim_id'];
                }
            }
        }
        foreach ($groups as $group) {
            $group->assigned = array_values(array_filter($group->assigned, fn(string $username): bool => isset($current[$group->groupId][$username])));
            foreach ($group->assigned as $username) {
                if (isset($scimUsers[$group->groupId][$username])) {
                    $group->members[$scimUsers[$group->groupId][$username]] = $username;
                }
            }
            // ordered by username, like before
            asort($group->members);
        }
        return $groups;
    }
}
