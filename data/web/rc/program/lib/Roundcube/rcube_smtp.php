<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide SMTP functionality using socket connections                 |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Class to provide SMTP functionality using PEAR Net_SMTP
 *
 * @package    Framework
 * @subpackage Mail
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 */
class rcube_smtp
{
    private $conn;
    private $response;
    private $error;
    private $anonymize_log = 0;

    // define headers delimiter
    const SMTP_MIME_CRLF = "\r\n";

    const DEBUG_LINE_LENGTH = 4098; // 4KB + 2B for \r\n


    /**
     * SMTP Connection and authentication
     *
     * @param string Server host
     * @param string Server port
     * @param string User name
     * @param string Password
     *
     * @return bool  Returns true on success, or false on error
     */
    public function connect($host = null, $port = null, $user = null, $pass = null)
    {
        $rcube = rcube::get_instance();

        // disconnect/destroy $this->conn
        $this->disconnect();

        // reset error/response var
        $this->error = $this->response = null;

        // let plugins alter smtp connection config
        $CONFIG = $rcube->plugins->exec_hook('smtp_connect', array(
            'smtp_server'    => $host ?: $rcube->config->get('smtp_server'),
            'smtp_port'      => $port ?: $rcube->config->get('smtp_port', 25),
            'smtp_user'      => $user !== null ? $user : $rcube->config->get('smtp_user'),
            'smtp_pass'      => $pass !== null ? $pass : $rcube->config->get('smtp_pass'),
            'smtp_auth_cid'  => $rcube->config->get('smtp_auth_cid'),
            'smtp_auth_pw'   => $rcube->config->get('smtp_auth_pw'),
            'smtp_auth_type' => $rcube->config->get('smtp_auth_type'),
            'smtp_helo_host' => $rcube->config->get('smtp_helo_host'),
            'smtp_timeout'   => $rcube->config->get('smtp_timeout'),
            'smtp_conn_options'   => $rcube->config->get('smtp_conn_options'),
            'smtp_auth_callbacks' => array(),
        ));

        $smtp_host = rcube_utils::parse_host($CONFIG['smtp_server']);
        // when called from Installer it's possible to have empty $smtp_host here
        if (!$smtp_host) $smtp_host = 'localhost';
        $smtp_port     = is_numeric($CONFIG['smtp_port']) ? $CONFIG['smtp_port'] : 25;
        $smtp_host_url = parse_url($smtp_host);

        // overwrite port
        if (isset($smtp_host_url['host']) && isset($smtp_host_url['port'])) {
            $smtp_host = $smtp_host_url['host'];
            $smtp_port = $smtp_host_url['port'];
        }

        // re-write smtp host
        if (isset($smtp_host_url['host']) && isset($smtp_host_url['scheme'])) {
            $smtp_host = sprintf('%s://%s', $smtp_host_url['scheme'], $smtp_host_url['host']);
        }

        // remove TLS prefix and set flag for use in Net_SMTP::auth()
        if (preg_match('#^tls://#i', $smtp_host)) {
            $smtp_host = preg_replace('#^tls://#i', '', $smtp_host);
            $use_tls   = true;
        }

        // Handle per-host socket options
        rcube_utils::parse_socket_options($CONFIG['smtp_conn_options'], $smtp_host);

        if (!empty($CONFIG['smtp_helo_host'])) {
            $helo_host = $CONFIG['smtp_helo_host'];
        }
        else if (!empty($_SERVER['SERVER_NAME'])) {
            $helo_host = preg_replace('/:\d+$/', '', $_SERVER['SERVER_NAME']);
        }
        else {
            $helo_host = 'localhost';
        }

        // IDNA Support
        $smtp_host = rcube_utils::idn_to_ascii($smtp_host);

        $this->conn = new Net_SMTP($smtp_host, $smtp_port, $helo_host, false, 0, $CONFIG['smtp_conn_options']);

        if ($rcube->config->get('smtp_debug')) {
            $this->conn->setDebug(true, array($this, 'debug_handler'));
            $this->anonymize_log = 0;
        }

        // register authentication methods
        if (!empty($CONFIG['smtp_auth_callbacks']) && method_exists($this->conn, 'setAuthMethod')) {
            foreach ($CONFIG['smtp_auth_callbacks'] as $callback) {
                $this->conn->setAuthMethod($callback['name'], $callback['function'],
                    isset($callback['prepend']) ? $callback['prepend'] : true);
            }
        }

        // try to connect to server and exit on failure
        $result = $this->conn->connect($CONFIG['smtp_timeout']);

        if (is_a($result, 'PEAR_Error')) {
            $this->response[] = "Connection failed: " . $result->getMessage();

            list($code,) = $this->conn->getResponse();
            $this->error = array('label' => 'smtpconnerror', 'vars' => array('code' => $code));
            $this->conn  = null;

            return false;
        }

        // workaround for timeout bug in Net_SMTP 1.5.[0-1] (#1487843)
        if (method_exists($this->conn, 'setTimeout')
            && ($timeout = ini_get('default_socket_timeout'))
        ) {
            $this->conn->setTimeout($timeout);
        }

        $smtp_user = str_replace('%u', $rcube->get_user_name(), $CONFIG['smtp_user']);
        $smtp_pass = str_replace('%p', $rcube->get_user_password(), $CONFIG['smtp_pass']);
        $smtp_auth_type = $CONFIG['smtp_auth_type'] ?: null;

        if (!empty($CONFIG['smtp_auth_cid'])) {
            $smtp_authz = $smtp_user;
            $smtp_user  = $CONFIG['smtp_auth_cid'];
            $smtp_pass  = $CONFIG['smtp_auth_pw'];
        }

        // attempt to authenticate to the SMTP server
        if ($smtp_user && $smtp_pass) {
            // IDNA Support
            if (strpos($smtp_user, '@')) {
                $smtp_user = rcube_utils::idn_to_ascii($smtp_user);
            }

            $result = $this->conn->auth($smtp_user, $smtp_pass, $smtp_auth_type, $use_tls, $smtp_authz);

            if (is_a($result, 'PEAR_Error')) {
                list($code,) = $this->conn->getResponse();
                $this->error = array('label' => 'smtpautherror', 'vars' => array('code' => $code));
                $this->response[] = 'Authentication failure: ' . $result->getMessage()
                    . ' (Code: ' . $result->getCode() . ')';

                $this->reset();
                $this->disconnect();

                return false;
            }
        }

        return true;
    }

