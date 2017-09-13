<?php

namespace RobThree\Auth\Providers\Rng;

class CSRNGProvider implements IRNGProvider
{
    public function getRandomBytes($bytecount) {
        return random_bytes($bytecount);    // PHP7+
    }
    
    public function isCryptographicallySecure() {
        return true;
    }
}