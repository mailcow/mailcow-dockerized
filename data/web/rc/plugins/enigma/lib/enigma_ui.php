<?php

/**
 +-------------------------------------------------------------------------+
 | User Interface for the Enigma Plugin                                    |
 |                                                                         |
 | Copyright (C) 2010-2015 The Roundcube Dev Team                          |
 |                                                                         |
 | Licensed under the GNU General Public License version 3 or              |
 | any later version with exceptions for skins & plugins.                  |
 | See the README file for a full license statement.                       |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                              |
 +-------------------------------------------------------------------------+
*/

class enigma_ui
{
    private $rc;
    private $enigma;
    private $home;
    private $css_loaded;
    private $js_loaded;
    private $data;
    private $keys_parts  = array();
    private $keys_bodies = array();


    function __construct($enigma_plugin, $home='')
    {
        $this->enigma = $enigma_plugin;
        $this->rc     = $enigma_plugin->rc;
        $this->home   = $home; // we cannot use $enigma_plugin->home here
    }

    /**
     * UI initialization and requests handlers.
     *
     * @param string Preferences section
     */
    function init()
    {
        $this->add_js();

        $action = rcube_utils::get_input_value('_a', rcube_utils::INPUT_GPC);

        if ($this->rc->action == 'plugin.enigmakeys') {
            switch ($action) {
                case 'delete':
                    $this->key_delete();
                    break;
/*
                case 'edit':
                    $this->key_edit();
                    break;
*/
                case 'import':
                    $this->key_import();
                    break;

                case 'export':
                    $this->key_export();
                    break;

                case 'generate':
                    $this->key_generate();
                    break;

                case 'create':
                    $this->key_create();
                    break;

                case 'search':
                case 'list':
                    $this->key_list();
                    break;

                case 'info':
                    $this->key_info();
                    break;
            }

            $this->rc->output->add_handlers(array(
                    'keyslist'     => array($this, 'tpl_keys_list'),
                    'keyframe'     => array($this, 'tpl_key_frame'),
                    'countdisplay' => array($this, 'tpl_keys_rowcount'),
                    'searchform'   => array($this->rc->output, 'search_form'),
            ));

            $this->rc->output->set_pagetitle($this->enigma->gettext('enigmakeys'));
            $this->rc->output->send('enigma.keys');
        }
/*
        // Preferences UI
        else if ($this->rc->action == 'plugin.enigmacerts') {
            $this->rc->output->add_handlers(array(
                    'keyslist'     => array($this, 'tpl_certs_list'),
                    'keyframe'     => array($this, 'tpl_cert_frame'),
                    'countdisplay' => array($this, 'tpl_certs_rowcount'),
                    'searchform'   => array($this->rc->output, 'search_form'),
            ));

            $this->rc->output->set_pagetitle($this->enigma->gettext('enigmacerts'));
            $this->rc->output->send('enigma.certs'); 
        }
*/
        // Message composing UI
        else if ($this->rc->action == 'compose') {
            $this->compose_ui();
        }
    }

    /**
     * Adds CSS style file to the page header.
     */
    function add_css()
    {
        if ($this->css_loaded)
            return;

        $skin_path = $this->enigma->local_skin_path();
        if (is_file($this->home . "/$skin_path/enigma.css")) {
            $this->enigma->include_stylesheet("$skin_path/enigma.css");
        }

        $this->css_loaded = true;
    }

    /**
     * Adds javascript file to the page header.
     */
    function add_js()
    {
        if ($this->js_loaded) {
            return;
        }

        $this->enigma->include_script('enigma.js');

        $this->js_loaded = true;
    }

    /**
     * Initializes key password prompt
     *
     * @param enigma_error $status Error object with key info
     * @param array        $params Optional prompt parameters
     */
    function password_prompt($status, $params = array())
    {
        $data = $status->getData('missing');

        if (empty($data)) {
            $data = $status->getData('bad');
        }

        $keyid = key($data);
        $data  = array(
            'keyid' => $params['keyid'] ?: $keyid,
            'user'  => $data[$keyid]
        );

        // With GnuPG 2.1 user name may not be specified (e.g. on private
        // key export), we'll get the key information and set the name appropriately
        if ($keyid && $params['keyid'] && strpos($data['user'], $keyid) !== false) {
            $key = $this->enigma->engine->get_key($params['keyid']);
            if ($key && $key->name) {
                $data['user'] = $key->name;
            }
        }

        if (!empty($params)) {
            $data = array_merge($params, $data);
        }

        if (preg_match('/^(send|plugin.enigmaimport|plugin.enigmakeys)$/', $this->rc->action)) {
            $this->rc->output->command('enigma_password_request', $data);
        }
        else {
            $this->rc->output->set_env('enigma_password_request', $data);
        }

        // add some labels to client
        $this->rc->output->add_label('enigma.enterkeypasstitle', 'enigma.enterkeypass',
            'save', 'cancel');

        $this->add_css();
        $this->add_js();
    }

