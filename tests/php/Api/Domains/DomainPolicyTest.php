<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** get/policy_wl_domain, get/policy_bl_domain, add/domain-policy, delete/domain-policy */
class DomainPolicyTest extends ApiTestCase
{
    private const LISTS = [
        'whitelist' => [['object' => 'example.org', 'value' => '*@partner.example.com', 'prefid' => 12]],
        'blacklist' => [['object' => 'anna@example.org', 'value' => '*@spam.example.com', 'prefid' => 13]],
    ];

    public function testAllowListOfADomain(): void
    {
        $result = $this->get('get/policy_wl_domain/example.org', ['script' => [self::answer('policy', ['get', 'domain', 'example.org'], self::LISTS)]]);

        $this->assertSame([['get', 'domain', 'example.org']], $result->callsTo('policy'));
        $this->assertSame(self::LISTS['whitelist'], $result->json());
    }

    public function testDenyListOfADomain(): void
    {
        $result = $this->get('get/policy_bl_domain/example.org', ['script' => [self::answer('policy', ['get', 'domain', 'example.org'], self::LISTS)]]);

        $this->assertSame([['get', 'domain', 'example.org']], $result->callsTo('policy'));
        $this->assertSame(self::LISTS['blacklist'], $result->json());
    }

    public function testEmptyListsAndUnknownDomains(): void
    {
        $empty = $this->get('get/policy_wl_domain/example.org', ['script' => [self::answer('policy', ['get', 'domain'], ['whitelist' => [], 'blacklist' => []])]]);
        $this->assertSame('{}', $empty->body);

        $unknown = $this->get('get/policy_bl_domain/example.com', ['script' => [self::answer('policy', ['get', 'domain'], false)]]);
        $this->assertSame('{}', $unknown->body);
    }

    public function testAddEntry(): void
    {
        $attr = ['domain' => 'example.org', 'object_list' => 'wl', 'object_from' => '*@partner.example.com'];

        $result = $this->post('add/domain-policy', $attr);

        $this->assertSame([['add', 'domain', $attr]], $result->callsTo('policy'));
    }

    public function testDeleteEntries(): void
    {
        $result = $this->post('delete/domain-policy', [12, 13]);

        $this->assertSame([['delete', 'domain', ['prefid' => [12, 13]]]], $result->callsTo('policy'));
    }
}
