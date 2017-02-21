<?php
/** vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 5 and 7                                                  |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2015 Jon Parise and Chuck Hagenbuch               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.01 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/3_01.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Chuck Hagenbuch <chuck@horde.org>                           |
// |          Jon Parise <jon@php.net>                                    |
// |          Damian Alejandro Fernandez Sosa <damlists@cnba.uba.ar>      |
// +----------------------------------------------------------------------+

require_once 'PEAR.php';
require_once 'Net/Socket.php';

/**
 * Provides an implementation of the SMTP protocol using PEAR's
 * Net_Socket class.
 *
 * @package Net_SMTP
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@php.net>
 * @author  Damian Alejandro Fernandez Sosa <damlists@cnba.uba.ar>
 *
 * @example basic.php A basic implementation of the Net_SMTP package.
 */
class Net_SMTP
{
    /**
     * The server to connect to.
     * @var string
     */
    public $host = 'localhost';

    /**
     * The port to connect to.
     * @var int
     */
    public $port = 25;

    /**
     * The value to give when sending EHLO or HELO.
     * @var string
     */
    public $localhost = 'localhost';

    /**
     * List of supported authentication methods, in preferential order.
     * @var array
     */
    public $auth_methods = array();

    /**
     * Use SMTP command pipelining (specified in RFC 2920) if the SMTP
     * server supports it.
     *
     * When pipeling is enabled, rcptTo(), mailFrom(), sendFrom(),
     * somlFrom() and samlFrom() do not wait for a response from the
     * SMTP server but return immediately.
     *
     * @var bool
     */
    public $pipelining = false;

    /**
     * Number of pipelined commands.
     * @var int
     */
    protected $pipelined_commands = 0;

    /**
     * Should debugging output be enabled?
     * @var boolean
     */
    protected $debug = false;

    /**
     * Debug output handler.
     * @var callback
     */
    protected $debug_handler = null;

    /**
     * The socket resource being used to connect to the SMTP server.
     * @var resource
     */
    protected $socket = null;

    /**
     * Array of socket options that will be passed to Net_Socket::connect().
     * @see stream_context_create()
     * @var array
     */
    protected $socket_options = null;

    /**
     * The socket I/O timeout value in seconds.
     * @var int
     */
    protected $timeout = 0;

    /**
     * The most recent server response code.
     * @var int
     */
    protected $code = -1;

    /**
     * The most recent server response arguments.
     * @var array
     */
    protected $arguments = array();

    /**
     * Stores the SMTP server's greeting string.
     * @var string
     */
    protected $greeting = null;

    /**
     * Stores detected features of the SMTP server.
     * @var array
     */
    protected $esmtp = array();

    /**
     * Instantiates a new Net_SMTP object, overriding any defaults
     * with parameters that are passed in.
     *
     * If you have SSL support in PHP, you can connect to a server
     * over SSL using an 'ssl://' prefix:
     *
     *   // 465 is a common smtps port.
     *   $smtp = new Net_SMTP('ssl://mail.host.com', 465);
     *   $smtp->connect();
     *
     * @param string  $host           The server to connect to.
     * @param integer $port           The port to connect to.
     * @param string  $localhost      The value to give when sending EHLO or HELO.
     * @param boolean $pipelining     Use SMTP command pipelining
     * @param integer $timeout        Socket I/O timeout in seconds.
     * @param array   $socket_options Socket stream_context_create() options.
     *
     * @since 1.0
     */
    public function __construct($host = null, $port = null, $localhost = null,
        $pipelining = false, $timeout = 0, $socket_options = null
    ) {
        if (isset($host)) {
            $this->host = $host;
        }
        if (isset($port)) {
            $this->port = $port;
        }
        if (isset($localhost)) {
            $this->localhost = $localhost;
        }

        $this->pipelining      = $pipelining;
        $this->socket         = new Net_Socket();
        $this->socket_options = $socket_options;
        $this->timeout        = $timeout;

        /* Include the Auth_SASL package.  If the package is available, we
         * enable the authentication methods that depend upon it. */
        if (@include_once 'Auth/SASL.php') {
            $this->setAuthMethod('CRAM-MD5', array($this, 'authCramMD5'));
            $this->setAuthMethod('DIGEST-MD5', array($this, 'authDigestMD5'));
        }

        /* These standard authentication methods are always available. */
        $this->setAuthMethod('LOGIN', array($this, 'authLogin'), false);
        $this->setAuthMethod('PLAIN', array($this, 'authPlain'), false);
    }

