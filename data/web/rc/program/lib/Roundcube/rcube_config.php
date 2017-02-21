<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Class to read configuration settings                                |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Configuration class for Roundcube
 *
 * @package    Framework
 * @subpackage Core
 */
class rcube_config
{
    const DEFAULT_SKIN = 'larry';

    private $env       = '';
    private $paths     = array();
    private $prop      = array();
    private $errors    = array();
    private $userprefs = array();


    /**
     * Renamed options
     *
     * @var array
     */
    private $legacy_props = array(
        // new name => old name
        'mail_pagesize'        => 'pagesize',
        'addressbook_pagesize' => 'pagesize',
        'reply_mode'           => 'top_posting',
        'refresh_interval'     => 'keep_alive',
        'min_refresh_interval' => 'min_keep_alive',
        'messages_cache_ttl'   => 'message_cache_lifetime',
        'mail_read_time'       => 'preview_pane_mark_read',
        'redundant_attachments_cache_ttl' => 'redundant_attachments_memcache_ttl',
    );

    /**
     * Object constructor
     *
     * @param string $env Environment suffix for config files to load
     */
    public function __construct($env = '')
    {
        $this->env = $env;

        if ($paths = getenv('RCUBE_CONFIG_PATH')) {
            $this->paths = explode(PATH_SEPARATOR, $paths);
            // make all paths absolute
            foreach ($this->paths as $i => $path) {
                if (!rcube_utils::is_absolute_path($path)) {
                    if ($realpath = realpath(RCUBE_INSTALL_PATH . $path)) {
                        $this->paths[$i] = unslashify($realpath) . '/';
                    }
                    else {
                        unset($this->paths[$i]);
                    }
                }
                else {
                    $this->paths[$i] = unslashify($path) . '/';
                }
            }
        }

        if (defined('RCUBE_CONFIG_DIR') && !in_array(RCUBE_CONFIG_DIR, $this->paths)) {
            $this->paths[] = RCUBE_CONFIG_DIR;
        }

        if (empty($this->paths)) {
            $this->paths[] = RCUBE_INSTALL_PATH . 'config/';
        }

        $this->load();

        // Defaults, that we do not require you to configure,
        // but contain information that is used in various locations in the code:
        if (empty($this->prop['contactlist_fields'])) {
            $this->set('contactlist_fields', array('name', 'firstname', 'surname', 'email'));
        }
    }

    /**
     * @brief Guess the type the string may fit into.
     *
     * Look inside the string to determine what type might be best as a container.
     *
     * @param mixed $value The value to inspect
     *
     * @return The guess at the type.
     */
    private function guess_type($value)
    {
        $type = 'string';

        // array requires hint to be passed.

        if (preg_match('/^[-+]?(\d+(\.\d*)?|\.\d+)([eE][-+]?\d+)?$/', $value) !== false) {
            $type = 'double';
        }
        else if (preg_match('/^\d+$/', $value) !== false) {
            $type = 'integer';
        }
        else if (preg_match('/(t(rue)?)|(f(alse)?)/i', $value) !== false) {
            $type = 'boolean';
        }

        return $type;
    }

    /**
     * @brief Parse environment variable into PHP type.
     *
     * Perform an appropriate parsing of the string to create the desired PHP type.
     *
     * @param string $string String to parse into PHP type
     * @param string $type   Type of value to return
     *
     * @return Appropriately typed interpretation of $string.
     */
    private function parse_env($string, $type)
    {
        $_ = $string;

        switch ($type) {
        case 'boolean':
            $_ = (boolean) $_;
            break;
        case 'integer':
            $_ = (integer) $_;
            break;
        case 'double':
            $_ = (double) $_;
            break;
        case 'string':
            break;
        case 'array':
            $_ = json_decode($_, true);
            break;
        case 'object':
            $_ = json_decode($_, false);
            break;
        case 'resource':
        case 'NULL':
        default:
            $_ = $this->parse_env($_, $this->guess_type($_));
        }

        return $_;
    }

    /**
     * @brief Get environment variable value.
     *
     * Retrieve an environment variable's value or if it's not found, return the
     * provided default value.
     *
     * @param string $varname       Environment variable name
     * @param mixed  $default_value Default value to return if necessary
     * @param string $type          Type of value to return
     *
     * @return Value of the environment variable or default if not found.
     */
    private function getenv_default($varname, $default_value, $type = null)
    {
        $value = getenv($varname);

        if ($value === false) {
            $value = $default_value;
        }
        else {
            $value = $this->parse_env($value, $type ?: gettype($default_value));
        }

        return $value;
    }

