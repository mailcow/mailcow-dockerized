<?php

declare(strict_types=1);

namespace Mailcow\Backup\Service;

use DateTimeImmutable;
use DateTimeZone;
use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Model\Settings;
use Mailcow\Backup\Model\Snapshot;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Repository\SnapshotRepository;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;

/**
 * Jobs of the backup runners, the snapshots with their content and the status page.
 *
 * Backup, check, prune and init jobs: admins (not read-only API keys) queue them, one of each type at a time.
 * Restores: admins, and domain admins for mailboxes or a domain they administer now. Mailboxes and domains deleted
 * since the snapshot are created from it (deleted domains by admins only). A component a domain admin could not
 * manage himself (e.g. sync jobs without the `syncjobs` ACL) is refused; his ACL and domains are stored with the job,
 * so the merge and the runners apply the same limits.
 * Reading: admins see every job and snapshot; domain admins the restores of their domains and, of each snapshot made
 * after their domain was created, their domains in it.
 */
class JobService
{
    /**
     * Restore components in their order. mails, imap_acl, sieve_activate and the Sieve scripts of filters are restored by
     * the dovecot runner, sogo by the restic runner, the others by RestoreService.
     */
    public const COMPONENTS = ['mails', 'imap_acl', 'sogo', 'aliases', 'groups', 'sender_acl', 'mail_policies', 'retention_rules',
        'filters', 'sieve_activate', 'spam', 'bcc', 'syncjobs', 'app_passwords', 'tfa', 'settings', 'password'];
    /** Components that replace current data with the backup's (all others only add what is missing) */
    public const OVERWRITE = ['sogo', 'settings', 'password'];
    /** Components that need the mails: not in snapshots without them (RESTIC_BACKUP_MODE=config) */
    public const MAIL_COMPONENTS = ['mails', 'imap_acl', 'sogo', 'sieve_activate'];
    public const MAIL_TARGETS = ['folder', 'original'];
    /** Domain admin ACL a component needs */
    private const COMPONENT_ACL = [
        'groups' => 'mailbox_groups',
        'mail_policies' => 'mail_policies',
        'retention_rules' => 'retention',
        'filters' => 'filters',
        'sieve_activate' => 'filters',
        'spam' => 'spam_policy',
        'bcc' => 'bcc_maps',
        'syncjobs' => 'syncjobs',
        'app_passwords' => 'app_passwds',
    ];
    /** Components that only work together with another one */
    private const REQUIRES = ['sieve_activate' => 'filters'];
    /** Domain admin ACLs the merge respects, stored with the job */
    private const RESTORE_ACL = ['filters', 'mailbox_groups', 'mail_policies', 'retention', 'spam_policy', 'bcc_maps', 'syncjobs',
        'app_passwds', 'protocol_access', 'mailbox_relayhost', 'unlimited_quota', 'sogo_access', 'pushover', 'extend_sender_acl',
        'alias_external_goto', 'ratelimit', 'tls_policy', 'quarantine', 'quarantine_notification', 'quarantine_category'];
    private const MAX_MAILBOXES = 1000;
    private const LIST_LIMIT = 100;
    private const MAX_LIST = 1000;
    /** Mailboxes a search returns at most */
    private const SEARCH_LIMIT = 500;
    /** The runner reports every 10 seconds */
    private const RUNNER_TIMEOUT = 120;
    /** @var array<string, array{bool, ?int}> domain => [access of the domain admin, creation time now], per request */
    private array $domainState = [];

    public function __construct(private JobRepository $jobs, private SnapshotRepository $snapshots, private SettingsService $settings,
        private Context $context, private string $timezone = 'UTC')
    {
    }

    /**
     * @param mixed $type only jobs of this type ('' or null = all; an unknown type finds nothing)
     * @return Job[] newest first, at most $limit (0 = LIST_LIMIT)
     */
    public function list(int $limit = 0, mixed $type = null): array
    {
        $type = Input::text($type);
        if ($type !== '' && !in_array($type, Job::TYPES, true)) {
            return [];
        }
        return $this->jobs->visible($this->context, ($limit > 0) ? min($limit, self::MAX_LIST) : self::LIST_LIMIT, ($type === '') ? null : $type);
    }

