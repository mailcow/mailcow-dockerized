<?php

namespace OAuth2\OpenID\ResponseType;

use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Storage\Bootstrap;
use OAuth2\GrantType\ClientCredentials;
use PHPUnit\Framework\TestCase;

class CodeIdTokenTest extends TestCase
{
    public function testHandleAuthorizeRequest()
    {
        // add the test parameters in memory
        $server = $this->getTestServer();

        $request = new Request(array(
            'response_type' => 'code id_token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid',
            'state'         => 'test',
            'nonce'         => 'test',
        ));

        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $this->assertStringNotContainsString('error', $location);

        $parts = parse_url($location);
        $this->assertArrayHasKey('query', $parts);

        // assert fragment is in "application/x-www-form-urlencoded" format
        parse_str($parts['query'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('id_token', $params);
        $this->assertArrayHasKey('code', $params);

        // validate ID Token
        $parts = explode('.', $params['id_token']);
        foreach ($parts as &$part) {
            // Each part is a base64url encoded json string.
            $part = str_replace(array('-', '_'), array('+', '/'), $part);
            $part = base64_decode($part);
            $part = json_decode($part, true);
        }
        list($header, $claims, $signature) = $parts;

        $this->assertArrayHasKey('iss', $claims);
        $this->assertArrayHasKey('sub', $claims);
        $this->assertArrayHasKey('aud', $claims);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('auth_time', $claims);
        $this->assertArrayHasKey('nonce', $claims);

        // only exists if an access token was granted along with the id_token
        $this->assertArrayNotHasKey('at_hash', $claims);

        $this->assertEquals($claims['iss'], 'test');
        $this->assertEquals($claims['aud'], 'Test Client ID');
        $this->assertEquals($claims['nonce'], 'test');
        $duration = $claims['exp'] - $claims['iat'];
        $this->assertEquals($duration, 3600);
    }

    public function testUserClaimsWithUserId()
    {
        // add the test parameters in memory
        $server = $this->getTestServer();

        $request = new Request(array(
            'response_type' => 'code id_token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid email',
            'state'         => 'test',
            'nonce'         => 'test',
        ));

        $userId = 'testuser';
        $server->handleAuthorizeRequest($request, $response = new Response(), true, $userId);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $this->assertStringNotContainsString('error', $location);

        $parts = parse_url($location);
        $this->assertArrayHasKey('query', $parts);

        // assert fragment is in "application/x-www-form-urlencoded" format
        parse_str($parts['query'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('id_token', $params);
        $this->assertArrayHasKey('code', $params);

        // validate ID Token
        $parts = explode('.', $params['id_token']);
        foreach ($parts as &$part) {
            // Each part is a base64url encoded json string.
            $part = str_replace(array('-', '_'), array('+', '/'), $part);
            $part = base64_decode($part);
            $part = json_decode($part, true);
        }
        list($header, $claims, $signature) = $parts;

        $this->assertArrayHasKey('email', $claims);
        $this->assertArrayHasKey('email_verified', $claims);
        $this->assertNotNull($claims['email']);
        $this->assertNotNull($claims['email_verified']);
    }

    public function testUserClaimsWithoutUserId()
    {
        // add the test parameters in memory
        $server = $this->getTestServer();

        $request = new Request(array(
            'response_type' => 'code id_token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid email',
            'state'         => 'test',
            'nonce'         => 'test',
        ));

        $userId = null;
        $server->handleAuthorizeRequest($request, $response = new Response(), true, $userId);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $this->assertStringNotContainsString('error', $location);

        $parts = parse_url($location);
        $this->assertArrayHasKey('query', $parts);

        // assert fragment is in "application/x-www-form-urlencoded" format
        parse_str($parts['query'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('id_token', $params);
        $this->assertArrayHasKey('code', $params);

        // validate ID Token
        $parts = explode('.', $params['id_token']);
        foreach ($parts as &$part) {
            // Each part is a base64url encoded json string.
            $part = str_replace(array('-', '_'), array('+', '/'), $part);
            $part = base64_decode($part);
            $part = json_decode($part, true);
        }
        list($header, $claims, $signature) = $parts;

        $this->assertArrayNotHasKey('email', $claims);
        $this->assertArrayNotHasKey('email_verified', $claims);
    }

    private function getTestServer($config = array())
    {
        $config += array(
            'use_openid_connect' => true,
            'issuer' => 'test',
            'id_lifetime' => 3600,
            'allow_implicit' => true,
        );

        $memoryStorage = Bootstrap::getInstance()->getMemoryStorage();
        $memoryStorage->supportedScopes[] = 'email';
        $responseTypes = array(
            'code'     => $code    = new AuthorizationCode($memoryStorage),
            'id_token' => $idToken = new IdToken($memoryStorage, $memoryStorage, $config),
            'code id_token' => new CodeIdToken($code, $idToken),
        );

        $server = new Server($memoryStorage, $config, array(), $responseTypes);
        $server->addGrantType(new ClientCredentials($memoryStorage));

        return $server;
    }
}
