<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/edit_folder.inc                                |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide functionality to create/edit a folder                       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// register UI objects
$OUTPUT->add_handlers(array(
    'folderdetails' => 'rcmail_folder_form',
));

$OUTPUT->add_label('nonamewarning');

$OUTPUT->send('folderedit');


// WARNING: folder names in UI are encoded with RCUBE_CHARSET

function rcmail_folder_form($attrib)
{
    global $RCMAIL;

    $storage = $RCMAIL->get_storage();

    // edited folder name (empty in create-folder mode)
    $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC, true);

    // predefined path for new folder
    $parent = rcube_utils::get_input_value('_path', rcube_utils::INPUT_GPC, true);

    $threading_supported = $storage->get_capability('THREAD');
    $delimiter = $storage->get_hierarchy_delimiter();

    // Get mailbox parameters
    if (strlen($mbox)) {
        $options   = rcmail_folder_options($mbox);
        $namespace = $storage->get_namespace();

        $path   = explode($delimiter, $mbox);
        $folder = array_pop($path);
        $path   = implode($delimiter, $path);
        $folder = rcube_charset::convert($folder, 'UTF7-IMAP');

        $hidden_fields = array('name' => '_mbox', 'value' => $mbox);
    }
    else {
        $options = array();
        $path    = $parent;

        // allow creating subfolders of INBOX folder
        if ($path == 'INBOX') {
            $path = $storage->mod_folder($path, 'in');
        }
    }

    // remove personal namespace prefix
    if (strlen($path)) {
        $path_id = $path;
        $path    = $storage->mod_folder($path.$delimiter);
        if ($path[strlen($path)-1] == $delimiter) {
            $path = substr($path, 0, -1);
        }
    }

    $form = array();

    // General tab
    $form['props'] = array(
        'name' => $RCMAIL->gettext('properties'),
    );

    // Location (name)
    if ($options['protected']) {
        $foldername = str_replace($delimiter, ' &raquo; ', rcube::Q($RCMAIL->localize_folderpath($mbox)));
    }
    else if ($options['norename']) {
        $foldername = rcube::Q($folder);
    }
    else {
        if (isset($_POST['_name']))
            $folder = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true));

        $foldername = new html_inputfield(array('name' => '_name', 'id' => '_name', 'size' => 30));
        $foldername = $foldername->show($folder);

        if ($options['special']) {
            $foldername .= '&nbsp;(' . rcube::Q($RCMAIL->localize_foldername($mbox)) .')';
        }
    }

    $form['props']['fieldsets']['location'] = array(
        'name'  => $RCMAIL->gettext('location'),
        'content' => array(
            'name' => array(
                'label' => $RCMAIL->gettext('foldername'),
                'value' => $foldername,
            ),
        ),
    );

    if (!empty($options) && ($options['norename'] || $options['protected'])) {
        // prevent user from moving folder
        $hidden_path = new html_hiddenfield(array('name' => '_parent', 'value' => $path));
        $form['props']['fieldsets']['location']['content']['name']['value'] .= $hidden_path->show();
    }
    else {
        $selected = isset($_POST['_parent']) ? $_POST['_parent'] : $path_id;
        $exceptions = array($mbox);

        // Exclude 'prefix' namespace from parent folders list (#1488349)
        // If INBOX. namespace exists, folders created as INBOX subfolders
        // will be listed at the same level - selecting INBOX as a parent does nothing
        if ($prefix = $storage->get_namespace('prefix')) {
            $exceptions[] = substr($prefix, 0, -1);
        }

        $select = $RCMAIL->folder_selector(array(
            'id'          => '_parent',
            'name'        => '_parent',
            'noselection' => '---',
            'maxlength'   => 150,
            'unsubscribed' => true,
            'skip_noinferiors' => true,
            'exceptions'  => $exceptions,
            'additional'  => strlen($selected) ? array($selected) : null,
        ));

        $form['props']['fieldsets']['location']['content']['path'] = array(
            'label' => $RCMAIL->gettext('parentfolder'),
            'value' => $select->show($selected),
        );
    }

    // Settings
    $form['props']['fieldsets']['settings'] = array(
        'name'  => $RCMAIL->gettext('settings'),
    );

    // Settings: threading
    if ($threading_supported && ($mbox == 'INBOX' || (!$options['noselect'] && !$options['is_root']))) {
        $select = new html_select(array('name' => '_viewmode', 'id' => '_viewmode'));
        $select->add($RCMAIL->gettext('list'), 0);
        $select->add($RCMAIL->gettext('threads'), 1);

        if (isset($_POST['_viewmode'])) {
            $value = (int) $_POST['_viewmode'];
        }
        else if (strlen($mbox)) {
            $a_threaded   = $RCMAIL->config->get('message_threading', array());
            $default_mode = $RCMAIL->config->get('default_list_mode', 'list');

            $value = (int) (isset($a_threaded[$mbox]) ? $a_threaded[$mbox] : $default_mode == 'threads');
        }

        $form['props']['fieldsets']['settings']['content']['viewmode'] = array(
            'label' => $RCMAIL->gettext('listmode'),
            'value' => $select->show($value),
        );
    }
