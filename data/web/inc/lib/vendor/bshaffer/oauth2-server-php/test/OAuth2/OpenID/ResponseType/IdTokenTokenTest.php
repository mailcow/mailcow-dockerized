<?php

namespace OAuth2\OpenID\ResponseType;

use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Storage\Bootstrap;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\ResponseType\AccessToken;
use PHPUnit\Framework\TestCase;

class IdTokenTokenTest extends TestCase
{

    public function testHandleAuthorizeRequest()
    {
        // add the test parameters in memory
        $server = $this->getTestServer(array('allow_implicit' => true));

        $request = new Request(array(
            'response_type' => 'id_token token',
            'redirect_uri'  => 'http://adobe.com',
            'client_id'     => 'Test Client ID',
            'scope'         => 'openid',
            'state'         => 'test',
            'nonce'         => 'test',
        ));

        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $this->assertNotContains('error', $location);

        $parts = parse_url($location);
        $this->assertArrayHasKey('fragment', $parts);
        $this->assertFalse(isset($parts['query']));

        // assert fragment is in "application/x-www-form-urlencoded" format
        parse_str($parts['fragment'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('id_token', $params);
        $this->assertArrayHasKey('access_token', $params);

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
        $this->assertArrayHasKey('at_hash', $claims);

        $this->assertEquals($claims['iss'], 'test');
        $this->assertEquals($claims['aud'], 'Test Client ID');
        $this->assertEquals($claims['nonce'], 'test');
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
        $responseTypes = array(
            'token'     => $token   = new AccessToken($memoryStorage, $memoryStorage),
            'id_token'  => $idToken = new IdToken($memoryStorage, $memoryStorage, $config),
            'id_token token' => new IdTokenToken($token, $idToken),
        );

        $server = new Server($memoryStorage, $config, array(), $responseTypes);
        $server->addGrantType(new ClientCredentials($memoryStorage));

        return $server;
    }
}
