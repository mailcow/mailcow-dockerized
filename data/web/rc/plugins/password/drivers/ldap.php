<?php

/**
 * LDAP Password Driver
 *
 * Driver for passwords stored in LDAP
 * This driver use the PEAR Net_LDAP2 class (http://pear.php.net/package/Net_LDAP2).
 *
 * @version 2.0
 * @author Edouard MOREAU <edouard.moreau@ensma.fr>
 *
 * method hashPassword based on code from the phpLDAPadmin development team (http://phpldapadmin.sourceforge.net/).
 * method randomSalt based on code from the phpLDAPadmin development team (http://phpldapadmin.sourceforge.net/).
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

class rcube_ldap_password
{
    public function save($curpass, $passwd)
    {
        $rcmail = rcmail::get_instance();
        require_once 'Net/LDAP2.php';

        // Building user DN
        if ($userDN = $rcmail->config->get('password_ldap_userDN_mask')) {
            $userDN = self::substitute_vars($userDN);
        }
        else {
            $userDN = $this->search_userdn($rcmail);
        }

        if (empty($userDN)) {
            return PASSWORD_CONNECT_ERROR;
        }

        // Connection Method
        switch($rcmail->config->get('password_ldap_method')) {
            case 'admin':
                $binddn = $rcmail->config->get('password_ldap_adminDN');
                $bindpw = $rcmail->config->get('password_ldap_adminPW');
                break;
            case 'user':
            default:
                $binddn = $userDN;
                $bindpw = $curpass;
                break;
        }

        // Configuration array
        $ldapConfig = array (
            'binddn'    => $binddn,
            'bindpw'    => $bindpw,
            'basedn'    => $rcmail->config->get('password_ldap_basedn'),
            'host'      => $rcmail->config->get('password_ldap_host'),
            'port'      => $rcmail->config->get('password_ldap_port'),
            'starttls'  => $rcmail->config->get('password_ldap_starttls'),
            'version'   => $rcmail->config->get('password_ldap_version'),
        );

        // Connecting using the configuration array
        $ldap = Net_LDAP2::connect($ldapConfig);

        // Checking for connection error
        if (is_a($ldap, 'PEAR_Error')) {
            return PASSWORD_CONNECT_ERROR;
        }

        $force        = $rcmail->config->get('password_ldap_force_replace');
        $pwattr       = $rcmail->config->get('password_ldap_pwattr');
        $lchattr      = $rcmail->config->get('password_ldap_lchattr');
        $smbpwattr    = $rcmail->config->get('password_ldap_samba_pwattr');
        $smblchattr   = $rcmail->config->get('password_ldap_samba_lchattr');
        $samba        = $rcmail->config->get('password_ldap_samba');
        $encodage     = $rcmail->config->get('password_ldap_encodage');

        // Support multiple userPassword values where desired.
        // multiple encodings can be specified separated by '+' (e.g. "cram-md5+ssha")
        $encodages    = explode('+', $encodage);
        $crypted_pass = array();

        foreach ($encodages as $enc) {
            if ($cpw = password::hash_password($passwd, $enc)) {
                $crypted_pass[] = $cpw;
            }
        }

        // Support password_ldap_samba option for backward compat.
        if ($samba && !$smbpwattr) {
            $smbpwattr  = 'sambaNTPassword';
            $smblchattr = 'sambaPwdLastSet';
        }

        // Crypt new password
        if (empty($crypted_pass)) {
            return PASSWORD_CRYPT_ERROR;
        }

        // Crypt new samba password
        if ($smbpwattr && !($samba_pass = password::hash_password($passwd, 'samba'))) {
            return PASSWORD_CRYPT_ERROR;
        }

        // Writing new crypted password to LDAP
        $userEntry = $ldap->getEntry($userDN);
        if (Net_LDAP2::isError($userEntry)) {
            return PASSWORD_CONNECT_ERROR;
        }

        if (!$userEntry->replace(array($pwattr => $crypted_pass), $force)) {
            return PASSWORD_CONNECT_ERROR;
        }

        // Updating PasswordLastChange Attribute if desired
        if ($lchattr) {
            $current_day = (int)(time() / 86400);
            if (!$userEntry->replace(array($lchattr => $current_day), $force)) {
                return PASSWORD_CONNECT_ERROR;
            }
        }

        // Update Samba password and last change fields
        if ($smbpwattr) {
            $userEntry->replace(array($smbpwattr => $samba_pass), $force);
        }
        // Update Samba password last change field
        if ($smblchattr) {
            $userEntry->replace(array($smblchattr => time()), $force);
        }

        if (Net_LDAP2::isError($userEntry->update())) {
            return PASSWORD_CONNECT_ERROR;
        }

        // All done, no error
        return PASSWORD_SUCCESS;
    }

    /**
     * Bind with searchDN and searchPW and search for the user's DN.
     * Use search_base and search_filter defined in config file.
     * Return the found DN.
     */
    function search_userdn($rcmail)
    {
        $binddn = $rcmail->config->get('password_ldap_searchDN');
        $bindpw = $rcmail->config->get('password_ldap_searchPW');

        $ldapConfig = array (
            'basedn'    => $rcmail->config->get('password_ldap_basedn'),
            'host'      => $rcmail->config->get('password_ldap_host'),
            'port'      => $rcmail->config->get('password_ldap_port'),
            'starttls'  => $rcmail->config->get('password_ldap_starttls'),
            'version'   => $rcmail->config->get('password_ldap_version'),
        );

        // allow anonymous searches
        if (!empty($binddn)) {
            $ldapConfig['binddn'] = $binddn;
            $ldapConfig['bindpw'] = $bindpw;
        }

        $ldap = Net_LDAP2::connect($ldapConfig);

        if (is_a($ldap, 'PEAR_Error')) {
            return '';
        }

        $base   = self::substitute_vars($rcmail->config->get('password_ldap_search_base'));
        $filter = self::substitute_vars($rcmail->config->get('password_ldap_search_filter'));
        $options = array (
            'scope' => 'sub',
            'attributes' => array(),
        );

        $result = $ldap->search($base, $filter, $options);
        if (is_a($result, 'PEAR_Error') || ($result->count() != 1)) {
            $ldap->done();
            return '';
        }
        $userDN = $result->current()->dn();
        $ldap->done();

        return $userDN;
    }

    /**
     * Substitute %login, %name, %domain, %dc in $str
     * See plugin config for details
     */
    static function substitute_vars($str)
    {
        $str = str_replace('%login', $_SESSION['username'], $str);
        $str = str_replace('%l', $_SESSION['username'], $str);

        $parts = explode('@', $_SESSION['username']);

        if (count($parts) == 2) {
            $dc = 'dc='.strtr($parts[1], array('.' => ',dc=')); // hierarchal domain string

            $str = str_replace('%name', $parts[0], $str);
            $str = str_replace('%n', $parts[0], $str);
            $str = str_replace('%dc', $dc, $str);
            $str = str_replace('%domain', $parts[1], $str);
            $str = str_replace('%d', $parts[1], $str);
        }

        return $str;
    }
}
