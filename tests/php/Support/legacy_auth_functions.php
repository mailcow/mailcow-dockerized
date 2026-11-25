<?php

// Stand-in for verify_hash() of data/web/inc/functions.inc.php, used by AppPasswordLoginTest only:
// app passwords are stored as {BLF-CRYPT} (bcrypt), the default scheme of hash_password().

if (!function_exists('verify_hash')) {
    function verify_hash($hash, $password)
    {
        return str_starts_with($hash, '{BLF-CRYPT}') && password_verify($password, substr($hash, strlen('{BLF-CRYPT}')));
    }
}
