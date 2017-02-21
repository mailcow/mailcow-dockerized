<?php

class Markasjunk_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../markasjunk.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new markasjunk($rcube->api);

        $this->assertInstanceOf('markasjunk', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

