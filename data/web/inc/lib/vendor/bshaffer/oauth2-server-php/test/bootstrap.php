<?php

require_once(dirname(__FILE__).'/../src/OAuth2/Autoloader.php');
OAuth2\Autoloader::register();

// register test classes
OAuth2\Autoloader::register(dirname(__FILE__).'/lib');

// register vendors if possible
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once(__DIR__.'/../vendor/autoload.php');
}
