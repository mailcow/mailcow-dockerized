<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiResult;
use Mailcow\Tests\Api\ApiTestCase;

/** search/domain: DataTables server-side processing (SSP) of the domain table */
class DomainSearchTest extends ApiTestCase
{
    private const REQUEST = [
        'draw' => 3,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => 'example'],
        'order' => [['column' => 2, 'dir' => 'asc']],
        'columns' => [
            ['data' => '', 'searchable' => false, 'orderable' => false],
            ['data' => 'chkbox', 'searchable' => false, 'orderable' => false],
            ['data' => 'domain_h_name', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '']],
        ],
    ];

    private const PAGE = ['draw' => 3, 'recordsTotal' => 12, 'recordsFiltered' => 2, 'data' => [[2 => 'example.org'], [2 => 'example.com']]];

    public function testAdminsSearchAllDomains(): void
    {
        $result = $this->post('search/domain', self::REQUEST, ['script' => [self::answer('SSP::simple', [], self::PAGE)]]);

        $call = $this->sspCall($result, 'SSP::simple');
        $this->assertSame([self::REQUEST, '@pdo', ['domain', 'd'], 'domain'], array_slice($call, 0, 4));
        $this->assertSame([
            2 => 'domain', 3 => 'aliases', 4 => 'mailboxes', 5 => 'quota', 6 => 'stats', 7 => 'defquota', 8 => 'maxquota',
            10 => 'backupmx', 14 => 'tags', 15 => 'active',
        ], array_column($call[4], 'db', 'dt'));
        $this->assertNotCalled($result, 'SSP::complex');
    }

    public function testRowsAreReplacedByTheDomainDetails(): void
    {
        $result = $this->post('search/domain', self::REQUEST, ['script' => [
            self::answer('SSP::simple', [], self::PAGE),
            self::answer('mailbox', ['get', 'domain_details', 'example.org'], ['domain_name' => 'example.org']),
            self::answer('mailbox', ['get', 'domain_details', 'example.com'], false),
        ]]);

        $this->assertSame([['get', 'domain_details', 'example.org'], ['get', 'domain_details', 'example.com']], $result->callsTo('mailbox'));
        $this->assertSame(['draw' => 3, 'recordsTotal' => 12, 'recordsFiltered' => 2, 'data' => [['domain_name' => 'example.org']]], $result->json());
    }

    public function testAnEmptyPageIsAnsweredAsItIs(): void
    {
        $page = ['draw' => 1, 'recordsTotal' => 12, 'recordsFiltered' => 0, 'data' => []];

        $result = $this->post('search/domain', ['draw' => 1], ['script' => [self::answer('SSP::simple', [], $page)]]);

        $this->assertSame($page, $result->json());
        $this->assertNotCalled($result, 'mailbox');
    }

    public function testErrorsOfTheSearchArePassedOn(): void
    {
        $result = $this->post('search/domain', self::REQUEST, ['script' => [self::answer('SSP::simple', [], ['error' => 'An SQL error occurred: unknown column'])]]);

        $this->assertSame(['error' => 'An SQL error occurred: unknown column'], $result->json());
    }

    public function testReadOnlyKeysCanSearch(): void
    {
        $result = $this->post('search/domain', self::REQUEST, ['session' => self::API_RO, 'script' => [self::answer('SSP::simple', [], self::PAGE)]]);

        $this->assertSame(200, $result->status);
        $this->assertSame(self::REQUEST, $this->sspCall($result, 'SSP::simple')[0]);
    }

    public function testDomainAdminsSearchTheirDomains(): void
    {
        $result = $this->post('search/domain', self::REQUEST, ['session' => self::DOMAIN_ADMIN, 'script' => [self::answer('SSP::complex', [], self::PAGE)]]);

        $call = $this->sspCall($result, 'SSP::complex');
        $this->assertSame([self::REQUEST, '@pdo', ['domain', 'd'], 'domain'], array_slice($call, 0, 4));
        $this->assertSame(['INNER JOIN domain_admins as da ON da.domain = d.domain', [
            'condition' => 'da.active = 1 and da.username = :username',
            'bindings' => ['username' => 'domainadmin'],
        ]], array_slice($call, 5));
        $this->assertNotCalled($result, 'SSP::simple');
    }

    public function testOtherLoginsAreRefused(): void
    {
        foreach ([self::USER, self::NO_SESSION] as $session) {
            $result = $this->post('search/domain', self::REQUEST, ['session' => $session]);

            $this->assertApiError($result, 403, 'Insufficient permissions');
            $this->assertNotCalled($result, 'SSP::simple');
            $this->assertNotCalled($result, 'SSP::complex');
        }
    }

    public function testWithoutJsonBodyTheSearchGetsNoParameters(): void
    {
        $result = $this->request('POST', 'search/domain', ['script' => [self::answer('SSP::simple', [], self::PAGE)]]);

        $this->assertNull($this->sspCall($result, 'SSP::simple')[0]);
    }

    private function sspCall(ApiResult $result, string $name): array
    {
        $calls = $result->callsTo($name);
        $this->assertCount(1, $calls, "one call of $name");
        return $calls[0];
    }
}
