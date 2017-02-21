<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/utils/save_pref.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Save preferences setting in database                                |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

$name     = rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST);
$value    = rcube_utils::get_input_value('_value', rcube_utils::INPUT_POST);
$sessname = rcube_utils::get_input_value('_session', rcube_utils::INPUT_POST);

// Whitelisted preferences and session variables, others
// can be added by plugins
$whitelist = array(
    'list_cols',
    'collapsed_folders',
    'collapsed_abooks',
);
$whitelist_sess = array(
    'list_attrib/columns',
);

$whitelist      = array_merge($whitelist, $RCMAIL->plugins->allowed_prefs);
$whitelist_sess = array_merge($whitelist_sess, $RCMAIL->plugins->allowed_session_prefs);

if (!in_array($name, $whitelist) || ($sessname && !in_array($sessname, $whitelist_sess))) {
    rcube::raise_error(array('code' => 500, 'type' => 'php',
        'file' => __FILE__, 'line' => __LINE__,
        'message' => sprintf("Hack attempt detected (user: %s)", $RCMAIL->get_user_name())),
        true, false);

    $OUTPUT->reset();
    $OUTPUT->send();
}

// save preference value
$RCMAIL->user->save_prefs(array($name => $value));

// update also session if requested
if ($sessname) {
    // Support multidimensional arrays...
    $vars = explode('/', $sessname);

    // ... up to 3 levels
    if (count($vars) == 1)
        $_SESSION[$vars[0]] = $value;
    else if (count($vars) == 2)
        $_SESSION[$vars[0]][$vars[1]] = $value;
    else if (count($vars) == 3)
        $_SESSION[$vars[0]][$vars[1]][$vars[2]] = $value;
}

$OUTPUT->reset();
$OUTPUT->send();
