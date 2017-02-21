<?php

class FilesystemAttachments_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../filesystem_attachments.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new filesystem_attachments($rcube->api);

        $this->assertInstanceOf('filesystem_attachments', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

