<?php

class Autologon_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../autologon.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new autologon($rcube->api);

        $this->assertInstanceOf('autologon', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

