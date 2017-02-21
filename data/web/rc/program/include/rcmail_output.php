<?php

/**
 +-----------------------------------------------------------------------+
 | program/include/rcmail_output.php                                     |
 |                                                                       |
 | This file is part of the Roundcube PHP suite                          |
 | Copyright (C) 2005-2012 The Roundcube Dev Team                        |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 | CONTENTS:                                                             |
 |   Abstract class for output generation                                |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Class for output generation
 *
 * @package    Webmail
 * @subpackage View
 */
abstract class rcmail_output extends rcube_output
{
    const JS_OBJECT_NAME = 'rcmail';
    const BLANK_GIF      = 'R0lGODlhDwAPAIAAAMDAwAAAACH5BAEAAAAALAAAAAAPAA8AQAINhI+py+0Po5y02otnAQA7';

    public $type      = 'html';
    public $ajax_call = false;
    public $framed    = false;

    protected $pagetitle       = '';
    protected $object_handlers = array();
    protected $devel_mode      = false;


    /**
     * Object constructor
     */
    public function __construct($task = null, $framed = false)
    {
        parent::__construct();

        $this->devel_mode = (bool) $this->config->get('devel_mode');
    }

    /**
     * Setter for page title
     *
     * @param string $title Page title
     */
    public function set_pagetitle($title)
    {
        $this->pagetitle = $title;
    }

    /**
     * Getter for the current skin path property
     */
    public function get_skin_path()
    {
        return $this->config->get('skin_path');
    }

    /**
     * Delete all stored env variables and commands
     */
    public function reset()
    {
        parent::reset();

        $this->object_handlers = array();
        $this->pagetitle = '';
    }

    /**
     * Call a client method
     *
     * @param string Method to call
     * @param ... Additional arguments
     */
    abstract function command();

    /**
     * Add a localized label to the client environment
     */
    abstract function add_label();

    /**
     * Register a template object handler
     *
     * @param string $name Object name
     * @param string $func Function name to call
     *
     * @return void
     */
    public function add_handler($name, $func)
    {
        $this->object_handlers[$name] = $func;
    }

    /**
     * Register a list of template object handlers
     *
     * @param array $handlers Hash array with object=>handler pairs
     *
     * @return void
     */
    public function add_handlers($handlers)
    {
        $this->object_handlers = array_merge($this->object_handlers, $handlers);
    }
}
