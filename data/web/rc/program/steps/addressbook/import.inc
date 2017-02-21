<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/import.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Import contacts from a vCard or CSV file                            |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <machniak@kolabsys.com>                   |
 +-----------------------------------------------------------------------+
*/

/** The import process **/

$importstep = 'rcmail_import_form';

if (is_array($_FILES['_file'])) {
    $replace      = (bool)rcube_utils::get_input_value('_replace', rcube_utils::INPUT_GPC);
    $target       = rcube_utils::get_input_value('_target', rcube_utils::INPUT_GPC);
    $with_groups  = intval(rcube_utils::get_input_value('_groups', rcube_utils::INPUT_GPC));

    $vcards       = array();
    $upload_error = null;

    $CONTACTS = $RCMAIL->get_address_book($target, true);

    if (!$CONTACTS->groups) {
        $with_groups = false;
    }

    if ($CONTACTS->readonly) {
        $OUTPUT->show_message('addresswriterror', 'error');
    }
    else {
        foreach ((array)$_FILES['_file']['tmp_name'] as $i => $filepath) {
            // Process uploaded file if there is no error
            $err = $_FILES['_file']['error'][$i];

            if ($err) {
                $upload_error = $err;
            }
            else {
                $file_content = file_get_contents($filepath);

                // let rcube_vcard do the hard work :-)
                $vcard_o = new rcube_vcard();
                $vcard_o->extend_fieldmap($CONTACTS->vcard_map);
                $v_list = $vcard_o->import($file_content);

                if (!empty($v_list)) {
                    $vcards = array_merge($vcards, $v_list);
                    continue;
                }

                // no vCards found, try CSV
                $csv = new rcube_csv2vcard($_SESSION['language']);
                $csv->import($file_content);
                $v_list = $csv->export();

                if (!empty($v_list)) {
                    $vcards = array_merge($vcards, $v_list);
                }
            }
        }
    }

    // no vcards detected
    if (!count($vcards)) {
        if ($upload_error == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
            $size = $RCMAIL->show_bytes(rcube_utils::max_upload_size());
            $OUTPUT->show_message('filesizeerror', 'error', array('size' => $size));
        }
        else if ($upload_error) {
            $OUTPUT->show_message('fileuploaderror', 'error');
        }
        else {
            $OUTPUT->show_message('importformaterror', 'error');
        }
    }
    else {
        $IMPORT_STATS = new stdClass;
        $IMPORT_STATS->names = array();
        $IMPORT_STATS->skipped_names = array();
        $IMPORT_STATS->count = count($vcards);
        $IMPORT_STATS->inserted = $IMPORT_STATS->skipped = $IMPORT_STATS->invalid = $IMPORT_STATS->errors = 0;

        if ($replace) {
            $CONTACTS->delete_all($CONTACTS->groups && $with_groups < 2);
        }

        if ($with_groups) {
            $import_groups = $CONTACTS->list_groups();
        }

        foreach ($vcards as $vcard) {
            $a_record = $vcard->get_assoc();

            // Generate contact's display name (must be before validation), the same we do in save.inc
            if (empty($a_record['name'])) {
                $a_record['name'] = rcube_addressbook::compose_display_name($a_record, true);
                // Reset it if equals to email address (from compose_display_name())
                if ($a_record['name'] == $a_record['email'][0]) {
                    $a_record['name'] = '';
                }
            }

            // skip invalid (incomplete) entries
            if (!$CONTACTS->validate($a_record, true)) {
                $IMPORT_STATS->invalid++;
                continue;
            }

            // We're using UTF8 internally
            $email = $vcard->email[0];
            $email = rcube_utils::idn_to_utf8($email);

            if (!$replace) {
                $existing = null;
                // compare e-mail address
                if ($email) {
                    $existing = $CONTACTS->search('email', $email, 1, false);
                }
                // compare display name if email not found
                if ((!$existing || !$existing->count) && $vcard->displayname) {
                    $existing = $CONTACTS->search('name', $vcard->displayname, 1, false);
                }
                if ($existing && $existing->count) {
                    $IMPORT_STATS->skipped++;
                    $IMPORT_STATS->skipped_names[] = $vcard->displayname ?: $email;
                    continue;
                }
            }

            $a_record['vcard'] = $vcard->export();

            $plugin = $RCMAIL->plugins->exec_hook('contact_create',
                array('record' => $a_record, 'source' => null));
            $a_record = $plugin['record'];

            // insert record and send response
            if (!$plugin['abort'])
                $success = $CONTACTS->insert($a_record);
            else
                $success = $plugin['result'];

            if ($success) {
                // assign groups for this contact (if enabled)
                if ($with_groups && !empty($a_record['groups'])) {
                    foreach (explode(',', $a_record['groups'][0]) as $group_name) {
                        if ($group_id = rcmail_import_group_id($group_name, $CONTACTS, $with_groups == 1, $import_groups)) {
                            $CONTACTS->add_to_group($group_id, $success);
                        }
                    }
                }

                $IMPORT_STATS->inserted++;
                $IMPORT_STATS->names[] = $a_record['name'] ?: $email;
            }
            else {
                $IMPORT_STATS->errors++;
            }
        }

        $importstep = 'rcmail_import_confirm';
    }
}


$OUTPUT->set_pagetitle($RCMAIL->gettext('importcontacts'));

$OUTPUT->add_handlers(array(
    'importstep' => $importstep,
    'importnav'  => 'rcmail_import_buttons',
));

// render page
$OUTPUT->send('importcontacts');



/**
 * Handler function to display the import/upload form
 */