    /**
     * Set the socket I/O timeout value in seconds plus microseconds.
     *
     * @param integer $seconds      Timeout value in seconds.
     * @param integer $microseconds Additional value in microseconds.
     *
     * @since 1.5.0
     */
    public function setTimeout($seconds, $microseconds = 0)
    {
        return $this->socket->setTimeout($seconds, $microseconds);
    }

    /**
     * Set the value of the debugging flag.
     *
     * @param boolean  $debug   New value for the debugging flag.
     * @param callback $handler Debug handler callback
     *
     * @since 1.1.0
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
     * @since 1.3.3
     */
    protected function debug($message)
    {
        if ($this->debug) {
            if ($this->debug_handler) {
                call_user_func_array(
                    $this->debug_handler, array(&$this, $message)
                );
            } else {
                echo "DEBUG: $message\n";
            }
        }
    }

    /**
     * Send the given string of data to the server.
     *
     * @param string $data The string of data to send.
     *
     * @return mixed The number of bytes that were actually written,
     *               or a PEAR_Error object on failure.
     *
     * @since 1.1.0
     */
    protected function send($data)
    {
        $this->debug("Send: $data");

        $result = $this->socket->write($data);
        if (!$result || PEAR::isError($result)) {
            $msg = $result ? $result->getMessage() : "unknown error";
            return PEAR::raiseError("Failed to write to socket: $msg");
        }

        return $result;
    }

    /**
     * Send a command to the server with an optional string of
     * arguments.  A carriage return / linefeed (CRLF) sequence will
     * be appended to each command string before it is sent to the
     * SMTP server - an error will be thrown if the command string
     * already contains any newline characters. Use send() for
     * commands that must contain newlines.
     *
     * @param string $command The SMTP command to send to the server.
     * @param string $args    A string of optional arguments to append
     *                        to the command.
     *
     * @return mixed The result of the send() call.
     *
     * @since 1.1.0
     */
    protected function put($command, $args = '')
    {
        if (!empty($args)) {
            $command .= ' ' . $args;
        }

        if (strcspn($command, "\r\n") !== strlen($command)) {
            return PEAR::raiseError('Commands cannot contain newlines');
        }

        return $this->send($command . "\r\n");
    }

    /**
     * Read a reply from the SMTP server.  The reply consists of a response
     * code and a response message.
     *
     * @param mixed $valid The set of valid response codes.  These
     *                     may be specified as an array of integer
     *                     values or as a single integer value.
     * @param bool  $later Do not parse the response now, but wait
     *                     until the last command in the pipelined
     *                     command group
     *
     * @return mixed True if the server returned a valid response code or
     *               a PEAR_Error object is an error condition is reached.
     *
     * @since 1.1.0
     *
     * @see getResponse
     */
    protected function parseResponse($valid, $later = false)
    {
        $this->code      = -1;
        $this->arguments = array();

        if ($later) {
            $this->pipelined_commands++;
            return true;
        }

        for ($i = 0; $i <= $this->pipelined_commands; $i++) {
            while ($line = $this->socket->readLine()) {
                $this->debug("Recv: $line");

                /* If we receive an empty line, the connection was closed. */
                if (empty($line)) {
                    $this->disconnect();
                    return PEAR::raiseError('Connection was closed');
                }

                /* Read the code and store the rest in the arguments array. */
                $code = substr($line, 0, 3);
                $this->arguments[] = trim(substr($line, 4));

                /* Check the syntax of the response code. */
                if (is_numeric($code)) {
                    $this->code = (int)$code;
                } else {
                    $this->code = -1;
                    break;
                }

                /* If this is not a multiline response, we're done. */
                if (substr($line, 3, 1) != '-') {
                    break;
                }
            }
        }

        $this->pipelined_commands = 0;

        /* Compare the server's response code with the valid code/codes. */
        if (is_int($valid) && ($this->code === $valid)) {
            return true;
        } elseif (is_array($valid) && in_array($this->code, $valid, true)) {
            return true;
        }

        return PEAR::raiseError('Invalid response code received from server', $this->code);
    }

