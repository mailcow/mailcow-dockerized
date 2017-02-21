<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/folders.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide functionality of folders management                         |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// init IMAP connection
$STORAGE = $RCMAIL->get_storage();

// subscribe mailbox
if ($RCMAIL->action == 'subscribe') {
    $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);

    if (strlen($mbox)) {
        $result = $STORAGE->subscribe(array($mbox));

        // Handle virtual (non-existing) folders
        if (!$result && $STORAGE->get_error_code() == -1 &&
            $STORAGE->get_response_code() == rcube_storage::TRYCREATE
        ) {
            $result = $STORAGE->create_folder($mbox, true);
            if ($result) {
                // @TODO: remove 'virtual' class of folder's row
            }
        }

        if ($result) {
            // Handle subscription of protected folder (#1487656)
            if ($RCMAIL->config->get('protect_default_folders')
                && $STORAGE->is_special_folder($mbox)
            ) {
                $OUTPUT->command('disable_subscription', $mbox);
            }

            $OUTPUT->show_message('foldersubscribed', 'confirmation');
        }
        else {
            $RCMAIL->display_server_error('errorsaving');
            $OUTPUT->command('reset_subscription', $mbox, false);
        }
    }
}
// unsubscribe mailbox
else if ($RCMAIL->action == 'unsubscribe') {
    $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);

    if (strlen($mbox)) {
        $result = $STORAGE->unsubscribe(array($mbox));

        if ($result) {
            $OUTPUT->show_message('folderunsubscribed', 'confirmation');
        }
        else {
            $RCMAIL->display_server_error('errorsaving');
            $OUTPUT->command('reset_subscription', $mbox, true);
        }
    }
}
// delete an existing mailbox
else if ($RCMAIL->action == 'delete-folder') {
    $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);

    if (strlen($mbox)) {
        $plugin = $RCMAIL->plugins->exec_hook('folder_delete', array('name' => $mbox));

        if (!$plugin['abort']) {
            $deleted = $STORAGE->delete_folder($plugin['name']);
        }
        else {
            $deleted = $plugin['result'];
        }

        // #1488692: update session
        if ($deleted && $_SESSION['mbox'] === $mbox) {
            $RCMAIL->session->remove('mbox');
        }
    }

    if ($OUTPUT->ajax_call && $deleted) {
        // Remove folder and subfolders rows
        $OUTPUT->command('remove_folder_row', $mbox);
        $OUTPUT->show_message('folderdeleted', 'confirmation');
        // Clear content frame
        $OUTPUT->command('subscription_select');
        $OUTPUT->command('set_quota', $RCMAIL->quota_content());
    }
    else if (!$deleted) {
        $RCMAIL->display_server_error('errorsaving');
    }
}
// rename an existing mailbox
else if ($RCMAIL->action == 'rename-folder') {
    $name    = trim(rcube_utils::get_input_value('_folder_newname', rcube_utils::INPUT_POST, true));
    $oldname = rcube_utils::get_input_value('_folder_oldname', rcube_utils::INPUT_POST, true);

    if (strlen($name) && strlen($oldname)) {
        $rename = rcmail_rename_folder($oldname, $name);
    }

    if ($rename && $OUTPUT->ajax_call) {
        rcmail_update_folder_row($name, $oldname);
    }
    else if (!$rename) {
        $RCMAIL->display_server_error('errorsaving');
    }
}
// clear mailbox
else if ($RCMAIL->action == 'purge') {
    $mbox         = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);
    $delimiter    = $STORAGE->get_hierarchy_delimiter();
    $trash_mbox   = $RCMAIL->config->get('trash_mbox');
    $trash_regexp = '/^' . preg_quote($trash . $delimiter, '/') . '/';

    // we should only be purging trash (or their subfolders)
    if (!strlen($trash_mbox) || $mbox === $trash_mbox
        || preg_match($trash_regexp, $mbox)
    ) {
        $success = $STORAGE->delete_message('*', $mbox);
        $delete = true;
    }
    // move to Trash
    else {
        $success = $STORAGE->move_message('1:*', $trash_mbox, $mbox);
        $delete = false;
    }

    if ($success) {
        $OUTPUT->set_env('messagecount', 0);
        if ($delete) {
            $OUTPUT->show_message('folderpurged', 'confirmation');
            $OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $mbox));
        }
        else {
            $OUTPUT->show_message('messagemoved', 'confirmation');
        }
        $_SESSION['unseen_count'][$mbox] = 0;
        $OUTPUT->command('show_folder', $mbox, null, true);
    }
    else {
        $RCMAIL->display_server_error('errorsaving');
    }
}
// get mailbox size
else if ($RCMAIL->action == 'folder-size') {
    $name = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);

    $size = $STORAGE->folder_size($name);

    // @TODO: check quota and show percentage usage of specified mailbox?

    if ($size !== false) {
        $OUTPUT->command('folder_size_update', $RCMAIL->show_bytes($size));
    }
    else {
        $RCMAIL->display_server_error();
    }
}

