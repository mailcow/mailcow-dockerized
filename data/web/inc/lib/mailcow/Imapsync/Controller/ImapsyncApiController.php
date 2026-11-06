<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Service\JobService;
use Mailcow\Imapsync\Service\SourceService;
use PDOException;

/**
 * HTTP API adapter for json_api.php (and the pages that show the same data).
 *
 * Write methods take the decoded request ($items = ids, $attr = attributes), loop over batch ids, turn
 * UserError into danger messages and write all messages to $_SESSION['return'] via ApiResponse.
 * They return what json_api passes to process_*_return(): false when the whole call was rejected.
 * A database error stops the call with one `mysql_error` message, like the rest of mailcow.
 * Read methods return the API data (an empty array when there is nothing the caller may see).
 * Messages are logged as ['syncjob', action, type, data] like before the conversion.
 */
class ImapsyncApiController
{
    public function __construct(private SourceService $sources, private JobService $jobs, private ApiResponse $response)
    {
    }

    /** POST add/syncjob */
    public function addJob(array $attr): bool
    {
        return $this->handle('add', 'job', $attr,
            fn(array $log): bool => $this->response->success(['mailbox_modified', $this->jobs->create($attr)->user2], $log));
    }

    /** POST add/syncjob_source */
    public function addSource(array $attr): bool
    {
        return $this->handle('add', 'source', $attr, function (array $log) use ($attr): bool {
            $source = $this->sources->create($attr);
            $this->tokenFailure($source, $log);
            return $this->response->success(['imapsync_source_added', $source->name], $log);
        });
    }

