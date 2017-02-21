<?php

/**
 *  Classes for managesieve operations (using PEAR::Net_Sieve)
 *
 * Copyright (C) 2008-2011, The Roundcube Dev Team
 * Copyright (C) 2011, Kolab Systems AG
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

// Managesieve Protocol: RFC5804

class rcube_sieve
{
    private $sieve;                 // Net_Sieve object
    private $error = false;         // error flag
    private $errorLines = array();  // array of line numbers within sieve script which raised an error
    private $list = array();        // scripts list

    public $script;                 // rcube_sieve_script object
    public $current;                // name of currently loaded script
    private $exts;                  // array of supported extensions

    const ERROR_CONNECTION = 1;
    const ERROR_LOGIN      = 2;
    const ERROR_NOT_EXISTS = 3;    // script not exists
    const ERROR_INSTALL    = 4;    // script installation
    const ERROR_ACTIVATE   = 5;    // script activation
    const ERROR_DELETE     = 6;    // script deletion
    const ERROR_INTERNAL   = 7;    // internal error
    const ERROR_DEACTIVATE = 8;    // script activation
    const ERROR_OTHER      = 255;  // other/unknown error


    /**
     * Object constructor
     *
     * @param string  Username (for managesieve login)
     * @param string  Password (for managesieve login)
     * @param string  Managesieve server hostname/address
     * @param string  Managesieve server port number
     * @param string  Managesieve authentication method 
     * @param boolean Enable/disable TLS use
     * @param array   Disabled extensions
     * @param boolean Enable/disable debugging
     * @param string  Proxy authentication identifier
     * @param string  Proxy authentication password
     * @param array   List of options to pass to stream_context_create().
     */
    public function __construct($username, $password='', $host='localhost', $port=2000,
        $auth_type=null, $usetls=true, $disabled=array(), $debug=false,
        $auth_cid=null, $auth_pw=null, $options=array())
    {
        $this->sieve = new Net_Sieve();

        if ($debug) {
            $this->sieve->setDebug(true, array($this, 'debug_handler'));
        }

        $result = $this->sieve->connect($host, $port, $options, $usetls);

        if (is_a($result, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_CONNECTION);
        }

        if (!empty($auth_cid)) {
            $authz    = $username;
            $username = $auth_cid;
        }
        if (!empty($auth_pw)) {
            $password = $auth_pw;
        }

        $result = $this->sieve->login($username, $password, $auth_type ? strtoupper($auth_type) : null, $authz);

        if (is_a($result, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_LOGIN);
        }

        $this->exts = $this->get_extensions();

        // disable features by config
        if (!empty($disabled)) {
            // we're working on lower-cased names
            $disabled = array_map('strtolower', (array) $disabled);
            foreach ($disabled as $ext) {
                if (($idx = array_search($ext, $this->exts)) !== false) {
                    unset($this->exts[$idx]);
                }
            }
        }
    }

    public function __destruct() {
        $this->sieve->disconnect();
    }

    /**
     * Getter for error code
     */
    public function error()
    {
        return $this->error ?: false;
    }

    /**
     * Saves current script into server
     */
    public function save($name = null)
    {
        if (!$this->sieve) {
            return $this->_set_error(self::ERROR_INTERNAL);
        }

        if (!$this->script) {
            return $this->_set_error(self::ERROR_INTERNAL);
        }

        if (!$name) {
            $name = $this->current;
        }

        $script = $this->script->as_text();

        if (!$script) {
            $script = '/* empty script */';
        }

        $result = $this->sieve->installScript($name, $script);
        if (is_a($result, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_INSTALL);
        }

        return true;
    }

    /**
     * Saves text script into server
     */
    public function save_script($name, $content = null)
    {
        if (!$this->sieve) {
            return $this->_set_error(self::ERROR_INTERNAL);
        }

        if (!$content) {
            $content = '/* empty script */';
        }

        $result = $this->sieve->installScript($name, $content);

        if (is_a($result, 'PEAR_Error')) {
            $rawErrorMessage = $result->getMessage();
            $errMessages = preg_split("/$name:/", $rawErrorMessage);

            if (sizeof($errMessages) > 0) {
                foreach ($errMessages as $singleError) {
                    $matches = array();
                    $res = preg_match('/line (\d+):(.*)/i', $singleError, $matches);

                    if ($res === 1 ) {
                        if (count($matches) > 2) {
                            $this->errorLines[] = array("line" => $matches[1], "msg" => $matches[2]);
                        }
                        else {
                            $this->errorLines[] = array("line" => $matches[1], "msg" => null);
                        }
                    }
	            }
            }
            
            return $this->_set_error(self::ERROR_INSTALL);
        }

        return true;
    }
    
    /**
     * Returns the current error line within the saved sieve script
     */
    public function get_error_lines()
    {
        return $this->errorLines;
    }

    /**
     * Activates specified script
     */
    public function activate($name = null)
    {
        if (!$this->sieve) {
            return $this->_set_error(self::ERROR_INTERNAL);
        }

        if (!$name) {
            $name = $this->current;
        }

        $result = $this->sieve->setActive($name);

        if (is_a($result, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_ACTIVATE);
        }

        return true;
    }

    /**
     * De-activates specified script
     */
    public function deactivate()
    {
        if (!$this->sieve) {
            return $this->_set_error(self::ERROR_INTERNAL);
        }

        $result = $this->sieve->setActive('');

        if (is_a($result, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_DEACTIVATE);
        }

        return true;
    }

    /**
     * Removes specified script
     */
    public function remove($name = null)
    {
        if (!$this->sieve) {
            return $this->_set_error(self::ERROR_INTERNAL);
        }

        if (!$name) {
            $name = $this->current;
        }

        // script must be deactivated first
        if ($name == $this->sieve->getActive()) {
            $result = $this->sieve->setActive('');

            if (is_a($result, 'PEAR_Error')) {
                return $this->_set_error(self::ERROR_DELETE);
            }
        }

        $result = $this->sieve->removeScript($name);

        if (is_a($result, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_DELETE);
        }

        if ($name == $this->current) {
            $this->current = null;
        }

        return true;
    }

    /**
     * Gets list of supported by server Sieve extensions
     */
    public function get_extensions()
    {
        if ($this->exts)
            return $this->exts;

        if (!$this->sieve)
            return $this->_set_error(self::ERROR_INTERNAL);

        $ext = $this->sieve->getExtensions();

        if (is_a($ext, 'PEAR_Error')) {
            return array();
        }

        // we're working on lower-cased names
        $ext = array_map('strtolower', (array) $ext);

        if ($this->script) {
            $supported = $this->script->get_extensions();
            foreach ($ext as $idx => $ext_name)
                if (!in_array($ext_name, $supported))
                    unset($ext[$idx]);
        }

        return array_values($ext);
    }

    /**
     * Gets list of scripts from server
     */
    public function get_scripts()
    {
        if (!$this->list) {

            if (!$this->sieve)
                return $this->_set_error(self::ERROR_INTERNAL);

            $list = $this->sieve->listScripts();

            if (is_a($list, 'PEAR_Error')) {
                return $this->_set_error(self::ERROR_OTHER);
            }

            $this->list = $list;
        }

        return $this->list;
    }

    /**
     * Returns active script name
     */
    public function get_active()
    {
        if (!$this->sieve)
            return $this->_set_error(self::ERROR_INTERNAL);

        return $this->sieve->getActive();
    }

    /**
     * Loads script by name
     */
    public function load($name)
    {
        if (!$this->sieve)
            return $this->_set_error(self::ERROR_INTERNAL);

        if ($this->current == $name)
            return true;

        $script = $this->sieve->getScript($name);

        if (is_a($script, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_OTHER);
        }

        // try to parse from Roundcube format
        $this->script = $this->_parse($script);

        $this->current = $name;

        return true;
    }

    /**
     * Loads script from text content
     */
    public function load_script($script)
    {
        if (!$this->sieve)
            return $this->_set_error(self::ERROR_INTERNAL);

        // try to parse from Roundcube format
        $this->script = $this->_parse($script);
    }

    /**
     * Creates rcube_sieve_script object from text script
     */
    private function _parse($txt)
    {
        // parse
        $script = new rcube_sieve_script($txt, $this->exts);

        // fix/convert to Roundcube format
        if (!empty($script->content)) {
            // replace all elsif with if+stop, we support only ifs
            foreach ($script->content as $idx => $rule) {
                if (empty($rule['type']) || !preg_match('/^(if|elsif|else)$/', $rule['type'])) {
                    continue;
                }

                $script->content[$idx]['type'] = 'if';

                // 'stop' not found?
                foreach ($rule['actions'] as $action) {
                    if (preg_match('/^(stop|vacation)$/', $action['type'])) {
                        continue 2;
                    }
                }
                if (!empty($script->content[$idx+1]) && $script->content[$idx+1]['type'] != 'if') {
                    $script->content[$idx]['actions'][] = array('type' => 'stop');
                }
            }
        }

        return $script;
    }

    /**
     * Gets specified script as text
     */
    public function get_script($name)
    {
        if (!$this->sieve)
            return $this->_set_error(self::ERROR_INTERNAL);

        $content = $this->sieve->getScript($name);

        if (is_a($content, 'PEAR_Error')) {
            return $this->_set_error(self::ERROR_OTHER);
        }

        return $content;
    }

    /**
     * Creates empty script or copy of other script
     */
    public function copy($name, $copy)
    {
        if (!$this->sieve)
            return $this->_set_error(self::ERROR_INTERNAL);

        if ($copy) {
            $content = $this->sieve->getScript($copy);

            if (is_a($content, 'PEAR_Error')) {
                return $this->_set_error(self::ERROR_OTHER);
            }
        }


        return $this->save_script($name, $content);
    }

    private function _set_error($error)
    {
        $this->error = $error;
        return false;
    }

    /**
     * This is our own debug handler for connection
     */
    public function debug_handler(&$sieve, $message)
    {
        rcube::write_log('sieve', preg_replace('/\r\n$/', '', $message));
    }
}
