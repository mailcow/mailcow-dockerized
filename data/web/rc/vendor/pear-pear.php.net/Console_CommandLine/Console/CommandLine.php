<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of the PEAR Console_CommandLine package.
 *
 * A full featured package for managing command-line options and arguments 
 * hightly inspired from python optparse module, it allows the developper to 
 * easily build complex command line interfaces.
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
 * @since     Class available since release 0.1.0
 * @filesource
 */

/**
 * Required unconditionally
 */
require_once 'Console/CommandLine/Exception.php';
require_once 'Console/CommandLine/Outputter/Default.php';
require_once 'Console/CommandLine/Renderer/Default.php';
require_once 'Console/CommandLine/MessageProvider/Default.php';

/**
 * Main class for parsing command line options and arguments.
 * 
 * There are three ways to create parsers with this class:
 * <code>
 * // direct usage
 * $parser = new Console_CommandLine();
 *
 * // with an xml definition file
 * $parser = Console_CommandLine::fromXmlFile('path/to/file.xml');
 *
 * // with an xml definition string
 * $validXmlString = '..your xml string...';
 * $parser = Console_CommandLine::fromXmlString($validXmlString);
 * </code>
 *
 * @category  Console
 * @package   Console_CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License 
 * @version   Release: 1.2.2
 * @link      http://pear.php.net/package/Console_CommandLine
 * @since     File available since release 0.1.0
 * @example   docs/examples/ex1.php
 * @example   docs/examples/ex2.php
 */
class Console_CommandLine
{
    // Public properties {{{

    /**
     * Error messages.
     *
     * @var array $errors Error messages
     * @todo move this to Console_CommandLine_MessageProvider
     */
    public static $errors = array(
        'option_bad_name'                    => 'option name must be a valid php variable name (got: {$name})',
        'argument_bad_name'                  => 'argument name must be a valid php variable name (got: {$name})',
        'argument_no_default'                => 'only optional arguments can have a default value',
        'option_long_and_short_name_missing' => 'you must provide at least an option short name or long name for option "{$name}"',
        'option_bad_short_name'              => 'option "{$name}" short name must be a dash followed by a letter (got: "{$short_name}")',
        'option_bad_long_name'               => 'option "{$name}" long name must be 2 dashes followed by a word (got: "{$long_name}")',
        'option_unregistered_action'         => 'unregistered action "{$action}" for option "{$name}".',
        'option_bad_action'                  => 'invalid action for option "{$name}".',
        'option_invalid_callback'            => 'you must provide a valid callback for option "{$name}"',
        'action_class_does_not_exists'       => 'action "{$name}" class "{$class}" not found, make sure that your class is available before calling Console_CommandLine::registerAction()',
        'invalid_xml_file'                   => 'XML definition file "{$file}" does not exists or is not readable',
        'invalid_rng_file'                   => 'RNG file "{$file}" does not exists or is not readable'
    );

    /**
     * The name of the program, if not given it defaults to argv[0].
     *
     * @var string $name Name of your program
     */
    public $name;

    /**
     * A description text that will be displayed in the help message.
     *
     * @var string $description Description of your program
     */
    public $description = '';

    /**
     * A string that represents the version of the program, if this property is 
     * not empty and property add_version_option is not set to false, the
     * command line parser will add a --version option, that will display the
     * property content.
     *
     * @var    string $version
     * @access public
     */
    public $version = '';

    /**
     * Boolean that determine if the command line parser should add the help
     * (-h, --help) option automatically.
     *
     * @var bool $add_help_option Whether to add a help option or not
     */
    public $add_help_option = true;

    /**
     * Boolean that determine if the command line parser should add the version
     * (-v, --version) option automatically.
     * Note that the version option is also generated only if the version 
     * property is not empty, it's up to you to provide a version string of 
     * course.
     *
     * @var bool $add_version_option Whether to add a version option or not
     */
    public $add_version_option = true;

    /**
     * Boolean that determine if providing a subcommand is mandatory.
     *
     * @var bool $subcommand_required Whether a subcommand is required or not
     */
    public $subcommand_required = false;

    /**
     * The command line parser renderer instance.
     *
     * @var    object that implements Console_CommandLine_Renderer interface
     * @access protected
     */
    public $renderer = false;

    /**
     * The command line parser outputter instance.
     *
     * @var Console_CommandLine_Outputter An outputter
     */
    public $outputter = false;

    /**
     * The command line message provider instance.
     *
     * @var Console_CommandLine_MessageProvider A message provider instance
     */
    public $message_provider = false;

    /**
     * Boolean that tells the parser to be POSIX compliant, POSIX demands the 
     * following behavior: the first non-option stops option processing.
     *
     * @var bool $force_posix Whether to force posix compliance or not
     */
    public $force_posix = false;

