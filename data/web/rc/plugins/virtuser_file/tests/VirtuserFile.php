<?php

class VirtuserFile_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../virtuser_file.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new virtuser_file($rcube->api);

        $this->assertInstanceOf('virtuser_file', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

