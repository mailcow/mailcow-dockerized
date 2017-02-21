<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/save.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Save a contact entry or to add a new one                            |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$CONTACTS      = rcmail_contact_source(null, true, true);
$cid           = rcube_utils::get_input_value('_cid', rcube_utils::INPUT_POST);
$return_action = empty($cid) ? 'add' : 'edit';

// Source changed, display the form again
if (!empty($_GET['_reload'])) {
    $RCMAIL->overwrite_action($return_action);
    return;
}

// cannot edit record
if ($CONTACTS->readonly) {
    $OUTPUT->show_message('contactreadonly', 'error');
    $RCMAIL->overwrite_action($return_action);
    return;
}

// read POST values into hash array
$a_record = array();
foreach ($GLOBALS['CONTACT_COLTYPES'] as $col => $colprop) {
    if ($colprop['composite']) {
        continue;
    }

    $fname = '_'.$col;

    // gather form data of composite fields
    if ($colprop['childs']) {
        $values = array();
        foreach ($colprop['childs'] as $childcol => $cp) {
            $vals = rcube_utils::get_input_value('_'.$childcol, rcube_utils::INPUT_POST, true);
            foreach ((array)$vals as $i => $val) {
                $values[$i][$childcol] = $val;
            }
        }

        $subtypes = isset($_REQUEST['_subtype_' . $col]) ? (array)rcube_utils::get_input_value('_subtype_' . $col, rcube_utils::INPUT_POST) : array('');
        foreach ($subtypes as $i => $subtype) {
            $suffix = $subtype ? ':'.$subtype : '';
            if ($values[$i]) {
                $a_record[$col.$suffix][] = $values[$i];
            }
        }
    }
    // assign values and subtypes
    else if (is_array($_POST[$fname])) {
        $values   = rcube_utils::get_input_value($fname, rcube_utils::INPUT_POST, true);
        $subtypes = rcube_utils::get_input_value('_subtype_' . $col, rcube_utils::INPUT_POST);

        foreach ($values as $i => $val) {
            if ($col == 'email') {
                // extract email from full address specification, e.g. "Name" <addr@domain.tld>
                $addr = rcube_mime::decode_address_list($val, 1, false);
                if (!empty($addr) && ($addr = array_pop($addr)) && $addr['mailto']) {
                    $val = $addr['mailto'];
                }
            }

            $subtype = $subtypes[$i] ? ':'.$subtypes[$i] : '';
            $a_record[$col.$subtype][] = $val;
        }
    }
    else if (isset($_POST[$fname])) {
        $a_record[$col] = rcube_utils::get_input_value($fname, rcube_utils::INPUT_POST, true);

        // normalize the submitted date strings
        if ($colprop['type'] == 'date') {
            if ($a_record[$col] && ($dt = rcube_utils::anytodatetime($a_record[$col]))) {
                $a_record[$col] = $dt->format('Y-m-d');
            }
            else {
                unset($a_record[$col]);
            }
        }
    }
}

// Generate contact's display name (must be before validation)
if (empty($a_record['name'])) {
    $a_record['name'] = rcube_addressbook::compose_display_name($a_record, true);

    // Reset it if equals to email address (from compose_display_name())
    $email = rcube_addressbook::get_col_values('email', $a_record, true);
    if ($a_record['name'] == $email[0]) {
        $a_record['name'] = '';
    }
}

// do input checks (delegated to $CONTACTS instance)
if (!$CONTACTS->validate($a_record)) {
    $err = (array)$CONTACTS->get_error();
    $OUTPUT->show_message($err['message'] ? rcube::Q($err['message']) : 'formincomplete', 'warning');
    $GLOBALS['EDIT_RECORD'] = $a_record;  // store submitted data to be used in edit form
    $RCMAIL->overwrite_action($return_action);
    return;
}

// get raw photo data if changed
if (isset($a_record['photo'])) {
    if ($a_record['photo'] == '-del-') {
        $a_record['photo'] = '';
    }
    else if ($tempfile = $_SESSION['contacts']['files'][$a_record['photo']]) {
        $tempfile = $RCMAIL->plugins->exec_hook('attachment_get', $tempfile);
        if ($tempfile['status'])
            $a_record['photo'] = $tempfile['data'] ?: @file_get_contents($tempfile['path']);
    }
    else
        unset($a_record['photo']);

    // cleanup session data
    $RCMAIL->plugins->exec_hook('attachments_cleanup', array('group' => 'contact'));
    $RCMAIL->session->remove('contacts');
}

$source = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);

