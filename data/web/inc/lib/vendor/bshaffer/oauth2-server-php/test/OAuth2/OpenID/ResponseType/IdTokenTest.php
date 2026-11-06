<?php

namespace OAuth2\OpenID\ResponseType;

use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Storage\Bootstrap;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\Encryption\Jwt;
use PHPUnit\Framework\TestCase;

class IdTokenTest extends TestCase
{
    public function testValidateAuthorizeRequest()
    {
        $query = array(
            'response_type' => 'id_token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid',
            'state'         => 'test',
        );

        // attempt to do the request without a nonce.
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request($query);
        $valid = $server->validateAuthorizeRequest($request, $response = new Response());

        // Add a nonce and retry.
        $query['nonce'] = 'test';
        $request = new Request($query);
        $valid = $server->validateAuthorizeRequest($request, $response = new Response());
        $this->assertTrue($valid);
    }

    public function testHandleAuthorizeRequest()
    {
        // add the test parameters in memory
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request(array(
            'response_type' => 'id_token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid email',
            'state'         => 'test',
            'nonce'         => 'test',
        ));

        $user_id = 'testuser';
        $server->handleAuthorizeRequest($request, $response = new Response(), true, $user_id);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $this->assertStringNotContainsString('error', $location);

        $parts = parse_url($location);
        $this->assertArrayHasKey('fragment', $parts);
        $this->assertFalse(isset($parts['query']));

        // assert fragment is in "application/x-www-form-urlencoded" format
        parse_str($parts['fragment'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('id_token', $params);
        $this->assertArrayNotHasKey('access_token', $params);
        $this->validateIdToken($params['id_token']);
    }

    public function testPassInAuthTime()
    {
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request(array(
            'response_type' => 'id_token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid email',
            'state'         => 'test',
            'nonce'         => 'test',
        ));

        // test with a scalar user id
        $user_id = 'testuser123';
        $server->handleAuthorizeRequest($request, $response = new Response(), true, $user_id);

        list($header, $payload, $signature) = $this->extractTokenDataFromResponse($response);

        $this->assertTrue(is_array($payload));
        $this->assertArrayHasKey('sub', $payload);
        $this->assertEquals($user_id, $payload['sub']);
        $this->assertArrayHasKey('auth_time', $payload);

        // test with an array of user info
        $userInfo = array(
            'user_id' => 'testuser1234',
            'auth_time' => date('Y-m-d H:i:s', strtotime('20 minutes ago')
        ));

        $server->handleAuthorizeRequest($request, $response = new Response(), true, $userInfo);

        list($header, $payload, $signature) = $this->extractTokenDataFromResponse($response);

        $this->assertTrue(is_array($payload));
        $this->assertArrayHasKey('sub', $payload);
        $this->assertEquals($userInfo['user_id'], $payload['sub']);
        $this->assertArrayHasKey('auth_time', $payload);
        $this->assertEquals($userInfo['auth_time'], $payload['auth_time']);
    }

    private function extractTokenDataFromResponse(Response $response)
    {
        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $this->assertStringNotContainsString('error', $location);

        $parts = parse_url($location);
        $this->assertArrayHasKey('fragment', $parts);
        $this->assertFalse(isset($parts['query']));

        parse_str($parts['fragment'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('id_token', $params);
        $this->assertArrayNotHasKey('access_token', $params);

        list($headb64, $payloadb64, $signature) = explode('.', $params['id_token']);

        $jwt = new Jwt();
        $header = json_decode($jwt->urlSafeB64Decode($headb64), true);
        $payload = json_decode($jwt->urlSafeB64Decode($payloadb64), true);

        return array($header, $payload, $signature);
    }

    private function validateIdToken($id_token)
    {
        $parts = explode('.', $id_token);
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
        $this->assertArrayHasKey('email', $claims);
        $this->assertArrayHasKey('email_verified', $claims);

        $this->assertEquals($claims['iss'], 'test');
        $this->assertEquals($claims['aud'], 'Test Client ID');
        $this->assertEquals($claims['nonce'], 'test');
        $this->assertEquals($claims['email'], 'testuser@test.com');
        $duration = $claims['exp'] - $claims['iat'];
        $this->assertEquals($duration, 3600);
    }

    private function getTestServer($config = array())
    {
        $config += array(
            'use_openid_connect' => true,
            'issuer' => 'test',
            'id_lifetime' => 3600,
        );

        $memoryStorage = Bootstrap::getInstance()->getMemoryStorage();
        $memoryStorage->supportedScopes[] = 'email';
        $storage = array(
            'client' => $memoryStorage,
            'scope' => $memoryStorage,
        );
        $responseTypes = array(
            'id_token' => new IdToken($memoryStorage, $memoryStorage, $config),
        );

        $server = new Server($storage, $config, array(), $responseTypes);
        $server->addGrantType(new ClientCredentials($memoryStorage));

        return $server;
    }
}
