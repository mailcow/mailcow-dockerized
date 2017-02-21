<?php
/**
 * This file contains the Net_Sieve class.
 *
 * PHP version 5
 *
 * +-----------------------------------------------------------------------+
 * | All rights reserved.                                                  |
 * |                                                                       |
 * | Redistribution and use in source and binary forms, with or without    |
 * | modification, are permitted provided that the following conditions    |
 * | are met:                                                              |
 * |                                                                       |
 * | o Redistributions of source code must retain the above copyright      |
 * |   notice, this list of conditions and the following disclaimer.       |
 * | o Redistributions in binary form must reproduce the above copyright   |
 * |   notice, this list of conditions and the following disclaimer in the |
 * |   documentation and/or other materials provided with the distribution.|
 * |                                                                       |
 * | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   |
 * | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     |
 * | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR |
 * | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  |
 * | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, |
 * | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      |
 * | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
 * | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY |
 * | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   |
 * | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE |
 * | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  |
 * +-----------------------------------------------------------------------+
 *
 * @category  Networking
 * @package   Net_Sieve
 * @author    Richard Heyes <richard@phpguru.org>
 * @author    Damian Fernandez Sosa <damlists@cnba.uba.ar>
 * @author    Anish Mistry <amistry@am-productions.biz>
 * @author    Jan Schneider <jan@horde.org>
 * @copyright 2002-2003 Richard Heyes
 * @copyright 2006-2008 Anish Mistry
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD
 * @link      http://pear.php.net/package/Net_Sieve
 */

require_once 'PEAR.php';
require_once 'Net/Socket.php';

/**
 * TODO
 *
 * o supportsAuthMech()
 */

/**
 * Disconnected state
 * @const NET_SIEVE_STATE_DISCONNECTED
 */
define('NET_SIEVE_STATE_DISCONNECTED', 1, true);

/**
 * Authorisation state
 * @const NET_SIEVE_STATE_AUTHORISATION
 */
define('NET_SIEVE_STATE_AUTHORISATION', 2, true);

/**
 * Transaction state
 * @const NET_SIEVE_STATE_TRANSACTION
 */
define('NET_SIEVE_STATE_TRANSACTION', 3, true);


/**
 * A class for talking to the timsieved server which comes with Cyrus IMAP.
 *
 * @category  Networking
 * @package   Net_Sieve
 * @author    Richard Heyes <richard@phpguru.org>
 * @author    Damian Fernandez Sosa <damlists@cnba.uba.ar>
 * @author    Anish Mistry <amistry@am-productions.biz>
 * @author    Jan Schneider <jan@horde.org>
 * @copyright 2002-2003 Richard Heyes
 * @copyright 2006-2008 Anish Mistry
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Net_Sieve
 * @link      http://tools.ietf.org/html/rfc5228 RFC 5228 (Sieve: An Email
 *            Filtering Language)
 * @link      http://tools.ietf.org/html/rfc5804 RFC 5804 A Protocol for
 *            Remotely Managing Sieve Scripts
 */
class Net_Sieve
{
    /**
     * The authentication methods this class supports.
     *
     * Can be overwritten if having problems with certain methods.
     *
     * @var array
     */
    var $supportedAuthMethods = array(
        'DIGEST-MD5',
        'CRAM-MD5',
        'EXTERNAL',
        'PLAIN' ,
        'LOGIN'
    );

    /**
     * SASL authentication methods that require Auth_SASL.
     *
     * @var array
     */
    var $supportedSASLAuthMethods = array('DIGEST-MD5', 'CRAM-MD5');

    /**
     * The socket handle.
     *
     * @var resource
     */
    var $_sock;

    /**
     * Parameters and connection information.
     *
     * @var array
     */
    var $_data;

    /**
     * Current state of the connection.
     *
     * One of the NET_SIEVE_STATE_* constants.
     *
     * @var integer
     */
    var $_state;

    /**
     * PEAR object to avoid strict warnings.
     *
     * @var PEAR_Error
     */
    var $_pear;

    /**
     * Constructor error.
     *
     * @var PEAR_Error
     */
    var $_error;

    /**
     * Whether to enable debugging.
     *
     * @var boolean
     */
    var $_debug = false;

    /**
     * Debug output handler.
     *
     * This has to be a valid callback.
     *
     * @var string|array
     */
    var $_debug_handler = null;