    /**
     * Issue an SMTP command and verify its response.
     *
     * @param string $command The SMTP command string or data.
     * @param mixed  $valid   The set of valid response codes. These
     *                        may be specified as an array of integer
     *                        values or as a single integer value.
     *
     * @return mixed True on success or a PEAR_Error object on failure.
     *
     * @since 1.6.0
     */
    public function command($command, $valid)
    {
        if (PEAR::isError($error = $this->put($command))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse($valid))) {
            return $error;
        }

        return true;
    }

    /**
     * Return a 2-tuple containing the last response from the SMTP server.
     *
     * @return array A two-element array: the first element contains the
     *               response code as an integer and the second element
     *               contains the response's arguments as a string.
     *
     * @since 1.1.0
     */
    public function getResponse()
    {
        return array($this->code, join("\n", $this->arguments));
    }

    /**
     * Return the SMTP server's greeting string.
     *
     * @return string A string containing the greeting string, or null if
     *                a greeting has not been received.
     *
     * @since 1.3.3
     */
    public function getGreeting()
    {
        return $this->greeting;
    }

    /**
     * Attempt to connect to the SMTP server.
     *
     * @param int  $timeout    The timeout value (in seconds) for the
     *                         socket connection attempt.
     * @param bool $persistent Should a persistent socket connection
     *                         be used?
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function connect($timeout = null, $persistent = false)
    {
        $this->greeting = null;

        $result = $this->socket->connect(
            $this->host, $this->port, $persistent, $timeout, $this->socket_options
        );

        if (PEAR::isError($result)) {
            return PEAR::raiseError(
                'Failed to connect socket: ' . $result->getMessage()
            );
        }

        /*
         * Now that we're connected, reset the socket's timeout value for
         * future I/O operations.  This allows us to have different socket
         * timeout values for the initial connection (our $timeout parameter)
         * and all other socket operations.
         */
        if ($this->timeout > 0) {
            if (PEAR::isError($error = $this->setTimeout($this->timeout))) {
                return $error;
            }
        }

        if (PEAR::isError($error = $this->parseResponse(220))) {
            return $error;
        }

        /* Extract and store a copy of the server's greeting string. */
        list(, $this->greeting) = $this->getResponse();

        if (PEAR::isError($error = $this->negotiate())) {
            return $error;
        }

        return true;
    }

    /**
     * Attempt to disconnect from the SMTP server.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function disconnect()
    {
        if (PEAR::isError($error = $this->put('QUIT'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(221))) {
            return $error;
        }
        if (PEAR::isError($error = $this->socket->disconnect())) {
            return PEAR::raiseError(
                'Failed to disconnect socket: ' . $error->getMessage()
            );
        }

        return true;
    }

    /**
     * Attempt to send the EHLO command and obtain a list of ESMTP
     * extensions available, and failing that just send HELO.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @since 1.1.0
     */
    protected function negotiate()
    {
        if (PEAR::isError($error = $this->put('EHLO', $this->localhost))) {
            return $error;
        }

        if (PEAR::isError($this->parseResponse(250))) {
            /* If the EHLO failed, try the simpler HELO command. */
            if (PEAR::isError($error = $this->put('HELO', $this->localhost))) {
                return $error;
            }
            if (PEAR::isError($this->parseResponse(250))) {
                return PEAR::raiseError('HELO was not accepted', $this->code);
            }

            return true;
        }

        foreach ($this->arguments as $argument) {
            $verb      = strtok($argument, ' ');
            $len       = strlen($verb);
            $arguments = substr($argument, $len + 1, strlen($argument) - $len - 1);
            $this->esmtp[$verb] = $arguments;
        }

        if (!isset($this->esmtp['PIPELINING'])) {
            $this->pipelining = false;
        }

        return true;
    }

    /**
     * Returns the name of the best authentication method that the server
     * has advertised.
     *
     * @return mixed Returns a string containing the name of the best
     *               supported authentication method or a PEAR_Error object
     *               if a failure condition is encountered.
     * @since 1.1.0
     */
    protected function getBestAuthMethod()
    {
        $available_methods = explode(' ', $this->esmtp['AUTH']);

        foreach ($this->auth_methods as $method => $callback) {
            if (in_array($method, $available_methods)) {
                return $method;
            }
        }

        return PEAR::raiseError('No supported authentication methods');
    }

    /**
     * Attempt to do SMTP authentication.
     *
     * @param string $uid    The userid to authenticate as.
     * @param string $pwd    The password to authenticate with.
     * @param string $method The requested authentication method.  If none is
     *                       specified, the best supported method will be used.
     * @param bool   $tls    Flag indicating whether or not TLS should be attempted.
     * @param string $authz  An optional authorization identifier.  If specified, this
     *                       identifier will be used as the authorization proxy.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function auth($uid, $pwd , $method = '', $tls = true, $authz = '')
    {
        /* We can only attempt a TLS connection if one has been requested,
         * we're running PHP 5.1.0 or later, have access to the OpenSSL
         * extension, are connected to an SMTP server which supports the
         * STARTTLS extension, and aren't already connected over a secure
         * (SSL) socket connection. */
        if ($tls && version_compare(PHP_VERSION, '5.1.0', '>=')
            && extension_loaded('openssl') && isset($this->esmtp['STARTTLS'])
            && strncasecmp($this->host, 'ssl://', 6) !== 0
        ) {
            /* Start the TLS connection attempt. */
            if (PEAR::isError($result = $this->put('STARTTLS'))) {
                return $result;
            }
            if (PEAR::isError($result = $this->parseResponse(220))) {
                return $result;
            }
            if (isset($this->socket_options['ssl']['crypto_method'])) {
                $crypto_method = $this->socket_options['ssl']['crypto_method'];
            } else {
                /* STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT constant does not exist
                 * and STREAM_CRYPTO_METHOD_SSLv23_CLIENT constant is
                 * inconsistent across PHP versions. */
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT
                                 | @STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                                 | @STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (PEAR::isError($result = $this->socket->enableCrypto(true, $crypto_method))) {
                return $result;
            } elseif ($result !== true) {
                return PEAR::raiseError('STARTTLS failed');
            }

            /* Send EHLO again to recieve the AUTH string from the
             * SMTP server. */
            $this->negotiate();
        }

        if (empty($this->esmtp['AUTH'])) {
            return PEAR::raiseError('SMTP server does not support authentication');
        }

        /* If no method has been specified, get the name of the best
         * supported method advertised by the SMTP server. */
        if (empty($method)) {
            if (PEAR::isError($method = $this->getBestAuthMethod())) {
                /* Return the PEAR_Error object from _getBestAuthMethod(). */
                return $method;
            }
        } else {
            $method = strtoupper($method);
            if (!array_key_exists($method, $this->auth_methods)) {
                return PEAR::raiseError("$method is not a supported authentication method");
            }
        }

        if (!isset($this->auth_methods[$method])) {
            return PEAR::raiseError("$method is not a supported authentication method");
        }

        if (!is_callable($this->auth_methods[$method], false)) {
            return PEAR::raiseError("$method authentication method cannot be called");
        }

        if (is_array($this->auth_methods[$method])) {
            list($object, $method) = $this->auth_methods[$method];
            $result = $object->{$method}($uid, $pwd, $authz, $this);
        } else {
            $func   = $this->auth_methods[$method];
            $result = $func($uid, $pwd, $authz, $this);
        }

        /* If an error was encountered, return the PEAR_Error object. */
        if (PEAR::isError($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Add a new authentication method.
     *
     * @param string $name     The authentication method name (e.g. 'PLAIN')
     * @param mixed  $callback The authentication callback (given as the name of a
     *                         function or as an (object, method name) array).
     * @param bool   $prepend  Should the new method be prepended to the list of
     *                         available methods?  This is the default behavior,
     *                         giving the new method the highest priority.
     *
     * @return mixed True on success or a PEAR_Error object on failure.
     *
     * @since 1.6.0
     */
    public function setAuthMethod($name, $callback, $prepend = true)
    {
        if (!is_string($name)) {
            return PEAR::raiseError('Method name is not a string');
        }

        if (!is_string($callback) && !is_array($callback)) {
            return PEAR::raiseError('Method callback must be string or array');
        }

        if (is_array($callback)) {
            if (!is_object($callback[0]) || !is_string($callback[1])) {
                return PEAR::raiseError('Bad mMethod callback array');
            }
        }

        if ($prepend) {
            $this->auth_methods = array_merge(
                array($name => $callback), $this->auth_methods
            );
        } else {
            $this->auth_methods[$name] = $callback;
        }

        return true;
    }

    /**
     * Authenticates the user using the DIGEST-MD5 method.
     *
     * @param string $uid   The userid to authenticate as.
     * @param string $pwd   The password to authenticate with.
     * @param string $authz The optional authorization proxy identifier.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.1.0
     */
    protected function authDigestMD5($uid, $pwd, $authz = '')
    {
        if (PEAR::isError($error = $this->put('AUTH', 'DIGEST-MD5'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->code === 503) {
                return true;
            }
            return $error;
        }

        $digest    = Auth_SASL::factory('digest-md5');
        $challenge = base64_decode($this->arguments[0]);
        $auth_str  = base64_encode(
            $digest->getResponse($uid, $pwd, $challenge, $this->host, "smtp", $authz)
        );

        if (PEAR::isError($error = $this->put($auth_str))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->parseResponse(334))) {
            return $error;
        }

        /* We don't use the protocol's third step because SMTP doesn't
         * allow subsequent authentication, so we just silently ignore
         * it. */
        if (PEAR::isError($error = $this->put(''))) {
            return $error;
        }
        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->parseResponse(235))) {
            return $error;
        }
    }

    /**
     * Authenticates the user using the CRAM-MD5 method.
     *
     * @param string $uid   The userid to authenticate as.
     * @param string $pwd   The password to authenticate with.
     * @param string $authz The optional authorization proxy identifier.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.1.0
     */
    protected function authCRAMMD5($uid, $pwd, $authz = '')
    {
        if (PEAR::isError($error = $this->put('AUTH', 'CRAM-MD5'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->code === 503) {
                return true;
            }
            return $error;
        }

        $challenge = base64_decode($this->arguments[0]);
        $cram      = Auth_SASL::factory('cram-md5');
        $auth_str  = base64_encode($cram->getResponse($uid, $pwd, $challenge));

        if (PEAR::isError($error = $this->put($auth_str))) {
            return $error;
        }

        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->parseResponse(235))) {
            return $error;
        }
    }

    /**
     * Authenticates the user using the LOGIN method.
     *
     * @param string $uid   The userid to authenticate as.
     * @param string $pwd   The password to authenticate with.
     * @param string $authz The optional authorization proxy identifier.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.1.0
     */
    protected function authLogin($uid, $pwd, $authz = '')
    {
        if (PEAR::isError($error = $this->put('AUTH', 'LOGIN'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->code === 503) {
                return true;
            }
            return $error;
        }

        if (PEAR::isError($error = $this->put(base64_encode($uid)))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->parseResponse(334))) {
            return $error;
        }

        if (PEAR::isError($error = $this->put(base64_encode($pwd)))) {
            return $error;
        }

        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->parseResponse(235))) {
            return $error;
        }

        return true;
    }

    /**
     * Authenticates the user using the PLAIN method.
     *
     * @param string $uid   The userid to authenticate as.
     * @param string $pwd   The password to authenticate with.
     * @param string $authz The optional authorization proxy identifier.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.1.0
     */
    protected function authPlain($uid, $pwd, $authz = '')
    {
        if (PEAR::isError($error = $this->put('AUTH', 'PLAIN'))) {
            return $error;
        }
        /* 334: Continue authentication request */
        if (PEAR::isError($error = $this->parseResponse(334))) {
            /* 503: Error: already authenticated */
            if ($this->code === 503) {
                return true;
            }
            return $error;
        }

        $auth_str = base64_encode($authz . chr(0) . $uid . chr(0) . $pwd);

        if (PEAR::isError($error = $this->put($auth_str))) {
            return $error;
        }

        /* 235: Authentication successful */
        if (PEAR::isError($error = $this->parseResponse(235))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the HELO command.
     *
     * @param string $domain The domain name to say we are.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function helo($domain)
    {
        if (PEAR::isError($error = $this->put('HELO', $domain))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250))) {
            return $error;
        }

        return true;
    }

    /**
     * Return the list of SMTP service extensions advertised by the server.
     *
     * @return array The list of SMTP service extensions.
     * @since 1.3
     */
    public function getServiceExtensions()
    {
        return $this->esmtp;
    }

    /**
     * Send the MAIL FROM: command.
     *
     * @param string $sender The sender (reverse path) to set.
     * @param string $params String containing additional MAIL parameters,
     *                       such as the NOTIFY flags defined by RFC 1891
     *                       or the VERP protocol.
     *
     *                       If $params is an array, only the 'verp' option
     *                       is supported.  If 'verp' is true, the XVERP
     *                       parameter is appended to the MAIL command.
     *                       If the 'verp' value is a string, the full
     *                       XVERP=value parameter is appended.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function mailFrom($sender, $params = null)
    {
        $args = "FROM:<$sender>";

        /* Support the deprecated array form of $params. */
        if (is_array($params) && isset($params['verp'])) {
            if ($params['verp'] === true) {
                $args .= ' XVERP';
            } elseif (trim($params['verp'])) {
                $args .= ' XVERP=' . $params['verp'];
            }
        } elseif (is_string($params) && !empty($params)) {
            $args .= ' ' . $params;
        }

        if (PEAR::isError($error = $this->put('MAIL', $args))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the RCPT TO: command.
     *
     * @param string $recipient The recipient (forward path) to add.
     * @param string $params    String containing additional RCPT parameters,
     *                          such as the NOTIFY flags defined by RFC 1891.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     *
     * @since 1.0
     */
    public function rcptTo($recipient, $params = null)
    {
        $args = "TO:<$recipient>";
        if (is_string($params)) {
            $args .= ' ' . $params;
        }

        if (PEAR::isError($error = $this->put('RCPT', $args))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(array(250, 251), $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Quote the data so that it meets SMTP standards.
     *
     * This is provided as a separate public function to facilitate
     * easier overloading for the cases where it is desirable to
     * customize the quoting behavior.
     *
     * @param string &$data The message text to quote. The string must be passed
     *                      by reference, and the text will be modified in place.
     *
     * @since 1.2
     */
    public function quotedata(&$data)
    {
        /* Because a single leading period (.) signifies an end to the
         * data, legitimate leading periods need to be "doubled" ('..'). */
        $data = preg_replace('/^\./m', '..', $data);

        /* Change Unix (\n) and Mac (\r) linefeeds into CRLF's (\r\n). */
        $data = preg_replace('/(?:\r\n|\n|\r(?!\n))/', "\r\n", $data);
    }

    /**
     * Send the DATA command.
     *
     * @param mixed  $data    The message data, either as a string or an open
     *                        file resource.
     * @param string $headers The message headers.  If $headers is provided,
     *                        $data is assumed to contain only body data.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function data($data, $headers = null)
    {
        /* Verify that $data is a supported type. */
        if (!is_string($data) && !is_resource($data)) {
            return PEAR::raiseError('Expected a string or file resource');
        }

        /* Start by considering the size of the optional headers string.  We
         * also account for the addition 4 character "\r\n\r\n" separator
         * sequence. */
        $size = (is_null($headers)) ? 0 : strlen($headers) + 4;

        if (is_resource($data)) {
            $stat = fstat($data);
            if ($stat === false) {
                return PEAR::raiseError('Failed to get file size');
            }
            $size += $stat['size'];
        } else {
            $size += strlen($data);
        }

        /* RFC 1870, section 3, subsection 3 states "a value of zero indicates
         * that no fixed maximum message size is in force".  Furthermore, it
         * says that if "the parameter is omitted no information is conveyed
         * about the server's fixed maximum message size". */
        $limit = (isset($this->esmtp['SIZE'])) ? $this->esmtp['SIZE'] : 0;
        if ($limit > 0 && $size >= $limit) {
            $this->disconnect();
            return PEAR::raiseError('Message size exceeds server limit');
        }

        /* Initiate the DATA command. */
        if (PEAR::isError($error = $this->put('DATA'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(354))) {
            return $error;
        }

        /* If we have a separate headers string, send it first. */
        if (!is_null($headers)) {
            $this->quotedata($headers);
            if (PEAR::isError($result = $this->send($headers . "\r\n\r\n"))) {
                return $result;
            }

            /* Subtract the headers size now that they've been sent. */
            $size -= strlen($headers) + 4;
        }

        /* Now we can send the message body data. */
        if (is_resource($data)) {
            /* Stream the contents of the file resource out over our socket
             * connection, line by line.  Each line must be run through the
             * quoting routine. */
            while (strlen($line = fread($data, 8192)) > 0) {
                /* If the last character is an newline, we need to grab the
                 * next character to check to see if it is a period. */
                while (!feof($data)) {
                    $char = fread($data, 1);
                    $line .= $char;
                    if ($char != "\n") {
                        break;
                    }
                }
                $this->quotedata($line);
                if (PEAR::isError($result = $this->send($line))) {
                    return $result;
                }
            }

             $last = $line;
        } else {
            /*
             * Break up the data by sending one chunk (up to 512k) at a time.
             * This approach reduces our peak memory usage.
             */
            for ($offset = 0; $offset < $size;) {
                $end = $offset + 512000;

                /*
                 * Ensure we don't read beyond our data size or span multiple
                 * lines.  quotedata() can't properly handle character data
                 * that's split across two line break boundaries.
                 */
                if ($end >= $size) {
                    $end = $size;
                } else {
                    for (; $end < $size; $end++) {
                        if ($data[$end] != "\n") {
                            break;
                        }
                    }
                }

                /* Extract our chunk and run it through the quoting routine. */
                $chunk = substr($data, $offset, $end - $offset);
                $this->quotedata($chunk);

                /* If we run into a problem along the way, abort. */
                if (PEAR::isError($result = $this->send($chunk))) {
                    return $result;
                }

                /* Advance the offset to the end of this chunk. */
                $offset = $end;
            }

            $last = $chunk;
        }

        /* Don't add another CRLF sequence if it's already in the data */
        $terminator = (substr($last, -2) == "\r\n" ? '' : "\r\n") . ".\r\n";

        /* Finally, send the DATA terminator sequence. */
        if (PEAR::isError($result = $this->send($terminator))) {
            return $result;
        }

        /* Verify that the data was successfully received by the server. */
        if (PEAR::isError($error = $this->parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the SEND FROM: command.
     *
     * @param string $path The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.2.6
     */
    public function sendFrom($path)
    {
        if (PEAR::isError($error = $this->put('SEND', "FROM:<$path>"))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the SOML FROM: command.
     *
     * @param string $path The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.2.6
     */
    public function somlFrom($path)
    {
        if (PEAR::isError($error = $this->put('SOML', "FROM:<$path>"))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the SAML FROM: command.
     *
     * @param string $path The reverse path to send.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.2.6
     */
    public function samlFrom($path)
    {
        if (PEAR::isError($error = $this->put('SAML', "FROM:<$path>"))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the RSET command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since  1.0
     */
    public function rset()
    {
        if (PEAR::isError($error = $this->put('RSET'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250, $this->pipelining))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the VRFY command.
     *
     * @param string $string The string to verify
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function vrfy($string)
    {
        /* Note: 251 is also a valid response code */
        if (PEAR::isError($error = $this->put('VRFY', $string))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(array(250, 252)))) {
            return $error;
        }

        return true;
    }

    /**
     * Send the NOOP command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @since 1.0
     */
    public function noop()
    {
        if (PEAR::isError($error = $this->put('NOOP'))) {
            return $error;
        }
        if (PEAR::isError($error = $this->parseResponse(250))) {
            return $error;
        }

        return true;
    }

    /**
     * Backwards-compatibility method.  identifySender()'s functionality is
     * now handled internally.
     *
     * @return boolean This method always return true.
     *
     * @since 1.0
     */
    public function identifySender()
    {
        return true;
    }
}
