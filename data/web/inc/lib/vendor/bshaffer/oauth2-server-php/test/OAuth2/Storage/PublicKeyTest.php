<?php

namespace OAuth2\Storage;

class PublicKeyTest extends BaseTest
{
    /** @dataProvider provideStorage */
    public function testSetAccessToken($storage)
    {
        if ($storage instanceof NullStorage) {
            $this->markTestSkipped('Skipped Storage: ' . $storage->getMessage());

            return;
        }

        if (!$storage instanceof PublicKeyInterface) {
            // incompatible storage
            return;
        }

        $configDir = Bootstrap::getInstance()->getConfigDir();
        $globalPublicKey  = file_get_contents($configDir.'/keys/id_rsa.pub');
        $globalPrivateKey = file_get_contents($configDir.'/keys/id_rsa');

        /* assert values from storage */
        $this->assertEquals($storage->getPublicKey(), $globalPublicKey);
        $this->assertEquals($storage->getPrivateKey(), $globalPrivateKey);
    }
}