    /**
     * Entry of the API. Domain admins get it restricted: e-mail addresses outside the job's domain and its alias domains
     * and repository details (URLs, restic repository strings, paths of the repository) are shown as "…".
     */
    public function view(Job $job, bool $withLog = false): array
    {
        $entry = $job->toArray($withLog);
        if ($this->context->hasAdminRights()) {
            return $entry;
        }
        $domains = array_merge([strtolower($job->domain)], $this->jobs->aliasDomainsOf(strtolower($job->domain)));
        foreach (['result', 'error', 'log'] as $key) {
            if (isset($entry[$key])) {
                $entry[$key] = self::restricted($entry[$key], $domains);
            }
        }
        // the account of an admin who requested the restore stays hidden, the UI shows "Administrator"
        if (($job->params['requested_role'] ?? '') !== 'domainadmin') {
            $entry['requested_by'] = '';
            if (isset($entry['log'])) {
                $entry['log'] = str_replace('(requested by ' . $job->requestedBy . ')', '(requested by an administrator)', $entry['log']);
            }
        }
        return $entry;
    }

    /** The job with its log, null when it does not exist or the caller may not see it */
    public function get(int $id): ?Job
    {
        $job = $this->jobs->find($id, true);
        return ($job !== null && $this->visible($job)) ? $job : null;
    }

    /**
     * Queues a backup, check, prune or init job (admins).
     *
     * @throws UserError
     */
    public function create(array $input): Job
    {
        $this->context->requireAdmin();
        $type = Input::text($input['type'] ?? null);
        if (!in_array($type, Job::MANUAL_TYPES, true)) {
            throw new UserError('backup_job_type_invalid', [htmlspecialchars((string)$type)]);
        }
        $settings = $this->settings->load();
        if (!$settings->configured()) {
            throw new UserError('backup_not_configured');
        }
        if ($type === 'backup' && $settings->get('password_confirmed') !== '1') {
            throw new UserError('backup_password_not_confirmed');
        }
        $job = new Job($type);
        $job->requestedBy = (string)$this->context->username;
        $this->jobs->insertExclusive($job, function (array $active) use ($type): void {
            foreach ($active as $other) {
                if ($other->type === $type) {
                    throw new UserError('backup_job_active', [$type]);
                }
            }
        });
        return $job;
    }

    /**
     * Queues a restore: {snapshot, scope: mailbox|domain, mailboxes | domain, components, mail_target: folder|original}.
     * All mailboxes belong to one domain. Mailboxes deleted since the snapshot are created from it (create_mailboxes);
     * an admin may restore a deleted domain (create_domain). Scope domain restores the snapshot's mailboxes of the
     * domain (the current ones for snapshots without content).
     *
     * @throws UserError
     */
    public function restore(array $input): Job
    {
        $this->requireRestorer();
        if (!$this->settings->load()->configured()) {
            throw new UserError('backup_not_configured');
        }
        $snapshot = $this->snapshot($input['snapshot'] ?? null);
        $scope = Input::text($input['scope'] ?? 'mailbox');
        [$domain, $mailboxes] = match ($scope) {
            'domain' => $this->domainScope($snapshot, $input['domain'] ?? null),
            'mailbox' => $this->mailboxScope($snapshot, $input['mailboxes'] ?? null, $input['domain'] ?? null),
            default => throw new UserError('backup_scope_invalid'),
        };
        $components = $this->components($input['components'] ?? null);
        $missing = array_values(array_intersect($components, self::MAIL_COMPONENTS));
        if ($snapshot->mode() === 'config' && !empty($missing)) {
            throw new UserError('backup_components_no_mails', [htmlspecialchars(implode(', ', $missing))]);
        }
        $mailTarget = Input::text($input['mail_target'] ?? null) ?: 'folder';
        if (!in_array($mailTarget, self::MAIL_TARGETS, true)) {
            throw new UserError('backup_mail_target_invalid');
        }
        $job = new Job('restore');
        $job->step = 'files';
        $job->domain = $domain;
        $job->requestedBy = (string)$this->context->username;
        $job->params = [
            'snapshot' => $snapshot->id,
            'snapshot_time' => $snapshot->time,
            'scope' => $scope,
            'domain' => $domain,
            'mailboxes' => $mailboxes,
            'components' => $components,
            'mail_target' => $mailTarget,
            'requested_role' => $this->context->hasAdminRights() ? 'admin' : 'domainadmin',
            'create_domain' => (int)!$this->jobs->isDomain($domain),
            'create_mailboxes' => array_values(array_filter($mailboxes, fn(string $username): bool => $this->jobs->mailboxDomain($username) === null)),
        ];
        if (!$this->context->hasAdminRights()) {
            $job->params['acl'] = array_combine(self::RESTORE_ACL, array_map(fn(string $acl): int => (int)$this->context->hasAcl($acl), self::RESTORE_ACL));
            $job->params['allowed_domains'] = array_values(array_unique(array_merge([$domain], $this->jobs->aliasDomainsOf($domain))));
        }
        $domainAdmin = !$this->context->hasAdminRights();
        $this->jobs->insertExclusive($job, function (array $active) use ($mailboxes, $domain, $domainAdmin): void {
            foreach ($active as $other) {
                if ($other->type !== 'restore') {
                    continue;
                }
                // a domain admin has one restore per domain at a time
                if ($domainAdmin && strtolower($other->domain) === $domain) {
                    throw new UserError('backup_restore_domain_active', [htmlspecialchars($domain)]);
                }
                $both = array_intersect($mailboxes, $other->mailboxes());
                if (!empty($both)) {
                    throw new UserError('backup_restore_active', [htmlspecialchars((string)reset($both))]);
                }
            }
        });
        return $job;
    }

