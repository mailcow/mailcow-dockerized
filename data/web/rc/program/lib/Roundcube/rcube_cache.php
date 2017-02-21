<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2011, The Roundcube Dev Team                            |
 | Copyright (C) 2011, Kolab Systems AG                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Caching engine                                                      |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Interface class for accessing Roundcube cache
 *
 * @package    Framework
 * @subpackage Cache
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 */
class rcube_cache
{
    /**
     * Instance of database handler
     *
     * @var rcube_db|Memcache|bool
     */
    private $db;
    private $type;
    private $userid;
    private $prefix;
    private $table;
    private $ttl;
    private $packed;
    private $index;
    private $debug;
    private $index_changed = false;
    private $cache         = array();
    private $cache_changes = array();
    private $cache_sums    = array();
    private $max_packet    = -1;


    /**
     * Object constructor.
     *
     * @param string $type   Engine type ('db' or 'memcache' or 'apc')
     * @param int    $userid User identifier
     * @param string $prefix Key name prefix
     * @param string $ttl    Expiration time of memcache/apc items
     * @param bool   $packed Enables/disabled data serialization.
     *                       It's possible to disable data serialization if you're sure
     *                       stored data will be always a safe string
     */
    function __construct($type, $userid, $prefix='', $ttl=0, $packed=true)
    {
        $rcube = rcube::get_instance();
        $type  = strtolower($type);

        if ($type == 'memcache') {
            $this->type  = 'memcache';
            $this->db    = $rcube->get_memcache();
            $this->debug = $rcube->config->get('memcache_debug');
        }
        else if ($type == 'apc') {
            $this->type  = 'apc';
            $this->db    = function_exists('apc_exists'); // APC 3.1.4 required
            $this->debug = $rcube->config->get('apc_debug');
        }
        else {
            $this->type  = 'db';
            $this->db    = $rcube->get_dbh();
            $this->table = $this->db->table_name('cache', true);
        }

        // convert ttl string to seconds
        $ttl = get_offset_sec($ttl);
        if ($ttl > 2592000) $ttl = 2592000;

        $this->userid    = (int) $userid;
        $this->ttl       = $ttl;
        $this->packed    = $packed;
        $this->prefix    = $prefix;
    }

    /**
     * Returns cached value.
     *
     * @param string $key Cache key name
     *
     * @return mixed Cached value
     */
    function get($key)
    {
        if (!array_key_exists($key, $this->cache)) {
            return $this->read_record($key);
        }

        return $this->cache[$key];
    }

    /**
     * Sets (add/update) value in cache.
     *
     * @param string $key  Cache key name
     * @param mixed  $data Cache data
     */
    function set($key, $data)
    {
        $this->cache[$key]         = $data;
        $this->cache_changes[$key] = true;
    }

