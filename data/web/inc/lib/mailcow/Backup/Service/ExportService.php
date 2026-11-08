<?php

declare(strict_types=1);

namespace Mailcow\Backup\Service;

use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Repository\ExportRepository;
use Mailcow\Core\UserError;

/**
 * Export of a domain for the backup (/mailcow/export/<domain>.json in a snapshot), in pages of mailboxes the runner
 * merges into one file. The first page holds version, db_version, domain, domain_data (what recreating the domain
 * needs), the aliases and `mailbox_aliases` (username => the aliases of any domain whose goto names the mailbox,
 * without its own alias and group addresses; the runner moves them into the mailbox sections when it reads the file);
 * every page a part of `mailboxes` and `next_after`, the last username of the page to ask for the next one with
 * (keyset paging; null after the last page).
 *
 * Per mailbox every row a restore needs, with all columns. Password hashes, DKIM keys and the secrets of app
 * passwords, 2FA and sync jobs are part of it on purpose; only the runner gets it (backup.php with the token). Binary
 * values (fido2) are written as {"base64": "..."}. Beside the keys of the spec, a mailbox has `own_alias` (its alias
 * row: forwarding with or without a copy), `aliases` (empty, see mailbox_aliases) and `ratelimit` (Redis).
 */
class ExportService
{
    public const VERSION = 1;
    public const PAGE_SIZE = 500;
    /** Lists of a mailbox: key => [table, column with the username] */
    private const MAILBOX_ROWS = [
        'sender_acl' => ['sender_acl', 'logged_in_as'],
        'sender_acl_granted' => ['sender_acl', 'send_as'],
        'sieve_filters' => ['sieve_filters', 'username'],
        'filterconf' => ['filterconf', 'object'],
        'spamalias' => ['spamalias', 'goto'],
        'bcc_maps' => ['bcc_maps', 'local_dest'],
        'app_passwd' => ['app_passwd', 'mailbox'],
        'tfa' => ['tfa', 'username'],
        // FIDO2 keys of mailbox users (passwordless login on the user page), like those of admins
        'fido2' => ['fido2', 'username'],
        'identity_link' => ['identity_link', 'username'],
    ];
    /** Single rows of a mailbox: key => [table, column with the username] */
    private const MAILBOX_ROW = [
        'user_acl' => ['user_acl', 'username'],
        'pushover' => ['pushover', 'username'],
    ];

    public function __construct(private ExportRepository $repo, private LegacyDomain $legacy)
    {
    }

    /** @return string[] all domains */
    public function domains(): array
    {
        return array_values(array_map('strval', $this->repo->domains()));
    }

    /**
     * @param ?string $after the last username of the previous page (keyset paging), null for the first page
     * @throws UserError domain_not_found
     */
    public function export(string $domain, ?string $after = null, int $pageSize = self::PAGE_SIZE): array
    {
        $domain = strtolower(trim($domain));
        $row = ($domain === '') ? null : ($this->repo->rows('domain', 'domain', $domain)[0] ?? null);
        if ($row === null) {
            throw new UserError('domain_not_found', [htmlspecialchars($domain)]);
        }
        $rows = $this->repo->mailboxesAfter($domain, strtolower($after ?? ''), $pageSize + 1);
        $more = count($rows) > $pageSize;
        $rows = array_slice($rows, 0, $pageSize);
        $export = ['version' => self::VERSION, 'domain' => $domain];
        if ($after === null) {
            $export += [
                'db_version' => $this->repo->dbVersion(),
                'domain_data' => $this->domainData($domain, $row),
                'aliases' => array_map(fn(array $alias): array => self::encode($alias), $this->repo->aliases($domain)),
                'mailbox_aliases' => $this->mailboxAliases($domain) ?: new \stdClass(),
            ];
        }
        $export['mailboxes'] = $this->mailboxes($domain, $rows) ?: new \stdClass();
        $export['next_after'] = ($more && !empty($rows)) ? strtolower((string)end($rows)['username']) : null;
        return $export;
    }

    /** What recreating the domain needs: its row and settings, alias domains, DKIM keys, groups, policies, rules */
    private function domainData(string $domain, array $row): array
    {
        $aliasDomains = $this->repo->rows('alias_domain', 'target_domain', $domain);
        $dkim = [];
        foreach (array_merge([$domain], array_map(fn(array $alias): string => strtolower((string)$alias['alias_domain']), $aliasDomains)) as $name) {
            $key = $this->legacy->dkim($name);
            if ($key !== null) {
                $dkim[] = ['domain' => $name] + $key;
            }
        }
        return [
            'domain' => self::encode($row),
            'tags' => array_map(fn(array $tag): string => (string)$tag['tag_name'], $this->repo->rows('tags_domain', 'domain', $domain)),
            'alias_domains' => $aliasDomains,
            'dkim' => $dkim,
            'ratelimit' => $this->legacy->rateLimits([$domain])[$domain] ?? null,
            'filterconf' => $this->repo->rows('filterconf', 'object', $domain),
            'bcc_maps' => $this->repo->domainBccMaps($domain),
            'footer' => $this->repo->rows('domain_wide_footer', 'domain', $domain)[0] ?? null,
            'mta_sts' => $this->repo->rows('mta_sts', 'domain', $domain)[0] ?? null,
            'domain_admins' => $this->repo->rows('domain_admins', 'domain', $domain),
            'groups' => $this->repo->rows('mailbox_group', 'domain', $domain),
            'mail_policies' => $this->repo->domainRules('mail_policy', 'mail_policy_member', 'policy_id', $domain),
            'retention_rules' => $this->repo->domainRules('retention_rule', 'retention_rule_member', 'rule_id', $domain),
        ];
    }

