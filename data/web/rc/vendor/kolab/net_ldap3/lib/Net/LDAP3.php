<?php

/*
 +-----------------------------------------------------------------------+
 | Net/LDAP3.php                                                         |
 |                                                                       |
 | Based on code created by the Roundcube Webmail team.                  |
 |                                                                       |
 | Copyright (C) 2006-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2012-2014, Kolab Systems AG                             |
 |                                                                       |
 | This program is free software: you can redistribute it and/or modify  |
 | it under the terms of the GNU General Public License as published by  |
 | the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                   |
 |                                                                       |
 | This program is distributed in the hope that it will be useful,       |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of        |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the          |
 | GNU General Public License for more details.                          |
 |                                                                       |
 | You should have received a copy of the GNU General Public License     |
 | along with this program.  If not, see <http://www.gnu.org/licenses/>. |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide advanced functionality for accessing LDAP directories       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Authors: Thomas Bruederli <roundcube@gmail.com>                       |
 |          Aleksander Machniak <machniak@kolabsys.com>                  |
 |          Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                 |
 +-----------------------------------------------------------------------+
*/

require_once __DIR__ . '/LDAP3/Result.php';

/**
 * Model class to access a LDAP directories
 *
 * @package Net_LDAP3
 */
class Net_LDAP3
{
    public $conn;
    public $vlv_active = false;

    private $attribute_level_rights_map = array(
        "r" => "read",
        "s" => "search",
        "w" => "write",
        "o" => "delete",
        "c" => "compare",
        "W" => "write",
        "O" => "delete"
    );

    private $entry_level_rights_map = array(
        "a" => "add",
        "d" => "delete",
        "n" => "modrdn",
        "v" => "read"
    );

    /*
     * Manipulate configuration through the config_set and config_get methods.
     * Available options:
     *       'debug'           => false,
     *       'hosts'           => array(),
     *       'port'            => 389,
     *       'use_tls'         => false,
     *       'ldap_version'    => 3,        // using LDAPv3
     *       'auth_method'     => '',       // SASL authentication method (for proxy auth), e.g. DIGEST-MD5
     *       'numsub_filter'   => '(objectClass=organizationalUnit)', // with VLV, we also use numSubOrdinates to query the total number of records. Set this filter to get all numSubOrdinates attributes for counting
     *       'referrals'       => false,    // Sets the LDAP_OPT_REFERRALS option. Mostly used in multi-domain Active Directory setups
     *       'network_timeout' => 10,       // The timeout (in seconds) for connect + bind arrempts. This is only supported in PHP >= 5.3.0 with OpenLDAP 2.x
     *       'sizelimit'       => 0,        // Enables you to limit the count of entries fetched. Setting this to 0 means no limit.
     *       'timelimit'       => 0,        // Sets the number of seconds how long is spend on the search. Setting this to 0 means no limit.
     *       'vlv'             => false,    // force VLV off
     *       'config_root_dn'  => 'cn=config',  // Root DN to read config (e.g. vlv indexes) from
     *       'service_bind_dn' => 'uid=kolab-service,ou=Special Users,dc=example,dc=org',
     *       'service_bind_pw' => 'Welcome2KolabSystems',
     *       'root_dn'         => 'dc=example,dc=org',
     */
    protected $config = array(
        'sizelimit' => 0,
        'timelimit' => 0,
    );

    protected $debug_level = false;
    protected $list_page   = 1;
    protected $page_size   = 10;
    protected $icache      = array();
    protected $cache;

    // Use public method config_set('log_hook', $callback) to have $callback be
    // call_user_func'ed instead of the local log functions.
    protected $_log_hook;

    // Use public method config_set('config_get_hook', $callback) to have
    // $callback be call_user_func'ed instead of the local config_get function.
    protected $_config_get_hook;

    // Use public method config_set('config_set_hook', $callback) to have
    // $callback be call_user_func'ed instead of the local config_set function.
    protected $_config_set_hook;

    // Not Yet Implemented
    // Intended to allow hooking in for the purpose of caching.
    protected $_result_hook;

    // Runtime. These are not the variables you're looking for.
    protected $_current_bind_dn;
    protected $_current_bind_pw;
    protected $_current_host;
    protected $_supported_control = array();
    protected $_vlv_indexes_and_searches;

    /**
     * Constructor
     *
     * @param array $config Configuration parameters that have not already
     *                      been initialized. For configuration parameters
     *                      that have in fact been set, use the config_set()
     *                      method after initialization.
     */
    public function __construct($config = array())
    {
        if (!empty($config) && is_array($config)) {
            foreach ($config as $key => $value) {
                if (empty($this->config[$key])) {
                    $setter = 'config_set_' . $key;
                    if (method_exists($this, $setter)) {
                        $this->$setter($value);
                    }
                    else if (isset($this->$key)) {
                        $this->$key = $value;
                    }
                    else {
                        $this->config[$key] = $value;
                    }
                }
            }
        }
    }

    /**
     *  Add multiple entries to the directory information tree in one go.
     */
    public function add_entries($entries, $attributes = array())
    {
        // If $entries is an associative array, it's keys are DNs and its
        // values are the attributes for that DN.
        //
        // If $entries is a non-associative array, the attributes are expected
        // to be positional in $attributes.

        $result_set = array();

        if (array_keys($entries) == range(0, count($entries) - 1)) {
            // $entries is sequential
            if (count($entries) !== count($attributes)) {
                $this->_error("LDAP: Wrong entry/attribute count in " . __FUNCTION__);
                return false;
            }

            for ($i = 0; $i < count($entries); $i++) {
                $result_set[$i] = $this->add_entry($entries[$i], $attributes[$i]);
            }
        }
        else {
            // $entries is associative
            foreach ($entries as $entry_dn => $entry_attributes) {
                if (array_keys($attributes) !== range(0, count($attributes)-1)) {
                    // $attributes is associative as well, let's merge these
                    //
                    // $entry_attributes takes precedence, so is in the second
                    // position in array_merge()
                    $entry_attributes = array_merge($attributes, $entry_attributes);
                }

                $result_set[$entry_dn] = $this->add_entry($entry_dn, $entry_attributes);
            }
        }

        return $result_set;
    }

    /**
     * Add an entry to the directory information tree.
     */
    public function add_entry($entry_dn, $attributes)
    {
        // TODO:
        // - Get entry rdn attribute value from entry_dn and see if it exists in
        //   attributes -> issue warning if so (but not block the operation).
        $this->_debug("Entry DN", $entry_dn);
        $this->_debug("Attributes", $attributes);

        foreach ($attributes as $attr_name => $attr_value) {
            if (empty($attr_value)) {
                unset($attributes[$attr_name]);
            } else if (is_array($attr_value)) {
                $attributes[$attr_name] = array_values($attr_value);
            }
        }

        $this->_debug("C: Add $entry_dn: " . json_encode($attributes));

        if (!ldap_add($this->conn, $entry_dn, $attributes)) {
            $this->_debug("S: " . ldap_error($this->conn));
            $this->_warning("LDAP: Adding entry $entry_dn failed. " . ldap_error($this->conn));

            return false;
        }

        $this->_debug("S: OK");

        return true;
    }

    /**
     * Add replication agreements and initialize the consumer(s) for
     * $domain_root_dn.
     *
     * Searches the configured replicas for any of the current domain/config
     * databases, and uses this information to configure the additional
     * replication for the (new) domain database (at $domain_root_dn).
     *
     * Very specific to Netscape-based directory servers, and currently also
     * very specific to multi-master replication.
     */
    public function add_replication_agreements($domain_root_dn)
    {
        $replica_hosts = $this->list_replicas();

        if (empty($replica_hosts)) {
            return;
        }

        $result = $this->search($this->config_get('config_root_dn'), "(&(objectclass=nsDS5Replica)(nsDS5ReplicaType=3))", "sub");

        if (!$result) {
            $this->_debug("No replication configuration found.");
            return;
        }

        // Preserve the number of replicated databases we have, because the replication ID
        // can be calculated from the number of databases replicated, and the number of
        // servers.
        $num_replica_dbs = $result->count();
        $replicas        = $result->entries(true);
        $max_replica_agreements = 0;

        foreach ($replicas as $replica_dn => $replica_attrs) {
            $result = $this->search($replica_dn, "(objectclass=nsDS5ReplicationAgreement)", "sub");
            if ($result) {
                if ($max_replica_agreements < $result->count()) {
                    $max_replica_agreements    = $result->count();
                    $max_replica_agreements_dn = $replica_dn;
                }
            }
        }

        $max_repl_id = $num_replica_dbs * count($replica_hosts);

        $this->_debug("The current maximum replication ID is $max_repl_id");
        $this->_debug("The current maximum number of replication agreements for any database is $max_replica_agreements (for $max_replica_agreements_dn)");
        $this->_debug("With " . count($replica_hosts) . " replicas, the next is " . ($max_repl_id + 1) . " and the last one is " . ($max_repl_id + count($replica_hosts)));

        // Then add the replication agreements
        foreach ($replica_hosts as $num => $replica_host) {
            $ldap = new Net_LDAP3($this->config);
            $ldap->config_set('hosts', array($replica_host));
            $ldap->connect();
            $ldap->bind($this->_current_bind_dn, $this->_current_bind_pw);

            $replica_attrs = array(
                'cn' => 'replica',
                'objectclass' => array(
                    'top',
                    'nsds5replica',
                    'extensibleobject',
                ),
                'nsDS5ReplicaBindDN'     => $ldap->get_entry_attribute($replica_dn, "nsDS5ReplicaBindDN"),
                'nsDS5ReplicaId'         => ($max_repl_id + $num + 1),
                'nsDS5ReplicaRoot'       => $domain_root_dn,
                'nsDS5ReplicaType'       => $ldap->get_entry_attribute($replica_dn, "nsDS5ReplicaType"),
                'nsds5ReplicaPurgeDelay' => $ldap->get_entry_attribute($replica_dn, "nsds5ReplicaPurgeDelay"),
                'nsDS5Flags'             => $ldap->get_entry_attribute($replica_dn, "nsDS5Flags")
            );

            $new_replica_dn = 'cn=replica,cn="' . $domain_root_dn . '",cn=mapping tree,cn=config';

            $this->_debug("Adding $new_replica_dn to $replica_host with attributes: " . var_export($replica_attrs, true));

            $result = $ldap->add_entry($new_replica_dn, $replica_attrs);

            if (!$result) {
                $this->_error("LDAP: Could not add replication configuration to database for $domain_root_dn on $replica_host");
                continue;
            }

            $result = $ldap->search($replica_dn, "(objectclass=nsDS5ReplicationAgreement)", "sub");

            if (!$result) {
                $this->_error("LDAP: Host $replica_host does not have any replication agreements");
                continue;
            }

            $entries = $result->entries(true);
            $replica_agreement_tpl_dn = key($entries);

            $this->_debug("Using " . var_export($replica_agreement_tpl_dn, true) . " as the template for new replication agreements");

            foreach ($replica_hosts as $replicate_to_host) {
                // Skip the current server
                if ($replicate_to_host == $replica_host) {
                    continue;
                }

                $this->_debug("Adding a replication agreement for $domain_root_dn to $replicate_to_host on " . $replica_host);

                $attrs = array(
                    'objectclass',
                    'nsDS5ReplicaBindDN',
                    'nsDS5ReplicaCredentials',
                    'nsDS5ReplicaTransportInfo',
                    'nsDS5ReplicaBindMethod',
                    'nsDS5ReplicaHost',
                    'nsDS5ReplicaPort'
                );

                $replica_agreement_attrs = $ldap->get_entry_attributes($replica_agreement_tpl_dn, $attrs);
                $replica_agreement_attrs['cn'] = array_shift(explode('.', $replicate_to_host)) . str_replace(array('dc=',','), array('_',''), $domain_root_dn);
                $replica_agreement_attrs['nsDS5ReplicaRoot'] = $domain_root_dn;
                $replica_agreement_dn = "cn=" . $replica_agreement_attrs['cn'] . "," . $new_replica_dn;

                $this->_debug("Adding $replica_agreement_dn to $replica_host with attributes: " . var_export($replica_agreement_attrs, true));

                $result = $ldap->add_entry($replica_agreement_dn, $replica_agreement_attrs);

                if (!$result) {
                    $this->_error("LDAP: Failed adding $replica_agreement_dn");
                }
            }
        }

        $server_id = implode('', array_diff($replica_hosts, $this->_server_id_not));

        $this->_debug("About to trigger consumer initialization for replicas on current 'parent': $server_id");

        $result = $this->search($this->config_get('config_root_dn'), "(&(objectclass=nsDS5ReplicationAgreement)(nsds5replicaroot=$domain_root_dn))", "sub");

        if ($result) {
            foreach ($result->entries(true) as $agreement_dn => $agreement_attrs) {
                $this->modify_entry_attributes(
                    $agreement_dn,
                    array(
                        'replace' => array(
                            'nsds5BeginReplicaRefresh' => 'start',
                        ),
                    )
                );
            }
        }
    }

