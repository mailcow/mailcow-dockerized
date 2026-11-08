<?php

declare(strict_types=1);

namespace Mailcow\Backup\Service;

use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Repository\RestoreRepository;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Throwable;

/**
 * Action `restore_domain`: recreates a domain deleted since the snapshot from its export (`domain_data`), before its
 * mailboxes are restored. Only for running restores an admin requested while the domain did not exist
 * (params.create_domain).
 *
 * Creates the domain like the legacy domain add (row, tags, domain map, external send-as rights for it dropped, SOGo
 * restarted so it knows the domain), unless it exists by now. Then adds what is missing: alias domains, DKIM keys, rate
 * limit, spam settings, BCC maps, footer, MTA-STS, domain admin assignments of accounts that still exist, groups (no
 * members, send-as rights or shares; members follow with the mailboxes), mail policies and retention rules with their
 * group assignments (matched by name).
 */
class DomainRestoreService
{
    private const NEW_ROW = ['id', 'created', 'modified'];
    private const POLICY_LISTS = ['send_allow', 'send_block', 'receive_allow', 'receive_block'];
    private const RATE_LIMIT = '/^\d+ \/ 1[smhd]$/';

    public function __construct(private RestoreRepository $repo, private JobRepository $jobs, private GroupMembers $groups,
        private LegacyDomain $legacy)
    {
    }

    /**
     * @return array{domain: array{added: int, skipped: int, errors: string[], created: int}}
     * @throws UserError
     */
    public function restore(mixed $jobId, mixed $data): array
    {
        $id = Input::intInRange($jobId, 1, PHP_INT_MAX);
        $job = ($id === null) ? null : $this->jobs->find($id);
        if ($job === null || $job->type !== 'restore' || $job->state !== 'running') {
            throw new UserError('backup_job_not_running');
        }
        if (!$job->createsDomain() || ($job->params['requested_role'] ?? '') !== 'admin') {
            throw new AccessDenied();
        }
        $domain = strtolower((string)$job->domain);
        if (!is_array($data) || !is_array($data['domain'] ?? null) || strtolower((string)($data['domain']['domain'] ?? '')) !== $domain) {
            throw new UserError('backup_export_invalid');
        }
        $result = $this->repo->locked($job->id . ':domain', fn(): array => $this->restoreDomain($job, $domain, $data));
        return is_array($result) ? ['domain' => $result] : throw new UserError('backup_job_busy');
    }

    private function restoreDomain(Job $job, string $domain, array $data): array
    {
        $result = ['added' => 0, 'skipped' => 0, 'errors' => [], 'created' => 0];
        if (!empty($this->repo->rows('alias_domain', ['alias_domain' => $domain]))) {
            $result['errors'][] = "$domain is an alias domain now";
            return $result;
        }
        if (!empty($this->repo->rows('domain', ['domain' => $domain]))) {
            // created meanwhile: only what is missing is added
            $result['skipped']++;
        } else {
            $this->createDomain($domain, $data, $result);
        }
        $this->repo->inTransaction(function () use ($domain, $data, &$result): void {
            $this->aliasDomains($domain, $data, $result);
            $this->domainSettings($domain, $data, $result);
        });
        $this->redisSettings($domain, $data, $result);
        $groupIds = $this->createGroups($domain, $data, $result);
        $this->repo->inTransaction(function () use ($domain, $data, $groupIds, &$result): void {
            $this->rules($domain, $data, 'mail_policies', 'mail_policy', 'mail_policy_member', 'policy_id', $groupIds, $result);
            $this->rules($domain, $data, 'retention_rules', 'retention_rule', 'retention_rule_member', 'rule_id', $groupIds, $result);
        });
        return $result;
    }

    /** The domain row, its tags and the effects of the legacy domain add */
    private function createDomain(string $domain, array $data, array &$result): void
    {
        // the domain keeps its creation time: domain admins may use the snapshots made after it
        $row = array_diff_key(RestoreService::decode($data['domain']), array_flip(['modified']));
        $relayhost = (string)($row['relayhost'] ?? '0');
        if ($relayhost !== '0' && $relayhost !== '' && empty($this->repo->rows('relayhosts', ['id' => (int)$relayhost]))) {
            $row['relayhost'] = '0';
            $result['errors'][] = 'relayhost: does not exist';
        }
        $this->repo->inTransaction(function () use ($domain, $row, $data): void {
            $this->repo->insert('domain', ['domain' => $domain] + $row);
            $this->repo->dropExternalSenderAcl($domain);
            foreach (array_unique(array_filter(array_map(fn(mixed $tag): string => is_scalar($tag) ? trim((string)$tag) : '', (array)($data['tags'] ?? [])), 'strlen')) as $tag) {
                $this->repo->insert('tags_domain', ['domain' => $domain, 'tag_name' => $tag]);
            }
        });
        $result['added']++;
        $result['created'] = 1;
        $this->effect($result, 'domain map', fn() => $this->legacy->addDomainMap($domain));
        $error = $this->legacy->restartSogo();
        if ($error !== null) {
            $result['errors'][] = 'SOGo restart failed: ' . $error;
        }
    }

