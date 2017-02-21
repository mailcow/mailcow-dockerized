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
 * Class that represent the List action, a special action that simply output an 
 * array as a list.
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
class Console_CommandLine_Action_List extends Console_CommandLine_Action
{
    // execute() {{{

    /**
     * Executes the action with the value entered by the user.
     * Possible parameters are:
     * - message: an alternative message to display instead of the default 
     *   message,
     * - delimiter: an alternative delimiter instead of the comma,
     * - post: a string to append after the message (default is the new line 
     *   char).
     *
     * @param mixed $value  The option value
     * @param array $params An optional array of parameters
     *
     * @return string
     */
    public function execute($value = false, $params = array())
    {
        $list = isset($params['list']) ? $params['list'] : array();
        $msg  = isset($params['message']) 
            ? $params['message'] 
            : $this->parser->message_provider->get('LIST_DISPLAYED_MESSAGE');
        $del  = isset($params['delimiter']) ? $params['delimiter'] : ', ';
        $post = isset($params['post']) ? $params['post'] : "\n";
        $this->parser->outputter->stdout($msg . implode($del, $list) . $post);
        exit(0);
    }
    // }}}
}
