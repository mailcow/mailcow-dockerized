<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/show.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Show contact details                                                |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// Get contact ID and source ID from request
$cids   = rcmail_get_cids();
$source = key($cids);
$cid    = $cids ? array_shift($cids[$source]) : null;

// Initialize addressbook source
$CONTACTS  = rcmail_contact_source($source, true);
$SOURCE_ID = $source;

// read contact record (or get the one defined in 'save' action)
if ($cid && ($record = ($CONTACT_RECORD ?: $CONTACTS->get_record($cid, true)))) {
    $OUTPUT->set_env('readonly', $CONTACTS->readonly || $record['readonly']);
    $OUTPUT->set_env('cid', $record['ID']);

    // remember current search request ID (if in search mode)
    if ($search = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GET)) {
        $OUTPUT->set_env('search_request', $search);
    }
}

// get address book name (for display)
rcmail_set_sourcename($CONTACTS);

// Disable qr-code if php-gd is not installed
$OUTPUT->set_env('qrcode', function_exists('imagecreate'));
$OUTPUT->add_label('qrcode');

$OUTPUT->add_handlers(array(
    'contacthead'    => 'rcmail_contact_head',
    'contactdetails' => 'rcmail_contact_details',
    'contactphoto'   => 'rcmail_contact_photo',
));

$OUTPUT->send('contact');



function rcmail_contact_head($attrib)
{
    global $CONTACTS, $RCMAIL;

    // check if we have a valid result
    if (!(($result = $CONTACTS->get_result()) && ($record = $result->first()))) {
        $RCMAIL->output->show_message('contactnotfound', 'error');
        return false;
    }

    $form = array(
        'head' => array(  // section 'head' is magic!
            'name' => $RCMAIL->gettext('contactnameandorg'),
            'content' => array(
                'prefix'       => array('type' => 'text'),
                'firstname'    => array('type' => 'text'),
                'middlename'   => array('type' => 'text'),
                'surname'      => array('type' => 'text'),
                'suffix'       => array('type' => 'text'),
                'name'         => array('type' => 'text'),
                'nickname'     => array('type' => 'text'),
                'organization' => array('type' => 'text'),
                'department'   => array('type' => 'text'),
                'jobtitle'     => array('type' => 'text'),
            ),
        ),
    );

    unset($attrib['name']);
    return rcmail_contact_form($form, $record, $attrib);
}


function rcmail_contact_details($attrib)
{
    global $CONTACTS, $RCMAIL, $CONTACT_COLTYPES;

    // check if we have a valid result
    if (!(($result = $CONTACTS->get_result()) && ($record = $result->first()))) {
        return false;
    }

    $i_size = $attrib['size'] ?: 40;

    $form = array(
        'contact' => array(
            'name'    => $RCMAIL->gettext('properties'),
            'content' => array(
              'email' => array('size' => $i_size, 'render_func' => 'rcmail_render_email_value'),
              'phone' => array('size' => $i_size),
              'address' => array(),
              'website' => array('size' => $i_size, 'render_func' => 'rcmail_render_url_value'),
              'im' => array('size' => $i_size),
            ),
        ),
        'personal' => array(
            'name'    => $RCMAIL->gettext('personalinfo'),
            'content' => array(
                'gender' => array('size' => $i_size),
                'maidenname' => array('size' => $i_size),
                'birthday' => array('size' => $i_size),
                'anniversary' => array('size' => $i_size),
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
                'notes' => array('type' => 'textarea', 'label' => false),
            ),
        );
    }

    if ($CONTACTS->groups) {
        $form['groups'] = array(
            'name'    => $RCMAIL->gettext('groups'),
            'content' => rcmail_contact_record_groups($record['ID']),
        );
    }

    return rcmail_contact_form($form, $record);
}


function rcmail_render_email_value($email)
{
    global $RCMAIL;

    return html::a(array(
        'href' => 'mailto:' . $email,
        'onclick' => sprintf("return %s.command('compose','%s',this)", rcmail_output::JS_OBJECT_NAME, rcube::JQ($email)),
        'title' => $RCMAIL->gettext('composeto'),
        'class' => 'email',
    ), rcube::Q($email));
}


function rcmail_render_url_value($url)
{
    $prefix = preg_match('!^(http|ftp)s?://!', $url) ? '' : 'http://';
    return html::a(array(
        'href' => $prefix . $url,
        'target' => '_blank',
        'class' => 'url',
    ), rcube::Q($url));
}


function rcmail_contact_record_groups($contact_id)
{
    global $RCMAIL, $CONTACTS, $GROUPS;

    $GROUPS = $CONTACTS->list_groups();

    if (empty($GROUPS)) {
        return '';
    }

    $members  = $CONTACTS->get_record_groups($contact_id);
    $table    = new html_table(array('cols' => 2, 'cellspacing' => 0, 'border' => 0));
    $checkbox = new html_checkbox(array('name' => '_gid[]',
        'class' => 'groupmember', 'disabled' => $CONTACTS->readonly));

    foreach ($GROUPS as $group) {
        $gid = $group['ID'];
        $table->add(null, $checkbox->show($members[$gid] ? $gid : null,
            array('value' => $gid, 'id' => 'ff_gid' . $gid)));
        $table->add(null, html::label('ff_gid' . $gid, rcube::Q($group['name'])));
    }

    $hiddenfields = new html_hiddenfield(array('name' => '_source', 'value' => rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC)));
    $hiddenfields->add(array('name' => '_cid', 'value' => $contact_id));

    $form_start = $RCMAIL->output->request_form(array(
        'name' => "form", 'method' => "post",
        'task' => $RCMAIL->task, 'action' => 'save',
        'request' => 'save.'.intval($contact_id),
        'noclose' => true), $hiddenfields->show());
    $form_end = '</form>';

    $RCMAIL->output->add_gui_object('editform', 'form');
    $RCMAIL->output->add_label('addingmember', 'removingmember');

    return $form_start . html::tag('fieldset', 'contactfieldgroup contactgroups', $table->show()) . $form_end;
}
