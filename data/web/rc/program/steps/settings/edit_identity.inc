<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/edit_identity.inc                              |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Show edit form for a identity record or to add a new one            |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('IDENTITIES_LEVEL', intval($RCMAIL->config->get('identities_level', 0)));

// edit-identity
if (($_GET['_iid'] || $_POST['_iid']) && $RCMAIL->action=='edit-identity') {
    $id = rcube_utils::get_input_value('_iid', rcube_utils::INPUT_GPC);
    $IDENTITY_RECORD = $RCMAIL->user->get_identity($id);

    if (is_array($IDENTITY_RECORD)) {
        $OUTPUT->set_env('iid', $IDENTITY_RECORD['identity_id']);
    }
    else {
        $OUTPUT->show_message('dberror', 'error');
        // go to identities page
        $RCMAIL->overwrite_action('identities');
        return;
    }
}
// add-identity
else {
    if (IDENTITIES_LEVEL > 1) {
        $OUTPUT->show_message('opnotpermitted', 'error');
        // go to identities page
        $RCMAIL->overwrite_action('identities');
        return;
    }
    else if (IDENTITIES_LEVEL == 1) {
        $IDENTITY_RECORD['email'] = $RCMAIL->get_user_email();
    }
}

$OUTPUT->include_script('list.js');
$OUTPUT->add_handler('identityform', 'rcube_identity_form');
$OUTPUT->set_env('identities_level', IDENTITIES_LEVEL);
$OUTPUT->add_label('deleteidentityconfirm', 'uploading');

$OUTPUT->set_pagetitle($RCMAIL->gettext(($RCMAIL->action == 'add-identity' ? 'addidentity' : 'editidentity')));

if ($RCMAIL->action == 'add-identity' && $OUTPUT->template_exists('identityadd')) {
    $OUTPUT->send('identityadd');
}

$OUTPUT->send('identityedit');


function rcube_identity_form($attrib)
{
    global $IDENTITY_RECORD, $RCMAIL, $OUTPUT;

    // Add HTML editor script(s)
    $RCMAIL->html_editor('identity');

    // add some labels to client
    $OUTPUT->add_label('noemailwarning', 'converting', 'editorwarning');

    $i_size = $attrib['size'] ?: 40;
    $t_rows = $attrib['textarearows'] ?: 6;
    $t_cols = $attrib['textareacols'] ?: 40;

    // list of available cols
    $form = array(
        'addressing' => array(
            'name'    => $RCMAIL->gettext('settings'),
            'content' => array(
                'name'         => array('type' => 'text', 'size' => $i_size),
                'email'        => array('type' => 'text', 'size' => $i_size),
                'organization' => array('type' => 'text', 'size' => $i_size),
                'reply-to'     => array('type' => 'text', 'size' => $i_size),
                'bcc'          => array('type' => 'text', 'size' => $i_size),
                'standard'     => array('type' => 'checkbox', 'label' => $RCMAIL->gettext('setdefault')),
        )),
        'signature' => array(
            'name'    => $RCMAIL->gettext('signature'),
            'content' => array(
                'signature'      => array('type' => 'textarea', 'size' => $t_cols, 'rows' => $t_rows,
                    'spellcheck' => true),
                'html_signature' => array('type' => 'checkbox',
                    'label'   => $RCMAIL->gettext('htmlsignature'),
                    'onclick' => 'return rcmail.command(\'toggle-editor\', {id: \'rcmfd_signature\', html: this.checked}, \'\', event)'),
        ))
    );

    // Enable TinyMCE editor
    if ($IDENTITY_RECORD['html_signature']) {
        $form['signature']['content']['signature']['class']      = 'mce_editor';
        $form['signature']['content']['signature']['is_escaped'] = true;

        // Correctly handle HTML entities in HTML editor (#1488483)
        $IDENTITY_RECORD['signature'] = htmlspecialchars($IDENTITY_RECORD['signature'], ENT_NOQUOTES, RCUBE_CHARSET);
    }

    // hide "default" checkbox if only one identity is allowed
    if (IDENTITIES_LEVEL > 1) {
        unset($form['addressing']['content']['standard']);
    }

    // disable some field according to access level
    if (IDENTITIES_LEVEL == 1 || IDENTITIES_LEVEL == 3) {
        $form['addressing']['content']['email']['disabled'] = true;
        $form['addressing']['content']['email']['class']    = 'disabled';
    }

    if (IDENTITIES_LEVEL == 4) {
        foreach($form['addressing']['content'] as $formfield => $value){
            $form['addressing']['content'][$formfield]['disabled'] = true;
            $form['addressing']['content'][$formfield]['class']    = 'disabled';
        }
    }

    $IDENTITY_RECORD['email'] = rcube_utils::idn_to_utf8($IDENTITY_RECORD['email']);

    // Allow plugins to modify identity form content
    $plugin = $RCMAIL->plugins->exec_hook('identity_form', array(
        'form' => $form, 'record' => $IDENTITY_RECORD));

    $form = $plugin['form'];
    $IDENTITY_RECORD = $plugin['record'];

    // Set form tags and hidden fields
    list($form_start, $form_end) = get_form_tags($attrib, 'save-identity',
        intval($IDENTITY_RECORD['identity_id']),
        array('name' => '_iid', 'value' => $IDENTITY_RECORD['identity_id']));

    unset($plugin);
    unset($attrib['form'], $attrib['id']);

    // return the complete edit form as table
    $out = "$form_start\n";

    foreach ($form as $fieldset) {
        if (empty($fieldset['content'])) {
            continue;
        }

        $content = '';
        if (is_array($fieldset['content'])) {
            $table = new html_table(array('cols' => 2));

            foreach ($fieldset['content'] as $col => $colprop) {
                $colprop['id'] = 'rcmfd_'.$col;

                $label = $colprop['label'] ?: $RCMAIL->gettext(str_replace('-', '', $col));
                $value = $colprop['value'] ?: rcube_output::get_edit_field($col, $IDENTITY_RECORD[$col], $colprop, $colprop['type']);

                $table->add('title', html::label($colprop['id'], rcube::Q($label)));
                $table->add(null, $value);
            }

            $content = $table->show($attrib);
        }
        else {
            $content = $fieldset['content'];
        }

        $content = html::tag('legend', null, rcube::Q($fieldset['name'])) . $content;
        $out .= html::tag('fieldset', null, $content) . "\n";
    }

    $out .= $form_end;

    // add image upload form
    $max_filesize   = $RCMAIL->upload_init($RCMAIL->config->get('identity_image_size', 64) * 1024);
    $upload_form_id = 'identityImageUpload';

    $out .= '<form id="' . $upload_form_id . '" style="display: none">'
        . html::div('hint', $RCMAIL->gettext(array('name' => 'maxuploadsize', 'vars' => array('size' => $max_filesize))))
        . '</form>';

    $RCMAIL->output->add_gui_object('uploadform', $upload_form_id);

    return $out;
}
