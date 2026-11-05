<?php

namespace OAuth2\Storage;

use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

class PdoTest extends BaseTest
{
    use ExpectPHPException;

    public function testCreatePdoStorageUsingPdoClass()
    {
        $dsn = sprintf('sqlite:%s', Bootstrap::getInstance()->getSqliteDir());
        $pdo = new \PDO($dsn);
        $storage = new Pdo($pdo);

        $this->assertNotNull($storage->getClientDetails('oauth_test_client'));
    }

    public function testCreatePdoStorageUsingDSN()
    {
        $dsn = sprintf('sqlite:%s', Bootstrap::getInstance()->getSqliteDir());
        $storage = new Pdo($dsn);

        $this->assertNotNull($storage->getClientDetails('oauth_test_client'));
    }

    public function testCreatePdoStorageUsingConfig()
    {
        $dsn = sprintf('sqlite:%s', Bootstrap::getInstance()->getSqliteDir());
        $config = array('dsn' => $dsn);
        $storage = new Pdo($config);

        $this->assertNotNull($storage->getClientDetails('oauth_test_client'));
    }

    public function testCreatePdoStorageWithoutDSNThrowsException()
    {
        $this->expectErrorMessage('dsn');
        $config = array('username' => 'brent', 'password' => 'brentisaballer');
        $storage = new Pdo($config);
    }
}
