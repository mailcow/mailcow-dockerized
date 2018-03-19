<?php

namespace OAuth2\GrantType;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class ImplicitTest extends TestCase
{
    public function testImplicitNotAllowedResponse()
    {
        $server = $this->getTestServer();
        $request = new Request(array(
            'client_id' => 'Test Client ID', // valid client id
            'redirect_uri' => 'http://adobe.com', // valid redirect URI
            'response_type' => 'token', // invalid response type
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), false);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $parts = parse_url($location);
        parse_str($parts['query'], $query);

        $this->assertEquals($query['error'], 'unsupported_response_type');
        $this->assertEquals($query['error_description'], 'implicit grant type not supported');
    }

    public function testUserDeniesAccessResponse()
    {
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request(array(
            'client_id' => 'Test Client ID', // valid client id
            'redirect_uri' => 'http://adobe.com', // valid redirect URI
            'response_type' => 'token', // valid response type
            'state' => 'xyz',
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), false);

        $this->assertEquals($response->getStatusCode(), 302);
        $location = $response->getHttpHeader('Location');
        $parts = parse_url($location);
        parse_str($parts['query'], $query);

        $this->assertEquals($query['error'], 'access_denied');
        $this->assertEquals($query['error_description'], 'The user denied access to your application');
    }

    public function testSuccessfulRequestFragmentParameter()
    {
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request(array(
            'client_id' => 'Test Client ID', // valid client id
            'redirect_uri' => 'http://adobe.com', // valid redirect URI
            'response_type' => 'token', // valid response type
            'state' => 'xyz',
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        $this->assertEquals($response->getStatusCode(), 302);
        $this->assertNull($response->getParameter('error'));
        $this->assertNull($response->getParameter('error_description'));

        $location = $response->getHttpHeader('Location');
        $parts = parse_url($location);

        $this->assertEquals('http', $parts['scheme']); // same as passed in to redirect_uri
        $this->assertEquals('adobe.com', $parts['host']); // same as passed in to redirect_uri
        $this->assertArrayHasKey('fragment', $parts);
        $this->assertFalse(isset($parts['query']));

        // assert fragment is in "application/x-www-form-urlencoded" format
        parse_str($parts['fragment'], $params);
        $this->assertNotNull($params);
        $this->assertArrayHasKey('access_token', $params);
        $this->assertArrayHasKey('expires_in', $params);
        $this->assertArrayHasKey('token_type', $params);
    }

    public function testSuccessfulRequestReturnsStateParameter()
    {
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request(array(
            'client_id' => 'Test Client ID', // valid client id
            'redirect_uri' => 'http://adobe.com', // valid redirect URI
            'response_type' => 'token', // valid response type
            'state' => 'test', // valid state string (just needs to be passed back to us)
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        $this->assertEquals($response->getStatusCode(), 302);
        $this->assertNull($response->getParameter('error'));
        $this->assertNull($response->getParameter('error_description'));

        $location = $response->getHttpHeader('Location');
        $parts = parse_url($location);
        $this->assertArrayHasKey('fragment', $parts);
        parse_str($parts['fragment'], $params);

        $this->assertArrayHasKey('state', $params);
        $this->assertEquals($params['state'], 'test');
    }

    public function testSuccessfulRequestStripsExtraParameters()
    {
        $server = $this->getTestServer(array('allow_implicit' => true));
        $request = new Request(array(
            'client_id' => 'Test Client ID', // valid client id
            'redirect_uri' => 'http://adobe.com?fake=something', // valid redirect URI
            'response_type' => 'token', // valid response type
            'state' => 'test', // valid state string (just needs to be passed back to us)
            'fake' => 'something', // add extra param to querystring
        ));
        $server->handleAuthorizeRequest($request, $response = new Response(), true);

        $this->assertEquals($response->getStatusCode(), 302);
        $this->assertNull($response->getParameter('error'));
        $this->assertNull($response->getParameter('error_description'));

        $location = $response->getHttpHeader('Location');
        $parts = parse_url($location);
        $this->assertFalse(isset($parts['fake']));
        $this->assertArrayHasKey('fragment', $parts);
        parse_str($parts['fragment'], $params);

        $this->assertFalse(isset($params['fake']));
        $this->assertArrayHasKey('state', $params);
        $this->assertEquals($params['state'], 'test');
    }

    private function getTestServer($config = array())
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server($storage, $config);

        // Add the two types supported for authorization grant
        $server->addGrantType(new AuthorizationCode($storage));

        return $server;
    }
}
