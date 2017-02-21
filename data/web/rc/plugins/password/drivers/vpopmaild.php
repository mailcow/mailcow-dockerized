<?php

/**
 * vpopmail Password Driver
 *
 * Driver to change passwords via vpopmaild
 *
 * @version 2.0
 * @author Johannes Hessellund
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

class rcube_vpopmaild_password
{
    function save($curpass, $passwd)
    {
        $rcmail    = rcmail::get_instance();
        $vpopmaild = new Net_Socket();
        $host      = $rcmail->config->get('password_vpopmaild_host');
        $port      = $rcmail->config->get('password_vpopmaild_port');

        $result = $vpopmaild->connect($host, $port, null);
        if (is_a($result, 'PEAR_Error')) {
            return PASSWORD_CONNECT_ERROR;
        }

        $vpopmaild->setTimeout($rcmail->config->get('password_vpopmaild_timeout'),0);

        $result = $vpopmaild->readLine();
        if(!preg_match('/^\+OK/', $result)) {
            $vpopmaild->disconnect();
            return PASSWORD_CONNECT_ERROR;
        }

        $vpopmaild->writeLine("slogin ". $_SESSION['username'] . " " . $curpass);
        $result = $vpopmaild->readLine();

        if(!preg_match('/^\+OK/', $result) ) {
            $vpopmaild->writeLine("quit");
            $vpopmaild->disconnect();
            return PASSWORD_ERROR;
        }

        $vpopmaild->writeLine("mod_user ". $_SESSION['username']);
        $vpopmaild->writeLine("clear_text_password ". $passwd);
        $vpopmaild->writeLine(".");
        $result = $vpopmaild->readLine();
        $vpopmaild->writeLine("quit");
        $vpopmaild->disconnect();

        if (!preg_match('/^\+OK/', $result)) {
            return PASSWORD_ERROR;
        }

        return PASSWORD_SUCCESS;
    }
}
