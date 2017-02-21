<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2014, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Framework base class providing core functions and holding           |
 |   instances of all 'global' objects like db- and storage-connections  |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Base class of the Roundcube Framework
 * implemented as singleton
 *
 * @package    Framework
 * @subpackage Core
 */
class rcube
{
    // Init options
    const INIT_WITH_DB      = 1;
    const INIT_WITH_PLUGINS = 2;

    // Request status
    const REQUEST_VALID       = 0;
    const REQUEST_ERROR_URL   = 1;
    const REQUEST_ERROR_TOKEN = 2;

    const DEBUG_LINE_LENGTH = 4096;

    /**
     * Singleton instace of rcube
     *
     * @var rcube
     */
    static protected $instance;

    /**
     * Stores instance of rcube_config.
     *
     * @var rcube_config
     */
    public $config;

    /**
     * Instace of database class.
     *
     * @var rcube_db
     */
    public $db;

    /**
     * Instace of Memcache class.
     *
     * @var Memcache
     */
    public $memcache;

   /**
     * Instace of rcube_session class.
     *
     * @var rcube_session
     */
    public $session;

    /**
     * Instance of rcube_smtp class.
     *
     * @var rcube_smtp
     */
    public $smtp;

    /**
     * Instance of rcube_storage class.
     *
     * @var rcube_storage
     */
    public $storage;

    /**
     * Instance of rcube_output class.
     *
     * @var rcube_output
     */
    public $output;

    /**
     * Instance of rcube_plugin_api.
     *
     * @var rcube_plugin_api
     */
    public $plugins;

    /**
     * Instance of rcube_user class.
     *
     * @var rcube_user
     */
    public $user;

    /**
     * Request status
     *
     * @var int
     */
    public $request_status = 0;

    /* private/protected vars */
    protected $texts;
    protected $caches = array();
    protected $shutdown_functions = array();


    /**
     * This implements the 'singleton' design pattern
     *
     * @param integer $mode Options to initialize with this instance. See rcube::INIT_WITH_* constants
     * @param string  $env  Environment name to run (e.g. live, dev, test)
     *
     * @return rcube The one and only instance
     */
    static function get_instance($mode = 0, $env = '')
    {
        if (!self::$instance) {
            self::$instance = new rcube($env);
            self::$instance->init($mode);
        }

        return self::$instance;
    }

