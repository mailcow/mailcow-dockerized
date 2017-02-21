<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/edit.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Show edit form for a contact entry or to add a new one              |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

if ($RCMAIL->action == 'edit') {
    // Get contact ID and source ID from request
    $cids   = rcmail_get_cids();
    $source = key($cids);
    $cid    = array_shift($cids[$source]);

    // Initialize addressbook
    $CONTACTS = rcmail_contact_source($source, true);

    // Contact edit
    if ($cid && ($record = $CONTACTS->get_record($cid, true))) {
        $OUTPUT->set_env('cid', $record['ID']);
    }

    // editing not allowed here
    if ($CONTACTS->readonly || $record['readonly']) {
        $OUTPUT->show_message('sourceisreadonly');
        $RCMAIL->overwrite_action('show');
        return;
    }
}
else {
    $source = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);

    if (strlen($source)) {
        $CONTACTS = $RCMAIL->get_address_book($source, true);
    }

    if (!$CONTACTS || $CONTACTS->readonly) {
        $CONTACTS = $RCMAIL->get_address_book(-1, true);
        $source   = $RCMAIL->get_address_book_id($CONTACTS);
    }

    // Initialize addressbook
    $CONTACTS = rcmail_contact_source($source, true);
}

$SOURCE_ID = $source;
rcmail_set_sourcename($CONTACTS);


$OUTPUT->add_handlers(array(
    'contactedithead' => 'rcmail_contact_edithead',
    'contacteditform' => 'rcmail_contact_editform',
    'contactphoto'    => 'rcmail_contact_photo',
    'photouploadform' => 'rcmail_upload_photo_form',
    'sourceselector'  => 'rcmail_source_selector',
    'filedroparea'    => 'rcmail_photo_drop_area',
));

$OUTPUT->set_pagetitle($RCMAIL->gettext(($RCMAIL->action == 'add' ? 'addcontact' : 'editcontact')));

if ($RCMAIL->action == 'add' && $OUTPUT->template_exists('contactadd')) {
    $OUTPUT->send('contactadd');
}

// this will be executed if no template for addcontact exists
$OUTPUT->send('contactedit');



function rcmail_get_edit_record()
{
    global $RCMAIL, $CONTACTS;

     // check if we have a valid result
     if ($GLOBALS['EDIT_RECORD']) {
         $record = $GLOBALS['EDIT_RECORD'];
     }
     else if ($RCMAIL->action != 'add'
         && !(($result = $CONTACTS->get_result()) && ($record = $result->first()))
     ) {
         $RCMAIL->output->show_message('contactnotfound', 'error');
         return false;
     }

     return $record;
}

function rcmail_contact_edithead($attrib)
{
    global $RCMAIL;

    // check if we have a valid result
    $record = rcmail_get_edit_record();
    $i_size = $attrib['size'] ?: 20;

    $form = array(
        'head' => array(
            'name' => $RCMAIL->gettext('contactnameandorg'),
            'content' => array(
                'prefix' => array('size' => $i_size),
                'firstname' => array('size' => $i_size, 'visible' => true),
                'middlename' => array('size' => $i_size),
                'surname' => array('size' => $i_size, 'visible' => true),
                'suffix' => array('size' => $i_size),
                'name' => array('size' => 2*$i_size),
                'nickname' => array('size' => 2*$i_size),
                'organization' => array('size' => 2*$i_size),
                'department' => array('size' => 2*$i_size),
                'jobtitle' => array('size' => 2*$i_size),
            )
        )
    );

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form'], $attrib['name'], $attrib['size']);

    // return the address edit form
    $out = rcmail_contact_form($form, $record, $attrib);

    return $form_start . $out . $form_end;
}