    /**
     * Boolean that tells the parser to set relevant options default values, 
     * according to the option action.
     *
     * @see Console_CommandLine_Option::setDefaults()
     * @var bool $force_options_defaults Whether to force option default values
     */
    public $force_options_defaults = false;

 
   /**
    * Boolean that tells the parser to treat a single - option as an argument
    * instead of trying to read STDIN.
    *
    * @var bool $avoid_reading_stdin Whether to treat - as an argument
    */
    public $avoid_reading_stdin = false;

    /**
     * An array of Console_CommandLine_Option objects.
     *
     * @var array $options The options array
     */
    public $options = array();

    /**
     * An array of Console_CommandLine_Argument objects.
     *
     * @var array $args The arguments array
     */
    public $args = array();

    /**
     * An array of Console_CommandLine_Command objects (sub commands).
     *
     * @var array $commands The commands array
     */
    public $commands = array();

    /**
     * Parent, only relevant in Command objects but left here for interface 
     * convenience.
     *
     * @var Console_CommandLine The parent instance
     * @todo move Console_CommandLine::parent to Console_CommandLine_Command
     */
    public $parent = false;

    /**
     * Array of valid actions for an option, this array will also store user 
     * registered actions.
     *
     * The array format is:
     * <pre>
     * array(
     *     <ActionName:string> => array(<ActionClass:string>, <builtin:bool>)
     * )
     * </pre>
     *
     * @var array $actions List of valid actions
     */
    public static $actions = array(
        'StoreTrue'   => array('Console_CommandLine_Action_StoreTrue', true),
        'StoreFalse'  => array('Console_CommandLine_Action_StoreFalse', true),
        'StoreString' => array('Console_CommandLine_Action_StoreString', true),
        'StoreInt'    => array('Console_CommandLine_Action_StoreInt', true),
        'StoreFloat'  => array('Console_CommandLine_Action_StoreFloat', true),
        'StoreArray'  => array('Console_CommandLine_Action_StoreArray', true),
        'Callback'    => array('Console_CommandLine_Action_Callback', true),
        'Counter'     => array('Console_CommandLine_Action_Counter', true),
        'Help'        => array('Console_CommandLine_Action_Help', true),
        'Version'     => array('Console_CommandLine_Action_Version', true),
        'Password'    => array('Console_CommandLine_Action_Password', true),
        'List'        => array('Console_CommandLine_Action_List', true),
    );

    /**
     * Custom errors messages for this command
     *
     * This array is of the form:
     * <code>
     * <?php
     * array(
     *     $messageName => $messageText,
     *     $messageName => $messageText,
     *     ...
     * );
     * ?>
     * </code>
     *
     * If specified, these messages override the messages provided by the
     * default message provider. For example:
     * <code>
     * <?php
     * $messages = array(
     *     'ARGUMENT_REQUIRED' => 'The argument foo is required.',
     * );
     * ?>
     * </code>
     *
     * @var array
     * @see Console_CommandLine_MessageProvider_Default
     */
    public $messages = array();

    // }}}
    // {{{ Private properties

    /**
     * Array of options that must be dispatched at the end.
     *
     * @var array $_dispatchLater Options to be dispatched
     */
    private $_dispatchLater = array();

    private $_lastopt = false;
    private $_stopflag = false;

    // }}}
    // __construct() {{{

    /**
     * Constructor.
     * Example:
     *
     * <code>
     * $parser = new Console_CommandLine(array(
     *     'name'               => 'yourprogram', // defaults to argv[0]
     *     'description'        => 'Description of your program',
     *     'version'            => '0.0.1', // your program version
     *     'add_help_option'    => true, // or false to disable --help option
     *     'add_version_option' => true, // or false to disable --version option
     *     'force_posix'        => false // or true to force posix compliance
     * ));
     * </code>
     *
     * @param array $params An optional array of parameters
     *
     * @return void
     */
    public function __construct(array $params = array()) 
    {
        if (isset($params['name'])) {
            $this->name = $params['name'];
        } else if (isset($argv) && count($argv) > 0) {
            $this->name = $argv[0];
        } else if (isset($_SERVER['argv']) && count($_SERVER['argv']) > 0) {
            $this->name = $_SERVER['argv'][0];
        } else if (isset($_SERVER['SCRIPT_NAME'])) {
            $this->name = basename($_SERVER['SCRIPT_NAME']);
        }
        if (isset($params['description'])) {
            $this->description = $params['description'];
        }
        if (isset($params['version'])) {
            $this->version = $params['version'];
        }
        if (isset($params['add_version_option'])) {
            $this->add_version_option = $params['add_version_option'];
        }
        if (isset($params['add_help_option'])) {
            $this->add_help_option = $params['add_help_option'];
        }
        if (isset($params['subcommand_required'])) {
            $this->subcommand_required = $params['subcommand_required'];
        }
        if (isset($params['force_posix'])) {
            $this->force_posix = $params['force_posix'];
        } else if (getenv('POSIXLY_CORRECT')) {
            $this->force_posix = true;
        }
        if (isset($params['messages']) && is_array($params['messages'])) {
            $this->messages = $params['messages'];
        }
        // set default instances
        $this->renderer         = new Console_CommandLine_Renderer_Default($this);
        $this->outputter        = new Console_CommandLine_Outputter_Default();
        $this->message_provider = new Console_CommandLine_MessageProvider_Default();
    }

