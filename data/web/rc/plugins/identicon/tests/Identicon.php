<?php

class Identicon_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../identicon.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new identicon($rcube->api);

        $this->assertInstanceOf('identicon', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}
