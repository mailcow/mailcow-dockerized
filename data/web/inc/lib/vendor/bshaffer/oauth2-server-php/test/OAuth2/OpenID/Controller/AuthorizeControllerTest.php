<?php

namespace OAuth2\OpenID\Controller;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class AuthorizeControllerTest extends TestCase
{
    public function testValidateAuthorizeRequest()
    {
        $server = $this->getTestServer();

        $response = new Response();
        $request = new Request(array(
            'client_id'     => 'Test Client ID', // valid client id
            'redirect_uri'  => 'http://adobe.com', // valid redirect URI
            'response_type' => 'id_token',
            'state'         => 'af0ifjsldkj',
            'nonce'         => 'n-0S6_WzA2Mj',
        ));

        // Test valid id_token request
        $server->handleAuthorizeRequest($request, $response, true);

        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['fragment'], $query);

        $this->assertEquals('n-0S6_WzA2Mj', $server->getAuthorizeController()->getNonce());
        $this->assertEquals($query['state'], 'af0ifjsldkj');

        $this->assertArrayHasKey('id_token', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayNotHasKey('access_token', $query);
        $this->assertArrayNotHasKey('expires_in', $query);
        $this->assertArrayNotHasKey('token_type', $query);

        // Test valid token id_token request
        $request->query['response_type'] = 'id_token token';
        $server->handleAuthorizeRequest($request, $response, true);

        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['fragment'], $query);

        $this->assertEquals('n-0S6_WzA2Mj', $server->getAuthorizeController()->getNonce());
        $this->assertEquals($query['state'], 'af0ifjsldkj');

        $this->assertArrayHasKey('access_token', $query);
        $this->assertArrayHasKey('expires_in', $query);
        $this->assertArrayHasKey('token_type', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('id_token', $query);

        // assert that with multiple-valued response types, order does not matter
        $request->query['response_type'] = 'token id_token';
        $server->handleAuthorizeRequest($request, $response, true);

        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['fragment'], $query);

        $this->assertEquals('n-0S6_WzA2Mj', $server->getAuthorizeController()->getNonce());
        $this->assertEquals($query['state'], 'af0ifjsldkj');

        $this->assertArrayHasKey('access_token', $query);
        $this->assertArrayHasKey('expires_in', $query);
        $this->assertArrayHasKey('token_type', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('id_token', $query);

        // assert that with multiple-valued response types with extra spaces do not matter
        $request->query['response_type'] = ' token  id_token ';
        $server->handleAuthorizeRequest($request, $response, true);

        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['fragment'], $query);

        $this->assertEquals('n-0S6_WzA2Mj', $server->getAuthorizeController()->getNonce());
        $this->assertEquals($query['state'], 'af0ifjsldkj');

        $this->assertArrayHasKey('access_token', $query);
        $this->assertArrayHasKey('expires_in', $query);
        $this->assertArrayHasKey('token_type', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('id_token', $query);
    }

    public function testMissingNonce()
    {
        $server    = $this->getTestServer();
        $authorize = $server->getAuthorizeController();

        $response = new Response();
        $request  = new Request(array(
            'client_id'     => 'Test Client ID', // valid client id
            'redirect_uri'  => 'http://adobe.com', // valid redirect URI
            'response_type' => 'id_token',
            'state'         => 'xyz',
        ));

        // Test missing nonce for 'id_token' response type
        $server->handleAuthorizeRequest($request, $response, true);
        $params = $response->getParameters();

        $this->assertEquals($params['error'], 'invalid_nonce');
        $this->assertEquals($params['error_description'], 'This application requires you specify a nonce parameter');

        // Test missing nonce for 'id_token token' response type
        $request->query['response_type'] = 'id_token token';
        $server->handleAuthorizeRequest($request, $response, true);
        $params = $response->getParameters();

        $this->assertEquals($params['error'], 'invalid_nonce');
        $this->assertEquals($params['error_description'], 'This application requires you specify a nonce parameter');
    }

    public function testNotGrantedApplication()
    {
        $server = $this->getTestServer();

        $response = new Response();
        $request  = new Request(array(
            'client_id'     => 'Test Client ID', // valid client id
            'redirect_uri'  => 'http://adobe.com', // valid redirect URI
            'response_type' => 'id_token',
            'state'         => 'af0ifjsldkj',
            'nonce'         => 'n-0S6_WzA2Mj',
        ));

        // Test not approved application
        $server->handleAuthorizeRequest($request, $response, false);

        $params = $response->getParameters();

        $this->assertEquals($params['error'], 'consent_required');
        $this->assertEquals($params['error_description'], 'The user denied access to your application');

        // Test not approved application with prompt parameter
        $request->query['prompt'] = 'none';
        $server->handleAuthorizeRequest($request, $response, false);

        $params = $response->getParameters();

        $this->assertEquals($params['error'], 'login_required');
        $this->assertEquals($params['error_description'], 'The user must log in');

        // Test not approved application with user_id set
        $request->query['prompt'] = 'none';
        $server->handleAuthorizeRequest($request, $response, false, 'some-user-id');

        $params = $response->getParameters();

        $this->assertEquals($params['error'], 'interaction_required');
        $this->assertEquals($params['error_description'], 'The user must grant access to your application');
    }

    public function testNeedsIdToken()
    {
        $server = $this->getTestServer();
        $authorize = $server->getAuthorizeController();

        $this->assertTrue($authorize->needsIdToken('openid'));
        $this->assertTrue($authorize->needsIdToken('openid profile'));
        $this->assertFalse($authorize->needsIdToken(''));
        $this->assertFalse($authorize->needsIdToken('some-scope'));
    }

    private function getTestServer($config = array())
    {
        $config += array(
            'use_openid_connect' => true,
            'issuer'             => 'phpunit',
            'allow_implicit'     => true
        );

        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server  = new Server($storage, $config);

        return $server;
    }
}