    /**
     * Cancels a queued or running job (the restic runner stops at its next check): admins any, domain admins the
     * restores of their domains that domain admins requested. A restore in the dovecot step (mails, mails_running) runs
     * to its end.
     *
     * @throws UserError
     */
    public function cancel(int $id): Job
    {
        if ($this->context->isReadOnlyApi()) {
            throw new AccessDenied();
        }
        $job = $this->jobs->find($id);
        // domain admins cancel only restores domain admins requested
        if ($job === null || !$this->visible($job) || (!$this->context->hasAdminRights() && ($job->params['requested_role'] ?? '') !== 'domainadmin')) {
            throw new AccessDenied();
        }
        if (!$job->isActive() || ($job->type === 'restore' && in_array($job->step, ['mails', 'mails_running'], true)) || !$this->jobs->cancel($id)) {
            throw new UserError('backup_job_not_cancellable', [$id]);
        }
        $job->state = 'cancelled';
        return $job;
    }

    /**
     * Snapshots, newest first. Domain admins get those holding one of their domains (see mayUse()), with id, short_id,
     * time and those domains.
     */
    public function snapshots(): array
    {
        if ($this->context->hasAdminRights()) {
            return array_map(fn(Snapshot $snapshot): array => $snapshot->toArray(), $this->snapshots->all());
        }
        if (!$this->context->isDomainAdmin()) {
            return [];
        }
        $snapshots = [];
        // the domains with their creation time come from the snapshot row, the content is not needed here
        foreach ($this->snapshots->all() as $snapshot) {
            $domains = array_values(array_filter($snapshot->domainList(), fn(string $domain): bool => $this->mayUse($snapshot, $domain)));
            if (!empty($domains)) {
                $snapshots[] = ['id' => $snapshot->id, 'short_id' => $snapshot->shortId, 'time' => $snapshot->time, 'domains' => $domains,
                    'mode' => $snapshot->mode()];
            }
        }
        return $snapshots;
    }

    /**
     * Domains of a snapshot with their state now: GET backup-snapshot-content/<snapshot>. `available` 0: the snapshot has
     * no content (not made by this installation), the list comes from its manifest and only existing mailboxes can be restored. A domain
     * of the manifest missing in the content (its export failed) lists its current mailboxes as well.
     *
     * @return array{snapshot: string, time: int, available: int, domains: array[]}|array{} [] when not found or not visible
     */
    public function content(mixed $snapshotId): array
    {
        $snapshot = $this->visibleSnapshot($snapshotId);
        if ($snapshot === null) {
            return [];
        }
        $visible = array_values(array_filter($snapshot->domainList(), fn(string $domain): bool => $this->mayUse($snapshot, $domain)));
        [$existing, $usernames] = $this->jobs->domainsAndUsernames($visible);
        $domains = [];
        foreach ($visible as $domain) {
            $current = $usernames[$domain] ?? [];
            $mailboxes = $snapshot->mailboxesOf($domain) ?? $current;
            $domains[] = ['domain' => $domain, 'exists' => (int)isset($existing[$domain]), 'mailboxes' => count($mailboxes),
                'missing' => count(array_diff_key($mailboxes, $current))];
        }
        return ['snapshot' => $snapshot->id, 'time' => $snapshot->time, 'available' => (int)($snapshot->content !== null), 'domains' => $domains];
    }

