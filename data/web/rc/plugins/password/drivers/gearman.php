<?php

/**
 * Gearman Password Driver
 *
 * Payload is json string containing username, oldPassword and newPassword
 * Return value is a json string saying result: true if success.
 *
 * @version 1.0
 * @author Mohammad Anwari <mdamt@mdamt.net>
 *
 * Copyright (C) 2005-2014, The Roundcube Dev Team
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

class rcube_gearman_password
{
    function save($currpass, $newpass)
    {
        if (extension_loaded('gearman')) {
            $rcmail  = rcmail::get_instance();
            $user    = $_SESSION['username'];
            $payload = array(
                'username'    => $user,
                'oldPassword' => $currpass,
                'newPassword' => $newpass,
            );

            $gmc = new GearmanClient();
            $gmc->addServer($rcmail->config->get('password_gearman_host'));

            $result  = $gmc->doNormal('setPassword', json_encode($payload));
            $success = json_decode($result);

            if ($success && $success->result == 1) {
                return PASSWORD_SUCCESS;
            }
            else {
                rcube::raise_error(array(
                    'code' => 600,
                    'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Password plugin: Gearman authentication failed for user $user: $error"
                ), true, false);
            }
        }
        else {
            rcube::raise_error(array(
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Password plugin: PECL Gearman module not loaded"
            ), true, false);
        }

        return PASSWORD_ERROR;
    }
}
