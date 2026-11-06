<?php

declare(strict_types=1);

namespace Mailcow\Tests\Imapsync\Service;

use Mailcow\Core\UserError;
use Mailcow\Imapsync\Http\OAuthClient;
use Mailcow\Imapsync\Http\OAuthException;
use Mailcow\Imapsync\Model\OAuthToken;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Repository\SourceRepository;
use Mailcow\Imapsync\Repository\TokenRepository;
use Mailcow\Imapsync\Service\OAuthService;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OAuthServiceTest extends TestCase
{
    private SourceRepository&MockObject $sources;
    private TokenRepository&MockObject $tokens;
    private OAuthClient&MockObject $client;

    protected function setUp(): void
    {
        $this->sources = $this->createMock(SourceRepository::class);
        $this->tokens = $this->createMock(TokenRepository::class);
        $this->client = $this->createMock(OAuthClient::class);
    }

    private function service(string $hostname = 'Mail.Example.org '): OAuthService
    {
        return new OAuthService($this->sources, $this->tokens, $this->client, $hostname);
    }

    private static function source(array $row = []): Source
    {
        return Source::fromRow($row + [
            'id' => 4, 'name' => 'm365', 'created_by' => 'da', 'host1' => 'outlook.office365.com', 'port1' => 993,
            'auth_type' => 'XOAUTH2', 'oauth_flow' => 'client_credentials', 'active' => 1,
            'oauth_token_endpoint' => 'https://login.example.org/token', 'oauth_authorize_endpoint' => 'https://login.example.org/authorize?tenant=x',
            'oauth_userinfo_endpoint' => 'https://login.example.org/userinfo',
            'oauth_client_id' => 'client', 'oauth_client_secret' => 'secret', 'oauth_scope' => 'scope',
        ]);
    }

    private static function userToken(array $row = []): OAuthToken
    {
        return OAuthToken::fromRow($row + ['source_id' => 4, 'owner' => 'local@example.org', 'username' => 'remote@example.net',
            'refresh_token' => 'old-refresh', 'access_token' => 'old-access']);
    }

    public function testRedirectUriUsesMailcowHostnameOnly(): void
    {
        $this->assertSame('https://mail.example.org/syncjob-oauth', $this->service()->redirectUri());
        $this->assertSame('', $this->service('')->redirectUri());
    }

    public function testEndpointsOfAdminSourcesAreNotAddressRestricted(): void
    {
        $this->sources->method('isSuperAdmin')->willReturnMap([['admin', true], ['da', false]]);
        $this->client->expects($this->exactly(3))->method('isAllowed')
            ->willReturnCallback(fn(string $url, bool $strict): bool => !$strict);
        $service = $this->service();

        foreach (['admin' => true, 'API' => true, 'da' => false] as $createdBy => $allowed) {
            $source = self::source(['created_by' => $createdBy, 'oauth_flow' => 'authorization_code']);
            $this->assertSame($allowed, $service->authorizeUrl($source, 'state') !== null, $createdBy);
        }
    }

    public function testAuthorizeUrl(): void
    {
        $this->client->method('isAllowed')->willReturnOnConsecutiveCalls(true, false);
        $source = self::source(['oauth_flow' => 'authorization_code']);

        $this->assertSame('https://login.example.org/authorize?tenant=x&prompt=select_account&client_id=client&response_type=code'
            . '&redirect_uri=https%3A%2F%2Fmail.example.org%2Fsyncjob-oauth&response_mode=query&scope=scope&state=abc',
            $this->service()->authorizeUrl($source, 'abc'));
        $this->assertNull($this->service()->authorizeUrl($source, 'abc'));
    }

    public function testSignInParamsGoToTheAuthorizeUrlOnly(): void
    {
        $this->client->method('isAllowed')->willReturn(true);
        $this->client->expects($this->once())->method('request')->with(
            'https://login.example.org/token',
            true,
            ['resource' => 'r', 'client_id' => 'client', 'client_secret' => 'secret', 'scope' => 'scope', 'grant_type' => 'client_credentials']
        )->willReturn(['access_token' => 'new']);
        $extra = '{"prompt":"consent select_account","access_type":"offline","resource":"r"}';

        // prompt overrides the default; sign-in params never reach the token endpoint and vice versa
        $this->assertSame('https://login.example.org/authorize?tenant=x&prompt=consent+select_account&access_type=offline&client_id=client'
            . '&response_type=code&redirect_uri=https%3A%2F%2Fmail.example.org%2Fsyncjob-oauth&response_mode=query&scope=scope&state=abc',
            $this->service()->authorizeUrl(self::source(['oauth_flow' => 'authorization_code', 'oauth_extra_params' => $extra]), 'abc'));
        $this->assertNull($this->service()->refreshSourceToken(self::source(['oauth_extra_params' => $extra])));
    }

    public function testClientCredentialsRefreshStoresToken(): void
    {
        $this->client->expects($this->once())->method('request')->with(
            'https://login.example.org/token',
            true,
            ['resource' => 'r', 'client_id' => 'client', 'client_secret' => 'secret', 'scope' => 'scope', 'grant_type' => 'client_credentials']
        )->willReturn(['access_token' => 'new', 'expires_in' => '120']);
        $this->tokens->expects($this->once())->method('storeSourceToken')->with(4, 'new', $this->callback(
            fn(int $expires): bool => abs($expires - (time() + 120)) <= 2
        ));

        // Stored extra params never override the client and grant fields
        $source = self::source(['oauth_extra_params' => '{"resource":"r","client_id":"evil","grant_type":"password"}']);
        $this->assertNull($this->service()->refreshSourceToken($source));
    }

    public function testFailedRefreshStoresTheSanitisedError(): void
    {
        $this->client->method('request')->willThrowException(new OAuthException('HTTP 401: invalid_client'));
        $this->tokens->expects($this->once())->method('storeSourceError')->with(4, 'HTTP 401: invalid_client');
        $this->tokens->expects($this->never())->method('storeSourceToken');

        $this->assertSame('HTTP 401: invalid_client', $this->service()->refreshSourceToken(self::source()));
    }

    public function testTokenResponseWithoutAccessTokenIsMalformed(): void
    {
        $this->client->method('request')->willReturn(['access_token' => ['x']]);
        $this->tokens->expects($this->once())->method('storeSourceError')->with(4, 'malformed token response');

        $this->assertSame('malformed token response', $this->service()->refreshSourceToken(self::source()));
    }

    public function testOnlyClientCredentialsSourcesHaveASourceToken(): void
    {
        $this->client->expects($this->never())->method('request');

        $this->assertSame('not refreshable', $this->service()->refreshSourceToken(self::source(['oauth_flow' => 'authorization_code'])));
    }

    public function testUserRefreshKeepsRefreshTokenWhenProviderSendsNone(): void
    {
        $source = self::source(['oauth_flow' => 'authorization_code']);
        $this->client->method('request')->willReturnOnConsecutiveCalls(
            ['access_token' => 'a1'],
            ['access_token' => 'a2', 'refresh_token' => 'r2', 'expires_in' => 60]
        );
        $stored = [];
        $this->tokens->method('storeUserRefresh')->willReturnCallback(function (OAuthToken $t) use (&$stored): void {
            $stored[] = [$t->accessToken, $t->refreshToken, $t->tokenExpires - time()];
        });

        $this->assertNull($this->service()->refreshUserToken($source, self::userToken()));
        $this->assertNull($this->service()->refreshUserToken($source, self::userToken()));

        $this->assertEqualsWithDelta([['a1', 'old-refresh', 3600], ['a2', 'r2', 60]], $stored, 2);
    }

    public function testUserRefreshFailureIsStoredOnTheToken(): void
    {
        $this->client->method('request')->willThrowException(new OAuthException('HTTP 400: invalid_grant'));
        $this->tokens->expects($this->once())->method('storeUserError')->with($this->isInstanceOf(OAuthToken::class), 'HTTP 400: invalid_grant');

        $this->assertSame('HTTP 400: invalid_grant',
            $this->service()->refreshUserToken(self::source(['oauth_flow' => 'authorization_code']), self::userToken()));
        $this->assertSame('not refreshable',
            $this->service()->refreshUserToken(self::source(['oauth_flow' => 'authorization_code']), self::userToken(['refresh_token' => ''])));
    }

    public function testRefreshDueAppliesExpiryAndRetryRules(): void
    {
        // due 20 min ahead, retried 3 min after a failure
        $soon = time() + 1100;
        $this->tokens->method('refreshableSources')->willReturn([
            self::source(['id' => 1, 'name' => 'no token']),
            self::source(['id' => 2, 'name' => 'valid', 'oauth_token_expires' => time() + 1300]),
            self::source(['id' => 3, 'name' => 'failed recently', 'oauth_token_expires' => $soon, 'oauth_last_refresh_error' => 'HTTP 500', 'refresh_attempt_age' => 180]),
            self::source(['id' => 4, 'name' => 'failed long ago', 'oauth_token_expires' => $soon, 'oauth_last_refresh_error' => 'HTTP 500', 'refresh_attempt_age' => 181]),
        ]);
        $this->tokens->method('deleteUnusedOlderThan')->with(86400)->willReturn(0);
        $this->tokens->method('refreshableUserTokens')->willReturn([]);
        $this->client->expects($this->exactly(2))->method('request')->willReturnOnConsecutiveCalls(
            ['access_token' => 't'],
            $this->throwException(new OAuthException('HTTP 401: invalid_client'))
        );

        $this->assertSame([
            ['priority' => 'info', 'message' => "Refreshed token for source 'no token' (id=1)"],
            ['priority' => 'warning', 'message' => "Refresh failed for source 'failed long ago' (id=4): HTTP 401: invalid_client"],
        ], $this->service()->refreshDue());
    }

    public function testRefreshDueCleansUpAndRefreshesUserTokens(): void
    {
        $source = self::source(['oauth_flow' => 'authorization_code']);
        $this->tokens->method('refreshableSources')->willReturn([]);
        $this->tokens->method('deleteUnusedOlderThan')->willReturn(2);
        $this->tokens->method('refreshableUserTokens')->willReturn([
            self::userToken(),
            self::userToken(['username' => 'fresh@example.net', 'token_expires' => time() + 3600]),
            self::userToken(['username' => 'db@example.net']),
            self::userToken(['source_id' => 99, 'username' => 'orphan@example.net']),
        ]);
        $this->sources->method('find')->willReturnMap([[4, $source], [99, null]]);
        $this->client->method('request')->willReturn(['access_token' => 'new']);
        $this->tokens->method('storeUserRefresh')->willReturnOnConsecutiveCalls(null, $this->throwException(new PDOException('lost')));

        $this->assertSame([
            ['priority' => 'info', 'message' => 'Removed 2 unused user token(s)'],
            ['priority' => 'info', 'message' => "Refreshed user token 'remote@example.net' (mailbox local@example.org) on source 'm365' (id=4)"],
            ['priority' => 'warning', 'message' => "Refresh failed for user token 'db@example.net' (mailbox local@example.org) on source 'm365' (id=4): PDOException"],
            ['priority' => 'warning', 'message' => "Refresh failed for user token 'orphan@example.net' (mailbox local@example.org) on source '' (id=99): not refreshable"],
        ], $this->service()->refreshDue());
    }

    public function testConnectUserTakesIdentityFromIdToken(): void
    {
        $claims = rtrim(strtr(base64_encode(json_encode(['preferred_username' => 'not an address', 'upn' => 'Remote@Example.NET'])), '+/', '-_'), '=');
        $this->client->expects($this->once())->method('request')->with(
            'https://login.example.org/token',
            true,
            $this->callback(fn(array $post): bool => $post['grant_type'] === 'authorization_code' && $post['code'] === 'c0de'
                && $post['redirect_uri'] === 'https://mail.example.org/syncjob-oauth')
        )->willReturn(['access_token' => 'acc', 'expires_in' => 100, 'id_token' => 'h.' . $claims . '.s']);
        $this->tokens->expects($this->once())->method('saveUserToken')->with($this->callback(
            fn(OAuthToken $t): bool => $t->sourceId === 4 && $t->owner === 'local@example.org' && $t->username === 'remote@example.net'
                && $t->accessToken === 'acc' && $t->refreshToken === ''
        ));

        $this->assertSame('remote@example.net', $this->service()->connectUser(self::source(['oauth_flow' => 'authorization_code']), 'local@example.org', 'c0de'));
    }

    public function testConnectUserFallsBackToUserinfo(): void
    {
        $this->client->method('request')->willReturnOnConsecutiveCalls(
            ['access_token' => 'acc', 'refresh_token' => 'ref'],
            ['email' => 'remote@example.net']
        );
        $this->tokens->expects($this->once())->method('saveUserToken')->with($this->callback(fn(OAuthToken $t): bool => $t->refreshToken === 'ref'));

        $this->assertSame('remote@example.net', $this->service()->connectUser(self::source(['oauth_flow' => 'authorization_code']), 'local@example.org', 'c'));
    }

    public function testConnectUserErrors(): void
    {
        $source = self::source(['oauth_flow' => 'authorization_code']);
        $this->client->method('request')->willReturnOnConsecutiveCalls(
            $this->throwException(new OAuthException('HTTP 400: invalid_grant')),
            ['access_token' => 'acc'],
            $this->throwException(new OAuthException('HTTP 401')),
            ['access_token' => 'acc', 'id_token' => 'not.a.jwt.at.all']
        );
        $this->tokens->expects($this->never())->method('saveUserToken');

        $this->assertUserError(['token_failed', 'token exchange failed for source 4: HTTP 400: invalid_grant'], fn() => $this->service()->connectUser($source, 'o', 'c'));
        $this->assertUserError('no_identity', fn() => $this->service()->connectUser($source, 'o', 'c'));
        $this->assertUserError('no_identity', fn() => $this->service()->connectUser(self::source(['oauth_flow' => 'authorization_code', 'oauth_userinfo_endpoint' => null]), 'o', 'c'));
    }

    public function testConnectUserReportsStorageFailure(): void
    {
        $this->client->method('request')->willReturnOnConsecutiveCalls(['access_token' => 'acc'], ['email' => 'r@example.net']);
        $this->tokens->method('saveUserToken')->willThrowException(new PDOException('duplicate'));

        $this->assertUserError(['token_failed', 'storing token failed for source 4: duplicate'],
            fn() => $this->service()->connectUser(self::source(['oauth_flow' => 'authorization_code']), 'o', 'c'));
    }

    private function assertUserError(string|array $msg, callable $call): void
    {
        try {
            $call();
            $this->fail('UserError expected');
        } catch (UserError $e) {
            $this->assertSame($msg, $e->msg());
        }
    }
}
