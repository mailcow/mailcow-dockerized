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
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Database independent query interface.
 * This is a wrapper for the PHP PDO.
 *
 * @package   Framework
 * @subpackage Database
 */
class rcube_db
{
    public $db_provider;

    protected $db_dsnw;               // DSN for write operations
    protected $db_dsnr;               // DSN for read operations
    protected $db_connected = false;  // Already connected ?
    protected $db_mode;               // Connection mode
    protected $dbh;                   // Connection handle
    protected $dbhs = array();
    protected $table_connections = array();

    protected $db_error     = false;
    protected $db_error_msg = '';
    protected $conn_failure = false;
    protected $db_index     = 0;
    protected $last_result;
    protected $tables;
    protected $variables;

    protected $options = array(
        // column/table quotes
        'identifier_start' => '"',
        'identifier_end'   => '"',
    );

    const DEBUG_LINE_LENGTH = 4096;
    const DEFAULT_QUOTE     = '`';

    /**
     * Factory, returns driver-specific instance of the class
     *
     * @param string $db_dsnw DSN for read/write operations
     * @param string $db_dsnr Optional DSN for read only operations
     * @param bool   $pconn   Enables persistent connections
     *
     * @return rcube_db Object instance
     */
    public static function factory($db_dsnw, $db_dsnr = '', $pconn = false)
    {
        $driver     = strtolower(substr($db_dsnw, 0, strpos($db_dsnw, ':')));
        $driver_map = array(
            'sqlite2' => 'sqlite',
            'sybase'  => 'mssql',
            'dblib'   => 'mssql',
            'mysqli'  => 'mysql',
            'oci'     => 'oracle',
            'oci8'    => 'oracle',
        );

        $driver = isset($driver_map[$driver]) ? $driver_map[$driver] : $driver;
        $class  = "rcube_db_$driver";

        if (!$driver || !class_exists($class)) {
            rcube::raise_error(array('code' => 600, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => "Configuration error. Unsupported database driver: $driver"),
                true, true);
        }

        return new $class($db_dsnw, $db_dsnr, $pconn);
    }

    /**
     * Object constructor
     *
     * @param string $db_dsnw DSN for read/write operations
     * @param string $db_dsnr Optional DSN for read only operations
     * @param bool   $pconn   Enables persistent connections
     */
    public function __construct($db_dsnw, $db_dsnr = '', $pconn = false)
    {
        if (empty($db_dsnr)) {
            $db_dsnr = $db_dsnw;
        }

        $this->db_dsnw  = $db_dsnw;
        $this->db_dsnr  = $db_dsnr;
        $this->db_pconn = $pconn;

        $this->db_dsnw_array = self::parse_dsn($db_dsnw);
        $this->db_dsnr_array = self::parse_dsn($db_dsnr);

        $config = rcube::get_instance()->config;

        $this->options['table_prefix']  = $config->get('db_prefix');
        $this->options['dsnw_noread']   = $config->get('db_dsnw_noread', false);
        $this->options['table_dsn_map'] = array_map(array($this, 'table_name'), $config->get('db_table_dsn', array()));
    }

    /**
     * Connect to specific database
     *
     * @param array  $dsn  DSN for DB connections
     * @param string $mode Connection mode (r|w)
     */
    protected function dsn_connect($dsn, $mode)
    {
        $this->db_error     = false;
        $this->db_error_msg = null;

        // return existing handle
        if ($this->dbhs[$mode]) {
            $this->dbh = $this->dbhs[$mode];
            $this->db_mode = $mode;
            return $this->dbh;
        }

        // connect to database
        if ($dbh = $this->conn_create($dsn)) {
            $this->dbh          = $dbh;
            $this->dbhs[$mode]  = $dbh;
            $this->db_mode      = $mode;
            $this->db_connected = true;
        }
    }

