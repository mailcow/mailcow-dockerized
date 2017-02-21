<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Plugins repository                                                  |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// location where plugins are loade from
if (!defined('RCUBE_PLUGINS_DIR')) {
    define('RCUBE_PLUGINS_DIR', RCUBE_INSTALL_PATH . 'plugins/');
}

/**
 * The plugin loader and global API
 *
 * @package    Framework
 * @subpackage PluginAPI
 */
class rcube_plugin_api
{
    static protected $instance;

    public $dir;
    public $url = 'plugins/';
    public $task = '';
    public $initialized = false;

    public $output;
    public $handlers              = array();
    public $allowed_prefs         = array();
    public $allowed_session_prefs = array();
    public $active_plugins        = array();

    protected $plugins           = array();
    protected $plugins_initialized = array();
    protected $tasks             = array();
    protected $actions           = array();
    protected $actionmap         = array();
    protected $objectsmap        = array();
    protected $template_contents = array();
    protected $exec_stack        = array();
    protected $deprecated_hooks  = array();


    /**
     * This implements the 'singleton' design pattern
     *
     * @return rcube_plugin_api The one and only instance if this class
     */
    static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new rcube_plugin_api();
        }

        return self::$instance;
    }

    /**
     * Private constructor
     */
    protected function __construct()
    {
        $this->dir = slashify(RCUBE_PLUGINS_DIR);
    }

    /**
     * Initialize plugin engine
     *
     * This has to be done after rcmail::load_gui() or rcmail::json_init()
     * was called because plugins need to have access to rcmail->output
     *
     * @param object rcube Instance of the rcube base class
     * @param string Current application task (used for conditional plugin loading)
     */
    public function init($app, $task = '')
    {
        $this->task   = $task;
        $this->output = $app->output;
        // register an internal hook
        $this->register_hook('template_container', array($this, 'template_container_hook'));
        // maybe also register a shudown function which triggers
        // shutdown functions of all plugin objects

        foreach ($this->plugins as $plugin) {
            // ... task, request type and framed mode
            if (!$this->plugins_initialized[$plugin->ID] && !$this->filter($plugin)) {
                $plugin->init();
                $this->plugins_initialized[$plugin->ID] = $plugin;
            }
        }

        // we have finished initializing all plugins
        $this->initialized = true;
    }

    /**
     * Load and init all enabled plugins
     *
     * This has to be done after rcmail::load_gui() or rcmail::json_init()
     * was called because plugins need to have access to rcmail->output
     *
     * @param array List of configured plugins to load
     * @param array List of plugins required by the application
     */
    public function load_plugins($plugins_enabled, $required_plugins = array())
    {
        foreach ($plugins_enabled as $plugin_name) {
            $this->load_plugin($plugin_name);
        }

        // check existance of all required core plugins
        foreach ($required_plugins as $plugin_name) {
            $loaded = false;
            foreach ($this->plugins as $plugin) {
                if ($plugin instanceof $plugin_name) {
                    $loaded = true;
                    break;
                }
            }

            // load required core plugin if no derivate was found
            if (!$loaded) {
                $loaded = $this->load_plugin($plugin_name);
            }

            // trigger fatal error if still not loaded
            if (!$loaded) {
                rcube::raise_error(array(
                    'code' => 520, 'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Requried plugin $plugin_name was not loaded"), true, true);
            }
        }
    }

    /**
     * Load the specified plugin
     *
     * @param string  Plugin name
     * @param boolean Force loading of the plugin even if it doesn't match the filter
     * @param boolean Require loading of the plugin, error if it doesn't exist
     *
     * @return boolean True on success, false if not loaded or failure
     */
    public function load_plugin($plugin_name, $force = false, $require = true)
    {
        static $plugins_dir;

        if (!$plugins_dir) {
            $dir         = dir($this->dir);
            $plugins_dir = unslashify($dir->path);
        }

        // plugin already loaded?
        if (!$this->plugins[$plugin_name]) {
            $fn = "$plugins_dir/$plugin_name/$plugin_name.php";

            if (!is_readable($fn)) {
                if ($require) {
                    rcube::raise_error(array('code' => 520, 'type' => 'php',
                        'file' => __FILE__, 'line' => __LINE__,
                        'message' => "Failed to load plugin file $fn"), true, false);
                }

                return false;
            }

            if (!class_exists($plugin_name, false)) {
                include $fn;
            }

            // instantiate class if exists
            if (!class_exists($plugin_name, false)) {
                rcube::raise_error(array('code' => 520, 'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "No plugin class $plugin_name found in $fn"),
                    true, false);

                return false;
            }

            $plugin = new $plugin_name($this);
            $this->active_plugins[] = $plugin_name;

            // check inheritance...
            if (is_subclass_of($plugin, 'rcube_plugin')) {
                // call onload method on plugin if it exists.
                // this is useful if you want to be called early in the boot process
                if (method_exists($plugin, 'onload')) {
                    $plugin->onload();
                }

                if (!empty($plugin->allowed_prefs)) {
                    $this->allowed_prefs = array_merge($this->allowed_prefs, $plugin->allowed_prefs);
                }

                $this->plugins[$plugin_name] = $plugin;
            }
        }

        if ($plugin = $this->plugins[$plugin_name]) {
            // init a plugin only if $force is set or if we're called after initialization
            if (($force || $this->initialized) && !$this->plugins_initialized[$plugin_name] && ($force || !$this->filter($plugin))) {
                $plugin->init();
                $this->plugins_initialized[$plugin_name] = $plugin;
            }
        }

        return true;
    }

    /**
     * check if we should prevent this plugin from initialising
     *
     * @param $plugin
     * @return bool
     */
    private function filter($plugin)
    {
        return ($plugin->noajax  && !(is_object($this->output) && $this->output->type == 'html'))
             || ($plugin->task && !preg_match('/^('.$plugin->task.')$/i', $this->task))
             || ($plugin->noframe && !empty($_REQUEST['_framed']));
    }

    /**
     * Get information about a specific plugin.
     * This is either provided my a plugin's info() method or extracted from a package.xml or a composer.json file
     *
     * @param string Plugin name
     * @return array Meta information about a plugin or False if plugin was not found
     */
    public function get_info($plugin_name)
    {
        static $composer_lock, $license_uris = array(
            'Apache'       => 'http://www.apache.org/licenses/LICENSE-2.0.html',
            'Apache-2'     => 'http://www.apache.org/licenses/LICENSE-2.0.html',
            'Apache-1'     => 'http://www.apache.org/licenses/LICENSE-1.0',
            'Apache-1.1'   => 'http://www.apache.org/licenses/LICENSE-1.1',
            'GPL'          => 'http://www.gnu.org/licenses/gpl.html',
            'GPLv2'        => 'http://www.gnu.org/licenses/gpl-2.0.html',
            'GPL-2.0'      => 'http://www.gnu.org/licenses/gpl-2.0.html',
            'GPLv3'        => 'http://www.gnu.org/licenses/gpl-3.0.html',
            'GPLv3+'       => 'http://www.gnu.org/licenses/gpl-3.0.html',
            'GPL-3.0'      => 'http://www.gnu.org/licenses/gpl-3.0.html',
            'GPL-3.0+'     => 'http://www.gnu.org/licenses/gpl.html',
            'GPL-2.0+'     => 'http://www.gnu.org/licenses/gpl.html',
            'AGPLv3'       => 'http://www.gnu.org/licenses/agpl.html',
            'AGPLv3+'      => 'http://www.gnu.org/licenses/agpl.html',
            'AGPL-3.0'     => 'http://www.gnu.org/licenses/agpl.html',
            'LGPL'         => 'http://www.gnu.org/licenses/lgpl.html',
            'LGPLv2'       => 'http://www.gnu.org/licenses/lgpl-2.0.html',
            'LGPLv2.1'     => 'http://www.gnu.org/licenses/lgpl-2.1.html',
            'LGPLv3'       => 'http://www.gnu.org/licenses/lgpl.html',
            'LGPL-2.0'     => 'http://www.gnu.org/licenses/lgpl-2.0.html',
            'LGPL-2.1'     => 'http://www.gnu.org/licenses/lgpl-2.1.html',
            'LGPL-3.0'     => 'http://www.gnu.org/licenses/lgpl.html',
            'LGPL-3.0+'    => 'http://www.gnu.org/licenses/lgpl.html',
            'BSD'          => 'http://opensource.org/licenses/bsd-license.html',
            'BSD-2-Clause' => 'http://opensource.org/licenses/BSD-2-Clause',
            'BSD-3-Clause' => 'http://opensource.org/licenses/BSD-3-Clause',
            'FreeBSD'      => 'http://opensource.org/licenses/BSD-2-Clause',
            'MIT'          => 'http://www.opensource.org/licenses/mit-license.php',
            'PHP'          => 'http://opensource.org/licenses/PHP-3.0',
            'PHP-3'        => 'http://www.php.net/license/3_01.txt',
            'PHP-3.0'      => 'http://www.php.net/license/3_0.txt',
            'PHP-3.01'     => 'http://www.php.net/license/3_01.txt',
        );

        $dir  = dir($this->dir);
        $fn   = unslashify($dir->path) . "/$plugin_name/$plugin_name.php";
        $info = false;

        if (!class_exists($plugin_name, false)) {
            if (is_readable($fn)) {
                include($fn);
            }
            else {
                return false;
            }
        }

        if (class_exists($plugin_name)) {
            $info = $plugin_name::info();
        }

        // fall back to composer.json file
        if (!$info) {
            $composer = INSTALL_PATH . "/plugins/$plugin_name/composer.json";
            if (is_readable($composer) && ($json = @json_decode(file_get_contents($composer), true))) {
                list($info['vendor'], $info['name']) = explode('/', $json['name']);
                $info['version'] = $json['version'];
                $info['license'] = $json['license'];
                $info['uri']     = $json['homepage'];
                $info['require'] = array_filter(array_keys((array)$json['require']), function($pname) {
                    if (strpos($pname, '/') == false) {
                        return false;
                    }
                    list($vendor, $name) = explode('/', $pname);
                    return !($name == 'plugin-installer' || $vendor == 'pear-pear');
                });
            }

            // read local composer.lock file (once)
            if (!isset($composer_lock)) {
                $composer_lock = @json_decode(@file_get_contents(INSTALL_PATH . "/composer.lock"), true);
                if ($composer_lock['packages']) {
                    foreach ($composer_lock['packages'] as $i => $package) {
                        $composer_lock['installed'][$package['name']] = $package;
                    }
                }
            }

            // load additional information from local composer.lock file
            if ($lock = $composer_lock['installed'][$json['name']]) {
                $info['version'] = $lock['version'];
                $info['uri']     = $lock['homepage'] ?: $lock['source']['uri'];
                $info['src_uri'] = $lock['dist']['uri'] ?: $lock['source']['uri'];
            }
        }

        // fall back to package.xml file
        if (!$info) {
            $package = INSTALL_PATH . "/plugins/$plugin_name/package.xml";
            if (is_readable($package) && ($file = file_get_contents($package))) {
                $doc = new DOMDocument();
                $doc->loadXML($file);
                $xpath = new DOMXPath($doc);
                $xpath->registerNamespace('rc', "http://pear.php.net/dtd/package-2.0");

                // XPaths of plugin metadata elements
                $metadata = array(
                    'name'    => 'string(//rc:package/rc:name)',
                    'version' => 'string(//rc:package/rc:version/rc:release)',
                    'license' => 'string(//rc:package/rc:license)',
                    'license_uri' => 'string(//rc:package/rc:license/@uri)',
                    'src_uri' => 'string(//rc:package/rc:srcuri)',
                    'uri'     => 'string(//rc:package/rc:uri)',
                );

                foreach ($metadata as $key => $path) {
                    $info[$key] = $xpath->evaluate($path);
                }

                // dependent required plugins (can be used, but not included in config)
                $deps = $xpath->evaluate('//rc:package/rc:dependencies/rc:required/rc:package/rc:name');
                for ($i = 0; $i < $deps->length; $i++) {
                    $dn = $deps->item($i)->nodeValue;
                    $info['require'][] = $dn;
                }
            }
        }

        // At least provide the name
        if (!$info && class_exists($plugin_name)) {
            $info = array('name' => $plugin_name, 'version' => '--');
        }
        else if ($info['license'] && empty($info['license_uri']) && ($license_uri = $license_uris[$info['license']])) {
            $info['license_uri'] = $license_uri;
        }

        return $info;
    }

    /**
     * Allows a plugin object to register a callback for a certain hook
     *
     * @param string $hook Hook name
     * @param mixed  $callback String with global function name or array($obj, 'methodname')
     */
    public function register_hook($hook, $callback)
    {
        if (is_callable($callback)) {
            if (isset($this->deprecated_hooks[$hook])) {
                rcube::raise_error(array('code' => 522, 'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Deprecated hook name. "
                        . $hook . ' -> ' . $this->deprecated_hooks[$hook]), true, false);
                $hook = $this->deprecated_hooks[$hook];
            }
            $this->handlers[$hook][] = $callback;
        }
        else {
            rcube::raise_error(array('code' => 521, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Invalid callback function for $hook"), true, false);
        }
    }

    /**
     * Allow a plugin object to unregister a callback.
     *
     * @param string $hook Hook name
     * @param mixed  $callback String with global function name or array($obj, 'methodname')
     */
    public function unregister_hook($hook, $callback)
    {
        $callback_id = array_search($callback, (array) $this->handlers[$hook]);
        if ($callback_id !== false) {
            // array_splice() removes the element and re-indexes keys
            // that is required by the 'for' loop in exec_hook() below
            array_splice($this->handlers[$hook], $callback_id, 1);
        }
    }

    /**
     * Triggers a plugin hook.
     * This is called from the application and executes all registered handlers
     *
     * @param string $hook Hook name
     * @param array $args Named arguments (key->value pairs)
     *
     * @return array The (probably) altered hook arguments
     */
    public function exec_hook($hook, $args = array())
    {
        if (!is_array($args)) {
            $args = array('arg' => $args);
        }

        // TODO: avoid recursion by checking in_array($hook, $this->exec_stack) ?

        $args += array('abort' => false);
        array_push($this->exec_stack, $hook);

        // Use for loop here, so handlers added in the hook will be executed too
        for ($i = 0; $i < count($this->handlers[$hook]); $i++) {
            $ret = call_user_func($this->handlers[$hook][$i], $args);
            if ($ret && is_array($ret)) {
                $args = $ret + $args;
            }

            if ($args['break']) {
                break;
            }
        }

        array_pop($this->exec_stack);
        return $args;
    }

    /**
     * Let a plugin register a handler for a specific request
     *
     * @param string $action   Action name (_task=mail&_action=plugin.foo)
     * @param string $owner    Plugin name that registers this action
     * @param mixed  $callback Callback: string with global function name or array($obj, 'methodname')
     * @param string $task     Task name registered by this plugin
     */
    public function register_action($action, $owner, $callback, $task = null)
    {
        // check action name
        if ($task)
            $action = $task.'.'.$action;
        else if (strpos($action, 'plugin.') !== 0)
            $action = 'plugin.'.$action;

        // can register action only if it's not taken or registered by myself
        if (!isset($this->actionmap[$action]) || $this->actionmap[$action] == $owner) {
            $this->actions[$action] = $callback;
            $this->actionmap[$action] = $owner;
        }
        else {
            rcube::raise_error(array('code' => 523, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Cannot register action $action;"
                    ." already taken by another plugin"), true, false);
        }
    }

    /**
     * This method handles requests like _task=mail&_action=plugin.foo
     * It executes the callback function that was registered with the given action.
     *
     * @param string $action Action name
     */
    public function exec_action($action)
    {
        if (isset($this->actions[$action])) {
            call_user_func($this->actions[$action]);
        }
        else if (rcube::get_instance()->action != 'refresh') {
            rcube::raise_error(array('code' => 524, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "No handler found for action $action"), true, true);
        }
    }

    /**
     * Register a handler function for template objects
     *
     * @param string $name Object name
     * @param string $owner Plugin name that registers this action
     * @param mixed  $callback Callback: string with global function name or array($obj, 'methodname')
     */
    public function register_handler($name, $owner, $callback)
    {
        // check name
        if (strpos($name, 'plugin.') !== 0) {
            $name = 'plugin.' . $name;
        }

        // can register handler only if it's not taken or registered by myself
        if (is_object($this->output)
            && (!isset($this->objectsmap[$name]) || $this->objectsmap[$name] == $owner)
        ) {
            $this->output->add_handler($name, $callback);
            $this->objectsmap[$name] = $owner;
        }
        else {
            rcube::raise_error(array('code' => 525, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Cannot register template handler $name;"
                    ." already taken by another plugin or no output object available"), true, false);
        }
    }

    /**
     * Register this plugin to be responsible for a specific task
     *
     * @param string $task Task name (only characters [a-z0-9_-] are allowed)
     * @param string $owner Plugin name that registers this action
     */
    public function register_task($task, $owner)
    {
        // tasks are irrelevant in framework mode
        if (!class_exists('rcmail', false)) {
            return true;
        }

        if ($task != asciiwords($task, true)) {
            rcube::raise_error(array('code' => 526, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Invalid task name: $task."
                    ." Only characters [a-z0-9_.-] are allowed"), true, false);
        }
        else if (in_array($task, rcmail::$main_tasks)) {
            rcube::raise_error(array('code' => 526, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Cannot register taks $task;"
                    ." already taken by another plugin or the application itself"), true, false);
        }
        else {
            $this->tasks[$task] = $owner;
            rcmail::$main_tasks[] = $task;
            return true;
        }

        return false;
    }

    /**
     * Checks whether the given task is registered by a plugin
     *
     * @param string $task Task name
     *
     * @return boolean True if registered, otherwise false
     */
    public function is_plugin_task($task)
    {
        return $this->tasks[$task] ? true : false;
    }

    /**
     * Check if a plugin hook is currently processing.
     * Mainly used to prevent loops and recursion.
     *
     * @param string $hook Hook to check (optional)
     *
     * @return boolean True if any/the given hook is currently processed, otherwise false
     */
    public function is_processing($hook = null)
    {
        return count($this->exec_stack) > 0 && (!$hook || in_array($hook, $this->exec_stack));
    }

    /**
     * Include a plugin script file in the current HTML page
     *
     * @param string $fn Path to script
     */
    public function include_script($fn)
    {
        if (is_object($this->output) && $this->output->type == 'html') {
            $src = $this->resource_url($fn);
            $this->output->add_header(html::tag('script',
                array('type' => "text/javascript", 'src' => $src)));
        }
    }

    /**
     * Include a plugin stylesheet in the current HTML page
     *
     * @param string $fn Path to stylesheet
     */
    public function include_stylesheet($fn)
    {
        if (is_object($this->output) && $this->output->type == 'html') {
            $src = $this->resource_url($fn);
            $this->output->include_css($src);
        }
    }

    /**
     * Save the given HTML content to be added to a template container
     *
     * @param string $html HTML content
     * @param string $container Template container identifier
     */
    public function add_content($html, $container)
    {
        $this->template_contents[$container] .= $html . "\n";
    }

    /**
     * Returns list of loaded plugins names
     *
     * @return array List of plugin names
     */
    public function loaded_plugins()
    {
        return array_keys($this->plugins);
    }

    /**
     * Returns loaded plugin
     *
     * @return rcube_plugin Plugin instance
     */
    public function get_plugin($name)
    {
        return $this->plugins[$name];
    }

    /**
     * Callback for template_container hooks
     *
     * @param array $attrib
     * @return array
     */
    protected function template_container_hook($attrib)
    {
        $container = $attrib['name'];
        return array('content' => $attrib['content'] . $this->template_contents[$container]);
    }

    /**
     * Make the given file name link into the plugins directory
     *
     * @param string $fn Filename
     * @return string
     */
    protected function resource_url($fn)
    {
        if ($fn[0] != '/' && !preg_match('|^https?://|i', $fn))
            return $this->url . $fn;
        else
            return $fn;
    }
}
