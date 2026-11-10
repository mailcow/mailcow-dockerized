<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Routing;

use Mailcow\Tests\Api\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** bcc, recipient_map and tls-policy-map: json_api.php handles the three alike */
class MapRoutesTest extends ApiTestCase
{
    public static function maps(): array
    {
        return [
            'bcc' => ['bcc', 'bcc',
                ['local_dest' => 'example.org', 'bcc_dest' => 'archive@example.com', 'type' => 'rcpt', 'active' => '1'],
                ['id' => 3, 'local_dest' => '@example.org', 'bcc_dest' => 'archive@example.com', 'active' => 1, 'type' => 'rcpt', 'domain' => 'example.org']],
            'recipient_map' => ['recipient_map', 'recipient_map',
                ['recipient_map_old' => 'old@example.org', 'recipient_map_new' => 'new@example.org', 'active' => 1],
                ['id' => 3, 'recipient_map_old' => 'old@example.org', 'recipient_map_new' => 'new@example.org', 'active' => 1]],
            'tls-policy-map' => ['tls-policy-map', 'tls_policy_maps',
                ['dest' => 'example.com', 'policy' => 'encrypt', 'parameters' => 'protocols=>=TLSv1.2', 'active' => '1'],
                ['id' => 3, 'dest' => 'example.com', 'policy' => 'encrypt', 'parameters' => '', 'active' => 1]],
        ];
    }

    #[DataProvider('maps')]
    public function testAddPassesTheBody(string $category, string $function, array $attr): void
    {
        $result = $this->post("add/$category", $attr);

        $this->assertSame([['add', $attr]], $result->callsTo($function));
    }

    #[DataProvider('maps')]
    public function testEditMergesItemsAndAttributes(string $category, string $function): void
    {
        $list = $this->post("edit/$category", ['items' => [3, '4'], 'attr' => ['active' => '0']]);
        $this->assertSame([['edit', ['id' => [3, '4'], 'active' => '0']]], $list->callsTo($function));

        $single = $this->post("edit/$category", ['items' => 3, 'attr' => ['active' => '1']]);
        $this->assertSame([['edit', ['id' => [3], 'active' => '1']]], $single->callsTo($function));
    }

    #[DataProvider('maps')]
    public function testDeleteTakesIds(string $category, string $function): void
    {
        $result = $this->post("delete/$category", [3, 4]);

        $this->assertSame([['delete', ['id' => [3, 4]]]], $result->callsTo($function));
    }

    #[DataProvider('maps')]
    public function testGetAllCollectsTheDetailsOfEveryId(string $category, string $function, array $attr, array $details): void
    {
        $result = $this->get("get/$category/all", ['script' => [
            self::answer($function, ['get'], [3, 4, 5]),
            self::answer($function, ['details', 3], $details),
            self::answer($function, ['details', 4], false),
            self::answer($function, ['details', 5], ['id' => 5] + $details),
        ]]);

        $this->assertSame([$details, ['id' => 5] + $details], $result->json());
        $this->assertSame([['get'], ['details', 3], ['details', 4], ['details', 5]], $result->callsTo($function));
    }

    #[DataProvider('maps')]
    public function testGetAllWithoutEntries(string $category, string $function): void
    {
        $result = $this->get("get/$category/all", ['script' => [self::answer($function, ['get'], [])]]);

        $this->assertSame('{}', $result->body);
    }

    #[DataProvider('maps')]
    public function testGetOneAddsANullMember(string $category, string $function, array $attr, array $details): void
    {
        // json_api.php appends the undefined $details to the answer
        $found = $this->get("get/$category/3", ['script' => [self::answer($function, ['details', '3'], $details)]]);
        $this->assertSame($details + [0 => null], $found->json());

        $missing = $this->get("get/$category/9", ['script' => [self::answer($function, ['details', '9'], false)]]);
        $this->assertSame('{}', $missing->body);
    }
}
