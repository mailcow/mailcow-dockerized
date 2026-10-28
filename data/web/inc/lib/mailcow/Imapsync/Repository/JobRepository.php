<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Repository;

use Mailcow\Core\Repository;
use Mailcow\Imapsync\Model\Job;
use PDO;

/**
 * SQL for sync jobs (table `imapsync`).
 */
class JobRepository extends Repository
{
    /** Job queue order (C4): prio desc, never-run first, then least recently run */
    private const QUEUE_ORDER = "ORDER BY `prio` DESC, `last_run` IS NOT NULL, `last_run` ASC, `id` ASC";

    /** Every column except returned_text (the log, loaded on demand) */
    private const COLUMNS = ['id', 'user2', 'regextrans2', 'authmd51', 'domain2', 'subfolder2', 'user1', 'password1',
        'exclude', 'maxage', 'mins_interval', 'maxbytespersecond', 'delete2duplicates', 'delete1', 'delete2', 'automap',
        'skipcrossduplicates', 'custom_params', 'timeout1', 'timeout2', 'subscribeall', 'dry', 'is_running',
        'last_run', 'success', 'exit_status', 'created', 'modified', 'active', 'source_id', 'custom_params_legacy', 'prio'];

    /**
     * The job with display data of its source and per-user token (C1: source, owner = user2, username = user1).
     * $withLog also loads returned_text.
     */
    public function find(int $id, bool $withLog = true): ?Job
    {
        $columns = $withLog ? [...self::COLUMNS, 'returned_text'] : self::COLUMNS;
        $row = $this->query("SELECT i.`" . implode("`, i.`", $columns) . "`,
              s.`name` AS source_name, s.`description` AS source_description, s.`host1` AS source_host1,
              s.`port1` AS source_port1, s.`enc1` AS source_enc1, s.`auth_type` AS source_auth_type,
              s.`created_by` AS source_created_by, s.`scope` AS source_scope, s.`oauth_flow` AS source_oauth_flow,
              s.`active` AS source_active, s.`oauth_token_expires` AS source_oauth_token_expires,
              s.`oauth_last_refresh_error` AS source_oauth_last_refresh_error,
              t.`token_expires` AS user_token_expires, t.`last_refresh_error` AS user_token_error,
              CASE WHEN t.`username` IS NULL THEN 0 ELSE 1 END AS user_token_exists
            FROM `imapsync` i
            LEFT JOIN `imapsync_source` s ON i.`source_id` = s.`id`
            LEFT JOIN `imapsync_source_oauth_token` t
              ON t.`source_id` = i.`source_id` AND t.`owner` = i.`user2` AND t.`username` = i.`user1`
            WHERE i.`id` = :id", ['id' => $id])->fetch(PDO::FETCH_ASSOC);
        return $row ? Job::fromRow($row) : null;
    }

    /** @return int[] job ids of a local mailbox in queue order */
    public function idsForMailbox(string $user2): array
    {
        return array_map('intval', $this->query("SELECT `id` FROM `imapsync` WHERE `user2` = :user2 " . self::QUEUE_ORDER,
            ['user2' => $user2])->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return int[] all job ids (incl. orphaned ones) in queue order */
    public function allIds(): array
    {
        return array_map('intval', $this->query("SELECT `id` FROM `imapsync` " . self::QUEUE_ORDER)->fetchAll(PDO::FETCH_COLUMN));
    }

    /** True when another job (not $exceptId) already syncs (source, user1) into user2 */
    public function tupleExists(int $sourceId, string $user2, string $user1, ?int $exceptId = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `imapsync`
            WHERE `user2` = :user2 AND `user1` = :user1 AND `source_id` = :source_id AND `id` != :id",
            ['user2' => $user2, 'user1' => $user1, 'source_id' => $sourceId, 'id' => $exceptId ?? 0])->fetchColumn();
    }

    public function insert(Job $job): int
    {
        return $job->id = $this->insertRow('imapsync', $this->values($job) + ['user2' => $job->user2]);
    }

    public function update(Job $job): void
    {
        $this->updateRow('imapsync', (int)$job->id,
            $this->values($job) + ['last_run' => $job->lastRun, 'success' => $job->success, 'prio' => $job->prio]);
    }

    public function delete(int $id): void
    {
        $this->query("DELETE FROM `imapsync` WHERE `id` = :id", ['id' => $id]);
    }

    /**
     * Moves idle jobs to the front of the queue: each gets a prio above the current global max
     * (lowest current prio first) and last_run/success are cleared. The rows are locked first, so the
     * runner cannot claim a job in between (its claim UPDATE waits for this transaction).
     *
     * @param int[] $ids
     * @return array{running: int[], moved: int} ids that are running (left alone) and the number of moved jobs
     */
    public function moveToFront(array $ids): array
    {
        return $this->transaction(function () use ($ids): array {
            $rows = $this->query("SELECT `id`, `is_running` FROM `imapsync` WHERE `id` IN (" . self::placeholders($ids) . ") FOR UPDATE",
                $ids)->fetchAll(PDO::FETCH_KEY_PAIR);
            $running = array_keys(array_filter($rows, fn($isRunning): bool => (int)$isRunning === 1));
            $idle = array_values(array_diff(array_keys($rows), $running));
            $moved = 0;
            if (!empty($idle)) {
                $this->pdo->exec("SET @m := (SELECT COALESCE(MAX(`prio`), 0) FROM `imapsync`)");
                $this->pdo->exec("SET @r := 0");
                $moved = $this->query("UPDATE `imapsync` SET `prio` = @m + (@r := @r + 1), `last_run` = NULL, `success` = NULL
                  WHERE `id` IN (" . self::placeholders($idle) . ") AND `is_running` = 0 ORDER BY `prio` ASC, `id` ASC", $idle)->rowCount();
            }
            return ['running' => $running, 'moved' => $moved];
        });
    }

    /** Values of the columns a user can edit (shared by insert and update) */
    private function values(Job $job): array
    {
        $values = ['mins_interval' => $job->minsInterval, 'maxbytespersecond' => $job->maxBytesPerSecond,
            'custom_params' => $job->customParams, 'source_id' => $job->sourceId];
        // Columns with a property of the same name
        foreach (['user1', 'password1', 'subfolder2', 'exclude', 'maxage', 'timeout1', 'timeout2', 'active', 'delete1', 'delete2',
            'delete2duplicates', 'automap', 'skipcrossduplicates', 'subscribeall', 'dry'] as $column) {
            $values[$column] = $job->$column;
        }
        return $values;
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
