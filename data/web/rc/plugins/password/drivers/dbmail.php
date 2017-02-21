<?php

/**
 * DBMail Password Driver
 *
 * Driver that adds functionality to change the users DBMail password.
 * The code is derrived from the Squirrelmail "Change SASL Password" Plugin
 * by Galen Johnson.
 *
 * It only works with dbmail-users on the same host where Roundcube runs
 * and requires shell access and gcc in order to compile the binary.
 *
 * For installation instructions please read the README file.
 *
 * @version 1.0
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

class rcube_dbmail_password
{
    function save($currpass, $newpass)
    {
        $curdir   = RCUBE_PLUGINS_DIR . 'password/helpers';
        $username = escapeshellarg($_SESSION['username']);
        $password = escapeshellarg($newpass);
        $args     = rcmail::get_instance()->config->get('password_dbmail_args', '');
        $command  = "$curdir/chgdbmailusers -c $username -w $password $args";

        exec($command, $output, $return_value);

        if ($return_value == 0) {
            return PASSWORD_SUCCESS;
        }
        else {
            rcube::raise_error(array(
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Password plugin: Unable to execute $curdir/chgdbmailusers"
                ), true, false);
        }

        return PASSWORD_ERROR;
    }
}
