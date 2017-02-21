<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/qrcode.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Show contact data as QR code                                        |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// Get contact ID and source ID from request
$cids   = rcmail_get_cids();
$source = key($cids);
$cid    = $cids ? array_shift($cids[$source]) : null;

// read contact record
$abook   = rcmail_contact_source($source, true);
$contact = $abook->get_record($cid, true);

// generate QR code image
if ($data = rcmail_contact_qrcode($contact)) {
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($data));
    echo $data;
}
else {
    header('HTTP/1.0 404 Contact not found');
}

exit;


function rcmail_contact_qrcode($contact)
{
    $vcard = new rcube_vcard();

    // QR code input is limited, use only common fields
    $fields = array('firstname', 'surname', 'middlename', 'nickname', 'organization',
        'prefix', 'suffix', 'phone', 'email', 'jobtitle');

    foreach ($contact as $field => $value) {
        list($field, $section) = explode(':', $field, 2);
        if (in_array($field, $fields)) {
            foreach ((array) $value as $v) {
                $vcard->set($field, $v, $section);
            }
        }
    }

    $data = $vcard->export();

    $qrCode = new Endroid\QrCode\QrCode();
    $qrCode
        ->setText($data)
        ->setSize(300)
        ->setPadding(0)
        ->setErrorCorrection('high')
    //    ->setLabel('Scan the code')
    //    ->setLabelFontSize(16)
        ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
        ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0));

    return $qrCode->get('png');
}
