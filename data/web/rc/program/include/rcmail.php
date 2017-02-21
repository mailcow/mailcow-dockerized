<?php

/**
 +-----------------------------------------------------------------------+
 | program/include/rcmail.php                                            |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2014, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Application class providing core functions and holding              |
 |   instances of all 'global' objects like db- and imap-connections     |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Application class of Roundcube Webmail
 * implemented as singleton
 *
 * @package Webmail
 */
class rcmail extends rcube
{
    /**
     * Main tasks.
     *
     * @var array
     */
    static public $main_tasks = array('mail','settings','addressbook','login','logout','utils','dummy');

    /**
     * Current task.
     *
     * @var string
     */
    public $task;

    /**
     * Current action.
     *
     * @var string
     */
    public $action    = '';
    public $comm_path = './';
    public $filename  = '';

    private $address_books = array();
    private $action_map    = array();


    const ERROR_STORAGE          = -2;
    const ERROR_INVALID_REQUEST  = 1;
    const ERROR_INVALID_HOST     = 2;
    const ERROR_COOKIES_DISABLED = 3;
    const ERROR_RATE_LIMIT       = 4;


    /**
     * This implements the 'singleton' design pattern
     *
     * @param integer $mode Ignored rcube::get_instance() argument
     * @param string  $env  Environment name to run (e.g. live, dev, test)
     *
     * @return rcmail The one and only instance
     */
    static function get_instance($mode = 0, $env = '')
    {
        if (!self::$instance || !is_a(self::$instance, 'rcmail')) {
            self::$instance = new rcmail($env);
            // init AFTER object was linked with self::$instance
            self::$instance->startup();
        }

        return self::$instance;
    }

    /**
     * Initial startup function
     * to register session, create database and imap connections
     */
    protected function startup()
    {
        $this->init(self::INIT_WITH_DB | self::INIT_WITH_PLUGINS);

        // set filename if not index.php
        if (($basename = basename($_SERVER['SCRIPT_FILENAME'])) && $basename != 'index.php') {
            $this->filename = $basename;
        }

        // load all configured plugins
        $plugins          = (array) $this->config->get('plugins', array());
        $required_plugins = array('filesystem_attachments', 'jqueryui');
        $this->plugins->load_plugins($plugins, $required_plugins);

        // start session
        $this->session_init();

        // create user object
        $this->set_user(new rcube_user($_SESSION['user_id']));

        // set task and action properties
        $this->set_task(rcube_utils::get_input_value('_task', rcube_utils::INPUT_GPC));
        $this->action = asciiwords(rcube_utils::get_input_value('_action', rcube_utils::INPUT_GPC));

        // reset some session parameters when changing task
        if ($this->task != 'utils') {
            // we reset list page when switching to another task
            // but only to the main task interface - empty action (#1489076, #1490116)
            // this will prevent from unintentional page reset on cross-task requests
            if ($this->session && $_SESSION['task'] != $this->task && empty($this->action)) {
                $this->session->remove('page');

                // set current task to session
                $_SESSION['task'] = $this->task;
            }
        }

        // init output class (not in CLI mode)
        if (!empty($_REQUEST['_remote'])) {
            $GLOBALS['OUTPUT'] = $this->json_init();
        }
        else if ($_SERVER['REMOTE_ADDR']) {
            $GLOBALS['OUTPUT'] = $this->load_gui(!empty($_REQUEST['_framed']));
        }

        // run init method on all the plugins
        $this->plugins->init($this, $this->task);
    }

    /**
     * Setter for application task
     *
     * @param string $task Task to set
     */
    public function set_task($task)
    {
        if (php_sapi_name() == 'cli') {
            $task = 'cli';
        }
        else if (!$this->user || !$this->user->ID) {
            $task = 'login';
        }
        else {
            $task = asciiwords($task, true) ?: 'mail';
        }

        $this->task      = $task;
        $this->comm_path = $this->url(array('task' => $this->task));

        if (!empty($_REQUEST['_framed'])) {
            $this->comm_path .= '&_framed=1';
        }

        if ($this->output) {
            $this->output->set_env('task', $this->task);
            $this->output->set_env('comm_path', $this->comm_path);
        }
    }

    /**
     * Setter for system user object
     *
     * @param rcube_user $user Current user instance
     */
    public function set_user($user)
    {
        parent::set_user($user);

        $lang = $this->language_prop($this->config->get('language', $_SESSION['language']));
        $_SESSION['language'] = $this->user->language = $lang;

        // set localization
        setlocale(LC_ALL, $lang . '.utf8', $lang . '.UTF-8', 'en_US.utf8', 'en_US.UTF-8');

        // Workaround for http://bugs.php.net/bug.php?id=18556
        // Also strtoupper/strtolower and other methods are locale-aware
        // for these locales it is problematic (#1490519)
        if (in_array($lang, array('tr_TR', 'ku', 'az_AZ'))) {
            setlocale(LC_CTYPE, 'en_US.utf8', 'en_US.UTF-8', 'C');
        }
    }

    /**
     * Return instance of the internal address book class
     *
     * @param string  $id        Address book identifier (-1 for default addressbook)
     * @param boolean $writeable True if the address book needs to be writeable
     *
     * @return rcube_contacts Address book object
     */
    public function get_address_book($id, $writeable = false)
    {
        $contacts    = null;
        $ldap_config = (array)$this->config->get('ldap_public');

        // 'sql' is the alias for '0' used by autocomplete
        if ($id == 'sql')
            $id = '0';
        else if ($id == -1) {
            $id = $this->config->get('default_addressbook');
            $default = true;
        }

        // use existing instance
        if (isset($this->address_books[$id]) && ($this->address_books[$id] instanceof rcube_addressbook)) {
            $contacts = $this->address_books[$id];
        }
        else if ($id && $ldap_config[$id]) {
            $domain   = $this->config->mail_domain($_SESSION['storage_host']);
            $contacts = new rcube_ldap($ldap_config[$id], $this->config->get('ldap_debug'), $domain);
        }
        else if ($id === '0') {
            $contacts = new rcube_contacts($this->db, $this->get_user_id());
        }
        else {
            $plugin = $this->plugins->exec_hook('addressbook_get', array('id' => $id, 'writeable' => $writeable));

            // plugin returned instance of a rcube_addressbook
            if ($plugin['instance'] instanceof rcube_addressbook) {
                $contacts = $plugin['instance'];
            }
        }

        // when user requested default writeable addressbook
        // we need to check if default is writeable, if not we
        // will return first writeable book (if any exist)
        if ($contacts && $default && $contacts->readonly && $writeable) {
            $contacts = null;
        }

        // Get first addressbook from the list if configured default doesn't exist
        // This can happen when user deleted the addressbook (e.g. Kolab folder)
        if (!$contacts && (!$id || $default)) {
            $source = reset($this->get_address_sources($writeable, !$default));
            if (!empty($source)) {
                $contacts = $this->get_address_book($source['id']);
                if ($contacts) {
                    $id = $source['id'];
                }
            }
        }

        if (!$contacts) {
            // there's no default, just return
            if ($default) {
                return null;
            }

            self::raise_error(array(
                    'code'    => 700,
                    'file'    => __FILE__,
                    'line'    => __LINE__,
                    'message' => "Addressbook source ($id) not found!"
                ),
                true, true);
        }

        // add to the 'books' array for shutdown function
        $this->address_books[$id] = $contacts;

        if ($writeable && $contacts->readonly) {
            return null;
        }

        // set configured sort order
        if ($sort_col = $this->config->get('addressbook_sort_col')) {
            $contacts->set_sort_order($sort_col);
        }

        return $contacts;
    }

    /**
     * Return identifier of the address book object
     *
     * @param rcube_addressbook $object Addressbook source object
     *
     * @return string Source identifier
     */
    public function get_address_book_id($object)
    {
        foreach ($this->address_books as $index => $book) {
            if ($book === $object) {
                return $index;
            }
        }
    }

    /**
     * Return address books list
     *
     * @param boolean $writeable   True if the address book needs to be writeable
     * @param boolean $skip_hidden True if the address book needs to be not hidden
     *
     * @return array Address books array
     */
    public function get_address_sources($writeable = false, $skip_hidden = false)
    {
        $abook_type   = (string) $this->config->get('address_book_type');
        $ldap_config  = (array) $this->config->get('ldap_public');
        $autocomplete = (array) $this->config->get('autocomplete_addressbooks');
        $list         = array();

        // We are using the DB address book or a plugin address book
        if (!empty($abook_type) && strtolower($abook_type) != 'ldap') {
            if (!isset($this->address_books['0'])) {
                $this->address_books['0'] = new rcube_contacts($this->db, $this->get_user_id());
            }

            $list['0'] = array(
                'id'       => '0',
                'name'     => $this->gettext('personaladrbook'),
                'groups'   => $this->address_books['0']->groups,
                'readonly' => $this->address_books['0']->readonly,
                'undelete' => $this->address_books['0']->undelete && $this->config->get('undo_timeout'),
                'autocomplete' => in_array('sql', $autocomplete),
            );
        }

        if (!empty($ldap_config)) {
            foreach ($ldap_config as $id => $prop) {
                // handle misconfiguration
                if (empty($prop) || !is_array($prop)) {
                    continue;
                }

                $list[$id] = array(
                    'id'       => $id,
                    'name'     => html::quote($prop['name']),
                    'groups'   => !empty($prop['groups']) || !empty($prop['group_filters']),
                    'readonly' => !$prop['writable'],
                    'hidden'   => $prop['hidden'],
                    'autocomplete' => in_array($id, $autocomplete)
                );
            }
        }

        $plugin = $this->plugins->exec_hook('addressbooks_list', array('sources' => $list));
        $list   = $plugin['sources'];

        foreach ($list as $idx => $item) {
            // register source for shutdown function
            if (!is_object($this->address_books[$item['id']])) {
                $this->address_books[$item['id']] = $item;
            }
            // remove from list if not writeable as requested
            if ($writeable && $item['readonly']) {
                unset($list[$idx]);
            }
            // remove from list if hidden as requested
            else if ($skip_hidden && $item['hidden']) {
                unset($list[$idx]);
            }
        }

        return $list;
    }

