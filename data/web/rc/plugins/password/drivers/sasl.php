<?php

/**
 * SASL Password Driver
 *
 * Driver that adds functionality to change the users Cyrus/SASL password.
 * The code is derrived from the Squirrelmail "Change SASL Password" Plugin
 * by Galen Johnson.
 *
 * It only works with saslpasswd2 on the same host where Roundcube runs
 * and requires shell access and gcc in order to compile the binary.
 *
 * For installation instructions please read the README file.
 *
 * @version 2.0
 * @author Thomas Bruederli
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

class rcube_sasl_password
{
    function save($currpass, $newpass)
    {
        $curdir   = RCUBE_PLUGINS_DIR . 'password/helpers';
        $username = escapeshellcmd($_SESSION['username']);
        $args     = rcmail::get_instance()->config->get('password_saslpasswd_args', '');

        if ($fh = popen("$curdir/chgsaslpasswd -p $args $username", 'w')) {
            fwrite($fh, $newpass."\n");
            $code = pclose($fh);

            if ($code == 0)
                return PASSWORD_SUCCESS;
        }
        else {
            rcube::raise_error(array(
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Password plugin: Unable to execute $curdir/chgsaslpasswd"
                ), true, false);
        }

        return PASSWORD_ERROR;
    }
}
