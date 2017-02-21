<?php

class DatabaseAttachments_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../database_attachments.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new database_attachments($rcube->api);

        $this->assertInstanceOf('database_attachments', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

