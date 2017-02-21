<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/move.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2007-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Move a contact record from one direcotry to another                 |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// only process ajax requests
if (!$OUTPUT->ajax_call) {
    return;
}

$cids         = rcmail_get_cids();
$target       = rcube_utils::get_input_value('_to', rcube_utils::INPUT_POST);
$target_group = rcube_utils::get_input_value('_togid', rcube_utils::INPUT_POST);

$all      = 0;
$deleted  = 0;
$success  = 0;
$errormsg = 'moveerror';
$maxnum   = $RCMAIL->config->get('max_group_members', 0);
$page     = $_SESSION['page'] ?: 1;

foreach ($cids as $source => $source_cids) {
    // Something wrong, target not specified
    if (!strlen($target)) {
        break;
    }

    // It maight happen when moving records from search result
    // Do nothing, go to next source
    if ((string)$target === (string)$source) {
        continue;
    }

    $CONTACTS = $RCMAIL->get_address_book($source);
    $TARGET   = $RCMAIL->get_address_book($target);

    if (!$TARGET || !$TARGET->ready || $TARGET->readonly) {
        break;
    }

    if (!$CONTACTS || !$CONTACTS->ready || $CONTACTS->readonly) {
        continue;
    }

    $ids = array();

    foreach ($source_cids as $idx => $cid) {
        $a_record = $CONTACTS->get_record($cid, true);

        // avoid moving groups
        if ($a_record['_type'] == 'group') {
            unset($source_cids[$idx]);
            continue;
        }

        // Check if contact exists, if so, we'll need it's ID
        // Note: Some addressbooks allows empty email address field
        // @TODO: should we check all email addresses?
        $email = $CONTACTS->get_col_values('email', $a_record, true);
        if (!empty($email))
            $result = $TARGET->search('email', $email[0], 1, true, true);
        else if (!empty($a_record['name']))
            $result = $TARGET->search('name', $a_record['name'], 1, true, true);
        else
            $result = new rcube_result_set();

        // insert contact record
        if (!$result->count) {
            $plugin = $RCMAIL->plugins->exec_hook('contact_create', array(
                'record' => $a_record, 'source' => $target, 'group' => $target_group));

            if (!$plugin['abort']) {
                if ($insert_id = $TARGET->insert($plugin['record'], false)) {
                    $ids[] = $insert_id;
                    $success++;
                }
            }
            else if ($plugin['result']) {
                $ids = array_merge($ids, $plugin['result']);
                $success++;
            }
        }
        else {
            $record   = $result->first();
            $ids[]    = $record['ID'];
            $errormsg = empty($email) ? 'contactnameexists' : 'contactexists';
        }
    }

    // remove source contacts
    if ($success && !empty($source_cids)) {
        $all   += count($source_cids);
        $plugin = $RCMAIL->plugins->exec_hook('contact_delete', array(
            'id' => $source_cids, 'source' => $source));

        $del_status = !$plugin['abort'] ? $CONTACTS->delete($source_cids) : $plugin['result'];

        if ($del_status) {
            $deleted += $del_status;
        }
    }

    // assign to group
    if ($target_group && $TARGET->groups && !empty($ids)) {
        $plugin = $RCMAIL->plugins->exec_hook('group_addmembers', array(
            'group_id' => $target_group, 'ids' => $ids, 'source' => $target));

        if (!$plugin['abort']) {
            $TARGET->reset();
            $TARGET->set_group($target_group);

            if ($maxnum && ($TARGET->count()->count + count($plugin['ids']) > $maxnum)) {
                $OUTPUT->show_message('maxgroupmembersreached', 'warning', array('max' => $maxnum));
                $OUTPUT->send();
            }

            if (($cnt = $TARGET->add_to_group($target_group, $plugin['ids'])) && $cnt > $success)
                $success = $cnt;
        }
        else if ($plugin['result']) {
            $success = $plugin['result'];
        }

        $errormsg = $plugin['message'] ?: 'moveerror';
    }
}

if (!$deleted || $deleted != $all) {
    $OUTPUT->command('list_contacts');
}
else {
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
            $res = new rcube_result_set($count, $first - $deleted);

            if ($PAGE_SIZE < $count) {
                $records = array_slice($records, $first - $deleted, $deleted);
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
        $pages  = ceil(($result->count + $deleted) / $PAGE_SIZE);

        // last page and it's empty, display previous one
        if ($result->count && $result->count <= ($PAGE_SIZE * ($page - 1))) {
            $OUTPUT->command('list_page', 'prev');
            $rowcount = $RCMAIL->gettext('loading');
        }
        // get records from the next page to add to the list
        else if ($pages > 1 && $page < $pages) {
            $CONTACTS->set_page($page);
            $records = $CONTACTS->list_records(null, -$deleted);
        }
    }

    // update message count display
    $OUTPUT->set_env('pagecount', ceil($result->count / $PAGE_SIZE));
    $OUTPUT->command('set_rowcount', $rowcount ?: rcmail_get_rowcount_text($result));

    // add new rows from next page (if any)
    if (!empty($records)) {
        rcmail_js_contacts_list($records);
    }
}

if (!$success)
    $OUTPUT->show_message($errormsg, 'error');
else
    $OUTPUT->show_message('movesuccess', 'notice', array('nr' => $success));

// send response
$OUTPUT->send();
