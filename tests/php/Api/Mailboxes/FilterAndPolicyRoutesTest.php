<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiTestCase;

/** Sieve filters and the spam allow/deny lists of mailboxes */
class FilterAndPolicyRoutesTest extends ApiTestCase
{
    public function testAddEditDeleteFilters(): void
    {
        $body = ['username' => 'john@example.org', 'script_desc' => 'Move newsletters', 'script_data' => 'keep;', 'filter_type' => 'prefilter', 'active' => 1];
        $this->assertCalled($this->post('add/filter', $body), 'mailbox', ['add', 'filter', $body]);

        $this->assertCalled($this->post('edit/filter', ['items' => [7], 'attr' => ['active' => 0]]), 'mailbox', ['edit', 'filter', ['id' => [7], 'active' => 0]]);
        $this->assertCalled($this->post('delete/filter', [7]), 'mailbox', ['delete', 'filter', ['id' => [7]]]);
    }

    public function testAllFiltersOfAllMailboxes(): void
    {
        $result = $this->get('get/filters/all', ['script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.org'], ['john@example.org', 'jane@example.org']),
            self::answer('mailbox', ['get', 'filters', 'john@example.org'], [7, 8]),
            self::answer('mailbox', ['get', 'filter_details', 7], ['id' => 7]),
            self::answer('mailbox', ['get', 'filter_details', 8], false),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'filters', 'jane@example.org']);
        $this->assertSame([['id' => 7]], $result->json());
        $this->assertSame('{}', $this->get('get/filters/all')->body);
    }

    public function testFiltersOfAMailbox(): void
    {
        $result = $this->get('get/filters/john@example.org', ['script' => [
            self::answer('mailbox', ['get', 'filters'], [7]),
            self::answer('mailbox', ['get', 'filter_details', 7], ['id' => 7, 'username' => 'john@example.org']),
        ]]);

        $this->assertCalled($result, 'mailbox', ['get', 'filters', 'john@example.org']);
        $this->assertSame([['id' => 7, 'username' => 'john@example.org']], $result->json());
        $this->assertSame('{}', $this->get('get/filters/jane@example.org')->body);
    }

    public function testActiveUserSieveIsAListWithOneScript(): void
    {
        $result = $this->get('get/active-user-sieve/john@example.org', ['script' => [self::answer('mailbox', ['get', 'active_user_sieve'], "require \"fileinto\";\nkeep;")]]);

        $this->assertSame([['get', 'active_user_sieve', 'john@example.org']], $result->callsTo('mailbox'));
        $this->assertSame(["require \"fileinto\";\nkeep;"], $result->json());
        $this->assertSame('{}', $this->get('get/active-user-sieve/john@example.org', ['script' => [self::answer('mailbox', [], false)]])->body);

        $none = $this->get('get/active-user-sieve');
        $this->assertNotCalled($none, 'mailbox');
        $this->assertSame('{}', $none->body);
    }

    public function testAddAndDeleteMailboxPolicies(): void
    {
        $body = ['username' => 'john@example.org', 'object_list' => 'bl', 'object_from' => '*@spam.example.com'];
        $this->assertCalled($this->post('add/mailbox-policy', $body), 'policy', ['add', 'mailbox', $body]);

        $this->assertCalled($this->post('delete/mailbox-policy', [13]), 'policy', ['delete', 'mailbox', ['prefid' => [13]]]);
    }

    public function testAllowAndDenyListsArePartsOfThePolicy(): void
    {
        $policy = [
            'whitelist' => [['object' => 'john@example.org', 'value' => '*@partner.example.com', 'prefid' => 12]],
            'blacklist' => [['object' => 'example.org', 'value' => '*@spam.example.com', 'prefid' => 13]],
        ];
        $script = [self::answer('policy', ['get', 'mailbox'], $policy)];

        $allow = $this->get('get/policy_wl_mailbox/john@example.org', ['script' => $script]);
        $this->assertSame([['get', 'mailbox', 'john@example.org']], $allow->callsTo('policy'));
        $this->assertSame($policy['whitelist'], $allow->json());

        $this->assertSame($policy['blacklist'], $this->get('get/policy_bl_mailbox/john@example.org', ['script' => $script])->json());
        $this->assertSame('{}', $this->get('get/policy_bl_mailbox/nobody@example.org')->body);
    }
}
