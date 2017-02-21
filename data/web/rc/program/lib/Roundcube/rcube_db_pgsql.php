<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Database wrapper class that implements PHP PDO functions            |
 |   for PostgreSQL database                                             |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Database independent query interface
 * This is a wrapper for the PHP PDO
 *
 * @package    Framework
 * @subpackage Database
 */
class rcube_db_pgsql extends rcube_db
{
    public $db_provider = 'postgres';

    /**
     * Driver-specific configuration of database connection
     *
     * @param array $dsn DSN for DB connections
     * @param PDO   $dbh Connection handler
     */
    protected function conn_configure($dsn, $dbh)
    {
        $dbh->query("SET NAMES 'utf8'");
    }

    /**
     * Get last inserted record ID
     *
     * @param string $table Table name (to find the incremented sequence)
     *
     * @return mixed ID or false on failure
     */
    public function insert_id($table = null)
    {
        if (!$this->db_connected || $this->db_mode == 'r') {
            return false;
        }

        if ($table) {
            $table = $this->sequence_name($table);
        }

        $id = $this->dbh->lastInsertId($table);

        return $id;
    }

    /**
     * Return correct name for a specific database sequence
     *
     * @param string $table Table name
     *
     * @return string Translated sequence name
     */
    protected function sequence_name($table)
    {
        // Note: we support only one sequence per table
        // Note: The sequence name must be <table_name>_seq
        $sequence = $table . '_seq';

        // modify sequence name if prefix is configured
        if ($prefix = $this->options['table_prefix']) {
            return $prefix . $sequence;
        }

        return $sequence;
    }

    /**
     * Return SQL statement to convert a field value into a unix timestamp
     *
     * @param string $field Field name
     *
     * @return string SQL statement to use in query
     * @deprecated
     */
    public function unixtimestamp($field)
    {
        return "EXTRACT (EPOCH FROM $field)";
    }

    /**
     * Return SQL function for current time and date
     *
     * @param int $interval Optional interval (in seconds) to add/subtract
     *
     * @return string SQL function to use in query
     */
    public function now($interval = 0)
    {
        if ($interval) {
            $add = ' ' . ($interval > 0 ? '+' : '-') . " interval '";
            $add .= $interval > 0 ? intval($interval) : intval($interval) * -1;
            $add .= " seconds'";
        }

        return "now()" . $add;
    }

    /**
     * Return SQL statement for case insensitive LIKE
     *
     * @param string $column Field name
     * @param string $value  Search value
     *
     * @return string SQL statement to use in query
     */
    public function ilike($column, $value)
    {
        return $this->quote_identifier($column) . ' ILIKE ' . $this->quote($value);
    }

    /**
     * Get database runtime variables
     *
     * @param string $varname Variable name
     * @param mixed  $default Default value if variable is not set
     *
     * @return mixed Variable value or default
     */
    public function get_variable($varname, $default = null)
    {
        // There's a known case when max_allowed_packet is queried
        // PostgreSQL doesn't have such limit, return immediately
        if ($varname == 'max_allowed_packet') {
            return rcube::get_instance()->config->get('db_' . $varname, $default);
        }

        $this->variables[$varname] = rcube::get_instance()->config->get('db_' . $varname);

        if (!isset($this->variables)) {
            $this->variables = array();

            $result = $this->query('SHOW ALL');

            while ($row = $this->fetch_array($result)) {
                $this->variables[$row[0]] = $row[1];
            }
        }

        return isset($this->variables[$varname]) ? $this->variables[$varname] : $default;
    }

    /**
     * Returns list of tables in a database
     *
     * @return array List of all tables of the current database
     */
    public function list_tables()
    {
        // get tables if not cached
        if ($this->tables === null) {
            $q = $this->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES"
                . " WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA NOT IN ('pg_catalog', 'information_schema')"
                . " ORDER BY TABLE_NAME");

            $this->tables = $q ? $q->fetchAll(PDO::FETCH_COLUMN, 0) : array();
        }

        return $this->tables;
    }

    /**
     * Returns PDO DSN string from DSN array
     *
     * @param array $dsn DSN parameters
     *
     * @return string DSN string
     */
    protected function dsn_string($dsn)
    {
        $params = array();
        $result = 'pgsql:';

        if ($dsn['hostspec']) {
            $params[] = 'host=' . $dsn['hostspec'];
        }
        else if ($dsn['socket']) {
            $params[] = 'host=' . $dsn['socket'];
        }

        if ($dsn['port']) {
            $params[] = 'port=' . $dsn['port'];
        }

        if ($dsn['database']) {
            $params[] = 'dbname=' . $dsn['database'];
        }

        if (!empty($params)) {
            $result .= implode(';', $params);
        }

        return $result;
    }

    /**
     * Parse SQL file and fix table names according to table prefix
     */
    protected function fix_table_names($sql)
    {
        if (!$this->options['table_prefix']) {
            return $sql;
        }

        $sql = parent::fix_table_names($sql);

        // replace sequence names, and other postgres-specific commands
        $sql = preg_replace_callback(
            '/((SEQUENCE |RENAME TO |nextval\()["\']*)([^"\' \r\n]+)/',
            array($this, 'fix_table_names_callback'),
            $sql
        );

        return $sql;
    }
}
