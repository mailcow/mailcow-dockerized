<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/list.inc                                           |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Send message list to client (as remote response)                    |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

if (!$OUTPUT->ajax_call) {
    return;
}

$save_arr      = array();
$dont_override = (array) $RCMAIL->config->get('dont_override');

// is there a sort type for this request?
if ($sort = rcube_utils::get_input_value('_sort', rcube_utils::INPUT_GET)) {
    // yes, so set the sort vars
    list($sort_col, $sort_order) = explode('_', $sort);

    // set session vars for sort (so next page and task switch know how to sort)
    if (!in_array('message_sort_col', $dont_override)) {
        $_SESSION['sort_col'] = $save_arr['message_sort_col'] = $sort_col;
    }
    if (!in_array('message_sort_order', $dont_override)) {
        $_SESSION['sort_order'] = $save_arr['message_sort_order'] = $sort_order;
    }
}

// register layout change
if ($layout = rcube_utils::get_input_value('_layout', rcube_utils::INPUT_GET)) {
    $OUTPUT->set_env('layout', $layout);
    $save_arr['layout'] = $layout;
    // force header replace on layout change
    $cols = $_SESSION['list_attrib']['columns'];
}
// is there a set of columns for this request?
else if ($cols = rcube_utils::get_input_value('_cols', rcube_utils::INPUT_GET)) {
    $_SESSION['list_attrib']['columns'] = explode(',', $cols);
    if (!in_array('list_cols', $dont_override)) {
        $save_arr['list_cols'] = explode(',', $cols);
    }
}

if (!empty($save_arr)) {
    $RCMAIL->user->save_prefs($save_arr);
}

$mbox_name = $RCMAIL->storage->get_folder();
$threading = (bool) $RCMAIL->storage->get_threading();

// Synchronize mailbox cache, handle flag changes
$RCMAIL->storage->folder_sync($mbox_name);

// fetch message headers
if ($count = $RCMAIL->storage->count($mbox_name, $threading ? 'THREADS' : 'ALL', !empty($_REQUEST['_refresh']))) {
    $a_headers = $RCMAIL->storage->list_messages($mbox_name, NULL, rcmail_sort_column(), rcmail_sort_order());
}

// update search set (possible change of threading mode)
if (!empty($_REQUEST['_search']) && isset($_SESSION['search'])
    && $_SESSION['search_request'] == $_REQUEST['_search']
) {
    $search_request = $_REQUEST['_search'];
    $_SESSION['search'] = $RCMAIL->storage->get_search_set();
}
// remove old search data
else if (empty($_REQUEST['_search']) && isset($_SESSION['search'])) {
    $RCMAIL->session->remove('search');
}

rcmail_list_pagetitle();

// update mailboxlist
if (empty($search_request)) {
    rcmail_send_unread_count($mbox_name, !empty($_REQUEST['_refresh']), empty($a_headers) ? 0 : null);
}

// update message count display
$pages  = ceil($count/$RCMAIL->storage->get_pagesize());
$page   = $count ? $RCMAIL->storage->get_page() : 1;
$exists = $RCMAIL->storage->count($mbox_name, 'EXISTS', true);

$OUTPUT->set_env('messagecount', $count);
$OUTPUT->set_env('pagecount', $pages);
$OUTPUT->set_env('threading', $threading);
$OUTPUT->set_env('current_page', $page);
$OUTPUT->set_env('exists', $exists);
$OUTPUT->command('set_rowcount', rcmail_get_messagecount_text($count), $mbox_name);

// remove old message rows if commanded by the client
if (!empty($_REQUEST['_clear'])) {
    $OUTPUT->command('clear_message_list');
}

// add message rows
rcmail_js_message_list($a_headers, false, $cols);

if (isset($a_headers) && count($a_headers)) {
    if ($search_request) {
        $OUTPUT->show_message('searchsuccessful', 'confirmation', array('nr' => $count));
    }

    // remember last HIGHESTMODSEQ value (if supported)
    // we need it for flag updates in check-recent
    $data = $RCMAIL->storage->folder_data($mbox_name);
    if (!empty($data['HIGHESTMODSEQ'])) {
        $_SESSION['list_mod_seq'] = $data['HIGHESTMODSEQ'];
    }
}
else {
    // handle IMAP errors (e.g. #1486905)
    if ($err_code = $RCMAIL->storage->get_error_code()) {
        $RCMAIL->display_server_error();
    }
    else if ($search_request) {
        $OUTPUT->show_message('searchnomatch', 'notice');
    }
    else {
        $OUTPUT->show_message('nomessagesfound', 'notice');
    }
}

// set trash folder state
if ($mbox_name === $RCMAIL->config->get('trash_mbox')) {
    $OUTPUT->command('set_trash_count', $exists);
}

if ($page == 1) {
    $OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $multifolder ? 'INBOX' : $mbox_name));
}

// send response
$OUTPUT->send();
