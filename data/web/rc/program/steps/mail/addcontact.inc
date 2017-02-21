<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/addcontact.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Add the submitted contact to the users address book                 |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// only process ajax requests
if (!$OUTPUT->ajax_call) {
    return;
}

// Get default addressbook
$CONTACTS = $RCMAIL->get_address_book(-1, true);

if (!empty($_POST['_address']) && is_object($CONTACTS)) {
    $address = rcube_utils::get_input_value('_address', rcube_utils::INPUT_POST, true);
    $contact_arr = rcube_mime::decode_address_list($address, 1, false);

    if (!empty($contact_arr[1]['mailto'])) {
        $contact = array(
            'email' => $contact_arr[1]['mailto'],
            'name'  => $contact_arr[1]['name'],
        );

        // Validity checks
        if (empty($contact['email'])) {
            $OUTPUT->show_message('errorsavingcontact', 'error');
            $OUTPUT->send();
        }

        $email = rcube_utils::idn_to_ascii($contact['email']);
        if (!rcube_utils::check_email($email, false)) {
            $OUTPUT->show_message('emailformaterror', 'error', array('email' => $contact['email']));
            $OUTPUT->send();
        }

        $contact['email'] = rcube_utils::idn_to_utf8($contact['email']);

        $contact = $RCMAIL->plugins->exec_hook('contact_displayname', $contact);

        if (empty($contact['firstname']) || empty($contact['surname'])) {
            $contact['name'] = rcube_addressbook::compose_display_name($contact);
        }

        // validate contact record
        if (!$CONTACTS->validate($contact, true)) {
            $error = $CONTACTS->get_error();
            // TODO: show dialog to complete record
            // if ($error['type'] == rcube_addressbook::ERROR_VALIDATE) { }

            $OUTPUT->show_message($error['message'] ?: 'errorsavingcontact', 'error');
            $OUTPUT->send();
        }

        // check for existing contacts
        $existing = $CONTACTS->search('email', $contact['email'], 1, false);

        if ($done = $existing->count) {
            $OUTPUT->show_message('contactexists', 'warning');
        }
        else {
            $plugin = $RCMAIL->plugins->exec_hook('contact_create', array('record' => $contact, 'source' => null));
            $contact = $plugin['record'];

            $done = !$plugin['abort'] ? $CONTACTS->insert($contact) : $plugin['result'];

            if ($done) {
                $OUTPUT->show_message('addedsuccessfully', 'confirmation');
            }
        }
    }
}

if (!$done) {
    $OUTPUT->show_message($plugin['message'] ?: 'errorsavingcontact', 'error');
}

$OUTPUT->send();
