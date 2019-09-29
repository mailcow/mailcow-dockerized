<?php

namespace OAuth2\Storage;

class UserCredentialsTest extends BaseTest
{
    /** @dataProvider provideStorage */
    public function testCheckUserCredentials(UserCredentialsInterface $storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        // create a new user for testing
        $success = $storage->setUser('testusername', 'testpass', 'Test', 'User');
        $this->assertTrue($success);

        // correct credentials
        $this->assertTrue($storage->checkUserCredentials('testusername', 'testpass'));
        // invalid password
        $this->assertFalse($storage->checkUserCredentials('testusername', 'fakepass'));
        // invalid username
        $this->assertFalse($storage->checkUserCredentials('fakeusername', 'testpass'));

        // invalid username
        $this->assertFalse($storage->getUserDetails('fakeusername'));

        // ensure all properties are set
        $user = $storage->getUserDetails('testusername');
        $this->assertTrue($user !== false);
        $this->assertArrayHasKey('user_id', $user);
        $this->assertArrayHasKey('first_name', $user);
        $this->assertArrayHasKey('last_name', $user);
        $this->assertEquals($user['user_id'], 'testusername');
        $this->assertEquals($user['first_name'], 'Test');
        $this->assertEquals($user['last_name'], 'User');
    }
}
