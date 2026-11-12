<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiTestCase;

/** Mailboxes, their templates, tags, custom attributes, renaming, ACLs, logins and resets */
class MailboxRoutesTest extends ApiTestCase
{
    public function testAddMailbox(): void
    {
        $body = ['local_part' => 'john.doe', 'domain' => 'example.org', 'password' => 'Moo2Shoh5aiXu', 'password2' => 'Moo2Shoh5aiXu', 'tags' => ['sales']];

        $this->assertCalled($this->post('add/mailbox', $body), 'mailbox', ['add', 'mailbox', $body]);
        // any other object is a mailbox as well
        $this->assertCalled($this->post('add/mailbox/something', $body), 'mailbox', ['add', 'mailbox', $body]);
    }

    public function testAddMailboxTemplate(): void
    {
        $body = ['template' => 'Sales', 'quota' => 2048, 'acl' => ['spam_alias']];

        $this->assertCalled($this->post('add/mailbox/template', $body), 'mailbox', ['add', 'mailbox_templates', $body]);
    }

    public function testGetAllMailboxesWalksAllDomains(): void
    {
        $result = $this->get('get/mailbox/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org', 'example.com']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.org'], ['john@example.org', 'gone@example.org']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.com'], ['jane@example.com']),
            self::answer('mailbox', ['get', 'mailbox_details', 'john@example.org'], ['username' => 'john@example.org']),
            self::answer('mailbox', ['get', 'mailbox_details', 'gone@example.org'], false),
            self::answer('mailbox', ['get', 'mailbox_details', 'jane@example.com'], ['username' => 'jane@example.com']),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'mailboxes', 'example.org', null]);
        $this->assertCalled($result, 'mailbox', ['get', 'mailbox_details', 'john@example.org', 'all']);
        $this->assertSame([['username' => 'john@example.org'], ['username' => 'jane@example.com']], $result->json());
    }

    public function testReducedDetailsOfGivenDomainsWithTags(): void
    {
        $result = $this->get('get/mailbox/reduced/example.org,example.com', [
            'query' => ['tags' => 'sales,support'],
            'script' => [
                self::answer('mailbox', ['get', 'mailboxes', 'example.org'], ['john@example.org']),
                self::answer('mailbox', ['get', 'mailbox_details'], ['username' => 'john@example.org']),
            ],
        ]);

        $this->assertNotContains(['get', 'domains'], $result->callsTo('mailbox'));
        $this->assertCalled($result, 'mailbox', ['get', 'mailboxes', 'example.org', ['sales', 'support']]);
        $this->assertCalled($result, 'mailbox', ['get', 'mailboxes', 'example.com', ['sales', 'support']]);
        $this->assertCalled($result, 'mailbox', ['get', 'mailbox_details', 'john@example.org', 'reduced']);
        $this->assertSame([['username' => 'john@example.org']], $result->json());
    }

    public function testMailboxListsWithoutResults(): void
    {
        $this->assertSame('{}', $this->get('get/mailbox/all')->body);
        $this->assertSame('{}', $this->get('get/mailbox/all/example.org')->body);
    }

    public function testGetOneMailbox(): void
    {
        $found = $this->get('get/mailbox/john@example.org', ['script' => [
            self::answer('mailbox', ['get', 'mailbox_details', 'john@example.org'], ['username' => 'john@example.org', 'quota' => 0]),
        ]]);

        $this->assertCalled($found, 'mailbox', ['get', 'mailbox_details', 'john@example.org']);
        $this->assertSame(['username' => 'john@example.org', 'quota' => 0], $found->json());
        $this->assertSame('{}', $this->get('get/mailbox/nobody@example.org')->body);
    }

    public function testGetMailboxWithTagsSearchesByAddressPart(): void
    {
        $result = $this->get('get/mailbox/example.org', [
            'query' => ['tags' => 'sales'],
            'script' => [
                self::answer('mailbox', ['get', 'mailboxes', 'example.org', ['sales']], ['john@example.org']),
                self::answer('mailbox', ['get', 'mailbox_details', 'john@example.org'], ['username' => 'john@example.org']),
            ],
        ]);

        $this->assertCalled($result, 'mailbox', ['get', 'mailbox_details', 'john@example.org']);
        $this->assertSame([['username' => 'john@example.org']], $result->json());
        $this->assertSame('[]', $this->get('get/mailbox/example.org', ['query' => ['tags' => 'sales']])->body);
    }

    public function testGetMailboxTemplates(): void
    {
        $templates = [['id' => 2, 'template' => 'Default', 'type' => 'mailbox', 'attributes' => ['quota' => 0]]];
        $all = $this->get('get/mailbox/template/all', ['script' => [self::answer('mailbox', ['get', 'mailbox_templates'], $templates)]]);

        $this->assertSame([['get', 'mailbox_templates']], $all->callsTo('mailbox'));
        $this->assertSame($templates, $all->json());
        $this->assertCalled($this->get('get/mailbox/template/2'), 'mailbox', ['get', 'mailbox_templates', '2']);
    }

    public function testEditMailbox(): void
    {
        $result = $this->post('edit/mailbox', ['items' => 'john@example.org', 'attr' => ['quota' => 5120, 'sender_acl' => ['@example.com']]]);

        $this->assertCalled($result, 'mailbox', ['edit', 'mailbox', ['username' => ['john@example.org'], 'quota' => 5120, 'sender_acl' => ['@example.com']]]);
    }

    public function testEditMailboxTemplatesAndCustomAttributes(): void
    {
        $template = $this->post('edit/mailbox/template', ['items' => [3], 'attr' => ['template' => 'Sales', 'quarantine_category' => 'reject']]);
        $this->assertCalled($template, 'mailbox', ['edit', 'mailbox_templates', ['ids' => [3], 'template' => 'Sales', 'quarantine_category' => 'reject']]);

        $custom = $this->post('edit/mailbox/custom-attribute', ['items' => ['john@example.org'], 'attr' => ['attribute' => ['department'], 'value' => ['Sales']]]);
        $this->assertCalled($custom, 'mailbox', ['edit', 'mailbox_custom_attribute', ['mailboxes' => ['john@example.org'], 'attribute' => ['department'], 'value' => ['Sales']]]);
    }

    public function testRenameMailboxTakesTheNamesFromAttr(): void
    {
        $attr = ['domain' => 'example.org', 'old_local_part' => 'john.doe', 'new_local_part' => 'john', 'create_alias' => 1];

        $result = $this->post('edit/rename-mbox', ['items' => [], 'attr' => $attr]);

        $this->assertCalled($result, 'mailbox', ['edit', 'mailbox_rename', ['mailbox' => []] + $attr]);
    }

    public function testEditUserAcl(): void
    {
        $result = $this->post('edit/user-acl', ['items' => ['john@example.org'], 'attr' => ['user_acl' => ['spam_alias', 'app_passwds']]]);

        $this->assertCalled($result, 'acl', ['edit', 'user', ['username' => ['john@example.org'], 'user_acl' => ['spam_alias', 'app_passwds']]]);
    }

    public function testDeleteMailboxesTagsAndTemplates(): void
    {
        $this->assertCalled($this->post('delete/mailbox', ['john@example.org']), 'mailbox', ['delete', 'mailbox', ['username' => ['john@example.org']]]);

        $tags = $this->post('delete/mailbox/tag/john@example.org', ['sales', 'trainee']);
        $this->assertCalled($tags, 'mailbox', ['delete', 'tags_mailbox', ['tags' => ['sales', 'trainee'], 'username' => 'john@example.org']]);

        $this->assertCalled($this->post('delete/mailbox/template', [3]), 'mailbox', ['delete', 'mailbox_templates', ['ids' => [3]]]);
    }

    public function testResetEasCacheAndSogoProfile(): void
    {
        $this->assertCalled($this->post('delete/eas_cache', ['john@example.org']), 'mailbox', ['delete', 'eas_cache', ['username' => ['john@example.org']]]);
        $this->assertCalled($this->post('delete/sogo_profile', ['john@example.org']), 'mailbox', ['delete', 'sogo_profile', ['username' => ['john@example.org']]]);
    }

    public function testLastLogin(): void
    {
        $logins = ['ui' => false, 'sasl' => [['real_rip' => '198.51.100.23', 'service' => 'imap']]];
        $default = $this->get('get/last-login/john@example.org', ['script' => [self::answer('last_login', [], $logins)]]);
        $this->assertSame([['get', 'john@example.org']], $default->callsTo('last_login'));
        $this->assertSame($logins, $default->json());

        $this->assertSame([['get', 'john@example.org', 30]], $this->get('get/last-login/john@example.org/30')->callsTo('last_login'));
        $this->assertSame([['get', 'john@example.org']], $this->get('get/last-login/john@example.org/0')->callsTo('last_login'));

        $none = $this->get('get/last-login');
        $this->assertSame('', $none->body);
        $this->assertNotCalled($none, 'last_login');
    }

    public function testResetLastLoginIsAReadRoute(): void
    {
        $result = $this->get('get/reset-last-login/john@example.org', ['session' => self::API_RO, 'script' => [self::answer('last_login', ['reset'], true)]]);

        $this->assertSame([['reset', 'john@example.org']], $result->callsTo('last_login'));
        $this->assertSame('true', $result->body);
    }

    public function testBccDestinationOptions(): void
    {
        $result = $this->get('get/bcc-destination-options', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org']),
            self::answer('mailbox', ['get', 'alias_domains'], ['example.net']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.org'], ['john@example.org']),
            self::answer('user_get_alias_details', ['john@example.org'], [
                'direct_aliases' => ['sales@example.org' => ['public_comment' => ''], 'john@other.example' => ['public_comment' => '']],
                'shared_aliases' => ['team@example.org' => ['public_comment' => '']],
            ]),
            self::answer('hasDomainAccess', ['API', 'admin', 'example.org'], true),
        ]]);

        $this->assertSame([
            'domains' => ['example.org'],
            'mailboxes' => ['john@example.org' => ['john@example.org', 'sales@example.org', 'team@example.org']],
            'alias_domains' => ['example.net'],
        ], $result->json());
    }
}
