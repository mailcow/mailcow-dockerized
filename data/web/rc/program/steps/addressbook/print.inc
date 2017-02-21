<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/print.inc                                   |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2015, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Print contact details                                               |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <machniak@kolabsys.com>                   |
 +-----------------------------------------------------------------------+
*/

// Get contact ID and source ID from request
$cids   = rcmail_get_cids();
$source = key($cids);
$cid    = $cids ? array_shift($cids[$source]) : null;

// Initialize addressbook source
$CONTACTS  = rcmail_contact_source($source, true);
$SOURCE_ID = $source;

// read contact record
if ($cid && $CONTACTS) {
    $record = $CONTACTS->get_record($cid, true);
}

$OUTPUT->add_handlers(array(
    'contacthead'    => 'rcmail_contact_head',
    'contactdetails' => 'rcmail_contact_details',
    'contactphoto'   => 'rcmail_contact_photo',
));

$OUTPUT->send('contactprint');



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
                'prefix'     => array(),
                'name'       => array(),
                'firstname'  => array(),
                'middlename' => array(),
                'surname'    => array(),
                'suffix'     => array(),
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
                'organization' => array(),
                'department'   => array(),
                'jobtitle'     => array(),
                'email'        => array(),
                'phone'        => array(),
                'address'      => array(),
                'website'      => array(),
                'im'           => array(),
                'groups'       => array(),
            ),
        ),
        'personal' => array(
            'name'    => $RCMAIL->gettext('personalinfo'),
            'content' => array(
                'nickname'    => array(),
                'gender'      => array(),
                'maidenname'  => array(),
                'birthday'    => array(),
                'anniversary' => array(),
                'manager'     => array(),
                'assistant'   => array(),
                'spouse'      => array(),
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
        $groups = $CONTACTS->get_record_groups($record['ID']);
        if (!empty($groups)) {
            $form['contact']['content']['groups'] = array(
                'value' => rcube::Q(implode(', ', $groups)),
                'label' => $RCMAIL->gettext('groups')
            );
        }
    }

    return rcmail_contact_form($form, $record);
}
