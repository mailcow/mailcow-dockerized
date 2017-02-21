<?php

class Archive_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../archive.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new archive($rcube->api);

        $this->assertInstanceOf('archive', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