    /**
     * Private constructor
     */
    protected function __construct($env = '')
    {
        // load configuration
        $this->config  = new rcube_config($env);
        $this->plugins = new rcube_dummy_plugin_api;

        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Initial startup function
     */
    protected function init($mode = 0)
    {
        // initialize syslog
        if ($this->config->get('log_driver') == 'syslog') {
            $syslog_id       = $this->config->get('syslog_id', 'roundcube');
            $syslog_facility = $this->config->get('syslog_facility', LOG_USER);
            openlog($syslog_id, LOG_ODELAY, $syslog_facility);
        }

        // connect to database
        if ($mode & self::INIT_WITH_DB) {
            $this->get_dbh();
        }

        // create plugin API and load plugins
        if ($mode & self::INIT_WITH_PLUGINS) {
            $this->plugins = rcube_plugin_api::get_instance();
        }
    }

    /**
     * Get the current database connection
     *
     * @return rcube_db Database object
     */
    public function get_dbh()
    {
        if (!$this->db) {
            $this->db = rcube_db::factory(
                $this->config->get('db_dsnw'),
                $this->config->get('db_dsnr'),
                $this->config->get('db_persistent')
            );

            $this->db->set_debug((bool)$this->config->get('sql_debug'));
        }

        return $this->db;
    }

    /**
     * Get global handle for memcache access
     *
     * @return object Memcache
     */
    public function get_memcache()
    {
        if (!isset($this->memcache)) {
            // no memcache support in PHP
            if (!class_exists('Memcache')) {
                $this->memcache = false;
                return false;
            }

            $this->memcache     = new Memcache;
            $this->memcache_init();

            // test connection and failover (will result in $this->mc_available == 0 on complete failure)
            $this->memcache->increment('__CONNECTIONTEST__', 1);  // NOP if key doesn't exist

            if (!$this->mc_available) {
                $this->memcache = false;
            }
        }

        return $this->memcache;
    }

    /**
     * Get global handle for memcache access
     *
     * @return object Memcache
     */
    protected function memcache_init()
    {
        $this->mc_available = 0;

        // add all configured hosts to pool
        $pconnect       = $this->config->get('memcache_pconnect', true);
        $timeout        = $this->config->get('memcache_timeout', 1);
        $retry_interval = $this->config->get('memcache_retry_interval', 15);

        foreach ($this->config->get('memcache_hosts', array()) as $host) {
            if (substr($host, 0, 7) != 'unix://') {
                list($host, $port) = explode(':', $host);
                if (!$port) $port = 11211;
            }
            else {
                $port = 0;
            }

            $this->mc_available += intval($this->memcache->addServer(
                $host, $port, $pconnect, 1, $timeout, $retry_interval, false, array($this, 'memcache_failure')));
        }
    }

    /**
     * Callback for memcache failure
     */
    public function memcache_failure($host, $port)
    {
        static $seen = array();

        // only report once
        if (!$seen["$host:$port"]++) {
            $this->mc_available--;
            self::raise_error(array(
                'code' => 604, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => "Memcache failure on host $host:$port"),
                true, false);
        }
    }

    /**
     * Initialize and get cache object
     *
     * @param string $name   Cache identifier
     * @param string $type   Cache type ('db', 'apc' or 'memcache')
     * @param string $ttl    Expiration time for cache items
     * @param bool   $packed Enables/disables data serialization
     *
     * @return rcube_cache Cache object
     */
    public function get_cache($name, $type='db', $ttl=0, $packed=true)
    {
        if (!isset($this->caches[$name]) && ($userid = $this->get_user_id())) {
            $this->caches[$name] = new rcube_cache($type, $userid, $name, $ttl, $packed);
        }

        return $this->caches[$name];
    }

    /**
     * Initialize and get shared cache object
     *
     * @param string $name   Cache identifier
     * @param bool   $packed Enables/disables data serialization
     *
     * @return rcube_cache_shared Cache object
     */
    public function get_cache_shared($name, $packed=true)
    {
        $shared_name = "shared_$name";

        if (!array_key_exists($shared_name, $this->caches)) {
            $opt  = strtolower($name) . '_cache';
            $type = $this->config->get($opt);
            $ttl  = $this->config->get($opt . '_ttl');

            if (!$type) {
                // cache is disabled
                return $this->caches[$shared_name] = null;
            }

            if ($ttl === null) {
                $ttl = $this->config->get('shared_cache_ttl', '10d');
            }

            $this->caches[$shared_name] = new rcube_cache_shared($type, $name, $ttl, $packed);
        }

        return $this->caches[$shared_name];
    }

    /**
     * Create SMTP object and connect to server
     *
     * @param boolean $connect True if connection should be established
     */
    public function smtp_init($connect = false)
    {
        $this->smtp = new rcube_smtp();

        if ($connect) {
            $this->smtp->connect();
        }
    }

    /**
     * Initialize and get storage object
     *
     * @return rcube_storage Storage object
     */
    public function get_storage()
    {
        // already initialized
        if (!is_object($this->storage)) {
            $this->storage_init();
        }

        return $this->storage;
    }

    /**
     * Initialize storage object
     */
    public function storage_init()
    {
        // already initialized
        if (is_object($this->storage)) {
            return;
        }

        $driver       = $this->config->get('storage_driver', 'imap');
        $driver_class = "rcube_{$driver}";

        if (!class_exists($driver_class)) {
            self::raise_error(array(
                'code' => 700, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Storage driver class ($driver) not found!"),
                true, true);
        }

        // Initialize storage object
        $this->storage = new $driver_class;

        // for backward compat. (deprecated, will be removed)
        $this->imap = $this->storage;

        // set class options
        $options = array(
            'auth_type'      => $this->config->get("{$driver}_auth_type", 'check'),
            'auth_cid'       => $this->config->get("{$driver}_auth_cid"),
            'auth_pw'        => $this->config->get("{$driver}_auth_pw"),
            'debug'          => (bool) $this->config->get("{$driver}_debug"),
            'force_caps'     => (bool) $this->config->get("{$driver}_force_caps"),
            'disabled_caps'  => $this->config->get("{$driver}_disabled_caps"),
            'socket_options' => $this->config->get("{$driver}_conn_options"),
            'timeout'        => (int) $this->config->get("{$driver}_timeout"),
            'skip_deleted'   => (bool) $this->config->get('skip_deleted'),
            'driver'         => $driver,
        );

        if (!empty($_SESSION['storage_host'])) {
            $options['host']     = $_SESSION['storage_host'];
            $options['user']     = $_SESSION['username'];
            $options['port']     = $_SESSION['storage_port'];
            $options['ssl']      = $_SESSION['storage_ssl'];
            $options['password'] = $this->decrypt($_SESSION['password']);
            $_SESSION[$driver.'_host'] = $_SESSION['storage_host'];
        }

        $options = $this->plugins->exec_hook("storage_init", $options);

        // for backward compat. (deprecated, to be removed)
        $options = $this->plugins->exec_hook("imap_init", $options);

        $this->storage->set_options($options);
        $this->set_storage_prop();

        // subscribe to 'storage_connected' hook for session logging
        if ($this->config->get('imap_log_session', false)) {
            $this->plugins->register_hook('storage_connected', array($this, 'storage_log_session'));
        }
    }

    /**
     * Set storage parameters.
     */
    protected function set_storage_prop()
    {
        $storage = $this->get_storage();

        // set pagesize from config
        $pagesize = $this->config->get('mail_pagesize');
        if (!$pagesize) {
            $pagesize = $this->config->get('pagesize', 50);
        }

        $storage->set_pagesize($pagesize);
        $storage->set_charset($this->config->get('default_charset', RCUBE_CHARSET));

        // enable caching of mail data
        $driver         = $this->config->get('storage_driver', 'imap');
        $storage_cache  = $this->config->get("{$driver}_cache");
        $messages_cache = $this->config->get('messages_cache');
        // for backward compatybility
        if ($storage_cache === null && $messages_cache === null && $this->config->get('enable_caching')) {
            $storage_cache  = 'db';
            $messages_cache = true;
        }

        if ($storage_cache) {
            $storage->set_caching($storage_cache);
        }
        if ($messages_cache) {
            $storage->set_messages_caching(true);
        }
    }

    /**
     * Set special folders type association.
     * This must be done AFTER connecting to the server!
     */
    protected function set_special_folders()
    {
        $storage = $this->get_storage();
        $folders = $storage->get_special_folders(true);
        $prefs   = array();

        // check SPECIAL-USE flags on IMAP folders
        foreach ($folders as $type => $folder) {
            $idx = $type . '_mbox';
            if ($folder !== $this->config->get($idx)) {
                $prefs[$idx] = $folder;
            }
        }

        // Some special folders differ, update user preferences
        if (!empty($prefs) && $this->user) {
            $this->user->save_prefs($prefs);
        }

        // create default folders (on login)
        if ($this->config->get('create_default_folders')) {
            $storage->create_default_folders();
        }
    }

    /**
     * Callback for IMAP connection events to log session identifiers
     */
    public function storage_log_session($args)
    {
        if (!empty($args['session']) && session_id()) {
            $this->write_log('imap_session', $args['session']);
        }
    }

    /**
     * Create session object and start the session.
     */
    public function session_init()
    {
        // session started (Installer?)
        if (session_id()) {
            return;
        }

        $sess_name   = $this->config->get('session_name');
        $sess_domain = $this->config->get('session_domain');
        $sess_path   = $this->config->get('session_path');
        $lifetime    = $this->config->get('session_lifetime', 0) * 60;
        $is_secure   = $this->config->get('use_https') || rcube_utils::https_check();

        // set session domain
        if ($sess_domain) {
            ini_set('session.cookie_domain', $sess_domain);
        }
        // set session path
        if ($sess_path) {
            ini_set('session.cookie_path', $sess_path);
        }
        // set session garbage collecting time according to session_lifetime
        if ($lifetime) {
            ini_set('session.gc_maxlifetime', $lifetime * 2);
        }

        ini_set('session.cookie_secure', $is_secure);
        ini_set('session.name', $sess_name ?: 'roundcube_sessid');
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);

        // get session driver instance
        $this->session = rcube_session::factory($this->config);
        $this->session->register_gc_handler(array($this, 'gc'));

        // start PHP session (if not in CLI mode)
        if ($_SERVER['REMOTE_ADDR']) {
            $this->session->start();
        }
    }

    /**
     * Garbage collector - cache/temp cleaner
     */
    public function gc()
    {
        rcube_cache::gc();
        rcube_cache_shared::gc();
        $this->get_storage()->cache_gc();

        $this->gc_temp();
    }

    /**
     * Garbage collector function for temp files.
     * Remove temp files older than two days
     */
    public function gc_temp()
    {
        $tmp = unslashify($this->config->get('temp_dir'));

        // expire in 48 hours by default
        $temp_dir_ttl = $this->config->get('temp_dir_ttl', '48h');
        $temp_dir_ttl = get_offset_sec($temp_dir_ttl);
        if ($temp_dir_ttl < 6*3600)
            $temp_dir_ttl = 6*3600;   // 6 hours sensible lower bound.

        $expire = time() - $temp_dir_ttl;

        if ($tmp && ($dir = opendir($tmp))) {
            while (($fname = readdir($dir)) !== false) {
                if ($fname[0] == '.') {
                    continue;
                }

                if (@filemtime($tmp.'/'.$fname) < $expire) {
                    @unlink($tmp.'/'.$fname);
                }
            }

            closedir($dir);
        }
    }

    /**
     * Runs garbage collector with probability based on
     * session settings. This is intended for environments
     * without a session.
     */
    public function gc_run()
    {
        $probability = (int) ini_get('session.gc_probability');
        $divisor     = (int) ini_get('session.gc_divisor');

        if ($divisor > 0 && $probability > 0) {
            $random = mt_rand(1, $divisor);
            if ($random <= $probability) {
                $this->gc();
            }
        }
    }

    /**
     * Get localized text in the desired language
     *
     * @param mixed  $attrib Named parameters array or label name
     * @param string $domain Label domain (plugin) name
     *
     * @return string Localized text
     */
    public function gettext($attrib, $domain = null)
    {
        // load localization files if not done yet
        if (empty($this->texts)) {
            $this->load_language();
        }

        // extract attributes
        if (is_string($attrib)) {
            $attrib = array('name' => $attrib);
        }

        $name = (string) $attrib['name'];

        // attrib contain text values: use them from now
        if (($setval = $attrib[strtolower($_SESSION['language'])]) || ($setval = $attrib['en_us'])) {
            $this->texts[$name] = $setval;
        }

        // check for text with domain
        if ($domain && ($text = $this->texts[$domain.'.'.$name])) {
        }
        // text does not exist
        else if (!($text = $this->texts[$name])) {
            return "[$name]";
        }

        // replace vars in text
        if (is_array($attrib['vars'])) {
            foreach ($attrib['vars'] as $var_key => $var_value) {
                $text = str_replace($var_key[0] != '$' ? '$'.$var_key : $var_key, $var_value, $text);
            }
        }

        // replace \n with real line break
        $text = strtr($text, array('\n' => "\n"));

        // case folding
        if (($attrib['uppercase'] && strtolower($attrib['uppercase']) == 'first') || $attrib['ucfirst']) {
            $case_mode = MB_CASE_TITLE;
        }
        else if ($attrib['uppercase']) {
            $case_mode = MB_CASE_UPPER;
        }
        else if ($attrib['lowercase']) {
            $case_mode = MB_CASE_LOWER;
        }

        if (isset($case_mode)) {
            $text = mb_convert_case($text, $case_mode);
        }

        return $text;
    }

    /**
     * Check if the given text label exists
     *
     * @param string $name       Label name
     * @param string $domain     Label domain (plugin) name or '*' for all domains
     * @param string $ref_domain Sets domain name if label is found
     *
     * @return boolean True if text exists (either in the current language or in en_US)
     */
    public function text_exists($name, $domain = null, &$ref_domain = null)
    {
        // load localization files if not done yet
        if (empty($this->texts)) {
            $this->load_language();
        }

        if (isset($this->texts[$name])) {
            $ref_domain = '';
            return true;
        }

        // any of loaded domains (plugins)
        if ($domain == '*') {
            foreach ($this->plugins->loaded_plugins() as $domain) {
                if (isset($this->texts[$domain.'.'.$name])) {
                    $ref_domain = $domain;
                    return true;
                }
            }
        }
        // specified domain
        else if ($domain) {
            $ref_domain = $domain;
            return isset($this->texts[$domain.'.'.$name]);
        }

        return false;
    }

    /**
     * Load a localization package
     *
     * @param string $lang  Language ID
     * @param array  $add   Additional text labels/messages
     * @param array  $merge Additional text labels/messages to merge
     */
    public function load_language($lang = null, $add = array(), $merge = array())
    {
        $lang = $this->language_prop($lang ?: $_SESSION['language']);

        // load localized texts
        if (empty($this->texts) || $lang != $_SESSION['language']) {
            $this->texts = array();

            // handle empty lines after closing PHP tag in localization files
            ob_start();

            // get english labels (these should be complete)
            @include(RCUBE_LOCALIZATION_DIR . 'en_US/labels.inc');
            @include(RCUBE_LOCALIZATION_DIR . 'en_US/messages.inc');

            if (is_array($labels))
                $this->texts = $labels;
            if (is_array($messages))
                $this->texts = array_merge($this->texts, $messages);

            // include user language files
            if ($lang != 'en' && $lang != 'en_US' && is_dir(RCUBE_LOCALIZATION_DIR . $lang)) {
                include_once(RCUBE_LOCALIZATION_DIR . $lang . '/labels.inc');
                include_once(RCUBE_LOCALIZATION_DIR . $lang . '/messages.inc');

                if (is_array($labels))
                    $this->texts = array_merge($this->texts, $labels);
                if (is_array($messages))
                    $this->texts = array_merge($this->texts, $messages);
            }

            ob_end_clean();

            $_SESSION['language'] = $lang;
        }

        // append additional texts (from plugin)
        if (is_array($add) && !empty($add)) {
            $this->texts += $add;
        }

        // merge additional texts (from plugin)
        if (is_array($merge) && !empty($merge)) {
            $this->texts = array_merge($this->texts, $merge);
        }
    }

    /**
     * Check the given string and return a valid language code
     *
     * @param string $lang Language code
     *
     * @return string Valid language code
     */
    protected function language_prop($lang)
    {
        static $rcube_languages, $rcube_language_aliases;

        // user HTTP_ACCEPT_LANGUAGE if no language is specified
        if (empty($lang) || $lang == 'auto') {
            $accept_langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $lang         = $accept_langs[0];

            if (preg_match('/^([a-z]+)[_-]([a-z]+)$/i', $lang, $m)) {
                $lang = $m[1] . '_' . strtoupper($m[2]);
            }
        }

        if (empty($rcube_languages)) {
            @include(RCUBE_LOCALIZATION_DIR . 'index.inc');
        }

        // check if we have an alias for that language
        if (!isset($rcube_languages[$lang]) && isset($rcube_language_aliases[$lang])) {
            $lang = $rcube_language_aliases[$lang];
        }
        // try the first two chars
        else if (!isset($rcube_languages[$lang])) {
            $short = substr($lang, 0, 2);

            // check if we have an alias for the short language code
            if (!isset($rcube_languages[$short]) && isset($rcube_language_aliases[$short])) {
                $lang = $rcube_language_aliases[$short];
            }
            // expand 'nn' to 'nn_NN'
            else if (!isset($rcube_languages[$short])) {
                $lang = $short.'_'.strtoupper($short);
            }
        }

        if (!isset($rcube_languages[$lang]) || !is_dir(RCUBE_LOCALIZATION_DIR . $lang)) {
            $lang = 'en_US';
        }

        return $lang;
    }

    /**
     * Read directory program/localization and return a list of available languages
     *
     * @return array List of available localizations
     */
    public function list_languages()
    {
        static $sa_languages = array();

        if (!sizeof($sa_languages)) {
            @include(RCUBE_LOCALIZATION_DIR . 'index.inc');

            if ($dh = @opendir(RCUBE_LOCALIZATION_DIR)) {
                while (($name = readdir($dh)) !== false) {
                    if ($name[0] == '.' || !is_dir(RCUBE_LOCALIZATION_DIR . $name)) {
                        continue;
                    }

                    if ($label = $rcube_languages[$name]) {
                        $sa_languages[$name] = $label;
                    }
                }
                closedir($dh);
            }
        }

        return $sa_languages;
    }

    /**
     * Encrypt a string
     *
     * @param string  $clear  Clear text input
     * @param string  $key    Encryption key to retrieve from the configuration, defaults to 'des_key'
     * @param boolean $base64 Whether or not to base64_encode() the result before returning
     *
     * @return string Encrypted text
     */
    public function encrypt($clear, $key = 'des_key', $base64 = true)
    {
        if (!is_string($clear) || !strlen($clear)) {
            return '';
        }

        $ckey   = $this->config->get_crypto_key($key);
        $method = $this->config->get_crypto_method();
        $opts   = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;
        $iv     = rcube_utils::random_bytes(openssl_cipher_iv_length($method), true);
        $cipher = $iv . openssl_encrypt($clear, $method, $ckey, $opts, $iv);

        return $base64 ? base64_encode($cipher) : $cipher;
    }

    /**
     * Decrypt a string
     *
     * @param string  $cipher Encrypted text
     * @param string  $key    Encryption key to retrieve from the configuration, defaults to 'des_key'
     * @param boolean $base64 Whether or not input is base64-encoded
     *
     * @return string Decrypted text
     */
    public function decrypt($cipher, $key = 'des_key', $base64 = true)
    {
        if (!$cipher) {
            return '';
        }

        $cipher  = $base64 ? base64_decode($cipher) : $cipher;
        $ckey    = $this->config->get_crypto_key($key);
        $method  = $this->config->get_crypto_method();
        $opts    = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;
        $iv_size = openssl_cipher_iv_length($method);
        $iv      = substr($cipher, 0, $iv_size);

        // session corruption? (#1485970)
        if (strlen($iv) < $iv_size) {
            return '';
        }

        $cipher = substr($cipher, $iv_size);
        $clear  = openssl_decrypt($cipher, $method, $ckey, $opts, $iv);

        return $clear;
    }

    /**
     * Returns session token for secure URLs
     *
     * @param bool $generate Generate token if not exists in session yet
     *
     * @return string|bool Token string, False when disabled
     */
    public function get_secure_url_token($generate = false)
    {
        if ($len = $this->config->get('use_secure_urls')) {
            if (empty($_SESSION['secure_token']) && $generate) {
                // generate x characters long token
                $length = $len > 1 ? $len : 16;
                $token  = rcube_utils::random_bytes($length);

                $plugin = $this->plugins->exec_hook('secure_token',
                    array('value' => $token, 'length' => $length));

                $_SESSION['secure_token'] = $plugin['value'];
            }

            return $_SESSION['secure_token'];
        }

        return false;
    }

    /**
     * Generate a unique token to be used in a form request
     *
     * @return string The request token
     */
    public function get_request_token()
    {
        if (empty($_SESSION['request_token'])) {
            $plugin = $this->plugins->exec_hook('request_token', array(
                'value' => rcube_utils::random_bytes(32)));

            $_SESSION['request_token'] = $plugin['value'];
        }

        return $_SESSION['request_token'];
    }

    /**
     * Check if the current request contains a valid token.
     * Empty requests aren't checked until use_secure_urls is set.
     *
     * @param int $mode Request method
     *
     * @return boolean True if request token is valid false if not
     */
    public function check_request($mode = rcube_utils::INPUT_POST)
    {
        // check secure token in URL if enabled
        if ($token = $this->get_secure_url_token()) {
            foreach (explode('/', preg_replace('/[?#&].*$/', '', $_SERVER['REQUEST_URI'])) as $tok) {
                if ($tok == $token) {
                    return true;
                }
            }

            $this->request_status = self::REQUEST_ERROR_URL;

            return false;
        }

        $sess_tok = $this->get_request_token();

        // ajax requests
        if (rcube_utils::request_header('X-Roundcube-Request') == $sess_tok) {
            return true;
        }

        // skip empty requests
        if (($mode == rcube_utils::INPUT_POST && empty($_POST))
            || ($mode == rcube_utils::INPUT_GET && empty($_GET))
        ) {
            return true;
        }

        // default method of securing requests
        $token   = rcube_utils::get_input_value('_token', $mode);
        $sess_id = $_COOKIE[ini_get('session.name')];

        if (empty($sess_id) || $token != $sess_tok) {
            $this->request_status = self::REQUEST_ERROR_TOKEN;
            return false;
        }

        return true;
    }

    /**
     * Build a valid URL to this instance of Roundcube
     *
     * @param mixed $p Either a string with the action or url parameters as key-value pairs
     *
     * @return string Valid application URL
     */
    public function url($p)
    {
        // STUB: should be overloaded by the application
        return '';
    }

    /**
     * Function to be executed in script shutdown
     * Registered with register_shutdown_function()
     */
    public function shutdown()
    {
        foreach ($this->shutdown_functions as $function) {
            call_user_func($function);
        }

        // write session data as soon as possible and before
        // closing database connection, don't do this before
        // registered shutdown functions, they may need the session
        // Note: this will run registered gc handlers (ie. cache gc)
        if ($_SERVER['REMOTE_ADDR'] && is_object($this->session)) {
            $this->session->write_close();
        }

        if (is_object($this->smtp)) {
            $this->smtp->disconnect();
        }

        foreach ($this->caches as $cache) {
            if (is_object($cache)) {
                $cache->close();
            }
        }

        if (is_object($this->storage)) {
            $this->storage->close();
        }

        if ($this->config->get('log_driver') == 'syslog') {
            closelog();
        }
    }

    /**
     * Registers shutdown function to be executed on shutdown.
     * The functions will be executed before destroying any
     * objects like smtp, imap, session, etc.
     *
     * @param callback $function Function callback
     */
    public function add_shutdown_function($function)
    {
        $this->shutdown_functions[] = $function;
    }

    /**
     * When you're going to sleep the script execution for a longer time
     * it is good to close all external connections (sql, memcache, SMTP, IMAP).
     *
     * No action is required on wake up, all connections will be
     * re-established automatically.
     */
    public function sleep()
    {
        foreach ($this->caches as $cache) {
            if (is_object($cache)) {
                $cache->close();
            }
        }

        if ($this->storage) {
            $this->storage->close();
        }

        if ($this->db) {
            $this->db->closeConnection();
        }

        if ($this->memcache) {
            $this->memcache->close();
            // after close() need to re-init memcache
            $this->memcache_init();
        }

        if ($this->smtp) {
            $this->smtp->disconnect();
        }
    }

    /**
     * Quote a given string.
     * Shortcut function for rcube_utils::rep_specialchars_output()
     *
     * @return string HTML-quoted string
     */
    public static function Q($str, $mode = 'strict', $newlines = true)
    {
        return rcube_utils::rep_specialchars_output($str, 'html', $mode, $newlines);
    }

    /**
     * Quote a given string for javascript output.
     * Shortcut function for rcube_utils::rep_specialchars_output()
     *
     * @return string JS-quoted string
     */
    public static function JQ($str)
    {
        return rcube_utils::rep_specialchars_output($str, 'js');
    }

    /**
     * Construct shell command, execute it and return output as string.
     * Keywords {keyword} are replaced with arguments
     *
     * @param $cmd    Format string with {keywords} to be replaced
     * @param $values (zero, one or more arrays can be passed)
     *
     * @return output of command. shell errors not detectable
     */
    public static function exec(/* $cmd, $values1 = array(), ... */)
    {
        $args   = func_get_args();
        $cmd    = array_shift($args);
        $values = $replacements = array();

        // merge values into one array
        foreach ($args as $arg) {
            $values += (array)$arg;
        }

        preg_match_all('/({(-?)([a-z]\w*)})/', $cmd, $matches, PREG_SET_ORDER);
        foreach ($matches as $tags) {
            list(, $tag, $option, $key) = $tags;
            $parts = array();

            if ($option) {
                foreach ((array)$values["-$key"] as $key => $value) {
                    if ($value === true || $value === false || $value === null) {
                        $parts[] = $value ? $key : "";
                    }
                    else {
                        foreach ((array)$value as $val) {
                            $parts[] = "$key " . escapeshellarg($val);
                        }
                    }
                }
            }
            else {
                foreach ((array)$values[$key] as $value) {
                    $parts[] = escapeshellarg($value);
                }
            }

            $replacements[$tag] = join(" ", $parts);
        }

        // use strtr behaviour of going through source string once
        $cmd = strtr($cmd, $replacements);

        return (string)shell_exec($cmd);
    }

    /**
     * Print or write debug messages
     *
     * @param mixed Debug message or data
     */
    public static function console()
    {
        $args = func_get_args();

        if (class_exists('rcube', false)) {
            $rcube  = self::get_instance();
            $plugin = $rcube->plugins->exec_hook('console', array('args' => $args));
            if ($plugin['abort']) {
                return;
            }

            $args = $plugin['args'];
        }

        $msg = array();
        foreach ($args as $arg) {
            $msg[] = !is_string($arg) ? var_export($arg, true) : $arg;
        }

        self::write_log('console', join(";\n", $msg));
    }

    /**
     * Append a line to a logfile in the logs directory.
     * Date will be added automatically to the line.
     *
     * @param string $name Name of the log file
     * @param mixed  $line Line to append
     *
     * @return bool True on success, False on failure
     */
    public static function write_log($name, $line)
    {
        if (!is_string($line)) {
            $line = var_export($line, true);
        }

        $date_format = $log_driver = $session_key = null;
        if (self::$instance) {
            $date_format = self::$instance->config->get('log_date_format');
            $log_driver  = self::$instance->config->get('log_driver');
            $session_key = intval(self::$instance->config->get('log_session_id', 8));
        }

        $date = rcube_utils::date_format($date_format);

        // trigger logging hook
        if (is_object(self::$instance) && is_object(self::$instance->plugins)) {
            $log = self::$instance->plugins->exec_hook('write_log',
                array('name' => $name, 'date' => $date, 'line' => $line));

            $name = $log['name'];
            $line = $log['line'];
            $date = $log['date'];

            if ($log['abort']) {
                return true;
            }
        }

        // add session ID to the log
        if ($session_key > 0 && ($sess = session_id())) {
            $line = '<' . substr($sess, 0, $session_key) . '> ' . $line;
        }

        if ($log_driver == 'syslog') {
            $prio = $name == 'errors' ? LOG_ERR : LOG_INFO;
            return syslog($prio, $line);
        }

        // log_driver == 'file' is assumed here

        $line = sprintf("[%s]: %s\n", $date, $line);

        // per-user logging is activated
        if (self::$instance && self::$instance->config->get('per_user_logging', false) && self::$instance->get_user_id()) {
            $log_dir = self::$instance->get_user_log_dir();
            if (empty($log_dir) && $name != 'errors') {
                return false;
            }
        }

        if (empty($log_dir)) {
            if (!empty($log['dir'])) {
                $log_dir = $log['dir'];
            }
            else if (self::$instance) {
                $log_dir = self::$instance->config->get('log_dir');
            }
        }

        if (empty($log_dir)) {
            $log_dir = RCUBE_INSTALL_PATH . 'logs';
        }

        return file_put_contents("$log_dir/$name", $line, FILE_APPEND) !== false;
    }

    /**
     * Throw system error (and show error page).
     *
     * @param array $arg Named parameters
     *      - code:    Error code (required)
     *      - type:    Error type [php|db|imap|javascript]
     *      - message: Error message
     *      - file:    File where error occurred
     *      - line:    Line where error occurred
     * @param boolean $log       True to log the error
     * @param boolean $terminate Terminate script execution
     */
    public static function raise_error($arg = array(), $log = false, $terminate = false)
    {
        // handle PHP exceptions
        if (is_object($arg) && is_a($arg, 'Exception')) {
            $arg = array(
                'code' => $arg->getCode(),
                'line' => $arg->getLine(),
                'file' => $arg->getFile(),
                'message' => $arg->getMessage(),
            );
        }
        else if (is_string($arg)) {
            $arg = array('message' => $arg);
        }

        if (empty($arg['code'])) {
            $arg['code'] = 500;
        }

        // installer
        if (class_exists('rcmail_install', false)) {
            $rci = rcmail_install::get_instance();
            $rci->raise_error($arg);
            return;
        }

        $cli = php_sapi_name() == 'cli';

        if (($log || $terminate) && !$cli && $arg['message']) {
            $arg['fatal'] = $terminate;
            self::log_bug($arg);
        }

        // terminate script
        if ($terminate) {
            // display error page
            if (is_object(self::$instance->output)) {
                self::$instance->output->raise_error($arg['code'], $arg['message']);
            }
            else if ($cli) {
                fwrite(STDERR, 'ERROR: ' . $arg['message']);
            }

            exit(1);
        }
        else if ($cli) {
            fwrite(STDERR, 'ERROR: ' . $arg['message']);
        }
    }

    /**
     * Report error according to configured debug_level
     *
     * @param array $arg_arr Named parameters
     * @see self::raise_error()
     */
    public static function log_bug($arg_arr)
    {
        $program = strtoupper($arg_arr['type'] ?: 'php');
        $level   = self::get_instance()->config->get('debug_level');

        // disable errors for ajax requests, write to log instead (#1487831)
        if (($level & 4) && !empty($_REQUEST['_remote'])) {
            $level = ($level ^ 4) | 1;
        }

        // write error to local log file
        if (($level & 1) || !empty($arg_arr['fatal'])) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                foreach (array('_task', '_action') as $arg) {
                    if ($_POST[$arg] && !$_GET[$arg]) {
                        $post_query[$arg] = $_POST[$arg];
                    }
                }

                if (!empty($post_query)) {
                    $post_query = (strpos($_SERVER['REQUEST_URI'], '?') != false ? '&' : '?')
                        . http_build_query($post_query, '', '&');
                }
            }

            $log_entry = sprintf("%s Error: %s%s (%s %s)",
                $program,
                $arg_arr['message'],
                $arg_arr['file'] ? sprintf(' in %s on line %d', $arg_arr['file'], $arg_arr['line']) : '',
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI'] . $post_query);

            if (!self::write_log('errors', $log_entry)) {
                // send error to PHPs error handler if write_log didn't succeed
                trigger_error($arg_arr['message'], E_USER_WARNING);
            }
        }

        // report the bug to the global bug reporting system
        if ($level & 2) {
            // TODO: Send error via HTTP
        }

        // show error if debug_mode is on
        if ($level & 4) {
            print "<b>$program Error";

            if (!empty($arg_arr['file']) && !empty($arg_arr['line'])) {
                print " in $arg_arr[file] ($arg_arr[line])";
            }

            print ':</b>&nbsp;';
            print nl2br($arg_arr['message']);
            print '<br />';
            flush();
        }
    }

