<?php

namespace OAuth2\ResponseType;

use OAuth2\Server;
use OAuth2\Storage\Memory;
use PHPUnit\Framework\TestCase;

class AccessTokenTest extends TestCase
{
    public function testRevokeAccessTokenWithTypeHint()
    {
        $tokenStorage = new Memory(array(
            'access_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getAccessToken('revoke'));
        $accessToken = new AccessToken($tokenStorage);
        $accessToken->revokeToken('revoke', 'access_token');
        $this->assertFalse($tokenStorage->getAccessToken('revoke'));
    }

    public function testRevokeAccessTokenWithoutTypeHint()
    {
        $tokenStorage = new Memory(array(
            'access_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getAccessToken('revoke'));
        $accessToken = new AccessToken($tokenStorage);
        $accessToken->revokeToken('revoke');
        $this->assertFalse($tokenStorage->getAccessToken('revoke'));
    }

    public function testRevokeRefreshTokenWithTypeHint()
    {
        $tokenStorage = new Memory(array(
            'refresh_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getRefreshToken('revoke'));
        $accessToken = new AccessToken(new Memory, $tokenStorage);
        $accessToken->revokeToken('revoke', 'refresh_token');
        $this->assertFalse($tokenStorage->getRefreshToken('revoke'));
    }

    public function testRevokeRefreshTokenWithoutTypeHint()
    {
        $tokenStorage = new Memory(array(
            'refresh_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getRefreshToken('revoke'));
        $accessToken = new AccessToken(new Memory, $tokenStorage);
        $accessToken->revokeToken('revoke');
        $this->assertFalse($tokenStorage->getRefreshToken('revoke'));
    }

    public function testRevokeAccessTokenWithRefreshTokenTypeHint()
    {
        $tokenStorage = new Memory(array(
            'access_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getAccessToken('revoke'));
        $accessToken = new AccessToken($tokenStorage);
        $accessToken->revokeToken('revoke', 'refresh_token');
        $this->assertFalse($tokenStorage->getAccessToken('revoke'));
    }

    public function testRevokeAccessTokenWithBogusTypeHint()
    {
        $tokenStorage = new Memory(array(
            'access_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getAccessToken('revoke'));
        $accessToken = new AccessToken($tokenStorage);
        $accessToken->revokeToken('revoke', 'foo');
        $this->assertFalse($tokenStorage->getAccessToken('revoke'));
    }

    public function testRevokeRefreshTokenWithBogusTypeHint()
    {
        $tokenStorage = new Memory(array(
            'refresh_tokens' => array(
                'revoke' => array('mytoken'),
            ),
        ));

        $this->assertEquals(array('mytoken'), $tokenStorage->getRefreshToken('revoke'));
        $accessToken = new AccessToken(new Memory, $tokenStorage);
        $accessToken->revokeToken('revoke', 'foo');
        $this->assertFalse($tokenStorage->getRefreshToken('revoke'));
    }
}
