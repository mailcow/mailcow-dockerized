<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2011-2014, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Database wrapper class that implements database functions           |
 |   for Oracle database using OCI8 extension                            |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                   |
 +-----------------------------------------------------------------------+
*/

/**
 * Database independent query interface
 *
 * @package    Framework
 * @subpackage Database
 */
class rcube_db_oracle extends rcube_db
{
    public $db_provider = 'oracle';


    /**
     * Create connection instance
     */
    protected function conn_create($dsn)
    {
        // Get database specific connection options
        $dsn_options = $this->dsn_options($dsn);

        $function = $this->db_pconn ? 'oci_pconnect' : 'oci_connect';

        if (!function_exists($function)) {
            $this->db_error     = true;
            $this->db_error_msg = 'OCI8 extension not loaded. See http://php.net/manual/en/book.oci8.php';

            rcube::raise_error(array('code' => 500, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => $this->db_error_msg), true, false);

            return;
        }

        // connect
        $dbh = @$function($dsn['username'], $dsn['password'], $dsn_options['database'], $dsn_options['charset']);

        if (!$dbh) {
            $error              = oci_error();
            $this->db_error     = true;
            $this->db_error_msg = $error['message'];

            rcube::raise_error(array('code' => 500, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => $this->db_error_msg), true, false);

            return;
        }

        // configure session
        $this->conn_configure($dsn, $dbh);

        return $dbh;
    }

    /**
     * Driver-specific configuration of database connection
     *
     * @param array $dsn DSN for DB connections
     * @param PDO   $dbh Connection handler
     */
    protected function conn_configure($dsn, $dbh)
    {
        $init_queries = array(
            "ALTER SESSION SET nls_date_format = 'YYYY-MM-DD'",
            "ALTER SESSION SET nls_timestamp_format = 'YYYY-MM-DD HH24:MI:SS'",
        );

        foreach ($init_queries as $query) {
            $stmt = oci_parse($dbh, $query);
            oci_execute($stmt);
        }
    }

    /**
     * Connection state checker
     *
     * @return boolean True if in connected state
     */
    public function is_connected()
    {
        return empty($this->dbh) ? false : $this->db_connected;
    }

    /**
     * Execute a SQL query with limits
     *
     * @param string $query   SQL query to execute
     * @param int    $offset  Offset for LIMIT statement
     * @param int    $numrows Number of rows for LIMIT statement
     * @param array  $params  Values to be inserted in query
     *
     * @return PDOStatement|bool Query handle or False on error
     */
    protected function _query($query, $offset, $numrows, $params)
    {
        $query = ltrim($query);

        $this->db_connect($this->dsn_select($query), true);

        // check connection before proceeding
        if (!$this->is_connected()) {
            return $this->last_result = false;
        }

        if ($numrows || $offset) {
            $query = $this->set_limit($query, $numrows, $offset);
        }

        // replace self::DEFAULT_QUOTE with driver-specific quoting
        $query = $this->query_parse($query);

        // Because in Roundcube we mostly use queries that are
        // executed only once, we will not use prepared queries
        $pos  = 0;
        $idx  = 0;
        $args = array();

        if (count($params)) {
            while ($pos = strpos($query, '?', $pos)) {
                if ($query[$pos+1] == '?') {  // skip escaped '?'
                    $pos += 2;
                }
                else {
                    $val = $this->quote($params[$idx++]);

                    // long strings are not allowed inline, need to be parametrized
                    if (strlen($val) > 4000) {
                        $key = ':param' . (count($args) + 1);
                        $args[$key] = $params[$idx-1];
                        $val = $key;
                    }

                    unset($params[$idx-1]);
                    $query = substr_replace($query, $val, $pos, 1);
                    $pos += strlen($val);
                }
            }
        }

        $query = rtrim($query, " \t\n\r\0\x0B;");

        // replace escaped '?' and quotes back to normal, see self::quote()
        $query = str_replace(
            array('??', self::DEFAULT_QUOTE.self::DEFAULT_QUOTE),
            array('?', self::DEFAULT_QUOTE),
            $query
        );

        // log query
        $this->debug($query);

        // destroy reference to previous result
        $this->last_result  = null;
        $this->db_error_msg = null;

        // prepare query
        $result = @oci_parse($this->dbh, $query);
        $mode   = $this->in_transaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;

        if ($result) {
            foreach (array_keys($args) as $param) {
                oci_bind_by_name($result, $param, $args[$param], -1, SQLT_LNG);
            }
        }

        // execute query
        if (!$result || !@oci_execute($result, $mode)) {
            $result = $this->handle_error($query, $result);
        }

        return $this->last_result = $result;
    }

