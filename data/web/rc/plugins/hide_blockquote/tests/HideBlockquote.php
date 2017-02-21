<?php

class HideBlockquote_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../hide_blockquote.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new hide_blockquote($rcube->api);

        $this->assertInstanceOf('hide_blockquote', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

