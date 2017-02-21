<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/photo.inc                                   |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Show contact photo                                                  |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// Get contact ID and source ID from request
$cids   = rcmail_get_cids();
$source = key($cids);
$cid    = $cids ? array_shift($cids[$source]) : null;

// read the referenced file
if (($file_id = rcube_utils::get_input_value('_photo', rcube_utils::INPUT_GPC)) && ($tempfile = $_SESSION['contacts']['files'][$file_id])) {
    $tempfile = $RCMAIL->plugins->exec_hook('attachment_display', $tempfile);
    if ($tempfile['status']) {
        if ($tempfile['data'])
            $data = $tempfile['data'];
        else if ($tempfile['path'])
            $data = file_get_contents($tempfile['path']);
    }
}
else {
    // by email, search for contact first
    if ($email = rcube_utils::get_input_value('_email', rcube_utils::INPUT_GPC)) {
        foreach ($RCMAIL->get_address_sources() as $s) {
            $abook = $RCMAIL->get_address_book($s['id']);
            $result = $abook->search(array('email'), $email, 1, true, true, 'photo');
            while ($result && ($record = $result->iterate())) {
                if ($record['photo'])
                    break 2;
            }
        }
    }

    // by contact id
    if (!$record && $cid) {
        // Initialize addressbook source
        $CONTACTS  = rcmail_contact_source($source, true);
        $SOURCE_ID = $source;
        // read contact record
        $record = $CONTACTS->get_record($cid, true);
    }

    if ($record['photo']) {
        $data = is_array($record['photo']) ? $record['photo'][0] : $record['photo'];
        if (!preg_match('![^a-z0-9/=+-]!i', $data))
            $data = base64_decode($data, true);
    }
}

// let plugins do fancy things with contact photos
$plugin = $RCMAIL->plugins->exec_hook('contact_photo',
    array('record' => $record, 'email' => $email, 'data' => $data));

// redirect to url provided by a plugin
if ($plugin['url']) {
    $RCMAIL->output->redirect($plugin['url']);
}

$data = $plugin['data'];

// detect if photo data is an URL
if (strlen($data) < 1024 && filter_var($data, FILTER_VALIDATE_URL)) {
    $RCMAIL->output->redirect($data);
}

// cache for one day if requested by email
if (!$cid && $email) {
    $RCMAIL->output->future_expire_header(86400);
}

if ($data) {
    header('Content-Type: ' . rcube_mime::image_content_type($data));
    echo $data;
}
else if (!empty($_GET['_error'])) {
    header('HTTP/1.0 404 Photo not found');
}
else {
    header('Content-Type: image/gif');
    echo base64_decode(rcmail_output::BLANK_GIF);
}
exit;