    /**
     * Getter for compose responses.
     * These are stored in local config and user preferences.
     *
     * @param boolean $sorted    True to sort the list alphabetically
     * @param boolean $user_only True if only this user's responses shall be listed
     *
     * @return array List of the current user's stored responses
     */
    public function get_compose_responses($sorted = false, $user_only = false)
    {
        $responses = array();

        if (!$user_only) {
            foreach ($this->config->get('compose_responses_static', array()) as $response) {
                if (empty($response['key'])) {
                    $response['key'] = substr(md5($response['name']), 0, 16);
                }

                $response['static'] = true;
                $response['class']  = 'readonly';

                $k = $sorted ? '0000-' . mb_strtolower($response['name']) : $response['key'];
                $responses[$k] = $response;
            }
        }

        foreach ($this->config->get('compose_responses', array()) as $response) {
            if (empty($response['key'])) {
                $response['key'] = substr(md5($response['name']), 0, 16);
            }

            $k = $sorted ? mb_strtolower($response['name']) : $response['key'];
            $responses[$k] = $response;
        }

        // sort list by name
        if ($sorted) {
            ksort($responses, SORT_LOCALE_STRING);
        }

        $responses = array_values($responses);

        $hook = $this->plugins->exec_hook('get_compose_responses', array(
            'list'      => $responses,
            'sorted'    => $sorted,
            'user_only' => $user_only,
        ));

        return $hook['list'];
    }

    /**
     * Init output object for GUI and add common scripts.
     * This will instantiate a rcmail_output_html object and set
     * environment vars according to the current session and configuration
     *
     * @param boolean $framed True if this request is loaded in a (i)frame
     *
     * @return rcube_output Reference to HTML output object
     */
    public function load_gui($framed = false)
    {
        // init output page
        if (!($this->output instanceof rcmail_output_html)) {
            $this->output = new rcmail_output_html($this->task, $framed);
        }

        // set refresh interval
        $this->output->set_env('refresh_interval', $this->config->get('refresh_interval', 0));
        $this->output->set_env('session_lifetime', $this->config->get('session_lifetime', 0) * 60);

        if ($framed) {
            $this->comm_path .= '&_framed=1';
            $this->output->set_env('framed', true);
        }

        $this->output->set_env('task', $this->task);
        $this->output->set_env('action', $this->action);
        $this->output->set_env('comm_path', $this->comm_path);
        $this->output->set_charset(RCUBE_CHARSET);

        if ($this->user && $this->user->ID) {
            $this->output->set_env('user_id', $this->user->get_hash());
        }

        // set compose mode for all tasks (message compose step can be triggered from everywhere)
        $this->output->set_env('compose_extwin', $this->config->get('compose_extwin',false));

        // add some basic labels to client
        $this->output->add_label('loading', 'servererror', 'connerror', 'requesttimedout',
            'refreshing', 'windowopenerror', 'uploadingmany', 'close');

        return $this->output;
    }

    /**
     * Create an output object for JSON responses
     *
     * @return rcube_output Reference to JSON output object
     */
    public function json_init()
    {
        if (!($this->output instanceof rcmail_output_json)) {
            $this->output = new rcmail_output_json($this->task);
        }

        return $this->output;
    }

    /**
     * Create session object and start the session.
     */
    public function session_init()
    {
        parent::session_init();

        // set initial session vars
        if (!$_SESSION['user_id']) {
            $_SESSION['temp'] = true;
        }

        // restore skin selection after logout
        if ($_SESSION['temp'] && !empty($_SESSION['skin'])) {
            $this->config->set('skin', $_SESSION['skin']);
        }
    }

    /**
     * Perfom login to the mail server and to the webmail service.
     * This will also create a new user entry if auto_create_user is configured.
     *
     * @param string $username    Mail storage (IMAP) user name
     * @param string $password    Mail storage (IMAP) password
     * @param string $host        Mail storage (IMAP) host
     * @param bool   $cookiecheck Enables cookie check
     *
     * @return boolean True on success, False on failure
     */
    function login($username, $password, $host = null, $cookiecheck = false)
    {
        $this->login_error = null;

        if (empty($username)) {
            return false;
        }

        if ($cookiecheck && empty($_COOKIE)) {
            $this->login_error = self::ERROR_COOKIES_DISABLED;
            return false;
        }

        $username_filter = $this->config->get('login_username_filter');
        $username_maxlen = $this->config->get('login_username_maxlen', 1024);
        $password_maxlen = $this->config->get('login_password_maxlen', 1024);
        $default_host    = $this->config->get('default_host');
        $default_port    = $this->config->get('default_port');
        $username_domain = $this->config->get('username_domain');
        $login_lc        = $this->config->get('login_lc', 2);

        // check input for security (#1490500)
        if (($username_maxlen && strlen($username) > $username_maxlen)
            || ($username_filter && !preg_match($username_filter, $username))
            || ($password_maxlen && strlen($password) > $password_maxlen)
        ) {
            $this->login_error = self::ERROR_INVALID_REQUEST;
            return false;
        }

        // host is validated in rcmail::autoselect_host(), so here
        // we'll only handle unset host (if possible)
        if (!$host && !empty($default_host)) {
            if (is_array($default_host)) {
                list($key, $val) = each($default_host);
                $host = is_numeric($key) ? $val : $key;
            }
            else {
                $host = $default_host;
            }

            $host = rcube_utils::parse_host($host);
        }

        if (!$host) {
            $this->login_error = self::ERROR_INVALID_HOST;
            return false;
        }

        // parse $host URL
        $a_host = parse_url($host);
        if ($a_host['host']) {
            $host = $a_host['host'];
            $ssl  = (isset($a_host['scheme']) && in_array($a_host['scheme'], array('ssl','imaps','tls'))) ? $a_host['scheme'] : null;

            if (!empty($a_host['port']))
                $port = $a_host['port'];
            else if ($ssl && $ssl != 'tls' && (!$default_port || $default_port == 143))
                $port = 993;
        }

        if (!$port) {
            $port = $default_port;
        }

        // Check if we need to add/force domain to username
        if (!empty($username_domain)) {
            $domain = is_array($username_domain) ? $username_domain[$host] : $username_domain;

            if ($domain = rcube_utils::parse_host((string)$domain, $host)) {
                $pos = strpos($username, '@');

                // force configured domains
                if ($pos !== false && $this->config->get('username_domain_forced')) {
                    $username = substr($username, 0, $pos) . '@' . $domain;
                }
                // just add domain if not specified
                else if ($pos === false) {
                    $username .= '@' . $domain;
                }
            }
        }

        // Convert username to lowercase. If storage backend
        // is case-insensitive we need to store always the same username (#1487113)
        if ($login_lc) {
            if ($login_lc == 2 || $login_lc === true) {
                $username = mb_strtolower($username);
            }
            else if (strpos($username, '@')) {
                // lowercase domain name
                list($local, $domain) = explode('@', $username);
                $username = $local . '@' . mb_strtolower($domain);
            }
        }

        // try to resolve email address from virtuser table
        if (strpos($username, '@') && ($virtuser = rcube_user::email2user($username))) {
            $username = $virtuser;
        }

        // Here we need IDNA ASCII
        // Only rcube_contacts class is using domain names in Unicode
        $host     = rcube_utils::idn_to_ascii($host);
        $username = rcube_utils::idn_to_ascii($username);

        // user already registered -> overwrite username
        if ($user = rcube_user::query($username, $host)) {
            $username = $user->data['username'];

            // Brute-force prevention
            if ($user->is_locked()) {
                $this->login_error = self::ERROR_RATE_LIMIT;
                return false;
            }
        }

        $storage = $this->get_storage();

        // try to log in
        if (!$storage->connect($host, $username, $password, $port, $ssl)) {
            if ($user) {
                $user->failed_login();
            }

            // Wait a second to slow down brute-force attacks (#1490549)
            sleep(1);
            return false;
        }

        // user already registered -> update user's record
        if (is_object($user)) {
            // update last login timestamp
            $user->touch();
        }
        // create new system user
        else if ($this->config->get('auto_create_user')) {
            if ($created = rcube_user::create($username, $host)) {
                $user = $created;
            }
            else {
                self::raise_error(array(
                        'code'    => 620,
                        'file'    => __FILE__,
                        'line'    => __LINE__,
                        'message' => "Failed to create a user record. Maybe aborted by a plugin?"
                    ),
                    true, false);
            }
        }
        else {
            self::raise_error(array(
                    'code'    => 621,
                    'file'    => __FILE__,
                    'line'    => __LINE__,
                    'message' => "Access denied for new user $username. 'auto_create_user' is disabled"
                ),
                true, false);
        }

        // login succeeded
        if (is_object($user) && $user->ID) {
            // Configure environment
            $this->set_user($user);
            $this->set_storage_prop();

            // set session vars
            $_SESSION['user_id']      = $user->ID;
            $_SESSION['username']     = $user->data['username'];
            $_SESSION['storage_host'] = $host;
            $_SESSION['storage_port'] = $port;
            $_SESSION['storage_ssl']  = $ssl;
            $_SESSION['password']     = $this->encrypt($password);
            $_SESSION['login_time']   = time();

            if (isset($_REQUEST['_timezone']) && $_REQUEST['_timezone'] != '_default_') {
                $_SESSION['timezone'] = rcube_utils::get_input_value('_timezone', rcube_utils::INPUT_GPC);
            }

            // fix some old settings according to namespace prefix
            $this->fix_namespace_settings($user);

            // set/create special folders
            $this->set_special_folders();

            // clear all mailboxes related cache(s)
            $storage->clear_cache('mailboxes', true);

            return true;
        }

        return false;
    }

