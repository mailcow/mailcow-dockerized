<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Modules;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes scim-token: json_api.php hands them to the Scim module and passes its answers through */
class ScimTokenRoutesTest extends ApiTestCase
{
    private const API = 'Mailcow\Scim\ScimModule->api->';
    private const TOKEN = ['id' => 3, 'description' => 'Entra ID', 'domain_restriction' => 'example.org', 'group_domain' => null,
        'allow_from' => [], 'default_template' => 'Staff', 'mappers' => [], 'templates' => [], 'allow_rename' => 0, 'rename_alias' => 1,
        'active' => 1, 'created' => '2026-11-12 12:00:00', 'modified' => null];

    public function testGetAll(): void
    {
        $result = $this->get('get/scim-token/all', ['script' => [self::answer(self::API . 'getTokens', [], [self::TOKEN])]]);

        $this->assertSame([self::TOKEN], $result->json());
    }

    public function testGetAllAnswersAnEmptyObjectWithoutTokens(): void
    {
        $result = $this->get('get/scim-token/all', ['script' => [self::answer(self::API . 'getTokens', [], [])]]);

        $this->assertSame('{}', $result->body);
    }

    public function testGetOne(): void
    {
        $result = $this->get('get/scim-token/3', ['script' => [self::answer(self::API . 'getToken', ['3'], self::TOKEN)]]);

        $this->assertSame(self::TOKEN, $result->json());
    }

    public function testAdd(): void
    {
        $attr = ['description' => 'Entra ID', 'domain_restriction' => 'example.org', 'active' => 1];
        // the token value is only in this answer, in the scim_token key of the success message
        $messages = [self::message('success', ['scim_token_added', 'Entra ID'], ['scim', 'add', 'token', $attr]) + ['scim_token' => 'c2NpbS10b2tlbg']];

        $result = $this->post('add/scim-token', $attr, ['script' => [self::answer(self::API . 'addToken', [], true, $messages)]]);

        $this->assertSame([[$attr]], $result->callsTo(self::API . 'addToken'));
        $this->assertSame($messages, $result->json());
    }

    public function testEdit(): void
    {
        $result = $this->post('edit/scim-token', ['items' => [3, 4], 'attr' => ['active' => 0]]);

        $this->assertSame([[[3, 4], ['active' => 0]]], $result->callsTo(self::API . 'editTokens'));
    }

    public function testDelete(): void
    {
        $result = $this->post('delete/scim-token', [3]);

        $this->assertSame([[[3]]], $result->callsTo(self::API . 'deleteTokens'));
    }
}
