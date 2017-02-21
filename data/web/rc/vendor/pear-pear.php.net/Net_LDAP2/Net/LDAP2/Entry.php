<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_Entry interface class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Jan Wagner <wagner@netsols.de>
* @author    Tarjej Huse <tarjei@bergfald.no>
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2009 Tarjej Huse, Jan Wagner, Benedikt Hallinger
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* Includes
*/
require_once 'PEAR.php';
require_once 'Net/LDAP2/Util.php';

/**
* Object representation of a directory entry
*
* This class represents a directory entry. You can add, delete, replace
* attributes and their values, rename the entry, delete the entry.
*
* @category Net
* @package  Net_LDAP2
* @author   Jan Wagner <wagner@netsols.de>
* @author   Tarjej Huse <tarjei@bergfald.no>
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP2/
*/
class Net_LDAP2_Entry extends PEAR
{
    /**
    * Entry ressource identifier
    *
    * @access protected
    * @var ressource
    */
    protected $_entry = null;

    /**
    * LDAP ressource identifier
    *
    * @access protected
    * @var ressource
    */
    protected $_link = null;

    /**
    * Net_LDAP2 object
    *
    * This object will be used for updating and schema checking
    *
    * @access protected
    * @var object Net_LDAP2
    */
    protected $_ldap = null;

    /**
    * Distinguished name of the entry
    *
    * @access protected
    * @var string
    */
    protected $_dn = null;

    /**
    * Attributes
    *
    * @access protected
    * @var array
    */
    protected $_attributes = array();

    /**
    * Original attributes before any modification
    *
    * @access protected
    * @var array
    */
    protected $_original = array();


    /**
    * Map of attribute names
    *
    * @access protected
    * @var array
    */
    protected $_map = array();


    /**
    * Is this a new entry?
    *
    * @access protected
    * @var boolean
    */
    protected $_new = true;

    /**
    * New distinguished name
    *
    * @access protected
    * @var string
    */
    protected $_newdn = null;

    /**
    * Shall the entry be deleted?
    *
    * @access protected
    * @var boolean
    */
    protected $_delete = false;

    /**
    * Map with changes to the entry
    *
    * @access protected
    * @var array
    */
    protected $_changes = array("add"     => array(),
                                "delete"  => array(),
                                "replace" => array()
                               );
    /**
    * Internal Constructor
    *
    * Constructor of the entry. Sets up the distinguished name and the entries
    * attributes.
    * You should not call this method manually! Use {@link Net_LDAP2_Entry::createFresh()}
    * or {@link Net_LDAP2_Entry::createConnected()} instead!
    *
    * @param Net_LDAP2|ressource|array $ldap Net_LDAP2 object, ldap-link ressource or array of attributes
    * @param string|ressource          $entry Either a DN or a LDAP-Entry ressource
    *
    * @access protected
    * @return none
    */
    public function __construct($ldap, $entry = null)
    {
        parent::__construct('Net_LDAP2_Error');

        // set up entry resource or DN
        if (is_resource($entry)) {
            $this->_entry = $entry;
        } else {
            $this->_dn = $entry;
        }

        // set up LDAP link
        if ($ldap instanceof Net_LDAP2) {
            $this->_ldap = $ldap;
            $this->_link = $ldap->getLink();
        } elseif (is_resource($ldap)) {
            $this->_link = $ldap;
        } elseif (is_array($ldap)) {
            // Special case: here $ldap is an array of attributes,
            // this means, we have no link. This is a "virtual" entry.
            // We just set up the attributes so one can work with the object
            // as expected, but an update() fails unless setLDAP() is called.
            $this->setAttributes($ldap);
        }

        // if this is an entry existing in the directory,
        // then set up as old and fetch attrs
        if (is_resource($this->_entry) && is_resource($this->_link)) {
            $this->_new = false;
            $this->_dn  = @ldap_get_dn($this->_link, $this->_entry);
            $this->setAttributes();  // fetch attributes from server
        }
    }

