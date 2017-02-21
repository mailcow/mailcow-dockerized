#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/gc.sh                                                             |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2013, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Trigger garbage collecting routines manually (e.g. via cronjob)     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require INSTALL_PATH.'program/include/clisetup.php';

$rcmail = rcube::get_instance();

$session_driver   = $rcmail->config->get('session_storage', 'db');
$session_lifetime = $rcmail->config->get('session_lifetime', 0) * 60 * 2;

// Clean expired SQL sessions
if ($session_driver == 'db' && $session_lifetime) {
    $db = $rcmail->get_dbh();
    $db->query("DELETE FROM " . $db->table_name('session')
        . " WHERE changed < " . $db->now(-$session_lifetime));
}

// Clean caches and temp directory
$rcmail->gc();
