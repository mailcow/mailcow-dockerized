<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** get/add/edit/delete alias-domain */
class AliasDomainTest extends ApiTestCase
{
    public function testAllAliasDomainsAreReadOneByOne(): void
    {
        $result = $this->get('get/alias-domain/all', ['script' => [
            self::answer('mailbox', ['get', 'alias_domains'], ['example.net', 'example.com']),
            self::answer('mailbox', ['get', 'alias_domain_details', 'example.net'], ['alias_domain' => 'example.net', 'target_domain' => 'example.org']),
            self::answer('mailbox', ['get', 'alias_domain_details', 'example.com'], false),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'alias_domains']);
        $this->assertSame([['alias_domain' => 'example.net', 'target_domain' => 'example.org']], $result->json());
    }

    public function testAllAliasDomainsWithoutAliasDomains(): void
    {
        $result = $this->get('get/alias-domain/all', ['script' => [self::answer('mailbox', ['get', 'alias_domains'], [])]]);

        $this->assertSame('{}', $result->body);
        $this->assertSame([['get', 'alias_domains']], $result->callsTo('mailbox'));
    }

    public function testOneAliasDomain(): void
    {
        $result = $this->get('get/alias-domain/example.net', ['script' => [
            self::answer('mailbox', ['get', 'alias_domain_details', 'example.net'], ['alias_domain' => 'example.net', 'rl' => false]),
        ]]);

        $this->assertSame(['alias_domain' => 'example.net', 'rl' => false], $result->json());
    }

    public function testUnknownAliasDomain(): void
    {
        $result = $this->get('get/alias-domain/example.com', ['script' => [self::answer('mailbox', ['get', 'alias_domain_details'], false)]]);

        $this->assertCalled($result, 'mailbox', ['get', 'alias_domain_details', 'example.com']);
        $this->assertSame('{}', $result->body);
    }

    public function testAddAliasDomains(): void
    {
        $attr = ['alias_domain' => "example.net\nexample.com", 'target_domain' => 'example.org', 'active' => '1', 'key_size' => '2048', 'dkim_selector' => 'dkim'];

        $result = $this->post('add/alias-domain', $attr);

        $this->assertCalled($result, 'mailbox', ['add', 'alias_domain', $attr]);
    }

    public function testEditAliasDomains(): void
    {
        $result = $this->post('edit/alias-domain', ['items' => ['example.net', 'example.com'], 'attr' => ['active' => '0']]);

        $this->assertCalled($result, 'mailbox', ['edit', 'alias_domain', ['alias_domain' => ['example.net', 'example.com'], 'active' => '0']]);
    }

    public function testDeleteAliasDomains(): void
    {
        $result = $this->post('delete/alias-domain', ['example.net']);

        $this->assertCalled($result, 'mailbox', ['delete', 'alias_domain', ['alias_domain' => ['example.net']]]);
    }
}
