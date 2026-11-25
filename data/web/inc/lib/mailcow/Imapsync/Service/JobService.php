<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Model\CustomParams;
use Mailcow\Imapsync\Model\Job;
use Mailcow\Imapsync\Model\Settings;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Repository\JobRepository;
use Mailcow\Imapsync\Repository\SettingsRepository;
use Mailcow\Imapsync\Repository\TokenRepository;

/**
 * Use cases and rules for sync jobs and the global sync settings.
 *
 * A job belongs to its local mailbox (user2): whoever may access that mailbox may see and manage it
 * (admins also orphaned jobs). Writing needs the `syncjobs` ACL. Jobs on an authorization_code source
 * need the per-user token of (source, owner = user2, username = user1) first (C1).
 */
class JobService
{
    private const FLAGS = ['active', 'delete2duplicates', 'subscribeall', 'dry', 'delete1', 'delete2', 'automap', 'skipcrossduplicates'];

    public function __construct(private JobRepository $jobs, private SourceService $sources, private TokenRepository $tokens,
        private SettingsRepository $settings, private Context $context, private array $allowlist)
    {
    }

    /** @throws AccessDenied without the syncjobs ACL */
    public function requireAcl(): void
    {
        if (!$this->context->hasAcl('syncjobs')) {
            throw new AccessDenied();
        }
    }

    /** The allowed custom params (option => rule), for the job form */
    public function customParamOptions(): array
    {
        return $this->allowlist;
    }

    /**
     * The job when the caller may access its mailbox (incl. password1: never pass the object to the UI, use toArray()).
     * Admins may also manage orphaned jobs whose local mailbox no longer exists.
     */
    public function get(int $id, bool $withLog = true): ?Job
    {
        $job = $this->jobs->find($id, $withLog);
        return ($job !== null && ($this->context->isAdmin() || $this->context->canAccessMailbox($job->user2))) ? $job : null;
    }

    /** @return int[] job ids of a mailbox (default: the caller's own) in queue order; [] without access */
    public function idsForMailbox(mixed $mailbox): array
    {
        if ($mailbox !== null && filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            if (!$this->context->canAccessMailbox((string)$mailbox)) {
                return [];
            }
        } else {
            $mailbox = $this->context->username;
        }
        return ($mailbox === null) ? [] : $this->jobs->idsForMailbox((string)$mailbox);
    }

    /** Only admins list all jobs at once (incl. orphaned ones) */
    public function canListAll(): bool
    {
        return $this->context->isAdmin();
    }

    /** @return int[] all job ids in queue order; [] for non-admins */
    public function allIds(): array
    {
        return $this->canListAll() ? $this->jobs->allIds() : [];
    }

    /** @throws UserError */
    public function create(array $input): Job
    {
        $this->requireAcl();
        $job = new Job();
        $job->user2 = $this->ownerMailbox($input);
        // Add keeps its legacy parsing of these attributes (e.g. maxage '1.5' means none, on edit 1); then the edit rules apply
        $input['source_id'] = (int)($input['source_id'] ?? 0);
        $input['maxage'] = Input::intInRange($input['maxage'] ?? null, 1, 32000) ?? 0;
        $input['maxbytespersecond'] = Input::intInRange($input['maxbytespersecond'] ?? null, 1, 125000000) ?? 0;
        $this->save($job, null, $input + ['custom_params' => '']);
        return $job;
    }

    /** @throws UserError */
    public function update(int $id, array $input): Job
    {
        $this->requireAcl();
        $current = $this->get($id, false) ?? throw new AccessDenied();
        $job = clone $current;
        $this->save($job, $current, $input);
        return $job;
    }

    /** @throws UserError access_denied when the job does not exist or the caller may not access it */
    public function delete(int $id): void
    {
        $this->requireAcl();
        $job = $this->get($id, false) ?? throw new AccessDenied();
        // prio is a sparse sort key (ties allowed), so no gap needs closing
        $this->jobs->delete($id);
        $this->tokens->deleteIfUnused($job->sourceId, $job->user2, $job->user1);
    }

