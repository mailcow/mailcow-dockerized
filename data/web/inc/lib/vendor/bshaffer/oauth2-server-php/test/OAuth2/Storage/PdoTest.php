<?php

namespace OAuth2\Storage;

class PdoTest extends BaseTest
{
    public function testCreatePdoStorageUsingPdoClass()
    {
        $pdo = new \PDO(sprintf('sqlite://%s', Bootstrap::getInstance()->getSqliteDir()));
        $storage = new Pdo($pdo);

        $this->assertNotNull($storage->getClientDetails('oauth_test_client'));
    }

    public function testCreatePdoStorageUsingDSN()
    {
        $dsn = sprintf('sqlite://%s', Bootstrap::getInstance()->getSqliteDir());
        $storage = new Pdo($dsn);

        $this->assertNotNull($storage->getClientDetails('oauth_test_client'));
    }

    public function testCreatePdoStorageUsingConfig()
    {
        $config = array('dsn' => sprintf('sqlite://%s', Bootstrap::getInstance()->getSqliteDir()));
        $storage = new Pdo($config);

        $this->assertNotNull($storage->getClientDetails('oauth_test_client'));
    }

    /**
     * @expectedException InvalidArgumentException dsn
     */
    public function testCreatePdoStorageWithoutDSNThrowsException()
    {
        $config = array('username' => 'brent', 'password' => 'brentisaballer');
        $storage = new Pdo($config);
    }
}