    /**
    * Creates a fresh entry that may be added to the directory later on
    *
    * Use this method, if you want to initialize a fresh entry.
    *
    * The method should be called statically: $entry = Net_LDAP2_Entry::createFresh();
    * You should put a 'objectClass' attribute into the $attrs so the directory server
    * knows which object you want to create. However, you may omit this in case you
    * don't want to add this entry to a directory server.
    *
    * The attributes parameter is as following:
    * <code>
    * $attrs = array( 'attribute1' => array('value1', 'value2'),
    *                 'attribute2' => 'single value'
    *          );
    * </code>
    *
    * @param string $dn    DN of the Entry
    * @param array  $attrs Attributes of the entry
    *
    * @static
    * @return Net_LDAP2_Entry|Net_LDAP2_Error
    */
    public static function createFresh($dn, $attrs = array())
    {
        if (!is_array($attrs)) {
            return PEAR::raiseError("Unable to create fresh entry: Parameter \$attrs needs to be an array!");
        }

        $entry = new Net_LDAP2_Entry($attrs, $dn);
        return $entry;
    }

    /**
    * Creates a Net_LDAP2_Entry object out of an ldap entry resource
    *
    * Use this method, if you want to initialize an entry object that is
    * already present in some directory and that you have read manually.
    *
    * Please note, that if you want to create an entry object that represents
    * some already existing entry, you should use {@link createExisting()}.
    *
    * The method should be called statically: $entry = Net_LDAP2_Entry::createConnected();
    *
    * @param Net_LDAP2 $ldap  Net_LDA2 object
    * @param resource  $entry PHP LDAP entry resource
    *
    * @static
    * @return Net_LDAP2_Entry|Net_LDAP2_Error
    */
    public static function createConnected($ldap, $entry)
    {
        if (!$ldap instanceof Net_LDAP2) {
            return PEAR::raiseError("Unable to create connected entry: Parameter \$ldap needs to be a Net_LDAP2 object!");
        }
        if (!is_resource($entry)) {
            return PEAR::raiseError("Unable to create connected entry: Parameter \$entry needs to be a ldap entry resource!");
        }

        $entry = new Net_LDAP2_Entry($ldap, $entry);
        return $entry;
    }

    /**
    * Creates an Net_LDAP2_Entry object that is considered already existing
    *
    * Use this method, if you want to modify an already existing entry
    * without fetching it first.
    * In most cases however, it is better to fetch the entry via Net_LDAP2->getEntry()!
    *
    * Please note that you should take care if you construct entries manually with this
    * because you may get weird synchronisation problems.
    * The attributes and values as well as the entry itself are considered existent
    * which may produce errors if you try to modify an entry which doesn't really exist
    * or if you try to overwrite some attribute with an value already present.
    *
    * This method is equal to calling createFresh() and after that markAsNew(FALSE).
    *
    * The method should be called statically: $entry = Net_LDAP2_Entry::createExisting();
    *
    * The attributes parameter is as following:
    * <code>
    * $attrs = array( 'attribute1' => array('value1', 'value2'),
    *                 'attribute2' => 'single value'
    *          );
    * </code>
    *
    * @param string $dn    DN of the Entry
    * @param array  $attrs Attributes of the entry
    *
    * @static
    * @return Net_LDAP2_Entry|Net_LDAP2_Error
    */
    public static function createExisting($dn, $attrs = array())
    {
        if (!is_array($attrs)) {
            return PEAR::raiseError("Unable to create entry object: Parameter \$attrs needs to be an array!");
        }

        $entry = Net_LDAP2_Entry::createFresh($dn, $attrs);
        if ($entry instanceof Net_LDAP2_Error) {
            return $entry;
        } else {
            $entry->markAsNew(false);
            return $entry;
        }
    }