    // }}}
    // accept() {{{

    /**
     * Method to allow Console_CommandLine to accept either:
     *  + a custom renderer, 
     *  + a custom outputter,
     *  + or a custom message provider
     *
     * @param mixed $instance The custom instance
     *
     * @return void
     * @throws Console_CommandLine_Exception if wrong argument passed
     */
    public function accept($instance) 
    {
        if ($instance instanceof Console_CommandLine_Renderer) {
            if (property_exists($instance, 'parser') && !$instance->parser) {
                $instance->parser = $this;
            }
            $this->renderer = $instance;
        } else if ($instance instanceof Console_CommandLine_Outputter) {
            $this->outputter = $instance;
        } else if ($instance instanceof Console_CommandLine_MessageProvider) {
            $this->message_provider = $instance;
        } else {
            throw Console_CommandLine_Exception::factory(
                'INVALID_CUSTOM_INSTANCE',
                array(),
                $this,
                $this->messages
            );
        }
    }

    // }}}
    // fromXmlFile() {{{

    /**
     * Returns a command line parser instance built from an xml file.
     *
     * Example:
     * <code>
     * require_once 'Console/CommandLine.php';
     * $parser = Console_CommandLine::fromXmlFile('path/to/file.xml');
     * $result = $parser->parse();
     * </code>
     *
     * @param string $file Path to the xml file
     *
     * @return Console_CommandLine The parser instance
     */
    public static function fromXmlFile($file) 
    {
        include_once 'Console/CommandLine/XmlParser.php';
        return Console_CommandLine_XmlParser::parse($file);
    }

    // }}}
    // fromXmlString() {{{

    /**
     * Returns a command line parser instance built from an xml string.
     *
     * Example:
     * <code>
     * require_once 'Console/CommandLine.php';
     * $xmldata = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
     * <command>
     *   <description>Compress files</description>
     *   <option name="quiet">
     *     <short_name>-q</short_name>
     *     <long_name>--quiet</long_name>
     *     <description>be quiet when run</description>
     *     <action>StoreTrue/action>
     *   </option>
     *   <argument name="files">
     *     <description>a list of files</description>
     *     <multiple>true</multiple>
     *   </argument>
     * </command>';
     * $parser = Console_CommandLine::fromXmlString($xmldata);
     * $result = $parser->parse();
     * </code>
     *
     * @param string $string The xml data
     *
     * @return Console_CommandLine The parser instance
     */
    public static function fromXmlString($string) 
    {
        include_once 'Console/CommandLine/XmlParser.php';
        return Console_CommandLine_XmlParser::parseString($string);
    }

    // }}}
    // addArgument() {{{

    /**
     * Adds an argument to the command line parser and returns it.
     *
     * Adds an argument with the name $name and set its attributes with the
     * array $params, then return the Console_CommandLine_Argument instance
     * created.
     * The method accepts another form: you can directly pass a 
     * Console_CommandLine_Argument object as the sole argument, this allows
     * you to contruct the argument separately, in order to reuse it in
     * different command line parsers or commands for example.
     *
     * Example:
     * <code>
     * $parser = new Console_CommandLine();
     * // add an array argument
     * $parser->addArgument('input_files', array('multiple'=>true));
     * // add a simple argument
     * $parser->addArgument('output_file');
     * $result = $parser->parse();
     * print_r($result->args['input_files']);
     * print_r($result->args['output_file']);
     * // will print:
     * // array('file1', 'file2')
     * // 'file3'
     * // if the command line was:
     * // myscript.php file1 file2 file3
     * </code>
     *
     * In a terminal, the help will be displayed like this:
     * <code>
     * $ myscript.php install -h
     * Usage: myscript.php <input_files...> <output_file>
     * </code>
     *
     * @param mixed $name   A string containing the argument name or an
     *                      instance of Console_CommandLine_Argument
     * @param array $params An array containing the argument attributes
     *
     * @return Console_CommandLine_Argument the added argument
     * @see Console_CommandLine_Argument
     */
    public function addArgument($name, $params = array())
    {
        if ($name instanceof Console_CommandLine_Argument) {
            $argument = $name;
        } else {
            include_once 'Console/CommandLine/Argument.php';
            $argument = new Console_CommandLine_Argument($name, $params);
        }
        $argument->validate();
        $this->args[$argument->name] = $argument;
        return $argument;
    }

