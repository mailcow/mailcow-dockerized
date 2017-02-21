<?php

class Parser extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../lib/Roundcube/rcube_sieve_script.php';
    }

    /**
     * Sieve script parsing
     *
     * @dataProvider data_parser
     */
    function test_parser($input, $output, $message)
    {
        // get capabilities list from the script
        $caps = array();
        if (preg_match('/require \[([a-z0-9", ]+)\]/', $input, $m)) {
            foreach (explode(',', $m[1]) as $cap) {
                $caps[] = trim($cap, '" ');
            }
        }

        $script = new rcube_sieve_script($input, $caps);
        $result = $script->as_text();

        $this->assertEquals(trim($result), trim($output), $message);
    }

    /**
     * Data provider for test_parser()
     */
    function data_parser()
    {
        $dir_path = realpath(__DIR__ . '/src');
        $dir      = opendir($dir_path);
        $result   = array();

        while ($file = readdir($dir)) {
            if (preg_match('/^[a-z0-9_]+$/', $file)) {
                $input = file_get_contents($dir_path . '/' . $file);

                if (file_exists($dir_path . '/' . $file . '.out')) {
                    $output = file_get_contents($dir_path . '/' . $file . '.out');
                }
                else {
                    $output = $input;
                }

                $result[] = array(
                    'input'   => $input,
                    'output'  => $output,
                    'message' => "Error in parsing '$file' file",
                );
            }
        }

        return $result;
    }
}
