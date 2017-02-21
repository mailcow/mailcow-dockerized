<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/sendmdn.inc                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Send a message disposition notification for a specific mail         |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// only process ajax requests
if (!$OUTPUT->ajax_call) {
    return;
}

if (!empty($_POST['_uid'])) {
    $sent = rcmail_send_mdn(rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST), $smtp_error);
}

// show either confirm or error message
if ($sent) {
    $OUTPUT->set_env('mdn_request', false);
    $OUTPUT->show_message('receiptsent', 'confirmation');
}
else if ($smtp_error) {
    $OUTPUT->show_message($smtp_error['label'], 'error', $smtp_error['vars']);
}
else {
    $OUTPUT->show_message('errorsendingreceipt', 'error');
}

$OUTPUT->send();