    /**
    * Get or set the distinguished name of the entry
    *
    * If called without an argument the current (or the new DN if set) DN gets returned.
    * If you provide an DN, this entry is moved to the new location specified if a DN existed.
    * If the DN was not set, the DN gets initialized. Call {@link update()} to actually create
    * the new Entry in the directory.
    * To fetch the current active DN after setting a new DN but before an update(), you can use
    * {@link currentDN()} to retrieve the DN that is currently active.
    *
    * Please note that special characters (eg german umlauts) should be encoded using utf8_encode().
    * You may use {@link Net_LDAP2_Util::canonical_dn()} for properly encoding of the DN.
    *
    * @param string $dn New distinguished name
    *
    * @access public
    * @return string|true Distinguished name (or true if a new DN was provided)
    */
    public function dn($dn = null)
    {
        if (false == is_null($dn)) {
            if (is_null($this->_dn) ) {
                $this->_dn = $dn;
            } else {
                $this->_newdn = $dn;
            }
            return true;
        }
        return (isset($this->_newdn) ? $this->_newdn : $this->currentDN());
    }

    /**
    * Renames or moves the entry
    *
    * This is just a convinience alias to {@link dn()}
    * to make your code more meaningful.
    *
    * @param string $newdn The new DN
    *
    * @return true
    */
    public function move($newdn)
    {
        return $this->dn($newdn);
    }

    /**
    * Sets the internal attributes array
    *
    * This fetches the values for the attributes from the server.
    * The attribute Syntax will be checked so binary attributes will be returned
    * as binary values.
    *
    * Attributes may be passed directly via the $attributes parameter to setup this
    * entry manually. This overrides attribute fetching from the server.
    *
    * @param array $attributes Attributes to set for this entry
    *
    * @access protected
    * @return void
    */
    protected function setAttributes($attributes = null)
    {
        /*
        * fetch attributes from the server
        */
        if (is_null($attributes) && is_resource($this->_entry) && is_resource($this->_link)) {
            // fetch schema
            if ($this->_ldap instanceof Net_LDAP2) {
                $schema = $this->_ldap->schema();
            }
            // fetch attributes
            $attributes = array();
            do {
                if (empty($attr)) {
                    $ber  = null;
                    $attr = @ldap_first_attribute($this->_link, $this->_entry, $ber);
                } else {
                    $attr = @ldap_next_attribute($this->_link, $this->_entry, $ber);
                }
                if ($attr) {
                    $func = 'ldap_get_values'; // standard function to fetch value

                    // Try to get binary values as binary data
                    if ($schema instanceof Net_LDAP2_Schema) {
                        if ($schema->isBinary($attr)) {
                             $func = 'ldap_get_values_len';
                        }
                    }
                    // fetch attribute value (needs error checking?)
                    $attributes[$attr] = $func($this->_link, $this->_entry, $attr);
                }
            } while ($attr);
        }

        /*
        * set attribute data directly, if passed
        */
        if (is_array($attributes) && count($attributes) > 0) {
            if (isset($attributes["count"]) && is_numeric($attributes["count"])) {
                unset($attributes["count"]);
            }
            foreach ($attributes as $k => $v) {
                // attribute names should not be numeric
                if (is_numeric($k)) {
                    continue;
                }
                // map generic attribute name to real one
                $this->_map[strtolower($k)] = $k;
                // attribute values should be in an array
                if (false == is_array($v)) {
                    $v = array($v);
                }
                // remove the value count (comes from ldap server)
                if (isset($v["count"])) {
                    unset($v["count"]);
                }
                $this->_attributes[$k] = $v;
            }
        }

        // save a copy for later use
        $this->_original = $this->_attributes;
    }

    /**
    * Get the values of all attributes in a hash
    *
    * The returned hash has the form
    * <code>array('attributename' => 'single value',
    *       'attributename' => array('value1', value2', value3'))</code>
    * Only attributes present at the entry will be returned.
    *
    * @access public
    * @return array Hash of all attributes with their values
    */
    public function getValues()
    {
        $attrs = array();
        foreach ($this->_attributes as $attr => $value) {
            $attrs[$attr] = $this->getValue($attr);
        }
        return $attrs;
    }

