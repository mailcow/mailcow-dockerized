<?php

/**
 * Virtualmin Password Driver
 *
 * Driver that adds functionality to change the users Virtualmin password.
 * The code is derrived from the Squirrelmail "Change Cyrus/SASL Password" Plugin
 * by Thomas Bruederli.
 *
 * It only works with virtualmin on the same host where Roundcube runs
 * and requires shell access and gcc in order to compile the binary.
 *
 * @version 3.0
 * @author Martijn de Munnik
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

class rcube_virtualmin_password
{
    function save($currpass, $newpass)
    {
        $rcmail   = rcmail::get_instance();
        $format   = $rcmail->config->get('password_virtualmin_format', 0);
        $username = $_SESSION['username'];

        switch ($format) {
        case 1: // username%domain
            $domain = substr(strrchr($username, "%"), 1);
            break;
        case 2: // username.domain (could be bogus)
            $pieces = explode(".", $username);
            $domain = $pieces[count($pieces)-2]. "." . end($pieces);
            break;
        case 3: // domain.username (could be bogus)
            $pieces = explode(".", $username);
            $domain = $pieces[0]. "." . $pieces[1];
            break;
        case 4: // username-domain
            $domain = substr(strrchr($username, "-"), 1);
            break;
        case 5: // domain-username
            $domain = str_replace(strrchr($username, "-"), "", $username);
            break;
        case 6: // username_domain
            $domain = substr(strrchr($username, "_"), 1);
            break;
        case 7: // domain_username
            $pieces = explode("_", $username);
            $domain = $pieces[0];
            break;
        default: // username@domain
            $domain = substr(strrchr($username, "@"), 1);
        }

        if (!$domain) {
            $domain = $rcmail->user->get_username('domain');
        }

        $username = escapeshellcmd($username);
        $domain   = escapeshellcmd($domain);
        $newpass  = escapeshellcmd($newpass);
        $curdir   = RCUBE_PLUGINS_DIR . 'password/helpers';

        exec("$curdir/chgvirtualminpasswd modify-user --domain $domain --user $username --pass $newpass", $output, $returnvalue);

        if ($returnvalue == 0) {
            return PASSWORD_SUCCESS;
        }
        else {
            rcube::raise_error(array(
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Password plugin: Unable to execute $curdir/chgvirtualminpasswd"
                ), true, false);
        }

        return PASSWORD_ERROR;
    }
}