    /**
     * Admin only (C4): moves jobs to the front of the queue. Running jobs are left alone; the runner never resets prio.
     *
     * @param int[] $ids
     * @return array{running: int[], moved: int}
     */
    public function runNext(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if (!$this->context->isAdmin() || empty($ids)) {
            throw new AccessDenied();
        }
        return $this->jobs->moveToFront($ids);
    }

    /** @throws AccessDenied for non-admins */
    public function getSettings(): Settings
    {
        if (!$this->context->isAdmin()) {
            throw new AccessDenied();
        }
        return $this->settings->load();
    }

    /**
     * Saves the global settings (admin only). The UI sends the bandwidth limit in KB/s; stored as bytes/s
     * like the per-job limit.
     *
     * @throws UserError
     */
    public function saveSettings(array $input): Settings
    {
        if (!$this->context->isAdmin()) {
            throw new AccessDenied();
        }
        $maxParallel = Input::intInRange($input['max_parallel'] ?? null, 1, 32);
        $kb = $input['max_kb_per_second'] ?? 0;
        if ($maxParallel === null) {
            throw new UserError('imapsync_max_parallel_invalid');
        }
        // 0 (unlimited) .. 10 GiB/s
        $kb = Input::intInRange(($kb === '') ? 0 : $kb, 0, 10485760) ?? throw new UserError('imapsync_max_kb_per_second_invalid');
        $settings = new Settings();
        $settings->maxParallel = $maxParallel;
        $settings->maxBytesPerSecond = $kb * 1024;
        $this->settings->save($settings);
        return $settings;
    }