    /**
    * Get the value of a specific attribute
    *
    * The first parameter is the name of the attribute
    * The second parameter influences the way the value is returned:
    * 'single':  only the first value is returned as string
    * 'all':     all values are returned in an array
    * 'default': in all other cases an attribute value with a single value is
    *            returned as string, if it has multiple values it is returned
    *            as an array
    *
    * If the attribute is not set at this entry (no value or not defined in
    * schema), "false" is returned when $option is 'single', an empty string if
    * 'default', and an empty array when 'all'.
    *
    * You may use Net_LDAP2_Schema->checkAttribute() to see if the attribute
    * is defined for the objectClasses of this entry.
    *
    * @param string  $attr         Attribute name
    * @param string  $option       Option
    *
    * @access public
    * @return string|array
    */
    public function getValue($attr, $option = null)
    {
        $attr = $this->getAttrName($attr);

        // return depending on set $options
        if (!array_key_exists($attr, $this->_attributes)) {
            // attribute not set
            switch ($option) {
                case 'single':
                    $value = false;
                break;
                case 'all':
                    $value = array();
                break;
                default:
                    $value = '';
            }

        } else {
            // attribute present
            switch ($option) {
                case 'single':
                    $value = $this->_attributes[$attr][0];
                break;
                case 'all':
                    $value = $this->_attributes[$attr];
                break;
                default:
                    $value = $this->_attributes[$attr];
                    if (count($value) == 1) {
                        $value = array_shift($value);
                    }
            }
            
        }

        return $value;
    }

    /**
    * Alias function of getValue for perl-ldap interface
    *
    * @see getValue()
    * @return string|array|PEAR_Error
    */
    public function get_value()
    {
        $args = func_get_args();
        return call_user_func_array(array( $this, 'getValue' ), $args);
    }

    /**
    * Returns an array of attributes names
    *
    * @access public
    * @return array Array of attribute names
    */
    public function attributes()
    {
        return array_keys($this->_attributes);
    }

    /**
    * Returns whether an attribute exists or not
    *
    * @param string $attr Attribute name
    *
    * @access public
    * @return boolean
    */
    public function exists($attr)
    {
        $attr = $this->getAttrName($attr);
        return array_key_exists($attr, $this->_attributes);
    }

    /**
    * Adds a new attribute or a new value to an existing attribute
    *
    * The paramter has to be an array of the form:
    * array('attributename' => 'single value',
    *       'attributename' => array('value1', 'value2))
    * When the attribute already exists the values will be added, else the
    * attribute will be created. These changes are local to the entry and do
    * not affect the entry on the server until update() is called.
    *
    * Note, that you can add values of attributes that you haven't selected, but if
    * you do so, {@link getValue()} and {@link getValues()} will only return the
    * values you added, _NOT_ all values present on the server. To avoid this, just refetch
    * the entry after calling {@link update()} or select the attribute.
    *
    * @param array $attr Attributes to add
    *
    * @access public
    * @return true|Net_LDAP2_Error
    */
    public function add($attr = array())
    {
        if (false == is_array($attr)) {
            return PEAR::raiseError("Parameter must be an array");
        }
        if ($this->isNew()) {
            $this->setAttributes($attr);
        }
        foreach ($attr as $k => $v) {
            $k = $this->getAttrName($k);
            if (false == is_array($v)) {
                // Do not add empty values
                if ($v == null) {
                    continue;
                } else {
                    $v = array($v);
                }
            }
            // add new values to existing attribute or add new attribute
            if ($this->exists($k)) {
                $this->_attributes[$k] = array_unique(array_merge($this->_attributes[$k], $v));
            } else {
                $this->_map[strtolower($k)] = $k;
                $this->_attributes[$k]      = $v;
            }
            // save changes for update()
            if (!isset($this->_changes["add"][$k])) {
                $this->_changes["add"][$k] = array();
            }
            $this->_changes["add"][$k] = array_unique(array_merge($this->_changes["add"][$k], $v));
        }

        $return = true;
        return $return;
    }

