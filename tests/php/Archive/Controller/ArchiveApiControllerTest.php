<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Controller;

use Mailcow\Archive\Controller\ArchiveApiController;
use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Service\JournalService;
use Mailcow\Archive\Service\MailboxListService;
use Mailcow\Archive\Service\SettingsService;
use Mailcow\Archive\Service\StatusService;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ArchiveApiControllerTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private StatusService&MockObject $status;
    private JournalService&MockObject $journal;
    private MailboxListService&MockObject $mailboxes;
    private ArchiveApiController $api;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->settings = $this->createMock(SettingsService::class);
        $this->status = $this->createMock(StatusService::class);
        $this->journal = $this->createMock(JournalService::class);
        $this->mailboxes = $this->createMock(MailboxListService::class);
        $this->api = new ArchiveApiController($this->settings, $this->status, $this->journal, $this->mailboxes,
            new ApiResponse(['password', 'signing_secret']));
    }

    /** Messages written to the session as [type, msg] */
    private function messages(): array
    {
        return array_map(fn(array $m): array => [$m['type'], $m['msg']], $_SESSION['return'] ?? []);
    }

    public function testSaveLogsWithoutSecrets(): void
    {
        $this->settings->expects($this->once())->method('save')->willReturn(new Settings());

        $this->assertTrue($this->api->edit(['enabled' => '1', 'username' => 'mailcow', 'password' => 'pw', 'signing_secret' => 'key']));

        $this->assertSame([[
            'type' => 'success',
            'log' => ['archive', 'edit', 'settings', ['enabled' => '1', 'username' => 'mailcow', 'password' => '*', 'signing_secret' => '*']],
            'msg' => 'archive_settings_saved',
        ]], $_SESSION['return']);
    }

    public function testActionsAndErrors(): void
    {
        $this->settings->method('test')->willReturnOnConsecutiveCalls(
            ['archive_test_ok_missing', '8BITMIME'],
            $this->throwException(new UserError('archive_test_failed', ['archive.example:25 connect: Connection refused'])),
        );
        $this->journal->method('testMail')->willReturn('4XyZ<1>');
        $this->settings->method('save')->willThrowException(new PDOException('gone away'));

        $this->assertTrue($this->api->edit(['action' => 'test']));
        $this->assertFalse($this->api->edit(['action' => 'test']));
        $this->assertTrue($this->api->edit(['action' => 'testmail']));
        $this->assertFalse($this->api->edit(['address' => 'journal@archive.example']));

        $this->assertSame([
            ['warning', ['archive_test_ok_missing', '8BITMIME']],
            ['danger', ['archive_test_failed', 'archive.example:25 connect: Connection refused']],
            ['success', ['archive_testmail_queued', '4XyZ&lt;1&gt;']],
            ['danger', ['mysql_error', 'gone away']],
        ], $this->messages());
    }

    public function testActionsNeedWriteAccess(): void
    {
        $this->journal->method('testMail')->willThrowException(new AccessDenied());
        $this->settings->expects($this->never())->method('save');

        $this->assertFalse($this->api->edit(['action' => 'testmail']));
        $this->assertSame([['danger', 'access_denied']], $this->messages());
    }

    public function testReadsAreEmptyWhenDenied(): void
    {
        $this->settings->method('get')->willThrowException(new AccessDenied());
        $this->status->method('status')->willThrowException(new AccessDenied());
        $this->status->method('log')->willThrowException(new AccessDenied());
        $this->mailboxes->method('archived')->willReturn(['enabled' => 1, 'mailboxes' => []]);

        $this->assertSame([], $this->api->getSettings());
        $this->assertSame([], $this->api->getStatus());
        $this->assertSame([], $this->api->getLog());
        $this->assertSame(['enabled' => 1, 'mailboxes' => []], $this->api->getMailboxes());
    }
}