    /**
     * Applies an add ($current = null) or edit request to $job and stores it. Attributes missing in $input keep
     * the value of $job, an empty password keeps the stored one. On edit the source is only checked when the job
     * moves to another source, so a domain admin or user can still edit or deactivate a job bound to a source
     * he cannot see.
     *
     * @throws UserError
     */
    private function save(Job $job, ?Job $current, array $input): void
    {
        foreach (self::FLAGS as $flag) {
            if (isset($input[$flag])) {
                $job->$flag = (int)$input[$flag];
            }
        }
        foreach (['maxage', 'timeout1', 'timeout2'] as $number) {
            if (isset($input[$number]) && $input[$number] != '') {
                $job->$number = (int)$input[$number];
            }
        }
        if (isset($input['maxbytespersecond']) && $input['maxbytespersecond'] != '') {
            $job->maxBytesPerSecond = (string)(int)$input['maxbytespersecond'];
        }
        // last_run/success are reset when present; prio is a global-queue concern that only admins may change
        $job->lastRun = isset($input['last_run']) ? null : $job->lastRun;
        $job->success = isset($input['success']) ? null : $job->success;
        if (isset($input['prio']) && is_numeric($input['prio']) && $this->context->isAdmin()) {
            $job->prio = (int)$input['prio'];
        }
        // An empty user1 keeps the stored one on edit; a new job needs one
        $user1 = (isset($input['user1']) && Input::text($input['user1']) !== '') ? Input::text($input['user1']) : $job->user1;
        if ($user1 === null || mb_strlen($user1) > 255 || ($current === null && $user1 === '')) {
            throw new UserError('imapsync_user1_invalid');
        }
        $password = (isset($input['password1']) && is_scalar($input['password1']) && (string)$input['password1'] !== '')
            ? (string)$input['password1'] : $job->password1;
        $sourceId = (!empty($input['source_id']) && is_scalar($input['source_id'])) ? (int)$input['source_id'] : $job->sourceId;
        $sourceChanged = ($current === null || $sourceId !== $current->sourceId);
        $tupleChanged = $sourceChanged || $user1 !== $current->user1;
        $job->user1 = $user1;
        $job->sourceId = $sourceId;

        $source = $sourceChanged ? $this->sources->get($sourceId) : $current->source;
        if ($source === null || ($sourceChanged && $source->active !== 1)) {
            throw new UserError('imapsync_source_unavailable');
        }
        if ($source->usesClientCredentials() && $tupleChanged && !$sourceChanged && !$this->mayUse($source)) {
            // The app token reaches every mailbox of the tenant, so only callers that may use the source
            // may pick another remote mailbox (user1). Other fields stay editable.
            throw new AccessDenied();
        }
        $job->password1 = $this->checkAuth($source, $job, $password, $tupleChanged);
        if (array_key_exists('custom_params', $input)) {
            $job->customParams = CustomParams::toJson($input['custom_params'], $this->allowlist);
        }
        $job->maxage = Input::intInRange($job->maxage, 1, 32000) ?? 0;
        $job->timeout1 = Input::intInRange($job->timeout1, 1, 32000) ?? 600;
        $job->timeout2 = Input::intInRange($job->timeout2, 1, 32000) ?? 600;
        $job->maxBytesPerSecond = (string)(Input::intInRange($job->maxBytesPerSecond, 1, 125000000) ?? 0);
        $exclude = isset($input['exclude']) ? Input::text($input['exclude']) : $job->exclude;
        $subfolder2 = isset($input['subfolder2']) ? Input::text($input['subfolder2']) : $job->subfolder2;
        $minsInterval = Input::intInRange(!empty($input['mins_interval']) ? $input['mins_interval'] : $job->minsInterval, 1, 43800);
        // exclude must be a valid regex, the interval 1..43800 minutes
        if ($exclude === null || $subfolder2 === null || $minsInterval === null || @preg_match("/" . $exclude . "/", '') === false) {
            throw new AccessDenied();
        }
        $job->exclude = $exclude;
        $job->subfolder2 = $subfolder2;
        $job->minsInterval = $minsInterval;
        if ($tupleChanged && $this->jobs->tupleExists($job->sourceId, $job->user2, $job->user1, $current?->id)) {
            throw new UserError('object_exists', [htmlspecialchars($source->name . ' / ' . $job->user1)]);
        }
        if ($current === null) {
            $this->jobs->insert($job);
            return;
        }
        $this->jobs->update($job);
        // The previous (source, owner, remote user) token is dropped once no job uses it (C1)
        if ($tupleChanged) {
            $this->tokens->deleteIfUnused($current->sourceId, $current->user2, $current->user1);
        }
    }

    /** Whether the caller may use the source for new jobs (sees it or may edit it) */
    private function mayUse(Source $source): bool
    {
        return $this->sources->get((int)$source->id) !== null || $this->sources->canEdit($source);
    }

    /**
     * Local mailbox of a new job: `username` (needs access), else the caller's own mailbox (users only).
     *
     * @throws UserError access_denied, no_user_defined
     */
    private function ownerMailbox(array $input): string
    {
        $username = $input['username'] ?? null;
        if (is_string($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return $this->context->canAccessMailbox($username) ? $username : throw new AccessDenied();
        }
        return $this->context->isUser() ? (string)$this->context->username : throw new UserError('no_user_defined');
    }

    /**
     * Returns the password to store. XOAUTH2 sources carry no password (the runner uses an access token);
     * an authorization_code source needs the per-user token of (source, owner, remote user) when it is new.
     *
     * @throws UserError imapsync_source_oauth_connect_required, password_empty
     */
    private function checkAuth(Source $source, Job $job, string $password, bool $tupleChanged): string
    {
        if (!$source->isXOAuth2()) {
            return ($password !== '') ? $password : throw new UserError('password_empty');
        }
        if ($source->usesAuthorizationCode() && $tupleChanged && !$this->tokens->exists($job->sourceId, $job->user2, $job->user1)) {
            throw new UserError('imapsync_source_oauth_connect_required');
        }
        return '';
    }
}