    /**
     * Load config from local config file
     *
     * @todo Remove global $CONFIG
     */
    private function load()
    {
        // Load default settings
        if (!$this->load_from_file('defaults.inc.php')) {
            $this->errors[] = 'defaults.inc.php was not found.';
        }

        // load main config file
        if (!$this->load_from_file('config.inc.php')) {
            // Old configuration files
            if (!$this->load_from_file('main.inc.php') || !$this->load_from_file('db.inc.php')) {
                $this->errors[] = 'config.inc.php was not found.';
            }
            else if (rand(1,100) == 10) {  // log warning on every 100th request (average)
                trigger_error("config.inc.php was not found. Please migrate your config by running bin/update.sh", E_USER_WARNING);
            }
        }

        // load host-specific configuration
        $this->load_host_config();

        // set skin (with fallback to old 'skin_path' property)
        if (empty($this->prop['skin'])) {
            if (!empty($this->prop['skin_path'])) {
                $this->prop['skin'] = str_replace('skins/', '', unslashify($this->prop['skin_path']));
            }
            else {
                $this->prop['skin'] = self::DEFAULT_SKIN;
            }
        }

        // larry is the new default skin :-)
        if ($this->prop['skin'] == 'default') {
            $this->prop['skin'] = self::DEFAULT_SKIN;
        }

        // fix paths
        foreach (array('log_dir' => 'logs', 'temp_dir' => 'temp') as $key => $dir) {
            foreach (array($this->prop[$key], '../' . $this->prop[$key], RCUBE_INSTALL_PATH . $dir) as $path) {
                if ($path && ($realpath = realpath(unslashify($path)))) {
                    $this->prop[$key] = $realpath;
                    break;
                }
            }
        }

        // fix default imap folders encoding
        foreach (array('drafts_mbox', 'junk_mbox', 'sent_mbox', 'trash_mbox') as $folder) {
            $this->prop[$folder] = rcube_charset::convert($this->prop[$folder], RCUBE_CHARSET, 'UTF7-IMAP');
        }

        // set PHP error logging according to config
        if ($this->prop['debug_level'] & 1) {
            ini_set('log_errors', 1);

            if ($this->prop['log_driver'] == 'syslog') {
                ini_set('error_log', 'syslog');
            }
            else {
                ini_set('error_log', $this->prop['log_dir'].'/errors');
            }
        }

        // enable display_errors in 'show' level, but not for ajax requests
        ini_set('display_errors', intval(empty($_REQUEST['_remote']) && ($this->prop['debug_level'] & 4)));

        // remove deprecated properties
        unset($this->prop['dst_active']);

        // export config data
        $GLOBALS['CONFIG'] = &$this->prop;
    }

    /**
     * Load a host-specific config file if configured
     * This will merge the host specific configuration with the given one
     */
    private function load_host_config()
    {
        if (empty($this->prop['include_host_config'])) {
            return;
        }

        foreach (array('HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR') as $key) {
            $fname = null;
            $name  = $_SERVER[$key];

            if (!$name) {
                continue;
            }

            if (is_array($this->prop['include_host_config'])) {
                $fname = $this->prop['include_host_config'][$name];
            }
            else {
                $fname = preg_replace('/[^a-z0-9\.\-_]/i', '', $name) . '.inc.php';
            }

            if ($fname && $this->load_from_file($fname)) {
                return;
            }
        }
    }

    /**
     * Read configuration from a file
     * and merge with the already stored config values
     *
     * @param string $file Name of the config file to be loaded
     *
     * @return booelan True on success, false on failure
     */
    public function load_from_file($file)
    {
        $success = false;

        foreach ($this->resolve_paths($file) as $fpath) {
            if ($fpath && is_file($fpath) && is_readable($fpath)) {
                // use output buffering, we don't need any output here
                ob_start();
                include($fpath);
                ob_end_clean();

                if (is_array($config)) {
                    $this->merge($config);
                    $success = true;
                }
                // deprecated name of config variable
                if (is_array($rcmail_config)) {
                    $this->merge($rcmail_config);
                    $success = true;
                }
            }
        }

        return $success;
    }

    /**
     * Helper method to resolve absolute paths to the given config file.
     * This also takes the 'env' property into account.
     *
     * @param string  $file    Filename or absolute file path
     * @param boolean $use_env Return -$env file path if exists
     *
     * @return array List of candidates in config dir path(s)
     */
    public function resolve_paths($file, $use_env = true)
    {
        $files    = array();
        $abs_path = rcube_utils::is_absolute_path($file);

        foreach ($this->paths as $basepath) {
            $realpath = $abs_path ? $file : realpath($basepath . '/' . $file);

            // check if <file>-env.ini exists
            if ($realpath && $use_env && !empty($this->env)) {
                $envfile = preg_replace('/\.(inc.php)$/', '-' . $this->env . '.\\1', $realpath);
                if (is_file($envfile)) {
                    $realpath = $envfile;
                }
            }

            if ($realpath) {
                $files[] = $realpath;

                // no need to continue the loop if an absolute file path is given
                if ($abs_path) {
                    break;
                }
            }
        }

        return $files;
    }

