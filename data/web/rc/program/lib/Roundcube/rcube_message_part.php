<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Class representing a message part                                   |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Class representing a message part
 *
 * @package    Framework
 * @subpackage Storage
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 */
class rcube_message_part
{
    /**
     * Part MIME identifier
     *
     * @var string
     */
    public $mime_id = '';

    /**
     * Content main type
     *
     * @var string
     */
    public $ctype_primary = 'text';

    /**
     * Content subtype
     *
     * @var string
     */
    public $ctype_secondary = 'plain';

    /**
     * Complete content type
     *
     * @var string
     */
    public $mimetype = 'text/plain';

    /**
     * Part size in bytes
     *
     * @var int
     */
    public $size = 0;

    /**
     * Part headers
     *
     * @var array
     */
    public $headers = array();

    public $disposition  = '';
    public $filename     = '';
    public $encoding     = '8bit';
    public $charset      = '';
    public $d_parameters = array();
    public $ctype_parameters = array();


    /**
     * Clone handler.
     */
    function __clone()
    {
        if (isset($this->parts)) {
            foreach ($this->parts as $idx => $part) {
                if (is_object($part)) {
                    $this->parts[$idx] = clone $part;
                }
            }
        }
    }
}
