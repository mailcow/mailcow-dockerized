<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/move_del.inc                                       |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Move the submitted messages to a specific mailbox or delete them    |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// only process ajax requests
if (!$OUTPUT->ajax_call) {
    return;
}

// count messages before changing anything
$threading = (bool) $RCMAIL->storage->get_threading();
$trash     = $RCMAIL->config->get('trash_mbox');
$sources   = array();

if ($_POST['_from'] != 'show') {
    $old_count = $RCMAIL->storage->count(NULL, $threading ? 'THREADS' : 'ALL');
    $old_pages = ceil($old_count / $RCMAIL->storage->get_pagesize());
}

// move messages
if ($RCMAIL->action == 'move' && !empty($_POST['_uid']) && strlen($_POST['_target_mbox'])) {
    $target  = rcube_utils::get_input_value('_target_mbox', rcube_utils::INPUT_POST, true);
    $success = true;

    foreach (rcmail::get_uids(null, null, $multifolder) as $mbox => $uids) {
        if ($mbox === $target) {
            $count += count($uids);
        }
        else if ($RCMAIL->storage->move_message($uids, $target, $mbox)) {
            $count += count($uids);
            $sources[] = $mbox;
        }
        else {
            $success = false;
        }
    }

    if (!$success) {
        // send error message
        if ($_POST['_from'] != 'show')
            $OUTPUT->command('list_mailbox');
        $RCMAIL->display_server_error('errormoving', null, $target == $trash ? 'delete' : '');
        $OUTPUT->send();
    }
    else {
        $OUTPUT->show_message($target == $trash ? 'messagemovedtotrash' : 'messagemoved', 'confirmation');
    }

    if (!empty($_POST['_refresh'])) {
        // FIXME: send updated message rows instead of reloading the entire list
        $OUTPUT->command('refresh_list');
    }
    else {
        $addrows = true;
    }
}
// delete messages
else if ($RCMAIL->action == 'delete' && !empty($_POST['_uid'])) {
    foreach (rcmail::get_uids(null, null, $multifolder) as $mbox => $uids) {
        $del      += (int)$RCMAIL->storage->delete_message($uids, $mbox);
        $count    += count($uids);
        $sources[] = $mbox;
    }

    if (!$del) {
        // send error message
        if ($_POST['_from'] != 'show')
            $OUTPUT->command('list_mailbox');
        $RCMAIL->display_server_error('errordeleting');
        $OUTPUT->send();
    }
    else {
        $OUTPUT->show_message('messagedeleted', 'confirmation');
    }

    $addrows = true;
}
// unknown action or missing query param
else {
    $OUTPUT->show_message('internalerror', 'error');
    $OUTPUT->send();
}

$search_request = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC);

// refresh saved search set after moving some messages
if ($search_request && $RCMAIL->storage->get_search_set()) {
    $_SESSION['search'] = $RCMAIL->storage->refresh_search();
}

if ($_POST['_from'] == 'show') {
    if ($next = rcube_utils::get_input_value('_next_uid', rcube_utils::INPUT_GPC)) {
        $OUTPUT->command('show_message', $next);
    }
    else {
        $OUTPUT->command('command', 'list');
    }

    $OUTPUT->send();
}

$mbox           = $RCMAIL->storage->get_folder();
$msg_count      = $RCMAIL->storage->count(NULL, $threading ? 'THREADS' : 'ALL');
$exists         = $RCMAIL->storage->count($mbox, 'EXISTS', true);
$page_size      = $RCMAIL->storage->get_pagesize();
$page           = $RCMAIL->storage->get_page();
$pages          = ceil($msg_count / $page_size);
$nextpage_count = $old_count - $page_size * $page;
$remaining      = $msg_count - $page_size * ($page - 1);

// jump back one page (user removed the whole last page)
if ($page > 1 && $remaining == 0) {
    $page -= 1;
    $RCMAIL->storage->set_page($page);
    $_SESSION['page'] = $page;
    $jump_back = true;
}

// update message count display
$OUTPUT->set_env('messagecount', $msg_count);
$OUTPUT->set_env('current_page', $page);
$OUTPUT->set_env('pagecount', $pages);
$OUTPUT->set_env('exists', $exists);

// update mailboxlist
$unseen_count = $msg_count ? $RCMAIL->storage->count($mbox, 'UNSEEN') : 0;
$old_unseen   = rcmail_get_unseen_count($mbox);

if ($old_unseen != $unseen_count) {
    $OUTPUT->command('set_unread_count', $mbox, $unseen_count, ($mbox == 'INBOX'));
    rcmail_set_unseen_count($mbox, $unseen_count);
}

if ($RCMAIL->action == 'move' && strlen($target)) {
    rcmail_send_unread_count($target, true);
}

$OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $multifolder ? $sources[0] : 'INBOX'));
$OUTPUT->command('set_rowcount', rcmail_get_messagecount_text($msg_count), $mbox);

if ($threading) {
    $count = rcube_utils::get_input_value('_count', rcube_utils::INPUT_POST);
}

// add new rows from next page (if any)
if ($addrows && $count && $uids != '*' && ($jump_back || $nextpage_count > 0)) {
    $a_headers = $RCMAIL->storage->list_messages($mbox, NULL,
        rcmail_sort_column(), rcmail_sort_order(), $jump_back ? NULL : $count);

    rcmail_js_message_list($a_headers, false);
}

// set trash folder state
if ($mbox === $trash) {
    $OUTPUT->command('set_trash_count', $exists);
}
else if ($target !== null && $target === $trash) {
    $OUTPUT->command('set_trash_count', $RCMAIL->storage->count($trash, 'EXISTS', true));
}

// send response
$OUTPUT->send();
