<?php

/**
 * DirectAdmin Password Driver
 *
 * Driver to change passwords via DirectAdmin Control Panel
 *
 * @version 2.1
 * @author Victor Benincasa <vbenincasa@gmail.com>
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

class rcube_directadmin_password
{
    public function save($curpass, $passwd)
    {
        $rcmail = rcmail::get_instance();
        $Socket = new HTTPSocket;

        $da_user    = $_SESSION['username'];
        $da_curpass = $curpass;
        $da_newpass = $passwd;
        $da_host    = $rcmail->config->get('password_directadmin_host');
        $da_port    = $rcmail->config->get('password_directadmin_port');

        if (strpos($da_user, '@') === false) {
            return array('code' => PASSWORD_ERROR, 'message' => 'Change the SYSTEM user password through control panel!');
        }

        $da_host = str_replace('%h', $_SESSION['imap_host'], $da_host);
        $da_host = str_replace('%d', $rcmail->user->get_username('domain'), $da_host);

        $Socket->connect($da_host,$da_port); 
        $Socket->set_method('POST');
        $Socket->query('/CMD_CHANGE_EMAIL_PASSWORD',
            array(
                'email'         => $da_user,
                'oldpassword'   => $da_curpass,
                'password1'     => $da_newpass,
                'password2'     => $da_newpass,
                'api'           => '1'
            ));
        $response = $Socket->fetch_parsed_body();

        //DEBUG
        //rcube::console("Password Plugin: [USER: $da_user] [HOST: $da_host] - Response: [SOCKET: ".$Socket->result_status_code."] [DA ERROR: ".strip_tags($response['error'])."] [TEXT: ".$response[text]."]");

        if($Socket->result_status_code != 200)
            return array('code' => PASSWORD_CONNECT_ERROR, 'message' => $Socket->error[0]);
        elseif($response['error'] == 1)
            return array('code' => PASSWORD_ERROR, 'message' => strip_tags($response['text']));
        else
            return PASSWORD_SUCCESS;
    }
}


/**
 * Socket communication class.
 *
 * Originally designed for use with DirectAdmin's API, this class will fill any HTTP socket need.
 *
 * Very, very basic usage:
 *   $Socket = new HTTPSocket;
 *   echo $Socket->get('http://user:pass@somehost.com:2222/CMD_API_SOMEAPI?query=string&this=that');
 *
 * @author Phi1 'l0rdphi1' Stier <l0rdphi1@liquenox.net>
 * @updates 2.7 and 2.8 by Victor Benincasa <vbenincasa @ gmail.com>
 * @package HTTPSocket
 * @version 2.8
 */
class HTTPSocket {

    var $version = '2.8';

    /* all vars are private except $error, $query_cache, and $doFollowLocationHeader */

    var $method = 'GET';

    var $remote_host;
    var $remote_port;
    var $remote_uname;
    var $remote_passwd;

    var $result;
    var $result_header;
    var $result_body;
    var $result_status_code;

    var $lastTransferSpeed;

    var $bind_host;

    var $error = array();
    var $warn = array();
    var $query_cache = array();

    var $doFollowLocationHeader = TRUE;
    var $redirectURL;

    var $extra_headers = array();

    /**
     * Create server "connection".
     *
     */
    function connect($host, $port = '' )
    {
        if (!is_numeric($port))
        {
            $port = 2222;
        }

        $this->remote_host = $host;
        $this->remote_port = $port;
    }

    function bind( $ip = '' )
    {
        if ( $ip == '' )
        {
            $ip = $_SERVER['SERVER_ADDR'];
        }

        $this->bind_host = $ip;
    }

    /**
     * Change the method being used to communicate.
     *
     * @param string|null request method. supports GET, POST, and HEAD. default is GET
     */
    function set_method( $method = 'GET' )
    {
        $this->method = strtoupper($method);
    }

