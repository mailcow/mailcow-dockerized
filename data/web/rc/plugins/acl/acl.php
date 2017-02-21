<?php

/**
 * Folders Access Control Lists Management (RFC4314, RFC2086)
 *
 * @author Aleksander Machniak <alec@alec.pl>
 *
 * Copyright (C) 2011-2012, Kolab Systems AG
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 */

class acl extends rcube_plugin
{
    public $task = 'settings|addressbook|calendar';

    private $rc;
    private $supported = null;
    private $mbox;
    private $ldap;
    private $specials = array('anyone', 'anonymous');

    /**
     * Plugin initialization
     */
    function init()
    {
        $this->rc = rcmail::get_instance();

        // Register hooks
        $this->add_hook('folder_form', array($this, 'folder_form'));
        // kolab_addressbook plugin
        $this->add_hook('addressbook_form', array($this, 'folder_form'));
        $this->add_hook('calendar_form_kolab', array($this, 'folder_form'));
        // Plugin actions
        $this->register_action('plugin.acl', array($this, 'acl_actions'));
        $this->register_action('plugin.acl-autocomplete', array($this, 'acl_autocomplete'));
    }

    /**
     * Handler for plugin actions (AJAX)
     */
    function acl_actions()
    {
        $action = trim(rcube_utils::get_input_value('_act', rcube_utils::INPUT_GPC));

        // Connect to IMAP
        $this->rc->storage_init();

        // Load localization and configuration
        $this->add_texts('localization/');
        $this->load_config();

        if ($action == 'save') {
            $this->action_save();
        }
        else if ($action == 'delete') {
            $this->action_delete();
        }
        else if ($action == 'list') {
            $this->action_list();
        }

        // Only AJAX actions
        $this->rc->output->send();
    }

    /**
     * Handler for user login autocomplete request
     */
    function acl_autocomplete()
    {
        $this->load_config();

        $search = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC, true);
        $reqid  = rcube_utils::get_input_value('_reqid', rcube_utils::INPUT_GPC);
        $users  = array();
        $keys   = array();

        if ($this->init_ldap()) {
            $max  = (int) $this->rc->config->get('autocomplete_max', 15);
            $mode = (int) $this->rc->config->get('addressbook_search_mode');

            $this->ldap->set_pagesize($max);
            $result = $this->ldap->search('*', $search, $mode);

            foreach ($result->records as $record) {
                $user = $record['uid'];

                if (is_array($user)) {
                    $user = array_filter($user);
                    $user = $user[0];
                }

                if ($user) {
                    $display = rcube_addressbook::compose_search_name($record);
                    $user    = array('name' => $user, 'display' => $display);
                    $users[] = $user;
                    $keys[]  = $display ?: $user['name'];
                }
            }

            if ($this->rc->config->get('acl_groups')) {
                $prefix      = $this->rc->config->get('acl_group_prefix');
                $group_field = $this->rc->config->get('acl_group_field', 'name');
                $result      = $this->ldap->list_groups($search, $mode);

                foreach ($result as $record) {
                    $group    = $record['name'];
                    $group_id = is_array($record[$group_field]) ? $record[$group_field][0] : $record[$group_field];

                    if ($group) {
                        $users[] = array('name' => ($prefix ?: '') . $group_id, 'display' => $group, 'type' => 'group');
                        $keys[]  = $group;
                    }
                }
            }
        }

        if (count($users)) {
            // sort users index
            asort($keys, SORT_LOCALE_STRING);
            // re-sort users according to index
            foreach ($keys as $idx => $val) {
                $keys[$idx] = $users[$idx];
            }
            $users = array_values($keys);
        }

