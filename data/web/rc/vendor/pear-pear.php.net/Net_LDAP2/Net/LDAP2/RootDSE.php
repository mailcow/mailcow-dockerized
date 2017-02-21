<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_RootDSE interface class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Jan Wagner <wagner@netsols.de>
* @copyright 2009 Jan Wagner
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* Includes
*/
require_once 'PEAR.php';

/**
* Getting the rootDSE entry of a LDAP server
*
* @category Net
* @package  Net_LDAP2
* @author   Jan Wagner <wagner@netsols.de>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP22/
*/
class Net_LDAP2_RootDSE extends PEAR
{
    /**
    * @access protected
    * @var object Net_LDAP2_Entry
    **/
    protected $_entry;

    /**
    * Class constructor
    *
    * @param Net_LDAP2_Entry &$entry Net_LDAP2_Entry object of the RootDSE
    */
    public function __construct(&$entry)
    {
        $this->_entry = $entry;
    }

    /**
    * Fetches a RootDSE object from an LDAP connection
    *
    * @param Net_LDAP2 $ldap  Directory from which the RootDSE should be fetched
    * @param array     $attrs Array of attributes to search for
    *
    * @access static
    * @return Net_LDAP2_RootDSE|Net_LDAP2_Error
    */
    public static function fetch($ldap, $attrs = null)
    {
        if (!$ldap instanceof Net_LDAP2) {
            return PEAR::raiseError("Unable to fetch Schema: Parameter \$ldap must be a Net_LDAP2 object!");
        }

        if (is_array($attrs) && count($attrs) > 0 ) {
            $attributes = $attrs;
        } else {
            $attributes = array('vendorName',
                                'vendorVersion',
                                'namingContexts',
                                'altServer',
                                'supportedExtension',
                                'supportedControl',
                                'supportedSASLMechanisms',
                                'supportedLDAPVersion',
                                'subschemaSubentry' );
        }
        $result = $ldap->search('', '(objectClass=*)', array('attributes' => $attributes, 'scope' => 'base'));
        if (self::isError($result)) {
            return $result;
        }
        $entry = $result->shiftEntry();
        if (false === $entry) {
            return PEAR::raiseError('Could not fetch RootDSE entry');
        }
        $ret = new Net_LDAP2_RootDSE($entry);
        return $ret;
    }

    /**
    * Gets the requested attribute value
    *
    * Same usuage as {@link Net_LDAP2_Entry::getValue()}
    *
    * @param string $attr    Attribute name
    * @param array  $options Array of options
    *
    * @access public
    * @return mixed Net_LDAP2_Error object or attribute values
    * @see Net_LDAP2_Entry::get_value()
    */
    public function getValue($attr = '', $options = '')
    {
        return $this->_entry->get_value($attr, $options);
    }

    /**
    * Alias function of getValue() for perl-ldap interface
    *
    * @see getValue()
    * @return mixed
    */
    public function get_value()
    {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'getValue' ), $args);
    }

    /**
    * Determines if the extension is supported
    *
    * @param array $oids Array of oids to check
    *
    * @access public
    * @return boolean
    */
    public function supportedExtension($oids)
    {
        return $this->checkAttr($oids, 'supportedExtension');
    }

    /**
    * Alias function of supportedExtension() for perl-ldap interface
    *
    * @see supportedExtension()
    * @return boolean
    */
    public function supported_extension()
    {
        $args = func_get_args();
        return call_user_func_array(array( &$this, 'supportedExtension'), $args);
    }

    /**
    * Determines if the version is supported
    *
    * @param array $versions Versions to check
    *
    * @access public
    * @return boolean
    */
    public function supportedVersion($versions)
    {
        return $this->checkAttr($versions, 'supportedLDAPVersion');
    }

    /**
    * Alias function of supportedVersion() for perl-ldap interface
    *
    * @see supportedVersion()
    * @return boolean
    */
    public function supported_version()
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'supportedVersion'), $args);
    }

    /**
    * Determines if the control is supported
    *
    * @param array $oids Control oids to check
    *
    * @access public
    * @return boolean
    */
    public function supportedControl($oids)
    {
        return $this->checkAttr($oids, 'supportedControl');
    }

    /**
    * Alias function of supportedControl() for perl-ldap interface
    *
    * @see supportedControl()
    * @return boolean
    */
    public function supported_control()
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'supportedControl' ), $args);
    }

    /**
    * Determines if the sasl mechanism is supported
    *
    * @param array $mechlist SASL mechanisms to check
    *
    * @access public
    * @return boolean
    */
    public function supportedSASLMechanism($mechlist)
    {
        return $this->checkAttr($mechlist, 'supportedSASLMechanisms');
    }

    /**
    * Alias function of supportedSASLMechanism() for perl-ldap interface
    *
    * @see supportedSASLMechanism()
    * @return boolean
    */
    public function supported_sasl_mechanism()
    {
        $args = func_get_args();
        return call_user_func_array(array(&$this, 'supportedSASLMechanism'), $args);
    }

    /**
    * Checks for existance of value in attribute
    *
    * @param array  $values values to check
    * @param string $attr   attribute name
    *
    * @access protected
    * @return boolean
    */
    protected function checkAttr($values, $attr)
    {
        if (!is_array($values)) $values = array($values);

        foreach ($values as $value) {
            if (!@in_array($value, $this->get_value($attr, 'all'))) {
                return false;
            }
        }
        return true;
    }
}

?>
