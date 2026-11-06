<?php

declare(strict_types=1);

namespace Mailcow\Archive\Controller;

use Mailcow\Archive\Service\JournalService;
use Mailcow\Archive\Service\MailboxListService;
use Mailcow\Archive\Service\SettingsService;
use Mailcow\Archive\Service\StatusService;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PDOException;

/**
 * HTTP API adapter for json_api.php (routes `archive/*`, `archive`).
 *
 * Write methods return what json_api passes to process_*_return() and write their messages via ApiResponse.
 * Messages are logged as ['archive', action, object, data].
 */
class ArchiveApiController
{
    public function __construct(private SettingsService $settings, private StatusService $status, private JournalService $journal,
        private MailboxListService $mailboxes, private ApiResponse $response)
    {
    }

    /** GET archive/settings */
    public function getSettings(): array
    {
        return $this->read(fn(): array => $this->settings->get()->toArray());
    }

    /** GET archive/status */
    public function getStatus(): array
    {
        return $this->read(fn(): array => $this->status->status());
    }

    /** GET logs/archive[/count] */
    public function getLog(int $count = 0): array
    {
        return $this->read(fn(): array => $this->status->log($count));
    }

    /** GET archive/mailboxes */
    public function getMailboxes(): array
    {
        return $this->read(fn(): array => $this->mailboxes->archived());
    }

    /** POST edit/archive: the settings; `action: test` tests the connection, `action: testmail` sends a test copy */
    public function edit(array $attr): bool
    {
        return match ($attr['action'] ?? null) {
            'test' => $this->handle('edit', 'test', [], fn(array $log): bool => $this->testResult($this->settings->test(), $log)),
            'testmail' => $this->handle('edit', 'testmail', [],
                fn(array $log): bool => $this->response->success(['archive_testmail_queued', htmlspecialchars($this->journal->testMail())], $log)),
            default => $this->handle('edit', 'settings', $attr, function (array $log) use ($attr): bool {
                $this->settings->save($attr);
                return $this->response->success('archive_settings_saved', $log);
            }),
        };
    }

    /** A missing extension is a warning: the archive works, but may refuse some mails */
    private function testResult(string|array $msg, array $log): bool
    {
        if (is_array($msg)) {
            $this->response->warning($msg, $log);
            return true;
        }
        return $this->response->success($msg, $log);
    }

    /** A GET answer, empty when the caller may not read it */
    private function read(callable $work): array
    {
        try {
            return $work();
        } catch (UserError) {
            return [];
        }
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, string $object, mixed $data, callable $work): bool
    {
        $log = ['archive', $action, $object, $data];
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
}