    /**
     * Search in a snapshot: GET backup-snapshot-search/<snapshot>/<term>. Matches domain names and mailboxes by local
     * part, address or name ("x@": local part, "@d": domain, "x@d": address), with the rules of content(); at most
     * SEARCH_LIMIT mailboxes. A matching domain comes with all its mailboxes.
     *
     * @return array{domains: array[], truncated: int}|array{} [] when the snapshot is not found or not visible
     */
    public function search(mixed $snapshotId, mixed $term): array
    {
        $snapshot = $this->visibleSnapshot($snapshotId);
        $term = mb_strtolower((string)Input::text($term));
        if ($snapshot === null) {
            return [];
        }
        if (trim($term, '@') === '') {
            return ['domains' => [], 'truncated' => 0];
        }
        $domains = [];
        $hits = 0;
        $truncated = 0;
        [$local, $at, $domainTerm] = str_contains($term, '@') ? [strstr($term, '@', true), true, substr((string)strstr($term, '@'), 1)] : [$term, false, ''];
        foreach ($snapshot->domainList() as $domain) {
            if (!$this->mayUse($snapshot, $domain)) {
                continue;
            }
            $domainMatch = $at ? ($domainTerm !== '' && str_contains($domain, $domainTerm)) : str_contains($domain, $term);
            if ($at && $domainTerm !== '' && !$domainMatch) {
                continue;
            }
            $current = $this->jobs->mailboxNames($domain);
            $mailboxes = [];
            foreach (($snapshot->mailboxesOf($domain) ?? $current) as $username => $name) {
                $username = (string)$username;
                $part = (string)strstr($username, '@', true);
                $match = match (true) {
                    // "x@d": the address, "x@": the local part, "@d": every mailbox of the matching domains
                    $at && $local !== '' && $domainTerm !== '' => str_contains($username, $term),
                    $at && $local !== '' => str_contains($part, $local),
                    $at => true,
                    default => $domainMatch || str_contains($username, $term) || str_contains(mb_strtolower($name), $term),
                };
                if (!$match) {
                    continue;
                }
                if ($hits >= self::SEARCH_LIMIT) {
                    $truncated = 1;
                    break;
                }
                $hits++;
                $mailboxes[] = ['username' => $username, 'name' => $name, 'exists' => (int)isset($current[$username])];
            }
            // a domain without matching mailboxes only for a search by domain
            if (!empty($mailboxes) || ($domainMatch && !($at && $local !== '') && !$truncated)) {
                $domains[] = ['domain' => $domain, 'exists' => (int)$this->jobs->isDomain($domain), 'mailboxes' => $mailboxes];
            }
            if ($truncated) {
                break;
            }
        }
        return ['domains' => $domains, 'truncated' => $truncated];
    }

    /**
     * Mailboxes of a domain in a snapshot with their state now: GET backup-snapshot-content/<snapshot>/<domain>
     *
     * @return array{domain: string, exists: int, mailboxes: array[]}|array{} [] when not found or not visible
     */
    public function domainContent(mixed $snapshotId, mixed $domain): array
    {
        $snapshot = $this->visibleSnapshot($snapshotId);
        $domain = strtolower((string)Input::text($domain));
        if ($snapshot === null || !$snapshot->hasDomain($domain) || !$this->mayUse($snapshot, $domain)) {
            return [];
        }
        $current = $this->jobs->mailboxNames($domain);
        $mailboxes = [];
        foreach (($snapshot->mailboxesOf($domain) ?? $current) as $username => $name) {
            $mailboxes[] = ['username' => (string)$username, 'name' => $name, 'exists' => (int)isset($current[$username])];
        }
        return ['domain' => $domain, 'exists' => (int)$this->jobs->isDomain($domain), 'mailboxes' => $mailboxes];
    }

