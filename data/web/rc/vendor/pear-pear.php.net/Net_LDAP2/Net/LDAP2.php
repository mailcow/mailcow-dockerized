<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2 interface class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Tarjej Huse <tarjei@bergfald.no>
* @author    Jan Wagner <wagner@netsols.de>
* @author    Del <del@babel.com.au>
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2003-2007 Tarjej Huse, Jan Wagner, Del Elson, Benedikt Hallinger
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* Package includes.
*/
require_once 'PEAR.php';
require_once 'Net/LDAP2/RootDSE.php';
require_once 'Net/LDAP2/Schema.php';
require_once 'Net/LDAP2/Entry.php';
require_once 'Net/LDAP2/Search.php';
require_once 'Net/LDAP2/Util.php';
require_once 'Net/LDAP2/Filter.php';
require_once 'Net/LDAP2/LDIF.php';
require_once 'Net/LDAP2/SchemaCache.interface.php';
require_once 'Net/LDAP2/SimpleFileSchemaCache.php';

/**
*  Error constants for errors that are not LDAP errors.
*/
define('NET_LDAP2_ERROR', 1000);

/**
* Net_LDAP2 Version
*/
define('NET_LDAP2_VERSION', '2.1.0');

/**
* Net_LDAP2 - manipulate LDAP servers the right way!
*
* @category  Net
* @package   Net_LDAP2
* @author    Tarjej Huse <tarjei@bergfald.no>
* @author    Jan Wagner <wagner@netsols.de>
* @author    Del <del@babel.com.au>
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2003-2007 Tarjej Huse, Jan Wagner, Del Elson, Benedikt Hallinger
* @license   http://www.gnu.org/copyleft/lesser.html LGPL
* @link      http://pear.php.net/package/Net_LDAP2/
*/
class Net_LDAP2 extends PEAR
{
    /**
    * Class configuration array
    *
    * host     = the ldap host to connect to
    *            (may be an array of several hosts to try)
    * port     = the server port
    * version  = ldap version (defaults to v 3)
    * starttls = when set, ldap_start_tls() is run after connecting.
    * bindpw   = no explanation needed
    * binddn   = the DN to bind as.
    * basedn   = ldap base
    * options  = hash of ldap options to set (opt => val)
    * filter   = default search filter
    * scope    = default search scope
    *
    * Newly added in 2.0.0RC4, for auto-reconnect:
    * auto_reconnect  = if set to true then the class will automatically
    *                   attempt to reconnect to the LDAP server in certain
    *                   failure conditionswhen attempting a search, or other
    *                   LDAP operation.  Defaults to false.  Note that if you
    *                   set this to true, calls to search() may block
    *                   indefinitely if there is a catastrophic server failure.
    * min_backoff     = minimum reconnection delay period (in seconds).
    * current_backoff = initial reconnection delay period (in seconds).
    * max_backoff     = maximum reconnection delay period (in seconds).
    *
    * @access protected
    * @var array
    */
    protected $_config = array('host'            => 'localhost',
                               'port'            => 389,
                               'version'         => 3,
                               'starttls'        => false,
                               'binddn'          => '',
                               'bindpw'          => '',
                               'basedn'          => '',
                               'options'         => array(),
                               'filter'          => '(objectClass=*)',
                               'scope'           => 'sub',
                               'auto_reconnect'  => false,
                               'min_backoff'     => 1,
                               'current_backoff' => 1,
                               'max_backoff'     => 32);

    /**
    * List of hosts we try to establish a connection to
    *
    * @access protected
    * @var array
    */
    protected $_host_list = array();

    /**
    * List of hosts that are known to be down.
    *
    * @access protected
    * @var array
    */
    protected $_down_host_list = array();

    /**
    * LDAP resource link.
    *
    * @access protected
    * @var resource
    */
    protected $_link = false;

    /**
    * Net_LDAP2_Schema object
    *
    * This gets set and returned by {@link schema()}
    *
    * @access protected
    * @var object Net_LDAP2_Schema
    */
    protected $_schema = null;

    /**
    * Schema cacher function callback
    *
    * @see registerSchemaCache()
    * @var string
    */
    protected $_schema_cache = null;

    /**
    * Cache for attribute encoding checks
    *
    * @access protected
    * @var array Hash with attribute names as key and boolean value
    *            to determine whether they should be utf8 encoded or not.
    */
    protected $_schemaAttrs = array();

    /**
    * Cache for rootDSE objects
    *
    * Hash with requested rootDSE attr names as key and rootDSE object as value
    *
    * Since the RootDSE object itself may request a rootDSE object,
    * {@link rootDse()} caches successful requests.
    * Internally, Net_LDAP2 needs several lookups to this object, so
    * caching increases performance significally.
    *
    * @access protected
    * @var array
    */
    protected $_rootDSE_cache = array();

    /**
    * Returns the Net_LDAP2 Release version, may be called statically
    *
    * @static
    * @return string Net_LDAP2 version
    */
    public static function getVersion()
    {
        return NET_LDAP2_VERSION;
    }

    /**
    * Configure Net_LDAP2, connect and bind
    *
    * Use this method as starting point of using Net_LDAP2
    * to establish a connection to your LDAP server.
    *
    * Static function that returns either an error object or the new Net_LDAP2
    * object. Something like a factory. Takes a config array with the needed
    * parameters.
    *
    * @param array $config Configuration array
    *
    * @access public
    * @return Net_LDAP2_Error|Net_LDAP2   Net_LDAP2_Error or Net_LDAP2 object
    */
    public static function connect($config = array())
    {
        $ldap_check = self::checkLDAPExtension();
        if (self::iserror($ldap_check)) {
            return $ldap_check;
        }

        @$obj = new Net_LDAP2($config);

        // todo? better errorhandling for setConfig()?

        // connect and bind with credentials in config
        $err = $obj->bind();
        if (self::isError($err)) {
            return $err;
        }

        return $obj;
    }

    /**
    * Net_LDAP2 constructor
    *
    * Sets the config array
    *
    * Please note that the usual way of getting Net_LDAP2 to work is
    * to call something like:
    * <code>$ldap = Net_LDAP2::connect($ldap_config);</code>
    *
    * @param array $config Configuration array
    *
    * @access protected
    * @return void
    * @see $_config
    */
    public function __construct($config = array())
    {
        parent::__construct('Net_LDAP2_Error');
        $this->setConfig($config);
    }

    /**
    * Sets the internal configuration array
    *
    * @param array $config Configuration array
    *
    * @access protected
    * @return void
    */
    protected function setConfig($config)
    {
        //
        // Parameter check -- probably should raise an error here if config
        // is not an array.
        //
        if (! is_array($config)) {
            return;
        }

        foreach ($config as $k => $v) {
            if (isset($this->_config[$k])) {
                $this->_config[$k] = $v;
            } else {
                // map old (Net_LDAP2) parms to new ones
                switch($k) {
                case "dn":
                    $this->_config["binddn"] = $v;
                    break;
                case "password":
                    $this->_config["bindpw"] = $v;
                    break;
                case "tls":
                    $this->_config["starttls"] = $v;
                    break;
                case "base":
                    $this->_config["basedn"] = $v;
                    break;
                }
            }
        }

        //
        // Ensure the host list is an array.
        //
        if (is_array($this->_config['host'])) {
            $this->_host_list = $this->_config['host'];
        } else {
            if (strlen($this->_config['host']) > 0) {
                $this->_host_list = array($this->_config['host']);
            } else {
                $this->_host_list = array();
                // ^ this will cause an error in performConnect(),
                // so the user is notified about the failure
            }
        }

        //
        // Reset the down host list, which seems like a sensible thing to do
        // if the config is being reset for some reason.
        //
        $this->_down_host_list = array();
    }

