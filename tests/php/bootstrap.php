<?php

declare(strict_types=1);

// Production autoloader first: the tests must find the classes through the PSR-4 mapping
// of data/web/inc/lib/composer.json, exactly as php-fpm does.
require __DIR__ . '/../../data/web/inc/lib/vendor/autoload.php';
require __DIR__ . '/vendor/autoload.php';

// The php:8.2-cli test image has no intl (php-fpm has); ASCII domains are all the unit tests need
if (!function_exists('idn_to_ascii')) {
    define('INTL_IDNA_VARIANT_UTS46', 1);

    function idn_to_ascii(string $domain, int $flags = 0, int $variant = INTL_IDNA_VARIANT_UTS46): string|false
    {
        return preg_match('/^[\x21-\x7e]*$/', $domain) ? $domain : false;
    }
}
