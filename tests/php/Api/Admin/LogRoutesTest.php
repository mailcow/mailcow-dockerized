<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Routes logs/<type>[/<count>] */
class LogRoutesTest extends ApiTestCase
{
    /** @return array<string, array{0: string, 1: string}> route type => log name of get_logs() */
    public static function logs(): array
    {
        return [
            'dovecot' => ['dovecot', 'dovecot-mailcow'],
            'ratelimited' => ['ratelimited', 'ratelimited'],
            'netfilter' => ['netfilter', 'netfilter-mailcow'],
            'cron' => ['cron', 'cron-mailcow'],
            'postfix' => ['postfix', 'postfix-mailcow'],
            'autodiscover' => ['autodiscover', 'autodiscover-mailcow'],
            'sogo' => ['sogo', 'sogo-mailcow'],
            'ui' => ['ui', 'mailcow-ui'],
            'sasl' => ['sasl', 'sasl'],
            'watchdog' => ['watchdog', 'watchdog-mailcow'],
            'acme' => ['acme', 'acme-mailcow'],
            'api' => ['api', 'api-mailcow'],
            'rspamd-history' => ['rspamd-history', 'rspamd-history'],
        ];
    }

    #[DataProvider('logs')]
    public function testWithoutCount(string $type, string $log): void
    {
        $entries = [['time' => '1794308452', 'message' => 'first'], ['time' => '1794308451', 'message' => 'second']];

        $result = $this->get("get/logs/$type", ['script' => [self::answer('get_logs', [$log], $entries)]]);

        $this->assertSame([[$log]], $result->callsTo('get_logs'));
        $this->assertSame($entries, $result->json());
    }

    #[DataProvider('logs')]
    public function testCountOrRange(string $type, string $log): void
    {
        $this->assertSame([[$log, '50']], $this->get("get/logs/$type/50")->callsTo('get_logs'));
        $this->assertSame([[$log, '2-3']], $this->get("get/logs/$type/2-3")->callsTo('get_logs'));
    }

    #[DataProvider('logs')]
    public function testEmptyLogs(string $type, string $log): void
    {
        $this->assertSame('{}', $this->get("get/logs/$type/5", ['script' => [self::answer('get_logs', [$log], false)]])->body);
        $this->assertSame('{}', $this->get("get/logs/$type", ['script' => [self::answer('get_logs', [$log], [])]])->body);
    }

    public function testCountKeepsOnlyDigitsAndDashes(): void
    {
        $this->assertSame([['postfix-mailcow', '12-30']], $this->get('get/logs/postfix/a12-3x0')->callsTo('get_logs'));
    }

    public function testTrailingSlashPassesAnEmptyCount(): void
    {
        $this->assertSame([['postfix-mailcow', '']], $this->get('get/logs/postfix/')->callsTo('get_logs'));
    }

    public function testRspamdStatsIgnoresTheCount(): void
    {
        $stats = ['version' => '3.12.1', 'scanned' => 300, 'actions' => ['reject' => 38]];

        $result = $this->get('get/logs/rspamd-stats/10', ['script' => [self::answer('get_logs', ['rspamd-stats'], $stats)]]);

        $this->assertSame([['rspamd-stats']], $result->callsTo('get_logs'));
        $this->assertSame($stats, $result->json());
    }

    public function testUnknownLog(): void
    {
        $this->assertApiError($this->get('get/logs/clamd'), 404, 'route not found');
    }
}
