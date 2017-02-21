<?php

class Tokenizer extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../lib/Roundcube/rcube_sieve_script.php';
    }

    function data_tokenizer()
    {
        return array(
            array(1, "text: #test\nThis is test ; message;\nMulti line\n.\n;\n", '"This is test ; message;\nMulti line"'),
            array(0, '["test1","test2"]', '[["test1","test2"]]'),
            array(1, '["test"]', '["test"]'),
            array(1, '"te\\"st"', '"te\\"st"'),
            array(0, 'test #comment', '["test"]'),
            array(0, "text:\ntest\n.\ntext:\ntest\n.\n", '["test","test"]'),
            array(1, '"\\a\\\\\\"a"', '"a\\\\\\"a"'),
        );
    }

    /**
     * @dataProvider data_tokenizer
     */
    function test_tokenizer($num, $input, $output)
    {
        $res = json_encode(rcube_sieve_script::tokenize($input, $num));

        $this->assertEquals(trim($res), trim($output));
    }
}
