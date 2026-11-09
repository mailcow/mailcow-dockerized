<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Support;

use Mailcow\Backup\Repository\RestoreRepository;
use PDOException;
use Throwable;

/**
 * RestoreRepository on in-memory tables: the merge rules are tested against table contents instead of SQL. Strings
 * compare case-insensitively like the MariaDB collation; transactions and savepoints restore a copy of the tables.
 */
class FakeRestoreRepository extends RestoreRepository
{
    /** @var array<string, array[]> table => rows */
    public array $tables = [];
    /** Inserts into these tables fail with a database error */
    public array $failingTables = [];
    /** Named locks that are taken by another request */
    public array $busyLocks = [];
    private const SCHEMA = [
        'domain' => ['domain', 'description', 'aliases', 'mailboxes', 'defquota', 'maxquota', 'quota', 'relayhost', 'backupmx', 'gal', 'created', 'modified', 'active'],
        'tags_domain' => ['tag_name', 'domain'],
        'domain_wide_footer' => ['domain', 'html', 'plain', 'mbox_exclude', 'alias_domain_exclude', 'skip_replies'],
        'mta_sts' => ['id', 'domain', 'version', 'mode', 'mx', 'max_age', 'active'],
        'domain_admins' => ['id', 'username', 'domain', 'created', 'active'],
        'admin' => ['username', 'superadmin', 'active'],
        'da_acl' => ['username', 'syncjobs', 'quarantine', 'app_passwds', 'pushover', 'spam_policy', 'filters', 'mailbox_groups', 'mail_policies',
            'retention', 'protocol_access', 'mailbox_relayhost', 'unlimited_quota', 'ratelimit', 'extend_sender_acl', 'alias_external_goto'],
        'quota2' => ['username', 'bytes', 'messages'],
        'alias_domain' => ['alias_domain', 'target_domain', 'active'],
        'mailbox' => ['username', 'password', 'name', 'quota', 'local_part', 'domain', 'attributes', 'custom_attributes', 'kind', 'authsource', 'created', 'modified', 'active'],
        'alias' => ['id', 'address', 'goto', 'domain', 'created', 'modified', 'private_comment', 'public_comment', 'sogo_visible', 'internal',
            'sender_allowed', 'active', 'group_id'],
        'sender_acl' => ['id', 'logged_in_as', 'send_as', 'external', 'group_id'],
        'spamalias' => ['address', 'goto', 'description', 'created', 'modified', 'validity', 'permanent'],
        'filterconf' => ['prefid', 'object', 'option', 'value', 'created', 'modified'],
        'sieve_filters' => ['id', 'username', 'script_desc', 'script_name', 'script_data', 'filter_type', 'created', 'modified'],
        'bcc_maps' => ['id', 'local_dest', 'bcc_dest', 'domain', 'type', 'created', 'modified', 'active'],
        'app_passwd' => ['id', 'name', 'mailbox', 'domain', 'password', 'created', 'modified', 'imap_access', 'smtp_access', 'active'],
        'tfa' => ['id', 'key_id', 'username', 'authmech', 'secret', 'keyHandle', 'publicKey', 'counter', 'certificate', 'active'],
        'fido2' => ['username', 'friendlyName', 'rpId', 'credentialPublicKey', 'AAGUID', 'credentialId', 'created', 'modified', 'active'],
        'tags_mailbox' => ['tag_name', 'username'],
        'user_acl' => ['username', 'spam_alias', 'syncjobs', 'pushover', 'quarantine', 'app_passwds'],
        'pushover' => ['username', 'key', 'token', 'attributes', 'title', 'active'],
        'imapsync' => ['id', 'user2', 'source_id', 'user1', 'password1', 'maxage', 'created', 'modified', 'is_running', 'returned_text',
            'last_run', 'success', 'exit_status', 'prio', 'active'],
        'imapsync_source' => ['id', 'host1', 'port1', 'scope'],
        'imapsync_source_domain' => ['source_id', 'domain'],
        'imapsync_source_user' => ['source_id', 'username'],
        'mailbox_group' => ['id', 'domain', 'name', 'address'],
        'mailbox_group_member' => ['group_id', 'username', 'source'],
        'mail_policy' => ['id', 'domain', 'name', 'send_mode', 'send_allow', 'send_block', 'receive_mode', 'receive_allow', 'receive_block', 'created'],
        'mail_policy_member' => ['id', 'policy_id', 'username', 'group_id'],
        'retention_rule' => ['id', 'domain', 'name', 'folders', 'days', 'created'],
        'retention_rule_member' => ['id', 'rule_id', 'username', 'group_id'],
        'relayhosts' => ['id', 'hostname'],
    ];

    public function __construct(array $tables = [])
    {
        $this->tables = array_fill_keys(array_keys(self::SCHEMA), []);
        foreach ($tables as $table => $rows) {
            foreach ($rows as $row) {
                $this->tables[$table][] = $row;
            }
        }
    }

    public function inTransaction(callable $work): mixed
    {
        return $this->savepoint('transaction', $work);
    }

    public function savepoint(string $name, callable $work): mixed
    {
        $copy = $this->tables;
        try {
            return $work();
        } catch (Throwable $e) {
            $this->tables = $copy;
            throw $e;
        }
    }

    public function locked(string $key, callable $work, int $timeout = 120): mixed
    {
        return in_array($key, $this->busyLocks, true) ? null : $work();
    }

    public function resetQuota(string $username): void
    {
        $this->tables['quota2'] = array_values(array_filter($this->tables['quota2'], fn(array $row): bool => $row['username'] !== $username));
        $this->tables['quota2'][] = ['username' => $username, 'bytes' => 0, 'messages' => 0];
    }

