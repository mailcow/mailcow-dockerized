<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/viewsource.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Display a mail message similar as a usual mail application does     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

if (!empty($_GET['_save'])) {
    $RCMAIL->request_security_check(rcube_utils::INPUT_GET);
}

ob_end_clean();

// similar code as in program/steps/mail/get.inc
if ($uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET)) {
    if ($pos = strpos($uid, '.')) {
        $message = new rcube_message($uid);
        $headers = $message->headers;
        $part_id = substr($uid, $pos + 1);
    }
    else {
        $headers = $RCMAIL->storage->get_message_headers($uid);
    }

    $charset = $headers->charset ?: $RCMAIL->config->get('default_charset');

    header("Content-Type: text/plain; charset={$charset}");

    if (!empty($_GET['_save'])) {
        $browser  = $RCMAIL->output->browser;
        $subject  = rcube_mime::decode_header($headers->subject, $headers->charset);
        $filename = rcmail_filename_from_subject(mb_substr($subject, 0, 128));
        $filename = ($filename ?: $uid)  . '.eml';
        $filename = $browser->ie ? rawurlencode($filename) : addcslashes($filename, '"');

        header("Content-Length: {$headers->size}");
        header("Content-Disposition: attachment; filename=\"$filename\"");
    }

    if (isset($message)) {
        $message->get_part_body($part_id, empty($_GET['_save']), 0, -1);
    }
    else {
        $RCMAIL->storage->print_raw_body($uid, empty($_GET['_save']));
    }
}
else {
    rcube::raise_error(array(
            'code'    => 500,
            'type'    => 'php',
            'file'    => __FILE__,
            'line'    => __LINE__,
            'message' => "Message UID $uid not found"
        ),
        true, true);
}

exit;


/**
 * Helper function to convert message subject into filename
 */
function rcmail_filename_from_subject($str)
{
    $str = preg_replace('/[:\t\n\r\0\x0B\/]+\s*/', ' ', $str);

    return trim($str, " \t\n\r\0\x0B./_");
}
