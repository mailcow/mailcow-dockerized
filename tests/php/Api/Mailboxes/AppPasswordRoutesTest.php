<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiTestCase;

/** App passwords of mailboxes */
class AppPasswordRoutesTest extends ApiTestCase
{
    public function testAddAppPassword(): void
    {
        $body = ['username' => 'john@example.org', 'app_name' => 'Thunderbird', 'app_passwd' => 'eiQu4ahz8iePh', 'app_passwd2' => 'eiQu4ahz8iePh', 'protocols' => ['imap_access']];

        $this->assertCalled($this->post('add/app-passwd', $body), 'app_passwd', ['add', $body]);
    }

    public function testListsOfAMailboxAndOfTheSessionUser(): void
    {
        $script = [
            self::answer('app_passwd', ['get'], [['id' => 23, 'name' => 'Thunderbird'], ['id' => 24, 'name' => 'Gone']]),
            self::answer('app_passwd', ['details', 23], ['id' => 23, 'mailbox' => 'john@example.org']),
            self::answer('app_passwd', ['details', 24], false),
        ];

        $mailbox = $this->get('get/app-passwd/all/john@example.org', ['script' => $script]);
        $this->assertSame([['get', ['username' => 'john@example.org']], ['details', 23], ['details', 24]], $mailbox->callsTo('app_passwd'));
        $this->assertSame([['id' => 23, 'mailbox' => 'john@example.org']], $mailbox->json());

        $own = $this->get('get/app-passwd/all', ['session' => self::USER, 'script' => $script]);
        $this->assertSame(['get'], $own->callsTo('app_passwd')[0]);

        $this->assertSame('{}', $this->get('get/app-passwd/all')->body);
    }

    public function testSingleAppPasswordFails(): void
    {
        $result = $this->get('get/app-passwd/23', ['allow_error' => true]);

        $this->assertStringContainsString('Cannot access offset of type string on string', (string)$result->error);
        $this->assertNotCalled($result, 'app_passwd');
    }

    public function testEditAndDeleteAppPasswords(): void
    {
        $edit = $this->post('edit/app-passwd', ['items' => [23], 'attr' => ['username' => 'john@example.org', 'active' => '0']]);
        $this->assertCalled($edit, 'app_passwd', ['edit', ['id' => [23], 'username' => 'john@example.org', 'active' => '0']]);

        $this->assertCalled($this->post('delete/app-passwd', [23, 24]), 'app_passwd', ['delete', ['id' => [23, 24]]]);
    }
}
