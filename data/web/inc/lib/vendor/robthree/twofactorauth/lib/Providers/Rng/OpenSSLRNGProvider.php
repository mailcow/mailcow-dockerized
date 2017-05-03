<?php

namespace RobThree\Auth\Providers\Rng;

class OpenSSLRNGProvider implements IRNGProvider
{
    private $requirestrong;
    
    function __construct($requirestrong = true) {
        $this->requirestrong = $requirestrong;
    }
    
    public function getRandomBytes($bytecount) {
        $result = openssl_random_pseudo_bytes($bytecount, $crypto_strong);
        if ($this->requirestrong && ($crypto_strong === false))
            throw new \RNGException('openssl_random_pseudo_bytes returned non-cryptographically strong value');
        if ($result === false)
            throw new \RNGException('openssl_random_pseudo_bytes returned an invalid value');
        return $result;
    }
    
    public function isCryptographicallySecure() {
        return $this->requirestrong;
    }
}