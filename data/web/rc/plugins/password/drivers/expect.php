<?php

/**
 * expect driver
 *
 * Driver that adds functionality to change the systems user password via
 * the 'expect' command.
 *
 * For installation instructions please read the README file.
 *
 * @version 2.0
 * @author Andy Theuninck <gohanman@gmail.com)
 *
 * Based on chpasswd roundcubemail password driver by
 * @author Alex Cartwright <acartwright@mutinydesign.co.uk)
 * and expect horde passwd driver by
 * @author  Gaudenz Steinlin <gaudenz@soziologie.ch>
 *
 * Configuration settings:
 * password_expect_bin => location of expect (e.g. /usr/bin/expect)
 * password_expect_script => path to "password-expect" file
 * password_expect_params => arguments for the expect script
 *   see the password-expect file for details. This is probably
 *   a good starting default:
 *   -telent -host localhost -output /tmp/passwd.log -log /tmp/passwd.log
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

class rcube_expect_password
{
    public function save($currpass, $newpass)
    {
        $rcmail   = rcmail::get_instance();
        $bin      = $rcmail->config->get('password_expect_bin');
        $script   = $rcmail->config->get('password_expect_script');
        $params   = $rcmail->config->get('password_expect_params');
        $username = $_SESSION['username'];

        $cmd = $bin . ' -f ' . $script . ' -- ' . $params;
        $handle = popen($cmd, "w");
        fwrite($handle, "$username\n");
        fwrite($handle, "$currpass\n");
        fwrite($handle, "$newpass\n");

        if (pclose($handle) == 0) {
            return PASSWORD_SUCCESS;
        }
        else {
            rcube::raise_error(array(
                'code' => 600,
                'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Password plugin: Unable to execute $cmd"
                ), true, false);
        }

        return PASSWORD_ERROR;
    }
}