    // }}}
    // addCommand() {{{

    /**
     * Adds a sub-command to the command line parser.
     *
     * Adds a command with the given $name to the parser and returns the 
     * Console_CommandLine_Command instance, you can then populate the command
     * with options, configure it, etc... like you would do for the main parser
     * because the class Console_CommandLine_Command inherits from
     * Console_CommandLine.
     *
     * An example:
     * <code>
     * $parser = new Console_CommandLine();
     * $install_cmd = $parser->addCommand('install');
     * $install_cmd->addOption(
     *     'verbose',
     *     array(
     *         'short_name'  => '-v',
     *         'long_name'   => '--verbose',
     *         'description' => 'be noisy when installing stuff',
     *         'action'      => 'StoreTrue'
     *      )
     * );
     * $parser->parse();
     * </code>
     * Then in a terminal:
     * <code>
     * $ myscript.php install -h
     * Usage: myscript.php install [options]
     *
     * Options:
     *   -h, --help     display this help message and exit
     *   -v, --verbose  be noisy when installing stuff
     *
     * $ myscript.php install --verbose
     * Installing whatever...
     * $
     * </code>
     *
     * @param mixed $name   A string containing the command name or an
     *                      instance of Console_CommandLine_Command
     * @param array $params An array containing the command attributes
     *
     * @return Console_CommandLine_Command the added subcommand
     * @see    Console_CommandLine_Command
     */
    public function addCommand($name, $params = array())
    {
        if ($name instanceof Console_CommandLine_Command) {
            $command = $name;
        } else {
            include_once 'Console/CommandLine/Command.php';
            $params['name'] = $name;
            $command        = new Console_CommandLine_Command($params);
            // some properties must cascade to the child command if not 
            // passed explicitely. This is done only in this case, because if 
            // we have a Command object we have no way to determine if theses 
            // properties have already been set
            $cascade = array(
                'add_help_option',
                'add_version_option',
                'outputter',
                'message_provider',
                'force_posix',
                'force_options_defaults'
            );
            foreach ($cascade as $property) {
                if (!isset($params[$property])) {
                    $command->$property = $this->$property;
                }
            }
            if (!isset($params['renderer'])) {
                $renderer          = clone $this->renderer;
                $renderer->parser  = $command;
                $command->renderer = $renderer;
            }
        }
        $command->parent = $this;
        $this->commands[$command->name] = $command;
        return $command;
    }

    // }}}
    // addOption() {{{

    /**
     * Adds an option to the command line parser and returns it.
     *
     * Adds an option with the name $name and set its attributes with the
     * array $params, then return the Console_CommandLine_Option instance
     * created.
     * The method accepts another form: you can directly pass a 
     * Console_CommandLine_Option object as the sole argument, this allows
     * you to contruct the option separately, in order to reuse it in different
     * command line parsers or commands for example.
     *
     * Example:
     * <code>
     * $parser = new Console_CommandLine();
     * $parser->addOption('path', array(
     *     'short_name'  => '-p',  // a short name
     *     'long_name'   => '--path', // a long name
     *     'description' => 'path to the dir', // a description msg
     *     'action'      => 'StoreString',
     *     'default'     => '/tmp' // a default value
     * ));
     * $parser->parse();
     * </code>
     *
     * In a terminal, the help will be displayed like this:
     * <code>
     * $ myscript.php --help
     * Usage: myscript.php [options]
     *
     * Options:
     *   -h, --help  display this help message and exit
     *   -p, --path  path to the dir
     *
     * </code>
     *
     * Various methods to specify an option, these 3 commands are equivalent:
     * <code>
     * $ myscript.php --path=some/path
     * $ myscript.php -p some/path
     * $ myscript.php -psome/path
     * </code>
     *
     * @param mixed $name   A string containing the option name or an
     *                      instance of Console_CommandLine_Option
     * @param array $params An array containing the option attributes
     *
     * @return Console_CommandLine_Option The added option
     * @see    Console_CommandLine_Option
     */
    public function addOption($name, $params = array())
    {
        include_once 'Console/CommandLine/Option.php';
        if ($name instanceof Console_CommandLine_Option) {
            $opt = $name;
        } else {
            $opt = new Console_CommandLine_Option($name, $params);
        }
        $opt->validate();
        if ($this->force_options_defaults) {
            $opt->setDefaults();
        }
        $this->options[$opt->name] = $opt;
        if (!empty($opt->choices) && $opt->add_list_option) {
            $this->addOption('list_' . $opt->name, array(
                'long_name'     => '--list-' . $opt->name,
                'description'   => $this->message_provider->get(
                    'LIST_OPTION_MESSAGE',
                    array('name' => $opt->name)
                ),
                'action'        => 'List',
                'action_params' => array('list' => $opt->choices),
            ));
        }
        return $opt;
    }

