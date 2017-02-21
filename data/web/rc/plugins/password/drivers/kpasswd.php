<?php

/**
 * kpasswd Driver
 *
 * Driver that adds functionality to change the systems user password via
 * the 'kpasswd' command.
 *
 * For installation instructions please read the README file.
 *
 * @version 1.0
 * @author Peter Allgeyer <peter.allgeyer@salzburgresearch.at>
 *
 * Based on chpasswd roundcubemail password driver by
 * @author Alex Cartwright <acartwright@mutinydesign.co.uk>
 */

class rcube_kpasswd_password
{
    public function save($currpass, $newpass)
    {
        $bin      = rcmail::get_instance()->config->get('password_kpasswd_cmd', '/usr/bin/kpasswd');
        $username = $_SESSION['username'];
        $cmd      = $bin . ' "' . $username . '" 2>&1';

        $handle = popen($cmd, "w");
        fwrite($handle, $currpass."\n");
        fwrite($handle, $newpass."\n");
        fwrite($handle, $newpass."\n");

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
