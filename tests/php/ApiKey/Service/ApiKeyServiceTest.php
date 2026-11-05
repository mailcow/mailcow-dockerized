<?php

declare(strict_types=1);

namespace Mailcow\Tests\ApiKey\Service;

use Mailcow\ApiKey\Model\ApiKey;
use Mailcow\ApiKey\Repository\ApiKeyRepository;
use Mailcow\ApiKey\Service\ApiKeyService;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiKeyServiceTest extends TestCase
{
    private const KEY_FORMAT = '/^([0-9A-F]{6}-){4}[0-9A-F]{6}$/';

    private ApiKeyRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(ApiKeyRepository::class);
    }

    private function service(?Context $context = null): ApiKeyService
    {
        return new ApiKeyService($this->repo, $context ?? FakeContext::admin());
    }

    private static function apiContext(string $access): Context
    {
        return new Context('admin', 'API', [], false, $access);
    }

    private static function stored(array $row = []): ApiKey
    {
        return ApiKey::fromRow($row + ['id' => 3, 'api_key' => 'AAAAAA-BBBBBB-CCCCCC-DDDDDD-EEEEEE', 'description' => 'crm',
            'access' => 'ro', 'allow_from' => '192.0.2.1,2001:db8::/64', 'skip_ip_check' => 0, 'service' => 0, 'active' => 1]);
    }

    private function expectUserError(string|array $msg, callable $call): void
    {
        try {
            $call();
            $this->fail('UserError ' . json_encode($msg) . ' expected');
        } catch (UserError $e) {
            $this->assertSame($msg, $e->msg());
        }
    }

    public static function deniedCallers(): array
    {
        return [
            'domain admin' => [FakeContext::domainAdmin()],
            'user' => [FakeContext::user()],
            'system' => [Context::system()],
            'read-only API key' => [self::apiContext('ro')],
        ];
    }

    #[DataProvider('deniedCallers')]
    public function testOnlyAdminsMayReadOrWrite(Context $context): void
    {
        $this->repo->expects($this->never())->method('all');
        $this->repo->expects($this->never())->method('insert');
        $service = $this->service($context);

        $this->assertSame([], $service->all());
        $this->assertNull($service->get(3));
        $this->expectUserError('access_denied', fn() => $service->create(['access' => 'ro', 'skip_ip_check' => '1']));
        $this->expectUserError('access_denied', fn() => $service->delete(3));
    }

    public function testReadWriteApiKeyMayManageKeys(): void
    {
        $this->repo->method('all')->willReturn([self::stored()]);

        $this->assertCount(1, $this->service(self::apiContext('rw'))->all());
    }

    public function testCreateGeneratesKeyAndNormalisesNetworks(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(function (ApiKey $key): bool {
            $this->assertMatchesRegularExpression(self::KEY_FORMAT, $key->key);
            $this->assertSame(['192.0.2.1', '2001:db8::/64', '10.0.0.0/8'], $key->allowFrom);
            $this->assertSame('rw', $key->access);
            $this->assertSame('crm', $key->description);
            $this->assertSame(0, $key->service);
            // Hidden "0" + checked checkbox
            $this->assertSame(1, $key->active);
            $this->assertSame(0, $key->skipIpCheck);
            return true;
        }))->willReturn(7);

        $this->service()->create(['description' => ' crm ', 'access' => 'rw', 'active' => ['0', '1'], 'skip_ip_check' => '0',
            'allow_from' => "192.0.2.1\n2001:db8::/64, 10.0.0.0/8;192.0.2.1"]);
    }

    public function testCreateAcceptsNetworkArray(): void
    {
        $this->repo->expects($this->once())->method('insert')
            ->with($this->callback(fn(ApiKey $key): bool => $key->allowFrom === ['192.0.2.1', '::1']));

        $this->service()->create(['access' => 'ro', 'allow_from' => ['192.0.2.1', ' ::1 ']]);
    }

    public static function invalidInput(): array
    {
        return [
            'access missing' => [['allow_from' => '192.0.2.1'], 'api_key_access_invalid'],
            'access unknown' => [['access' => 'admin', 'allow_from' => '192.0.2.1'], 'api_key_access_invalid'],
            'no network' => [['access' => 'ro'], 'ip_list_empty'],
            'only separators' => [['access' => 'ro', 'allow_from' => " ,\n"], 'ip_list_empty'],
            'hostname' => [['access' => 'ro', 'allow_from' => 'mail.example.org'], ['api_key_network_invalid', 'mail.example.org']],
            'v4 prefix too long' => [['access' => 'ro', 'allow_from' => '192.0.2.0/33'], ['api_key_network_invalid', '192.0.2.0/33']],
            'empty prefix' => [['access' => 'ro', 'allow_from' => '192.0.2.0/'], ['api_key_network_invalid', '192.0.2.0/']],
            'escaped' => [['access' => 'ro', 'allow_from' => '<b>'], ['api_key_network_invalid', '&lt;b&gt;']],
            'description too long' => [['access' => 'ro', 'skip_ip_check' => '1', 'description' => str_repeat('x', 256)], 'api_key_description_invalid'],
            'description array' => [['access' => 'ro', 'skip_ip_check' => '1', 'description' => ['x']], 'api_key_description_invalid'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function testCreateRejectsInvalidInput(array $input, string|array $msg): void
    {
        $this->repo->expects($this->never())->method('insert');

        $this->expectUserError($msg, fn() => $this->service()->create($input));
    }

    public function testSkipIpCheckNeedsNoNetwork(): void
    {
        $this->repo->expects($this->once())->method('insert')
            ->with($this->callback(fn(ApiKey $key): bool => $key->skipIpCheck === 1 && $key->allowFrom === []));

        $this->service()->create(['access' => 'ro', 'skip_ip_check' => 'true']);
    }

    public function testUpdateKeepsMissingAttributesAndKeyValue(): void
    {
        $this->repo->method('find')->with(3)->willReturn(self::stored());
        $this->repo->expects($this->once())->method('update')->with($this->callback(function (ApiKey $key): bool {
            $this->assertSame('AAAAAA-BBBBBB-CCCCCC-DDDDDD-EEEEEE', $key->key);
            $this->assertSame(['192.0.2.1', '2001:db8::/64'], $key->allowFrom);
            $this->assertSame('ro', $key->access);
            $this->assertSame('crm', $key->description);
            $this->assertSame(0, $key->active);
            return true;
        }));

        $this->service()->update(3, ['active' => '0']);
    }

    public function testUpdateRegeneratesKey(): void
    {
        $this->repo->method('find')->willReturn(self::stored());
        $this->repo->expects($this->once())->method('update')->with($this->callback(fn(ApiKey $key): bool =>
            preg_match(self::KEY_FORMAT, $key->key) === 1 && $key->key !== 'AAAAAA-BBBBBB-CCCCCC-DDDDDD-EEEEEE'));

        $this->service()->update(3, ['regen_key' => '1']);
    }

    public function testDisablingSkipIpCheckNeedsNetworks(): void
    {
        $this->repo->method('find')->willReturn(self::stored(['allow_from' => '', 'skip_ip_check' => 1]));
        $this->repo->expects($this->never())->method('update');

        $this->expectUserError('ip_list_empty', fn() => $this->service()->update(3, ['skip_ip_check' => '0']));
    }

    public function testServiceKeysAreLocked(): void
    {
        $this->repo->method('find')->willReturn(self::stored(['service' => 1, 'description' => 'API_KEY']));
        $this->repo->expects($this->never())->method('update');
        $this->repo->expects($this->never())->method('delete');

        $this->expectUserError(['api_key_service_locked', 'API_KEY'], fn() => $this->service()->update(3, ['regen_key' => '1']));
        $this->expectUserError(['api_key_service_locked', 'API_KEY'], fn() => $this->service()->delete(3));
    }

    public function testMissingKeyIsAccessDenied(): void
    {
        $this->repo->method('find')->willReturn(null);

        $this->expectUserError('access_denied', fn() => $this->service()->update(99, []));
        $this->expectUserError('access_denied', fn() => $this->service()->delete(99));
    }

    public function testDeleteReturnsDeletedKey(): void
    {
        $this->repo->method('find')->willReturn(self::stored());
        $this->repo->expects($this->once())->method('delete')->with(3);

        $this->assertSame('crm', $this->service()->delete(3)->description);
    }

    public function testSyncMirrorsBothServiceKeys(): void
    {
        $this->repo->expects($this->once())->method('syncService')->with($this->callback(function (array $keys): bool {
            $this->assertCount(2, $keys);
            [$rw, $ro] = $keys;
            $this->assertSame(['RW-KEY', 'API_KEY', 'rw', 1, ['192.0.2.1', '2001:db8::/64']],
                [$rw->key, $rw->description, $rw->access, $rw->service, $rw->allowFrom]);
            $this->assertSame(['RO-KEY', 'API_KEY_READ_ONLY', 'ro', 1, 1, 0],
                [$ro->key, $ro->description, $ro->access, $ro->service, $ro->active, $ro->skipIpCheck]);
            return true;
        }));

        $warnings = $this->service(Context::system())->syncServiceKeys([
            'API_KEY' => 'RW-KEY', 'API_KEY_READ_ONLY' => ' RO-KEY ', 'API_ALLOW_FROM' => '192.0.2.1,2001:db8::/64,nonsense',
        ]);

        $this->assertSame(['API_ALLOW_FROM: skipped invalid IP/network nonsense'], $warnings);
    }

    public static function unsetEnv(): array
    {
        return [
            'compose defaults' => [['API_KEY' => 'invalid', 'API_KEY_READ_ONLY' => 'invalid', 'API_ALLOW_FROM' => 'invalid']],
            'empty' => [['API_KEY' => '', 'API_ALLOW_FROM' => '192.0.2.1']],
            'missing' => [[]],
        ];
    }

    #[DataProvider('unsetEnv')]
    public function testSyncWithoutServiceKeysRemovesThem(array $env): void
    {
        $this->repo->expects($this->once())->method('syncService')->with([]);

        $this->assertSame([], $this->service(Context::system())->syncServiceKeys($env));
    }

    public function testSyncSkipsInvalidKeys(): void
    {
        $this->repo->expects($this->once())->method('syncService')
            ->with($this->callback(fn(array $keys): bool => count($keys) === 1 && $keys[0]->description === 'API_KEY'));

        $warnings = $this->service(Context::system())->syncServiceKeys([
            'API_KEY' => 'SAME-KEY', 'API_KEY_READ_ONLY' => 'SAME-KEY', 'API_ALLOW_FROM' => '192.0.2.1',
        ]);
        $this->assertSame(['API_KEY_READ_ONLY: same value as API_KEY, key ignored'], $warnings);

        $this->repo = $this->createMock(ApiKeyRepository::class);
        $this->repo->expects($this->once())->method('syncService')->with([]);
        $warnings = $this->service(Context::system())->syncServiceKeys(['API_KEY' => 'bad"key', 'API_KEY_READ_ONLY' => 'RO-KEY',
            'API_ALLOW_FROM' => 'invalid']);
        $this->assertSame([
            'API_KEY: invalid characters (allowed: a-z, A-Z, 0-9, -), key ignored',
            'API_KEY_READ_ONLY: API_ALLOW_FROM contains no valid IP/network, key ignored',
        ], $warnings);
    }
}
