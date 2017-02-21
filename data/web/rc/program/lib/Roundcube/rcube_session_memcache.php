<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2011, Kolab Systems AG                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide database supported session management                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Cor Bosman <cor@roundcu.bet>                                  |
 +-----------------------------------------------------------------------+
*/

/**
 * Class to provide memcache session storage
 *
 * @package    Framework
 * @subpackage Core
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 * @author     Cor Bosman <cor@roundcu.be>
 */
class rcube_session_memcache extends rcube_session
{
    private $memcache;
    private $debug;

    /**
     * @param Object $config
     */
    public function __construct($config)
    {
        parent::__construct($config);

        $this->memcache = rcube::get_instance()->get_memcache();
        $this->debug    = $config->get('memcache_debug');

        if (!$this->memcache) {
            rcube::raise_error(array(
                    'code' => 604, 'type' => 'db',
                    'line' => __LINE__, 'file' => __FILE__,
                    'message' => "Failed to connect to memcached. Please check configuration"),
                true, true);
        }

        // register sessions handler
        $this->register_session_handler();
    }

    /**
     * @param $save_path
     * @param $session_name
     * @return bool
     */
    public function open($save_path, $session_name)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Handler for session_destroy() with memcache backend
     *
     * @param $key
     * @return bool
     */
    public function destroy($key)
    {
        if ($key) {
            // #1488592: use 2nd argument
            $result = $this->memcache->delete($key, 0);

            if ($this->debug) {
                $this->debug('delete', $key, null, $result);
            }
        }

        return true;
    }

    /**
     * Read session data from memcache
     *
     * @param $key
     * @return null|string
     */
    public function read($key)
    {
        if ($value = $this->memcache->get($key)) {
            $arr = unserialize($value);
            $this->changed = $arr['changed'];
            $this->ip      = $arr['ip'];
            $this->vars    = $arr['vars'];
            $this->key     = $key;
        }

        if ($this->debug) {
            $this->debug('get', $key, $value);
        }

        return $this->vars ?: '';
    }

    /**
     * Write data to memcache storage
     *
     * @param $key
     * @param $vars
     *
     * @return bool
     */
    public function write($key, $vars)
    {
        $data   = serialize(array('changed' => time(), 'ip' => $this->ip, 'vars' => $vars));
        $result = $this->memcache->set($key, $data, MEMCACHE_COMPRESSED, $this->lifetime + 60);

        if ($this->debug) {
            $this->debug('set', $key, $data, $result);
        }

        return $result;
    }

    /**
     * Update memcache session data
     *
     * @param $key
     * @param $newvars
     * @param $oldvars
     *
     * @return bool
     */
    public function update($key, $newvars, $oldvars)
    {
        $ts = microtime(true);

        if ($newvars !== $oldvars || $ts - $this->changed > $this->lifetime / 3) {
            $data   = serialize(array('changed' => time(), 'ip' => $this->ip, 'vars' => $newvars));
            $result = $this->memcache->set($key, $data, MEMCACHE_COMPRESSED, $this->lifetime + 60);

            if ($this->debug) {
                $this->debug('set', $key, $data, $result);
            }

            return $result;
        }

        return true;
    }

    /**
     * Write memcache debug info to the log
     */
    protected function debug($type, $key, $data = null, $result = null)
    {
        $line = strtoupper($type) . ' ' . $key;

        if ($data !== null) {
            $line .= ' ' . $data;
        }

        rcube::debug('memcache', $line, $result);
    }
}