    /**
     * Returns cached value without storing it in internal memory.
     *
     * @param string $key Cache key name
     *
     * @return mixed Cached value
     */
    function read($key)
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->read_record($key, true);
    }

    /**
     * Sets (add/update) value in cache and immediately saves
     * it in the backend, no internal memory will be used.
     *
     * @param string $key  Cache key name
     * @param mixed  $data Cache data
     *
     * @param boolean True on success, False on failure
     */
    function write($key, $data)
    {
        return $this->write_record($key, $this->serialize($data));
    }

    /**
     * Clears the cache.
     *
     * @param string  $key         Cache key name or pattern
     * @param boolean $prefix_mode Enable it to clear all keys starting
     *                             with prefix specified in $key
     */
    function remove($key=null, $prefix_mode=false)
    {
        // Remove all keys
        if ($key === null) {
            $this->cache         = array();
            $this->cache_changes = array();
            $this->cache_sums    = array();
        }
        // Remove keys by name prefix
        else if ($prefix_mode) {
            foreach (array_keys($this->cache) as $k) {
                if (strpos($k, $key) === 0) {
                    $this->cache[$k] = null;
                    $this->cache_changes[$k] = false;
                    unset($this->cache_sums[$k]);
                }
            }
        }
        // Remove one key by name
        else {
            $this->cache[$key] = null;
            $this->cache_changes[$key] = false;
            unset($this->cache_sums[$key]);
        }

        // Remove record(s) from the backend
        $this->remove_record($key, $prefix_mode);
    }

    /**
     * Remove cache records older than ttl
     */
    function expunge()
    {
        if ($this->type == 'db' && $this->db && $this->ttl) {
            $this->db->query(
                "DELETE FROM {$this->table}".
                " WHERE `user_id` = ?".
                " AND `cache_key` LIKE ?".
                " AND `expires` < " . $this->db->now(),
                $this->userid,
                $this->prefix.'.%');
        }
    }

    /**
     * Remove expired records of all caches
     */
    static function gc()
    {
        $rcube = rcube::get_instance();
        $db    = $rcube->get_dbh();

        $db->query("DELETE FROM " . $db->table_name('cache', true) . " WHERE `expires` < " . $db->now());
    }

    /**
     * Writes the cache back to the DB.
     */
    function close()
    {
        foreach ($this->cache as $key => $data) {
            // The key has been used
            if ($this->cache_changes[$key]) {
                // Make sure we're not going to write unchanged data
                // by comparing current md5 sum with the sum calculated on DB read
                $data = $this->serialize($data);

                if (!$this->cache_sums[$key] || $this->cache_sums[$key] != md5($data)) {
                    $this->write_record($key, $data);
                }
            }
        }

        if ($this->index_changed) {
            $this->write_index();
        }

        // reset internal cache index, thanks to this we can force index reload
        $this->index         = null;
        $this->index_changed = false;
        $this->cache         = array();
        $this->cache_sums    = array();
        $this->cache_changes = array();
    }

    /**
     * Reads cache entry.
     *
     * @param string  $key     Cache key name
     * @param boolean $nostore Enable to skip in-memory store
     *
     * @return mixed Cached value
     */
    private function read_record($key, $nostore=false)
    {
        if (!$this->db) {
            return null;
        }

        if ($this->type != 'db') {
            $this->load_index();

            // Consistency check (#1490390)
            if (!in_array($key, $this->index)) {
                // we always check if the key exist in the index
                // to have data in consistent state. Keeping the index consistent
                // is needed for keys delete operation when we delete all keys or by prefix.
            }
            else {
                $ckey = $this->ckey($key);

                if ($this->type == 'memcache') {
                    $data = $this->db->get($ckey);
                }
                else if ($this->type == 'apc') {
                    $data = apc_fetch($ckey);
                }

                if ($this->debug) {
                    $this->debug('get', $ckey, $data);
                }
            }

            if ($data !== false) {
                $md5sum = md5($data);
                $data   = $this->unserialize($data);

                if ($nostore) {
                    return $data;
                }

                $this->cache_sums[$key] = $md5sum;
                $this->cache[$key]      = $data;
            }
            else {
                $this->cache[$key] = null;
            }
        }
        else {
            $sql_result = $this->db->query(
                "SELECT `data`, `cache_key` FROM {$this->table}"
                . " WHERE `user_id` = ? AND `cache_key` = ?",
                $this->userid, $this->prefix.'.'.$key);

            if ($sql_arr = $this->db->fetch_assoc($sql_result)) {
                if (strlen($sql_arr['data']) > 0) {
                    $md5sum = md5($sql_arr['data']);
                    $data   = $this->unserialize($sql_arr['data']);
                }

                $this->db->reset();

                if ($nostore) {
                    return $data;
                }

                $this->cache[$key]      = $data;
                $this->cache_sums[$key] = $md5sum;
            }
            else {
                $this->cache[$key] = null;
            }
        }

        return $this->cache[$key];
    }

    /**
     * Writes single cache record into DB.
     *
     * @param string $key  Cache key name
     * @param mixed  $data Serialized cache data
     *
     * @param boolean True on success, False on failure
     */
    private function write_record($key, $data)
    {
        if (!$this->db) {
            return false;
        }

        // don't attempt to write too big data sets
        if (strlen($data) > $this->max_packet_size()) {
            trigger_error("rcube_cache: max_packet_size ($this->max_packet) exceeded for key $key. Tried to write " . strlen($data) . " bytes", E_USER_WARNING);
            return false;
        }

        if ($this->type == 'memcache' || $this->type == 'apc') {
            $result = $this->add_record($this->ckey($key), $data);

            // make sure index will be updated
            if ($result) {
                if (!array_key_exists($key, $this->cache_sums)) {
                    $this->cache_sums[$key] = true;
                }

                $this->load_index();

                if (!$this->index_changed && !in_array($key, $this->index)) {
                    $this->index_changed = true;
                }
            }

            return $result;
        }

        $db_key = $this->prefix . '.' . $key;

        // Remove NULL rows (here we don't need to check if the record exist)
        if ($data == 'N;') {
            $result = $this->db->query(
                "DELETE FROM {$this->table}".
                " WHERE `user_id` = ? AND `cache_key` = ?",
                $this->userid, $db_key);

            return !$this->db->is_error($result);
        }

        $key_exists = array_key_exists($key, $this->cache_sums);
        $expires    = $this->ttl ? $this->db->now($this->ttl) : 'NULL';

        if (!$key_exists) {
            // Try INSERT temporarily ignoring "duplicate key" errors
            $this->db->set_option('ignore_key_errors', true);

            $result = $this->db->query(
                "INSERT INTO {$this->table} (`expires`, `user_id`, `cache_key`, `data`)"
                . " VALUES ($expires, ?, ?, ?)",
                $this->userid, $db_key, $data);

            $this->db->set_option('ignore_key_errors', false);
        }

        // otherwise try UPDATE
        if (!isset($result) || !($count = $this->db->affected_rows($result))) {
            $result = $this->db->query(
                "UPDATE {$this->table} SET `expires` = $expires, `data` = ?"
                . " WHERE `user_id` = ? AND `cache_key` = ?",
                $data, $this->userid, $db_key);

            $count = $this->db->affected_rows($result);
        }

        return $count > 0;
    }

    /**
     * Deletes the cache record(s).
     *
     * @param string  $key         Cache key name or pattern
     * @param boolean $prefix_mode Enable it to clear all keys starting
     *                             with prefix specified in $key
     */
    private function remove_record($key=null, $prefix_mode=false)
    {
        if (!$this->db) {
            return;
        }

        if ($this->type != 'db') {
            $this->load_index();

            // Remove all keys
            if ($key === null) {
                foreach ($this->index as $key) {
                    $this->delete_record($this->ckey($key));
                }

                $this->index = array();
            }
            // Remove keys by name prefix
            else if ($prefix_mode) {
                foreach ($this->index as $idx => $k) {
                    if (strpos($k, $key) === 0) {
                        $this->delete_record($this->ckey($k));
                        unset($this->index[$idx]);
                    }
                }
            }
            // Remove one key by name
            else {
                $this->delete_record($this->ckey($key));
                if (($idx = array_search($key, $this->index)) !== false) {
                    unset($this->index[$idx]);
                }
            }

            $this->index_changed = true;

            return;
        }

        // Remove all keys (in specified cache)
        if ($key === null) {
            $where = " AND `cache_key` LIKE " . $this->db->quote($this->prefix.'.%');
        }
        // Remove keys by name prefix
        else if ($prefix_mode) {
            $where = " AND `cache_key` LIKE " . $this->db->quote($this->prefix.'.'.$key.'%');
        }
        // Remove one key by name
        else {
            $where = " AND `cache_key` = " . $this->db->quote($this->prefix.'.'.$key);
        }

        $this->db->query(
            "DELETE FROM {$this->table} WHERE `user_id` = ?" . $where,
            $this->userid);
    }

    /**
     * Adds entry into memcache/apc DB.
     *
     * @param string $key  Cache key name
     * @param mixed  $data Serialized cache data
     *
     * @param boolean True on success, False on failure
     */
    private function add_record($key, $data)
    {
        if ($this->type == 'memcache') {
            $result = $this->db->replace($key, $data, MEMCACHE_COMPRESSED, $this->ttl);

            if (!$result) {
                $result = $this->db->set($key, $data, MEMCACHE_COMPRESSED, $this->ttl);
            }
        }
        else if ($this->type == 'apc') {
            if (apc_exists($key)) {
                apc_delete($key);
            }

            $result = apc_store($key, $data, $this->ttl);
        }

        if ($this->debug) {
            $this->debug('set', $key, $data, $result);
        }

        return $result;
    }

    /**
     * Deletes entry from memcache/apc DB.
     *
     * @param string $key Cache key name
     *
     * @param boolean True on success, False on failure
     */
    private function delete_record($key)
    {
        if ($this->type == 'memcache') {
            // #1488592: use 2nd argument
            $result = $this->db->delete($key, 0);
        }
        else {
            $result = apc_delete($key);
        }

        if ($this->debug) {
            $this->debug('delete', $key, null, $result);
        }

        return $result;
    }

    /**
     * Writes the index entry into memcache/apc DB.
     */
    private function write_index()
    {
        if (!$this->db || $this->type == 'db') {
            return;
        }

        $this->load_index();

        // Make sure index contains new keys
        foreach ($this->cache as $key => $value) {
            if ($value !== null && !in_array($key, $this->index)) {
                $this->index[] = $key;
            }
        }

        // new keys added using self::write()
        foreach ($this->cache_sums as $key => $value) {
            if ($value === true && !in_array($key, $this->index)) {
                $this->index[] = $key;
            }
        }

        $data = serialize($this->index);
        $this->add_record($this->ikey(), $data);
    }

    /**
     * Gets the index entry from memcache/apc DB.
     */
    private function load_index()
    {
        if (!$this->db || $this->type == 'db') {
            return;
        }

        if ($this->index !== null) {
            return;
        }

        $index_key = $this->ikey();

        if ($this->type == 'memcache') {
            $data = $this->db->get($index_key);
        }
        else if ($this->type == 'apc') {
            $data = apc_fetch($index_key);
        }

        if ($this->debug) {
            $this->debug('get', $index_key, $data);
        }

        $this->index = $data ? unserialize($data) : array();
    }

    /**
     * Creates per-user cache key name (for memcache and apc)
     *
     * @param string $key Cache key name
     *
     * @return string Cache key
     */
    private function ckey($key)
    {
        return sprintf('%d:%s:%s', $this->userid, $this->prefix, $key);
    }

    /**
     * Creates per-user index cache key name (for memcache and apc)
     *
     * @return string Cache key
     */
    private function ikey()
    {
        // This way each cache will have its own index
        return sprintf('%d:%s%s', $this->userid, $this->prefix, 'INDEX');
    }

    /**
     * Serializes data for storing
     */
    private function serialize($data)
    {
        if ($this->type == 'db') {
            return $this->db->encode($data, $this->packed);
        }

        return $this->packed ? serialize($data) : $data;
    }

    /**
     * Unserializes serialized data
     */
    private function unserialize($data)
    {
        if ($this->type == 'db') {
            return $this->db->decode($data, $this->packed);
        }

        return $this->packed ? @unserialize($data) : $data;
    }

    /**
     * Determine the maximum size for cache data to be written
     */
    private function max_packet_size()
    {
        if ($this->max_packet < 0) {
            $this->max_packet = 2097152; // default/max is 2 MB

            if ($this->type == 'db') {
                if ($value = $this->db->get_variable('max_allowed_packet', $this->max_packet)) {
                    $this->max_packet = $value;
                }
                $this->max_packet -= 2000;
            }
            else {
                $max_packet = rcube::get_instance()->config->get($this->type . '_max_allowed_packet');
                $this->max_packet = parse_bytes($max_packet) ?: $this->max_packet;
            }
        }

        return $this->max_packet;
    }

    /**
     * Write memcache/apc debug info to the log
     */
    private function debug($type, $key, $data = null, $result = null)
    {
        $line = strtoupper($type) . ' ' . $key;

        if ($data !== null) {
            $line .= ' ' . ($this->packed ? $data : serialize($data));
        }

        rcube::debug($this->type, $line, $result);
    }
}
