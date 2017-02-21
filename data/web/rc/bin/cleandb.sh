#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/cleandb.sh                                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2010-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Finally remove all db records marked as deleted some time ago       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require INSTALL_PATH.'program/include/clisetup.php';

if (!empty($_SERVER['argv'][1]))
    $days = intval($_SERVER['argv'][1]);
else
    $days = 7;

rcmail_utils::db_clean($days);

?>