    /**
     * Returns error code of last login operation
     *
     * @return int Error code
     */
    public function login_error()
    {
        if ($this->login_error) {
            return $this->login_error;
        }

        if ($this->storage && $this->storage->get_error_code() < -1) {
            return self::ERROR_STORAGE;
        }
    }

    /**
     * Auto-select IMAP host based on the posted login information
     *
     * @return string Selected IMAP host
     */
    public function autoselect_host()
    {
        $default_host = $this->config->get('default_host');
        $host         = null;

        if (is_array($default_host)) {
            $post_host = rcube_utils::get_input_value('_host', rcube_utils::INPUT_POST);
            $post_user = rcube_utils::get_input_value('_user', rcube_utils::INPUT_POST);

            list(, $domain) = explode('@', $post_user);

            // direct match in default_host array
            if ($default_host[$post_host] || in_array($post_host, array_values($default_host))) {
                $host = $post_host;
            }
            // try to select host by mail domain
            else if (!empty($domain)) {
                foreach ($default_host as $storage_host => $mail_domains) {
                    if (is_array($mail_domains) && in_array_nocase($domain, $mail_domains)) {
                        $host = $storage_host;
                        break;
                    }
                    else if (stripos($storage_host, $domain) !== false || stripos(strval($mail_domains), $domain) !== false) {
                        $host = is_numeric($storage_host) ? $mail_domains : $storage_host;
                        break;
                    }
                }
            }

            // take the first entry if $host is still not set
            if (empty($host)) {
                list($key, $val) = each($default_host);
                $host = is_numeric($key) ? $val : $key;
            }
        }
        else if (empty($default_host)) {
            $host = rcube_utils::get_input_value('_host', rcube_utils::INPUT_POST);
        }
        else {
            $host = rcube_utils::parse_host($default_host);
        }

        return $host;
    }

    /**
     * Destroy session data and remove cookie
     */
    public function kill_session()
    {
        $this->plugins->exec_hook('session_destroy');

        $this->session->kill();
        $_SESSION = array('language' => $this->user->language, 'temp' => true, 'skin' => $this->config->get('skin'));
        $this->user->reset();
    }

    /**
     * Do server side actions on logout
     */
    public function logout_actions()
    {
        $storage        = $this->get_storage();
        $logout_expunge = $this->config->get('logout_expunge');
        $logout_purge   = $this->config->get('logout_purge');
        $trash_mbox     = $this->config->get('trash_mbox');

        if ($logout_purge && !empty($trash_mbox)) {
            $storage->clear_folder($trash_mbox);
        }

        if ($logout_expunge) {
            $storage->expunge_folder('INBOX');
        }

        // Try to save unsaved user preferences
        if (!empty($_SESSION['preferences'])) {
            $this->user->save_prefs(unserialize($_SESSION['preferences']));
        }
    }

    /**
     * Build a valid URL to this instance of Roundcube
     *
     * @param mixed   $p        Either a string with the action or
     *                          url parameters as key-value pairs
     * @param boolean $absolute Build an URL absolute to document root
     * @param boolean $full     Create fully qualified URL including http(s):// and hostname
     * @param bool    $secure   Return absolute URL in secure location
     *
     * @return string Valid application URL
     */
    public function url($p, $absolute = false, $full = false, $secure = false)
    {
        if (!is_array($p)) {
            if (strpos($p, 'http') === 0) {
                return $p;
            }

            $p = array('_action' => @func_get_arg(0));
        }

        $pre = array();
        $task = $p['_task'] ?: ($p['task'] ?: $this->task);
        $pre['_task'] = $task;
        unset($p['task'], $p['_task']);

        $url  = $this->filename;
        $delm = '?';

        foreach (array_merge($pre, $p) as $key => $val) {
            if ($val !== '' && $val !== null) {
                $par  = $key[0] == '_' ? $key : '_'.$key;
                $url .= $delm.urlencode($par).'='.urlencode($val);
                $delm = '&';
            }
        }

        $base_path = strval($_SERVER['REDIRECT_SCRIPT_URL'] ?: $_SERVER['SCRIPT_NAME']);
        $base_path = preg_replace('![^/]+$!', '', $base_path);

        if ($secure && ($token = $this->get_secure_url_token(true))) {
            // add token to the url
            $url = $token . '/' . $url;

            // remove old token from the path
            $base_path = rtrim($base_path, '/');
            $base_path = preg_replace('/\/[a-zA-Z0-9]{' . strlen($token) . '}$/', '', $base_path);

            // this need to be full url to make redirects work
            $absolute = true;
        }
        else if ($secure && ($token = $this->get_request_token()))
            $url .= $delm . '_token=' . urlencode($token);

        if ($absolute || $full) {
            // add base path to this Roundcube installation
            if ($base_path == '') $base_path = '/';
            $prefix = $base_path;

            // prepend protocol://hostname:port
            if ($full) {
                $prefix = rcube_utils::resolve_url($prefix);
            }

            $prefix = rtrim($prefix, '/') . '/';
        }
        else {
            $prefix = './';
        }

        return $prefix . $url;
    }

    /**
     * Function to be executed in script shutdown
     */
    public function shutdown()
    {
        parent::shutdown();

        foreach ($this->address_books as $book) {
            if (is_object($book) && is_a($book, 'rcube_addressbook')) {
                $book->close();
            }
        }

        // write performance stats to logs/console
        if ($this->config->get('devel_mode') || $this->config->get('performance_stats')) {
            // make sure logged numbers use unified format
            setlocale(LC_NUMERIC, 'en_US.utf8', 'en_US.UTF-8', 'en_US', 'C');

            if (function_exists('memory_get_usage')) {
                $mem = $this->show_bytes(memory_get_usage());
            }
            if (function_exists('memory_get_peak_usage')) {
                $mem .= '/'.$this->show_bytes(memory_get_peak_usage());
            }

            $log = $this->task . ($this->action ? '/'.$this->action : '') . ($mem ? " [$mem]" : '');

            if (defined('RCMAIL_START')) {
                self::print_timer(RCMAIL_START, $log);
            }
            else {
                self::console($log);
            }
        }
    }

    /**
     * CSRF attack prevention code. Raises error when check fails.
     *
     * @param int $mode Request mode
     */
    public function request_security_check($mode = rcube_utils::INPUT_POST)
    {
        // check request token
        if (!$this->check_request($mode)) {
            $error = array('code' => 403, 'message' => "Request security check failed");
            self::raise_error($error, false, true);
        }

        // check referer if configured
        if ($this->config->get('referer_check') && !rcube_utils::check_referer()) {
            $error = array('code' => 403, 'message' => "Referer check failed");
            self::raise_error($error, true, true);
        }
    }

    /**
     * Registers action aliases for current task
     *
     * @param array $map Alias-to-filename hash array
     */
    public function register_action_map($map)
    {
        if (is_array($map)) {
            foreach ($map as $idx => $val) {
                $this->action_map[$idx] = $val;
            }
        }
    }

    /**
     * Returns current action filename
     *
     * @param array $map Alias-to-filename hash array
     */
    public function get_action_file()
    {
        if (!empty($this->action_map[$this->action])) {
            return $this->action_map[$this->action];
        }

        return strtr($this->action, '-', '_') . '.inc';
    }