    /**
     * State of the backup for admins: settings state, runner heartbeat, repository, newest job of each type, queued and
     * running jobs, the next scheduled run. [] for everybody else.
     */
    public function status(?int $now = null): array
    {
        if (!$this->context->hasAdminRights()) {
            return [];
        }
        $now ??= time();
        $settings = $this->settings->load();
        $seen = ($settings->get('state_runner_seen') === '') ? null : (int)$settings->get('state_runner_seen');
        $snapshots = $this->snapshots->all();
        $latest = $this->jobs->latestOfEachType();
        $stats = json_decode($settings->get('state_repo_stats'), true);
        return [
            'enabled' => (int)$settings->get('enabled'),
            'target' => $settings->get('target'),
            'configured' => (int)$settings->configured(),
            'password_confirmed' => (int)$settings->get('password_confirmed'),
            'initialized' => (int)$settings->get('state_initialized'),
            // the runner cannot decrypt the stored credentials (e.g. a database restored on another server): enter them again
            'secrets_error' => ($settings->get('state_secrets_error') === '') ? null : $settings->get('state_secrets_error'),
            // RESTIC_BACKUP_MODE (null until the runner reported it)
            'mode' => ($settings->get('state_backup_mode') === '') ? null : $settings->get('state_backup_mode'),
            'runner_seen' => $seen,
            'runner_age' => ($seen === null) ? null : max(0, $now - $seen),
            'runner_ok' => (int)($seen !== null && $now - $seen <= self::RUNNER_TIMEOUT),
            'last_success' => ($settings->get('state_last_success') === '') ? null : (int)$settings->get('state_last_success'),
            'repo_size' => ($settings->get('state_repo_size') === '') ? null : (int)$settings->get('state_repo_size'),
            'repo_stats' => is_array($stats) ? $stats : null,
            'snapshots' => count($snapshots),
            'latest_snapshot' => empty($snapshots) ? null : $snapshots[0]->time,
            'next_run' => $this->nextRun($settings, $now),
            // the longest time between two scheduled backups (seconds): a later last success is overdue
            'schedule_gap' => $settings->scheduleGap(),
            'jobs' => array_map(fn(string $type): ?array => isset($latest[$type]) ? $latest[$type]->toArray() : null,
                array_combine(Job::TYPES, Job::TYPES)),
            'active' => array_map(fn(Job $job): array => $job->toArray(), $this->jobs->active()),
        ];
    }

    /**
     * Unix time of the next scheduled backup, null while the schedule is off. Like the runner: the schedule is armed
     * (state_schedule_since/state_schedule) when it is switched on or changed, a run is due at the first schedule time
     * after that; a due run without a scheduled backup since its time is the next one (now).
     */
    private function nextRun(Settings $settings, int $now): ?int
    {
        if ($settings->get('enabled') !== '1' || $settings->get('password_confirmed') !== '1' || !$settings->configured()) {
            return null;
        }
        $slots = $settings->scheduleSlots();
        try {
            $zone = new DateTimeZone($this->timezone);
        } catch (\Exception) {
            $zone = new DateTimeZone('UTC');
        }
        $today = (new DateTimeImmutable('@' . $now))->setTimezone($zone);
        // the last schedule time up to now, and the one after it
        $due = self::slot($slots, $today, -1);
        $next = self::slot($slots, $today, 1);
        $since = $settings->get('state_schedule_since');
        if ($due === null || $since === '' || $settings->get('state_schedule') !== $settings->get('schedule') || (int)$since > $due) {
            return $next;
        }
        return $this->jobs->scheduledSince($due) ? $next : $due;
    }

    /**
     * The newest schedule time up to $now ($direction -1) or the first one after it (1) as Unix time, null without
     * slots ([weekday, hour, minute], sorted)
     */
    private static function slot(array $slots, DateTimeImmutable $now, int $direction): ?int
    {
        for ($i = 0; $i <= 7; $i++) {
            $day = $now->modify(sprintf('%+d day', $direction * $i));
            foreach (($direction < 0) ? array_reverse($slots) : $slots as [$weekday, $hour, $minute]) {
                if ($weekday !== (int)$day->format('w')) {
                    continue;
                }
                $slot = $day->setTime($hour, $minute)->getTimestamp();
                if (($direction < 0) ? $slot <= $now->getTimestamp() : $slot > $now->getTimestamp()) {
                    return $slot;
                }
            }
        }
        return null;
    }

