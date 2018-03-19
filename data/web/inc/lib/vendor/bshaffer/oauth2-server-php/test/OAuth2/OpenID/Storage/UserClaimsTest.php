<?php

namespace OAuth2\OpenID\Storage;

use OAuth2\Storage\BaseTest;
use OAuth2\Storage\NullStorage;

class UserClaimsTest extends BaseTest
{
    /** @dataProvider provideStorage */
    public function testGetUserClaims($storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        if (!$storage instanceof UserClaimsInterface) {
            // incompatible storage
            return;
        }

        // invalid user
        $claims = $storage->getUserClaims('fake-user', '');
        $this->assertFalse($claims);

        // valid user (no scope)
        $claims = $storage->getUserClaims('testuser', '');

        /* assert the decoded token is the same */
        $this->assertFalse(isset($claims['email']));

        // valid user
        $claims = $storage->getUserClaims('testuser', 'email');

        /* assert the decoded token is the same */
        $this->assertEquals($claims['email'], "testuser@test.com");
        $this->assertEquals($claims['email_verified'], true);
    }
}