if ($OUTPUT->ajax_call) {
    $OUTPUT->send();
}

$OUTPUT->set_pagetitle($RCMAIL->gettext('folders'));
$OUTPUT->set_env('prefix_ns', $STORAGE->get_namespace('prefix'));
$OUTPUT->set_env('quota', (bool) $STORAGE->get_capability('QUOTA'));
$OUTPUT->include_script('treelist.js');

// add some labels to client
$OUTPUT->add_label('deletefolderconfirm', 'purgefolderconfirm', 'folderdeleting',
    'foldermoving', 'foldersubscribing', 'folderunsubscribing', 'quota');

// register UI objects
$OUTPUT->add_handlers(array(
    'foldersubscription' => 'rcmail_subscription_form',
    'folderframe'        => 'rcmail_folder_frame',
    'folderfilter'       => 'rcmail_folder_filter',
    'quotadisplay'       => array($RCMAIL, 'quota_display'),
));

$OUTPUT->send('folders');


// build table with all folders listed by server
function rcmail_subscription_form($attrib)
{
    global $RCMAIL, $OUTPUT;

    list($form_start, $form_end) = get_form_tags($attrib, 'folders');
    unset($attrib['form']);

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmSubscriptionlist';
    }

    $STORAGE = $RCMAIL->get_storage();

    // get folders from server
    $STORAGE->clear_cache('mailboxes', true);

    $a_unsubscribed  = $STORAGE->list_folders();
    $a_subscribed    = $STORAGE->list_folders_subscribed('', '*', null, null, true); // unsorted
    $delimiter       = $STORAGE->get_hierarchy_delimiter();
    $namespace       = $STORAGE->get_namespace();
    $special_folders = array_flip(array_merge(array('inbox' => 'INBOX'), $STORAGE->get_special_folders()));
    $protect_default = $RCMAIL->config->get('protect_default_folders');
    $seen            = array();
    $list_folders    = array();

    // pre-process folders list
    foreach ($a_unsubscribed as $i => $folder) {
        $folder_id     = $folder;
        $folder        = $STORAGE->mod_folder($folder);
        $foldersplit   = explode($delimiter, $folder);
        $name          = rcube_charset::convert(array_pop($foldersplit), 'UTF7-IMAP');
        $parent_folder = join($delimiter, $foldersplit);
        $level         = count($foldersplit);

        // add any necessary "virtual" parent folders
        if ($parent_folder && !isset($seen[$parent_folder])) {
            for ($i=1; $i<=$level; $i++) {
                $ancestor_folder = join($delimiter, array_slice($foldersplit, 0, $i));
                if ($ancestor_folder && !$seen[$ancestor_folder]++) {
                    $ancestor_name = rcube_charset::convert($foldersplit[$i-1], 'UTF7-IMAP');
                    $list_folders[] = array(
                        'id'      => $ancestor_folder,
                        'name'    => $ancestor_name,
                        'level'   => $i-1,
                        'virtual' => true,
                    );
                }
            }
        }

        // Handle properly INBOX.INBOX situation
        if (isset($seen[$folder])) {
            continue;
        }

        $seen[$folder]++;

        $list_folders[] = array(
            'id'    => $folder_id,
            'name'  => $name,
            'level' => $level,
        );
    }

    unset($seen);

    $checkbox_subscribe = new html_checkbox(array(
        'name'    => '_subscribed[]',
        'title'   => $RCMAIL->gettext('changesubscription'),
        'onclick' => rcmail_output::JS_OBJECT_NAME.".command(this.checked?'subscribe':'unsubscribe',this.value)",
    ));

    $js_folders = array();
    $folders    = array();
    $collapsed  = (string) $RCMAIL->config->get('collapsed_folders');

    // create list of available folders
    foreach ($list_folders as $i => $folder) {
        $sub_key    = array_search($folder['id'], $a_subscribed);
        $subscribed = $sub_key !== false;
        $protected  = $folder['id'] == 'INBOX' || ($protect_default && isset($special_folders[$folder['id']]));
        $noselect   = false;
        $classes    = array();

        $folder_utf8    = rcube_charset::convert($folder['id'], 'UTF7-IMAP');
        $display_folder = rcube::Q($protected ? $RCMAIL->localize_foldername($folder['id']) : $folder['name']);

        if ($folder['virtual']) {
            $classes[] = 'virtual';
        }

        // Check \Noselect flag (of existing folder)
        if (!$protected && in_array($folder['id'], $a_unsubscribed)) {
            $attrs = $STORAGE->folder_attributes($folder['id']);
            $noselect = in_array_nocase('\\Noselect', $attrs);
        }

        $disabled = (($protected && $subscribed) || $noselect);

        // Below we will disable subscription option for "virtual" folders
        // according to namespaces, but only if they aren't already subscribed.
        // User should be able to unsubscribe from the folder
        // even if it doesn't exists or is not accessible (OTRS:1000059)
        if (!$subscribed && !$disabled && !empty($namespace) && $folder['virtual']) {
            // check if the folder is a namespace prefix, then disable subscription option on it
            if (!$disabled && $folder['level'] == 0) {
                $fname = $folder['id'] . $delimiter;
                foreach ($namespace as $ns) {
                    if (is_array($ns)) {
                        foreach ($ns as $item) {
                            if ($item[0] === $fname) {
                                $disabled = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            // check if the folder is an other users virtual-root folder, then disable subscription option on it
            if (!$disabled && $folder['level'] == 1 && !empty($namespace['other'])) {
                $parts = explode($delimiter, $folder['id']);
                $fname = $parts[0] . $delimiter;
                foreach ($namespace['other'] as $item) {
                    if ($item[0] === $fname) {
                        $disabled = true;
                        break;
                    }
                }
            }
            // check if the folder is shared, then disable subscription option on it (if not subscribed already)
            if (!$disabled) {
                $tmp_ns = array_merge((array)$namespace['other'], (array)$namespace['shared']);
                foreach ($tmp_ns as $item) {
                    if (strlen($item[0]) && strpos($folder['id'], $item[0]) === 0) {
                        $disabled = true;
                        break;
                    }
                }
            }
        }

        $is_collapsed = strpos($collapsed, '&'.rawurlencode($folder['id']).'&') !== false;
        $folder_id    = rcube_utils::html_identifier($folder['id'], true);

        if ($folder_class = $RCMAIL->folder_classname($folder['id'])) {
            $classes[] = $folder_class;
        }

        $folders[$folder['id']] = array(
            'idx'         => $folder_id,
            'folder_imap' => $folder['id'],
            'folder'      => $folder_utf8,
            'display'     => $display_folder,
            'protected'   => $protected || $folder['virtual'],
            'class'       => join(' ', $classes),
            'subscribed'  => $subscribed,
            'level'       => $folder['level'],
            'collapsed'   => $is_collapsed,
            'content'     => html::a(array('href' => '#'), $display_folder)
                . $checkbox_subscribe->show(($subscribed ? $folder['id'] : ''),
                    array('value' => $folder['id'], 'disabled' => $disabled ? 'disabled' : ''))
        );
    }

    $plugin = $RCMAIL->plugins->exec_hook('folders_list', array('list' => $folders));

    // add drop-target representing 'root'
    $root = array(
        'idx'         => rcube_utils::html_identifier('*', true),
        'folder_imap' => '*',
        'folder'      => '',
        'display'     => '',
        'protected'   => true,
        'class'       => 'root',
        'content'     => '<span>&nbsp;</span>',
    );

    $folders        = array();
    $plugin['list'] = array_values($plugin['list']);

    array_unshift($plugin['list'], $root);

    for ($i = 0, $length = count($plugin['list']); $i<$length; $i++) {
        $folders[] = rcmail_folder_tree_element($plugin['list'], $i, $js_folders);
    }

    $OUTPUT->add_gui_object('subscriptionlist', $attrib['id']);
    $OUTPUT->set_env('subscriptionrows', $js_folders);
    $OUTPUT->set_env('defaultfolders', array_keys($special_folders));
    $OUTPUT->set_env('collapsed_folders', $collapsed);
    $OUTPUT->set_env('delimiter', $delimiter);

    return $form_start . html::tag('ul', $attrib, implode('', $folders), html::$common_attrib) . $form_end;
}

function rcmail_folder_tree_element($folders, &$key, &$js_folders)
{
    $data = $folders[$key];
    $idx  = 'rcmli' . $data['idx'];

    $js_folders[$data['folder_imap']] = array($data['folder'], $data['display'], $data['protected']);
    $content          = $data['content'];
    $attribs          = array(
        'id'    => $idx,
        'class' => trim($data['class'] . ' mailbox')
    );

    $children = array();
    while ($folders[$key+1] && $folders[$key+1]['level'] > $data['level']) {
        $key++;
        $children[] = rcmail_folder_tree_element($folders, $key, $js_folders);
    }

    if (!empty($children)) {
        $content .= html::div('treetoggle ' . ($data['collapsed'] ? 'collapsed' : 'expanded'), '&nbsp;')
            . html::tag('ul', array('style' => ($data['collapsed'] ? "display:none" : null)),
                implode("\n", $children));
    }

    return html::tag('li', $attribs, $content);
}

function rcmail_folder_frame($attrib)
{
    global $OUTPUT;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmfolderframe';
    }

    return $OUTPUT->frame($attrib, true);
}

function rcmail_folder_filter($attrib)
{
    global $RCMAIL;

    $storage   = $RCMAIL->get_storage();
    $namespace = $storage->get_namespace();

    if (empty($namespace['personal']) && empty($namespace['shared']) && empty($namespace['other'])) {
        return '';
    }

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmfolderfilter';
    }

    $attrib['onchange'] = rcmail_output::JS_OBJECT_NAME . '.folder_filter(this.value)';

    $roots  = array();
    $select = new html_select($attrib);
    $select->add($RCMAIL->gettext('all'), '---');

    foreach (array_keys($namespace) as $type) {
        foreach ((array)$namespace[$type] as $ns) {
            $root  = rtrim($ns[0], $ns[1]);
            $label = $RCMAIL->gettext('namespace.' . $type);

            if (count($namespace[$type]) > 1) {
                $label .= ' (' . rcube_charset::convert($root, 'UTF7-IMAP', RCUBE_CHARSET) . ')';
            }

            $select->add($label, $root);

            if (strlen($root)) {
                $roots[] = $root;
            }
        }
    }

    $RCMAIL->output->add_gui_object('foldersfilter', $attrib['id']);
    $RCMAIL->output->set_env('ns_roots', $roots);

    return $select->show();
}

function rcmail_rename_folder($oldname, $newname)
{
    global $RCMAIL;

    $storage   = $RCMAIL->get_storage();
    $delimiter = $storage->get_hierarchy_delimiter();

    $plugin = $RCMAIL->plugins->exec_hook('folder_rename', array(
        'oldname' => $oldname, 'newname' => $newname));

    if (!$plugin['abort']) {
        $renamed =  $storage->rename_folder($oldname, $newname);
    }
    else {
        $renamed = $plugin['result'];
    }

    // update per-folder options for modified folder and its subfolders
    if ($renamed) {
        $a_threaded = (array) $RCMAIL->config->get('message_threading', array());
        $oldprefix  = '/^' . preg_quote($oldname . $delimiter, '/') . '/';

        foreach ($a_threaded as $key => $val) {
            if ($key == $oldname) {
                unset($a_threaded[$key]);
                $a_threaded[$newname] = $val;
            }
            else if (preg_match($oldprefix, $key)) {
                unset($a_threaded[$key]);
                $a_threaded[preg_replace($oldprefix, $newname.$delimiter, $key)] = $val;
            }
        }

        $RCMAIL->user->save_prefs(array('message_threading' => $a_threaded));

        // #1488692: update session
        if ($_SESSION['mbox'] === $oldname) {
            $_SESSION['mbox'] = $newname;
        }

        return true;
    }

    return false;
}
