<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Routing;

use Mailcow\Tests\Api\ApiTestCase;

/** add/edit/delete/get fwdhost */
class ForwardingHostRoutesTest extends ApiTestCase
{
    private const HOSTS = [
        ['host' => '192.0.2.25', 'source' => 'relay.example.com', 'keep_spam' => 'yes'],
        ['host' => '2001:db8::25', 'source' => 'relay.example.com', 'keep_spam' => 'yes'],
    ];

    public function testAddPassesTheBody(): void
    {
        $result = $this->post('add/fwdhost', ['hostname' => 'relay.example.com', 'filter_spam' => '1']);

        $this->assertSame([['add', ['hostname' => 'relay.example.com', 'filter_spam' => '1']]], $result->callsTo('fwdhost'));
    }

    public function testEditNamesTheHostsFwdhost(): void
    {
        $result = $this->post('edit/fwdhost', ['items' => ['192.0.2.25', '198.51.100.0/24'], 'attr' => ['keep_spam' => '0']]);

        $this->assertSame([['edit', ['fwdhost' => ['192.0.2.25', '198.51.100.0/24'], 'keep_spam' => '0']]], $result->callsTo('fwdhost'));
    }

    public function testDeleteNamesTheHostsForwardinghost(): void
    {
        $result = $this->post('delete/fwdhost', ['192.0.2.25', '2001:db8::25']);

        $this->assertSame([['delete', ['forwardinghost' => ['192.0.2.25', '2001:db8::25']]]], $result->callsTo('fwdhost'));
    }

    public function testGetAllPassesTheList(): void
    {
        $result = $this->get('get/fwdhost/all', ['script' => [self::answer('fwdhost', ['get'], self::HOSTS)]]);

        $this->assertSame(self::HOSTS, $result->json());
        $this->assertSame([['get']], $result->callsTo('fwdhost'));

        $this->assertSame('{}', $this->get('get/fwdhost/all', ['script' => [self::answer('fwdhost', ['get'], [])]])->body);
    }

    public function testGetOneHost(): void
    {
        $found = $this->get('get/fwdhost/192.0.2.25', ['script' => [self::answer('fwdhost', ['details', '192.0.2.25'], self::HOSTS[0])]]);
        $this->assertSame(self::HOSTS[0], $found->json());

        $this->assertSame('{}', $this->get('get/fwdhost/192.0.2.99', ['script' => [self::answer('fwdhost', ['details'], [])]])->body);
    }

    public function testGetOneNetworkIsCutAtTheSlash(): void
    {
        $result = $this->get('get/fwdhost/198.51.100.0/24');

        $this->assertSame([['details', '198.51.100.0']], $result->callsTo('fwdhost'));
    }
}