    /**
     * Whether to pick up an already established connection.
     *
     * @var boolean
     */
    var $_bypassAuth = false;

    /**
     * Whether to use TLS if available.
     *
     * @var boolean
     */
    var $_useTLS = true;

    /**
     * Additional options for stream_context_create().
     *
     * @var array
     */
    var $_options = null;

    /**
     * Maximum number of referral loops
     *
     * @var array
     */
    var $_maxReferralCount = 15;

    /**
     * Constructor.
     *
     * Sets up the object, connects to the server and logs in. Stores any
     * generated error in $this->_error, which can be retrieved using the
     * getError() method.
     *
     * @param string  $user       Login username.
     * @param string  $pass       Login password.
     * @param string  $host       Hostname of server.
     * @param string  $port       Port of server.
     * @param string  $logintype  Type of login to perform (see
     *                            $supportedAuthMethods).
     * @param string  $euser      Effective user. If authenticating as an
     *                            administrator, login as this user.
     * @param boolean $debug      Whether to enable debugging (@see setDebug()).
     * @param string  $bypassAuth Skip the authentication phase. Useful if the
     *                            socket is already open.
     * @param boolean $useTLS     Use TLS if available.
     * @param array   $options    Additional options for
     *                            stream_context_create().
     * @param mixed   $handler    A callback handler for the debug output.
     */
    function __construct($user = null, $pass  = null, $host = 'localhost',
        $port = 2000, $logintype = '', $euser = '',
        $debug = false, $bypassAuth = false, $useTLS = true,
        $options = null, $handler = null
    ) {
        $this->_pear = new PEAR();
        $this->_state             = NET_SIEVE_STATE_DISCONNECTED;
        $this->_data['user']      = $user;
        $this->_data['pass']      = $pass;
        $this->_data['host']      = $host;
        $this->_data['port']      = $port;
        $this->_data['logintype'] = $logintype;
        $this->_data['euser']     = $euser;
        $this->_sock              = new Net_Socket();
        $this->_bypassAuth        = $bypassAuth;
        $this->_useTLS            = $useTLS;
        $this->_options           = (array) $options;
        $this->setDebug($debug, $handler);

        /* Try to include the Auth_SASL package.  If the package is not
         * available, we disable the authentication methods that depend upon
         * it. */
        if ((@include_once 'Auth/SASL.php') === false) {
            $this->_debug('Auth_SASL not present');
            $this->supportedAuthMethods = array_diff(
                $this->supportedAuthMethods,
                $this->supportedSASLAuthMethods
            );
        }

        if (strlen($user) && strlen($pass)) {
            $this->_error = $this->_handleConnectAndLogin();
        }
    }

    /**
     * Returns any error that may have been generated in the constructor.
     *
     * @return boolean|PEAR_Error  False if no error, PEAR_Error otherwise.
     */
    function getError()
    {
        return is_a($this->_error, 'PEAR_Error') ? $this->_error : false;
    }

    /**
     * Sets the debug state and handler function.
     *
     * @param boolean $debug   Whether to enable debugging.
     * @param string  $handler A custom debug handler. Must be a valid callback.
     *
     * @return void
     */
    function setDebug($debug = true, $handler = null)
    {
        $this->_debug = $debug;
        $this->_debug_handler = $handler;
    }