/*
    // Settings: sorting column
    $select = new html_select(array('name' => '_sortcol', 'id' => '_sortcol'));
    $select->add($RCMAIL->gettext('nonesort'), '');
    $select->add($RCMAIL->gettext('arrival'), 'arrival');
    $select->add($RCMAIL->gettext('sentdate'), 'date');
    $select->add($RCMAIL->gettext('subject'), 'subject');
    $select->add($RCMAIL->gettext('fromto'), 'from');
    $select->add($RCMAIL->gettext('replyto'), 'replyto');
    $select->add($RCMAIL->gettext('cc'), 'cc');
    $select->add($RCMAIL->gettext('size'), 'size');

    $value = isset($_POST['_sortcol']) ? $_POST['_sortcol'] : '';

    $form['props']['fieldsets']['settings']['content']['sortcol'] = array(
        'label' => $RCMAIL->gettext('listsorting'),
        'value' => $select->show($value),
    );

    // Settings: sorting order
    $select = new html_select(array('name' => '_sortord', 'id' => '_sortord'));
    $select->add($RCMAIL->gettext('asc'), 'ASC');
    $select->add($RCMAIL->gettext('desc'), 'DESC');

    $value = isset($_POST['_sortord']) ? $_POST['_sortord'] : '';

    $form['props']['fieldsets']['settings']['content']['sortord'] = array(
        'label' => $RCMAIL->gettext('listorder'),
        'value' => $select->show(),
    );
*/
    // Information (count, size) - Edit mode
    if (strlen($mbox)) {
        // Number of messages
        $form['props']['fieldsets']['info'] = array(
            'name'  => $RCMAIL->gettext('info'),
            'content' => array()
        );

        if ((!$options['noselect'] && !$options['is_root']) || $mbox == 'INBOX') {
            $msgcount = $storage->count($mbox, 'ALL', true, false);

            // Size
            if ($msgcount) {
                // create link with folder-size command
                $onclick = sprintf("return %s.command('folder-size', '%s', this)",
                    rcmail_output::JS_OBJECT_NAME, rcube::JQ($mbox));
                $size = html::a(array('href' => '#', 'onclick' => $onclick,
                    'id' => 'folder-size'), $RCMAIL->gettext('getfoldersize'));
            }
            else {
                // no messages -> zero size
                $size = 0;
            }

            $form['props']['fieldsets']['info']['content']['count'] = array(
                'label' => $RCMAIL->gettext('messagecount'),
                'value' => (int) $msgcount
            );
            $form['props']['fieldsets']['info']['content']['size'] = array(
                'label' => $RCMAIL->gettext('size'),
                'value' => $size,
            );
        }

        // show folder type only if we have non-private namespaces
        if (!empty($namespace['shared']) || !empty($namespace['others'])) {
            $form['props']['fieldsets']['info']['content']['foldertype'] = array(
                'label' => $RCMAIL->gettext('foldertype'),
                'value' => $RCMAIL->gettext($options['namespace'] . 'folder'));
        }
    }

    // Allow plugins to modify folder form content
    $plugin = $RCMAIL->plugins->exec_hook('folder_form',
        array('form' => $form, 'options' => $options,
            'name' => $mbox, 'parent_name' => $parent));

    $form = $plugin['form'];

    // Set form tags and hidden fields
    list($form_start, $form_end) = get_form_tags($attrib, 'save-folder', null, $hidden_fields);

    unset($attrib['form'], $attrib['id']);

    // return the complete edit form as table
    $out = "$form_start\n";

    // Create form output
    foreach ($form as $idx => $tab) {
        if (!empty($tab['fieldsets']) && is_array($tab['fieldsets'])) {
            $content = '';
            foreach ($tab['fieldsets'] as $fieldset) {
                $subcontent = rcmail_get_form_part($fieldset, $attrib);
                if ($subcontent) {
                    $subcontent = html::tag('legend', null, rcube::Q($fieldset['name'])) . $subcontent;
                    $content .= html::tag('fieldset', null, $subcontent) ."\n";
                }
            }
        }
        else {
            $content = rcmail_get_form_part($tab, $attrib);
        }

        if ($idx != 'props') {
            $out .= html::tag('fieldset', null, html::tag('legend', null, rcube::Q($tab['name'])) . $content) ."\n";
        }
        else {
            $out .= $content ."\n";
        }
    }

    $out .= "\n$form_end";

    $RCMAIL->output->set_env('messagecount', (int) $msgcount);
    $RCMAIL->output->set_env('folder', $mbox);

    if ($mbox !== null && empty($_POST)) {
        $RCMAIL->output->command('parent.set_quota', $RCMAIL->quota_content(null, $mbox));
    }

    return $out;
}

function rcmail_get_form_part($form, $attrib = array())
{
    global $RCMAIL;

    $content = '';

    if (is_array($form['content']) && !empty($form['content'])) {
        $table = new html_table(array('cols' => 2));
        foreach ($form['content'] as $col => $colprop) {
            $colprop['id'] = '_'.$col;
            $label = $colprop['label'] ?: $RCMAIL->gettext($col);

            $table->add('title', html::label($colprop['id'], rcube::Q($label)));
            $table->add(null, $colprop['value']);
        }
        $content = $table->show($attrib);
    }
    else {
        $content = $form['content'];
    }

    return $content;
}
