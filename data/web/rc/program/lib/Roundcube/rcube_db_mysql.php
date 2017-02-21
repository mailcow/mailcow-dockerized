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
 |   for MySQL database                                                  |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Database independent query interface
 *
 * This is a wrapper for the PHP PDO
 *
 * @package    Framework
 * @subpackage Database
 */
class rcube_db_mysql extends rcube_db
{
    public $db_provider = 'mysql';

    /**
     * Object constructor
     *
     * @param string $db_dsnw DSN for read/write operations
     * @param string $db_dsnr Optional DSN for read only operations
     * @param bool   $pconn   Enables persistent connections
     */
    public function __construct($db_dsnw, $db_dsnr = '', $pconn = false)
    {
        parent::__construct($db_dsnw, $db_dsnr, $pconn);

        // SQL identifiers quoting
        $this->options['identifier_start'] = '`';
        $this->options['identifier_end'] = '`';
    }

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

        return 'CONCAT(' . join(', ', $args) . ')';
    }

    /**
     * Returns PDO DSN string from DSN array
     *
     * @param array $dsn DSN parameters
     *
     * @return string Connection string
     */
    protected function dsn_string($dsn)
    {
        $params = array();
        $result = 'mysql:';

        if ($dsn['database']) {
            $params[] = 'dbname=' . $dsn['database'];
        }

        if ($dsn['hostspec']) {
            $params[] = 'host=' . $dsn['hostspec'];
        }

        if ($dsn['port']) {
            $params[] = 'port=' . $dsn['port'];
        }

        if ($dsn['socket']) {
            $params[] = 'unix_socket=' . $dsn['socket'];
        }

        $params[] = 'charset=utf8';

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
        $result = parent::dsn_options($dsn);

        if (!empty($dsn['key'])) {
            $result[PDO::MYSQL_ATTR_SSL_KEY] = $dsn['key'];
        }

        if (!empty($dsn['cipher'])) {
            $result[PDO::MYSQL_ATTR_SSL_CIPHER] = $dsn['cipher'];
        }

        if (!empty($dsn['cert'])) {
            $result[PDO::MYSQL_ATTR_SSL_CERT] = $dsn['cert'];
        }

        if (!empty($dsn['capath'])) {
            $result[PDO::MYSQL_ATTR_SSL_CAPATH] = $dsn['capath'];
        }

        if (!empty($dsn['ca'])) {
            $result[PDO::MYSQL_ATTR_SSL_CA] = $dsn['ca'];
        }

        // Always return matching (not affected only) rows count
        $result[PDO::MYSQL_ATTR_FOUND_ROWS] = true;

        // Enable AUTOCOMMIT mode (#1488902)
        $result[PDO::ATTR_AUTOCOMMIT] = true;

        return $result;
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
            // first fetch current database name
            $d = $this->query("SELECT database()");
            $d = $this->fetch_array($d);

            // get list of tables in current database
            $q = $this->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES"
                . " WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
                . " ORDER BY TABLE_NAME", $d ? $d[0] : '');

            $this->tables = $q ? $q->fetchAll(PDO::FETCH_COLUMN, 0) : array();
        }

        return $this->tables;
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
        if (!isset($this->variables)) {
            $this->variables = array();
        }

        if (array_key_exists($varname, $this->variables)) {
            return $this->variables[$varname];
        }

        // configured value has higher prio
        $conf_value = rcube::get_instance()->config->get('db_' . $varname);
        if ($conf_value !== null) {
            return $this->variables[$varname] = $conf_value;
        }

        $result = $this->query('SHOW VARIABLES LIKE ?', $varname);

        while ($row = $this->fetch_array($result)) {
            $this->variables[$row[0]] = $row[1];
        }

        // not found, use default
        if (!isset($this->variables[$varname])) {
            $this->variables[$varname] = $default;
        }

        return $this->variables[$varname];
    }

    /**
     * Handle DB errors, re-issue the query on deadlock errors from InnoDB row-level locking
     *
     * @param string Query that triggered the error
     * @return mixed Result to be stored and returned
     */
    protected function handle_error($query)
    {
        $error = $this->dbh->errorInfo();

        // retry after "Deadlock found when trying to get lock" errors
        $retries = 2;
        while ($error[1] == 1213 && $retries >= 0) {
            usleep(50000);  // wait 50 ms
            $result = $this->dbh->query($query);
            if ($result !== false) {
                return $result;
            }
            $error = $this->dbh->errorInfo();
            $retries--;
        }

        return parent::handle_error($query);
    }

}
