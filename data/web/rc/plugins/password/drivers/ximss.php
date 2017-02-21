<?php
/**
 * Communigate driver for the Password Plugin for Roundcube 
 *
 * Tested with Communigate Pro 5.1.2
 *
 * Configuration options:
 *   password_ximss_host - Host name of Communigate server
 *   password_ximss_port - XIMSS port on Communigate server
 *
 * References:
 *   http://www.communigate.com/WebGuide/XMLAPI.html
 *
 * @version 2.0
 * @author Erik Meitner <erik wanderings.us>
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

class rcube_ximss_password
{
    function save($pass, $newpass)
    {
        $rcmail = rcmail::get_instance();

        $host = $rcmail->config->get('password_ximss_host');
        $port = $rcmail->config->get('password_ximss_port');
        $sock = stream_socket_client("tcp://$host:$port", $errno, $errstr, 30);

        if ($sock === FALSE) {
            return PASSWORD_CONNECT_ERROR;
        }

        // send all requests at once(pipelined)
        fwrite( $sock, '<login id="A001" authData="'.$_SESSION['username'].'" password="'.$pass.'" />'."\0");
        fwrite( $sock, '<passwordModify id="A002" oldPassword="'.$pass.'" newPassword="'.$newpass.'"  />'."\0");
        fwrite( $sock, '<bye id="A003" />'."\0");

  //example responses
  //  <session id="A001" urlID="4815-vN2Txjkggy7gjHRD10jw" userName="user@example.com"/>\0
  //  <response id="A001"/>\0
  //  <response id="A002"/>\0
  //  <response id="A003"/>\0
  // or an error:
  //  <response id="A001" errorText="incorrect password or account name" errorNum="515"/>\0

        $responseblob = '';
        while (!feof($sock)) {
            $responseblob .= fgets($sock, 1024);
        }

        fclose($sock);

        foreach( explode( "\0",$responseblob) as $response ) {
            $resp = simplexml_load_string("<xml>".$response."</xml>");

            if( $resp->response[0]['id'] == 'A001' ) {
                if( isset( $resp->response[0]['errorNum'] ) ) {
                    return PASSWORD_CONNECT_ERROR;
                }
            }
            else if( $resp->response[0]['id'] == 'A002' ) {
                if( isset( $resp->response[0]['errorNum'] )) {
                    return PASSWORD_ERROR;
                }
            }
            else if( $resp->response[0]['id'] == 'A003' ) {
                if( isset($resp->response[0]['errorNum'] )) {
                    //There was a problem during logout(This is probably harmless)
                }
            }
        } //foreach

        return PASSWORD_SUCCESS;
    }
}
