<?php

/**
 * SQL Password Driver
 *
 * Driver for passwords stored in SQL database
 *
 * @version 2.0
 * @author Aleksander 'A.L.E.C' Machniak <alec@alec.pl>
 *
 * Copyright (C) 2005-2013, The Roundcube Dev Team
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 */

class rcube_sql_password
{
    function save($curpass, $passwd)
    {
        $rcmail = rcmail::get_instance();

        if (!($sql = $rcmail->config->get('password_query'))) {
            $sql = 'SELECT update_passwd(%c, %u)';
        }

        if ($dsn = $rcmail->config->get('password_db_dsn')) {
            $db = rcube_db::factory($dsn, '', false);
            $db->set_debug((bool)$rcmail->config->get('sql_debug'));
        }
        else {
            $db = $rcmail->get_dbh();
        }

        if ($db->is_error()) {
            return PASSWORD_ERROR;
        }

        // new password - default hash method
        if (strpos($sql, '%P') !== false) {
            $password = password::hash_password($passwd);

            if ($password === false) {
                return PASSWORD_CRYPT_ERROR;
            }

            $sql = str_replace('%P',  $db->quote($password), $sql);
        }

        // old password - default hash method
        if (strpos($sql, '%O') !== false) {
            $password = password::hash_password($curpass);

            if ($password === false) {
                return PASSWORD_CRYPT_ERROR;
            }

            $sql = str_replace('%O',  $db->quote($password), $sql);
        }

        // crypted password (deprecated, use %P)
        if (strpos($sql, '%c') !== false) {
            $password = password::hash_password($passwd, 'crypt', false);

            if ($password === false) {
                return PASSWORD_CRYPT_ERROR;
            }

            $sql = str_replace('%c',  $db->quote($password), $sql);
        }

        // dovecotpw (deprecated, use %P)
        if (strpos($sql, '%D') !== false) {
            $password = password::hash_password($passwd, 'dovecot', false);

            if ($password === false) {
                return PASSWORD_CRYPT_ERROR;
            }

            $sql = str_replace('%D', $db->quote($password), $sql);
        }

        // hashed passwords (deprecated, use %P)
        if (strpos($sql, '%n') !== false) {
            $password = password::hash_password($passwd, 'hash', false);

            if ($password === false) {
                return PASSWORD_CRYPT_ERROR;
            }

            $sql = str_replace('%n', $db->quote($password, 'text'), $sql);
        }

        // hashed passwords (deprecated, use %P)
        if (strpos($sql, '%q') !== false) {
            $password = password::hash_password($curpass, 'hash', false);

            if ($password === false) {
                return PASSWORD_CRYPT_ERROR;
            }

            $sql = str_replace('%q', $db->quote($password, 'text'), $sql);
        }

        // Handle clear text passwords securely (#1487034)
        $sql_vars = array();
        if (preg_match_all('/%[p|o]/', $sql, $m)) {
            foreach ($m[0] as $var) {
                if ($var == '%p') {
                    $sql = preg_replace('/%p/', '?', $sql, 1);
                    $sql_vars[] = (string) $passwd;
                }
                else { // %o
                    $sql = preg_replace('/%o/', '?', $sql, 1);
                    $sql_vars[] = (string) $curpass;
                }
            }
        }

        $local_part  = $rcmail->user->get_username('local');
        $domain_part = $rcmail->user->get_username('domain');
        $username    = $_SESSION['username'];
        $host        = $_SESSION['imap_host'];

        // convert domains to/from punnycode
        if ($rcmail->config->get('password_idn_ascii')) {
            $domain_part = rcube_utils::idn_to_ascii($domain_part);
            $username    = rcube_utils::idn_to_ascii($username);
            $host        = rcube_utils::idn_to_ascii($host);
        }
        else {
            $domain_part = rcube_utils::idn_to_utf8($domain_part);
            $username    = rcube_utils::idn_to_utf8($username);
            $host        = rcube_utils::idn_to_utf8($host);
        }

        // at least we should always have the local part
        $sql = str_replace('%l', $db->quote($local_part, 'text'), $sql);
        $sql = str_replace('%d', $db->quote($domain_part, 'text'), $sql);
        $sql = str_replace('%u', $db->quote($username, 'text'), $sql);
        $sql = str_replace('%h', $db->quote($host, 'text'), $sql);

        $res = $db->query($sql, $sql_vars);

        if (!$db->is_error()) {
            if (strtolower(substr(trim($sql),0,6)) == 'select') {
                if ($db->fetch_array($res)) {
                    return PASSWORD_SUCCESS;
                }
            }
            else {
                // This is the good case: 1 row updated
                if ($db->affected_rows($res) == 1)
                    return PASSWORD_SUCCESS;
                // @TODO: Some queries don't affect any rows
                // Should we assume a success if there was no error?
            }
        }

        return PASSWORD_ERROR;
    }
}
