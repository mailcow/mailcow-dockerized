<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/pagenav.inc                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Updates message page navigation controls                            |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

$uid   = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET);
$index = $RCMAIL->storage->index(null, rcmail_sort_column(), rcmail_sort_order());
$cnt   = $index->count_messages();

if ($cnt && ($pos = $index->exists($uid, true)) !== false) {
    $prev  = $pos ? $index->get_element($pos-1) : 0;
    $first = $pos ? $index->get_element('FIRST') : 0;
    $next  = $pos < $cnt-1 ? $index->get_element($pos+1) : 0;
    $last  = $pos < $cnt-1 ? $index->get_element('LAST') : 0;
}
else {
    // error, this will at least disable page navigation
    $OUTPUT->command('set_rowcount', '');
    $OUTPUT->send();
}

// Set UIDs and activate navigation buttons
if ($prev) {
    $OUTPUT->set_env('prev_uid', $prev);
    $OUTPUT->command('enable_command', 'previousmessage', 'firstmessage', true);
}

if ($next) {
    $OUTPUT->set_env('next_uid', $next);
    $OUTPUT->command('enable_command', 'nextmessage', 'lastmessage', true);
}

if ($first) {
    $OUTPUT->set_env('first_uid', $first);
}

if ($last) {
    $OUTPUT->set_env('last_uid', $last);
}

// Don't need a real messages count value
$OUTPUT->set_env('messagecount', 1);

// Set rowcount text
$OUTPUT->command('set_rowcount', $RCMAIL->gettext(array(
    'name' => 'messagenrof',
    'vars' => array('nr'  => $pos+1, 'count' => $cnt)
)));

$OUTPUT->send();
