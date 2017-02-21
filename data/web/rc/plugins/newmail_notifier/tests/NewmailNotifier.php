<?php

class NewmailNotifier_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../newmail_notifier.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new newmail_notifier($rcube->api);

        $this->assertInstanceOf('newmail_notifier', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