function rcmail_contact_editform($attrib)
{
    global $RCMAIL, $CONTACT_COLTYPES;

    $record = rcmail_get_edit_record();

    // copy (parsed) address template to client
    if (preg_match_all('/\{([a-z0-9]+)\}([^{]*)/i', $RCMAIL->config->get('address_template', ''), $templ, PREG_SET_ORDER))
      $RCMAIL->output->set_env('address_template', $templ);

    $i_size = $attrib['size'] ?: 40;
    $t_rows = $attrib['textarearows'] ?: 10;
    $t_cols = $attrib['textareacols'] ?: 40;

    $form = array(
        'contact' => array(
            'name'    => $RCMAIL->gettext('properties'),
            'content' => array(
                'email' => array('size' => $i_size, 'maxlength' => 254, 'visible' => true),
                'phone' => array('size' => $i_size, 'visible' => true),
                'address' => array('visible' => true),
                'website' => array('size' => $i_size),
                'im' => array('size' => $i_size),
            ),
        ),
        'personal' => array(
            'name'    => $RCMAIL->gettext('personalinfo'),
            'content' => array(
                'gender' => array('visible' => true),
                'maidenname' => array('size' => $i_size),
                'birthday' => array('visible' => true),
                'anniversary' => array(),
                'manager' => array('size' => $i_size),
                'assistant' => array('size' => $i_size),
                'spouse' => array('size' => $i_size),
            ),
        ),
    );

    if (isset($CONTACT_COLTYPES['notes'])) {
        $form['notes'] = array(
            'name'    => $RCMAIL->gettext('notes'),
            'content' => array(
                'notes' => array('size' => $t_cols, 'rows' => $t_rows, 'label' => false, 'visible' => true, 'limit' => 1),
            ),
            'single' => true,
        );
    }

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form']);

    // return the complete address edit form as table
    $out = rcmail_contact_form($form, $record, $attrib);

    return $form_start . $out . $form_end;
}

function rcmail_upload_photo_form($attrib)
{
    global $RCMAIL;

    $hidden           = new html_hiddenfield(array('name' => '_cid', 'value' => $GLOBALS['cid']));
    $attrib['prefix'] = $hidden->show();
    $input_attr       = array('name' => '_photo', 'accept' => 'image/*');

    $RCMAIL->output->add_label('addphoto','replacephoto');

    return $RCMAIL->upload_form($attrib, 'uploadform', 'upload-photo', $input_attr);
}

// similar function as in /steps/settings/edit_identity.inc
function get_form_tags($attrib)
{
    global $CONTACTS, $EDIT_FORM, $RCMAIL, $SOURCE_ID;

    $form_start = $form_end = '';

    if (empty($EDIT_FORM)) {
        $hiddenfields = new html_hiddenfield();

        if ($RCMAIL->action == 'edit')
            $hiddenfields->add(array('name' => '_source', 'value' => $SOURCE_ID));
        $hiddenfields->add(array('name' => '_gid', 'value' => $CONTACTS->group_id));
        $hiddenfields->add(array('name' => '_search', 'value' => rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC)));

        if (($result = $CONTACTS->get_result()) && ($record = $result->first()))
            $hiddenfields->add(array('name' => '_cid', 'value' => $record['ID']));

        $form_start = $RCMAIL->output->request_form(array(
            'name' => "form", 'method' => "post",
            'task' => $RCMAIL->task, 'action' => 'save',
            'request' => 'save.'.intval($record['ID']),
            'noclose' => true) + $attrib, $hiddenfields->show());
        $form_end = !strlen($attrib['form']) ? '</form>' : '';

        $EDIT_FORM = $attrib['form'] ?: 'form';
        $RCMAIL->output->add_gui_object('editform', $EDIT_FORM);
    }

    return array($form_start, $form_end); 
}

function rcmail_source_selector($attrib)
{
    global $RCMAIL, $SOURCE_ID;

    $sources_list = $RCMAIL->get_address_sources(true, true);

    if (count($sources_list) < 2) {
        $source = $sources_list[$SOURCE_ID];
        $hiddenfield = new html_hiddenfield(array('name' => '_source', 'value' => $SOURCE_ID));
        return html::span($attrib, $source['name'] . $hiddenfield->show());
    }

    $attrib['name']       = '_source';
    $attrib['is_escaped'] = true;
    $attrib['onchange']   = rcmail_output::JS_OBJECT_NAME . ".command('save', 'reload', this.form)";

    $select = new html_select($attrib);

    foreach ($sources_list as $source)
        $select->add($source['name'], $source['id']);

    return $select->show($SOURCE_ID);
}


/**
 * Register container as active area to drop photos onto
 */
function rcmail_photo_drop_area($attrib)
{
    global $OUTPUT;

    if ($attrib['id']) {
        $OUTPUT->add_gui_object('filedrop', $attrib['id']);
        $OUTPUT->set_env('filedrop', array('action' => 'upload-photo', 'fieldname' => '_photo', 'single' => 1, 'filter' => '^image/.+'));
    }
}
