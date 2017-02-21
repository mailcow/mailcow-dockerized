<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/save_prefs.inc                                 |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Save user preferences to DB and to the current session              |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

$CURR_SECTION = rcube_utils::get_input_value('_section', rcube_utils::INPUT_POST);
$a_user_prefs = array();

// set options for specified section
switch ($CURR_SECTION) {
case 'general':
    $a_user_prefs = array(
        'language'     => isset($_POST['_language']) ? rcube_utils::get_input_value('_language', rcube_utils::INPUT_POST) : $CONFIG['language'],
        'timezone'     => isset($_POST['_timezone']) ? rcube_utils::get_input_value('_timezone', rcube_utils::INPUT_POST) : $CONFIG['timezone'],
        'date_format'  => isset($_POST['_date_format']) ? rcube_utils::get_input_value('_date_format', rcube_utils::INPUT_POST) : $CONFIG['date_format'],
        'time_format'  => isset($_POST['_time_format']) ? rcube_utils::get_input_value('_time_format', rcube_utils::INPUT_POST) : ($CONFIG['time_format'] ? $CONFIG['time_format'] : 'H:i'),
        'prettydate'   => isset($_POST['_pretty_date']),
        'refresh_interval' => isset($_POST['_refresh_interval']) ? intval($_POST['_refresh_interval'])*60 : $CONFIG['refresh_interval'],
        'standard_windows' => isset($_POST['_standard_windows']),
        'skin'         => isset($_POST['_skin']) ? rcube_utils::get_input_value('_skin', rcube_utils::INPUT_POST) : $CONFIG['skin'],
    );

    // compose derived date/time format strings
    if ((isset($_POST['_date_format']) || isset($_POST['_time_format'])) && $a_user_prefs['date_format'] && $a_user_prefs['time_format']) {
        $a_user_prefs['date_short'] = 'D ' . $a_user_prefs['time_format'];
        $a_user_prefs['date_long']  = $a_user_prefs['date_format'] . ' ' . $a_user_prefs['time_format'];
    }

    break;

case 'mailbox':
    $a_user_prefs = array(
        'mail_read_time'       => isset($_POST['_mail_read_time']) ? intval($_POST['_mail_read_time']) : $CONFIG['mail_read_time'],
        'autoexpand_threads'   => isset($_POST['_autoexpand_threads']) ? intval($_POST['_autoexpand_threads']) : 0,
        'mdn_requests'         => isset($_POST['_mdn_requests']) ? intval($_POST['_mdn_requests']) : 0,
        'check_all_folders'    => isset($_POST['_check_all_folders']),
        'mail_pagesize'        => is_numeric($_POST['_mail_pagesize']) ? max(2, intval($_POST['_mail_pagesize'])) : $CONFIG['mail_pagesize'],
    );

    break;

case 'mailview':
    $a_user_prefs = array(
        'message_extwin'  => intval($_POST['_message_extwin']),
        'message_show_email' => isset($_POST['_message_show_email']),
        'prefer_html'     => isset($_POST['_prefer_html']),
        'inline_images'   => isset($_POST['_inline_images']),
        'show_images'     => isset($_POST['_show_images']) ? intval($_POST['_show_images']) : 0,
        'display_next'    => isset($_POST['_display_next']),
        'default_charset' => rcube_utils::get_input_value('_default_charset', rcube_utils::INPUT_POST),
    );

    break;

case 'compose':
    $a_user_prefs = array(
        'compose_extwin'     => intval($_POST['_compose_extwin']),
        'htmleditor'         => intval($_POST['_htmleditor']),
        'draft_autosave'     => isset($_POST['_draft_autosave']) ? intval($_POST['_draft_autosave']) : 0,
        'mime_param_folding' => isset($_POST['_mime_param_folding']) ? intval($_POST['_mime_param_folding']) : 0,
        'force_7bit'         => isset($_POST['_force_7bit']),
        'mdn_default'        => isset($_POST['_mdn_default']),
        'dsn_default'        => isset($_POST['_dsn_default']),
        'reply_same_folder'  => isset($_POST['_reply_same_folder']),
        'spellcheck_before_send' => isset($_POST['_spellcheck_before_send']),
        'spellcheck_ignore_syms' => isset($_POST['_spellcheck_ignore_syms']),
        'spellcheck_ignore_nums' => isset($_POST['_spellcheck_ignore_nums']),
        'spellcheck_ignore_caps' => isset($_POST['_spellcheck_ignore_caps']),
        'show_sig'           => isset($_POST['_show_sig']) ? intval($_POST['_show_sig']) : 1,
        'reply_mode'         => isset($_POST['_reply_mode']) ? intval($_POST['_reply_mode']) : 0,
        'sig_below'          => isset($_POST['_sig_below']),
        'strip_existing_sig' => isset($_POST['_strip_existing_sig']),
        'sig_separator'      => isset($_POST['_sig_separator']),
        'default_font'       => rcube_utils::get_input_value('_default_font', rcube_utils::INPUT_POST),
        'default_font_size'  => rcube_utils::get_input_value('_default_font_size', rcube_utils::INPUT_POST),
        'reply_all_mode'     => intval($_POST['_reply_all_mode']),
        'forward_attachment' => !empty($_POST['_forward_attachment']),
        'compose_save_localstorage' => intval($_POST['_compose_save_localstorage']),
    );

    break;

case 'addressbook':
    $a_user_prefs = array(
        'default_addressbook'  => rcube_utils::get_input_value('_default_addressbook', rcube_utils::INPUT_POST, true),
        'autocomplete_single'  => isset($_POST['_autocomplete_single']),
        'addressbook_sort_col' => rcube_utils::get_input_value('_addressbook_sort_col', rcube_utils::INPUT_POST),
        'addressbook_name_listing' => intval(rcube_utils::get_input_value('_addressbook_name_listing', rcube_utils::INPUT_POST)),
        'addressbook_pagesize' => is_numeric($_POST['_addressbook_pagesize']) ? max(2, intval($_POST['_addressbook_pagesize'])) : $CONFIG['addressbook_pagesize'],
    );

    break;

case 'server':
    $a_user_prefs = array(
        'read_when_deleted' => isset($_POST['_read_when_deleted']),
        'skip_deleted'      => isset($_POST['_skip_deleted']),
        'flag_for_deletion' => isset($_POST['_flag_for_deletion']),
        'delete_always'     => isset($_POST['_delete_always']),
        'delete_junk'       => isset($_POST['_delete_junk']),
        'logout_purge'      => isset($_POST['_logout_purge']),
        'logout_expunge'    => isset($_POST['_logout_expunge']),
    );

    break;

case 'folders':
    $a_user_prefs = array(
        'show_real_foldernames' => isset($_POST['_show_real_foldernames']),
        // stop using SPECIAL-USE (#4782)
        'lock_special_folders'  => !in_array('lock_special_folders', (array) $CONFIG['dont_override']),
    );

    foreach (rcube_storage::$folder_types as $type) {
        $a_user_prefs[$type . '_mbox'] = rcube_utils::get_input_value('_' . $type . '_mbox', rcube_utils::INPUT_POST, true);
    };

    break;
}