    /** @return array<string, array> username => its section */
    private function mailboxes(string $domain, array $rows): array
    {
        $mailboxes = [];
        foreach ($rows as $row) {
            $mailboxes[strtolower((string)$row['username'])] = [
                'mailbox' => self::encode($row) + ['tags' => []],
                'user_acl' => null,
                'pushover' => null,
                'ratelimit' => null,
                'own_alias' => null,
                'aliases' => [],
                'groups' => [],
                'mail_policies' => [],
                'retention_rules' => [],
                'imapsync' => [],
            ] + array_fill_keys(array_keys(self::MAILBOX_ROWS), []);
        }
        $usernames = array_map('strval', array_keys($mailboxes));
        if (empty($usernames)) {
            return [];
        }
        // the mailbox of the page a row belongs to, null for anything else
        $owner = fn(array $row, string $column): ?string => isset($mailboxes[strtolower((string)$row[$column])]) ? strtolower((string)$row[$column]) : null;
        foreach ($this->repo->rowsOfMailboxes('tags_mailbox', 'username', $usernames) as $row) {
            if (($username = $owner($row, 'username')) !== null) {
                $mailboxes[$username]['mailbox']['tags'][] = (string)$row['tag_name'];
            }
        }
        foreach (self::MAILBOX_ROW as $key => [$table, $column]) {
            foreach ($this->repo->rowsOfMailboxes($table, $column, $usernames) as $row) {
                if (($username = $owner($row, $column)) !== null) {
                    $mailboxes[$username][$key] = self::encode($row);
                }
            }
        }
        foreach (self::MAILBOX_ROWS as $key => [$table, $column]) {
            foreach ($this->repo->rowsOfMailboxes($table, $column, $usernames) as $row) {
                if (($username = $owner($row, $column)) !== null) {
                    $mailboxes[$username][$key][] = self::encode($row);
                }
            }
        }
        foreach ($this->legacy->rateLimits($usernames) as $username => $value) {
            if (isset($mailboxes[strtolower((string)$username)])) {
                $mailboxes[strtolower((string)$username)]['ratelimit'] = $value;
            }
        }
        foreach ($this->repo->groupMemberships($usernames) as $row) {
            if (($username = $owner($row, 'username')) !== null) {
                $mailboxes[$username]['groups'][] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'domain' => (string)$row['domain'],
                    'source' => (string)$row['source']];
            }
        }
        foreach (['mail_policies' => ['mail_policy', 'mail_policy_member', 'policy_id'], 'retention_rules' => ['retention_rule', 'retention_rule_member', 'rule_id']]
            as $key => [$table, $member, $column]) {
            foreach ($this->repo->assignments($table, $member, $column, $usernames) as $row) {
                if (($username = $owner($row, 'username')) !== null) {
                    $mailboxes[$username][$key][] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'domain' => (string)$row['domain']];
                }
            }
        }
        foreach ($this->repo->syncJobs($usernames) as $row) {
            if (($username = $owner($row, 'user2')) !== null) {
                $mailboxes[$username]['imapsync'][] = self::encode($row);
            }
        }
        foreach ($this->repo->rowsOfMailboxes('alias', 'address', $usernames) as $row) {
            if (($username = $owner($row, 'address')) !== null) {
                $mailboxes[$username]['own_alias'] = self::encode($row);
            }
        }
        return $mailboxes;
    }

    /**
     * @return array<string, array[]> username => aliases of any domain whose goto names the mailbox (once per domain: the
     * alias table is scanned, not once per page)
     */
    private function mailboxAliases(string $domain): array
    {
        $usernames = array_fill_keys(array_map(fn(mixed $username): string => strtolower((string)$username), $this->repo->usernames($domain)), true);
        $aliases = [];
        foreach ($this->repo->aliasesPointingTo($domain) as $row) {
            if ($row['group_id'] !== null) {
                continue;
            }
            $address = strtolower((string)$row['address']);
            foreach (array_unique(array_map('strtolower', self::gotos((string)$row['goto']))) as $goto) {
                if ($goto !== $address && isset($usernames[$goto])) {
                    $aliases[$goto][] = self::encode($row);
                }
            }
        }
        return $aliases;
    }

    /** @return string[] addresses of a goto list */
    public static function gotos(string $goto): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $goto)), 'strlen'));
    }

    /** Binary values (not UTF-8) as {"base64": "..."}, so the row survives JSON */
    public static function encode(array $row): array
    {
        foreach ($row as $column => $value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $row[$column] = ['base64' => base64_encode($value)];
            }
        }
        return $row;
    }
}
