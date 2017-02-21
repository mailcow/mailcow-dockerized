<?php

/**
 * PAM Password Driver
 *
 * @version 2.0
 * @author Aleksander Machniak
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

class rcube_pam_password
{
    function save($currpass, $newpass)
    {
        $user  = $_SESSION['username'];
        $error = '';

        if (extension_loaded('pam') || extension_loaded('pam_auth')) {
            if (pam_auth($user, $currpass, $error, false)) {
                if (pam_chpass($user, $currpass, $newpass)) {
                    return PASSWORD_SUCCESS;
                }
            }
            else {
                rcube::raise_error(array(
                    'code' => 600,
                    'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Password plugin: PAM authentication failed for user $user: $error"
                    ), true, false);
            }
        }
        else {
            rcube::raise_error(array(
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Password plugin: PECL-PAM module not loaded"
                ), true, false);
        }

        return PASSWORD_ERROR;
    }
}
