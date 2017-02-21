<?php

class EmoticonsEngine extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        include_once __DIR__ . '/../emoticons_engine.php';
    }

    /**
     * text2icons() method tests
     */
    function test_text2icons()
    {
        $map = array(
            ':D'  => array('smiley-laughing.gif',    ':D'    ),
            ':-D' => array('smiley-laughing.gif',    ':-D'   ),
            ':('  => array('smiley-frown.gif',       ':('    ),
            ':-(' => array('smiley-frown.gif',       ':-('   ),
            '8)'  => array('smiley-cool.gif',        '8)'    ),
            '8-)' => array('smiley-cool.gif',        '8-)'   ),
            ':O'  => array('smiley-surprised.gif',   ':O'    ),
            ':-O' => array('smiley-surprised.gif',   ':-O'   ),
            ':P'  => array('smiley-tongue-out.gif',  ':P'    ),
            ':-P' => array('smiley-tongue-out.gif',  ':-P'   ),
            ':@'  => array('smiley-yell.gif',        ':@'    ),
            ':-@' => array('smiley-yell.gif',        ':-@'   ),
            'O:)' => array('smiley-innocent.gif',    'O:)'   ),
            'O:-)' => array('smiley-innocent.gif',    'O:-)' ),
            ':)'  => array('smiley-smile.gif',       ':)'    ),
            ':-)' => array('smiley-smile.gif',       ':-)'   ),
            ':$'  => array('smiley-embarassed.gif',  ':$'    ),
            ':-$' => array('smiley-embarassed.gif',  ':-$'   ),
            ':*'  => array('smiley-kiss.gif',       ':*'     ),
            ':-*' => array('smiley-kiss.gif',       ':-*'    ),
            ':S'  => array('smiley-undecided.gif',   ':S'    ),
            ':-S' => array('smiley-undecided.gif',   ':-S'   ),
        );

        foreach ($map as $body => $expected) {
            $result = emoticons_engine::text2icons($body);

            $this->assertRegExp('/' . preg_quote($expected[0], '/') . '/', $result);
            $this->assertRegExp('/title="' . preg_quote($expected[1], '/') . '"/', $result);
        }
    }
}
