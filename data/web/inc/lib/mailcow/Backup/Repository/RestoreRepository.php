<?php

declare(strict_types=1);

namespace Mailcow\Backup\Repository;

use Mailcow\Core\Repository;
use PDO;
use Throwable;

/**
 * Reads and writes of the restore merge. Rows of a backup may come from an older schema, so inserts and updates only
 * use the columns the table has now. Table and column names come from code, never from the backup.
 */
class RestoreRepository extends Repository
{
    /** @var array<string, string[]> table => columns */
    private array $columns = [];

    /** Runs $work in one transaction */
    public function inTransaction(callable $work): mixed
    {
        return $this->transaction($work);
    }

    /** Runs $work in a savepoint of the running transaction: an error undoes only its writes and is thrown on */
    public function savepoint(string $name, callable $work): mixed
    {
        self::identifier($name);
        $this->pdo->exec("SAVEPOINT `$name`");
        try {
            $result = $work();
            $this->pdo->exec("RELEASE SAVEPOINT `$name`");
            return $result;
        } catch (Throwable $e) {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT `$name`");
            throw $e;
        }
    }

    /**
     * Runs $work while holding a named lock, so a retried request of the runner waits for the first one instead of
     * merging the same mailbox twice; null when the lock was not free within $timeout seconds
     */
    public function locked(string $key, callable $work, int $timeout = 120): mixed
    {
        $name = 'mailcow_backup_' . md5($key);
        if ((int)$this->query("SELECT GET_LOCK(:name, " . max(0, $timeout) . ")", ['name' => $name])->fetchColumn() !== 1) {
            return null;
        }
        try {
            return $work();
        } finally {
            $this->query("SELECT RELEASE_LOCK(:name)", ['name' => $name]);
        }
    }

