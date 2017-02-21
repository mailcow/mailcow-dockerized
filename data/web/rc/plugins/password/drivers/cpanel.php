<?php

/**
 * cPanel Password Driver
 *
 * Driver that adds functionality to change the users cPanel password.
 * Originally written by Fulvio Venturelli <fulvio@venturelli.org>
 *
 * Completely rewritten using the cPanel API2 call Email::passwdpop
 * as opposed to the original coding against the UI, which is a fragile method that
 * makes the driver to always return a failure message for any language other than English
 * see https://github.com/roundcube/roundcubemail/issues/3063
 *
 * This driver has been tested with o2switch hosting and seems to work fine.
 *
 * @version 3.1
 * @author Christian Chech <christian@chech.fr>
 *
 * Copyright (C) 2005-2016, The Roundcube Dev Team
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

class rcube_cpanel_password
{
    public function save($curpas, $newpass)
    {
        require_once 'xmlapi.php';

        $rcmail = rcmail::get_instance();

        $this->cuser = $rcmail->config->get('password_cpanel_username');
        $cpanel_host = $rcmail->config->get('password_cpanel_host');
        $cpanel_port = $rcmail->config->get('password_cpanel_port');
        $cpanel_hash = $rcmail->config->get('password_cpanel_hash');
        $cpanel_pass = $rcmail->config->get('password_cpanel_password');

        // Setup the xmlapi connection
        $this->xmlapi = new xmlapi($cpanel_host);
        $this->xmlapi->set_port($cpanel_port);

        // Hash auth
        if (!empty($cpanel_hash)) {
            $this->xmlapi->hash_auth($this->cuser, $cpanel_hash);
        }
        // Pass auth
        else if (!empty($cpanel_pass)) {
            $this->xmlapi->password_auth($this->cuser, $cpanel_pass);
        }
        else {
            return PASSWORD_ERROR;
        }

        $this->xmlapi->set_output('json');
        $this->xmlapi->set_debug(0);

        return $this->setPassword($_SESSION['username'], $newpass);
    }

    /**
     * Change email account password
     *
     * @param string $address  Email address/username
     * @param string $password Email account password
     *
     * @return int|array Operation status
     */
    function setPassword($address, $password)
    {
        if (strpos($address, '@')) {
            list($data['email'], $data['domain']) = explode('@', $address);
        }
        else {
            list($data['email'], $data['domain']) = array($address, '');
        }

        $data['password'] = $password;

        // Get the cPanel user
        $query = $this->xmlapi->listaccts('domain', $data['domain']);
        $query = json_decode($query, true);
        if ( $query['status'] != 1) {
            return false;
        }
        $cpanel_user = $query['acct'][0]['user'];

        $query  = $this->xmlapi->api2_query($cpanel_user, 'Email', 'passwdpop', $data);
        $query  = json_decode($query, true);
        $result = $query['cpanelresult']['data'][0];

        if ($result['result'] == 1) {
            return PASSWORD_SUCCESS;
        }

        if ($result['reason']) {
            return array(
                'code'    => PASSWORD_ERROR,
                'message' => $result['reason'],
            );
        }

        return PASSWORD_ERROR;
    }
}
