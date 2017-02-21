<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/identities.inc                                 |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Manage identities of a user account                                 |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

if ($RCMAIL->action == 'delete-identity' && $OUTPUT->ajax_call) {
    $iid = rcube_utils::get_input_value('_iid', rcube_utils::INPUT_POST);

    if ($iid && preg_match('/^[0-9]+(,[0-9]+)*$/', $iid)) {
        $plugin = $RCMAIL->plugins->exec_hook('identity_delete', array('id' => $iid));

        $deleted = !$plugin['abort'] ? $RCMAIL->user->delete_identity($iid) : $plugin['result'];

        if ($deleted > 0 && $deleted !== false) {
            $OUTPUT->show_message('deletedsuccessfully', 'confirmation', null, false);
            $OUTPUT->command('remove_identity', $iid);
        }
        else {
            $msg = $plugin['message'] ?: ($deleted < 0 ? 'nodeletelastidentity' : 'errorsaving');
            $OUTPUT->show_message($msg, 'error', null, false);
        }
    }

    $OUTPUT->send();
}


define('IDENTITIES_LEVEL', intval($RCMAIL->config->get('identities_level', 0)));

$OUTPUT->set_pagetitle($RCMAIL->gettext('identities'));
$OUTPUT->include_script('list.js');

$OUTPUT->add_handler('identityframe', 'rcmail_identity_frame');
$OUTPUT->set_env('identities_level', IDENTITIES_LEVEL);
$OUTPUT->add_label('deleteidentityconfirm');

$OUTPUT->send('identities');


// similar function as /steps/addressbook/func.inc::rcmail_contact_frame()
function rcmail_identity_frame($attrib)
{
    global $OUTPUT;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmIdentityFrame';
    }

    return $OUTPUT->frame($attrib, true);
}
