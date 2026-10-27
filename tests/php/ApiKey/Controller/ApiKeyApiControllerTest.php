<?php

declare(strict_types=1);

namespace Mailcow\Tests\ApiKey\Controller;

use Mailcow\ApiKey\Controller\ApiKeyApiController;
use Mailcow\ApiKey\Model\ApiKey;
use Mailcow\ApiKey\Service\ApiKeyService;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiKeyApiControllerTest extends TestCase
{
    private ApiKeyService&MockObject $keys;
    private ApiKeyApiController $api;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->keys = $this->createMock(ApiKeyService::class);
        $this->api = new ApiKeyApiController($this->keys, new ApiResponse(['api_key']));
    }

    private static function key(int $id, string $description = ''): ApiKey
    {
        return ApiKey::fromRow(['id' => $id, 'api_key' => 'KEY-' . $id, 'description' => $description, 'access' => 'rw',
            'allow_from' => '192.0.2.1']);
    }

    /** Messages written to the session as [type, msg] */
    private function messages(): array
    {
        return array_map(fn(array $m): array => [$m['type'], $m['msg']], $_SESSION['return'] ?? []);
    }

    public function testAddLogsWithoutKeyAndNamesTheKey(): void
    {
        $this->keys->method('create')->willReturn(self::key(4, '<crm>'));

        $this->assertTrue($this->api->addKey(['access' => 'rw', 'api_key' => 'chosen']));

        $this->assertSame([[
            'type' => 'success',
            'log' => ['api', 'add', 'key', ['access' => 'rw', 'api_key' => '*']],
            'msg' => ['api_key_added', '&lt;crm&gt;'],
        ]], $_SESSION['return']);
    }

    public function testErrorsBecomeDangerMessages(): void
    {
        $this->keys->method('create')->willReturnOnConsecutiveCalls(
            $this->throwException(new UserError('ip_list_empty')),
            $this->throwException(new PDOException('gone away'))
        );

        $this->assertFalse($this->api->addKey([]));
        $this->assertFalse($this->api->addKey([]));

        $this->assertSame([['danger', 'ip_list_empty'], ['danger', ['mysql_error', 'gone away']]], $this->messages());
    }

    public function testEditReportsEachIdAndSkipsInvalidIds(): void
    {
        $this->keys->method('update')->willReturnCallback(fn(int $id, array $attr): ApiKey => ($id === 2)
            ? throw new UserError('api_key_service_locked', ['API_KEY']) : self::key($id));

        $this->assertTrue($this->api->editKeys(['1', '2', 'x'], ['active' => '0']));

        $this->assertSame([
            ['success', ['api_key_modified', '1']],
            ['danger', ['api_key_service_locked', 'API_KEY']],
            ['danger', 'access_denied'],
        ], $this->messages());
    }

    public function testEditAndDeleteNeedAdminOnce(): void
    {
        $this->keys->method('requireAdmin')->willThrowException(new AccessDenied());
        $this->keys->expects($this->never())->method('update');
        $this->keys->expects($this->never())->method('delete');

        $this->assertFalse($this->api->editKeys(['1', '2'], []));
        $this->assertFalse($this->api->deleteKeys(['1', '2']));

        $this->assertSame([['danger', 'access_denied'], ['danger', 'access_denied']], $this->messages());
    }

    public function testDelete(): void
    {
        $this->keys->expects($this->once())->method('delete')->with(5)->willReturn(self::key(5, 'old'));

        $this->assertTrue($this->api->deleteKeys(['5']));

        $this->assertSame([['success', ['api_key_deleted', 'old']]], $this->messages());
    }

    public function testGetReturnsApiShape(): void
    {
        $this->keys->method('all')->willReturn([self::key(1, 'crm')]);
        $this->keys->method('get')->willReturnMap([[1, self::key(1, 'crm')], [2, null]]);

        $this->assertSame([[
            'id' => 1, 'description' => 'crm', 'access' => 'rw', 'api_key' => 'KEY-1', 'allow_from' => ['192.0.2.1'],
            'skip_ip_check' => 0, 'service' => 0, 'active' => 1, 'created' => null, 'modified' => null,
        ]], $this->api->getKeys());
        $this->assertSame('KEY-1', $this->api->getKey('1')['api_key']);
        $this->assertSame([], $this->api->getKey('2'));
        $this->assertSame([], $this->api->getKey('1.5'));
        $this->assertSame([], $this->api->getKey('all'));
    }
}