    public function attribute_details($attributes = array())
    {
        $schema = $this->init_schema();

        if (!$schema) {
            return array();
        }

        $attribs = $schema->getAll('attributes');

        $attributes_details = array();

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $attribs)) {
                $attrib_details = $attribs[$attribute];

                if (!empty($attrib_details['sup'])) {
                    foreach ($attrib_details['sup'] as $super_attrib) {
                        $_attrib_details = $attribs[$super_attrib];
                        if (is_array($_attrib_details)) {
                            $attrib_details = array_merge($_attrib_details, $attrib_details);
                        }
                    }
                }
            }
            else if (array_key_exists(strtolower($attribute), $attribs)) {
                $attrib_details = $attribs[strtolower($attribute)];

                if (!empty($attrib_details['sup'])) {
                    foreach ($attrib_details['sup'] as $super_attrib) {
                        $_attrib_details = $attribs[$super_attrib];
                        if (is_array($_attrib_details)) {
                            $attrib_details = array_merge($_attrib_details, $attrib_details);
                        }
                    }
                }
            }
            else {
                $this->_warning("LDAP: No schema details exist for attribute $attribute (which is strange)");
            }

            // The relevant parts only, please
            $attributes_details[$attribute] = array(
                'type'        => !empty($attrib_details['single-value']) ? 'text' : 'list',
                'description' => $attrib_details['desc'],
                'syntax'      => $attrib_details['syntax'],
                'max-length'  => $attrib_details['max-length'] ?: false,
            );
        }

        return $attributes_details;
    }

    public function attributes_allowed($objectclasses = array())
    {
        $this->_debug("Listing allowed_attributes for objectclasses", $objectclasses);

        if (!is_array($objectclasses) || empty($objectclasses)) {
            return false;
        }

        $schema = $this->init_schema();
        if (!$schema) {
            return false;
        }

        $may          = array();
        $must         = array();
        $superclasses = array();

        foreach ($objectclasses as $objectclass) {
            $superclass = $schema->superclass($objectclass);
            if (!empty($superclass)) {
                $superclasses = array_merge($superclass, $superclasses);
            }

            $_may  = $schema->may($objectclass);
            $_must = $schema->must($objectclass);

            if (is_array($_may)) {
                $may = array_merge($may, $_may);
            }
            if (is_array($_must)) {
                $must = array_merge($must, $_must);
            }
        }

        $may          = array_unique($may);
        $must         = array_unique($must);
        $superclasses = array_unique($superclasses);

        return array('may' => $may, 'must' => $must, 'super' => $superclasses);
    }

    public function classes_allowed()
    {
        $schema = $this->init_schema();
        if (!$schema) {
            return false;
        }

        $list    = $schema->getAll('objectclasses');
        $classes = array();

        foreach ($list as $class) {
            $classes[] = $class['name'];
        }

        return $classes;
    }

    /**
     * Bind connection with DN and password
     *
     * @param string $dn   Bind DN
     * @param string $pass Bind password
     *
     * @return boolean True on success, False on error
     */
    public function bind($bind_dn, $bind_pw)
    {
        if (!$this->conn) {
            return false;
        }

        if ($bind_dn == $this->_current_bind_dn) {
            return true;
        }

        $this->_debug("C: Bind [dn: $bind_dn]");

        if (@ldap_bind($this->conn, $bind_dn, $bind_pw)) {
            $this->_debug("S: OK");
            $this->_current_bind_dn = $bind_dn;
            $this->_current_bind_pw = $bind_pw;

            return true;
        }

        $this->_debug("S: ".ldap_error($this->conn));
        $this->_error("LDAP: Bind failed for dn=$bind_dn. ".ldap_error($this->conn));

        return false;
    }

    /**
     * Close connection to LDAP server
     */
    public function close()
    {
        if ($this->conn) {
            $this->_debug("C: Close");
            ldap_unbind($this->conn);

            $this->_current_bind_dn = null;
            $this->_current_bind_pw = null;
            $this->conn             = null;
        }
    }

    /**
     * Get the value of a configuration item.
     *
     * @param string $key     Configuration key
     * @param mixed  $default Default value to return
     */
    public function config_get($key, $default = null)
    {
        if (!empty($this->_config_get_hook)) {
            return call_user_func_array($this->_config_get_hook, array($key, $value));
        }
        else if (method_exists($this, "config_get_{$key}")) {
            return call_user_func(array($this, "config_get_$key"), $value);
        }
        else if (!isset($this->config[$key])) {
            return $default;
        }
        else {
            return $this->config[$key];
        }
    }

    /**
     * Set a configuration item to value.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     */
    public function config_set($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->config_set($k, $v);
            }
            return;
        }

        if (!empty($this->_config_set_hook)) {
            call_user_func($this->_config_set_hook, array($key, $value));
        }
        else if (method_exists($this, "config_set_{$key}")) {
            call_user_func_array(array($this, "config_set_$key"), array($value));
        }
        else if (property_exists($this, $key)) {
            $this->$key = $value;
        }
        else {
            // 'host' option is deprecated
            if ($key == 'host') {
                $this->config['hosts'] = (array) $value;
            }
            else {
                $this->config[$key] = $value;
            }
        }
    }

    /**
     * Establish a connection to the LDAP server
     */
    public function connect($host = null)
    {
        if (!function_exists('ldap_connect')) {
            $this->_error("No ldap support in this PHP installation");
            return false;
        }

        if (is_resource($this->conn)) {
            $this->_debug("Connection already exists");
            return true;
        }

        $hosts = !empty($host) ? $host : $this->config_get('hosts', array());
        $port  = $this->config_get('port', 389);

        foreach ((array) $hosts as $host) {
            $this->_debug("C: Connect [$host:$port]");

            if ($lc = @ldap_connect($host, $port)) {
                if ($this->config_get('use_tls', false) === true) {
                    if (!ldap_start_tls($lc)) {
                        $this->_debug("S: Could not start TLS. " . ldap_error($lc));
                        continue;
                    }
                }

                $this->_debug("S: OK");

                $ldap_version = $this->config_get('ldap_version', 3);
                $timeout      = $this->config_get('network_timeout');
                $referrals    = $this->config_get('referrals');

                ldap_set_option($lc, LDAP_OPT_PROTOCOL_VERSION, $ldap_version);

                if ($timeout) {
                    ldap_set_option($lc, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
                }

                if ($referrals !== null) {
                    ldap_set_option($lc, LDAP_OPT_REFERRALS, (bool) $referrals);
                }

                $this->_current_host = $host;
                $this->conn          = $lc;

                break;
            }

            $this->_debug("S: NOT OK");
        }

        if (!is_resource($this->conn)) {
            $this->_error("Could not connect to LDAP");
            return false;
        }

        return true;
    }

    /**
     *   Shortcut to ldap_delete()
     */
    public function delete_entry($entry_dn)
    {
        $this->_debug("C: Delete $entry_dn");

        if (ldap_delete($this->conn, $entry_dn) === false) {
            $this->_debug("S: " . ldap_error($this->conn));
            $this->_warning("LDAP: Removing entry $entry_dn failed. " . ldap_error($this->conn));
            return false;
        }

        $this->_debug("S: OK");

        return true;
    }

    /**
     * Deletes specified entry and all entries in the tree
     */
    public function delete_entry_recursive($entry_dn)
    {
        // searching for sub entries, but not scope sub, just one level
        $result = $this->search($entry_dn, '(|(objectclass=*)(objectclass=ldapsubentry))', 'one');

        if ($result) {
            $entries = $result->entries(true);

            foreach (array_keys($entries) as $sub_dn) {
                if (!$this->delete_entry_recursive($sub_dn)) {
                    return false;
                }
            }
        }

        if (!$this->delete_entry($entry_dn)) {
            return false;
        }

        return true;
    }

    public function effective_rights($subject)
    {
        $effective_rights_control_oid = "1.3.6.1.4.1.42.2.27.9.5.2";

        $supported_controls = $this->supported_controls();

        if (!in_array($effective_rights_control_oid, $supported_controls)) {
            $this->_debug("LDAP: No getEffectiveRights control in supportedControls");
            return false;
        }

        $attributes = array(
            'attributeLevelRights' => array(),
            'entryLevelRights' => array(),
        );

        $entry_dn = $this->entry_dn($subject);

        if (!$entry_dn) {
            $entry_dn = $this->config_get($subject . "_base_dn");
        }

        if (!$entry_dn) {
            $entry_dn = $this->config_get("base_dn");
        }

        if (!$entry_dn) {
            $entry_dn = $this->config_get("root_dn");
        }

        $this->_debug("effective_rights for subject $subject resolves to entry dn $entry_dn");

        $moz_ldapsearch = "/usr/lib64/mozldap/ldapsearch";
        if (!is_file($moz_ldapsearch)) {
            $moz_ldapsearch = "/usr/lib/mozldap/ldapsearch";
        }
        if (!is_file($moz_ldapsearch)) {
            $moz_ldapsearch = null;
        }

        if (empty($moz_ldapsearch)) {
            $this->_error("Mozilla LDAP C SDK binary ldapsearch not found, cannot get effective rights on subject $subject");
            return null;
        }

        $output = array();
        $command = Array(
                $moz_ldapsearch,
                '-x',
                '-h',
                $this->_current_host,
                '-p',
                $this->config_get('port', 389),
                '-b',
                escapeshellarg($entry_dn),
                '-s',
                'base',
                '-D',
                escapeshellarg($this->_current_bind_dn),
                '-w',
                escapeshellarg($this->_current_bind_pw)
            );

        if ($this->vendor_name() == "Oracle Corporation") {
            // For Oracle DSEE
            $command[] = "-J";
            $command[] = escapeshellarg(
                    implode(
                            ':',
                            Array(
                                    $effective_rights_control_oid,          // OID
                                    'true'                                  // Criticality
                                )
                        )
                );
            $command[] = "-c";
            $command[] = escapeshellarg(
                    'dn:' . $this->_current_bind_dn
                );

        } else {
            // For 389 DS:
            $command[] = "-J";
            $command[] = escapeshellarg(
                    implode(
                            ':',
                            Array(
                                    $effective_rights_control_oid,          // OID
                                    'true',                                 // Criticality
                                    'dn:' . $this->_current_bind_dn         // User DN
                                )
                        )
                );
        }

        // For both
        $command[] = '"(objectclass=*)"';
        $command[] = '"*"';

        if ($this->vendor_name() == "Oracle Corporation") {
            // Oracle DSEE
            $command[] = 'aclRights';
        }

        // remove password from debug log
        $command_debug     = $command;
        $command_debug[13] = '*';

        $command       = implode(' ', $command);
        $command_debug = implode(' ', $command_debug);

        $this->_debug("LDAP: Executing command: $command_debug");

        exec($command, $output, $return_code);

        $this->_debug("LDAP: Command output:" . var_export($output, true));
        $this->_debug("Return code: " . $return_code);

        if ($return_code) {
            $this->_error("Command $moz_ldapsearch returned error code: $return_code");
            return null;
        }

        $lines = array();
        foreach ($output as $line_num => $line) {
            if (substr($line, 0, 1) == " ") {
                $lines[count($lines)-1] .= trim($line);
            }
            else {
                $lines[] = trim($line);
            }
        }

        if ($this->vendor_name() == "Oracle Corporation") {
            // Example for attribute level rights:
            // aclRights;attributeLevel;$attr:$right:$bool,$right:$bool
            // Example for entry level rights:
            // aclRights;entryLevel: add:1,delete:1,read:1,write:1,proxy:1
            foreach ($lines as $line) {
                $line_components = explode(':', $line);
                $attribute_name = explode(';', array_shift($line_components));

                switch ($attribute_name[0]) {
                    case "aclRights":
                        $this->parse_aclrights($attributes, $line);
                        break;
                    case "dn":
                        $attributes[$attribute_name[0]] = trim(implode(';', $line_components));
                        break;
                    default:
                        break;
                }
            }

        } else {
            foreach ($lines as $line) {
                $line_components = explode(':', $line);
                $attribute_name  = array_shift($line_components);
                $attribute_value = trim(implode(':', $line_components));

                switch ($attribute_name) {
                    case "attributeLevelRights":
                        $attributes[$attribute_name] = $this->parse_attribute_level_rights($attribute_value);
                        break;
                    case "dn":
                        $attributes[$attribute_name] = $attribute_value;
                        break;
                    case "entryLevelRights":
                        $attributes[$attribute_name] = $this->parse_entry_level_rights($attribute_value);
                        break;
                    default:
                        break;
                }
            }
        }

        return $attributes;
    }

    /**
     * Resolve entry data to entry DN
     *
     * @param string $subject    Entry string (e.g. entry DN or unique attribute value)
     * @param array  $attributes Additional attributes
     * @param string $base_dn    Optional base DN
     *
     * @return string Entry DN string
     */
    public function entry_dn($subject, $attributes = array(), $base_dn = null)
    {
        $this->_debug("Net_LDAP3::entry_dn($subject)");
        $is_dn = ldap_explode_dn($subject, 1);

        if (is_array($is_dn) && array_key_exists("count", $is_dn) && $is_dn["count"] > 0) {
            $this->_debug("$subject is a dn");
            return $subject;
        }

        $this->_debug("$subject is not a dn");

        if (strlen($subject) < 32 || preg_match('/[^a-fA-F0-9-]/', $subject)) {
            $this->_debug("$subject is not a unique identifier");
            return;
        }

        $unique_attr = $this->config_get('unique_attribute', 'nsuniqueid');

        $this->_debug("Using unique_attribute " . var_export($unique_attr, true) . " at " . __FILE__ . ":" . __LINE__);

        $attributes  = array_merge(array($unique_attr => $subject), (array)$attributes);
        $subject     = $this->entry_find_by_attribute($attributes, $base_dn);

        if (!empty($subject)) {
            return key($subject);
        }
    }

    public function entry_find_by_attribute($attributes, $base_dn = null)
    {
        $this->_debug("Net_LDAP3::entry_find_by_attribute(\$attributes, \$base_dn) called with base_dn", $base_dn, "and attributes", $attributes);

        if (empty($attributes) || !is_array($attributes)) {
            return false;
        }

        if (empty($attributes[key($attributes)])) {
            return false;
        }

        $filter = count($attributes) ? "(&" : "";

        foreach ($attributes as $key => $value) {
            $filter .= "(" . $key . "=" . $value . ")";
        }

        $filter .= count($attributes) ? ")" : "";

        if (empty($base_dn)) {
            $base_dn = $this->config_get('root_dn');
            $this->_debug("Using base_dn from domain " . $this->domain . ": " . $base_dn);
        }

        $result = $this->search($base_dn, $filter, 'sub', array_keys($attributes));

        if ($result && $result->count() > 0) {
            $this->_debug("Results found: " . implode(', ', array_keys($result->entries(true))));
            return $result->entries(true);
        }
        else {
            $this->_debug("No result");
            return false;
        }
    }

    public function find_user_groups($member_dn)
    {
        $groups  = array();
        $root_dn = $this->config_get('root_dn');

        // TODO: Do not query for both, it's either one or the other
        $entries = $this->search($root_dn, "(|" .
            "(&(objectclass=groupofnames)(member=$member_dn))" .
            "(&(objectclass=groupofuniquenames)(uniquemember=$member_dn))" .
            ")"
        );

        if ($entries) {
            $groups = array_keys($entries->entries(true));
        }

        return $groups;
    }

    public function get_entry_attribute($subject_dn, $attribute)
    {
        $entry = $this->get_entry_attributes($subject_dn, (array)$attribute);

        return $entry[strtolower($attribute)];
    }

    public function get_entry_attributes($subject_dn, $attributes)
    {
        // @TODO: use get_entry?
        $result = $this->search($subject_dn, '(objectclass=*)', 'base', $attributes);

        if (!$result) {
            return array();
        }

        $entries  = $result->entries(true);
        $entry_dn = key($entries);
        $entry    = $entries[$entry_dn];

        return $entry;
    }

    /**
     * Get a specific LDAP entry, identified by its DN
     *
     * @param string $dn         Record identifier
     * @param array  $attributes Attributes to return
     *
     * @return array Hash array
     */
    public function get_entry($dn, $attributes = array())
    {
        $rec = null;

        if ($this->conn && $dn) {
            $this->_debug("C: Read [dn: $dn] [(objectclass=*)]");

            if ($ldap_result = @ldap_read($this->conn, $dn, '(objectclass=*)', $attributes)) {
                $this->_debug("S: OK");

                if ($entry = ldap_first_entry($this->conn, $ldap_result)) {
                    $rec = ldap_get_attributes($this->conn, $entry);
                }
            }
            else {
                $this->_debug("S: ".ldap_error($this->conn));
                $this->_warning("LDAP: Failed to read $dn. " . ldap_error($this->conn));
            }

            if (!empty($rec)) {
                $rec['dn'] = $dn; // Add in the dn for the entry.
            }
        }

        return $rec;
    }

    public function list_replicas()
    {
        $this->_debug("Finding replicas for this server.");

        // Search any host that is a replica for the current host
        $replica_hosts = $this->config_get('replica_hosts', array());
        $root_dn       = $this->config_get('config_root_dn');

        if (!empty($replica_hosts)) {
            return $replica_hosts;
        }

        $ldap = new Net_LDAP3($this->config);
        $ldap->connect();
        $ldap->bind($this->_current_bind_dn, $this->_current_bind_pw);

        $result = $ldap->search($root_dn, '(objectclass=nsds5replicationagreement)', 'sub', array('nsds5replicahost'));

        if (!$result) {
            $this->_debug("No replicas configured");
            return $replica_hosts;
        }

        $this->_debug("Replication agreements found: " . var_export($result->entries(true), true));

        foreach ($result->entries(true) as $dn => $attrs) {
            if (!in_array($attrs['nsds5replicahost'], $replica_hosts)) {
                $replica_hosts[] = $attrs['nsds5replicahost'];
            }
        }

        // $replica_hosts now holds the IDs of servers we are currently NOT
        // connected to. We might need this later in order to set
        $this->_server_id_not = $replica_hosts;

        $this->_debug("So far, we have the following replicas: " . var_export($replica_hosts, true));

        $ldap->close();

        foreach ($replica_hosts as $replica_host) {
            $ldap->config_set('hosts', array($replica_host));
            $ldap->connect();
            $ldap->bind($this->_current_bind_dn, $this->_current_bind_pw);

            $result = $ldap->search($root_dn, '(objectclass=nsds5replicationagreement)', 'sub', array('nsds5replicahost'));
            if (!$result) {
                $this->_debug("No replicas configured on $replica_host");
                $ldap->close();
                continue;
            }

            foreach ($result->entries(true) as $dn => $attrs) {
                if (!in_array($attrs['nsds5replicahost'], $replica_hosts)) {
                    $replica_hosts[] = $attrs['nsds5replicahost'];
                }
            }

            $ldap->close();
        }

        $this->config_set('replica_hosts', $replica_hosts);

        return $replica_hosts;
    }

    public function login($username, $password, $domain = null, &$attributes = null)
    {
        $this->_debug("Net_LDAP3::login($username,***,$domain)");

        $_bind_dn = $this->config_get('service_bind_dn');
        $_bind_pw = $this->config_get('service_bind_pw');

        if (empty($_bind_dn)) {
            $this->_debug("No valid service bind dn found.");
            return null;
        }

        if (empty($_bind_pw)) {
            $this->_debug("No valid service bind password found.");
            return null;
        }

        $bound = $this->bind($_bind_dn, $_bind_pw);

        if (!$bound) {
            $this->_debug("Could not bind with service bind credentials.");
            return null;
        }

        $entry_dn = $this->entry_dn($username);

        if (!empty($entry_dn)) {
            $bound = $this->bind($entry_dn, $password);

            if (!$bound) {
                $this->_error("LDAP: Could not bind with " . $entry_dn);
                return null;
            }

            // fetch user attributes if requested
            if (!empty($attributes)) {
                $attributes = $this->get_entry($entry_dn, $attributes);
                $attributes = self::normalize_entry($attributes, true);
            }

            return $entry_dn;
        }

        $base_dn = $this->config_get('root_dn');

        if (empty($base_dn)) {
            $this->_debug("Could not get a valid base dn to search.");
            return null;
        }

        $localpart = $username;

        if (empty($domain) ) {
            if (count(explode('@', $username)) > 1) {
                $_parts    = explode('@', $username);
                $localpart = $_parts[0];
                $domain    = $_parts[1];
            }
            else {
                $localpart = $username;
                $domain    = '';
            }
        }

        $realm  = $domain;
        $filter = $this->config_get("login_filter", null);

        if (empty($filter)) {
            $filter = $this->config_get("filter", null);
        }
        if (empty($filter)) {
            $filter = "(&(|(mail=%s)(mail=%U@%d)(alias=%s)(alias=%U@%d)(uid=%s))(objectclass=inetorgperson))";
        }

        $this->_debug("Net::LDAP3::login() original filter: " . $filter);

        $replace_patterns = array(
            '/%s/' => $username,
            '/%d/' => $domain,
            '/%U/' => $localpart,
            '/%r/' => $realm
        );

        $filter = preg_replace(array_keys($replace_patterns), array_values($replace_patterns), $filter);

        $this->_debug("Net::LDAP3::login() actual filter: " . $filter);

        $result = $this->search($base_dn, $filter, 'sub', $attributes);

        if (!$result) {
            $this->_debug("Could not search $base_dn with $filter");
            return null;
        }

        if ($result->count() > 1) {
            $this->_debug("Multiple entries found.");
            return null;
        }
        else if ($result->count() < 1) {
            $this->_debug("No entries found.");
            return null;
        }

        $entries  = $result->entries(true);
        $entry_dn = key($entries);

        $bound = $this->bind($entry_dn, $password);

        if (!$bound) {
            $this->_debug("Could not bind with " . $entry_dn);
            return null;
        }

        // replace attributes list with key-value data
        if (!empty($attributes)) {
            $attributes = $entries[$entry_dn];
        }

        return $entry_dn;
    }

    public function list_group_members($dn, $entry = null, $recurse = true)
    {
        $this->_debug("Net_LDAP3::list_group_members($dn)");

        if (is_array($entry) && in_array('objectclass', $entry)) {
            if (!in_array(array('groupofnames', 'groupofuniquenames', 'groupofurls'), $entry['objectclass'])) {
                $this->_debug("Called list_group_members on a non-group!");
                return array();
            }
        }
        else {
            $entry = $this->get_entry($dn, array('member', 'uniquemember', 'memberurl', 'objectclass'));

            if (!$entry) {
                return array();
            }
        }

        $group_members = array();

        foreach ((array)$entry['objectclass'] as $objectclass) {
            switch (strtolower($objectclass)) {
                case "groupofnames":
                case "kolabgroupofnames":
                    $group_members = array_merge($group_members, $this->list_group_member($dn, $entry['member'], $recurse));
                    break;
                case "groupofuniquenames":
                case "kolabgroupofuniquenames":
                    $group_members = array_merge($group_members, $this->list_group_uniquemember($dn, $entry['uniquemember'], $recurse));
                    break;
                case "groupofurls":
                    $group_members = array_merge($group_members, $this->list_group_memberurl($dn, $entry['memberurl'], $recurse));
                    break;
            }
        }

        return array_values(array_filter($group_members));
    }

    public function modify_entry($subject_dn, $old_attrs, $new_attrs)
    {
        $this->_debug("OLD ATTRIBUTES", $old_attrs);
        $this->_debug("NEW ATTRIBUTES", $new_attrs);

        // TODO: Get $rdn_attr - we have type_id in $new_attrs
        $dn_components  = ldap_explode_dn($subject_dn, 0);
        $rdn_components = explode('=', $dn_components[0]);
        $rdn_attr       = $rdn_components[0];

        $this->_debug("Net_LDAP3::modify_entry() using rdn attribute: " . $rdn_attr);

        $mod_array = array(
            'add'       => array(), // For use with ldap_mod_add()
            'del'       => array(), // For use with ldap_mod_del()
            'replace'   => array(), // For use with ldap_mod_replace()
            'rename'    => array(), // For use with ldap_rename()
        );

        // This is me cheating. Remove this special attribute.
        if (array_key_exists('ou', $old_attrs) || array_key_exists('ou', $new_attrs)) {
            $old_ou = is_array($old_attrs['ou']) ? array_shift($old_attrs['ou']) : $old_attrs['ou'];
            $new_ou = is_array($new_attrs['ou']) ? array_shift($new_attrs['ou']) : $new_attrs['ou'];
            unset($old_attrs['ou']);
            unset($new_attrs['ou']);
        }
        else {
            $old_ou = null;
            $new_ou = null;
        }

        // Compare each attribute value of the old attrs with the corresponding value
        // in the new attrs, if any.
        foreach ($old_attrs as $attr => $old_attr_value) {
            if (is_array($old_attr_value)) {
                if (count($old_attr_value) == 1) {
                    $old_attrs[$attr] = $old_attr_value[0];
                    $old_attr_value = $old_attrs[$attr];
                }
            }

            if (array_key_exists($attr, $new_attrs)) {
                if (is_array($new_attrs[$attr])) {
                    if (count($new_attrs[$attr]) == 1) {
                        $new_attrs[$attr] = $new_attrs[$attr][0];
                    }
                }

                if (is_array($old_attrs[$attr]) && is_array($new_attrs[$attr])) {
                    $_sort1 = $new_attrs[$attr];
                    sort($_sort1);
                    $_sort2 = $old_attr_value;
                    sort($_sort2);
                }
                else {
                    $_sort1 = true;
                    $_sort2 = false;
                }

                if ($new_attrs[$attr] !== $old_attr_value && $_sort1 !== $_sort2) {
                    $this->_debug("Attribute $attr changed from " . var_export($old_attr_value, true) . " to " . var_export($new_attrs[$attr], true));
                    if ($attr === $rdn_attr) {
                        $this->_debug("This attribute is the RDN attribute. Let's see if it is multi-valued, and if the original still exists in the new value.");
                        if (is_array($old_attrs[$attr])) {
                            if (!is_array($new_attrs[$attr])) {
                                if (in_array($new_attrs[$attr], $old_attrs[$attr])) {
                                    // TODO: Need to remove all $old_attrs[$attr] values not equal to $new_attrs[$attr], and not equal to the current $rdn_attr value [0]

                                    $this->_debug("old attrs. is array, new attrs. is not array. new attr. exists in old attrs.");

                                    $rdn_attr_value  = array_shift($old_attrs[$attr]);
                                    $_attr_to_remove = array();

                                    foreach ($old_attrs[$attr] as $value) {
                                        if (strtolower($value) != strtolower($new_attrs[$attr])) {
                                            $_attr_to_remove[] = $value;
                                        }
                                    }

                                    $this->_debug("Adding to delete attribute $attr values:" . implode(', ', $_attr_to_remove));

                                    $mod_array['del'][$attr] = $_attr_to_remove;

                                    if (strtolower($new_attrs[$attr]) !== strtolower($rdn_attr_value)) {
                                        $this->_debug("new attrs is not the same as the old rdn value, issuing a rename");
                                        $mod_array['rename']['dn']      = $subject_dn;
                                        $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . self::quote_string($new_attrs[$attr], true);
                                    }
                                }
                                else {
                                    $this->_debug("new attrs is not the same as any of the old rdn value, issuing a full rename");
                                    $mod_array['rename']['dn']      = $subject_dn;
                                    $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . self::quote_string($new_attrs[$attr], true);
                                }
                            }
                            else {
                                // TODO: See if the rdn attr. value is still in $new_attrs[$attr]
                                if (in_array($old_attrs[$attr][0], $new_attrs[$attr])) {
                                    $this->_debug("Simply replacing attr $attr as rdn attr value is preserved.");
                                    $mod_array['replace'][$attr] = $new_attrs[$attr];
                                }
                                else {
                                    // TODO: This fails.
                                    $mod_array['rename']['dn']      = $subject_dn;
                                    $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . self::quote_string($new_attrs[$attr][0], true);
                                    $mod_array['del'][$attr]        = $old_attrs[$attr][0];
                                }
                            }
                        }
                        else {
                            if (!is_array($new_attrs[$attr])) {
                                $this->_debug("Renaming " . $old_attrs[$attr] . " to " . $new_attrs[$attr]);
                                $mod_array['rename']['dn']      = $subject_dn;
                                $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . self::quote_string($new_attrs[$attr], true);
                            }
                            else {
                                $this->_debug("Adding to replace");
                                // An additional attribute value is being supplied. Just replace and continue.
                                $mod_array['replace'][$attr] = $new_attrs[$attr];
                                continue;
                            }
                        }
                    }
                    else {
                        if (!isset($new_attrs[$attr]) || $new_attrs[$attr] === '' || (is_array($new_attrs[$attr]) && empty($new_attrs[$attr]))) {
                            switch ($attr) {
                                case "userpassword":
                                    break;
                                default:
                                    $this->_debug("Adding to del: $attr");
                                    $mod_array['del'][$attr] = (array)($old_attr_value);
                                    break;
                            }
                        }
                        else {
                            $this->_debug("Adding to replace: $attr");
                            $mod_array['replace'][$attr] = (array)($new_attrs[$attr]);
                        }
                    }
                }
                else {
                    $this->_debug("Attribute $attr unchanged");
                }
            }
            else {
                // TODO: Since we're not shipping the entire object back and forth, and only post
                // part of the data... we don't know what is actually removed (think modifiedtimestamp, etc.)
                $this->_debug("Group attribute $attr not mentioned in \$new_attrs..., but not explicitly removed... by assumption");
            }
        }

        foreach ($new_attrs as $attr => $value) {
            // OU's parent base dn
            if ($attr == 'base_dn') {
                continue;
            }

            if (is_array($value)) {
                if (count($value) == 1) {
                    $new_attrs[$attr] = $value[0];
                    $value = $new_attrs[$attr];
                }
            }

            if (array_key_exists($attr, $old_attrs)) {
                if (is_array($old_attrs[$attr])) {
                    if (count($old_attrs[$attr]) == 1) {
                        $old_attrs[$attr] = $old_attrs[$attr][0];
                    }
                }

                if (is_array($new_attrs[$attr]) && is_array($old_attrs[$attr])) {
                    $_sort1 = $old_attrs[$attr];
                    sort($_sort1);
                    $_sort2 = $value;
                    sort($_sort2);
                }
                else {
                    $_sort1 = true;
                    $_sort2 = false;
                }

                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    if (!array_key_exists($attr, $mod_array['del'])) {
                        switch ($attr) {
                            case 'userpassword':
                                break;
                            default:
                                $this->_debug("Adding to del(2): $attr");
                                $mod_array['del'][$attr] = (array)($old_attrs[$attr]);
                                break;
                        }
                    }
                }
                else {
                    if (!($old_attrs[$attr] === $value) && !($attr === $rdn_attr) && !($_sort1 === $_sort2)) {
                        if (!array_key_exists($attr, $mod_array['replace'])) {
                            $this->_debug("Adding to replace(2): $attr");
                            $mod_array['replace'][$attr] = $value;
                        }
                    }
                }
            }
            else {
                if (!empty($value)) {
                    $mod_array['add'][$attr] = $value;
                }
            }
        }

        if (empty($old_ou)) {
            $subject_dn_components = ldap_explode_dn($subject_dn, 0);
            unset($subject_dn_components["count"]);
            $subject_rdn = array_shift($subject_dn_components);
            $old_ou      = implode(',', $subject_dn_components);
        }

        $subject_dn = self::unified_dn($subject_dn);
        $prefix     = self::unified_dn('ou=' . $old_ou) . ',';

        // object is an organizational unit
        if (strpos($subject_dn, $prefix) === 0) {
            $root = substr($subject_dn, strlen($prefix)); // remove ou=*,

            if ((!empty($new_attrs['base_dn']) && strtolower($new_attrs['base_dn']) !== strtolower($root))
                || (strtolower($old_ou) !== strtolower($new_ou))
            ) {
                if (!empty($new_attrs['base_dn'])) {
                    $root = $new_attrs['base_dn'];
                }

                $mod_array['rename']['new_parent'] = $root;
                $mod_array['rename']['dn']         = $subject_dn;
                $mod_array['rename']['new_rdn']    = 'ou=' . self::quote_string($new_ou, true);
            }
        }
        // not OU object, but changed ou attribute
        else if (!empty($old_ou) && !empty($new_ou)) {
            // unify DN strings for comparison
            $old_ou = self::unified_dn($old_ou);
            $new_ou = self::unified_dn($new_ou);

            if (strtolower($old_ou) !== strtolower($new_ou)) {
                $mod_array['rename']['new_parent'] = $new_ou;
                if (empty($mod_array['rename']['dn']) || empty($mod_array['rename']['new_rdn'])) {
                    $rdn_attr_value = self::quote_string($new_attrs[$rdn_attr], true);
                    $mod_array['rename']['dn']      = $subject_dn;
                    $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . $rdn_attr_value;
                }
            }
        }

        $this->_debug($mod_array);

        $result = $this->modify_entry_attributes($subject_dn, $mod_array);

        if ($result) {
            return $mod_array;
        }
    }

    /**
     * Bind connection with (SASL-) user and password
     *
     * @param string $authc Authentication user
     * @param string $pass  Bind password
     * @param string $authz Autorization user
     *
     * @return boolean True on success, False on error
     */
    public function sasl_bind($authc, $pass, $authz=null)
    {
        if (!$this->conn) {
            return false;
        }

        if (!function_exists('ldap_sasl_bind')) {
            $this->_error("LDAP: Unable to bind. ldap_sasl_bind() not exists");
            return false;
        }

        if (!empty($authz)) {
            $authz = 'u:' . $authz;
        }

        $method = $this->config_get('auth_method');
        if (empty($method)) {
            $method = 'DIGEST-MD5';
        }

        $this->_debug("C: Bind [mech: $method, authc: $authc, authz: $authz]");

        if (ldap_sasl_bind($this->conn, null, $pass, $method, null, $authc, $authz)) {
            $this->_debug("S: OK");
            return true;
        }

        $this->_debug("S: ".ldap_error($this->conn));
        $this->_error("LDAP: Bind failed for authcid=$authc. ".ldap_error($this->conn));

        return false;
    }

    /**
     * Execute LDAP search
     *
     * @param string $base_dn    Base DN to use for searching
     * @param string $filter     Filter string to query
     * @param string $scope      The LDAP scope (list|sub|base)
     * @param array  $attrs      List of entry attributes to read
     * @param array  $prop       Hash array with query configuration properties:
     *   - sort:   array of sort attributes (has to be in sync with the VLV index)
     *   - search: search string used for VLV controls
     * @param bool   $count_only Set to true if only entry count is requested
     *
     * @return mixed Net_LDAP3_Result object or number of entries (if $count_only=true) or False on failure
     */
    public function search($base_dn, $filter = '(objectclass=*)', $scope = 'sub', $attrs = array('dn'), $props = array(), $count_only = false)
    {
        if (!$this->conn) {
            $this->_debug("No active connection for " . __CLASS__ . "::" . __FUNCTION__);
            return false;
        }

        // make sure attributes list is not empty
        if (empty($attrs)) {
            $attrs = array('dn');
        }
        // make sure filter is not empty
        if (empty($filter)) {
            $filter = '(objectclass=*)';
        }

        $this->_debug("C: Search base dn: [$base_dn] scope [$scope] with filter [$filter]");

        $function = self::scope_to_function($scope, $ns_function);

        if (!$count_only && ($sort = $this->find_vlv($base_dn, $filter, $scope, $props['sort']))) {
            // when using VLV, we get the total count by...
            // ...either reading numSubOrdinates attribute
            if (($sub_filter = $this->config_get('numsub_filter')) &&
                ($result_count = @$ns_function($this->conn, $base_dn, $sub_filter, array('numSubOrdinates'), 0, 0, 0))
            ) {
                $counts = ldap_get_entries($this->conn, $result_count);
                for ($vlv_count = $j = 0; $j < $counts['count']; $j++) {
                    $vlv_count += $counts[$j]['numsubordinates'][0];
                }
                $this->_debug("D: total numsubordinates = " . $vlv_count);
            }
            // ...or by fetching all records dn and count them
            else if (!function_exists('ldap_parse_virtuallist_control')) {
                // @FIXME: this search will ignore $props['search']
                $vlv_count = $this->search($base_dn, $filter, $scope, array('dn'), $props, true);
            }

            $this->vlv_active = $this->_vlv_set_controls($sort, $this->list_page, $this->page_size,
                $this->_vlv_search($sort, $props['search']));
        }
        else {
            $this->vlv_active = false;
        }

        $sizelimit = (int) $this->config['sizelimit'];
        $timelimit = (int) $this->config['timelimit'];
        $phplimit  = (int) @ini_get('max_execution_time');

        // set LDAP time limit to be (one second) less than PHP time limit
        // otherwise we have no chance to log the error below
        if ($phplimit && $timelimit >= $phplimit) {
            $timelimit = $phplimit - 1;
        }

        $this->_debug("Using function $function on scope $scope (\$ns_function is $ns_function)");

        if ($this->vlv_active) {
            if (!empty($this->additional_filter)) {
                $filter = "(&" . $filter . $this->additional_filter . ")";
                $this->_debug("C: (With VLV) Setting a filter (with additional filter) of " . $filter);
            }
            else {
                $this->_debug("C: (With VLV) Setting a filter (without additional filter) of " . $filter);
            }
        }
        else {
            if (!empty($this->additional_filter)) {
                $filter = "(&" . $filter . $this->additional_filter . ")";
            }
            $this->_debug("C: (Without VLV) Setting a filter of " . $filter);
        }

        $this->_debug("Executing search with return attributes: " . var_export($attrs, true));

        $ldap_result = @$function($this->conn, $base_dn, $filter, $attrs, 0, $sizelimit, $timelimit);

        if (!$ldap_result) {
            $this->_warning("LDAP: $function failed for dn=$base_dn. " . ldap_error($this->conn));
            return false;
        }

        // when running on a patched PHP we can use the extended functions
        // to retrieve the total count from the LDAP search result
        if ($this->vlv_active && function_exists('ldap_parse_virtuallist_control')) {
            if (ldap_parse_result($this->conn, $ldap_result, $errcode, $matcheddn, $errmsg, $referrals, $serverctrls)) {
                ldap_parse_virtuallist_control($this->conn, $serverctrls, $last_offset, $vlv_count, $vresult);
                $this->_debug("S: VLV result: last_offset=$last_offset; content_count=$vlv_count");
            }
            else {
                $this->_debug("S: ".($errmsg ? $errmsg : ldap_error($this->conn)));
            }
        }
        else {
            $this->_debug("S: ".ldap_count_entries($this->conn, $ldap_result)." record(s) found");
        }

        $result = new Net_LDAP3_Result($this->conn, $base_dn, $filter, $scope, $ldap_result);

        if (isset($last_offset)) {
            $result->set('offset', $last_offset);
        }
        if (isset($vlv_count)) {
            $result->set('count', $vlv_count);
        }

        $result->set('vlv', $this->vlv_active);

        return $count_only ? $result->count() : $result;
    }

    /**
     * Similar to Net_LDAP3::search() but using a search array with multiple
     * keys and values that to continue to use the VLV but with an original
     * filter adding the search stuff to an additional filter.
     *
     * @see Net_LDAP3::search()
     */
    public function search_entries($base_dn, $filter = '(objectclass=*)', $scope = 'sub', $attrs = array('dn'), $props = array())
    {
        $this->_debug("Net_LDAP3::search_entries with search " . var_export($props, true));

        if (is_array($props['search']) && array_key_exists('params', $props['search'])) {
            $_search = $this->search_filter($props['search']);
            $this->_debug("C: Search filter: $_search");

            if (!empty($_search)) {
                $this->additional_filter = $_search;
            }
            else {
                $this->additional_filter = "(|";

                foreach ($props['search'] as $attr => $value) {
                    $this->additional_filter .= "(" . $attr . "=" . $this->_fuzzy_search_prefix() . $value . $this->_fuzzy_search_suffix() . ")";
                }

                $this->additional_filter .= ")";
            }

            $this->_debug("C: Setting an additional filter " . $this->additional_filter);
        }

        $search = $this->search($base_dn, $filter, $scope, $attrs, $props);

        $this->additional_filter = null;

        if (!$search) {
            $this->_debug("Net_LDAP3: Search did not succeed!");
            return false;
        }

        return $search;
    }

    /**
     * Create LDAP search filter string according to defined parameters.
     */
    public function search_filter($search)
    {
        if (empty($search) || !is_array($search) || empty($search['params'])) {
            return null;
        }

        $operators = array('=', '~=', '>=', '<=');
        $filter    = '';

        foreach ((array) $search['params'] as $field => $param) {
            $value = (array) $param['value'];

            switch ((string)$param['type']) {
                case 'prefix':
                    $prefix = '';
                    $suffix = '*';
                    break;

                case 'suffix':
                    $prefix = '*';
                    $suffix = '';
                    break;

                case 'exact':
                case '=':
                case '~=':
                case '>=':
                case '<=':
                    $prefix = '';
                    $suffix = '';

                    // this is a common query to find entry by DN, make sure
                    // it is a unified DN so special characters are handled correctly
                    if ($field == 'entrydn') {
                        $value = array_map(array('Net_LDAP3', 'unified_dn'), $value);
                    }

                    break;

                case 'exists':
                    $prefix = '*';
                    $suffix = '';
                    $param['value'] = '';
                    break;

                case 'both':
                default:
                    $prefix = '*';
                    $suffix = '*';
                    break;
            }

            $operator = $param['type'] && in_array($param['type'], $operators) ? $param['type'] : '=';

            if (count($value) < 2) {
                $value = array_pop($value);
            }

            if (is_array($value)) {
                $val_filter = array();
                foreach ($value as $val) {
                    $val          = self::quote_string($val);
                    $val_filter[] = "(" . $field . $operator . $prefix . $val . $suffix . ")";
                }
                $filter .= "(|" . implode($val_filter, '') . ")";
            }
            else {
                $value = self::quote_string($value);
                $filter .= "(" . $field . $operator . $prefix . $value . $suffix . ")";
            }
        }

        // join search parameters with specified operator ('OR' or 'AND')
        if (count($search['params']) > 1) {
            $filter = '(' . ($search['operator'] == 'AND' ? '&' : '|') . $filter . ')';
        }

        return $filter;
    }

    /**
     * Set properties for VLV-based paging
     *
     * @param number $page Page number to list (starting at 1)
     * @param number $size Number of entries to display on one page
     */
    public function set_vlv_page($page, $size = 10)
    {
        $this->list_page = $page;
        $this->page_size = $size;
    }

    /**
     * Turn an LDAP entry into a regular PHP array with attributes as keys.
     *
     * @param array $entry Attributes array as retrieved from ldap_get_attributes() or ldap_get_entries()
     * @param bool  $flat  Convert one-element-array values into strings
     *
     * @return array Hash array with attributes as keys
     */
    public static function normalize_entry($entry, $flat = false)
    {
        $rec = array();
        for ($i=0; $i < $entry['count']; $i++) {
            $attr = $entry[$i];
            for ($j=0; $j < $entry[$attr]['count']; $j++) {
                $rec[$attr][$j] = $entry[$attr][$j];
            }

            if ($flat && count($rec[$attr]) == 1) {
                $rec[$attr] = $rec[$attr][0];
            }
        }

        return $rec;
    }

    /**
     * Normalize a ldap result by converting entry attribute arrays into single values
     */
    public static function normalize_result($_result)
    {
        if (!is_array($_result)) {
            return array();
        }

        $result = array();

        for ($x = 0; $x < $_result['count']; $x++) {
            $dn    = $_result[$x]['dn'];
            $entry = self::normalize_entry($_result[$x], true);

            if (!empty($entry['objectclass'])) {
                if (is_array($entry['objectclass'])) {
                    $entry['objectclass'] = array_map('strtolower', $entry['objectclass']);
                }
                else {
                    $entry['objectclass'] = strtolower($entry['objectclass']);
                }
            }

            $result[$dn] = $entry;
        }

        return $result;
    }

    public static function scopeint2str($scope)
    {
        switch ($scope) {
            case 2:
                return 'sub';

            case 1:
                return 'one';

            case 0:
                return 'base';

            default:
                $this->_debug("Scope $scope is not a valid scope integer");
        }
    }

    /**
     * Choose the right PHP function according to scope property
     *
     * @param string $scope         The LDAP scope (sub|base|list)
     * @param string $ns_function   Function to be used for numSubOrdinates queries
     * @return string  PHP function to be used to query directory
     */
    public static function scope_to_function($scope, &$ns_function = null)
    {
        switch ($scope) {
            case 'sub':
                $function = $ns_function = 'ldap_search';
                break;
            case 'base':
                $function = $ns_function = 'ldap_read';
                break;
            case 'one':
            case 'list':
            default:
                $function    = 'ldap_list';
                $ns_function = 'ldap_read';
                break;
        }

        return $function;
    }

    private function config_set_config_get_hook($callback)
    {
        $this->_config_get_hook = $callback;
    }

    private function config_set_config_set_hook($callback)
    {
        $this->_config_set_hook = $callback;
    }

    /**
     * Sets the debug level both for this class and the ldap connection.
     */
    private function config_set_debug($value)
    {
        $this->config['debug'] = (bool) $value;
        ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, (int) $value);
    }

    /**
     *  Sets a log hook that is called with every log message in this module.
     */
    private function config_set_log_hook($callback)
    {
        $this->_log_hook = $callback;
    }

    /**
     * Find a matching VLV
     */
    protected function find_vlv($base_dn, $filter, $scope, $sort_attrs = null)
    {
        if ($scope == 'base') {
            return false;
        }

        $vlv_indexes = $this->find_vlv_indexes_and_searches();

        if (empty($vlv_indexes)) {
            return false;
        }

        $this->_debug("Existing vlv index and search information", $vlv_indexes);

        $filter  = strtolower($filter);
        $base_dn = self::unified_dn($base_dn);

        foreach ($vlv_indexes as $vlv_index) {
            if (!empty($vlv_index[$base_dn])) {
                $this->_debug("Found a VLV for base_dn: " . $base_dn);
                if ($vlv_index[$base_dn]['filter'] == $filter) {
                    if ($vlv_index[$base_dn]['scope'] == $scope) {
                        $this->_debug("Scope and filter matches");

                        // Not passing any sort attributes means you don't care
                        if (!empty($sort_attrs)) {
                            $sort_attrs = array_map('strtolower', (array) $sort_attrs);

                            foreach ($vlv_index[$base_dn]['sort'] as $sss_config) {
                                $sss_config = array_map('strtolower', $sss_config);
                                if (count(array_intersect($sort_attrs, $sss_config)) == count($sort_attrs)) {
                                    $this->_debug("Sorting matches");

                                    return $sort_attrs;
                                }
                            }

                            $this->_debug("Sorting does not match");
                        }
                        else {
                            $sort = array_filter((array) $vlv_index[$base_dn]['sort'][0]);
                            $this->_debug("Sorting unimportant");

                            return $sort;
                        }
                    }
                    else {
                        $this->_debug("Scope does not match");
                    }
                }
                else {
                    $this->_debug("Filter does not match");
                }
            }
        }

        return false;
    }

    /**
     * Return VLV indexes and searches including necessary configuration
     * details.
     */
    protected function find_vlv_indexes_and_searches()
    {
        // Use of Virtual List View control has been specifically disabled.
        if ($this->config['vlv'] === false) {
            return false;
        }

        // Virtual List View control has been configured in kolab.conf, for example;
        //
        // [ldap]
        // vlv = [
        //         {
        //                 'ou=People,dc=example,dc=org': {
        //                         'scope': 'sub',
        //                         'filter': '(objectclass=inetorgperson)',
        //                         'sort' : [
        //                                 [
        //                                         'displayname',
        //                                         'sn',
        //                                         'givenname',
        //                                         'cn'
        //                                     ]
        //                             ]
        //                     }
        //             },
        //         {
        //                 'ou=Groups,dc=example,dc=org': {
        //                         'scope': 'sub',
        //                         'filter': '(objectclass=groupofuniquenames)',
        //                         'sort' : [
        //                                 [
        //                                         'cn'
        //                                     ]
        //                             ]
        //                     }
        //             },
        //     ]
        //
        if (is_array($this->config['vlv'])) {
            return $this->config['vlv'];
        }

        // We have done this dance before.
        if ($this->_vlv_indexes_and_searches !== null) {
            return $this->_vlv_indexes_and_searches;
        }

        $this->_vlv_indexes_and_searches = array();

        $config_root_dn = $this->config_get('config_root_dn');

        if (empty($config_root_dn)) {
            return array();
        }

        if ($cached_config = $this->get_cache_data('vlvconfig')) {
            $this->_vlv_indexes_and_searches = $cached_config;
            return $this->_vlv_indexes_and_searches;
        }

        $this->_debug("No VLV information available yet, refreshing");

        $search_filter = '(objectclass=vlvsearch)';
        $search_result = ldap_search($this->conn, $config_root_dn, $search_filter, array('*'), 0, 0, 0);

        if ($search_result === false) {
            $this->_debug("Search for '$search_filter' on '$config_root_dn' failed:".ldap_error($this->conn));
            return;
        }

        $vlv_searches = new Net_LDAP3_Result($this->conn, $config_root_dn, $search_filter, 'sub', $search_result);

        if ($vlv_searches->count() < 1) {
            $this->_debug("Empty result from search for '(objectclass=vlvsearch)' on '$config_root_dn'");
            return;
        }

        $index_filter = '(objectclass=vlvindex)';

        foreach ($vlv_searches->entries(true) as $vlv_search_dn => $vlv_search_attrs) {
            // The attributes we are interested in are as follows:
            $_vlv_base_dn = self::unified_dn($vlv_search_attrs['vlvbase']);
            $_vlv_scope   = $vlv_search_attrs['vlvscope'];
            $_vlv_filter  = $vlv_search_attrs['vlvfilter'];

            // Multiple indexes may exist
            $index_result = ldap_search($this->conn, $vlv_search_dn, $index_filter, array('*'), 0, 0, 0);

            if ($index_result === false) {
                $this->_debug("Search for '$index_filter' on '$vlv_search_dn' failed:".ldap_error($this->conn));
                continue;
            }

            $vlv_indexes = new Net_LDAP3_Result($this->conn, $vlv_search_dn, $index_filter, 'sub', $index_result);
            $vlv_indexes = $vlv_indexes->entries(true);

            // Reset this one for each VLV search.
            $_vlv_sort = array();

            foreach ($vlv_indexes as $vlv_index_dn => $vlv_index_attrs) {
                $_vlv_sort[] = explode(' ', trim($vlv_index_attrs['vlvsort']));
            }

            $this->_vlv_indexes_and_searches[] = array(
                    $_vlv_base_dn => array(
                            'scope'  => self::scopeint2str($_vlv_scope),
                            'filter' => strtolower($_vlv_filter),
                            'sort'   => $_vlv_sort,
                        ),
                );
        }

        // cache this
        $this->set_cache_data('vlvconfig', $this->_vlv_indexes_and_searches);

        return $this->_vlv_indexes_and_searches;
    }

    private function init_schema()
    {
        // use PEAR include if autoloading failed
        if (!class_exists('Net_LDAP2')) {
            require_once('Net/LDAP2.php');
        }

        $port = $this->config_get('port', 389);
        $tls  = $this->config_get('use_tls', false);

        foreach ((array) $this->config_get('hosts') as $host) {
            $this->_debug("C: Connect [$host:$port]");

            $_ldap_cfg = array(
                'host'   => $host,
                'port'   => $port,
                'tls'    => $tls,
                'version' => 3,
                'binddn' => $this->config_get('service_bind_dn'),
                'bindpw' => $this->config_get('service_bind_pw')
            );

            $_ldap_schema_cache_cfg = array(
                'path' => "/tmp/" . $host . ":" . ($port ? $port : '389') . "-Net_LDAP2_Schema.cache",
                'max_age' => 86400,
            );

            $_ldap = Net_LDAP2::connect($_ldap_cfg);

            if (!is_a($_ldap, 'Net_LDAP2_Error')) {
                $this->_debug("S: OK");
                break;
            }

            $this->_debug("S: NOT OK");
            $this->_debug($_ldap->getMessage());
        }

        if (is_a($_ldap, 'Net_LDAP2_Error')) {
            return null;
        }

        $_ldap_schema_cache = new Net_LDAP2_SimpleFileSchemaCache($_ldap_schema_cache_cfg);

        $_ldap->registerSchemaCache($_ldap_schema_cache);

        // TODO: We should learn what LDAP tech. we're running against.
        // Perhaps with a scope base objectclass recognize rootdse entry
        $schema_root_dn = $this->config_get('schema_root_dn');

        if (!$schema_root_dn) {
            $_schema = $_ldap->schema();
        }

        return $_schema;
    }

    private function list_group_member($dn, $members, $recurse = true)
    {
        $this->_debug("Net_LDAP3::list_group_member($dn)");

        $members       = (array) $members;
        $group_members = array();

        // remove possible 'count' item
        unset($members['count']);

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        foreach ($members as $member) {
            $member_entry = $this->get_entry($member, array('member', 'uniquemember', 'memberurl', 'objectclass'));

            if (empty($member_entry)) {
                continue;
            }

            $group_members[$member] = $member;

            if ($recurse) {
                // Nested groups
                $group_group_members = $this->list_group_members($member, $member_entry);
                if ($group_group_members) {
                    $group_members = array_merge($group_group_members, $group_members);
                }
            }
        }

        return array_filter($group_members);
    }

    private function list_group_uniquemember($dn, $uniquemembers, $recurse = true)
    {
        $this->_debug("Net_LDAP3::list_group_uniquemember($dn)", $entry);

        $uniquemembers = (array)($uniquemembers);
        $group_members = array();

        // remove possible 'count' item
        unset($uniquemembers['count']);

        foreach ($uniquemembers as $member) {
            $member_entry = $this->get_entry($member, array('member', 'uniquemember', 'memberurl', 'objectclass'));

            if (empty($member_entry)) {
                continue;
            }

            $group_members[$member] = $member;

            if ($recurse) {
                // Nested groups
                $group_group_members = $this->list_group_members($member, $member_entry);
                if ($group_group_members) {
                    $group_members = array_merge($group_group_members, $group_members);
                }
            }
        }

        return array_filter($group_members);
    }

    private function list_group_memberurl($dn, $memberurls, $recurse = true)
    {
        $this->_debug("Net_LDAP3::list_group_memberurl($dn)");

        $group_members = array();
        $memberurls    = (array) $memberurls;
        $attributes    = array('member', 'uniquemember', 'memberurl', 'objectclass');

        // remove possible 'count' item
        unset($memberurls['count']);

        foreach ($memberurls as $url) {
            $ldap_uri = $this->parse_memberurl($url);
            $result   = $this->search($ldap_uri[3], $ldap_uri[6], 'sub', $attributes);

            if (!$result) {
                continue;
            }

            foreach ($result->entries(true) as $entry_dn => $_entry) {
                $group_members[$entry_dn] = $entry_dn;
                $this->_debug("Found " . $entry_dn);

                if ($recurse) {
                    // Nested group
                    $group_group_members = $this->list_group_members($entry_dn, $_entry);
                    if ($group_group_members) {
                        $group_members = array_merge($group_members, $group_group_members);
                    }
                }
            }
        }

        return array_filter($group_members);
    }

    /**
     * memberUrl attribute parser
     *
     * @param string $url URL string
     *
     * @return array URL elements
     */
    private function parse_memberurl($url)
    {
        preg_match('/(.*):\/\/(.*)\/(.*)\?(.*)\?(.*)\?(.*)/', $url, $matches);
        return $matches;
    }

    private function modify_entry_attributes($subject_dn, $attributes)
    {
        if (is_array($attributes['rename']) && !empty($attributes['rename'])) {
            $olddn      = $attributes['rename']['dn'];
            $newrdn     = $attributes['rename']['new_rdn'];
            $new_parent = $attributes['rename']['new_parent'];

            $this->_debug("C: Rename $olddn to $newrdn,$new_parent");

            // Note: for some reason the operation fails if RDN contains special characters
            // and last argument of ldap_rename() is set to TRUE. That's why we use FALSE.
            // However, we need to modify RDN attribute value later, otherwise it
            // will contain an array of previous and current values
            for ($i = 1; $i >= 0; $i--) {
                $result = ldap_rename($this->conn, $olddn, $newrdn, $new_parent, $i == 1);
                if ($result) {
                    break;
                }
            }

            if ($result) {
                $this->_debug("S: OK");

                if ($new_parent) {
                    $subject_dn = $newrdn . ',' . $new_parent;
                }
                else {
                    $old_parent_dn_components = ldap_explode_dn($olddn, 0);
                    unset($old_parent_dn_components["count"]);
                    $old_rdn       = array_shift($old_parent_dn_components);
                    $old_parent_dn = implode(",", $old_parent_dn_components);
                    $subject_dn    = $newrdn . ',' . $old_parent_dn;
                }

                // modify RDN attribute value, see note above
                if (!$i && empty($attributes['replace'][$attr])) {
                    list($attr, $val) = explode('=', $newrdn, 2);
                    $attributes['replace'][$attr] = self::quote_string($val, true, true);
                }
            }
            else {
                $this->_debug("S: " . ldap_error($this->conn));
                $this->_warning("LDAP: Failed to rename $olddn to $newrdn,$new_parent. " . ldap_error($this->conn));
                return false;
            }
        }

        if (is_array($attributes['replace']) && !empty($attributes['replace'])) {
            $this->_debug("C: Mod-Replace $subject_dn: " . json_encode($attributes['replace']));

            $result = ldap_mod_replace($this->conn, $subject_dn, $attributes['replace']);

            if ($result) {
                $this->_debug("S: OK");
            }
            else {
                $this->_debug("S: " . ldap_error($this->conn));
                $this->_warning("LDAP: Failed to replace attributes on $subject_dn: " . json_encode($attributes['replace']));
                return false;
            }
        }

        if (is_array($attributes['del']) && !empty($attributes['del'])) {
            $this->_debug("C: Mod-Delete $subject_dn: " . json_encode($attributes['del']));

            $result = ldap_mod_del($this->conn, $subject_dn, $attributes['del']);

            if ($result) {
                $this->_debug("S: OK");
            }
            else {
                $this->_debug("S: " . ldap_error($this->conn));
                $this->_warning("LDAP: Failed to delete attributes on $subject_dn: " . json_encode($attributes['del']));
                return false;
            }
        }

        if (is_array($attributes['add']) && !empty($attributes['add'])) {
            $this->_debug("C: Mod-Add $subject_dn: " . json_encode($attributes['add']));

            $result = ldap_mod_add($this->conn, $subject_dn, $attributes['add']);

            if ($result) {
                $this->_debug("S: OK");
            }
            else {
                $this->_debug("S: " . ldap_error($this->conn));
                $this->_warning("LDAP: Failed to add attributes on $subject_dn: " . json_encode($attributes['add']));
                return false;
            }
        }

        return true;
    }

    private function parse_aclrights(&$attributes, $attribute_value)
    {
        $components = explode(':', $attribute_value);
        $_acl_target = array_shift($components);
        $_acl_value = trim(implode(':', $components));

        $_acl_components = explode(';', $_acl_target);

        switch ($_acl_components[1]) {
            case "entryLevel":
                $attributes['entryLevelRights'] = Array();
                $_acl_value = explode(',', $_acl_value);

                foreach ($_acl_value as $right) {
                    list($method, $bool) = explode(':', $right);
                    if ($bool == "1" && !in_array($method, $attributes['entryLevelRights'])) {
                        $attributes['entryLevelRights'][] = $method;
                    }
                }

                break;

            case "attributeLevel":
                $attributes['attributeLevelRights'][$_acl_components[2]] = Array();
                $_acl_value = explode(',', $_acl_value);

                foreach ($_acl_value as $right) {
                    list($method, $bool) = explode(':', $right);
                    if ($bool == "1" && !in_array($method, $attributes['attributeLevelRights'][$_acl_components[2]])) {
                        $attributes['attributeLevelRights'][$_acl_components[2]][] = $method;
                    }
                }

                break;

            default:
                break;
        }
    }

    private function parse_attribute_level_rights($attribute_value)
    {
        $attribute_value  = str_replace(", ", ",", $attribute_value);
        $attribute_values = explode(",", $attribute_value);
        $attribute_value  = array();

        foreach ($attribute_values as $access_right) {
            $access_right_components = explode(":", $access_right);
            $access_attribute        = strtolower(array_shift($access_right_components));
            $access_value            = array_shift($access_right_components);

            $attribute_value[$access_attribute] = array();

            for ($i = 0; $i < strlen($access_value); $i++) {
                $method = $this->attribute_level_rights_map[substr($access_value, $i, 1)];

                if (!in_array($method, $attribute_value[$access_attribute])) {
                    $attribute_value[$access_attribute][] = $method;
                }
            }
        }

        return $attribute_value;
    }

    private function parse_entry_level_rights($attribute_value)
    {
        $_attribute_value = array();

        for ($i = 0; $i < strlen($attribute_value); $i++) {
            $method = $this->entry_level_rights_map[substr($attribute_value, $i, 1)];

            if (!in_array($method, $_attribute_value)) {
                $_attribute_value[] = $method;
            }
        }

        return $_attribute_value;
    }

    private function supported_controls()
    {
        if (!empty($this->supported_controls)) {
            return $this->supported_controls;
        }

        $this->_info("Obtaining supported controls");

        if ($result = $this->search('', '(objectclass=*)', 'base', array('supportedcontrol'))) {
            $result  = $result->entries(true);
            $control = $result['']['supportedcontrol'];
        }
        else {
            $control = array();
        }

        $this->_info("Obtained " . count($control) . " supported controls");
        $this->supported_controls = $control;

        return $control;
    }

    private function vendor_name()
    {
        if (!empty($this->vendor_name)) {
            return $this->vendor_name;
        }

        $this->_info("Obtaining LDAP server vendor name");

        if ($result = $this->search('', '(objectclass=*)', 'base', array('vendorname'))) {
            $result  = $result->entries(true);
            $name = $result['']['vendorname'];
        }
        else {
            $name = false;
        }

        if ($name !== false) {
            $this->_info("Vendor name is $name");
        } else {
            $this->_info("No vendor name!");
        }

        $this->vendor = $name;

        return $name;
    }

    protected function _alert()
    {
        $this->__log(LOG_ALERT, func_get_args());
    }

    protected function _critical()
    {
        $this->__log(LOG_CRIT, func_get_args());
    }

    protected function _debug()
    {
        $this->__log(LOG_DEBUG, func_get_args());
    }

    protected function _emergency()
    {
        $this->__log(LOG_EMERG, func_get_args());
    }

    protected function _error()
    {
        $this->__log(LOG_ERR, func_get_args());
    }

    protected function _info()
    {
        $this->__log(LOG_INFO, func_get_args());
    }

    protected function _notice()
    {
        $this->__log(LOG_NOTICE, func_get_args());
    }

    protected function _warning()
    {
        $this->__log(LOG_WARNING, func_get_args());
    }

    /**
     *  Log a message.
     */
    private function __log($level, $args)
    {
        $msg = array();

        foreach ($args as $arg) {
            $msg[] = !is_string($arg) ? var_export($arg, true) : $arg;
        }

        if (!empty($this->_log_hook)) {
            call_user_func_array($this->_log_hook, array($level, $msg));
            return;
        }

        if ($this->debug_level > 0) {
            syslog($level, implode("\n", $msg));
        }
    }

    /**
     * Add BER sequence with correct length and the given identifier
     */
    private static function _ber_addseq($str, $identifier)
    {
        $len = dechex(strlen($str)/2);
        if (strlen($len) % 2 != 0) {
            $len = '0'.$len;
        }

        return $identifier . $len . $str;
    }

    /**
     * Returns BER encoded integer value in hex format
     */
    private static function _ber_encode_int($offset)
    {
        $val    = dechex($offset);
        $prefix = '';

        // check if bit 8 of high byte is 1
        if (preg_match('/^[89abcdef]/', $val)) {
            $prefix = '00';
        }

        if (strlen($val)%2 != 0) {
            $prefix .= '0';
        }

        return $prefix . $val;
    }

    /**
     * Quotes attribute value string
     *
     * @param string $str     Attribute value
     * @param bool   $dn      True if the attribute is a DN
     * @param bool   $reverse Do reverse replacement
     *
     * @return string Quoted string
     */
    public static function quote_string($str, $is_dn = false, $reverse = false)
    {
        // take first entry if array given
        if (is_array($str)) {
            $str = reset($str);
        }

        if ($is_dn) {
            $replace = array(
                ',' => '\2c',
                '=' => '\3d',
                '+' => '\2b',
                '<' => '\3c',
                '>' => '\3e',
                ';' => '\3b',
                "\\"=> '\5c',
                '"' => '\22',
                '#' => '\23'
            );
        }
        else {
            $replace = array(
                '*' => '\2a',
                '(' => '\28',
                ')' => '\29',
                "\\" => '\5c',
                '/' => '\2f'
            );
        }

        if ($reverse) {
            return str_replace(array_values($replace), array_keys($replace), $str);
        }

        return strtr($str, $replace);
    }

    /**
     * Unify DN string for comparison
     *
     * @para string $str DN string
     *
     * @return string Unified DN string
     */
    public static function unified_dn($str)
    {
        $result = array();

        foreach (explode(',', $str) as $token) {
            list($attr, $value) = explode('=', $token, 2);

            $pos = 0;
            while (preg_match('/\\\\[0-9a-fA-F]{2}/', $value, $matches, PREG_OFFSET_CAPTURE, $pos)) {
                $char  = chr(hexdec(substr($matches[0][0], 1)));
                $pos   = $matches[0][1];
                $value = substr_replace($value, $char, $pos, 3);
                $pos += 1;
            }

            $result[] = $attr . '=' . self::quote_string($value, true);
        }

        return implode(',', $result);
    }

    /**
     * create ber encoding for sort control
     *
     * @param array List of cols to sort by
     * @return string BER encoded option value
     */
    private static function _sort_ber_encode($sortcols)
    {
        $str = '';
        foreach (array_reverse((array)$sortcols) as $col) {
            $ber_val = self::_string2hex($col);

            // 30 = ber sequence with a length of octet value
            // 04 = octet string with a length of the ascii value
            $oct = self::_ber_addseq($ber_val, '04');
            $str = self::_ber_addseq($oct, '30') . $str;
        }

        // now tack on sequence identifier and length
        $str = self::_ber_addseq($str, '30');

        return pack('H'.strlen($str), $str);
    }

    /**
     * Returns ascii string encoded in hex
     */
    private static function _string2hex($str)
    {
        $hex = '';
        for ($i=0; $i < strlen($str); $i++)
            $hex .= dechex(ord($str[$i]));

        return $hex;
    }

    /**
     * Generate BER encoded string for Virtual List View option
     *
     * @param integer List offset (first record)
     * @param integer Records per page
     * @return string BER encoded option value
     */
    private static function _vlv_ber_encode($offset, $rpp, $search = '')
    {
        // This string is ber-encoded, php will prefix this value with:
        // 04 (octet string) and 10 (length of 16 bytes)
        // the code behind this string is broken down as follows:
        // 30 = ber sequence with a length of 0e (14) bytes following
        // 02 = type integer (in two's complement form) with 2 bytes following (beforeCount): 01 00 (ie 0)
        // 02 = type integer (in two's complement form) with 2 bytes following (afterCount):  01 18 (ie 25-1=24)
        // a0 = type context-specific/constructed with a length of 06 (6) bytes following
        // 02 = type integer with 2 bytes following (offset): 01 01 (ie 1)
        // 02 = type integer with 2 bytes following (contentCount):  01 00

        // whith a search string present:
        // 81 = type context-specific/constructed with a length of 04 (4) bytes following (the length will change here)
        // 81 indicates a user string is present where as a a0 indicates just a offset search
        // 81 = type context-specific/constructed with a length of 06 (6) bytes following

        // the following info was taken from the ISO/IEC 8825-1:2003 x.690 standard re: the
        // encoding of integer values (note: these values are in
        // two-complement form so since offset will never be negative bit 8 of the
        // leftmost octet should never by set to 1):
        // 8.3.2: If the contents octets of an integer value encoding consist
        // of more than one octet, then the bits of the first octet (rightmost) and bit 8
        // of the second (to the left of first octet) octet:
        // a) shall not all be ones; and
        // b) shall not all be zero

        if ($search) {
            $search  = preg_replace('/[^-[:alpha:] ,.()0-9]+/', '', $search);
            $ber_val = self::_string2hex($search);
            $str     = self::_ber_addseq($ber_val, '81');
        }
        else {
            // construct the string from right to left
            $str = "020100"; # contentCount

            // returns encoded integer value in hex format
            $ber_val = self::_ber_encode_int($offset);

            // calculate octet length of $ber_val
            $str = self::_ber_addseq($ber_val, '02') . $str;

            // now compute length over $str
            $str = self::_ber_addseq($str, 'a0');
        }

        // now tack on records per page
        $str = "020100" . self::_ber_addseq(self::_ber_encode_int($rpp-1), '02') . $str;

        // now tack on sequence identifier and length
        $str = self::_ber_addseq($str, '30');

        return pack('H'.strlen($str), $str);
    }

    private function _fuzzy_search_prefix()
    {
        switch ($this->config_get("fuzzy_search", 2)) {
            case 2:
                return "*";
                break;
            case 1:
            case 0:
            default:
                return "";
                break;
        }
    }

    private function _fuzzy_search_suffix()
    {
        switch ($this->config_get("fuzzy_search", 2)) {
            case 2:
                return "*";
                break;
            case 1:
                return "*";
            case 0:
            default:
                return "";
                break;
        }
    }

    /**
     * Return the search string value to be used in VLV controls
     *
     * @param array        $sort   List of attributes in vlv index
     * @param array|string $search Search string or attribute => value hash
     *
     * @return string Search string
     */
    private function _vlv_search($sort, $search)
    {
        if (!empty($this->additional_filter)) {
            $this->_debug("Not setting a VLV search filter because we already have a filter");
            return;
        }

        if (empty($search)) {
            return;
        }

        foreach ((array) $search as $attr => $value) {
            if ($attr && !in_array(strtolower($attr), $sort)) {
                $this->_debug("Cannot use VLV search using attribute not indexed: $attr (not in " . var_export($sort, true) . ")");
                return;
            }
            else {
                return $value . $this->_fuzzy_search_suffix();
            }
        }
    }

    /**
     * Set server controls for Virtual List View (paginated listing)
     */
    private function _vlv_set_controls($sort, $list_page, $page_size, $search = null)
    {
        $sort_ctrl = array(
            'oid'   => "1.2.840.113556.1.4.473",
            'value' => self::_sort_ber_encode($sort)
        );

        if (!empty($search)) {
            $this->_debug("_vlv_set_controls to include search: " . var_export($search, true));
        }

        $vlv_ctrl  = array(
            'oid' => "2.16.840.1.113730.3.4.9",
            'value' => self::_vlv_ber_encode(
                    $offset = ($list_page-1) * $page_size + 1,
                    $page_size,
                    $search
            ),
            'iscritical' => true
        );

        $this->_debug("C: set controls sort=" . join(' ', unpack('H'.(strlen($sort_ctrl['value'])*2), $sort_ctrl['value']))
            . " (" . implode(',', (array) $sort) . ");"
            . " vlv=" . join(' ', (unpack('H'.(strlen($vlv_ctrl['value'])*2), $vlv_ctrl['value']))) . " ($offset/$page_size)");

        if (!ldap_set_option($this->conn, LDAP_OPT_SERVER_CONTROLS, array($sort_ctrl, $vlv_ctrl))) {
            $this->_debug("S: ".ldap_error($this->conn));
            $this->set_error(self::ERROR_SEARCH, 'vlvnotsupported');

            return false;
        }

        return true;
    }

    /**
     * Get global handle for cache access
     *
     * @return object Cache object
     */
    public function get_cache()
    {
        if ($this->cache === true) {
            // no memcache support in PHP
            if (!class_exists('Memcache')) {
                $this->cache = false;
                return false;
            }

            // add all configured hosts to pool
            $pconnect = $this->config_get('memcache_pconnect');
            $hosts    = $this->config_get('memcache_hosts');

            if ($hosts) {
                $this->cache        = new Memcache;
                $this->mc_available = 0;

                $hosts = explode(',', $hosts);
                foreach ($hosts as $host) {
                    $host = trim($host);
                    if (substr($host, 0, 7) != 'unix://') {
                        list($host, $port) = explode(':', $host);
                        if (!$port) $port = 11211;
                    }
                    else {
                        $port = 0;
                    }

                    $this->mc_available += intval($this->cache->addServer(
                        $host, $port, $pconnect, 1, 1, 15, false, array($this, 'memcache_failure')));
                }

                // test connection and failover (will result in $this->mc_available == 0 on complete failure)
                $this->cache->increment('__CONNECTIONTEST__', 1);  // NOP if key doesn't exist
            }

            if (!$this->mc_available) {
                $this->cache = false;
            }
        }

        return $this->cache;
    }

    /**
     * Callback for memcache failure
     */
    public function memcache_failure($host, $port)
    {
        static $seen = array();

        // only report once
        if (!$seen["$host:$port"]++) {
            $this->mc_available--;
            $this->_error("LDAP: Memcache failure on host $host:$port");
        }
    }

    /**
     * Get cached data
     *
     * @param string $key Cache key
     *
     * @return mixed Cached value
     */
    public function get_cache_data($key)
    {
        if ($cache = $this->get_cache()) {
            return $cache->get($key);
        }
    }

    /**
     * Store cached data
     *
     * @param string $key  Cache key
     * @param mixed  $data Data
     * @param int    $ttl  Cache TTL in seconds
     *
     * @return bool False on failure or when cache is disabled, True if data was saved succesfully
     */
    public function set_cache_data($key, $data, $ttl = 3600)
    {
        if ($cache = $this->get_cache()) {
            if (!method_exists($cache, 'replace') || !$cache->replace($key, $data, MEMCACHE_COMPRESSED, $ttl)) {
                return $cache->set($key, $data, MEMCACHE_COMPRESSED, $ttl);
            }
            else {
                return true;
            }
        }

        return false;
    }

    /**
     * Translate a domain name into it's corresponding root dn.
     *
     * @param string $domain Domain name
     *
     * @return string|bool Domain root DN or False on error
     */
    public function domain_root_dn($domain)
    {
        if (empty($domain)) {
            return false;
        }

        $ckey = 'domain.root::' . $domain;
        if ($result = $this->icache[$ckey]) {
            return $result;
        }

        $this->_debug("Net_LDAP3::domain_root_dn($domain)");

        if ($entry_attrs = $this->find_domain($domain)) {
            $name_attribute = $this->config_get('domain_name_attribute');

            if (empty($name_attribute)) {
                $name_attribute = 'associateddomain';
            }

            if (is_array($entry_attrs)) {
                if (!empty($entry_attrs['inetdomainbasedn'])) {
                    $domain_root_dn = $entry_attrs['inetdomainbasedn'];
                }
                else {
                    if (is_array($entry_attrs[$name_attribute])) {
                        $domain_root_dn = $this->_standard_root_dn($entry_attrs[$name_attribute][0]);
                    }
                    else {
                        $domain_root_dn = $this->_standard_root_dn($entry_attrs[$name_attribute]);
                    }
                }
            }
        }

        if (empty($domain_root_dn)) {
            $domain_root_dn = $this->_standard_root_dn($domain);
        }

        $this->_debug("Net_LDAP3::domain_root_dn() result: $domain_root_dn");

        return $this->icache[$ckey] = $domain_root_dn;
    }

    /**
     * Find domain by name
     *
     * @param string $domain     Domain name
     * @param array  $attributes Result attributes
     *
     * @return array|bool Domain attributes (plus 'dn' attribute) or False if not found
     */
    public function find_domain($domain, $attributes = array('*'))
    {
        if (empty($domain)) {
            return false;
        }

        $ckey  = 'domain::' . $domain;
        $ickey = $ckey . '::' . md5(implode(',', $attributes));

        if (isset($this->icache[$ickey])) {
            return $this->icache[$ickey];
        }

        $this->_debug("Net_LDAP3::find_domain($domain)");

        // use cache
        $domain_dn = $this->get_cache_data($ckey);

        if ($domain_dn) {
            $result = $this->get_entry_attributes($domain_dn, $attributes);
            if (!empty($result)) {
                $result['dn'] = $domain_dn;
            }
            else {
                $result = false;
            }
        }
        else if ($domain_base_dn = $this->config_get('domain_base_dn')) {
            $domain_filter  = $this->config_get('domain_filter');

            if (strpos($domain_filter, '%s') !== false) {
                $domain_filter = str_replace('%s', self::quote_string($domain), $domain_filter);
            }
            else {
                $name_attribute = $this->config_get('domain_name_attribute');
                if (empty($name_attribute)) {
                    $name_attribute = 'associateddomain';
                }

                $domain_filter = "(&" . $domain_filter . "(" . $name_attribute . "=" . self::quote_string($domain) . "))";
            }

            if ($result = $this->search($domain_base_dn, $domain_filter, 'sub', $attributes)) {
                $result       = $result->entries(true);
                $domain_dn    = key($result);

                if (empty($domain_dn)) {
                    $result = false;
                }
                else {
                    $result       = $result[$domain_dn];
                    $result['dn'] = $domain_dn;

                    // cache domain DN
                    $this->set_cache_data($ckey, $domain_dn);
                }
            }
        }

        $this->_debug("Net_LDAP3::find_domain() result: " . var_export($result, true));

        return $this->icache[$ickey] = $result;
    }

    /**
     * From a domain name, such as 'kanarip.com', create a standard root
     * dn, such as 'dc=kanarip,dc=com'.
     *
     * As the parameter $associatedDomains, either pass it an array (such
     * as may have been returned by ldap_get_entries() or perhaps
     * ldap_list()), where the function will assume the first value
     * ($array[0]) to be the uber-level domain name, or pass it a string
     * such as 'kanarip.nl'.
     *
     * @return string
     */
    protected function _standard_root_dn($associatedDomains)
    {
        if (is_array($associatedDomains)) {
            // Usually, the associatedDomain in position 0 is the naming attribute associatedDomain
            if ($associatedDomains['count'] > 1) {
                // Issue a debug message here
                $relevant_associatedDomain = $associatedDomains[0];
            }
            else {
                $relevant_associatedDomain = $associatedDomains[0];
            }
        }
        else {
            $relevant_associatedDomain = $associatedDomains;
        }

        return 'dc=' . implode(',dc=', explode('.', $relevant_associatedDomain));
    }
}
