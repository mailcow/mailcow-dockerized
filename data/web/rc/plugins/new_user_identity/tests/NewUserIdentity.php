<?php

class NewUserIdentity_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../new_user_identity.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new new_user_identity($rcube->api);

        $this->assertInstanceOf('new_user_identity', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

