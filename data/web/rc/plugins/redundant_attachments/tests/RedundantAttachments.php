<?php

class RedundantAttachments_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../redundant_attachments.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new redundant_attachments($rcube->api);

        $this->assertInstanceOf('redundant_attachments', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

