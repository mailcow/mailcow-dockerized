<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Routing;

use Mailcow\Tests\Api\ApiTestCase;

/** add/edit/delete/get alias */
class AliasRoutesTest extends ApiTestCase
{
    private const SALES = ['id' => 12, 'address' => 'sales@example.org', 'goto' => 'anna@example.org,ben@example.org', 'active' => 1];
    private const INFO = ['id' => 13, 'address' => 'info@example.org', 'goto' => 'anna@example.org', 'active' => 1];
    private const SHOP = ['id' => 20, 'address' => 'shop@example.net', 'goto' => 'ben@example.org', 'active' => 0];

    public function testAddPassesTheBody(): void
    {
        $attr = [
            'address' => "sales@example.org, info@example.org",
            'goto' => 'anna@example.org,ben@example.org',
            'active' => '1',
            'sogo_visible' => 1,
            'internal' => '0',
            'sender_allowed' => true,
            'public_comment' => 'Sales team',
        ];

        $result = $this->post('add/alias', $attr + ['csrf_token' => 'x']);

        $this->assertSame([['add', 'alias', $attr]], $result->callsTo('mailbox'));
    }

    public function testEditMergesItemsAndAttributes(): void
    {
        $list = $this->post('edit/alias', ['items' => [12, 'info@example.org'], 'attr' => ['goto_null' => '1', 'active' => '0']]);
        $this->assertCalled($list, 'mailbox', ['edit', 'alias', ['id' => [12, 'info@example.org'], 'goto_null' => '1', 'active' => '0']]);

        $single = $this->post('edit/alias', ['items' => 12, 'attr' => ['expand_alias' => true]]);
        $this->assertCalled($single, 'mailbox', ['edit', 'alias', ['id' => [12], 'expand_alias' => true]]);
    }

    public function testDeleteTakesIdsOrAddresses(): void
    {
        $result = $this->post('delete/alias', [12, 'info@example.org']);

        $this->assertSame([['delete', 'alias', ['id' => [12, 'info@example.org']]]], $result->callsTo('mailbox'));
    }

    public function testGetOneAliasByIdOrAddress(): void
    {
        $byId = $this->get('get/alias/12', ['script' => [self::answer('mailbox', ['get', 'alias_details', '12'], self::SALES)]]);
        $this->assertSame(self::SALES, $byId->json());

        $byAddress = $this->get('get/alias/sales@example.org');
        $this->assertSame([['get', 'alias_details', 'sales@example.org']], $byAddress->callsTo('mailbox'));
        $this->assertSame('{}', $byAddress->body);
    }

    public function testGetAllWalksTheDomainsAndAliasDomains(): void
    {
        $result = $this->get('get/alias/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org', 'example.com']),
            self::answer('mailbox', ['get', 'alias_domains'], ['example.net']),
            self::answer('mailbox', ['get', 'aliases', 'example.org'], [12, 13, 14]),
            self::answer('mailbox', ['get', 'aliases', 'example.com'], []),
            self::answer('mailbox', ['get', 'aliases', 'example.net'], [20]),
            self::answer('mailbox', ['get', 'alias_details', 12], self::SALES),
            self::answer('mailbox', ['get', 'alias_details', 13], self::INFO),
            self::answer('mailbox', ['get', 'alias_details', 14], false),
            self::answer('mailbox', ['get', 'alias_details', 20], self::SHOP),
        ]]);

        $this->assertSame([self::SALES, self::INFO, self::SHOP], $result->json());
        $this->assertSame([
            ['get', 'domains'],
            ['get', 'alias_domains'],
            ['get', 'aliases', 'example.org'],
            ['get', 'alias_details', 12],
            ['get', 'alias_details', 13],
            ['get', 'alias_details', 14],
            ['get', 'aliases', 'example.com'],
            ['get', 'aliases', 'example.net'],
            ['get', 'alias_details', 20],
        ], $result->callsTo('mailbox'));
    }

    public function testGetAllOfTheNamedDomains(): void
    {
        $result = $this->get('get/alias/all/example.org,example.net', ['script' => [
            self::answer('mailbox', ['get', 'aliases', 'example.org'], [12]),
            self::answer('mailbox', ['get', 'aliases', 'example.net'], [20]),
            self::answer('mailbox', ['get', 'alias_details', 12], self::SALES),
            self::answer('mailbox', ['get', 'alias_details', 20], self::SHOP),
        ]]);

        $this->assertSame([self::SALES, self::SHOP], $result->json());
        $this->assertSame([
            ['get', 'aliases', 'example.org'],
            ['get', 'alias_details', 12],
            ['get', 'aliases', 'example.net'],
            ['get', 'alias_details', 20],
        ], $result->callsTo('mailbox'));
    }

    public function testGetAllWithoutAliases(): void
    {
        $noDomains = $this->get('get/alias/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], []),
            self::answer('mailbox', ['get', 'alias_domains'], []),
        ]]);
        $this->assertSame('{}', $noDomains->body);

        $noAliases = $this->get('get/alias/all/example.org', ['script' => [self::answer('mailbox', ['get', 'aliases'], false)]]);
        $this->assertSame('{}', $noAliases->body);
    }
}
