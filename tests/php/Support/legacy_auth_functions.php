<?php

// Stand-in for verify_hash() of data/web/inc/functions.inc.php, used by the Auth tests:
// app passwords are stored as {BLF-CRYPT} (bcrypt), the default scheme of hash_password().

if (!function_exists('verify_hash')) {
    function verify_hash($hash, $password)
    {
        // a missing row gives null, like verify_hash() it is then no match
        $hash = (string)$hash;
        return str_starts_with($hash, '{BLF-CRYPT}') && password_verify((string)$password, substr($hash, strlen('{BLF-CRYPT}')));
    }
}
