<?php

class Managesieve_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../managesieve.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new managesieve($rcube->api);

        $this->assertInstanceOf('managesieve', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

