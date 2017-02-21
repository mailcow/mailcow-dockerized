<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/list.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
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

if (!empty($_GET['_page']))
    $page = intval($_GET['_page']);
else
    $page = $_SESSION['page'] ?: 1;

$_SESSION['page'] = $page;

// Use search result
if (($records = rcmail_search_update(true)) !== false) {
    // sort the records
    ksort($records, SORT_LOCALE_STRING);

    // create resultset object
    $count  = count($records);
    $first  = ($page-1) * $PAGE_SIZE;
    $result = new rcube_result_set($count, $first);

    // we need only records for current page
    if ($PAGE_SIZE < $count) {
        $records = array_slice($records, $first, $PAGE_SIZE);
    }

    $result->records = array_values($records);
}
// List selected directory
else {
    $afields  = $RCMAIL->config->get('contactlist_fields');
    $CONTACTS = rcmail_contact_source(null, true);

    // get contacts for this user
    $result = $CONTACTS->list_records($afields);

    if (!$result->count && $result->searchonly) {
        $OUTPUT->show_message('contactsearchonly', 'notice');
        $OUTPUT->command('command', 'advanced-search');
    }

    if ($CONTACTS->group_id) {
        $group_data = array('ID' => $CONTACTS->group_id)
            + array_intersect_key((array)$CONTACTS->get_group($CONTACTS->group_id), array('name'=>1,'email'=>1));
    }
}

$OUTPUT->command('set_group_prop', $group_data);

// update message count display
$OUTPUT->set_env('pagecount', ceil($result->count / $PAGE_SIZE));
$OUTPUT->command('set_rowcount', rcmail_get_rowcount_text($result));

// create javascript list
rcmail_js_contacts_list($result);

// send response
$OUTPUT->send();
