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
 * Required by this class.
 */
require_once 'Console/CommandLine/Action.php';

/**
 * Class that represent the Callback action.
 *
 * The result option array entry value is set to the return value of the
 * callback defined in the option.
 *
 * There are two steps to defining a callback option:
 *   - define the option itself using the callback action
 *   - write the callback; this is a function (or method) that takes five
 *     arguments, as described below.
 *
 * All callbacks are called as follows:
 * <code>
 * callable_func(
 *     $value,           // the value of the option
 *     $option_instance, // the option instance
 *     $result_instance, // the result instance
 *     $parser_instance, // the parser instance
 *     $params           // an array of params as specified in the option
 * );
 * </code>
 * and *must* return the option value.
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
class Console_CommandLine_Action_Callback extends Console_CommandLine_Action
{
    // execute() {{{

    /**
     * Executes the action with the value entered by the user.
     *
     * @param mixed $value  The value of the option
     * @param array $params An optional array of parameters
     *
     * @return string
     */
    public function execute($value = false, $params = array())
    {
        $this->setResult(call_user_func($this->option->callback, $value,
            $this->option, $this->result, $this->parser, $params));
    }
    // }}}
}
