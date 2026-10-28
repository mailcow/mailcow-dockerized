<?php

declare(strict_types=1);

// Production autoloader first: the tests must find the classes through the PSR-4 mapping
// of data/web/inc/lib/composer.json, exactly as php-fpm does.
require __DIR__ . '/../../data/web/inc/lib/vendor/autoload.php';
require __DIR__ . '/vendor/autoload.php';