    /** POST edit/syncjob: one message per id; `id` in $attr wins over $items (array_merge like before) */
    public function editJobs(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', 'job', $data, function (array $log) use ($data): bool {
            $this->jobs->requireAcl();
            return $this->batch((array)($data['id'] ?? []), $log,
                fn(int $id): array => ['mailbox_modified', $this->jobs->update($id, $data)->user2]);
        });
    }

    /** POST edit/syncjob/run_next (admin): false when no job was moved */
    public function runNext(?array $items): bool
    {
        return $this->handle('edit', 'run_next', ['id' => $items], function (array $log) use ($items): bool {
            $outcome = $this->jobs->runNext(array_map('intval', array_values(array_filter((array)$items, 'is_numeric'))));
            foreach ($outcome['running'] as $id) {
                $this->response->danger(['imapsync_job_running', $id], $log);
            }
            return ($outcome['moved'] > 0) ? $this->response->success(['imapsync_run_next_done', $outcome['moved']], $log) : false;
        });
    }

    /** POST edit/syncjob_source: one message per id, non-numeric ids are skipped */
    public function editSources(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', 'source', $data, function (array $log) use ($data): bool {
            $this->sources->requireEditor();
            return $this->batch(array_filter((array)($data['id'] ?? []), 'is_numeric'), $log, function (int $id) use ($data, $log): array {
                $source = $this->sources->update($id, $data);
                $this->tokenFailure($source, $log);
                return ['imapsync_source_modified', $source->name];
            });
        });
    }

    /** POST edit/syncjob_source/refresh_token: the first id only */
    public function refreshSourceToken(?array $items): bool
    {
        return $this->handle('edit', 'refresh_token', ['id' => $items], function (array $log) use ($items): bool {
            $source = $this->sources->refreshToken($this->requireId(is_array($items) ? reset($items) : null));
            return $this->response->success(['imapsync_source_token_refreshed', $source->name], $log);
        });
    }

    /** POST edit/imapsync_settings (admin) */
    public function editSettings(array $attr): bool
    {
        return $this->handle('edit', 'settings', $attr, function (array $log) use ($attr): bool {
            $this->jobs->saveSettings($attr);
            return $this->response->success('max_parallel_saved', $log);
        });
    }

    /** POST delete/syncjob: one message per id (with the id as requested) */
    public function deleteJobs(array $items): bool
    {
        return $this->handle('delete', 'job', ['id' => $items], function (array $log) use ($items): bool {
            $this->jobs->requireAcl();
            return $this->batch($items, $log, function (int $id, mixed $requested): array {
                $this->jobs->delete($id);
                return ['deleted_syncjob', $requested];
            });
        });
    }

    /** POST delete/syncjob_source: one message per id, non-numeric ids are skipped */
    public function deleteSources(array $items): bool
    {
        return $this->handle('delete', 'source', ['id' => $items], function (array $log) use ($items): bool {
            $this->sources->requireAcl();
            return $this->batch(array_filter($items, 'is_numeric'), $log,
                fn(int $id): array => ['imapsync_source_deleted', $this->sources->delete($id)->name]);
        });
    }

    /** GET syncjob_options */
    public function getOptions(): array
    {
        return $this->jobs->customParamOptions();
    }

    /** GET imapsync_settings (admin), also admin/system.php */
    public function getSettings(): array
    {
        try {
            return $this->jobs->getSettings()->toArray();
        } catch (UserError) {
            return [];
        }
    }

    /** GET syncjob_source/all */
    public function getSources(): array
    {
        return array_values(array_filter(array_map(fn(int $id): array => $this->getSource($id), $this->sources->visibleIds())));
    }

    /** GET syncjob_source/<id>, also edit.php */
    public function getSource(mixed $id): array
    {
        $id = $this->toId($id);
        $source = ($id === null) ? null : $this->sources->get($id);
        return ($source === null) ? [] : $this->sources->toArray($source);
    }

    /**
     * GET syncjobs/all. Admins get every job (incl. orphaned ones). Others get the jobs of $mailboxes,
     * the mailboxes the caller manages (json_api collects them with the legacy mailbox() functions
     * until functions.mailbox.inc.php is converted), in queue order.
     */
    public function getAllJobs(?string $extra, array $mailboxes): array
    {
        if ($this->jobs->canListAll()) {
            return $this->jobRows($this->jobs->allIds(), $extra);
        }
        $ids = [];
        foreach ($mailboxes as $mailbox) {
            $ids = array_merge($ids, $this->jobs->idsForMailbox((string)$mailbox));
        }
        $rows = $this->jobRows($ids, $extra);
        // Queue order (C4): prio desc, never-run first, least recently run, id
        usort($rows, fn(array $a, array $b): int => [$b['prio'], !empty($a['last_run']), (string)$a['last_run'], (int)$a['id']]
            <=> [$a['prio'], !empty($b['last_run']), (string)$b['last_run'], (int)$b['id']]);
        return $rows;
    }

    /** GET syncjobs/<mailbox>; without a valid mailbox the caller's own jobs */
    public function getJobs(?string $mailbox, ?string $extra): array
    {
        return $this->jobRows($this->jobs->idsForMailbox($mailbox), $extra);
    }

    /** One job (edit.php, inc/ajax/syncjob_logs.php); [] when missing or not accessible. `no_log` in $extra skips the log. */
    public function getJob(mixed $id, ?string $extra = null): array
    {
        $id = $this->toId($id);
        $withLog = ($extra === null || !in_array('no_log', explode(',', $extra), true));
        $job = ($id === null) ? null : $this->jobs->get($id, $withLog);
        return ($job === null) ? [] : $job->toArray($withLog);
    }

    /**
     * Account rename ($new) or delete ($new = null) from legacy account code that runs outside a transaction
     * (admin, domain admin, mailbox delete): a database error becomes a `mysql_error` message.
     * Inside a transaction (mailbox rename) call $module->sources->onAccountRenamed() directly instead,
     * so the caller's catch can roll back.
     */
    public function accountChanged(string $old, ?string $new = null): void
    {
        try {
            if ($new === null) {
                $this->sources->onAccountDeleted($old);
            } else {
                $this->sources->onAccountRenamed($old, $new);
            }
        } catch (PDOException $e) {
            $this->response->danger(['mysql_error', $e->getMessage()], ['imapsync_account_changed', $old, $new]);
        }
        $this->response->flush();
    }

    /** The token fetched when saving a client_credentials source failed: the source is saved, report the error */
    private function tokenFailure(Source $source, array $log): void
    {
        if ($source->usesClientCredentials() && $source->active === 1 && $source->lastRefreshError !== null) {
            $this->response->danger(['imapsync_source_token_refresh_failed', $source->name . ' (' . $source->lastRefreshError . ')'], $log);
        }
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, string $type, mixed $data, callable $work): bool
    {
        $log = ['syncjob', $action, $type, $data];
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

    /** One message per id: the success message returned by $work(id, requested id), or the UserError */
    private function batch(array $ids, array $log, callable $work): bool
    {
        foreach ($ids as $id) {
            try {
                $this->response->success($work($this->requireId($id), $id), $log);
            } catch (UserError $e) {
                $this->response->error($e, $log);
            }
        }
        return true;
    }

    /** @return array[] API rows of the jobs the caller may access */
    private function jobRows(array $ids, ?string $extra): array
    {
        return array_values(array_filter(array_map(fn(int $id): array => $this->getJob($id, $extra), $ids)));
    }

    /** Integer id from request input ("12", 12); null for anything else, also "1.5" (matches no row) */
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
