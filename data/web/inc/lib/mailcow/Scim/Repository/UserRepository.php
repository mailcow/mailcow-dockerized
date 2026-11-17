<?php

declare(strict_types=1);

namespace Mailcow\Scim\Repository;

use Mailcow\Core\Repository;
use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\User;
use Mailcow\Scim\ScimError;
use PDO;

/**
 * SQL for the SCIM users: `scim_maps` joined with their mailboxes (authsource 'scim').
 *
 * A row without scim_id is a user deleted through SCIM: it is no SCIM
 * resource anymore, but keeps the `disabled` flag the template guard of mailbox('edit', 'mailbox_from_template')
 * reads. Like an IdP link of the Keycloak/LDAP sync, it keeps who the user was (token + externalId): only that
 * person is linked again, the address goes to someone else only after an admin released it (release()).
 */
class UserRepository extends Repository
{
    // lastModified: the later change of the mailbox and of its SCIM row (externalId, name parts)
    private const SELECT = "SELECT m.`username`, m.`domain`, m.`name`, m.`active`, m.`created`,
        GREATEST(COALESCE(m.`modified`, m.`created`), COALESCE(s.`modified`, s.`created`)) AS `modified`,
        s.`scim_id`, s.`external_id`, s.`token_id`, s.`template`, s.`disabled`, s.`given_name`, s.`family_name`
      FROM `scim_maps` s JOIN `mailbox` m ON m.`username` = s.`username` AND m.`authsource` = 'scim' AND s.`scim_id` IS NOT NULL";

    /** User attributes stored in a column, for FilterSql (emails are derived from userName; a NULL name reads as '') */
    private const COLUMNS = ['id' => 's.`scim_id`', 'externalid' => 's.`external_id`', 'username' => 'm.`username`',
        'displayname' => "COALESCE(m.`name`, '')", 'name.formatted' => "COALESCE(m.`name`, '')", 'emails' => 'm.`username`',
        'emails.value' => 'm.`username`', 'active' => 'm.`active`'];

    public function find(string $scimId, ?string $domain): ?User
    {
        $row = $this->query(self::SELECT . " WHERE s.`scim_id` = :id AND (:domain IS NULL OR m.`domain` = :domain2)",
            ['id' => $scimId, 'domain' => $domain, 'domain2' => $domain])->fetch(PDO::FETCH_ASSOC);
        return $row ? User::fromRow($row) : null;
    }

    /**
     * @param string[] $scimIds
     * @return array<string, User> SCIM id => user, of the ids that exist (in $domain, when set)
     */
    public function findMany(array $scimIds, ?string $domain): array
    {
        $users = [];
        // chunks of 1000 ids keep the statement far below the placeholder limit
        foreach (array_chunk(array_values(array_unique($scimIds)), 1000) as $chunk) {
            $params = ['domain' => $domain, 'domain2' => $domain];
            foreach ($chunk as $i => $id) {
                $params['i' . $i] = $id;
            }
            $in = implode(', ', array_map(fn(string $key): string => ':' . $key, array_slice(array_keys($params), 2)));
            foreach ($this->query(self::SELECT . " WHERE s.`scim_id` IN ($in) AND (:domain IS NULL OR m.`domain` = :domain2)", $params)
                ->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $users[(string)$row['scim_id']] = User::fromRow($row);
            }
        }
        return $users;
    }