function rcmail_import_form($attrib)
{
    global $RCMAIL, $OUTPUT;

    $target = rcube_utils::get_input_value('_target', rcube_utils::INPUT_GPC);

    $attrib += array('id' => "rcmImportForm");

    $writable_books = $RCMAIL->get_address_sources(true, true);

    $upload = new html_inputfield(array(
        'type'     => 'file',
        'name'     => '_file[]',
        'id'       => 'rcmimportfile',
        'size'     => 40,
        'multiple' => 'multiple',
    ));
    $form  = html::p(null, html::label('rcmimportfile', $RCMAIL->gettext('importfromfile')) . $upload->show());
    $table = new html_table(array('cols' => 2));

    // addressbook selector
    if (count($writable_books) > 1) {
        $select = new html_select(array('name' => '_target', 'id' => 'rcmimporttarget', 'is_escaped' => true));

        foreach ($writable_books as $book) {
            $select->add($book['name'], $book['id']);
        }

        $table->add('title', html::label('rcmimporttarget', $RCMAIL->gettext('importtarget')));
        $table->add(null, $select->show($target));
    }
    else {
        $abook = new html_hiddenfield(array('name' => '_target', 'value' => key($writable_books)));
        $form .= $abook->show();
    }

    // selector for group import options
    if (count($writable_books) >= 1 || $writable_books[0]->groups) {
        $select = new html_select(array('name' => '_groups', 'id' => 'rcmimportgroups', 'is_escaped' => true));
        $select->add($RCMAIL->gettext('none'), '0');
        $select->add($RCMAIL->gettext('importgroupsall'), '1');
        $select->add($RCMAIL->gettext('importgroupsexisting'), '2');

        $table->add('title', html::label('rcmimportgroups', $RCMAIL->gettext('importgroups')));
        $table->add(null, $select->show(rcube_utils::get_input_value('_groups', rcube_utils::INPUT_GPC)));
    }

    // checkbox to replace the entire address book
    $check_replace = new html_checkbox(array('name' => '_replace', 'value' => 1, 'id' => 'rcmimportreplace'));
    $table->add('title', html::label('rcmimportreplace', $RCMAIL->gettext('importreplace')));
    $table->add(null, $check_replace->show(rcube_utils::get_input_value('_replace', rcube_utils::INPUT_GPC)));

    $form .= $table->show(array('id' => null) + $attrib);

    $OUTPUT->set_env('writable_source', !empty($writable_books));
    $OUTPUT->add_label('selectimportfile','importwait');
    $OUTPUT->add_gui_object('importform', $attrib['id']);

    $out = html::p(null, rcube::Q($RCMAIL->gettext('importdesc'), 'show'))
        . $OUTPUT->form_tag(array(
            'action'  => $RCMAIL->url('import'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data') + $attrib,
            $form);

    return $out;
}

/**
 * Render the confirmation page for the import process
 */
function rcmail_import_confirm($attrib)
{
    global $IMPORT_STATS, $RCMAIL;

    $vars = get_object_vars($IMPORT_STATS);
    $vars['names'] = $vars['skipped_names'] = '';

    $content = html::p(null, $RCMAIL->gettext(array(
        'name' => 'importconfirm',
        'nr'   => $IMPORT_STATS->inserted,
        'vars' => $vars,
        )) . ($IMPORT_STATS->names ? ':' : '.'));

    if ($IMPORT_STATS->names) {
        $content .= html::p('em', join(', ', array_map(array('rcube', 'Q'), $IMPORT_STATS->names)));
    }

    if ($IMPORT_STATS->skipped) {
        $content .= html::p(null, $RCMAIL->gettext(array(
                'name' => 'importconfirmskipped',
                'nr'   => $IMPORT_STATS->skipped,
                'vars' => $vars,
            )) . ':')
            . html::p('em', join(', ', array_map(array('rcube', 'Q'), $IMPORT_STATS->skipped_names)));
    }

    return html::div($attrib, $content);
}

/**
 * Create navigation buttons for the current import step
 */
function rcmail_import_buttons($attrib)
{
    global $IMPORT_STATS, $OUTPUT;

    $target = rcube_utils::get_input_value('_target', rcube_utils::INPUT_GPC);

    $attrib += array('type' => 'input');
    unset($attrib['name']);

    if (is_object($IMPORT_STATS)) {
        $attrib['class'] = trim($attrib['class'] . ' mainaction');
        $out = $OUTPUT->button(array('command' => 'list', 'prop' => $target, 'label' => 'done') + $attrib);
    }
    else {
        $cancel = $OUTPUT->button(array('command' => 'list', 'label' => 'cancel') + $attrib);
        $attrib['class'] = trim($attrib['class'] . ' mainaction');
        $out  = $OUTPUT->button(array('command' => 'import', 'label' => 'import') + $attrib);
        $out .= '&nbsp;';
        $out .= $cancel;
    }

    return $out;
}

/**
 * Returns the matching group id. If group doesn't exist, it'll be created if allowed.
 */
function rcmail_import_group_id($group_name, $CONTACTS, $create, &$import_groups)
{
    $group_id = 0;
    foreach ($import_groups as $group) {
        if (strtolower($group['name']) == strtolower($group_name)) {
            $group_id = $group['ID'];
            break;
        }
    }

    // create a new group
    if (!$group_id && $create) {
        $new_group = $CONTACTS->create_group($group_name);
        if (!$new_group['ID'])
            $new_group['ID'] = $new_group['id'];
        $import_groups[] = $new_group;
        $group_id = $new_group['ID'];
    }

    return $group_id;
}
