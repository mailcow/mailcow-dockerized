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
 * Class that represent the Version action.
 *
 * The execute methode add 1 to the value of the result option array entry.
 * The value is incremented each time the option is found, for example
 * with an option defined like that:
 *
 * <code>
 * $parser->addOption(
 *     'verbose',
 *     array(
 *         'short_name' => '-v',
 *         'action'     => 'Counter'
 *     )
 * );
 * </code>
 * If the user type:
 * <code>
 * $ script.php -v -v -v
 * </code>
 * or: 
 * <code>
 * $ script.php -vvv
 * </code>
 * the verbose variable will be set to to 3.
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
class Console_CommandLine_Action_Counter extends Console_CommandLine_Action
{
    // execute() {{{

    /**
     * Executes the action with the value entered by the user.
     *
     * @param mixed $value  The option value
     * @param array $params An optional array of parameters
     *
     * @return string
     */
    public function execute($value = false, $params = array())
    {
        $result = $this->getResult();
        if ($result === null) {
            $result = 0;
        }
        $this->setResult(++$result);
    }
    // }}}
}
