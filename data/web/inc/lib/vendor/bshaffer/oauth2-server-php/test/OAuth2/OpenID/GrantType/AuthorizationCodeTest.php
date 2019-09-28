<?php

namespace OAuth2\OpenID\GrantType;

use OAuth2\Storage\Bootstrap;
use OAuth2\Server;
use OAuth2\Request\TestRequest;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

class AuthorizationCodeTest extends TestCase
{
    public function testValidCode()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type'    => 'authorization_code', // valid grant type
            'client_id'     => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'code'          => 'testcode-openid', // valid code
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('id_token', $token);
        $this->assertEquals('test_id_token', $token['id_token']);

        // this is only true if "offline_access" was requested
        $this->assertFalse(isset($token['refresh_token']));
    }

    public function testOfflineAccess()
    {
        $server = $this->getTestServer();
        $request = TestRequest::createPost(array(
            'grant_type'    => 'authorization_code', // valid grant type
            'client_id'     => 'Test Client ID', // valid client id
            'client_secret' => 'TestSecret', // valid client secret
            'code'          => 'testcode-openid', // valid code
            'scope'         => 'offline_access', // valid code
        ));
        $token = $server->grantAccessToken($request, new Response());

        $this->assertNotNull($token);
        $this->assertArrayHasKey('id_token', $token);
        $this->assertEquals('test_id_token', $token['id_token']);
        $this->assertTrue(isset($token['refresh_token']));
    }

    private function getTestServer()
    {
        $storage = Bootstrap::getInstance()->getMemoryStorage();
        $server = new Server($storage, array('use_openid_connect' => true));
        $server->addGrantType(new AuthorizationCode($storage));

        return $server;
    }
}
