<?php

class DebugLogger_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../debug_logger.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new debug_logger($rcube->api);

        $this->assertInstanceOf('debug_logger', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

