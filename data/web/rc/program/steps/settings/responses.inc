<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/responses.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2013, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Manage and save canned response texts                               |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/


if (!empty($_POST['_insert'])) {
    $name = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST));
    $text = trim(rcube_utils::get_input_value('_text', rcube_utils::INPUT_POST, true));

    if (!empty($name) && !empty($text)) {
        $dupes = 0;
        $responses = $RCMAIL->get_compose_responses(false, true);
        foreach ($responses as $resp) {
            if (strcasecmp($name, preg_replace('/\s\(\d+\)$/', '', $resp['name'])) == 0)
                $dupes++;
        }
        if ($dupes) {  // require a unique name
            $name .= ' (' . ++$dupes . ')';
        }

        $response = array('name' => $name, 'text' => $text, 'format' => 'text', 'key' => substr(md5($name), 0, 16));
        $responses[] = $response;

        if ($RCMAIL->user->save_prefs(array('compose_responses' => $responses))) {
            $RCMAIL->output->command('add_response_item', $response);
            $RCMAIL->output->command('display_message', $RCMAIL->gettext('successfullysaved'), 'confirmation');
        }
        else {
            $RCMAIL->output->command('display_message', $RCMAIL->gettext('errorsaving'), 'error');
        }
    }

    // send response
    $RCMAIL->output->send();
}

if ($RCMAIL->action == 'delete-response' && $RCMAIL->output->ajax_call) {
    if ($key = rcube_utils::get_input_value('_key', rcube_utils::INPUT_POST)) {
        $responses = $RCMAIL->get_compose_responses(false, true);
        foreach ($responses as $i => $response) {
            if (empty($response['key']))
                $response['key'] = substr(md5($response['name']), 0, 16);
            if ($response['key'] == $key) {
                unset($responses[$i]);
                $deleted = $RCMAIL->user->save_prefs(array('compose_responses' => $responses));
                break;
            }
        }
    }

    if ($deleted) {
        $RCMAIL->output->command('display_message', $RCMAIL->gettext('deletedsuccessfully'), 'confirmation');
        $RCMAIL->output->command('remove_response', $key);
    }

    $RCMAIL->output->send();
}


$OUTPUT->set_pagetitle($RCMAIL->gettext('responses'));
$OUTPUT->include_script('list.js');

$OUTPUT->add_handlers(array(
    'responseframe' => 'rcmail_response_frame',
    'responseslist' => 'rcmail_responses_list',
));
$OUTPUT->add_label('deleteresponseconfirm');

$OUTPUT->send('responses');


/**
 *
 */
function rcmail_responses_list($attrib)
{
    global $RCMAIL, $OUTPUT;

    $attrib += array('id' => 'rcmresponseslist', 'tagname' => 'table');

    $plugin = $RCMAIL->plugins->exec_hook('responses_list', array(
        'list' => $RCMAIL->get_compose_responses(true),
        'cols' => array('name')
    ));

    $out = $RCMAIL->table_output($attrib, $plugin['list'], $plugin['cols'], 'key');

    $readonly_responses = array();
    foreach ($plugin['list'] as $item) {
        if (!empty($item['static'])) {
            $readonly_responses[] = $item['key'];
        }
    }

    // set client env
    $OUTPUT->add_gui_object('responseslist', $attrib['id']);
    $OUTPUT->set_env('readonly_responses', $readonly_responses);

    return $out;
}

// similar function as /steps/addressbook/func.inc::rcmail_contact_frame()
function rcmail_response_frame($attrib)
{
    global $OUTPUT;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmResponseFrame';
    }

    $OUTPUT->set_env('contentframe', $attrib['id']);

    return $OUTPUT->frame($attrib, true);
}
