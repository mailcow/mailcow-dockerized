<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/delete.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Delete the submitted contacts (CIDs) from the users address book    |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// process ajax requests only
if (!$OUTPUT->ajax_call) {
    return;
}

$cids   = rcmail_get_cids(null, rcube_utils::INPUT_POST);
$delcnt = 0;

// remove previous deletes
$undo_time = $RCMAIL->config->get('undo_timeout', 0);
$RCMAIL->session->remove('contact_undo');

foreach ($cids as $source => $cid) {
    $CONTACTS = rcmail_contact_source($source);

    if ($CONTACTS->readonly) {
        // more sources? do nothing, probably we have search results from
        // more than one source, some of these sources can be readonly
        if (count($cids) == 1) {
            $OUTPUT->show_message('contactdelerror', 'error');
            $OUTPUT->command('list_contacts');
            $OUTPUT->send();
        }
        continue;
    }

    $plugin = $RCMAIL->plugins->exec_hook('contact_delete', array(
        'id' => $cid, 'source' => $source));

    $deleted = !$plugin['abort'] ? $CONTACTS->delete($cid, $undo_time < 1) : $plugin['result'];

    if (!$deleted) {
        if ($plugin['message']) {
            $error = $plugin['message'];
        }
        else if (($error = $CONTACTS->get_error()) && $error['message']) {
            $error = $error['message'];
        }
        else {
            $error = 'contactdelerror';
        }

        $source = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);
        $group  = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_GPC);

        $OUTPUT->show_message($error, 'error');
        $OUTPUT->command('list_contacts', $source, $group);
        $OUTPUT->send();
    }
    else {
        $delcnt += $deleted;

        // store deleted contacts IDs in session for undo action
        if ($undo_time > 0 && $CONTACTS->undelete) {
            $_SESSION['contact_undo']['data'][$source] = $cid;
        }
    }
}

if (!empty($_SESSION['contact_undo'])) {
    $_SESSION['contact_undo']['ts'] = time();
    $msg = html::span(null, $RCMAIL->gettext('contactdeleted'))
        . ' ' . html::a(array('onclick' => rcmail_output::JS_OBJECT_NAME.".command('undo', '', this)"), $RCMAIL->gettext('undo'));

    $OUTPUT->show_message($msg, 'confirmation', null, true, $undo_time);
}
else {
    $OUTPUT->show_message('contactdeleted', 'confirmation');
}

$page = isset($_SESSION['page']) ? $_SESSION['page'] : 1;

// update saved search after data changed
if (($records = rcmail_search_update(true)) !== false) {
    // create resultset object
    $count  = count($records);
    $first  = ($page-1) * $PAGE_SIZE;
    $result = new rcube_result_set($count, $first);
    $pages  = ceil((count($records) + $delcnt) / $PAGE_SIZE);

    // last page and it's empty, display previous one
    if ($result->count && $result->count <= ($PAGE_SIZE * ($page - 1))) {
        $OUTPUT->command('list_page', 'prev');
        $rowcount = $RCMAIL->gettext('loading');
    }
    // get records from the next page to add to the list
    else if ($pages > 1 && $page < $pages) {
        // sort the records
        ksort($records, SORT_LOCALE_STRING);

        $first += $PAGE_SIZE;
        // create resultset object
        $res = new rcube_result_set($count, $first - $delcnt);

        if ($PAGE_SIZE < $count) {
            $records = array_slice($records, $first - $delcnt, $delcnt);
        }

        $res->records = array_values($records);
        $records = $res;
    }
    else {
        unset($records);
    }
}
else {
    // count contacts for this user
    $result = $CONTACTS->count();
    $pages  = ceil(($result->count + $delcnt) / $PAGE_SIZE);

    // last page and it's empty, display previous one
    if ($result->count && $result->count <= ($PAGE_SIZE * ($page - 1))) {
        $OUTPUT->command('list_page', 'prev');
        $rowcount = $RCMAIL->gettext('loading');
    }
    // get records from the next page to add to the list
    else if ($pages > 1 && $page < $pages) {
        $CONTACTS->set_page($page);
        $records = $CONTACTS->list_records(null, -$delcnt);
    }
}

// update message count display
$OUTPUT->set_env('pagecount', ceil($result->count / $PAGE_SIZE));
$OUTPUT->command('set_rowcount', $rowcount ?: rcmail_get_rowcount_text($result));

// add new rows from next page (if any)
if (!empty($records)) {
    rcmail_js_contacts_list($records);
}

// send response
$OUTPUT->send();
