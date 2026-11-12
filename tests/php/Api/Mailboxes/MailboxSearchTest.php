<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Mailboxes;

use Mailcow\Tests\Api\ApiResult;
use Mailcow\Tests\Api\ApiTestCase;

/** search/mailbox: the DataTables server-side search of the mailbox table */
class MailboxSearchTest extends ApiTestCase
{
    private const REQUEST = [
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'search' => ['value' => 'john'],
        'order' => [['column' => 2, 'dir' => 'asc']],
        'columns' => [['data' => 'username', 'searchable' => true, 'orderable' => true]],
    ];

    private const KIND_FILTER = "(`m`.`kind` = '' OR `m`.`kind` = NULL)";

    public function testAdminsSearchAllMailboxes(): void
    {
        $call = $this->searchCall($this->post('search/mailbox', self::REQUEST));

        $this->assertSame([self::REQUEST, '@pdo', ['mailbox', 'm'], 'username'], array_slice($call, 0, 4));
        $this->assertSame([null, self::KIND_FILTER], array_slice($call, 5));
        $this->assertSame([2 => 'username', 3 => 'quota', 4 => 'last_mail_login', 5 => 'last_pw_change', 6 => 'in_use', 7 => 'name',
            20 => 'messages', 23 => 'tags', 24 => 'active'], array_column($call[4], 'db', 'dt'));
    }

    public function testReadOnlyKeysMaySearch(): void
    {
        $this->searchCall($this->post('search/mailbox', self::REQUEST, ['session' => self::API_RO]));
    }

    public function testDomainAdminsSearchTheirDomains(): void
    {
        $call = $this->searchCall($this->post('search/mailbox', self::REQUEST, ['session' => self::DOMAIN_ADMIN]));

        $this->assertSame('INNER JOIN domain_admins as da ON da.domain = m.domain', $call[5]);
        $this->assertSame([
            'condition' => self::KIND_FILTER . ' AND `da`.`active` = 1 AND `da`.`username` = :username',
            'bindings' => ['username' => 'domainadmin'],
        ], $call[6]);
    }

    public function testOthersAreRefused(): void
    {
        foreach ([self::USER, self::NO_SESSION] as $session) {
            $result = $this->post('search/mailbox', self::REQUEST, ['session' => $session]);
            $this->assertApiError($result, 403, 'Insufficient permissions');
            $this->assertNotCalled($result, 'SSP::complex');
        }
    }

    public function testRowsBecomeMailboxDetails(): void
    {
        $page = ['draw' => 1, 'recordsTotal' => 2, 'recordsFiltered' => 2, 'data' => [
            [2 => 'john@example.org', 3 => 0, 4 => '', 7 => 'John'],
            [2 => 'gone@example.org', 3 => 0, 4 => '', 7 => 'Gone'],
        ]];
        $result = $this->post('search/mailbox', self::REQUEST, ['script' => [
            self::answer('SSP::complex', [], $page),
            self::answer('mailbox', ['get', 'mailbox_details', 'john@example.org'], ['username' => 'john@example.org']),
        ]]);

        $this->assertSame([['get', 'mailbox_details', 'john@example.org'], ['get', 'mailbox_details', 'gone@example.org']], $result->callsTo('mailbox'));
        $this->assertSame(['draw' => 1, 'recordsTotal' => 2, 'recordsFiltered' => 2, 'data' => [['username' => 'john@example.org']]], $result->json());
    }

    public function testEmptyPageIsPassedOn(): void
    {
        $page = ['draw' => 4, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []];

        $result = $this->post('search/mailbox', self::REQUEST, ['script' => [self::answer('SSP::complex', [], $page)]]);

        $this->assertNotCalled($result, 'mailbox');
        $this->assertSame($page, $result->json());
    }

    private function searchCall(ApiResult $result): array
    {
        $calls = $result->callsTo('SSP::complex');
        $this->assertCount(1, $calls, $result->body);
        $this->assertNotCalled($result, 'SSP::simple');
        return $calls[0];
    }
}