    // }}}
    // displayError() {{{

    /**
     * Displays an error to the user via stderr and exit with $exitCode if its
     * value is not equals to false.
     *
     * @param string $error    The error message
     * @param int    $exitCode The exit code number (default: 1). If set to
     *                         false, the exit() function will not be called
     *
     * @return void
     */
    public function displayError($error, $exitCode = 1)
    {
        $this->outputter->stderr($this->renderer->error($error));
        if ($exitCode !== false) {
            exit($exitCode);
        }
    }

    // }}}
    // displayUsage() {{{

    /**
     * Displays the usage help message to the user via stdout and exit with
     * $exitCode if its value is not equals to false.
     *
     * @param int $exitCode The exit code number (default: 0). If set to
     *                      false, the exit() function will not be called
     *
     * @return void
     */
    public function displayUsage($exitCode = 0)
    {
        $this->outputter->stdout($this->renderer->usage());
        if ($exitCode !== false) {
            exit($exitCode);
        }
    }

    // }}}
    // displayVersion() {{{

    /**
     * Displays the program version to the user via stdout and exit with
     * $exitCode if its value is not equals to false.
     *
     *
     * @param int $exitCode The exit code number (default: 0). If set to
     *                      false, the exit() function will not be called
     *
     * @return void
     */
    public function displayVersion($exitCode = 0)
    {
        $this->outputter->stdout($this->renderer->version());
        if ($exitCode !== false) {
            exit($exitCode);
        }
    }

    // }}}
    // findOption() {{{

    /**
     * Finds the option that matches the given short_name (ex: -v), long_name
     * (ex: --verbose) or name (ex: verbose).
     *
     * @param string $str The option identifier
     *
     * @return mixed A Console_CommandLine_Option instance or false
     */
    public function findOption($str)
    {
        $str = trim($str);
        if ($str === '') {
            return false;
        }
        $matches = array();
        foreach ($this->options as $opt) {
            if ($opt->short_name == $str || $opt->long_name == $str ||
                $opt->name == $str) {
                // exact match
                return $opt;
            }
            if (substr($opt->long_name, 0, strlen($str)) === $str) {
                // abbreviated long option
                $matches[] = $opt;
            }
        }
        if ($count = count($matches)) {
            if ($count > 1) {
                $matches_str = '';
                $padding     = '';
                foreach ($matches as $opt) {
                    $matches_str .= $padding . $opt->long_name;
                    $padding      = ', ';
                }
                throw Console_CommandLine_Exception::factory(
                    'OPTION_AMBIGUOUS',
                    array('name' => $str, 'matches' => $matches_str),
                    $this,
                    $this->messages
                );
            }
            return $matches[0];
        }
        return false;
    }
    // }}}
    // registerAction() {{{

    /**
     * Registers a custom action for the parser, an example:
     *
     * <code>
     *
     * // in this example we create a "range" action:
     * // the user will be able to enter something like:
     * // $ <program> -r 1,5
     * // and in the result we will have:
     * // $result->options['range']: array(1, 5)
     *
     * require_once 'Console/CommandLine.php';
     * require_once 'Console/CommandLine/Action.php';
     *
     * class ActionRange extends Console_CommandLine_Action
     * {
     *     public function execute($value=false, $params=array())
     *     {
     *         $range = explode(',', str_replace(' ', '', $value));
     *         if (count($range) != 2) {
     *             throw new Exception(sprintf(
     *                 'Option "%s" must be 2 integers separated by a comma',
     *                 $this->option->name
     *             ));
     *         }
     *         $this->setResult($range);
     *     }
     * }
     * // then we can register our action
     * Console_CommandLine::registerAction('Range', 'ActionRange');
     * // and now our action is available !
     * $parser = new Console_CommandLine();
     * $parser->addOption('range', array(
     *     'short_name'  => '-r',
     *     'long_name'   => '--range',
     *     'action'      => 'Range', // note our custom action
     *     'description' => 'A range of two integers separated by a comma'
     * ));
     * // etc...
     *
     * </code>
     *
     * @param string $name  The name of the custom action
     * @param string $class The class name of the custom action
     *
     * @return void
     */
    public static function registerAction($name, $class) 
    {
        if (!isset(self::$actions[$name])) {
            if (!class_exists($class)) {
                self::triggerError('action_class_does_not_exists',
                    E_USER_ERROR,
                    array('{$name}' => $name, '{$class}' => $class));
            }
            self::$actions[$name] = array($class, false);
        }
    }