    /** @throws AccessDenied unless the caller may request restores */
    private function requireRestorer(): void
    {
        if ($this->context->isReadOnlyApi() || !($this->context->hasAdminRights() || $this->context->isDomainAdmin())) {
            throw new AccessDenied();
        }
    }

    /** Admins every job; domain admins the restores of their domains requested after the domain was created */
    private function visible(Job $job): bool
    {
        if ($this->context->hasAdminRights()) {
            return true;
        }
        if (!$this->context->isDomainAdmin() || $job->type !== 'restore' || $job->domain === '' || !$this->context->canAccessDomain($job->domain)) {
            return false;
        }
        $created = $this->jobs->domainCreated(strtolower($job->domain));
        return $created !== null && (int)$job->created >= $created;
    }

    /**
     * A value of a job as a domain admin sees it: e-mail addresses outside $domains and repository details become "…"
     *
     * @param string[] $domains
     */
    private static function restricted(mixed $value, array $domains): mixed
    {
        if (is_array($value)) {
            $restricted = [];
            foreach ($value as $key => $item) {
                $restricted[is_string($key) ? (string)self::restricted($key, $domains) : $key] = self::restricted($item, $domains);
            }
            return $restricted;
        }
        if (!is_string($value)) {
            return $value;
        }
        $value = preg_replace(['~\b(?:s3|sftp|rest|b2|azure|gs|swift|rclone):\S+~i', '~\bhttps?://\S+~i', '~/repository\S*~'], '…', $value);
        return preg_replace_callback('/[^\s@<>"\'(),;:\[\]]+@([a-z0-9.-]+\.[a-z]{2,})/i',
            fn(array $match): string => in_array(strtolower($match[1]), $domains, true) ? $match[0] : '…', (string)$value);
    }

    /** @throws UserError backup_snapshot_not_found (also for snapshots a domain admin may not use) */
    private function snapshot(mixed $value): Snapshot
    {
        $id = (string)Input::text($value);
        $snapshot = ($id === '') ? null : $this->snapshots->find($id);
        if ($snapshot === null || (!$this->context->hasAdminRights() && empty(array_filter($snapshot->domainList(),
            fn(string $domain): bool => $this->mayUse($snapshot, $domain))))) {
            throw new UserError('backup_snapshot_not_found', [htmlspecialchars($id)]);
        }
        return $snapshot;
    }

    private function visibleSnapshot(mixed $value): ?Snapshot
    {
        try {
            return ($this->context->hasAdminRights() || $this->context->isDomainAdmin()) ? $this->snapshot($value) : null;
        } catch (UserError) {
            return null;
        }
    }

    /**
     * Admins every domain of the snapshot; domain admins the domains they administer now, when the snapshot holds this
     * domain and not a former one of the same name: its recorded creation time is the domain's (snapshots without it:
     * made after the domain was created)
     */
    private function mayUse(Snapshot $snapshot, string $domain): bool
    {
        if ($this->context->hasAdminRights()) {
            return true;
        }
        if (!$this->context->isDomainAdmin()) {
            return false;
        }
        $this->domainState[$domain] ??= [$this->context->canAccessDomain($domain), $this->jobs->domainCreated($domain)];
        [$access, $created] = $this->domainState[$domain];
        if (!$access) {
            return false;
        }
        $recorded = $snapshot->createdOf($domain);
        return $created !== null && (($recorded !== null) ? $recorded === $created : $snapshot->time > $created);
    }

    /**
     * @return array{0: string, 1: string[]} the domain and its mailboxes in the snapshot (now, for snapshots without content)
     * @throws UserError
     */
    private function domainScope(Snapshot $snapshot, mixed $value): array
    {
        $domain = strtolower((string)Input::text($value));
        if ($domain !== '' && !$this->context->hasAdminRights() && !$this->context->canAccessDomain($domain)) {
            throw new AccessDenied();
        }
        $exists = $domain !== '' && $this->jobs->isDomain($domain);
        // a deleted domain is created from the snapshot's export: only when the content lists it
        if (!$exists && !($this->context->hasAdminRights() && $snapshot->mailboxesOf($domain) !== null)) {
            throw new UserError('domain_not_found', [htmlspecialchars((string)Input::text($value))]);
        }
        $this->requireDomain($snapshot, $domain);
        $listed = $snapshot->mailboxesOf($domain);
        $mailboxes = ($listed === null) ? $this->jobs->mailboxesOf($domain) : array_map('strval', array_keys($listed));
        // a deleted domain without mailboxes comes back with its aliases and settings
        if (empty($mailboxes) && $exists) {
            throw new UserError('backup_mailboxes_empty');
        }
        return [$domain, $mailboxes];
    }

