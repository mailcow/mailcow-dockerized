<?php

namespace OAuth2\GrantType;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\Request\TestRequest;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class RefreshTokenTest extends TestCase
{
    private $storage;

    public function testNoRefreshToken()
    {
        $server = $this->getTestServer();
        $server->addGrantType(new RefreshToken($this->storage));

        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token',  // valid grant type
            'client_id'  => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret',  // valid client secret
        ));
        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'Missing parameter: "refresh_token" is required');
    }

    public function testInvalidRefreshToken()
    {
        $server = $this->getTestServer();
        $server->addGrantType(new RefreshToken($this->storage));

        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'fake-token', // invalid refresh token
        ));
        $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Invalid refresh token');
    }

    public function testValidRefreshTokenWithNewRefreshTokenInResponse()
    {
        $server = $this->getTestServer();
        $server->addGrantType(new RefreshToken($this->storage, array('always_issue_new_refresh_token' => true)));

        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken', // valid refresh token
        ));
        $token = $server->grantAccessToken($request, new Response());
        $this->assertTrue(isset($token['refresh_token']), 'refresh token should always refresh');

        $refresh_token = $this->storage->getRefreshToken($token['refresh_token']);
        $this->assertNotNull($refresh_token);
        $this->assertEquals($refresh_token['refresh_token'], $token['refresh_token']);
        $this->assertEquals($refresh_token['client_id'], $request->request('client_id'));
        $this->assertTrue($token['refresh_token'] != 'test-refreshtoken', 'the refresh token returned is not the one used');
        $used_token = $this->storage->getRefreshToken('test-refreshtoken');
        $this->assertFalse($used_token, 'the refresh token used is no longer valid');
    }

    public function testValidRefreshTokenDoesNotUnsetToken()
    {
        $server = $this->getTestServer();
        $server->addGrantType(new RefreshToken($this->storage, array(
            'always_issue_new_refresh_token' => true,
            'unset_refresh_token_after_use'  => false,
        )));

        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken', // valid refresh token
        ));
        $token = $server->grantAccessToken($request, new Response());
        $this->assertTrue(isset($token['refresh_token']), 'refresh token should always refresh');

        $used_token = $this->storage->getRefreshToken('test-refreshtoken');
        $this->assertNotNull($used_token, 'the refresh token used is still valid');
    }

    public function testValidRefreshTokenWithNoRefreshTokenInResponse()
    {
        $server = $this->getTestServer();
        $server->addGrantType(new RefreshToken($this->storage, array('always_issue_new_refresh_token' => false)));

        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken', // valid refresh token
        ));
        $token = $server->grantAccessToken($request, new Response());
        $this->assertFalse(isset($token['refresh_token']), 'refresh token should not be returned');

        $used_token = $this->storage->getRefreshToken('test-refreshtoken');
        $this->assertNotNull($used_token, 'the refresh token used is still valid');
    }

    public function testValidRefreshTokenSameScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken-with-scope', // valid refresh token (with scope)
            'scope'         => 'scope2 scope1',
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertArrayHasKey('scope', $token);
        $this->assertEquals($token['scope'], 'scope2 scope1');
    }

    public function testValidRefreshTokenLessScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken-with-scope', // valid refresh token (with scope)
            'scope'         => 'scope1',
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertArrayHasKey('scope', $token);
        $this->assertEquals($token['scope'], 'scope1');
    }

    public function testValidRefreshTokenDifferentScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken-with-scope', // valid refresh token (with scope)
            'scope'         => 'scope3',
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_scope');
        $this->assertEquals($response->getParameter('error_description'), 'The scope requested is invalid for this request');
    }

    public function testValidRefreshTokenInvalidScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'refresh_token', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'refresh_token' => 'test-refreshtoken-with-scope', // valid refresh token (with scope)
            'scope'         => 'invalid-scope',
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_scope');
        $this->assertEquals($response->getParameter('error_description'), 'The scope requested is invalid for this request');
    }

    public function testValidClientDifferentRefreshToken()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type'    => 'refresh_token', // valid grant type
            'client_id'     => 'Test Some Other Client', // valid client id
            'client_secret' => 'TestSecret3', // valid client secret
            'refresh_token' => 'test-refreshtoken', // valid refresh token
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'refresh_token doesn\'t exist or is invalid for the client');
    }

    private function getTestServer()
    {
        $this->storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server($this->storage);

        return $server;
    }
}