    /**
    * Deletes an whole attribute or a value or the whole entry
    *
    * The parameter can be one of the following:
    *
    * "attributename" - The attribute as a whole will be deleted
    * array("attributename1", "attributename2) - All given attributes will be
    *                                            deleted
    * array("attributename" => "value") - The value will be deleted
    * array("attributename" => array("value1", "value2") - The given values
    *                                                      will be deleted
    * If $attr is null or omitted , then the whole Entry will be deleted!
    *
    * These changes are local to the entry and do
    * not affect the entry on the server until {@link update()} is called.
    *
    * Please note that you must select the attribute (at $ldap->search() for example)
    * to be able to delete values of it, Otherwise {@link update()} will silently fail
    * and remove nothing.
    *
    * @param string|array $attr Attributes to delete (NULL or missing to delete whole entry)
    *
    * @access public
    * @return true
    */
    public function delete($attr = null)
    {
        if (is_null($attr)) {
            $this->_delete = true;
            return true;
        }
        if (is_string($attr)) {
            $attr = array($attr);
        }
        // Make the assumption that attribute names cannot be numeric,
        // therefore this has to be a simple list of attribute names to delete
        if (is_numeric(key($attr))) {
            foreach ($attr as $name) {
                if (is_array($name)) {
                    // someone mixed modes (list mode but specific values given!)
                    $del_attr_name = array_search($name, $attr);
                    $this->delete(array($del_attr_name => $name));
                } else {
                    // mark for update() if this attr was not marked before
                    $name = $this->getAttrName($name);
                    if ($this->exists($name)) {
                        $this->_changes["delete"][$name] = null;
                        unset($this->_attributes[$name]);
                    }
                }
            }
        } else {
            // Here we have a hash with "attributename" => "value to delete"
            foreach ($attr as $name => $values) {
                if (is_int($name)) {
                    // someone mixed modes and gave us just an attribute name
                    $this->delete($values);
                } else {
                    // mark for update() if this attr was not marked before;
                    // this time it must consider the selected values also
                    $name = $this->getAttrName($name);
                    if ($this->exists($name)) {
                        if (false == is_array($values)) {
                            $values = array($values);
                        }
                        // save values to be deleted
                        if (empty($this->_changes["delete"][$name])) {
                            $this->_changes["delete"][$name] = array();
                        }
                        $this->_changes["delete"][$name] =
                            array_unique(array_merge($this->_changes["delete"][$name], $values));
                        foreach ($values as $value) {
                            // find the key for the value that should be deleted
                            $key = array_search($value, $this->_attributes[$name]);
                            if (false !== $key) {
                                // delete the value
                                unset($this->_attributes[$name][$key]);
                            }
                        }
                    }
                }
            }
        }
        $return = true;
        return $return;
    }