    /**
     * Fixes some user preferences according to namespace handling change.
     * Old Roundcube versions were using folder names with removed namespace prefix.
     * Now we need to add the prefix on servers where personal namespace has prefix.
     *
     * @param rcube_user $user User object
     */
    private function fix_namespace_settings($user)
    {
        $prefix     = $this->storage->get_namespace('prefix');
        $prefix_len = strlen($prefix);

        if (!$prefix_len) {
            return;
        }

        if ($this->config->get('namespace_fixed')) {
            return;
        }

        $prefs = array();

        // Build namespace prefix regexp
        $ns     = $this->storage->get_namespace();
        $regexp = array();

        foreach ($ns as $entry) {
            if (!empty($entry)) {
                foreach ($entry as $item) {
                    if (strlen($item[0])) {
                        $regexp[] = preg_quote($item[0], '/');
                    }
                }
            }
        }
        $regexp = '/^('. implode('|', $regexp).')/';

        // Fix preferences
        $opts = array('drafts_mbox', 'junk_mbox', 'sent_mbox', 'trash_mbox', 'archive_mbox');
        foreach ($opts as $opt) {
            if ($value = $this->config->get($opt)) {
                if ($value != 'INBOX' && !preg_match($regexp, $value)) {
                    $prefs[$opt] = $prefix.$value;
                }
            }
        }

        if (($search_mods = $this->config->get('search_mods')) && !empty($search_mods)) {
            $folders = array();
            foreach ($search_mods as $idx => $value) {
                if ($idx != 'INBOX' && $idx != '*' && !preg_match($regexp, $idx)) {
                    $idx = $prefix.$idx;
                }
                $folders[$idx] = $value;
            }

            $prefs['search_mods'] = $folders;
        }

        if (($threading = $this->config->get('message_threading')) && !empty($threading)) {
            $folders = array();
            foreach ($threading as $idx => $value) {
                if ($idx != 'INBOX' && !preg_match($regexp, $idx)) {
                    $idx = $prefix.$idx;
                }
                $folders[$prefix.$idx] = $value;
            }

            $prefs['message_threading'] = $folders;
        }

        if ($collapsed = $this->config->get('collapsed_folders')) {
            $folders     = explode('&&', $collapsed);
            $count       = count($folders);
            $folders_str = '';

            if ($count) {
                $folders[0]        = substr($folders[0], 1);
                $folders[$count-1] = substr($folders[$count-1], 0, -1);
            }

            foreach ($folders as $value) {
                if ($value != 'INBOX' && !preg_match($regexp, $value)) {
                    $value = $prefix.$value;
                }
                $folders_str .= '&'.$value.'&';
            }

            $prefs['collapsed_folders'] = $folders_str;
        }

        $prefs['namespace_fixed'] = true;

        // save updated preferences and reset imap settings (default folders)
        $user->save_prefs($prefs);
        $this->set_storage_prop();
    }

    /**
     * Overwrite action variable
     *
     * @param string $action New action value
     */
    public function overwrite_action($action)
    {
        $this->action = $action;
        $this->output->set_env('action', $action);
    }

    /**
     * Set environment variables for specified config options
     *
     * @param array $options List of configuration option names
     */
    public function set_env_config($options)
    {
        foreach ((array) $options as $option) {
            if ($this->config->get($option)) {
                $this->output->set_env($option, true);
            }
        }
    }

    /**
     * Returns RFC2822 formatted current date in user's timezone
     *
     * @return string Date
     */
    public function user_date()
    {
        // get user's timezone
        try {
            $tz   = new DateTimeZone($this->config->get('timezone'));
            $date = new DateTime('now', $tz);
        }
        catch (Exception $e) {
            $date = new DateTime();
        }

        return $date->format('r');
    }

    /**
     * Write login data (name, ID, IP address) to the 'userlogins' log file.
     */
    public function log_login($user = null, $failed_login = false, $error_code = 0)
    {
        if (!$this->config->get('log_logins')) {
            return;
        }

        // failed login
        if ($failed_login) {
            // don't fill the log with complete input, which could
            // have been prepared by a hacker
            if (strlen($user) > 256) {
                $user = substr($user, 0, 256) . '...';
            }

            $message = sprintf('Failed login for %s from %s in session %s (error: %d)',
                $user, rcube_utils::remote_ip(), session_id(), $error_code);
        }
        // successful login
        else {
            $user_name = $this->get_user_name();
            $user_id   = $this->get_user_id();

            if (!$user_id) {
                return;
            }

            $message = sprintf('Successful login for %s (ID: %d) from %s in session %s',
                $user_name, $user_id, rcube_utils::remote_ip(), session_id());
        }

        // log login
        self::write_log('userlogins', $message);
    }

    /**
     * Create a HTML table based on the given data
     *
     * @param array  $attrib     Named table attributes
     * @param mixed  $table_data Table row data. Either a two-dimensional array
     *                           or a valid SQL result set
     * @param array  $show_cols  List of cols to show
     * @param string $id_col     Name of the identifier col
     *
     * @return string HTML table code
     */
    public function table_output($attrib, $table_data, $show_cols, $id_col)
    {
        $table = new html_table($attrib);

        // add table header
        if (!$attrib['noheader']) {
            foreach ($show_cols as $col) {
                $table->add_header($col, $this->Q($this->gettext($col)));
            }
        }

        if (!is_array($table_data)) {
            $db = $this->get_dbh();
            while ($table_data && ($sql_arr = $db->fetch_assoc($table_data))) {
                $table->add_row(array('id' => 'rcmrow' . rcube_utils::html_identifier($sql_arr[$id_col])));

                // format each col
                foreach ($show_cols as $col) {
                    $table->add($col, $this->Q($sql_arr[$col]));
                }
            }
        }
        else {
            foreach ($table_data as $row_data) {
                $class = !empty($row_data['class']) ? $row_data['class'] : null;
                if (!empty($attrib['rowclass']))
                    $class = trim($class . ' ' . $attrib['rowclass']);
                $rowid = 'rcmrow' . rcube_utils::html_identifier($row_data[$id_col]);

                $table->add_row(array('id' => $rowid, 'class' => $class));

                // format each col
                foreach ($show_cols as $col) {
                    $val = is_array($row_data[$col]) ? $row_data[$col][0] : $row_data[$col];
                    $table->add($col, empty($attrib['ishtml']) ? $this->Q($val) : $val);
                }
            }
        }

        return $table->show($attrib);
    }

    /**
     * Convert the given date to a human readable form
     * This uses the date formatting properties from config
     *
     * @param mixed  $date    Date representation (string, timestamp or DateTime object)
     * @param string $format  Date format to use
     * @param bool   $convert Enables date convertion according to user timezone
     *
     * @return string Formatted date string
     */
    public function format_date($date, $format = null, $convert = true)
    {
        if (is_object($date) && is_a($date, 'DateTime')) {
            $timestamp = $date->format('U');
        }
        else {
            if (!empty($date)) {
                $timestamp = rcube_utils::strtotime($date);
            }

            if (empty($timestamp)) {
                return '';
            }

            try {
                $date = new DateTime("@".$timestamp);
            }
            catch (Exception $e) {
                return '';
            }
        }

        if ($convert) {
            try {
                // convert to the right timezone
                $stz = date_default_timezone_get();
                $tz = new DateTimeZone($this->config->get('timezone'));
                $date->setTimezone($tz);
                date_default_timezone_set($tz->getName());

                $timestamp = $date->format('U');
            }
            catch (Exception $e) {
            }
        }

        // define date format depending on current time
        if (!$format) {
            $now         = time();
            $now_date    = getdate($now);
            $today_limit = mktime(0, 0, 0, $now_date['mon'], $now_date['mday'], $now_date['year']);
            $week_limit  = mktime(0, 0, 0, $now_date['mon'], $now_date['mday']-6, $now_date['year']);
            $pretty_date = $this->config->get('prettydate');

            if ($pretty_date && $timestamp > $today_limit && $timestamp <= $now) {
                $format = $this->config->get('date_today', $this->config->get('time_format', 'H:i'));
                $today  = true;
            }
            else if ($pretty_date && $timestamp > $week_limit && $timestamp <= $now) {
                $format = $this->config->get('date_short', 'D H:i');
            }
            else {
                $format = $this->config->get('date_long', 'Y-m-d H:i');
            }
        }

        // strftime() format
        if (preg_match('/%[a-z]+/i', $format)) {
            $format = strftime($format, $timestamp);
            if ($stz) {
                date_default_timezone_set($stz);
            }
            return $today ? ($this->gettext('today') . ' ' . $format) : $format;
        }

        // parse format string manually in order to provide localized weekday and month names
        // an alternative would be to convert the date() format string to fit with strftime()
        $out = '';
        for ($i=0; $i<strlen($format); $i++) {
            if ($format[$i] == "\\") {  // skip escape chars
                continue;
            }

            // write char "as-is"
            if ($format[$i] == ' ' || $format[$i-1] == "\\") {
                $out .= $format[$i];
            }
            // weekday (short)
            else if ($format[$i] == 'D') {
                $out .= $this->gettext(strtolower(date('D', $timestamp)));
            }
            // weekday long
            else if ($format[$i] == 'l') {
                $out .= $this->gettext(strtolower(date('l', $timestamp)));
            }
            // month name (short)
            else if ($format[$i] == 'M') {
                $out .= $this->gettext(strtolower(date('M', $timestamp)));
            }
            // month name (long)
            else if ($format[$i] == 'F') {
                $out .= $this->gettext('long'.strtolower(date('M', $timestamp)));
            }
            else if ($format[$i] == 'x') {
                $out .= strftime('%x %X', $timestamp);
            }
            else {
                $out .= date($format[$i], $timestamp);
            }
        }

        if ($today) {
            $label = $this->gettext('today');
            // replcae $ character with "Today" label (#1486120)
            if (strpos($out, '$') !== false) {
                $out = preg_replace('/\$/', $label, $out, 1);
            }
            else {
                $out = $label . ' ' . $out;
            }
        }

        if ($stz) {
            date_default_timezone_set($stz);
        }

        return $out;
    }

