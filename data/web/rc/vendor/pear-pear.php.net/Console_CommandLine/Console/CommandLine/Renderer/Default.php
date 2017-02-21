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
 * The renderer interface.
 */
require_once 'Console/CommandLine/Renderer.php';

/**
 * Console_CommandLine default renderer.
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
class Console_CommandLine_Renderer_Default implements Console_CommandLine_Renderer
{
    // Properties {{{

    /**
     * Integer that define the max width of the help text.
     *
     * @var integer $line_width Line width
     */
    public $line_width = 75;

    /**
     * Integer that define the max width of the help text.
     *
     * @var integer $line_width Line width
     */
    public $options_on_different_lines = false;

    /**
     * An instance of Console_CommandLine.
     *
     * @var Console_CommandLine $parser The parser
     */
    public $parser = false;

    // }}}
    // __construct() {{{

    /**
     * Constructor.
     *
     * @param object $parser A Console_CommandLine instance
     *
     * @return void
     */
    public function __construct($parser = false) 
    {
        $this->parser = $parser;
    }

    // }}}
    // usage() {{{

    /**
     * Returns the full usage message.
     *
     * @return string The usage message
     */
    public function usage()
    {
        $ret = '';
        if (!empty($this->parser->description)) { 
            $ret .= $this->description() . "\n\n";
        }
        $ret .= $this->usageLine() . "\n";
        if (count($this->parser->commands) > 0) {
            $ret .= $this->commandUsageLine() . "\n";
        }
        if (count($this->parser->options) > 0) {
            $ret .= "\n" . $this->optionList() . "\n";
        }
        if (count($this->parser->args) > 0) {
            $ret .= "\n" . $this->argumentList() . "\n";
        }
        if (count($this->parser->commands) > 0) {
            $ret .= "\n" . $this->commandList() . "\n";
        }
        $ret .= "\n";
        return $ret;
    }
    // }}}
    // error() {{{

    /**
     * Returns a formatted error message.
     *
     * @param string $error The error message to format
     *
     * @return string The error string
     */
    public function error($error)
    {
        $ret = 'Error: ' . $error . "\n";
        if ($this->parser->add_help_option) {
            $name = $this->name();
            $ret .= $this->wrap($this->parser->message_provider->get('PROG_HELP_LINE',
                array('progname' => $name))) . "\n";
            if (count($this->parser->commands) > 0) {
                $ret .= $this->wrap($this->parser->message_provider->get('COMMAND_HELP_LINE',
                    array('progname' => $name))) . "\n";
            }
        }
        return $ret;
    }

    // }}}
    // version() {{{

    /**
     * Returns the program version string.
     *
     * @return string The version string
     */
    public function version()
    {
        return $this->parser->message_provider->get('PROG_VERSION_LINE', array(
            'progname' => $this->name(),
            'version'  => $this->parser->version
        )) . "\n";
    }

    // }}}
    // name() {{{

    /**
     * Returns the full name of the program or the sub command
     *
     * @return string The name of the program
     */
    protected function name()
    {
        $name   = $this->parser->name;
        $parent = $this->parser->parent;
        while ($parent) {
            if (count($parent->options) > 0) {
                $name = '[' 
                    . strtolower($this->parser->message_provider->get('OPTION_WORD',
                          array('plural' => 's'))) 
                    . '] ' . $name;
            }
            $name = $parent->name . ' ' . $name;
            $parent = $parent->parent;
        }
        return $this->wrap($name);
    }

    // }}}
    // description() {{{

    /**
     * Returns the command line description message.
     *
     * @return string The description message
     */
    protected function description()
    {
        return $this->wrap($this->parser->description);
    }

    // }}}
    // usageLine() {{{

    /**
     * Returns the command line usage message
     *
     * @return string the usage message
     */
    protected function usageLine()
    {
        $usage = $this->parser->message_provider->get('USAGE_WORD') . ":\n";
        $ret   = $usage . '  ' . $this->name();
        if (count($this->parser->options) > 0) {
            $ret .= ' [' 
                . strtolower($this->parser->message_provider->get('OPTION_WORD'))
                . ']';
        }
        if (count($this->parser->args) > 0) {
            foreach ($this->parser->args as $name=>$arg) {
                $arg_str = $arg->help_name;
                if ($arg->multiple) {
                    $arg_str .= '1 ' . $arg->help_name . '2 ...';
                }
                if ($arg->optional) {
                    $arg_str = '[' . $arg_str . ']';
                }
                $ret .= ' ' . $arg_str;
            }
        }
        return $this->columnWrap($ret, 2);
    }

    // }}}
    // commandUsageLine() {{{

    /**
     * Returns the command line usage message for subcommands.
     *
     * @return string The usage line
     */
    protected function commandUsageLine()
    {
        if (count($this->parser->commands) == 0) {
            return '';
        }
        $ret = '  ' . $this->name();
        if (count($this->parser->options) > 0) {
            $ret .= ' [' 
                . strtolower($this->parser->message_provider->get('OPTION_WORD'))
                . ']';
        }
        $ret       .= " <command>";
        $hasArgs    = false;
        $hasOptions = false;
        foreach ($this->parser->commands as $command) {
            if (!$hasArgs && count($command->args) > 0) {
                $hasArgs = true;
            }
            if (!$hasOptions && ($command->add_help_option || 
                $command->add_version_option || count($command->options) > 0)) {
                $hasOptions = true;
            }
        }
        if ($hasOptions) {
            $ret .= ' [options]';
        }
        if ($hasArgs) {
            $ret .= ' [args]';
        }
        return $this->columnWrap($ret, 2);
    }

    // }}}
    // argumentList() {{{

    /**
     * Render the arguments list that will be displayed to the user, you can 
     * override this method if you want to change the look of the list.
     *
     * @return string The formatted argument list
     */
    protected function argumentList()
    {
        $col  = 0;
        $args = array();
        foreach ($this->parser->args as $arg) {
            $argstr = '  ' . $arg->toString();
            $args[] = array($argstr, $arg->description);
            $ln     = strlen($argstr);
            if ($col < $ln) {
                $col = $ln;
            }
        }
        $ret = $this->parser->message_provider->get('ARGUMENT_WORD') . ":";
        foreach ($args as $arg) {
            $text = str_pad($arg[0], $col) . '  ' . $arg[1];
            $ret .= "\n" . $this->columnWrap($text, $col+2);
        }
        return $ret;
    }

    // }}}
    // optionList() {{{

    /**
     * Render the options list that will be displayed to the user, you can 
     * override this method if you want to change the look of the list.
     *
     * @return string The formatted option list
     */
    protected function optionList()
    {
        $col     = 0;
        $options = array();
        foreach ($this->parser->options as $option) {
            $delim    = $this->options_on_different_lines ? "\n" : ', ';
            $optstr   = $option->toString($delim);
            $lines    = explode("\n", $optstr);
            $lines[0] = '  ' . $lines[0];
            if (count($lines) > 1) {
                $lines[1] = '  ' . $lines[1];
                $ln       = strlen($lines[1]);
            } else {
                $ln = strlen($lines[0]);
            }
            $options[] = array($lines, $option->description);
            if ($col < $ln) {
                $col = $ln;
            }
        }
        $ret = $this->parser->message_provider->get('OPTION_WORD') . ":";
        foreach ($options as $option) {
            if (count($option[0]) > 1) {
                $text = str_pad($option[0][1], $col) . '  ' . $option[1];
                $pre  = $option[0][0] . "\n";
            } else {
                $text = str_pad($option[0][0], $col) . '  ' . $option[1];
                $pre  = '';
            }
            $ret .= "\n" . $pre . $this->columnWrap($text, $col+2);
        }
        return $ret;
    }

    // }}}
    // commandList() {{{

    /**
     * Render the command list that will be displayed to the user, you can 
     * override this method if you want to change the look of the list.
     *
     * @return string The formatted subcommand list
     */
    protected function commandList()
    {

        $commands = array();
        $col      = 0;
        foreach ($this->parser->commands as $cmdname=>$command) {
            $cmdname    = '  ' . $cmdname;
            $commands[] = array($cmdname, $command->description, $command->aliases);
            $ln         = strlen($cmdname);
            if ($col < $ln) {
                $col = $ln;
            }
        }
        $ret = $this->parser->message_provider->get('COMMAND_WORD') . ":";
        foreach ($commands as $command) {
            $text = str_pad($command[0], $col) . '  ' . $command[1];
            if ($aliasesCount = count($command[2])) {
                $pad = '';
                $text .= ' (';
                $text .= $aliasesCount > 1 ? 'aliases: ' : 'alias: ';
                foreach ($command[2] as $alias) {
                    $text .= $pad . $alias;
                    $pad   = ', ';
                }
                $text .= ')';
            }
            $ret .= "\n" . $this->columnWrap($text, $col+2);
        }
        return $ret;
    }

    // }}}
    // wrap() {{{

    /**
     * Wraps the text passed to the method.
     *
     * @param string $text The text to wrap
     * @param int    $lw   The column width (defaults to line_width property)
     *
     * @return string The wrapped text
     */
    protected function wrap($text, $lw=null)
    {
        if ($this->line_width > 0) {
            if ($lw === null) {
                $lw = $this->line_width;
            }
            return wordwrap($text, $lw, "\n", false);
        }
        return $text;
    }

    // }}}
    // columnWrap() {{{

    /**
     * Wraps the text passed to the method at the specified width.
     *
     * @param string $text The text to wrap
     * @param int    $cw   The wrap width
     *
     * @return string The wrapped text
     */
    protected function columnWrap($text, $cw)
    {
        $tokens = explode("\n", $this->wrap($text));
        $ret    = $tokens[0];
        $text   = trim(substr($text, strlen($ret)));
        if (empty($text)) {
            return $ret;
        }

        $chunks = $this->wrap($text, $this->line_width - $cw);
        $tokens = explode("\n", $chunks);
        foreach ($tokens as $token) {
            if (!empty($token)) {
                $ret .= "\n" . str_repeat(' ', $cw) . $token;
            } else {
                $ret .= "\n";
            }
        }
        return $ret;
    }

    // }}}
}
