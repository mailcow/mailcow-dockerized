<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Model;

use Mailcow\Archive\Model\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public static function nexthops(): array
    {
        return [
            'mx of the address domain' => ['', 25, 'archive.example'],
            'mx, other port' => ['', 2525, 'archive.example:2525'],
            'host' => ['smtp.archive.example', 25, '[smtp.archive.example]:25'],
            'ipv4' => ['192.0.2.5', 587, '[192.0.2.5]:587'],
            'ipv6' => ['2001:db8::25', 25, '[ipv6:2001:db8::25]:25'],
        ];
    }

    #[DataProvider('nexthops')]
    public function testNexthop(string $host, int $port, string $nexthop): void
    {
        $settings = new Settings();
        $settings->address = 'journal@archive.example';
        $settings->host = $host;
        $settings->port = $port;

        $this->assertSame($nexthop, $settings->nexthop());
    }

    public function testCanDeliverAndNoSecretsInTheApi(): void
    {
        $settings = new Settings();
        $this->assertFalse($settings->canDeliver());
        $settings->address = 'journal@archive.example';
        $this->assertFalse($settings->canDeliver());
        $settings->profile = 'envelope';
        $this->assertTrue($settings->canDeliver());

        $settings->password = 'password-value';
        $settings->signingKey = 'signing-key-value';
        $api = json_encode($settings->toArray());
        $this->assertStringNotContainsString('password-value', $api);
        $this->assertStringNotContainsString('signing-key-value', $api);
        $this->assertSame([1, 1], [$settings->toArray()['has_password'], $settings->toArray()['has_signing_secret']]);
    }
}
