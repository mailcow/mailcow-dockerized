<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/func.inc                                       |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide functionality for user's settings & preferences             |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

if (!$OUTPUT->ajax_call) {
    $OUTPUT->set_pagetitle($RCMAIL->gettext('preferences'));
}

// register UI objects
$OUTPUT->add_handlers(array(
    'settingstabs'   => 'rcmail_settings_tabs',
    'prefsframe'     => 'rcmail_preferences_frame',
    'sectionslist'   => 'rcmail_sections_list',
    'identitieslist' => 'rcmail_identities_list',
));

// register action aliases
$RCMAIL->register_action_map(array(
    'folders'       => 'folders.inc',
    'rename-folder' => 'folders.inc',
    'delete-folder' => 'folders.inc',
    'subscribe'     => 'folders.inc',
    'unsubscribe'   => 'folders.inc',
    'purge'         => 'folders.inc',
    'folder-size'   => 'folders.inc',
    'add-identity'  => 'edit_identity.inc',
    'add-response'  => 'edit_response.inc',
    'save-response' => 'edit_response.inc',
    'delete-response' => 'responses.inc',
    'delete-identity' => 'identities.inc',
    'upload-display'  => 'upload.inc',
));


// similar function as /steps/settings/identities.inc::rcmail_identity_frame()
function rcmail_preferences_frame($attrib)
{
    global $OUTPUT;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmprefsframe';
    }

    return $OUTPUT->frame($attrib, true);
}


function rcmail_sections_list($attrib)
{
    global $RCMAIL;

    // add id to message list table if not specified
    if (!strlen($attrib['id'])) {
        $attrib['id'] = 'rcmsectionslist';
    }

    list($list, $cols) = rcmail_user_prefs();

    // create XHTML table
    $out = $RCMAIL->table_output($attrib, $list, $cols, 'id');

    // set client env
    $RCMAIL->output->add_gui_object('sectionslist', $attrib['id']);
    $RCMAIL->output->include_script('list.js');

    return $out;
}


function rcmail_identities_list($attrib)
{
    global $OUTPUT, $RCMAIL;

    // add id to message list table if not specified
    if (!strlen($attrib['id'])) {
        $attrib['id'] = 'rcmIdentitiesList';
    }

    // get identities list and define 'mail' column
    $list = $RCMAIL->user->list_emails();
    foreach ($list as $idx => $row) {
        $list[$idx]['mail'] = trim($row['name'] . ' <' . rcube_utils::idn_to_utf8($row['email']) . '>');
    }

    // get all identites from DB and define list of cols to be displayed
    $plugin = $RCMAIL->plugins->exec_hook('identities_list', array(
        'list' => $list,
        'cols' => array('mail')
    ));

    // @TODO: use <UL> instead of <TABLE> for identities list
    // create XHTML table
    $out = $RCMAIL->table_output($attrib, $plugin['list'], $plugin['cols'], 'identity_id');

    // set client env
    $OUTPUT->add_gui_object('identitieslist', $attrib['id']);

    return $out;
}


// similar function as in /steps/addressbook/edit.inc
function get_form_tags($attrib, $action, $id = null, $hidden = null)
{
    global $EDIT_FORM, $RCMAIL;

    $form_start = $form_end = '';

    if (empty($EDIT_FORM)) {
        $request_key = $action . (isset($id) ? '.'.$id : '');
        $form_start = $RCMAIL->output->request_form(array(
            'name'    => 'form',
            'method'  => 'post',
            'task'    => $RCMAIL->task,
            'action'  => $action,
            'request' => $request_key,
            'noclose' => true
        ) + $attrib);

        if (is_array($hidden)) {
            $hiddenfields = new html_hiddenfield($hidden);
            $form_start .= $hiddenfields->show();
        }

        $form_end = !strlen($attrib['form']) ? '</form>' : '';

        $EDIT_FORM = !empty($attrib['form']) ? $attrib['form'] : 'form';
        $RCMAIL->output->add_gui_object('editform', $EDIT_FORM);
    }

    return array($form_start, $form_end);
}


