<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/upload_photo.inc                            |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2011, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Handles contact photo uploads                                       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// Supported image format types
// ImageMagick works with other non-image types (e.g.pdf) we don't want here
$IMAGE_TYPES = explode(',', 'jpeg,jpg,jp2,tiff,tif,bmp,eps,gif,png,png8,png24,png32,svg,ico'); 

// clear all stored output properties (like scripts and env vars)
$OUTPUT->reset();

if ($filepath = $_FILES['_photo']['tmp_name']) {
    // check file type and resize image
    $image     = new rcube_image($_FILES['_photo']['tmp_name']);
    $imageprop = $image->props();

    if (in_array(strtolower($imageprop['type']), $IMAGE_TYPES)
        && $imageprop['width'] && $imageprop['height']
    ) {
        $maxsize   = intval($RCMAIL->config->get('contact_photo_size', 160));
        $tmpfname  = tempnam($RCMAIL->config->get('temp_dir'), 'rcmImgConvert');
        $save_hook = 'attachment_upload';

        // scale image to a maximum size
        if (($imageprop['width'] > $maxsize || $imageprop['height'] > $maxsize) && $image->resize($maxsize, $tmpfname)) {
            $filepath  = $tmpfname;
            $save_hook = 'attachment_save';
        }

        // save uploaded file in storage backend
        $attachment = $RCMAIL->plugins->exec_hook($save_hook, array(
            'path' => $filepath,
            'size' => $_FILES['_photo']['size'],
            'name' => $_FILES['_photo']['name'],
            'mimetype' => 'image/' . $imageprop['type'],
            'group' => 'contact',
        ));
    }
    else {
        $attachment['error'] = $RCMAIL->gettext('invalidimageformat');
    }

    if ($attachment['status'] && !$attachment['abort']) {
        $file_id = $attachment['id'];
        $_SESSION['contacts']['files'][$file_id] = $attachment;
        $OUTPUT->command('replace_contact_photo', $file_id);
    }
    else {  // upload failed
        $err  = $_FILES['_photo']['error'];
        $size = $RCMAIL->show_bytes(rcube_utils::max_upload_size());

        if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE)
            $msg = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $size)));
        else if ($attachment['error'])
            $msg = $attachment['error'];
        else
            $msg = $RCMAIL->gettext('fileuploaderror');

        $OUTPUT->command('display_message', $msg, 'error');
    }
}
else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // if filesize exceeds post_max_size then $_FILES array is empty,
    // show filesizeerror instead of fileuploaderror
    if ($maxsize = ini_get('post_max_size'))
        $msg = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $RCMAIL->show_bytes(parse_bytes($maxsize)))));
    else
        $msg = $RCMAIL->gettext('fileuploaderror');

    $OUTPUT->command('display_message', $msg, 'error');
}

$OUTPUT->command('photo_upload_end');
$OUTPUT->send('iframe');
