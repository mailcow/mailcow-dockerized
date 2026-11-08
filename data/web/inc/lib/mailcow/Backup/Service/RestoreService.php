<?php

declare(strict_types=1);

namespace Mailcow\Backup\Service;

use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Model\RestoreRun;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Repository\RestoreRepository;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Dovecot\Service\DovecotService;
use Mailcow\Sogo\Service\SogoService;
use PDOException;

/**
 * Merges the settings of a mailbox from a snapshot's export into the mailbox as it is now (the runner of a restore job
 * posts one mailbox at a time to backup.php); a mailbox deleted since the snapshot is created first. The aliases of a
 * domain restore come in parts before the mailboxes (mergeAliases()).
 *
 * Only what is missing is added, nothing is deleted; `settings` and `password` overwrite. Components and limits come
 * from the job in the database, not from the request: the job must be running and hold the mailbox. A domain admin's
 * restore keeps to his ACL at the time of the request, to his rights now for groups and to the job's domain (and its
 * alias domains). A named lock per job and mailbox makes a retried request wait for the first one.
 *
 * The components run in one transaction per mailbox, each in a savepoint: a database error drops only that component.
 * Group memberships go through the group module afterwards (its own transaction and effects, no group templates),
 * then the SOGo view and the Dovecot auth cache of the touched mailboxes are refreshed.
 */
class RestoreService
{
    /** Components merged here, in this order */
    public const COMPONENTS = ['aliases', 'sender_acl', 'mail_policies', 'retention_rules', 'filters', 'spam', 'bcc', 'syncjobs', 'app_passwords',
        'tfa', 'settings', 'password', 'groups'];
    /** Aliases per restore_aliases request */
    public const MAX_ALIASES = 500;
    private const SPECIAL_GOTOS = ['null@localhost', 'spam@localhost', 'ham@localhost'];
    /** Attributes `settings` leaves alone: the password component's, the storage format, the IdP's */
    private const KEEP_ATTRIBUTES = ['passwd_update', 'mailbox_format', 'attribute_hash'];
    /** Attributes a domain admin needs an ACL for */
    private const ATTRIBUTE_ACL = ['imap_access' => 'protocol_access', 'pop3_access' => 'protocol_access', 'smtp_access' => 'protocol_access',
        'sieve_access' => 'protocol_access', 'eas_access' => 'protocol_access', 'dav_access' => 'protocol_access',
        'relayhost' => 'mailbox_relayhost', 'sogo_access' => 'sogo_access', 'tls_enforce_in' => 'tls_policy', 'tls_enforce_out' => 'tls_policy',
        'quarantine_notification' => 'quarantine_notification', 'quarantine_category' => 'quarantine_category'];
    /** Columns a restored row never takes from the backup */
    private const NEW_ROW = ['id', 'created', 'modified'];
    /** @var array<string, int> primary domain => aliases it may still get, counted once per request */
    private array $aliasesLeft = [];
    private const MIB = 1048576;

    public function __construct(private RestoreRepository $repo, private JobRepository $jobs, private GroupMembers $groups,
        private SogoService $sogo, private DovecotService $dovecot, private LegacyDomain $legacy, private bool $externalGotoDisabled = false)
    {
    }

    /**
     * Action `restore`: one mailbox of a running restore job. A mailbox in params.create_mailboxes that does not exist
     * is created from the backup first (result key `mailbox`); when that fails, nothing else is merged.
     *
     * @param mixed $data the mailbox's section of the domain export
     * @return array<string, array{added: int, skipped: int, errors: string[]}> per component of the job merged here
     * @throws UserError
     */
    public function merge(mixed $jobId, mixed $username, mixed $data): array
    {
        $job = $this->runningRestore($jobId);
        $username = strtolower((string)Input::text($username));
        if ($username === '' || !in_array($username, array_map('strtolower', $job->mailboxes()), true)) {
            throw new AccessDenied();
        }
        if (!is_array($data) || !is_array($data['mailbox'] ?? null) || strtolower((string)($data['mailbox']['username'] ?? '')) !== $username) {
            throw new UserError('backup_export_invalid');
        }
        return $this->locked($job, $username, fn(): array => $this->mergeMailbox($job, $username, $data));
    }

    /**
     * Action `restore_aliases`: a part of the aliases of the domain (scope domain), before its mailboxes. Missing aliases
     * are created with their gotos that are valid now; existing ones are left to the mailboxes' merge.
     *
     * @return array{aliases: array{added: int, skipped: int, errors: string[]}}
     * @throws UserError
     */
    public function mergeAliases(mixed $jobId, mixed $aliases): array
    {
        $job = $this->runningRestore($jobId);
        if (($job->params['scope'] ?? '') !== 'domain' || !in_array('aliases', $job->components(), true)) {
            throw new AccessDenied();
        }
        if (!is_array($aliases)) {
            throw new UserError('backup_export_invalid');
        }
        if (count($aliases) > self::MAX_ALIASES) {
            throw new UserError('backup_entries_exceeded', [self::MAX_ALIASES]);
        }
        return $this->locked($job, '', function () use ($job, $aliases): array {
            if ($this->permissionRevoked($job)) {
                return ['aliases' => ['added' => 0, 'skipped' => 0, 'errors' => ['permission revoked']]];
            }
            $domain = strtolower((string)$job->domain);
            $run = new RestoreRun('', $domain, [], $job->params, [], $this->allowedDomains($job, $domain), $job->requestedBy);
            $run->start('aliases');
            try {
                $this->repo->inTransaction(fn() => $this->aliases($run, array_values(array_filter($aliases, 'is_array'))));
            } catch (PDOException $e) {
                $run->failed('aliases', 'database error ' . $e->getCode());
            }
            $this->sogo->refreshStaticView($run->sogoUsers);
            return $run->results;
        });
    }

