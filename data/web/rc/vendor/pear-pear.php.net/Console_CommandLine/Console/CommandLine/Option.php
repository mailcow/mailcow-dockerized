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
require_once 'Console/CommandLine.php';
require_once 'Console/CommandLine/Element.php';

/**
 * Class that represent a commandline option.
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
class Console_CommandLine_Option extends Console_CommandLine_Element
{
    // Public properties {{{

    /**
     * The option short name (ex: -v).
     *
     * @var string $short_name Short name of the option
     */
    public $short_name;

    /**
     * The option long name (ex: --verbose).
     *
     * @var string $long_name Long name of the option
     */
    public $long_name;

    /**
     * The option action, defaults to "StoreString".
     *
     * @var string $action Option action
     */
    public $action = 'StoreString';

    /**
     * An array of possible values for the option. If this array is not empty 
     * and the value passed is not in the array an exception is raised.
     * This only make sense for actions that accept values of course.
     *
     * @var array $choices Valid choices for the option
     */
    public $choices = array();

    /**
     * The callback function (or method) to call for an action of type 
     * Callback, this can be any callable supported by the php function 
     * call_user_func.
     * 
     * Example:
     *
     * <code>
     * $parser->addOption('myoption', array(
     *     'short_name' => '-m',
     *     'long_name'  => '--myoption',
     *     'action'     => 'Callback',
     *     'callback'   => 'myCallbackFunction'
     * ));
     * </code>
     *
     * @var callable $callback The option callback
     */
    public $callback;

    /**
     * An associative array of additional params to pass to the class 
     * corresponding to the action, this array will also be passed to the 
     * callback defined for an action of type Callback, Example:
     *
     * <code>
     * // for a custom action
     * $parser->addOption('myoption', array(
     *     'short_name'    => '-m',
     *     'long_name'     => '--myoption',
     *     'action'        => 'MyCustomAction',
     *     'action_params' => array('foo'=>true, 'bar'=>false)
     * ));
     *
     * // if the user type:
     * // $ <yourprogram> -m spam
     * // in your MyCustomAction class the execute() method will be called
     * // with the value 'spam' as first parameter and 
     * // array('foo'=>true, 'bar'=>false) as second parameter
     * </code>
     *
     * @var array $action_params Additional parameters to pass to the action
     */
    public $action_params = array();

    /**
     * For options that expect an argument, this property tells the parser if 
     * the option argument is optional and can be ommited.
     *
     * @var bool $argumentOptional Whether the option arg is optional or not
     */
    public $argument_optional = false;

    /**
     * For options that uses the "choice" property only.
     * Adds a --list-<choice> option to the parser that displays the list of 
     * choices for the option.
     *
     * @var bool $add_list_option Whether to add a list option or not
     */
    public $add_list_option = false;

    // }}}
    // Private properties {{{

    /**
     * When an action is called remember it to allow for multiple calls.
     *
     * @var object $action_instance Placeholder for action
     */
    private $_action_instance = null;

    // }}}
    // __construct() {{{

    /**
     * Constructor.
     *
     * @param string $name   The name of the option
     * @param array  $params An optional array of parameters
     *
     * @return void
     */
    public function __construct($name = null, $params = array()) 
    {
        parent::__construct($name, $params);
        if ($this->action == 'Password') {
            // special case for Password action, password can be passed to the 
            // commandline or prompted by the parser
            $this->argument_optional = true;
        }
    }

    // }}}
    // toString() {{{

    /**
     * Returns the string representation of the option.
     *
     * @param string $delim Delimiter to use between short and long option
     *
     * @return string The string representation of the option
     * @todo use __toString() instead
     */
    public function toString($delim = ", ")
    {
        $ret     = '';
        $padding = '';
        if ($this->short_name != null) {
            $ret .= $this->short_name;
            if ($this->expectsArgument()) {
                $ret .= ' ' . $this->help_name;
            }
            $padding = $delim;
        }
        if ($this->long_name != null) {
            $ret .= $padding . $this->long_name;
            if ($this->expectsArgument()) {
                $ret .= '=' . $this->help_name;
            }
        }
        return $ret;
    }

    // }}}
    // expectsArgument() {{{

    /**
     * Returns true if the option requires one or more argument and false 
     * otherwise.
     *
     * @return bool Whether the option expects an argument or not
     */
    public function expectsArgument()
    {
        if ($this->action == 'StoreTrue' || $this->action == 'StoreFalse' ||
            $this->action == 'Help' || $this->action == 'Version' ||
            $this->action == 'Counter' || $this->action == 'List') {
            return false;
        }
        return true;
    }

    // }}}
    // dispatchAction() {{{

    /**
     * Formats the value $value according to the action of the option and 
     * updates the passed Console_CommandLine_Result object.
     *
     * @param mixed                      $value  The value to format
     * @param Console_CommandLine_Result $result The result instance
     * @param Console_CommandLine        $parser The parser instance
     *
     * @return void
     * @throws Console_CommandLine_Exception
     */
    public function dispatchAction($value, $result, $parser)
    {
        $actionInfo = Console_CommandLine::$actions[$this->action];
        if (true === $actionInfo[1]) {
            // we have a "builtin" action
            $tokens = explode('_', $actionInfo[0]);
            include_once implode('/', $tokens) . '.php';
        }
        $clsname = $actionInfo[0];
        if ($this->_action_instance === null) {
            $this->_action_instance  = new $clsname($result, $this, $parser);
        }

        // check value is in option choices
        if (!empty($this->choices) && !in_array($this->_action_instance->format($value), $this->choices)) {
            throw Console_CommandLine_Exception::factory(
                'OPTION_VALUE_NOT_VALID',
                array(
                    'name'    => $this->name,
                    'choices' => implode('", "', $this->choices),
                    'value'   => $value,
                ),
                $parser,
                $this->messages
            );
        }
        $this->_action_instance->execute($value, $this->action_params);
    }

    // }}}
    // validate() {{{

    /**
     * Validates the option instance.
     *
     * @return void
     * @throws Console_CommandLine_Exception
     * @todo use exceptions instead
     */
    public function validate()
    {
        // check if the option name is valid
        if (!preg_match('/^[a-zA-Z_\x7f-\xff]+[a-zA-Z0-9_\x7f-\xff]*$/',
            $this->name)) {
            Console_CommandLine::triggerError('option_bad_name',
                E_USER_ERROR, array('{$name}' => $this->name));
        }
        // call the parent validate method
        parent::validate();
        // a short_name or a long_name must be provided
        if ($this->short_name == null && $this->long_name == null) {
            Console_CommandLine::triggerError('option_long_and_short_name_missing',
                E_USER_ERROR, array('{$name}' => $this->name));
        }
        // check if the option short_name is valid
        if ($this->short_name != null && 
            !(preg_match('/^\-[a-zA-Z]{1}$/', $this->short_name))) {
            Console_CommandLine::triggerError('option_bad_short_name',
                E_USER_ERROR, array(
                    '{$name}' => $this->name, 
                    '{$short_name}' => $this->short_name
                ));
        }
        // check if the option long_name is valid
        if ($this->long_name != null && 
            !preg_match('/^\-\-[a-zA-Z]+[a-zA-Z0-9_\-]*$/', $this->long_name)) {
            Console_CommandLine::triggerError('option_bad_long_name',
                E_USER_ERROR, array(
                    '{$name}' => $this->name, 
                    '{$long_name}' => $this->long_name
                ));
        }
        // check if we have a valid action
        if (!is_string($this->action)) {
            Console_CommandLine::triggerError('option_bad_action',
                E_USER_ERROR, array('{$name}' => $this->name));
        }
        if (!isset(Console_CommandLine::$actions[$this->action])) {
            Console_CommandLine::triggerError('option_unregistered_action',
                E_USER_ERROR, array(
                    '{$action}' => $this->action,
                    '{$name}' => $this->name
                ));
        }
        // if the action is a callback, check that we have a valid callback
        if ($this->action == 'Callback' && !is_callable($this->callback)) {
            Console_CommandLine::triggerError('option_invalid_callback',
                E_USER_ERROR, array('{$name}' => $this->name));
        }
    }

    // }}}
    // setDefaults() {{{

    /**
     * Set the default value according to the configured action.
     *
     * Note that for backward compatibility issues this method is only called 
     * when the 'force_options_defaults' is set to true, it will become the
     * default behaviour in the next major release of Console_CommandLine.
     *
     * @return void
     */
    public function setDefaults()
    {
        if ($this->default !== null) {
            // already set
            return;
        }
        switch ($this->action) {
        case 'Counter':
        case 'StoreInt':
            $this->default = 0;
            break;
        case 'StoreFloat':
            $this->default = 0.0;
            break;
        case 'StoreArray':
            $this->default = array();
            break;
        case 'StoreTrue':
            $this->default = false;
            break;
        case 'StoreFalse':
            $this->default = true;
            break;
        default:
            return;
        }
    }

    // }}}
}
