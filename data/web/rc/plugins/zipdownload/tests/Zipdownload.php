<?php

class Zipdownload_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../zipdownload.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new zipdownload($rcube->api);

        $this->assertInstanceOf('zipdownload', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

