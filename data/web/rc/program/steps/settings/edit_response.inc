<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/edit_response.inc                              |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2013, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Show edit form for a canned response record or to add a new one     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$responses = $RCMAIL->get_compose_responses();

// edit-response
if (($key = rcube_utils::get_input_value('_key', rcube_utils::INPUT_GPC))) {
    foreach ($responses as $i => $response) {
        if ($response['key'] == $key) {
            $RESPONSE_RECORD = $response;
            $RESPONSE_RECORD['index'] = $i;
            break;
        }
    }
}

// save response
if ($RCMAIL->action == 'save-response' && isset($_POST['_name']) && !$RESPONSE_RECORD['static']) {
    $name = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST));
    $text = trim(rcube_utils::get_input_value('_text', rcube_utils::INPUT_POST, true));

    if (!empty($name) && !empty($text)) {
        $dupes = 0;
        foreach ($responses as $i => $resp) {
            if ($RESPONSE_RECORD && $RESPONSE_RECORD['index'] === $i)
                continue;
            if (strcasecmp($name, preg_replace('/\s\(\d+\)$/', '', $resp['name'])) == 0)
                $dupes++;
        }
        if ($dupes) {  // require a unique name
            $name .= ' (' . ++$dupes . ')';
        }

        $response = array('name' => $name, 'text' => $text, 'format' => 'text', 'key' => substr(md5($name), 0, 16));
        if ($RESPONSE_RECORD && $responses[$RESPONSE_RECORD['index']]) {
            $responses[$RESPONSE_RECORD['index']] = $response;
        }
        else {
            $responses[] = $response;
        }

        $responses = array_filter($responses, function($item){ return empty($item['static']); });
        if ($RCMAIL->user->save_prefs(array('compose_responses' => array_values($responses)))) {
            $RCMAIL->output->show_message('successfullysaved', 'confirmation');
            $RCMAIL->output->command('parent.update_response_row', $response, $key);
            $RCMAIL->overwrite_action('edit-response');
            $RESPONSE_RECORD = $response;
        }
    }
    else {
        $RCMAIL->output->show_message('formincomplete', 'error');
    }
}

$OUTPUT->set_env('readonly', !empty($RESPONSE_RECORD['static']));
$OUTPUT->add_handler('responseform', 'rcube_response_form');
$OUTPUT->set_pagetitle($RCMAIL->gettext($RCMAIL->action == 'add-response' ? 'addresponse' : 'editresponse'));

$OUTPUT->send('responseedit');


function rcube_response_form($attrib)
{
    global $RCMAIL, $RESPONSE_RECORD;

    // Set form tags and hidden fields
    $disabled = !empty($RESPONSE_RECORD['static']);
    $key = $RESPONSE_RECORD['key'];
    list($form_start, $form_end) = get_form_tags($attrib, 'save-response', $key, array('name' => '_key', 'value' => $key));
    unset($attrib['form'], $attrib['id']);

    // return the complete edit form as table
    $out = "$form_start\n";

    $table = new html_table(array('cols' => 2));

    $table->add('title', html::label('ffname', rcube::Q($RCMAIL->gettext('responsename'))));
    $table->add(null, rcube_output::get_edit_field('name', $RESPONSE_RECORD['name'],
        array('id' => 'ffname', 'size' => $attrib['size'], 'disabled' => $disabled), 'text'));

    $table->add('title', html::label('fftext', rcube::Q($RCMAIL->gettext('responsetext'))));
    $table->add(null, rcube_output::get_edit_field('text', $RESPONSE_RECORD['text'],
        array('id' => 'fftext', 'size' => $attrib['textareacols'], 'rows' => $attrib['textarearows'], 'disabled' => $disabled), 'textarea'));

    $out .= $table->show($attrib);
    $out .= $form_end;

    return $out;
}
