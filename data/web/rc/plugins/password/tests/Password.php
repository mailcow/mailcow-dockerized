<?php

class Password_Plugin extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../password.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $rcube  = rcube::get_instance();
        $plugin = new password($rcube->api);

        $this->assertInstanceOf('password', $plugin);
        $this->assertInstanceOf('rcube_plugin', $plugin);
    }

    /**
     * cpanel_webmail driver test
     */
    function test_driver_cpanel_webmail()
    {
        $driver_class = $this->load_driver('cpanel_webmail');

        $error_result = $driver_class::decode_response(false);
        $this->assertEquals($error_result, PASSWORD_CONNECT_ERROR);

        $bad_result = $driver_class::decode_response(null);
        $this->assertEquals($bad_result, PASSWORD_CONNECT_ERROR);

        $null_result = $driver_class::decode_response('null');
        $this->assertEquals($null_result, PASSWORD_ERROR);

        $malformed_result = $driver_class::decode_response('random {string]!');
        $this->assertEquals($malformed_result, PASSWORD_ERROR);

        $other_result = $driver_class::decode_response('{"a":"b"}');
        $this->assertEquals($other_result, PASSWORD_ERROR);

        $fail_response   = '{"data":null,"errors":["Execution of Email::passwdp'
                . 'op (api version:3) is not permitted inside of webmail"],"sta'
                . 'tus":0,"metadata":{},"messages":null}';
        $error_message   = 'Execution of Email::passwdpop (api version:3) is no'
                . 't permitted inside of webmail';
        $expected_result = array(
            'code'    => PASSWORD_ERROR,
            'message' => $error_message
        );
        $fail_result     = $driver_class::decode_response($fail_response);
        $this->assertEquals($expected_result, $fail_result);

        $success_response = '{"metadata":{},"data":null,"messages":null,"errors'
                . '":null,"status":1}';
        $good_result      = $driver_class::decode_response($success_response);
        $this->assertEquals($good_result, PASSWORD_SUCCESS);
    }

    /**
     * Loads a driver's source file, checks that its class exist and returns the
     * driver's class name.
     *
     * @param string $driver driver name, example: "chpasswd"
     * @return string driver's class name, example: "rcube_chpasswd_password"
     */
    function load_driver($driver)
    {
        include_once __DIR__ . "/../drivers/$driver.php";
        $driver_class = "rcube_${driver}_password";
        $this->assertTrue(class_exists($driver_class));
        return $driver_class;
    }
}