        $this->rc->output->command('ksearch_query_results', $users, $search, $reqid);
        $this->rc->output->send();
    }

    /**
     * Handler for 'folder_form' hook
     *
     * @param array $args Hook arguments array (form data)
     *
     * @return array Hook arguments array
     */
    function folder_form($args)
    {
        $mbox_imap = $args['options']['name'];
        $myrights  = $args['options']['rights'];

        // Edited folder name (empty in create-folder mode)
        if (!strlen($mbox_imap)) {
            return $args;
        }
/*
        // Do nothing on protected folders (?)
        if ($args['options']['protected']) {
            return $args;
        }
*/
        // Get MYRIGHTS
        if (empty($myrights)) {
            return $args;
        }

        // Load localization and include scripts
        $this->load_config();
        $this->specials = $this->rc->config->get('acl_specials', $this->specials);
        $this->add_texts('localization/', array('deleteconfirm', 'norights',
            'nouser', 'deleting', 'saving', 'newuser', 'editperms'));
        $this->rc->output->add_label('save', 'cancel');
        $this->include_script('acl.js');
        $this->rc->output->include_script('list.js');
        $this->include_stylesheet($this->local_skin_path().'/acl.css');

        // add Info fieldset if it doesn't exist
        if (!isset($args['form']['props']['fieldsets']['info']))
            $args['form']['props']['fieldsets']['info'] = array(
                'name'  => $this->rc->gettext('info'),
                'content' => array());

        // Display folder rights to 'Info' fieldset
        $args['form']['props']['fieldsets']['info']['content']['myrights'] = array(
            'label' => rcube::Q($this->gettext('myrights')),
            'value' => $this->acl2text($myrights)
        );

        // Return if not folder admin
        if (!in_array('a', $myrights)) {
            return $args;
        }

        // The 'Sharing' tab
        $this->mbox = $mbox_imap;
        $this->rc->output->set_env('acl_users_source', (bool) $this->rc->config->get('acl_users_source'));
        $this->rc->output->set_env('mailbox', $mbox_imap);
        $this->rc->output->add_handlers(array(
            'acltable'  => array($this, 'templ_table'),
            'acluser'   => array($this, 'templ_user'),
            'aclrights' => array($this, 'templ_rights'),
        ));

        $this->rc->output->set_env('autocomplete_max', (int)$this->rc->config->get('autocomplete_max', 15));
        $this->rc->output->set_env('autocomplete_min_length', $this->rc->config->get('autocomplete_min_length'));
        $this->rc->output->add_label('autocompletechars', 'autocompletemore');

        $args['form']['sharing'] = array(
            'name'    => rcube::Q($this->gettext('sharing')),
            'content' => $this->rc->output->parse('acl.table', false, false),
        );

        return $args;
    }

    /**
     * Creates ACL rights table
     *
     * @param array $attrib Template object attributes
     *
     * @return string HTML Content
     */
    function templ_table($attrib)
    {
        if (empty($attrib['id']))
            $attrib['id'] = 'acl-table';

        $out = $this->list_rights($attrib);

        $this->rc->output->add_gui_object('acltable', $attrib['id']);

        return $out;
    }

    /**
     * Creates ACL rights form (rights list part)
     *
     * @param array $attrib Template object attributes
     *
     * @return string HTML Content
     */
    function templ_rights($attrib)
    {
        // Get supported rights
        $supported = $this->rights_supported();

        // give plugins the opportunity to adjust this list
        $data = $this->rc->plugins->exec_hook('acl_rights_supported',
            array('rights' => $supported, 'folder' => $this->mbox, 'labels' => array()));
        $supported = $data['rights'];

        // depending on server capability either use 'te' or 'd' for deleting msgs
        $deleteright = implode(array_intersect(str_split('ted'), $supported));

        $out = '';
        $ul  = '';
        $input = new html_checkbox();

        // Advanced rights
        $attrib['id'] = 'advancedrights';
        foreach ($supported as $key => $val) {
            $id = "acl$val";
            $ul .= html::tag('li', null,
                $input->show('', array(
                    'name' => "acl[$val]", 'value' => $val, 'id' => $id))
                . html::label(array('for' => $id, 'title' => $this->gettext('longacl'.$val)),
                    $this->gettext('acl'.$val)));
        }

        $out = html::tag('ul', $attrib, $ul, html::$common_attrib);

        // Simple rights
        $ul = '';
        $attrib['id'] = 'simplerights';
        $items = array(
            'read' => 'lrs',
            'write' => 'wi',
            'delete' => $deleteright,
            'other' => preg_replace('/[lrswi'.$deleteright.']/', '', implode($supported)),
        );

        // give plugins the opportunity to adjust this list
        $data = $this->rc->plugins->exec_hook('acl_rights_simple',
            array('rights' => $items, 'folder' => $this->mbox, 'labels' => array(), 'titles' => array()));

        foreach ($data['rights'] as $key => $val) {
            $id = "acl$key";
            $ul .= html::tag('li', null,
                $input->show('', array(
                    'name' => "acl[$val]", 'value' => $val, 'id' => $id))
                . html::label(array('for' => $id, 'title' => $data['titles'][$key] ?: $this->gettext('longacl'.$key)),
                    $data['labels'][$key] ?: $this->gettext('acl'.$key)));
        }

        $out .= "\n" . html::tag('ul', $attrib, $ul, html::$common_attrib);

        $this->rc->output->set_env('acl_items', $data['rights']);

        return $out;
    }

    /**
     * Creates ACL rights form (user part)
     *
     * @param array $attrib Template object attributes
     *
     * @return string HTML Content
     */
    function templ_user($attrib)
    {
        // Create username input
        $attrib['name'] = 'acluser';

        $textfield = new html_inputfield($attrib);

        $fields['user'] = html::label(array('for' => $attrib['id']), $this->gettext('username'))
            . ' ' . $textfield->show();

        // Add special entries
        if (!empty($this->specials)) {
            foreach ($this->specials as $key) {
                $fields[$key] = html::label(array('for' => 'id'.$key), $this->gettext($key));
            }
        }

        $this->rc->output->set_env('acl_specials', $this->specials);

        // Create list with radio buttons
        if (count($fields) > 1) {
            $ul = '';
            $radio = new html_radiobutton(array('name' => 'usertype'));
            foreach ($fields as $key => $val) {
                $ul .= html::tag('li', null, $radio->show($key == 'user' ? 'user' : '',
                        array('value' => $key, 'id' => 'id'.$key))
                    . $val);
            }

            $out = html::tag('ul', array('id' => 'usertype', 'class' => $attrib['class']), $ul, html::$common_attrib);
        }
        // Display text input alone
        else {
            $out = $fields['user'];
        }

        return $out;
    }

    /**
     * Creates ACL rights table
     *
     * @param array $attrib Template object attributes
     *
     * @return string HTML Content
     */
    private function list_rights($attrib=array())
    {
        // Get ACL for the folder
        $acl = $this->rc->storage->get_acl($this->mbox);

        if (!is_array($acl)) {
            $acl = array();
        }

        // Keep special entries (anyone/anonymous) on top of the list
        if (!empty($this->specials) && !empty($acl)) {
            foreach ($this->specials as $key) {
                if (isset($acl[$key])) {
                    $acl_special[$key] = $acl[$key];
                    unset($acl[$key]);
                }
            }
        }

        // Sort the list by username
        uksort($acl, 'strnatcasecmp');

        if (!empty($acl_special)) {
            $acl = array_merge($acl_special, $acl);
        }

        // Get supported rights and build column names
        $supported = $this->rights_supported();

        // give plugins the opportunity to adjust this list
        $data = $this->rc->plugins->exec_hook('acl_rights_supported',
            array('rights' => $supported, 'folder' => $this->mbox, 'labels' => array()));
        $supported = $data['rights'];

        // depending on server capability either use 'te' or 'd' for deleting msgs
        $deleteright = implode(array_intersect(str_split('ted'), $supported));

        // Use advanced or simple (grouped) rights
        $advanced = $this->rc->config->get('acl_advanced_mode');

        if ($advanced) {
            $items = array();
            foreach ($supported as $sup) {
                $items[$sup] = $sup;
            }
        }
        else {
            $items = array(
                'read' => 'lrs',
                'write' => 'wi',
                'delete' => $deleteright,
                'other' => preg_replace('/[lrswi'.$deleteright.']/', '', implode($supported)),
            );

            // give plugins the opportunity to adjust this list
            $data = $this->rc->plugins->exec_hook('acl_rights_simple',
                array('rights' => $items, 'folder' => $this->mbox, 'labels' => array()));
            $items = $data['rights'];
        }

        // Create the table
        $attrib['noheader'] = true;
        $table = new html_table($attrib);

        // Create table header
        $table->add_header('user', $this->gettext('identifier'));
        foreach (array_keys($items) as $key) {
            $label = $data['labels'][$key] ?: $this->gettext('shortacl'.$key);
            $table->add_header(array('class' => 'acl'.$key, 'title' => $label), $label);
        }

        $js_table = array();
        foreach ($acl as $user => $rights) {
            if ($this->rc->storage->conn->user == $user) {
                continue;
            }

            // filter out virtual rights (c or d) the server may return
            $userrights = array_intersect($rights, $supported);
            $userid = rcube_utils::html_identifier($user);

            if (!empty($this->specials) && in_array($user, $this->specials)) {
                $user = $this->gettext($user);
            }

            $table->add_row(array('id' => 'rcmrow'.$userid));
            $table->add('user', html::a(array('id' => 'rcmlinkrow'.$userid), rcube::Q($user)));

            foreach ($items as $key => $right) {
                $in = $this->acl_compare($userrights, $right);
                switch ($in) {
                    case 2: $class = 'enabled'; break;
                    case 1: $class = 'partial'; break;
                    default: $class = 'disabled'; break;
                }
                $table->add('acl' . $key . ' ' . $class, '');
            }

            $js_table[$userid] = implode($userrights);
        }

        $this->rc->output->set_env('acl', $js_table);
        $this->rc->output->set_env('acl_advanced', $advanced);

        $out = $table->show();

        return $out;
    }

    /**
     * Handler for ACL update/create action
     */
    private function action_save()
    {
        $mbox  = trim(rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true)); // UTF7-IMAP
        $user  = trim(rcube_utils::get_input_value('_user', rcube_utils::INPUT_POST));
        $acl   = trim(rcube_utils::get_input_value('_acl', rcube_utils::INPUT_POST));
        $oldid = trim(rcube_utils::get_input_value('_old', rcube_utils::INPUT_POST));

        $acl    = array_intersect(str_split($acl), $this->rights_supported());
        $users  = $oldid ? array($user) : explode(',', $user);
        $result = 0;

        foreach ($users as $user) {
            $user   = trim($user);
            $prefix = $this->rc->config->get('acl_groups') ? $this->rc->config->get('acl_group_prefix') : '';

            if ($prefix && strpos($user, $prefix) === 0) {
                $username = $user;
            }
            else if (!empty($this->specials) && in_array($user, $this->specials)) {
                $username = $this->gettext($user);
            }
            else if (!empty($user)) {
                if (!strpos($user, '@') && ($realm = $this->get_realm())) {
                    $user .= '@' . rcube_utils::idn_to_ascii(preg_replace('/^@/', '', $realm));
                }
                $username = $user;
            }

            if (!$acl || !$user || !strlen($mbox)) {
                continue;
            }

            $user     = $this->mod_login($user);
            $username = $this->mod_login($username);

            if ($user != $_SESSION['username'] && $username != $_SESSION['username']) {
                if ($this->rc->storage->set_acl($mbox, $user, $acl)) {
                    $ret = array('id' => rcube_utils::html_identifier($user),
                         'username' => $username, 'acl' => implode($acl), 'old' => $oldid);
                    $this->rc->output->command('acl_update', $ret);
                    $result++;
                }
            }
        }

        if ($result) {
            $this->rc->output->show_message($oldid ? 'acl.updatesuccess' : 'acl.createsuccess', 'confirmation');
        }
        else {
            $this->rc->output->show_message($oldid ? 'acl.updateerror' : 'acl.createerror', 'error');
        }
    }

    /**
     * Handler for ACL delete action
     */
    private function action_delete()
    {
        $mbox = trim(rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST, true)); //UTF7-IMAP
        $user = trim(rcube_utils::get_input_value('_user', rcube_utils::INPUT_POST));

        $user = explode(',', $user);

        foreach ($user as $u) {
            $u = trim($u);
            if ($this->rc->storage->delete_acl($mbox, $u)) {
                $this->rc->output->command('acl_remove_row', rcube_utils::html_identifier($u));
            }
            else {
                $error = true;
            }
        }

        if (!$error) {
            $this->rc->output->show_message('acl.deletesuccess', 'confirmation');
        }
        else {
            $this->rc->output->show_message('acl.deleteerror', 'error');
        }
    }

    /**
     * Handler for ACL list update action (with display mode change)
     */
    private function action_list()
    {
        if (in_array('acl_advanced_mode', (array)$this->rc->config->get('dont_override'))) {
            return;
        }

        $this->mbox = trim(rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC, true)); // UTF7-IMAP
        $advanced   = trim(rcube_utils::get_input_value('_mode', rcube_utils::INPUT_GPC));
        $advanced   = $advanced == 'advanced';

        // Save state in user preferences
        $this->rc->user->save_prefs(array('acl_advanced_mode' => $advanced));

        $out = $this->list_rights();

        $out = preg_replace(array('/^<table[^>]+>/', '/<\/table>$/'), '', $out);

        $this->rc->output->command('acl_list_update', $out);
    }

    /**
     * Creates <UL> list with descriptive access rights
     *
     * @param array $rights MYRIGHTS result
     *
     * @return string HTML content
     */
    function acl2text($rights)
    {
        if (empty($rights)) {
            return '';
        }

        $supported = $this->rights_supported();
        $list      = array();
        $attrib    = array(
            'name' => 'rcmyrights',
            'style' => 'margin:0; padding:0 15px;',
        );

        foreach ($supported as $right) {
            if (in_array($right, $rights)) {
                $list[] = html::tag('li', null, rcube::Q($this->gettext('acl' . $right)));
            }
        }

        if (count($list) == count($supported))
            return rcube::Q($this->gettext('aclfull'));

        return html::tag('ul', $attrib, implode("\n", $list));
    }

    /**
     * Compares two ACLs (according to supported rights)
     *
     * @param array $acl1 ACL rights array (or string)
     * @param array $acl2 ACL rights array (or string)
     *
     * @param int Comparision result, 2 - full match, 1 - partial match, 0 - no match
     */
    function acl_compare($acl1, $acl2)
    {
        if (!is_array($acl1)) $acl1 = str_split($acl1);
        if (!is_array($acl2)) $acl2 = str_split($acl2);

        $rights = $this->rights_supported();

        $acl1 = array_intersect($acl1, $rights);
        $acl2 = array_intersect($acl2, $rights);
        $res  = array_intersect($acl1, $acl2);

        $cnt1 = count($res);
        $cnt2 = count($acl2);

        if ($cnt1 == $cnt2)
            return 2;
        else if ($cnt1)
            return 1;
        else
            return 0;
    }

    /**
     * Get list of supported access rights (according to RIGHTS capability)
     *
     * @return array List of supported access rights abbreviations
     */
    function rights_supported()
    {
        if ($this->supported !== null) {
            return $this->supported;
        }

        $capa = $this->rc->storage->get_capability('RIGHTS');

        if (is_array($capa)) {
            $rights = strtolower($capa[0]);
        }
        else {
            $rights = 'cd';
        }

        return $this->supported = str_split('lrswi' . $rights . 'pa');
    }

    /**
     * Username realm detection.
     *
     * @return string Username realm (domain)
     */
    private function get_realm()
    {
        // When user enters a username without domain part, realm
        // allows to add it to the username (and display correct username in the table)

        if (isset($_SESSION['acl_username_realm'])) {
            return $_SESSION['acl_username_realm'];
        }

        // find realm in username of logged user (?)
        list($name, $domain) = explode('@', $_SESSION['username']);

        // Use (always existent) ACL entry on the INBOX for the user to determine
        // whether or not the user ID in ACL entries need to be qualified and how
        // they would need to be qualified.
        if (empty($domain)) {
            $acl = $this->rc->storage->get_acl('INBOX');
            if (is_array($acl)) {
                $regexp = '/^' . preg_quote($_SESSION['username'], '/') . '@(.*)$/';
                foreach (array_keys($acl) as $name) {
                    if (preg_match($regexp, $name, $matches)) {
                        $domain = $matches[1];
                        break;
                    }
                }
            }
        }

        return $_SESSION['acl_username_realm'] = $domain;
    }

    /**
     * Initializes autocomplete LDAP backend
     */
    private function init_ldap()
    {
        if ($this->ldap) {
            return $this->ldap->ready;
        }

        // get LDAP config
        $config = $this->rc->config->get('acl_users_source');

        if (empty($config)) {
            return false;
        }

        // not an array, use configured ldap_public source
        if (!is_array($config)) {
            $ldap_config = (array) $this->rc->config->get('ldap_public');
            $config      = $ldap_config[$config];
        }

        $uid_field = $this->rc->config->get('acl_users_field', 'mail');
        $filter    = $this->rc->config->get('acl_users_filter');

        if (empty($uid_field) || empty($config)) {
            return false;
        }

        // get name attribute
        if (!empty($config['fieldmap'])) {
            $name_field = $config['fieldmap']['name'];
        }
        // ... no fieldmap, use the old method
        if (empty($name_field)) {
            $name_field = $config['name_field'];
        }

        // add UID field to fieldmap, so it will be returned in a record with name
        $config['fieldmap']['name'] = $name_field;
        $config['fieldmap']['uid']  = $uid_field;

        // search in UID and name fields
        // $name_field can be in a form of <field>:<modifier> (#1490591)
        $name_field = preg_replace('/:.*$/', '', $name_field);
        $search     = array_unique(array($name_field, $uid_field));

        $config['search_fields']   = $search;
        $config['required_fields'] = array($uid_field);

        // set search filter
        if ($filter) {
            $config['filter'] = $filter;
        }

        // disable vlv
        $config['vlv'] = false;

        // Initialize LDAP connection
        $this->ldap = new rcube_ldap($config,
            $this->rc->config->get('ldap_debug'),
            $this->rc->config->mail_domain($_SESSION['imap_host']));

        return $this->ldap->ready;
    }

    /**
     * Modify user login according to 'login_lc' setting
     */
    protected function mod_login($user)
    {
        $login_lc = $this->rc->config->get('login_lc');

        if ($login_lc === true || $login_lc == 2) {
            $user = mb_strtolower($user);
        }
        // lowercase domain name
        else if ($login_lc && strpos($user, '@')) {
            list($local, $domain) = explode('@', $user);
            $user = $local . '@' . mb_strtolower($domain);
        }

        return $user;
    }
}