    /**
     * Template object for key info/edit frame.
     *
     * @param array Object attributes
     *
     * @return string HTML output
     */
    function tpl_key_frame($attrib)
    {
        return $this->rc->output->frame($attrib, true);
    }

    /**
     * Template object for list of keys.
     *
     * @param array Object attributes
     *
     * @return string HTML content
     */
    function tpl_keys_list($attrib)
    {
        // add id to message list table if not specified
        if (!strlen($attrib['id'])) {
            $attrib['id'] = 'rcmenigmakeyslist';
        }

        // define list of cols to be displayed
        $a_show_cols = array('name');

        // create XHTML table
        $out = $this->rc->table_output($attrib, array(), $a_show_cols, 'id');

        // set client env
        $this->rc->output->add_gui_object('keyslist', $attrib['id']);
        $this->rc->output->include_script('list.js');

        // add some labels to client
        $this->rc->output->add_label('enigma.keyremoveconfirm', 'enigma.keyremoving',
            'enigma.keyexportprompt', 'enigma.withprivkeys', 'enigma.onlypubkeys', 'enigma.exportkeys'
        );

        return $out;
    }

    /**
     * Key listing (and searching) request handler
     */
    private function key_list()
    {
        $this->enigma->load_engine();

        $pagesize = $this->rc->config->get('pagesize', 100);
        $page     = max(intval(rcube_utils::get_input_value('_p', rcube_utils::INPUT_GPC)), 1);
        $search   = rcube_utils::get_input_value('_q', rcube_utils::INPUT_GPC);

        // Get the list
        $list = $this->enigma->engine->list_keys($search);

        if ($list && ($list instanceof enigma_error))
            $this->rc->output->show_message('enigma.keylisterror', 'error');
        else if (empty($list))
            $this->rc->output->show_message('enigma.nokeysfound', 'notice');
        else if (is_array($list)) {
            // Save the size
            $listsize = count($list);

            // Sort the list by key (user) name
            usort($list, array('enigma_key', 'cmp'));

            // Slice current page
            $list = array_slice($list, ($page - 1) * $pagesize, $pagesize);
            $size = count($list);

            // Add rows
            foreach ($list as $key) {
                $this->rc->output->command('enigma_add_list_row', array(
                        'name'  => rcube::Q($key->name),
                        'id'    => $key->id,
                        'flags' => $key->is_private() ? 'p' : ''
                ));
            }
        }

        $this->rc->output->set_env('rowcount', $size);
        $this->rc->output->set_env('search_request', $search);
        $this->rc->output->set_env('pagecount', ceil($listsize/$pagesize));
        $this->rc->output->set_env('current_page', $page);
        $this->rc->output->command('set_rowcount',
            $this->get_rowcount_text($listsize, $size, $page));

        $this->rc->output->send();
    }

    /**
     * Template object for list records counter.
     *
     * @param array Object attributes
     *
     * @return string HTML output
     */
    function tpl_keys_rowcount($attrib)
    {
        if (!$attrib['id'])
            $attrib['id'] = 'rcmcountdisplay';

        $this->rc->output->add_gui_object('countdisplay', $attrib['id']);

        return html::span($attrib, $this->get_rowcount_text());
    }

    /**
     * Returns text representation of list records counter
     */
    private function get_rowcount_text($all=0, $curr_count=0, $page=1)
    {
        if (!$curr_count) {
            $out = $this->enigma->gettext('nokeysfound');
        }
        else {
            $pagesize = $this->rc->config->get('pagesize', 100);
            $first    = ($page - 1) * $pagesize;

            $out = $this->enigma->gettext(array(
                'name' => 'keysfromto',
                'vars' => array(
                    'from'  => $first + 1,
                    'to'    => $first + $curr_count,
                    'count' => $all)
            ));
        }

        return $out;
    }

    /**
     * Key information page handler
     */
    private function key_info()
    {
        $this->enigma->load_engine();

        $id  = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
        $res = $this->enigma->engine->get_key($id);

        if ($res instanceof enigma_key) {
            $this->data = $res;
        }
        else { // error
            $this->rc->output->show_message('enigma.keyopenerror', 'error');
            $this->rc->output->command('parent.enigma_loadframe');
            $this->rc->output->send('iframe');
        }

        $this->rc->output->add_handlers(array(
            'keyname' => array($this, 'tpl_key_name'),
            'keydata' => array($this, 'tpl_key_data'),
        ));

        $this->rc->output->set_pagetitle($this->enigma->gettext('keyinfo'));
        $this->rc->output->send('enigma.keyinfo');
    }

    /**
     * Template object for key name
     */
    function tpl_key_name($attrib)
    {
        return rcube::Q($this->data->name);
    }

