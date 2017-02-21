<?php

class HttpAuthentication_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../http_authentication.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new http_authentication($rcube->api);

        $this->assertInstanceOf('http_authentication', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

