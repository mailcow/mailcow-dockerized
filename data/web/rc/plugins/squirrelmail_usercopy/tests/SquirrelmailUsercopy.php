<?php

class SquirrelmailUsercopy_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../squirrelmail_usercopy.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new squirrelmail_usercopy($rcube->api);

        $this->assertInstanceOf('squirrelmail_usercopy', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

