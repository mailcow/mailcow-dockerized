<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes api-key: the ApiKey module */
class ApiKeyRoutesTest extends ApiTestCase
{
    private const API = 'Mailcow\ApiKey\ApiKeyModule->api->';
    private const KEY = ['id' => 3, 'description' => 'provisioning', 'access' => 'rw', 'api_key' => '1B7E2C-94F0A3-6D5B18-C2E7F4-03A9D6',
        'allow_from' => ['192.0.2.10'], 'skip_ip_check' => 0, 'service' => 0, 'active' => 1, 'created' => '2026-11-02 09:12:44', 'modified' => null];

    public function testGetAll(): void
    {
        $result = $this->get('get/api-key/all', ['script' => [self::answer(self::API . 'getKeys', [], [self::KEY])]]);

        $this->assertSame([self::KEY], $result->json());
        $this->assertSame([[]], $result->callsTo(self::API . 'getKeys'));
    }

    public function testGetAllAnswersAnEmptyObjectWithoutKeys(): void
    {
        // also the answer for read-only API keys: the module returns no keys for them
        $result = $this->get('get/api-key/all', ['session' => self::API_RO, 'script' => [self::answer(self::API . 'getKeys', [], [])]]);

        $this->assertSame('{}', $result->body);
    }

    public function testGetOne(): void
    {
        $found = $this->get('get/api-key/3', ['script' => [self::answer(self::API . 'getKey', ['3'], self::KEY)]]);
        $this->assertSame(self::KEY, $found->json());

        $missing = $this->get('get/api-key/99', ['script' => [self::answer(self::API . 'getKey', [], [])]]);
        $this->assertSame('{}', $missing->body);
        $this->assertSame([['99']], $missing->callsTo(self::API . 'getKey'));
    }

    public function testAdd(): void
    {
        $attr = ['description' => 'provisioning', 'access' => 'rw', 'allow_from' => '192.0.2.10, 2001:db8::/64', 'skip_ip_check' => 0, 'active' => 1];
        $messages = [self::message('success', ['api_key_added', 'provisioning'], ['api', 'add', 'key', $attr])];

        $result = $this->post('add/api-key', $attr, ['script' => [self::answer(self::API . 'addKey', [], true, $messages)]]);

        $this->assertSame([[$attr]], $result->callsTo(self::API . 'addKey'));
        $this->assertSame($messages, $result->json());
    }

    public function testEdit(): void
    {
        $result = $this->post('edit/api-key', ['items' => [3, 4], 'attr' => ['regen_key' => 1]]);

        $this->assertSame([[[3, 4], ['regen_key' => 1]]], $result->callsTo(self::API . 'editKeys'));
    }

    public function testEditTakesASingleId(): void
    {
        $result = $this->post('edit/api-key', ['items' => 3, 'attr' => ['active' => 0]]);

        $this->assertSame([[[3], ['active' => 0]]], $result->callsTo(self::API . 'editKeys'));
    }

    public function testDelete(): void
    {
        $messages = [self::message('danger', ['api_key_service_locked', 'API_KEY'])];

        $result = $this->post('delete/api-key', [1], ['script' => [self::answer(self::API . 'deleteKeys', [], true, $messages)]]);

        $this->assertSame([[[1]]], $result->callsTo(self::API . 'deleteKeys'));
        $this->assertSame($messages, $result->json());
    }
}
