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
 * Include base element class.
 */
require_once 'Console/CommandLine/Element.php';

/**
 * Class that represent a command line argument.
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
class Console_CommandLine_Argument extends Console_CommandLine_Element
{
    // Public properties {{{

    /**
     * Setting this to true will tell the parser that the argument expects more
     * than one argument and that argument values should be stored in an array.
     *
     * @var boolean $multiple Whether the argument expects multiple values
     */
    public $multiple = false;

    /**
     * Setting this to true will tell the parser that the argument is optional
     * and can be ommited.
     * Note that it is not a good practice to make arguments optional, it is
     * the role of the options to be optional, by essence.
     *
     * @var boolean $optional Whether the argument is optional or not.
     */
    public $optional = false;

    /**
     * An array of possible values for the argument.
     *
     * @var array $choices Valid choices for the argument
     */
    public $choices = array();

    // }}}
    // validate() {{{

    /**
     * Validates the argument instance.
     *
     * @return void
     * @throws Console_CommandLine_Exception
     * @todo use exceptions
     */
    public function validate()
    {
        // check if the argument name is valid
        if (!preg_match('/^[a-zA-Z_\x7f-\xff]+[a-zA-Z0-9_\x7f-\xff]*$/',
            $this->name)) {
            Console_CommandLine::triggerError(
                'argument_bad_name',
                E_USER_ERROR,
                array('{$name}' => $this->name)
            );
        }
        if (!$this->optional && $this->default !== null) {
            Console_CommandLine::triggerError(
                'argument_no_default',
                E_USER_ERROR
            );
        }
        parent::validate();
    }

    // }}}
}