    /**
     * Specify a username and password.
     *
     * @param string|null username. defualt is null
     * @param string|null password. defualt is null
     */
    function set_login( $uname = '', $passwd = '' )
    {
        if ( strlen($uname) > 0 )
        {
            $this->remote_uname = $uname;
        }

        if ( strlen($passwd) > 0 )
        {
            $this->remote_passwd = $passwd;
        }

    }

    /**
     * Query the server
     *
     * @param string containing properly formatted server API. See DA API docs and examples. Http:// URLs O.K. too.
     * @param string|array query to pass to url
     * @param int if connection KB/s drops below value here, will drop connection
     */
    function query( $request, $content = '', $doSpeedCheck = 0 )
    {
        $this->error = $this->warn = array();
        $this->result_status_code = NULL;

        // is our request a http(s):// ... ?
        if (preg_match('/^(http|https):\/\//i',$request))
        {
            $location = parse_url($request);
            $this->connect($location['host'],$location['port']);
            $this->set_login($location['user'],$location['pass']);

            $request = $location['path'];
            $content = $location['query'];

            if ( strlen($request) < 1 )
            {
                $request = '/';
            }

        }

        $array_headers = array(
            'User-Agent' => "HTTPSocket/$this->version",
            'Host' => ( $this->remote_port == 80 ? parse_url($this->remote_host,PHP_URL_HOST) : parse_url($this->remote_host,PHP_URL_HOST).":".$this->remote_port ),
            'Accept' => '*/*',
            'Connection' => 'Close' );

        foreach ( $this->extra_headers as $key => $value )
        {
            $array_headers[$key] = $value;
        }

        $this->result = $this->result_header = $this->result_body = '';

        // was content sent as an array? if so, turn it into a string
        if (is_array($content))
        {
            $pairs = array();

            foreach ( $content as $key => $value )
            {
                $pairs[] = "$key=".urlencode($value);
            }

            $content = join('&',$pairs);
            unset($pairs);
        }

        $OK = TRUE;

        // instance connection
        if ($this->bind_host)
        {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_bind($socket,$this->bind_host);

            if (!@socket_connect($socket,$this->remote_host,$this->remote_port))
            {
                $OK = FALSE;
            }

        }
        else
        {
            $socket = @fsockopen( $this->remote_host, $this->remote_port, $sock_errno, $sock_errstr, 10 );
        }

        if ( !$socket || !$OK )
        {
            $this->error[] = "Can't create socket connection to $this->remote_host:$this->remote_port.";
            return 0;
        }

        // if we have a username and password, add the header
        if ( isset($this->remote_uname) && isset($this->remote_passwd) )
        {
            $array_headers['Authorization'] = 'Basic '.base64_encode("$this->remote_uname:$this->remote_passwd");
        }

        // for DA skins: if $this->remote_passwd is NULL, try to use the login key system
        if ( isset($this->remote_uname) && $this->remote_passwd == NULL )
        {
            $array_headers['Cookie'] = "session={$_SERVER['SESSION_ID']}; key={$_SERVER['SESSION_KEY']}";
        }

        // if method is POST, add content length & type headers
        if ( $this->method == 'POST' )
        {
            $array_headers['Content-type'] = 'application/x-www-form-urlencoded';
            $array_headers['Content-length'] = strlen($content);
        }
        // else method is GET or HEAD. we don't support anything else right now.
        else
        {
            if ($content)
            {
                $request .= "?$content";
            }
        }

        // prepare query
        $query = "$this->method $request HTTP/1.0\r\n";
        foreach ( $array_headers as $key => $value )
        {
            $query .= "$key: $value\r\n";
        }
        $query .= "\r\n";

        // if POST we need to append our content
        if ( $this->method == 'POST' && $content )
        {
            $query .= "$content\r\n\r\n";
        }

        // query connection
        if ($this->bind_host)
        {
            socket_write($socket,$query);

            // now load results
            while ( $out = socket_read($socket,2048) )
            {
                $this->result .= $out;
            }
        }
        else
        {
            fwrite( $socket, $query, strlen($query) );

            // now load results
            $this->lastTransferSpeed = 0;
            $status = socket_get_status($socket);
            $startTime = time();
            $length = 0;
            while ( !feof($socket) && !$status['timed_out'] )
            {
                $chunk = fgets($socket,1024);
                $length += strlen($chunk);
                $this->result .= $chunk;

                $elapsedTime = time() - $startTime;

                if ( $elapsedTime > 0 )
                {
                    $this->lastTransferSpeed = ($length/1024)/$elapsedTime;
                }

                if ( $doSpeedCheck > 0 && $elapsedTime > 5 && $this->lastTransferSpeed < $doSpeedCheck )
                {
                    $this->warn[] = "kB/s for last 5 seconds is below 50 kB/s (~".( ($length/1024)/$elapsedTime )."), dropping connection...";
                    $this->result_status_code = 503;
                    break;
                }

            }

            if ( $this->lastTransferSpeed == 0 )
            {
                $this->lastTransferSpeed = $length/1024;
            }

        }

        list($this->result_header,$this->result_body) = preg_split("/\r\n\r\n/",$this->result,2);

        if ($this->bind_host)
        {
            socket_close($socket);
        }
        else
        {
            fclose($socket);
        }

        $this->query_cache[] = $query;


        $headers = $this->fetch_header();

        // what return status did we get?
        if (!$this->result_status_code)
        {
            preg_match("#HTTP/1\.. (\d+)#",$headers[0],$matches);
            $this->result_status_code = $matches[1];
        }

        // did we get the full file?
        if ( !empty($headers['content-length']) && $headers['content-length'] != strlen($this->result_body) )
        {
            $this->result_status_code = 206;
        }

        // now, if we're being passed a location header, should we follow it?
        if ($this->doFollowLocationHeader)
        {
            if ($headers['location'])
            {
                $this->redirectURL = $headers['location'];
                $this->query($headers['location']);
            }
        }
    }