    private function aliasDomains(string $domain, array $data, array &$result): void
    {
        foreach (self::rows($data, 'alias_domains') as $row) {
            $alias = strtolower(trim((string)($row['alias_domain'] ?? '')));
            if ($alias === '' || $this->repo->domainExists($alias)) {
                $result['skipped']++;
                continue;
            }
            $this->repo->insert('alias_domain', ['alias_domain' => $alias, 'target_domain' => $domain] + array_diff_key($row, array_flip(self::NEW_ROW)));
            $this->repo->dropExternalSenderAcl($alias);
            $this->effect($result, "domain map of $alias", fn() => $this->legacy->addDomainMap($alias));
            $result['added']++;
        }
    }

    /** Spam settings, BCC maps, footer, MTA-STS and domain admins of the domain that are missing */
    private function domainSettings(string $domain, array $data, array &$result): void
    {
        foreach (self::rows($data, 'filterconf') as $row) {
            $option = (string)($row['option'] ?? '');
            $value = (string)($row['value'] ?? '');
            $current = $this->repo->rows('filterconf', ['object' => $domain]);
            $present = in_array($option, ['whitelist_from', 'blacklist_from'], true)
                ? array_filter($current, fn(array $c): bool => in_array($c['option'], ['whitelist_from', 'blacklist_from'], true) && strtolower((string)$c['value']) === strtolower($value))
                : array_filter($current, fn(array $c): bool => $c['option'] === $option && (in_array($option, ['lowspamlevel', 'highspamlevel'], true) || (string)$c['value'] === $value));
            if ($option === '' || !empty($present)) {
                $result['skipped']++;
                continue;
            }
            $this->repo->insert('filterconf', ['object' => $domain, 'option' => $option, 'value' => $value]);
            $result['added']++;
        }
        foreach (self::rows($data, 'bcc_maps') as $row) {
            $localDest = strtolower((string)($row['local_dest'] ?? ''));
            if (!in_array($localDest, [$domain, '@' . $domain], true) || !filter_var((string)($row['bcc_dest'] ?? ''), FILTER_VALIDATE_EMAIL)
                || !in_array($row['type'] ?? '', ['sender', 'rcpt'], true)) {
                $result['errors'][] = "BCC map $localDest: invalid";
            } elseif (!empty($this->repo->rows('bcc_maps', ['local_dest' => $localDest, 'type' => $row['type']]))) {
                $result['skipped']++;
            } else {
                $this->repo->insert('bcc_maps', ['domain' => $domain] + array_diff_key($row, array_flip(self::NEW_ROW)));
                $result['added']++;
            }
        }
        foreach (['footer' => 'domain_wide_footer', 'mta_sts' => 'mta_sts'] as $key => $table) {
            if (!is_array($data[$key] ?? null)) {
                continue;
            }
            if (!empty($this->repo->rows($table, ['domain' => $domain]))) {
                $result['skipped']++;
                continue;
            }
            $this->repo->insert($table, ['domain' => $domain] + array_diff_key(RestoreService::decode($data[$key]), array_flip(['created', 'modified'])));
            $result['added']++;
        }
        foreach (self::rows($data, 'domain_admins') as $row) {
            $username = (string)($row['username'] ?? '');
            if (empty($this->repo->rows('admin', ['username' => $username, 'superadmin' => 0]))) {
                $result['errors'][] = "domain admin $username: does not exist";
            } elseif (!empty($this->repo->rows('domain_admins', ['username' => $username, 'domain' => $domain]))) {
                $result['skipped']++;
            } else {
                $this->repo->insert('domain_admins', ['username' => $username, 'domain' => $domain, 'active' => (int)($row['active'] ?? 1)]);
                $result['added']++;
            }
        }
    }