    // }}}
    // triggerError() {{{

    /**
     * A wrapper for programming errors triggering.
     *
     * @param string $msgId  Identifier of the message
     * @param int    $level  The php error level
     * @param array  $params An array of search=>replaces entries
     *
     * @return void
     * @todo remove Console::triggerError() and use exceptions only
     */
    public static function triggerError($msgId, $level, $params=array()) 
    {
        if (isset(self::$errors[$msgId])) {
            $msg = str_replace(array_keys($params),
                array_values($params), self::$errors[$msgId]); 
            trigger_error($msg, $level);
        } else {
            trigger_error('unknown error', $level);
        }
    }

    // }}}
    // parse() {{{

    /**
     * Parses the command line arguments and returns a
     * Console_CommandLine_Result instance.
     *
     * @param integer $userArgc Number of arguments (optional)
     * @param array   $userArgv Array containing arguments (optional)
     *
     * @return Console_CommandLine_Result The result instance
     * @throws Exception on user errors
     */
    public function parse($userArgc=null, $userArgv=null)
    {
        $this->_lastopt  = false;
        $this->_stopflag = false;

        $this->addBuiltinOptions();
        if ($userArgc !== null && $userArgv !== null) {
            $argc = $userArgc;
            $argv = $userArgv;
        } else {
            list($argc, $argv) = $this->getArgcArgv();
        }
        // build an empty result
        include_once 'Console/CommandLine/Result.php';
        $result = new Console_CommandLine_Result();
        if (!($this instanceof Console_CommandLine_Command)) {
            // remove script name if we're not in a subcommand
            array_shift($argv);
            $argc--;
        }
        // will contain arguments
        $args = array();
        foreach ($this->options as $name=>$option) {
            $result->options[$name] = $option->default;
        }
        // parse command line tokens
        while ($argc--) {
            $token = array_shift($argv);
            try {
                if ($cmd = $this->_getSubCommand($token)) {
                    $result->command_name = $cmd->name;
                    $result->command      = $cmd->parse($argc, $argv);
                    break;
                } else {
                    $this->parseToken($token, $result, $args, $argc);
                }
            } catch (Exception $exc) {
                throw $exc;
            }
        }
        // Parse a null token to allow any undespatched actions to be despatched.
        $this->parseToken(null, $result, $args, 0);
        // Check if an invalid subcommand was specified. If there are
        // subcommands and no arguments, but an argument was provided, it is
        // an invalid subcommand.
        if (   count($this->commands) > 0
            && count($this->args) === 0
            && count($args) > 0
        ) {
            throw Console_CommandLine_Exception::factory(
                'INVALID_SUBCOMMAND',
                array('command' => $args[0]),
                $this,
                $this->messages
            );
        }
        // if subcommand_required is set to true we must check that we have a
        // subcommand.
        if (   count($this->commands)
            && $this->subcommand_required
            && !$result->command_name
        ) {
            throw Console_CommandLine_Exception::factory(
                'SUBCOMMAND_REQUIRED',
                array('commands' => implode(array_keys($this->commands), ', ')),
                $this,
                $this->messages
            );
        }
        // minimum argument number check
        $argnum = 0;
        foreach ($this->args as $name=>$arg) {
            if (!$arg->optional) {
                $argnum++;
            }
        }
        if (count($args) < $argnum) {
            throw Console_CommandLine_Exception::factory(
                'ARGUMENT_REQUIRED',
                array('argnum' => $argnum, 'plural' => $argnum>1 ? 's': ''),
                $this,
                $this->messages
            );
        }
        // handle arguments
        $c = count($this->args);
        foreach ($this->args as $name=>$arg) {
            $c--;
            if ($arg->multiple) {
                $result->args[$name] = $c ? array_splice($args, 0, -$c) : $args;
            } else {
                $result->args[$name] = array_shift($args);
            }
            if (!$result->args[$name] && $arg->optional && $arg->default) {
                $result->args[$name] = $arg->default;
            }
            // check value is in argument choices
            if (!empty($this->args[$name]->choices)) {
                foreach ($result->args[$name] as $value) {
                    if (!in_array($value, $arg->choices)) {
                        throw Console_CommandLine_Exception::factory(
                            'ARGUMENT_VALUE_NOT_VALID',
                            array(
                                'name'    => $name,
                                'choices' => implode('", "', $arg->choices),
                                'value'   => implode(' ', $result->args[$name]),
                            ),
                            $this,
                            $arg->messages
                        );
                    }
                }
            }
        }
        // dispatch deferred options
        foreach ($this->_dispatchLater as $optArray) {
            $optArray[0]->dispatchAction($optArray[1], $optArray[2], $this);
        }
        return $result;
    }

