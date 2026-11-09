<?php

declare(strict_types=1);

namespace Mailcow\Backup\Repository;

use Mailcow\Core\Repository;
use PDO;

/**
 * Reads what the export of a domain holds: the domain with its settings, its aliases and a page of its mailboxes with
 * every row that belongs to a mailbox. Rows are read with all their columns (SELECT *).
 */
class ExportRepository extends Repository
{
    /** @return string[] all domains */
    public function domains(): array
    {
        return $this->query("SELECT `domain` FROM `domain` ORDER BY `domain`")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function dbVersion(): string
    {
        return (string)$this->query("SELECT `version` FROM `versions` WHERE `application` = 'db_schema'")->fetchColumn();
    }

    /** @return array[] rows of a table matching column => value (table and column names from code) */
    public function rows(string $table, string $column, string $value): array
    {
        self::identifier($table);
        self::identifier($column);
        return $this->query("SELECT * FROM `$table` WHERE `$column` = :value", ['value' => $value])->fetchAll();
    }

    /** @return string[] usernames of the domain's mailboxes */
    public function usernames(string $domain): array
    {
        return $this->query("SELECT `username` FROM `mailbox` WHERE `domain` = :domain", ['domain' => $domain])->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array[] the domain's mailbox rows after $after in username order (keyset paging), at most $limit */
    public function mailboxesAfter(string $domain, string $after, int $limit): array
    {
        return $this->query("SELECT * FROM `mailbox` WHERE `domain` = :domain AND `username` > :after ORDER BY `username` LIMIT " . max(1, $limit),
            ['domain' => $domain, 'after' => $after])->fetchAll();
    }

    /** @return array[] alias rows of the domain and its alias domains */
    public function aliases(string $domain): array
    {
        return $this->query("SELECT * FROM `alias` WHERE `domain` = :domain
            OR `domain` IN (SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :target) ORDER BY `id`",
            ['domain' => $domain, 'target' => $domain])->fetchAll();
    }

    /** @return array[] alias rows of any domain whose goto may name a mailbox of the domain (the caller checks) */
    public function aliasesPointingTo(string $domain): array
    {
        return $this->query("SELECT * FROM `alias` WHERE `goto` LIKE :pattern ORDER BY `id`",
            ['pattern' => '%@' . addcslashes($domain, '%_\\') . '%'])->fetchAll();
    }

    /**
     * @param string $table  table with rows of mailboxes
     * @param string $column its column holding the username
     * @param string[] $usernames
     * @return array[] rows of these mailboxes
     */
    public function rowsOfMailboxes(string $table, string $column, array $usernames): array
    {
        self::identifier($table);
        self::identifier($column);
        if (empty($usernames)) {
            return [];
        }
        [$in, $params] = self::in($usernames);
        return $this->query("SELECT * FROM `$table` WHERE `$column` IN ($in)", $params)->fetchAll();
    }

    /** @return array[] username, id, name, domain, source of the group memberships of these mailboxes */
    public function groupMemberships(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        [$in, $params] = self::in($usernames);
        return $this->query("SELECT `gm`.`username`, `g`.`id`, `g`.`name`, `g`.`domain`, `gm`.`source` FROM `mailbox_group_member` `gm`
            JOIN `mailbox_group` `g` ON `g`.`id` = `gm`.`group_id` WHERE `gm`.`username` IN ($in) ORDER BY `g`.`id`", $params)->fetchAll();
    }

    /**
     * @param string $table  mail_policy or retention_rule
     * @param string $member its member table
     * @param string $key    the member table's column with the id
     * @return array[] username, id, name, domain of the direct assignments of these mailboxes
     */
    public function assignments(string $table, string $member, string $key, array $usernames): array
    {
        self::identifier($table);
        self::identifier($member);
        self::identifier($key);
        if (empty($usernames)) {
            return [];
        }
        [$in, $params] = self::in($usernames);
        return $this->query("SELECT `a`.`username`, `p`.`id`, `p`.`name`, `p`.`domain` FROM `$member` `a`
            JOIN `$table` `p` ON `p`.`id` = `a`.`$key` WHERE `a`.`username` IN ($in) ORDER BY `p`.`id`", $params)->fetchAll();
    }

    /** @return array[] sync jobs of these mailboxes (without their log) with host1, port1 and name of the source */
    public function syncJobs(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        [$in, $params] = self::in($usernames);
        $rows = $this->query("SELECT `i`.*, `s`.`host1` AS `source_host1`, `s`.`port1` AS `source_port1`, `s`.`name` AS `source_name`
            FROM `imapsync` `i` LEFT JOIN `imapsync_source` `s` ON `s`.`id` = `i`.`source_id` WHERE `i`.`user2` IN ($in) ORDER BY `i`.`id`", $params)->fetchAll();
        foreach ($rows as &$row) {
            unset($row['returned_text']);
        }
        return $rows;
    }

    /** @return array[] BCC maps of the whole domain (local_dest "@domain", older rows "domain") */
    public function domainBccMaps(string $domain): array
    {
        return $this->query("SELECT * FROM `bcc_maps` WHERE `local_dest` IN (:domain, :at)", ['domain' => $domain, 'at' => '@' . $domain])->fetchAll();
    }

    /**
     * @param string $table  mail_policy or retention_rule
     * @param string $member its member table
     * @param string $key    the member table's column with the id
     * @return array[] rows of the domain, each with `group_members`: the names of the groups it is assigned to
     */
    public function domainRules(string $table, string $member, string $key, string $domain): array
    {
        self::identifier($table);
        self::identifier($member);
        self::identifier($key);
        $rows = $this->query("SELECT * FROM `$table` WHERE `domain` = :domain ORDER BY `id`", ['domain' => $domain])->fetchAll();
        foreach ($rows as &$row) {
            $row['group_members'] = $this->query("SELECT `g`.`name` FROM `$member` `a` JOIN `mailbox_group` `g` ON `g`.`id` = `a`.`group_id`
                WHERE `a`.`$key` = :id ORDER BY `g`.`name`", ['id' => $row['id']])->fetchAll(PDO::FETCH_COLUMN);
        }
        return $rows;
    }

    /** @return array{0: string, 1: array} placeholders and parameters of an IN list */
    private static function in(array $values): array
    {
        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $names[] = ':in' . $i;
            $params['in' . $i] = $value;
        }
        return [implode(', ', $names), $params];
    }

    /** Table and column names come from code only; this keeps it that way */
    private static function identifier(string $name): void
    {
        if (!preg_match('/^[a-z_0-9]+$/i', $name)) {
            throw new \InvalidArgumentException('invalid identifier');
        }
    }
}