    /**
     * Create PDO connection
     */
    protected function conn_create($dsn)
    {
        // Get database specific connection options
        $dsn_string  = $this->dsn_string($dsn);
        $dsn_options = $this->dsn_options($dsn);

        // Connect
        try {
            // with this check we skip fatal error on PDO object creation
            if (!class_exists('PDO', false)) {
                throw new Exception('PDO extension not loaded. See http://php.net/manual/en/intro.pdo.php');
            }

            $this->conn_prepare($dsn);

            $dbh = new PDO($dsn_string, $dsn['username'], $dsn['password'], $dsn_options);

            // don't throw exceptions or warnings
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

            $this->conn_configure($dsn, $dbh);
        }
        catch (Exception $e) {
            $this->db_error     = true;
            $this->db_error_msg = $e->getMessage();

            rcube::raise_error(array('code' => 500, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => $this->db_error_msg), true, false);

            return null;
        }

        return $dbh;
    }

    /**
     * Driver-specific preparation of database connection
     *
     * @param array $dsn DSN for DB connections
     */
    protected function conn_prepare($dsn)
    {
    }

    /**
     * Driver-specific configuration of database connection
     *
     * @param array $dsn DSN for DB connections
     * @param PDO   $dbh Connection handler
     */
    protected function conn_configure($dsn, $dbh)
    {
    }

    /**
     * Connect to appropriate database depending on the operation
     *
     * @param string  $mode  Connection mode (r|w)
     * @param boolean $force Enforce using the given mode
     */
    public function db_connect($mode, $force = false)
    {
        // previous connection failed, don't attempt to connect again
        if ($this->conn_failure) {
            return;
        }

        // no replication
        if ($this->db_dsnw == $this->db_dsnr) {
            $mode = 'w';
        }

        // Already connected
        if ($this->db_connected) {
            // connected to db with the same or "higher" mode (if allowed)
            if ($this->db_mode == $mode || $this->db_mode == 'w' && !$force && !$this->options['dsnw_noread']) {
                return;
            }
        }

        $dsn = ($mode == 'r') ? $this->db_dsnr_array : $this->db_dsnw_array;
        $this->dsn_connect($dsn, $mode);

        // use write-master when read-only fails
        if (!$this->db_connected && $mode == 'r' && $this->is_replicated()) {
            $this->dsn_connect($this->db_dsnw_array, 'w');
        }

        $this->conn_failure = !$this->db_connected;
    }

    /**
     * Analyze the given SQL statement and select the appropriate connection to use
     */
    protected function dsn_select($query)
    {
        // no replication
        if ($this->db_dsnw == $this->db_dsnr) {
            return 'w';
        }

        // Read or write ?
        $mode = preg_match('/^(select|show|set)/i', $query) ? 'r' : 'w';

        $start = '[' . $this->options['identifier_start'] . self::DEFAULT_QUOTE . ']';
        $end   = '[' . $this->options['identifier_end']   . self::DEFAULT_QUOTE . ']';
        $regex = '/(?:^|\s)(from|update|into|join)\s+'.$start.'?([a-z0-9._]+)'.$end.'?\s+/i';

        // find tables involved in this query
        if (preg_match_all($regex, $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $table = $m[2];

                // always use direct mapping
                if ($this->options['table_dsn_map'][$table]) {
                    $mode = $this->options['table_dsn_map'][$table];
                    break;  // primary table rules
                }
                else if ($mode == 'r') {
                    // connected to db with the same or "higher" mode for this table
                    $db_mode = $this->table_connections[$table];
                    if ($db_mode == 'w' && !$this->options['dsnw_noread']) {
                        $mode = $db_mode;
                    }
                }
            }

            // remember mode chosen (for primary table)
            $table = $matches[0][2];
            $this->table_connections[$table] = $mode;
        }

        return $mode;
    }

    /**
     * Activate/deactivate debug mode
     *
     * @param boolean $dbg True if SQL queries should be logged
     */
    public function set_debug($dbg = true)
    {
        $this->options['debug_mode'] = $dbg;
    }

    /**
     * Writes debug information/query to 'sql' log file
     *
     * @param string $query SQL query
     */
    protected function debug($query)
    {
        if ($this->options['debug_mode']) {
            if (($len = strlen($query)) > self::DEBUG_LINE_LENGTH) {
                $diff  = $len - self::DEBUG_LINE_LENGTH;
                $query = substr($query, 0, self::DEBUG_LINE_LENGTH)
                    . "... [truncated $diff bytes]";
            }
            rcube::write_log('sql', '[' . (++$this->db_index) . '] ' . $query . ';');
        }
    }