    function getTransferSpeed()
    {
        return $this->lastTransferSpeed;
    }

    /**
     * The quick way to get a URL's content :)
     *
     * @param string URL
     * @param boolean return as array? (like PHP's file() command)
     * @return string result body
     */
    function get($location, $asArray = FALSE )
    {
        $this->query($location);

        if ( $this->get_status_code() == 200 )
        {
            if ($asArray)
            {
                return preg_split("/\n/",$this->fetch_body());
            }

            return $this->fetch_body();
        }

        return FALSE;
    }

    /**
     * Returns the last status code.
     * 200 = OK;
     * 403 = FORBIDDEN;
     * etc.
     *
     * @return int status code
     */
    function get_status_code()
    {
        return $this->result_status_code;
    }

    /**
     * Adds a header, sent with the next query.
     *
     * @param string header name
     * @param string header value
     */
    function add_header($key,$value)
    {
        $this->extra_headers[$key] = $value;
    }

    /**
     * Clears any extra headers.
     *
     */
    function clear_headers()
    {
        $this->extra_headers = array();
    }

    /**
     * Return the result of a query.
     *
     * @return string result
     */
    function fetch_result()
    {
        return $this->result;
    }

    /**
     * Return the header of result (stuff before body).
     *
     * @param string (optional) header to return
     * @return array result header
     */
    function fetch_header( $header = '' )
    {
        $array_headers = preg_split("/\r\n/",$this->result_header);
        $array_return  = array( 0 => $array_headers[0] );
        unset($array_headers[0]);

        foreach ( $array_headers as $pair )
        {
            list($key,$value) = preg_split("/: /",$pair,2);
            $array_return[strtolower($key)] = $value;
        }

        if ( $header != '' )
        {
            return $array_return[strtolower($header)];
        }

        return $array_return;
    }

    /**
     * Return the body of result (stuff after header).
     *
     * @return string result body
     */
    function fetch_body()
    {
        return $this->result_body;
    }

    /**
     * Return parsed body in array format.
     *
     * @return array result parsed
     */
    function fetch_parsed_body()
    {
        parse_str($this->result_body,$x);
        return $x;
    }

}
