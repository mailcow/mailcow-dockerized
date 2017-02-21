<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide alternative IMAP library that doesn't rely on the standard  |
 |   C-Client based version. This allows to function regardless          |
 |   of whether or not the PHP build it's running on has IMAP            |
 |   functionality built-in.                                             |
 |                                                                       |
 |   Based on Iloha IMAP Library. See http://ilohamail.org/ for details  |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Ryo Chijiiwa <Ryo@IlohaMail.org>                              |
 +-----------------------------------------------------------------------+
*/

/**
 * PHP based wrapper class to connect to an IMAP server
 *
 * @package    Framework
 * @subpackage Storage
 */
class rcube_imap_generic
{
    public $error;
    public $errornum;
    public $result;
    public $resultcode;
    public $selected;
    public $data = array();
    public $flags = array(
        'SEEN'     => '\\Seen',
        'DELETED'  => '\\Deleted',
        'ANSWERED' => '\\Answered',
        'DRAFT'    => '\\Draft',
        'FLAGGED'  => '\\Flagged',
        'FORWARDED' => '$Forwarded',
        'MDNSENT'  => '$MDNSent',
        '*'        => '\\*',
    );

    protected $fp;
    protected $host;
    protected $cmd_tag;
    protected $cmd_num = 0;
    protected $resourceid;
    protected $prefs             = array();
    protected $logged            = false;
    protected $capability        = array();
    protected $capability_readed = false;
    protected $debug             = false;
    protected $debug_handler     = false;

    const ERROR_OK       = 0;
    const ERROR_NO       = -1;
    const ERROR_BAD      = -2;
    const ERROR_BYE      = -3;
    const ERROR_UNKNOWN  = -4;
    const ERROR_COMMAND  = -5;
    const ERROR_READONLY = -6;

    const COMMAND_NORESPONSE = 1;
    const COMMAND_CAPABILITY = 2;
    const COMMAND_LASTLINE   = 4;
    const COMMAND_ANONYMIZED = 8;

    const DEBUG_LINE_LENGTH = 4098; // 4KB + 2B for \r\n


    /**
     * Send simple (one line) command to the connection stream
     *
     * @param string $string     Command string
     * @param bool   $endln      True if CRLF need to be added at the end of command
     * @param bool   $anonymized Don't write the given data to log but a placeholder
     *
     * @param int Number of bytes sent, False on error
     */
    protected function putLine($string, $endln = true, $anonymized = false)
    {
        if (!$this->fp) {
            return false;
        }

        if ($this->debug) {
            // anonymize the sent command for logging
            $cut = $endln ? 2 : 0;
            if ($anonymized && preg_match('/^(A\d+ (?:[A-Z]+ )+)(.+)/', $string, $m)) {
                $log = $m[1] . sprintf('****** [%d]', strlen($m[2]) - $cut);
            }
            else if ($anonymized) {
                $log = sprintf('****** [%d]', strlen($string) - $cut);
            }
            else {
                $log = rtrim($string);
            }

            $this->debug('C: ' . $log);
        }

        if ($endln) {
            $string .= "\r\n";
        }

        $res = fwrite($this->fp, $string);

        if ($res === false) {
            @fclose($this->fp);
            $this->fp = null;
        }

        return $res;
    }

    /**
     * Send command to the connection stream with Command Continuation
     * Requests (RFC3501 7.5) and LITERAL+ (RFC2088) support
     *
     * @param string $string     Command string
     * @param bool   $endln      True if CRLF need to be added at the end of command
     * @param bool   $anonymized Don't write the given data to log but a placeholder
     *
     * @return int|bool Number of bytes sent, False on error
     */
    protected function putLineC($string, $endln=true, $anonymized=false)
    {
        if (!$this->fp) {
            return false;
        }

        if ($endln) {
            $string .= "\r\n";
        }

        $res = 0;
        if ($parts = preg_split('/(\{[0-9]+\}\r\n)/m', $string, -1, PREG_SPLIT_DELIM_CAPTURE)) {
            for ($i=0, $cnt=count($parts); $i<$cnt; $i++) {
                if (preg_match('/^\{([0-9]+)\}\r\n$/', $parts[$i+1], $matches)) {
                    // LITERAL+ support
                    if ($this->prefs['literal+']) {
                        $parts[$i+1] = sprintf("{%d+}\r\n", $matches[1]);
                    }

                    $bytes = $this->putLine($parts[$i].$parts[$i+1], false, $anonymized);
                    if ($bytes === false) {
                        return false;
                    }

                    $res += $bytes;

                    // don't wait if server supports LITERAL+ capability
                    if (!$this->prefs['literal+']) {
                        $line = $this->readLine(1000);
                        // handle error in command
                        if ($line[0] != '+') {
                            return false;
                        }
                    }

                    $i++;
                }
                else {
                    $bytes = $this->putLine($parts[$i], false, $anonymized);
                    if ($bytes === false) {
                        return false;
                    }

                    $res += $bytes;
                }
            }
        }

        return $res;
    }

    /**
     * Reads line from the connection stream
     *
     * @param int $size Buffer size
     *
     * @return string Line of text response
     */
    protected function readLine($size = 1024)
    {
        $line = '';

        if (!$size) {
            $size = 1024;
        }

        do {
            if ($this->eof()) {
                return $line ?: null;
            }

            $buffer = fgets($this->fp, $size);

            if ($buffer === false) {
                $this->closeSocket();
                break;
            }

            if ($this->debug) {
                $this->debug('S: '. rtrim($buffer));
            }

            $line .= $buffer;
        }
        while (substr($buffer, -1) != "\n");

        return $line;
    }

    /**
     * Reads more data from the connection stream when provided
     * data contain string literal
     *
     * @param string  $line    Response text
     * @param bool    $escape  Enables escaping
     *
     * @return string Line of text response
     */
    protected function multLine($line, $escape = false)
    {
        $line = rtrim($line);
        if (preg_match('/\{([0-9]+)\}$/', $line, $m)) {
            $out   = '';
            $str   = substr($line, 0, -strlen($m[0]));
            $bytes = $m[1];

            while (strlen($out) < $bytes) {
                $line = $this->readBytes($bytes);
                if ($line === null) {
                    break;
                }

                $out .= $line;
            }

            $line = $str . ($escape ? $this->escape($out) : $out);
        }

        return $line;
    }

    /**
     * Reads specified number of bytes from the connection stream
     *
     * @param int $bytes Number of bytes to get
     *
     * @return string Response text
     */
    protected function readBytes($bytes)
    {
        $data = '';
        $len  = 0;

        while ($len < $bytes && !$this->eof()) {
            $d = fread($this->fp, $bytes-$len);
            if ($this->debug) {
                $this->debug('S: '. $d);
            }
            $data .= $d;
            $data_len = strlen($data);
            if ($len == $data_len) {
                break; // nothing was read -> exit to avoid apache lockups
            }
            $len = $data_len;
        }

        return $data;
    }

    /**
     * Reads complete response to the IMAP command
     *
     * @param array $untagged Will be filled with untagged response lines
     *
     * @return string Response text
     */
    protected function readReply(&$untagged = null)
    {
        do {
            $line = trim($this->readLine(1024));
            // store untagged response lines
            if ($line[0] == '*') {
                $untagged[] = $line;
            }
        }
        while ($line[0] == '*');

        if ($untagged) {
            $untagged = join("\n", $untagged);
        }

        return $line;
    }

    /**
     * Response parser.
     *
     * @param string $string     Response text
     * @param string $err_prefix Error message prefix
     *
     * @return int Response status
     */
    protected function parseResult($string, $err_prefix = '')
    {
        if (preg_match('/^[a-z0-9*]+ (OK|NO|BAD|BYE)(.*)$/i', trim($string), $matches)) {
            $res = strtoupper($matches[1]);
            $str = trim($matches[2]);

            if ($res == 'OK') {
                $this->errornum = self::ERROR_OK;
            }
            else if ($res == 'NO') {
                $this->errornum = self::ERROR_NO;
            }
            else if ($res == 'BAD') {
                $this->errornum = self::ERROR_BAD;
            }
            else if ($res == 'BYE') {
                $this->closeSocket();
                $this->errornum = self::ERROR_BYE;
            }

            if ($str) {
                $str = trim($str);
                // get response string and code (RFC5530)
                if (preg_match("/^\[([a-z-]+)\]/i", $str, $m)) {
                    $this->resultcode = strtoupper($m[1]);
                    $str = trim(substr($str, strlen($m[1]) + 2));
                }
                else {
                    $this->resultcode = null;
                    // parse response for [APPENDUID 1204196876 3456]
                    if (preg_match("/^\[APPENDUID [0-9]+ ([0-9]+)\]/i", $str, $m)) {
                        $this->data['APPENDUID'] = $m[1];
                    }
                    // parse response for [COPYUID 1204196876 3456:3457 123:124]
                    else if (preg_match("/^\[COPYUID [0-9]+ ([0-9,:]+) ([0-9,:]+)\]/i", $str, $m)) {
                        $this->data['COPYUID'] = array($m[1], $m[2]);
                    }
                }

                $this->result = $str;

                if ($this->errornum != self::ERROR_OK) {
                    $this->error = $err_prefix ? $err_prefix.$str : $str;
                }
            }

            return $this->errornum;
        }

        return self::ERROR_UNKNOWN;
    }

    /**
     * Checks connection stream state.
     *
     * @return bool True if connection is closed
     */
    protected function eof()
    {
        if (!is_resource($this->fp)) {
            return true;
        }

        // If a connection opened by fsockopen() wasn't closed
        // by the server, feof() will hang.
        $start = microtime(true);

        if (feof($this->fp) ||
            ($this->prefs['timeout'] && (microtime(true) - $start > $this->prefs['timeout']))
        ) {
            $this->closeSocket();
            return true;
        }

        return false;
    }

    /**
     * Closes connection stream.
     */
    protected function closeSocket()
    {
        @fclose($this->fp);
        $this->fp = null;
    }

    /**
     * Error code/message setter.
     */
    protected function setError($code, $msg = '')
    {
        $this->errornum = $code;
        $this->error    = $msg;
    }

    /**
     * Checks response status.
     * Checks if command response line starts with specified prefix (or * BYE/BAD)
     *
     * @param string $string   Response text
     * @param string $match    Prefix to match with (case-sensitive)
     * @param bool   $error    Enables BYE/BAD checking
     * @param bool   $nonempty Enables empty response checking
     *
     * @return bool True any check is true or connection is closed.
     */
    protected function startsWith($string, $match, $error = false, $nonempty = false)
    {
        if (!$this->fp) {
            return true;
        }

        if (strncmp($string, $match, strlen($match)) == 0) {
            return true;
        }

        if ($error && preg_match('/^\* (BYE|BAD) /i', $string, $m)) {
            if (strtoupper($m[1]) == 'BYE') {
                $this->closeSocket();
            }
            return true;
        }

        if ($nonempty && !strlen($string)) {
            return true;
        }

        return false;
    }

    /**
     * Capabilities checker
     */
    protected function hasCapability($name)
    {
        if (empty($this->capability) || $name == '') {
            return false;
        }

        if (in_array($name, $this->capability)) {
            return true;
        }
        else if (strpos($name, '=')) {
            return false;
        }

        $result = array();
        foreach ($this->capability as $cap) {
            $entry = explode('=', $cap);
            if ($entry[0] == $name) {
                $result[] = $entry[1];
            }
        }

        return $result ?: false;
    }

    /**
     * Capabilities checker
     *
     * @param string $name Capability name
     *
     * @return mixed Capability values array for key=value pairs, true/false for others
     */
    public function getCapability($name)
    {
        $result = $this->hasCapability($name);

        if (!empty($result)) {
            return $result;
        }
        else if ($this->capability_readed) {
            return false;
        }

        // get capabilities (only once) because initial
        // optional CAPABILITY response may differ
        $result = $this->execute('CAPABILITY');

        if ($result[0] == self::ERROR_OK) {
            $this->parseCapability($result[1]);
        }

        $this->capability_readed = true;

        return $this->hasCapability($name);
    }

    /**
     * Clears detected server capabilities
     */
    public function clearCapability()
    {
        $this->capability        = array();
        $this->capability_readed = false;
    }