    /**
     * Getter for error state
     *
     * @param mixed $result Optional query result
     *
     * @return string Error message
     */
    public function is_error($result = null)
    {
        if ($result !== null) {
            return $result === false ? $this->db_error_msg : null;
        }

        return $this->db_error ? $this->db_error_msg : null;
    }

    /**
     * Connection state checker
     *
     * @return boolean True if in connected state
     */
    public function is_connected()
    {
        return !is_object($this->dbh) ? false : $this->db_connected;
    }

    /**
     * Is database replication configured?
     *
     * @return bool Returns true if dsnw != dsnr
     */
    public function is_replicated()
    {
      return !empty($this->db_dsnr) && $this->db_dsnw != $this->db_dsnr;
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
        // to be implemented by driver class
        return rcube::get_instance()->config->get('db_' . $varname, $default);
    }

    /**
     * Execute a SQL query
     *
     * @param string SQL query to execute
     * @param mixed  Values to be inserted in query
     *
     * @return number  Query handle identifier
     */
    public function query()
    {
        $params = func_get_args();
        $query = array_shift($params);

        // Support one argument of type array, instead of n arguments
        if (count($params) == 1 && is_array($params[0])) {
            $params = $params[0];
        }

        return $this->_query($query, 0, 0, $params);
    }