    // }}}
    // parseToken() {{{

    /**
     * Parses the command line token and modifies *by reference* the $options
     * and $args arrays.
     *
     * @param string $token  The command line token to parse
     * @param object $result The Console_CommandLine_Result instance
     * @param array  &$args  The argv array
     * @param int    $argc   Number of lasting args
     *
     * @return void
     * @access protected
     * @throws Exception on user errors
     */
    protected function parseToken($token, $result, &$args, $argc)
    {
        $last  = $argc === 0;
        if (!$this->_stopflag && $this->_lastopt) {
            if (strlen($token) > ($this->avoid_reading_stdin ? 1 : 0) &&
                substr($token, 0, 1) == '-') {
                if ($this->_lastopt->argument_optional) {
                    $this->_dispatchAction($this->_lastopt, '', $result);
                    if ($this->_lastopt->action != 'StoreArray') {
                        $this->_lastopt = false;
                    }
                } else if (isset($result->options[$this->_lastopt->name])) {
                    // case of an option that expect a list of args
                    $this->_lastopt = false;
                } else {
                    throw Console_CommandLine_Exception::factory(
                        'OPTION_VALUE_REQUIRED',
                        array('name' => $this->_lastopt->name),
                        $this,
                        $this->messages
                    );
                }
            } else {
                // when a StoreArray option is positioned last, the behavior
                // is to consider that if there's already an element in the
                // array, and the commandline expects one or more args, we
                // leave last tokens to arguments
                if ($this->_lastopt->action == 'StoreArray'
                    && !empty($result->options[$this->_lastopt->name])
                    && count($this->args) > ($argc + count($args))
                ) {
                    if (!is_null($token)) {
                        $args[] = $token;
                    }
                    return;
                }
                if (!is_null($token) || $this->_lastopt->action == 'Password') {
                    $this->_dispatchAction($this->_lastopt, $token, $result);
                }
                if ($this->_lastopt->action != 'StoreArray') {
                    $this->_lastopt = false;
                }
                return;
            }
        }
        if (!$this->_stopflag && substr($token, 0, 2) == '--') {
            // a long option
            $optkv = explode('=', $token, 2);
            if (trim($optkv[0]) == '--') {
                // the special argument "--" forces in all cases the end of 
                // option scanning.
                $this->_stopflag = true;
                return;
            }
            $opt = $this->findOption($optkv[0]);
            if (!$opt) {
                throw Console_CommandLine_Exception::factory(
                    'OPTION_UNKNOWN',
                    array('name' => $optkv[0]),
                    $this,
                    $this->messages
                );
            }
            $value = isset($optkv[1]) ? $optkv[1] : false;
            if (!$opt->expectsArgument() && $value !== false) {
                throw Console_CommandLine_Exception::factory(
                    'OPTION_VALUE_UNEXPECTED',
                    array('name' => $opt->name, 'value' => $value),
                    $this,
                    $this->messages
                );
            }
            if ($opt->expectsArgument() && $value === false) {
                // maybe the long option argument is separated by a space, if 
                // this is the case it will be the next arg
                if ($last && !$opt->argument_optional) {
                    throw Console_CommandLine_Exception::factory(
                        'OPTION_VALUE_REQUIRED',
                        array('name' => $opt->name),
                        $this,
                        $this->messages
                    );
                }
                // we will have a value next time
                $this->_lastopt = $opt;
                return;
            }
            if ($opt->action == 'StoreArray') {
                $this->_lastopt = $opt;
            }
            $this->_dispatchAction($opt, $value, $result);
        } else if (!$this->_stopflag &&
                   strlen($token) > ($this->avoid_reading_stdin ? 1 : 0) &&
                   substr($token, 0, 1) == '-') {
            // a short option
            $optname = substr($token, 0, 2);
            if ($optname == '-' && !$this->avoid_reading_stdin) {
                // special case of "-": try to read stdin
                $args[] = file_get_contents('php://stdin');
                return;
            }
            $opt = $this->findOption($optname);
            if (!$opt) {
                throw Console_CommandLine_Exception::factory(
                    'OPTION_UNKNOWN',
                    array('name' => $optname),
                    $this,
                    $this->messages
                );
            }
            // parse other options or set the value
            // in short: handle -f<value> and -f <value>
            $next = substr($token, 2, 1);
            // check if we must wait for a value
            if (!$next) {
                if ($opt->expectsArgument()) {
                    if ($last && !$opt->argument_optional) {
                        throw Console_CommandLine_Exception::factory(
                            'OPTION_VALUE_REQUIRED',
                            array('name' => $opt->name),
                            $this,
                            $this->messages
                        );
                    }
                    // we will have a value next time
                    $this->_lastopt = $opt;
                    return;
                }
                $value = false;
            } else {
                if (!$opt->expectsArgument()) { 
                    if ($nextopt = $this->findOption('-' . $next)) {
                        $this->_dispatchAction($opt, false, $result);
                        $this->parseToken('-' . substr($token, 2), $result,
                            $args, $last);
                        return;
                    } else {
                        throw Console_CommandLine_Exception::factory(
                            'OPTION_UNKNOWN',
                            array('name' => $next),
                            $this,
                            $this->messages
                        );
                    }
                }
                if ($opt->action == 'StoreArray') {
                    $this->_lastopt = $opt;
                }
                $value = substr($token, 2);
            }
            $this->_dispatchAction($opt, $value, $result);
        } else {
            // We have an argument.
            // if we are in POSIX compliant mode, we must set the stop flag to 
            // true in order to stop option parsing.
            if (!$this->_stopflag && $this->force_posix) {
                $this->_stopflag = true;
            }
            if (!is_null($token)) {
                $args[] = $token;
            }
        }
    }

