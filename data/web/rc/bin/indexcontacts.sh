#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/indexcontacts.sh                                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2011, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Update the fulltext index for all contacts of the internal          |
 |   address book.                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require_once INSTALL_PATH.'program/include/clisetup.php';
ini_set('memory_limit', -1);

rcmail_utils::indexcontacts();

?>
