<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/utils/killcache.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2010, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Delete rows from cache tables                                       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Dennis P. Nikolaenko <dennis@nikolaenko.ru>                   |
 +-----------------------------------------------------------------------+
*/

// don't allow public access if not in devel_mode
if (!$RCMAIL->config->get('devel_mode')) {
    header("HTTP/1.0 401 Access denied");
    die("Access denied!");
}

// @TODO: transaction here (if supported by DB) would be a good thing
$res = $RCMAIL->db->query("DELETE FROM " . $RCMAIL->db->table_name('cache', true));
if ($err = $RCMAIL->db->is_error($res)) {
    exit($err);
}

$res = $RCMAIL->db->query("DELETE FROM " . $RCMAIL->db->table_name('cache_shared', true));
if ($err = $RCMAIL->db->is_error($res)) {
    exit($err);
}

$res = $RCMAIL->db->query("DELETE FROM " . $RCMAIL->db->table_name('cache_messages', true));
if ($err = $RCMAIL->db->is_error($res)) {
    exit($err);
}

$res = $RCMAIL->db->query("DELETE FROM " . $RCMAIL->db->table_name('cache_index', true));
if ($err = $RCMAIL->db->is_error($res)) {
    exit($err);
}

$res = $RCMAIL->db->query("DELETE FROM " . $RCMAIL->db->table_name('cache_thread', true));
if ($err = $RCMAIL->db->is_error($res)) {
    exit($err);
}

echo "Cache cleared\n";
exit;
