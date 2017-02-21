<?php

class Jqueryui_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../jqueryui.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new jqueryui($rcube->api);

        $this->assertInstanceOf('jqueryui', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

