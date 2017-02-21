<?php

/**
 * hMailserver password driver
 *
 * @version 2.0
 * @author Roland 'rosali' Liebl <myroundcube@mail4us.net>
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

class rcube_hmail_password
{
    public function save($curpass, $passwd)
    {
        $rcmail = rcmail::get_instance();

        if ($curpass == '' || $passwd == '') {
            return PASSWORD_ERROR;
        }

        try {
            $remote = $rcmail->config->get('hmailserver_remote_dcom', false);
            if ($remote)
                $obApp = new COM("hMailServer.Application", $rcmail->config->get('hmailserver_server'));
            else
                $obApp = new COM("hMailServer.Application");
        }
        catch (Exception $e) {
            rcube::write_log('errors', "Plugin password (hmail driver): " . trim(strip_tags($e->getMessage())));
            rcube::write_log('errors', "Plugin password (hmail driver): This problem is often caused by DCOM permissions not being set.");
            return PASSWORD_ERROR;
        }

        $username = $rcmail->user->data['username'];
        if (strstr($username,'@')){
            $temparr = explode('@', $username);
            $domain = $temparr[1];
        }
        else {
            $domain = $rcmail->config->get('username_domain',false);
            if (!$domain) {
                rcube::write_log('errors','Plugin password (hmail driver): $config[\'username_domain\'] is not defined.');
                return PASSWORD_ERROR;
            }
            $username = $username . "@" . $domain;
        }

        $obApp->Authenticate($username, $curpass);
        try {
            $obDomain = $obApp->Domains->ItemByName($domain);
            $obAccount = $obDomain->Accounts->ItemByAddress($username);
            $obAccount->Password = $passwd;
            $obAccount->Save();
            return PASSWORD_SUCCESS;
        }
        catch (Exception $e) {
            rcube::write_log('errors', "Plugin password (hmail driver): " . trim(strip_tags($e->getMessage())));
            rcube::write_log('errors', "Plugin password (hmail driver): This problem is often caused by DCOM permissions not being set.");
            return PASSWORD_ERROR;
        }
    }
}
