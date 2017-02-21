<?php

class AdditionalMessageHeaders_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../additional_message_headers.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new additional_message_headers($rcube->api);

        $this->assertInstanceOf('additional_message_headers', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