    public function mailboxCount(string $domain): int
    {
        return count($this->rows('mailbox', ['domain' => $domain, 'kind' => '']));
    }

    public function dropExternalSenderAcl(string $domain): void
    {
        $this->tables['sender_acl'] = array_values(array_filter($this->tables['sender_acl'], fn(array $row): bool =>
            !((int)$row['external'] === 1 && str_ends_with(strtolower((string)$row['send_as']), '@' . strtolower($domain)))));
    }

    public function columns(string $table): array
    {
        return self::SCHEMA[$table];
    }

    public function rows(string $table, array $where): array
    {
        return array_values(array_filter($this->tables[$table], fn(array $row): bool => self::matches($row, $where)));
    }

    public function insert(string $table, array $values): int
    {
        if (in_array($table, $this->failingTables, true)) {
            throw new PDOException('SQLSTATE[23000]: duplicate', 23000);
        }
        $row = array_fill_keys(self::SCHEMA[$table], null);
        foreach (array_intersect_key($values, $row) as $column => $value) {
            $row[$column] = $value;
        }
        $id = 0;
        foreach (['id', 'prefid'] as $key) {
            if (array_key_exists($key, $row)) {
                $id = $row[$key] = 1 + max([0, ...array_map(fn(array $r): int => (int)$r[$key], $this->tables[$table])]);
            }
        }
        $this->tables[$table][] = $row;
        return $id;
    }

    public function update(string $table, array $where, array $values): int
    {
        $count = 0;
        foreach ($this->tables[$table] as &$row) {
            if (self::matches($row, $where)) {
                $row = array_merge($row, array_intersect_key($values, array_flip(self::SCHEMA[$table])));
                $count++;
            }
        }
        return $count;
    }

    public function domainExists(string $domain): bool
    {
        return $this->primaryDomain($domain) !== null;
    }

    public function primaryDomain(string $domain): ?string
    {
        if (!empty($this->rows('domain', ['domain' => $domain]))) {
            return strtolower($domain);
        }
        $alias = $this->rows('alias_domain', ['alias_domain' => $domain])[0] ?? null;
        return ($alias === null) ? null : (string)$alias['target_domain'];
    }

    public function isLocalDomain(string $domain): bool
    {
        return !empty($this->rows('domain', ['domain' => $domain, 'active' => 1])) || !empty($this->rows('alias_domain', ['alias_domain' => $domain, 'active' => 1]));
    }

    public function aliasDomainsOf(string $domain): array
    {
        return array_map(fn(array $row): string => (string)$row['alias_domain'], $this->rows('alias_domain', ['target_domain' => $domain]));
    }

    public function addressKind(string $address): ?string
    {
        $mailbox = $this->rows('mailbox', ['username' => $address])[0] ?? null;
        if ($mailbox !== null) {
            return preg_match('/location|thing|group/', (string)$mailbox['kind']) ? 'resource' : 'mailbox';
        }
        [$local, $domain] = array_pad(explode('@', $address, 2), 2, '');
        foreach ($this->rows('alias_domain', ['alias_domain' => $domain]) as $aliasDomain) {
            if (!empty($this->rows('mailbox', ['username' => $local . '@' . $aliasDomain['target_domain']]))) {
                return 'mailbox';
            }
        }
        if (!empty($this->rows('alias', ['address' => $address]))) {
            return 'alias';
        }
        return empty($this->rows('spamalias', ['address' => $address])) ? null : 'spamalias';
    }

    public function aliasesLeft(string $domain): int
    {
        $domains = array_merge([$domain], $this->aliasDomainsOf($domain));
        $used = array_filter($this->tables['alias'], fn(array $alias): bool => in_array(strtolower((string)$alias['domain']), $domains, true)
            && empty($this->rows('mailbox', ['username' => $alias['address']])));
        return (int)($this->rows('domain', ['domain' => $domain])[0]['aliases'] ?? 0) - count($used);
    }

    public function quotaInUse(string $domain, string $exceptUsername): int
    {
        return array_sum(array_map(fn(array $m): int => (int)$m['quota'], array_filter($this->rows('mailbox', ['domain' => $domain, 'kind' => '']),
            fn(array $m): bool => strtolower((string)$m['username']) !== strtolower($exceptUsername))));
    }

    public function syncJobsOf(string $username): array
    {
        return array_map(function (array $job): array {
            $source = $this->rows('imapsync_source', ['id' => $job['source_id']])[0] ?? [];
            return ['user1' => $job['user1'], 'host1' => $source['host1'] ?? null, 'port1' => $source['port1'] ?? null];
        }, $this->rows('imapsync', ['user2' => $username]));
    }

    public function syncSourcesFor(string $host, int $port, string $username, string $domain): array
    {
        $ids = [];
        foreach ($this->rows('imapsync_source', ['host1' => $host, 'port1' => $port]) as $source) {
            if ($source['scope'] === 'all'
                || ($source['scope'] === 'domain' && !empty($this->rows('imapsync_source_domain', ['source_id' => $source['id'], 'domain' => $domain])))
                || ($source['scope'] === 'user' && !empty($this->rows('imapsync_source_user', ['source_id' => $source['id'], 'username' => $username])))) {
                $ids[] = (int)$source['id'];
            }
        }
        sort($ids);
        return $ids;
    }

    private static function matches(array $row, array $where): bool
    {
        foreach ($where as $column => $value) {
            $actual = $row[$column] ?? null;
            if ($value === null ? $actual !== null : ($actual === null || strtolower((string)$actual) !== strtolower((string)$value))) {
                return false;
            }
        }
        return true;
    }
}
