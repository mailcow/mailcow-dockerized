<?php

class Managesieve_Vacation extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../lib/Roundcube/rcube_sieve_engine.php';
        include_once __DIR__ . '/../lib/Roundcube/rcube_sieve_vacation.php';
    }

    /**
     * Plugin object construction test
     */
    function test_constructor()
    {
        $vacation = new rcube_sieve_vacation(true);

        $this->assertInstanceOf('rcube_sieve_vacation', $vacation);
    }

    function test_build_regexp_tests()
    {
        $tests = rcube_sieve_vacation::build_regexp_tests('2014-02-20', '2014-03-05', $error);

        $this->assertCount(2, $tests);
        $this->assertSame('header', $tests[0]['test']);
        $this->assertSame('regex', $tests[0]['type']);
        $this->assertSame('received', $tests[0]['arg1']);
        $this->assertSame('(20|21|22|23|24|25|26|27|28) Feb 2014', $tests[0]['arg2']);
        $this->assertSame('header', $tests[1]['test']);
        $this->assertSame('regex', $tests[1]['type']);
        $this->assertSame('received', $tests[1]['arg1']);
        $this->assertSame('([ 0]1|[ 0]2|[ 0]3|[ 0]4|[ 0]5) Mar 2014', $tests[1]['arg2']);

        $tests = rcube_sieve_vacation::build_regexp_tests('2014-02-20', '2014-01-05', $error);

        $this->assertSame(null, $tests);
        $this->assertSame('managesieve.invaliddateformat', $error);
    }

    function test_parse_regexp_tests()
    {
        $tests = array(
            array(
                'test' => 'header',
                'type' => 'regex',
                'arg1' => 'received',
                'arg2' => '(20|21|22|23|24|25|26|27|28) Feb 2014',
            ),
            array(
                'test' => 'header',
                'type' => 'regex',
                'arg1' => 'received',
                'arg2' => '([ 0]1|[ 0]2|[ 0]3|[ 0]4|[ 0]5) Mar 2014',
            )
        );

        $result = rcube_sieve_vacation::parse_regexp_tests($tests);

        $this->assertCount(2, $result);
        $this->assertSame('20 Feb 2014', $result['from']);
        $this->assertSame('05 Mar 2014', $result['to']);
    }
}