$plugin = rcmail::get_instance()->plugins->exec_hook('preferences_save',
    array('prefs' => $a_user_prefs, 'section' => $CURR_SECTION));

$a_user_prefs = $plugin['prefs'];

// don't override these parameters
foreach ((array)$CONFIG['dont_override'] as $p) {
    $a_user_prefs[$p] = $CONFIG[$p];
}


// verify some options
switch ($CURR_SECTION) {
case 'general':
    // switch UI language
    if (isset($_POST['_language']) && $a_user_prefs['language'] != $_SESSION['language']) {
        $RCMAIL->load_language($a_user_prefs['language']);
        $OUTPUT->command('reload', 500);
    }

    // switch skin (if valid, otherwise unset the pref and fall back to default)
    if (!$OUTPUT->set_skin($a_user_prefs['skin']))
        unset($a_user_prefs['skin']);
    else if ($RCMAIL->config->get('skin') != $a_user_prefs['skin'])
        $OUTPUT->command('reload', 500);

    $a_user_prefs['timezone'] = (string) $a_user_prefs['timezone'];

    if (!empty($a_user_prefs['refresh_interval']) && !empty($CONFIG['min_refresh_interval'])) {
        if ($a_user_prefs['refresh_interval'] < $CONFIG['min_refresh_interval']) {
            $a_user_prefs['refresh_interval'] = $CONFIG['min_refresh_interval'];
        }
    }

    break;

case 'mailbox':
    // force min size
    if ($a_user_prefs['mail_pagesize'] < 1) {
        $a_user_prefs['mail_pagesize'] = 10;
    }

    if (isset($CONFIG['max_pagesize']) && ($a_user_prefs['mail_pagesize'] > $CONFIG['max_pagesize'])) {
        $a_user_prefs['mail_pagesize'] = (int) $CONFIG['max_pagesize'];
    }

    break;

case 'addressbook':
    // force min size
    if ($a_user_prefs['addressbook_pagesize'] < 1) {
        $a_user_prefs['addressbook_pagesize'] = 10;
    }

    if (isset($CONFIG['max_pagesize']) && ($a_user_prefs['addressbook_pagesize'] > $CONFIG['max_pagesize'])) {
        $a_user_prefs['addressbook_pagesize'] = (int) $CONFIG['max_pagesize'];
    }

    break;

case 'folders':
    $storage  = $RCMAIL->get_storage();
    $specials = array();

    foreach (rcube_storage::$folder_types as $type) {
        $specials[$type] = $a_user_prefs[$type . '_mbox'];
    }

    $storage->set_special_folders($specials);

    break;
}

// Save preferences
if (!$plugin['abort'])
    $saved = $RCMAIL->user->save_prefs($a_user_prefs);
else
    $saved = $plugin['result'];

if ($saved)
    $OUTPUT->show_message('successfullysaved', 'confirmation');
else
    $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error');

// display the form again
$RCMAIL->overwrite_action('edit-prefs');
