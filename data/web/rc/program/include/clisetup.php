<?php

/**
 +-----------------------------------------------------------------------+
 | program/include/clisetup.php                                          |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2010-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Setup the command line environment and provide some utitlity        |
 |   functions.                                                          |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

if (php_sapi_name() != 'cli') {
    die('Not on the "shell" (php-cli).');
}

require_once INSTALL_PATH . 'program/include/iniset.php';

// Unset max. execution time limit, set to 120 seconds in iniset.php
@set_time_limit(0);

$rcmail = rcmail::get_instance();
$rcmail->output = new rcmail_output_cli();
