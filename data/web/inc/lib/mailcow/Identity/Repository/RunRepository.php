<?php

declare(strict_types=1);

namespace Mailcow\Identity\Repository;

use Mailcow\Core\Repository;
use PDO;

/**
 * Sync runs (table `identity_sync_run`): requests from the UI (status pending), the run itself and its summary.
 */
class RunRepository extends Repository
{
    private const KEEP = 30;

    /** Queues a run for the cron; an identical pending request is reused */
    public function request(string $provider, string $mode, string $requestedBy): int
    {
        $id = $this->query("SELECT `id` FROM `identity_sync_run` WHERE `status` = 'pending' AND `mode` = :mode",
            ['mode' => $mode])->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
        return $this->insertRow('identity_sync_run', ['provider' => $provider, 'mode' => $mode, 'status' => 'pending',
            'requested_by' => $requestedBy]);
    }

    /** @return ?array oldest pending request */
    public function nextPending(): ?array
    {
        $row = $this->query("SELECT * FROM `identity_sync_run` WHERE `status` = 'pending' ORDER BY `id` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** A scheduled run (not requested by anyone) */
    public function create(string $provider, string $mode): int
    {
        return $this->insertRow('identity_sync_run', ['provider' => $provider, 'mode' => $mode, 'status' => 'pending', 'requested_by' => '']);
    }

    public function start(int $id): void
    {
        $this->query("UPDATE `identity_sync_run` SET `status` = 'running', `started` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    public function finish(int $id, string $status, array $summary): void
    {
        $this->query("UPDATE `identity_sync_run` SET `status` = :status, `finished` = NOW(), `summary` = :summary WHERE `id` = :id",
            ['status' => $status, 'summary' => json_encode($summary), 'id' => $id]);
        $this->query("DELETE FROM `identity_sync_run` WHERE `id` NOT IN (SELECT `id` FROM (SELECT `id` FROM `identity_sync_run`
          ORDER BY `id` DESC LIMIT " . self::KEEP . ") AS `keep`)");
    }

    /** Seconds since the last scheduled run started, null if there was none */
    public function secondsSinceScheduled(): ?int
    {
        $seconds = $this->query("SELECT TIMESTAMPDIFF(SECOND, MAX(`started`), NOW()) FROM `identity_sync_run`
          WHERE `requested_by` = '' AND `mode` = 'run'")->fetchColumn();
        return ($seconds === false || $seconds === null) ? null : (int)$seconds;
    }

    /** Runs left in status running by a crashed cron are closed, so they do not hide new runs */
    public function failStale(int $minutes): void
    {
        $this->query("UPDATE `identity_sync_run` SET `status` = 'error', `finished` = NOW(),
            `summary` = JSON_OBJECT('error', 'The sync run did not finish')
          WHERE `status` = 'running' AND `started` < NOW() - INTERVAL :minutes MINUTE", ['minutes' => $minutes]);
    }

    /** @return array[] newest first, summary decoded */
    public function recent(int $limit = 10): array
    {
        $rows = $this->query("SELECT * FROM `identity_sync_run` ORDER BY `id` DESC LIMIT " . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['summary'] = ($row['summary'] === null) ? null : json_decode((string)$row['summary'], true);
        }
        return $rows;
    }
}
