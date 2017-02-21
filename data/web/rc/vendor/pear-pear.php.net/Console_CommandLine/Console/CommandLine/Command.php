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
 * File containing the parent class.
 */
require_once 'Console/CommandLine.php';

/**
 * Class that represent a command with option and arguments.
 *
 * This class exist just to clarify the interface but at the moment it is 
 * strictly identical to Console_CommandLine class, it could change in the
 * future though.
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
class Console_CommandLine_Command extends Console_CommandLine
{
    // Public properties {{{

    /**
     * An array of aliases for the subcommand.
     *
     * @var array $aliases Aliases for the subcommand.
     */
    public $aliases = array();

    // }}}
    // __construct() {{{

    /**
     * Constructor.
     *
     * @param array  $params An optional array of parameters
     *
     * @return void
     */
    public function __construct($params = array()) 
    {
        if (isset($params['aliases'])) {
            $this->aliases = $params['aliases'];
        }
        parent::__construct($params);
    }

    // }}}
}
