<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/save_folder.inc                                |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide functionality to create/edit a folder                       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// WARNING: folder names in UI are encoded with RCUBE_CHARSET

// init IMAP connection
$STORAGE = $RCMAIL->get_storage();

$name      = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true));
$path      = rcube_utils::get_input_value('_parent', rcube_utils::INPUT_POST, true);
$old_imap  = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);
$name_imap = rcube_charset::convert($name, RCUBE_CHARSET, 'UTF7-IMAP');
// $path is in UTF7-IMAP already

$delimiter = $STORAGE->get_hierarchy_delimiter();
$options = strlen($old_imap) ? rcmail_folder_options($old_imap) : array();

// Folder name checks
if ($options['protected'] || $options['norename']) {
}
else if (!strlen($name)) {
    $error = $RCMAIL->gettext('namecannotbeempty');
}
else if (mb_strlen($name) > 128) {
    $error = $RCMAIL->gettext('nametoolong');
}
else if ($name[0] == '.' && $RCMAIL->config->get('imap_skip_hidden_folders')) {
    $error = $RCMAIL->gettext('namedotforbidden');
}
else {
    // these characters are problematic e.g. when used in LIST/LSUB
    foreach (array($delimiter, '%', '*') as $char) {
        if (strpos($name, $delimiter) !== false) {
            $error = $RCMAIL->gettext('forbiddencharacter') . " ($char)";
            break;
        }
    }
}

if ($error) {
    $OUTPUT->command('display_message', $error, 'error');
}
else {
    if ($options['protected'] || $options['norename']) {
        $name_imap = $old_imap;
    }
    else if (strlen($path)) {
        $name_imap = $path . $delimiter . $name_imap;
    }
    else {
        $name_imap = $STORAGE->mod_folder($name_imap, 'in');
    }
}

// Check access rights to the parent folder
if (!$error && strlen($path) && (!strlen($old_imap) || $old_imap != $name_imap)
    && $STORAGE->get_capability('ACL')
) {
    $parent_opts = $STORAGE->folder_info($path);
    if ($parent_opts['namespace'] != 'personal'
        && (empty($parent_opts['rights']) || !preg_match('/[ck]/', implode($parent_opts['rights'])))
    ) {
        $error = $RCMAIL->gettext('parentnotwritable');
    }
}

if ($error) {
    $OUTPUT->command('display_message', $error, 'error');
}
else {
    $folder['name']     = $name_imap;
    $folder['oldname']  = $old_imap;
    $folder['class']    = '';
    $folder['options']  = $options;
    $folder['settings'] = array(
        // List view mode: 0-list, 1-threads
        'view_mode'   => (int) rcube_utils::get_input_value('_viewmode', rcube_utils::INPUT_POST),
        'sort_column' => rcube_utils::get_input_value('_sortcol', rcube_utils::INPUT_POST),
        'sort_order'  => rcube_utils::get_input_value('_sortord', rcube_utils::INPUT_POST),
    );
}

// create a new mailbox
if (!$error && !strlen($old_imap)) {
    $folder['subscribe'] = true;

    $plugin = $RCMAIL->plugins->exec_hook('folder_create', array('record' => $folder));

    $folder = $plugin['record'];

    if (!$plugin['abort']) {
        $created = $STORAGE->create_folder($folder['name'], $folder['subscribe']);
    }
    else {
        $created = $plugin['result'];
    }

    if ($created) {
        // Save folder settings
        if (isset($_POST['_viewmode'])) {
            $a_threaded = (array) $RCMAIL->config->get('message_threading', array());

            $a_threaded[$folder['name']] = (bool) $_POST['_viewmode'];

            $RCMAIL->user->save_prefs(array('message_threading' => $a_threaded));
        }

        rcmail_update_folder_row($folder['name'], null, $folder['subscribe'], $folder['class']);

        $OUTPUT->show_message('foldercreated', 'confirmation');
        // reset folder preview frame
        $OUTPUT->command('subscription_select');
        $OUTPUT->send('iframe');
    }
    else {
        // show error message
        $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error', null, false);
    }
}
// update a mailbox
else if (!$error) {
    $plugin = $RCMAIL->plugins->exec_hook('folder_update', array('record' => $folder));

    $folder = $plugin['record'];
    $rename = ($folder['oldname'] != $folder['name']);

    if (!$plugin['abort']) {
        if ($rename) {
            $updated = $STORAGE->rename_folder($folder['oldname'], $folder['name']);
        }
        else {
            $updated = true;
        }
    }
    else {
        $updated = $plugin['result'];
    }

    if ($updated) {
        // Update folder settings,
        if (isset($_POST['_viewmode'])) {
            $a_threaded = (array) $RCMAIL->config->get('message_threading', array());

            // In case of name change update names of childrens in settings
            if ($rename) {
                $oldprefix  = '/^' . preg_quote($folder['oldname'] . $delimiter, '/') . '/';
                foreach ($a_threaded as $key => $val) {
                    if ($key == $folder['oldname']) {
                        unset($a_threaded[$key]);
                    }
                    else if (preg_match($oldprefix, $key)) {
                        unset($a_threaded[$key]);
                        $a_threaded[preg_replace($oldprefix, $folder['name'].$delimiter, $key)] = $val;
                    }
                }
            }

            $a_threaded[$folder['name']] = (bool) $_POST['_viewmode'];

            $RCMAIL->user->save_prefs(array('message_threading' => $a_threaded));
        }

        $OUTPUT->show_message('folderupdated', 'confirmation');
        $OUTPUT->set_env('folder', $folder['name']);

        if ($rename) {
            // #1488692: update session
            if ($_SESSION['mbox'] === $folder['oldname']) {
                $_SESSION['mbox'] = $folder['name'];
            }
            rcmail_update_folder_row($folder['name'], $folder['oldname'], $folder['subscribe'], $folder['class']);
            $OUTPUT->send('iframe');
        }
        else if (!empty($folder['class'])) {
            rcmail_update_folder_row($folder['name'], $folder['oldname'], $folder['subscribe'], $folder['class']);
        }
    }
    else {
        // show error message
        $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error', null, false);
    }
}

$RCMAIL->overwrite_action('edit-folder');
