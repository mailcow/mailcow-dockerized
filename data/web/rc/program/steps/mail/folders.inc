<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/folders.inc                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Implement folder operations line EXPUNGE and Clear                  |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// only process ajax requests
if (!$OUTPUT->ajax_call) {
    return;
}

$mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true);

// send EXPUNGE command
if ($RCMAIL->action == 'expunge') {
    $success = $RCMAIL->storage->expunge_folder($mbox);

    // reload message list if current mailbox
    if ($success) {
        $OUTPUT->show_message('folderexpunged', 'confirmation');

        if (!empty($_REQUEST['_reload'])) {
            $OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $mbox));
            $OUTPUT->command('message_list.clear');
            $RCMAIL->action = 'list';
            return;
        }
    }
    else {
        $RCMAIL->display_server_error();
    }
}
// clear mailbox
else if ($RCMAIL->action == 'purge') {
    $delimiter    = $RCMAIL->storage->get_hierarchy_delimiter();
    $trash_mbox   = $RCMAIL->config->get('trash_mbox');
    $junk_mbox    = $RCMAIL->config->get('junk_mbox');
    $trash_regexp = '/^' . preg_quote($trash_mbox . $delimiter, '/') . '/';
    $junk_regexp  = '/^' . preg_quote($junk_mbox . $delimiter, '/') . '/';

    // we should only be purging trash and junk (or their subfolders)
    if ($mbox == $trash_mbox || $mbox == $junk_mbox
        || preg_match($trash_regexp, $mbox) || preg_match($junk_regexp, $mbox)
    ) {
        $success = $RCMAIL->storage->clear_folder($mbox);

        if ($success) {
            $OUTPUT->show_message('folderpurged', 'confirmation');

            if (!empty($_REQUEST['_reload'])) {
                $OUTPUT->set_env('messagecount', 0);
                $OUTPUT->set_env('pagecount', 0);
                $OUTPUT->set_env('exists', 0);
                $OUTPUT->command('message_list.clear');
                $OUTPUT->command('set_rowcount', rcmail_get_messagecount_text(), $mbox);
                $OUTPUT->command('set_unread_count', $mbox, 0);
                $OUTPUT->command('set_quota', $RCMAIL->quota_content(null, $mbox));
                rcmail_set_unseen_count($mbox, 0);

                // set trash folder state
                if ($mbox === $trash_mbox) {
                    $OUTPUT->command('set_trash_count', 0);
                }
            }
        }
        else {
            $RCMAIL->display_server_error();
        }
    }
}

$OUTPUT->send();
