#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/updatedb.sh                                                       |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2010-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2010-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Update database schema                                              |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require_once INSTALL_PATH . 'program/include/clisetup.php';

// get arguments
$opts = rcube_utils::get_opt(array(
    'v' => 'version',
    'd' => 'dir',
    'p' => 'package',
));

if (empty($opts['dir'])) {
    rcube::raise_error("Database schema directory not specified (--dir).", false, true);
}
if (empty($opts['package'])) {
    rcube::raise_error("Database schema package name not specified (--package).", false, true);
}

rcmail_utils::db_update($opts['dir'], $opts['package'], $opts['version'], array('errors' => true));

?>
