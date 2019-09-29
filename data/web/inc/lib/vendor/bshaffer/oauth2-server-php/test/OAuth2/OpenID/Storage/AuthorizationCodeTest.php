<?php

namespace OAuth2\OpenID\Storage;

use OAuth2\Storage\BaseTest;
use OAuth2\Storage\NullStorage;

class AuthorizationCodeTest extends BaseTest
{
    /** @dataProvider provideStorage */
    public function testCreateAuthorizationCode($storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        if (!$storage instanceof AuthorizationCodeInterface) {
            return;
        }

        // assert code we are about to add does not exist
        $code = $storage->getAuthorizationCode('new-openid-code');
        $this->assertFalse($code);

        // add new code
        $expires = time() + 20;
        $scope = null;
        $id_token = 'fake_id_token';
        $success = $storage->setAuthorizationCode('new-openid-code', 'client ID', 'SOMEUSERID', 'http://example.com', $expires, $scope, $id_token);
        $this->assertTrue($success);

        $code = $storage->getAuthorizationCode('new-openid-code');
        $this->assertNotNull($code);
        $this->assertArrayHasKey('authorization_code', $code);
        $this->assertArrayHasKey('client_id', $code);
        $this->assertArrayHasKey('user_id', $code);
        $this->assertArrayHasKey('redirect_uri', $code);
        $this->assertArrayHasKey('expires', $code);
        $this->assertEquals($code['authorization_code'], 'new-openid-code');
        $this->assertEquals($code['client_id'], 'client ID');
        $this->assertEquals($code['user_id'], 'SOMEUSERID');
        $this->assertEquals($code['redirect_uri'], 'http://example.com');
        $this->assertEquals($code['expires'], $expires);
        $this->assertEquals($code['id_token'], $id_token);

        // change existing code
        $expires = time() + 42;
        $new_id_token = 'fake_id_token-2';
        $success = $storage->setAuthorizationCode('new-openid-code', 'client ID2', 'SOMEOTHERID', 'http://example.org', $expires, $scope, $new_id_token);
        $this->assertTrue($success);

        $code = $storage->getAuthorizationCode('new-openid-code');
        $this->assertNotNull($code);
        $this->assertArrayHasKey('authorization_code', $code);
        $this->assertArrayHasKey('client_id', $code);
        $this->assertArrayHasKey('user_id', $code);
        $this->assertArrayHasKey('redirect_uri', $code);
        $this->assertArrayHasKey('expires', $code);
        $this->assertEquals($code['authorization_code'], 'new-openid-code');
        $this->assertEquals($code['client_id'], 'client ID2');
        $this->assertEquals($code['user_id'], 'SOMEOTHERID');
        $this->assertEquals($code['redirect_uri'], 'http://example.org');
        $this->assertEquals($code['expires'], $expires);
        $this->assertEquals($code['id_token'], $new_id_token);
    }

        /** @dataProvider provideStorage */
    public function testRemoveIdTokenFromAuthorizationCode($storage)
    {
        // add new code
        $expires = time() + 20;
        $scope = null;
        $id_token = 'fake_id_token_to_remove';
        $authcode = 'new-openid-code-'.rand();
        $success = $storage->setAuthorizationCode($authcode, 'client ID', 'SOMEUSERID', 'http://example.com', $expires, $scope, $id_token);
        $this->assertTrue($success);

        // verify params were set
        $code = $storage->getAuthorizationCode($authcode);
        $this->assertNotNull($code);
        $this->assertArrayHasKey('id_token', $code);
        $this->assertEquals($code['id_token'], $id_token);

        // remove the id_token
        $success = $storage->setAuthorizationCode($authcode, 'client ID', 'SOMEUSERID', 'http://example.com', $expires, $scope, null);

        // verify the "id_token" is now null
        $code = $storage->getAuthorizationCode($authcode);
        $this->assertNotNull($code);
        $this->assertArrayHasKey('id_token', $code);
        $this->assertEquals($code['id_token'], null);
    }
}