    /**
     * Function for sending mail
     *
     * @param string Sender e-Mail address
     *
     * @param mixed  Either a comma-seperated list of recipients
     *               (RFC822 compliant), or an array of recipients,
     *               each RFC822 valid. This may contain recipients not
     *               specified in the headers, for Bcc:, resending
     *               messages, etc.
     * @param mixed  The message headers to send with the mail
     *               Either as an associative array or a finally
     *               formatted string
     * @param mixed  The full text of the message body, including any Mime parts
     *               or file handle
     * @param array  Delivery options (e.g. DSN request)
     *
     * @return bool  Returns true on success, or false on error
     */
    public function send_mail($from, $recipients, &$headers, &$body, $opts=null)
    {
        if (!is_object($this->conn)) {
            return false;
        }

        // prepare message headers as string
        if (is_array($headers)) {
            if (!($headerElements = $this->_prepare_headers($headers))) {
                $this->reset();
                return false;
            }

            list($from, $text_headers) = $headerElements;
        }
        else if (is_string($headers)) {
            $text_headers = $headers;
        }

        // exit if no from address is given
        if (!isset($from)) {
            $this->reset();
            $this->response[] = "No From address has been provided";
            return false;
        }

        // RFC3461: Delivery Status Notification
        if ($opts['dsn']) {
            $exts = $this->conn->getServiceExtensions();

            if (isset($exts['DSN'])) {
                $from_params      = 'RET=HDRS';
                $recipient_params = 'NOTIFY=SUCCESS,FAILURE';
            }
        }

        // RFC2298.3: remove envelope sender address
        if (empty($opts['mdn_use_from'])
            && preg_match('/Content-Type: multipart\/report/', $text_headers)
            && preg_match('/report-type=disposition-notification/', $text_headers)
        ) {
            $from = '';
        }

        // set From: address
        $result = $this->conn->mailFrom($from, $from_params);
        if (is_a($result, 'PEAR_Error')) {
            $err = $this->conn->getResponse();
            $this->error = array('label' => 'smtpfromerror', 'vars' => array(
                'from' => $from, 'code' => $err[0], 'msg' => $err[1]));
            $this->response[] = "Failed to set sender '$from'. "
                . $err[1] . ' (Code: ' . $err[0] . ')';
            $this->reset();
            return false;
        }

        // prepare list of recipients
        $recipients = $this->_parse_rfc822($recipients);
        if (is_a($recipients, 'PEAR_Error')) {
            $this->error = array('label' => 'smtprecipientserror');
            $this->reset();
            return false;
        }

        // set mail recipients
        foreach ($recipients as $recipient) {
            $result = $this->conn->rcptTo($recipient, $recipient_params);
            if (is_a($result, 'PEAR_Error')) {
                $err = $this->conn->getResponse();
                $this->error = array('label' => 'smtptoerror', 'vars' => array(
                    'to' => $recipient, 'code' => $err[0], 'msg' => $err[1]));
                $this->response[] = "Failed to add recipient '$recipient'. "
                    . $err[1] . ' (Code: ' . $err[0] . ')';
                $this->reset();
                return false;
            }
        }

        if (is_resource($body)) {
            // file handle
            $data = $body;

            if ($text_headers) {
                $text_headers = preg_replace('/[\r\n]+$/', '', $text_headers);
            }
        }
        else {
            // Concatenate headers and body so it can be passed by reference to SMTP_CONN->data
            // so preg_replace in SMTP_CONN->quotedata will store a reference instead of a copy.
            // We are still forced to make another copy here for a couple ticks so we don't really
            // get to save a copy in the method call.
            $data = $text_headers . "\r\n" . $body;

            // unset old vars to save data and so we can pass into SMTP_CONN->data by reference.
            unset($text_headers, $body);
        }

        // Send the message's headers and the body as SMTP data.
        $result = $this->conn->data($data, $text_headers);
        if (is_a($result, 'PEAR_Error')) {
            $err = $this->conn->getResponse();
            if (!in_array($err[0], array(354, 250, 221))) {
                $msg = sprintf('[%d] %s', $err[0], $err[1]);
            }
            else {
                $msg = $result->getMessage();
            }

            $this->error = array('label' => 'smtperror', 'vars' => array('msg' => $msg));
            $this->response[] = "Failed to send data. " . $msg;
            $this->reset();
            return false;
        }

        $this->response[] = join(': ', $this->conn->getResponse());
        return true;
    }