    /**
     * Template object for key information page content
     */
    function tpl_key_data($attrib)
    {
        $out   = '';
        $table = new html_table(array('cols' => 2));

        // Key user ID
        $table->add('title', $this->enigma->gettext('keyuserid'));
        $table->add(null, rcube::Q($this->data->name));

        // Key ID
        $table->add('title', $this->enigma->gettext('keyid'));
        $table->add(null, $this->data->subkeys[0]->get_short_id());

        // Key type
        $keytype = $this->data->get_type();
        if ($keytype == enigma_key::TYPE_KEYPAIR) {
            $type = $this->enigma->gettext('typekeypair');
        }
        else if ($keytype == enigma_key::TYPE_PUBLIC) {
            $type = $this->enigma->gettext('typepublickey');
        }
        $table->add('title', $this->enigma->gettext('keytype'));
        $table->add(null, $type);

        // Key fingerprint
        $table->add('title', $this->enigma->gettext('fingerprint'));
        $table->add(null, $this->data->subkeys[0]->get_fingerprint());

        $out .= html::tag('fieldset', null,
            html::tag('legend', null,
                $this->enigma->gettext('basicinfo')) . $table->show($attrib));

        // Subkeys
        $table = new html_table(array('cols' => 5, 'id' => 'enigmasubkeytable', 'class' => 'records-table'));

        $table->add_header('id', $this->enigma->gettext('subkeyid'));
        $table->add_header('algo', $this->enigma->gettext('subkeyalgo'));
        $table->add_header('created', $this->enigma->gettext('subkeycreated'));
        $table->add_header('expires', $this->enigma->gettext('subkeyexpires'));
        $table->add_header('usage', $this->enigma->gettext('subkeyusage'));

        $now         = time();
        $date_format = $this->rc->config->get('date_format', 'Y-m-d');
        $usage_map   = array(
            enigma_key::CAN_ENCRYPT      => $this->enigma->gettext('typeencrypt'),
            enigma_key::CAN_SIGN         => $this->enigma->gettext('typesign'),
            enigma_key::CAN_CERTIFY      => $this->enigma->gettext('typecert'),
            enigma_key::CAN_AUTHENTICATE => $this->enigma->gettext('typeauth'),
        );

        foreach ($this->data->subkeys as $subkey) {
            $algo = $subkey->get_algorithm();
            if ($algo && $subkey->length) {
                $algo .= ' (' . $subkey->length . ')';
            }

            $usage = array();
            foreach ($usage_map as $key => $text) {
                if ($subkey->usage & $key) {
                    $usage[] = $text;
                }
            }

            $table->add('id', $subkey->get_short_id());
            $table->add('algo', $algo);
            $table->add('created', $subkey->created ? $this->rc->format_date($subkey->created, $date_format, false) : '');
            $table->add('expires', $subkey->expires ? $this->rc->format_date($subkey->expires, $date_format, false) : $this->enigma->gettext('expiresnever'));
            $table->add('usage', implode(',', $usage));
            $table->set_row_attribs($subkey->revoked || ($subkey->expires && $subkey->expires < $now) ? 'deleted' : '');
        }

        $out .= html::tag('fieldset', null,
            html::tag('legend', null,
                $this->enigma->gettext('subkeys')) . $table->show());

        // Additional user IDs
        $table = new html_table(array('cols' => 2, 'id' => 'enigmausertable', 'class' => 'records-table'));

        $table->add_header('id', $this->enigma->gettext('userid'));
        $table->add_header('valid', $this->enigma->gettext('uservalid'));

        foreach ($this->data->users as $user) {
            $username = $user->name;
            if ($user->comment) {
                $username .= ' (' . $user->comment . ')';
            }
            $username .= ' <' . $user->email . '>';

            $table->add('id', rcube::Q(trim($username)));
            $table->add('valid', $this->enigma->gettext($user->valid ? 'valid' : 'unknown'));
            $table->set_row_attribs($user->revoked || !$user->valid ? 'deleted' : '');
        }

        $out .= html::tag('fieldset', null,
            html::tag('legend', null,
                $this->enigma->gettext('userids')) . $table->show());

        return $out;
    }

    /**
     * Key(s) export handler
     */
    private function key_export()
    {
        $keys   = rcube_utils::get_input_value('_keys', rcube_utils::INPUT_POST);
        $priv   = rcube_utils::get_input_value('_priv', rcube_utils::INPUT_POST);
        $engine = $this->enigma->load_engine();
        $list   = $keys == '*' ? $engine->list_keys() : explode(',', $keys);

        if (is_array($list) && ($fp = fopen('php://memory', 'rw'))) {
            $filename = 'export.pgp';
            if (count($list) == 1) {
                $filename = (is_object($list[0]) ? $list[0]->id : $list[0]) . '.pgp';
            }

            $status = null;
            foreach ($list as $key) {
                $keyid  = is_object($key) ? $key->id : $key;
                $status = $engine->export_key($keyid, $fp, (bool) $priv);

                if ($status instanceof enigma_error) {
                    $code = $status->getCode();

                    if ($code == enigma_error::BADPASS) {
                        $this->password_prompt($status, array(
                                'input_keys'   => $keys,
                                'input_priv'   => 1,
                                'input_task'   => 'settings',
                                'input_action' => 'plugin.enigmakeys',
                                'input_a'      => 'export',
                                'action'       => '?',
                                'iframe'       => true,
                                'nolock'       => true,
                                'keyid'        => $keyid,
                        ));
                        fclose($fp);
                        $this->rc->output->send('iframe');
                    }
                }
            }

            // send downlaod headers
            header('Content-Type: application/pgp-keys');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            rewind($fp);
            while (!feof($fp)) {
                echo fread($fp, 1024 * 1024);
            }
            fclose($fp);
        }

        exit;
    }