    /**
    * Bind or rebind to the ldap-server
    *
    * This function binds with the given dn and password to the server. In case
    * no connection has been made yet, it will be started and startTLS issued
    * if appropiate.
    *
    * The internal bind configuration is not being updated, so if you call
    * bind() without parameters, you can rebind with the credentials
    * provided at first connecting to the server.
    *
    * @param string $dn       Distinguished name for binding
    * @param string $password Password for binding
    *
    * @access public
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    public function bind($dn = null, $password = null)
    {
        // fetch current bind credentials
        if (is_null($dn)) {
            $dn = $this->_config["binddn"];
        }
        if (is_null($password)) {
            $password = $this->_config["bindpw"];
        }

        // Connect first, if we haven't so far.
        // This will also bind us to the server.
        if ($this->_link === false) {
            // store old credentials so we can revert them later
            // then overwrite config with new bind credentials
            $olddn = $this->_config["binddn"];
            $oldpw = $this->_config["bindpw"];

            // overwrite bind credentials in config
            // so performConnect() knows about them
            $this->_config["binddn"] = $dn;
            $this->_config["bindpw"] = $password;

            // try to connect with provided credentials
            $msg = $this->performConnect();

            // reset to previous config
            $this->_config["binddn"] = $olddn;
            $this->_config["bindpw"] = $oldpw;

            // see if bind worked
            if (self::isError($msg)) {
                return $msg;
            }
        } else {
            // do the requested bind as we are
            // asked to bind manually
            if (is_null($dn)) {
                // anonymous bind
                $msg = @ldap_bind($this->_link);
            } else {
                // privileged bind
                $msg = @ldap_bind($this->_link, $dn, $password);
            }
            if (false === $msg) {
                return PEAR::raiseError("Bind failed: " .
                                        @ldap_error($this->_link),
                                        @ldap_errno($this->_link));
            }
        }
        return true;
    }

    /**
    * Connect to the ldap-server
    *
    * This function connects to the LDAP server specified in
    * the configuration, binds and set up the LDAP protocol as needed.
    *
    * @access protected
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    protected function performConnect()
    {
        // Note: Connecting is briefly described in RFC1777.
        // Basicly it works like this:
        //  1. set up TCP connection
        //  2. secure that connection if neccessary
        //  3a. setLDAPVersion to tell server which version we want to speak
        //  3b. perform bind
        //  3c. setLDAPVersion to tell server which version we want to speak
        //      together with a test for supported versions
        //  4. set additional protocol options

        // Return true if we are already connected.
        if ($this->_link !== false) {
            return true;
        }

        // Connnect to the LDAP server if we are not connected.  Note that
        // with some LDAP clients, ldapperformConnect returns a link value even
        // if no connection is made.  We need to do at least one anonymous
        // bind to ensure that a connection is actually valid.
        //
        // Ref: http://www.php.net/manual/en/function.ldap-connect.php

        // Default error message in case all connection attempts
        // fail but no message is set
        $current_error = new PEAR_Error('Unknown connection error');

        // Catch empty $_host_list arrays.
        if (!is_array($this->_host_list) || count($this->_host_list) == 0) {
            $current_error = PEAR::raiseError('No Servers configured! Please '.
               'pass in an array of servers to Net_LDAP2');
            return $current_error;
        }

        // Cycle through the host list.
        foreach ($this->_host_list as $host) {

            // Ensure we have a valid string for host name
            if (is_array($host)) {
                $current_error = PEAR::raiseError('No Servers configured! '.
                   'Please pass in an one dimensional array of servers to '.
                   'Net_LDAP2! (multidimensional array detected!)');
                continue;
            }

            // Skip this host if it is known to be down.
            if (in_array($host, $this->_down_host_list)) {
                continue;
            }

            // Record the host that we are actually connecting to in case
            // we need it later.
            $this->_config['host'] = $host;

            // Attempt a connection.
            $this->_link = @ldap_connect($host, $this->_config['port']);
            if (false === $this->_link) {
                $current_error = PEAR::raiseError('Could not connect to ' .
                    $host . ':' . $this->_config['port']);
                $this->_down_host_list[] = $host;
                continue;
            }

            // If we're supposed to use TLS, do so before we try to bind,
            // as some strict servers only allow binding via secure connections
            if ($this->_config["starttls"] === true) {
                if (self::isError($msg = $this->startTLS())) {
                    $current_error           = $msg;
                    $this->_link             = false;
                    $this->_down_host_list[] = $host;
                    continue;
                }
            }

            // Try to set the configured LDAP version on the connection if LDAP
            // server needs that before binding (eg OpenLDAP).
            // This could be necessary since rfc-1777 states that the protocol version
            // has to be set at the bind request.
            // We use force here which means that the test in the rootDSE is skipped;
            // this is neccessary, because some strict LDAP servers only allow to
            // read the LDAP rootDSE (which tells us the supported protocol versions)
            // with authenticated clients.
            // This may fail in which case we try again after binding.
            // In this case, most probably the bind() or setLDAPVersion()-call
            // below will also fail, providing error messages.
            $version_set = false;
            $ignored_err = $this->setLDAPVersion(0, true);
            if (!self::isError($ignored_err)) {
                $version_set = true;
            }

            // Attempt to bind to the server. If we have credentials configured,
            // we try to use them, otherwise its an anonymous bind.
            // As stated by RFC-1777, the bind request should be the first
            // operation to be performed after the connection is established.
            // This may give an protocol error if the server does not support
            // V2 binds and the above call to setLDAPVersion() failed.
            // In case the above call failed, we try an V2 bind here and set the
            // version afterwards (with checking to the rootDSE).
            $msg = $this->bind();
            if (self::isError($msg)) {
                // The bind failed, discard link and save error msg.
                // Then record the host as down and try next one
                if ($msg->getCode() == 0x02 && !$version_set) {
                    // provide a finer grained error message
                    // if protocol error arieses because of invalid version
                    $msg = new Net_LDAP2_Error($msg->getMessage().
                        " (could not set LDAP protocol version to ".
                        $this->_config['version'].")",
                        $msg->getCode());
                }
                $this->_link             = false;
                $current_error           = $msg;
                $this->_down_host_list[] = $host;
                continue;
            }

            // Set desired LDAP version if not successfully set before.
            // Here, a check against the rootDSE is performed, so we get a
            // error message if the server does not support the version.
            // The rootDSE entry should tell us which LDAP versions are
            // supported. However, some strict LDAP servers only allow
            // bound suers to read the rootDSE.
            if (!$version_set) {
                if (self::isError($msg = $this->setLDAPVersion())) {
                    $current_error           = $msg;
                    $this->_link             = false;
                    $this->_down_host_list[] = $host;
                    continue;
                }
            }

            // Set LDAP parameters, now we know we have a valid connection.
            if (isset($this->_config['options']) &&
                is_array($this->_config['options']) &&
                count($this->_config['options'])) {
                foreach ($this->_config['options'] as $opt => $val) {
                    $err = $this->setOption($opt, $val);
                    if (self::isError($err)) {
                        $current_error           = $err;
                        $this->_link             = false;
                        $this->_down_host_list[] = $host;
                        continue 2;
                    }
                }
            }

            // At this stage we have connected, bound, and set up options,
            // so we have a known good LDAP server.  Time to go home.
            return true;
        }


        // All connection attempts have failed, return the last error.
        return $current_error;
    }

    /**
    * Reconnect to the ldap-server.
    *
    * In case the connection to the LDAP
    * service has dropped out for some reason, this function will reconnect,
    * and re-bind if a bind has been attempted in the past.  It is probably
    * most useful when the server list provided to the new() or connect()
    * function is an array rather than a single host name, because in that
    * case it will be able to connect to a failover or secondary server in
    * case the primary server goes down.
    *
    * This doesn't return anything, it just tries to re-establish
    * the current connection.  It will sleep for the current backoff
    * period (seconds) before attempting the connect, and if the
    * connection fails it will double the backoff period, but not
    * try again.  If you want to ensure a reconnection during a
    * transient period of server downtime then you need to call this
    * function in a loop.
    *
    * @access protected
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    protected function performReconnect()
    {

        // Return true if we are already connected.
        if ($this->_link !== false) {
            return true;
        }

        // Default error message in case all connection attempts
        // fail but no message is set
        $current_error = new PEAR_Error('Unknown connection error');

        // Sleep for a backoff period in seconds.
        sleep($this->_config['current_backoff']);

        // Retry all available connections.
        $this->_down_host_list = array();
        $msg = $this->performConnect();

        // Bail out if that fails.
        if (self::isError($msg)) {
            $this->_config['current_backoff'] =
               $this->_config['current_backoff'] * 2;
            if ($this->_config['current_backoff'] > $this->_config['max_backoff']) {
                $this->_config['current_backoff'] = $this->_config['max_backoff'];
            }
            return $msg;
        }

        // Now we should be able to safely (re-)bind.
        $msg = $this->bind();
        if (self::isError($msg)) {
            $this->_config['current_backoff'] = $this->_config['current_backoff'] * 2;
            if ($this->_config['current_backoff'] > $this->_config['max_backoff']) {
                $this->_config['current_backoff'] = $this->_config['max_backoff'];
            }

            // _config['host'] should have had the last connected host stored in it
            // by performConnect().  Since we are unable to bind to that host we can safely
            // assume that it is down or has some other problem.
            $this->_down_host_list[] = $this->_config['host'];
            return $msg;
        }

        // At this stage we have connected, bound, and set up options,
        // so we have a known good LDAP server. Time to go home.
        $this->_config['current_backoff'] = $this->_config['min_backoff'];
        return true;
    }

    /**
    * Starts an encrypted session
    *
    * @access public
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    public function startTLS()
    {
        /* Test to see if the server supports TLS first.
           This is done via testing the extensions offered by the server.
           The OID 1.3.6.1.4.1.1466.20037 tells us, if TLS is supported.
           Note, that not all servers allow to feth either the rootDSE or
           attributes over an unencrypted channel, so we must ignore errors. */
        $rootDSE = $this->rootDse();
        if (self::isError($rootDSE)) {
            /* IGNORE this error, because server may refuse fetching the
               RootDSE over an unencrypted connection. */
            //return $this->raiseError("Unable to fetch rootDSE entry ".
            //"to see if TLS is supoported: ".$rootDSE->getMessage(), $rootDSE->getCode());
        } else {
            /* Fetch suceeded, see, if the server supports TLS. Again, we
               ignore errors, because the server may refuse to return
               attributes over unencryted connections. */
            $supported_extensions = $rootDSE->getValue('supportedExtension');
            if (self::isError($supported_extensions)) {
                /* IGNORE error, because server may refuse attribute
                   returning over an unencrypted connection. */
                //return $this->raiseError("Unable to fetch rootDSE attribute 'supportedExtension' ".
                //"to see if TLS is supoported: ".$supported_extensions->getMessage(), $supported_extensions->getCode());
            } else {
                // fetch succeedet, lets see if the server supports it.
                // if not, then drop an error. If supported, then do nothing,
                // because then we try to issue TLS afterwards.
                if (!in_array('1.3.6.1.4.1.1466.20037', $supported_extensions)) {
                    return $this->raiseError("Server reports that it does not support TLS.");
                 }
            }
        }

