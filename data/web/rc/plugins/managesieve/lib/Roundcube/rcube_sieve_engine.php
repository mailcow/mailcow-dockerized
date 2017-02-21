<?php

/**
 * Managesieve (Sieve Filters) Engine
 *
 * Engine part of Managesieve plugin implementing UI and backend access.
 *
 * Copyright (C) 2008-2014, The Roundcube Dev Team
 * Copyright (C) 2011-2014, Kolab Systems AG
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

class rcube_sieve_engine
{
    protected $rc;
    protected $sieve;
    protected $errors;
    protected $form;
    protected $list;
    protected $tips    = array();
    protected $script  = array();
    protected $exts    = array();
    protected $active  = array();
    protected $headers = array(
        'subject' => 'Subject',
        'from'    => 'From',
        'to'      => 'To',
    );
    protected $addr_headers = array(
        // Required
        "from", "to", "cc", "bcc", "sender", "resent-from", "resent-to",
        // Additional (RFC 822 / RFC 2822)
        "reply-to", "resent-reply-to", "resent-sender", "resent-cc", "resent-bcc",
        // Non-standard (RFC 2076, draft-palme-mailext-headers-08.txt)
        "for-approval", "for-handling", "for-comment", "apparently-to", "errors-to",
        "delivered-to", "return-receipt-to", "x-admin", "read-receipt-to",
        "x-confirm-reading-to", "return-receipt-requested",
        "registered-mail-reply-requested-by", "mail-followup-to", "mail-reply-to",
        "abuse-reports-to", "x-complaints-to", "x-report-abuse-to",
        // Undocumented
        "x-beenthere",
    );
    protected $notify_methods = array(
        'mailto',
        // 'sms',
        // 'tel',
    );
    protected $notify_importance_options = array(
        3 => 'notifyimportancelow',
        2 => 'notifyimportancenormal',
        1 => 'notifyimportancehigh'
    );

    const VERSION  = '8.8';
    const PROGNAME = 'Roundcube (Managesieve)';
    const PORT     = 4190;


    /**
     * Class constructor
     */
    function __construct($plugin)
    {
        $this->rc     = rcube::get_instance();
        $this->plugin = $plugin;
    }

    /**
     * Loads configuration, initializes plugin (including sieve connection)
     */
    function start($mode = null)
    {
        // register UI objects
        $this->rc->output->add_handlers(array(
            'filterslist'      => array($this, 'filters_list'),
            'filtersetslist'   => array($this, 'filtersets_list'),
            'filterframe'      => array($this, 'filter_frame'),
            'filterform'       => array($this, 'filter_form'),
            'filtersetform'    => array($this, 'filterset_form'),
            'filterseteditraw' => array($this, 'filterset_editraw'),
        ));

        // connect to managesieve server
        $error = $this->connect($_SESSION['username'], $this->rc->decrypt($_SESSION['password']));

        // load current/active script
        if (!$error) {
            // Get list of scripts
            $list = $this->list_scripts();

            // reset current script when entering filters UI (#1489412)
            if ($this->rc->action == 'plugin.managesieve') {
                $this->rc->session->remove('managesieve_current');
            }

            if ($mode != 'vacation') {
                if (!empty($_GET['_set']) || !empty($_POST['_set'])) {
                    $script_name = rcube_utils::get_input_value('_set', rcube_utils::INPUT_GPC, true);
                }
                else if (!empty($_SESSION['managesieve_current'])) {
                    $script_name = $_SESSION['managesieve_current'];
                }
            }

            $error = $this->load_script($script_name);
        }

        // finally set script objects
        if ($error) {
            switch ($error) {
                case rcube_sieve::ERROR_CONNECTION:
                case rcube_sieve::ERROR_LOGIN:
                    $this->rc->output->show_message('managesieve.filterconnerror', 'error');
                    break;

                default:
                    $this->rc->output->show_message('managesieve.filterunknownerror', 'error');
                    break;
            }

            // reload interface in case of possible error when specified script wasn't found (#1489412)
            if ($script_name !== null && !empty($list) && !in_array($script_name, $list)) {
                $this->rc->output->command('reload', 500);
            }

            // to disable 'Add filter' button set env variable
            $this->rc->output->set_env('filterconnerror', true);
            $this->script = array();
        }
        else {
            $this->exts = $this->sieve->get_extensions();
            $this->init_script();
            $this->rc->output->set_env('currentset', $this->sieve->current);
            $_SESSION['managesieve_current'] = $this->sieve->current;
        }

        $this->rc->output->set_env('raw_sieve_editor', $this->rc->config->get('managesieve_raw_editor', true));

        return $error;
    }

    /**
     * Connect to configured managesieve server
     *
     * @param string $username User login
     * @param string $password User password
     *
     * @return int Connection status: 0 on success, >0 on failure
     */
    public function connect($username, $password)
    {
        // Get connection parameters
        $host = $this->rc->config->get('managesieve_host', 'localhost');
        $port = $this->rc->config->get('managesieve_port');
        $tls  = $this->rc->config->get('managesieve_usetls', false);

        $host = rcube_utils::parse_host($host);
        $host = rcube_utils::idn_to_ascii($host);

        // remove tls:// prefix, set TLS flag
        if (($host = preg_replace('|^tls://|i', '', $host, 1, $cnt)) && $cnt) {
            $tls = true;
        }

        if (empty($port)) {
            $port = getservbyname('sieve', 'tcp');
            if (empty($port)) {
                $port = self::PORT;
            }
        }

        $plugin = $this->rc->plugins->exec_hook('managesieve_connect', array(
            'user'      => $username,
            'password'  => $password,
            'host'      => $host,
            'port'      => $port,
            'usetls'    => $tls,
            'auth_type' => $this->rc->config->get('managesieve_auth_type'),
            'disabled'  => $this->rc->config->get('managesieve_disabled_extensions'),
            'debug'     => $this->rc->config->get('managesieve_debug', false),
            'auth_cid'  => $this->rc->config->get('managesieve_auth_cid'),
            'auth_pw'   => $this->rc->config->get('managesieve_auth_pw'),
            'socket_options' => $this->rc->config->get('managesieve_conn_options'),
        ));

        // Handle per-host socket options
        rcube_utils::parse_socket_options($plugin['socket_options'], $plugin['host']);

        // try to connect to managesieve server and to fetch the script
        $this->sieve = new rcube_sieve(
            $plugin['user'],
            $plugin['password'],
            $plugin['host'],
            $plugin['port'],
            $plugin['auth_type'],
            $plugin['usetls'],
            $plugin['disabled'],
            $plugin['debug'],
            $plugin['auth_cid'],
            $plugin['auth_pw'],
            $plugin['socket_options']
        );

        $error = $this->sieve->error();

        if ($error) {
            rcube::raise_error(array(
                    'code'    => 403,
                    'file'    => __FILE__,
                    'line'    => __LINE__,
                    'message' => "Unable to connect to managesieve on $host:$port"
                ), true, false);
        }

        return $error;
    }

    /**
     * Load specified (or active) script
     *
     * @param string $script_name Optional script name
     *
     * @return int Connection status: 0 on success, >0 on failure
     */
    protected function load_script($script_name = null)
    {
        // Get list of scripts
        $list = $this->list_scripts();

        if ($script_name === null || $script_name === '') {
            // get (first) active script
            if (!empty($this->active)) {
               $script_name = $this->active[0];
            }
            else if ($list) {
                $script_name = $list[0];
            }
            // create a new (initial) script
            else {
                // if script not exists build default script contents
                $script_file = $this->rc->config->get('managesieve_default');
                $script_name = $this->rc->config->get('managesieve_script_name');

                if (empty($script_name)) {
                    $script_name = 'roundcube';
                }

                if ($script_file && is_readable($script_file)) {
                    $content = file_get_contents($script_file);
                }

                // add script and set it active
                if ($this->sieve->save_script($script_name, $content)) {
                    $this->activate_script($script_name);
                    $this->list[] = $script_name;
                }
            }
        }

        if ($script_name) {
            $this->sieve->load($script_name);
        }

        return $this->sieve->error();
    }

    /**
     * User interface actions handler
     */
    function actions()
    {
        $error = $this->start();

        // Handle user requests
        if ($action = rcube_utils::get_input_value('_act', rcube_utils::INPUT_GPC)) {
            $fid = (int) rcube_utils::get_input_value('_fid', rcube_utils::INPUT_POST);

            if ($action == 'delete' && !$error) {
                if (isset($this->script[$fid])) {
                    if ($this->sieve->script->delete_rule($fid))
                        $result = $this->save_script();

                    if ($result === true) {
                        $this->rc->output->show_message('managesieve.filterdeleted', 'confirmation');
                        $this->rc->output->command('managesieve_updatelist', 'del', array('id' => $fid));
                    }
                    else {
                        $this->rc->output->show_message('managesieve.filterdeleteerror', 'error');
                    }
                }
            }
            else if ($action == 'move' && !$error) {
                if (isset($this->script[$fid])) {
                    $to   = (int) rcube_utils::get_input_value('_to', rcube_utils::INPUT_POST);
                    $rule = $this->script[$fid];

                    // remove rule
                    unset($this->script[$fid]);
                    $this->script = array_values($this->script);

                    // add at target position
                    if ($to >= count($this->script)) {
                        $this->script[] = $rule;
                    }
                    else {
                        $script = array();
                        foreach ($this->script as $idx => $r) {
                            if ($idx == $to)
                                $script[] = $rule;
                            $script[] = $r;
                        }
                        $this->script = $script;
                    }

                    $this->sieve->script->content = $this->script;
                    $result = $this->save_script();

                    if ($result === true) {
                        $result = $this->list_rules();

                        $this->rc->output->show_message('managesieve.moved', 'confirmation');
                        $this->rc->output->command('managesieve_updatelist', 'list',
                            array('list' => $result, 'clear' => true, 'set' => $to));
                    }
                    else {
                        $this->rc->output->show_message('managesieve.moveerror', 'error');
                    }
                }
            }
            else if ($action == 'act' && !$error) {
                if (isset($this->script[$fid])) {
                    $rule     = $this->script[$fid];
                    $disabled = !empty($rule['disabled']);
                    $rule['disabled'] = !$disabled;
                    $result = $this->sieve->script->update_rule($fid, $rule);

                    if ($result !== false)
                        $result = $this->save_script();

                    if ($result === true) {
                        if ($rule['disabled'])
                            $this->rc->output->show_message('managesieve.deactivated', 'confirmation');
                        else
                            $this->rc->output->show_message('managesieve.activated', 'confirmation');
                        $this->rc->output->command('managesieve_updatelist', 'update',
                            array('id' => $fid, 'disabled' => $rule['disabled']));
                    }
                    else {
                        if ($rule['disabled'])
                            $this->rc->output->show_message('managesieve.deactivateerror', 'error');
                        else
                            $this->rc->output->show_message('managesieve.activateerror', 'error');
                    }
                }
            }
            else if ($action == 'setact' && !$error) {
                $script_name = rcube_utils::get_input_value('_set', rcube_utils::INPUT_POST, true);
                $result = $this->activate_script($script_name);
                $kep14  = $this->rc->config->get('managesieve_kolab_master');

                if ($result === true) {
                    $this->rc->output->set_env('active_sets', $this->active);
                    $this->rc->output->show_message('managesieve.setactivated', 'confirmation');
                    $this->rc->output->command('managesieve_updatelist', 'setact',
                        array('name' => $script_name, 'active' => true, 'all' => !$kep14));
                }
                else {
                    $this->rc->output->show_message('managesieve.setactivateerror', 'error');
                }
            }
            else if ($action == 'deact' && !$error) {
                $script_name = rcube_utils::get_input_value('_set', rcube_utils::INPUT_POST, true);
                $result = $this->deactivate_script($script_name);

                if ($result === true) {
                    $this->rc->output->set_env('active_sets', $this->active);
                    $this->rc->output->show_message('managesieve.setdeactivated', 'confirmation');
                    $this->rc->output->command('managesieve_updatelist', 'setact',
                        array('name' => $script_name, 'active' => false));
                }
                else {
                    $this->rc->output->show_message('managesieve.setdeactivateerror', 'error');
                }
            }
            else if ($action == 'setdel' && !$error) {
                $script_name = rcube_utils::get_input_value('_set', rcube_utils::INPUT_POST, true);
                $result = $this->remove_script($script_name);

                if ($result === true) {
                    $this->rc->output->show_message('managesieve.setdeleted', 'confirmation');
                    $this->rc->output->command('managesieve_updatelist', 'setdel',
                        array('name' => $script_name));
                    $this->rc->session->remove('managesieve_current');
                }
                else {
                    $this->rc->output->show_message('managesieve.setdeleteerror', 'error');
                }
            }
            else if ($action == 'setget') {
                $this->rc->request_security_check(rcube_utils::INPUT_GET);

                $script_name = rcube_utils::get_input_value('_set', rcube_utils::INPUT_GPC, true);
                $script      = $this->sieve->get_script($script_name);

                if ($script === false) {
                    exit;
                }

                $browser = new rcube_browser;

                // send download headers
                header("Content-Type: application/octet-stream");
                header("Content-Length: ".strlen($script));

                if ($browser->ie) {
                    header("Content-Type: application/force-download");
                    $filename = rawurlencode($script_name);
                }
                else {
                    $filename = addcslashes($script_name, '\\"');
                }

                header("Content-Disposition: attachment; filename=\"$filename.txt\"");
                echo $script;
                exit;
            }
            else if ($action == 'list') {
                $result = $this->list_rules();

                $this->rc->output->command('managesieve_updatelist', 'list', array('list' => $result));
            }
            else if ($action == 'ruleadd') {
                $rid = rcube_utils::get_input_value('_rid', rcube_utils::INPUT_POST);
                $id = $this->genid();
                $content = $this->rule_div($fid, $id, false);

                $this->rc->output->command('managesieve_rulefill', $content, $id, $rid);
            }
            else if ($action == 'actionadd') {
                $aid = rcube_utils::get_input_value('_aid', rcube_utils::INPUT_POST);
                $id = $this->genid();
                $content = $this->action_div($fid, $id, false);

                $this->rc->output->command('managesieve_actionfill', $content, $id, $aid);
            }
            else if ($action == 'addresses') {
                $aid = rcube_utils::get_input_value('_aid', rcube_utils::INPUT_POST);

                $this->rc->output->command('managesieve_vacation_addresses_update', $aid, $this->user_emails());
            }

            $this->rc->output->send();
        }
        else if ($this->rc->task == 'mail') {
            // Initialize the form
            $rules = rcube_utils::get_input_value('r', rcube_utils::INPUT_GET);
            if (!empty($rules)) {
                $i = 0;
                foreach ($rules as $rule) {
                    list($header, $value) = explode(':', $rule, 2);
                    $tests[$i] = array(
                        'type' => 'contains',
                        'test' => 'header',
                        'arg1' => $header,
                        'arg2' => $value,
                    );
                    $i++;
                }

                $this->form = array(
                    'join'  => count($tests) > 1 ? 'allof' : 'anyof',
                    'name'  => '',
                    'tests' => $tests,
                    'actions' => array(
                        0 => array('type' => 'fileinto'),
                        1 => array('type' => 'stop'),
                    ),
                );
            }
        }

        $this->send();
    }

    function saveraw()
    {
        // Init plugin and handle managesieve connection
        $error = $this->start();

        $script_name = rcube_utils::get_input_value('_set', rcube_utils::INPUT_POST);

        $result = $this->sieve->save_script($script_name, $_POST['rawsetcontent']);

        if ($result === false) {
            $this->rc->output->show_message('managesieve.filtersaveerror', 'error');
            $errorLines = $this->sieve->get_error_lines();
            if (sizeof($errorLines) > 0) {
                $this->rc->output->set_env("sieve_errors", $errorLines);
            }
        }
        else {
            $this->rc->output->show_message('managesieve.setupdated', 'confirmation');
            $this->rc->output->command('parent.managesieve_updatelist', 'refresh');
        }

        $this->send();
    }

    function save()
    {
        // Init plugin and handle managesieve connection
        $error = $this->start();

        // get request size limits (#1488648)
        $max_post = max(array(
            ini_get('max_input_vars'),
            ini_get('suhosin.request.max_vars'),
            ini_get('suhosin.post.max_vars'),
        ));
        $max_depth = max(array(
            ini_get('suhosin.request.max_array_depth'),
            ini_get('suhosin.post.max_array_depth'),
        ));

        // check request size limit
        if ($max_post && count($_POST, COUNT_RECURSIVE) >= $max_post) {
            rcube::raise_error(array(
                'code' => 500, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Request size limit exceeded (one of max_input_vars/suhosin.request.max_vars/suhosin.post.max_vars)"
                ), true, false);
            $this->rc->output->show_message('managesieve.filtersaveerror', 'error');
        }
        // check request depth limits
        else if ($max_depth && count($_POST['_header']) > $max_depth) {
            rcube::raise_error(array(
                'code' => 500, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Request size limit exceeded (one of suhosin.request.max_array_depth/suhosin.post.max_array_depth)"
                ), true, false);
            $this->rc->output->show_message('managesieve.filtersaveerror', 'error');
        }
        // filters set add action
        else if (!empty($_POST['_newset'])) {
            $name       = rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true);
            $copy       = rcube_utils::get_input_value('_copy', rcube_utils::INPUT_POST, true);
            $from       = rcube_utils::get_input_value('_from', rcube_utils::INPUT_POST);
            $exceptions = $this->rc->config->get('managesieve_filename_exceptions');
            $kolab      = $this->rc->config->get('managesieve_kolab_master');
            $name_uc    = mb_strtolower($name);
            $list       = $this->list_scripts();

            if (!$name) {
                $this->errors['name'] = $this->plugin->gettext('cannotbeempty');
            }
            else if (mb_strlen($name) > 128) {
                $this->errors['name'] = $this->plugin->gettext('nametoolong');
            }
            else if (!empty($exceptions) && in_array($name, (array)$exceptions)) {
                $this->errors['name'] = $this->plugin->gettext('namereserved');
            }
            else if (!empty($kolab) && in_array($name_uc, array('MASTER', 'USER', 'MANAGEMENT'))) {
                $this->errors['name'] = $this->plugin->gettext('namereserved');
            }
            else if (in_array($name, $list)) {
                $this->errors['name'] = $this->plugin->gettext('setexist');
            }
            else if ($from == 'file') {
                // from file
                if (is_uploaded_file($_FILES['_file']['tmp_name'])) {
                    $file = file_get_contents($_FILES['_file']['tmp_name']);
                    $file = preg_replace('/\r/', '', $file);
                    // for security don't save script directly
                    // check syntax before, like this...
                    $this->sieve->load_script($file);
                    if (!$this->save_script($name)) {
                        $this->errors['file'] = $this->plugin->gettext('setcreateerror');
                    }
                }
                else {  // upload failed
                    $err = $_FILES['_file']['error'];

                    if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
                        $msg = $this->rc->gettext(array('name' => 'filesizeerror',
                            'vars' => array('size' =>
                                $this->rc->show_bytes(rcube_utils::max_upload_size()))));
                    }
                    else {
                        $this->errors['file'] = $this->plugin->gettext('fileuploaderror');
                    }
                }
            }
            else if (!$this->sieve->copy($name, $from == 'set' ? $copy : '')) {
                $error = 'managesieve.setcreateerror';
            }

            if (!$error && empty($this->errors)) {
                // Find position of the new script on the list
                $list[] = $name;
                asort($list, SORT_LOCALE_STRING);
                $list  = array_values($list);
                $index = array_search($name, $list);

                $this->rc->output->show_message('managesieve.setcreated', 'confirmation');
                $this->rc->output->command('parent.managesieve_updatelist', 'setadd',
                    array('name' => $name, 'index' => $index));
            }
            else if ($msg) {
                $this->rc->output->command('display_message', $msg, 'error');
            }
            else if ($error) {
                $this->rc->output->show_message($error, 'error');
            }
        }
        // filter add/edit action
        else if (isset($_POST['_name'])) {
            $name = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true));
            $fid  = trim(rcube_utils::get_input_value('_fid', rcube_utils::INPUT_POST));
            $join = trim(rcube_utils::get_input_value('_join', rcube_utils::INPUT_POST));

            // and arrays
            $headers        = rcube_utils::get_input_value('_header', rcube_utils::INPUT_POST);
            $cust_headers   = rcube_utils::get_input_value('_custom_header', rcube_utils::INPUT_POST);
            $cust_vars      = rcube_utils::get_input_value('_custom_var', rcube_utils::INPUT_POST);
            $ops            = rcube_utils::get_input_value('_rule_op', rcube_utils::INPUT_POST);
            $sizeops        = rcube_utils::get_input_value('_rule_size_op', rcube_utils::INPUT_POST);
            $sizeitems      = rcube_utils::get_input_value('_rule_size_item', rcube_utils::INPUT_POST);
            $sizetargets    = rcube_utils::get_input_value('_rule_size_target', rcube_utils::INPUT_POST);
            $targets        = rcube_utils::get_input_value('_rule_target', rcube_utils::INPUT_POST, true);
            $mods           = rcube_utils::get_input_value('_rule_mod', rcube_utils::INPUT_POST);
            $mod_types      = rcube_utils::get_input_value('_rule_mod_type', rcube_utils::INPUT_POST);
            $body_trans     = rcube_utils::get_input_value('_rule_trans', rcube_utils::INPUT_POST);
            $body_types     = rcube_utils::get_input_value('_rule_trans_type', rcube_utils::INPUT_POST, true);
            $comparators    = rcube_utils::get_input_value('_rule_comp', rcube_utils::INPUT_POST);
            $indexes        = rcube_utils::get_input_value('_rule_index', rcube_utils::INPUT_POST);
            $lastindexes    = rcube_utils::get_input_value('_rule_index_last', rcube_utils::INPUT_POST);
            $dateheaders    = rcube_utils::get_input_value('_rule_date_header', rcube_utils::INPUT_POST);
            $dateparts      = rcube_utils::get_input_value('_rule_date_part', rcube_utils::INPUT_POST);
            $message        = rcube_utils::get_input_value('_rule_message', rcube_utils::INPUT_POST);
            $dup_handles    = rcube_utils::get_input_value('_rule_duplicate_handle', rcube_utils::INPUT_POST, true);
            $dup_headers    = rcube_utils::get_input_value('_rule_duplicate_header', rcube_utils::INPUT_POST, true);
            $dup_uniqueids  = rcube_utils::get_input_value('_rule_duplicate_uniqueid', rcube_utils::INPUT_POST, true);
            $dup_seconds    = rcube_utils::get_input_value('_rule_duplicate_seconds', rcube_utils::INPUT_POST);
            $dup_lasts      = rcube_utils::get_input_value('_rule_duplicate_last', rcube_utils::INPUT_POST);
            $act_types      = rcube_utils::get_input_value('_action_type', rcube_utils::INPUT_POST, true);
            $mailboxes      = rcube_utils::get_input_value('_action_mailbox', rcube_utils::INPUT_POST, true);
            $act_targets    = rcube_utils::get_input_value('_action_target', rcube_utils::INPUT_POST, true);
            $domain_targets = rcube_utils::get_input_value('_action_target_domain', rcube_utils::INPUT_POST);
            $area_targets   = rcube_utils::get_input_value('_action_target_area', rcube_utils::INPUT_POST, true);
            $reasons        = rcube_utils::get_input_value('_action_reason', rcube_utils::INPUT_POST, true);
            $addresses      = rcube_utils::get_input_value('_action_addresses', rcube_utils::INPUT_POST, true);
            $intervals      = rcube_utils::get_input_value('_action_interval', rcube_utils::INPUT_POST);
            $interval_types = rcube_utils::get_input_value('_action_interval_type', rcube_utils::INPUT_POST);
            $from           = rcube_utils::get_input_value('_action_from', rcube_utils::INPUT_POST);
            $subject        = rcube_utils::get_input_value('_action_subject', rcube_utils::INPUT_POST, true);
            $flags          = rcube_utils::get_input_value('_action_flags', rcube_utils::INPUT_POST);
            $varnames       = rcube_utils::get_input_value('_action_varname', rcube_utils::INPUT_POST);
            $varvalues      = rcube_utils::get_input_value('_action_varvalue', rcube_utils::INPUT_POST);
            $varmods        = rcube_utils::get_input_value('_action_varmods', rcube_utils::INPUT_POST);
            $notifymethods  = rcube_utils::get_input_value('_action_notifymethod', rcube_utils::INPUT_POST);
            $notifytargets  = rcube_utils::get_input_value('_action_notifytarget', rcube_utils::INPUT_POST, true);
            $notifyoptions  = rcube_utils::get_input_value('_action_notifyoption', rcube_utils::INPUT_POST, true);
            $notifymessages = rcube_utils::get_input_value('_action_notifymessage', rcube_utils::INPUT_POST, true);
            $notifyfrom     = rcube_utils::get_input_value('_action_notifyfrom', rcube_utils::INPUT_POST);
            $notifyimp      = rcube_utils::get_input_value('_action_notifyimportance', rcube_utils::INPUT_POST);

            // we need a "hack" for radiobuttons
            foreach ($sizeitems as $item)
                $items[] = $item;

            $this->form['disabled'] = !empty($_POST['_disabled']);
            $this->form['join']     = $join == 'allof';
            $this->form['name']     = $name;
            $this->form['tests']    = array();
            $this->form['actions']  = array();

            if ($name == '')
                $this->errors['name'] = $this->plugin->gettext('cannotbeempty');
            else {
                foreach($this->script as $idx => $rule)
                    if($rule['name'] == $name && $idx != $fid) {
                        $this->errors['name'] = $this->plugin->gettext('ruleexist');
                        break;
                    }
            }

            $i = 0;
            // rules
            if ($join == 'any') {
                $this->form['tests'][0]['test'] = 'true';
            }
            else {
                foreach ($headers as $idx => $header) {
                    // targets are indexed differently (assume form order)
                    $target     = $this->strip_value(array_shift($targets), true);
                    $header     = $this->strip_value($header);
                    $operator   = $this->strip_value($ops[$idx]);
                    $comparator = $this->strip_value($comparators[$idx]);

                    if ($header == 'size') {
                        $sizeop     = $this->strip_value($sizeops[$idx]);
                        $sizeitem   = $this->strip_value($items[$idx]);
                        $sizetarget = $this->strip_value($sizetargets[$idx]);

                        $this->form['tests'][$i]['test'] = 'size';
                        $this->form['tests'][$i]['type'] = $sizeop;
                        $this->form['tests'][$i]['arg']  = $sizetarget;

                        if ($sizetarget == '')
                            $this->errors['tests'][$i]['sizetarget'] = $this->plugin->gettext('cannotbeempty');
                        else if (!preg_match('/^[0-9]+(K|M|G)?$/i', $sizetarget.$sizeitem, $m)) {
                            $this->errors['tests'][$i]['sizetarget'] = $this->plugin->gettext('forbiddenchars');
                            $this->form['tests'][$i]['item'] = $sizeitem;
                        }
                        else
                            $this->form['tests'][$i]['arg'] .= $m[1];
                    }
                    else if ($header == 'currentdate') {
                        $datepart = $this->strip_value($dateparts[$idx]);

                        if (preg_match('/^not/', $operator))
                            $this->form['tests'][$i]['not'] = true;
                        $type = preg_replace('/^not/', '', $operator);

                        if ($type == 'exists') {
                            $this->errors['tests'][$i]['op'] = true;
                        }

                        $this->form['tests'][$i]['test'] = 'currentdate';
                        $this->form['tests'][$i]['type'] = $type;
                        $this->form['tests'][$i]['part'] = $datepart;
                        $this->form['tests'][$i]['arg']  = $target;

                        if ($type != 'exists') {
                            if (!count($target)) {
                                $this->errors['tests'][$i]['target'] = $this->plugin->gettext('cannotbeempty');
                            }
                            else if (strpos($type, 'count-') === 0) {
                                foreach ($target as $arg) {
                                    if (preg_match('/[^0-9]/', $arg)) {
                                        $this->errors['tests'][$i]['target'] = $this->plugin->gettext('forbiddenchars');
                                    }
                                }
                            }
                            else if (strpos($type, 'value-') === 0) {
                                // Some date/time formats do not support i;ascii-numeric comparator
                                if ($comparator == 'i;ascii-numeric' && in_array($datepart, array('date', 'time', 'iso8601', 'std11'))) {
                                    $comparator = '';
                                }
                            }

                            if (!preg_match('/^(regex|matches|count-)/', $type) && count($target)) {
                                foreach ($target as $arg) {
                                    if (!$this->validate_date_part($datepart, $arg)) {
                                        $this->errors['tests'][$i]['target'] = $this->plugin->gettext('invaliddateformat');
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    else if ($header == 'date') {
                        $datepart    = $this->strip_value($dateparts[$idx]);
                        $dateheader  = $this->strip_value($dateheaders[$idx]);
                        $index       = $this->strip_value($indexes[$idx]);
                        $indexlast   = $this->strip_value($lastindexes[$idx]);

                        if (preg_match('/^not/', $operator))
                            $this->form['tests'][$i]['not'] = true;
                        $type = preg_replace('/^not/', '', $operator);

                        if ($type == 'exists') {
                            $this->errors['tests'][$i]['op'] = true;
                        }

                        if (!empty($index) && $mod != 'envelope') {
                            $this->form['tests'][$i]['index'] = intval($index);
                            $this->form['tests'][$i]['last']  = !empty($indexlast);
                        }

                        if (empty($dateheader)) {
                            $dateheader = 'Date';
                        }
                        else if (!preg_match('/^[\x21-\x39\x41-\x7E]+$/i', $dateheader)) {
                            $this->errors['tests'][$i]['dateheader'] = $this->plugin->gettext('forbiddenchars');
                        }

                        $this->form['tests'][$i]['test']   = 'date';
                        $this->form['tests'][$i]['type']   = $type;
                        $this->form['tests'][$i]['part']   = $datepart;
                        $this->form['tests'][$i]['arg']    = $target;
                        $this->form['tests'][$i]['header'] = $dateheader;

                        if ($type != 'exists') {
                            if (!count($target)) {
                                $this->errors['tests'][$i]['target'] = $this->plugin->gettext('cannotbeempty');
                            }
                            else if (strpos($type, 'count-') === 0) {
                                foreach ($target as $arg) {
                                    if (preg_match('/[^0-9]/', $arg)) {
                                        $this->errors['tests'][$i]['target'] = $this->plugin->gettext('forbiddenchars');
                                    }
                                }
                            }
                            else if (strpos($type, 'value-') === 0) {
                                // Some date/time formats do not support i;ascii-numeric comparator
                                if ($comparator == 'i;ascii-numeric' && in_array($datepart, array('date', 'time', 'iso8601', 'std11'))) {
                                    $comparator = '';
                                }
                            }

                            if (count($target) && !preg_match('/^(regex|matches|count-)/', $type)) {
                                foreach ($target as $arg) {
                                    if (!$this->validate_date_part($datepart, $arg)) {
                                        $this->errors['tests'][$i]['target'] = $this->plugin->gettext('invaliddateformat');
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    else if ($header == 'body') {
                        $trans      = $this->strip_value($body_trans[$idx]);
                        $trans_type = $this->strip_value($body_types[$idx], true);

                        if (preg_match('/^not/', $operator))
                            $this->form['tests'][$i]['not'] = true;
                        $type = preg_replace('/^not/', '', $operator);

                        if ($type == 'exists') {
                            $this->errors['tests'][$i]['op'] = true;
                        }

                        $this->form['tests'][$i]['test'] = 'body';
                        $this->form['tests'][$i]['type'] = $type;
                        $this->form['tests'][$i]['arg']  = $target;

                        if (empty($target) && $type != 'exists') {
                            $this->errors['tests'][$i]['target'] = $this->plugin->gettext('cannotbeempty');
                        }
                        else if (preg_match('/^(value|count)-/', $type)) {
                            foreach ($target as $target_value) {
                                if (preg_match('/[^0-9]/', $target_value)) {
                                    $this->errors['tests'][$i]['target'] = $this->plugin->gettext('forbiddenchars');
                                }
                            }
                        }

                        $this->form['tests'][$i]['part'] = $trans;
                        if ($trans == 'content') {
                            $this->form['tests'][$i]['content'] = $trans_type;
                        }
                    }
                    else if ($header == 'message') {
                        $test = $this->strip_value($message[$idx]);

                        if (preg_match('/^not/', $test)) {
                            $this->form['tests'][$i]['not'] = true;
                            $test = substr($test, 3);
                        }

                        $this->form['tests'][$i]['test'] = $test;

                        if ($test == 'duplicate') {
                            $this->form['tests'][$i]['last']     = !empty($dup_lasts[$idx]);
                            $this->form['tests'][$i]['handle']   = trim($dup_handles[$idx]);
                            $this->form['tests'][$i]['header']   = trim($dup_headers[$idx]);
                            $this->form['tests'][$i]['uniqueid'] = trim($dup_uniqueids[$idx]);
                            $this->form['tests'][$i]['seconds']  = trim($dup_seconds[$idx]);

                            if ($this->form['tests'][$i]['seconds']
                                && preg_match('/[^0-9]/', $this->form['tests'][$i]['seconds'])
                            ) {
                                $this->errors['tests'][$i]['duplicate_seconds'] = $this->plugin->gettext('forbiddenchars');
                            }

                            if ($this->form['tests'][$i]['header'] && $this->form['tests'][$i]['uniqueid']) {
                                $this->errors['tests'][$i]['duplicate_uniqueid'] = $this->plugin->gettext('duplicate.conflict.err');
                            }
                        }
                    }
                    else {
                        $cust_header = $headers = $this->strip_value(array_shift($cust_headers));
                        $mod         = $this->strip_value($mods[$idx]);
                        $mod_type    = $this->strip_value($mod_types[$idx]);
                        $index       = $this->strip_value($indexes[$idx]);
                        $indexlast   = $this->strip_value($lastindexes[$idx]);

                        if ($header == 'string') {
                            $cust_var = $headers = $this->strip_value(array_shift($cust_vars));
                        }

                        if (preg_match('/^not/', $operator))
                            $this->form['tests'][$i]['not'] = true;
                        $type = preg_replace('/^not/', '', $operator);

                        if (!empty($index) && $mod != 'envelope') {
                            $this->form['tests'][$i]['index'] = intval($index);
                            $this->form['tests'][$i]['last']  = !empty($indexlast);
                        }

                        if ($header == '...' || $header == 'string') {
                            if (!count($headers))
                                $this->errors['tests'][$i]['header'] = $this->plugin->gettext('cannotbeempty');
                            else if ($header == '...') {
                                foreach ($headers as $hr) {
                                    // RFC2822: printable ASCII except colon
                                    if (!preg_match('/^[\x21-\x39\x41-\x7E]+$/i', $hr)) {
                                        $this->errors['tests'][$i]['header'] = $this->plugin->gettext('forbiddenchars');
                                    }
                                }
                            }

                            if (empty($this->errors['tests'][$i]['header']))
                                $cust_header = $cust_var = (is_array($headers) && count($headers) == 1) ? $headers[0] : $headers;
                        }

                        $test   = $header == 'string' ? 'string' : 'header';
                        $header = $header == 'string' ? $cust_var : $header;
                        $header = $header == '...' ? $cust_header : $header;

                        if (is_array($header)) {
                            foreach ($header as $h_index => $val) {
                                if (isset($this->headers[$val])) {
                                    $header[$h_index] = $this->headers[$val];
                                }
                            }
                        }

                        if ($type == 'exists') {
                            $this->form['tests'][$i]['test'] = 'exists';
                            $this->form['tests'][$i]['arg'] = $header;
                        }
                        else {
                            if ($mod == 'address' || $mod == 'envelope') {
                                $found = false;
                                if (empty($this->errors['tests'][$i]['header'])) {
                                    foreach ((array)$header as $hdr) {
                                        if (!in_array(strtolower(trim($hdr)), $this->addr_headers))
                                            $found = true;
                                    }
                                }
                                if (!$found)
                                    $test = $mod;
                            }

                            $this->form['tests'][$i]['type'] = $type;
                            $this->form['tests'][$i]['test'] = $test;
                            $this->form['tests'][$i]['arg1'] = $header;
                            $this->form['tests'][$i]['arg2'] = $target;

                            if (empty($target)) {
                                $this->errors['tests'][$i]['target'] = $this->plugin->gettext('cannotbeempty');
                            }
                            else if (preg_match('/^(value|count)-/', $type)) {
                                foreach ($target as $target_value) {
                                    if (preg_match('/[^0-9]/', $target_value)) {
                                        $this->errors['tests'][$i]['target'] = $this->plugin->gettext('forbiddenchars');
                                    }
                                }
                            }

                            if ($mod) {
                                $this->form['tests'][$i]['part'] = $mod_type;
                            }
                        }
                    }

                    if ($header != 'size' && $comparator) {
                        $this->form['tests'][$i]['comparator'] = $comparator;
                    }

                    $i++;
                }
            }

            $i = 0;
            // actions
            foreach ($act_types as $idx => $type) {
                $type = $this->strip_value($type);

                switch ($type) {
                case 'fileinto':
                case 'fileinto_copy':
                    $mailbox = $this->strip_value($mailboxes[$idx], false, false);
                    $this->form['actions'][$i]['target'] = $this->mod_mailbox($mailbox, 'in');

                    if ($type == 'fileinto_copy') {
                        $type = 'fileinto';
                        $this->form['actions'][$i]['copy'] = true;
                    }
                    break;

                case 'reject':
                case 'ereject':
                    $target = $this->strip_value($area_targets[$idx]);
                    $this->form['actions'][$i]['target'] = str_replace("\r\n", "\n", $target);

 //                 if ($target == '')
//                      $this->errors['actions'][$i]['targetarea'] = $this->plugin->gettext('cannotbeempty');
                    break;

                case 'redirect':
                case 'redirect_copy':
                    $target = $this->strip_value($act_targets[$idx]);
                    $domain = $this->strip_value($domain_targets[$idx]);

                    // force one of the configured domains
                    $domains = (array) $this->rc->config->get('managesieve_domains');
                    if (!empty($domains) && !empty($target)) {
                        if (!$domain || !in_array($domain, $domains)) {
                            $domain = $domains[0];
                        }

                        $target .= '@' . $domain;
                    }

                    $this->form['actions'][$i]['target'] = $target;

                    if ($target == '')
                        $this->errors['actions'][$i]['target'] = $this->plugin->gettext('cannotbeempty');
                    else if (!rcube_utils::check_email($target))
                        $this->errors['actions'][$i]['target'] = $this->plugin->gettext(!empty($domains) ? 'forbiddenchars' : 'noemailwarning');

                    if ($type == 'redirect_copy') {
                        $type = 'redirect';
                        $this->form['actions'][$i]['copy'] = true;
                    }

                    break;

                case 'addflag':
                case 'setflag':
                case 'removeflag':
                    $_target = array();
                    if (empty($flags[$idx])) {
                        $this->errors['actions'][$i]['target'] = $this->plugin->gettext('noflagset');
                    }
                    else {
                        foreach ($flags[$idx] as $flag) {
                            $_target[] = $this->strip_value($flag);
                        }
                    }
                    $this->form['actions'][$i]['target'] = $_target;
                    break;

                case 'vacation':
                    $reason        = $this->strip_value($reasons[$idx]);
                    $interval_type = $interval_types[$idx] == 'seconds' ? 'seconds' : 'days';

                    $this->form['actions'][$i]['reason']    = str_replace("\r\n", "\n", $reason);
                    $this->form['actions'][$i]['from']      = $from[$idx];
                    $this->form['actions'][$i]['subject']   = $subject[$idx];
                    $this->form['actions'][$i]['addresses'] = array_shift($addresses);
                    $this->form['actions'][$i][$interval_type] = $intervals[$idx];
// @TODO: vacation :mime, :handle

                    foreach ((array)$this->form['actions'][$i]['addresses'] as $aidx => $address) {
                        $this->form['actions'][$i]['addresses'][$aidx] = $address = trim($address);

                        if (empty($address)) {
                            unset($this->form['actions'][$i]['addresses'][$aidx]);
                        }
                        else if (!rcube_utils::check_email($address)) {
                            $this->errors['actions'][$i]['addresses'] = $this->plugin->gettext('noemailwarning');
                            break;
                        }
                    }

                    if (!empty($this->form['actions'][$i]['from']) && !rcube_utils::check_email($this->form['actions'][$i]['from'])) {
                        $this->errors['actions'][$i]['from'] = $this->plugin->gettext('noemailwarning');
                    }

                    if ($this->form['actions'][$i]['reason'] == '')
                        $this->errors['actions'][$i]['reason'] = $this->plugin->gettext('cannotbeempty');
                    if ($this->form['actions'][$i][$interval_type] && !preg_match('/^[0-9]+$/', $this->form['actions'][$i][$interval_type]))
                        $this->errors['actions'][$i]['interval'] = $this->plugin->gettext('forbiddenchars');
                    break;

                case 'set':
                    $this->form['actions'][$i]['name'] = $varnames[$idx];
                    $this->form['actions'][$i]['value'] = $varvalues[$idx];
                    foreach ((array)$varmods[$idx] as $v_m) {
                        $this->form['actions'][$i][$v_m] = true;
                    }

                    if (empty($varnames[$idx])) {
                        $this->errors['actions'][$i]['name'] = $this->plugin->gettext('cannotbeempty');
                    }
                    else if (!preg_match('/^[0-9a-z_]+$/i', $varnames[$idx])) {
                        $this->errors['actions'][$i]['name'] = $this->plugin->gettext('forbiddenchars');
                    }

                    if (!isset($varvalues[$idx]) || $varvalues[$idx] === '') {
                        $this->errors['actions'][$i]['value'] = $this->plugin->gettext('cannotbeempty');
                    }
                    break;

                case 'notify':
                    if (empty($notifymethods[$idx])) {
                        $this->errors['actions'][$i]['method'] = $this->plugin->gettext('cannotbeempty');
                    }
                    if (empty($notifytargets[$idx])) {
                        $this->errors['actions'][$i]['target'] = $this->plugin->gettext('cannotbeempty');
                    }
                    if (!empty($notifyfrom[$idx]) && !rcube_utils::check_email($notifyfrom[$idx])) {
                        $this->errors['actions'][$i]['from'] = $this->plugin->gettext('noemailwarning');
                    }

                    // skip empty options
                    foreach ((array)$notifyoptions[$idx] as $opt_idx => $opt) {
                        if (!strlen(trim($opt))) {
                            unset($notifyoptions[$idx][$opt_idx]);
                        }
                    }

                    $this->form['actions'][$i]['method']     = $notifymethods[$idx] . ':' . $notifytargets[$idx];
                    $this->form['actions'][$i]['options']    = $notifyoptions[$idx];
                    $this->form['actions'][$i]['message']    = $notifymessages[$idx];
                    $this->form['actions'][$i]['from']       = $notifyfrom[$idx];
                    $this->form['actions'][$i]['importance'] = $notifyimp[$idx];
                    break;
                }

                $this->form['actions'][$i]['type'] = $type;
                $i++;
            }

            if (!$this->errors && !$error) {
                // save the script
                if (!isset($this->script[$fid])) {
                    $fid = $this->sieve->script->add_rule($this->form);
                    $new = true;
                }
                else {
                    $fid = $this->sieve->script->update_rule($fid, $this->form);
                }

                if ($fid !== false) {
                    $save = $this->save_script();
                }

                if ($save && $fid !== false) {
                    $this->rc->output->show_message('managesieve.filtersaved', 'confirmation');
                    if ($this->rc->task != 'mail') {
                        $this->rc->output->command('parent.managesieve_updatelist',
                            isset($new) ? 'add' : 'update',
                            array(
                                'name' => $this->form['name'],
                                'id' => $fid,
                                'disabled' => $this->form['disabled']
                        ));
                    }
                    else {
                        $this->rc->output->command('managesieve_dialog_close');
                        $this->rc->output->send('iframe');
                    }
                }
                else {
                    $this->rc->output->show_message('managesieve.filtersaveerror', 'error');
                }
            }
            else {
                $this->rc->output->show_message('managesieve.filterformerror', 'warning');
            }
        }

        $this->send();
    }

    protected function send()
    {
        // Handle form action
        if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
            if (isset($_GET['_newset']) || isset($_POST['_newset'])) {
                $this->rc->output->send('managesieve.setedit');
            }
            else if (isset($_GET['_seteditraw']) || isset($_POST['_seteditraw'])) {
                $this->rc->output->send('managesieve.seteditraw');
            }
            else {
                $this->rc->output->send('managesieve.filteredit');
            }
        }
        else {
            $this->rc->output->set_pagetitle($this->plugin->gettext('filters'));
            $this->rc->output->send('managesieve.managesieve');
        }
    }

    // return the filters list as HTML table
    function filters_list($attrib)
    {
        // add id to message list table if not specified
        if (!strlen($attrib['id']))
            $attrib['id'] = 'rcmfilterslist';

        // define list of cols to be displayed
        $a_show_cols = array('name');

        $result = $this->list_rules();

        // create XHTML table
        $out = $this->rc->table_output($attrib, $result, $a_show_cols, 'id');

        // set client env
        $this->rc->output->add_gui_object('filterslist', $attrib['id']);
        $this->rc->output->include_script('list.js');

        // add some labels to client
        $this->rc->output->add_label('managesieve.filterdeleteconfirm');

        return $out;
    }

    // return the filters list as <SELECT>
    function filtersets_list($attrib, $no_env = false)
    {
        // add id to message list table if not specified
        if (!strlen($attrib['id'])) {
            $attrib['id'] = 'rcmfiltersetslist';
        }

        $list = $this->list_scripts();

        if ($list) {
            asort($list, SORT_LOCALE_STRING);
        }

        if (!empty($attrib['type']) && $attrib['type'] == 'list') {
            // define list of cols to be displayed
            $a_show_cols = array('name');

            if ($list) {
                foreach ($list as $idx => $set) {
                    $scripts['S'.$idx] = $set;
                    $result[] = array(
                        'name' => $set,
                        'id' => 'S'.$idx,
                        'class' => !in_array($set, $this->active) ? 'disabled' : '',
                    );
                }
            }

            // create XHTML table
            $out = $this->rc->table_output($attrib, $result, $a_show_cols, 'id');

            $this->rc->output->set_env('filtersets', $scripts);
            $this->rc->output->include_script('list.js');
        }
        else {
            $select = new html_select(array('name' => '_set', 'id' => $attrib['id'],
                'onchange' => $this->rc->task != 'mail' ? 'rcmail.managesieve_set()' : ''));

            if ($list) {
                foreach ($list as $set)
                    $select->add($set, $set);
            }

            $out = $select->show($this->sieve->current);
        }

        // set client env
        if (!$no_env) {
            $this->rc->output->add_gui_object('filtersetslist', $attrib['id']);
            $this->rc->output->add_label('managesieve.setdeleteconfirm');
        }

        return $out;
    }

    function filter_frame($attrib)
    {
        return $this->rc->output->frame($attrib, true);
    }

    function filterset_editraw($attrib)
    {
        $script_name = isset($_GET['_set']) ? $_GET['_set'] : $_POST['_set'];
        $script      = $this->sieve->get_script($script_name);
        $script_post = $_POST['rawsetcontent'];

        $out = '<form name="filtersetrawform" action="./" method="post" enctype="multipart/form-data">'."\n";

        $hiddenfields = new html_hiddenfield();
        $hiddenfields->add(array('name' => '_task',   'value' => $this->rc->task));
        $hiddenfields->add(array('name' => '_action', 'value' => 'plugin.managesieve-saveraw'));
        $hiddenfields->add(array('name' => '_set',    'value' => $script_name));
        $hiddenfields->add(array('name' => '_seteditraw', 'value' => 1));
        $hiddenfields->add(array('name' => '_framed', 'value' => ($_POST['_framed'] || $_GET['_framed'] ? 1 : 0)));

        $out .= $hiddenfields->show();

        $txtarea = new html_textarea(array(
                'id'   => 'rawfiltersettxt',
                'name' => 'rawsetcontent',
                'rows' => '15'
        ));

        $out .= $txtarea->show($script_post !== null ? $script_post : ($script !== false ? rtrim($script) : ''));

        $this->rc->output->add_gui_object('sievesetrawform', 'filtersetrawform');
        $this->plugin->include_stylesheet('codemirror/lib/codemirror.css');
        $this->plugin->include_script('codemirror/lib/codemirror.js');
        $this->plugin->include_script('codemirror/addon/selection/active-line.js');
        $this->plugin->include_script('codemirror/mode/sieve/sieve.js');

        if ($script === false) {
            $this->rc->output->show_message('managesieve.filterunknownerror', 'error');
        }

        return $out;
    }

    function filterset_form($attrib)
    {
        if (!$attrib['id'])
            $attrib['id'] = 'rcmfiltersetform';

        $out = '<form name="filtersetform" action="./" method="post" enctype="multipart/form-data">'."\n";

        $hiddenfields = new html_hiddenfield(array('name' => '_task', 'value' => $this->rc->task));
        $hiddenfields->add(array('name' => '_action', 'value' => 'plugin.managesieve-save'));
        $hiddenfields->add(array('name' => '_framed', 'value' => ($_POST['_framed'] || $_GET['_framed'] ? 1 : 0)));
        $hiddenfields->add(array('name' => '_newset', 'value' => 1));

        $out .= $hiddenfields->show();

        $name     = rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST);
        $copy     = rcube_utils::get_input_value('_copy', rcube_utils::INPUT_POST);
        $selected = rcube_utils::get_input_value('_from', rcube_utils::INPUT_POST);

        // filter set name input
        $input_name = new html_inputfield(array('name' => '_name', 'id' => '_name', 'size' => 30,
            'class' => ($this->errors['name'] ? 'error' : '')));

        $out .= sprintf('<label for="%s"><b>%s:</b></label> %s<br><br>',
            '_name', rcube::Q($this->plugin->gettext('filtersetname')), $input_name->show($name));

        $out .="\n<fieldset class=\"itemlist\"><legend>" . $this->plugin->gettext('filters') . ":</legend>\n";
        $out .= html::tag('input', array(
                'type'    => 'radio',
                'id'      => 'from_none',
                'name'    => '_from',
                'value'   => 'none',
                'checked' => !$selected || $selected == 'none'
            ));
        $out .= html::label('from_none', rcube::Q($this->plugin->gettext('none')));

        // filters set list
        $list   = $this->list_scripts();
        $select = new html_select(array('name' => '_copy', 'id' => '_copy'));

        if (is_array($list)) {
            asort($list, SORT_LOCALE_STRING);

            if (!$copy)
                $copy = $_SESSION['managesieve_current'];

            foreach ($list as $set) {
                $select->add($set, $set);
            }

            $out .= '<br>';
            $out .= html::tag('input', array(
                    'type'    => 'radio',
                    'id'      => 'from_set',
                    'name'    => '_from',
                    'value'   => 'set',
                    'checked' => $selected == 'set',
                ));
            $out .= html::label('from_set', rcube::Q($this->plugin->gettext('fromset')));
            $out .= $select->show($copy);
        }

        // script upload box
        $upload = new html_inputfield(array('name' => '_file', 'id' => '_file', 'size' => 30,
            'type' => 'file', 'class' => ($this->errors['file'] ? 'error' : '')));

        $out .= '<br>';
        $out .= html::tag('input', array(
                'type'    => 'radio',
                'id'      => 'from_file',
                'name'    => '_from',
                'value'   => 'file',
                'checked' => $selected == 'file',
            ));
        $out .= html::label('from_file', rcube::Q($this->plugin->gettext('fromfile')));
        $out .= $upload->show();
        $out .= '</fieldset>';

        $this->rc->output->add_gui_object('sieveform', 'filtersetform');

        if ($this->errors['name'])
            $this->add_tip('_name', $this->errors['name'], true);
        if ($this->errors['file'])
            $this->add_tip('_file', $this->errors['file'], true);

        $this->print_tips();

        return $out;
    }


    function filter_form($attrib)
    {
        if (!$attrib['id'])
            $attrib['id'] = 'rcmfilterform';

        $fid = rcube_utils::get_input_value('_fid', rcube_utils::INPUT_GPC);
        $scr = isset($this->form) ? $this->form : $this->script[$fid];

        $hiddenfields = new html_hiddenfield(array('name' => '_task', 'value' => $this->rc->task));
        $hiddenfields->add(array('name' => '_action', 'value' => 'plugin.managesieve-save'));
        $hiddenfields->add(array('name' => '_framed', 'value' => ($_POST['_framed'] || $_GET['_framed'] ? 1 : 0)));
        $hiddenfields->add(array('name' => '_fid', 'value' => $fid));

        $out = '<form name="filterform" action="./" method="post">'."\n";
        $out .= $hiddenfields->show();

        // 'any' flag
        if ((!isset($this->form) && empty($scr['tests']) && !empty($scr))
            || (sizeof($scr['tests']) == 1 && $scr['tests'][0]['test'] == 'true' && !$scr['tests'][0]['not'])
        ) {
            $any = true;
        }

        // filter name input
        $field_id = '_name';
        $input_name = new html_inputfield(array('name' => '_name', 'id' => $field_id, 'size' => 30,
            'class' => ($this->errors['name'] ? 'error' : '')));

        if ($this->errors['name'])
            $this->add_tip($field_id, $this->errors['name'], true);

        if (isset($scr))
            $input_name = $input_name->show($scr['name']);
        else
            $input_name = $input_name->show();

        $out .= sprintf("\n<label for=\"%s\"><b>%s:</b></label> %s\n",
            $field_id, rcube::Q($this->plugin->gettext('filtername')), $input_name);

        // filter set selector
        if ($this->rc->task == 'mail') {
            $out .= sprintf("\n&nbsp;<label for=\"%s\"><b>%s:</b></label> %s\n",
                $field_id, rcube::Q($this->plugin->gettext('filterset')),
                $this->filtersets_list(array('id' => 'sievescriptname'), true));
        }

        $out .= '<br><br><fieldset><legend>' . rcube::Q($this->plugin->gettext('messagesrules')) . "</legend>\n";

        // any, allof, anyof radio buttons
        $field_id = '_allof';
        $input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'allof',
            'onclick' => 'rule_join_radio(\'allof\')', 'class' => 'radio'));

        if (isset($scr) && !$any)
            $input_join = $input_join->show($scr['join'] ? 'allof' : '');
        else
            $input_join = $input_join->show();

        $out .= $input_join . html::label($field_id, rcube::Q($this->plugin->gettext('filterallof')));

        $field_id = '_anyof';
        $input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'anyof',
            'onclick' => 'rule_join_radio(\'anyof\')', 'class' => 'radio'));

        if (isset($scr) && !$any)
            $input_join = $input_join->show($scr['join'] ? '' : 'anyof');
        else
            $input_join = $input_join->show('anyof'); // default

        $out .= $input_join . html::label($field_id, rcube::Q($this->plugin->gettext('filteranyof')));

        $field_id = '_any';
        $input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'any',
            'onclick' => 'rule_join_radio(\'any\')', 'class' => 'radio'));

        $input_join = $input_join->show($any ? 'any' : '');

        $out .= $input_join . html::label($field_id, rcube::Q($this->plugin->gettext('filterany')));

        $rows_num = !empty($scr['tests']) ? sizeof($scr['tests']) : 1;

        $out .= '<div id="rules"'.($any ? ' style="display: none"' : '').'>';
        for ($x=0; $x<$rows_num; $x++)
            $out .= $this->rule_div($fid, $x);
        $out .= "</div>\n";

        $out .= "</fieldset>\n";

        // actions
        $out .= '<fieldset><legend>' . rcube::Q($this->plugin->gettext('messagesactions')) . "</legend>\n";

        $rows_num = isset($scr) ? sizeof($scr['actions']) : 1;

        $out .= '<div id="actions">';
        for ($x=0; $x<$rows_num; $x++)
            $out .= $this->action_div($fid, $x);
        $out .= "</div>\n";

        $out .= "</fieldset>\n";

        $this->print_tips();

        if ($scr['disabled']) {
            $this->rc->output->set_env('rule_disabled', true);
        }
        $this->rc->output->add_label(
            'managesieve.ruledeleteconfirm',
            'managesieve.actiondeleteconfirm'
        );
        $this->rc->output->add_gui_object('sieveform', 'filterform');

        return $out;
    }

    function rule_div($fid, $id, $div=true)
    {
        $rule     = isset($this->form) ? $this->form['tests'][$id] : $this->script[$fid]['tests'][$id];
        $rows_num = isset($this->form) ? sizeof($this->form['tests']) : sizeof($this->script[$fid]['tests']);

        // headers select
        $select_header = new html_select(array('name' => "_header[]", 'id' => 'header'.$id,
            'onchange' => 'rule_header_select(' .$id .')'));

        foreach ($this->headers as $index => $header) {
            $header = $this->rc->text_exists($index) ? $this->plugin->gettext($index) : $header;
            $select_header->add($header, $index);
        }
        $select_header->add($this->plugin->gettext('...'), '...');
        if (in_array('body', $this->exts)) {
            $select_header->add($this->plugin->gettext('body'), 'body');
        }
        $select_header->add($this->plugin->gettext('size'), 'size');
        if (in_array('date', $this->exts)) {
            $select_header->add($this->plugin->gettext('datetest'), 'date');
            $select_header->add($this->plugin->gettext('currdate'), 'currentdate');
        }
        if (in_array('variables', $this->exts)) {
            $select_header->add($this->plugin->gettext('string'), 'string');
        }
        if (in_array('duplicate', $this->exts)) {
            $select_header->add($this->plugin->gettext('message'), 'message');
        }

        if (isset($rule['test'])) {
            if (in_array($rule['test'], array('header', 'address', 'envelope'))) {
                if (is_array($rule['arg1']) && count($rule['arg1']) == 1) {
                    $rule['arg1'] = $rule['arg1'][0];
                }

                $matches = ($header = strtolower($rule['arg1'])) && isset($this->headers[$header]);
                $test    = $matches ? $header : '...';
            }
            else if ($rule['test'] == 'exists') {
                if (is_array($rule['arg']) && count($rule['arg']) == 1) {
                    $rule['arg'] = $rule['arg'][0];
                }

                $matches = ($header = strtolower($rule['arg'])) && isset($this->headers[$header]);
                $test    = $matches ? $header : '...';
            }
            else if (in_array($rule['test'], array('size', 'body', 'date', 'currentdate', 'string'))) {
                $test = $rule['test'];
            }
            else if (in_array($rule['test'], array('duplicate'))) {
                $test = 'message';
            }
            else if ($rule['test'] != 'true') {
                $test = '...';
            }
        }

        $aout = $select_header->show($test);

        // custom headers input
        if (isset($rule['test']) && in_array($rule['test'], array('header', 'address', 'envelope'))) {
            $custom = (array) $rule['arg1'];
            if (count($custom) == 1 && isset($this->headers[strtolower($custom[0])])) {
                unset($custom);
            }
        }
        else if (isset($rule['test']) && $rule['test'] == 'string') {
            $customv = (array) $rule['arg1'];
            if (count($customv) == 1 && isset($this->headers[strtolower($customv[0])])) {
                unset($customv);
            }
        }
        else if (isset($rule['test']) && $rule['test'] == 'exists') {
            $custom = (array) $rule['arg'];
            if (count($custom) == 1 && isset($this->headers[strtolower($custom[0])])) {
                unset($custom);
            }
        }

        // custom variable input
        $tout = $this->list_input($id, 'custom_header', $custom, isset($custom),
            $this->error_class($id, 'test', 'header', 'custom_header'), 15) . "\n";

        $tout .= $this->list_input($id, 'custom_var', $customv, isset($customv),
            $this->error_class($id, 'test', 'header', 'custom_var'), 15) . "\n";

        // matching type select (operator)
        $select_op = new html_select(array('name' => "_rule_op[]", 'id' => 'rule_op'.$id,
            'style' => 'display:' .(!in_array($rule['test'], array('size', 'duplicate')) ? 'inline' : 'none'),
            'class' => 'operator_selector',
            'onchange' => 'rule_op_select(this, '.$id.')'));
        $select_op->add(rcube::Q($this->plugin->gettext('filtercontains')), 'contains');
        $select_op->add(rcube::Q($this->plugin->gettext('filternotcontains')), 'notcontains');
        $select_op->add(rcube::Q($this->plugin->gettext('filteris')), 'is');
        $select_op->add(rcube::Q($this->plugin->gettext('filterisnot')), 'notis');
        $select_op->add(rcube::Q($this->plugin->gettext('filterexists')), 'exists');
        $select_op->add(rcube::Q($this->plugin->gettext('filternotexists')), 'notexists');
        $select_op->add(rcube::Q($this->plugin->gettext('filtermatches')), 'matches');
        $select_op->add(rcube::Q($this->plugin->gettext('filternotmatches')), 'notmatches');
        if (in_array('regex', $this->exts)) {
            $select_op->add(rcube::Q($this->plugin->gettext('filterregex')), 'regex');
            $select_op->add(rcube::Q($this->plugin->gettext('filternotregex')), 'notregex');
        }
        if (in_array('relational', $this->exts)) {
            $select_op->add(rcube::Q($this->plugin->gettext('countisgreaterthan')), 'count-gt');
            $select_op->add(rcube::Q($this->plugin->gettext('countisgreaterthanequal')), 'count-ge');
            $select_op->add(rcube::Q($this->plugin->gettext('countislessthan')), 'count-lt');
            $select_op->add(rcube::Q($this->plugin->gettext('countislessthanequal')), 'count-le');
            $select_op->add(rcube::Q($this->plugin->gettext('countequals')), 'count-eq');
            $select_op->add(rcube::Q($this->plugin->gettext('countnotequals')), 'count-ne');
            $select_op->add(rcube::Q($this->plugin->gettext('valueisgreaterthan')), 'value-gt');
            $select_op->add(rcube::Q($this->plugin->gettext('valueisgreaterthanequal')), 'value-ge');
            $select_op->add(rcube::Q($this->plugin->gettext('valueislessthan')), 'value-lt');
            $select_op->add(rcube::Q($this->plugin->gettext('valueislessthanequal')), 'value-le');
            $select_op->add(rcube::Q($this->plugin->gettext('valueequals')), 'value-eq');
            $select_op->add(rcube::Q($this->plugin->gettext('valuenotequals')), 'value-ne');
        }

        $test   = self::rule_test($rule);
        $target = '';

        // target(s) input
        if (in_array($rule['test'], array('header', 'address', 'envelope','string'))) {
            $target = $rule['arg2'];
        }
        else if (in_array($rule['test'], array('body', 'date', 'currentdate'))) {
            $target = $rule['arg'];
        }
        else if ($rule['test'] == 'size') {
            if (preg_match('/^([0-9]+)(K|M|G)?$/', $rule['arg'], $matches)) {
                $sizetarget = $matches[1];
                $sizeitem   = $matches[2];
            }
            else {
                $sizetarget = $rule['arg'];
                $sizeitem   = $rule['item'];
            }
        }

        // (current)date part select
        if (in_array('date', $this->exts) || in_array('currentdate', $this->exts)) {
            $date_parts = array('date', 'iso8601', 'std11', 'julian', 'time',
                'year', 'month', 'day', 'hour', 'minute', 'second', 'weekday', 'zone');
            $select_dp = new html_select(array('name' => "_rule_date_part[]", 'id' => 'rule_date_part'.$id,
                'style' => in_array($rule['test'], array('currentdate', 'date')) && !preg_match('/^(notcount|count)-/', $test) ? '' : 'display:none',
                'class' => 'datepart_selector',
            ));

            foreach ($date_parts as $part) {
                $select_dp->add(rcube::Q($this->plugin->gettext($part)), $part);
            }

            $tout .= $select_dp->show($rule['test'] == 'currentdate' || $rule['test'] == 'date' ? $rule['part'] : '');
        }

        // message test select (e.g. duplicate)
        if (in_array('duplicate', $this->exts)) {
            $select_msg = new html_select(array('name' => "_rule_message[]", 'id' => 'rule_message'.$id,
                'style' => in_array($rule['test'], array('duplicate')) ? '' : 'display:none',
                'class' => 'message_selector',
            ));

            $select_msg->add(rcube::Q($this->plugin->gettext('duplicate')), 'duplicate');
            $select_msg->add(rcube::Q($this->plugin->gettext('notduplicate')), 'notduplicate');

            $tout .= $select_msg->show($test);
        }

        $tout .= $select_op->show($test);
        $tout .= $this->list_input($id, 'rule_target', $target,
            $rule['test'] != 'size' && $rule['test'] != 'exists' && $rule['test'] != 'duplicate',
            $this->error_class($id, 'test', 'target', 'rule_target')) . "\n";

        $select_size_op = new html_select(array('name' => "_rule_size_op[]", 'id' => 'rule_size_op'.$id));
        $select_size_op->add(rcube::Q($this->plugin->gettext('filterover')), 'over');
        $select_size_op->add(rcube::Q($this->plugin->gettext('filterunder')), 'under');

        $tout .= '<div id="rule_size' .$id. '" style="display:' . ($rule['test']=='size' ? 'inline' : 'none') .'">';
        $tout .= $select_size_op->show($rule['test']=='size' ? $rule['type'] : '');
        $tout .= html::tag('input', array(
                'type'  => 'text',
                'name'  => '_rule_size_target[]',
                'id'    => 'rule_size_i'.$id,
                'value' => $sizetarget,
                'size'  => 10,
                'class' => $this->error_class($id, 'test', 'sizetarget', 'rule_size_i'),
            ));
        foreach (array('', 'K', 'M', 'G') as $unit) {
            $tout .= html::label(null, html::tag('input', array(
                    'type'    => 'radio',
                    'name'    => '_rule_size_item['.$id.']',
                    'value'   => $unit,
                    'checked' => $sizeitem == $unit,
                    'class'   => 'radio',
                )) . $this->rc->gettext($unit . 'B'));
        }
        $tout .= '</div>';

        // Advanced modifiers (address, envelope)
        $select_mod = new html_select(array('name' => "_rule_mod[]", 'id' => 'rule_mod_op'.$id,
            'onchange' => 'rule_mod_select(' .$id .')'));
        $select_mod->add(rcube::Q($this->plugin->gettext('none')), '');
        $select_mod->add(rcube::Q($this->plugin->gettext('address')), 'address');
        if (in_array('envelope', $this->exts)) {
            $select_mod->add(rcube::Q($this->plugin->gettext('envelope')), 'envelope');
        }

        $select_type = new html_select(array('name' => "_rule_mod_type[]", 'id' => 'rule_mod_type'.$id));
        $select_type->add(rcube::Q($this->plugin->gettext('allparts')), 'all');
        $select_type->add(rcube::Q($this->plugin->gettext('domain')), 'domain');
        $select_type->add(rcube::Q($this->plugin->gettext('localpart')), 'localpart');
        if (in_array('subaddress', $this->exts)) {
            $select_type->add(rcube::Q($this->plugin->gettext('user')), 'user');
            $select_type->add(rcube::Q($this->plugin->gettext('detail')), 'detail');
        }

        $need_mod = !in_array($rule['test'], array('size', 'body', 'date', 'currentdate', 'duplicate', 'string'));
        $mout = '<div id="rule_mod' .$id. '" class="adv"' . (!$need_mod ? ' style="display:none"' : '') . '>';
        $mout .= ' <span class="label">' . rcube::Q($this->plugin->gettext('modifier')) . ' </span>';
        $mout .= $select_mod->show($rule['test']);
        $mout .= ' <span id="rule_mod_type' . $id . '"';
        $mout .= ' style="display:' . (in_array($rule['test'], array('address', 'envelope')) ? 'inline' : 'none') .'">';
        $mout .= rcube::Q($this->plugin->gettext('modtype')) . ' ';
        $mout .= $select_type->show($rule['part']);
        $mout .= '</span>';
        $mout .= '</div>';

        // Advanced modifiers (body transformations)
        $select_mod = new html_select(array('name' => "_rule_trans[]", 'id' => 'rule_trans_op'.$id,
            'onchange' => 'rule_trans_select(' .$id .')'));
        $select_mod->add(rcube::Q($this->plugin->gettext('text')), 'text');
        $select_mod->add(rcube::Q($this->plugin->gettext('undecoded')), 'raw');
        $select_mod->add(rcube::Q($this->plugin->gettext('contenttype')), 'content');

        $mout .= '<div id="rule_trans' .$id. '" class="adv"' . ($rule['test'] != 'body' ? ' style="display:none"' : '') . '>';
        $mout .= '<span class="label">' . rcube::Q($this->plugin->gettext('modifier')) . '</span>';
        $mout .= $select_mod->show($rule['part']);
        $mout .= html::tag('input', array(
                'type'  => 'text',
                'name'  => '_rule_trans_type[]',
                'id'    => 'rule_trans_type'.$id,
                'value' => is_array($rule['content']) ? implode(',', $rule['content']) : $rule['content'],
                'size'  => 20,
                'style' => $rule['part'] != 'content' ? 'display:none' : '',
                'class' => $this->error_class($id, 'test', 'part', 'rule_trans_type'),
            ));
        $mout .= '</div>';

        // Advanced modifiers (body transformations)
        $select_comp = new html_select(array('name' => "_rule_comp[]", 'id' => 'rule_comp_op'.$id));
        $select_comp->add(rcube::Q($this->plugin->gettext('default')), '');
        $select_comp->add(rcube::Q($this->plugin->gettext('octet')), 'i;octet');
        $select_comp->add(rcube::Q($this->plugin->gettext('asciicasemap')), 'i;ascii-casemap');
        if (in_array('comparator-i;ascii-numeric', $this->exts)) {
            $select_comp->add(rcube::Q($this->plugin->gettext('asciinumeric')), 'i;ascii-numeric');
        }

        // Comparators
        $need_comp = $rule['test'] != 'size' && $rule['test'] != 'duplicate';
        $mout .= '<div id="rule_comp' .$id. '" class="adv"' . (!$need_comp ? ' style="display:none"' : '') . '>';
        $mout .= '<span class="label">' . rcube::Q($this->plugin->gettext('comparator')) . '</span>';
        $mout .= $select_comp->show($rule['comparator']);
        $mout .= '</div>';

        // Date header
        if (in_array('date', $this->exts)) {
            $mout .= '<div id="rule_date_header_div' .$id. '" class="adv"'. ($rule['test'] != 'date' ? ' style="display:none"' : '') .'>';
            $mout .= '<span class="label">' . rcube::Q($this->plugin->gettext('dateheader')) . '</span>';
            $mout .= html::tag('input', array(
                    'type'  => 'text',
                    'name'  => '_rule_date_header[]',
                    'id'    => 'rule_date_header' . $id,
                    'value' => $rule['test'] == 'date' ? $rule['header'] : '',
                    'size'  => 15,
                    'class' => $this->error_class($id, 'test', 'dateheader', 'rule_date_header'),
                ));
            $mout .= '</div>';
        }

        // Index
        if (in_array('index', $this->exts)) {
            $need_index = in_array($rule['test'], array('header', ', address', 'date'));
            $mout .= '<div id="rule_index_div' .$id. '" class="adv"'. (!$need_index ? ' style="display:none"' : '') .'>';
            $mout .= '<span class="label">' . rcube::Q($this->plugin->gettext('index')) . '</span>';
            $mout .= html::tag('input', array(
                    'type'  => 'text',
                    'name'  => '_rule_index[]',
                    'id'    => 'rule_index' . $id,
                    'value' => $rule['index'] ? intval($rule['index']) : '',
                    'size'  => 3,
                    'class' => $this->error_class($id, 'test', 'index', 'rule_index'),
                ));
            $mout .= '&nbsp;' . html::tag('input', array(
                    'type'    => 'checkbox',
                    'name'    => '_rule_index_last[]',
                    'id'      => 'rule_index_last' . $id,
                    'value'   => 1,
                    'checked' => !empty($rule['last']),
                ))
                . html::label('rule_index_last' . $id, rcube::Q($this->plugin->gettext('indexlast')));
            $mout .= '</div>';
        }

        // Duplicate
        if (in_array('duplicate', $this->exts)) {
            $need_duplicate = $rule['test'] == 'duplicate';
            $mout .= '<div id="rule_duplicate_div' .$id. '" class="adv"'. (!$need_duplicate ? ' style="display:none"' : '') .'>';

            foreach (array('handle', 'header', 'uniqueid') as $unit) {
                $mout .= '<span class="label">' . rcube::Q($this->plugin->gettext('duplicate.handle')) . '</span>';
                $mout .= html::tag('input', array(
                        'type'  => 'text',
                        'name'  => '_rule_duplicate_' . $unit . '[]',
                        'id'    => 'rule_duplicate_' . $unit . $id,
                        'value' => $rule[$unit],
                        'size'  => 30,
                        'class' => $this->error_class($id, 'test', 'duplicate_' . $unit, 'rule_duplicate_' . $unit),
                    ));
                $mout .= '<br>';
            }

            $mout .= '<span class="label">' . rcube::Q($this->plugin->gettext('duplicate.seconds')) . '</span>';
            $mout .= html::tag('input', array(
                    'type'  => 'text',
                    'name'  => '_rule_duplicate_seconds[]',
                    'id'    => 'rule_duplicate_seconds' . $id,
                    'value' => $rule['seconds'],
                    'size'  => 6,
                    'class' => $this->error_class($id, 'test', 'duplicate_seconds', 'rule_duplicate_seconds'),
                ));
            $mout .= '&nbsp;' . html::tag('input', array(
                    'type'    => 'checkbox',
                    'name'    => '_rule_duplicate_last[' . $id . ']',
                    'id'      => 'rule_duplicate_last' . $id,
                    'value'   => 1,
                    'checked' => !empty($rule['last']),
                ));
            $mout .= html::label('rule_duplicate_last' . $id, rcube::Q($this->plugin->gettext('duplicate.last')));
            $mout .= '</div>';
        }

        // Build output table
        $out = $div ? '<div class="rulerow" id="rulerow' .$id .'">'."\n" : '';
        $out .= '<table><tr>';
        $out .= '<td class="advbutton">';
        $out .= '<a href="#" id="ruleadv' . $id .'" title="'. rcube::Q($this->plugin->gettext('advancedopts')). '"
            onclick="rule_adv_switch(' . $id .', this)" class="show">&nbsp;&nbsp;</a>';
        $out .= '</td>';
        $out .= '<td class="rowactions">' . $aout . '</td>';
        $out .= '<td class="rowtargets">' . $tout . "\n";
        $out .= '<div id="rule_advanced' .$id. '" style="display:none">' . $mout . '</div>';
        $out .= '</td>';

        // add/del buttons
        $out .= '<td class="rowbuttons">';
        $out .= '<a href="#" id="ruleadd' . $id .'" title="'. rcube::Q($this->plugin->gettext('add')). '"
            onclick="rcmail.managesieve_ruleadd(' . $id .')" class="button add"></a>';
        $out .= '<a href="#" id="ruledel' . $id .'" title="'. rcube::Q($this->plugin->gettext('del')). '"
            onclick="rcmail.managesieve_ruledel(' . $id .')" class="button del' . ($rows_num<2 ? ' disabled' : '') .'"></a>';
        $out .= '</td>';
        $out .= '</tr></table>';

        $out .= $div ? "</div>\n" : '';

        return $out;
    }

    private static function rule_test(&$rule)
    {
        // first modify value/count tests with 'not' keyword
        // we'll revert the meaning of operators
        if ($rule['not'] && preg_match('/^(count|value)-([gteqnl]{2})/', $rule['type'], $m)) {
            $rule['not'] = false;

            switch ($m[2]) {
            case 'gt': $rule['type'] = $m[1] . '-le'; break;
            case 'ge': $rule['type'] = $m[1] . '-lt'; break;
            case 'lt': $rule['type'] = $m[1] . '-ge'; break;
            case 'le': $rule['type'] = $m[1] . '-gt'; break;
            case 'eq': $rule['type'] = $m[1] . '-ne'; break;
            case 'ne': $rule['type'] = $m[1] . '-eq'; break;
            }
        }
        else if ($rule['not'] && $rule['test'] == 'size') {
            $rule['not']  = false;
            $rule['type'] = $rule['type'] == 'over' ? 'under' : 'over';
        }

        $set = array('header', 'address', 'envelope', 'body', 'date', 'currentdate', 'string');

        // build test string supported by select element
        if ($rule['size']) {
            $test = $rule['type'];
        }
        else if (in_array($rule['test'], $set)) {
            $test = ($rule['not'] ? 'not' : '') . ($rule['type'] ?: 'is');
        }
        else {
            $test = ($rule['not'] ? 'not' : '') . $rule['test'];
        }

        return $test;
    }

    function action_div($fid, $id, $div=true)
    {
        $action   = isset($this->form) ? $this->form['actions'][$id] : $this->script[$fid]['actions'][$id];
        $rows_num = isset($this->form) ? sizeof($this->form['actions']) : sizeof($this->script[$fid]['actions']);

        $out = $div ? '<div class="actionrow" id="actionrow' .$id .'">'."\n" : '';

        $out .= '<table><tr><td class="rowactions">';

        // action select
        $select_action = new html_select(array('name' => "_action_type[$id]", 'id' => 'action_type'.$id,
            'onchange' => 'action_type_select(' .$id .')'));
        if (in_array('fileinto', $this->exts))
            $select_action->add(rcube::Q($this->plugin->gettext('messagemoveto')), 'fileinto');
        if (in_array('fileinto', $this->exts) && in_array('copy', $this->exts))
            $select_action->add(rcube::Q($this->plugin->gettext('messagecopyto')), 'fileinto_copy');
        $select_action->add(rcube::Q($this->plugin->gettext('messageredirect')), 'redirect');
        if (in_array('copy', $this->exts))
            $select_action->add(rcube::Q($this->plugin->gettext('messagesendcopy')), 'redirect_copy');
        if (in_array('reject', $this->exts))
            $select_action->add(rcube::Q($this->plugin->gettext('messagediscard')), 'reject');
        else if (in_array('ereject', $this->exts))
            $select_action->add(rcube::Q($this->plugin->gettext('messagediscard')), 'ereject');
        if (in_array('vacation', $this->exts))
            $select_action->add(rcube::Q($this->plugin->gettext('messagereply')), 'vacation');
        $select_action->add(rcube::Q($this->plugin->gettext('messagedelete')), 'discard');
        if (in_array('imapflags', $this->exts) || in_array('imap4flags', $this->exts)) {
            $select_action->add(rcube::Q($this->plugin->gettext('setflags')), 'setflag');
            $select_action->add(rcube::Q($this->plugin->gettext('addflags')), 'addflag');
            $select_action->add(rcube::Q($this->plugin->gettext('removeflags')), 'removeflag');
        }
        if (in_array('variables', $this->exts)) {
            $select_action->add(rcube::Q($this->plugin->gettext('setvariable')), 'set');
        }
        if (in_array('enotify', $this->exts) || in_array('notify', $this->exts)) {
            $select_action->add(rcube::Q($this->plugin->gettext('notify')), 'notify');
        }
        $select_action->add(rcube::Q($this->plugin->gettext('messagekeep')), 'keep');
        $select_action->add(rcube::Q($this->plugin->gettext('rulestop')), 'stop');

        $select_type = $action['type'];
        if (in_array($action['type'], array('fileinto', 'redirect')) && $action['copy']) {
            $select_type .= '_copy';
        }

        $out .= $select_action->show($select_type);
        $out .= '</td>';

        // actions target inputs
        $out .= '<td class="rowtargets">';

        // force domain selection in redirect email input
        $domains = (array) $this->rc->config->get('managesieve_domains');
        if (!empty($domains)) {
            sort($domains);

            $domain_select = new html_select(array('name' => "_action_target_domain[$id]", 'id' => 'action_target_domain'.$id));
            $domain_select->add(array_combine($domains, $domains));

            if ($action['type'] == 'redirect') {
                $parts = explode('@', $action['target']);
                if (!empty($parts)) {
                    $action['domain'] = array_pop($parts);
                    $action['target'] = implode('@', $parts);
                }
            }
        }

        // redirect target
        $out .= '<span id="redirect_target' . $id . '" style="white-space:nowrap;'
            . ' display:' . ($action['type'] == 'redirect' ? 'inline' : 'none') . '">'
            . html::tag('input', array(
                'type'  => 'text',
                'name'  => '_action_target[' . $id . ']',
                'id'    => 'action_target' . $id,
                'value' => $action['type'] == 'redirect' ? $action['target'] : '',
                'size'  => !empty($domains) ? 20 : 35,
                'class' => $this->error_class($id, 'action', 'target', 'action_target'),
            ));
        $out .= !empty($domains) ? ' @ ' . $domain_select->show($action['domain']) : '';
        $out .= '</span>';

        // (e)reject target
        $out .= html::tag('textarea', array(
                'name'  => '_action_target_area[' . $id . ']',
                'id'    => 'action_target_area' . $id,
                'rows'  => 3,
                'cols'  => 35,
                'class' => $this->error_class($id, 'action', 'targetarea', 'action_target_area'),
                'style' => 'display:' . (in_array($action['type'], array('reject', 'ereject')) ? 'inline' : 'none'),
            ), (in_array($action['type'], array('reject', 'ereject')) ? rcube::Q($action['target'], 'strict', false) : ''));

        // vacation
        $vsec      = in_array('vacation-seconds', $this->exts);
        $auto_addr = $this->rc->config->get('managesieve_vacation_addresses_init');
        $from_addr = $this->rc->config->get('managesieve_vacation_from_init');

        if (empty($action)) {
            if ($auto_addr) {
                $action['addresses'] = $this->user_emails();
            }
            if ($from_addr) {
                $default_identity = $this->rc->user->list_emails(true);
                $action['from'] = $default_identity['email'];
            }
        }

        $out .= '<div id="action_vacation' .$id.'" style="display:' .($action['type']=='vacation' ? 'inline' : 'none') .'">';
        $out .= '<span class="label">'. rcube::Q($this->plugin->gettext('vacationreason')) .'</span><br>';
        $out .= html::tag('textarea', array(
                'name'  => '_action_reason[' . $id . ']',
                'id'   => 'action_reason' . $id,
                'rows'  => 3,
                'cols'  => 35,
                'class' => $this->error_class($id, 'action', 'reason', 'action_reason'),
            ), rcube::Q($action['reason'], 'strict', false));
        $out .= '<br><span class="label">' .rcube::Q($this->plugin->gettext('vacationsubject')) . '</span><br>';
        $out .= html::tag('input', array(
                'type'  => 'text',
                'name'  => '_action_subject[' . $id . ']',
                'id'    => 'action_subject' . $id,
                'value' => is_array($action['subject']) ? implode(', ', $action['subject']) : $action['subject'],
                'size'  => 35,
                'class' => $this->error_class($id, 'action', 'subject', 'action_subject'),
            ));
        $out .= '<br><span class="label">' .rcube::Q($this->plugin->gettext('vacationfrom')) . '</span><br>';
        $out .= html::tag('input', array(
                'type'  => 'text',
                'name'  => '_action_from[' . $id . ']',
                'id'    => 'action_from' . $id,
                'value' => $action['from'],
                'size'  => 35,
                'class' => $this->error_class($id, 'action', 'from', 'action_from'),
            ));
        $out .= '<br><span class="label">' .rcube::Q($this->plugin->gettext('vacationaddr')) . '</span><br>';
        $out .= $this->list_input($id, 'action_addresses', $action['addresses'], true,
                    $this->error_class($id, 'action', 'addresses', 'action_addresses'), 30)
            . html::a(array('href' => '#', 'onclick' => rcmail_output::JS_OBJECT_NAME . ".managesieve_vacation_addresses($id)"),
                rcube::Q($this->plugin->gettext('filladdresses')));
        $out .= '<br><span class="label">' . rcube::Q($this->plugin->gettext($vsec ? 'vacationinterval' : 'vacationdays')) . '</span><br>';
        $out .= html::tag('input', array(
                'type'  => 'text',
                'name'  => '_action_interval[' . $id . ']',
                'id'    => 'action_interval' . $id,
                'value' => rcube_sieve_vacation::vacation_interval($action),
                'size'  => 2,
                'class' => $this->error_class($id, 'action', 'interval', 'action_interval'),
            ));
        if ($vsec) {
            foreach (array('days', 'seconds') as $unit) {
                $out .= '&nbsp;' . html::label(null, html::tag('input', array(
                        'type'    => 'radio',
                        'name'    => '_action_interval_type[' . $id . ']',
                        'value'   => $unit,
                        'checked' => ($unit == 'seconds' && isset($action['seconds'])
                                        || $unit == 'deys' && !isset($action['seconds'])),
                        'class'   => 'radio',
                )) . $this->plugin->gettext($unit));
            }
        }
        $out .= '</div>';

        // flags
        $flags = array(
            'read'      => '\\Seen',
            'answered'  => '\\Answered',
            'flagged'   => '\\Flagged',
            'deleted'   => '\\Deleted',
            'draft'     => '\\Draft',
        );
        $flags_target = (array)$action['target'];

        $flout = '';
        foreach ($flags as $fidx => $flag) {
            $flout .= html::tag('input', array(
                    'type'    => 'checkbox',
                    'name'    => '_action_flags[' .$id .'][]',
                    'value'   => $flag,
                    'checked' => in_array_nocase($flag, $flags_target),
                ))
                . rcube::Q($this->plugin->gettext('flag'.$fidx)) .'<br>';
        }
        $out .= html::div(array(
                'id'    => 'action_flags' . $id,
                'style' => 'display:' . (preg_match('/^(set|add|remove)flag$/', $action['type']) ? 'inline' : 'none'),
                'class' => $this->error_class($id, 'action', 'flags', 'action_flags'),
            ), $flout);

        // set variable
        $set_modifiers = array(
            'lower',
            'upper',
            'lowerfirst',
            'upperfirst',
            'quotewildcard',
            'length'
        );

        $out .= '<div id="action_set' .$id.'" style="display:' .($action['type']=='set' ? 'inline' : 'none') .'">';
        foreach (array('name', 'value') as $unit) {
            $out .= '<span class="label">' .rcube::Q($this->plugin->gettext('setvar' . $unit)) . '</span><br>';
            $out .= html::tag('input', array(
                    'type'  => 'text',
                    'name'  => '_action_var' . $unit . '[' . $id . ']',
                    'id'    => 'action_var' . $unit . $id,
                    'value' => $action[$unit],
                    'size'  => 35,
                    'class' => $this->error_class($id, 'action', $unit, 'action_var' . $unit),
                ));
            $out .= '<br>';
        }
        $out .= '<span class="label">' .rcube::Q($this->plugin->gettext('setvarmodifiers')) . '</span>';
        foreach ($set_modifiers as $s_m) {
            $s_m_id = 'action_varmods' . $id . $s_m;
            $out .= '<br>' . html::tag('input', array(
                    'type'    => 'checkbox',
                    'name'    => '_action_varmods[' . $id . '][]',
                    'value'   => $s_m,
                    'id'      => $s_m_id,
                    'checked' => array_key_exists($s_m, (array)$action) && $action[$s_m],
                ))
                .rcube::Q($this->plugin->gettext('var' . $s_m));
        }
        $out .= '</div>';

        // notify
        $notify_methods     = (array) $this->rc->config->get('managesieve_notify_methods');
        $importance_options = $this->notify_importance_options;

        if (empty($notify_methods)) {
            $notify_methods = $this->notify_methods;
        }

        list($method, $target) = explode(':', $action['method'], 2);
        $method = strtolower($method);

        if ($method && !in_array($method, $notify_methods)) {
            $notify_methods[] = $method;
        }

        $select_method = new html_select(array(
            'name'  => "_action_notifymethod[$id]",
            'id'    => "_action_notifymethod$id",
            'class' => $this->error_class($id, 'action', 'method', 'action_notifymethod'),
        ));
        foreach ($notify_methods as $m_n) {
            $select_method->add(rcube::Q($this->rc->text_exists('managesieve.notifymethod'.$m_n) ? $this->plugin->gettext('managesieve.notifymethod'.$m_n) : $m_n), $m_n);
        }

        $select_importance = new html_select(array(
            'name'  => "_action_notifyimportance[$id]",
            'id'    => "_action_notifyimportance$id",
            'class' => $this->error_class($id, 'action', 'importance', 'action_notifyimportance')
        ));
        foreach ($importance_options as $io_v => $io_n) {
            $select_importance->add(rcube::Q($this->plugin->gettext($io_n)), $io_v);
        }

        // @TODO: nice UI for mailto: (other methods too) URI parameters
        $out .= '<div id="action_notify' .$id.'" style="display:' .($action['type'] == 'notify' ? 'inline' : 'none') .'">';
        $out .= '<span class="label">' .rcube::Q($this->plugin->gettext('notifytarget')) . '</span><br>';
        $out .= $select_method->show($method);
        $out .= html::tag('input', array(
                'type'  => 'text',
                'name'  => '_action_notifytarget[' . $id . ']',
                'id'    => 'action_notifytarget' . $id,
                'value' => $target,
                'size'  => 25,
                'class' => $this->error_class($id, 'action', 'target', 'action_notifytarget'),
            ));
        $out .= '<br><span class="label">'. rcube::Q($this->plugin->gettext('notifymessage')) .'</span><br>';
        $out .= html::tag('textarea', array(
                'name'  => '_action_notifymessage[' . $id . ']',
                'id'    => 'action_notifymessage' . $id,
                'rows'  => 3,
                'cols'  => 35,
                'class' => $this->error_class($id, 'action', 'message', 'action_notifymessage'),
            ), rcube::Q($action['message'], 'strict', false));
        if (in_array('enotify', $this->exts)) {
            $out .= '<br><span class="label">' .rcube::Q($this->plugin->gettext('notifyfrom')) . '</span><br>';
            $out .= html::tag('input', array(
                    'type'  => 'text',
                    'name'  => '_action_notifyfrom[' . $id . ']',
                    'id'    => 'action_notifyfrom' . $id,
                    'value' => $action['from'],
                    'size'  => 35,
                    'class' => $this->error_class($id, 'action', 'from', 'action_notifyfrom'),
                ));
        }
        $out .= '<br><span class="label">' . rcube::Q($this->plugin->gettext('notifyimportance')) . '</span><br>';
        $out .= $select_importance->show($action['importance'] ? (int) $action['importance'] : 2);
        $out .= '<div id="action_notifyoption_div' . $id  . '">'
            .'<span class="label">' . rcube::Q($this->plugin->gettext('notifyoptions')) . '</span><br>'
            .$this->list_input($id, 'action_notifyoption', (array)$action['options'], true,
                $this->error_class($id, 'action', 'options', 'action_notifyoption'), 30) . '</div>';
        $out .= '</div>';

        // mailbox select
        if ($action['type'] == 'fileinto') {
            $mailbox = $this->mod_mailbox($action['target'], 'out');
            // make sure non-existing (or unsubscribed) mailbox is listed (#1489956)
            $additional = array($mailbox);
        }
        else {
            $mailbox = '';
        }

        $select = $this->rc->folder_selector(array(
            'realnames'  => false,
            'maxlength'  => 100,
            'id'         => 'action_mailbox' . $id,
            'name'       => "_action_mailbox[$id]",
            'style'      => 'display:'.(empty($action['type']) || $action['type'] == 'fileinto' ? 'inline' : 'none'),
            'additional' => $additional,
        ));
        $out .= $select->show($mailbox);
        $out .= '</td>';

        // add/del buttons
        $out .= '<td class="rowbuttons">';
        $out .= '<a href="#" id="actionadd' . $id .'" title="'. rcube::Q($this->plugin->gettext('add')). '"
            onclick="rcmail.managesieve_actionadd(' . $id .')" class="button add"></a>';
        $out .= '<a href="#" id="actiondel' . $id .'" title="'. rcube::Q($this->plugin->gettext('del')). '"
            onclick="rcmail.managesieve_actiondel(' . $id .')" class="button del' . ($rows_num<2 ? ' disabled' : '') .'"></a>';
        $out .= '</td>';

        $out .= '</tr></table>';

        $out .= $div ? "</div>\n" : '';

        return $out;
    }

    protected function genid()
    {
        return preg_replace('/[^0-9]/', '', microtime(true));
    }

    protected function strip_value($str, $allow_html = false, $trim = true)
    {
        if (is_array($str)) {
            foreach ($str as $idx => $val) {
                $val = $this->strip_value($val, $allow_html, $trim);

                if ($val === '') {
                    unset($str[$idx]);
                }
            }

            return $str;
        }

        if (!$allow_html) {
            $str = strip_tags($str);
        }

        return $trim ? trim($str) : $str;
    }

    protected function error_class($id, $type, $target, $elem_prefix='')
    {
        // TODO: tooltips
        if (($type == 'test' && ($str = $this->errors['tests'][$id][$target])) ||
            ($type == 'action' && ($str = $this->errors['actions'][$id][$target]))
        ) {
            $this->add_tip($elem_prefix.$id, $str, true);
            return 'error';
        }

        return '';
    }

    protected function add_tip($id, $str, $error=false)
    {
        if ($error) {
            $str = html::span('sieve error', $str);
        }

        $this->tips[] = array($id, $str);
    }

    protected function print_tips()
    {
        if (empty($this->tips)) {
            return;
        }

        $script = rcmail_output::JS_OBJECT_NAME.'.managesieve_tip_register('.json_encode($this->tips).');';
        $this->rc->output->add_script($script, 'foot');
    }

    protected function list_input($id, $name, $value, $enabled, $class, $size=null)
    {
        $value = (array) $value;
        $value = array_map(array('rcube', 'Q'), $value);
        $value = implode("\n", $value);

        return html::tag('textarea', array(
                'data-type' => 'list',
                'data-size' => $size,
                'name'      => '_' . $name . '['. $id .']',
                'id'        => $name.$id,
                'disabled'  => !$enabled,
                'class'     => $class,
                'style'     => 'display:none',
            ), $value);
    }

    /**
     * Validate input for date part elements
     */
    protected function validate_date_part($type, $value)
    {
        // we do simple validation of date/part format
        switch ($type) {
            case 'date': // yyyy-mm-dd
                return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value);
            case 'iso8601':
                return preg_match('/^[0-9: .,ZWT+-]+$/', $value);
            case 'std11':
                return preg_match('/^((Sun|Mon|Tue|Wed|Thu|Fri|Sat),\s+)?[0-9]{1,2}\s+'
                    . '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+[0-9]{2,4}\s+'
                    . '[0-9]{2}:[0-9]{2}(:[0-9]{2})?\s+([+-]*[0-9]{4}|[A-Z]{1,3})$', $value);
            case 'julian':
                return preg_match('/^[0-9]+$/', $value);
            case 'time': // hh:mm:ss
                return preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/', $value);
            case 'year':
                return preg_match('/^[0-9]{4}$/', $value);
            case 'month':
                return preg_match('/^[0-9]{2}$/', $value) && $value > 0 && $value < 13;
            case 'day':
                return preg_match('/^[0-9]{2}$/', $value) && $value > 0 && $value < 32;
            case 'hour':
                return preg_match('/^[0-9]{2}$/', $value) && $value < 24;
            case 'minute':
                return preg_match('/^[0-9]{2}$/', $value) && $value < 60;
            case 'second':
                // According to RFC5260, seconds can be from 00 to 60
                return preg_match('/^[0-9]{2}$/', $value) && $value < 61;
            case 'weekday':
                return preg_match('/^[0-9]$/', $value) && $value < 7;
            case 'zone':
                return preg_match('/^[+-][0-9]{4}$/', $value);
        }
    }

    /**
     * Converts mailbox name from/to UTF7-IMAP from/to internal Sieve encoding
     * with delimiter replacement.
     *
     * @param string $mailbox Mailbox name
     * @param string $mode    Conversion direction ('in'|'out')
     *
     * @return string Mailbox name
     */
    protected function mod_mailbox($mailbox, $mode = 'out')
    {
        $delimiter         = $_SESSION['imap_delimiter'];
        $replace_delimiter = $this->rc->config->get('managesieve_replace_delimiter');
        $mbox_encoding     = $this->rc->config->get('managesieve_mbox_encoding', 'UTF7-IMAP');

        if ($mode == 'out') {
            $mailbox = rcube_charset::convert($mailbox, $mbox_encoding, 'UTF7-IMAP');
            if ($replace_delimiter && $replace_delimiter != $delimiter)
                $mailbox = str_replace($replace_delimiter, $delimiter, $mailbox);
        }
        else {
            $mailbox = rcube_charset::convert($mailbox, 'UTF7-IMAP', $mbox_encoding);
            if ($replace_delimiter && $replace_delimiter != $delimiter)
                $mailbox = str_replace($delimiter, $replace_delimiter, $mailbox);
        }

        return $mailbox;
    }

    /**
     * List sieve scripts
     *
     * @return array Scripts list
     */
    public function list_scripts()
    {
        if ($this->list !== null) {
            return $this->list;
        }

        $this->list = $this->sieve->get_scripts();

        // Handle active script(s) and list of scripts according to Kolab's KEP:14
        if ($this->rc->config->get('managesieve_kolab_master')) {
            // Skip protected names
            foreach ((array)$this->list as $idx => $name) {
                $_name = strtoupper($name);
                if ($_name == 'MASTER')
                    $master_script = $name;
                else if ($_name == 'MANAGEMENT')
                    $management_script = $name;
                else if($_name == 'USER')
                    $user_script = $name;
                else
                    continue;

                unset($this->list[$idx]);
            }

            // get active script(s), read USER script
            if ($user_script) {
                $extension = $this->rc->config->get('managesieve_filename_extension', '.sieve');
                $filename_regex = '/'.preg_quote($extension, '/').'$/';
                $_SESSION['managesieve_user_script'] = $user_script;

                $this->sieve->load($user_script);

                foreach ($this->sieve->script->as_array() as $rules) {
                    foreach ($rules['actions'] as $action) {
                        if ($action['type'] == 'include' && empty($action['global'])) {
                            $name = preg_replace($filename_regex, '', $action['target']);
                            // make sure the script exist
                            if (in_array($name, $this->list)) {
                                $this->active[] = $name;
                            }
                        }
                    }
                }
            }
            // create USER script if it doesn't exist
            else {
                $content = "# USER Management Script\n"
                    ."#\n"
                    ."# This script includes the various active sieve scripts\n"
                    ."# it is AUTOMATICALLY GENERATED. DO NOT EDIT MANUALLY!\n"
                    ."#\n"
                    ."# For more information, see http://wiki.kolab.org/KEP:14#USER\n"
                    ."#\n";
                if ($this->sieve->save_script('USER', $content)) {
                    $_SESSION['managesieve_user_script'] = 'USER';
                    if (empty($this->master_file))
                        $this->sieve->activate('USER');
                }
            }
        }
        else if (!empty($this->list)) {
            // Get active script name
            if ($active = $this->sieve->get_active()) {
                $this->active = array($active);
            }

            // Hide scripts from config
            $exceptions = $this->rc->config->get('managesieve_filename_exceptions');
            if (!empty($exceptions)) {
                $this->list = array_diff($this->list, (array)$exceptions);
            }
        }

        // reindex
        if (!empty($this->list)) {
            $this->list = array_values($this->list);
        }

        return $this->list;
    }

    /**
     * Removes sieve script
     *
     * @param string $name Script name
     *
     * @return bool True on success, False on failure
     */
    public function remove_script($name)
    {
        $result = $this->sieve->remove($name);

        // Kolab's KEP:14
        if ($result && $this->rc->config->get('managesieve_kolab_master')) {
            $this->deactivate_script($name);
        }

        return $result;
    }

    /**
     * Activates sieve script
     *
     * @param string $name Script name
     *
     * @return bool True on success, False on failure
     */
    public function activate_script($name)
    {
        // Kolab's KEP:14
        if ($this->rc->config->get('managesieve_kolab_master')) {
            $extension   = $this->rc->config->get('managesieve_filename_extension', '.sieve');
            $user_script = $_SESSION['managesieve_user_script'];

            // if the script is not active...
            if ($user_script && array_search($name, $this->active) === false) {
                // ...rewrite USER file adding appropriate include command
                if ($this->sieve->load($user_script)) {
                    $script = $this->sieve->script->as_array();
                    $list   = array();
                    $regexp = '/' . preg_quote($extension, '/') . '$/';

                    // Create new include entry
                    $rule = array(
                        'actions' => array(
                            0 => array(
                                'target'   => $name.$extension,
                                'type'     => 'include',
                                'personal' => true,
                    )));

                    // get all active scripts for sorting
                    foreach ($script as $rid => $rules) {
                        foreach ($rules['actions'] as $action) {
                            if ($action['type'] == 'include' && empty($action['global'])) {
                                $target = $extension ? preg_replace($regexp, '', $action['target']) : $action['target'];
                                $list[] = $target;
                            }
                        }
                    }
                    $list[] = $name;

                    // Sort and find current script position
                    asort($list, SORT_LOCALE_STRING);
                    $list = array_values($list);
                    $index = array_search($name, $list);

                    // add rule at the end of the script
                    if ($index === false || $index == count($list)-1) {
                        $this->sieve->script->add_rule($rule);
                    }
                    // add rule at index position
                    else {
                        $script2 = array();
                        foreach ($script as $rid => $rules) {
                            if ($rid == $index) {
                                $script2[] = $rule;
                            }
                            $script2[] = $rules;
                        }
                        $this->sieve->script->content = $script2;
                    }

                    $result = $this->sieve->save();
                    if ($result) {
                        $this->active[] = $name;
                    }
                }
            }
        }
        else {
            $result = $this->sieve->activate($name);
            if ($result)
                $this->active = array($name);
        }

        return $result;
    }

    /**
     * Deactivates sieve script
     *
     * @param string $name Script name
     *
     * @return bool True on success, False on failure
     */
    public function deactivate_script($name)
    {
        // Kolab's KEP:14
        if ($this->rc->config->get('managesieve_kolab_master')) {
            $extension   = $this->rc->config->get('managesieve_filename_extension', '.sieve');
            $user_script = $_SESSION['managesieve_user_script'];

            // if the script is active...
            if ($user_script && ($key = array_search($name, $this->active)) !== false) {
                // ...rewrite USER file removing appropriate include command
                if ($this->sieve->load($user_script)) {
                    $script = $this->sieve->script->as_array();
                    $name   = $name.$extension;

                    foreach ($script as $rid => $rules) {
                        foreach ($rules['actions'] as $action) {
                            if ($action['type'] == 'include' && empty($action['global'])
                                && $action['target'] == $name
                            ) {
                                break 2;
                            }
                        }
                    }

                    // Entry found
                    if ($rid < count($script)) {
                        $this->sieve->script->delete_rule($rid);
                        $result = $this->sieve->save();
                        if ($result) {
                            unset($this->active[$key]);
                        }
                    }
                }
            }
        }
        else {
            $result = $this->sieve->deactivate();
            if ($result)
                $this->active = array();
        }

        return $result;
    }

    /**
     * Saves current script (adding some variables)
     */
    public function save_script($name = null)
    {
        // Kolab's KEP:14
        if ($this->rc->config->get('managesieve_kolab_master')) {
            $this->sieve->script->set_var('EDITOR', self::PROGNAME);
            $this->sieve->script->set_var('EDITOR_VERSION', self::VERSION);
        }

        return $this->sieve->save($name);
    }

    /**
     * Returns list of rules from the current script
     *
     * @return array List of rules
     */
    public function list_rules()
    {
        $result = array();
        $i      = 1;

        foreach ($this->script as $idx => $filter) {
            if (empty($filter['actions'])) {
                continue;
            }
            $fname = $filter['name'] ?: "#$i";
            $result[] = array(
                'id'    => $idx,
                'name'  => $fname,
                'class' => $filter['disabled'] ? 'disabled' : '',
            );
            $i++;
        }

        return $result;
    }

    /**
     * Initializes internal script data
     */
    protected function init_script()
    {
        if (!$this->sieve->script) {
            return;
        }

        $this->script = $this->sieve->script->as_array();

        $headers    = array();
        $exceptions = array('date', 'currentdate', 'size', 'body');

        // find common headers used in script, will be added to the list
        // of available (predefined) headers (#1489271)
        foreach ($this->script as $rule) {
            foreach ((array) $rule['tests'] as $test) {
                if ($test['test'] == 'header') {
                    foreach ((array) $test['arg1'] as $header) {
                        $lc_header = strtolower($header);

                        // skip special names to not confuse UI
                        if (in_array($lc_header, $exceptions)) {
                            continue;
                        }

                        if (!isset($this->headers[$lc_header]) && !isset($headers[$lc_header])) {
                            $headers[$lc_header] = $header;
                        }
                    }
                }
            }
        }

        ksort($headers);

        $this->headers += $headers;
    }

    /**
     * Get all e-mail addresses of the user
     */
    protected function user_emails()
    {
        $addresses = $this->rc->user->list_emails();

        foreach ($addresses as $idx => $email) {
            $addresses[$idx] = $email['email'];
        }

        $addresses = array_unique($addresses);
        sort($addresses);

        return $addresses;
    }
}
