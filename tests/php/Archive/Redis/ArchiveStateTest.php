<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Redis;

use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Tests\Support\FakeRedis;
use PHPUnit\Framework\TestCase;

class ArchiveStateTest extends TestCase
{
    private FakeRedis $redis;
    private ArchiveState $state;

    protected function setUp(): void
    {
        $this->redis = new FakeRedis();
        $this->state = new ArchiveState($this->redis);
    }

    /** A Postfix log entry as syslog-ng pushes it (newest first) */
    private function log(string $program, string $message, int $time): void
    {
        $this->redis->lPush('POSTFIX_MAILLOG', json_encode(['time' => $time, 'program' => $program, 'message' => $message]));
    }

    public function testGlobalSwitchIsKeptHere(): void
    {
        // no key yet: off
        $this->assertFalse($this->state->enabled());
        $this->state->setEnabled(true);
        $this->assertSame('1', $this->redis->strings['ARCHIVE_ENABLED']);
        $this->assertTrue($this->state->enabled());
        $this->state->setEnabled(false);
        $this->assertSame('0', $this->redis->strings['ARCHIVE_ENABLED']);
        $this->assertFalse($this->state->enabled());
        // no connection or a failing one: off, rspamd cannot read the switch either
        $this->assertFalse((new ArchiveState(null))->enabled());
        $failing = new class {
            public function get(string $key): string
            {
                throw new \RuntimeException('connection lost');
            }
        };
        $this->assertFalse((new ArchiveState($failing))->enabled());
    }

    public function testDroppedCopiesAreCountedAndTheLast100Listed(): void
    {
        for ($i = 1; $i <= 105; $i++) {
            $this->state->dropped('Q' . $i, 'owner lookup failed', 'm' . $i . '@firma.de');
        }

        $stats = $this->state->droppedStats(2);
        $this->assertSame(105, $stats['count']);
        $this->assertSame(['Q105', 'Q104'], array_column($stats['recent'], 'qid'));
        $this->assertSame(['owner lookup failed', 'm105@firma.de'], [$stats['recent'][0]['reason'], $stats['recent'][0]['message_id']]);
        $this->assertCount(100, $this->redis->lists['ARCHIVE_DROPPED_LOG']);
    }

    public function testEventsOfTheArchiveServiceOnly(): void
    {
        $this->log('postfix/cleanup', '1A2B: message-id=<orig@extern.example>', 1700000000);
        $this->log('postfix/archive/smtp', '1A2B: to=<journal@archive.example>, relay=archive.example[192.0.2.5]:25, delay=0.2, dsn=2.0.0, '
            . 'status=sent (250 2.0.0 Ok: queued as X1)', 1700000001);
        $this->log('postfix/smtp', '3C4D: to=<someone@extern.example>, relay=mx.extern.example[192.0.2.9]:25, dsn=2.0.0, status=sent (250 ok)', 1700000002);
        // notices about copies are dropped by archive_discard, they are no deliveries
        $this->log('postfix/archive/discard', '5E6F: to=<archive-bounce@localhost>, relay=none, dsn=2.0.0, status=sent (discards mail)', 1700000003);
        $this->log('postfix/archive/smtp', 'connect to archive.example[192.0.2.5]:25: Connection refused', 1700000004);
        $this->log('postfix/archive/smtp', '7A8B: to=<journal@archive.example>, relay=archive.example[192.0.2.5]:25, dsn=5.1.1, '
            . 'status=SOFTBOUNCE (host archive.example[192.0.2.5] said: 550 5.1.1 unknown (in reply to RCPT TO command))', 1700000005);
        $this->redis->lPush('POSTFIX_MAILLOG', 'no json');

        $this->assertSame([
            ['time' => 1700000005, 'status' => 'softbounce', 'qid' => '7A8B', 'message_id' => '',
                'text' => 'host archive.example[192.0.2.5] said: 550 5.1.1 unknown (in reply to RCPT TO command)'],
            ['time' => 1700000001, 'status' => 'sent', 'qid' => '1A2B', 'message_id' => 'orig@extern.example', 'text' => '250 2.0.0 Ok: queued as X1'],
        ], $this->state->events());
        $this->assertCount(1, $this->state->events(1));
    }

    public function testWithoutRedisNothingHappens(): void
    {
        $state = new ArchiveState(null);
        $state->setEnabled(true);
        $state->dropped('Q1', 'reason');

        $this->assertSame(['count' => 0, 'recent' => []], $state->droppedStats());
        $this->assertSame([], $state->events());
    }
}