    /**
     * Execute a SQL query with limits
     *
     * @param string SQL query to execute
     * @param int    Offset for LIMIT statement
     * @param int    Number of rows for LIMIT statement
     * @param mixed  Values to be inserted in query
     *
     * @return PDOStatement|bool Query handle or False on error
     */
    public function limitquery()
    {
        $params  = func_get_args();
        $query   = array_shift($params);
        $offset  = array_shift($params);
        $numrows = array_shift($params);

        return $this->_query($query, $offset, $numrows, $params);
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
        $pos = 0;
        $idx = 0;

        if (count($params)) {
            while ($pos = strpos($query, '?', $pos)) {
                if ($query[$pos+1] == '?') {  // skip escaped '?'
                    $pos += 2;
                }
                else {
                    $val = $this->quote($params[$idx++]);
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

        return $this->query_execute($query);
    }

    /**
     * Query execution
     */
    protected function query_execute($query)
    {
        // destroy reference to previous result, required for SQLite driver (#1488874)
        $this->last_result  = null;
        $this->db_error_msg = null;

        // send query
        $result = $this->dbh->query($query);

        if ($result === false) {
            $result = $this->handle_error($query);
        }

        return $this->last_result = $result;
    }

    /**
     * Parse SQL query and replace identifier quoting
     *
     * @param string $query SQL query
     *
     * @return string SQL query
     */
    protected function query_parse($query)
    {
        $start = $this->options['identifier_start'];
        $end   = $this->options['identifier_end'];
        $quote = self::DEFAULT_QUOTE;

        if ($start == $quote) {
            return $query;
        }

        $pos = 0;
        $in  = false;

        while ($pos = strpos($query, $quote, $pos)) {
            if ($query[$pos+1] == $quote) {  // skip escaped quote
                $pos += 2;
            }
            else {
                if ($in) {
                    $q  = $end;
                    $in = false;
                }
                else {
                    $q  = $start;
                    $in = true;
                }

                $query = substr_replace($query, $q, $pos, 1);
                $pos++;
            }
        }

        return $query;
    }

    /**
     * Helper method to handle DB errors.
     * This by default logs the error but could be overriden by a driver implementation
     *
     * @param string $query Query that triggered the error
     *
     * @return mixed Result to be stored and returned
     */
    protected function handle_error($query)
    {
        $error = $this->dbh->errorInfo();

        if (empty($this->options['ignore_key_errors']) || !in_array($error[0], array('23000', '23505'))) {
            $this->db_error = true;
            $this->db_error_msg = sprintf('[%s] %s', $error[1], $error[2]);

            rcube::raise_error(array('code' => 500, 'type' => 'db',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => $this->db_error_msg . " (SQL Query: $query)"
                ), true, false);
        }

        return false;
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
            if ($result !== true) {
                return $result->rowCount();
            }
        }

        return 0;
    }

    /**
     * Get number of rows for a SQL query
     * If no query handle is specified, the last query will be taken as reference
     *
     * @param mixed $result Optional query handle
     *
     * @return mixed Number of rows or false on failure
     * @deprecated This method shows very poor performance and should be avoided.
     */
    public function num_rows($result = null)
    {
        if (($result || ($result === null && ($result = $this->last_result))) && $result !== true) {
            // repeat query with SELECT COUNT(*) ...
            if (preg_match('/^SELECT\s+(?:ALL\s+|DISTINCT\s+)?(?:.*?)\s+FROM\s+(.*)$/ims', $result->queryString, $m)) {
                $query = $this->dbh->query('SELECT COUNT(*) FROM ' . $m[1], PDO::FETCH_NUM);
                return $query ? intval($query->fetchColumn(0)) : false;
            }
            else {
                $num = count($result->fetchAll());
                $result->execute();  // re-execute query because there's no seek(0)
                return $num;
            }
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
    public function insert_id($table = '')
    {
        if (!$this->db_connected || $this->db_mode == 'r') {
            return false;
        }

        if ($table) {
            // resolve table name
            $table = $this->table_name($table);
        }

        $id = $this->dbh->lastInsertId($table);

        return $id;
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
        return $this->_fetch_row($result, PDO::FETCH_ASSOC);
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
        return $this->_fetch_row($result, PDO::FETCH_NUM);
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
            if ($result !== true) {
                return $result->fetch($mode);
            }
        }

        return false;
    }

    /**
     * Adds LIMIT,OFFSET clauses to the query
     *
     * @param string $query  SQL query
     * @param int    $limit  Number of rows
     * @param int    $offset Offset
     *
     * @return string SQL query
     */
    protected function set_limit($query, $limit = 0, $offset = 0)
    {
        if ($limit) {
            $query .= ' LIMIT ' . intval($limit);
        }

        if ($offset) {
            $query .= ' OFFSET ' . intval($offset);
        }

        return $query;
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
                . " WHERE TABLE_TYPE = 'BASE TABLE'"
                . " ORDER BY TABLE_NAME");

            $this->tables = $q ? $q->fetchAll(PDO::FETCH_COLUMN, 0) : array();
        }

        return $this->tables;
    }

    /**
     * Returns list of columns in database table
     *
     * @param string $table Table name
     *
     * @return array List of table cols
     */
    public function list_cols($table)
    {
        $q = $this->query('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?',
            array($table));

        if ($q) {
            return $q->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        return array();
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

        return $this->last_result = $this->dbh->beginTransaction();
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

        return $this->last_result = $this->dbh->commit();
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

        return $this->last_result = $this->dbh->rollBack();
    }

    /**
     * Release resources related to the last query result.
     * When we know we don't need to access the last query result we can destroy it
     * and release memory. Usefull especially if the query returned big chunk of data.
     */
    public function reset()
    {
        $this->last_result = null;
    }

    /**
     * Terminate database connection.
     */
    public function closeConnection()
    {
        $this->db_connected = false;
        $this->db_index     = 0;

        // release statement and connection resources
        $this->last_result  = null;
        $this->dbh          = null;
        $this->dbhs         = array();
    }

    /**
     * Formats input so it can be safely used in a query
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

        // create DB handle if not available
        if (!$this->dbh) {
            $this->db_connect('r');
        }

        if ($this->dbh) {
            $map = array(
                'bool'    => PDO::PARAM_BOOL,
                'integer' => PDO::PARAM_INT,
            );

            $type = isset($map[$type]) ? $map[$type] : PDO::PARAM_STR;

            return strtr($this->dbh->quote($input, $type),
                // escape ? and `
                array('?' => '??', self::DEFAULT_QUOTE => self::DEFAULT_QUOTE.self::DEFAULT_QUOTE)
            );
        }

        return 'NULL';
    }

    /**
     * Escapes a string so it can be safely used in a query
     *
     * @param string $str A string to escape
     *
     * @return string Escaped string for use in a query
     */
    public function escape($str)
    {
        if (is_null($str)) {
            return 'NULL';
        }

        return substr($this->quote($str), 1, -1);
    }

    /**
     * Quotes a string so it can be safely used as a table or column name
     *
     * @param string $str Value to quote
     *
     * @return string Quoted string for use in query
     * @deprecated    Replaced by rcube_db::quote_identifier
     * @see           rcube_db::quote_identifier
     */
    public function quoteIdentifier($str)
    {
        return $this->quote_identifier($str);
    }

    /**
     * Escapes a string so it can be safely used in a query
     *
     * @param string $str A string to escape
     *
     * @return string Escaped string for use in a query
     * @deprecated    Replaced by rcube_db::escape
     * @see           rcube_db::escape
     */
    public function escapeSimple($str)
    {
        return $this->escape($str);
    }

    /**
     * Quotes a string so it can be safely used as a table or column name
     *
     * @param string $str Value to quote
     *
     * @return string Quoted string for use in query
     */
    public function quote_identifier($str)
    {
        $start = $this->options['identifier_start'];
        $end   = $this->options['identifier_end'];
        $name  = array();

        foreach (explode('.', $str) as $elem) {
            $elem = str_replace(array($start, $end), '', $elem);
            $name[] = $start . $elem . $end;
        }

        return implode($name, '.');
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
            $add = ' ' . ($interval > 0 ? '+' : '-') . ' INTERVAL ';
            $add .= $interval > 0 ? intval($interval) : intval($interval) * -1;
            $add .= ' SECOND';
        }

        return "now()" . $add;
    }

    /**
     * Return list of elements for use with SQL's IN clause
     *
     * @param array  $arr  Input array
     * @param string $type Type of data (integer, bool, ident)
     *
     * @return string Comma-separated list of quoted values for use in query
     */
    public function array2list($arr, $type = null)
    {
        if (!is_array($arr)) {
            return $this->quote($arr, $type);
        }

        foreach ($arr as $idx => $item) {
            $arr[$idx] = $this->quote($item, $type);
        }

        return implode(',', $arr);
    }

    /**
     * Return SQL statement to convert a field value into a unix timestamp
     *
     * This method is deprecated and should not be used anymore due to limitations
     * of timestamp functions in Mysql (year 2038 problem)
     *
     * @param string $field Field name
     *
     * @return string  SQL statement to use in query
     * @deprecated
     */
    public function unixtimestamp($field)
    {
        return "UNIX_TIMESTAMP($field)";
    }

    /**
     * Return SQL statement to convert from a unix timestamp
     *
     * @param int $timestamp Unix timestamp
     *
     * @return string Date string in db-specific format
     */
    public function fromunixtime($timestamp)
    {
        return date("'Y-m-d H:i:s'", $timestamp);
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
        return $this->quote_identifier($column).' LIKE '.$this->quote($value);
    }

    /**
     * Abstract SQL statement for value concatenation
     *
     * @return string SQL statement to be used in query
     */
    public function concat(/* col1, col2, ... */)
    {
        $args = func_get_args();
        if (is_array($args[0])) {
            $args = $args[0];
        }

        return '(' . join(' || ', $args) . ')';
    }

    /**
     * Encodes non-UTF-8 characters in string/array/object (recursive)
     *
     * @param mixed $input      Data to fix
     * @param bool  $serialized Enable serialization
     *
     * @return mixed Properly UTF-8 encoded data
     */
    public static function encode($input, $serialized = false)
    {
        // use Base64 encoding to workaround issues with invalid
        // or null characters in serialized string (#1489142)
        if ($serialized) {
            return base64_encode(serialize($input));
        }

        if (is_object($input)) {
            foreach (get_object_vars($input) as $idx => $value) {
                $input->$idx = self::encode($value);
            }
            return $input;
        }
        else if (is_array($input)) {
            foreach ($input as $idx => $value) {
                $input[$idx] = self::encode($value);
            }

            return $input;
        }

        return utf8_encode($input);
    }

    /**
     * Decodes encoded UTF-8 string/object/array (recursive)
     *
     * @param mixed $input      Input data
     * @param bool  $serialized Enable serialization
     *
     * @return mixed Decoded data
     */
    public static function decode($input, $serialized = false)
    {
        // use Base64 encoding to workaround issues with invalid
        // or null characters in serialized string (#1489142)
        if ($serialized) {
            // Keep backward compatybility where base64 wasn't used
            if (strpos(substr($input, 0, 16), ':') !== false) {
                return self::decode(@unserialize($input));
            }

            return @unserialize(base64_decode($input));
        }

        if (is_object($input)) {
            foreach (get_object_vars($input) as $idx => $value) {
                $input->$idx = self::decode($value);
            }
            return $input;
        }
        else if (is_array($input)) {
            foreach ($input as $idx => $value) {
                $input[$idx] = self::decode($value);
            }
            return $input;
        }

        return utf8_decode($input);
    }

    /**
     * Return correct name for a specific database table
     *
     * @param string $table  Table name
     * @param bool   $quoted Quote table identifier
     *
     * @return string Translated table name
     */
    public function table_name($table, $quoted = false)
    {
        // let plugins alter the table name (#1489837)
        $plugin = rcube::get_instance()->plugins->exec_hook('db_table_name', array('table' => $table));
        $table = $plugin['table'];

        // add prefix to the table name if configured
        if (($prefix = $this->options['table_prefix']) && strpos($table, $prefix) !== 0) {
            $table = $prefix . $table;
        }

        if ($quoted) {
            $table = $this->quote_identifier($table);
        }

        return $table;
    }

    /**
     * Set class option value
     *
     * @param string $name  Option name
     * @param mixed  $value Option value
     */
    public function set_option($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * Set DSN connection to be used for the given table
     *
     * @param string $table Table name
     * @param string $mode  DSN connection ('r' or 'w') to be used
     */
    public function set_table_dsn($table, $mode)
    {
        $this->options['table_dsn_map'][$this->table_name($table)] = $mode;
    }

    /**
     * MDB2 DSN string parser
     *
     * @param string $sequence Secuence name
     *
     * @return array DSN parameters
     */
    public static function parse_dsn($dsn)
    {
        if (empty($dsn)) {
            return null;
        }

        // Find phptype and dbsyntax
        if (($pos = strpos($dsn, '://')) !== false) {
            $str = substr($dsn, 0, $pos);
            $dsn = substr($dsn, $pos + 3);
        }
        else {
            $str = $dsn;
            $dsn = null;
        }

        // Get phptype and dbsyntax
        // $str => phptype(dbsyntax)
        if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
            $parsed['phptype']  = $arr[1];
            $parsed['dbsyntax'] = !$arr[2] ? $arr[1] : $arr[2];
        }
        else {
            $parsed['phptype']  = $str;
            $parsed['dbsyntax'] = $str;
        }

        if (empty($dsn)) {
            return $parsed;
        }

        // Get (if found): username and password
        // $dsn => username:password@protocol+hostspec/database
        if (($at = strrpos($dsn,'@')) !== false) {
            $str = substr($dsn, 0, $at);
            $dsn = substr($dsn, $at + 1);
            if (($pos = strpos($str, ':')) !== false) {
                $parsed['username'] = rawurldecode(substr($str, 0, $pos));
                $parsed['password'] = rawurldecode(substr($str, $pos + 1));
            }
            else {
                $parsed['username'] = rawurldecode($str);
            }
        }

        // Find protocol and hostspec

        // $dsn => proto(proto_opts)/database
        if (preg_match('|^([^(]+)\((.*?)\)/?(.*?)$|', $dsn, $match)) {
            $proto       = $match[1];
            $proto_opts  = $match[2] ? $match[2] : false;
            $dsn         = $match[3];
        }
        // $dsn => protocol+hostspec/database (old format)
        else {
            if (strpos($dsn, '+') !== false) {
                list($proto, $dsn) = explode('+', $dsn, 2);
            }
            if (   strpos($dsn, '//') === 0
                && strpos($dsn, '/', 2) !== false
                && $parsed['phptype'] == 'oci8'
            ) {
                //oracle's "Easy Connect" syntax:
                //"username/password@[//]host[:port][/service_name]"
                //e.g. "scott/tiger@//mymachine:1521/oracle"
                $proto_opts = $dsn;
                $pos = strrpos($proto_opts, '/');
                $dsn = substr($proto_opts, $pos + 1);
                $proto_opts = substr($proto_opts, 0, $pos);
            }
            else if (strpos($dsn, '/') !== false) {
                list($proto_opts, $dsn) = explode('/', $dsn, 2);
            }
            else {
                $proto_opts = $dsn;
                $dsn = null;
            }
        }

        // process the different protocol options
        $parsed['protocol'] = $proto ?: 'tcp';
        $proto_opts = rawurldecode($proto_opts);
        if (strpos($proto_opts, ':') !== false) {
            list($proto_opts, $parsed['port']) = explode(':', $proto_opts);
        }
        if ($parsed['protocol'] == 'tcp') {
            $parsed['hostspec'] = $proto_opts;
        }
        else if ($parsed['protocol'] == 'unix') {
            $parsed['socket'] = $proto_opts;
        }

        // Get dabase if any
        // $dsn => database
        if ($dsn) {
            // /database
            if (($pos = strpos($dsn, '?')) === false) {
                $parsed['database'] = rawurldecode($dsn);
            // /database?param1=value1&param2=value2
            }
            else {
                $parsed['database'] = rawurldecode(substr($dsn, 0, $pos));
                $dsn = substr($dsn, $pos + 1);
                if (strpos($dsn, '&') !== false) {
                    $opts = explode('&', $dsn);
                }
                else { // database?param1=value1
                    $opts = array($dsn);
                }
                foreach ($opts as $opt) {
                    list($key, $value) = explode('=', $opt);
                    if (!array_key_exists($key, $parsed) || false === $parsed[$key]) {
                        // don't allow params overwrite
                        $parsed[$key] = rawurldecode($value);
                    }
                }
            }
        }

        return $parsed;
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
        $result = $dsn['phptype'] . ':';

        if ($dsn['hostspec']) {
            $params[] = 'host=' . $dsn['hostspec'];
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
     * Returns driver-specific connection options
     *
     * @param array $dsn DSN parameters
     *
     * @return array Connection options
     */
    protected function dsn_options($dsn)
    {
        $result = array();

        if ($this->db_pconn) {
            $result[PDO::ATTR_PERSISTENT] = true;
        }

        if (!empty($dsn['prefetch'])) {
            $result[PDO::ATTR_PREFETCH] = (int) $dsn['prefetch'];
        }

        if (!empty($dsn['timeout'])) {
            $result[PDO::ATTR_TIMEOUT] = (int) $dsn['timeout'];
        }

        return $result;
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

        foreach (explode("\n", $sql) as $line) {
            if (preg_match('/^--/', $line) || trim($line) == '')
                continue;

            $buff .= $line . "\n";
            if (preg_match('/(;|^GO)$/', trim($line))) {
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
     * Parse SQL file and fix table names according to table prefix
     */
    protected function fix_table_names($sql)
    {
        if (!$this->options['table_prefix']) {
            return $sql;
        }

        $sql = preg_replace_callback(
            '/((TABLE|TRUNCATE|(?<!ON )UPDATE|INSERT INTO|FROM'
            . '| ON(?! (DELETE|UPDATE))|REFERENCES|CONSTRAINT|FOREIGN KEY|INDEX)'
            . '\s+(IF (NOT )?EXISTS )?[`"]*)([^`"\( \r\n]+)/',
            array($this, 'fix_table_names_callback'),
            $sql
        );

        return $sql;
    }

    /**
     * Preg_replace callback for fix_table_names()
     */
    protected function fix_table_names_callback($matches)
    {
        return $matches[1] . $this->options['table_prefix'] . $matches[count($matches)-1];
    }
}
