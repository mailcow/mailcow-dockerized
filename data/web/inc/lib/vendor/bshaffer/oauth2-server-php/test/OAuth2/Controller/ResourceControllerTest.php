<?php

namespace OAuth2\Controller;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\GrantType\AuthorizationCode;
use OAuth2\Request;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class ResourceControllerTest extends TestCase
{
    public function testNoAccessToken()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertNull($response->getParameter('error'));
        $this->assertNull($response->getParameter('error_description'));
        $this->assertEquals('', $response->getResponseBody());
    }

    public function testMalformedHeader()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'tH1s i5 B0gU5';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'Malformed auth header');
    }

    public function testMultipleTokensSubmitted()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->request['access_token'] = 'TEST';
        $request->query['access_token'] = 'TEST';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'Only one method may be used to authenticate at a time (Auth header, GET or POST)');
    }

    public function testInvalidRequestMethod()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->server['REQUEST_METHOD'] = 'GET';
        $request->request['access_token'] = 'TEST';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'When putting the token in the body, the method must be POST or PUT');
    }

    public function testInvalidContentType()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->server['REQUEST_METHOD'] = 'POST';
        $request->server['CONTENT_TYPE'] = 'application/json';
        $request->request['access_token'] = 'TEST';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'The content type for POST requests must be "application/x-www-form-urlencoded"');
    }

    public function testInvalidToken()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'Bearer TESTTOKEN';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'invalid_token');
        $this->assertEquals($response->getParameter('error_description'), 'The access token provided is invalid');
    }

    public function testExpiredToken()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'Bearer accesstoken-expired';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'invalid_token');
        $this->assertEquals($response->getParameter('error_description'), 'The access token provided has expired');
    }

    public function testOutOfScopeToken()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'Bearer accesstoken-scope';
        $scope = 'outofscope';
        $allow = $server->verifyResourceRequest($request, $response = new Response(), $scope);
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 403);
        $this->assertEquals($response->getParameter('error'), 'insufficient_scope');
        $this->assertEquals($response->getParameter('error_description'), 'The request requires higher privileges than provided by the access token');

        // verify the "scope" has been set in the "WWW-Authenticate" header
        preg_match('/scope="(.*?)"/', $response->getHttpHeader('WWW-Authenticate'), $matches);
        $this->assertEquals(2, count($matches));
        $this->assertEquals($matches[1], 'outofscope');
    }

    public function testMalformedToken()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'Bearer accesstoken-malformed';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertFalse($allow);

        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'malformed_token');
        $this->assertEquals($response->getParameter('error_description'), 'Malformed token (missing "expires")');
    }

    public function testValidToken()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'Bearer accesstoken-scope';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertTrue($allow);
    }

    public function testValidTokenWithScopeParam()
    {
        $server = $this->getTestServer();
        $request = Request::createFromGlobals();
        $request->headers['AUTHORIZATION'] = 'Bearer accesstoken-scope';
        $request->query['scope'] = 'testscope';
        $allow = $server->verifyResourceRequest($request, $response = new Response());
        $this->assertTrue($allow);
    }

    public function testCreateController()
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $tokenType = new \OAuth2\TokenType\Bearer();
        $controller = new ResourceController($tokenType, $storage);
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
