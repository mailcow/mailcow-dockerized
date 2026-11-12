<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Modules;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes mailbox-group: json_api.php hands them to the MailboxGroup module and passes its answers through */
class MailboxGroupRoutesTest extends ApiTestCase
{
    private const API = 'Mailcow\MailboxGroup\MailboxGroupModule->api->';

    public function testAdd(): void
    {
        $attr = ['domain' => 'example.org', 'name' => 'Sales', 'members' => ['alice@example.org'], 'sender_allowed' => 1];
        $messages = [
            self::message('success', ['group_added', 'Sales'], ['mailbox_group', 'add', 'group', $attr]),
            self::message('warning', ['group_imap_grant_failed', 'shared@example.org'], ['mailbox_group', 'add', 'group', $attr]),
        ];

        $result = $this->post('add/mailbox-group', $attr, ['script' => [self::answer(self::API . 'addGroup', [], true, $messages)]]);

        $this->assertCalled($result, self::API . 'addGroup', [$attr]);
        $this->assertSame($messages, $result->json());
    }

    public function testGetAll(): void
    {
        $groups = [['id' => 7, 'domain' => 'example.org', 'name' => 'Sales', 'can_edit' => true]];

        $result = $this->get('get/mailbox-group/all', ['script' => [self::answer(self::API . 'getGroups', [], $groups)]]);

        $this->assertCalled($result, self::API . 'getGroups', [null]);
        $this->assertSame($groups, $result->json());
    }

    public function testGetAllOfDomains(): void
    {
        $result = $this->get('get/mailbox-group/all/example.org,example.com');

        $this->assertCalled($result, self::API . 'getGroups', ['example.org,example.com']);
    }

    public function testNoGroupsAnswerAnEmptyObject(): void
    {
        $result = $this->get('get/mailbox-group/all', ['script' => [self::answer(self::API . 'getGroups', [], [])]]);

        $this->assertSame('{}', $result->body);
    }

    public function testGetMemberships(): void
    {
        $memberships = ['groups' => [['id' => 7, 'name' => 'Sales', 'source' => 'manual']], 'template' => 'Staff', 'send_as' => ['sales@example.org']];

        $result = $this->get('get/mailbox-group/member/alice@example.org', ['script' => [self::answer(self::API . 'getMemberships', [], $memberships)]]);

        $this->assertCalled($result, self::API . 'getMemberships', ['alice@example.org']);
        $this->assertSame($memberships, $result->json());
        $this->assertNotCalled($result, self::API . 'getGroup');
    }

    public function testGetMembershipsWithoutMailbox(): void
    {
        $result = $this->get('get/mailbox-group/member');

        $this->assertCalled($result, self::API . 'getMemberships', ['']);
        $this->assertSame('{}', $result->body);
    }

    public function testGetOne(): void
    {
        $group = ['id' => 7, 'domain' => 'example.org', 'name' => 'Sales'];

        $result = $this->get('get/mailbox-group/7', ['script' => [self::answer(self::API . 'getGroup', ['7'], $group)]]);

        $this->assertCalled($result, self::API . 'getGroup', ['7']);
        $this->assertSame($group, $result->json());
    }

    public function testEdit(): void
    {
        $attr = ['add_members' => ['carol@example.org'], 'remove_members' => 'bob@example.org'];

        $result = $this->post('edit/mailbox-group', ['items' => [7, '8'], 'attr' => $attr]);

        $this->assertCalled($result, self::API . 'editGroups', [[7, '8'], $attr]);
    }

    public function testEditWithASingleIdOrWithoutItems(): void
    {
        $single = $this->post('edit/mailbox-group', ['items' => 7, 'attr' => ['active' => 0]]);
        $this->assertCalled($single, self::API . 'editGroups', [[7], ['active' => 0]]);

        $none = $this->post('edit/mailbox-group', ['attr' => ['id' => [9], 'active' => 0]]);
        $this->assertCalled($none, self::API . 'editGroups', [[], ['id' => [9], 'active' => 0]]);
    }

    public function testDelete(): void
    {
        $messages = [
            self::message('success', ['group_removed', 'Sales'], ['mailbox_group', 'delete', 'group', ['id' => [7, 'x']]]),
            self::message('danger', 'access_denied', ['mailbox_group', 'delete', 'group', ['id' => [7, 'x']]]),
        ];

        $result = $this->post('delete/mailbox-group', [7, 'x'], ['script' => [self::answer(self::API . 'deleteGroups', [], true, $messages)]]);

        $this->assertCalled($result, self::API . 'deleteGroups', [[7, 'x']]);
        $this->assertSame($messages, $result->json());
    }
}
