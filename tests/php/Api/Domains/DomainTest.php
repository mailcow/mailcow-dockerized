<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** get/domain, add/domain, edit/domain, delete/domain, delete/domain/tag, edit/domain/footer */
class DomainTest extends ApiTestCase
{
    public function testAllDomainsAreReadOneByOne(): void
    {
        $result = $this->get('get/domain/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org', 'example.com', 'example.net']),
            self::answer('mailbox', ['get', 'domain_details', 'example.org'], ['domain_name' => 'example.org']),
            self::answer('mailbox', ['get', 'domain_details', 'example.com'], false),
            self::answer('mailbox', ['get', 'domain_details', 'example.net'], ['domain_name' => 'example.net']),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'domains', null, null]);
        $this->assertSame([['domain_name' => 'example.org'], ['domain_name' => 'example.net']], $result->json());
    }

    public function testAllDomainsFilteredByTags(): void
    {
        $result = $this->get('get/domain/all', ['query' => ['tags' => 'customer,premium'], 'script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org']),
            self::answer('mailbox', ['get', 'domain_details', 'example.org'], ['domain_name' => 'example.org', 'tags' => ['customer']]),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'domains', null, ['customer', 'premium']]);
        $this->assertSame([['domain_name' => 'example.org', 'tags' => ['customer']]], $result->json());
    }

    public function testAnEmptyTagsParameterDoesNotFilter(): void
    {
        $result = $this->get('get/domain/all', ['query' => ['tags' => ''], 'script' => [self::answer('mailbox', ['get', 'domains'], [])]]);

        $this->assertCalled($result, 'mailbox', ['get', 'domains', null, null]);
    }

    public function testAllDomainsWithoutDomains(): void
    {
        $result = $this->get('get/domain/all', ['script' => [self::answer('mailbox', ['get', 'domains'], [])]]);

        $this->assertSame('{}', $result->body);
        $this->assertSame([['get', 'domains', null, null]], $result->callsTo('mailbox'));
    }

    public function testAllDomainsWithoutReadableDetails(): void
    {
        $result = $this->get('get/domain/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org']),
            self::answer('mailbox', ['get', 'domain_details'], false),
        ]]);

        $this->assertSame('{}', $result->body);
    }

    public function testOneDomain(): void
    {
        $result = $this->get('get/domain/example.org', ['script' => [
            self::answer('mailbox', ['get', 'domain_details', 'example.org'], ['domain_name' => 'example.org', 'rl' => false]),
        ]]);

        $this->assertSame(['domain_name' => 'example.org', 'rl' => false], $result->json());
        $this->assertSame([['get', 'domain_details', 'example.org']], $result->callsTo('mailbox'));
    }

    public function testUnknownDomain(): void
    {
        $result = $this->get('get/domain/example.com', ['script' => [self::answer('mailbox', ['get', 'domain_details'], false)]]);

        $this->assertSame('{}', $result->body);
    }

    public function testAddDomain(): void
    {
        $attr = ['domain' => 'example.org', 'description' => 'Example Org', 'defquota' => 3072, 'tags' => ['customer'], 'restart_sogo' => '1'];

        $result = $this->post('add/domain', $attr);

        $this->assertCalled($result, 'mailbox', ['add', 'domain', $attr]);
    }

    public function testAddDomainIgnoresFurtherSegments(): void
    {
        $result = $this->post('add/domain/example.org', ['domain' => 'example.org']);

        $this->assertCalled($result, 'mailbox', ['add', 'domain', ['domain' => 'example.org']]);
    }

    public function testEditDomains(): void
    {
        $result = $this->post('edit/domain', ['items' => ['example.org', 'example.com'], 'attr' => ['quota' => 51200, 'tags' => ['premium']]]);

        $this->assertCalled($result, 'mailbox', ['edit', 'domain', ['domain' => ['example.org', 'example.com'], 'quota' => 51200, 'tags' => ['premium']]]);
    }

    public function testEditDomainWithASingleItem(): void
    {
        $result = $this->post('edit/domain', ['items' => 'example.org', 'attr' => ['active' => '0']]);

        $this->assertCalled($result, 'mailbox', ['edit', 'domain', ['domain' => ['example.org'], 'active' => '0']]);
    }

    public function testAnAttributeNamedLikeTheItemsWins(): void
    {
        $result = $this->post('edit/domain', ['items' => ['example.org'], 'attr' => ['domain' => 'example.com']]);

        $this->assertCalled($result, 'mailbox', ['edit', 'domain', ['domain' => 'example.com']]);
    }

    public function testDeleteDomains(): void
    {
        $result = $this->post('delete/domain', ['example.org', 'example.com']);

        $this->assertCalled($result, 'mailbox', ['delete', 'domain', ['domain' => ['example.org', 'example.com']]]);
    }

    public function testDeleteDomainTags(): void
    {
        $result = $this->post('delete/domain/tag/example.org', ['customer', 'premium']);

        $this->assertCalled($result, 'mailbox', ['delete', 'tags_domain', ['tags' => ['customer', 'premium'], 'domain' => 'example.org']]);
    }

    public function testDeleteDomainTagsWithoutTags(): void
    {
        $result = $this->post('delete/domain/tag/example.org', [], ['script' => [self::answer('mailbox', ['delete', 'tags_domain'], false)]]);

        $this->assertCalled($result, 'mailbox', ['delete', 'tags_domain', ['tags' => [], 'domain' => 'example.org']]);
        $this->assertSame(['type' => 'error', 'msg' => 'Cannot delete item'], $result->json());
    }

    public function testDeleteDomainTagsWithoutDomain(): void
    {
        $result = $this->post('delete/domain/tag', ['customer']);

        $this->assertCalled($result, 'mailbox', ['delete', 'tags_domain', ['tags' => ['customer'], 'domain' => null]]);
    }

    public function testEditDomainFooter(): void
    {
        $attr = ['html' => '<p>{= from_name =}</p>', 'plain' => '{= from_name =}', 'skip_replies' => 1, 'exclude' => ['noreply@example.org', 'example.net']];

        $result = $this->post('edit/domain/footer', ['items' => ['example.org'], 'attr' => $attr]);

        $this->assertCalled($result, 'mailbox', ['edit', 'domain_wide_footer', ['domains' => ['example.org']] + $attr]);
    }
}
