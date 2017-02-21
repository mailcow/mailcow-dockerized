<?php

class VcardAttachments_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../vcard_attachments.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new vcard_attachments($rcube->api);

        $this->assertInstanceOf('vcard_attachments', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