    /** The mailbox's merge under the lock: create it if needed, then the components */
    private function mergeMailbox(Job $job, string $username, array $data): array
    {
        if ($this->permissionRevoked($job)) {
            return ['mailbox' => ['added' => 0, 'skipped' => 0, 'errors' => ['permission revoked'], 'created' => 0]];
        }
        $domain = strtolower((string)$job->domain);
        $run = new RestoreRun($username, $domain, $this->mailbox($username) ?? [], $job->params, $data, $this->allowedDomains($job, $domain), $job->requestedBy);
        $results = [];
        if (in_array($username, array_map('strtolower', $job->createMailboxes()), true)) {
            $created = ['added' => 0, 'skipped' => 0, 'errors' => [], 'created' => 0];
            if (!empty($run->mailbox)) {
                $created['skipped'] = 1;
            } else {
                $error = $this->createMailbox($run);
                if ($error !== null) {
                    $created['errors'][] = $error;
                    return ['mailbox' => $created];
                }
                $created = ['added' => 1, 'skipped' => 0, 'errors' => [], 'created' => 1];
                $run = new RestoreRun($username, $domain, $this->mailbox($username) ?? [], $job->params, $data, $run->domains, $job->requestedBy);
                $run->created = true;
                $run->sogo($username);
                $run->auth($username);
            }
            $results['mailbox'] = $created;
        }
        if (empty($run->mailbox)) {
            throw new UserError('backup_mailbox_not_found', [htmlspecialchars($username)]);
        }
        $components = array_values(array_intersect(self::COMPONENTS, $job->components()));
        $this->repo->inTransaction(function () use ($run, $components): void {
            foreach (array_diff($components, ['groups']) as $component) {
                $run->start($component);
                try {
                    $this->repo->savepoint($component, fn() => $this->component($run, $component));
                } catch (PDOException $e) {
                    $run->failed($component, 'database error ' . $e->getCode());
                }
            }
        });
        if (in_array('groups', $components, true)) {
            $run->start('groups');
            $this->groups($run);
        }
        $this->sogo->refreshStaticView($run->sogoUsers);
        $this->dovecot->flushAuthCache($run->authUsers);
        return $results + $run->results;
    }

    /**
     * Creates a mailbox deleted since the snapshot from its exported row, with the side effects of the legacy mailbox
     * add (own alias, quota rows, user ACL, tags) and within the domain's limits
     *
     * @return ?string null on success, else why it was not created
     */
    private function createMailbox(RestoreRun $run): ?string
    {
        $backup = self::decode($run->data['mailbox']);
        $username = $run->username;
        [$local, $domain] = array_pad(explode('@', $username, 2), 2, '');
        if (!filter_var($username, FILTER_VALIDATE_EMAIL) || $domain !== $run->domain) {
            return 'invalid mailbox';
        }
        $domainRow = $this->repo->rows('domain', ['domain' => $domain])[0] ?? null;
        if ($domainRow === null) {
            return 'the domain does not exist';
        }
        $kind = $this->repo->addressKind($username);
        // deleting a forwarding mailbox leaves its address as an alias to the forward targets: that one is taken back
        $leftover = ($kind === 'alias') ? $this->leftoverAlias($run) : null;
        if ($kind !== null && $leftover === null) {
            return "the address is in use ($kind)";
        }
        $quota = max(0, (int)($backup['quota'] ?? 0));
        if ((string)($backup['kind'] ?? '') === '') {
            if ($this->repo->mailboxCount($domain) >= (int)($domainRow['mailboxes'] ?? 0)) {
                return 'mailbox limit of the domain reached';
            }
            $error = $this->quotaError($run, $quota);
            if ($error !== null) {
                return "quota: $error";
            }
        }
        $this->repo->inTransaction(function () use ($run, $backup, $username, $local, $domain, $leftover): void {
            $this->repo->insert('mailbox', ['username' => $username, 'local_part' => $local, 'domain' => $domain]
                + array_diff_key($backup, array_flip(['modified', 'tags'])));
            $this->repo->resetQuota($username);
            if ($leftover !== null) {
                $this->repo->update('alias', ['id' => (int)$leftover['id']], ['goto' => $username, 'active' => (int)($backup['active'] ?? 1)]);
            } elseif (empty($this->repo->rows('alias', ['address' => $username]))) {
                $this->repo->insert('alias', ['address' => $username, 'goto' => $username, 'domain' => $domain,
                    'active' => (int)($backup['active'] ?? 1)]);
            }
            // the user ACL of the backup (a domain admin's restore: defaults for what he may not grant), else the defaults
            if (empty($this->repo->rows('user_acl', ['username' => $username]))) {
                $acl = is_array($run->data['user_acl'] ?? null) ? self::decode($run->data['user_acl']) : [];
                $this->repo->insert('user_acl', ['username' => $username] + array_diff_key($acl, array_flip($this->lockedUserAcl($run))));
            }
            foreach (array_unique(array_filter(array_map(fn(mixed $tag): string => is_scalar($tag) ? trim((string)$tag) : '',
                (array)($run->data['mailbox']['tags'] ?? [])), 'strlen')) as $tag) {
                $this->repo->insert('tags_mailbox', ['username' => $username, 'tag_name' => $tag]);
            }
        });
        return null;
    }

