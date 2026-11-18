<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiTestCase;

/** Resources (rooms, equipment, groups) */
class ResourceRoutesTest extends ApiTestCase
{
    public function testAddResource(): void
    {
        $body = ['domain' => 'example.org', 'description' => 'Meeting Room 1', 'kind' => 'location', 'multiple_bookings' => 0, 'active' => 1];

        $this->assertCalled($this->post('add/resource', $body), 'mailbox', ['add', 'resource', $body]);
    }

    public function testGetAllResourcesWalksAllDomains(): void
    {
        $result = $this->get('get/resource/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org', 'example.com']),
            self::answer('mailbox', ['get', 'resources', 'example.org'], ['room1@example.org', 'gone@example.org']),
            self::answer('mailbox', ['get', 'resource_details', 'room1@example.org'], ['name' => 'room1@example.org']),
            self::answer('mailbox', ['get', 'resource_details', 'gone@example.org'], false),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'resources', 'example.com']);
        $this->assertSame([['name' => 'room1@example.org']], $result->json());
        $this->assertSame('{}', $this->get('get/resource/all')->body);
    }

    public function testGetOneResource(): void
    {
        $result = $this->get('get/resource/room1@example.org', ['script' => [self::answer('mailbox', ['get', 'resource_details'], ['name' => 'room1@example.org'])]]);

        $this->assertSame([['get', 'resource_details', 'room1@example.org']], $result->callsTo('mailbox'));
        $this->assertSame(['name' => 'room1@example.org'], $result->json());
    }

    public function testEditAndDeleteResources(): void
    {
        $edit = $this->post('edit/resource', ['items' => ['room1@example.org'], 'attr' => ['multiple_bookings' => -1]]);
        $this->assertCalled($edit, 'mailbox', ['edit', 'resource', ['name' => ['room1@example.org'], 'multiple_bookings' => -1]]);

        $delete = $this->post('delete/resource', ['room1@example.org']);
        $this->assertCalled($delete, 'mailbox', ['delete', 'resource', ['name' => ['room1@example.org']]]);
    }
}