    /**
     * Return folders list in HTML
     *
     * @param array $attrib Named parameters
     *
     * @return string HTML code for the gui object
     */
    public function folder_list($attrib)
    {
        static $a_mailboxes;

        $attrib += array('maxlength' => 100, 'realnames' => false, 'unreadwrap' => ' (%s)');

        $type = $attrib['type'] ? $attrib['type'] : 'ul';
        unset($attrib['type']);

        if ($type == 'ul' && !$attrib['id']) {
            $attrib['id'] = 'rcmboxlist';
        }

        if (empty($attrib['folder_name'])) {
            $attrib['folder_name'] = '*';
        }

        // get current folder
        $storage   = $this->get_storage();
        $mbox_name = $storage->get_folder();

        // build the folders tree
        if (empty($a_mailboxes)) {
            // get mailbox list
            $a_folders = $storage->list_folders_subscribed(
                '', $attrib['folder_name'], $attrib['folder_filter']);
            $delimiter = $storage->get_hierarchy_delimiter();
            $a_mailboxes = array();

            foreach ($a_folders as $folder) {
                $this->build_folder_tree($a_mailboxes, $folder, $delimiter);
            }
        }

        // allow plugins to alter the folder tree or to localize folder names
        $hook = $this->plugins->exec_hook('render_mailboxlist', array(
            'list'      => $a_mailboxes,
            'delimiter' => $delimiter,
            'type'      => $type,
            'attribs'   => $attrib,
        ));

        $a_mailboxes = $hook['list'];
        $attrib      = $hook['attribs'];

        if ($type == 'select') {
            $attrib['is_escaped'] = true;
            $select = new html_select($attrib);

            // add no-selection option
            if ($attrib['noselection']) {
                $select->add(html::quote($this->gettext($attrib['noselection'])), '');
            }

            $this->render_folder_tree_select($a_mailboxes, $mbox_name, $attrib['maxlength'], $select, $attrib['realnames']);
            $out = $select->show($attrib['default']);
        }
        else {
            $js_mailboxlist = array();
            $tree = $this->render_folder_tree_html($a_mailboxes, $mbox_name, $js_mailboxlist, $attrib);

            if ($type != 'js') {
                $out = html::tag('ul', $attrib, $tree, html::$common_attrib);

                $this->output->include_script('treelist.js');
                $this->output->add_gui_object('mailboxlist', $attrib['id']);
                $this->output->set_env('unreadwrap', $attrib['unreadwrap']);
                $this->output->set_env('collapsed_folders', (string) $this->config->get('collapsed_folders'));
            }

            $this->output->set_env('mailboxes', $js_mailboxlist);

            // we can't use object keys in javascript because they are unordered
            // we need sorted folders list for folder-selector widget
            $this->output->set_env('mailboxes_list', array_keys($js_mailboxlist));
        }

        // add some labels to client
        $this->output->add_label('purgefolderconfirm', 'deletemessagesconfirm');

        return $out;
    }

    /**
     * Return folders list as html_select object
     *
     * @param array $p Named parameters
     *
     * @return html_select HTML drop-down object
     */
    public function folder_selector($p = array())
    {
        $realnames = $this->config->get('show_real_foldernames');
        $p += array('maxlength' => 100, 'realnames' => $realnames, 'is_escaped' => true);
        $a_mailboxes = array();
        $storage = $this->get_storage();

        if (empty($p['folder_name'])) {
            $p['folder_name'] = '*';
        }

        if ($p['unsubscribed']) {
            $list = $storage->list_folders('', $p['folder_name'], $p['folder_filter'], $p['folder_rights']);
        }
        else {
            $list = $storage->list_folders_subscribed('', $p['folder_name'], $p['folder_filter'], $p['folder_rights']);
        }

        $delimiter = $storage->get_hierarchy_delimiter();

        if (!empty($p['exceptions'])) {
            $list = array_diff($list, (array) $p['exceptions']);
        }

        if (!empty($p['additional'])) {
            foreach ($p['additional'] as $add_folder) {
                $add_items = explode($delimiter, $add_folder);
                $folder    = '';
                while (count($add_items)) {
                    $folder .= array_shift($add_items);

                    // @TODO: sorting
                    if (!in_array($folder, $list)) {
                        $list[] = $folder;
                    }

                    $folder .= $delimiter;
                }
            }
        }

        foreach ($list as $folder) {
            $this->build_folder_tree($a_mailboxes, $folder, $delimiter);
        }

        $select = new html_select($p);

        if ($p['noselection']) {
            $select->add(html::quote($p['noselection']), '');
        }

        $this->render_folder_tree_select($a_mailboxes, $mbox, $p['maxlength'], $select, $p['realnames'], 0, $p);

        return $select;
    }

    /**
     * Create a hierarchical array of the mailbox list
     */
    public function build_folder_tree(&$arrFolders, $folder, $delm = '/', $path = '')
    {
        // Handle namespace prefix
        $prefix = '';
        if (!$path) {
            $n_folder = $folder;
            $folder   = $this->storage->mod_folder($folder);

            if ($n_folder != $folder) {
                $prefix = substr($n_folder, 0, -strlen($folder));
            }
        }

        $pos = strpos($folder, $delm);

        if ($pos !== false) {
            $subFolders    = substr($folder, $pos+1);
            $currentFolder = substr($folder, 0, $pos);

            // sometimes folder has a delimiter as the last character
            if (!strlen($subFolders)) {
                $virtual = false;
            }
            else if (!isset($arrFolders[$currentFolder])) {
                $virtual = true;
            }
            else {
                $virtual = $arrFolders[$currentFolder]['virtual'];
            }
        }
        else {
            $subFolders    = false;
            $currentFolder = $folder;
            $virtual       = false;
        }

        $path .= $prefix . $currentFolder;

        if (!isset($arrFolders[$currentFolder])) {
            $arrFolders[$currentFolder] = array(
                'id'      => $path,
                'name'    => rcube_charset::convert($currentFolder, 'UTF7-IMAP'),
                'virtual' => $virtual,
                'folders' => array()
            );
        }
        else {
            $arrFolders[$currentFolder]['virtual'] = $virtual;
        }

        if (strlen($subFolders)) {
            $this->build_folder_tree($arrFolders[$currentFolder]['folders'], $subFolders, $delm, $path.$delm);
        }
    }

    /**
     * Return html for a structured list &lt;ul&gt; for the mailbox tree
     */
    public function render_folder_tree_html(&$arrFolders, &$mbox_name, &$jslist, $attrib, $nestLevel = 0)
    {
        $maxlength = intval($attrib['maxlength']);
        $realnames = (bool)$attrib['realnames'];
        $msgcounts = $this->storage->get_cache('messagecount');
        $collapsed = $this->config->get('collapsed_folders');
        $realnames = $this->config->get('show_real_foldernames');

        $out = '';
        foreach ($arrFolders as $folder) {
            $title        = null;
            $folder_class = $this->folder_classname($folder['id']);
            $is_collapsed = strpos($collapsed, '&'.rawurlencode($folder['id']).'&') !== false;
            $unread       = $msgcounts ? intval($msgcounts[$folder['id']]['UNSEEN']) : 0;

            if ($folder_class && !$realnames) {
                $foldername = $this->gettext($folder_class);
            }
            else {
                $foldername = $folder['name'];

                // shorten the folder name to a given length
                if ($maxlength && $maxlength > 1) {
                    $fname = abbreviate_string($foldername, $maxlength);
                    if ($fname != $foldername) {
                        $title = $foldername;
                    }
                    $foldername = $fname;
                }
            }

            // make folder name safe for ids and class names
            $folder_id = rcube_utils::html_identifier($folder['id'], true);
            $classes   = array('mailbox');

            // set special class for Sent, Drafts, Trash and Junk
            if ($folder_class) {
                $classes[] = $folder_class;
            }

            if ($folder['id'] == $mbox_name) {
                $classes[] = 'selected';
            }

            if ($folder['virtual']) {
                $classes[] = 'virtual';
            }
            else if ($unread) {
                $classes[] = 'unread';
            }

            $js_name     = $this->JQ($folder['id']);
            $html_name   = $this->Q($foldername) . ($unread ? html::span('unreadcount', sprintf($attrib['unreadwrap'], $unread)) : '');
            $link_attrib = $folder['virtual'] ? array() : array(
                'href'    => $this->url(array('_mbox' => $folder['id'])),
                'onclick' => sprintf("return %s.command('list','%s',this,event)", rcmail_output::JS_OBJECT_NAME, $js_name),
                'rel'     => $folder['id'],
                'title'   => $title,
            );

            $out .= html::tag('li', array(
                    'id'      => "rcmli" . $folder_id,
                    'class'   => join(' ', $classes),
                    'noclose' => true
                ),
                html::a($link_attrib, $html_name));

            if (!empty($folder['folders'])) {
                $out .= html::div('treetoggle ' . ($is_collapsed ? 'collapsed' : 'expanded'), '&nbsp;');
            }

            $jslist[$folder['id']] = array(
                'id'      => $folder['id'],
                'name'    => $foldername,
                'virtual' => $folder['virtual'],
            );

            if (!empty($folder_class)) {
                $jslist[$folder['id']]['class'] = $folder_class;
            }

            if (!empty($folder['folders'])) {
                $out .= html::tag('ul', array('style' => ($is_collapsed ? "display:none;" : null)),
                    $this->render_folder_tree_html($folder['folders'], $mbox_name, $jslist, $attrib, $nestLevel+1));
            }

            $out .= "</li>\n";
        }

        return $out;
    }