    /**
     * DIGEST-MD5/CRAM-MD5/PLAIN Authentication
     *
     * @param string $user Username
     * @param string $pass Password
     * @param string $type Authentication type (PLAIN/CRAM-MD5/DIGEST-MD5)
     *
     * @return resource Connection resourse on success, error code on error
     */
    protected function authenticate($user, $pass, $type = 'PLAIN')
    {
        if ($type == 'CRAM-MD5' || $type == 'DIGEST-MD5') {
            if ($type == 'DIGEST-MD5' && !class_exists('Auth_SASL')) {
                $this->setError(self::ERROR_BYE,
                    "The Auth_SASL package is required for DIGEST-MD5 authentication");
                return self::ERROR_BAD;
            }

            $this->putLine($this->nextTag() . " AUTHENTICATE $type");
            $line = trim($this->readReply());

            if ($line[0] == '+') {
                $challenge = substr($line, 2);
            }
            else {
                return $this->parseResult($line);
            }

            if ($type == 'CRAM-MD5') {
                // RFC2195: CRAM-MD5
                $ipad = '';
                $opad = '';
                $xor  = function($str1, $str2) {
                    $result = '';
                    $size   = strlen($str1);
                    for ($i=0; $i<$size; $i++) {
                        $result .= chr(ord($str1[$i]) ^ ord($str2[$i]));
                    }
                    return $result;
                };

                // initialize ipad, opad
                for ($i=0; $i<64; $i++) {
                    $ipad .= chr(0x36);
                    $opad .= chr(0x5C);
                }

                // pad $pass so it's 64 bytes
                $pass = str_pad($pass, 64, chr(0));

                // generate hash
                $hash  = md5($xor($pass, $opad) . pack("H*",
                    md5($xor($pass, $ipad) . base64_decode($challenge))));
                $reply = base64_encode($user . ' ' . $hash);

                // send result
                $this->putLine($reply, true, true);
            }
            else {
                // RFC2831: DIGEST-MD5
                // proxy authorization
                if (!empty($this->prefs['auth_cid'])) {
                    $authc = $this->prefs['auth_cid'];
                    $pass  = $this->prefs['auth_pw'];
                }
                else {
                    $authc = $user;
                    $user  = '';
                }

                $auth_sasl = new Auth_SASL;
                $auth_sasl = $auth_sasl->factory('digestmd5');
                $reply     = base64_encode($auth_sasl->getResponse($authc, $pass,
                    base64_decode($challenge), $this->host, 'imap', $user));

                // send result
                $this->putLine($reply, true, true);
                $line = trim($this->readReply());

                if ($line[0] != '+') {
                    return $this->parseResult($line);
                }

                // check response
                $challenge = substr($line, 2);
                $challenge = base64_decode($challenge);
                if (strpos($challenge, 'rspauth=') === false) {
                    $this->setError(self::ERROR_BAD,
                        "Unexpected response from server to DIGEST-MD5 response");
                    return self::ERROR_BAD;
                }

                $this->putLine('');
            }

            $line   = $this->readReply();
            $result = $this->parseResult($line);
        }
        else if ($type == 'GSSAPI') {
            if (!extension_loaded('krb5')) {
                $this->setError(self::ERROR_BYE,
                    "The krb5 extension is required for GSSAPI authentication");
                return self::ERROR_BAD;
            }

            if (empty($this->prefs['gssapi_cn'])) {
                $this->setError(self::ERROR_BYE,
                    "The gssapi_cn parameter is required for GSSAPI authentication");
                return self::ERROR_BAD;
            }

            if (empty($this->prefs['gssapi_context'])) {
                $this->setError(self::ERROR_BYE,
                    "The gssapi_context parameter is required for GSSAPI authentication");
                return self::ERROR_BAD;
            }

            putenv('KRB5CCNAME=' . $this->prefs['gssapi_cn']);

            try {
                $ccache = new KRB5CCache();
                $ccache->open($this->prefs['gssapi_cn']);
                $gssapicontext = new GSSAPIContext();
                $gssapicontext->acquireCredentials($ccache);

                $token   = '';
                $success = $gssapicontext->initSecContext($this->prefs['gssapi_context'], null, null, null, $token);
                $token   = base64_encode($token);
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                $this->setError(self::ERROR_BYE, "GSSAPI authentication failed");
                return self::ERROR_BAD;
            }

            $this->putLine($this->nextTag() . " AUTHENTICATE GSSAPI " . $token);
            $line = trim($this->readReply());

            if ($line[0] != '+') {
                return $this->parseResult($line);
            }

            try {
                $challenge = base64_decode(substr($line, 2));
                $gssapicontext->unwrap($challenge, $challenge);
                $gssapicontext->wrap($challenge, $challenge, true);
            }
            catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                $this->setError(self::ERROR_BYE, "GSSAPI authentication failed");
                return self::ERROR_BAD;
            }

            $this->putLine(base64_encode($challenge));

            $line   = $this->readReply();
            $result = $this->parseResult($line);
        }
        else { // PLAIN
            // proxy authorization
            if (!empty($this->prefs['auth_cid'])) {
                $authc = $this->prefs['auth_cid'];
                $pass  = $this->prefs['auth_pw'];
            }
            else {
                $authc = $user;
                $user  = '';
            }

            $reply = base64_encode($user . chr(0) . $authc . chr(0) . $pass);

            // RFC 4959 (SASL-IR): save one round trip
            if ($this->getCapability('SASL-IR')) {
                list($result, $line) = $this->execute("AUTHENTICATE PLAIN", array($reply),
                    self::COMMAND_LASTLINE | self::COMMAND_CAPABILITY | self::COMMAND_ANONYMIZED);
            }
            else {
                $this->putLine($this->nextTag() . " AUTHENTICATE PLAIN");
                $line = trim($this->readReply());

                if ($line[0] != '+') {
                    return $this->parseResult($line);
                }

                // send result, get reply and process it
                $this->putLine($reply, true, true);
                $line   = $this->readReply();
                $result = $this->parseResult($line);
            }
        }