    /**
     * Key import (page) handler
     */
    private function key_import()
    {
        // Import process
        if ($data = rcube_utils::get_input_value('_keys', rcube_utils::INPUT_POST)) {
            $this->enigma->load_engine();
            $this->enigma->engine->password_handler();

            $result = $this->enigma->engine->import_key($data);

            if (is_array($result)) {
                if (rcube_utils::get_input_value('_generated', rcube_utils::INPUT_POST)) {
                    $this->rc->output->command('enigma_key_create_success');
                    $this->rc->output->show_message('enigma.keygeneratesuccess', 'confirmation');
                }
                else {
                    $this->rc->output->show_message('enigma.keysimportsuccess', 'confirmation',
                        array('new' => $result['imported'], 'old' => $result['unchanged']));

                    if ($result['imported'] && !empty($_POST['_refresh'])) {
                        $this->rc->output->command('enigma_list', 1, false);
                    }
                }
            }
            else {
                $this->rc->output->show_message('enigma.keysimportfailed', 'error');
            }

            $this->rc->output->send();
        }
        else if ($_FILES['_file']['tmp_name'] && is_uploaded_file($_FILES['_file']['tmp_name'])) {
            $this->enigma->load_engine();
            $result = $this->enigma->engine->import_key($_FILES['_file']['tmp_name'], true);

            if (is_array($result)) {
                // reload list if any keys has been added
                if ($result['imported']) {
                    $this->rc->output->command('parent.enigma_list', 1);
                }
                else {
                    $this->rc->output->command('parent.enigma_loadframe');
                }

                $this->rc->output->show_message('enigma.keysimportsuccess', 'confirmation',
                    array('new' => $result['imported'], 'old' => $result['unchanged']));
            }
            else if ($result instanceof enigma_error && $result->getCode() == enigma_error::BADPASS) {
                $this->password_prompt($result);
            }
            else {
                $this->rc->output->show_message('enigma.keysimportfailed', 'error');
            }

            $this->rc->output->send('iframe');
        }
        else if ($err = $_FILES['_file']['error']) {
            if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
                $this->rc->output->show_message('filesizeerror', 'error',
                    array('size' => $this->rc->show_bytes(rcube_utils::max_upload_size())));
            } else {
                $this->rc->output->show_message('fileuploaderror', 'error');
            }

            $this->rc->output->send('iframe');
        }

        $this->rc->output->add_handlers(array(
            'importform' => array($this, 'tpl_key_import_form'),
        ));