    /**
     * Connects to the server and logs in.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function _handleConnectAndLogin()
    {
        $res = $this->connect($this->_data['host'], $this->_data['port'], $this->_options, $this->_useTLS);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        if ($this->_bypassAuth === false) {
            $res = $this->login($this->_data['user'], $this->_data['pass'], $this->_data['logintype'], $this->_data['euser'], $this->_bypassAuth);
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }
        return true;
    }

    /**
     * Handles connecting to the server and checks the response validity.
     *
     * @param string  $host    Hostname of server.
     * @param string  $port    Port of server.
     * @param array   $options List of options to pass to
     *                         stream_context_create().
     * @param boolean $useTLS  Use TLS if available.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function connect($host, $port, $options = null, $useTLS = true)
    {
        $this->_data['host'] = $host;
        $this->_data['port'] = $port;
        $this->_useTLS       = $useTLS;
        if (is_array($options)) {
            $this->_options = array_merge($this->_options, $options);
        }

        if (NET_SIEVE_STATE_DISCONNECTED != $this->_state) {
            return $this->_pear->raiseError('Not currently in DISCONNECTED state', 1);
        }

        $res = $this->_sock->connect($host, $port, false, 5, $options);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        if ($this->_bypassAuth) {
            $this->_state = NET_SIEVE_STATE_TRANSACTION;
        } else {
            $this->_state = NET_SIEVE_STATE_AUTHORISATION;
            $res = $this->_doCmd();
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }

        // Explicitly ask for the capabilities in case the connection is
        // picked up from an existing connection.
        $res = $this->_cmdCapability();
        if (is_a($res, 'PEAR_Error')) {
            return $this->_pear->raiseError(
                'Failed to connect, server said: ' . $res->getMessage(), 2
            );
        }

        // Check if we can enable TLS via STARTTLS.
        if ($useTLS && !empty($this->_capability['starttls'])
            && function_exists('stream_socket_enable_crypto')
        ) {
            $res = $this->_startTLS();
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }

        return true;
    }

    /**
     * Disconnect from the Sieve server.
     *
     * @param boolean $sendLogoutCMD Whether to send LOGOUT command before
     *                               disconnecting.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function disconnect($sendLogoutCMD = true)
    {
        return $this->_cmdLogout($sendLogoutCMD);
    }

    /**
     * Logs into server.
     *
     * @param string  $user       Login username.
     * @param string  $pass       Login password.
     * @param string  $logintype  Type of login method to use.
     * @param string  $euser      Effective UID (perform on behalf of $euser).
     * @param boolean $bypassAuth Do not perform authentication.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function login($user, $pass, $logintype = null, $euser = '', $bypassAuth = false)
    {
        $this->_data['user']      = $user;
        $this->_data['pass']      = $pass;
        $this->_data['logintype'] = $logintype;
        $this->_data['euser']     = $euser;
        $this->_bypassAuth        = $bypassAuth;

        if (NET_SIEVE_STATE_AUTHORISATION != $this->_state) {
            return $this->_pear->raiseError('Not currently in AUTHORISATION state', 1);
        }

        if (!$bypassAuth ) {
            $res = $this->_cmdAuthenticate($user, $pass, $logintype, $euser);
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }
        $this->_state = NET_SIEVE_STATE_TRANSACTION;

        return true;
    }

    /**
     * Returns an indexed array of scripts currently on the server.
     *
     * @return array  Indexed array of scriptnames.
     */
    function listScripts()
    {
        if (is_array($scripts = $this->_cmdListScripts())) {
            return $scripts[0];
        } else {
            return $scripts;
        }
    }

    /**
     * Returns the active script.
     *
     * @return string  The active scriptname.
     */
    function getActive()
    {
        if (is_array($scripts = $this->_cmdListScripts())) {
            return $scripts[1];
        }
    }

    /**
     * Sets the active script.
     *
     * @param string $scriptname The name of the script to be set as active.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function setActive($scriptname)
    {
        return $this->_cmdSetActive($scriptname);
    }

    /**
     * Retrieves a script.
     *
     * @param string $scriptname The name of the script to be retrieved.
     *
     * @return string  The script on success, PEAR_Error on failure.
    */
    function getScript($scriptname)
    {
        return $this->_cmdGetScript($scriptname);
    }

    /**
     * Adds a script to the server.
     *
     * @param string  $scriptname Name of the script.
     * @param string  $script     The script content.
     * @param boolean $makeactive Whether to make this the active script.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function installScript($scriptname, $script, $makeactive = false)
    {
        $res = $this->_cmdPutScript($scriptname, $script);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        if ($makeactive) {
            return $this->_cmdSetActive($scriptname);
        }
        return true;
    }

    /**
     * Removes a script from the server.
     *
     * @param string $scriptname Name of the script.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function removeScript($scriptname)
    {
        return $this->_cmdDeleteScript($scriptname);
    }

    /**
     * Checks if the server has space to store the script by the server.
     *
     * @param string  $scriptname The name of the script to mark as active.
     * @param integer $size       The size of the script.
     *
     * @return boolean|PEAR_Error  True if there is space, PEAR_Error otherwise.
     *
     * @todo Rename to hasSpace()
     */
    function haveSpace($scriptname, $size)
    {
        if (NET_SIEVE_STATE_TRANSACTION != $this->_state) {
            return $this->_pear->raiseError('Not currently in TRANSACTION state', 1);
        }

        $res = $this->_doCmd(sprintf('HAVESPACE %s %d', $this->_escape($scriptname), $size));
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        return true;
    }

