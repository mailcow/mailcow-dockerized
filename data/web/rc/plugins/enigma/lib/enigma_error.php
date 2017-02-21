<?php

/**
 +-------------------------------------------------------------------------+
 | Error class for the Enigma Plugin                                       |
 |                                                                         |
 | Copyright (C) 2010-2015 The Roundcube Dev Team                          |
 |                                                                         |
 | Licensed under the GNU General Public License version 3 or              |
 | any later version with exceptions for skins & plugins.                  |
 | See the README file for a full license statement.                       |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                              |
 +-------------------------------------------------------------------------+
*/

class enigma_error
{
    private $code;
    private $message;
    private $data = array();

    // error codes
    const OK          = 0;
    const INTERNAL    = 1;
    const NODATA      = 2;
    const KEYNOTFOUND = 3;
    const DELKEY      = 4;
    const BADPASS     = 5;
    const EXPIRED     = 6;
    const UNVERIFIED  = 7;


    function __construct($code = null, $message = '', $data = array())
    {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    function getCode()
    {
        return $this->code;
    }

    function getMessage()
    {
        return $this->message;
    }

    function getData($name)
    {
        return $name ? $this->data[$name] : $this->data;
    }
}
