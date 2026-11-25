<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** get/rl-domain, edit/rl-domain */
class DomainRatelimitTest extends ApiTestCase
{
    public function testRatelimitOfADomain(): void
    {
        $result = $this->get('get/rl-domain/example.org', ['script' => [self::answer('ratelimit', ['get', 'domain', 'example.org'], ['value' => '100', 'frame' => 'h'])]]);

        $this->assertSame([['get', 'domain', 'example.org']], $result->callsTo('ratelimit'));
        $this->assertSame(['value' => '100', 'frame' => 'h'], $result->json());
    }

    public function testDomainWithoutRatelimit(): void
    {
        $result = $this->get('get/rl-domain/example.org', ['script' => [self::answer('ratelimit', ['get', 'domain'], false)]]);

        $this->assertSame('{}', $result->body);
    }

    public function testAllRatelimitsOfDomainsAndAliasDomains(): void
    {
        $result = $this->get('get/rl-domain/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org', 'example.com']),
            self::answer('mailbox', ['get', 'alias_domains'], ['example.net']),
            self::answer('ratelimit', ['get', 'domain', 'example.org'], ['value' => '100', 'frame' => 'h']),
            self::answer('ratelimit', ['get', 'domain', 'example.com'], false),
            self::answer('ratelimit', ['get', 'domain', 'example.net'], ['value' => '10', 'frame' => 's']),
        ]]);

        $this->assertSame([['get', 'domains'], ['get', 'alias_domains']], $result->callsTo('mailbox'));
        $this->assertSame([['get', 'domain', 'example.org'], ['get', 'domain', 'example.com'], ['get', 'domain', 'example.net']], $result->callsTo('ratelimit'));
        $this->assertSame([
            ['value' => '100', 'frame' => 'h', 'domain' => 'example.org'],
            ['value' => '10', 'frame' => 's', 'domain' => 'example.net'],
        ], $result->json());
    }

    public function testAllWithoutRatelimits(): void
    {
        $none = $this->get('get/rl-domain/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org']),
            self::answer('mailbox', ['get', 'alias_domains'], []),
            self::answer('ratelimit', ['get', 'domain'], false),
        ]]);
        $this->assertSame('{}', $none->body);

        $noDomains = $this->get('get/rl-domain/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], []),
            self::answer('mailbox', ['get', 'alias_domains'], []),
        ]]);
        $this->assertSame('{}', $noDomains->body);
        $this->assertNotCalled($noDomains, 'ratelimit');
    }

    public function testSetRatelimits(): void
    {
        $result = $this->post('edit/rl-domain', ['items' => ['example.org', 'example.net'], 'attr' => ['rl_value' => 100, 'rl_frame' => 'h']]);

        $this->assertSame([['edit', 'domain', ['object' => ['example.org', 'example.net'], 'rl_value' => 100, 'rl_frame' => 'h']]], $result->callsTo('ratelimit'));
    }
}