    /**
     * A domain admin's restore stops when he lost what it was requested with: his account, the domain, or an ACL he had
     * then (columns of da_acl)
     */
    private function permissionRevoked(Job $job): bool
    {
        if (($job->params['requested_role'] ?? '') === 'admin') {
            return false;
        }
        $username = $job->requestedBy;
        if (empty($this->repo->rows('admin', ['username' => $username, 'superadmin' => 0, 'active' => 1]))
            || empty($this->repo->rows('domain_admins', ['username' => $username, 'domain' => strtolower($job->domain), 'active' => 1]))) {
            return true;
        }
        $acl = $this->repo->rows('da_acl', ['username' => $username])[0] ?? [];
        foreach ((array)($job->params['acl'] ?? []) as $name => $flag) {
            if ((int)$flag === 1 && array_key_exists((string)$name, $acl) && (int)$acl[$name] !== 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * User ACL columns a domain admin may not grant: those of da_acl he does not have himself (legacy acl('get', 'user'));
     * none for admins
     *
     * @return string[]
     */
    private function lockedUserAcl(RestoreRun $run): array
    {
        if ($run->byAdmin()) {
            return [];
        }
        return array_values(array_filter(array_diff($this->repo->columns('da_acl'), ['username']), fn(string $column): bool => !$run->may($column)));
    }

    /** @throws UserError backup_job_busy while another request merges the same mailbox of the job */
    private function locked(Job $job, string $username, callable $work): array
    {
        $result = $this->repo->locked($job->id . ':' . $username, $work);
        return is_array($result) ? $result : throw new UserError('backup_job_busy');
    }

    /** @throws UserError backup_job_not_running */
    private function runningRestore(mixed $jobId): Job
    {
        $id = Input::intInRange($jobId, 1, PHP_INT_MAX);
        $job = ($id === null) ? null : $this->jobs->find($id);
        if ($job === null || $job->type !== 'restore' || $job->state !== 'running') {
            throw new UserError('backup_job_not_running');
        }
        return $job;
    }

    /** @return string[] domains whose objects the restore may change: [] = all (admins), else the job's domain and its alias domains */
    private function allowedDomains(Job $job, string $domain): array
    {
        return (($job->params['requested_role'] ?? '') === 'admin') ? [] : array_values(array_unique(array_merge([$domain], $this->repo->aliasDomainsOf($domain))));
    }

    private function component(RestoreRun $run, string $component): void
    {
        match ($component) {
            'aliases' => $this->aliases($run, []),
            'sender_acl' => $this->senderAcl($run),
            'mail_policies' => $this->policies($run, 'mail_policies', 'mail_policies', 'mail_policy', 'mail_policy_member', 'policy_id'),
            'retention_rules' => $this->policies($run, 'retention_rules', 'retention', 'retention_rule', 'retention_rule_member', 'rule_id'),
            'filters' => $this->filters($run),
            'spam' => $this->spam($run),
            'bcc' => $this->bcc($run),
            'syncjobs' => $this->syncJobs($run),
            'app_passwords' => $this->appPasswords($run),
            'tfa' => $this->tfa($run),
            'settings' => $this->settings($run),
            'password' => $this->password($run),
        };
    }

    /**
     * Aliases whose goto named the mailbox, or a part of the domain's aliases: a missing alias is created with its gotos
     * that are valid now, an existing one gets the mailbox back into its goto. Group addresses belong to their group
     * and are left alone.
     */
    private function aliases(RestoreRun $run, array $domainAliases): void
    {
        if ($run->created) {
            $this->ownAlias($run);
        }
        $seen = [];
        $created = array_map('strtolower', (array)($run->params['create_mailboxes'] ?? []));
        foreach (array_merge($domainAliases, $run->rows('aliases')) as $row) {
            $row = self::decode($row);
            $address = strtolower(trim((string)($row['address'] ?? '')));
            $gotos = ExportService::gotos(strtolower((string)($row['goto'] ?? '')));
            // the own alias of a mailbox comes with the mailbox (ownAlias())
            if ($address === '' || isset($seen[$address]) || $gotos === [$address] || in_array($address, $created, true)) {
                continue;
            }
            $seen[$address] = true;
            if (($row['group_id'] ?? null) !== null) {
                $run->skipped('aliases');
                continue;
            }
            $domain = self::domainOf($address);
            if (!$run->mayChangeDomain($domain)) {
                $run->error('aliases', "$address: outside the domain of the restore");
                continue;
            }
            $current = $this->repo->rows('alias', ['address' => $address])[0] ?? null;
            if ($current !== null) {
                $this->aliasGoto($run, $current, $gotos);
                continue;
            }
            // naming itself: the own alias of a deleted mailbox (forwarding with a copy), as an alias it would block the mailbox
            if (in_array($address, $gotos, true)) {
                $run->skipped('aliases');
                continue;
            }
            $this->createAlias($run, $row, $address, $domain, $gotos);
        }
    }

    /**
     * The alias at the address of a deleted mailbox when it is what the deletion left of the mailbox's forwarding: the
     * forward targets of the backup's own alias, without the mailbox. Any other alias (e.g. one added since) blocks.
     */
    private function leftoverAlias(RestoreRun $run): ?array
    {
        $backup = is_array($run->data['own_alias'] ?? null) ? self::decode($run->data['own_alias']) : [];
        $forward = array_unique(array_diff(ExportService::gotos(strtolower((string)($backup['goto'] ?? ''))), [$run->username]));
        $current = $this->repo->rows('alias', ['address' => $run->username])[0] ?? null;
        if ($current === null || empty($forward) || ($current['group_id'] ?? null) !== null) {
            return null;
        }
        $now = array_unique(ExportService::gotos(strtolower((string)$current['goto'])));
        sort($forward);
        sort($now);
        return ($now === $forward) ? $current : null;
    }

    /**
     * The own alias of a mailbox the restore created gets the forwarding of the backup: the gotos that are valid now, with
     * the mailbox itself when it kept a copy. Without a valid one it keeps delivering to the mailbox.
     */
    private function ownAlias(RestoreRun $run): void
    {
        $backup = is_array($run->data['own_alias'] ?? null) ? self::decode($run->data['own_alias']) : [];
        $gotos = ExportService::gotos(strtolower((string)($backup['goto'] ?? '')));
        $forward = array_values(array_diff($gotos, [$run->username]));
        $current = $this->repo->rows('alias', ['address' => $run->username])[0] ?? null;
        if (empty($forward) || $current === null) {
            return;
        }
        $valid = $this->validGotos($run, $run->username, $forward);
        if (empty($valid)) {
            return;
        }
        $goto = in_array($run->username, $gotos, true) ? array_merge([$run->username], $valid) : $valid;
        $this->repo->update('alias', ['id' => (int)$current['id']], ['goto' => implode(',', $goto)]);
        $run->added('aliases');
    }

    /** The mailbox goes back into the goto of an existing alias (never into a group address or null/spam/ham) */
    private function aliasGoto(RestoreRun $run, array $current, array $backupGotos): void
    {
        $gotos = ExportService::gotos((string)$current['goto']);
        $lower = array_map('strtolower', $gotos);
        if ($run->username === '' || !in_array($run->username, $backupGotos, true) || in_array($run->username, $lower, true) || ($current['group_id'] ?? null) !== null
            || !empty(array_intersect($lower, self::SPECIAL_GOTOS))) {
            $run->skipped('aliases');
            return;
        }
        $gotos[] = $run->username;
        $this->repo->update('alias', ['id' => (int)$current['id']], ['goto' => implode(',', $gotos)]);
        $run->added('aliases');
        $run->sogo($run->username);
    }

    /** Creates a missing alias with the checks of the legacy alias form */
    private function createAlias(RestoreRun $run, array $row, string $address, string $domain, array $backupGotos): void
    {
        if (!self::validAddress($address)) {
            $run->error('aliases', "$address: invalid address");
            return;
        }
        $primary = $this->repo->primaryDomain($domain);
        if ($primary === null) {
            $run->error('aliases', "$address: domain does not exist");
            return;
        }
        $kind = str_starts_with($address, '@') ? null : $this->repo->addressKind($address);
        if ($kind !== null) {
            $run->error('aliases', "$address: address is in use ($kind)");
            return;
        }
        $this->aliasesLeft[$primary] ??= $this->repo->aliasesLeft($primary);
        if ($this->aliasesLeft[$primary] <= 0) {
            $run->error('aliases', "$address: alias limit of the domain reached");
            return;
        }
        $gotos = $this->validGotos($run, $address, $backupGotos);
        if (empty($gotos)) {
            $run->error('aliases', "$address: no valid goto");
            return;
        }
        $values = array_diff_key($row, array_flip([...self::NEW_ROW, 'group_id']));
        $this->repo->insert('alias', ['address' => $address, 'goto' => implode(',', $gotos), 'domain' => $domain] + $values);
        $this->aliasesLeft[$primary]--;
        $run->added('aliases');
        $run->sogo(...array_filter($gotos, fn(string $goto): bool => $this->repo->addressKind($goto) === 'mailbox'));
    }

    /**
     * Gotos of a new alias that are valid now: null/spam/ham alone, local addresses that exist or that the restore creates
     * (no resources), external
     * ones unless external gotos are disabled (globally or by the domain admin's ACL). Dropped ones are reported.
     *
     * @return string[]
     */
    private function validGotos(RestoreRun $run, string $address, array $gotos): array
    {
        $special = array_intersect($gotos, self::SPECIAL_GOTOS);
        if (!empty($special)) {
            return (count($gotos) === 1) ? $gotos : [];
        }
        $valid = [];
        foreach (array_unique($gotos) as $goto) {
            if ($goto === $address || !filter_var($goto, FILTER_VALIDATE_EMAIL)) {
                $run->error('aliases', "$address: goto $goto dropped (invalid)");
                continue;
            }
            if ($this->repo->isLocalDomain(self::domainOf($goto))) {
                // mailboxes the restore creates count as existing (the domain's aliases come before its mailboxes)
                if (!in_array($this->repo->addressKind($goto), ['mailbox', 'alias'], true)
                    && !in_array($goto, array_map('strtolower', (array)($run->params['create_mailboxes'] ?? [])), true)) {
                    $run->error('aliases', "$address: goto $goto dropped (no mailbox or alias)");
                    continue;
                }
            } elseif ($this->externalGotoDisabled || !$run->may('alias_external_goto')) {
                $run->error('aliases', "$address: goto $goto dropped (external gotos are not allowed)");
                continue;
            }
            $valid[] = $goto;
        }
        return $valid;
    }

    /**
     * Send-as rights of the mailbox and rights of others to send as it, for addresses and mailboxes that exist now.
     * Rows of groups are rebuilt by the group module and left out.
     */
    private function senderAcl(RestoreRun $run): void
    {
        foreach ($run->rows('sender_acl') as $row) {
            if (($row['group_id'] ?? null) !== null) {
                continue;
            }
            $sendAs = strtolower(trim((string)($row['send_as'] ?? '')));
            $external = (int)($row['external'] ?? 0);
            $error = $this->sendAsError($run, $sendAs, $external);
            if ($error !== null) {
                $run->error('sender_acl', "$sendAs: $error");
            } elseif (!empty($this->repo->rows('sender_acl', ['logged_in_as' => $run->username, 'send_as' => $sendAs]))) {
                $run->skipped('sender_acl');
            } else {
                $this->repo->insert('sender_acl', ['logged_in_as' => $run->username, 'send_as' => $sendAs, 'external' => $external]);
                $run->added('sender_acl');
                $run->sogo($run->username);
            }
        }
        foreach ($run->rows('sender_acl_granted') as $row) {
            if (($row['group_id'] ?? null) !== null) {
                continue;
            }
            $login = strtolower(trim((string)($row['logged_in_as'] ?? '')));
            if (empty($this->repo->rows('mailbox', ['username' => $login]))) {
                $run->error('sender_acl', "$login: mailbox does not exist");
            } elseif (!$run->mayChangeDomain(self::domainOf($login))) {
                $run->error('sender_acl', "$login: outside the domain of the restore");
            } elseif (!empty($this->repo->rows('sender_acl', ['logged_in_as' => $login, 'send_as' => $run->username]))) {
                $run->skipped('sender_acl');
            } else {
                $this->repo->insert('sender_acl', ['logged_in_as' => $login, 'send_as' => $run->username, 'external' => 0]);
                $run->added('sender_acl');
                $run->sogo($login);
            }
        }
    }

    /** Why the mailbox may not send as this address (anymore), null when it may */
    private function sendAsError(RestoreRun $run, string $sendAs, int $external): ?string
    {
        if ($sendAs === '*') {
            return $run->byAdmin() ? null : 'only admins grant *';
        }
        $domain = self::domainOf($sendAs);
        if (!self::validAddress($sendAs)) {
            return 'invalid address';
        }
        if ($external === 1) {
            if (!$run->may('extend_sender_acl')) {
                return 'not allowed';
            }
            return $this->repo->domainExists($domain) ? 'domain is local now' : null;
        }
        if (!$run->mayChangeDomain($domain)) {
            return 'outside the domain of the restore';
        }
        if (str_starts_with($sendAs, '@')) {
            return $this->repo->domainExists($domain) ? null : 'domain does not exist';
        }
        return in_array($this->repo->addressKind($sendAs), ['mailbox', 'alias'], true) ? null : 'address does not exist';
    }

    /**
     * Assignments to mail policies or retention rules of the mailbox's domain that exist now (by id, else by name)
     *
     * @param string $key    the list in the backup and the component
     * @param string $acl    the domain admin ACL it needs
     * @param string $member the member table and its $column with the id
     */
    private function policies(RestoreRun $run, string $key, string $acl, string $table, string $member, string $column): void
    {
        $entries = $run->rows($key);
        if (!empty($entries) && !$run->may($acl)) {
            $run->error($key, 'not allowed');
            return;
        }
        foreach ($entries as $entry) {
            $name = (string)($entry['name'] ?? '');
            $target = $this->repo->rows($table, ['id' => (int)($entry['id'] ?? 0), 'domain' => $run->domain])[0]
                ?? $this->repo->rows($table, ['domain' => $run->domain, 'name' => $name])[0] ?? null;
            if ($target === null) {
                $run->error($key, "$name: does not exist");
            } elseif (!empty($this->repo->rows($member, [$column => (int)$target['id'], 'username' => $run->username]))) {
                $run->skipped($key);
            } else {
                $this->repo->insert($member, [$column => (int)$target['id'], 'username' => $run->username]);
                $run->added($key);
            }
        }
    }

    /**
     * mailcow filters (sieve_filters) missing with the same type and script; a restored filter is active only if it
     * was and the mailbox has no active filter of its type now.
     */
    private function filters(RestoreRun $run): void
    {
        foreach ($run->rows('sieve_filters') as $row) {
            $type = (string)($row['filter_type'] ?? '');
            $script = (string)($row['script_data'] ?? '');
            $description = (string)($row['script_desc'] ?? '');
            if (!in_array($type, ['prefilter', 'postfilter'], true) || trim($script) === '') {
                $run->error('filters', "$description: invalid filter");
                continue;
            }
            $current = $this->repo->rows('sieve_filters', ['username' => $run->username, 'filter_type' => $type]);
            if (!empty(array_filter($current, fn(array $filter): bool => $filter['script_data'] === $script))) {
                $run->skipped('filters');
                continue;
            }
            $active = ($row['script_name'] ?? '') === 'active' && empty(array_filter($current, fn(array $filter): bool => $filter['script_name'] === 'active'));
            $this->repo->insert('sieve_filters', ['username' => $run->username, 'script_name' => $active ? 'active' : 'inactive']
                + array_diff_key($row, array_flip(self::NEW_ROW)));
            $run->added('filters');
        }
    }

    /**
     * Spam settings (filterconf): missing allow/deny list entries and spam score levels (an existing level stays);
     * spam aliases that are still valid, whose address is free and in a domain of the mailbox.
     */
    private function spam(RestoreRun $run): void
    {
        foreach ($run->rows('filterconf') as $row) {
            $option = (string)($row['option'] ?? '');
            $value = (string)($row['value'] ?? '');
            $current = $this->repo->rows('filterconf', ['object' => $run->username]);
            $present = match ($option) {
                'whitelist_from', 'blacklist_from' => array_filter($current, fn(array $c): bool => in_array($c['option'], ['whitelist_from', 'blacklist_from'], true)
                    && strtolower((string)$c['value']) === strtolower($value)),
                'lowspamlevel', 'highspamlevel' => array_filter($current, fn(array $c): bool => $c['option'] === $option),
                default => array_filter($current, fn(array $c): bool => $c['option'] === $option && (string)$c['value'] === $value),
            };
            if ($option === '' || !empty($present)) {
                $run->skipped('spam');
                continue;
            }
            $this->repo->insert('filterconf', ['object' => $run->username, 'option' => $option, 'value' => $value]);
            $run->added('spam');
        }
        foreach ($run->rows('spamalias') as $row) {
            $address = strtolower(trim((string)($row['address'] ?? '')));
            if ((int)($row['permanent'] ?? 0) !== 1 && (int)($row['validity'] ?? 0) < time()) {
                $run->skipped('spam');
                continue;
            }
            $current = $this->repo->rows('spamalias', ['address' => $address])[0] ?? null;
            if ($current !== null && strtolower((string)$current['goto']) === $run->username) {
                $run->skipped('spam');
                continue;
            }
            // like the legacy form: spam aliases in the mailbox's domain and its alias domains only
            if (!filter_var($address, FILTER_VALIDATE_EMAIL) || !in_array(self::domainOf($address),
                array_merge([$run->domain], $this->repo->aliasDomainsOf($run->domain)), true)) {
                $run->error('spam', "$address: not a domain of the mailbox");
                continue;
            }
            $kind = $this->repo->addressKind($address);
            if ($kind !== null) {
                $run->error('spam', "$address: address is in use ($kind)");
                continue;
            }
            $this->repo->insert('spamalias', ['address' => $address, 'goto' => $run->username] + array_diff_key($row, array_flip(self::NEW_ROW)));
            $run->added('spam');
        }
    }

    /** BCC maps of the mailbox: one per type */
    private function bcc(RestoreRun $run): void
    {
        foreach ($run->rows('bcc_maps') as $row) {
            $type = (string)($row['type'] ?? '');
            $destination = (string)($row['bcc_dest'] ?? '');
            if (!in_array($type, ['sender', 'rcpt'], true) || !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
                $run->error('bcc', "$destination: invalid BCC map");
                continue;
            }
            if (!empty($this->repo->rows('bcc_maps', ['local_dest' => $run->username, 'type' => $type]))) {
                $run->skipped('bcc');
                continue;
            }
            $this->repo->insert('bcc_maps', ['local_dest' => $run->username, 'domain' => $run->domain] + array_diff_key($row, array_flip(self::NEW_ROW)));
            $run->added('bcc');
        }
    }

    /**
     * Sync jobs missing by user1, host and port. The source must exist now (same id with that host and port, else
     * another source the mailbox may use). Restored jobs are inactive: an old migration must not start on its own.
     */
    private function syncJobs(RestoreRun $run): void
    {
        $current = array_map(fn(array $job): string => self::syncKey((string)$job['user1'], (string)$job['host1'], (int)$job['port1']),
            $this->repo->syncJobsOf($run->username));
        foreach ($run->rows('imapsync') as $row) {
            $row = self::decode($row);
            $user1 = (string)($row['user1'] ?? '');
            $host = (string)($row['source_host1'] ?? '');
            $port = (int)($row['source_port1'] ?? 0);
            if (in_array(self::syncKey($user1, $host, $port), $current, true)) {
                $run->skipped('syncjobs');
                continue;
            }
            $source = $this->repo->rows('imapsync_source', ['id' => (int)($row['source_id'] ?? 0)])[0] ?? null;
            $sourceId = ($source !== null && strtolower((string)$source['host1']) === strtolower($host) && (int)$source['port1'] === $port)
                ? (int)$source['id'] : ($this->repo->syncSourcesFor($host, $port, $run->username, $run->domain)[0] ?? null);
            if ($sourceId === null || $user1 === '') {
                $run->error('syncjobs', "$user1 ($host:$port): sync source does not exist");
                continue;
            }
            $values = array_diff_key($row, array_flip([...self::NEW_ROW, 'is_running', 'returned_text', 'last_run', 'success', 'exit_status', 'prio']));
            $this->repo->insert('imapsync', ['user2' => $run->username, 'source_id' => $sourceId, 'active' => 0] + $values);
            $current[] = self::syncKey($user1, $host, $port);
            $run->added('syncjobs');
        }
    }

    /** App passwords missing by name */
    private function appPasswords(RestoreRun $run): void
    {
        foreach ($run->rows('app_passwd') as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name === '' || (string)($row['password'] ?? '') === '') {
                $run->error('app_passwords', "$name: invalid app password");
                continue;
            }
            if (!empty($this->repo->rows('app_passwd', ['mailbox' => $run->username, 'name' => $name]))) {
                $run->skipped('app_passwords');
                continue;
            }
            $this->repo->insert('app_passwd', ['mailbox' => $run->username, 'domain' => $run->domain] + array_diff_key($row, array_flip(self::NEW_ROW)));
            $run->added('app_passwords');
            $run->auth($run->username);
        }
    }

    /**
     * 2FA entries missing (same key_id, keyHandle or secret = present) and FIDO2 keys of the passwordless login
     * missing by credential id.
     */
    private function tfa(RestoreRun $run): void
    {
        foreach ($run->rows('tfa') as $row) {
            $current = $this->repo->rows('tfa', ['username' => $run->username]);
            $present = array_filter($current, fn(array $tfa): bool => (string)$tfa['key_id'] === (string)($row['key_id'] ?? '')
                || (($row['keyHandle'] ?? '') !== '' && ($row['keyHandle'] ?? null) !== null && $tfa['keyHandle'] === $row['keyHandle'])
                || (($row['secret'] ?? '') !== '' && ($row['secret'] ?? null) !== null && $tfa['secret'] === $row['secret']));
            if (!empty($present)) {
                $run->skipped('tfa');
                continue;
            }
            $this->repo->insert('tfa', ['username' => $run->username] + array_diff_key($row, array_flip(self::NEW_ROW)));
            $run->added('tfa');
        }
        foreach ($run->rows('fido2') as $row) {
            $row = self::decode($row);
            $credentialId = (string)($row['credentialId'] ?? '');
            if ($credentialId === '') {
                $run->error('tfa', 'FIDO2 key without credential id');
                continue;
            }
            $present = array_filter($this->repo->rows('fido2', ['username' => $run->username]), fn(array $key): bool => $key['credentialId'] === $credentialId);
            if (!empty($present)) {
                $run->skipped('tfa');
                continue;
            }
            $this->repo->insert('fido2', ['username' => $run->username] + array_diff_key($row, array_flip(self::NEW_ROW)));
            $run->added('tfa');
        }
    }

    /**
     * Overwrites quota (within the domain's limits), attributes (protocols, relayhost, ...), custom attributes, user
     * ACL, Pushover settings and the rate limit; adds missing tags. Counts changed values as added, equal ones as skipped.
     */
    private function settings(RestoreRun $run): void
    {
        $backup = self::decode($run->data['mailbox']);
        $mailbox = $this->mailbox($run->username) ?? $run->mailbox;
        $changes = [];
        if (isset($backup['quota']) && is_numeric($backup['quota'])) {
            $quota = (int)$backup['quota'];
            $error = ($quota === (int)$mailbox['quota']) ? null : $this->quotaError($run, $quota);
            if ($error !== null) {
                $run->error('settings', "quota: $error");
            } elseif ($quota === (int)$mailbox['quota']) {
                $run->skipped('settings');
            } else {
                $changes['quota'] = $quota;
                $run->added('settings');
            }
        }
        $attributes = self::json($mailbox['attributes'] ?? null);
        $changed = false;
        foreach (self::json($backup['attributes'] ?? null) as $key => $value) {
            if (in_array($key, self::KEEP_ATTRIBUTES, true)) {
                continue;
            }
            if (array_key_exists($key, $attributes) && $attributes[$key] === $value) {
                $run->skipped('settings');
                continue;
            }
            if (isset(self::ATTRIBUTE_ACL[$key]) && !$run->may(self::ATTRIBUTE_ACL[$key])) {
                $run->error('settings', "$key: not allowed");
                continue;
            }
            if ($key === 'relayhost' && (string)$value !== '0' && (string)$value !== '' && empty($this->repo->rows('relayhosts', ['id' => (int)$value]))) {
                $run->error('settings', 'relayhost: does not exist');
                continue;
            }
            $attributes[$key] = $value;
            $changed = true;
            $run->added('settings');
        }
        if ($changed) {
            $changes['attributes'] = self::jsonText($attributes);
        }
        if (array_key_exists('custom_attributes', $backup)) {
            $custom = self::json($mailbox['custom_attributes'] ?? null);
            $restored = array_merge($custom, self::json($backup['custom_attributes']));
            if ($restored !== $custom) {
                $changes['custom_attributes'] = self::jsonText($restored);
                $run->added('settings');
            }
        }
        if (!empty($changes)) {
            $this->repo->update('mailbox', ['username' => $run->username], $changes);
            $run->auth($run->username);
            $run->sogo($run->username);
        }
        $this->tags($run, (array)($run->data['mailbox']['tags'] ?? []));
        if (is_array($run->data['user_acl'] ?? null)) {
            $this->overwriteRow($run, 'user_acl', self::decode($run->data['user_acl']));
        }
        if (is_array($run->data['pushover'] ?? null)) {
            if ($run->may('pushover')) {
                $this->overwriteRow($run, 'pushover', self::decode($run->data['pushover']));
            } else {
                $run->error('settings', 'pushover: not allowed');
            }
        }
        $rateLimit = $run->data['ratelimit'] ?? null;
        if (is_string($rateLimit) && preg_match('/^\d+ \/ 1[smhd]$/', $rateLimit)) {
            if (($this->legacy->rateLimits([$run->username])[$run->username] ?? null) === $rateLimit) {
                $run->skipped('settings');
            } elseif ($run->may('ratelimit')) {
                $this->legacy->setRateLimit($run->username, $rateLimit);
                $run->added('settings');
            } else {
                $run->error('settings', 'rate limit: not allowed');
            }
        }
    }

    /** Why the quota cannot be restored, null when it can (domain limits like the legacy mailbox form) */
    private function quotaError(RestoreRun $run, int $quota): ?string
    {
        if ($quota < 0) {
            return 'invalid';
        }
        if ($quota === 0) {
            return $run->may('unlimited_quota') ? null : 'unlimited quota not allowed';
        }
        $domain = $this->repo->rows('domain', ['domain' => $run->domain])[0] ?? null;
        if ($domain === null) {
            return 'domain does not exist';
        }
        if ($quota > (int)$domain['maxquota'] * self::MIB) {
            return 'exceeds the maximum quota of a mailbox in the domain';
        }
        if ($quota > (int)$domain['quota'] * self::MIB - $this->repo->quotaInUse($run->domain, $run->username)) {
            return 'exceeds the quota left in the domain';
        }
        return null;
    }

    /** @param array $tags tags of the backup: missing ones are added */
    private function tags(RestoreRun $run, array $tags): void
    {
        $current = array_map(fn(array $row): string => (string)$row['tag_name'], $this->repo->rows('tags_mailbox', ['username' => $run->username]));
        foreach (array_unique(array_filter(array_map(fn(mixed $tag): string => is_scalar($tag) ? trim((string)$tag) : '', $tags), 'strlen')) as $tag) {
            if (in_array($tag, $current, true)) {
                $run->skipped('settings');
                continue;
            }
            $this->repo->insert('tags_mailbox', ['username' => $run->username, 'tag_name' => $tag]);
            $run->added('settings');
        }
    }

    /** Overwrites the mailbox's row of user_acl or pushover with the backup's values (creates it if missing) */
    private function overwriteRow(RestoreRun $run, string $table, array $backup): void
    {
        $values = array_diff_key($backup, ['username' => true]);
        $current = $this->repo->rows($table, ['username' => $run->username])[0] ?? null;
        if ($table === 'user_acl') {
            // a domain admin's restore keeps what he may not grant
            foreach ($this->lockedUserAcl($run) as $column) {
                if (array_key_exists($column, $values) && $current !== null && array_key_exists($column, $current) && (string)$current[$column] !== (string)$values[$column]) {
                    $run->error('settings', "user ACL $column: not allowed");
                }
                unset($values[$column]);
            }
        }
        if ($current === null) {
            $this->repo->insert($table, ['username' => $run->username] + $values);
            $run->added('settings');
            return;
        }
        $changed = array_filter($values, fn(mixed $value, string $column): bool => array_key_exists($column, $current)
            && (string)$current[$column] !== (string)$value, ARRAY_FILTER_USE_BOTH);
        $run->skipped('settings', count(array_intersect_key($values, $current)) - count($changed));
        if (!empty($changed)) {
            $this->repo->update($table, ['username' => $run->username], $changed);
            $run->added('settings', count($changed));
        }
    }

    /** Overwrites the password hash (mailboxes that log in with mailcow passwords only) */
    private function password(RestoreRun $run): void
    {
        $backup = $run->data['mailbox'];
        $hash = (string)($backup['password'] ?? '');
        $mailbox = $this->mailbox($run->username) ?? $run->mailbox;
        if ($hash === '') {
            $run->error('password', 'no password in the backup');
        } elseif (($mailbox['authsource'] ?? 'mailcow') !== 'mailcow') {
            $run->error('password', 'the mailbox logs in through an identity provider');
        } elseif (hash_equals((string)$mailbox['password'], $hash)) {
            $run->skipped('password');
        } else {
            $attributes = self::json($mailbox['attributes'] ?? null);
            $attributes['passwd_update'] = self::json($backup['attributes'] ?? null)['passwd_update'] ?? date('Y-m-d H:i:s');
            $this->repo->update('mailbox', ['username' => $run->username], ['password' => $hash, 'attributes' => self::jsonText($attributes)]);
            $run->added('password');
            $run->auth($run->username);
        }
    }

    /**
     * Manual memberships in groups of the mailbox's domain that exist now (by id, else by name), through the group
     * module as the requester (a domain admin with his rights now: groups read-only for him are not allowed).
     * IdP memberships are the IdP sync's.
     */
    private function groups(RestoreRun $run): void
    {
        $context = $run->requester();
        foreach ($run->rows('groups') as $entry) {
            $name = (string)($entry['name'] ?? '');
            if (($entry['source'] ?? 'manual') === 'idp') {
                $run->skipped('groups');
                continue;
            }
            $group = $this->repo->rows('mailbox_group', ['id' => (int)($entry['id'] ?? 0), 'domain' => $run->domain])[0]
                ?? $this->repo->rows('mailbox_group', ['domain' => $run->domain, 'name' => $name])[0] ?? null;
            if ($group === null) {
                $run->error('groups', "$name: does not exist");
                continue;
            }
            if (!empty($this->repo->rows('mailbox_group_member', ['group_id' => (int)$group['id'], 'username' => $run->username]))) {
                $run->skipped('groups');
                continue;
            }
            try {
                $this->groups->add($context, (int)$group['id'], $run->username);
                $run->added('groups');
            } catch (AccessDenied) {
                $run->error('groups', "$name: not allowed");
            } catch (UserError $e) {
                $run->error('groups', "$name: " . $e->key);
            } catch (PDOException $e) {
                $run->error('groups', "$name: database error " . $e->getCode());
            }
        }
    }

    private function mailbox(string $username): ?array
    {
        return $this->repo->rows('mailbox', ['username' => $username])[0] ?? null;
    }

    private static function syncKey(string $user1, string $host, int $port): string
    {
        return strtolower($user1) . '|' . strtolower($host) . '|' . $port;
    }

    /** user@domain or @domain */
    private static function validAddress(string $address): bool
    {
        return str_starts_with($address, '@') ? (bool)preg_match('/^@[a-z0-9.-]+\.[a-z0-9-]+$/i', $address)
            : filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function domainOf(string $address): string
    {
        return strtolower(substr((string)strrchr($address, '@'), 1));
    }

    /** Values of a backup row: {"base64": ...} back to binary, other lists as JSON text */
    public static function decode(array $row): array
    {
        foreach ($row as $column => $value) {
            if (is_array($value)) {
                $row[$column] = (array_keys($value) === ['base64']) ? base64_decode((string)$value['base64'], true) : json_encode($value);
            }
        }
        return $row;
    }

    /** A JSON column as array ({} when empty or broken) */
    private static function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) ? $decoded : [];
    }

    private static function jsonText(array $value): string
    {
        return (string)json_encode($value ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
