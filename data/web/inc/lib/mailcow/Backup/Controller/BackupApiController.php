<?php

declare(strict_types=1);

namespace Mailcow\Backup\Controller;

use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Service\DomainRestoreService;
use Mailcow\Backup\Service\ExportService;
use Mailcow\Backup\Service\JobService;
use Mailcow\Backup\Service\RestoreService;
use Mailcow\Backup\Service\SettingsService;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PDOException;

/**
 * HTTP API adapter for json_api.php (routes backup-settings, backup-status, backup-snapshots, backup-snapshot-content,
 * backup-jobs, backup-job, backup-restore) and the runner's endpoint data/conf/rspamd/dynmaps/backup.php (domains,
 * export pages, restore actions). Messages are logged as ['backup', action, object, data].
 */
class BackupApiController
{
    public function __construct(private SettingsService $settings, private JobService $jobs, private ExportService $export,
        private RestoreService $restore, private DomainRestoreService $domainRestore, private ApiResponse $response)
    {
    }

    /** GET backup-settings: secrets as <key>_set; {} for non-admins */
    public function getSettings(): array
    {
        return $this->settings->get()?->toArray() ?? [];
    }

    /** POST edit/backup-settings */
    public function editSettings(array $attr): bool
    {
        return $this->handle('edit', 'settings', $attr, function (array $log) use ($attr): bool {
            $this->settings->save($attr);
            return $this->response->success('backup_settings_saved', $log);
        });
    }

    /** GET backup-status */
    public function getStatus(): array
    {
        return $this->jobs->status();
    }

    /** GET backup-snapshots */
    public function getSnapshots(): array
    {
        return $this->jobs->snapshots();
    }

    /** GET backup-snapshot-content/<snapshot>[/<domain>]: domains of a snapshot, or the mailboxes of one, with their state now */
    public function getSnapshotContent(mixed $snapshot, mixed $domain = null): array
    {
        return ($domain === null || $domain === '') ? $this->jobs->content($snapshot) : $this->jobs->domainContent($snapshot, $domain);
    }

    /** GET backup-snapshot-search/<snapshot>/<term>: domains and mailboxes of a snapshot matching the term */
    public function getSnapshotSearch(mixed $snapshot, mixed $term): array
    {
        return $this->jobs->search($snapshot, $term);
    }

    /** GET backup-jobs[/<count>[/<type>]]: newest first, without logs; count 0 = default */
    public function getJobs(int $limit = 0, mixed $type = null): array
    {
        return array_map(fn(Job $job): array => $this->jobs->view($job), $this->jobs->list($limit, $type));
    }

    /** GET backup-job/<id>: with its log */
    public function getJob(mixed $id): array
    {
        $id = $this->toId($id);
        $job = ($id === null) ? null : $this->jobs->get($id);
        return ($job === null) ? [] : $this->jobs->view($job, true);
    }

    /** POST add/backup-job: {type: backup|check|prune|init} */
    public function addJob(array $attr): bool
    {
        return $this->handle('add', 'job', $attr, function (array $log) use ($attr): bool {
            $job = $this->jobs->create($attr);
            return $this->response->success(['backup_job_added', $job->type, $job->id], $log);
        });
    }

    /** POST add/backup-restore: {snapshot, scope, mailboxes | domain, components, mail_target} */
    public function addRestore(array $attr): bool
    {
        return $this->handle('add', 'restore', $attr, function (array $log) use ($attr): bool {
            $job = $this->jobs->restore($attr);
            return $this->response->success(['backup_restore_added', $job->id, count($job->mailboxes())], $log);
        });
    }

    /** POST delete/backup-job: cancels queued and running jobs, one message per id */
    public function deleteJobs(array $items): bool
    {
        return $this->handle('delete', 'job', ['id' => $items], function (array $log) use ($items): bool {
            foreach ($items as $id) {
                try {
                    $job = $this->jobs->cancel($this->requireId($id));
                    $this->response->success(['backup_job_cancelled', $job->id], $log);
                } catch (UserError $e) {
                    $this->response->error($e, $log);
                }
            }
            return true;
        });
    }

    /** backup.php ?domains=1 */
    public function domains(): array
    {
        return ['domains' => $this->export->domains()];
    }

    /** backup.php ?export=<domain>[&after=<username>] @throws UserError */
    public function export(string $domain, ?string $after = null): array
    {
        return $this->export->export($domain, $after);
    }

    /**
     * backup.php POST of the runner, {"result": ...} of
     *   {"action": "restore_domain", "job": id, "data": <domain_data>}: creates a deleted domain
     *   {"action": "restore_aliases", "job": id, "aliases": [...]}: a part of the domain's aliases (scope domain)
     *   {"action": "restore", "job": id, "username": u, "data": <mailbox section>}: one mailbox
     *
     * @throws UserError
     */
    public function post(array $body): array
    {
        return ['result' => match ($body['action'] ?? null) {
            'restore' => $this->restore->merge($body['job'] ?? null, $body['username'] ?? null, $body['data'] ?? null),
            'restore_aliases' => $this->restore->mergeAliases($body['job'] ?? null, $body['aliases'] ?? null),
            'restore_domain' => $this->domainRestore->restore($body['job'] ?? null, $body['data'] ?? null),
            default => throw new UserError('backup_action_invalid'),
        }];
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, string $object, mixed $data, callable $work): bool
    {
        $log = ['backup', $action, $object, $data];
        try {
            $result = $work($log);
        } catch (UserError $e) {
            $result = $this->response->error($e, $log);
        } catch (PDOException $e) {
            $result = $this->response->danger(['mysql_error', $e->getMessage()], $log);
        }
        $this->response->flush();
        return $result;
    }

    /** Integer id from request input ("12", 12); null for anything else */
    private function toId(mixed $id): ?int
    {
        return (is_numeric($id) && (float)$id == (int)(float)$id) ? (int)(float)$id : null;
    }

    /** @throws AccessDenied for ids that cannot exist */
    private function requireId(mixed $id): int
    {
        return $this->toId($id) ?? throw new AccessDenied();
    }
}