    /**
     * Write debug info to the log
     *
     * @param string $engine Engine type - file name (memcache, apc)
     * @param string $data   Data string to log
     * @param bool   $result Operation result
     */
    public static function debug($engine, $data, $result = null)
    {
        static $debug_counter;

        $line = '[' . (++$debug_counter[$engine]) . '] ' . $data;

        if (($len = strlen($line)) > self::DEBUG_LINE_LENGTH) {
            $diff = $len - self::DEBUG_LINE_LENGTH;
            $line = substr($line, 0, self::DEBUG_LINE_LENGTH) . "... [truncated $diff bytes]";
        }

        if ($result !== null) {
            $line .= ' [' . ($result ? 'TRUE' : 'FALSE') . ']';
        }

        self::write_log($engine, $line);
    }

    /**
     * Returns current time (with microseconds).
     *
     * @return float Current time in seconds since the Unix
     */
    public static function timer()
    {
        return microtime(true);
    }

    /**
     * Logs time difference according to provided timer
     *
     * @param float  $timer Timer (self::timer() result)
     * @param string $label Log line prefix
     * @param string $dest  Log file name
     *
     * @see self::timer()
     */
    public static function print_timer($timer, $label = 'Timer', $dest = 'console')
    {
        static $print_count = 0;

        $print_count++;
        $now  = self::timer();
        $diff = $now - $timer;

        if (empty($label)) {
            $label = 'Timer '.$print_count;
        }

        self::write_log($dest, sprintf("%s: %0.4f sec", $label, $diff));
    }

