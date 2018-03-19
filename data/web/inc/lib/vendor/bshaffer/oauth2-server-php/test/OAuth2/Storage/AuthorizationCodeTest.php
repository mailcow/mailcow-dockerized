<?php

namespace OAuth2\Storage;

class AuthorizationCodeTest extends BaseTest
{
    /** @dataProvider provideStorage */
    public function testGetAuthorizationCode(AuthorizationCodeInterface $storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        // nonexistant client_id
        $details = $storage->getAuthorizationCode('faketoken');
        $this->assertFalse($details);

        // valid client_id
        $details = $storage->getAuthorizationCode('testtoken');
        $this->assertNotNull($details);
    }

    /** @dataProvider provideStorage */
    public function testSetAuthorizationCode(AuthorizationCodeInterface $storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        // assert code we are about to add does not exist
        $code = $storage->getAuthorizationCode('newcode');
        $this->assertFalse($code);

        // add new code
        $expires = time() + 20;
        $success = $storage->setAuthorizationCode('newcode', 'client ID', 'SOMEUSERID', 'http://example.com', $expires);
        $this->assertTrue($success);

        $code = $storage->getAuthorizationCode('newcode');
        $this->assertNotNull($code);
        $this->assertArrayHasKey('authorization_code', $code);
        $this->assertArrayHasKey('client_id', $code);
        $this->assertArrayHasKey('user_id', $code);
        $this->assertArrayHasKey('redirect_uri', $code);
        $this->assertArrayHasKey('expires', $code);
        $this->assertEquals($code['authorization_code'], 'newcode');
        $this->assertEquals($code['client_id'], 'client ID');
        $this->assertEquals($code['user_id'], 'SOMEUSERID');
        $this->assertEquals($code['redirect_uri'], 'http://example.com');
        $this->assertEquals($code['expires'], $expires);

        // change existing code
        $expires = time() + 42;
        $success = $storage->setAuthorizationCode('newcode', 'client ID2', 'SOMEOTHERID', 'http://example.org', $expires);
        $this->assertTrue($success);

        $code = $storage->getAuthorizationCode('newcode');
        $this->assertNotNull($code);
        $this->assertArrayHasKey('authorization_code', $code);
        $this->assertArrayHasKey('client_id', $code);
        $this->assertArrayHasKey('user_id', $code);
        $this->assertArrayHasKey('redirect_uri', $code);
        $this->assertArrayHasKey('expires', $code);
        $this->assertEquals($code['authorization_code'], 'newcode');
        $this->assertEquals($code['client_id'], 'client ID2');
        $this->assertEquals($code['user_id'], 'SOMEOTHERID');
        $this->assertEquals($code['redirect_uri'], 'http://example.org');
        $this->assertEquals($code['expires'], $expires);

        // add new code with scope having an empty string value
        $expires = time() + 20;
        $success = $storage->setAuthorizationCode('newcode', 'client ID', 'SOMEUSERID', 'http://example.com', $expires, '');
        $this->assertTrue($success);
    }

        /** @dataProvider provideStorage */
    public function testExpireAccessToken(AccessTokenInterface $storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        // create a valid code
        $expires = time() + 20;
        $success = $storage->setAuthorizationCode('code-to-expire', 'client ID', 'SOMEUSERID', 'http://example.com', time() + 20);
        $this->assertTrue($success);

        // verify the new code exists
        $code = $storage->getAuthorizationCode('code-to-expire');
        $this->assertNotNull($code);

        $this->assertArrayHasKey('authorization_code', $code);
        $this->assertEquals($code['authorization_code'], 'code-to-expire');

        // now expire the code and ensure it's no longer available
        $storage->expireAuthorizationCode('code-to-expire');
        $code = $storage->getAuthorizationCode('code-to-expire');
        $this->assertFalse($code);
    }
}
