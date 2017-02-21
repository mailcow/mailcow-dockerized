<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/headers.inc                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Fetch message headers in raw format for display                     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

if ($uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST)) {
    if ($pos = strpos($uid, '.')) {
        $message = new rcube_message($uid);
        $source  = $message->get_part_body(substr($uid, $pos + 1));
        $source  = substr($source, 0, strpos($source, "\r\n\r\n"));
    }
    else {
        $source = $RCMAIL->storage->get_raw_headers($uid);
    }

    if ($source !== false) {
        $source = trim(rcube_charset::clean($source));
        $source = htmlspecialchars($source);
        $source = preg_replace(
            array(
                '/\n[\t\s]+/',
                '/^([a-z0-9_:-]+)/im',
                '/\r?\n/'
            ),
            array(
                "\n&nbsp;&nbsp;&nbsp;&nbsp;",
                '<font class="bold">\1</font>',
                '<br />'
            ), $source);

        $OUTPUT->command('set_headers', $source);
    }
    else {
        $RCMAIL->output->show_message('messageopenerror', 'error');
    }

    $OUTPUT->send();
}

exit;