    /**
     * Return html for a flat list <select> for the mailbox tree
     */
    public function render_folder_tree_select(&$arrFolders, &$mbox_name, $maxlength, &$select, $realnames = false, $nestLevel = 0, $opts = array())
    {
        $out = '';

        foreach ($arrFolders as $folder) {
            // skip exceptions (and its subfolders)
            if (!empty($opts['exceptions']) && in_array($folder['id'], $opts['exceptions'])) {
                continue;
            }

            // skip folders in which it isn't possible to create subfolders
            if (!empty($opts['skip_noinferiors'])) {
                $attrs = $this->storage->folder_attributes($folder['id']);
                if ($attrs && in_array_nocase('\\Noinferiors', $attrs)) {
                    continue;
                }
            }

            if (!$realnames && ($folder_class = $this->folder_classname($folder['id']))) {
                $foldername = $this->gettext($folder_class);
            }
            else {
                $foldername = $folder['name'];

                // shorten the folder name to a given length
                if ($maxlength && $maxlength > 1) {
                    $foldername = abbreviate_string($foldername, $maxlength);
                }
            }

            $select->add(str_repeat('&nbsp;', $nestLevel*4) . html::quote($foldername), $folder['id']);

            if (!empty($folder['folders'])) {
                $out .= $this->render_folder_tree_select($folder['folders'], $mbox_name, $maxlength,
                    $select, $realnames, $nestLevel+1, $opts);
            }
        }

        return $out;
    }

    /**
     * Return internal name for the given folder if it matches the configured special folders
     */
    public function folder_classname($folder_id)
    {
        if ($folder_id == 'INBOX') {
            return 'inbox';
        }

        // for these mailboxes we have localized labels and css classes
        foreach (array('sent', 'drafts', 'trash', 'junk') as $smbx)
        {
            if ($folder_id === $this->config->get($smbx.'_mbox')) {
                return $smbx;
            }
        }
    }

