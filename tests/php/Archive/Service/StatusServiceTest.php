<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Service;

use Mailcow\Archive\Legacy\LegacyQueue;
use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Service\StatusService;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeContext;
use Mailcow\Tests\Support\FakeRedis;
use PHPUnit\Framework\TestCase;

class StatusServiceTest extends TestCase
{
    private const REFUSED = 'connect to archive.example[192.0.2.5]:25: Connection refused';

    private FakeRedis $redis;

    protected function setUp(): void
    {
        $this->redis = new FakeRedis();
    }

    private function service(?Context $context = null): StatusService
    {
        $settings = new Settings();
        $settings->enabled = true;
        $settings->address = 'journal@archive.example';
        $settings->profile = 'none';
        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('load')->willReturn($settings);
        $queue = $this->createMock(LegacyQueue::class);
        $queue->method('entries')->with('archive-bounce@localhost')->willReturn([
            ['queue_id' => 'A1', 'arrival_time' => 1700000100, 'message_size' => 2048, 'recipients' => ['journal@archive.example (' . self::REFUSED . ')']],
            ['queue_id' => 'A2', 'arrival_time' => 1700000000, 'message_size' => 1024, 'recipients' => ['journal@archive.example (' . self::REFUSED . ')']],
            ['queue_id' => 'A3', 'arrival_time' => 1700000200, 'message_size' => 10, 'recipients' => ['journal@archive.example']],
        ]);
        return new StatusService($repo, new ArchiveState($this->redis), $queue, $context ?? new Context('admin', 'API', [], false, 'ro'));
    }

    /** POSTFIX_MAILLOG line as syslog-ng writes it */
    private static function log(int $time, string $program, string $message): string
    {
        return json_encode(['time' => (string)$time, 'program' => $program, 'priority' => 'info', 'message' => $message]);
    }

    public function testStatus(): void
    {
        $state = new ArchiveState($this->redis);
        // a day old: no longer a problem, still counted
        $this->redis->lPush('ARCHIVE_DROPPED_LOG', json_encode(['time' => time() - 90000, 'qid' => 'OLD1', 'reason' => 'old', 'message_id' => '']));
        $state->dropped('4XyZ1', 'owner lookup failed', 'm1@firma.de');
        $this->redis->lists['POSTFIX_MAILLOG'] = [
            self::log(1700000300, 'postfix/archive/smtp', 'A4: to=<journal@archive.example>, relay=archive.example[192.0.2.5]:25, delay=0.2, '
                . 'delays=0.1/0/0.05/0.05, dsn=2.0.0, status=sent (250 2.0.0 Ok: queued as 77)'),
            self::log(1700000280, 'postfix/archive/smtp', 'A1: to=<journal@archive.example>, relay=none, delay=180, dsn=4.4.1, status=deferred (' . self::REFUSED . ')'),
            self::log(1700000200, 'postfix/archive/smtp', 'A0: to=<journal@archive.example>, relay=archive.example[192.0.2.5]:25, dsn=2.0.0, status=sent (250 ok)'),
        ];

        $this->assertSame([
            'enabled' => 1, 'configured' => 1, 'queued' => 3, 'queued_bytes' => 3082, 'oldest_queued' => 1700000000,
            'delay_reasons' => [self::REFUSED], 'last_delivery' => 1700000300, 'dropped' => 1, 'dropped_24h' => 1,
            'last_dropped' => ['time' => $this->redis->lists['ARCHIVE_DROPPED_LOG'] ? json_decode($this->redis->lists['ARCHIVE_DROPPED_LOG'][0], true)['time'] : 0,
                'reason' => 'owner lookup failed'],
        ], $this->service()->status());
    }

    public function testLogWithMessageIds(): void
    {
        (new ArchiveState($this->redis))->dropped('4XyZ1', 'owner lookup failed', 'm1@firma.de');
        $this->redis->lists['POSTFIX_MAILLOG'] = [
            self::log(1700000310, 'postfix/qmgr', 'E1: from=<archive-bounce@localhost>, status=expired, returned to sender'),
            self::log(1700000300, 'postfix/archive/smtp', 'A4: to=<journal@archive.example>, relay=archive.example[192.0.2.5]:25, dsn=2.0.0, '
                . 'status=sent (250 2.0.0 Ok: queued as 77)'),
            self::log(1700000295, 'postfix/qmgr', 'Q9: from=<someone@firma.de>, status=expired, returned to sender'),
            self::log(1700000290, 'postfix/smtp', 'B1: to=<x@extern.example>, relay=mx.extern.example[198.51.100.7]:25, dsn=2.0.0, status=sent (250 Ok)'),
            self::log(1700000285, 'postfix/archive/discard', 'D1: to=<archive-bounce@localhost>, relay=none, dsn=2.0.0, status=sent (localhost)'),
            self::log(1700000280, 'postfix/archive/smtp', 'A2: to=<journal@archive.example>, relay=archive.example[192.0.2.5]:25, dsn=4.1.1, '
                . 'status=SOFTBOUNCE (host archive.example[192.0.2.5] said: 550 5.1.1 unknown (in reply to RCPT TO command))'),
            self::log(1700000270, 'postfix/archive/smtp', 'Untrusted TLS connection established to archive.example[192.0.2.5]:25'),
            self::log(1700000262, 'postfix/cleanup', 'A4: message-id=<m4@extern.example>'),
            self::log(1700000261, 'postfix/cleanup', 'A2: message-id=<m2@firma.de>'),
            'not json',
        ];

        $log = $this->service()->log();

        // the mail not archived just now is the newest entry
        $this->assertSame(['status' => 'dropped', 'qid' => '4XyZ1', 'message_id' => 'm1@firma.de', 'text' => 'owner lookup failed'],
            array_diff_key($log[0], ['time' => 0]));
        $this->assertSame([
            ['time' => 1700000310, 'status' => 'expired', 'qid' => 'E1', 'message_id' => '', 'text' => ''],
            ['time' => 1700000300, 'status' => 'sent', 'qid' => 'A4', 'message_id' => 'm4@extern.example', 'text' => '250 2.0.0 Ok: queued as 77'],
            ['time' => 1700000280, 'status' => 'softbounce', 'qid' => 'A2', 'message_id' => 'm2@firma.de',
                'text' => 'host archive.example[192.0.2.5] said: 550 5.1.1 unknown (in reply to RCPT TO command)'],
        ], array_slice($log, 1));
    }

    public function testOnlyAdmins(): void
    {
        foreach (['status', 'log'] as $method) {
            try {
                $this->service(FakeContext::domainAdmin(acl: ['archive' => '1']))->$method();
                $this->fail('UserError expected');
            } catch (UserError) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
