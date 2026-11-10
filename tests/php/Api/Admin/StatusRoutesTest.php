<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes status/<object> */
class StatusRoutesTest extends ApiTestCase
{
    public function testContainersAreReducedToStateStartAndImage(): void
    {
        $info = [
            'dovecot-mailcow' => ['State' => ['Status' => 'running', 'StartedAt' => '2026-11-10T11:00:51.541059812Z', 'Pid' => 42],
                'Config' => ['Image' => 'ghcr.io/mailcow/dovecot:2.3.21.1-4', 'Hostname' => 'dovecot'], 'Id' => '5d0f3cbb2a6e'],
            'ofelia-mailcow' => ['State' => ['Status' => 'exited', 'StartedAt' => '2026-11-09T08:00:00Z'],
                'Config' => ['Image' => 'mcuadros/ofelia:latest'], 'Id' => '77aa01'],
        ];

        $result = $this->get('get/status/containers', ['script' => [self::answer('docker', ['info'], $info)]]);

        $this->assertSame([['info']], $result->callsTo('docker'));
        $this->assertSame([
            'dovecot-mailcow' => ['type' => 'info', 'container' => 'dovecot-mailcow', 'state' => 'running',
                'started_at' => '2026-11-10T11:00:51.541059812Z', 'image' => 'ghcr.io/mailcow/dovecot:2.3.21.1-4'],
            'ofelia-mailcow' => ['type' => 'info', 'container' => 'ofelia-mailcow', 'state' => 'exited',
                'started_at' => '2026-11-09T08:00:00Z', 'image' => 'mcuadros/ofelia:latest'],
        ], $result->json());
        $this->assertStringContainsString('"ghcr.io/mailcow/dovecot', $result->body);
    }

    public function testContainersWithoutDockerapi(): void
    {
        $result = $this->get('get/status/containers', ['script' => [self::answer('docker', ['info'], 'Could not resolve host: dockerapi')]]);

        $this->assertSame('null', $result->body);
    }

    public function testContainerStatsByContainerId(): void
    {
        $stats = [['read' => '2026-11-10T19:20:01Z', 'pids_stats' => ['current' => 38]], ['read' => '2026-11-10T19:20:06Z', 'pids_stats' => ['current' => 39]]];

        $result = $this->get('get/status/container/5d0f3cbb2a6e', ['script' => [self::answer('docker', ['container_stats'], $stats)]]);

        $this->assertSame([['container_stats', '5d0f3cbb2a6e']], $result->callsTo('docker'));
        $this->assertSame($stats, $result->json());
    }

    public function testContainerStatsWithoutId(): void
    {
        $result = $this->get('get/status/container', ['script' => [self::answer('docker', ['container_stats'], false)]]);

        $this->assertSame([['container_stats', null]], $result->callsTo('docker'));
        $this->assertSame('false', $result->body);
    }

    public function testVmailSplitsTheDfLine(): void
    {
        $df = json_encode('/dev/sda1,105G,65G,40G,66%,/var/vmail');

        $result = $this->get('get/status/vmail', ['script' => [self::answer('docker', ['post', 'dovecot-mailcow', 'exec'], $df)]]);

        $this->assertSame([['post', 'dovecot-mailcow', 'exec', ['cmd' => 'system', 'task' => 'df', 'dir' => '/var/vmail']]], $result->callsTo('docker'));
        $this->assertSame(['type' => 'info', 'disk' => '/dev/sda1', 'used' => '65G', 'total' => '105G', 'used_percent' => '66%'], $result->json());
    }

    public function testHost(): void
    {
        $stats = ['cpu' => ['cores' => 4, 'usage' => 6], 'memory' => ['total' => 12541669376, 'usage' => 62.5], 'uptime' => 703800.5,
            'system_time' => '10.11.2026 19:13:07', 'architecture' => 'x86_64'];

        $result = $this->get('get/status/host', ['script' => [self::answer('docker', ['host_stats'], $stats)]]);

        $this->assertSame([['host_stats']], $result->callsTo('docker'));
        $this->assertSame($stats, $result->json());
    }

    public function testPublicAddressesAskMailcowEmail(): void
    {
        $result = $this->get('get/status/host/ip', ['script' => [
            self::answer('curl_exec', [], '198.51.100.7', once: true),
            self::answer('curl_exec', [], '2001:db8::7', once: true),
        ]]);

        $this->assertSame([[null, CURLOPT_URL, 'http://ipv4.mailcow.email'], [null, CURLOPT_URL, 'http://ipv6.mailcow.email']],
            array_values(array_filter($result->callsTo('curl_setopt'), fn(array $args): bool => $args[1] === CURLOPT_URL)));
        $this->assertSame(['ipv4' => '198.51.100.7', 'ipv6' => '2001:db8::7'], $result->json());
    }

    public function testHostWithAnotherSegmentAnswersNothing(): void
    {
        $result = $this->get('get/status/host/cpu');

        $this->assertSame([200, ''], [$result->status, $result->body]);
        $this->assertNotCalled($result, 'docker');
    }

    public function testVersion(): void
    {
        // $MAILCOW_GIT_VERSION comes from app_info.inc.php, which the harness does not load
        $this->assertSame(['version' => null], $this->get('get/status/version')->json());
    }

    public function testUnknownStatusAnswersNothing(): void
    {
        $result = $this->get('get/status/disks');

        $this->assertSame([200, ''], [$result->status, $result->body]);
    }

    public function testOnlyForAdmins(): void
    {
        foreach (['containers', 'vmail', 'host', 'version'] as $object) {
            $result = $this->get("get/status/$object", ['session' => self::DOMAIN_ADMIN]);
            $this->assertSame([200, ''], [$result->status, $result->body], $object);
            $this->assertNotCalled($result, 'docker');
        }
    }
}
