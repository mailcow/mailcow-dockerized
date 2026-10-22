<?php

declare(strict_types=1);

namespace Mailcow\Tests\Imapsync\Service;

use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Http\OAuthClient;
use Mailcow\Imapsync\Http\OAuthException;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Repository\SourceRepository;
use Mailcow\Imapsync\Repository\TokenRepository;
use Mailcow\Imapsync\Service\OAuthService;
use Mailcow\Imapsync\Service\SourceService;
use Mailcow\Tests\Support\FakeContext;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SourceServiceTest extends TestCase
{
    private SourceRepository&MockObject $repo;
    private TokenRepository&MockObject $tokens;
    private OAuthClient&MockObject $client;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SourceRepository::class);
        $this->tokens = $this->createMock(TokenRepository::class);
        $this->client = $this->createMock(OAuthClient::class);
        $this->client->method('isAllowed')->willReturn(true);
    }

    /** SourceService with the real OAuthService (settings rules); only the HTTP client and the repositories are mocked */
    private function service(Context $context, ?OAuthClient $client = null): SourceService
    {
        return new SourceService($this->repo, $this->tokens,
            new OAuthService($this->repo, $this->tokens, $client ?? $this->client, 'mail.example.org'), $context);
    }

    private static function source(array $row = []): Source
    {
        return Source::fromRow($row + [
            'id' => 7,
            'name' => 'src',
            'created_by' => 'owner@example.org',
            'scope' => 'user',
            'host1' => 'imap.example.org',
            'port1' => 993,
            'enc1' => 'SSL',
            'auth_type' => 'PLAIN',
            'active' => 1,
        ]);
    }

    private static function oauthSource(array $row = []): Source
    {
        return self::source($row + [
            'created_by' => 'da',
            'auth_type' => 'XOAUTH2',
            'oauth_flow' => 'client_credentials',
            'oauth_token_endpoint' => 'https://login.example.org/token',
            'oauth_client_id' => 'client',
            'oauth_client_secret' => 'stored-secret',
            'oauth_scope' => 'https://outlook.office365.com/.default',
            'oauth_access_token' => 'access',
        ]);
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

    public static function editMatrix(): array
    {
        $other = ['other@example.org'];
        return [
            'admin edits everything' => [FakeContext::admin(), 'x@example.org', true],
            'admin edits handed-over sources' => [FakeContext::admin(), '', true],
            'access_all_exception' => [new FakeContext('user', 'u@example.org', [], true), 'x@example.org', true],
            'user edits own' => [FakeContext::user('u@example.org'), 'u@example.org', true],
            'user not foreign' => [FakeContext::user('u@example.org'), 'x@example.org', false],
            'user not handed-over' => [FakeContext::user('u@example.org'), '', false],
            'domain admin edits own' => [FakeContext::domainAdmin(), 'da', true],
            'domain admin edits his mailboxes' => [FakeContext::domainAdmin($other), 'other@example.org', true],
            'domain admin not foreign mailboxes' => [FakeContext::domainAdmin($other), 'x@example.org', false],
            'domain admin not handed-over' => [FakeContext::domainAdmin(['']), '', false],
            'missing username is no creator' => [new FakeContext('user', null), '', false],
        ];
    }

    #[DataProvider('editMatrix')]
    public function testEditPermissionMatrix(Context $context, string $createdBy, bool $canEdit): void
    {
        $this->assertSame($canEdit, $this->service($context)->canEdit(self::source(['created_by' => $createdBy])));
    }

    public function testWithoutAclNothingIsVisibleExceptForAdmins(): void
    {
        $this->repo->expects($this->once())->method('findVisible')->willReturn(self::source());
        $this->repo->expects($this->once())->method('visibleIds')->willReturn([7]);

        $this->assertNull($this->service(FakeContext::user('u@example.org', []))->get(7));
        $this->assertSame([], $this->service(FakeContext::domainAdmin([], [], []))->visibleIds());
        $this->assertNotNull($this->service(FakeContext::admin([]))->get(7));
        $this->assertSame([7], $this->service(FakeContext::admin([]))->visibleIds());
    }

    public static function noEditors(): array
    {
        return [
            'not logged in' => [new FakeContext('', null)],
            'system' => [Context::system()],
            'user without ACL' => [FakeContext::user('u@example.org', [])],
            'domain admin without ACL' => [FakeContext::domainAdmin([], [], ['syncjobs' => '0'])],
        ];
    }

    #[DataProvider('noEditors')]
    public function testCreateNeedsAnEditorWithAcl(Context $context): void
    {
        $this->repo->expects($this->never())->method('insert');

        $this->expectUserError('access_denied', fn() => $this->service($context)->create(['name' => 'a', 'host' => 'h.example.org', 'port' => 993]));
    }

    public function testUserSourceIsAlwaysPrivate(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(function (Source $s): bool {
            return $s->scope === 'user' && $s->domains === [] && $s->users === [] && $s->createdBy === 'u@example.org'
                && $s->host === 'imap.example.org' && $s->port === 993 && $s->enc === 'TLS' && $s->active === 1;
        }));

        $source = $this->service(FakeContext::user('u@example.org'))->create([
            'name' => ' Mine ', 'host' => 'IMAP.example.org', 'port' => '993', 'scope' => 'all', 'domains' => ['example.org'],
        ]);

        $this->assertSame('Mine', $source->name);
    }

    public function testUsersMayNotCreateXOAuth2Sources(): void
    {
        $this->repo->expects($this->never())->method('insert');

        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user())->create($this->oauthInput()));
    }

    public function testDomainAdminScopeRules(): void
    {
        $service = $this->service(FakeContext::domainAdmin(['m@example.org'], ['example.org']));
        $input = ['name' => 'a', 'host' => 'imap.example.org', 'port' => 993];

        $this->expectUserError('access_denied', fn() => $service->create($input + ['scope' => 'all']));
        $this->expectUserError('access_denied', fn() => $service->create($input + ['scope' => 'everyone']));
        $this->expectUserError('access_denied', fn() => $service->create($input + ['scope' => 'domain', 'domains' => ['foreign.org']]));
        $this->expectUserError('access_denied', fn() => $service->create($input + ['scope' => 'user', 'users' => ['x@foreign.org']]));
        $this->expectUserError('imapsync_source_scope_empty', fn() => $service->create($input + ['scope' => 'domain', 'domains' => [' ', ['x']]]));
    }

    public function testDomainScopeTargetsAreCleaned(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(
            fn(Source $s): bool => $s->scope === 'domain' && $s->domains === ['example.org'] && $s->users === []
        ));

        $this->service(FakeContext::domainAdmin([], ['example.org']))->create([
            'name' => 'a', 'host' => 'imap.example.org', 'port' => 993,
            'scope' => 'domain', 'domains' => [' Example.org', 'example.org', ''], 'users' => ['ignored@example.org'],
        ]);
    }

    public function testClientCredentialsSourcesAreNeverShared(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(
            fn(Source $s): bool => $s->scope === 'user' && $s->domains === [] && $s->usesClientCredentials()
        ));

        $this->service(FakeContext::admin())->create($this->oauthInput() + ['scope' => 'all']);
    }

    public static function invalidBasics(): array
    {
        $valid = ['name' => 'a', 'description' => '', 'host' => 'imap.example.org', 'port' => 993, 'enc' => 'TLS', 'auth_type' => 'PLAIN'];
        return [
            'name missing' => [['name' => ''] + $valid, 'imapsync_source_name_invalid'],
            'name too long' => [['name' => str_repeat('n', 101)] + $valid, 'imapsync_source_name_invalid'],
            'name is array' => [['name' => ['x']] + $valid, 'imapsync_source_name_invalid'],
            'description too long' => [['description' => str_repeat('d', 256)] + $valid, 'imapsync_source_description_invalid'],
            'host invalid' => [['host' => 'imap_server.lan'] + $valid, 'imapsync_source_host_invalid'],
            'host too long' => [['host' => str_repeat('a.', 127) . 'org'] + $valid, 'imapsync_source_host_invalid'],
            'port 0' => [['port' => 0] + $valid, 'imapsync_source_port_invalid'],
            'port too high' => [['port' => 65536] + $valid, 'imapsync_source_port_invalid'],
            'enc unknown' => [['enc' => 'STARTTLS'] + $valid, 'imapsync_source_enc_invalid'],
            'auth unknown' => [['auth_type' => 'GSSAPI'] + $valid, 'imapsync_source_auth_invalid'],
            'name checked first' => [['name' => '', 'host' => '', 'port' => 0] + $valid, 'imapsync_source_name_invalid'],
        ];
    }

    #[DataProvider('invalidBasics')]
    public function testCreateValidatesBasics(array $input, string $msg): void
    {
        $this->expectUserError($msg, fn() => $this->service(FakeContext::admin())->create($input));
    }

    public function testIpAddressAndLegacyAliasesAreAccepted(): void
    {
        $this->repo->expects($this->once())->method('insert')->with($this->callback(
            fn(Source $s): bool => $s->host === '192.0.2.10' && $s->port === 143 && $s->enc === 'PLAIN'
        ));

        $this->service(FakeContext::admin())->create(['name' => 'a', 'host1' => '192.0.2.10', 'port1' => 143, 'enc1' => 'PLAIN']);
    }

    public function testNameMustBeUniquePerCreator(): void
    {
        $this->repo->method('nameExists')->with('<b>', 'admin', null)->willReturn(true);
        $this->repo->expects($this->never())->method('insert');

        $this->expectUserError(['object_exists', '&lt;b&gt;'], fn() => $this->service(FakeContext::admin())->create(
            ['name' => '<b>', 'host' => 'imap.example.org', 'port' => 993]
        ));
    }

    public static function invalidOAuth(): array
    {
        return [
            'secret missing' => [['oauth_client_secret' => ''], 'imapsync_source_oauth_required'],
            'scope missing' => [['oauth_scope' => ''], 'imapsync_source_oauth_required'],
            'endpoint is array' => [['oauth_token_endpoint' => ['x']], 'imapsync_source_oauth_invalid'],
            'secret is array' => [['oauth_client_secret' => ['x']], 'imapsync_source_oauth_invalid'],
            'client id too long' => [['oauth_client_id' => str_repeat('c', 501)], 'imapsync_source_oauth_invalid'],
            'extra params list' => [['oauth_extra_params' => '[1,2]'], 'imapsync_source_oauth_extra_invalid'],
            'extra params nested' => [['oauth_extra_params' => ['a' => ['b' => 1]]], 'imapsync_source_oauth_extra_invalid'],
            'extra params no JSON' => [['oauth_extra_params' => '{nope'], 'imapsync_source_oauth_extra_invalid'],
            'extra params too long' => [['oauth_extra_params' => ['a' => str_repeat('x', 4000)]], 'imapsync_source_oauth_extra_invalid'],
        ];
    }

    #[DataProvider('invalidOAuth')]
    public function testOAuthAttributesAreValidated(array $override, string $msg): void
    {
        $this->expectUserError($msg, fn() => $this->service(FakeContext::admin())->create($override + $this->oauthInput()));
    }

    public function testExtraParamsAreAcceptedAsArrayOrString(): void
    {
        $stored = [];
        $this->repo->method('insert')->willReturnCallback(function (Source $s) use (&$stored): int {
            $stored[] = $s->extraParams;
            return 1;
        });
        $service = $this->service(FakeContext::admin());

        $service->create(['oauth_extra_params' => ['resource' => 'https://x/', 'n' => 1]] + $this->oauthInput());
        $service->create(['oauth_extra_params' => '{"resource":"https://x/"}'] + $this->oauthInput());
        $service->create(['oauth_extra_params' => '  '] + $this->oauthInput());

        $this->assertSame(['{"resource":"https://x/","n":1}', '{"resource":"https://x/"}', null], $stored);
    }

    public function testEndpointsMustPassThePolicyOfTheCreator(): void
    {
        // Sources of a domain admin ('da', no superadmin) get the strict address policy for every endpoint
        $client = $this->createMock(OAuthClient::class);
        $client->expects($this->exactly(3))->method('isAllowed')
            ->willReturnCallback(fn(string $url, bool $strict): bool => $strict && $url !== 'https://internal/userinfo');
        $this->repo->method('isSuperAdmin')->with('da')->willReturn(false);
        $service = $this->service(FakeContext::domainAdmin(), $client);

        $this->expectUserError('imapsync_source_oauth_endpoint_invalid', fn() => $service->create([
            'oauth_flow' => 'authorization_code',
            'oauth_authorize_endpoint' => 'https://login.example.org/authorize',
            'oauth_userinfo_endpoint' => 'https://internal/userinfo',
        ] + $this->oauthInput()));
    }

    public function testUpdateNeedsVisibleEditableSource(): void
    {
        $this->repo->method('findVisible')->willReturnOnConsecutiveCalls(null, self::source(['created_by' => 'x@example.org']));
        $this->repo->expects($this->never())->method('update');
        $service = $this->service(FakeContext::user('u@example.org'));

        $this->expectUserError('access_denied', fn() => $service->update(7, ['name' => 'b']));
        $this->expectUserError('access_denied', fn() => $service->update(7, ['name' => 'b']));
    }

    public function testUsersMayNotSwitchToXOAuth2(): void
    {
        $this->repo->method('findVisible')->willReturn(self::source(['created_by' => 'u@example.org']));
        $this->repo->expects($this->never())->method('update');

        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('u@example.org'))->update(7, $this->oauthInput()));
    }

    public function testUnchangedLegacyValuesAreNotValidatedAgain(): void
    {
        $this->repo->method('findVisible')->willReturn(self::source(['host1' => 'imap_server.lan', 'enc1' => null]));
        $this->repo->expects($this->once())->method('update')->with(
            $this->callback(fn(Source $s): bool => $s->name === 'renamed' && $s->host === 'imap_server.lan' && $s->enc === null),
            false,
            false
        );

        $this->service(FakeContext::admin())->update(7, ['name' => 'renamed', 'host' => 'IMAP_SERVER.lan']);
    }

    public function testRenameMustStayUniqueForTheCreator(): void
    {
        $this->repo->method('findVisible')->willReturn(self::source());
        $this->repo->method('nameExists')->with('taken', 'owner@example.org', 7)->willReturn(true);

        $this->expectUserError(['object_exists', 'taken'], fn() => $this->service(FakeContext::admin())->update(7, ['name' => 'taken']));
    }

    public function testEmptySecretKeepsStoredOneAndTokensSurviveUnrelatedChanges(): void
    {
        $this->repo->method('findVisible')->willReturn(self::oauthSource());
        $this->repo->expects($this->once())->method('update')->with(
            $this->callback(fn(Source $s): bool => $s->clientSecret === 'stored-secret' && $s->oauthScope === 'new-scope'),
            true,
            false
        );

        $this->service(FakeContext::domainAdmin())->update(7, ['oauth_client_secret' => '', 'oauth_scope' => 'new-scope']);
    }

    public static function connectionChanges(): array
    {
        return [
            'token endpoint' => [['oauth_token_endpoint' => 'https://other.example.org/token']],
            'client id' => [['oauth_client_id' => 'other']],
            'host' => [['host' => 'other.example.org']],
            'port' => [['port' => 143]],
            'encryption' => [['enc' => 'TLS']],
            'flow' => [['oauth_flow' => 'authorization_code', 'oauth_authorize_endpoint' => 'https://login.example.org/a']],
            'auth type' => [['auth_type' => 'PLAIN']],
        ];
    }

    #[DataProvider('connectionChanges')]
    public function testTokensAreResetWhenTheConnectionChanges(array $input): void
    {
        $this->repo->method('findVisible')->willReturn(self::oauthSource());
        $this->repo->expects($this->once())->method('update')->with($this->anything(), $this->anything(), true);

        $this->service(FakeContext::domainAdmin())->update(7, $input);
    }

    public function testTargetsAreOnlyRewrittenWhenScopeIsSent(): void
    {
        $this->repo->method('findVisible')->willReturn(self::source(['scope' => 'domain']));
        $calls = [];
        $this->repo->method('update')->willReturnCallback(function (Source $s, bool $targets) use (&$calls): void {
            $calls[] = [$s->scope, $targets];
        });
        $service = $this->service(FakeContext::admin());

        $service->update(7, ['name' => 'x']);
        $service->update(7, ['scope' => 'all']);

        $this->assertSame([['domain', false], ['all', true]], $calls);
    }

    public function testApiArrayNeverContainsSecrets(): void
    {
        $source = self::oauthSource(['oauth_extra_params' => '{"a":"b"}']);

        $editor = $this->service(FakeContext::admin())->toArray($source);
        $viewer = $this->service(FakeContext::user('u@example.org'))->toArray($source);

        foreach ([$editor, $viewer] as $data) {
            $this->assertArrayNotHasKey('oauth_client_secret', $data);
            $this->assertArrayNotHasKey('oauth_access_token', $data);
            $this->assertStringNotContainsString('stored-secret', json_encode($data));
            $this->assertTrue($data['has_client_secret']);
            $this->assertTrue($data['has_access_token']);
            $this->assertArrayNotHasKey('oauth_redirect_uri', $data);
        }
        $this->assertTrue($editor['can_edit']);
        $this->assertSame('{"a":"b"}', $editor['oauth_extra_params']);
        $this->assertFalse($viewer['can_edit']);
        $this->assertNull($viewer['oauth_extra_params']);
    }

    public function testApiArrayOfAuthorizationCodeSourceHasRedirectUri(): void
    {
        $data = $this->service(FakeContext::admin())->toArray(self::oauthSource(['oauth_flow' => 'authorization_code']));

        $this->assertSame('https://mail.example.org/syncjob-oauth', $data['oauth_redirect_uri']);
        $this->assertSame(['host' => 'imap.example.org', 'port' => 993, 'enc' => 'SSL'],
            array_intersect_key($data, ['host' => 1, 'port' => 1, 'enc' => 1]));
    }

    public function testDeleteRefusesSourcesInUse(): void
    {
        $this->repo->method('findVisible')->willReturn(self::source());
        $this->repo->method('isInUse')->willReturnOnConsecutiveCalls(true, false);
        $this->repo->expects($this->once())->method('delete')->with(7);
        $service = $this->service(FakeContext::admin());

        $this->expectUserError(['imapsync_source_in_use', 'src'], fn() => $service->delete(7));
        $this->assertSame('src', $service->delete(7)->name);
    }

    public function testRefreshTokenRules(): void
    {
        $this->repo->method('findVisible')->willReturnOnConsecutiveCalls(
            self::oauthSource(['oauth_flow' => 'authorization_code']),
            self::oauthSource(),
            self::oauthSource()
        );
        $this->client->method('request')->willReturnOnConsecutiveCalls(
            $this->throwException(new OAuthException('HTTP 401: invalid_client')),
            ['access_token' => 'new']
        );
        $service = $this->service(FakeContext::admin());

        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::admin([]))->refreshToken(7));
        $this->expectUserError('imapsync_source_refresh_unsupported', fn() => $service->refreshToken(7));
        $this->expectUserError(['imapsync_source_token_refresh_failed', 'src (HTTP 401: invalid_client)'], fn() => $service->refreshToken(7));
        $this->assertSame('src', $service->refreshToken(7)->name);
    }

    public function testSavedClientCredentialsSourcesFetchTheirFirstToken(): void
    {
        $this->repo->method('findVisible')->willReturnOnConsecutiveCalls(self::oauthSource(), self::oauthSource(['active' => 0]));
        $this->client->expects($this->exactly(2))->method('request')->willReturnOnConsecutiveCalls(
            ['access_token' => 'first', 'expires_in' => 3600],
            $this->throwException(new OAuthException('HTTP 401: invalid_client'))
        );
        $this->tokens->expects($this->once())->method('storeSourceToken');
        $this->tokens->expects($this->once())->method('storeSourceError')->with(7, 'HTTP 401: invalid_client');
        $service = $this->service(FakeContext::admin());

        $this->assertNull($service->create($this->oauthInput())->lastRefreshError);
        $this->assertSame('HTTP 401: invalid_client', $service->update(7, ['oauth_scope' => 'new-scope'])->lastRefreshError);
        // inactive sources get no token
        $service->update(7, ['oauth_scope' => 'other-scope']);
    }

    public function testConnectableSourceIsActiveAuthorizationCode(): void
    {
        $this->repo->method('findVisible')->willReturnOnConsecutiveCalls(
            self::oauthSource(['oauth_flow' => 'authorization_code']),
            self::oauthSource(['oauth_flow' => 'authorization_code', 'active' => 0]),
            self::oauthSource()
        );
        $service = $this->service(FakeContext::user());

        $this->assertNotNull($service->findConnectable(7));
        $this->assertNull($service->findConnectable(7));
        $this->assertNull($service->findConnectable(7));
    }

    public function testAccountRenameReleasesLeftoversAndMovesEverything(): void
    {
        $this->repo->method('listCreatedBy')->with('new@example.org')->willReturn([
            self::source(['id' => 1, 'name' => 'unused']),
            self::source(['id' => 2, 'name' => 'used']),
        ]);
        $this->repo->method('isInUse')->willReturnMap([[1, false], [2, true]]);
        $this->repo->method('nameExists')->with('used', '')->willReturn(true);
        $this->repo->expects($this->once())->method('deleteWithChildren')->with(1);
        $this->repo->expects($this->once())->method('handOver')->with(2, 'used (#2)');
        $this->repo->expects($this->once())->method('renameAccount')->with('old@example.org', 'new@example.org');
        $this->tokens->expects($this->once())->method('renameOwner')->with('old@example.org', 'new@example.org');

        $this->service(Context::system())->onAccountRenamed('old@example.org', 'new@example.org');
    }

    public function testAccountRenameToItselfDoesNothing(): void
    {
        $this->repo->expects($this->never())->method('listCreatedBy');
        $this->tokens->expects($this->never())->method('renameOwner');

        $this->service(Context::system())->onAccountRenamed('a@example.org', 'a@example.org');
        $this->service(Context::system())->onAccountDeleted('');
    }

    public function testAccountDeleteRemovesTokensAndTargets(): void
    {
        $this->repo->method('listCreatedBy')->with('old@example.org')->willReturn([self::source(['id' => 3, 'name' => str_repeat('n', 100)])]);
        $this->repo->method('isInUse')->willReturn(true);
        $this->repo->method('nameExists')->willReturn(true);
        $this->repo->expects($this->once())->method('handOver')->with(3, str_repeat('n', 80) . ' (#3)');
        $this->tokens->expects($this->once())->method('deleteByOwner')->with('old@example.org');
        $this->repo->expects($this->once())->method('deleteUserTarget')->with('old@example.org');

        $this->service(Context::system())->onAccountDeleted('old@example.org');
    }

    public function testAccountCascadeLetsDatabaseErrorsThrough(): void
    {
        $this->repo->method('listCreatedBy')->willThrowException(new PDOException('gone'));

        $this->expectException(PDOException::class);
        $this->service(Context::system())->onAccountDeleted('old@example.org');
    }

    private function oauthInput(): array
    {
        return [
            'name' => 'oauth',
            'host' => 'outlook.office365.com',
            'port' => 993,
            'enc' => 'SSL',
            'auth_type' => 'XOAUTH2',
            'oauth_flow' => 'client_credentials',
            'oauth_token_endpoint' => 'https://login.example.org/token',
            'oauth_client_id' => 'client',
            'oauth_client_secret' => 'secret',
            'oauth_scope' => 'scope',
        ];
    }
}
