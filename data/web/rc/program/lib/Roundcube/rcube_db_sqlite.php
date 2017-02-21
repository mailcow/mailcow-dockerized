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
 |   for SQLite database                                                 |
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
class rcube_db_sqlite extends rcube_db
{
    public $db_provider = 'sqlite';

    /**
     * Prepare connection
     */
    protected function conn_prepare($dsn)
    {
        // Create database file, required by PDO to exist on connection
        if (!empty($dsn['database']) && !file_exists($dsn['database'])) {
            $created = touch($dsn['database']);

            // File mode setting, for compat. with MDB2
            if (!empty($dsn['mode']) && $created) {
                chmod($dsn['database'], octdec($dsn['mode']));
            }
        }
    }

    /**
     * Configure connection, create database if not exists
     */
    protected function conn_configure($dsn, $dbh)
    {
        // Initialize database structure in file is empty
        if (!empty($dsn['database']) && !filesize($dsn['database'])) {
            $data = file_get_contents(RCUBE_INSTALL_PATH . 'SQL/sqlite.initial.sql');

            if (strlen($data)) {
                $this->debug('INITIALIZE DATABASE');

                $q = $dbh->exec($data);

                if ($q === false) {
                    $error = $dbh->errorInfo();
                    $this->db_error = true;
                    $this->db_error_msg = sprintf('[%s] %s', $error[1], $error[2]);

                    rcube::raise_error(array('code' => 500, 'type' => 'db',
                        'line' => __LINE__, 'file' => __FILE__,
                        'message' => $this->db_error_msg), true, false);
                }
            }
        }
    }

    /**
     * Return SQL statement to convert a field value into a unix timestamp
     *
     * @param string $field Field name
     *
     * @return string  SQL statement to use in query
     * @deprecated
     */
    public function unixtimestamp($field)
    {
        return "strftime('%s', $field)";
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
            $add = ($interval > 0 ? '+' : '') . intval($interval) . ' seconds';
        }

        return "datetime('now'" . ($add ? ",'$add'" : "") . ")";
    }

    /**
     * Returns list of tables in database
     *
     * @return array List of all tables of the current database
     */
    public function list_tables()
    {
        if ($this->tables === null) {
            $q = $this->query('SELECT name FROM sqlite_master'
                .' WHERE type = \'table\' ORDER BY name');

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
        $q = $this->query('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?',
            array('table', $table));

        $columns = array();

        if ($sql = $this->fetch_array($q)) {
            $sql       = $sql[0];
            $start_pos = strpos($sql, '(');
            $end_pos   = strrpos($sql, ')');
            $sql       = substr($sql, $start_pos+1, $end_pos-$start_pos-1);
            $lines     = explode(',', $sql);

            foreach ($lines as $line) {
                $line = explode(' ', trim($line));

                if ($line[0] && strpos($line[0], '--') !== 0) {
                    $column = $line[0];
                    $columns[] = trim($column, '"');
                }
            }
        }

        return $columns;
    }

    /**
     * Build DSN string for PDO constructor
     */
    protected function dsn_string($dsn)
    {
        return $dsn['phptype'] . ':' . $dsn['database'];
    }
}