        if ($result == self::ERROR_OK) {
            // optional CAPABILITY response
            if ($line && preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)) {
                $this->parseCapability($matches[1], true);
            }
            return $this->fp;
        }
        else {
            $this->setError($result, "AUTHENTICATE $type: $line");
        }

        return $result;
    }

    /**
     * LOGIN Authentication
     *
     * @param string $user Username
     * @param string $pass Password
     *
     * @return resource Connection resourse on success, error code on error
     */
    protected function login($user, $password)
    {
        list($code, $response) = $this->execute('LOGIN', array(
            $this->escape($user), $this->escape($password)), self::COMMAND_CAPABILITY | self::COMMAND_ANONYMIZED);

        // re-set capabilities list if untagged CAPABILITY response provided
        if (preg_match('/\* CAPABILITY (.+)/i', $response, $matches)) {
            $this->parseCapability($matches[1], true);
        }

        if ($code == self::ERROR_OK) {
            return $this->fp;
        }

        return $code;
    }

    /**
     * Detects hierarchy delimiter
     *
     * @return string The delimiter
     */
    public function getHierarchyDelimiter()
    {
        if ($this->prefs['delimiter']) {
            return $this->prefs['delimiter'];
        }

        // try (LIST "" ""), should return delimiter (RFC2060 Sec 6.3.8)
        list($code, $response) = $this->execute('LIST',
            array($this->escape(''), $this->escape('')));

        if ($code == self::ERROR_OK) {
            $args = $this->tokenizeResponse($response, 4);
            $delimiter = $args[3];

            if (strlen($delimiter) > 0) {
                return ($this->prefs['delimiter'] = $delimiter);
            }
        }
    }

    /**
     * NAMESPACE handler (RFC 2342)
     *
     * @return array Namespace data hash (personal, other, shared)
     */
    public function getNamespace()
    {
        if (array_key_exists('namespace', $this->prefs)) {
            return $this->prefs['namespace'];
        }

        if (!$this->getCapability('NAMESPACE')) {
            return self::ERROR_BAD;
        }

        list($code, $response) = $this->execute('NAMESPACE');

        if ($code == self::ERROR_OK && preg_match('/^\* NAMESPACE /', $response)) {
            $response = substr($response, 11);
            $data     = $this->tokenizeResponse($response);
        }

        if (!is_array($data)) {
            return $code;
        }

        $this->prefs['namespace'] = array(
            'personal' => $data[0],
            'other'    => $data[1],
            'shared'   => $data[2],
        );

        return $this->prefs['namespace'];
    }

    /**
     * Connects to IMAP server and authenticates.
     *
     * @param string $host     Server hostname or IP
     * @param string $user     User name
     * @param string $password Password
     * @param array  $options  Connection and class options
     *
     * @return bool True on success, False on failure
     */
    public function connect($host, $user, $password, $options = array())
    {
        // configure
        $this->set_prefs($options);

        $this->host     = $host;
        $this->user     = $user;
        $this->logged   = false;
        $this->selected = null;

        // check input
        if (empty($host)) {
            $this->setError(self::ERROR_BAD, "Empty host");
            return false;
        }

        if (empty($user)) {
            $this->setError(self::ERROR_NO, "Empty user");
            return false;
        }

        if (empty($password) && empty($options['gssapi_cn'])) {
            $this->setError(self::ERROR_NO, "Empty password");
            return false;
        }

        // Connect
        if (!$this->_connect($host)) {
            return false;
        }

        // Send ID info
        if (!empty($this->prefs['ident']) && $this->getCapability('ID')) {
            $this->data['ID'] = $this->id($this->prefs['ident']);
        }

        $auth_method  = $this->prefs['auth_type'];
        $auth_methods = array();
        $result       = null;

        // check for supported auth methods
        if ($auth_method == 'CHECK') {
            if ($auth_caps = $this->getCapability('AUTH')) {
                $auth_methods = $auth_caps;
            }

            // RFC 2595 (LOGINDISABLED) LOGIN disabled when connection is not secure
            $login_disabled = $this->getCapability('LOGINDISABLED');
            if (($key = array_search('LOGIN', $auth_methods)) !== false) {
                if ($login_disabled) {
                    unset($auth_methods[$key]);
                }
            }
            else if (!$login_disabled) {
                $auth_methods[] = 'LOGIN';
            }

            // Use best (for security) supported authentication method
            $all_methods = array('DIGEST-MD5', 'CRAM-MD5', 'CRAM_MD5', 'PLAIN', 'LOGIN');

            if (!empty($this->prefs['gssapi_cn'])) {
                array_unshift($all_methods, 'GSSAPI');
            }

            foreach ($all_methods as $auth_method) {
                if (in_array($auth_method, $auth_methods)) {
                    break;
                }
            }
        }
        else {
            // Prevent from sending credentials in plain text when connection is not secure
            if ($auth_method == 'LOGIN' && $this->getCapability('LOGINDISABLED')) {
                $this->setError(self::ERROR_BAD, "Login disabled by IMAP server");
                $this->closeConnection();
                return false;
            }
            // replace AUTH with CRAM-MD5 for backward compat.
            if ($auth_method == 'AUTH') {
                $auth_method = 'CRAM-MD5';
            }
        }

        // pre-login capabilities can be not complete
        $this->capability_readed = false;

        // Authenticate
        switch ($auth_method) {
            case 'CRAM_MD5':
                $auth_method = 'CRAM-MD5';
            case 'CRAM-MD5':
            case 'DIGEST-MD5':
            case 'PLAIN':
            case 'GSSAPI':
                $result = $this->authenticate($user, $password, $auth_method);
                break;
            case 'LOGIN':
                $result = $this->login($user, $password);
                break;
            default:
                $this->setError(self::ERROR_BAD, "Configuration error. Unknown auth method: $auth_method");
        }

        // Connected and authenticated
        if (is_resource($result)) {
            if ($this->prefs['force_caps']) {
                $this->clearCapability();
            }
            $this->logged = true;

            return true;
        }

        $this->closeConnection();

        return false;
    }

    /**
     * Connects to IMAP server.
     *
     * @param string $host Server hostname or IP
     *
     * @return bool True on success, False on failure
     */
    protected function _connect($host)
    {
        // initialize connection
        $this->error    = '';
        $this->errornum = self::ERROR_OK;

        if (!$this->prefs['port']) {
            $this->prefs['port'] = 143;
        }

        // check for SSL
        if ($this->prefs['ssl_mode'] && $this->prefs['ssl_mode'] != 'tls') {
            $host = $this->prefs['ssl_mode'] . '://' . $host;
        }

        if ($this->prefs['timeout'] <= 0) {
            $this->prefs['timeout'] = max(0, intval(ini_get('default_socket_timeout')));
        }

        if (!empty($this->prefs['socket_options'])) {
            $context  = stream_context_create($this->prefs['socket_options']);
            $this->fp = stream_socket_client($host . ':' . $this->prefs['port'], $errno, $errstr,
                $this->prefs['timeout'], STREAM_CLIENT_CONNECT, $context);
        }
        else {
            $this->fp = @fsockopen($host, $this->prefs['port'], $errno, $errstr, $this->prefs['timeout']);
        }

        if (!$this->fp) {
            $this->setError(self::ERROR_BAD, sprintf("Could not connect to %s:%d: %s",
                $host, $this->prefs['port'], $errstr ?: "Unknown reason"));

            return false;
        }

        if ($this->prefs['timeout'] > 0) {
            stream_set_timeout($this->fp, $this->prefs['timeout']);
        }

        $line = trim(fgets($this->fp, 8192));

        if ($this->debug) {
            // set connection identifier for debug output
            preg_match('/#([0-9]+)/', (string) $this->fp, $m);
            $this->resourceid = strtoupper(substr(md5($m[1].$this->user.microtime()), 0, 4));

            if ($line) {
                $this->debug('S: '. $line);
            }
        }

        // Connected to wrong port or connection error?
        if (!preg_match('/^\* (OK|PREAUTH)/i', $line)) {
            if ($line)
                $error = sprintf("Wrong startup greeting (%s:%d): %s", $host, $this->prefs['port'], $line);
            else
                $error = sprintf("Empty startup greeting (%s:%d)", $host, $this->prefs['port']);

            $this->setError(self::ERROR_BAD, $error);
            $this->closeConnection();
            return false;
        }

        $this->data['GREETING'] = trim(preg_replace('/\[[^\]]+\]\s*/', '', $line));

        // RFC3501 [7.1] optional CAPABILITY response
        if (preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)) {
            $this->parseCapability($matches[1], true);
        }

        // TLS connection
        if ($this->prefs['ssl_mode'] == 'tls' && $this->getCapability('STARTTLS')) {
            $res = $this->execute('STARTTLS');

            if ($res[0] != self::ERROR_OK) {
                $this->closeConnection();
                return false;
            }

            if (isset($this->prefs['socket_options']['ssl']['crypto_method'])) {
                $crypto_method = $this->prefs['socket_options']['ssl']['crypto_method'];
            }
            else {
                // There is no flag to enable all TLS methods. Net_SMTP
                // handles enabling TLS similarly.
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | @STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                    | @STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            if (!stream_socket_enable_crypto($this->fp, true, $crypto_method)) {
                $this->setError(self::ERROR_BAD, "Unable to negotiate TLS");
                $this->closeConnection();
                return false;
            }

            // Now we're secure, capabilities need to be reread
            $this->clearCapability();
        }

        return true;
    }

    /**
     * Initializes environment
     */
    protected function set_prefs($prefs)
    {
        // set preferences
        if (is_array($prefs)) {
            $this->prefs = $prefs;
        }

        // set auth method
        if (!empty($this->prefs['auth_type'])) {
            $this->prefs['auth_type'] = strtoupper($this->prefs['auth_type']);
        }
        else {
            $this->prefs['auth_type'] = 'CHECK';
        }

        // disabled capabilities
        if (!empty($this->prefs['disabled_caps'])) {
            $this->prefs['disabled_caps'] = array_map('strtoupper', (array)$this->prefs['disabled_caps']);
        }

        // additional message flags
        if (!empty($this->prefs['message_flags'])) {
            $this->flags = array_merge($this->flags, $this->prefs['message_flags']);
            unset($this->prefs['message_flags']);
        }
    }

    /**
     * Checks connection status
     *
     * @return bool True if connection is active and user is logged in, False otherwise.
     */
    public function connected()
    {
        return $this->fp && $this->logged;
    }

    /**
     * Closes connection with logout.
     */
    public function closeConnection()
    {
        if ($this->logged && $this->putLine($this->nextTag() . ' LOGOUT')) {
            $this->readReply();
        }

        $this->closeSocket();
    }

    /**
     * Executes SELECT command (if mailbox is already not in selected state)
     *
     * @param string $mailbox      Mailbox name
     * @param array  $qresync_data QRESYNC data (RFC5162)
     *
     * @return boolean True on success, false on error
     */
    public function select($mailbox, $qresync_data = null)
    {
        if (!strlen($mailbox)) {
            return false;
        }

        if ($this->selected === $mailbox) {
            return true;
        }
/*
    Temporary commented out because Courier returns \Noselect for INBOX
    Requires more investigation

        if (is_array($this->data['LIST']) && is_array($opts = $this->data['LIST'][$mailbox])) {
            if (in_array('\\Noselect', $opts)) {
                return false;
            }
        }
*/
        $params = array($this->escape($mailbox));

        // QRESYNC data items
        //    0. the last known UIDVALIDITY,
        //    1. the last known modification sequence,
        //    2. the optional set of known UIDs, and
        //    3. an optional parenthesized list of known sequence ranges and their
        //       corresponding UIDs.
        if (!empty($qresync_data)) {
            if (!empty($qresync_data[2])) {
                $qresync_data[2] = self::compressMessageSet($qresync_data[2]);
            }

            $params[] = array('QRESYNC', $qresync_data);
        }

        list($code, $response) = $this->execute('SELECT', $params);

        if ($code == self::ERROR_OK) {
            $this->clear_mailbox_cache();

            $response = explode("\r\n", $response);
            foreach ($response as $line) {
                if (preg_match('/^\* OK \[/i', $line)) {
                    $pos   = strcspn($line, ' ]', 6);
                    $token = strtoupper(substr($line, 6, $pos));
                    $pos   += 7;

                    switch ($token) {
                    case 'UIDNEXT':
                    case 'UIDVALIDITY':
                    case 'UNSEEN':
                        if ($len = strspn($line, '0123456789', $pos)) {
                            $this->data[$token] = (int) substr($line, $pos, $len);
                        }
                        break;

                    case 'HIGHESTMODSEQ':
                        if ($len = strspn($line, '0123456789', $pos)) {
                            $this->data[$token] = (string) substr($line, $pos, $len);
                        }
                        break;

                    case 'NOMODSEQ':
                        $this->data[$token] = true;
                        break;

                    case 'PERMANENTFLAGS':
                        $start = strpos($line, '(', $pos);
                        $end   = strrpos($line, ')');
                        if ($start && $end) {
                            $flags = substr($line, $start + 1, $end - $start - 1);
                            $this->data[$token] = explode(' ', $flags);
                        }
                        break;
                    }
                }
                else if (preg_match('/^\* ([0-9]+) (EXISTS|RECENT|FETCH)/i', $line, $match)) {
                    $token = strtoupper($match[2]);
                    switch ($token) {
                    case 'EXISTS':
                    case 'RECENT':
                        $this->data[$token] = (int) $match[1];
                        break;

                    case 'FETCH':
                        // QRESYNC FETCH response (RFC5162)
                        $line       = substr($line, strlen($match[0]));
                        $fetch_data = $this->tokenizeResponse($line, 1);
                        $data       = array('id' => $match[1]);

                        for ($i=0, $size=count($fetch_data); $i<$size; $i+=2) {
                            $data[strtolower($fetch_data[$i])] = $fetch_data[$i+1];
                        }

                        $this->data['QRESYNC'][$data['uid']] = $data;
                        break;
                    }
                }
                // QRESYNC VANISHED response (RFC5162)
                else if (preg_match('/^\* VANISHED [()EARLIER]*/i', $line, $match)) {
                    $line   = substr($line, strlen($match[0]));
                    $v_data = $this->tokenizeResponse($line, 1);

                    $this->data['VANISHED'] = $v_data;
                }
            }

            $this->data['READ-WRITE'] = $this->resultcode != 'READ-ONLY';
            $this->selected = $mailbox;

            return true;
        }

        return false;
    }

    /**
     * Executes STATUS command
     *
     * @param string $mailbox Mailbox name
     * @param array  $items   Additional requested item names. By default
     *                        MESSAGES and UNSEEN are requested. Other defined
     *                        in RFC3501: UIDNEXT, UIDVALIDITY, RECENT
     *
     * @return array Status item-value hash
     * @since 0.5-beta
     */
    public function status($mailbox, $items = array())
    {
        if (!strlen($mailbox)) {
            return false;
        }

        if (!in_array('MESSAGES', $items)) {
            $items[] = 'MESSAGES';
        }
        if (!in_array('UNSEEN', $items)) {
            $items[] = 'UNSEEN';
        }

        list($code, $response) = $this->execute('STATUS', array($this->escape($mailbox),
            '(' . implode(' ', (array) $items) . ')'));

        if ($code == self::ERROR_OK && preg_match('/^\* STATUS /i', $response)) {
            $result   = array();
            $response = substr($response, 9); // remove prefix "* STATUS "

            list($mbox, $items) = $this->tokenizeResponse($response, 2);

            // Fix for #1487859. Some buggy server returns not quoted
            // folder name with spaces. Let's try to handle this situation
            if (!is_array($items) && ($pos = strpos($response, '(')) !== false) {
                $response = substr($response, $pos);
                $items    = $this->tokenizeResponse($response, 1);

                if (!is_array($items)) {
                    return $result;
                }
            }

            for ($i=0, $len=count($items); $i<$len; $i += 2) {
                $result[$items[$i]] = $items[$i+1];
            }

            $this->data['STATUS:'.$mailbox] = $result;

            return $result;
        }

        return false;
    }

    /**
     * Executes EXPUNGE command
     *
     * @param string       $mailbox  Mailbox name
     * @param string|array $messages Message UIDs to expunge
     *
     * @return boolean True on success, False on error
     */
    public function expunge($mailbox, $messages = null)
    {
        if (!$this->select($mailbox)) {
            return false;
        }

        if (!$this->data['READ-WRITE']) {
            $this->setError(self::ERROR_READONLY, "Mailbox is read-only");
            return false;
        }

        // Clear internal status cache
        $this->clear_status_cache($mailbox);

        if (!empty($messages) && $messages != '*' && $this->hasCapability('UIDPLUS')) {
            $messages = self::compressMessageSet($messages);
            $result   = $this->execute('UID EXPUNGE', array($messages), self::COMMAND_NORESPONSE);
        }
        else {
            $result = $this->execute('EXPUNGE', null, self::COMMAND_NORESPONSE);
        }

        if ($result == self::ERROR_OK) {
            $this->selected = null; // state has changed, need to reselect
            return true;
        }

        return false;
    }

    /**
     * Executes CLOSE command
     *
     * @return boolean True on success, False on error
     * @since 0.5
     */
    public function close()
    {
        $result = $this->execute('CLOSE', null, self::COMMAND_NORESPONSE);

        if ($result == self::ERROR_OK) {
            $this->selected = null;
            return true;
        }

        return false;
    }

    /**
     * Folder subscription (SUBSCRIBE)
     *
     * @param string $mailbox Mailbox name
     *
     * @return boolean True on success, False on error
     */
    public function subscribe($mailbox)
    {
        $result = $this->execute('SUBSCRIBE', array($this->escape($mailbox)),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Folder unsubscription (UNSUBSCRIBE)
     *
     * @param string $mailbox Mailbox name
     *
     * @return boolean True on success, False on error
     */
    public function unsubscribe($mailbox)
    {
        $result = $this->execute('UNSUBSCRIBE', array($this->escape($mailbox)),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Folder creation (CREATE)
     *
     * @param string $mailbox Mailbox name
     * @param array  $types   Optional folder types (RFC 6154)
     *
     * @return bool True on success, False on error
     */
    public function createFolder($mailbox, $types = null)
    {
        $args = array($this->escape($mailbox));

        // RFC 6154: CREATE-SPECIAL-USE
        if (!empty($types) && $this->getCapability('CREATE-SPECIAL-USE')) {
            $args[] = '(USE (' . implode(' ', $types) . '))';
        }

        $result = $this->execute('CREATE', $args, self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Folder renaming (RENAME)
     *
     * @param string $mailbox Mailbox name
     *
     * @return bool True on success, False on error
     */
    public function renameFolder($from, $to)
    {
        $result = $this->execute('RENAME', array($this->escape($from), $this->escape($to)),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Executes DELETE command
     *
     * @param string $mailbox Mailbox name
     *
     * @return boolean True on success, False on error
     */
    public function deleteFolder($mailbox)
    {
        $result = $this->execute('DELETE', array($this->escape($mailbox)),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Removes all messages in a folder
     *
     * @param string $mailbox Mailbox name
     *
     * @return boolean True on success, False on error
     */
    public function clearFolder($mailbox)
    {
        if ($this->countMessages($mailbox) > 0) {
            $res = $this->flag($mailbox, '1:*', 'DELETED');
        }

        if ($res) {
            if ($this->selected === $mailbox) {
                $res = $this->close();
            }
            else {
                $res = $this->expunge($mailbox);
            }
        }

        return $res;
    }

    /**
     * Returns list of mailboxes
     *
     * @param string $ref         Reference name
     * @param string $mailbox     Mailbox name
     * @param array  $return_opts (see self::_listMailboxes)
     * @param array  $select_opts (see self::_listMailboxes)
     *
     * @return array|bool List of mailboxes or hash of options if STATUS/MYROGHTS response
     *                    is requested, False on error.
     */
    public function listMailboxes($ref, $mailbox, $return_opts = array(), $select_opts = array())
    {
        return $this->_listMailboxes($ref, $mailbox, false, $return_opts, $select_opts);
    }

    /**
     * Returns list of subscribed mailboxes
     *
     * @param string $ref         Reference name
     * @param string $mailbox     Mailbox name
     * @param array  $return_opts (see self::_listMailboxes)
     *
     * @return array|bool List of mailboxes or hash of options if STATUS/MYROGHTS response
     *                    is requested, False on error.
     */
    public function listSubscribed($ref, $mailbox, $return_opts = array())
    {
        return $this->_listMailboxes($ref, $mailbox, true, $return_opts, null);
    }

    /**
     * IMAP LIST/LSUB command
     *
     * @param string $ref         Reference name
     * @param string $mailbox     Mailbox name
     * @param bool   $subscribed  Enables returning subscribed mailboxes only
     * @param array  $return_opts List of RETURN options (RFC5819: LIST-STATUS, RFC5258: LIST-EXTENDED)
     *                            Possible: MESSAGES, RECENT, UIDNEXT, UIDVALIDITY, UNSEEN,
     *                                      MYRIGHTS, SUBSCRIBED, CHILDREN
     * @param array  $select_opts List of selection options (RFC5258: LIST-EXTENDED)
     *                            Possible: SUBSCRIBED, RECURSIVEMATCH, REMOTE,
     *                                      SPECIAL-USE (RFC6154)
     *
     * @return array|bool List of mailboxes or hash of options if STATUS/MYROGHTS response
     *                    is requested, False on error.
     */
    protected function _listMailboxes($ref, $mailbox, $subscribed=false,
        $return_opts=array(), $select_opts=array())
    {
        if (!strlen($mailbox)) {
            $mailbox = '*';
        }

        $args = array();
        $rets = array();

        if (!empty($select_opts) && $this->getCapability('LIST-EXTENDED')) {
            $select_opts = (array) $select_opts;

            $args[] = '(' . implode(' ', $select_opts) . ')';
        }

        $args[] = $this->escape($ref);
        $args[] = $this->escape($mailbox);

        if (!empty($return_opts) && $this->getCapability('LIST-EXTENDED')) {
            $ext_opts    = array('SUBSCRIBED', 'CHILDREN');
            $rets        = array_intersect($return_opts, $ext_opts);
            $return_opts = array_diff($return_opts, $rets);
        }

        if (!empty($return_opts) && $this->getCapability('LIST-STATUS')) {
            $lstatus     = true;
            $status_opts = array('MESSAGES', 'RECENT', 'UIDNEXT', 'UIDVALIDITY', 'UNSEEN');
            $opts        = array_diff($return_opts, $status_opts);
            $status_opts = array_diff($return_opts, $opts);

            if (!empty($status_opts)) {
                $rets[] = 'STATUS (' . implode(' ', $status_opts) . ')';
            }

            if (!empty($opts)) {
                $rets = array_merge($rets, $opts);
            }
        }

        if (!empty($rets)) {
            $args[] = 'RETURN (' . implode(' ', $rets) . ')';
        }

        list($code, $response) = $this->execute($subscribed ? 'LSUB' : 'LIST', $args);

        if ($code == self::ERROR_OK) {
            $folders  = array();
            $last     = 0;
            $pos      = 0;
            $response .= "\r\n";

            while ($pos = strpos($response, "\r\n", $pos+1)) {
                // literal string, not real end-of-command-line
                if ($response[$pos-1] == '}') {
                    continue;
                }

                $line = substr($response, $last, $pos - $last);
                $last = $pos + 2;

                if (!preg_match('/^\* (LIST|LSUB|STATUS|MYRIGHTS) /i', $line, $m)) {
                    continue;
                }

                $cmd  = strtoupper($m[1]);
                $line = substr($line, strlen($m[0]));

                // * LIST (<options>) <delimiter> <mailbox>
                if ($cmd == 'LIST' || $cmd == 'LSUB') {
                    list($opts, $delim, $mailbox) = $this->tokenizeResponse($line, 3);

                    // Remove redundant separator at the end of folder name, UW-IMAP bug? (#1488879)
                    if ($delim) {
                        $mailbox = rtrim($mailbox, $delim);
                    }

                    // Add to result array
                    if (!$lstatus) {
                        $folders[] = $mailbox;
                    }
                    else {
                        $folders[$mailbox] = array();
                    }

                    // store folder options
                    if ($cmd == 'LIST') {
                        // Add to options array
                        if (empty($this->data['LIST'][$mailbox])) {
                            $this->data['LIST'][$mailbox] = $opts;
                        }
                        else if (!empty($opts)) {
                            $this->data['LIST'][$mailbox] = array_unique(array_merge(
                                $this->data['LIST'][$mailbox], $opts));
                        }
                    }
                }
                else if ($lstatus) {
                    // * STATUS <mailbox> (<result>)
                    if ($cmd == 'STATUS') {
                        list($mailbox, $status) = $this->tokenizeResponse($line, 2);

                        for ($i=0, $len=count($status); $i<$len; $i += 2) {
                            list($name, $value) = $this->tokenizeResponse($status, 2);
                            $folders[$mailbox][$name] = $value;
                        }
                    }
                    // * MYRIGHTS <mailbox> <acl>
                    else if ($cmd == 'MYRIGHTS') {
                        list($mailbox, $acl)  = $this->tokenizeResponse($line, 2);
                        $folders[$mailbox]['MYRIGHTS'] = $acl;
                    }
                }
            }

            return $folders;
        }

        return false;
    }

    /**
     * Returns count of all messages in a folder
     *
     * @param string $mailbox Mailbox name
     *
     * @return int Number of messages, False on error
     */
    public function countMessages($mailbox)
    {
        if ($this->selected === $mailbox && isset($this->data['EXISTS'])) {
            return $this->data['EXISTS'];
        }

        // Check internal cache
        $cache = $this->data['STATUS:'.$mailbox];
        if (!empty($cache) && isset($cache['MESSAGES'])) {
            return (int) $cache['MESSAGES'];
        }

        // Try STATUS (should be faster than SELECT)
        $counts = $this->status($mailbox);
        if (is_array($counts)) {
            return (int) $counts['MESSAGES'];
        }

        return false;
    }

    /**
     * Returns count of messages with \Recent flag in a folder
     *
     * @param string $mailbox Mailbox name
     *
     * @return int Number of messages, False on error
     */
    public function countRecent($mailbox)
    {
        if ($this->selected === $mailbox && isset($this->data['RECENT'])) {
            return $this->data['RECENT'];
        }

        // Check internal cache
        $cache = $this->data['STATUS:'.$mailbox];
        if (!empty($cache) && isset($cache['RECENT'])) {
            return (int) $cache['RECENT'];
        }

        // Try STATUS (should be faster than SELECT)
        $counts = $this->status($mailbox, array('RECENT'));
        if (is_array($counts)) {
            return (int) $counts['RECENT'];
        }

        return false;
    }

    /**
     * Returns count of messages without \Seen flag in a specified folder
     *
     * @param string $mailbox Mailbox name
     *
     * @return int Number of messages, False on error
     */
    public function countUnseen($mailbox)
    {
        // Check internal cache
        $cache = $this->data['STATUS:'.$mailbox];
        if (!empty($cache) && isset($cache['UNSEEN'])) {
            return (int) $cache['UNSEEN'];
        }

        // Try STATUS (should be faster than SELECT+SEARCH)
        $counts = $this->status($mailbox);
        if (is_array($counts)) {
            return (int) $counts['UNSEEN'];
        }

        // Invoke SEARCH as a fallback
        $index = $this->search($mailbox, 'ALL UNSEEN', false, array('COUNT'));
        if (!$index->is_error()) {
            return $index->count();
        }

        return false;
    }

    /**
     * Executes ID command (RFC2971)
     *
     * @param array $items Client identification information key/value hash
     *
     * @return array Server identification information key/value hash
     * @since 0.6
     */
    public function id($items = array())
    {
        if (is_array($items) && !empty($items)) {
            foreach ($items as $key => $value) {
                $args[] = $this->escape($key, true);
                $args[] = $this->escape($value, true);
            }
        }

        list($code, $response) = $this->execute('ID', array(
            !empty($args) ? '(' . implode(' ', (array) $args) . ')' : $this->escape(null)
        ));

        if ($code == self::ERROR_OK && preg_match('/^\* ID /i', $response)) {
            $response = substr($response, 5); // remove prefix "* ID "
            $items    = $this->tokenizeResponse($response, 1);
            $result   = null;

            for ($i=0, $len=count($items); $i<$len; $i += 2) {
                $result[$items[$i]] = $items[$i+1];
            }

            return $result;
        }

        return false;
    }

    /**
     * Executes ENABLE command (RFC5161)
     *
     * @param mixed $extension Extension name to enable (or array of names)
     *
     * @return array|bool List of enabled extensions, False on error
     * @since 0.6
     */
    public function enable($extension)
    {
        if (empty($extension)) {
            return false;
        }

        if (!$this->hasCapability('ENABLE')) {
            return false;
        }

        if (!is_array($extension)) {
            $extension = array($extension);
        }

        if (!empty($this->extensions_enabled)) {
            // check if all extensions are already enabled
            $diff = array_diff($extension, $this->extensions_enabled);

            if (empty($diff)) {
                return $extension;
            }

            // Make sure the mailbox isn't selected, before enabling extension(s)
            if ($this->selected !== null) {
                $this->close();
            }
        }

        list($code, $response) = $this->execute('ENABLE', $extension);

        if ($code == self::ERROR_OK && preg_match('/^\* ENABLED /i', $response)) {
            $response = substr($response, 10); // remove prefix "* ENABLED "
            $result   = (array) $this->tokenizeResponse($response);

            $this->extensions_enabled = array_unique(array_merge((array)$this->extensions_enabled, $result));

            return $this->extensions_enabled;
        }

        return false;
    }

    /**
     * Executes SORT command
     *
     * @param string $mailbox    Mailbox name
     * @param string $field      Field to sort by (ARRIVAL, CC, DATE, FROM, SIZE, SUBJECT, TO)
     * @param string $criteria   Searching criteria
     * @param bool   $return_uid Enables UID SORT usage
     * @param string $encoding   Character set
     *
     * @return rcube_result_index Response data
     */
    public function sort($mailbox, $field = 'ARRIVAL', $criteria = '', $return_uid = false, $encoding = 'US-ASCII')
    {
        $old_sel   = $this->selected;
        $supported = array('ARRIVAL', 'CC', 'DATE', 'FROM', 'SIZE', 'SUBJECT', 'TO');
        $field     = strtoupper($field);

        if ($field == 'INTERNALDATE') {
            $field = 'ARRIVAL';
        }

        if (!in_array($field, $supported)) {
            return new rcube_result_index($mailbox);
        }

        if (!$this->select($mailbox)) {
            return new rcube_result_index($mailbox);
        }

        // return empty result when folder is empty and we're just after SELECT
        if ($old_sel != $mailbox && !$this->data['EXISTS']) {
            return new rcube_result_index($mailbox, '* SORT');
        }

        // RFC 5957: SORT=DISPLAY
        if (($field == 'FROM' || $field == 'TO') && $this->getCapability('SORT=DISPLAY')) {
            $field = 'DISPLAY' . $field;
        }

        $encoding = $encoding ? trim($encoding) : 'US-ASCII';
        $criteria = $criteria ? 'ALL ' . trim($criteria) : 'ALL';

        list($code, $response) = $this->execute($return_uid ? 'UID SORT' : 'SORT',
            array("($field)", $encoding, $criteria));

        if ($code != self::ERROR_OK) {
            $response = null;
        }

        return new rcube_result_index($mailbox, $response);
    }

    /**
     * Executes THREAD command
     *
     * @param string $mailbox    Mailbox name
     * @param string $algorithm  Threading algorithm (ORDEREDSUBJECT, REFERENCES, REFS)
     * @param string $criteria   Searching criteria
     * @param bool   $return_uid Enables UIDs in result instead of sequence numbers
     * @param string $encoding   Character set
     *
     * @return rcube_result_thread Thread data
     */
    public function thread($mailbox, $algorithm = 'REFERENCES', $criteria = '', $return_uid = false, $encoding = 'US-ASCII')
    {
        $old_sel = $this->selected;

        if (!$this->select($mailbox)) {
            return new rcube_result_thread($mailbox);
        }

        // return empty result when folder is empty and we're just after SELECT
        if ($old_sel != $mailbox && !$this->data['EXISTS']) {
            return new rcube_result_thread($mailbox, '* THREAD');
        }

        $encoding  = $encoding ? trim($encoding) : 'US-ASCII';
        $algorithm = $algorithm ? trim($algorithm) : 'REFERENCES';
        $criteria  = $criteria ? 'ALL '.trim($criteria) : 'ALL';

        list($code, $response) = $this->execute($return_uid ? 'UID THREAD' : 'THREAD',
            array($algorithm, $encoding, $criteria));

        if ($code != self::ERROR_OK) {
            $response = null;
        }

        return new rcube_result_thread($mailbox, $response);
    }

    /**
     * Executes SEARCH command
     *
     * @param string $mailbox    Mailbox name
     * @param string $criteria   Searching criteria
     * @param bool   $return_uid Enable UID in result instead of sequence ID
     * @param array  $items      Return items (MIN, MAX, COUNT, ALL)
     *
     * @return rcube_result_index Result data
     */
    public function search($mailbox, $criteria, $return_uid = false, $items = array())
    {
        $old_sel = $this->selected;

        if (!$this->select($mailbox)) {
            return new rcube_result_index($mailbox);
        }

        // return empty result when folder is empty and we're just after SELECT
        if ($old_sel != $mailbox && !$this->data['EXISTS']) {
            return new rcube_result_index($mailbox, '* SEARCH');
        }

        // If ESEARCH is supported always use ALL
        // but not when items are specified or using simple id2uid search
        if (empty($items) && preg_match('/[^0-9]/', $criteria)) {
            $items = array('ALL');
        }

        $esearch  = empty($items) ? false : $this->getCapability('ESEARCH');
        $criteria = trim($criteria);
        $params   = '';

        // RFC4731: ESEARCH
        if (!empty($items) && $esearch) {
            $params .= 'RETURN (' . implode(' ', $items) . ')';
        }

        if (!empty($criteria)) {
            $params .= ($params ? ' ' : '') . $criteria;
        }
        else {
            $params .= 'ALL';
        }

        list($code, $response) = $this->execute($return_uid ? 'UID SEARCH' : 'SEARCH',
            array($params));

        if ($code != self::ERROR_OK) {
            $response = null;
        }

        return new rcube_result_index($mailbox, $response);
    }

    /**
     * Simulates SORT command by using FETCH and sorting.
     *
     * @param string       $mailbox      Mailbox name
     * @param string|array $message_set  Searching criteria (list of messages to return)
     * @param string       $index_field  Field to sort by (ARRIVAL, CC, DATE, FROM, SIZE, SUBJECT, TO)
     * @param bool         $skip_deleted Makes that DELETED messages will be skipped
     * @param bool         $uidfetch     Enables UID FETCH usage
     * @param bool         $return_uid   Enables returning UIDs instead of IDs
     *
     * @return rcube_result_index Response data
     */
    public function index($mailbox, $message_set, $index_field='', $skip_deleted=true,
        $uidfetch=false, $return_uid=false)
    {
        $msg_index = $this->fetchHeaderIndex($mailbox, $message_set,
            $index_field, $skip_deleted, $uidfetch, $return_uid);

        if (!empty($msg_index)) {
            asort($msg_index); // ASC
            $msg_index = array_keys($msg_index);
            $msg_index = '* SEARCH ' . implode(' ', $msg_index);
        }
        else {
            $msg_index = is_array($msg_index) ? '* SEARCH' : null;
        }

        return new rcube_result_index($mailbox, $msg_index);
    }

    /**
     * Fetches specified header/data value for a set of messages.
     *
     * @param string       $mailbox      Mailbox name
     * @param string|array $message_set  Searching criteria (list of messages to return)
     * @param string       $index_field  Field to sort by (ARRIVAL, CC, DATE, FROM, SIZE, SUBJECT, TO)
     * @param bool         $skip_deleted Makes that DELETED messages will be skipped
     * @param bool         $uidfetch     Enables UID FETCH usage
     * @param bool         $return_uid   Enables returning UIDs instead of IDs
     *
     * @return array|bool List of header values or False on failure
     */
    public function fetchHeaderIndex($mailbox, $message_set, $index_field = '', $skip_deleted = true,
        $uidfetch = false, $return_uid = false)
    {
        if (is_array($message_set)) {
            if (!($message_set = $this->compressMessageSet($message_set))) {
                return false;
            }
        }
        else {
            list($from_idx, $to_idx) = explode(':', $message_set);
            if (empty($message_set) ||
                (isset($to_idx) && $to_idx != '*' && (int)$from_idx > (int)$to_idx)
            ) {
                return false;
            }
        }

        $index_field = empty($index_field) ? 'DATE' : strtoupper($index_field);

        $fields_a['DATE']         = 1;
        $fields_a['INTERNALDATE'] = 4;
        $fields_a['ARRIVAL']      = 4;
        $fields_a['FROM']         = 1;
        $fields_a['REPLY-TO']     = 1;
        $fields_a['SENDER']       = 1;
        $fields_a['TO']           = 1;
        $fields_a['CC']           = 1;
        $fields_a['SUBJECT']      = 1;
        $fields_a['UID']          = 2;
        $fields_a['SIZE']         = 2;
        $fields_a['SEEN']         = 3;
        $fields_a['RECENT']       = 3;
        $fields_a['DELETED']      = 3;

        if (!($mode = $fields_a[$index_field])) {
            return false;
        }

        //  Select the mailbox
        if (!$this->select($mailbox)) {
            return false;
        }

        // build FETCH command string
        $key    = $this->nextTag();
        $cmd    = $uidfetch ? 'UID FETCH' : 'FETCH';
        $fields = array();

        if ($return_uid) {
            $fields[] = 'UID';
        }
        if ($skip_deleted) {
            $fields[] = 'FLAGS';
        }

        if ($mode == 1) {
            if ($index_field == 'DATE') {
                $fields[] = 'INTERNALDATE';
            }
            $fields[] = "BODY.PEEK[HEADER.FIELDS ($index_field)]";
        }
        else if ($mode == 2) {
            if ($index_field == 'SIZE') {
                $fields[] = 'RFC822.SIZE';
            }
            else if (!$return_uid || $index_field != 'UID') {
                $fields[] = $index_field;
            }
        }
        else if ($mode == 3 && !$skip_deleted) {
            $fields[] = 'FLAGS';
        }
        else if ($mode == 4) {
            $fields[] = 'INTERNALDATE';
        }

        $request = "$key $cmd $message_set (" . implode(' ', $fields) . ")";

        if (!$this->putLine($request)) {
            $this->setError(self::ERROR_COMMAND, "Failed to send $cmd command");
            return false;
        }

        $result = array();

        do {
            $line = rtrim($this->readLine(200));
            $line = $this->multLine($line);

            if (preg_match('/^\* ([0-9]+) FETCH/', $line, $m)) {
                $id     = $m[1];
                $flags  = null;

                if ($return_uid) {
                    if (preg_match('/UID ([0-9]+)/', $line, $matches)) {
                        $id = (int) $matches[1];
                    }
                    else {
                        continue;
                    }
                }
                if ($skip_deleted && preg_match('/FLAGS \(([^)]+)\)/', $line, $matches)) {
                    $flags = explode(' ', strtoupper($matches[1]));
                    if (in_array('\\DELETED', $flags)) {
                        continue;
                    }
                }

                if ($mode == 1 && $index_field == 'DATE') {
                    if (preg_match('/BODY\[HEADER\.FIELDS \("*DATE"*\)\] (.*)/', $line, $matches)) {
                        $value = preg_replace(array('/^"*[a-z]+:/i'), '', $matches[1]);
                        $value = trim($value);
                        $result[$id] = rcube_utils::strtotime($value);
                    }
                    // non-existent/empty Date: header, use INTERNALDATE
                    if (empty($result[$id])) {
                        if (preg_match('/INTERNALDATE "([^"]+)"/', $line, $matches)) {
                            $result[$id] = rcube_utils::strtotime($matches[1]);
                        }
                        else {
                            $result[$id] = 0;
                        }
                    }
                }
                else if ($mode == 1) {
                    if (preg_match('/BODY\[HEADER\.FIELDS \("?(FROM|REPLY-TO|SENDER|TO|SUBJECT)"?\)\] (.*)/', $line, $matches)) {
                        $value = preg_replace(array('/^"*[a-z]+:/i', '/\s+$/sm'), array('', ''), $matches[2]);
                        $result[$id] = trim($value);
                    }
                    else {
                        $result[$id] = '';
                    }
                }
                else if ($mode == 2) {
                    if (preg_match('/' . $index_field . ' ([0-9]+)/', $line, $matches)) {
                        $result[$id] = trim($matches[1]);
                    }
                    else {
                        $result[$id] = 0;
                    }
                }
                else if ($mode == 3) {
                    if (!$flags && preg_match('/FLAGS \(([^)]+)\)/', $line, $matches)) {
                        $flags = explode(' ', $matches[1]);
                    }
                    $result[$id] = in_array("\\".$index_field, (array) $flags) ? 1 : 0;
                }
                else if ($mode == 4) {
                    if (preg_match('/INTERNALDATE "([^"]+)"/', $line, $matches)) {
                        $result[$id] = rcube_utils::strtotime($matches[1]);
                    }
                    else {
                        $result[$id] = 0;
                    }
                }
            }
        }
        while (!$this->startsWith($line, $key, true, true));

        return $result;
    }

    /**
     * Returns message sequence identifier
     *
     * @param string $mailbox Mailbox name
     * @param int    $uid     Message unique identifier (UID)
     *
     * @return int Message sequence identifier
     */
    public function UID2ID($mailbox, $uid)
    {
        if ($uid > 0) {
            $index = $this->search($mailbox, "UID $uid");

            if ($index->count() == 1) {
                $arr = $index->get();
                return (int) $arr[0];
            }
        }
    }

    /**
     * Returns message unique identifier (UID)
     *
     * @param string $mailbox Mailbox name
     * @param int    $uid     Message sequence identifier
     *
     * @return int Message unique identifier
     */
    public function ID2UID($mailbox, $id)
    {
        if (empty($id) || $id < 0) {
            return null;
        }

        if (!$this->select($mailbox)) {
            return null;
        }

        if ($uid = $this->data['UID-MAP'][$id]) {
            return $uid;
        }

        if (isset($this->data['EXISTS']) && $id > $this->data['EXISTS']) {
            return null;
        }

        $index = $this->search($mailbox, $id, true);

        if ($index->count() == 1) {
            $arr = $index->get();
            return $this->data['UID-MAP'][$id] = (int) $arr[0];
        }
    }

    /**
     * Sets flag of the message(s)
     *
     * @param string       $mailbox  Mailbox name
     * @param string|array $messages Message UID(s)
     * @param string       $flag     Flag name
     *
     * @return bool True on success, False on failure
     */
    public function flag($mailbox, $messages, $flag)
    {
        return $this->modFlag($mailbox, $messages, $flag, '+');
    }

    /**
     * Unsets flag of the message(s)
     *
     * @param string       $mailbox  Mailbox name
     * @param string|array $messages Message UID(s)
     * @param string       $flag     Flag name
     *
     * @return bool True on success, False on failure
     */
    public function unflag($mailbox, $messages, $flag)
    {
        return $this->modFlag($mailbox, $messages, $flag, '-');
    }

    /**
     * Changes flag of the message(s)
     *
     * @param string       $mailbox  Mailbox name
     * @param string|array $messages Message UID(s)
     * @param string       $flag     Flag name
     * @param string       $mod      Modifier [+|-]. Default: "+".
     *
     * @return bool True on success, False on failure
     */
    protected function modFlag($mailbox, $messages, $flag, $mod = '+')
    {
        if (!$flag) {
            return false;
        }

        if (!$this->select($mailbox)) {
            return false;
        }

        if (!$this->data['READ-WRITE']) {
            $this->setError(self::ERROR_READONLY, "Mailbox is read-only");
            return false;
        }

        if ($this->flags[strtoupper($flag)]) {
            $flag = $this->flags[strtoupper($flag)];
        }

        // if PERMANENTFLAGS is not specified all flags are allowed
        if (!empty($this->data['PERMANENTFLAGS'])
            && !in_array($flag, (array) $this->data['PERMANENTFLAGS'])
            && !in_array('\\*', (array) $this->data['PERMANENTFLAGS'])
        ) {
            return false;
        }

        // Clear internal status cache
        if ($flag == 'SEEN') {
            unset($this->data['STATUS:'.$mailbox]['UNSEEN']);
        }

        if ($mod != '+' && $mod != '-') {
            $mod = '+';
        }

        $result = $this->execute('UID STORE', array(
            $this->compressMessageSet($messages), $mod . 'FLAGS.SILENT', "($flag)"),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Copies message(s) from one folder to another
     *
     * @param string|array $messages Message UID(s)
     * @param string       $from     Mailbox name
     * @param string       $to       Destination mailbox name
     *
     * @return bool True on success, False on failure
     */
    public function copy($messages, $from, $to)
    {
        // Clear last COPYUID data
        unset($this->data['COPYUID']);

        if (!$this->select($from)) {
            return false;
        }

        // Clear internal status cache
        unset($this->data['STATUS:'.$to]);

        $result = $this->execute('UID COPY', array(
            $this->compressMessageSet($messages), $this->escape($to)),
            self::COMMAND_NORESPONSE);

        return $result == self::ERROR_OK;
    }

    /**
     * Moves message(s) from one folder to another.
     *
     * @param string|array $messages Message UID(s)
     * @param string       $from     Mailbox name
     * @param string       $to       Destination mailbox name
     *
     * @return bool True on success, False on failure
     */
    public function move($messages, $from, $to)
    {
        if (!$this->select($from)) {
            return false;
        }

        if (!$this->data['READ-WRITE']) {
            $this->setError(self::ERROR_READONLY, "Mailbox is read-only");
            return false;
        }

        // use MOVE command (RFC 6851)
        if ($this->hasCapability('MOVE')) {
            // Clear last COPYUID data
            unset($this->data['COPYUID']);

            // Clear internal status cache
            unset($this->data['STATUS:'.$to]);
            $this->clear_status_cache($from);

            $result = $this->execute('UID MOVE', array(
                $this->compressMessageSet($messages), $this->escape($to)),
                self::COMMAND_NORESPONSE);

            return $result == self::ERROR_OK;
        }

        // use COPY + STORE +FLAGS.SILENT \Deleted + EXPUNGE
        $result = $this->copy($messages, $from, $to);

        if ($result) {
            // Clear internal status cache
            unset($this->data['STATUS:'.$from]);

            $result = $this->flag($from, $messages, 'DELETED');

            if ($messages == '*') {
                // CLOSE+SELECT should be faster than EXPUNGE
                $this->close();
            }
            else {
                $this->expunge($from, $messages);
            }
        }

        return $result;
    }

    /**
     * FETCH command (RFC3501)
     *
     * @param string $mailbox     Mailbox name
     * @param mixed  $message_set Message(s) sequence identifier(s) or UID(s)
     * @param bool   $is_uid      True if $message_set contains UIDs
     * @param array  $query_items FETCH command data items
     * @param string $mod_seq     Modification sequence for CHANGEDSINCE (RFC4551) query
     * @param bool   $vanished    Enables VANISHED parameter (RFC5162) for CHANGEDSINCE query
     *
     * @return array List of rcube_message_header elements, False on error
     * @since 0.6
     */
    public function fetch($mailbox, $message_set, $is_uid = false, $query_items = array(),
        $mod_seq = null, $vanished = false)
    {
        if (!$this->select($mailbox)) {
            return false;
        }

        $message_set = $this->compressMessageSet($message_set);
        $result      = array();

        $key      = $this->nextTag();
        $cmd      = ($is_uid ? 'UID ' : '') . 'FETCH';
        $request  = "$key $cmd $message_set (" . implode(' ', $query_items) . ")";

        if ($mod_seq !== null && $this->hasCapability('CONDSTORE')) {
            $request .= " (CHANGEDSINCE $mod_seq" . ($vanished ? " VANISHED" : '') .")";
        }

        if (!$this->putLine($request)) {
            $this->setError(self::ERROR_COMMAND, "Failed to send $cmd command");
            return false;
        }

        do {
            $line = $this->readLine(4096);

            if (!$line) {
                break;
            }

            // Sample reply line:
            // * 321 FETCH (UID 2417 RFC822.SIZE 2730 FLAGS (\Seen)
            // INTERNALDATE "16-Nov-2008 21:08:46 +0100" BODYSTRUCTURE (...)
            // BODY[HEADER.FIELDS ...

            if (preg_match('/^\* ([0-9]+) FETCH/', $line, $m)) {
                $id = intval($m[1]);

                $result[$id]            = new rcube_message_header;
                $result[$id]->id        = $id;
                $result[$id]->subject   = '';
                $result[$id]->messageID = 'mid:' . $id;

                $headers = null;
                $lines   = array();
                $line    = substr($line, strlen($m[0]) + 2);
                $ln      = 0;

                // get complete entry
                while (preg_match('/\{([0-9]+)\}\r\n$/', $line, $m)) {
                    $bytes = $m[1];
                    $out   = '';

                    while (strlen($out) < $bytes) {
                        $out = $this->readBytes($bytes);
                        if ($out === null) {
                            break;
                        }
                        $line .= $out;
                    }

                    $str = $this->readLine(4096);
                    if ($str === false) {
                        break;
                    }

                    $line .= $str;
                }

                // Tokenize response and assign to object properties
                while (list($name, $value) = $this->tokenizeResponse($line, 2)) {
                    if ($name == 'UID') {
                        $result[$id]->uid = intval($value);
                    }
                    else if ($name == 'RFC822.SIZE') {
                        $result[$id]->size = intval($value);
                    }
                    else if ($name == 'RFC822.TEXT') {
                        $result[$id]->body = $value;
                    }
                    else if ($name == 'INTERNALDATE') {
                        $result[$id]->internaldate = $value;
                        $result[$id]->date         = $value;
                        $result[$id]->timestamp    = rcube_utils::strtotime($value);
                    }
                    else if ($name == 'FLAGS') {
                        if (!empty($value)) {
                            foreach ((array)$value as $flag) {
                                $flag = str_replace(array('$', "\\"), '', $flag);
                                $flag = strtoupper($flag);

                                $result[$id]->flags[$flag] = true;
                            }
                        }
                    }
                    else if ($name == 'MODSEQ') {
                        $result[$id]->modseq = $value[0];
                    }
                    else if ($name == 'ENVELOPE') {
                        $result[$id]->envelope = $value;
                    }
                    else if ($name == 'BODYSTRUCTURE' || ($name == 'BODY' && count($value) > 2)) {
                        if (!is_array($value[0]) && (strtolower($value[0]) == 'message' && strtolower($value[1]) == 'rfc822')) {
                            $value = array($value);
                        }
                        $result[$id]->bodystructure = $value;
                    }
                    else if ($name == 'RFC822') {
                        $result[$id]->body = $value;
                    }
                    else if (stripos($name, 'BODY[') === 0) {
                        $name = str_replace(']', '', substr($name, 5));

                        if ($name == 'HEADER.FIELDS') {
                            // skip ']' after headers list
                            $this->tokenizeResponse($line, 1);
                            $headers = $this->tokenizeResponse($line, 1);
                        }
                        else if (strlen($name)) {
                            $result[$id]->bodypart[$name] = $value;
                        }
                        else {
                            $result[$id]->body = $value;
                        }
                    }
                }

                // create array with header field:data
                if (!empty($headers)) {
                    $headers = explode("\n", trim($headers));
                    foreach ($headers as $resln) {
                        if (ord($resln[0]) <= 32) {
                            $lines[$ln] .= (empty($lines[$ln]) ? '' : "\n") . trim($resln);
                        }
                        else {
                            $lines[++$ln] = trim($resln);
                        }
                    }

                    foreach ($lines as $str) {
                        list($field, $string) = explode(':', $str, 2);

                        $field  = strtolower($field);
                        $string = preg_replace('/\n[\t\s]*/', ' ', trim($string));

                        switch ($field) {
                        case 'date';
                            $result[$id]->date = $string;
                            $result[$id]->timestamp = rcube_utils::strtotime($string);
                            break;
                        case 'to':
                            $result[$id]->to = preg_replace('/undisclosed-recipients:[;,]*/', '', $string);
                            break;
                        case 'from':
                        case 'subject':
                        case 'cc':
                        case 'bcc':
                        case 'references':
                            $result[$id]->{$field} = $string;
                            break;
                        case 'reply-to':
                            $result[$id]->replyto = $string;
                            break;
                        case 'content-transfer-encoding':
                            $result[$id]->encoding = $string;
                        break;
                        case 'content-type':
                            $ctype_parts = preg_split('/[; ]+/', $string);
                            $result[$id]->ctype = strtolower(array_shift($ctype_parts));
                            if (preg_match('/charset\s*=\s*"?([a-z0-9\-\.\_]+)"?/i', $string, $regs)) {
                                $result[$id]->charset = $regs[1];
                            }
                            break;
                        case 'in-reply-to':
                            $result[$id]->in_reply_to = str_replace(array("\n", '<', '>'), '', $string);
                            break;
                        case 'return-receipt-to':
                        case 'disposition-notification-to':
                        case 'x-confirm-reading-to':
                            $result[$id]->mdn_to = $string;
                            break;
                        case 'message-id':
                            $result[$id]->messageID = $string;
                            break;
                        case 'x-priority':
                            if (preg_match('/^(\d+)/', $string, $matches)) {
                                $result[$id]->priority = intval($matches[1]);
                            }
                            break;
                        default:
                            if (strlen($field) < 3) {
                                break;
                            }
                            if ($result[$id]->others[$field]) {
                                $string = array_merge((array)$result[$id]->others[$field], (array)$string);
                            }
                            $result[$id]->others[$field] = $string;
                        }
                    }
                }
            }
            // VANISHED response (QRESYNC RFC5162)
            // Sample: * VANISHED (EARLIER) 300:310,405,411
            else if (preg_match('/^\* VANISHED [()EARLIER]*/i', $line, $match)) {
                $line   = substr($line, strlen($match[0]));
                $v_data = $this->tokenizeResponse($line, 1);

                $this->data['VANISHED'] = $v_data;
            }
        }
        while (!$this->startsWith($line, $key, true));

        return $result;
    }

    /**
     * Returns message(s) data (flags, headers, etc.)
     *
     * @param string $mailbox     Mailbox name
     * @param mixed  $message_set Message(s) sequence identifier(s) or UID(s)
     * @param bool   $is_uid      True if $message_set contains UIDs
     * @param bool   $bodystr     Enable to add BODYSTRUCTURE data to the result
     * @param array  $add_headers List of additional headers
     *
     * @return bool|array List of rcube_message_header elements, False on error
     */
    public function fetchHeaders($mailbox, $message_set, $is_uid = false, $bodystr = false, $add_headers = array())
    {
        $query_items = array('UID', 'RFC822.SIZE', 'FLAGS', 'INTERNALDATE');
        $headers     = array('DATE', 'FROM', 'TO', 'SUBJECT', 'CONTENT-TYPE', 'CC', 'REPLY-TO',
            'LIST-POST', 'DISPOSITION-NOTIFICATION-TO', 'X-PRIORITY');

        if (!empty($add_headers)) {
            $add_headers = array_map('strtoupper', $add_headers);
            $headers     = array_unique(array_merge($headers, $add_headers));
        }

        if ($bodystr) {
            $query_items[] = 'BODYSTRUCTURE';
        }

        $query_items[] = 'BODY.PEEK[HEADER.FIELDS (' . implode(' ', $headers) . ')]';

        return $this->fetch($mailbox, $message_set, $is_uid, $query_items);
    }

    /**
     * Returns message data (flags, headers, etc.)
     *
     * @param string $mailbox     Mailbox name
     * @param int    $id          Message sequence identifier or UID
     * @param bool   $is_uid      True if $id is an UID
     * @param bool   $bodystr     Enable to add BODYSTRUCTURE data to the result
     * @param array  $add_headers List of additional headers
     *
     * @return bool|rcube_message_header Message data, False on error
     */
    public function fetchHeader($mailbox, $id, $is_uid = false, $bodystr = false, $add_headers = array())
    {
        $a = $this->fetchHeaders($mailbox, $id, $is_uid, $bodystr, $add_headers);
        if (is_array($a)) {
            return array_shift($a);
        }

        return false;
    }

    /**
     * Sort messages by specified header field
     *
     * @param array  $messages Array of rcube_message_header objects
     * @param string $field    Name of the property to sort by
     * @param string $flag     Sorting order (ASC|DESC)
     *
     * @return array Sorted input array
     */
    public static function sortHeaders($messages, $field, $flag)
    {
        // Strategy: First, we'll create an "index" array.
        // Then, we'll use sort() on that array, and use that to sort the main array.

        $field  = empty($field) ? 'uid' : strtolower($field);
        $flag   = empty($flag) ? 'ASC' : strtoupper($flag);
        $index  = array();
        $result = array();

        reset($messages);

        while (list($key, $headers) = each($messages)) {
            $value = null;

            switch ($field) {
            case 'arrival':
                $field = 'internaldate';
            case 'date':
            case 'internaldate':
            case 'timestamp':
                $value = rcube_utils::strtotime($headers->$field);
                if (!$value && $field != 'timestamp') {
                    $value = $headers->timestamp;
                }

                break;

            default:
                // @TODO: decode header value, convert to UTF-8
                $value = $headers->$field;
                if (is_string($value)) {
                    $value = str_replace('"', '', $value);
                    if ($field == 'subject') {
                        $value = preg_replace('/^(Re:\s*|Fwd:\s*|Fw:\s*)+/i', '', $value);
                    }

                    $data = strtoupper($value);
                }
            }

            $index[$key] = $value;
        }

        if (!empty($index)) {
            // sort index
            if ($flag == 'ASC') {
                asort($index);
            }
            else {
                arsort($index);
            }

            // form new array based on index
            while (list($key, $val) = each($index)) {
                $result[$key] = $messages[$key];
            }
        }

        return $result;
    }

    /**
     * Fetch MIME headers of specified message parts
     *
     * @param string $mailbox Mailbox name
     * @param int    $uid     Message UID
     * @param array  $parts   Message part identifiers
     * @param bool   $mime    Use MIME instad of HEADER
     *
     * @return array|bool Array containing headers string for each specified body
     *                    False on failure.
     */
    public function fetchMIMEHeaders($mailbox, $uid, $parts, $mime = true)
    {
        if (!$this->select($mailbox)) {
            return false;
        }

        $result = false;
        $parts  = (array) $parts;
        $key    = $this->nextTag();
        $peeks  = array();
        $type   = $mime ? 'MIME' : 'HEADER';

        // format request
        foreach ($parts as $part) {
            $peeks[] = "BODY.PEEK[$part.$type]";
        }

        $request = "$key UID FETCH $uid (" . implode(' ', $peeks) . ')';

        // send request
        if (!$this->putLine($request)) {
            $this->setError(self::ERROR_COMMAND, "Failed to send UID FETCH command");
            return false;
        }

        do {
            $line = $this->readLine(1024);

            if (preg_match('/^\* [0-9]+ FETCH [0-9UID( ]+/', $line, $m)) {
                $line = ltrim(substr($line, strlen($m[0])));
                while (preg_match('/^BODY\[([0-9\.]+)\.'.$type.'\]/', $line, $matches)) {
                    $line = substr($line, strlen($matches[0]));
                    $result[$matches[1]] = trim($this->multLine($line));
                    $line = $this->readLine(1024);
                }
            }
        }
        while (!$this->startsWith($line, $key, true));

        return $result;
    }

    /**
     * Fetches message part header
     */
    public function fetchPartHeader($mailbox, $id, $is_uid = false, $part = null)
    {
        $part = empty($part) ? 'HEADER' : $part.'.MIME';

        return $this->handlePartBody($mailbox, $id, $is_uid, $part);
    }

    /**
     * Fetches body of the specified message part
     */
    public function handlePartBody($mailbox, $id, $is_uid=false, $part='', $encoding=null, $print=null, $file=null, $formatted=false, $max_bytes=0)
    {
        if (!$this->select($mailbox)) {
            return false;
        }

        $binary = true;

        do {
            if (!$initiated) {
                switch ($encoding) {
                case 'base64':
                    $mode = 1;
                    break;
                case 'quoted-printable':
                    $mode = 2;
                    break;
                case 'x-uuencode':
                case 'x-uue':
                case 'uue':
                case 'uuencode':
                    $mode = 3;
                    break;
                default:
                    $mode = 0;
                }

                // Use BINARY extension when possible (and safe)
                $binary     = $binary && $mode && preg_match('/^[0-9.]+$/', $part) && $this->hasCapability('BINARY');
                $fetch_mode = $binary ? 'BINARY' : 'BODY';
                $partial    = $max_bytes ? sprintf('<0.%d>', $max_bytes) : '';

                // format request
                $key       = $this->nextTag();
                $cmd       = ($is_uid ? 'UID ' : '') . 'FETCH';
                $request   = "$key $cmd $id ($fetch_mode.PEEK[$part]$partial)";
                $result    = false;
                $found     = false;
                $initiated = true;

                // send request
                if (!$this->putLine($request)) {
                    $this->setError(self::ERROR_COMMAND, "Failed to send $cmd command");
                    return false;
                }

                if ($binary) {
                    // WARNING: Use $formatted argument with care, this may break binary data stream
                    $mode = -1;
                }
            }

            $line = trim($this->readLine(1024));

            if (!$line) {
                break;
            }

            // handle UNKNOWN-CTE response - RFC 3516, try again with standard BODY request
            if ($binary && !$found && preg_match('/^' . $key . ' NO \[UNKNOWN-CTE\]/i', $line)) {
                $binary = $initiated = false;
                continue;
            }

            // skip irrelevant untagged responses (we have a result already)
            if ($found || !preg_match('/^\* ([0-9]+) FETCH (.*)$/', $line, $m)) {
                continue;
            }

            $line = $m[2];

            // handle one line response
            if ($line[0] == '(' && substr($line, -1) == ')') {
                // tokenize content inside brackets
                // the content can be e.g.: (UID 9844 BODY[2.4] NIL)
                $tokens = $this->tokenizeResponse(preg_replace('/(^\(|\)$)/', '', $line));

                for ($i=0; $i<count($tokens); $i+=2) {
                    if (preg_match('/^(BODY|BINARY)/i', $tokens[$i])) {
                        $result = $tokens[$i+1];
                        $found  = true;
                        break;
                    }
                }

                if ($result !== false) {
                    if ($mode == 1) {
                        $result = base64_decode($result);
                    }
                    else if ($mode == 2) {
                        $result = quoted_printable_decode($result);
                    }
                    else if ($mode == 3) {
                        $result = convert_uudecode($result);
                    }
                }
            }
            // response with string literal
            else if (preg_match('/\{([0-9]+)\}$/', $line, $m)) {
                $bytes = (int) $m[1];
                $prev  = '';
                $found = true;

                // empty body
                if (!$bytes) {
                    $result = '';
                }
                else while ($bytes > 0) {
                    $line = $this->readLine(8192);

                    if ($line === null) {
                        break;
                    }

                    $len = strlen($line);

                    if ($len > $bytes) {
                        $line = substr($line, 0, $bytes);
                        $len  = strlen($line);
                    }
                    $bytes -= $len;

                    // BASE64
                    if ($mode == 1) {
                        $line = preg_replace('|[^a-zA-Z0-9+=/]|', '', $line);
                        // create chunks with proper length for base64 decoding
                        $line = $prev.$line;
                        $length = strlen($line);
                        if ($length % 4) {
                            $length = floor($length / 4) * 4;
                            $prev = substr($line, $length);
                            $line = substr($line, 0, $length);
                        }
                        else {
                            $prev = '';
                        }
                        $line = base64_decode($line);
                    }
                    // QUOTED-PRINTABLE
                    else if ($mode == 2) {
                        $line = rtrim($line, "\t\r\0\x0B");
                        $line = quoted_printable_decode($line);
                    }
                    // UUENCODE
                    else if ($mode == 3) {
                        $line = rtrim($line, "\t\r\n\0\x0B");
                        if ($line == 'end' || preg_match('/^begin\s+[0-7]+\s+.+$/', $line)) {
                            continue;
                        }
                        $line = convert_uudecode($line);
                    }
                    // default
                    else if ($formatted) {
                        $line = rtrim($line, "\t\r\n\0\x0B") . "\n";
                    }

                    if ($file) {
                        if (fwrite($file, $line) === false) {
                            break;
                        }
                    }
                    else if ($print) {
                        echo $line;
                    }
                    else {
                        $result .= $line;
                    }
                }
            }
        }
        while (!$this->startsWith($line, $key, true) || !$initiated);

        if ($result !== false) {
            if ($file) {
                return fwrite($file, $result);
            }
            else if ($print) {
                echo $result;
                return true;
            }

            return $result;
        }

        return false;
    }

    /**
     * Handler for IMAP APPEND command
     *
     * @param string       $mailbox Mailbox name
     * @param string|array $message The message source string or array (of strings and file pointers)
     * @param array        $flags   Message flags
     * @param string       $date    Message internal date
     * @param bool         $binary  Enable BINARY append (RFC3516)
     *
     * @return string|bool On success APPENDUID response (if available) or True, False on failure
     */
    public function append($mailbox, &$message, $flags = array(), $date = null, $binary = false)
    {
        unset($this->data['APPENDUID']);

        if ($mailbox === null || $mailbox === '') {
            return false;
        }

        $binary       = $binary && $this->getCapability('BINARY');
        $literal_plus = !$binary && $this->prefs['literal+'];
        $len          = 0;
        $msg          = is_array($message) ? $message : array(&$message);
        $chunk_size   = 512000;

        for ($i=0, $cnt=count($msg); $i<$cnt; $i++) {
            if (is_resource($msg[$i])) {
                $stat = fstat($msg[$i]);
                if ($stat === false) {
                    return false;
                }
                $len += $stat['size'];
            }
            else {
                if (!$binary) {
                    $msg[$i] = str_replace("\r", '', $msg[$i]);
                    $msg[$i] = str_replace("\n", "\r\n", $msg[$i]);
                }

                $len += strlen($msg[$i]);
            }
        }

        if (!$len) {
            return false;
        }

        // build APPEND command
        $key = $this->nextTag();
        $request = "$key APPEND " . $this->escape($mailbox) . ' (' . $this->flagsToStr($flags) . ')';
        if (!empty($date)) {
            $request .= ' ' . $this->escape($date);
        }
        $request .= ' ' . ($binary ? '~' : '') . '{' . $len . ($literal_plus ? '+' : '') . '}';

        // send APPEND command
        if (!$this->putLine($request)) {
            $this->setError(self::ERROR_COMMAND, "Failed to send APPEND command");
            return false;
        }

        // Do not wait when LITERAL+ is supported
        if (!$literal_plus) {
            $line = $this->readReply();

            if ($line[0] != '+') {
                $this->parseResult($line, 'APPEND: ');
                return false;
            }
        }

        foreach ($msg as $msg_part) {
            // file pointer
            if (is_resource($msg_part)) {
                rewind($msg_part);
                while (!feof($msg_part) && $this->fp) {
                    $buffer = fread($msg_part, $chunk_size);
                    $this->putLine($buffer, false);
                }
                fclose($msg_part);
            }
            // string
            else {
                $size = strlen($msg_part);

                // Break up the data by sending one chunk (up to 512k) at a time.
                // This approach reduces our peak memory usage
                for ($offset = 0; $offset < $size; $offset += $chunk_size) {
                    $chunk = substr($msg_part, $offset, $chunk_size);
                    if (!$this->putLine($chunk, false)) {
                        return false;
                    }
                }
            }
        }

        if (!$this->putLine('')) { // \r\n
            return false;
        }

        do {
            $line = $this->readLine();
        } while (!$this->startsWith($line, $key, true, true));

        // Clear internal status cache
        unset($this->data['STATUS:'.$mailbox]);

        if ($this->parseResult($line, 'APPEND: ') != self::ERROR_OK) {
            return false;
        }

        if (!empty($this->data['APPENDUID'])) {
            return $this->data['APPENDUID'];
        }

        return true;
    }

    /**
     * Handler for IMAP APPEND command.
     *
     * @param string $mailbox Mailbox name
     * @param string $path    Path to the file with message body
     * @param string $headers Message headers
     * @param array  $flags   Message flags
     * @param string $date    Message internal date
     * @param bool   $binary  Enable BINARY append (RFC3516)
     *
     * @return string|bool On success APPENDUID response (if available) or True, False on failure
     */
    public function appendFromFile($mailbox, $path, $headers=null, $flags = array(), $date = null, $binary = false)
    {
        // open message file
        if (file_exists(realpath($path))) {
            $fp = fopen($path, 'r');
        }

        if (!$fp) {
            $this->setError(self::ERROR_UNKNOWN, "Couldn't open $path for reading");
            return false;
        }

        $message = array();
        if ($headers) {
            $message[] = trim($headers, "\r\n") . "\r\n\r\n";
        }
        $message[] = $fp;

        return $this->append($mailbox, $message, $flags, $date, $binary);
    }

    /**
     * Returns QUOTA information
     *
     * @param string $mailbox Mailbox name
     *
     * @return array Quota information
     */
    public function getQuota($mailbox = null)
    {
        if ($mailbox === null || $mailbox === '') {
            $mailbox = 'INBOX';
        }

        // a0001 GETQUOTAROOT INBOX
        // * QUOTAROOT INBOX user/sample
        // * QUOTA user/sample (STORAGE 654 9765)
        // a0001 OK Completed

        list($code, $response) = $this->execute('GETQUOTAROOT', array($this->escape($mailbox)));

        $result   = false;
        $min_free = PHP_INT_MAX;
        $all      = array();

        if ($code == self::ERROR_OK) {
            foreach (explode("\n", $response) as $line) {
                if (preg_match('/^\* QUOTA /', $line)) {
                    list(, , $quota_root) = $this->tokenizeResponse($line, 3);

                    while ($line) {
                        list($type, $used, $total) = $this->tokenizeResponse($line, 1);
                        $type = strtolower($type);

                        if ($type && $total) {
                            $all[$quota_root][$type]['used']  = intval($used);
                            $all[$quota_root][$type]['total'] = intval($total);
                        }
                    }

                    if (empty($all[$quota_root]['storage'])) {
                        continue;
                    }

                    $used  = $all[$quota_root]['storage']['used'];
                    $total = $all[$quota_root]['storage']['total'];
                    $free  = $total - $used;

                    // calculate lowest available space from all storage quotas
                    if ($free < $min_free) {
                        $min_free          = $free;
                        $result['used']    = $used;
                        $result['total']   = $total;
                        $result['percent'] = min(100, round(($used/max(1,$total))*100));
                        $result['free']    = 100 - $result['percent'];
                    }
                }
            }
        }

        if (!empty($result)) {
            $result['all'] = $all;
        }

        return $result;
    }

    /**
     * Send the SETACL command (RFC4314)
     *
     * @param string $mailbox Mailbox name
     * @param string $user    User name
     * @param mixed  $acl     ACL string or array
     *
     * @return boolean True on success, False on failure
     *
     * @since 0.5-beta
     */
    public function setACL($mailbox, $user, $acl)
    {
        if (is_array($acl)) {
            $acl = implode('', $acl);
        }

        $result = $this->execute('SETACL', array(
            $this->escape($mailbox), $this->escape($user), strtolower($acl)),
            self::COMMAND_NORESPONSE);

        return ($result == self::ERROR_OK);
    }

    /**
     * Send the DELETEACL command (RFC4314)
     *
     * @param string $mailbox Mailbox name
     * @param string $user    User name
     *
     * @return boolean True on success, False on failure
     *
     * @since 0.5-beta
     */
    public function deleteACL($mailbox, $user)
    {
        $result = $this->execute('DELETEACL', array(
            $this->escape($mailbox), $this->escape($user)),
            self::COMMAND_NORESPONSE);

        return ($result == self::ERROR_OK);
    }

    /**
     * Send the GETACL command (RFC4314)
     *
     * @param string $mailbox Mailbox name
     *
     * @return array User-rights array on success, NULL on error
     * @since 0.5-beta
     */
    public function getACL($mailbox)
    {
        list($code, $response) = $this->execute('GETACL', array($this->escape($mailbox)));

        if ($code == self::ERROR_OK && preg_match('/^\* ACL /i', $response)) {
            // Parse server response (remove "* ACL ")
            $response = substr($response, 6);
            $ret  = $this->tokenizeResponse($response);
            $mbox = array_shift($ret);
            $size = count($ret);

            // Create user-rights hash array
            // @TODO: consider implementing fixACL() method according to RFC4314.2.1.1
            // so we could return only standard rights defined in RFC4314,
            // excluding 'c' and 'd' defined in RFC2086.
            if ($size % 2 == 0) {
                for ($i=0; $i<$size; $i++) {
                    $ret[$ret[$i]] = str_split($ret[++$i]);
                    unset($ret[$i-1]);
                    unset($ret[$i]);
                }
                return $ret;
            }

            $this->setError(self::ERROR_COMMAND, "Incomplete ACL response");
        }
    }

    /**
     * Send the LISTRIGHTS command (RFC4314)
     *
     * @param string $mailbox Mailbox name
     * @param string $user    User name
     *
     * @return array List of user rights
     * @since 0.5-beta
     */
    public function listRights($mailbox, $user)
    {
        list($code, $response) = $this->execute('LISTRIGHTS', array(
            $this->escape($mailbox), $this->escape($user)));

        if ($code == self::ERROR_OK && preg_match('/^\* LISTRIGHTS /i', $response)) {
            // Parse server response (remove "* LISTRIGHTS ")
            $response = substr($response, 13);

            $ret_mbox = $this->tokenizeResponse($response, 1);
            $ret_user = $this->tokenizeResponse($response, 1);
            $granted  = $this->tokenizeResponse($response, 1);
            $optional = trim($response);

            return array(
                'granted'  => str_split($granted),
                'optional' => explode(' ', $optional),
            );
        }
    }

    /**
     * Send the MYRIGHTS command (RFC4314)
     *
     * @param string $mailbox Mailbox name
     *
     * @return array MYRIGHTS response on success, NULL on error
     * @since 0.5-beta
     */
    public function myRights($mailbox)
    {
        list($code, $response) = $this->execute('MYRIGHTS', array($this->escape($mailbox)));

        if ($code == self::ERROR_OK && preg_match('/^\* MYRIGHTS /i', $response)) {
            // Parse server response (remove "* MYRIGHTS ")
            $response = substr($response, 11);

            $ret_mbox = $this->tokenizeResponse($response, 1);
            $rights   = $this->tokenizeResponse($response, 1);

            return str_split($rights);
        }
    }

    /**
     * Send the SETMETADATA command (RFC5464)
     *
     * @param string $mailbox Mailbox name
     * @param array  $entries Entry-value array (use NULL value as NIL)
     *
     * @return boolean True on success, False on failure
     * @since 0.5-beta
     */
    public function setMetadata($mailbox, $entries)
    {
        if (!is_array($entries) || empty($entries)) {
            $this->setError(self::ERROR_COMMAND, "Wrong argument for SETMETADATA command");
            return false;
        }

        foreach ($entries as $name => $value) {
            $entries[$name] = $this->escape($name) . ' ' . $this->escape($value, true);
        }

        $entries = implode(' ', $entries);
        $result = $this->execute('SETMETADATA', array(
            $this->escape($mailbox), '(' . $entries . ')'),
            self::COMMAND_NORESPONSE);

        return ($result == self::ERROR_OK);
    }

    /**
     * Send the SETMETADATA command with NIL values (RFC5464)
     *
     * @param string $mailbox Mailbox name
     * @param array  $entries Entry names array
     *
     * @return boolean True on success, False on failure
     *
     * @since 0.5-beta
     */
    public function deleteMetadata($mailbox, $entries)
    {
        if (!is_array($entries) && !empty($entries)) {
            $entries = explode(' ', $entries);
        }

        if (empty($entries)) {
            $this->setError(self::ERROR_COMMAND, "Wrong argument for SETMETADATA command");
            return false;
        }

        foreach ($entries as $entry) {
            $data[$entry] = null;
        }

        return $this->setMetadata($mailbox, $data);
    }

    /**
     * Send the GETMETADATA command (RFC5464)
     *
     * @param string $mailbox Mailbox name
     * @param array  $entries Entries
     * @param array  $options Command options (with MAXSIZE and DEPTH keys)
     *
     * @return array GETMETADATA result on success, NULL on error
     *
     * @since 0.5-beta
     */
    public function getMetadata($mailbox, $entries, $options=array())
    {
        if (!is_array($entries)) {
            $entries = array($entries);
        }

        // create entries string
        foreach ($entries as $idx => $name) {
            $entries[$idx] = $this->escape($name);
        }

        $optlist = '';
        $entlist = '(' . implode(' ', $entries) . ')';

        // create options string
        if (is_array($options)) {
            $options = array_change_key_case($options, CASE_UPPER);
            $opts = array();

            if (!empty($options['MAXSIZE'])) {
                $opts[] = 'MAXSIZE '.intval($options['MAXSIZE']);
            }
            if (!empty($options['DEPTH'])) {
                $opts[] = 'DEPTH '.intval($options['DEPTH']);
            }

            if ($opts) {
                $optlist = '(' . implode(' ', $opts) . ')';
            }
        }

        $optlist .= ($optlist ? ' ' : '') . $entlist;

        list($code, $response) = $this->execute('GETMETADATA', array(
            $this->escape($mailbox), $optlist));

        if ($code == self::ERROR_OK) {
            $result = array();
            $data   = $this->tokenizeResponse($response);

            // The METADATA response can contain multiple entries in a single
            // response or multiple responses for each entry or group of entries
            if (!empty($data) && ($size = count($data))) {
                for ($i=0; $i<$size; $i++) {
                    if (isset($mbox) && is_array($data[$i])) {
                        $size_sub = count($data[$i]);
                        for ($x=0; $x<$size_sub; $x+=2) {
                            if ($data[$i][$x+1] !== null)
                                $result[$mbox][$data[$i][$x]] = $data[$i][$x+1];
                        }
                        unset($data[$i]);
                    }
                    else if ($data[$i] == '*') {
                        if ($data[$i+1] == 'METADATA') {
                            $mbox = $data[$i+2];
                            unset($data[$i]);   // "*"
                            unset($data[++$i]); // "METADATA"
                            unset($data[++$i]); // Mailbox
                        }
                        // get rid of other untagged responses
                        else {
                            unset($mbox);
                            unset($data[$i]);
                        }
                    }
                    else if (isset($mbox)) {
                        if ($data[++$i] !== null)
                            $result[$mbox][$data[$i-1]] = $data[$i];
                        unset($data[$i]);
                        unset($data[$i-1]);
                    }
                    else {
                        unset($data[$i]);
                    }
                }
            }

            return $result;
        }
    }

    /**
     * Send the SETANNOTATION command (draft-daboo-imap-annotatemore)
     *
     * @param string $mailbox Mailbox name
     * @param array  $data    Data array where each item is an array with
     *                        three elements: entry name, attribute name, value
     *
     * @return boolean True on success, False on failure
     * @since 0.5-beta
     */
    public function setAnnotation($mailbox, $data)
    {
        if (!is_array($data) || empty($data)) {
            $this->setError(self::ERROR_COMMAND, "Wrong argument for SETANNOTATION command");
            return false;
        }

        foreach ($data as $entry) {
            // ANNOTATEMORE drafts before version 08 require quoted parameters
            $entries[] = sprintf('%s (%s %s)', $this->escape($entry[0], true),
                $this->escape($entry[1], true), $this->escape($entry[2], true));
        }

        $entries = implode(' ', $entries);
        $result  = $this->execute('SETANNOTATION', array(
            $this->escape($mailbox), $entries), self::COMMAND_NORESPONSE);

        return ($result == self::ERROR_OK);
    }

    /**
     * Send the SETANNOTATION command with NIL values (draft-daboo-imap-annotatemore)
     *
     * @param string $mailbox Mailbox name
     * @param array  $data    Data array where each item is an array with
     *                        two elements: entry name and attribute name
     *
     * @return boolean True on success, False on failure
     *
     * @since 0.5-beta
     */
    public function deleteAnnotation($mailbox, $data)
    {
        if (!is_array($data) || empty($data)) {
            $this->setError(self::ERROR_COMMAND, "Wrong argument for SETANNOTATION command");
            return false;
        }

        return $this->setAnnotation($mailbox, $data);
    }

    /**
     * Send the GETANNOTATION command (draft-daboo-imap-annotatemore)
     *
     * @param string $mailbox Mailbox name
     * @param array  $entries Entries names
     * @param array  $attribs Attribs names
     *
     * @return array Annotations result on success, NULL on error
     *
     * @since 0.5-beta
     */
    public function getAnnotation($mailbox, $entries, $attribs)
    {
        if (!is_array($entries)) {
            $entries = array($entries);
        }

        // create entries string
        // ANNOTATEMORE drafts before version 08 require quoted parameters
        foreach ($entries as $idx => $name) {
            $entries[$idx] = $this->escape($name, true);
        }
        $entries = '(' . implode(' ', $entries) . ')';

        if (!is_array($attribs)) {
            $attribs = array($attribs);
        }

        // create attributes string
        foreach ($attribs as $idx => $name) {
            $attribs[$idx] = $this->escape($name, true);
        }
        $attribs = '(' . implode(' ', $attribs) . ')';

        list($code, $response) = $this->execute('GETANNOTATION', array(
            $this->escape($mailbox), $entries, $attribs));

        if ($code == self::ERROR_OK) {
            $result = array();
            $data   = $this->tokenizeResponse($response);

            // Here we returns only data compatible with METADATA result format
            if (!empty($data) && ($size = count($data))) {
                for ($i=0; $i<$size; $i++) {
                    $entry = $data[$i];
                    if (isset($mbox) && is_array($entry)) {
                        $attribs = $entry;
                        $entry   = $last_entry;
                    }
                    else if ($entry == '*') {
                        if ($data[$i+1] == 'ANNOTATION') {
                            $mbox = $data[$i+2];
                            unset($data[$i]);   // "*"
                            unset($data[++$i]); // "ANNOTATION"
                            unset($data[++$i]); // Mailbox
                        }
                        // get rid of other untagged responses
                        else {
                            unset($mbox);
                            unset($data[$i]);
                        }
                        continue;
                    }
                    else if (isset($mbox)) {
                        $attribs = $data[++$i];
                    }
                    else {
                        unset($data[$i]);
                        continue;
                    }

                    if (!empty($attribs)) {
                        for ($x=0, $len=count($attribs); $x<$len;) {
                            $attr  = $attribs[$x++];
                            $value = $attribs[$x++];
                            if ($attr == 'value.priv' && $value !== null) {
                                $result[$mbox]['/private' . $entry] = $value;
                            }
                            else if ($attr == 'value.shared' && $value !== null) {
                                $result[$mbox]['/shared' . $entry] = $value;
                            }
                        }
                    }
                    $last_entry = $entry;
                    unset($data[$i]);
                }
            }

            return $result;
        }
    }

    /**
     * Returns BODYSTRUCTURE for the specified message.
     *
     * @param string $mailbox Folder name
     * @param int    $id      Message sequence number or UID
     * @param bool   $is_uid  True if $id is an UID
     *
     * @return array/bool Body structure array or False on error.
     * @since 0.6
     */
    public function getStructure($mailbox, $id, $is_uid = false)
    {
        $result = $this->fetch($mailbox, $id, $is_uid, array('BODYSTRUCTURE'));

        if (is_array($result)) {
            $result = array_shift($result);
            return $result->bodystructure;
        }

        return false;
    }

    /**
     * Returns data of a message part according to specified structure.
     *
     * @param array  $structure Message structure (getStructure() result)
     * @param string $part      Message part identifier
     *
     * @return array Part data as hash array (type, encoding, charset, size)
     */
    public static function getStructurePartData($structure, $part)
    {
        $part_a = self::getStructurePartArray($structure, $part);
        $data   = array();

        if (empty($part_a)) {
            return $data;
        }

        // content-type
        if (is_array($part_a[0])) {
            $data['type'] = 'multipart';
        }
        else {
            $data['type'] = strtolower($part_a[0]);

            // encoding
            $data['encoding'] = strtolower($part_a[5]);

            // charset
            if (is_array($part_a[2])) {
               while (list($key, $val) = each($part_a[2])) {
                    if (strcasecmp($val, 'charset') == 0) {
                        $data['charset'] = $part_a[2][$key+1];
                        break;
                    }
                }
            }
        }

        // size
        $data['size'] = intval($part_a[6]);

        return $data;
    }

    public static function getStructurePartArray($a, $part)
    {
        if (!is_array($a)) {
            return false;
        }

        if (empty($part)) {
            return $a;
        }

        $ctype = is_string($a[0]) && is_string($a[1]) ? $a[0] . '/' . $a[1] : '';

        if (strcasecmp($ctype, 'message/rfc822') == 0) {
            $a = $a[8];
        }

        if (strpos($part, '.') > 0) {
            $orig_part = $part;
            $pos       = strpos($part, '.');
            $rest      = substr($orig_part, $pos+1);
            $part      = substr($orig_part, 0, $pos);

            return self::getStructurePartArray($a[$part-1], $rest);
        }
        else if ($part > 0) {
            return (is_array($a[$part-1])) ? $a[$part-1] : $a;
        }
    }

    /**
     * Creates next command identifier (tag)
     *
     * @return string Command identifier
     * @since 0.5-beta
     */
    public function nextTag()
    {
        $this->cmd_num++;
        $this->cmd_tag = sprintf('A%04d', $this->cmd_num);

        return $this->cmd_tag;
    }

    /**
     * Sends IMAP command and parses result
     *
     * @param string $command   IMAP command
     * @param array  $arguments Command arguments
     * @param int    $options   Execution options
     *
     * @return mixed Response code or list of response code and data
     * @since 0.5-beta
     */
    public function execute($command, $arguments=array(), $options=0)
    {
        $tag      = $this->nextTag();
        $query    = $tag . ' ' . $command;
        $noresp   = ($options & self::COMMAND_NORESPONSE);
        $response = $noresp ? null : '';

        if (!empty($arguments)) {
            foreach ($arguments as $arg) {
                $query .= ' ' . self::r_implode($arg);
            }
        }

        // Send command
        if (!$this->putLineC($query, true, ($options & self::COMMAND_ANONYMIZED))) {
            preg_match('/^[A-Z0-9]+ ((UID )?[A-Z]+)/', $query, $matches);
            $cmd = $matches[1] ?: 'UNKNOWN';
            $this->setError(self::ERROR_COMMAND, "Failed to send $cmd command");

            return $noresp ? self::ERROR_COMMAND : array(self::ERROR_COMMAND, '');
        }

        // Parse response
        do {
            $line = $this->readLine(4096);
            if ($response !== null) {
                $response .= $line;
            }
        }
        while (!$this->startsWith($line, $tag . ' ', true, true));

        $code = $this->parseResult($line, $command . ': ');

        // Remove last line from response
        if ($response) {
            $line_len = min(strlen($response), strlen($line) + 2);
            $response = substr($response, 0, -$line_len);
        }

        // optional CAPABILITY response
        if (($options & self::COMMAND_CAPABILITY) && $code == self::ERROR_OK
            && preg_match('/\[CAPABILITY ([^]]+)\]/i', $line, $matches)
        ) {
            $this->parseCapability($matches[1], true);
        }

        // return last line only (without command tag, result and response code)
        if ($line && ($options & self::COMMAND_LASTLINE)) {
            $response = preg_replace("/^$tag (OK|NO|BAD|BYE|PREAUTH)?\s*(\[[a-z-]+\])?\s*/i", '', trim($line));
        }

        return $noresp ? $code : array($code, $response);
    }

    /**
     * Splits IMAP response into string tokens
     *
     * @param string &$str The IMAP's server response
     * @param int    $num  Number of tokens to return
     *
     * @return mixed Tokens array or string if $num=1
     * @since 0.5-beta
     */
    public static function tokenizeResponse(&$str, $num=0)
    {
        $result = array();

        while (!$num || count($result) < $num) {
            // remove spaces from the beginning of the string
            $str = ltrim($str);

            switch ($str[0]) {

            // String literal
            case '{':
                if (($epos = strpos($str, "}\r\n", 1)) == false) {
                    // error
                }
                if (!is_numeric(($bytes = substr($str, 1, $epos - 1)))) {
                    // error
                }

                $result[] = $bytes ? substr($str, $epos + 3, $bytes) : '';
                $str      = substr($str, $epos + 3 + $bytes);
                break;

            // Quoted string
            case '"':
                $len = strlen($str);

                for ($pos=1; $pos<$len; $pos++) {
                    if ($str[$pos] == '"') {
                        break;
                    }
                    if ($str[$pos] == "\\") {
                        if ($str[$pos + 1] == '"' || $str[$pos + 1] == "\\") {
                            $pos++;
                        }
                    }
                }

                // we need to strip slashes for a quoted string
                $result[] = stripslashes(substr($str, 1, $pos - 1));
                $str      = substr($str, $pos + 1);
                break;

            // Parenthesized list
            case '(':
                $str      = substr($str, 1);
                $result[] = self::tokenizeResponse($str);
                break;

            case ')':
                $str = substr($str, 1);
                return $result;

            // String atom, number, astring, NIL, *, %
            default:
                // empty string
                if ($str === '' || $str === null) {
                    break 2;
                }

                // excluded chars: SP, CTL, ), DEL
                // we do not exclude [ and ] (#1489223)
                if (preg_match('/^([^\x00-\x20\x29\x7F]+)/', $str, $m)) {
                    $result[] = $m[1] == 'NIL' ? null : $m[1];
                    $str      = substr($str, strlen($m[1]));
                }
                break;
            }
        }

        return $num == 1 ? $result[0] : $result;
    }

    /**
     * Joins IMAP command line elements (recursively)
     */
    protected static function r_implode($element)
    {
        $string = '';

        if (is_array($element)) {
            reset($element);
            foreach ($element as $value) {
                $string .= ' ' . self::r_implode($value);
            }
        }
        else {
            return $element;
        }

        return '(' . trim($string) . ')';
    }

    /**
     * Converts message identifiers array into sequence-set syntax
     *
     * @param array $messages Message identifiers
     * @param bool  $force    Forces compression of any size
     *
     * @return string Compressed sequence-set
     */
    public static function compressMessageSet($messages, $force=false)
    {
        // given a comma delimited list of independent mid's,
        // compresses by grouping sequences together

        if (!is_array($messages)) {
            // if less than 255 bytes long, let's not bother
            if (!$force && strlen($messages)<255) {
                return $messages;
            }

            // see if it's already been compressed
            if (strpos($messages, ':') !== false) {
                return $messages;
            }

            // separate, then sort
            $messages = explode(',', $messages);
        }

        sort($messages);

        $result = array();
        $start  = $prev = $messages[0];

        foreach ($messages as $id) {
            $incr = $id - $prev;
            if ($incr > 1) { // found a gap
                if ($start == $prev) {
                    $result[] = $prev; // push single id
                }
                else {
                    $result[] = $start . ':' . $prev; // push sequence as start_id:end_id
                }
                $start = $id; // start of new sequence
            }
            $prev = $id;
        }

        // handle the last sequence/id
        if ($start == $prev) {
            $result[] = $prev;
        }
        else {
            $result[] = $start.':'.$prev;
        }

        // return as comma separated string
        return implode(',', $result);
    }

    /**
     * Converts message sequence-set into array
     *
     * @param string $messages Message identifiers
     *
     * @return array List of message identifiers
     */
    public static function uncompressMessageSet($messages)
    {
        if (empty($messages)) {
            return array();
        }

        $result   = array();
        $messages = explode(',', $messages);

        foreach ($messages as $idx => $part) {
            $items = explode(':', $part);
            $max   = max($items[0], $items[1]);

            for ($x=$items[0]; $x<=$max; $x++) {
                $result[] = (int)$x;
            }
            unset($messages[$idx]);
        }

        return $result;
    }

    /**
     * Clear internal status cache
     */
    protected function clear_status_cache($mailbox)
    {
        unset($this->data['STATUS:' . $mailbox]);

        $keys = array('EXISTS', 'RECENT', 'UNSEEN', 'UID-MAP');

        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
    }

    /**
     * Clear internal cache of the current mailbox
     */
    protected function clear_mailbox_cache()
    {
        $this->clear_status_cache($this->selected);

        $keys = array('UIDNEXT', 'UIDVALIDITY', 'HIGHESTMODSEQ', 'NOMODSEQ',
            'PERMANENTFLAGS', 'QRESYNC', 'VANISHED', 'READ-WRITE');

        foreach ($keys as $key) {
            unset($this->data[$key]);
        }
    }

    /**
     * Converts flags array into string for inclusion in IMAP command
     *
     * @param array $flags Flags (see self::flags)
     *
     * @return string Space-separated list of flags
     */
    protected function flagsToStr($flags)
    {
        foreach ((array)$flags as $idx => $flag) {
            if ($flag = $this->flags[strtoupper($flag)]) {
                $flags[$idx] = $flag;
            }
        }

        return implode(' ', (array)$flags);
    }

    /**
     * CAPABILITY response parser
     */
    protected function parseCapability($str, $trusted=false)
    {
        $str = preg_replace('/^\* CAPABILITY /i', '', $str);

        $this->capability = explode(' ', strtoupper($str));

        if (!empty($this->prefs['disabled_caps'])) {
            $this->capability = array_diff($this->capability, $this->prefs['disabled_caps']);
        }

        if (!isset($this->prefs['literal+']) && in_array('LITERAL+', $this->capability)) {
            $this->prefs['literal+'] = true;
        }

        if ($trusted) {
            $this->capability_readed = true;
        }
    }

    /**
     * Escapes a string when it contains special characters (RFC3501)
     *
     * @param string  $string       IMAP string
     * @param boolean $force_quotes Forces string quoting (for atoms)
     *
     * @return string String atom, quoted-string or string literal
     * @todo lists
     */
    public static function escape($string, $force_quotes=false)
    {
        if ($string === null) {
            return 'NIL';
        }

        if ($string === '') {
            return '""';
        }

        // atom-string (only safe characters)
        if (!$force_quotes && !preg_match('/[\x00-\x20\x22\x25\x28-\x2A\x5B-\x5D\x7B\x7D\x80-\xFF]/', $string)) {
            return $string;
        }

        // quoted-string
        if (!preg_match('/[\r\n\x00\x80-\xFF]/', $string)) {
            return '"' . addcslashes($string, '\\"') . '"';
        }

        // literal-string
        return sprintf("{%d}\r\n%s", strlen($string), $string);
    }

    /**
     * Set the value of the debugging flag.
     *
     * @param boolean  $debug   New value for the debugging flag.
     * @param callback $handler Logging handler function
     *
     * @since 0.5-stable
     */
    public function setDebug($debug, $handler = null)
    {
        $this->debug         = $debug;
        $this->debug_handler = $handler;
    }

    /**
     * Write the given debug text to the current debug output handler.
     *
     * @param string $message Debug mesage text.
     *
     * @since 0.5-stable
     */
    protected function debug($message)
    {
        if (($len = strlen($message)) > self::DEBUG_LINE_LENGTH) {
            $diff    = $len - self::DEBUG_LINE_LENGTH;
            $message = substr($message, 0, self::DEBUG_LINE_LENGTH)
                . "... [truncated $diff bytes]";
        }

        if ($this->resourceid) {
            $message = sprintf('[%s] %s', $this->resourceid, $message);
        }

        if ($this->debug_handler) {
            call_user_func_array($this->debug_handler, array(&$this, $message));
        }
        else {
            echo "DEBUG: $message\n";
        }
    }
}
