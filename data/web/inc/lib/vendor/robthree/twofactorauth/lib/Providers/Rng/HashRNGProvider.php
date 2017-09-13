<?php
namespace RobThree\Auth\Providers\Rng;

class HashRNGProvider implements IRNGProvider
{
    private $algorithm;
    
    function __construct($algorithm = 'sha256' ) {
        $algos = array_values(hash_algos());
        if (!in_array($algorithm, $algos, true))
            throw new \RNGException('Unsupported algorithm specified');
        $this->algorithm = $algorithm;
    }
    
    public function getRandomBytes($bytecount) {
        $result = '';
        $hash = mt_rand();
        for ($i = 0; $i < $bytecount; $i++) {
            $hash = hash($this->algorithm, $hash.mt_rand(), true);
            $result .= $hash[mt_rand(0, sizeof($hash))];
        }
        return $result;
    }
    
    public function isCryptographicallySecure() {
        return false;
    }
}