    /**
    * Replaces attributes or its values
    *
    * The parameter has to an array of the following form:
    * array("attributename" => "single value",
    *       "attribute2name" => array("value1", "value2"),
    *       "deleteme1" => null,
    *       "deleteme2" => "")
    * If the attribute does not yet exist it will be added instead (see also $force).
    * If the attribue value is null, the attribute will de deleted.
    *
    * These changes are local to the entry and do
    * not affect the entry on the server until {@link update()} is called.
    *
    * In some cases you are not allowed to read the attributes value (for
    * example the ActiveDirectory attribute unicodePwd) but are allowed to
    * replace the value. In this case replace() would assume that the attribute
    * is not in the directory yet and tries to add it which will result in an
    * LDAP_TYPE_OR_VALUE_EXISTS error.
    * To force replace mode instead of add, you can set $force to true.
    *
    * @param array $attr  Attributes to replace
    * @param bool  $force Force replacing mode in case we can't read the attr value but are allowed to replace it
    *
    * @access public
    * @return true|Net_LDAP2_Error
    */
    public function replace($attr = array(), $force = false)
    {
        if (false == is_array($attr)) {
            return PEAR::raiseError("Parameter must be an array");
        }
        foreach ($attr as $k => $v) {
            $k = $this->getAttrName($k);
            if (false == is_array($v)) {
                // delete attributes with empty values; treat ints as string
                if (is_int($v)) {
                    $v = "$v";
                }
                if ($v == null) {
                    $this->delete($k);
                    continue;
                } else {
                    $v = array($v);
                }
            }
            // existing attributes will get replaced
            if ($this->exists($k) || $force) {
                $this->_changes["replace"][$k] = $v;
                $this->_attributes[$k]         = $v;
            } else {
                // new ones just get added
                $this->add(array($k => $v));
            }
        }
        $return = true;
        return $return;
    }

    /**
    * Update the entry on the directory server
    *
    * This will evaluate all changes made so far and send them
    * to the directory server.
    * Please note, that if you make changes to objectclasses wich
    * have mandatory attributes set, update() will currently fail.
    * Remove the entry from the server and readd it as new in such cases.
    * This also will deal with problems with setting structural object classes.
    *
    * @param Net_LDAP2 $ldap If passed, a call to setLDAP() is issued prior update, thus switching the LDAP-server. This is for perl-ldap interface compliance
    *
    * @access public
    * @return true|Net_LDAP2_Error
    * @todo Entry rename with a DN containing special characters needs testing!
    */
    public function update($ldap = null)
    {
        if ($ldap) {
            $msg = $this->setLDAP($ldap);
            if (Net_LDAP2::isError($msg)) {
                return PEAR::raiseError('You passed an invalid $ldap variable to update()');
            }
        }

        // ensure we have a valid LDAP object
        $ldap = $this->getLDAP();
        if (!$ldap instanceof Net_LDAP2) {
            return PEAR::raiseError("The entries LDAP object is not valid");
        }

        // Get and check link
        $link = $ldap->getLink();
        if (!is_resource($link)) {
            return PEAR::raiseError("Could not update entry: internal LDAP link is invalid");
        }

        /*
        * Delete the entry
        */
        if (true === $this->_delete) {
            return $ldap->delete($this);
        }

        /*
        * New entry
        */
        if (true === $this->_new) {
            $msg = $ldap->add($this);
            if (Net_LDAP2::isError($msg)) {
                return $msg;
            }
            $this->_new                = false;
            $this->_changes['add']     = array();
            $this->_changes['delete']  = array();
            $this->_changes['replace'] = array();
            $this->_original           = $this->_attributes;

            // In case the "new" entry was moved after creation, we must
            // adjust the internal DNs as the entry was already created
            // with the most current DN.
            if (false == is_null($this->_newdn)) {
                $this->_dn    = $this->_newdn;
                $this->_newdn = null;
            }

            $return = true;
            return $return;
        }

        /*
        * Rename/move entry
        */
        if (false == is_null($this->_newdn)) {
            if ($ldap->getLDAPVersion() !== 3) {
                return PEAR::raiseError("Renaming/Moving an entry is only supported in LDAPv3");
            }
            // make dn relative to parent (needed for ldap rename)
            $parent = Net_LDAP2_Util::ldap_explode_dn($this->_newdn, array('casefolding' => 'none', 'reverse' => false, 'onlyvalues' => false));
            if (Net_LDAP2::isError($parent)) {
                return $parent;
            }
            $child = array_shift($parent);
            // maybe the dn consist of a multivalued RDN, we must build the dn in this case
            // because the $child-RDN is an array!
            if (is_array($child)) {
                $child = Net_LDAP2_Util::canonical_dn($child);
            }
            $parent = Net_LDAP2_Util::canonical_dn($parent);

            // rename/move
            if (false == @ldap_rename($link, $this->_dn, $child, $parent, false)) {

                return PEAR::raiseError("Entry not renamed: " .
                                        @ldap_error($link), @ldap_errno($link));
            }
            // reflect changes to local copy
            $this->_dn    = $this->_newdn;
            $this->_newdn = null;
        }

        /*
        * Retrieve a entry that has all attributes we need so that the list of changes to build is created accurately
        */
        $fullEntry = $ldap->getEntry( $this->dn() );
        if ( Net_LDAP2::isError($fullEntry) ) {
            return PEAR::raiseError("Could not retrieve a full set of attributes to reconcile changes with");
        }
        $modifications = array();

        // ADD
        foreach ($this->_changes["add"] as $attr => $value) {
            // if attribute exists, we need to combine old and new values
            if ($fullEntry->exists($attr)) {
                $currentValue = $fullEntry->getValue($attr, "all");
                $value = array_merge( $currentValue, $value );
            } 
            
            $modifications[$attr] = $value;
        }

        // DELETE
        foreach ($this->_changes["delete"] as $attr => $value) {
            // In LDAPv3 you need to specify the old values for deleting
            if (is_null($value) && $ldap->getLDAPVersion() === 3) {
                $value = $fullEntry->getValue($attr);
            }
            if (!is_array($value)) {
                $value = array($value);
            }
            
            // Find out what is missing from $value and exclude it
            $currentValue = isset($modifications[$attr]) ? $modifications[$attr] : $fullEntry->getValue($attr, "all");
            $modifications[$attr] = array_values( array_diff( $currentValue, $value ) );
        }

        // REPLACE
        foreach ($this->_changes["replace"] as $attr => $value) {
            $modifications[$attr] = $value;
        }

        // COMMIT
        if (false === @ldap_modify($link, $this->dn(), $modifications)) {
            return PEAR::raiseError("Could not modify the entry: " . @ldap_error($link), @ldap_errno($link));
        }

        // all went well, so _original (server) becomes _attributes (local copy), reset _changes too...
        $this->_changes['add']     = array();
        $this->_changes['delete']  = array();
        $this->_changes['replace'] = array();
        $this->_original           = $this->_attributes;

        $return = true;
        return $return;
    }