    /** DKIM keys of the domain and its alias domains, and the rate limit of the domain (Redis), when missing */
    private function redisSettings(string $domain, array $data, array &$result): void
    {
        $domains = array_merge([$domain], $this->repo->aliasDomainsOf($domain));
        foreach (self::rows($data, 'dkim') as $key) {
            $name = strtolower((string)($key['domain'] ?? ''));
            $selector = (string)($key['selector'] ?? '');
            if (!in_array($name, $domains, true)) {
                continue;
            }
            if (!preg_match('/^[a-z0-9._-]{1,63}$/i', $selector) || (string)($key['pubkey'] ?? '') === '' || (string)($key['privkey'] ?? '') === '') {
                $result['errors'][] = "DKIM key of $name: invalid";
                continue;
            }
            $this->effect($result, "DKIM key of $name", function () use ($name, $selector, $key, &$result): void {
                if ($this->legacy->dkim($name) !== null) {
                    $result['skipped']++;
                    return;
                }
                $this->legacy->addDkim($name, $selector, (string)$key['pubkey'], (string)$key['privkey']);
                $result['added']++;
            });
        }
        $rateLimit = $data['ratelimit'] ?? null;
        if (is_string($rateLimit) && preg_match(self::RATE_LIMIT, $rateLimit)) {
            $this->effect($result, 'rate limit', function () use ($domain, $rateLimit, &$result): void {
                if (isset($this->legacy->rateLimits([$domain])[$domain])) {
                    $result['skipped']++;
                    return;
                }
                $this->legacy->setRateLimit($domain, $rateLimit);
                $result['added']++;
            });
        }
    }

    /**
     * Groups of the domain missing by name, through the group module (address, flags, priority, IdP link)
     *
     * @return array<int, int> id in the backup => id now, of all groups found by name
     */
    private function createGroups(string $domain, array $data, array &$result): array
    {
        $ids = [];
        $admin = new Context('admin', 'backup', [], true);
        foreach (self::rows($data, 'groups') as $row) {
            $name = (string)($row['name'] ?? '');
            $current = $this->repo->rows('mailbox_group', ['domain' => $domain, 'name' => $name])[0] ?? null;
            if ($current !== null) {
                $ids[(int)($row['id'] ?? 0)] = (int)$current['id'];
                $result['skipped']++;
                continue;
            }
            $input = ['domain' => $domain, 'name' => $name, 'description' => (string)($row['description'] ?? '')];
            foreach (['sender_allowed', 'sogo_visible', 'internal', 'active', 'priority'] as $key) {
                if (isset($row[$key])) {
                    $input[$key] = (int)$row[$key];
                }
            }
            foreach (['address', 'idp_ref'] as $key) {
                if (($row[$key] ?? null) !== null && $row[$key] !== '') {
                    $input[$key] = (string)$row[$key];
                }
            }
            try {
                $ids[(int)($row['id'] ?? 0)] = $this->groups->create($admin, $input);
                $result['added']++;
            } catch (UserError $e) {
                $result['errors'][] = "group $name: " . $e->key;
            }
        }
        return $ids;
    }

    /**
     * Mail policies or retention rules missing by name, with their group assignments; group:<id> entries of policies
     * follow the groups' new ids
     */
    private function rules(string $domain, array $data, string $key, string $table, string $member, string $column, array $groupIds, array &$result): void
    {
        foreach (self::rows($data, $key) as $row) {
            $name = (string)($row['name'] ?? '');
            if (!empty($this->repo->rows($table, ['domain' => $domain, 'name' => $name]))) {
                $result['skipped']++;
                continue;
            }
            $values = array_diff_key($row, array_flip([...self::NEW_ROW, 'group_members']));
            foreach (self::POLICY_LISTS as $list) {
                if (isset($values[$list])) {
                    $values[$list] = json_encode($this->remapGroups((string)$values[$list], $groupIds, "$name ($list)", $result));
                }
            }
            $id = $this->repo->insert($table, ['domain' => $domain, 'name' => $name] + $values);
            foreach ((array)($row['group_members'] ?? []) as $groupName) {
                $group = $this->repo->rows('mailbox_group', ['domain' => $domain, 'name' => (string)$groupName])[0] ?? null;
                if ($group === null) {
                    $result['errors'][] = "$name: group $groupName does not exist";
                    continue;
                }
                $this->repo->insert($member, [$column => $id, 'group_id' => (int)$group['id']]);
            }
            $result['added']++;
        }
    }

    /** @return string[] entries of a JSON list with group:<id> of the backup turned into the group's id now */
    private function remapGroups(string $json, array $groupIds, string $what, array &$result): array
    {
        $entries = [];
        foreach ((array)json_decode($json, true) as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            if (preg_match('/^group:(\d+)$/', $entry, $match)) {
                if (!isset($groupIds[(int)$match[1]])) {
                    $result['errors'][] = "$what: $entry dropped (group does not exist)";
                    continue;
                }
                $entry = 'group:' . $groupIds[(int)$match[1]];
            }
            $entries[] = $entry;
        }
        return $entries;
    }

    /** A Redis or dockerapi step: a failure is reported and the rest goes on */
    private function effect(array &$result, string $what, callable $work): void
    {
        try {
            $work();
        } catch (Throwable) {
            $result['errors'][] = "$what: not stored (Redis)";
        }
    }

    private static function rows(array $data, string $key): array
    {
        return array_values(array_filter((array)($data[$key] ?? []), 'is_array'));
    }
}