    /**
     * Helper method to handle DB errors.
     * This by default logs the error but could be overriden by a driver implementation
     *
     * @param string Query that triggered the error
     * @return mixed Result to be stored and returned
     */
    protected function handle_error($query, $result = null)
    {
        $error = oci_error(is_resource($result) ? $result : $this->dbh);

        // @TODO: Find error codes for key errors
        if (empty($this->options['ignore_key_errors']) || !in_array($error['code'], array('23000', '23505'))) {
            $this->db_error = true;
            $this->db_error_msg = sprintf('[%s] %s', $error['code'], $error['message']);

            rcube::raise_error(array('code' => 500, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => $this->db_error_msg . " (SQL Query: $query)"
                ), true, false);
        }

        return false;
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
        if (!$this->db_connected || $this->db_mode == 'r' || empty($table)) {
            return false;
        }

        $sequence = $this->quote_identifier($this->sequence_name($table));
        $result   = $this->query("SELECT $sequence.currval FROM dual");
        $result   = $this->fetch_array($result);

        return $result[0] ?: false;
    }

    /**
     * Get number of affected rows for the last query
     *
     * @param mixed $result Optional query handle
     *
     * @return int Number of (matching) rows
     */
    public function affected_rows($result = null)
    {
        if ($result || ($result === null && ($result = $this->last_result))) {
            return oci_num_rows($result);
        }

        return 0;
    }

    /**
     * Get number of rows for a SQL query
     * If no query handle is specified, the last query will be taken as reference
     *
     * @param mixed $result Optional query handle
     * @return mixed   Number of rows or false on failure
     * @deprecated This method shows very poor performance and should be avoided.
     */
    public function num_rows($result = null)
    {
        // not implemented
        return false;
    }

    /**
     * Get an associative array for one row
     * If no query handle is specified, the last query will be taken as reference
     *
     * @param mixed $result Optional query handle
     *
     * @return mixed Array with col values or false on failure
     */
    public function fetch_assoc($result = null)
    {
        return $this->_fetch_row($result, OCI_ASSOC);
    }

    /**
     * Get an index array for one row
     * If no query handle is specified, the last query will be taken as reference
     *
     * @param mixed $result Optional query handle
     *
     * @return mixed Array with col values or false on failure
     */
    public function fetch_array($result = null)
    {
        return $this->_fetch_row($result, OCI_NUM);
    }