    /**
    * Returns the right attribute name
    *
    * @param string $attr Name of attribute
    *
    * @access protected
    * @return string The right name of the attribute
    */
    protected function getAttrName($attr)
    {
        $name = strtolower($attr);
        if (array_key_exists($name, $this->_map)) {
            $attr = $this->_map[$name];
        }
        return $attr;
    }

    /**
    * Returns a reference to the LDAP-Object of this entry
    *
    * @access public
    * @return Net_LDAP2|Net_LDAP2_Error   Reference to the Net_LDAP2 Object (the connection) or Net_LDAP2_Error
    */
    public function getLDAP()
    {
        if (!$this->_ldap instanceof Net_LDAP2) {
            $err = new PEAR_Error('LDAP is not a valid Net_LDAP2 object');
            return $err;
        } else {
            return $this->_ldap;
        }
    }

    /**
    * Sets a reference to the LDAP-Object of this entry
    *
    * After setting a Net_LDAP2 object, calling update() will use that object for
    * updating directory contents. Use this to dynamicly switch directorys.
    *
    * @param Net_LDAP2 $ldap Net_LDAP2 object that this entry should be connected to
    *
    * @access public
    * @return true|Net_LDAP2_Error
    */
    public function setLDAP($ldap)
    {
        if (!$ldap instanceof Net_LDAP2) {
            return PEAR::raiseError("LDAP is not a valid Net_LDAP2 object");
        } else {
            $this->_ldap = $ldap;
            return true;
        }
    }

