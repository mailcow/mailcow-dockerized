<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/check_recent.inc                                   |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Check for recent messages, in all mailboxes                         |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// If there's no folder or messages list, there's nothing to update
// This can happen on 'refresh' request
if (empty($_POST['_folderlist']) && empty($_POST['_list'])) {
    return;
}

$trash     = $RCMAIL->config->get('trash_mbox');
$current   = $RCMAIL->storage->get_folder();
$check_all = $RCMAIL->action != 'refresh' || (bool)$RCMAIL->config->get('check_all_folders');
$page      = $RCMAIL->storage->get_page();
$page_size = $RCMAIL->storage->get_pagesize();

$search_request = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC);
if ($search_request && $_SESSION['search_request'] != $search_request) {
    $search_request = null;
}

// list of folders to check
if ($check_all) {
    $a_mailboxes = $RCMAIL->storage->list_folders_subscribed('', '*', 'mail');
}
else if ($search_request && is_object($_SESSION['search'][1])) {
    $a_mailboxes = (array) $_SESSION['search'][1]->get_parameters('MAILBOX');
}
else {
    $a_mailboxes = (array) $current;
    if ($current != 'INBOX') {
        $a_mailboxes[] = 'INBOX';
    }
}

// Control folders list from a plugin
$plugin      = $RCMAIL->plugins->exec_hook('check_recent', array('folders' => $a_mailboxes, 'all' => $check_all));
$a_mailboxes = $plugin['folders'];

// check recent/unseen counts
foreach ($a_mailboxes as $mbox_name) {
    $is_current = $mbox_name == $current || ($search_request && is_object($_SESSION['search'][1]) && in_array($mbox_name, (array)$_SESSION['search'][1]->get_parameters('MAILBOX')));
    if ($is_current) {
        // Synchronize mailbox cache, handle flag changes
        $RCMAIL->storage->folder_sync($mbox_name);
    }

    // Get mailbox status
    $status = $RCMAIL->storage->folder_status($mbox_name, $diff);

    if ($status & 1) {
        // trigger plugin hook
        $RCMAIL->plugins->exec_hook('new_messages',
            array('mailbox' => $mbox_name, 'is_current' => $is_current, 'diff' => $diff));
    }

    rcmail_send_unread_count($mbox_name, true, null,
      (!$is_current && ($status & 1)) ? 'recent' : '');

    if ($status && $is_current) {
        // refresh saved search set
        if ($search_request && isset($_SESSION['search'])) {
            unset($search_request);  // only do this once
            $_SESSION['search'] = $RCMAIL->storage->refresh_search();
            if ($_SESSION['search'][1]->multi) {
                $mbox_name = '';
            }
        }

        if (!empty($_POST['_quota'])) {
            $OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $mbox_name));
        }

        $OUTPUT->set_env('exists', $RCMAIL->storage->count($mbox_name, 'EXISTS', true));

        // "No-list" mode, don't get messages
        if (empty($_POST['_list'])) {
            continue;
        }

        // get overall message count; allow caching because rcube_storage::folder_status()
        // did a refresh but only in list mode
        $list_mode = $RCMAIL->storage->get_threading() ? 'THREADS' : 'ALL';
        $all_count = $RCMAIL->storage->count($mbox_name, $list_mode, $list_mode == 'THREADS', false);

        // check current page if we're not on the first page
        if ($all_count && $page > 1) {
            $remaining = $all_count - $page_size * ($page - 1);
            if ($remaining <= 0) {
                $page -= 1;
                $RCMAIL->storage->set_page($page);
                $_SESSION['page'] = $page;
            }
        }

        $OUTPUT->set_env('messagecount', $all_count);
        $OUTPUT->set_env('pagecount', ceil($all_count/$page_size));
        $OUTPUT->command('set_rowcount', rcmail_get_messagecount_text($all_count), $mbox_name);
        $OUTPUT->set_env('current_page', $all_count ? $page : 1);

        // remove old rows (and clear selection if new list is empty)
        $OUTPUT->command('message_list.clear', $all_count ? false : true);

        if ($all_count) {
            $a_headers = $RCMAIL->storage->list_messages($mbox_name, null, rcmail_sort_column(), rcmail_sort_order());
            // add message rows
            rcmail_js_message_list($a_headers, false);
            // remove messages that don't exists from list selection array
            $OUTPUT->command('update_selection');
        }
    }
    // handle flag updates
    else if ($is_current && ($uids = rcube_utils::get_input_value('_uids', rcube_utils::INPUT_GPC)) && empty($search_request)) {
        $data = $RCMAIL->storage->folder_data($mbox_name);

        if (empty($_SESSION['list_mod_seq']) || $_SESSION['list_mod_seq'] != $data['HIGHESTMODSEQ']) {
            $flags = $RCMAIL->storage->list_flags($mbox_name, explode(',', $uids), $_SESSION['list_mod_seq']);
            foreach ($flags as $idx => $row) {
                $flags[$idx] = array_change_key_case(array_map('intval', $row));
            }

            // remember last HIGHESTMODSEQ value (if supported)
            if (!empty($data['HIGHESTMODSEQ'])) {
                $_SESSION['list_mod_seq'] = $data['HIGHESTMODSEQ'];
            }

            $RCMAIL->output->set_env('recent_flags', $flags);
        }
    }

    // set trash folder state
    if ($mbox_name === $trash) {
        $OUTPUT->command('set_trash_count', $RCMAIL->storage->count($mbox_name, 'EXISTS', true));
    }
}

// trigger refresh hook
$RCMAIL->plugins->exec_hook('refresh', array());

$OUTPUT->send();
