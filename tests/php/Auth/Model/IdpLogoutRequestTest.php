<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth\Model;

use Mailcow\Auth\Model\IdpLogoutRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdpLogoutRequestTest extends TestCase
{
    private const SERVER = 'https://kc.example.org';
    private const REDIRECT = 'https://mail.example.org/';

    private static function idToken(int $size): string
    {
        return 'eyJhbGciOiJSUzI1NiJ9.' . str_repeat('a', $size) . '.' . str_repeat('s', 342);
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, string>}> version, method, params */
    public static function largeTokenCases(): array
    {
        return [
            // Keycloak < 19 handles a logout POST as refresh token logout: GET without id_token_hint
            'Keycloak 17' => ['17.0.1', 'GET', ['client_id' => 'mailcow', 'redirect_uri' => self::REDIRECT]],
            // post_logout_redirect_uri needs id_token_hint in Keycloak 18, so it goes too (confirmation page)
            'Keycloak 18' => ['18.0.2', 'GET', ['client_id' => 'mailcow']],
            'Keycloak 19' => ['19.0.0', 'POST', []],
            'Keycloak 26' => ['26.4.0', 'POST', []],
            'no version' => ['', 'POST', []],
        ];
    }

    #[DataProvider('largeTokenCases')]
    public function testLargeTokenTransportDependsOnTheKeycloakVersion(string $version, string $method, array $params): void
    {
        $token = self::idToken(4000);
        $request = IdpLogoutRequest::keycloak(self::SERVER, 'r1', $version, 'mailcow', self::REDIRECT, $token);
        $transport = $request->transport();

        $this->assertSame($method, $transport['method']);
        $this->assertSame(self::SERVER . '/realms/r1/protocol/openid-connect/logout', $transport['action']);
        if ($method === 'POST') {
            $this->assertFalse($transport['reduced']);
            $this->assertSame($token, $transport['params']['id_token_hint']);
            $this->assertSame(self::REDIRECT, $transport['params']['post_logout_redirect_uri']);
        } else {
            $this->assertTrue($transport['reduced']);
            $this->assertSame($params, $transport['params']);
            $this->assertLessThanOrEqual(IdpLogoutRequest::MAX_REDIRECT_URL, strlen($request->url($transport['params'])));
        }
    }

    /** @return array<string, array{0: string, 1: string}> version, redirect parameter */
    public static function smallTokenCases(): array
    {
        return [
            'Keycloak 17' => ['17.0.1', 'redirect_uri'],
            'Keycloak 18' => ['18.0.2', 'post_logout_redirect_uri'],
            'Keycloak 26' => ['26.4.0', 'post_logout_redirect_uri'],
        ];
    }

    #[DataProvider('smallTokenCases')]
    public function testSmallTokenIsARedirectWithTheHint(string $version, string $redirectParam): void
    {
        $token = self::idToken(800);
        $transport = IdpLogoutRequest::keycloak(self::SERVER, 'r1', $version, 'mailcow', self::REDIRECT, $token)->transport();

        $this->assertSame('GET', $transport['method']);
        $this->assertFalse($transport['reduced']);
        $this->assertSame(['client_id' => 'mailcow', $redirectParam => self::REDIRECT, 'id_token_hint' => $token], $transport['params']);
    }

    public function testGenericOidcPostsALargeToken(): void
    {
        $token = self::idToken(4000);
        $request = IdpLogoutRequest::generic('https://idp.example.org/logout?tenant=1', 'mailcow', self::REDIRECT, $token);

        $this->assertSame(['method' => 'POST', 'action' => 'https://idp.example.org/logout?tenant=1',
            'params' => ['client_id' => 'mailcow', 'post_logout_redirect_uri' => self::REDIRECT, 'id_token_hint' => $token],
            'reduced' => false], $request->transport());
        $this->assertStringStartsWith('https://idp.example.org/logout?tenant=1&client_id=mailcow&', $request->url());
    }

    public function testWithoutTokenThereIsNoHint(): void
    {
        $transport = IdpLogoutRequest::generic('https://idp.example.org/logout', 'mailcow', self::REDIRECT, null)->transport();

        $this->assertSame('GET', $transport['method']);
        $this->assertSame(['client_id' => 'mailcow', 'post_logout_redirect_uri' => self::REDIRECT], $transport['params']);
    }

    public function testTheLimitIsInclusive(): void
    {
        $request = IdpLogoutRequest::generic('https://idp.example.org/logout', 'mailcow', self::REDIRECT, '');
        $base = strlen($request->url());
        $fits = IdpLogoutRequest::generic('https://idp.example.org/logout', 'mailcow', self::REDIRECT,
            str_repeat('a', IdpLogoutRequest::MAX_REDIRECT_URL - $base - strlen('&id_token_hint=')));
        $over = IdpLogoutRequest::generic('https://idp.example.org/logout', 'mailcow', self::REDIRECT,
            str_repeat('a', IdpLogoutRequest::MAX_REDIRECT_URL - $base - strlen('&id_token_hint=') + 1));

        $this->assertSame(IdpLogoutRequest::MAX_REDIRECT_URL, strlen($fits->url()));
        $this->assertSame('GET', $fits->transport()['method']);
        $this->assertSame('POST', $over->transport()['method']);
    }
}
