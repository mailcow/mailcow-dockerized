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
 * A lightweight class to store the result of the command line parsing.
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
class Console_CommandLine_Result
{
    // Public properties {{{

    /**
     * The result options associative array.
     * Key is the name of the option and value its value.
     *
     * @var array $options Result options array
     */
    public $options = array();

    /**
     * The result arguments array.
     *
     * @var array $args Result arguments array
     */
    public $args = array();

    /**
     * Name of the command invoked by the user, false if no command invoked.
     *
     * @var string $command_name Result command name
     */
    public $command_name = false;

    /**
     * A result instance for the subcommand.
     *
     * @var Console_CommandLine_Result Result instance for the subcommand
     */
    public $command = false;

    // }}}
}