    /**
     * Get col values for a result row
     *
     * @param mixed $result Optional query handle
     * @param int   $mode   Fetch mode identifier
     *
     * @return mixed Array with col values or false on failure
     */
    protected function _fetch_row($result, $mode)
    {
        if ($result || ($result === null && ($result = $this->last_result))) {
            return oci_fetch_array($result, $mode + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
        }

        return false;
    }

    /**
     * Formats input so it can be safely used in a query
     * PDO_OCI does not implement quote() method
     *
     * @param mixed  $input Value to quote
     * @param string $type  Type of data (integer, bool, ident)
     *
     * @return string Quoted/converted string for use in query
     */
    public function quote($input, $type = null)
    {
        // handle int directly for better performance
        if ($type == 'integer' || $type == 'int') {
            return intval($input);
        }

        if (is_null($input)) {
            return 'NULL';
        }

        if ($type == 'ident') {
            return $this->quote_identifier($input);
        }

        switch ($type) {
        case 'bool':
        case 'integer':
            return intval($input);
        default:
            return "'" . strtr($input, array(
                    '?' => '??',
                    "'" => "''",
                    rcube_db::DEFAULT_QUOTE => rcube_db::DEFAULT_QUOTE . rcube_db::DEFAULT_QUOTE
            )) . "'";
        }
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
     * Return SQL statement for case insensitive LIKE
     *
     * @param string $column Field name
     * @param string $value  Search value
     *
     * @return string SQL statement to use in query
     */
    public function ilike($column, $value)
    {
        return 'UPPER(' . $this->quote_identifier($column) . ') LIKE UPPER(' . $this->quote($value) . ')';
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
            $interval = intval($interval);
            return "current_timestamp + INTERVAL '$interval' SECOND";
        }

        return "current_timestamp";
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
        return "(($field - to_date('1970-01-01','YYYY-MM-DD')) * 60 * 60 * 24)";
    }

    /**
     * Adds TOP (LIMIT,OFFSET) clause to the query
     *
     * @param string $query  SQL query
     * @param int    $limit  Number of rows
     * @param int    $offset Offset
     *
     * @return string SQL query
     */
    protected function set_limit($query, $limit = 0, $offset = 0)
    {
        $limit  = intval($limit);
        $offset = intval($offset);
        $end    = $offset + $limit;

        // @TODO: Oracle 12g has better OFFSET support

        if (!$offset) {
            $query = "SELECT * FROM ($query) a WHERE rownum <= $end";
        }
        else {
            $query = "SELECT * FROM (SELECT a.*, rownum as rn FROM ($query) a WHERE rownum <= $end) b WHERE rn > $offset";
        }

        return $query;
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

        // replace sequence names, and other Oracle-specific commands
        $sql = preg_replace_callback('/((SEQUENCE ["]?)([^" \r\n]+)/',
            array($this, 'fix_table_names_callback'),
            $sql
        );

        $sql = preg_replace_callback(
            '/([ \r\n]+["]?)([^"\' \r\n\.]+)(["]?\.nextval)/',
            array($this, 'fix_table_names_seq_callback'),
            $sql
        );

        return $sql;
    }

    /**
     * Preg_replace callback for fix_table_names()
     */
    protected function fix_table_names_seq_callback($matches)
    {
        return $matches[1] . $this->options['table_prefix'] . $matches[2] . $matches[3];
    }

    /**
     * Returns connection options from DSN array
     */
    protected function dsn_options($dsn)
    {
        $params = array();

        if ($dsn['hostspec']) {
            $host = $dsn['hostspec'];
            if ($dsn['port']) {
                $host .= ':' . $dsn['port'];
            }

            $params['database'] = $host . '/' . $dsn['database'];
        }

        $params['charset'] = 'UTF8';

        return $params;
    }

    /**
     * Execute the given SQL script
     *
     * @param string $sql SQL queries to execute
     *
     * @return boolen True on success, False on error
     */
    public function exec_script($sql)
    {
        $sql  = $this->fix_table_names($sql);
        $buff = '';
        $body = false;

        foreach (explode("\n", $sql) as $line) {
            $tok = strtolower(trim($line));
            if (preg_match('/^--/', $line) || $tok == '' || $tok == '/') {
                continue;
            }

            $buff .= $line . "\n";

            // detect PL/SQL function bodies, don't break on semicolon
            if ($body && $tok == 'end;') {
                $body = false;
            }
            else if (!$body && $tok == 'begin') {
                $body = true;
            }

            if (!$body && substr($tok, -1) == ';') {
                $this->query($buff);
                $buff = '';
                if ($this->db_error) {
                    break;
                }
            }
        }

        return !$this->db_error;
    }

    /**
     * Start transaction
     *
     * @return bool True on success, False on failure
     */
    public function startTransaction()
    {
        $this->db_connect('w', true);

        // check connection before proceeding
        if (!$this->is_connected()) {
            return $this->last_result = false;
        }

        $this->debug('BEGIN TRANSACTION');

        return $this->last_result = $this->in_transaction = true;
    }

    /**
     * Commit transaction
     *
     * @return bool True on success, False on failure
     */
    public function endTransaction()
    {
        $this->db_connect('w', true);

        // check connection before proceeding
        if (!$this->is_connected()) {
            return $this->last_result = false;
        }

        $this->debug('COMMIT TRANSACTION');

        if ($result = @oci_commit($this->dbh)) {
            $this->in_transaction = true;
        }
        else {
            $this->handle_error('COMMIT');
        }

        return $this->last_result = $result;
    }

    /**
     * Rollback transaction
     *
     * @return bool True on success, False on failure
     */
    public function rollbackTransaction()
    {
        $this->db_connect('w', true);

        // check connection before proceeding
        if (!$this->is_connected()) {
            return $this->last_result = false;
        }

        $this->debug('ROLLBACK TRANSACTION');

        if (@oci_rollback($this->dbh)) {
            $this->in_transaction = false;
        }
        else {
            $this->handle_error('ROLLBACK');
        }

        return $this->last_result = $this->dbh->rollBack();
    }

    /**
     * Terminate database connection.
     */
    public function closeConnection()
    {
        // release statement and close connection(s)
        $this->last_result = null;
        foreach ($this->dbhs as $dbh) {
            oci_close($dbh);
        }

        parent::closeConnection();
    }
}
