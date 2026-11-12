<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Routing;

use Mailcow\Tests\Api\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** relayhost (sender-dependent transports) and transport (transport maps): json_api.php handles both alike */
class TransportRoutesTest extends ApiTestCase
{
    public static function transports(): array
    {
        return [
            'relayhost' => ['relayhost',
                ['hostname' => '[smtp.example.com]:587', 'username' => 'relay-user', 'password' => 'secret'],
                ['id' => 1, 'hostname' => '[smtp.example.com]:587', 'username' => 'relay-user', 'active' => 1, 'used_by_domains' => 'example.org']],
            'transport' => ['transport',
                ['destination' => 'example.com, .example.com', 'nexthop' => '[mx.example.com]:25', 'is_mx_based' => '0', 'active' => '1'],
                ['id' => 1, 'is_mx_based' => 0, 'destination' => 'example.com', 'nexthop' => '[mx.example.com]:25', 'active' => 1]],
        ];
    }

    #[DataProvider('transports')]
    public function testAddPassesTheBody(string $category, array $attr): void
    {
        $result = $this->post("add/$category", $attr);

        $this->assertSame([['add', $attr]], $result->callsTo($category));
    }

    #[DataProvider('transports')]
    public function testEditMergesItemsAndAttributes(string $category): void
    {
        $result = $this->post("edit/$category", ['items' => [1, 2], 'attr' => ['active' => '0', 'password' => '']]);

        $this->assertSame([['edit', ['id' => [1, 2], 'active' => '0', 'password' => '']]], $result->callsTo($category));
    }

    #[DataProvider('transports')]
    public function testDeleteTakesIds(string $category): void
    {
        $result = $this->post("delete/$category", ['1', 2]);

        $this->assertSame([['delete', ['id' => ['1', 2]]]], $result->callsTo($category));
    }

    #[DataProvider('transports')]
    public function testGetAllCollectsTheDetailsOfEveryRow(string $category, array $attr, array $details): void
    {
        $result = $this->get("get/$category/all", ['script' => [
            self::answer($category, ['get'], [['id' => 1, 'active' => 1], ['id' => 2, 'active' => 1], ['id' => 3, 'active' => 0]]),
            self::answer($category, ['details', 1], $details),
            self::answer($category, ['details', 2], false),
            self::answer($category, ['details', 3], ['id' => 3] + $details),
        ]]);

        $this->assertSame([$details, ['id' => 3] + $details], $result->json());
        $this->assertSame([['get'], ['details', 1], ['details', 2], ['details', 3]], $result->callsTo($category));
    }

    #[DataProvider('transports')]
    public function testGetAllWithoutEntries(string $category): void
    {
        $none = $this->get("get/$category/all", ['script' => [self::answer($category, ['get'], [])]]);
        $this->assertSame('{}', $none->body);

        $noDetails = $this->get("get/$category/all", ['script' => [
            self::answer($category, ['get'], [['id' => 1]]),
            self::answer($category, ['details'], false),
        ]]);
        $this->assertSame('{}', $noDetails->body);
    }

    #[DataProvider('transports')]
    public function testGetOne(string $category, array $attr, array $details): void
    {
        $found = $this->get("get/$category/1", ['script' => [self::answer($category, ['details', '1'], $details)]]);
        $this->assertSame($details, $found->json());

        $missing = $this->get("get/$category/9", ['script' => [self::answer($category, ['details', '9'], false)]]);
        $this->assertSame('{}', $missing->body);
    }
}