    /**
    * Marks the entry as new/existing.
    *
    * If an Entry is marked as new, it will be added to the directory
    * when calling {@link update()}.
    * If the entry is marked as old ($mark = false), then the entry is
    * assumed to be present in the directory server wich results in
    * modification when calling {@link update()}.
    *
    * @param boolean $mark Value to set, defaults to "true"
    *
    * @return void
    */
    public function markAsNew($mark = true)
    {
        $this->_new = ($mark)? true : false;
    }

    /**
    * Applies a regular expression onto a single- or multivalued attribute (like preg_match())
    *
    * This method behaves like PHPs preg_match() but with some exceptions.
    * If you want to retrieve match information, then you MUST pass the
    * $matches parameter via reference! otherwise you will get no matches.
    * Since it is possible to have multi valued attributes the $matches
    * array will have a additionally numerical dimension (one for each value):
    * <code>
    * $matches = array(
    *         0 => array (usual preg_match() returnarray),
    *         1 => array (usual preg_match() returnarray)
    *     )
    * </code>
    * Please note, that $matches will be initialized to an empty array inside.
    *
    * Usage example:
    * <code>
    * $result = $entry->preg_match('/089(\d+)/', 'telephoneNumber', $matches);
    * if ( $result === true ){
    *     echo "First match: ".$matches[0][1];   // Match of value 1, content of first bracket
    * } else {
    *     if ( Net_LDAP2::isError($result) ) {
    *         echo "Error: ".$result->getMessage();
    *     } else {
    *         echo "No match found.";
    *     }
    * }
    * </code>
    *
    * Please note that it is important to test for an Net_LDAP2_Error, because objects are
    * evaluating to true by default, thus if an error occured, and you only check using "==" then
    * you get misleading results. Use the "identical" (===) operator to test for matches to
    * avoid this as shown above.
    *
    * @param string $regex     The regular expression
    * @param string $attr_name The attribute to search in
    * @param array  $matches   (optional, PASS BY REFERENCE!) Array to store matches in
    *
    * @return boolean|Net_LDAP2_Error  TRUE, if we had a match in one of the values, otherwise false. Net_LDAP2_Error in case something went wrong
    */
    public function pregMatch($regex, $attr_name, $matches = array())
    {
        $matches = array();

        // fetch attribute values
        $attr = $this->getValue($attr_name, 'all');

        // perform preg_match() on all values
        $match = false;
        foreach ($attr as $thisvalue) {
            $matches_int = array();
            if (preg_match($regex, $thisvalue, $matches_int)) {
                $match = true;
                array_push($matches, $matches_int); // store matches in reference
            }
        }
        return $match;
    }

    /**
    * Alias of {@link pregMatch()} for compatibility to Net_LDAP 1
    *
    * @see pregMatch()
    * @return boolean|Net_LDAP2_Error
    */
    public function preg_match()
    {
        $args = func_get_args();
        return call_user_func_array(array( $this, 'pregMatch' ), $args);
    }

    /**
    * Tells if the entry is consiedered as new (not present in the server)
    *
    * Please note, that this doesn't tell you if the entry is present on the server.
    * Use {@link Net_LDAP2::dnExists()} to see if an entry is already there.
    *
    * @return boolean
    */
    public function isNew()
    {
        return $this->_new;
    }


    /**
    * Is this entry going to be deleted once update() is called?
    *
    * @return boolean
    */
    public function willBeDeleted()
    {
        return $this->_delete;
    }

    /**
    * Is this entry going to be moved once update() is called?
    *
    * @return boolean
    */
    public function willBeMoved()
    {
        return ($this->dn() !== $this->currentDN());
    }

    /**
    * Returns always the original DN
    *
    * If an entry will be moved but {@link update()} was not called,
    * {@link dn()} will return the new DN. This method however, returns
    * always the current active DN.
    *
    * @return string
    */
    public function currentDN()
    {
        return $this->_dn;
    }

    /**
    * Returns the attribute changes to be carried out once update() is called
    *
    * @return array
    */
    public function getChanges()
    {
        return $this->_changes;
    }
}
?>
