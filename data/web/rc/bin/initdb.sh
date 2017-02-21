#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/initdb.sh                                                         |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2010-2015, The Roundcube Dev Team                       |
 | Copyright (C) 2010-2015, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Create database schema                                              |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require_once INSTALL_PATH . 'program/include/clisetup.php';

// get arguments
$opts = rcube_utils::get_opt(array(
    'd' => 'dir',
));

if (empty($opts['dir'])) {
    rcube::raise_error("Database schema directory not specified (--dir).", false, true);
}

// Check if directory exists
if (!file_exists($opts['dir'])) {
    rcube::raise_error("Specified database schema directory doesn't exist.", false, true);
}

rcmail_utils::db_init($opts['dir']);

?>
