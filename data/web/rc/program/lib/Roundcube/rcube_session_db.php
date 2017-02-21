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
 | Author: Cor Bosman <cor@roundcu.be>                                   |
 +-----------------------------------------------------------------------+
*/

/**
 * Class to provide database session storage
 *
 * @package    Framework
 * @subpackage Core
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 * @author     Cor Bosman <cor@roundcu.be>
 */
class rcube_session_db extends rcube_session
{
    private $db;
    private $table_name;

    /**
     * @param Object $config
     */
    public function __construct($config)
    {
        parent::__construct($config);

        // get db instance
        $this->db = rcube::get_instance()->get_dbh();

        // session table name
        $this->table_name = $this->db->table_name('session', true);

        // register sessions handler
        $this->register_session_handler();

        // register db gc handler
        $this->register_gc_handler(array($this, 'gc_db'));
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
     * Handler for session_destroy()
     *
     * @param $key
     * @return bool
     */
    public function destroy($key)
    {
        if ($key) {
            $this->db->query("DELETE FROM {$this->table_name} WHERE `sess_id` = ?", $key);
        }

        return true;
    }

    /**
     * Read session data from database
     *
     * @param string Session ID
     *
     * @return string Session vars
     */
    public function read($key)
    {
        $sql_result = $this->db->query(
            "SELECT `vars`, `ip`, `changed`, " . $this->db->now() . " AS ts"
            . " FROM {$this->table_name} WHERE `sess_id` = ?", $key);

        if ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
            $this->time_diff = time() - strtotime($sql_arr['ts']);
            $this->changed   = strtotime($sql_arr['changed']);
            $this->ip        = $sql_arr['ip'];
            $this->vars      = base64_decode($sql_arr['vars']);
            $this->key       = $key;

            $this->db->reset();

            return !empty($this->vars) ? (string) $this->vars : '';
        }

        return '';
    }

    /**
     * insert new data into db session store
     *
     * @param $key
     * @param $vars
     * @return bool
     */
    public function write($key, $vars)
    {
        $now = $this->db->now();

        $this->db->query("INSERT INTO {$this->table_name}"
            . " (`sess_id`, `vars`, `ip`, `changed`)"
            . " VALUES (?, ?, ?, $now)",
            $key, base64_encode($vars), (string)$this->ip);

        return true;
    }

    /**
     * update session data
     *
     * @param $key
     * @param $newvars
     * @param $oldvars
     *
     * @return bool
     */
    public function update($key, $newvars, $oldvars)
    {
        $now = $this->db->now();
        $ts  = microtime(true);

        // if new and old data are not the same, update data
        // else update expire timestamp only when certain conditions are met
        if ($newvars !== $oldvars) {
            $this->db->query("UPDATE {$this->table_name} "
                . "SET `changed` = $now, `vars` = ? WHERE `sess_id` = ?",
                base64_encode($newvars), $key);
        }
        else if ($ts - $this->changed + $this->time_diff > $this->lifetime / 2) {
            $this->db->query("UPDATE {$this->table_name} SET `changed` = $now"
                . " WHERE `sess_id` = ?", $key);
        }

        return true;
    }

    /**
     * Clean up db sessions.
     */
    public function gc_db()
    {
        // just clean all old sessions when this GC is called
        $this->db->query("DELETE FROM " . $this->db->table_name('session')
            . " WHERE changed < " . $this->db->now(-$this->gc_enabled));

        $this->log("Session GC (DB): remove records < "
            . date('Y-m-d H:i:s', time() - $this->gc_enabled)
            . '; rows = ' . intval($this->db->affected_rows()));
    }
}