    /** Quota rows of a new mailbox (like the legacy mailbox add) */
    public function resetQuota(string $username): void
    {
        foreach (['quota2', 'quota2replica'] as $table) {
            $this->query("INSERT INTO `$table` (`username`, `bytes`, `messages`) VALUES (:username, 0, 0)
              ON DUPLICATE KEY UPDATE `bytes` = 0, `messages` = 0", ['username' => $username]);
        }
    }

    /** Regular mailboxes of a domain (the domain's mailbox limit counts these) */
    public function mailboxCount(string $domain): int
    {
        return (int)$this->query("SELECT COUNT(*) FROM `mailbox` WHERE `domain` = :domain AND `kind` = ''", ['domain' => $domain])->fetchColumn();
    }

    /** External send-as rights for a domain that is local now (legacy domain and alias domain add) */
    public function dropExternalSenderAcl(string $domain): void
    {
        $this->query("DELETE FROM `sender_acl` WHERE `external` = 1 AND `send_as` LIKE :domain", ['domain' => '%@' . addcslashes($domain, '%_\\')]);
    }

    /** @return string[] the columns of a table in the current schema */
    public function columns(string $table): array
    {
        self::identifier($table);
        return $this->columns[$table] ??= $this->query("SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table ORDER BY `ORDINAL_POSITION`", ['table' => $table])->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array[] rows of a table matching every condition (column => value) */
    public function rows(string $table, array $where): array
    {
        [$condition, $params] = $this->where($table, $where);
        return $this->query("SELECT * FROM `$table` WHERE $condition", $params)->fetchAll();
    }

    /** Inserts a row with the values of the columns the table has now; returns the new id (0 without one) */
    public function insert(string $table, array $values): int
    {
        return $this->insertRow($table, $this->existing($table, $values));
    }

    /** Updates the rows matching $where with the values of the columns the table has now */
    public function update(string $table, array $where, array $values): int
    {
        $values = $this->existing($table, $values);
        if (empty($values)) {
            return 0;
        }
        [$condition, $params] = $this->where($table, $where);
        $set = [];
        foreach (array_keys($values) as $i => $column) {
            $set[] = "`$column` = :v$i";
            $params['v' . $i] = $values[$column];
        }
        return $this->query("UPDATE `$table` SET " . implode(', ', $set) . " WHERE $condition", $params)->rowCount();
    }

    /** A primary or alias domain */
    public function domainExists(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :domain UNION SELECT 1 FROM `alias_domain` WHERE `alias_domain` = :alias",
            ['domain' => $domain, 'alias' => $domain])->fetchColumn();
    }

    /** The primary domain of a domain or alias domain, null if mailcow has neither */
    public function primaryDomain(string $domain): ?string
    {
        $primary = $this->query("SELECT `domain` FROM `domain` WHERE `domain` = :domain
            UNION SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :alias", ['domain' => $domain, 'alias' => $domain])->fetchColumn();
        return is_string($primary) ? strtolower($primary) : null;
    }

    /** An active primary or alias domain (like the legacy is_local_mailcow_domain()) */
    public function isLocalDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :domain AND `active` = 1
            UNION SELECT 1 FROM `alias_domain` WHERE `alias_domain` = :alias AND `active` = 1", ['domain' => $domain, 'alias' => $domain])->fetchColumn();
    }

    /** @return string[] alias domains of a domain */
    public function aliasDomainsOf(string $domain): array
    {
        return array_map('strtolower', $this->query("SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :domain",
            ['domain' => $domain])->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * What an address is now: 'mailbox' (also local@alias-domain of a mailbox), 'resource' (a mailbox of kind
     * location, thing or group), 'alias', 'spamalias' or null
     */
    public function addressKind(string $address): ?string
    {
        $kind = $this->query("SELECT `kind` FROM `mailbox` WHERE `username` = :address", ['address' => $address])->fetchColumn();
        if (is_string($kind)) {
            return preg_match('/location|thing|group/', $kind) ? 'resource' : 'mailbox';
        }
        [$local, $domain] = array_pad(explode('@', $address, 2), 2, '');
        if ((bool)$this->query("SELECT 1 FROM `mailbox` `m` JOIN `alias_domain` `ad` ON `ad`.`alias_domain` = :domain
            WHERE `m`.`username` = CONCAT(:local, '@', `ad`.`target_domain`)", ['domain' => $domain, 'local' => $local])->fetchColumn()) {
            return 'mailbox';
        }
        if ((bool)$this->query("SELECT 1 FROM `alias` WHERE `address` = :address", ['address' => $address])->fetchColumn()) {
            return 'alias';
        }
        if ((bool)$this->query("SELECT 1 FROM `spamalias` WHERE `address` = :address", ['address' => $address])->fetchColumn()) {
            return 'spamalias';
        }
        return null;
    }

    /** Aliases the domain may still get (domain limit minus its aliases and those of its alias domains, like the legacy code) */
    public function aliasesLeft(string $domain): int
    {
        $limit = $this->query("SELECT `aliases` FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
        if ($limit === false) {
            return 0;
        }
        $used = (int)$this->query("SELECT COUNT(*) FROM `alias` WHERE (`domain` = :domain
            OR `domain` IN (SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :target))
            AND `address` NOT IN (SELECT `username` FROM `mailbox`)", ['domain' => $domain, 'target' => $domain])->fetchColumn();
        return (int)$limit - $used;
    }

    /** Quota (bytes) the other mailboxes of the domain have */
    public function quotaInUse(string $domain, string $exceptUsername): int
    {
        return (int)$this->query("SELECT COALESCE(SUM(`quota`), 0) FROM `mailbox` WHERE `domain` = :domain AND `kind` = '' AND `username` != :username",
            ['domain' => $domain, 'username' => $exceptUsername])->fetchColumn();
    }

    /** @return array[] user1, host1 and port1 of the mailbox's sync jobs */
    public function syncJobsOf(string $username): array
    {
        return $this->query("SELECT `i`.`user1`, `s`.`host1`, `s`.`port1` FROM `imapsync` `i`
            LEFT JOIN `imapsync_source` `s` ON `s`.`id` = `i`.`source_id` WHERE `i`.`user2` = :username", ['username' => $username])->fetchAll();
    }

    /** @return int[] ids of the sync sources with this host and port the mailbox may use, oldest first */
    public function syncSourcesFor(string $host, int $port, string $username, string $domain): array
    {
        return array_map('intval', $this->query("SELECT `s`.`id` FROM `imapsync_source` `s` WHERE `s`.`host1` = :host AND `s`.`port1` = :port
            AND (`s`.`scope` = 'all'
              OR (`s`.`scope` = 'domain' AND EXISTS (SELECT 1 FROM `imapsync_source_domain` `d` WHERE `d`.`source_id` = `s`.`id` AND `d`.`domain` = :domain))
              OR (`s`.`scope` = 'user' AND EXISTS (SELECT 1 FROM `imapsync_source_user` `u` WHERE `u`.`source_id` = `s`.`id` AND `u`.`username` = :username)))
            ORDER BY `s`.`id`", ['host' => $host, 'port' => $port, 'domain' => $domain, 'username' => $username])->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array{0: string, 1: array} condition and parameters of column => value pairs */
    private function where(string $table, array $where): array
    {
        self::identifier($table);
        $parts = [];
        $params = [];
        foreach (array_keys($where) as $i => $column) {
            self::identifier((string)$column);
            if ($where[$column] === null) {
                $parts[] = "`$column` IS NULL";
                continue;
            }
            $parts[] = "`$column` = :w$i";
            $params['w' . $i] = $where[$column];
        }
        return [empty($parts) ? '1=1' : implode(' AND ', $parts), $params];
    }

    /** The values of the columns the table has now */
    private function existing(string $table, array $values): array
    {
        return array_intersect_key($values, array_flip($this->columns($table)));
    }

    /** Table, column and savepoint names come from code only; this keeps it that way */
    private static function identifier(string $name): void
    {
        if (!preg_match('/^[a-z_0-9]+$/i', $name)) {
            throw new \InvalidArgumentException('invalid identifier');
        }
    }
}