    /**
     * Getter for a specific config parameter
     *
     * @param  string $name Parameter name
     * @param  mixed  $def  Default value if not set
     *
     * @return mixed  The requested config value
     */
    public function get($name, $def = null)
    {
        if (isset($this->prop[$name])) {
            $result = $this->prop[$name];
        }
        else {
            $result = $def;
        }

        $result = $this->getenv_default('ROUNDCUBE_' . strtoupper($name), $result);
        $rcube  = rcube::get_instance();

        if ($name == 'timezone') {
            if (empty($result) || $result == 'auto') {
                $result = $this->client_timezone();
            }
        }
        else if ($name == 'client_mimetypes') {
            if (!$result && !$def) {
                $result = 'text/plain,text/html,text/xml'
                    . ',image/jpeg,image/gif,image/png,image/bmp,image/tiff,image/webp'
                    . ',application/x-javascript,application/pdf,application/x-shockwave-flash';
            }
            if ($result && is_string($result)) {
                $result = explode(',', $result);
            }
        }

        $plugin = $rcube->plugins->exec_hook('config_get', array(
            'name' => $name, 'default' => $def, 'result' => $result));

        return $plugin['result'];
    }

    /**
     * Setter for a config parameter
     *
     * @param string $name  Parameter name
     * @param mixed  $value Parameter value
     */
    public function set($name, $value)
    {
        $this->prop[$name] = $value;
    }

    /**
     * Override config options with the given values (eg. user prefs)
     *
     * @param array $prefs Hash array with config props to merge over
     */
    public function merge($prefs)
    {
        $prefs = $this->fix_legacy_props($prefs);
        $this->prop = array_merge($this->prop, $prefs, $this->userprefs);
    }

    /**
     * Merge the given prefs over the current config
     * and make sure that they survive further merging.
     *
     * @param array $prefs Hash array with user prefs
     */
    public function set_user_prefs($prefs)
    {
        $prefs = $this->fix_legacy_props($prefs);

        // Honor the dont_override setting for any existing user preferences
        $dont_override = $this->get('dont_override');
        if (is_array($dont_override) && !empty($dont_override)) {
            foreach ($dont_override as $key) {
                unset($prefs[$key]);
            }
        }

        // larry is the new default skin :-)
        if ($prefs['skin'] == 'default') {
            $prefs['skin'] = self::DEFAULT_SKIN;
        }

        $this->userprefs = $prefs;
        $this->prop      = array_merge($this->prop, $prefs);
    }

    /**
     * Getter for all config options
     *
     * @return array Hash array containing all config properties
     */
    public function all()
    {
        $props = $this->prop;

        foreach ($props as $prop_name => $prop_value) {
            $props[$prop_name] = $this->getenv_default('ROUNDCUBE_' . strtoupper($prop_name), $prop_value);
        }

        $rcube  = rcube::get_instance();
        $plugin = $rcube->plugins->exec_hook('config_get', array(
            'name' => '*', 'result' => $props));

        return $plugin['result'];
    }

    /**
     * Special getter for user's timezone offset including DST
     *
     * @return float Timezone offset (in hours)
     * @deprecated
     */
    public function get_timezone()
    {
        if ($tz = $this->get('timezone')) {
            try {
                $tz = new DateTimeZone($tz);
                return $tz->getOffset(new DateTime('now')) / 3600;
            }
            catch (Exception $e) {
            }
        }

        return 0;
    }

    /**
     * Return requested DES crypto key.
     *
     * @param string $key Crypto key name
     *
     * @return string Crypto key
     */
    public function get_crypto_key($key)
    {
        // Bomb out if the requested key does not exist
        if (!array_key_exists($key, $this->prop) || empty($this->prop[$key])) {
            rcube::raise_error(array(
                'code' => 500, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Request for unconfigured crypto key \"$key\""
            ), true, true);
        }

        return $this->prop[$key];
    }

    /**
     * Return configured crypto method.
     *
     * @return string Crypto method
     */
    public function get_crypto_method()
    {
        return $this->get('cipher_method') ?: 'DES-EDE3-CBC';
    }

    /**
     * Try to autodetect operating system and find the correct line endings
     *
     * @return string The appropriate mail header delimiter
     * @deprecated Since 1.3 we don't use mail()
     */
    public function header_delimiter()
    {
        // use the configured delimiter for headers
        if (!empty($this->prop['mail_header_delimiter'])) {
            $delim = $this->prop['mail_header_delimiter'];
            if ($delim == "\n" || $delim == "\r\n") {
                return $delim;
            }
            else {
                rcube::raise_error(array(
                    'code' => 500, 'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Invalid mail_header_delimiter setting"
                ), true, false);
            }
        }

        $php_os = strtolower(substr(PHP_OS, 0, 3));

        if ($php_os == 'win')
            return "\r\n";

        if ($php_os == 'mac')
            return "\r\n";

        return "\n";
    }

