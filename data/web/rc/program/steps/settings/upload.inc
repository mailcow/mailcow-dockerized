<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/upload.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Handles image uploads                                               |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// Upload progress update
if (!empty($_GET['_progress'])) {
    $RCMAIL->upload_progress();
}

$from = rcube_utils::get_input_value('_from', rcube_utils::INPUT_GET);
$type = preg_replace('/(add|edit)-/', '', $from);

if ($RCMAIL->action == 'upload-display') {
    $id = 'undefined';

    if (preg_match('/^rcmfile(\w+)$/', $_GET['_file'], $regs)) {
        $id = $regs[1];
    }

    $RCMAIL->display_uploaded_file($_SESSION[$type]['files'][$id]);

    exit;
}


// Supported image format types
$IMAGE_TYPES = explode(',', 'jpeg,jpg,jp2,tiff,tif,bmp,eps,gif,png,png8,png24,png32,svg,ico');

// clear all stored output properties (like scripts and env vars)
$OUTPUT->reset();

$max_size  = $RCMAIL->config->get($type . '_image_size', 64) * 1024;
$post_size = $RCMAIL->show_bytes(rcube_utils::max_upload_size());
$uploadid  = rcube_utils::get_input_value('_uploadid', rcube_utils::INPUT_GET);


if (is_array($_FILES['_file']['tmp_name'])) {
    $multiple = count($_FILES['_file']['tmp_name']) > 1;

    foreach ($_FILES['_file']['tmp_name'] as $i => $filepath) {
        // Process uploaded attachment if there is no error
        $err = $_FILES['_file']['error'][$i];

        if (!$err) {
            if ($max_size < $_FILES['_file']['size'][$i]) {
                $err = 'size_error';
            }
            // check image file type
            else {
                $image     = new rcube_image($filepath);
                $imageprop = $image->props();

                if (!in_array(strtolower($imageprop['type']), $IMAGE_TYPES)) {
                    $err = 'type_error';
                }
            }
        }

        // save uploaded image in storage backend
        if (!$err) {
            $attachment = $RCMAIL->plugins->exec_hook('attachment_upload', array(
                'path'     => $filepath,
                'size'     => $_FILES['_file']['size'][$i],
                'name'     => $_FILES['_file']['name'][$i],
                'mimetype' => 'image/' . $imageprop['type'],
                'group'    => $type,
            ));
        }

        if (!$err && $attachment['status'] && !$attachment['abort']) {
            $id = $attachment['id'];

            // store new file in session
            unset($attachment['status'], $attachment['abort']);
            $RCMAIL->session->append($type . '.files', $id, $attachment);

            $content = rcube::Q($attachment['name']);

            $OUTPUT->command('add2attachment_list', "rcmfile$id", array(
                    'html'      => $content,
                    'name'      => $attachment['name'],
                    'mimetype'  => $attachment['mimetype'],
                    'classname' => rcube_utils::file2class($attachment['mimetype'], $attachment['name']),
                    'complete'  => true
                ),
                $uploadid
            );
        }
        else {
            if ($err == 'type_error') {
                $msg = $RCMAIL->gettext('invalidimageformat');
            }
            else if ($err == 'size_error') {
                $msg = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $max_size)));
            }
            else if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
                $msg = $RCMAIL->gettext(array('name' => 'filesizeerror', 'vars' => array('size' => $post_size)));
            }
            else if ($attachment['error']) {
                $msg = $attachment['error'];
            }
            else {
                $msg = $RCMAIL->gettext('fileuploaderror');
            }

            $OUTPUT->command('display_message', $msg, 'error');
        }
    }
}
else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // if filesize exceeds post_max_size then $_FILES array is empty,
    // show filesizeerror instead of fileuploaderror
    if ($maxsize = ini_get('post_max_size')) {
        $msg = $RCMAIL->gettext(array(
            'name' => 'filesizeerror',
            'vars' => array('size' => $RCMAIL->show_bytes(parse_bytes($maxsize)))
        ));
    }
    else {
        $msg = $RCMAIL->gettext('fileuploaderror');
    }

    $OUTPUT->command('display_message', $msg, 'error');
    $OUTPUT->command('remove_from_attachment_list', $uploadid);
}

$OUTPUT->send('iframe');