function rcmail_user_prefs($current = null)
{
    global $RCMAIL;

    $sections['general']     = array('id' => 'general', 'section' => $RCMAIL->gettext('uisettings'));
    $sections['mailbox']     = array('id' => 'mailbox', 'section' => $RCMAIL->gettext('mailboxview'));
    $sections['mailview']    = array('id' => 'mailview','section' => $RCMAIL->gettext('messagesdisplaying'));
    $sections['compose']     = array('id' => 'compose', 'section' => $RCMAIL->gettext('messagescomposition'));
    $sections['addressbook'] = array('id' => 'addressbook','section' => $RCMAIL->gettext('contacts'));
    $sections['folders']     = array('id' => 'folders', 'section' => $RCMAIL->gettext('specialfolders'));
    $sections['server']      = array('id' => 'server',  'section' => $RCMAIL->gettext('serversettings'));

    // hook + define list cols
    $plugin = $RCMAIL->plugins->exec_hook('preferences_sections_list',
        array('list' => $sections, 'cols' => array('section')));

    $sections    = $plugin['list'];
    $config      = $RCMAIL->config->all();
    $no_override = array_flip((array)$RCMAIL->config->get('dont_override'));

    foreach ($sections as $idx => $sect) {
        if ($current && $sect['id'] != $current) {
            continue;
        }

        $blocks = array();

        switch ($sect['id']) {

        // general
        case 'general':
            $blocks = array(
                'main'    => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'skin'    => array('name' => rcube::Q($RCMAIL->gettext('skin'))),
                'browser' => array('name' => rcube::Q($RCMAIL->gettext('browseroptions'))),
                'advanced'=> array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            // language selection
            if (!isset($no_override['language'])) {
                if (!$current) {
                    continue 2;
                }

                $a_lang = $RCMAIL->list_languages();
                asort($a_lang);

                $field_id = 'rcmfd_lang';
                $select   = new html_select(array('name' => '_language', 'id' => $field_id));
                $select->add(array_values($a_lang), array_keys($a_lang));

                $blocks['main']['options']['language'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('language'))),
                    'content' => $select->show($RCMAIL->user->language),
                );
            }

            // timezone selection
            if (!isset($no_override['timezone'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_timezone';
                $select   = new html_select(array('name' => '_timezone', 'id' => $field_id));
                $select->add($RCMAIL->gettext('autodetect'), 'auto');

                $zones = array();
                foreach (DateTimeZone::listIdentifiers() as $i => $tzs) {
                    try {
                        $tz      = new DateTimeZone($tzs);
                        $date    = new DateTime(date('Y') . '-12-21', $tz);
                        $offset  = $date->format('Z') + 45000;
                        $sortkey = sprintf('%06d.%s', $offset, $tzs);
                        $zones[$sortkey] = array($tzs, $date->format('P'));
                    }
                    catch (Exception $e) {}
                }

                ksort($zones);

                foreach ($zones as $zone) {
                    list($tzs, $offset) = $zone;
                    $select->add('(GMT ' . $offset . ') ' . strtr($tzs, '_', ' '), $tzs);
                }

                $blocks['main']['options']['timezone'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('timezone'))),
                    'content' => $select->show((string)$config['timezone']),
                );
            }

            // date/time formatting
            if (!isset($no_override['time_format'])) {
                if (!$current) {
                    continue 2;
                }

                $reftime  = mktime(7,30,0);
                $defaults = array('G:i', 'H:i', 'g:i a', 'h:i A');
                $formats  = (array)$RCMAIL->config->get('time_formats', $defaults);
                $field_id = 'rcmfd_time_format';
                $select   = new html_select(array('name' => '_time_format', 'id' => $field_id));

                foreach ($formats as $choice) {
                    $select->add(date($choice, $reftime), $choice);
                }

                $blocks['main']['options']['time_format'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('timeformat'))),
                    'content' => $select->show($RCMAIL->config->get('time_format')),
                );
            }

            if (!isset($no_override['date_format'])) {
                if (!$current) {
                    continue 2;
                }

                $refdate  = mktime(12,30,0,7,24);
                $defaults = array('Y-m-d','d-m-Y','Y/m/d','m/d/Y','d/m/Y','d.m.Y','j.n.Y');
                $formats  = (array)$RCMAIL->config->get('date_formats', $defaults);
                $field_id = 'rcmfd_date_format';
                $select   = new html_select(array('name' => '_date_format', 'id' => $field_id));

                foreach ($formats as $choice) {
                    $select->add(date($choice, $refdate), $choice);
                }

                $blocks['main']['options']['date_format'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('dateformat'))),
                    'content' => $select->show($config['date_format']),
                );
            }

            // Show checkbox for toggling 'pretty dates'
            if (!isset($no_override['prettydate'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_prettydate';
                $input    = new html_checkbox(array('name' => '_pretty_date', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['prettydate'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('prettydate'))),
                    'content' => $input->show($config['prettydate']?1:0),
                );
            }

            if (!isset($no_override['refresh_interval'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_refresh_interval';
                $select   = new html_select(array('name' => '_refresh_interval', 'id' => $field_id));

                $select->add($RCMAIL->gettext('never'), 0);
                foreach (array(1, 3, 5, 10, 15, 30, 60) as $min) {
                    if (!$config['min_refresh_interval'] || $config['min_refresh_interval'] <= $min * 60) {
                        $label = $RCMAIL->gettext(array('name' => 'everynminutes', 'vars' => array('n' => $min)));
                        $select->add($label, $min);
                    }
                }

                $blocks['main']['options']['refresh_interval'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('refreshinterval'))),
                    'content' => $select->show($config['refresh_interval']/60),
                );
            }

            // show drop-down for available skins
            if (!isset($no_override['skin'])) {
                if (!$current) {
                    continue 2;
                }

                $skins = rcmail_get_skins();

                if (count($skins) > 1) {
                    $field_id = 'rcmfd_skin';
                    $input    = new html_radiobutton(array('name'=>'_skin'));

                    foreach ($skins as $skin) {
                        $thumbnail   = "skins/$skin/thumbnail.png";
                        $skinname    = ucfirst($skin);
                        $author_link = $license_link = '';
                        $meta        = @json_decode(@file_get_contents(INSTALL_PATH . "skins/$skin/meta.json"), true);

                        if (is_array($meta) && $meta['name']) {
                            $skinname     = $meta['name'];
                            $author_link  = $meta['url'] ? html::a(array('href' => $meta['url'], 'target' => '_blank'), rcube::Q($meta['author'])) : rcube::Q($meta['author']);
                            $license_link = $meta['license-url'] ? html::a(array('href' => $meta['license-url'], 'target' => '_blank', 'tabindex' => '-1'), rcube::Q($meta['license'])) : rcube::Q($meta['license']);
                        }

                        $img = html::img(array(
                                'src'     => $thumbnail,
                                'class'   => 'skinthumbnail',
                                'alt'     => $skin,
                                'width'   => 64,
                                'height'  => 64,
                                'onerror' => "this.src = rcmail.assets_path('program/resources/blank.gif')",
                        ));

                        $skinnames[] = mb_strtolower($skinname);
                        $blocks['skin']['options'][$skin]['content'] = html::label(array('class' => 'skinselection'),
                            html::span('skinitem', $input->show($config['skin'], array('value' => $skin, 'id' => $field_id.$skin))) .
                            html::span('skinitem', $img) .
                            html::span('skinitem', html::span('skinname', rcube::Q($skinname)) . html::br() .
                                html::span('skinauthor', $author_link ? 'by ' . $author_link : '') . html::br() .
                                html::span('skinlicense', $license_link ? $RCMAIL->gettext('license').':&nbsp;' . $license_link : ''))
                        );
                    }
                    array_multisort($blocks['skin']['options'], SORT_ASC, SORT_STRING, $skinnames);
                }
            }

            // standard_windows option decides if new windows should be
            // opened as popups or standard windows (which can be handled by browsers as tabs)
            if (!isset($no_override['standard_windows'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_standard_windows';
                $checkbox = new html_checkbox(array('name' => '_standard_windows', 'id' => $field_id, 'value' => 1));

                $blocks['browser']['options']['standard_windows'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('standardwindows'))),
                    'content' => $checkbox->show($config['standard_windows']?1:0),
                );
            }

            if ($current) {
                $product_name = $RCMAIL->config->get('product_name', 'Roundcube Webmail');
                $RCMAIL->output->add_script(sprintf("%s.check_protocol_handler('%s', '#mailtoprotohandler');",
                    rcmail_output::JS_OBJECT_NAME, rcube::JQ($product_name)), 'docready');
            }

            $blocks['browser']['options']['mailtoprotohandler'] = array(
                'content' => html::a(array(
                    'href'    => '#',
                    'id'      => 'mailtoprotohandler'
                ),
                rcube::Q($RCMAIL->gettext('mailtoprotohandler'))) .
                html::span('mailtoprotohandler-status', ''),
            );

        break;

        // Mailbox view (mail screen)
        case 'mailbox':
            $blocks = array(
                'main'        => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'new_message' => array('name' => rcube::Q($RCMAIL->gettext('newmessage'))),
                'advanced'    => array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            // show config parameter for auto marking the previewed message as read
            if (!isset($no_override['mail_read_time'])) {
                if (!$current) {
                    continue 2;
                }

                // apply default if config option is not set at all
                $config['mail_read_time'] = intval($RCMAIL->config->get('mail_read_time'));

                $field_id = 'rcmfd_mail_read_time';
                $select   = new html_select(array('name' => '_mail_read_time', 'id' => $field_id));

                $select->add($RCMAIL->gettext('never'), -1);
                $select->add($RCMAIL->gettext('immediately'), 0);

                foreach (array(5, 10, 20, 30) as $sec) {
                    $label = $RCMAIL->gettext(array('name' => 'afternseconds', 'vars' => array('n' => $sec)));
                    $select->add($label, $sec);
                }

                $blocks['main']['options']['mail_read_time'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('previewpanemarkread'))),
                    'content' => $select->show($config['mail_read_time']),
                );
            }

            if (!isset($no_override['mdn_requests'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_mdn_requests';
                $select   = new html_select(array('name' => '_mdn_requests', 'id' => $field_id));
                $select->add($RCMAIL->gettext('askuser'), 0);
                $select->add($RCMAIL->gettext('autosend'), 1);
                $select->add($RCMAIL->gettext('autosendknown'), 3);
                $select->add($RCMAIL->gettext('autosendknownignore'), 4);
                $select->add($RCMAIL->gettext('ignorerequest'), 2);

                $blocks['main']['options']['mdn_requests'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('mdnrequests'))),
                    'content' => $select->show($config['mdn_requests']),
                );
            }

            if (!isset($no_override['autoexpand_threads'])) {
                if (!$current) {
                    continue 2;
                }

                $storage   = $RCMAIL->get_storage();
                $supported = $storage->get_capability('THREAD');

                if ($supported) {
                    $field_id = 'rcmfd_autoexpand_threads';
                    $select   = new html_select(array('name' => '_autoexpand_threads', 'id' => $field_id));
                    $select->add($RCMAIL->gettext('never'), 0);
                    $select->add($RCMAIL->gettext('do_expand'), 1);
                    $select->add($RCMAIL->gettext('expand_only_unread'), 2);

                    $blocks['main']['options']['autoexpand_threads'] = array(
                        'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('autoexpand_threads'))),
                        'content' => $select->show($config['autoexpand_threads']),
                    );
                }
            }

            // show page size selection
            if (!isset($no_override['mail_pagesize'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_mail_pagesize';
                $input    = new html_inputfield(array('name' => '_mail_pagesize', 'id' => $field_id, 'size' => 5));
                $size     = intval($config['mail_pagesize'] ?: $config['pagesize']);

                $blocks['main']['options']['pagesize'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('pagesize'))),
                    'content' => $input->show($size ?: 50),
                );
            }

            if (!isset($no_override['check_all_folders'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_check_all_folders';
                $input    = new html_checkbox(array('name' => '_check_all_folders', 'id' => $field_id, 'value' => 1));

                $blocks['new_message']['options']['check_all_folders'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('checkallfolders'))),
                    'content' => $input->show($config['check_all_folders']?1:0),
                );
            }
        break;

        // Message viewing
        case 'mailview':
            $blocks = array(
                'main' => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'advanced'   => array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            // show checkbox to open message view in new window
            if (!isset($no_override['message_extwin'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_message_extwin';
                $input    = new html_checkbox(array('name' => '_message_extwin', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['message_extwin'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('showinextwin'))),
                    'content' => $input->show($config['message_extwin']?1:0),
                );
            }

            // show checkbox to show email instead of name
            if (!isset($no_override['message_show_email'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_message_show_email';
                $input    = new html_checkbox(array('name' => '_message_show_email', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['message_show_email'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('showemail'))),
                    'content' => $input->show($config['message_show_email']?1:0),
                );
            }

            // show checkbox for HTML/plaintext messages
            if (!isset($no_override['prefer_html'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_htmlmsg';
                $input    = new html_checkbox(array('name' => '_prefer_html', 'id' => $field_id, 'value' => 1,
                    'onchange' => "$('#rcmfd_show_images').prop('disabled', !this.checked).val(0)"));

                $blocks['main']['options']['prefer_html'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('preferhtml'))),
                    'content' => $input->show($config['prefer_html']?1:0),
                );
            }

            if (!isset($no_override['default_charset'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_default_charset';

                $blocks['advanced']['options']['default_charset'] = array(
                    'title' => html::label($field_id, rcube::Q($RCMAIL->gettext('defaultcharset'))),
                    'content' => $RCMAIL->output->charset_selector(array(
                        'id' => $field_id, 'name' => '_default_charset', 'selected' => $config['default_charset']
                )));
            }

            if (!isset($no_override['show_images'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_show_images';
                $input    = new html_select(array('name' => '_show_images', 'id' => $field_id,
                    'disabled' => !$config['prefer_html']));

                $input->add($RCMAIL->gettext('never'), 0);
                $input->add($RCMAIL->gettext('fromknownsenders'), 1);
                $input->add($RCMAIL->gettext('always'), 2);

                $blocks['main']['options']['show_images'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('showremoteimages'))),
                    'content' => $input->show($config['prefer_html'] ? $config['show_images'] : 0),
                );
            }

            if (!isset($no_override['inline_images'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_inline_images';
                $input    = new html_checkbox(array('name' => '_inline_images', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['inline_images'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('showinlineimages'))),
                    'content' => $input->show($config['inline_images']?1:0),
                );
            }

            // "display after delete" checkbox
            if (!isset($no_override['display_next'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_displaynext';
                $input    = new html_checkbox(array('name' => '_display_next', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['display_next'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('displaynext'))),
                    'content' => $input->show($config['display_next']?1:0),
                );
            }
        break;

        // Mail composition
        case 'compose':
            $blocks = array(
                'main'       => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'sig'        => array('name' => rcube::Q($RCMAIL->gettext('signatureoptions'))),
                'spellcheck' => array('name' => rcube::Q($RCMAIL->gettext('spellcheckoptions'))),
                'advanced'   => array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            // show checkbox to compose messages in a new window
            if (!isset($no_override['compose_extwin'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfdcompose_extwin';
                $input    = new html_checkbox(array('name' => '_compose_extwin', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['compose_extwin'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('composeextwin'))),
                    'content' => $input->show($config['compose_extwin']?1:0),
                );
            }

            if (!isset($no_override['htmleditor'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_htmleditor';
                $select   = new html_select(array('name' => '_htmleditor', 'id' => $field_id));

                $select->add($RCMAIL->gettext('never'), 0);
                $select->add($RCMAIL->gettext('htmlonreply'), 2);
                $select->add($RCMAIL->gettext('htmlonreplyandforward'), 3);
                $select->add($RCMAIL->gettext('always'), 1);
                $select->add($RCMAIL->gettext('alwaysbutplain'), 4);

                $blocks['main']['options']['htmleditor'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('htmleditor'))),
                    'content' => $select->show(intval($config['htmleditor'])),
                );
            }

            if (!isset($no_override['draft_autosave'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_autosave';
                $select   = new html_select(array('name' => '_draft_autosave', 'id' => $field_id, 'disabled' => empty($config['drafts_mbox'])));

                $select->add($RCMAIL->gettext('never'), 0);
                foreach (array(1, 3, 5, 10) as $i => $min) {
                    $label = $RCMAIL->gettext(array('name' => 'everynminutes', 'vars' => array('n' => $min)));
                    $select->add($label, $min*60);
                }

                $blocks['main']['options']['draft_autosave'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('autosavedraft'))),
                    'content' => $select->show($config['draft_autosave']),
                );
            }

            if (!isset($no_override['mime_param_folding'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_param_folding';
                $select   = new html_select(array('name' => '_mime_param_folding', 'id' => $field_id));

                $select->add($RCMAIL->gettext('2231folding'), 0);
                $select->add($RCMAIL->gettext('miscfolding'), 1);
                $select->add($RCMAIL->gettext('2047folding'), 2);

                $blocks['advanced']['options']['mime_param_folding'] = array(
                    'title'    => html::label($field_id, rcube::Q($RCMAIL->gettext('mimeparamfolding'))),
                    'content'  => $select->show($config['mime_param_folding']),
                );
            }

            if (!isset($no_override['force_7bit'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_force_7bit';
                $input    = new html_checkbox(array('name' => '_force_7bit', 'id' => $field_id, 'value' => 1));

                $blocks['advanced']['options']['force_7bit'] = array(
                    'title'    => html::label($field_id, rcube::Q($RCMAIL->gettext('force7bit'))),
                    'content'  => $input->show($config['force_7bit']?1:0),
                );
            }

            if (!isset($no_override['mdn_default'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_mdn_default';
                $input    = new html_checkbox(array('name' => '_mdn_default', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['mdn_default'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('reqmdn'))),
                    'content' => $input->show($config['mdn_default']?1:0),
                );
            }

            if (!isset($no_override['dsn_default'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_dsn_default';
                $input    = new html_checkbox(array('name' => '_dsn_default', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['dsn_default'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('reqdsn'))),
                    'content' => $input->show($config['dsn_default']?1:0),
                );
            }

            if (!isset($no_override['reply_same_folder'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_reply_same_folder';
                $input    = new html_checkbox(array('name' => '_reply_same_folder', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['reply_same_folder'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('replysamefolder'))),
                    'content' => $input->show($config['reply_same_folder']?1:0),
                );
            }

            if (!isset($no_override['reply_mode'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_reply_mode';
                $select   = new html_select(array('name' => '_reply_mode', 'id' => $field_id));

                $select->add($RCMAIL->gettext('replyempty'), -1);
                $select->add($RCMAIL->gettext('replybottomposting'), 0);
                $select->add($RCMAIL->gettext('replytopposting'), 1);

                $blocks['main']['options']['reply_mode'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('whenreplying'))),
                    'content' => $select->show(intval($config['reply_mode'])),
                );
            }

            if (!isset($no_override['spellcheck_before_send']) && $config['enable_spellcheck']) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_spellcheck_before_send';
                $input    = new html_checkbox(array('name' => '_spellcheck_before_send', 'id' => $field_id, 'value' => 1));

                $blocks['spellcheck']['options']['spellcheck_before_send'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('spellcheckbeforesend'))),
                    'content' => $input->show($config['spellcheck_before_send']?1:0),
                );
            }

            if ($config['enable_spellcheck']) {
                if (!$current) {
                    continue 2;
                }

                foreach (array('syms', 'nums', 'caps') as $key) {
                    $key = 'spellcheck_ignore_'.$key;
                    if (!isset($no_override[$key])) {
                        $input = new html_checkbox(array('name' => '_'.$key, 'id' => 'rcmfd_'.$key, 'value' => 1));

                        $blocks['spellcheck']['options'][$key] = array(
                            'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext(str_replace('_', '', $key)))),
                            'content' => $input->show($config[$key]?1:0),
                        );
                    }
                }
            }

            if (!isset($no_override['show_sig'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_show_sig';
                $select   = new html_select(array('name' => '_show_sig', 'id' => $field_id));

                $select->add($RCMAIL->gettext('never'), 0);
                $select->add($RCMAIL->gettext('always'), 1);
                $select->add($RCMAIL->gettext('newmessageonly'), 2);
                $select->add($RCMAIL->gettext('replyandforwardonly'), 3);

                $blocks['sig']['options']['show_sig'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('autoaddsignature'))),
                    'content' => $select->show($RCMAIL->config->get('show_sig', 1)),
                );
            }

            if (!isset($no_override['sig_below'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_sig_below';
                $input    = new html_checkbox(array('name' => '_sig_below', 'id' => $field_id, 'value' => 1));

                $blocks['sig']['options']['sig_below'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('sigbelow'))),
                    'content' => $input->show($RCMAIL->config->get('sig_below') ? 1 : 0),
                );
            }

            if (!isset($no_override['strip_existing_sig'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_strip_existing_sig';
                $input    = new html_checkbox(array('name' => '_strip_existing_sig', 'id' => $field_id, 'value' => 1));

                $blocks['sig']['options']['strip_existing_sig'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('replyremovesignature'))),
                    'content' => $input->show($config['strip_existing_sig']?1:0),
                );
            }

            if (!isset($no_override['sig_separator'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_sig_separator';
                $input    = new html_checkbox(array('name' => '_sig_separator', 'id' => $field_id, 'value' => 1));

                $blocks['sig']['options']['sig_separator'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('sigseparator'))),
                    'content' => $input->show($RCMAIL->config->get('sig_separator') ? 1 : 0),
                );
            }

            if (!isset($no_override['forward_attachment'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_forward_attachment';
                $select = new html_select(array('name' => '_forward_attachment', 'id' => $field_id));

                $select->add($RCMAIL->gettext('inline'), 0);
                $select->add($RCMAIL->gettext('asattachment'), 1);

                $blocks['main']['options']['forward_attachment'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('forwardmode'))),
                    'content' => $select->show(intval($config['forward_attachment'])),
                );
            }

            if (!isset($no_override['default_font']) || !isset($no_override['default_font_size'])) {
                if (!$current) {
                    continue 2;
                }

                // Default font size
                $field_id = 'rcmfd_default_font_size';
                $select_default_font_size = new html_select(array('name' => '_default_font_size', 'id' => $field_id));

                $fontsizes = array('', '8pt', '10pt', '12pt', '14pt', '18pt', '24pt', '36pt');
                foreach ($fontsizes as $size) {
                    $select_default_font_size->add($size, $size);
                }

                // Default font
                $field_id = 'rcmfd_default_font';
                $select_default_font = new html_select(array('name' => '_default_font', 'id' => $field_id));
                $select_default_font->add('', '');

                $fonts = rcmail::font_defs();
                foreach (array_keys($fonts) as $fname) {
                    $select_default_font->add($fname, $fname);
                }

                $blocks['main']['options']['default_font'] = array(
                    'title' => html::label($field_id, rcube::Q($RCMAIL->gettext('defaultfont'))),
                    'content' => $select_default_font->show($RCMAIL->config->get('default_font', 1)) .
                        $select_default_font_size->show($RCMAIL->config->get('default_font_size', 1))
                );
            }

            if (!isset($no_override['reply_all_mode'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_reply_all_mode';
                $select   = new html_select(array('name' => '_reply_all_mode', 'id' => $field_id));

                $select->add($RCMAIL->gettext('replyalldefault'), 0);
                $select->add($RCMAIL->gettext('replyalllist'), 1);

                $blocks['main']['options']['reply_all_mode'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('replyallmode'))),
                    'content' => $select->show(intval($config['reply_all_mode'])),
                );
            }

            if (!isset($no_override['compose_save_localstorage'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_compose_save_localstorage';
                $input    = new html_checkbox(array('name' => '_compose_save_localstorage', 'id' => $field_id, 'value' => 1));

                $blocks['advanced']['options']['compose_save_localstorage'] = array(
                    'title'    => html::label($field_id, rcube::Q($RCMAIL->gettext('savelocalstorage'))),
                    'content'  => $input->show($config['compose_save_localstorage']?1:0),
                );
            }

        break;

        // Addressbook config
        case 'addressbook':
            $blocks = array(
                'main'     => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'advanced' => array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            if (!isset($no_override['default_addressbook'])
                && (!$current || ($books = $RCMAIL->get_address_sources(true, true)))
            ) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_default_addressbook';
                $select   = new html_select(array('name' => '_default_addressbook', 'id' => $field_id));

                foreach ($books as $book) {
                    $select->add(html_entity_decode($book['name'], ENT_COMPAT, 'UTF-8'), $book['id']);
                }

                $blocks['main']['options']['default_addressbook'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('defaultabook'))),
                    'content' => $select->show($config['default_addressbook']),
                );
            }

            // show addressbook listing mode selection
            if (!isset($no_override['addressbook_name_listing'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_addressbook_name_listing';
                $select   = new html_select(array('name' => '_addressbook_name_listing', 'id' => $field_id));

                $select->add($RCMAIL->gettext('name'), 0);
                $select->add($RCMAIL->gettext('firstname') . ' '  . $RCMAIL->gettext('surname'), 1);
                $select->add($RCMAIL->gettext('surname')   . ' '  . $RCMAIL->gettext('firstname'), 2);
                $select->add($RCMAIL->gettext('surname')   . ', ' . $RCMAIL->gettext('firstname'), 3);

                $blocks['main']['options']['list_name_listing'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('listnamedisplay'))),
                    'content' => $select->show($config['addressbook_name_listing']),
                );
            }

            // show addressbook sort column
            if (!isset($no_override['addressbook_sort_col'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_addressbook_sort_col';
                $select   = new html_select(array('name' => '_addressbook_sort_col', 'id' => $field_id));

                $select->add($RCMAIL->gettext('name'), 'name');
                $select->add($RCMAIL->gettext('firstname'), 'firstname');
                $select->add($RCMAIL->gettext('surname'), 'surname');

                $blocks['main']['options']['sort_col'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('listsorting'))),
                    'content' => $select->show($config['addressbook_sort_col']),
                );
            }

            // show addressbook page size selection
            if (!isset($no_override['addressbook_pagesize'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_addressbook_pagesize';
                $input    = new html_inputfield(array('name' => '_addressbook_pagesize', 'id' => $field_id, 'size' => 5));
                $size     = intval($config['addressbook_pagesize'] ?: $config['pagesize']);

                $blocks['main']['options']['pagesize'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('pagesize'))),
                    'content' => $input->show($size ?: 50),
                );
            }

            if (!isset($no_override['autocomplete_single'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_autocomplete_single';
                $checkbox = new html_checkbox(array('name' => '_autocomplete_single', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['autocomplete_single'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('autocompletesingle'))),
                    'content' => $checkbox->show($config['autocomplete_single']?1:0),
                );
            }
        break;

        // Special IMAP folders
        case 'folders':
            $blocks = array(
                'main'     => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'advanced' => array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            if (!isset($no_override['show_real_foldernames'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'show_real_foldernames';
                $input    = new html_checkbox(array('name' => '_show_real_foldernames', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['show_real_foldernames'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('show_real_foldernames'))),
                    'content' => $input->show($config['show_real_foldernames']?1:0),
                );
            }

            // Configure special folders
            $set = array('drafts_mbox', 'sent_mbox', 'junk_mbox', 'trash_mbox');
            if ($current && count(array_intersect($no_override, $set)) < 4) {
                $select = $RCMAIL->folder_selector(array(
                    'noselection'   => '---',
                    'realnames'     => true,
                    'maxlength'     => 30,
                    'folder_filter' => 'mail',
                    'folder_rights' => 'w',
                ));

                // #1486114, #1488279, #1489219
                $onchange = "if ($(this).val() == 'INBOX') $(this).val('')";
            }

            if (!isset($no_override['drafts_mbox'])) {
                if (!$current) {
                    continue 2;
                }

                $blocks['main']['options']['drafts_mbox'] = array(
                    'title'   => rcube::Q($RCMAIL->gettext('drafts')),
                    'content' => $select->show($config['drafts_mbox'], array('name' => "_drafts_mbox", 'onchange' => $onchange)),
                );
            }

            if (!isset($no_override['sent_mbox'])) {
                if (!$current) {
                    continue 2;
                }

                $blocks['main']['options']['sent_mbox'] = array(
                    'title'   => rcube::Q($RCMAIL->gettext('sent')),
                    'content' => $select->show($config['sent_mbox'], array('name' => "_sent_mbox", 'onchange' => '')),
                );
            }

            if (!isset($no_override['junk_mbox'])) {
                if (!$current) {
                    continue 2;
                }

                $blocks['main']['options']['junk_mbox'] = array(
                    'title'   => rcube::Q($RCMAIL->gettext('junk')),
                    'content' => $select->show($config['junk_mbox'], array('name' => "_junk_mbox", 'onchange' => $onchange)),
                );
            }

            if (!isset($no_override['trash_mbox'])) {
                if (!$current) {
                    continue 2;
                }

                $blocks['main']['options']['trash_mbox'] = array(
                    'title'   => rcube::Q($RCMAIL->gettext('trash')),
                    'content' => $select->show($config['trash_mbox'], array('name' => "_trash_mbox", 'onchange' => $onchange)),
                );
            }
        break;

        // Server settings
        case 'server':
            $blocks = array(
                'main'        => array('name' => rcube::Q($RCMAIL->gettext('mainoptions'))),
                'maintenance' => array('name' => rcube::Q($RCMAIL->gettext('maintenance'))),
                'advanced'    => array('name' => rcube::Q($RCMAIL->gettext('advancedoptions'))),
            );

            if (!isset($no_override['read_when_deleted'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_read_deleted';
                $input    = new html_checkbox(array('name' => '_read_when_deleted', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['read_when_deleted'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('readwhendeleted'))),
                    'content' => $input->show($config['read_when_deleted']?1:0),
                );
            }

            if (!isset($no_override['flag_for_deletion'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_flag_for_deletion';
                $input    = new html_checkbox(array('name' => '_flag_for_deletion', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['flag_for_deletion'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('flagfordeletion'))),
                    'content' => $input->show($config['flag_for_deletion']?1:0),
                );
            }

            // don't show deleted messages
            if (!isset($no_override['skip_deleted'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_skip_deleted';
                $input    = new html_checkbox(array('name' => '_skip_deleted', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['skip_deleted'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('skipdeleted'))),
                    'content' => $input->show($config['skip_deleted']?1:0),
                );
            }

            if (!isset($no_override['delete_always'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_delete_always';
                $input    = new html_checkbox(array('name' => '_delete_always', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['delete_always'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('deletealways'))),
                    'content' => $input->show($config['delete_always']?1:0),
                );
            }

            if (!isset($no_override['delete_junk'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_delete_junk';
                $input    = new html_checkbox(array('name' => '_delete_junk', 'id' => $field_id, 'value' => 1));

                $blocks['main']['options']['delete_junk'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('deletejunk'))),
                    'content' => $input->show($config['delete_junk']?1:0),
                );
            }

            // Trash purging on logout
            if (!isset($no_override['logout_purge'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_logout_purge';
                $input    = new html_checkbox(array('name' => '_logout_purge', 'id' => $field_id, 'value' => 1));

                $blocks['maintenance']['options']['logout_purge'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('logoutclear'))),
                    'content' => $input->show($config['logout_purge']?1:0),
                );
            }

            // INBOX compacting on logout
            if (!isset($no_override['logout_expunge'])) {
                if (!$current) {
                    continue 2;
                }

                $field_id = 'rcmfd_logout_expunge';
                $input    = new html_checkbox(array('name' => '_logout_expunge', 'id' => $field_id, 'value' => 1));

                $blocks['maintenance']['options']['logout_expunge'] = array(
                    'title'   => html::label($field_id, rcube::Q($RCMAIL->gettext('logoutcompact'))),
                    'content' => $input->show($config['logout_expunge']?1:0),
                );
            }
        }

        $found = false;
        $data  = $RCMAIL->plugins->exec_hook('preferences_list',
            array('section' => $sect['id'], 'blocks' => $blocks, 'current' => $current));

        $advanced_prefs = (array) $RCMAIL->config->get('advanced_prefs');

        // create output
        foreach ($data['blocks'] as $key => $block) {
            if (!empty($block['content']) || !empty($block['options'])) {
                $found = true;
            }
            // move some options to the 'advanced' block as configured by admin
            if ($key != 'advanced') {
                foreach ($advanced_prefs as $opt) {
                    if ($block['options'][$opt]) {
                        $data['blocks']['advanced']['options'][$opt] = $block['options'][$opt];
                        unset($data['blocks'][$key]['options'][$opt]);
                    }
                }
            }
        }

        // move 'advanced' block to the end of the list
        if (!empty($data['blocks']['advanced'])) {
            $adv = $data['blocks']['advanced'];
            unset($data['blocks']['advanced']);
            $data['blocks']['advanced'] = $adv;
        }

        if (!$found)
            unset($sections[$idx]);
        else
            $sections[$idx]['blocks'] = $data['blocks'];

        // allow plugins to add a header to each section
        $data = $RCMAIL->plugins->exec_hook('preferences_section_header',
            array('section' => $sect['id'], 'header' => '', 'current' => $current));

        if (!empty($data['header'])) {
            $sections[$idx]['header'] = $data['header'];
        }
    }

    return array($sections, $plugin['cols']);
}


function rcmail_get_skins()
{
    $path  = RCUBE_INSTALL_PATH . 'skins';
    $skins = array();
    $dir   = opendir($path);

    if (!$dir) {
        return false;
    }

    while (($file = readdir($dir)) !== false) {
        $filename = $path.'/'.$file;
        if (!preg_match('/^\./', $file) && is_dir($filename) && is_readable($filename)) {
            $skins[] = $file;
        }
    }

    closedir($dir);

    return $skins;
}


function rcmail_folder_options($mailbox)
{
    global $RCMAIL;

    $options = $RCMAIL->get_storage()->folder_info($mailbox);
    $options['protected'] = $options['is_root']
        || strtoupper($mailbox) === 'INBOX'
        || ($options['special'] && $RCMAIL->config->get('protect_default_folders'));

    return $options;
}

/**
 * Updates (or creates) folder row in the subscriptions table
 *
 * @param string $name      Folder name
 * @param string $oldname   Old folder name (for update)
 * @param bool   $subscribe Checks subscription checkbox
 * @param string $class     CSS class name for folder row
 */
function rcmail_update_folder_row($name, $oldname=null, $subscribe=false, $class_name=null)
{
    global $RCMAIL, $OUTPUT;

    $protect_folders = $RCMAIL->config->get('protect_default_folders');
    $storage         = $RCMAIL->get_storage();
    $delimiter       = $storage->get_hierarchy_delimiter();

    $name_utf8    = rcube_charset::convert($name, 'UTF7-IMAP');
    $protected    = $protect_folders && $storage->is_special_folder($name);
    $foldersplit  = explode($delimiter, $storage->mod_folder($name));
    $level        = count($foldersplit) - 1;
    $display_name = $protected ? $RCMAIL->localize_foldername($name) : rcube_charset::convert($foldersplit[$level], 'UTF7-IMAP');
    $class_name   = trim($class_name . ' mailbox');

    if ($oldname === null) {
        $OUTPUT->command('add_folder_row', $name, $name_utf8, $display_name, $protected, $subscribe,
            $class_name);
    }
    else {
        $OUTPUT->command('replace_folder_row', $oldname, $name, $name_utf8, $display_name, $protected, $class_name);
    }
}

/**
 * Render the list of settings sections (AKA tabs)
 */
function rcmail_settings_tabs($attrib)
{
    global $RCMAIL, $OUTPUT;

    // add default attributes
    $attrib += array('tagname' => 'span', 'idprefix' => 'settingstab', 'selclass' => 'selected');

    $default_actions = array(
        array('command' => 'preferences', 'type' => 'link', 'label' => 'preferences', 'title' => 'editpreferences'),
        array('command' => 'folders',     'type' => 'link', 'label' => 'folders',     'title' => 'managefolders'),
        array('command' => 'identities',  'type' => 'link', 'label' => 'identities',  'title' => 'manageidentities'),
        array('command' => 'responses',   'type' => 'link', 'label' => 'responses',   'title' => 'manageresponses'),
    );

    $disabled_actions = (array) $RCMAIL->config->get('disabled_actions');

    // get all identites from DB and define list of cols to be displayed
    $plugin = $RCMAIL->plugins->exec_hook('settings_actions', array(
        'actions' => $default_actions,
        'attrib'  => $attrib,
    ));

    $attrib  = $plugin['attrib'];
    $tagname = $attrib['tagname'];
    $tabs    = array();

    foreach ($plugin['actions'] as $action) {
        $task_action = $action['command'] ? $action['command'] : $action['action'];
        if (in_array('settings.' . $task_action, $disabled_actions)) {
            continue;
        }

        if (!$action['command'] && !$action['href'] && $action['action']) {
            $action['href'] = $RCMAIL->url(array('_action' => $action['action']));
        }

        $button = $OUTPUT->button($action);
        $attr   = $attrib;

        $cmd = $action['action'] ?: $action['command'];
        $id  = $action['id'] ?: $cmd;

        if (!empty($id)) {
            $attr['id'] = preg_replace('/[^a-z0-9]/i', '', $attrib['idprefix'] . $id);
        }

        $classnames = array($attrib['class']);
        if (!empty($action['class'])) {
            $classnames[] = $action['class'];
        }
        else if (!empty($cmd)) {
            $classnames[] = $cmd;
        }
        if ($RCMAIL->action == $cmd) {
            $classnames[] = $attrib['selclass'];
        }

        $attr['class'] = join(' ', $classnames);
        $tabs[] = html::tag($tagname, $attr, $button, html::$common_attrib);
    }

    return join('', $tabs);
}