    /**
     * @return array{0: string, 1: string[]} the domain of the mailboxes and the mailboxes
     * @throws UserError
     */
    private function mailboxScope(Snapshot $snapshot, mixed $value, mixed $domainValue): array
    {
        $mailboxes = array_values(array_unique(array_map('strtolower', self::values($value))));
        if (empty($mailboxes)) {
            throw new UserError('backup_mailboxes_empty');
        }
        if (count($mailboxes) > self::MAX_MAILBOXES) {
            throw new UserError('backup_entries_exceeded', [self::MAX_MAILBOXES]);
        }
        $domain = strtolower((string)Input::text($domainValue));
        foreach ($mailboxes as $username) {
            if (str_contains($username, '@') && !$this->context->hasAdminRights() && !$this->context->canAccessDomain(self::domainOf($username))) {
                throw new AccessDenied();
            }
            // a deleted mailbox can be restored when the snapshot knows it
            $mailboxDomain = $this->jobs->mailboxDomain($username) ?? (isset($snapshot->mailboxesOf(self::domainOf($username))[$username]) ? self::domainOf($username) : null);
            if ($mailboxDomain === null) {
                throw new UserError('backup_mailbox_not_found', [htmlspecialchars($username)]);
            }
            if ($domain !== '' && $mailboxDomain !== $domain) {
                throw new UserError('backup_mailboxes_one_domain');
            }
            $domain = $mailboxDomain;
        }
        if (!$this->jobs->isDomain($domain) && !$this->context->hasAdminRights()) {
            throw new AccessDenied();
        }
        $this->requireDomain($snapshot, $domain);
        return [$domain, $mailboxes];
    }

    /** @throws UserError the snapshot does not hold the domain, or the caller may not restore into it */
    private function requireDomain(Snapshot $snapshot, string $domain): void
    {
        if (!$this->context->hasAdminRights() && !$this->context->canAccessDomain($domain)) {
            throw new AccessDenied();
        }
        if ($snapshot->knowsDomains() && !$snapshot->hasDomain($domain)) {
            throw new UserError('backup_domain_not_in_snapshot', [htmlspecialchars($domain)]);
        }
        if (!$this->mayUse($snapshot, $domain)) {
            throw new UserError('backup_snapshot_not_found', [htmlspecialchars($snapshot->shortId)]);
        }
    }

    /**
     * @return string[] the requested components in their order
     * @throws UserError
     */
    private function components(mixed $value): array
    {
        $requested = array_values(array_unique(self::values($value)));
        if (empty($requested)) {
            throw new UserError('backup_components_invalid', ['']);
        }
        foreach ($requested as $component) {
            if (!in_array($component, self::COMPONENTS, true)) {
                throw new UserError('backup_components_invalid', [htmlspecialchars($component)]);
            }
            if (!$this->context->hasAdminRights() && isset(self::COMPONENT_ACL[$component]) && !$this->context->hasAcl(self::COMPONENT_ACL[$component])) {
                throw new UserError('backup_component_denied', [$component]);
            }
            if (isset(self::REQUIRES[$component]) && !in_array(self::REQUIRES[$component], $requested, true)) {
                throw new UserError('backup_component_requires', [$component, self::REQUIRES[$component]]);
            }
        }
        return array_values(array_intersect(self::COMPONENTS, $requested));
    }

    private static function domainOf(string $address): string
    {
        return strtolower(substr((string)strrchr($address, '@'), 1));
    }

    /**
     * Values of a list field: an array (multi-select, JSON) or a string separated by line breaks, commas or spaces.
     *
     * @return string[]
     */
    private static function values(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\s,;]+/', (string)Input::text($value));
        return array_values(array_filter(array_map(fn(mixed $v): string => is_scalar($v) ? trim((string)$v) : '', $values), 'strlen'));
    }
}
