<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/list_contacts.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2012-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Send contacts list to client (as remote response)                   |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$afields       = $RCMAIL->config->get('contactlist_fields');
$addr_sort_col = $RCMAIL->config->get('addressbook_sort_col', 'name');
$page_size     = $RCMAIL->config->get('addressbook_pagesize', $RCMAIL->config->get('pagesize', 50));
$list_page     = max(1, intval($_GET['_page']));
$jsresult      = array();

// Use search result
if (!empty($_REQUEST['_search']) && isset($_SESSION['search'][$_REQUEST['_search']])) {
    $search  = (array)$_SESSION['search'][$_REQUEST['_search']];
    $sparam = $_SESSION['search_params']['id'] == $_REQUEST['_search'] ? $_SESSION['search_params']['data'] : array();

    // get records from all sources
    foreach ($search as $s => $set) {
        $CONTACTS = $RCMAIL->get_address_book($s);

        // list matching groups of this source (on page one)
        if ($sparam[1] && $CONTACTS->groups && $list_page == 1) {
            $jsresult += rcmail_compose_contact_groups($CONTACTS, $s, $sparam[1], (int)$RCMAIL->config->get('addressbook_search_mode'));
        }

        // reset page
        $CONTACTS->set_page(1);
        $CONTACTS->set_pagesize(9999);
        $CONTACTS->set_search_set($set);

        // get records
        $result = $CONTACTS->list_records($afields);

        while ($row = $result->next()) {
            $row['sourceid'] = $s;
            $key = rcube_addressbook::compose_contact_key($row, $addr_sort_col);
            $records[$key] = $row;
        }
        unset($result);
    }

    // sort the records
    ksort($records, SORT_LOCALE_STRING);

    // create resultset object
    $count  = count($records);
    $first  = ($list_page-1) * $page_size;
    $result = new rcube_result_set($count, $first);

    // we need only records for current page
    if ($page_size < $count) {
        $records = array_slice($records, $first, $page_size);
    }

    $result->records = array_values($records);
}
// list contacts from selected source
else {
    $source = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);
    $CONTACTS = $RCMAIL->get_address_book($source);

    if ($CONTACTS && $CONTACTS->ready) {
        // set list properties
        $CONTACTS->set_pagesize($page_size);
        $CONTACTS->set_page($list_page);

        if ($group_id = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_GPC)) {
            $CONTACTS->set_group($group_id);
        }
        // list groups of this source (on page one)
        else if ($CONTACTS->groups && $CONTACTS->list_page == 1) {
            $jsresult = rcmail_compose_contact_groups($CONTACTS, $source);
        }

        // get contacts for this user
        $result = $CONTACTS->list_records($afields);
    }
}

if (!empty($result) && !$result->count && $result->searchonly) {
    $OUTPUT->show_message('contactsearchonly', 'notice');
}
else if (!empty($result) && $result->count > 0) {
    // create javascript list
    while ($row = $result->next()) {
        $name = rcube_addressbook::compose_list_name($row);

        // add record for every email address of the contact
        $emails = $CONTACTS->get_col_values('email', $row, true);
        foreach ($emails as $i => $email) {
            $row_id = $row['ID'].'-'.$i;
            $jsresult[$row_id] = format_email_recipient($email, $name);
            $classname = $row['_type'] == 'group' ? 'group' : 'person';
            $keyname = $row['_type'] == 'group' ? 'contactgroup' : 'contact';

            $OUTPUT->command('add_contact_row', $row_id, array(
                $keyname => html::a(array('title' => $email), rcube::Q($name ?: $email) .
                    ($name && count($emails) > 1 ? '&nbsp;' . html::span('email', rcube::Q($email)) : '')
                )), $classname);
        }
    }
}

// update env
$OUTPUT->set_env('contactdata', $jsresult);
$OUTPUT->set_env('pagecount', ceil($result->count / $page_size));
$OUTPUT->command('set_page_buttons');

// send response
$OUTPUT->send();
