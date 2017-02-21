<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/copy.inc                                           |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Copy the submitted messages to a specific mailbox                   |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// only process ajax requests
if (!$OUTPUT->ajax_call) {
    return;
}

// copy messages
if (!empty($_POST['_uid']) && strlen($_POST['_target_mbox'])) {
    $target  = rcube_utils::get_input_value('_target_mbox', rcube_utils::INPUT_POST, true);
    $sources = array();

    foreach (rcmail::get_uids(null, null, $multifolder) as $mbox => $uids) {
        if ($mbox === $target) {
            $copied++;
        }
        else {
            $copied += (int)$RCMAIL->storage->copy_message($uids, $target, $mbox);
            $sources[] = $mbox;
        }
    }

    if (!$copied) {
        // send error message
        $RCMAIL->display_server_error('errorcopying');
        $OUTPUT->send();
        exit;
    }
    else {
        $OUTPUT->show_message('messagecopied', 'confirmation');
    }

    rcmail_send_unread_count($target, true);

    $OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $multifolder ? $sources[0] : 'INBOX'));
}
// unknown action or missing query param
else {
    $OUTPUT->show_message('internalerror', 'error');
}

// send response
$OUTPUT->send();
