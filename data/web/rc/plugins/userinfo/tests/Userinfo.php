<?php

class Userinfo_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../userinfo.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new userinfo($rcube->api);

        $this->assertInstanceOf('userinfo', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