    // }}}
    // addBuiltinOptions() {{{

    /**
     * Adds the builtin "Help" and "Version" options if needed.
     *
     * @return void
     */
    public function addBuiltinOptions()
    {
        if ($this->add_help_option) {
            $helpOptionParams = array(
                'long_name'   => '--help',
                'description' => 'show this help message and exit',
                'action'      => 'Help'   
            );
            if (!($option = $this->findOption('-h')) || $option->action == 'Help') {
                // short name is available, take it
                $helpOptionParams['short_name'] = '-h';
            }
            $this->addOption('help', $helpOptionParams);
        }
        if ($this->add_version_option && !empty($this->version)) {
            $versionOptionParams = array(
                'long_name'   => '--version',
                'description' => 'show the program version and exit',
                'action'      => 'Version'   
            );
            if (!$this->findOption('-v')) {
                // short name is available, take it
                $versionOptionParams['short_name'] = '-v';
            }
            $this->addOption('version', $versionOptionParams);
        }
    } 

    // }}}
    // getArgcArgv() {{{

    /**
     * Tries to return an array containing argc and argv, or trigger an error
     * if it fails to get them.
     *
     * @return array The argc/argv array
     * @throws Console_CommandLine_Exception 
     */
    protected function getArgcArgv()
    {
        if (php_sapi_name() != 'cli') {
            // we have a web request
            $argv = array($this->name);
            if (isset($_REQUEST)) {
                foreach ($_REQUEST as $key => $value) {
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $opt = $this->findOption($key);
                    if ($opt instanceof Console_CommandLine_Option) {
                        // match a configured option
                        $argv[] = $opt->short_name ? 
                            $opt->short_name : $opt->long_name;
                        foreach ($value as $v) {
                            if ($opt->expectsArgument()) {
                                $argv[] = isset($_REQUEST[$key]) ? urldecode($v) : $v;
                            } else if ($v == '0' || $v == 'false') {
                                array_pop($argv);
                            }
                        }
                    } else if (isset($this->args[$key])) {
                        // match a configured argument
                        foreach ($value as $v) {
                            $argv[] = isset($_REQUEST[$key]) ? urldecode($v) : $v;
                        }
                    }
                }
            }
            return array(count($argv), $argv);
        }
        if (isset($argc) && isset($argv)) {
            // case of register_argv_argc = 1
            return array($argc, $argv);
        }
        if (isset($_SERVER['argc']) && isset($_SERVER['argv'])) {
            return array($_SERVER['argc'], $_SERVER['argv']);
        }
        return array(0, array());
    }

    // }}}
    // _dispatchAction() {{{

    /**
     * Dispatches the given option or store the option to dispatch it later.
     *
     * @param Console_CommandLine_Option $option The option instance
     * @param string                     $token  Command line token to parse
     * @param Console_CommandLine_Result $result The result instance
     *
     * @return void
     */
    private function _dispatchAction($option, $token, $result)
    {
        if ($option->action == 'Password') {
            $this->_dispatchLater[] = array($option, $token, $result);
        } else {
            $option->dispatchAction($token, $result, $this);
        }
    }
    // }}}
    // _getSubCommand() {{{

    /**
     * Tries to return the subcommand that matches the given token or returns
     * false if no subcommand was found.
     *
     * @param string $token Current command line token
     *
     * @return mixed An instance of Console_CommandLine_Command or false
     */
    private function _getSubCommand($token)
    {
        foreach ($this->commands as $cmd) {
            if ($cmd->name == $token || in_array($token, $cmd->aliases)) {
                return $cmd;
            }
        }
        return false;
    }

    // }}}
}
