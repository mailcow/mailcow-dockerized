#!/usr/bin/env php
<?php

/*
 +-----------------------------------------------------------------------+
 | bin/deluser.sh                                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2014, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Utility script to remove all data related to a certain user         |
 |   from the local database.                                            |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <thomas@roundcube.net>                       |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require_once INSTALL_PATH . 'program/include/clisetup.php';

function print_usage()
{
    print "Usage: deluser.sh [--host=mail_host] username\n";
    print "--host=HOST  The IMAP hostname or IP the given user is related to\n";
}

function _die($msg, $usage=false)
{
    fputs(STDERR, $msg . "\n");
    if ($usage) print_usage();
    exit(1);
}

$rcmail = rcube::get_instance();

// get arguments
$args     = rcube_utils::get_opt(array('h' => 'host'));
$username = trim($args[0]);

if (empty($username)) {
    _die("Missing required parameters", true);
}

if (empty($args['host'])) {
    $hosts = $rcmail->config->get('default_host', '');
    if (is_string($hosts)) {
        $args['host'] = $hosts;
    }
    else if (is_array($hosts) && count($hosts) == 1) {
        $args['host'] = reset($hosts);
    }
    else {
        _die("Specify a host name", true);
    }

    // host can be a URL like tls://192.168.12.44
    $host_url = parse_url($args['host']);
    if ($host_url['host']) {
        $args['host'] = $host_url['host'];
    }
}

// connect to DB
$db = $rcmail->get_dbh();
$db->db_connect('w');
$transaction = false;

if (!$db->is_connected() || $db->is_error()) {
    _die("No DB connection\n" . $db->is_error());
}

// find user in loca database
$user = rcube_user::query($username, $args['host']);

if (!$user) {
    die("User not found.\n");
}

// inform plugins about approaching user deletion
$plugin = $rcmail->plugins->exec_hook('user_delete_prepare', array('user' => $user, 'username' => $username, 'host' => $args['host']));

// let plugins cleanup their own user-related data
if (!$plugin['abort']) {
    $transaction = $db->startTransaction();
    $plugin = $rcmail->plugins->exec_hook('user_delete', $plugin);
}

if ($plugin['abort']) {
    if ($transaction) {
        $db->rollbackTransaction();
    }
    _die("User deletion aborted by plugin");
}

// deleting the user record should be sufficient due to ON DELETE CASCADE foreign key references
// but not all database backends actually support this so let's do it by hand
foreach (array('identities','contacts','contactgroups','dictionary','cache','cache_index','cache_messages','cache_thread','searches','users') as $table) {
    $db->query('DELETE FROM ' . $db->table_name($table, true) . ' WHERE `user_id` = ?', $user->ID);
}

if ($db->is_error()) {
    $rcmail->plugins->exec_hook('user_delete_rollback', $plugin);
    _die("DB error occurred: " . $db->is_error());
}
else {
    // inform plugins about executed user deletion
    $plugin = $rcmail->plugins->exec_hook('user_delete_commit', $plugin);

    if ($plugin['abort']) {
        unset($plugin['abort']);
        $db->rollbackTransaction();
        $rcmail->plugins->exec_hook('user_delete_rollback', $plugin);
    }
    else {
        $db->endTransaction();
        echo "Successfully deleted user $user->ID\n";
    }
}

