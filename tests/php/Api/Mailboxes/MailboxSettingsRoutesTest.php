<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiTestCase;

/** Settings a mailbox user changes on its page: time-limited aliases, Pushover, tagged mail, TLS, quarantine, spam score */
class MailboxSettingsRoutesTest extends ApiTestCase
{
    public function testTimeLimitedAliases(): void
    {
        $body = ['username' => 'john@example.org', 'domain' => 'example.org', 'validity' => 720];
        $this->assertCalled($this->post('add/time_limited_alias', $body), 'mailbox', ['add', 'time_limited_alias', $body]);

        $edit = $this->post('edit/time_limited_alias', ['items' => ['kahd.osuwe@example.org'], 'attr' => ['validity' => 24]]);
        $this->assertCalled($edit, 'mailbox', ['edit', 'time_limited_alias', ['address' => ['kahd.osuwe@example.org'], 'validity' => 24]]);

        $delete = $this->post('delete/time_limited_alias', ['kahd.osuwe@example.org']);
        $this->assertCalled($delete, 'mailbox', ['delete', 'time_limited_alias', ['address' => ['kahd.osuwe@example.org']]]);
    }

    public function testListTimeLimitedAliases(): void
    {
        $aliases = [['address' => 'kahd.osuwe@example.org', 'goto' => 'john@example.org', 'permanent' => 0]];

        $result = $this->get('get/time_limited_aliases/john@example.org', ['script' => [self::answer('mailbox', ['get', 'time_limited_aliases'], $aliases)]]);

        $this->assertSame([['get', 'time_limited_aliases', 'john@example.org']], $result->callsTo('mailbox'));
        $this->assertSame($aliases, $result->json());
        $this->assertSame('{}', $this->get('get/time_limited_aliases/jane@example.org')->body);
    }

    public function testPushover(): void
    {
        $attr = ['key' => str_repeat('a', 30), 'token' => str_repeat('b', 30), 'active' => 1];
        $this->assertCalled($this->post('edit/pushover', ['items' => ['john@example.org'], 'attr' => $attr]), 'pushover', ['edit', ['username' => ['john@example.org']] + $attr]);

        $this->assertCalled($this->post('edit/pushover-test', ['items' => ['john@example.org'], 'attr' => []]), 'pushover', ['test', ['username' => ['john@example.org']]]);
    }

    public function testMailboxUserSettingsGetTheMailboxesAsUsername(): void
    {
        $routes = [
            'delimiter_action' => ['tagged_mail_handler' => 'subfolder'],
            'tls_policy' => ['tls_enforce_in' => 1],
            'quarantine_notification' => ['quarantine_notification' => 'daily'],
            'quarantine_category' => ['quarantine_category' => 'all'],
            'spam_score' => ['spam_score' => '6,12'],
        ];
        foreach ($routes as $type => $attr) {
            $route = 'edit/' . ($type === 'spam_score' ? 'spam-score' : $type);
            $result = $this->post($route, ['items' => 'john@example.org', 'attr' => $attr]);
            $this->assertCalled($result, 'mailbox', ['edit', $type, ['username' => ['john@example.org']] + $attr]);
        }
    }

    public function testTheUiSendsItsOwnAddressAsItem(): void
    {
        $result = $this->postForm('edit/delimiter_action', ['items' => '["user@example.org"]', 'attr' => '{"tagged_mail_handler":"subject","csrf_token":"t"}'], ['session' => self::USER]);

        $this->assertCalled($result, 'mailbox', ['edit', 'delimiter_action', ['username' => ['user@example.org'], 'tagged_mail_handler' => 'subject']]);
    }

    public function testSpamScoreWithoutSpaces(): void
    {
        $mailbox = $this->get('get/spam-score/john@example.org', ['script' => [self::answer('mailbox', ['get', 'spam_score'], '6, 12')]]);
        $this->assertSame([['get', 'spam_score', 'john@example.org']], $mailbox->callsTo('mailbox'));
        $this->assertSame(['score' => '6,12'], $mailbox->json());

        $default = $this->get('get/spam-score', ['script' => [self::answer('mailbox', ['get', 'spam_score'], '8, 15')]]);
        $this->assertSame([['get', 'spam_score', null]], $default->callsTo('mailbox'));
        $this->assertSame(['score' => '8,15'], $default->json());

        $this->assertSame('{}', $this->get('get/spam-score/jane@example.org', ['script' => [self::answer('mailbox', [], false)]])->body);
    }
}
