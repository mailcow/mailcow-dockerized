<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide redis supported session management                          |
 +-----------------------------------------------------------------------+
 | Author: Cor Bosman <cor@roundcu.be>                                   |
 +-----------------------------------------------------------------------+
*/

/**
 * Class to provide redis session storage
 *
 * @package    Framework
 * @subpackage Core
 * @author     Cor Bosman <cor@roundcu.be>
 */
class rcube_session_redis extends rcube_session {

    private $redis;

    /**
     * @param Object $config
     */
    public function __construct($config)
    {
        parent::__construct($config);

        // instantiate Redis object
        $this->redis = new Redis();

        if (!$this->redis) {
            rcube::raise_error(array('code' => 604, 'type' => 'session',
                                   'line' => __LINE__, 'file' => __FILE__,
                                   'message' => "Failed to find Redis. Make sure php-redis is included"),
                               true, true);
        }

        // get config instance
        $hosts = $this->config->get('redis_hosts', array('localhost'));

        // host config is wrong
        if (!is_array($hosts) || empty($hosts)) {
            rcube::raise_error(array('code' => 604, 'type' => 'session',
                                   'line' => __LINE__, 'file' => __FILE__,
                                   'message' => "Redis host not configured"),
                               true, true);
        }

        // only allow 1 host for now until we support clustering
        if (count($hosts) > 1) {
            rcube::raise_error(array('code' => 604, 'type' => 'session',
                                   'line' => __LINE__, 'file' => __FILE__,
                                   'message' => "Redis cluster not yet supported"),
                               true, true);
        }

        foreach ($hosts as $host) {
            // explode individual fields
            list($host, $port, $database, $password) = array_pad(explode(':', $host, 4), 4, null);

            // set default values if not set
            $host = ($host !== null) ? $host : '127.0.0.1';
            $port = ($port !== null) ? $port : 6379;
            $database = ($database !== null) ? $database : 0;

            if ($this->redis->connect($host, $port) === false) {
                rcube::raise_error(
                    array(
                        'code' => 604,
                        'type' => 'session',
                        'line' => __LINE__,
                        'file' => __FILE__,
                        'message' => "Could not connect to Redis server. Please check host and port"
                    ),
                    true,
                    true
                );
            }

            if ($password != null && $this->redis->auth($password) === false) {
                rcube::raise_error(
                    array(
                        'code' => 604,
                        'type' => 'session',
                        'line' => __LINE__,
                        'file' => __FILE__,
                        'message' => "Could not authenticate with Redis server. Please check password."
                    ),
                    true,
                    true
                );
            }

            if ($database != 0 && $this->redis->select($database) === false) {
                rcube::raise_error(
                    array(
                        'code' => 604,
                        'type' => 'session',
                        'line' => __LINE__,
                        'file' => __FILE__,
                        'message' => "Could not select Redis database. Please check database setting."
                    ),
                    true,
                    true
                );
            }
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
     * remove data from store
     *
     * @param $key
     * @return bool
     */
    public function destroy($key)
    {
        if ($key) {
            $this->redis->del($key);
        }

        return true;
    }

    /**
     * read data from redis store
     *
     * @param $key
     * @return null
     */
    public function read($key)
    {
        if ($value = $this->redis->get($key)) {
            $arr = unserialize($value);
            $this->changed = $arr['changed'];
            $this->ip      = $arr['ip'];
            $this->vars    = $arr['vars'];
            $this->key     = $key;

            return !empty($this->vars) ? (string) $this->vars : '';
        }

        return '';
    }

    /**
     * write data to redis store
     *
     * @param $key
     * @param $newvars
     * @param $oldvars
     * @return bool
     */
    public function update($key, $newvars, $oldvars)
    {
        $ts = microtime(true);

        if ($newvars !== $oldvars || $ts - $this->changed > $this->lifetime / 3) {
            $data = serialize(array('changed' => time(), 'ip' => $this->ip, 'vars' => $newvars));
            $this->redis->setex($key, $this->lifetime + 60, $data);
        }

        return true;
    }

    /**
     * write data to redis store
     *
     * @param $key
     * @param $vars
     * @return bool
     */
    public function write($key, $vars)
    {
        $data = serialize(array('changed' => time(), 'ip' => $this->ip, 'vars' => $vars));

        return $this->redis->setex($key, $this->lifetime + 60, $data);
    }
}