    /**
     * Setter for system user object
     *
     * @param rcube_user Current user instance
     */
    public function set_user($user)
    {
        if (is_object($user)) {
            $this->user = $user;

            // overwrite config with user preferences
            $this->config->set_user_prefs((array)$this->user->get_prefs());
        }
    }

    /**
     * Getter for logged user ID.
     *
     * @return mixed User identifier
     */
    public function get_user_id()
    {
        if (is_object($this->user)) {
            return $this->user->ID;
        }
        else if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }

        return null;
    }

    /**
     * Getter for logged user name.
     *
     * @return string User name
     */
    public function get_user_name()
    {
        if (is_object($this->user)) {
            return $this->user->get_username();
        }
        else if (isset($_SESSION['username'])) {
            return $_SESSION['username'];
        }
    }

    /**
     * Getter for logged user email (derived from user name not identity).
     *
     * @return string User email address
     */
    public function get_user_email()
    {
        if (is_object($this->user)) {
            return $this->user->get_username('mail');
        }
    }

    /**
     * Getter for logged user password.
     *
     * @return string User password
     */
    public function get_user_password()
    {
        if ($this->password) {
            return $this->password;
        }
        else if ($_SESSION['password']) {
            return $this->decrypt($_SESSION['password']);
        }
    }

    /**
     * Get the per-user log directory
     */
    protected function get_user_log_dir()
    {
        $log_dir      = $this->config->get('log_dir', RCUBE_INSTALL_PATH . 'logs');
        $user_name    = $this->get_user_name();
        $user_log_dir = $log_dir . '/' . $user_name;

        return !empty($user_name) && is_writable($user_log_dir) ? $user_log_dir : false;
    }

    /**
     * Getter for logged user language code.
     *
     * @return string User language code
     */
    public function get_user_language()
    {
        if (is_object($this->user)) {
            return $this->user->language;
        }
        else if (isset($_SESSION['language'])) {
            return $_SESSION['language'];
        }
    }

    /**
     * Unique Message-ID generator.
     *
     * @param string $sender Optional sender e-mail address
     *
     * @return string Message-ID
     */
    public function gen_message_id($sender = null)
    {
        $local_part  = md5(uniqid('rcube'.mt_rand(), true));
        $domain_part = '';

        if ($sender && preg_match('/@([^\s]+\.[a-z0-9-]+)/', $sender, $m)) {
            $domain_part = $m[1];
        }
        else {
            $domain_part = $this->user->get_username('domain');
        }

        // Try to find FQDN, some spamfilters doesn't like 'localhost' (#1486924)
        if (!preg_match('/\.[a-z0-9-]+$/i', $domain_part)) {
            foreach (array($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']) as $host) {
                $host = preg_replace('/:[0-9]+$/', '', $host);
                if ($host && preg_match('/\.[a-z]+$/i', $host)) {
                    $domain_part = $host;
                    break;
                }
            }
        }

        return sprintf('<%s@%s>', $local_part, $domain_part);
    }

    /**
     * Send the given message using the configured method.
     *
     * @param object $message   Reference to Mail_MIME object
     * @param string $from      Sender address string
     * @param array  $mailto    Array of recipient address strings
     * @param array  $error     SMTP error array (reference)
     * @param string $body_file Location of file with saved message body (reference),
     *                          used when delay_file_io is enabled
     * @param array  $options   SMTP options (e.g. DSN request)
     * @param bool   $disconnect Close SMTP connection ASAP
     *
     * @return boolean Send status.
     */
    public function deliver_message(&$message, $from, $mailto, &$error,
        &$body_file = null, $options = null, $disconnect = false)
    {
        $plugin = $this->plugins->exec_hook('message_before_send', array(
            'message' => $message,
            'from'    => $from,
            'mailto'  => $mailto,
            'options' => $options,
        ));

        if ($plugin['abort']) {
            if (!empty($plugin['error'])) {
                $error = $plugin['error'];
            }
            if (!empty($plugin['body_file'])) {
                $body_file = $plugin['body_file'];
            }

            return isset($plugin['result']) ? $plugin['result'] : false;
        }

        $from    = $plugin['from'];
        $mailto  = $plugin['mailto'];
        $options = $plugin['options'];
        $message = $plugin['message'];
        $headers = $message->headers();

        // generate list of recipients
        $a_recipients = (array) $mailto;

        if (strlen($headers['Cc'])) {
            $a_recipients[] = $headers['Cc'];
        }
        if (strlen($headers['Bcc'])) {
            $a_recipients[] = $headers['Bcc'];
        }

        // remove Bcc header and get the whole head of the message as string
        $smtp_headers = $message->txtHeaders(array('Bcc' => null), true);

        if ($message->getParam('delay_file_io')) {
            // use common temp dir
            $temp_dir    = $this->config->get('temp_dir');
            $body_file   = tempnam($temp_dir, 'rcmMsg');
            $mime_result = $message->saveMessageBody($body_file);

            if (is_a($mime_result, 'PEAR_Error')) {
                self::raise_error(array('code' => 650, 'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Could not create message: ".$mime_result->getMessage()),
                    true, false);
                return false;
            }

            $msg_body = fopen($body_file, 'r');
        }
        else {
            $msg_body = $message->get();
        }

        // initialize SMTP connection
        if (!is_object($this->smtp)) {
            $this->smtp_init(true);
        }

        // send message
        $sent     = $this->smtp->send_mail($from, $a_recipients, $smtp_headers, $msg_body, $options);
        $response = $this->smtp->get_response();
        $error    = $this->smtp->get_error();

        if (!$sent) {
            self::raise_error(array('code' => 800, 'type' => 'smtp',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => join("\n", $response)), true, false);

            // allow plugins to catch sending errors with the same parameters as in 'message_before_send'
            $this->plugins->exec_hook('message_send_error', $plugin + array('error' => $error));
        }
        else {
            $this->plugins->exec_hook('message_sent', array('headers' => $headers, 'body' => $msg_body));

            // remove MDN headers after sending
            unset($headers['Return-Receipt-To'], $headers['Disposition-Notification-To']);

            if ($this->config->get('smtp_log')) {
                // get all recipient addresses
                $mailto = implode(',', $a_recipients);
                $mailto = rcube_mime::decode_address_list($mailto, null, false, null, true);

                self::write_log('sendmail', sprintf("User %s [%s]; Message for %s; %s",
                    $this->user->get_username(),
                    rcube_utils::remote_addr(),
                    implode(', ', $mailto),
                    !empty($response) ? join('; ', $response) : ''));
            }
        }

        if (is_resource($msg_body)) {
            fclose($msg_body);
        }

        if ($disconnect) {
            $this->smtp->disconnect();
        }

        $message->headers($headers, true);

        return $sent;
    }
}


/**
 * Lightweight plugin API class serving as a dummy if plugins are not enabled
 *
 * @package    Framework
 * @subpackage Core
 */
class rcube_dummy_plugin_api
{
    /**
     * Triggers a plugin hook.
     * @see rcube_plugin_api::exec_hook()
     */
    public function exec_hook($hook, $args = array())
    {
        return $args;
    }
}
