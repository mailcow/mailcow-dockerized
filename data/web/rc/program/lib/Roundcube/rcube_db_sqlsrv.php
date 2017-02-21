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
 |   for MS SQL Server database                                          |
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
class rcube_db_sqlsrv extends rcube_db_mssql
{
    /**
     * Returns PDO DSN string from DSN array
     */
    protected function dsn_string($dsn)
    {
        $params = array();
        $result = 'sqlsrv:';

        if ($dsn['hostspec']) {
            $host = $dsn['hostspec'];

            if ($dsn['port']) {
                $host .= ',' . $dsn['port'];
            }

            $params[] = 'Server=' . $host;
        }

        if ($dsn['database']) {
            $params[] = 'Database=' . $dsn['database'];
        }

        if (!empty($params)) {
            $result .= implode(';', $params);
        }

        return $result;
    }
}
