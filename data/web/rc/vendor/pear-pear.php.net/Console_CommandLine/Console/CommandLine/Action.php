<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of the PEAR Console_CommandLine package.
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to the MIT license that is available
 * through the world-wide-web at the following URI:
 * http://opensource.org/licenses/mit-license.php
 *
 * @category  Console 
 * @package   Console_CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License 
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Console_CommandLine
 * @since     File available since release 0.1.0
 * @filesource
 */

/**
 * Class that represent an option action.
 *
 * @category  Console
 * @package   Console_CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License 
 * @version   Release: 1.2.2
 * @link      http://pear.php.net/package/Console_CommandLine
 * @since     Class available since release 0.1.0
 */
abstract class Console_CommandLine_Action
{
    // Properties {{{

    /**
     * A reference to the result instance.
     *
     * @var Console_CommandLine_Result $result The result instance
     */
    protected $result;

    /**
     * A reference to the option instance.
     *
     * @var Console_CommandLine_Option $option The action option
     */
    protected $option;

    /**
     * A reference to the parser instance.
     *
     * @var Console_CommandLine $parser The parser
     */
    protected $parser;

    // }}}
    // __construct() {{{

    /**
     * Constructor
     *
     * @param Console_CommandLine_Result $result The result instance
     * @param Console_CommandLine_Option $option The action option
     * @param Console_CommandLine        $parser The current parser
     *
     * @return void
     */
    public function __construct($result, $option, $parser)
    {
        $this->result = $result;
        $this->option = $option;
        $this->parser = $parser;
    }

    // }}}
    // getResult() {{{

    /**
     * Convenience method to retrieve the value of result->options[name].
     *
     * @return mixed The result value or null
     */
    public function getResult()
    {
        if (isset($this->result->options[$this->option->name])) {
            return $this->result->options[$this->option->name];
        }
        return null;
    }

    // }}}
    // format() {{{

    /**
     * Allow a value to be pre-formatted prior to being used in a choices test.
     * Setting $value to the new format will keep the formatting.
     *
     * @param mixed &$value The value to format
     *
     * @return mixed The formatted value
     */
    public function format(&$value)
    {
        return $value;
    }

    // }}}
    // setResult() {{{

    /**
     * Convenience method to assign the result->options[name] value.
     *
     * @param mixed $result The result value
     *
     * @return void
     */
    public function setResult($result)
    {
        $this->result->options[$this->option->name] = $result;
    }

    // }}}
    // execute() {{{

    /**
     * Executes the action with the value entered by the user.
     * All children actions must implement this method.
     *
     * @param mixed $value  The option value
     * @param array $params An optional array of parameters
     *
     * @return string
     */
    abstract public function execute($value = false, $params = array());
    // }}}
}