    /**
     * Reset the global SMTP connection
     */
    public function reset()
    {
        if (is_object($this->conn)) {
            $this->conn->rset();
        }
    }

    /**
     * Disconnect the global SMTP connection
     */
    public function disconnect()
    {
        if (is_object($this->conn)) {
            $this->conn->disconnect();
            $this->conn = null;
        }
    }

    /**
     * This is our own debug handler for the SMTP connection
     */
    public function debug_handler(&$smtp, $message)
    {
        // catch AUTH commands and set anonymization flag for subsequent sends
        if (preg_match('/^Send: AUTH ([A-Z]+)/', $message, $m)) {
            $this->anonymize_log = $m[1] == 'LOGIN' ? 2 : 1;
        }
        // anonymize this log entry
        else if ($this->anonymize_log > 0 && strpos($message, 'Send:') === 0 && --$this->anonymize_log == 0) {
            $message = sprintf('Send: ****** [%d]', strlen($message) - 8);
        }

        if (($len = strlen($message)) > self::DEBUG_LINE_LENGTH) {
            $diff    = $len - self::DEBUG_LINE_LENGTH;
            $message = substr($message, 0, self::DEBUG_LINE_LENGTH)
                . "... [truncated $diff bytes]";
        }

        rcube::write_log('smtp', preg_replace('/\r\n$/', '', $message));
    }

    /**
     * Get error message
     */
    public function get_error()
    {
        return $this->error;
    }

    /**
     * Get server response messages array
     */
    public function get_response()
    {
         return $this->response;
    }

    /**
     * Take an array of mail headers and return a string containing
     * text usable in sending a message.
     *
     * @param array $headers The array of headers to prepare, in an associative
     *              array, where the array key is the header name (ie,
     *              'Subject'), and the array value is the header
     *              value (ie, 'test'). The header produced from those
     *              values would be 'Subject: test'.
     *
     * @return mixed Returns false if it encounters a bad address,
     *               otherwise returns an array containing two
     *               elements: Any From: address found in the headers,
     *               and the plain text version of the headers.
     */
    private function _prepare_headers($headers)
    {
        $lines = array();
        $from  = null;

        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'From') === 0) {
                $addresses = $this->_parse_rfc822($value);

                if (is_array($addresses)) {
                    $from = $addresses[0];
                }

                // Reject envelope From: addresses with spaces.
                if (strpos($from, ' ') !== false) {
                    return false;
                }

                $lines[] = $key . ': ' . $value;
            }
            else if (strcasecmp($key, 'Received') === 0) {
                $received = array();
                if (is_array($value)) {
                    foreach ($value as $line) {
                        $received[] = $key . ': ' . $line;
                    }
                }
                else {
                    $received[] = $key . ': ' . $value;
                }

                // Put Received: headers at the top.  Spam detectors often
                // flag messages with Received: headers after the Subject:
                // as spam.
                $lines = array_merge($received, $lines);
            }
            else {
                // If $value is an array (i.e., a list of addresses), convert
                // it to a comma-delimited string of its elements (addresses).
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                $lines[] = $key . ': ' . $value;
            }
        }

        return array($from, join(self::SMTP_MIME_CRLF, $lines) . self::SMTP_MIME_CRLF);
    }

    /**
     * Take a set of recipients and parse them, returning an array of
     * bare addresses (forward paths) that can be passed to sendmail
     * or an smtp server with the rcpt to: command.
     *
     * @param mixed Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid.
     *
     * @return array An array of forward paths (bare addresses).
     */
    private function _parse_rfc822($recipients)
    {
        // if we're passed an array, assume addresses are valid and implode them before parsing.
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }

        $addresses  = array();
        $recipients = preg_replace('/[\s\t]*\r?\n/', '', $recipients);
        $recipients = rcube_utils::explode_quoted_string(',', $recipients);

        reset($recipients);
        foreach ($recipients as $recipient) {
            $a = rcube_utils::explode_quoted_string(' ', $recipient);
            foreach ($a as $word) {
                $word = trim($word);
                $len  = strlen($word);

                if ($len && strpos($word, "@") > 0 && $word[$len-1] != '"') {
                    $word = preg_replace('/^<|>$/', '', $word);
                    if (!in_array($word, $addresses)) {
                        array_push($addresses, $word);
                    }
                }
            }
        }

        return $addresses;
    }
}
