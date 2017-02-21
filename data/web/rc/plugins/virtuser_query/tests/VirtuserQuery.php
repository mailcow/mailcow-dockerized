<?php

class VirtuserQuery_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../virtuser_query.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new virtuser_query($rcube->api);

        $this->assertInstanceOf('virtuser_query', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

