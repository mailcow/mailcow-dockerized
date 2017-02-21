<?php

class KrbAuthentication_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once dirname(__FILE__) . '/../krb_authentication.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new krb_authentication($rcube->api);

        $this->assertInstanceOf('krb_authentication', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