        // Try to establish TLS.
        if (false === @ldap_start_tls($this->_link)) {
            // Starting TLS failed. This may be an error, or because
            // the server does not support it but did not enable us to
            // detect that above.
            return $this->raiseError("TLS could not be started: " .
                                    @ldap_error($this->_link),
                                    @ldap_errno($this->_link));
        } else {
            return true; // TLS is started now.
        }
    }

    /**
    * alias function of startTLS() for perl-ldap interface
    *
    * @return void
    * @see startTLS()
    */
    public function start_tls()
    {
        $args = func_get_args();
        return call_user_func_array(array( $this, 'startTLS' ), $args);
    }

    /**
    * Close LDAP connection.
    *
    * Closes the connection. Use this when the session is over.
    *
    * @return void
    */
    public function done()
    {
        $this->_Net_LDAP2();
    }

    /**
    * Alias for {@link done()}
    *
    * @return void
    * @see done()
    */
    public function disconnect()
    {
        $this->done();
    }

    /**
    * Destructor
    *
    * @access protected
    */
    public function _Net_LDAP2()
    {
        @ldap_close($this->_link);
    }

    /**
    * Add a new entryobject to a directory.
    *
    * Use add to add a new Net_LDAP2_Entry object to the directory.
    * This also links the entry to the connection used for the add,
    * if it was a fresh entry ({@link Net_LDAP2_Entry::createFresh()})
    *
    * @param Net_LDAP2_Entry $entry Net_LDAP2_Entry
    *
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    public function add($entry)
    {
        if (!$entry instanceof Net_LDAP2_Entry) {
            return PEAR::raiseError('Parameter to Net_LDAP2::add() must be a Net_LDAP2_Entry object.');
        }

        // Continue attempting the add operation in a loop until we
        // get a success, a definitive failure, or the world ends.
        $foo = 0;
        while (true) {
            $link = $this->getLink();

            if ($link === false) {
                // We do not have a successful connection yet.  The call to
                // getLink() would have kept trying if we wanted one.  Go
                // home now.
                return PEAR::raiseError("Could not add entry " . $entry->dn() .
                       " no valid LDAP connection could be found.");
            }

            if (@ldap_add($link, $entry->dn(), $entry->getValues())) {
                // entry successfully added, we should update its $ldap reference
                // in case it is not set so far (fresh entry)
                if (!$entry->getLDAP() instanceof Net_LDAP2) {
                    $entry->setLDAP($this);
                }
                // store, that the entry is present inside the directory
                $entry->markAsNew(false);
                return true;
            } else {
                // We have a failure.  What type?  We may be able to reconnect
                // and try again.
                $error_code = @ldap_errno($link);
                $error_name = Net_LDAP2::errorMessage($error_code);

                if (($error_name === 'LDAP_OPERATIONS_ERROR') &&
                    ($this->_config['auto_reconnect'])) {

                    // The server has become disconnected before trying the
                    // operation.  We should try again, possibly with a different
                    // server.
                    $this->_link = false;
                    $this->performReconnect();
                } else {
                    // Errors other than the above catched are just passed
                    // back to the user so he may react upon them.
                    return PEAR::raiseError("Could not add entry " . $entry->dn() . " " .
                                            $error_name,
                                            $error_code);
                }
            }
        }
    }

    /**
    * Delete an entry from the directory
    *
    * The object may either be a string representing the dn or a Net_LDAP2_Entry
    * object. When the boolean paramter recursive is set, all subentries of the
    * entry will be deleted as well.
    *
    * @param string|Net_LDAP2_Entry $dn        DN-string or Net_LDAP2_Entry
    * @param boolean                $recursive Should we delete all children recursive as well?
    *
    * @access public
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    public function delete($dn, $recursive = false)
    {
        if ($dn instanceof Net_LDAP2_Entry) {
             $dn = $dn->dn();
        }
        if (false === is_string($dn)) {
            return PEAR::raiseError("Parameter is not a string nor an entry object!");
        }
        // Recursive delete searches for children and calls delete for them
        if ($recursive) {
            $result = @ldap_list($this->_link, $dn, '(objectClass=*)', array(null), 0, 0);
            if (@ldap_count_entries($this->_link, $result)) {
                $subentry = @ldap_first_entry($this->_link, $result);
                $this->delete(@ldap_get_dn($this->_link, $subentry), true);
                while ($subentry = @ldap_next_entry($this->_link, $subentry)) {
                    $this->delete(@ldap_get_dn($this->_link, $subentry), true);
                }
            }
        }

        // Continue attempting the delete operation in a loop until we
        // get a success, a definitive failure, or the world ends.
        while (true) {
            $link = $this->getLink();

            if ($link === false) {
                // We do not have a successful connection yet.  The call to
                // getLink() would have kept trying if we wanted one.  Go
                // home now.
                return PEAR::raiseError("Could not add entry " . $dn .
                       " no valid LDAP connection could be found.");
            }

            if (@ldap_delete($link, $dn)) {
                // entry successfully deleted.
                return true;
            } else {
                // We have a failure.  What type?
                // We may be able to reconnect and try again.
                $error_code = @ldap_errno($link);
                $error_name = Net_LDAP2::errorMessage($error_code);

                if ((Net_LDAP2::errorMessage($error_code) === 'LDAP_OPERATIONS_ERROR') &&
                    ($this->_config['auto_reconnect'])) {
                    // The server has become disconnected before trying the
                    // operation.  We should try again, possibly with a 
                    // different server.
                    $this->_link = false;
                    $this->performReconnect();

                } elseif ($error_code == 66) {
                    // Subentries present, server refused to delete.
                    // Deleting subentries is the clients responsibility, but
                    // since the user may not know of the subentries, we do not
                    // force that here but instead notify the developer so he
                    // may take actions himself.
                    return PEAR::raiseError("Could not delete entry $dn because of subentries. Use the recursive parameter to delete them.");

                } else {
                    // Errors other than the above catched are just passed
                    // back to the user so he may react upon them.
                    return PEAR::raiseError("Could not delete entry " . $dn . " " .
                                            $error_name,
                                            $error_code);
                }
            }
        }
    }

    /**
    * Modify an ldapentry directly on the server
    *
    * This one takes the DN or a Net_LDAP2_Entry object and an array of actions.
    * This array should be something like this:
    *
    * array('add' => array('attribute1' => array('val1', 'val2'),
    *                      'attribute2' => array('val1')),
    *       'delete' => array('attribute1'),
    *       'replace' => array('attribute1' => array('val1')),
    *       'changes' => array('add' => ...,
    *                          'replace' => ...,
    *                          'delete' => array('attribute1', 'attribute2' => array('val1')))
    *
    * The changes array is there so the order of operations can be influenced
    * (the operations are done in order of appearance).
    * The order of execution is as following:
    *   1. adds from 'add' array
    *   2. deletes from 'delete' array
    *   3. replaces from 'replace' array
    *   4. changes (add, replace, delete) in order of appearance
    * All subarrays (add, replace, delete, changes) may be given at the same time.
    *
    * The function calls the corresponding functions of an Net_LDAP2_Entry
    * object. A detailed description of array structures can be found there.
    *
    * Unlike the modification methods provided by the Net_LDAP2_Entry object,
    * this method will instantly carry out an update() after each operation,
    * thus modifying "directly" on the server.
    *
    * @param string|Net_LDAP2_Entry $entry DN-string or Net_LDAP2_Entry
    * @param array                  $parms Array of changes
    *
    * @access public
    * @return Net_LDAP2_Error|true Net_LDAP2_Error object or true
    */
    public function modify($entry, $parms = array())
    {
        if (is_string($entry)) {
            $entry = $this->getEntry($entry);
            if (self::isError($entry)) {
                return $entry;
            }
        }
        if (!$entry instanceof Net_LDAP2_Entry) {
            return PEAR::raiseError("Parameter is not a string nor an entry object!");
        }

        // Perform changes mentioned separately
        foreach (array('add', 'delete', 'replace') as $action) {
            if (isset($parms[$action])) {
                $msg = $entry->$action($parms[$action]);
                if (self::isError($msg)) {
                    return $msg;
                }
                $entry->setLDAP($this);

                // Because the @ldap functions are called inside Net_LDAP2_Entry::update(),
                // we have to trap the error codes issued from that if we want to support
                // reconnection.
                while (true) {
                    $msg = $entry->update();

                    if (self::isError($msg)) {
                        // We have a failure.  What type?  We may be able to reconnect
                        // and try again.
                        $error_code = $msg->getCode();
                        $error_name = Net_LDAP2::errorMessage($error_code);

                        if ((Net_LDAP2::errorMessage($error_code) === 'LDAP_OPERATIONS_ERROR') &&
                            ($this->_config['auto_reconnect'])) {

                            // The server has become disconnected before trying the
                            // operation.  We should try again, possibly with a different
                            // server.
                            $this->_link = false;
                            $this->performReconnect();

                        } else {

                            // Errors other than the above catched are just passed
                            // back to the user so he may react upon them.
                            return PEAR::raiseError("Could not modify entry: ".$msg->getMessage());
                        }
                    } else {
                        // modification succeedet, evaluate next change
                        break;
                    }
                }
            }
        }

        // perform combined changes in 'changes' array
        if (isset($parms['changes']) && is_array($parms['changes'])) {
            foreach ($parms['changes'] as $action => $value) {

                // Because the @ldap functions are called inside Net_LDAP2_Entry::update,
                // we have to trap the error codes issued from that if we want to support
                // reconnection.
                while (true) {
                    $msg = $this->modify($entry, array($action => $value));

                    if (self::isError($msg)) {
                        // We have a failure.  What type?  We may be able to reconnect
                        // and try again.
                        $error_code = $msg->getCode();
                        $error_name = Net_LDAP2::errorMessage($error_code);

                        if ((Net_LDAP2::errorMessage($error_code) === 'LDAP_OPERATIONS_ERROR') &&
                            ($this->_config['auto_reconnect'])) {

                            // The server has become disconnected before trying the
                            // operation.  We should try again, possibly with a different
                            // server.
                            $this->_link = false;
                            $this->performReconnect();

                        } else {
                            // Errors other than the above catched are just passed
                            // back to the user so he may react upon them.
                            return $msg;
                        }
                    } else {
                        // modification succeedet, evaluate next change
                        break;
                    }
                }
            }
        }

        return true;
    }

    /**
    * Run a ldap search query
    *
    * Search is used to query the ldap-database.
    * $base and $filter may be ommitted. The one from config will
    * then be used. $base is either a DN-string or an Net_LDAP2_Entry
    * object in which case its DN willb e used.
    *
    * Params may contain:
    *
    * scope: The scope which will be used for searching
    *        base - Just one entry
    *        sub  - The whole tree
    *        one  - Immediately below $base
    * sizelimit: Limit the number of entries returned (default: 0 = unlimited),
    * timelimit: Limit the time spent for searching (default: 0 = unlimited),
    * attrsonly: If true, the search will only return the attribute names,
    * attributes: Array of attribute names, which the entry should contain.
    *             It is good practice to limit this to just the ones you need.
    * [NOT IMPLEMENTED]
    * deref: By default aliases are dereferenced to locate the base object for the search, but not when
    *        searching subordinates of the base object. This may be changed by specifying one of the
    *        following values:
    *
    *        never  - Do not dereference aliases in searching or in locating the base object of the search.
    *        search - Dereference aliases in subordinates of the base object in searching, but not in
    *                locating the base object of the search.
    *        find
    *        always
    *
    * Please note, that you cannot override server side limitations to sizelimit
    * and timelimit: You can always only lower a given limit.
    *
    * @param string|Net_LDAP2_Entry  $base   LDAP searchbase
    * @param string|Net_LDAP2_Filter $filter LDAP search filter or a Net_LDAP2_Filter object
    * @param array                   $params Array of options
    *
    * @access public
    * @return Net_LDAP2_Search|Net_LDAP2_Error Net_LDAP2_Search object or Net_LDAP2_Error object
    * @todo implement search controls (sorting etc)
    */
    public function search($base = null, $filter = null, $params = array())
    {
        if (is_null($base)) {
            $base = $this->_config['basedn'];
        }
        if ($base instanceof Net_LDAP2_Entry) {
            $base = $base->dn(); // fetch DN of entry, making searchbase relative to the entry
        }
        if (is_null($filter)) {
            $filter = $this->_config['filter'];
        }
        if ($filter instanceof Net_LDAP2_Filter) {
            $filter = $filter->asString(); // convert Net_LDAP2_Filter to string representation
        }
        if (PEAR::isError($filter)) {
            return $filter;
        }
        if (PEAR::isError($base)) {
            return $base;
        }

        /* setting searchparameters  */
        (isset($params['sizelimit']))  ? $sizelimit  = $params['sizelimit']  : $sizelimit = 0;
        (isset($params['timelimit']))  ? $timelimit  = $params['timelimit']  : $timelimit = 0;
        (isset($params['attrsonly']))  ? $attrsonly  = $params['attrsonly']  : $attrsonly = 0;
        (isset($params['attributes'])) ? $attributes = $params['attributes'] : $attributes = array();

        // Ensure $attributes to be an array in case only one
        // attribute name was given as string
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }

        // reorganize the $attributes array index keys
        // sometimes there are problems with not consecutive indexes
        $attributes = array_values($attributes);

        // scoping makes searches faster!
        $scope = (isset($params['scope']) ? $params['scope'] : $this->_config['scope']);

        switch ($scope) {
        case 'one':
            $search_function = 'ldap_list';
            break;
        case 'base':
            $search_function = 'ldap_read';
            break;
        default:
            $search_function = 'ldap_search';
        }

        // Continue attempting the search operation until we get a success
        // or a definitive failure.
        while (true) {
            $link = $this->getLink();
            $search = @call_user_func($search_function,
                                      $link,
                                      $base,
                                      $filter,
                                      $attributes,
                                      $attrsonly,
                                      $sizelimit,
                                      $timelimit);

            if ($err = @ldap_errno($link)) {
                if ($err == 32) {
                    // Errorcode 32 = no such object, i.e. a nullresult.
                    return $obj = new Net_LDAP2_Search ($search, $this, $attributes);
                } elseif ($err == 4) {
                    // Errorcode 4 = sizelimit exeeded.
                    return $obj = new Net_LDAP2_Search ($search, $this, $attributes);
                } elseif ($err == 87) {
                    // bad search filter
                    return $this->raiseError(Net_LDAP2::errorMessage($err) . "($filter)", $err);
                } elseif (($err == 1) && ($this->_config['auto_reconnect'])) {
                    // Errorcode 1 = LDAP_OPERATIONS_ERROR but we can try a reconnect.
                    $this->_link = false;
                    $this->performReconnect();
                } else {
                    $msg = "\nParameters:\nBase: $base\nFilter: $filter\nScope: $scope";
                    return $this->raiseError(Net_LDAP2::errorMessage($err) . $msg, $err);
                }
            } else {
                return $obj = new Net_LDAP2_Search($search, $this, $attributes);
            }
        }
    }

    /**
    * Set an LDAP option
    *
    * @param string $option Option to set
    * @param mixed  $value  Value to set Option to
    *
    * @access public
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    */
    public function setOption($option, $value)
    {
        if ($this->_link) {
            if (defined($option)) {
                if (@ldap_set_option($this->_link, constant($option), $value)) {
                    return true;
                } else {
                    $err = @ldap_errno($this->_link);
                    if ($err) {
                        $msg = @ldap_err2str($err);
                    } else {
                        $err = NET_LDAP2_ERROR;
                        $msg = Net_LDAP2::errorMessage($err);
                    }
                    return $this->raiseError($msg, $err);
                }
            } else {
                return $this->raiseError("Unkown Option requested");
            }
        } else {
            return $this->raiseError("Could not set LDAP option: No LDAP connection");
        }
    }

    /**
    * Get an LDAP option value
    *
    * @param string $option Option to get
    *
    * @access public
    * @return Net_LDAP2_Error|string Net_LDAP2_Error or option value
    */
    public function getOption($option)
    {
        if ($this->_link) {
            if (defined($option)) {
                if (@ldap_get_option($this->_link, constant($option), $value)) {
                    return $value;
                } else {
                    $err = @ldap_errno($this->_link);
                    if ($err) {
                        $msg = @ldap_err2str($err);
                    } else {
                        $err = NET_LDAP2_ERROR;
                        $msg = Net_LDAP2::errorMessage($err);
                    }
                    return $this->raiseError($msg, $err);
                }
            } else {
                $this->raiseError("Unkown Option requested");
            }
        } else {
            $this->raiseError("No LDAP connection");
        }
    }

    /**
    * Get the LDAP_PROTOCOL_VERSION that is used on the connection.
    *
    * A lot of ldap functionality is defined by what protocol version the ldap server speaks.
    * This might be 2 or 3.
    *
    * @return int
    */
    public function getLDAPVersion()
    {
        if ($this->_link) {
            $version = $this->getOption("LDAP_OPT_PROTOCOL_VERSION");
        } else {
            $version = $this->_config['version'];
        }
        return $version;
    }

    /**
    * Set the LDAP_PROTOCOL_VERSION that is used on the connection.
    *
    * @param int     $version LDAP-version that should be used
    * @param boolean $force   If set to true, the check against the rootDSE will be skipped
    *
    * @return Net_LDAP2_Error|true    Net_LDAP2_Error object or true
    * @todo Checking via the rootDSE takes much time - why? fetching and instanciation is quick!
    */
    public function setLDAPVersion($version = 0, $force = false)
    {
        if (!$version) {
            $version = $this->_config['version'];
        }

        //
        // Check to see if the server supports this version first.
        //
        // Todo: Why is this so horribly slow?
        // $this->rootDse() is very fast, as well as Net_LDAP2_RootDSE::fetch()
        // seems like a problem at copiyng the object inside PHP??
        // Additionally, this is not always reproducable...
        //
        if (!$force) {
            $rootDSE = $this->rootDse();
            if ($rootDSE instanceof Net_LDAP2_Error) {
                return $rootDSE;
            } else {
                $supported_versions = $rootDSE->getValue('supportedLDAPVersion');
                if (is_string($supported_versions)) {
                    $supported_versions = array($supported_versions);
                }
                $check_ok = in_array($version, $supported_versions);
            }
        }

        if ($force || $check_ok) {
            return $this->setOption("LDAP_OPT_PROTOCOL_VERSION", $version);
        } else {
            return $this->raiseError("LDAP Server does not support protocol version " . $version);
        }
    }


    /**
    * Tells if a DN does exist in the directory
    *
    * @param string|Net_LDAP2_Entry $dn The DN of the object to test
    *
    * @return boolean|Net_LDAP2_Error
    */
    public function dnExists($dn)
    {
        if (PEAR::isError($dn)) {
            return $dn;
        }
        if ($dn instanceof Net_LDAP2_Entry) {
             $dn = $dn->dn();
        }
        if (false === is_string($dn)) {
            return PEAR::raiseError('Parameter $dn is not a string nor an entry object!');
        }

        // search LDAP for that DN by performing a baselevel search for any
        // object. We can only find the DN in question this way, or nothing.
        $s_opts = array(
            'scope'      => 'base',
            'sizelimit'  => 1,
            'attributes' => '1.1' // select no attrs
        );
        $search = $this->search($dn, '(objectClass=*)', $s_opts);

        if (self::isError($search)) {
            return $search;
        }

        // retun wehter the DN exists; that is, we found an entry
        return ($search->count() == 0)? false : true;
    }


    /**
    * Get a specific entry based on the DN
    *
    * @param string $dn   DN of the entry that should be fetched
    * @param array  $attr Array of Attributes to select. If ommitted, all attributes are fetched.
    *
    * @return Net_LDAP2_Entry|Net_LDAP2_Error    Reference to a Net_LDAP2_Entry object or Net_LDAP2_Error object
    * @todo Maybe check against the shema should be done to be sure the attribute type exists
    */
    public function getEntry($dn, $attr = array())
    {
        if (!is_array($attr)) {
            $attr = array($attr);
        }
        $result = $this->search($dn, '(objectClass=*)',
                                array('scope' => 'base', 'attributes' => $attr));
        if (self::isError($result)) {
            return $result;
        } elseif ($result->count() == 0) {
            return PEAR::raiseError('Could not fetch entry '.$dn.': no entry found');
        }
        $entry = $result->shiftEntry();
        if (false == $entry) {
            return PEAR::raiseError('Could not fetch entry (error retrieving entry from search result)');
        }
        return $entry;
    }

    /**
    * Rename or move an entry
    *
    * This method will instantly carry out an update() after the move,
    * so the entry is moved instantly.
    * You can pass an optional Net_LDAP2 object. In this case, a cross directory
    * move will be performed which deletes the entry in the source (THIS) directory
    * and adds it in the directory $target_ldap.
    * A cross directory move will switch the Entrys internal LDAP reference so
    * updates to the entry will go to the new directory.
    *
    * Note that if you want to do a cross directory move, you need to
    * pass an Net_LDAP2_Entry object, otherwise the attributes will be empty.
    *
    * @param string|Net_LDAP2_Entry $entry       Entry DN or Entry object
    * @param string                 $newdn       New location
    * @param Net_LDAP2              $target_ldap (optional) Target directory for cross server move; should be passed via reference
    *
    * @return Net_LDAP2_Error|true
    */
    public function move($entry, $newdn, $target_ldap = null)
    {
        if (is_string($entry)) {
            $entry_o = $this->getEntry($entry);
        } else {
            $entry_o = $entry;
        }
        if (!$entry_o instanceof Net_LDAP2_Entry) {
            return PEAR::raiseError('Parameter $entry is expected to be a Net_LDAP2_Entry object! (If DN was passed, conversion failed)');
        }
        if (null !== $target_ldap && !$target_ldap instanceof Net_LDAP2) {
            return PEAR::raiseError('Parameter $target_ldap is expected to be a Net_LDAP2 object!');
        }

        if ($target_ldap && $target_ldap !== $this) {
            // cross directory move
            if (is_string($entry)) {
                return PEAR::raiseError('Unable to perform cross directory move: operation requires a Net_LDAP2_Entry object');
            }
            if ($target_ldap->dnExists($newdn)) {
                return PEAR::raiseError('Unable to perform cross directory move: entry does exist in target directory');
            }
            $entry_o->dn($newdn);
            $res = $target_ldap->add($entry_o);
            if (self::isError($res)) {
                return PEAR::raiseError('Unable to perform cross directory move: '.$res->getMessage().' in target directory');
            }
            $res = $this->delete($entry_o->currentDN());
            if (self::isError($res)) {
                $res2 = $target_ldap->delete($entry_o); // undo add
                if (self::isError($res2)) {
                    $add_error_string = 'Additionally, the deletion (undo add) of $entry in target directory failed.';
                }
                return PEAR::raiseError('Unable to perform cross directory move: '.$res->getMessage().' in source directory. '.$add_error_string);
            }
            $entry_o->setLDAP($target_ldap);
            return true;
        } else {
            // local move
            $entry_o->dn($newdn);
            $entry_o->setLDAP($this);
            return $entry_o->update();
        }
    }

    /**
    * Copy an entry to a new location
    *
    * The entry will be immediately copied.
    * Please note that only attributes you have
    * selected will be copied.
    *
    * @param Net_LDAP2_Entry $entry Entry object
    * @param string          $newdn  New FQF-DN of the entry
    *
    * @return Net_LDAP2_Error|Net_LDAP2_Entry Error Message or reference to the copied entry
    */
    public function copy($entry, $newdn)
    {
        if (!$entry instanceof Net_LDAP2_Entry) {
            return PEAR::raiseError('Parameter $entry is expected to be a Net_LDAP2_Entry object!');
        }

        $newentry = Net_LDAP2_Entry::createFresh($newdn, $entry->getValues());
        $result   = $this->add($newentry);

        if ($result instanceof Net_LDAP2_Error) {
            return $result;
        } else {
            return $newentry;
        }
    }


    /**
    * Returns the string for an ldap errorcode.
    *
    * Made to be able to make better errorhandling
    * Function based on DB::errorMessage()
    * Tip: The best description of the errorcodes is found here:
    * http://www.directory-info.com/LDAP2/LDAPErrorCodes.html
    *
    * @param int $errorcode Error code
    *
    * @return string The errorstring for the error.
    */
    public static function errorMessage($errorcode)
    {
        $errorMessages = array(
                              0x00 => "LDAP_SUCCESS",
                              0x01 => "LDAP_OPERATIONS_ERROR",
                              0x02 => "LDAP_PROTOCOL_ERROR",
                              0x03 => "LDAP_TIMELIMIT_EXCEEDED",
                              0x04 => "LDAP_SIZELIMIT_EXCEEDED",
                              0x05 => "LDAP_COMPARE_FALSE",
                              0x06 => "LDAP_COMPARE_TRUE",
                              0x07 => "LDAP_AUTH_METHOD_NOT_SUPPORTED",
                              0x08 => "LDAP_STRONG_AUTH_REQUIRED",
                              0x09 => "LDAP_PARTIAL_RESULTS",
                              0x0a => "LDAP_REFERRAL",
                              0x0b => "LDAP_ADMINLIMIT_EXCEEDED",
                              0x0c => "LDAP_UNAVAILABLE_CRITICAL_EXTENSION",
                              0x0d => "LDAP_CONFIDENTIALITY_REQUIRED",
                              0x0e => "LDAP_SASL_BIND_INPROGRESS",
                              0x10 => "LDAP_NO_SUCH_ATTRIBUTE",
                              0x11 => "LDAP_UNDEFINED_TYPE",
                              0x12 => "LDAP_INAPPROPRIATE_MATCHING",
                              0x13 => "LDAP_CONSTRAINT_VIOLATION",
                              0x14 => "LDAP_TYPE_OR_VALUE_EXISTS",
                              0x15 => "LDAP_INVALID_SYNTAX",
                              0x20 => "LDAP_NO_SUCH_OBJECT",
                              0x21 => "LDAP_ALIAS_PROBLEM",
                              0x22 => "LDAP_INVALID_DN_SYNTAX",
                              0x23 => "LDAP_IS_LEAF",
                              0x24 => "LDAP_ALIAS_DEREF_PROBLEM",
                              0x30 => "LDAP_INAPPROPRIATE_AUTH",
                              0x31 => "LDAP_INVALID_CREDENTIALS",
                              0x32 => "LDAP_INSUFFICIENT_ACCESS",
                              0x33 => "LDAP_BUSY",
                              0x34 => "LDAP_UNAVAILABLE",
                              0x35 => "LDAP_UNWILLING_TO_PERFORM",
                              0x36 => "LDAP_LOOP_DETECT",
                              0x3C => "LDAP_SORT_CONTROL_MISSING",
                              0x3D => "LDAP_INDEX_RANGE_ERROR",
                              0x40 => "LDAP_NAMING_VIOLATION",
                              0x41 => "LDAP_OBJECT_CLASS_VIOLATION",
                              0x42 => "LDAP_NOT_ALLOWED_ON_NONLEAF",
                              0x43 => "LDAP_NOT_ALLOWED_ON_RDN",
                              0x44 => "LDAP_ALREADY_EXISTS",
                              0x45 => "LDAP_NO_OBJECT_CLASS_MODS",
                              0x46 => "LDAP_RESULTS_TOO_LARGE",
                              0x47 => "LDAP_AFFECTS_MULTIPLE_DSAS",
                              0x50 => "LDAP_OTHER",
                              0x51 => "LDAP_SERVER_DOWN",
                              0x52 => "LDAP_LOCAL_ERROR",
                              0x53 => "LDAP_ENCODING_ERROR",
                              0x54 => "LDAP_DECODING_ERROR",
                              0x55 => "LDAP_TIMEOUT",
                              0x56 => "LDAP_AUTH_UNKNOWN",
                              0x57 => "LDAP_FILTER_ERROR",
                              0x58 => "LDAP_USER_CANCELLED",
                              0x59 => "LDAP_PARAM_ERROR",
                              0x5a => "LDAP_NO_MEMORY",
                              0x5b => "LDAP_CONNECT_ERROR",
                              0x5c => "LDAP_NOT_SUPPORTED",
                              0x5d => "LDAP_CONTROL_NOT_FOUND",
                              0x5e => "LDAP_NO_RESULTS_RETURNED",
                              0x5f => "LDAP_MORE_RESULTS_TO_RETURN",
                              0x60 => "LDAP_CLIENT_LOOP",
                              0x61 => "LDAP_REFERRAL_LIMIT_EXCEEDED",
                              1000 => "Unknown Net_LDAP2 Error"
                              );

         return isset($errorMessages[$errorcode]) ?
            $errorMessages[$errorcode] :
            $errorMessages[NET_LDAP2_ERROR] . ' (' . $errorcode . ')';
    }

    /**
    * Gets a rootDSE object
    *
    * This either fetches a fresh rootDSE object or returns it from
    * the internal cache for performance reasons, if possible.
    *
    * @param array $attrs Array of attributes to search for
    *
    * @access public
    * @return Net_LDAP2_Error|Net_LDAP2_RootDSE Net_LDAP2_Error or Net_LDAP2_RootDSE object
    */
    public function rootDse($attrs = null)
    {
        if ($attrs !== null && !is_array($attrs)) {
            return PEAR::raiseError('Parameter $attr is expected to be an array!');
        }

        $attrs_signature = serialize($attrs);

        // see if we need to fetch a fresh object, or if we already
        // requested this object with the same attributes
        if (true || !array_key_exists($attrs_signature, $this->_rootDSE_cache)) {
            $rootdse = Net_LDAP2_RootDSE::fetch($this, $attrs);
            if ($rootdse instanceof Net_LDAP2_Error) {
                return $rootdse;
            }

            // search was ok, store rootDSE in cache
            $this->_rootDSE_cache[$attrs_signature] = $rootdse;
        }
        return $this->_rootDSE_cache[$attrs_signature];
    }

    /**
    * Alias function of rootDse() for perl-ldap interface
    *
    * @access public
    * @see rootDse()
    * @return Net_LDAP2_Error|Net_LDAP2_RootDSE
    */
    public function root_dse()
    {
        $args = func_get_args();
        return call_user_func_array(array($this, 'rootDse'), $args);
    }

    /**
    * Get a schema object
    *
    * @param string $dn (optional) Subschema entry dn
    *
    * @access public
    * @return Net_LDAP2_Schema|Net_LDAP2_Error  Net_LDAP2_Schema or Net_LDAP2_Error object
    */
    public function schema($dn = null)
    {
        // Schema caching by Knut-Olav Hoven
        // If a schema caching object is registered, we use that to fetch
        // a schema object.
        // See registerSchemaCache() for more info on this.
        if ($this->_schema === null) {
            if ($this->_schema_cache) {
               $cached_schema = $this->_schema_cache->loadSchema();
               if ($cached_schema instanceof Net_LDAP2_Error) {
                   return $cached_schema; // route error to client
               } else {
                   if ($cached_schema instanceof Net_LDAP2_Schema) {
                       $this->_schema = $cached_schema;
                   }
               }
            }
        }

        // Fetch schema, if not tried before and no cached version available.
        // If we are already fetching the schema, we will skip fetching.
        if ($this->_schema === null) {
            // store a temporary error message so subsequent calls to schema() can
            // detect, that we are fetching the schema already.
            // Otherwise we will get an infinite loop at Net_LDAP2_Schema::fetch()
            $this->_schema = new Net_LDAP2_Error('Schema not initialized');
            $this->_schema = Net_LDAP2_Schema::fetch($this, $dn);

            // If schema caching is active, advise the cache to store the schema
            if ($this->_schema_cache) {
                $caching_result = $this->_schema_cache->storeSchema($this->_schema);
                if ($caching_result instanceof Net_LDAP2_Error) {
                    return $caching_result; // route error to client
                }
            }
        }
        return $this->_schema;
    }

    /**
    * Enable/disable persistent schema caching
    *
    * Sometimes it might be useful to allow your scripts to cache
    * the schema information on disk, so the schema is not fetched
    * every time the script runs which could make your scripts run
    * faster.
    *
    * This method allows you to register a custom object that
    * implements your schema cache. Please see the SchemaCache interface
    * (SchemaCache.interface.php) for informations on how to implement this.
    * To unregister the cache, pass null as $cache parameter.
    *
    * For ease of use, Net_LDAP2 provides a simple file based cache
    * which is used in the example below. You may use this, for example,
    * to store the schema in a linux tmpfs which results in the schema
    * beeing cached inside the RAM which allows nearly instant access.
    * <code>
    *    // Create the simple file cache object that comes along with Net_LDAP2
    *    $mySchemaCache_cfg = array(
    *      'path'    =>  '/tmp/Net_LDAP2_Schema.cache',
    *      'max_age' =>  86400   // max age is 24 hours (in seconds)
    *    );
    *    $mySchemaCache = new Net_LDAP2_SimpleFileSchemaCache($mySchemaCache_cfg);
    *    $ldap = new Net_LDAP2::connect(...);
    *    $ldap->registerSchemaCache($mySchemaCache); // enable caching
    *    // now each call to $ldap->schema() will get the schema from disk!
    * </code>
    *
    * @param Net_LDAP2_SchemaCache|null $cache Object implementing the Net_LDAP2_SchemaCache interface
    *
    * @return true|Net_LDAP2_Error
    */
    public function registerSchemaCache($cache) {
        if (is_null($cache)
        || (is_object($cache) && in_array('Net_LDAP2_SchemaCache', class_implements($cache))) ) {
            $this->_schema_cache = $cache;
            return true;
        } else {
            return new Net_LDAP2_Error('Custom schema caching object is either no '.
                'valid object or does not implement the Net_LDAP2_SchemaCache interface!');
        }
    }


    /**
    * Checks if phps ldap-extension is loaded
    *
    * If it is not loaded, it tries to load it manually using PHPs dl().
    * It knows both windows-dll and *nix-so.
    *
    * @static
    * @return Net_LDAP2_Error|true
    */
    public static function checkLDAPExtension()
    {
        if (!extension_loaded('ldap') && !@dl('ldap.' . PHP_SHLIB_SUFFIX)) {
            return new Net_LDAP2_Error("It seems that you do not have the ldap-extension installed. Please install it before using the Net_LDAP2 package.");
        } else {
            return true;
        }
    }

    /**
    * Encodes given attributes from ISO-8859-1 to UTF-8 if needed by schema
    *
    * This function takes attributes in an array and then checks against the schema if they need
    * UTF8 encoding. If that is so, they will be encoded. An encoded array will be returned and
    * can be used for adding or modifying.
    *
    * $attributes is expected to be an array with keys describing
    * the attribute names and the values as the value of this attribute:
    * <code>$attributes = array('cn' => 'foo', 'attr2' => array('mv1', 'mv2'));</code>
    *
    * @param array $attributes Array of attributes
    *
    * @access public
    * @return array|Net_LDAP2_Error Array of UTF8 encoded attributes or Error
    */
    public function utf8Encode($attributes)
    {
        return $this->utf8($attributes, 'utf8_encode');
    }

    /**
    * Decodes the given attribute values from UTF-8 to ISO-8859-1 if needed by schema
    *
    * $attributes is expected to be an array with keys describing
    * the attribute names and the values as the value of this attribute:
    * <code>$attributes = array('cn' => 'foo', 'attr2' => array('mv1', 'mv2'));</code>
    *
    * @param array $attributes Array of attributes
    *
    * @access public
    * @see utf8Encode()
    * @return array|Net_LDAP2_Error Array with decoded attribute values or Error
    */
    public function utf8Decode($attributes)
    {
        return $this->utf8($attributes, 'utf8_decode');
    }

    /**
    * Encodes or decodes UTF-8/ISO-8859-1 attribute values if needed by schema
    *
    * @param array $attributes Array of attributes
    * @param array $function   Function to apply to attribute values
    *
    * @access protected
    * @return array|Net_LDAP2_Error Array of attributes with function applied to values or Error
    */
    protected function utf8($attributes, $function)
    {
        if (!is_array($attributes) || array_key_exists(0, $attributes)) {
            return PEAR::raiseError('Parameter $attributes is expected to be an associative array');
        }

        if (!$this->_schema) {
            $this->_schema = $this->schema();
        }

        if (!$this->_link || self::isError($this->_schema) || !function_exists($function)) {
            return $attributes;
        }

        if (is_array($attributes) && count($attributes) > 0) {

            foreach ($attributes as $k => $v) {

                if (!isset($this->_schemaAttrs[$k])) {

                    $attr = $this->_schema->get('attribute', $k);
                    if (self::isError($attr)) {
                        continue;
                    }

                    // Encoding is needed if this is a DIR_STR. We assume also
                    // needed encoding in case the schema contains no syntax
                    // information (he does not need to, see rfc2252, 4.2)
                    if (!array_key_exists('syntax', $attr) || false !== strpos($attr['syntax'], '1.3.6.1.4.1.1466.115.121.1.15')) {
                        $encode = true;
                    } else {
                        $encode = false;
                    }
                    $this->_schemaAttrs[$k] = $encode;

                } else {
                    $encode = $this->_schemaAttrs[$k];
                }

                if ($encode) {
                    if (is_array($v)) {
                        foreach ($v as $ak => $av) {
                            $v[$ak] = call_user_func($function, $av);
                        }
                    } else {
                        $v = call_user_func($function, $v);
                    }
                }
                $attributes[$k] = $v;
            }
        }
        return $attributes;
    }

    /**
    * Get the LDAP link resource.  It will loop attempting to
    * re-establish the connection if the connection attempt fails and
    * auto_reconnect has been turned on (see the _config array documentation).
    *
    * @access public
    * @return resource LDAP link
    */
    public function getLink()
    {
        if ($this->_config['auto_reconnect']) {
            while (true) {
                //
                // Return the link handle if we are already connected.  Otherwise
                // try to reconnect.
                //
                if ($this->_link !== false) {
                    return $this->_link;
                } else {
                    $this->performReconnect();
                }
            }
        }
        return $this->_link;
    }
}

/**
* Net_LDAP2_Error implements a class for reporting portable LDAP error messages.
*
* @category Net
* @package  Net_LDAP2
* @author   Tarjej Huse <tarjei@bergfald.no>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP22/
*/
class Net_LDAP2_Error extends PEAR_Error
{
    /**
     * Net_LDAP2_Error constructor.
     *
     * @param string  $message   String with error message.
     * @param integer $code      Net_LDAP2 error code
     * @param integer $mode      what "error mode" to operate in
     * @param mixed   $level     what error level to use for $mode & PEAR_ERROR_TRIGGER
     * @param mixed   $debuginfo additional debug info, such as the last query
     *
     * @access public
     * @see PEAR_Error
     */
    public function __construct($message = 'Net_LDAP2_Error', $code = NET_LDAP2_ERROR, $mode = PEAR_ERROR_RETURN,
                         $level = E_USER_NOTICE, $debuginfo = null)
    {
        if (is_int($code)) {
            parent::__construct($message . ': ' . Net_LDAP2::errorMessage($code), $code, $mode, $level, $debuginfo);
        } else {
            parent::__construct("$message: $code", NET_LDAP2_ERROR, $mode, $level, $debuginfo);
        }
    }
}

?>