// update an existing contact
if (!empty($cid)) {
    $plugin = $RCMAIL->plugins->exec_hook('contact_update',
        array('id' => $cid, 'record' => $a_record, 'source' => $source));
    $a_record = $plugin['record'];

    if (!$plugin['abort'])
        $result = $CONTACTS->update($cid, $a_record);
    else
        $result = $plugin['result'];

    if ($result) {
        // show confirmation
        $OUTPUT->show_message('successfullysaved', 'confirmation', null, false);

        // in search mode, just reload the list (#1490015)
        if ($_REQUEST['_search']) {
            $OUTPUT->command('parent.command', 'list');
            $OUTPUT->send('iframe');
        }

        // LDAP DN change
        if (is_string($result) && strlen($result) > 1) {
            $newcid = $result;
            // change cid in POST for 'show' action
            $_POST['_cid'] = $newcid;
        }

        // refresh contact data for list update and 'show' action
        $CONTACT_RECORD = $CONTACTS->get_record($newcid ?: $cid, true);

        // Plugins can decide to remove the contact on edit, e.g. automatic_addressbook
        // Best we can do is to refresh the list (#5522)
        if (empty($CONTACT_RECORD)) {
            $OUTPUT->command('parent.command', 'list');
            $OUTPUT->send('iframe');
        }

        // Update contacts list
        $a_js_cols = array();
        $record = $CONTACT_RECORD;
        $record['email'] = reset($CONTACTS->get_col_values('email', $record, true));
        $record['name']  = rcube_addressbook::compose_list_name($record);

        foreach (array('name') as $col) {
            $a_js_cols[] = rcube::Q((string)$record[$col]);
        }

        // performance: unset some big data items we don't need here
        $record = array_intersect_key($record, array('ID' => 1,'email' => 1,'name' => 1));
        $record['_type'] = 'person';

        // update the changed col in list
        $OUTPUT->command('parent.update_contact_row', $cid, $a_js_cols, $newcid, $source, $record);

        $RCMAIL->overwrite_action('show');
    }
    else {
        // show error message
        $err = $CONTACTS->get_error();
        $OUTPUT->show_message($plugin['message'] ?: ($err['message'] ?: 'errorsaving'), 'error', null, false);
        $RCMAIL->overwrite_action('show');
    }
}

// insert a new contact
else {
    // Name of the addressbook already selected on the list
    $orig_source = rcube_utils::get_input_value('_orig_source', rcube_utils::INPUT_GPC);

    if (!strlen($source)) {
        $source = $orig_source;
    }

    // show notice if existing contacts with same e-mail are found
    foreach ($CONTACTS->get_col_values('email', $a_record, true) as $email) {
        if ($email && ($res = $CONTACTS->search('email', $email, 1, false, true)) && $res->count) {
            $OUTPUT->show_message('contactexists', 'notice', null, false);
            break;
        }
    }

    $plugin = $RCMAIL->plugins->exec_hook('contact_create', array(
        'record' => $a_record, 'source' => $source));
    $a_record = $plugin['record'];

    // insert record and send response
    if (!$plugin['abort'])
        $insert_id = $CONTACTS->insert($a_record);
    else
        $insert_id = $plugin['result'];

    if ($insert_id) {
        $CONTACTS->reset();

        // add new contact to the specified group
        if ($CONTACTS->groups && $CONTACTS->group_id) {
            $plugin = $RCMAIL->plugins->exec_hook('group_addmembers', array(
                'group_id' => $CONTACTS->group_id, 'ids' => $insert_id, 'source' => $source));

            if (!$plugin['abort']) {
                if (($maxnum = $RCMAIL->config->get('max_group_members', 0)) && ($CONTACTS->count()->count + 1 > $maxnum)) {
                    // @FIXME: should we remove the contact?
                    $msgtext = $RCMAIL->gettext(array('name' => 'maxgroupmembersreached', 'vars' => array('max' => $maxnum)));
                    $OUTPUT->command('parent.display_message', $msgtext, 'warning');
                }
                else {
                    $CONTACTS->add_to_group($plugin['group_id'], $plugin['ids']);
                }
            }
        }

        // show confirmation
        $OUTPUT->show_message('successfullysaved', 'confirmation', null, false);

        $OUTPUT->command('parent.set_rowcount', $RCMAIL->gettext('loading'));
        $OUTPUT->command('parent.list_contacts');

        $OUTPUT->send('iframe');
    }
    else {
        // show error message
        $err = $CONTACTS->get_error();
        $OUTPUT->show_message($plugin['message'] ?: ($err['message'] ?: 'errorsaving'), 'error', null, false);
        $RCMAIL->overwrite_action('add');
    }
}