    /**
     * Return the mail domain configured for the given host
     *
     * @param string  $host   IMAP host
     * @param boolean $encode If true, domain name will be converted to IDN ASCII
     *
     * @return string Resolved SMTP host
     */
    public function mail_domain($host, $encode=true)
    {
        $domain = $host;

        if (is_array($this->prop['mail_domain'])) {
            if (isset($this->prop['mail_domain'][$host])) {
                $domain = $this->prop['mail_domain'][$host];
            }
        }
        else if (!empty($this->prop['mail_domain'])) {
            $domain = rcube_utils::parse_host($this->prop['mail_domain']);
        }

        if ($encode) {
            $domain = rcube_utils::idn_to_ascii($domain);
        }

        return $domain;
    }

    /**
     * Getter for error state
     *
     * @return mixed Error message on error, False if no errors
     */
    public function get_error()
    {
        return empty($this->errors) ? false : join("\n", $this->errors);
    }

    /**
     * Internal getter for client's (browser) timezone identifier
     */
    private function client_timezone()
    {
        // @TODO: remove this legacy timezone handling in the future
        $props = $this->fix_legacy_props(array('timezone' => $_SESSION['timezone']));

        if (!empty($props['timezone'])) {
            try {
                $tz = new DateTimeZone($props['timezone']);
                return $tz->getName();
            }
            catch (Exception $e) { /* gracefully ignore */ }
        }

        // fallback to server's timezone
        return date_default_timezone_get();
    }

    /**
     * Convert legacy options into new ones
     *
     * @param array $props Hash array with config props
     *
     * @return array Converted config props
     */
    private function fix_legacy_props($props)
    {
        foreach ($this->legacy_props as $new => $old) {
            if (isset($props[$old])) {
                if (!isset($props[$new])) {
                    $props[$new] = $props[$old];
                }
                unset($props[$old]);
            }
        }

        // convert deprecated numeric timezone value
        if (isset($props['timezone']) && is_numeric($props['timezone'])) {
            if ($tz = self::timezone_name_from_abbr($props['timezone'])) {
                $props['timezone'] = $tz;
            }
            else {
                unset($props['timezone']);
            }
        }

        return $props;
    }

    /**
     * timezone_name_from_abbr() replacement. Converts timezone offset
     * into timezone name abbreviation.
     *
     * @param float $offset Timezone offset (in hours)
     *
     * @return string Timezone abbreviation
     */
    static public function timezone_name_from_abbr($offset)
    {
        // List of timezones here is not complete - https://bugs.php.net/bug.php?id=44780
        if ($tz = timezone_name_from_abbr('', $offset * 3600, 0)) {
            return $tz;
        }

        // try with more complete list (#1489261)
        $timezones = array(
            '-660' => "Pacific/Apia",
            '-600' => "Pacific/Honolulu",
            '-570' => "Pacific/Marquesas",
            '-540' => "America/Anchorage",
            '-480' => "America/Los_Angeles",
            '-420' => "America/Denver",
            '-360' => "America/Chicago",
            '-300' => "America/New_York",
            '-270' => "America/Caracas",
            '-240' => "America/Halifax",
            '-210' => "Canada/Newfoundland",
            '-180' => "America/Sao_Paulo",
             '-60' => "Atlantic/Azores",
               '0' => "Europe/London",
              '60' => "Europe/Paris",
             '120' => "Europe/Helsinki",
             '180' => "Europe/Moscow",
             '210' => "Asia/Tehran",
             '240' => "Asia/Dubai",
             '270' => "Asia/Kabul",
             '300' => "Asia/Karachi",
             '330' => "Asia/Kolkata",
             '345' => "Asia/Katmandu",
             '360' => "Asia/Yekaterinburg",
             '390' => "Asia/Rangoon",
             '420' => "Asia/Krasnoyarsk",
             '480' => "Asia/Shanghai",
             '525' => "Australia/Eucla",
             '540' => "Asia/Tokyo",
             '570' => "Australia/Adelaide",
             '600' => "Australia/Melbourne",
             '630' => "Australia/Lord_Howe",
             '660' => "Asia/Vladivostok",
             '690' => "Pacific/Norfolk",
             '720' => "Pacific/Auckland",
             '765' => "Pacific/Chatham",
             '780' => "Pacific/Enderbury",
             '840' => "Pacific/Kiritimati",
        );

        return $timezones[(string) intval($offset * 60)];
    }
}
