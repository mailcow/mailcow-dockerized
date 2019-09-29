<?php

namespace OAuth2\GrantType;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\Request\TestRequest;
use OAuth2\Request;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class ClientCredentialsTest extends TestCase
{
    public function testInvalidCredentials()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'client_credentials', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'FakeSecret', // valid client secret
        ));
        $server->handleTokenRequest($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_client');
        $this->assertEquals($response->getParameter('error_description'), 'The client credentials are invalid');
    }

    public function testValidCredentials()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'client_credentials', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('scope', $token);
        $this->assertNull($token['scope']);
    }

    public function testValidCredentialsWithScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'client_credentials', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'scope' => 'scope1',
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertArrayHasKey('scope', $token);
        $this->assertEquals($token['scope'], 'scope1');
    }

    public function testValidCredentialsInvalidScope()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'client_credentials', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'scope' => 'invalid-scope',
        ));
        $token = $server->grantAccessToken($request, $response = new Response());

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_scope');
        $this->assertEquals($response->getParameter('error_description'), 'An unsupported scope was requested');
    }

    public function testValidCredentialsInHeader()
    {
        // create with HTTP_AUTHORIZATION in header
        $server = $this->getTestServer();
        $headers = array('HTTP_AUTHORIZATION' => 'Basic '.base64_encode('Test Client ID:TestSecret'), 'REQUEST_METHOD' => 'POST');
        $params  = array('grant_type' => 'client_credentials');
        $request = new Request(array(), $params, array(), array(), array(), $headers);
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertNotNull($token['access_token']);

        // create using PHP Authorization Globals
        $headers = array('PHP_AUTH_USER' => 'Test Client ID', 'PHP_AUTH_PW' => 'TestSecret', 'REQUEST_METHOD' => 'POST');
        $params  = array('grant_type' => 'client_credentials');
        $request = new Request(array(), $params, array(), array(), array(), $headers);
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertNotNull($token['access_token']);
    }

    public function testValidCredentialsInRequest()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'client_credentials', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertNotNull($token['access_token']);
    }

    public function testValidCredentialsInQuerystring()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type' => 'client_credentials', // valid grant type
            'client_id' => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);
        $this->assertNotNull($token['access_token']);
    }

    public function testClientUserIdIsSetInAccessToken()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type'    => 'client_credentials', // valid grant type
            'client_id'     => 'Client ID With User ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('access_token', $token);

        // verify the user_id was associated with the token
        $storage = $server->getStorage('client');
        $token = $storage->getAccessToken($token['access_token']);
        $this->assertNotNull($token);
        $this->assertArrayHasKey('user_id', $token);
        $this->assertEquals($token['user_id'], 'brent@brentertainment.com');
    }

    private function getTestServer()
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server($storage);
        $server->addGrantType(new ClientCredentials($storage));

        return $server;
    }
}