    /**
     * Try to localize the given IMAP folder name.
     * UTF-7 decode it in case no localized text was found
     *
     * @param string $name      Folder name
     * @param bool   $with_path Enable path localization
     *
     * @return string Localized folder name in UTF-8 encoding
     */
    public function localize_foldername($name, $with_path = false)
    {
        $realnames = $this->config->get('show_real_foldernames');

        if (!$realnames && ($folder_class = $this->folder_classname($name))) {
            return $this->gettext($folder_class);
        }

        // try to localize path of the folder
        if ($with_path && !$realnames) {
            $storage   = $this->get_storage();
            $delimiter = $storage->get_hierarchy_delimiter();
            $path      = explode($delimiter, $name);
            $count     = count($path);

            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $folder = implode($delimiter, array_slice($path, 0, -$i));
                    if ($folder_class = $this->folder_classname($folder)) {
                        $name = implode($delimiter, array_slice($path, $count - $i));
                        $name = rcube_charset::convert($name, 'UTF7-IMAP');

                        return $this->gettext($folder_class) . $delimiter . $name;
                    }
                }
            }
        }

        return rcube_charset::convert($name, 'UTF7-IMAP');
    }

    /**
     * Localize folder path
     */
    public function localize_folderpath($path)
    {
        $protect_folders = $this->config->get('protect_default_folders');
        $delimiter       = $this->storage->get_hierarchy_delimiter();
        $path            = explode($delimiter, $path);
        $result          = array();

        foreach ($path as $idx => $dir) {
            $directory = implode($delimiter, array_slice($path, 0, $idx+1));
            if ($protect_folders && $this->storage->is_special_folder($directory)) {
                unset($result);
                $result[] = $this->localize_foldername($directory);
            }
            else {
                $result[] = rcube_charset::convert($dir, 'UTF7-IMAP');
            }
        }

        return implode($delimiter, $result);
    }

    /**
     * Return HTML for quota indicator object
     *
     * @param array $attrib Named parameters
     *
     * @return string HTML code for the quota indicator object
     */
    public static function quota_display($attrib)
    {
        $rcmail = rcmail::get_instance();

        if (!$attrib['id']) {
            $attrib['id'] = 'rcmquotadisplay';
        }

        $_SESSION['quota_display'] = !empty($attrib['display']) ? $attrib['display'] : 'text';

        $rcmail->output->add_gui_object('quotadisplay', $attrib['id']);

        $quota = $rcmail->quota_content($attrib);

        $rcmail->output->add_script('rcmail.set_quota('.rcube_output::json_serialize($quota).');', 'docready');

        return html::span($attrib, '&nbsp;');
    }

    /**
     * Return (parsed) quota information
     *
     * @param array $attrib Named parameters
     * @param array $folder Current folder
     *
     * @return array Quota information
     */
    public function quota_content($attrib = null, $folder = null)
    {
        $quota = $this->storage->get_quota($folder);
        $quota = $this->plugins->exec_hook('quota', $quota);

        $quota_result           = (array) $quota;
        $quota_result['type']   = isset($_SESSION['quota_display']) ? $_SESSION['quota_display'] : '';
        $quota_result['folder'] = $folder !== null && $folder !== '' ? $folder : 'INBOX';

        if ($quota['total'] > 0) {
            if (!isset($quota['percent'])) {
                $quota_result['percent'] = min(100, round(($quota['used']/max(1,$quota['total']))*100));
            }

            $title = sprintf('%s / %s (%.0f%%)',
                $this->show_bytes($quota['used'] * 1024),
                $this->show_bytes($quota['total'] * 1024),
                $quota_result['percent']
            );

            $quota_result['title'] = $title;

            if ($attrib['width']) {
                $quota_result['width'] = $attrib['width'];
            }
            if ($attrib['height']) {
                $quota_result['height'] = $attrib['height'];
            }

            // build a table of quota types/roots info
            if (($root_cnt = count($quota_result['all'])) > 1 || count($quota_result['all'][key($quota_result['all'])]) > 1) {
                $table = new html_table(array('cols' => 3, 'class' => 'quota-info'));

                $table->add_header(null, self::Q($this->gettext('quotatype')));
                $table->add_header(null, self::Q($this->gettext('quotatotal')));
                $table->add_header(null, self::Q($this->gettext('quotaused')));

                foreach ($quota_result['all'] as $root => $data) {
                    if ($root_cnt > 1 && $root) {
                        $table->add(array('colspan' => 3, 'class' => 'root'), self::Q($root));
                    }

                    if ($storage = $data['storage']) {
                        $percent = min(100, round(($storage['used']/max(1,$storage['total']))*100));

                        $table->add('name', self::Q($this->gettext('quotastorage')));
                        $table->add(null, $this->show_bytes($storage['total'] * 1024));
                        $table->add(null, sprintf('%s (%.0f%%)', $this->show_bytes($storage['used'] * 1024), $percent));
                    }
                    if ($message = $data['message']) {
                        $percent = min(100, round(($message['used']/max(1,$message['total']))*100));

                        $table->add('name', self::Q($this->gettext('quotamessage')));
                        $table->add(null, intval($message['total']));
                        $table->add(null, sprintf('%d (%.0f%%)', $message['used'], $percent));
                    }
                }

                $quota_result['table'] = $table->show();
            }
        }
        else {
            $unlimited               = $this->config->get('quota_zero_as_unlimited');
            $quota_result['title']   = $this->gettext($unlimited ? 'unlimited' : 'unknown');
            $quota_result['percent'] = 0;
        }

        // cleanup
        unset($quota_result['abort']);
        if (empty($quota_result['table'])) {
            unset($quota_result['all']);
        }

        return $quota_result;
    }

    /**
     * Outputs error message according to server error/response codes
     *
     * @param string $fallback      Fallback message label
     * @param array  $fallback_args Fallback message label arguments
     * @param string $suffix        Message label suffix
     * @param array  $params        Additional parameters (type, prefix)
     */
    public function display_server_error($fallback = null, $fallback_args = null, $suffix = '', $params = array())
    {
        $err_code = $this->storage->get_error_code();
        $res_code = $this->storage->get_response_code();
        $args     = array();

        if ($res_code == rcube_storage::NOPERM) {
            $error = 'errornoperm';
        }
        else if ($res_code == rcube_storage::READONLY) {
            $error = 'errorreadonly';
        }
        else if ($res_code == rcube_storage::OVERQUOTA) {
            $error = 'erroroverquota';
        }
        else if ($err_code && ($err_str = $this->storage->get_error_str())) {
            // try to detect access rights problem and display appropriate message
            if (stripos($err_str, 'Permission denied') !== false) {
                $error = 'errornoperm';
            }
            // try to detect full mailbox problem and display appropriate message
            // there can be e.g. "Quota exceeded" / "quotum would exceed" / "Over quota"
            else if (stripos($err_str, 'quot') !== false && preg_match('/exceed|over/i', $err_str)) {
                $error = 'erroroverquota';
            }
            else {
                $error = 'servererrormsg';
                $args  = array('msg' => rcube::Q($err_str));
            }
        }
        else if ($err_code < 0) {
            $error = 'storageerror';
        }
        else if ($fallback) {
            $error = $fallback;
            $args  = $fallback_args;
            $params['prefix'] = false;
        }

        if ($error) {
            if ($suffix && $this->text_exists($error . $suffix)) {
                $error .= $suffix;
            }

            $msg = $this->gettext(array('name' => $error, 'vars' => $args));

            if ($params['prefix'] && $fallback) {
                $msg = $this->gettext(array('name' => $fallback, 'vars' => $fallback_args)) . ' ' . $msg;
            }

            $this->output->show_message($msg, $params['type'] ?: 'error');
        }
    }

    /**
     * Output HTML editor scripts
     *
     * @param string $mode Editor mode
     */
    public function html_editor($mode = '')
    {
        $spellcheck       = intval($this->config->get('enable_spellcheck'));
        $spelldict        = intval($this->config->get('spellcheck_dictionary'));
        $disabled_plugins = array();
        $disabled_buttons = array();
        $extra_plugins    = array();
        $extra_buttons    = array();

        if (!$spellcheck) {
            $disabled_plugins[] = 'spellchecker';
        }

        $hook = $this->plugins->exec_hook('html_editor', array(
                'mode'             => $mode,
                'disabled_plugins' => $disabled_plugins,
                'disabled_buttons' => $disabled_buttons,
                'extra_plugins' => $extra_plugins,
                'extra_buttons' => $extra_buttons,
        ));

        if ($hook['abort']) {
            return;
        }

        $lang_codes = array($_SESSION['language']);
        $assets_dir = $this->config->get('assets_dir') ?: INSTALL_PATH;

        if ($pos = strpos($_SESSION['language'], '_')) {
            $lang_codes[] = substr($_SESSION['language'], 0, $pos);
        }

        foreach ($lang_codes as $code) {
            if (file_exists("$assets_dir/program/js/tinymce/langs/$code.js")) {
                $lang = $code;
                break;
            }
        }

        if (empty($lang)) {
            $lang = 'en';
        }

        $config = array(
            'mode'       => $mode,
            'lang'       => $lang,
            'skin_path'  => $this->output->get_skin_path(),
            'spellcheck' => $spellcheck, // deprecated
            'spelldict'  => $spelldict,
            'disabled_plugins' => $hook['disabled_plugins'],
            'disabled_buttons' => $hook['disabled_buttons'],
            'extra_plugins'    => $hook['extra_plugins'],
            'extra_buttons'    => $hook['extra_buttons'],
        );

        $this->output->add_label('selectimage', 'addimage', 'selectmedia', 'addmedia');
        $this->output->set_env('editor_config', $config);
        $this->output->include_css('program/resources/tinymce/browser.css');
        $this->output->include_script('tinymce/tinymce.min.js');
        $this->output->include_script('editor.js');
    }

    /**
     * File upload progress handler.
     */
    public function upload_progress()
    {
        $params = array(
            'action' => $this->action,
            'name'   => rcube_utils::get_input_value('_progress', rcube_utils::INPUT_GET),
        );

        if (function_exists('uploadprogress_get_info')) {
            $status = uploadprogress_get_info($params['name']);

            if (!empty($status)) {
                $params['current'] = $status['bytes_uploaded'];
                $params['total']   = $status['bytes_total'];
            }
        }

        if (!isset($status) && filter_var(ini_get('apc.rfc1867'), FILTER_VALIDATE_BOOLEAN)
            && ini_get('apc.rfc1867_name')
        ) {
            $prefix = ini_get('apc.rfc1867_prefix');
            $status = apc_fetch($prefix . $params['name']);

            if (!empty($status)) {
                $params['current'] = $status['current'];
                $params['total']   = $status['total'];
            }
        }

        if (!isset($status) && filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN)
            && ini_get('session.upload_progress.name')
        ) {
            $key = ini_get('session.upload_progress.prefix') . $params['name'];

            $params['total']   = $_SESSION[$key]['content_length'];
            $params['current'] = $_SESSION[$key]['bytes_processed'];
        }

        if (!empty($params['total'])) {
            $total = $this->show_bytes($params['total'], $unit);
            switch ($unit) {
            case 'GB':
                $gb      = $params['current']/1073741824;
                $current = sprintf($gb >= 10 ? "%d" : "%.1f", $gb);
                break;
            case 'MB':
                $mb      = $params['current']/1048576;
                $current = sprintf($mb >= 10 ? "%d" : "%.1f", $mb);
                break;
            case 'KB':
                $current = round($params['current']/1024);
                break;
            case 'B':
            default:
                $current = $params['current'];
                break;
            }

            $params['percent'] = round($params['current']/$params['total']*100);
            $params['text']    = $this->gettext(array(
                'name' => 'uploadprogress',
                'vars' => array(
                    'percent' => $params['percent'] . '%',
                    'current' => $current,
                    'total'   => $total
                )
            ));
        }

        $this->output->command('upload_progress_update', $params);
        $this->output->send();
    }

    /**
     * Initializes file uploading interface.
     *
     * @param int $max_size Optional maximum file size in bytes
     *
     * @return string Human-readable file size limit
     */
    public function upload_init($max_size = null)
    {
        // Enable upload progress bar
        if ($seconds = $this->config->get('upload_progress')) {
            if (function_exists('uploadprogress_get_info')) {
                $field_name = 'UPLOAD_IDENTIFIER';
            }
            if (!$field_name && filter_var(ini_get('apc.rfc1867'), FILTER_VALIDATE_BOOLEAN)) {
                $field_name = ini_get('apc.rfc1867_name');
            }
            if (!$field_name && filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN)) {
                $field_name = ini_get('session.upload_progress.name');
            }

            if ($field_name) {
                $this->output->set_env('upload_progress_name', $field_name);
                $this->output->set_env('upload_progress_time', (int) $seconds);
            }
        }

        // find max filesize value
        $max_filesize = rcube_utils::max_upload_size();
        if ($max_size && $max_size < $max_filesize) {
            $max_filesize = $max_size;
        }

        $max_filesize_txt = $this->show_bytes($max_filesize);
        $this->output->set_env('max_filesize', $max_filesize);
        $this->output->set_env('filesizeerror', $this->gettext(array(
            'name' => 'filesizeerror', 'vars' => array('size' => $max_filesize_txt))));

        if ($max_filecount = ini_get('max_file_uploads')) {
            $this->output->set_env('max_filecount', $max_filecount);
            $this->output->set_env('filecounterror', $this->gettext(array(
                'name' => 'filecounterror', 'vars' => array('count' => $max_filecount))));
        }

        return $max_filesize_txt;
    }

    /**
     * Upload form object
     *
     * @param array  $attrib     Object attributes
     * @param string $name       Form object name
     * @param string $action     Form action name
     * @param array  $input_attr File input attributes
     *
     * @return string HTML output
     */
    public function upload_form($attrib, $name, $action, $input_attr = array())
    {
        // Get filesize, enable upload progress bar
        $max_filesize = $this->upload_init();

        $hint = html::div('hint', $this->gettext(array('name' => 'maxuploadsize', 'vars' => array('size' => $max_filesize))));

        if ($attrib['mode'] == 'hint') {
            return $hint;
        }

        // set defaults
        $attrib += array('id' => 'rcmUploadbox', 'buttons' => 'yes');

        $event   = rcmail_output::JS_OBJECT_NAME . ".command('$action', this.form)";
        $form_id = $attrib['id'] . 'Frm';

        // Default attributes of file input and form
        $input_attr += array(
            'id'   => $attrib['id'] . 'Input',
            'type' => 'file',
            'name' => '_attachments[]',
        );

        $form_attr = array(
            'id'      => $form_id,
            'name'    => $name,
            'method'  => 'post',
            'enctype' => 'multipart/form-data'
        );

        if ($attrib['mode'] == 'smart') {
            unset($attrib['buttons']);
            $form_attr['class'] = 'smart-upload';
            $input_attr = array_merge($input_attr, array(
                // Note: Chrome sometimes executes onchange event on Cancel, make sure a file was selected
                'onchange' => "if ((this.files && this.files.length) || (!this.files && this.value)) $event",
                'class'    => 'smart-upload',
                'tabindex' => '-1',
            ));
        }

        $input   = new html_inputfield($input_attr);
        $content = $attrib['prefix'] . $input->show();

        if ($attrib['mode'] != 'smart') {
            $content  = html::div(null, $content);
            $content .= $hint;
        }

        if (rcube_utils::get_boolean($attrib['buttons'])) {
            $button   = new html_inputfield(array('type' => 'button'));
            $content .= html::div('buttons',
                $button->show($this->gettext('close'), array('class' => 'button', 'onclick' => "$('#{$attrib['id']}').hide()")) . ' ' .
                $button->show($this->gettext('upload'), array('class' => 'button mainaction', 'onclick' => $event))
            );
        }

        $this->output->add_gui_object($name, $form_id);

        return html::div($attrib, $this->output->form_tag($form_attr, $content));
    }

    /**
     * Outputs uploaded file content (with image thumbnails support
     *
     * @param array $file Upload file data
     */
    public function display_uploaded_file($file)
    {
        if (empty($file)) {
            return;
        }

        $file = $this->plugins->exec_hook('attachment_display', $file);

        if ($file['status']) {
            if (empty($file['size'])) {
                $file['size'] = $file['data'] ? strlen($file['data']) : @filesize($file['path']);
            }

            // generate image thumbnail for file browser in HTML editor
            if (!empty($_GET['_thumbnail'])) {
                $temp_dir       = $this->config->get('temp_dir');
                $thumbnail_size = 80;
                $mimetype       = $file['mimetype'];
                $file_ident     = $file['id'] . ':' . $file['mimetype'] . ':' . $file['size'];
                $cache_basename = $temp_dir . '/' . md5($file_ident . ':' . $this->user->ID . ':' . $thumbnail_size);
                $cache_file     = $cache_basename . '.thumb';

                // render thumbnail image if not done yet
                if (!is_file($cache_file)) {
                    if (!$file['path']) {
                        $orig_name = $filename = $cache_basename . '.tmp';
                        file_put_contents($orig_name, $file['data']);
                    }
                    else {
                        $filename = $file['path'];
                    }

                    $image = new rcube_image($filename);
                    if ($imgtype = $image->resize($thumbnail_size, $cache_file, true)) {
                        $mimetype = 'image/' . $imgtype;

                        if ($orig_name) {
                            unlink($orig_name);
                        }
                    }
                }

                if (is_file($cache_file)) {
                    // cache for 1h
                    $this->output->future_expire_header(3600);
                    header('Content-Type: ' . $mimetype);
                    header('Content-Length: ' . filesize($cache_file));

                    readfile($cache_file);
                    exit;
                }
            }

            header('Content-Type: ' . $file['mimetype']);
            header('Content-Length: ' . $file['size']);

            if ($file['data']) {
                echo $file['data'];
            }
            else if ($file['path']) {
                readfile($file['path']);
            }
        }
    }

    /**
     * Initializes client-side autocompletion.
     */
    public function autocomplete_init()
    {
        static $init;

        if ($init) {
            return;
        }

        $init = 1;

        if (($threads = (int)$this->config->get('autocomplete_threads')) > 0) {
            $book_types = (array) $this->config->get('autocomplete_addressbooks', 'sql');
            if (count($book_types) > 1) {
                $this->output->set_env('autocomplete_threads', $threads);
                $this->output->set_env('autocomplete_sources', $book_types);
            }
        }

        $this->output->set_env('autocomplete_max', (int)$this->config->get('autocomplete_max', 15));
        $this->output->set_env('autocomplete_min_length', $this->config->get('autocomplete_min_length'));
        $this->output->add_label('autocompletechars', 'autocompletemore');
    }

    /**
     * Returns supported font-family specifications
     *
     * @param string $font Font name
     *
     * @param string|array Font-family specification array or string (if $font is used)
     */
    public static function font_defs($font = null)
    {
        $fonts = array(
            'Andale Mono'   => '"Andale Mono",Times,monospace',
            'Arial'         => 'Arial,Helvetica,sans-serif',
            'Arial Black'   => '"Arial Black","Avant Garde",sans-serif',
            'Book Antiqua'  => '"Book Antiqua",Palatino,serif',
            'Courier New'   => '"Courier New",Courier,monospace',
            'Georgia'       => 'Georgia,Palatino,serif',
            'Helvetica'     => 'Helvetica,Arial,sans-serif',
            'Impact'        => 'Impact,Chicago,sans-serif',
            'Tahoma'        => 'Tahoma,Arial,Helvetica,sans-serif',
            'Terminal'      => 'Terminal,Monaco,monospace',
            'Times New Roman' => '"Times New Roman",Times,serif',
            'Trebuchet MS'  => '"Trebuchet MS",Geneva,sans-serif',
            'Verdana'       => 'Verdana,Geneva,sans-serif',
        );

        if ($font) {
            return $fonts[$font];
        }

        return $fonts;
    }

    /**
     * Create a human readable string for a number of bytes
     *
     * @param int    $bytes Number of bytes
     * @param string &$unit Size unit
     *
     * @return string Byte string
     */
    public function show_bytes($bytes, &$unit = null)
    {
        if ($bytes >= 1073741824) {
            $unit = 'GB';
            $gb   = $bytes/1073741824;
            $str  = sprintf($gb >= 10 ? "%d " : "%.1f ", $gb) . $this->gettext($unit);
        }
        else if ($bytes >= 1048576) {
            $unit = 'MB';
            $mb   = $bytes/1048576;
            $str  = sprintf($mb >= 10 ? "%d " : "%.1f ", $mb) . $this->gettext($unit);
        }
        else if ($bytes >= 1024) {
            $unit = 'KB';
            $str  = sprintf("%d ",  round($bytes/1024)) . $this->gettext($unit);
        }
        else {
            $unit = 'B';
            $str  = sprintf('%d ', $bytes) . $this->gettext($unit);
        }

        return $str;
    }

    /**
     * Returns real size (calculated) of the message part
     *
     * @param rcube_message_part $part Message part
     *
     * @return string Part size (and unit)
     */
    public function message_part_size($part)
    {
        if (isset($part->d_parameters['size'])) {
            $size = $this->show_bytes((int)$part->d_parameters['size']);
        }
        else {
            $size = $part->size;

            if ($size === 0) {
                $part->exact_size = true;
            }

            if ($part->encoding == 'base64') {
                $size = $size / 1.33;
            }

            $size = $this->show_bytes($size);
        }

        if (!$part->exact_size) {
            $size = '~' . $size;
        }

        return $size;
    }

    /**
     * Returns message UID(s) and IMAP folder(s) from GET/POST data
     *
     * @param string $uids           UID value to decode
     * @param string $mbox           Default mailbox value (if not encoded in UIDs)
     * @param bool   $is_multifolder Will be set to True if multi-folder request
     *
     * @return array  List of message UIDs per folder
     */
    public static function get_uids($uids = null, $mbox = null, &$is_multifolder = false)
    {
        // message UID (or comma-separated list of IDs) is provided in
        // the form of <ID>-<MBOX>[,<ID>-<MBOX>]*

        $_uid  = $uids ?: rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC);
        $_mbox = $mbox ?: (string) rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC);

        // already a hash array
        if (is_array($_uid) && !isset($_uid[0])) {
            return $_uid;
        }

        $result = array();

        // special case: *
        if ($_uid == '*' && is_object($_SESSION['search'][1]) && $_SESSION['search'][1]->multi) {
            $is_multifolder = true;
            // extract the full list of UIDs per folder from the search set
            foreach ($_SESSION['search'][1]->sets as $subset) {
                $mbox = $subset->get_parameters('MAILBOX');
                $result[$mbox] = $subset->get();
            }
        }
        else {
            if (is_string($_uid))
                $_uid = explode(',', $_uid);

            // create a per-folder UIDs array
            foreach ((array)$_uid as $uid) {
                list($uid, $mbox) = explode('-', $uid, 2);
                if (!strlen($mbox)) {
                    $mbox = $_mbox;
                }
                else {
                    $is_multifolder = true;
                }

                if ($uid == '*') {
                    $result[$mbox] = $uid;
                }
                else {
                    $result[$mbox][] = $uid;
                }
            }
        }

        return $result;
    }

    /**
     * Get resource file content (with assets_dir support)
     *
     * @param string $name File name
     *
     * @return string File content
     */
    public function get_resource_content($name)
    {
        if (!strpos($name, '/')) {
            $name = "program/resources/$name";
        }

        $assets_dir = $this->config->get('assets_dir');

        if ($assets_dir) {
            $path = slashify($assets_dir) . $name;
            if (@file_exists($path)) {
                $name = $path;
            }
        }

        return file_get_contents($name, false);
    }

    /**
     * Converts HTML content into plain text
     *
     * @param string $html    HTML content
     * @param array  $options Conversion parameters (width, links, charset)
     *
     * @return string Plain text
     */
    public function html2text($html, $options = array())
    {
        $default_options = array(
            'links'   => true,
            'width'   => 75,
            'body'    => $html,
            'charset' => RCUBE_CHARSET,
        );

        $options = array_merge($default_options, (array) $options);

        // Plugins may want to modify HTML in another/additional way
        $options = $this->plugins->exec_hook('html2text', $options);

        // Convert to text
        if (!$options['abort']) {
            $converter = new rcube_html2text($options['body'],
                false, $options['links'], $options['width'], $options['charset']);

            $options['body'] = rtrim($converter->get_text());
        }

        return $options['body'];
    }

    /**
     * Connect to the mail storage server with stored session data
     *
     * @return bool True on success, False on error
     */
    public function storage_connect()
    {
        $storage = $this->get_storage();

        if ($_SESSION['storage_host'] && !$storage->is_connected()) {
            $host = $_SESSION['storage_host'];
            $user = $_SESSION['username'];
            $port = $_SESSION['storage_port'];
            $ssl  = $_SESSION['storage_ssl'];
            $pass = $this->decrypt($_SESSION['password']);

            if (!$storage->connect($host, $user, $pass, $port, $ssl)) {
                if (is_object($this->output)) {
                    $this->output->show_message('storageerror', 'error');
                }
            }
            else {
                $this->set_storage_prop();
            }
        }

        return $storage->is_connected();
    }
}