        $this->rc->output->set_pagetitle($this->enigma->gettext('keyimport'));
        $this->rc->output->send('enigma.keyimport');
    }

    /**
     * Template object for key import (upload) form
     */
    function tpl_key_import_form($attrib)
    {
        $attrib += array('id' => 'rcmKeyImportForm');

        $upload = new html_inputfield(array('type' => 'file', 'name' => '_file',
            'id' => 'rcmimportfile', 'size' => 30));
        $search = new html_inputfield(array('type' => 'text', 'name' => '_search',
            'id' => 'rcmimportsearch', 'size' => 30));

        $upload_button = new html_inputfield(array(
                'type'    => 'button',
                'value'   => $this->rc->gettext('import'),
                'class'   => 'button',
                'onclick' => "return rcmail.command('plugin.enigma-import','',this,event)",
        ));

        $search_button = new html_inputfield(array(
                'type'    => 'button',
                'value'   => $this->rc->gettext('search'),
                'class'   => 'button',
                'onclick' => "return rcmail.command('plugin.enigma-import-search','',this,event)",
        ));

        $upload_form = html::div(null,
            rcube::Q($this->enigma->gettext('keyimporttext'), 'show')
            . html::br() . html::br() . $upload->show()
            . html::br() . html::br() . $upload_button->show()
        );

        $search_form = html::div(null,
            rcube::Q($this->enigma->gettext('keyimportsearchtext'), 'show')
            . html::br() . html::br() . $search->show()
            . html::br() . html::br() . $search_button->show()
        );

        $form = html::tag('fieldset', '', html::tag('legend', null, $this->enigma->gettext('keyimportlabel')) . $upload_form)
            . html::tag('fieldset', '', html::tag('legend', null, $this->enigma->gettext('keyimportsearchlabel')) . $search_form);

        $this->rc->output->add_label('selectimportfile', 'importwait', 'nopubkeyfor', 'nopubkeyforsender',
            'encryptnoattachments','encryptedsendialog','searchpubkeyservers', 'importpubkeys',
            'encryptpubkeysfound',  'search', 'close', 'import', 'keyid', 'keylength', 'keyexpired',
            'keyrevoked', 'keyimportsuccess', 'keyservererror');
        $this->rc->output->add_gui_object('importform', $attrib['id']);
        $this->rc->output->include_script('publickey.js');

        $out = $this->rc->output->form_tag(array(
            'action'  => $this->rc->url(array('action' => $this->rc->action, 'a' => 'import')),
            'method'  => 'post',
            'enctype' => 'multipart/form-data') + $attrib,
            $form
        );

        return $out;
    }

    /**
     * Server-side key pair generation handler
     */
    private function key_generate()
    {
        // Crypt_GPG does not support key generation for multiple identities
        // It is also very slow (which is problematic because it may exceed
        // request time limit) and requires entropy generator
        // That's why we use only OpenPGP.js method of key generation
        return;

        $user = rcube_utils::get_input_value('_user', rcube_utils::INPUT_POST, true);
        $pass = rcube_utils::get_input_value('_password', rcube_utils::INPUT_POST, true);
        $size = (int) rcube_utils::get_input_value('_size', rcube_utils::INPUT_POST);

        if ($size > 4096) {
            $size = 4096;
        }

        $ident = rcube_mime::decode_address_list($user, 1, false);

        if (empty($ident)) {
            $this->rc->output->show_message('enigma.keygenerateerror', 'error');
            $this->rc->output->send();
        }

        $this->enigma->load_engine();
        $result = $this->enigma->engine->generate_key(array(
            'user'     => $ident[1]['name'],
            'email'    => $ident[1]['mailto'],
            'password' => $pass,
            'size'     => $size,
        ));

        if ($result instanceof enigma_key) {
            $this->rc->output->command('enigma_key_create_success');
            $this->rc->output->show_message('enigma.keygeneratesuccess', 'confirmation');
        }
        else {
            $this->rc->output->show_message('enigma.keygenerateerror', 'error');
        }

        $this->rc->output->send();
    }

    /**
     * Key generation page handler
     */
    private function key_create()
    {
        $this->enigma->include_script('openpgp.min.js');

        $this->rc->output->add_handlers(array(
            'keyform' => array($this, 'tpl_key_create_form'),
        ));

        $this->rc->output->set_pagetitle($this->enigma->gettext('keygenerate'));
        $this->rc->output->send('enigma.keycreate');
    }

    /**
     * Template object for key generation form
     */
    function tpl_key_create_form($attrib)
    {
        $attrib += array('id' => 'rcmKeyCreateForm');
        $table  = new html_table(array('cols' => 2));

        // get user's identities
        $identities = $this->rc->user->list_identities(null, true);
        $checkbox   = new html_checkbox(array('name' => 'identity[]'));
        foreach ((array) $identities as $idx => $ident) {
            $name = empty($ident['name']) ? ($ident['email']) : $ident['ident'];
            $identities[$idx] = html::label(null, $checkbox->show($name, array('value' => $name)) . rcube::Q($name));
        }

        $table->add('title', html::label('key-name', rcube::Q($this->enigma->gettext('newkeyident'))));
        $table->add(null, implode($identities, "\n"));

        // Key size
        $select = new html_select(array('name' => 'size', 'id' => 'key-size'));
        $select->add($this->enigma->gettext('key2048'), '2048');
        $select->add($this->enigma->gettext('key4096'), '4096');

        $table->add('title', html::label('key-size', rcube::Q($this->enigma->gettext('newkeysize'))));
        $table->add(null, $select->show());

        // Password and confirm password
        $table->add('title', html::label('key-pass', rcube::Q($this->enigma->gettext('newkeypass'))));
        $table->add(null, rcube_output::get_edit_field('password', '',
            array('id' => 'key-pass', 'size' => $attrib['size'], 'required' => true), 'password'));

        $table->add('title', html::label('key-pass-confirm', rcube::Q($this->enigma->gettext('newkeypassconfirm'))));
        $table->add(null, rcube_output::get_edit_field('password-confirm', '',
            array('id' => 'key-pass-confirm', 'size' => $attrib['size'], 'required' => true), 'password'));

        $this->rc->output->add_gui_object('keyform', $attrib['id']);
        $this->rc->output->add_label('enigma.keygenerating', 'enigma.formerror',
            'enigma.passwordsdiffer', 'enigma.keygenerateerror', 'enigma.noidentselected',
            'enigma.keygennosupport');

        return $this->rc->output->form_tag(array(), $table->show($attrib));
    }

    /**
     * Key deleting
     */
    private function key_delete()
    {
        $keys   = rcube_utils::get_input_value('_keys', rcube_utils::INPUT_POST);
        $engine = $this->enigma->load_engine();

        foreach ((array)$keys as $key) {
            $res = $engine->delete_key($key);

            if ($res !== true) {
                $this->rc->output->show_message('enigma.keyremoveerror', 'error');
                $this->rc->output->command('enigma_list');
                $this->rc->output->send();
            }
        }

        $this->rc->output->command('enigma_list');
        $this->rc->output->show_message('enigma.keyremovesuccess', 'confirmation');
        $this->rc->output->send();
    }

    /**
     * Init compose UI (add task button and the menu)
     */
    private function compose_ui()
    {
        $this->add_css();

        // Options menu button
        $this->enigma->add_button(array(
            'type'     => 'link',
            'command'  => 'plugin.enigma',
            'onclick'  => "rcmail.command('menu-open', 'enigmamenu', event.target, event)",
            'class'    => 'button enigma',
            'title'    => 'encryptionoptions',
            'label'    => 'encryption',
            'domain'   => $this->enigma->ID,
            'width'    => 32,
            'height'   => 32,
            'aria-owns'     => 'enigmamenu',
            'aria-haspopup' => 'true',
            'aria-expanded' => 'false',
            ), 'toolbar');

        $locks = (array) $this->rc->config->get('enigma_options_lock');
        $menu  = new html_table(array('cols' => 2));
        $chbox = new html_checkbox(array('value' => 1));

        $menu->add(null, html::label(array('for' => 'enigmasignopt'),
            rcube::Q($this->enigma->gettext('signmsg'))));
        $menu->add(null, $chbox->show($this->rc->config->get('enigma_sign_all') ? 1 : 0,
                array(
                    'name'     => '_enigma_sign',
                    'id'       => 'enigmasignopt',
                    'disabled' => in_array('sign', $locks),
                )));

        $menu->add(null, html::label(array('for' => 'enigmaencryptopt'),
            rcube::Q($this->enigma->gettext('encryptmsg'))));
        $menu->add(null, $chbox->show($this->rc->config->get('enigma_encrypt_all') ? 1 : 0,
                array(
                    'name'     => '_enigma_encrypt',
                    'id'       => 'enigmaencryptopt',
                    'disabled' => in_array('encrypt', $locks),
                )));

        $menu->add(null, html::label(array('for' => 'enigmaattachpubkeyopt'),
            rcube::Q($this->enigma->gettext('attachpubkeymsg'))));
        $menu->add(null, $chbox->show($this->rc->config->get('enigma_attach_pubkey') ? 1 : 0,
                array(
                    'name'     => '_enigma_attachpubkey',
                    'id'       => 'enigmaattachpubkeyopt',
                    'disabled' => in_array('pubkey', $locks),
                )));

        $menu = html::div(array('id' => 'enigmamenu', 'class' => 'popupmenu'), $menu->show());

        // Options menu contents
        $this->rc->output->add_footer($menu);
    }

    /**
     * Handler for message_body_prefix hook.
     * Called for every displayed (content) part of the message.
     * Adds infobox about signature verification and/or decryption
     * status above the body.
     *
     * @param array Original parameters
     *
     * @return array Modified parameters
     */
    function status_message($p)
    {
        // skip: not a message part
        if ($p['part'] instanceof rcube_message) {
            return $p;
        }

        // skip: message has no signed/encoded content
        if (!$this->enigma->engine) {
            return $p;
        }

        $engine   = $this->enigma->engine;
        $part_id  = $p['part']->mime_id;
        $messages = array();

        // Decryption status
        if (($found = $this->find_part_id($part_id, $engine->decryptions)) !== null
            && ($status = $engine->decryptions[$found])
        ) {
            $attach_scripts = true;

            // show the message only once
            unset($engine->decryptions[$found]);

            // display status info
            $attrib['id'] = 'enigma-message';

            if ($status instanceof enigma_error) {
                $attrib['class'] = 'enigmaerror';
                $code            = $status->getCode();

                if ($code == enigma_error::KEYNOTFOUND) {
                    $msg = rcube::Q(str_replace('$keyid', enigma_key::format_id($status->getData('id')),
                        $this->enigma->gettext('decryptnokey')));
                }
                else if ($code == enigma_error::BADPASS) {
                    $missing = $status->getData('missing');
                    $label   = 'decrypt' . (!empty($missing) ? 'no' : 'bad') . 'pass';
                    $msg     = rcube::Q($this->enigma->gettext($label));
                    $this->password_prompt($status);
                }
                else {
                    $msg = rcube::Q($this->enigma->gettext('decrypterror'));
                }
            }
            else if ($status === enigma_engine::ENCRYPTED_PARTIALLY) {
                $attrib['class'] = 'enigmawarning';
                $msg = rcube::Q($this->enigma->gettext('decryptpartial'));
            }
            else {
                $attrib['class'] = 'enigmanotice';
                $msg = rcube::Q($this->enigma->gettext('decryptok'));
            }

            $attrib['msg'] = $msg;
            $messages[] = $attrib;
        }

        // Signature verification status
        if (($found = $this->find_part_id($part_id, $engine->signatures)) !== null
            && ($sig = $engine->signatures[$found])
        ) {
            $attach_scripts = true;

            // show the message only once
            unset($engine->signatures[$found]);

            // display status info
            $attrib['id'] = 'enigma-message';

            if ($sig instanceof enigma_signature) {
                $sender = ($sig->name ? $sig->name . ' ' : '') . '<' . $sig->email . '>';

                if ($sig->valid === enigma_error::UNVERIFIED) {
                    $attrib['class'] = 'enigmawarning';
                    $msg = str_replace('$sender', $sender, $this->enigma->gettext('sigunverified'));
                    $msg = str_replace('$keyid', $sig->id, $msg);
                    $msg = rcube::Q($msg);
                }
                else if ($sig->valid) {
                    $attrib['class'] = $sig->partial ? 'enigmawarning' : 'enigmanotice';
                    $label = 'sigvalid' . ($sig->partial ? 'partial' : '');
                    $msg = rcube::Q(str_replace('$sender', $sender, $this->enigma->gettext($label)));
                }
                else {
                    $attrib['class'] = 'enigmawarning';
                    $msg = rcube::Q(str_replace('$sender', $sender, $this->enigma->gettext('siginvalid')));
                }
            }
            else if ($sig && $sig->getCode() == enigma_error::KEYNOTFOUND) {
                $attrib['class'] = 'enigmawarning';
                $msg = rcube::Q(str_replace('$keyid', enigma_key::format_id($sig->getData('id')),
                    $this->enigma->gettext('signokey')));
            }
            else {
                $attrib['class'] = 'enigmaerror';
                $msg = rcube::Q($this->enigma->gettext('sigerror'));
            }
/*
            $msg .= '&nbsp;' . html::a(array('href' => "#sigdetails",
                'onclick' => rcmail_output::JS_OBJECT_NAME.".command('enigma-sig-details')"),
                rcube::Q($this->enigma->gettext('showdetails')));
*/
            // test
//            $msg .= '<br /><pre>'.$sig->body.'</pre>';

            $attrib['msg'] = $msg;
            $messages[]    = $attrib;
        }

        if ($count = count($messages)) {
            if ($count == 2 && $messages[0]['class'] == $messages[1]['class']) {
                $p['prefix'] .= html::div($messages[0], $messages[0]['msg'] . ' ' . $messages[1]['msg']);
            }
            else {
                foreach ($messages as $msg) {
                    $p['prefix'] .= html::div($msg, $msg['msg']);
                }
            }
        }

        if ($attach_scripts) {
            // add css and js script
            $this->add_css();
            $this->add_js();
        }

        return $p;
    }

    /**
     * Handler for message_load hook.
     * Check message bodies and attachments for keys/certs.
     */
    function message_load($p)
    {
        $engine = $this->enigma->load_engine();

        // handle keys/certs in attachments
        foreach ((array) $p['object']->attachments as $attachment) {
            if ($engine->is_keys_part($attachment)) {
                $this->keys_parts[] = $attachment->mime_id;
            }
        }

        // the same with message bodies
        foreach ((array) $p['object']->parts as $part) {
            if ($engine->is_keys_part($part)) {
                $this->keys_parts[]  = $part->mime_id;
                $this->keys_bodies[] = $part->mime_id;
            }
        }

        // @TODO: inline PGP keys

        if ($this->keys_parts) {
            $this->enigma->add_texts('localization');
        }

        return $p;
    }

    /**
     * Handler for template_object_messagebody hook.
     * This callback function adds a box below the message content
     * if there is a key/cert attachment available
     */
    function message_output($p)
    {
        foreach ($this->keys_parts as $part) {
            // remove part's body
            if (in_array($part, $this->keys_bodies)) {
                $p['content'] = '';
            }

            // add box below message body
            $p['content'] .= html::p(array('class' => 'enigmaattachment'),
                html::a(array(
                    'href'    => "#",
                    'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".enigma_import_attachment('".rcube::JQ($part)."')",
                    'title'   => $this->enigma->gettext('keyattimport')),
                    html::span(null, $this->enigma->gettext('keyattfound'))));

            $attach_scripts = true;
        }

        if ($attach_scripts) {
            // add css and js script
            $this->add_css();
            $this->add_js();
        }

        return $p;
    }

    /**
     * Handle message_ready hook (encryption/signing/attach public key)
     */
    function message_ready($p)
    {
        $savedraft      = !empty($_POST['_draft']) && empty($_GET['_saveonly']);
        $sign_enable    = (bool) rcube_utils::get_input_value('_enigma_sign', rcube_utils::INPUT_POST);
        $encrypt_enable = (bool) rcube_utils::get_input_value('_enigma_encrypt', rcube_utils::INPUT_POST);
        $pubkey_enable  = (bool) rcube_utils::get_input_value('_enigma_attachpubkey', rcube_utils::INPUT_POST);
        $locks          = (array) $this->rc->config->get('enigma_options_lock');

        if (in_array('sign', $locks)) {
            $sign_enable = (bool) $this->rc->config->get('enigma_sign_all');
        }
        if (in_array('encrypt', $locks)) {
            $encrypt_enable = (bool) $this->rc->config->get('enigma_encrypt_all');
        }
        if (in_array('pubkey', $locks)) {
            $pubkey_enable = (bool) $this->rc->config->get('enigma_attach_pubkey');
        }

        if (!$savedraft && $pubkey_enable) {
            $engine = $this->enigma->load_engine();
            $engine->attach_public_key($p['message']);
        }

        if ($encrypt_enable) {
            $engine = $this->enigma->load_engine();
            $mode   = !$savedraft && $sign_enable ? enigma_engine::ENCRYPT_MODE_SIGN : null;
            $status = $engine->encrypt_message($p['message'], $mode, $savedraft);
            $mode   = 'encrypt';
        }
        else if (!$savedraft && $sign_enable) {
            $engine = $this->enigma->load_engine();
            $status = $engine->sign_message($p['message']);
            $mode   = 'sign';
        }

        if ($mode && ($status instanceof enigma_error)) {
            $code = $status->getCode();

            if ($code == enigma_error::KEYNOTFOUND) {
                $vars = array('email' => $status->getData('missing'));
                $msg  = 'enigma.' . $mode . 'nokey';
            }
            else if ($code == enigma_error::BADPASS) {
                $this->password_prompt($status);
            }
            else {
                $msg = 'enigma.' . $mode . 'error';
            }

            if ($msg) {
                if ($vars && $vars['email']) {
                    $this->rc->output->command('enigma_key_not_found', array(
                            'email'  => $vars['email'],
                            'text'   => $this->rc->gettext(array('name' => $msg, 'vars' => $vars)),
                            'title'  => $this->enigma->gettext('keynotfound'),
                            'button' => $this->enigma->gettext('findkey'),
                    ));
                }
                else {
                    $this->rc->output->show_message($msg, 'error', $vars);
                }
            }

            $this->rc->output->send('iframe');
        }

        return $p;
    }

   /**
     * Handler for message_compose_body hook
     * Display error when the message cannot be encrypted
     * and provide a way to try again with a password.
     */
    function message_compose($p)
    {
        $engine = $this->enigma->load_engine();

        // skip: message has no signed/encoded content
        if (!$this->enigma->engine) {
            return $p;
        }

        $engine = $this->enigma->engine;
        $locks  = (array) $this->rc->config->get('enigma_options_lock');

        // Decryption status
        foreach ($engine->decryptions as $status) {
            if ($status instanceof enigma_error) {
                $code = $status->getCode();

                if ($code == enigma_error::KEYNOTFOUND) {
                    $msg = rcube::Q(str_replace('$keyid', enigma_key::format_id($status->getData('id')),
                        $this->enigma->gettext('decryptnokey')));
                }
                else if ($code == enigma_error::BADPASS) {
                    $this->password_prompt($status, array('compose-init' => true));
                    return $p;
                }
                else {
                    $msg = rcube::Q($this->enigma->gettext('decrypterror'));
                }
            }
        }

        if ($msg) {
            $this->rc->output->show_message($msg, 'error');
        }

        // Check sign/ecrypt options for signed/encrypted drafts
        if (!in_array('encrypt', $locks)) {
            $this->rc->output->set_env('enigma_force_encrypt', !empty($engine->decryptions));
        }
        if (!in_array('sign', $locks)) {
            $this->rc->output->set_env('enigma_force_sign', !empty($engine->signatures));
        }

        return $p;
    }

    /**
     * Handler for keys/certs import request action
     */
    function import_file()
    {
        $uid     = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox    = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $mime_id = rcube_utils::get_input_value('_part', rcube_utils::INPUT_POST);
        $storage = $this->rc->get_storage();
        $engine  = $this->enigma->load_engine();

        if ($uid && $mime_id) {
            // Note: we get the attachment body via rcube_message class
            // to support keys inside encrypted messages (#5285)
            $message = new rcube_message($uid, $mbox);

            // Check if we don't need to ask for password again
            foreach ($engine->decryptions as $status) {
                if ($status instanceof enigma_error) {
                    if ($status->getCode() == enigma_error::BADPASS) {
                        $this->password_prompt($status, array(
                                'input_uid'    => $uid,
                                'input_mbox'   => $mbox,
                                'input_part'   => $mime_id,
                                'input_task'   => 'mail',
                                'input_action' => 'plugin.enigmaimport',
                                'action'       => '?',
                                'iframe'       => true,
                        ));
                        $this->rc->output->send($this->rc->output->type == 'html' ? 'iframe' : null);
                        return;
                    }
                }
            }

            if ($engine->is_keys_part($message->mime_parts[$mime_id])) {
                $part = $message->get_part_body($mime_id);
            }
        }

        if ($part && is_array($result = $engine->import_key($part))) {
            $this->rc->output->show_message('enigma.keysimportsuccess', 'confirmation',
                array('new' => $result['imported'], 'old' => $result['unchanged']));
        }
        else {
            $this->rc->output->show_message('enigma.keysimportfailed', 'error');
        }

        $this->rc->output->send($this->rc->output->type == 'html' ? 'iframe' : null);
    }

    /**
     * Check if the part or its parent exists in the array
     * of decryptions/signatures. Returns found ID.
     */
    private function find_part_id($part_id, $data)
    {
        $ids   = explode('.', $part_id);
        $i     = 0;
        $count = count($ids);

        while ($i < $count && strlen($part = implode('.', array_slice($ids, 0, ++$i)))) {
            if (array_key_exists($part, $data)) {
                return $part;
            }
        }
    }
}