    /**
     * Returns the list of extensions the server supports.
     *
     * @return array  List of extensions or PEAR_Error on failure.
     */
    function getExtensions()
    {
        if (NET_SIEVE_STATE_DISCONNECTED == $this->_state) {
            return $this->_pear->raiseError('Not currently connected', 7);
        }
        return $this->_capability['extensions'];
    }

    /**
     * Returns whether the server supports an extension.
     *
     * @param string $extension The extension to check.
     *
     * @return boolean  Whether the extension is supported or PEAR_Error on
     *                  failure.
     */
    function hasExtension($extension)
    {
        if (NET_SIEVE_STATE_DISCONNECTED == $this->_state) {
            return $this->_pear->raiseError('Not currently connected', 7);
        }

        $extension = trim($this->_toUpper($extension));
        if (is_array($this->_capability['extensions'])) {
            foreach ($this->_capability['extensions'] as $ext) {
                if ($ext == $extension) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the list of authentication methods the server supports.
     *
     * @return array  List of authentication methods or PEAR_Error on failure.
     */
    function getAuthMechs()
    {
        if (NET_SIEVE_STATE_DISCONNECTED == $this->_state) {
            return $this->_pear->raiseError('Not currently connected', 7);
        }
        return $this->_capability['sasl'];
    }

    /**
     * Returns whether the server supports an authentication method.
     *
     * @param string $method The method to check.
     *
     * @return boolean  Whether the method is supported or PEAR_Error on
     *                  failure.
     */
    function hasAuthMech($method)
    {
        if (NET_SIEVE_STATE_DISCONNECTED == $this->_state) {
            return $this->_pear->raiseError('Not currently connected', 7);
        }

        $method = trim($this->_toUpper($method));
        if (is_array($this->_capability['sasl'])) {
            foreach ($this->_capability['sasl'] as $sasl) {
                if ($sasl == $method) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handles the authentication using any known method.
     *
     * @param string $uid        The userid to authenticate as.
     * @param string $pwd        The password to authenticate with.
     * @param string $userMethod The method to use. If empty, the class chooses
     *                           the best (strongest) available method.
     * @param string $euser      The effective uid to authenticate as.
     *
     * @return void
     */
    function _cmdAuthenticate($uid, $pwd, $userMethod = null, $euser = '')
    {
        $method = $this->_getBestAuthMethod($userMethod);
        if (is_a($method, 'PEAR_Error')) {
            return $method;
        }
        switch ($method) {
        case 'DIGEST-MD5':
            return $this->_authDigestMD5($uid, $pwd, $euser);
        case 'CRAM-MD5':
            $result = $this->_authCRAMMD5($uid, $pwd, $euser);
            break;
        case 'LOGIN':
            $result = $this->_authLOGIN($uid, $pwd, $euser);
            break;
        case 'PLAIN':
            $result = $this->_authPLAIN($uid, $pwd, $euser);
            break;
        case 'EXTERNAL':
            $result = $this->_authEXTERNAL($uid, $pwd, $euser);
            break;
        default :
            $result = $this->_pear->raiseError(
                $method . ' is not a supported authentication method'
            );
            break;
        }

        $res = $this->_doCmd();
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        // Query the server capabilities again now that we are authenticated.
        if ($this->_pear->isError($res = $this->_cmdCapability())) {
            return $this->_pear->raiseError(
                'Failed to connect, server said: ' . $res->getMessage(), 2
            );
        }

        return $result;
    }

    /**
     * Authenticates the user using the PLAIN method.
     *
     * @param string $user  The userid to authenticate as.
     * @param string $pass  The password to authenticate with.
     * @param string $euser The effective uid to authenticate as.
     *
     * @return void
     */
    function _authPLAIN($user, $pass, $euser)
    {
        return $this->_sendCmd(
            sprintf(
                'AUTHENTICATE "PLAIN" "%s"',
                base64_encode($euser . chr(0) . $user . chr(0) . $pass)
            )
        );
    }

    /**
     * Authenticates the user using the LOGIN method.
     *
     * @param string $user  The userid to authenticate as.
     * @param string $pass  The password to authenticate with.
     * @param string $euser The effective uid to authenticate as. Not used.
     *
     * @return void
     */
    function _authLOGIN($user, $pass, $euser)
    {
        $result = $this->_sendCmd('AUTHENTICATE "LOGIN"');
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        $result = $this->_doCmd('"' . base64_encode($user) . '"', true);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        return $this->_doCmd('"' . base64_encode($pass) . '"', true);
    }

    /**
     * Authenticates the user using the CRAM-MD5 method.
     *
     * @param string $user  The userid to authenticate as.
     * @param string $pass  The password to authenticate with.
     * @param string $euser The effective uid to authenticate as. Not used.
     *
     * @return void
     */
    function _authCRAMMD5($user, $pass, $euser)
    {
        $challenge = $this->_doCmd('AUTHENTICATE "CRAM-MD5"', true);
        if (is_a($challenge, 'PEAR_Error')) {
            return $challenge;
        }

        $auth_sasl = new Auth_SASL;
        $cram      = $auth_sasl->factory('crammd5');
        $challenge = base64_decode(trim($challenge));
        $response  = $cram->getResponse($user, $pass, $challenge);

        if (is_a($response, 'PEAR_Error')) {
            return $response;
        }

        return $this->_sendStringResponse(base64_encode($response));
    }

    /**
     * Authenticates the user using the DIGEST-MD5 method.
     *
     * @param string $user  The userid to authenticate as.
     * @param string $pass  The password to authenticate with.
     * @param string $euser The effective uid to authenticate as.
     *
     * @return void
     */
    function _authDigestMD5($user, $pass, $euser)
    {
        $challenge = $this->_doCmd('AUTHENTICATE "DIGEST-MD5"', true);
        if (is_a($challenge, 'PEAR_Error')) {
            return $challenge;
        }

        $auth_sasl = new Auth_SASL;
        $digest    = $auth_sasl->factory('digestmd5');
        $challenge = base64_decode(trim($challenge));

        // @todo Really 'localhost'?
        $response = $digest->getResponse($user, $pass, $challenge, 'localhost', 'sieve', $euser);
        if (is_a($response, 'PEAR_Error')) {
            return $response;
        }

        $result = $this->_sendStringResponse(base64_encode($response));
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        $result = $this->_doCmd('', true);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }
        if ($this->_toUpper(substr($result, 0, 2)) == 'OK') {
            return;
        }

        /* We don't use the protocol's third step because SIEVE doesn't allow
         * subsequent authentication, so we just silently ignore it. */
        $result = $this->_sendStringResponse('');
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return $this->_doCmd();
    }

    /**
     * Authenticates the user using the EXTERNAL method.
     *
     * @param string $user  The userid to authenticate as.
     * @param string $pass  The password to authenticate with.
     * @param string $euser The effective uid to authenticate as.
     *
     * @return void
     *
     * @since  1.1.7
     */
    function _authEXTERNAL($user, $pass, $euser)
    {
        $cmd = sprintf(
            'AUTHENTICATE "EXTERNAL" "%s"',
            base64_encode(strlen($euser) ? $euser : $user)
        );
        return $this->_sendCmd($cmd);
    }

    /**
     * Removes a script from the server.
     *
     * @param string $scriptname Name of the script to delete.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function _cmdDeleteScript($scriptname)
    {
        if (NET_SIEVE_STATE_TRANSACTION != $this->_state) {
            return $this->_pear->raiseError('Not currently in AUTHORISATION state', 1);
        }

        $res = $this->_doCmd(sprintf('DELETESCRIPT %s', $this->_escape($scriptname)));
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        return true;
    }

    /**
     * Retrieves the contents of the named script.
     *
     * @param string $scriptname Name of the script to retrieve.
     *
     * @return string  The script if successful, PEAR_Error otherwise.
     */
    function _cmdGetScript($scriptname)
    {
        if (NET_SIEVE_STATE_TRANSACTION != $this->_state) {
            return $this->_pear->raiseError('Not currently in AUTHORISATION state', 1);
        }

        $res = $this->_doCmd(sprintf('GETSCRIPT %s', $this->_escape($scriptname)));
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        return preg_replace('/^{[0-9]+}\r\n/', '', $res);
    }

    /**
     * Sets the active script, i.e. the one that gets run on new mail by the
     * server.
     *
     * @param string $scriptname The name of the script to mark as active.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
    */
    function _cmdSetActive($scriptname)
    {
        if (NET_SIEVE_STATE_TRANSACTION != $this->_state) {
            return $this->_pear->raiseError('Not currently in AUTHORISATION state', 1);
        }

        $res = $this->_doCmd(sprintf('SETACTIVE %s', $this->_escape($scriptname)));
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        return true;
    }

    /**
     * Returns the list of scripts on the server.
     *
     * @return array  An array with the list of scripts in the first element
     *                and the active script in the second element on success,
     *                PEAR_Error otherwise.
     */
    function _cmdListScripts()
    {
        if (NET_SIEVE_STATE_TRANSACTION != $this->_state) {
            return $this->_pear->raiseError('Not currently in AUTHORISATION state', 1);
        }

        $res = $this->_doCmd('LISTSCRIPTS');
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        $scripts = array();
        $activescript = null;
        $res = explode("\r\n", $res);
        foreach ($res as $value) {
            if (preg_match('/^"(.*)"( ACTIVE)?$/i', $value, $matches)) {
                $script_name = stripslashes($matches[1]);
                $scripts[] = $script_name;
                if (!empty($matches[2])) {
                    $activescript = $script_name;
                }
            }
        }

        return array($scripts, $activescript);
    }

    /**
     * Adds a script to the server.
     *
     * @param string $scriptname Name of the new script.
     * @param string $scriptdata The new script.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function _cmdPutScript($scriptname, $scriptdata)
    {
        if (NET_SIEVE_STATE_TRANSACTION != $this->_state) {
            return $this->_pear->raiseError('Not currently in AUTHORISATION state', 1);
        }

        $stringLength = $this->_getLineLength($scriptdata);
        $command      = sprintf(
            "PUTSCRIPT %s {%d+}\r\n%s",
            $this->_escape($scriptname),
            $stringLength,
            $scriptdata
        );

        $res = $this->_doCmd($command);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        return true;
    }

    /**
     * Logs out of the server and terminates the connection.
     *
     * @param boolean $sendLogoutCMD Whether to send LOGOUT command before
     *                               disconnecting.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function _cmdLogout($sendLogoutCMD = true)
    {
        if (NET_SIEVE_STATE_DISCONNECTED == $this->_state) {
            return $this->_pear->raiseError('Not currently connected', 1);
        }

        if ($sendLogoutCMD) {
            $res = $this->_doCmd('LOGOUT');
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }

        $this->_sock->disconnect();
        $this->_state = NET_SIEVE_STATE_DISCONNECTED;

        return true;
    }

    /**
     * Sends the CAPABILITY command
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function _cmdCapability()
    {
        if (NET_SIEVE_STATE_DISCONNECTED == $this->_state) {
            return $this->_pear->raiseError('Not currently connected', 1);
        }
        $res = $this->_doCmd('CAPABILITY');
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        $this->_parseCapability($res);
        return true;
    }

    /**
     * Parses the response from the CAPABILITY command and stores the result
     * in $_capability.
     *
     * @param string $data The response from the capability command.
     *
     * @return void
     */
    function _parseCapability($data)
    {
        // Clear the cached capabilities.
        $this->_capability = array('sasl' => array(),
                                   'extensions' => array());

        $data = preg_split('/\r?\n/', $this->_toUpper($data), -1, PREG_SPLIT_NO_EMPTY);

        for ($i = 0; $i < count($data); $i++) {
            if (!preg_match('/^"([A-Z]+)"( "(.*)")?$/', $data[$i], $matches)) {
                continue;
            }
            switch ($matches[1]) {
            case 'IMPLEMENTATION':
                $this->_capability['implementation'] = $matches[3];
                break;

            case 'SASL':
                $this->_capability['sasl'] = preg_split('/\s+/', $matches[3]);
                break;

            case 'SIEVE':
                $this->_capability['extensions'] = preg_split('/\s+/', $matches[3]);
                break;

            case 'STARTTLS':
                $this->_capability['starttls'] = true;
                break;
            }
        }
    }

    /**
     * Sends a command to the server
     *
     * @param string $cmd The command to send.
     *
     * @return void
     */
    function _sendCmd($cmd)
    {
        $status = $this->_sock->getStatus();
        if (is_a($status, 'PEAR_Error') || $status['eof']) {
            return $this->_pear->raiseError('Failed to write to socket: connection lost');
        }
        $error = $this->_sock->write($cmd . "\r\n");
        if (is_a($error, 'PEAR_Error')) {
            return $this->_pear->raiseError(
                'Failed to write to socket: ' . $error->getMessage()
            );
        }
        $this->_debug("C: $cmd");
    }

    /**
     * Sends a string response to the server.
     *
     * @param string $str The string to send.
     *
     * @return void
     */
    function _sendStringResponse($str)
    {
        return $this->_sendCmd('{' . $this->_getLineLength($str) . "+}\r\n" . $str);
    }

    /**
     * Receives a single line from the server.
     *
     * @return string  The server response line.
     */
    function _recvLn()
    {
        $lastline = $this->_sock->gets(8192);
        if (is_a($lastline, 'PEAR_Error')) {
            return $this->_pear->raiseError(
                'Failed to read from socket: ' . $lastline->getMessage()
            );
        }

        $lastline = rtrim($lastline);
        $this->_debug("S: $lastline");

        if ($lastline === '') {
            return $this->_pear->raiseError('Failed to read from socket');
        }

        return $lastline;
    }

    /**
     * Receives a number of bytes from the server.
     *
     * @param integer $length Number of bytes to read.
     *
     * @return string The server response.
     */
    function _recvBytes($length)
    {
        $response = '';
        $response_length = 0;
        while ($response_length < $length) {
            $response .= $this->_sock->read($length - $response_length);
            $response_length = $this->_getLineLength($response);
        }
        $this->_debug('S: ' . rtrim($response));
        return $response;
    }

    /**
     * Send a command and retrieves a response from the server.
     *
     * @param string  $cmd  The command to send.
     * @param boolean $auth Whether this is an authentication command.
     *
     * @return string|PEAR_Error Reponse string if an OK response, PEAR_Error
     *                           if a NO response.
     */
    function _doCmd($cmd = '', $auth = false)
    {
        $referralCount = 0;
        while ($referralCount < $this->_maxReferralCount) {
            if (strlen($cmd)) {
                $error = $this->_sendCmd($cmd);
                if (is_a($error, 'PEAR_Error')) {
                    return $error;
                }
            }

            $response = '';
            while (true) {
                $line = $this->_recvLn();
                if (is_a($line, 'PEAR_Error')) {
                    return $line;
                }

                if (preg_match('/^(OK|NO)/i', $line, $tag)) {
                    // Check for string literal message.
                    if (preg_match('/{([0-9]+)}$/', $line, $matches)) {
                        $line = substr($line, 0, -(strlen($matches[1]) + 2))
                            . str_replace(
                                "\r\n", ' ', $this->_recvBytes($matches[1] + 2)
                            );
                    }

                    if ('OK' == $this->_toUpper($tag[1])) {
                        $response .= $line;
                        return rtrim($response);
                    }

                    return $this->_pear->raiseError(trim($response . substr($line, 2)), 3);
                }

                if (preg_match('/^BYE/i', $line)) {
                    $error = $this->disconnect(false);
                    if (is_a($error, 'PEAR_Error')) {
                        return $this->_pear->raiseError(
                            'Cannot handle BYE, the error was: '
                            . $error->getMessage(),
                            4
                        );
                    }
                    // Check for referral, then follow it.  Otherwise, carp an
                    // error.
                    if (preg_match('/^bye \(referral "(sieve:\/\/)?([^"]+)/i', $line, $matches)) {
                        // Replace the old host with the referral host
                        // preserving any protocol prefix.
                        $this->_data['host'] = preg_replace(
                            '/\w+(?!(\w|\:\/\/)).*/', $matches[2],
                            $this->_data['host']
                        );
                        $error = $this->_handleConnectAndLogin();
                        if (is_a($error, 'PEAR_Error')) {
                            return $this->_pear->raiseError(
                                'Cannot follow referral to '
                                . $this->_data['host'] . ', the error was: '
                                . $error->getMessage(),
                                5
                            );
                        }
                        break;
                    }
                    return $this->_pear->raiseError(trim($response . $line), 6);
                }

                if (preg_match('/^{([0-9]+)}/', $line, $matches)) {
                    // Matches literal string responses.
                    $line = $this->_recvBytes($matches[1] + 2);
                    if (!$auth) {
                        // Receive the pending OK only if we aren't
                        // authenticating since string responses during
                        // authentication don't need an OK.
                        $this->_recvLn();
                    }
                    return $line;
                }

                if ($auth) {
                    // String responses during authentication don't need an
                    // OK.
                    $response .= $line;
                    return rtrim($response);
                }

                $response .= $line . "\r\n";
                $referralCount++;
            }
        }

        return $this->_pear->raiseError('Max referral count (' . $referralCount . ') reached. Cyrus murder loop error?', 7);
    }

    /**
     * Returns the name of the best authentication method that the server
     * has advertised.
     *
     * @param string $userMethod Only consider this method as available.
     *
     * @return string  The name of the best supported authentication method or
     *                 a PEAR_Error object on failure.
     */
    function _getBestAuthMethod($userMethod = null)
    {
        if (!isset($this->_capability['sasl'])) {
            return $this->_pear->raiseError('This server doesn\'t support any authentication methods. SASL problem?');
        }
        if (!$this->_capability['sasl']) {
            return $this->_pear->raiseError('This server doesn\'t support any authentication methods.');
        }

        if ($userMethod) {
            if (in_array($userMethod, $this->_capability['sasl'])) {
                return $userMethod;
            }

            $msg = 'No supported authentication method found. The server supports these methods: %s, but we want to use: %s';
            return $this->_pear->raiseError(
                sprintf($msg, implode(', ', $this->_capability['sasl']), $userMethod)
            );
        }

        foreach ($this->supportedAuthMethods as $method) {
            if (in_array($method, $this->_capability['sasl'])) {
                return $method;
            }
        }

        $msg = 'No supported authentication method found. The server supports these methods: %s, but we only support: %s';
        return $this->_pear->raiseError(
            sprintf($msg, implode(', ', $this->_capability['sasl']), implode(', ', $this->supportedAuthMethods))
        );
    }

    /**
     * Starts a TLS connection.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function _startTLS()
    {
        $res = $this->_doCmd('STARTTLS');
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }

        if (isset($this->_options['ssl']['crypto_method'])) {
            $crypto_method = $this->_options['ssl']['crypto_method'];
        }
        else {
            // There is no flag to enable all TLS methods. Net_SMTP
            // handles enabling TLS similarly.
            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT
                | @STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | @STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        if (!stream_socket_enable_crypto($this->_sock->fp, true, $crypto_method)) {
            return $this->_pear->raiseError('Failed to establish TLS connection', 2);
        }

        $this->_debug('STARTTLS negotiation successful');

        // The server should be sending a CAPABILITY response after
        // negotiating TLS. Read it, and ignore if it doesn't.
        // Unfortunately old Cyrus versions are broken and don't send a
        // CAPABILITY response, thus we would wait here forever. Parse the
        // Cyrus version and work around this broken behavior.
        if (!preg_match('/^CYRUS TIMSIEVED V([0-9.]+)/', $this->_capability['implementation'], $matches)
            || version_compare($matches[1], '2.3.10', '>=')
        ) {
            $this->_doCmd();
        }

        // Query the server capabilities again now that we are under
        // encryption.
        $res = $this->_cmdCapability();
        if (is_a($res, 'PEAR_Error')) {
            return $this->_pear->raiseError(
                'Failed to connect, server said: ' . $res->getMessage(), 2
            );
        }

        return true;
    }

    /**
     * Returns the length of a string.
     *
     * @param string $string A string.
     *
     * @return integer  The length of the string.
     */
    function _getLineLength($string)
    {
        if (extension_loaded('mbstring')) {
            return mb_strlen($string, 'latin1');
        } else {
            return strlen($string);
        }
    }

    /**
     * Locale independant strtoupper() implementation.
     *
     * @param string $string The string to convert to lowercase.
     *
     * @return string  The lowercased string, based on ASCII encoding.
     */
    function _toUpper($string)
    {
        $language = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'C');
        $string = strtoupper($string);
        setlocale(LC_CTYPE, $language);
        return $string;
    }

    /**
     * Converts strings into RFC's quoted-string or literal-c2s form.
     *
     * @param string $string The string to convert.
     *
     * @return string Result string.
     */
    function _escape($string)
    {
        // Some implementations don't allow UTF-8 characters in quoted-string,
        // use literal-c2s.
        if (preg_match('/[^\x01-\x09\x0B-\x0C\x0E-\x7F]/', $string)) {
            return sprintf("{%d+}\r\n%s", $this->_getLineLength($string), $string);
        }

        return '"' . addcslashes($string, '\\"') . '"';
    }

    /**
     * Write debug text to the current debug output handler.
     *
     * @param string $message Debug message text.
     *
     * @return void
     */
    function _debug($message)
    {
        if ($this->_debug) {
            if ($this->_debug_handler) {
                call_user_func_array($this->_debug_handler, array(&$this, $message));
            } else {
                echo "$message\n";
            }
        }
    }
}
