<?php

class ExampleAddressbook_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../example_addressbook.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new example_addressbook($rcube->api);

        $this->assertInstanceOf('example_addressbook', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }
}

