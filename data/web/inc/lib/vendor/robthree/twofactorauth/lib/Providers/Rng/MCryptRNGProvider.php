<?php

namespace RobThree\Auth\Providers\Rng;

class MCryptRNGProvider implements IRNGProvider
{
    private $source;
    
    function __construct($source = MCRYPT_DEV_URANDOM) {
        $this->source = $source;
    }
    
    public function getRandomBytes($bytecount) {
        $result = @mcrypt_create_iv($bytecount, $this->source);
        if ($result === false)
            throw new \RNGException('mcrypt_create_iv returned an invalid value');
        return $result;
    }
    
    public function isCryptographicallySecure() {
        return true;
    }
}