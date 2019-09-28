<?php

namespace OAuth2\TokenType;

use OAuth2\Request\TestRequest;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class BearerTest extends TestCase
{
    public function testValidContentTypeWithCharset()
    {
        $bearer = new Bearer();
        $request = TestRequest::createPost(array(
            'access_token' => 'ThisIsMyAccessToken'
        ));
        $request->server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded; charset=UTF-8';

        $param = $bearer->getAccessTokenParameter($request, $response = new Response());
        $this->assertEquals($param, 'ThisIsMyAccessToken');
    }

    public function testInvalidContentType()
    {
        $bearer = new Bearer();
        $request = TestRequest::createPost(array(
            'access_token' => 'ThisIsMyAccessToken'
        ));
        $request->server['CONTENT_TYPE'] = 'application/json; charset=UTF-8';

        $param = $bearer->getAccessTokenParameter($request, $response = new Response());
        $this->assertNull($param);
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'The content type for POST requests must be "application/x-www-form-urlencoded"');
    }

    public function testValidRequestUsingAuthorizationHeader()
    {
        $bearer = new Bearer();
        $request = new TestRequest();
        $request->headers['AUTHORIZATION'] = 'Bearer MyToken';
        $request->server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded; charset=UTF-8';

        $param = $bearer->getAccessTokenParameter($request, $response = new Response());
        $this->assertEquals('MyToken', $param);
    }

    public function testValidRequestUsingAuthorizationHeaderCaseInsensitive()
    {
        $bearer = new Bearer();
        $request = new TestRequest();
        $request->server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        $request->headers['Authorization'] = 'Bearer MyToken';

        $param = $bearer->getAccessTokenParameter($request, $response = new Response());
        $this->assertEquals('MyToken', $param);
    }
}
