<?php

/**
 * cPanel Webmail Password Driver
 *
 * It uses Cpanel's Webmail UAPI to change the users password.
 *
 * This driver has been tested successfully with Digital Pacific hosting.
 *
 * @author Maikel Linke <maikel@email.org.au>
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

class rcube_cpanel_webmail_password
{
    /**
     * Changes the user's password. It is called by password.php.
     * See "Driver API" README and password.php for the interface details.
     *
     * @param string $curpas  current (old) password
     * @param string $newpass new requested password
     *
     * @return mixed int code or assoc array with 'code' and 'message', see
     *                   "Driver API" README and password.php
     */
    public function save($curpas, $newpass)
    {
        $user    = $_SESSION['username'];
        $userpwd = "$user:$curpas";
        list($login) = split('@', $user);

        $data = array(
            'email'    => $login,
            'password' => $newpass
        );

        $url      = self::url();
        $response = $this->curl_auth_post($userpwd, $url, $data);

        return self::decode_response($response);
    }

    /**
     * Provides the UAPI URL of the Email::passwd_pop function.
     *
     * @return string HTTPS URL
     */
    public static function url()
    {
        $config       = rcmail::get_instance()->config;
        $storage_host = $_SESSION['storage_host'];

        $host = $config->get('password_cpanel_webmail_host', $storage_host);
        $port = $config->get('password_cpanel_webmail_port', 2096);

        return "https://$host:$port/execute/Email/passwd_pop";
    }

    /**
     * Converts a UAPI response to a password driver response.
     *
     * @param string $response JSON response by the Cpanel UAPI
     *
     * @return mixed response code or array, see <code>save</code>
     */
    public static function decode_response($response)
    {
        if (!$response) {
            return PASSWORD_CONNECT_ERROR;
        }

        // $result should be `null` or `stdClass` object
        $result = json_decode($response);

        // The UAPI may return HTML instead of JSON on missing authentication
        if ($result && $result->status === 1) {
            return PASSWORD_SUCCESS;
        }

        if ($result && is_array($result->errors) && count($result->errors) > 0) {
            return array(
                'code'    => PASSWORD_ERROR,
                'message' => $result->errors[0],
            );
        }

        return PASSWORD_ERROR;
    }

    /**
     * Post data to the given URL using basic authentication.
     *
     * Example:
     *
     * <code>
     * curl_auth_post('john:Secr3t', 'https://example.org', array(
     *     'param' => 'value',
     *     'param' => 'value'
     * ));
     * </code>
     *
     * @param string $userpwd  user name and password separated by a colon
     *                         <code>:</code>
     * @param string $url      the URL to post data to
     * @param array  $postdata the data to post
     *
     * @return string|false The body of the reply, False on error
     */
    private function curl_auth_post($userpwd, $url, $postdata)
    {
        $ch = curl_init();
        $postfields = http_build_query($postdata, '', '&');

        // see http://php.net/manual/en/function.curl-setopt.php
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 131072);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_USERPWD, $userpwd);

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            rcube::raise_error("curl error: $error", true, false);
        }

        return $result;
    }
}
