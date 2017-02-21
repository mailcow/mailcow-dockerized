<?php

class IdentitySelect_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../identity_select.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new identity_select($rcube->api);

        $this->assertInstanceOf('identity_select', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}
