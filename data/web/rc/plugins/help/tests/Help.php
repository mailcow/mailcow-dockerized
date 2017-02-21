<?php

class Help_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../help.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new help($rcube->api);

        $this->assertInstanceOf('help', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