    /**
     * Users ordered by username, of one domain or all
     *
     * @param ?Filter $filter narrows the users by SQL (FilterSql): the result can still contain non-matches
     * @param ?int $limit with $offset: one page, null = all
     * @return User[]
     */
    public function all(?string $domain, ?Filter $filter = null, int $offset = 0, ?int $limit = null): array
    {
        [$where, $params] = FilterSql::where($filter, self::COLUMNS);
        $page = ($limit === null) ? '' : ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        return array_map([User::class, 'fromRow'], $this->query(self::SELECT . " WHERE (:domain IS NULL OR m.`domain` = :domain2)$where
            ORDER BY m.`username`$page", ['domain' => $domain, 'domain2' => $domain] + $params)->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Runs $work holding the advisory lock of the user, so SCIM writes of one user run one after the other.
     * Taken before any group lock (a rename and DELETE take those inside it); group writes never take a user lock,
     * so the two cannot deadlock.
     *
     * @throws ScimError 503 when the lock is not free within 10 seconds
     */
    public function locked(string $scimId, callable $work): mixed
    {
        // the id comes from the URL: hashed, lock names are limited to 64 characters
        $name = 'scim_user_' . substr(hash('sha256', $scimId), 0, 40);
        if ((int)$this->query("SELECT GET_LOCK(:name, 10)", ['name' => $name])->fetchColumn() !== 1) {
            throw new ScimError(503, 'The user is being changed by another request, retry later', null, ['Retry-After: 5']);
        }
        try {
            return $work();
        } finally {
            $this->query("SELECT RELEASE_LOCK(:name)", ['name' => $name]);
        }
    }

    public function count(?string $domain): int
    {
        return (int)$this->query("SELECT COUNT(*) FROM `scim_maps` s JOIN `mailbox` m ON m.`username` = s.`username` AND m.`authsource` = 'scim'
            AND s.`scim_id` IS NOT NULL WHERE (:domain IS NULL OR m.`domain` = :domain2)", ['domain' => $domain, 'domain2' => $domain])->fetchColumn();
    }

    /**
     * @return ?array{authsource: string, linked: bool, tombstone: bool, active: int, disabled: int, external_id: ?string, token_id: ?int}
     *     the mailbox of an address (tombstone: a SCIM row without id, with its flag and the identity it kept), null
     *     when there is none
     */
    public function mailbox(string $username): ?array
    {
        $row = $this->query("SELECT m.`authsource`, m.`active`, s.`scim_id` IS NOT NULL AS `linked`,
            (s.`id` IS NOT NULL AND s.`scim_id` IS NULL) AS `tombstone`, COALESCE(s.`disabled`, 0) AS `disabled`, s.`external_id`, s.`token_id`
          FROM `mailbox` m LEFT JOIN `scim_maps` s ON s.`username` = m.`username` WHERE m.`username` = :username",
            ['username' => $username])->fetch(PDO::FETCH_ASSOC);
        return $row ? ['authsource' => (string)$row['authsource'], 'linked' => (bool)$row['linked'], 'tombstone' => (bool)$row['tombstone'],
            'active' => (int)$row['active'], 'disabled' => (int)$row['disabled'],
            'external_id' => ($row['external_id'] ?? null) !== null ? (string)$row['external_id'] : null,
            'token_id' => ($row['token_id'] ?? null) !== null ? (int)$row['token_id'] : null] : null;
    }

    /**
     * The address belongs to a mailbox or an alias. An alias pointing only to $aliasOf (kept by an earlier rename
     * of that mailbox) does not count: it is removed by the rename back.
     */
    public function addressTaken(string $address, ?string $aliasOf = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mailbox` WHERE `username` = :a
            UNION ALL SELECT 1 FROM `alias` WHERE `address` = :b AND `goto` != :goto", ['a' => $address, 'b' => $address, 'goto' => $aliasOf ?? ''])
            ->fetchColumn();
    }

    /** @return ?int the id of the alias with this address that only points to $goto (not a group's address) */
    public function aliasTo(string $address, string $goto): ?int
    {
        $id = $this->query("SELECT `id` FROM `alias` WHERE `address` = :a AND `goto` = :goto AND `group_id` IS NULL",
            ['a' => $address, 'goto' => $goto])->fetchColumn();
        return ($id === false) ? null : (int)$id;
    }

    /**
     * @param string[] $usernames
     * @return array<string, array{mapped: ?string, applied: ?string, name: string}> of the SCIM users among them:
     *     the template mapped from the IdP, the one applied to the mailbox, the mailbox name
     */
    public function templateStates(array $usernames): array
    {
        $params = [];
        foreach (array_values($usernames) as $i => $username) {
            $params['u' . $i] = $username;
        }
        $in = implode(', ', array_map(fn(string $key): string => ':' . $key, array_keys($params)));
        $out = [];
        foreach ($this->query("SELECT m.`username`, m.`name`, s.`template`, JSON_UNQUOTE(JSON_EXTRACT(m.`attributes`, '$.template')) AS `applied`
            FROM `scim_maps` s JOIN `mailbox` m ON m.`username` = s.`username` AND m.`authsource` = 'scim'
            WHERE s.`scim_id` IS NOT NULL AND s.`username` IN ($in)", $params)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string)$row['username']] = ['mapped' => $row['template'], 'applied' => $row['applied'], 'name' => (string)($row['name'] ?? '')];
        }
        return $out;
    }

    public function templateExists(string $template): bool
    {
        return (bool)$this->query("SELECT 1 FROM `templates` WHERE `template` = :t AND `type` = 'mailbox'", ['t' => $template])->fetchColumn();
    }

    /**
     * externalId is caseExact and scoped to the provisioning client, the token (RFC 7643 3.1).
     * Checked here, not by a unique key (tombstones keep their value), so two concurrent writes of the same new
     * externalId can both pass. A unique key on a generated column would close that if it matters.
     */
    public function externalIdTaken(string $externalId, ?int $tokenId, ?string $exceptUsername = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `scim_maps` WHERE `external_id` = BINARY :id AND `token_id` <=> :token
            AND `username` != :username AND `scim_id` IS NOT NULL",
            ['id' => $externalId, 'token' => $tokenId, 'username' => $exceptUsername ?? ''])->fetchColumn();
    }

    /**
     * Links the mailbox; the row of a user deleted earlier is reused
     *
     * @return bool false when the mailbox is linked already (a concurrent POST won)
     */
    public function link(string $scimId, string $username, ?string $externalId, ?int $tokenId, ?string $template, int $disabled,
        ?string $givenName, ?string $familyName): bool
    {
        $values = ['scim_id' => $scimId, 'username' => $username, 'external_id' => $externalId, 'token_id' => $tokenId,
            'template' => $template, 'disabled' => $disabled, 'given_name' => $givenName, 'family_name' => $familyName];
        // a tombstone is taken over only while it is one: two adoptions of the address cannot both win
        $updated = $this->query("UPDATE `scim_maps` SET `scim_id` = :scim_id, `external_id` = :external_id, `token_id` = :token_id,
            `template` = :template, `disabled` = :disabled, `given_name` = :given_name, `family_name` = :family_name
            WHERE `username` = :username AND `scim_id` IS NULL", $values)->rowCount();
        if ($updated > 0) {
            return true;
        }
        try {
            $this->insertRow('scim_maps', $values);
            return true;
        } catch (\PDOException $e) {
            // duplicate username (MySQL 1062): linked meanwhile; other integrity errors are no conflict
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    /** Sets columns of the user's `scim_maps` row (external_id with token_id, template, disabled, given_name, family_name) */
    public function update(string $username, array $values): void
    {
        if ($values === []) {
            return;
        }
        $set = implode(', ', array_map(fn(string $column): string => "`$column` = :$column", array_keys($values)));
        // not a tombstone: a write racing a DELETE must not land on it
        $this->query("UPDATE `scim_maps` SET $set WHERE `username` = :username AND `scim_id` IS NOT NULL", $values + ['username' => $username]);
    }

    /** Removes the user from SCIM, keeping the row as tombstone with its `disabled` flag and its identity */
    public function unlink(string $username, int $disabled): void
    {
        $this->query("UPDATE `scim_maps` SET `scim_id` = NULL, `disabled` = :disabled WHERE `username` = :username",
            ['disabled' => $disabled, 'username' => $username]);
    }

    /**
     * Releases the mailbox: its SCIM row (with who the user was) and its IdP link go, so another person can get it
     * and log in
     */
    public function release(string $username): void
    {
        $this->transaction(function () use ($username): void {
            $this->query("DELETE FROM `scim_maps` WHERE `username` = :username", ['username' => $username]);
            $this->query("DELETE FROM `identity_link` WHERE `username` = :username", ['username' => $username]);
        });
    }
}
