<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_Search interface class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Tarjej Huse <tarjei@bergfald.no>
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2009 Tarjej Huse, Benedikt Hallinger
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* Includes
*/
require_once 'PEAR.php';

/**
* Result set of an LDAP search
*
* @category Net
* @package  Net_LDAP2
* @author   Tarjej Huse <tarjei@bergfald.no>
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP22/
*/
class Net_LDAP2_Search extends PEAR implements Iterator
{
    /**
    * Search result identifier
    *
    * @access protected
    * @var resource
    */
    protected $_search;

    /**
    * LDAP resource link
    *
    * @access protected
    * @var resource
    */
    protected $_link;

    /**
    * Net_LDAP2 object
    *
    * A reference of the Net_LDAP2 object for passing to Net_LDAP2_Entry
    *
    * @access protected
    * @var object Net_LDAP2
    */
    protected $_ldap;

    /**
    * Result entry identifier
    *
    * @access protected
    * @var resource
    */
    protected $_entry = null;

    /**
    * The errorcode the search got
    *
    * Some errorcodes might be of interest, but might not be best handled as errors.
    * examples: 4 - LDAP_SIZELIMIT_EXCEEDED - indicates a huge search.
    *               Incomplete results are returned. If you just want to check if there's anything in the search.
    *               than this is a point to handle.
    *           32 - no such object - search here returns a count of 0.
    *
    * @access protected
    * @var int
    */
    protected $_errorCode = 0; // if not set - sucess!

    /**
    * Cache for all entries already fetched from iterator interface
    *
    * @access protected
    * @var array
    */
    protected $_iteratorCache = array();

    /**
    * What attributes we searched for
    *
    * The $attributes array contains the names of the searched attributes and gets
    * passed from $Net_LDAP2->search() so the Net_LDAP2_Search object can tell
    * what attributes was searched for ({@link searchedAttrs())
    *
    * This variable gets set from the constructor and returned
    * from {@link searchedAttrs()}
    *
    * @access protected
    * @var array
    */
    protected $_searchedAttrs = array();

    /**
    * Cache variable for storing entries fetched internally
    *
    * This currently is not used by all functions and need consolidation.
    *
    * @access protected
    * @var array
    */
    protected $_entry_cache = false;

    /**
    * Cache variable for count()
    *
    * @see count()
    * @access protected
    * @var int
    */
    protected $_count_cache = null;

    /**
    * Constructor
    *
    * @param resource           $search    Search result identifier
    * @param Net_LDAP2|resource $ldap      Net_LDAP2 object or just a LDAP-Link resource
    * @param array              $attributes (optional) Array with searched attribute names. (see {@link $_searchedAttrs})
    *
    * @access public
    */
    public function __construct($search, $ldap, $attributes = array())
    {
        parent::__construct('Net_LDAP2_Error');

        $this->setSearch($search);

        if ($ldap instanceof Net_LDAP2) {
            $this->_ldap = $ldap;
            $this->setLink($this->_ldap->getLink());
        } else {
            $this->setLink($ldap);
        }

        $this->_errorCode = @ldap_errno($this->_link);

        if (is_array($attributes) && !empty($attributes)) {
            $this->_searchedAttrs = $attributes;
        }
    }

    /**
    * Returns an array of entry objects.
    *
    * @return array Array of entry objects.
    */
    public function entries()
    {
        $entries = array();

        if (false === $this->_entry_cache) {
            // cache is empty: fetch from LDAP
            while ($entry = $this->shiftEntry()) {
                $entries[] = $entry;
            }
            $this->_entry_cache = $entries; // store result in cache
        }

        return $this->_entry_cache;
    }

    /**
    * Get the next entry in the searchresult from LDAP server.
    *
    * This will return a valid Net_LDAP2_Entry object or false, so
    * you can use this method to easily iterate over the entries inside
    * a while loop.
    *
    * @return Net_LDAP2_Entry|false  Reference to Net_LDAP2_Entry object or false
    */
    public function shiftEntry()
    {
        if (is_null($this->_entry)) {
            if(!$this->_entry = @ldap_first_entry($this->_link, $this->_search)) {
                $false = false;
                return $false;
            }
            $entry = Net_LDAP2_Entry::createConnected($this->_ldap, $this->_entry);
            if ($entry instanceof PEAR_Error) $entry = false;
        } else {
            if (!$this->_entry = @ldap_next_entry($this->_link, $this->_entry)) {
                $false = false;
                return $false;
            }
            $entry = Net_LDAP2_Entry::createConnected($this->_ldap, $this->_entry);
            if ($entry instanceof PEAR_Error) $entry = false;
        }
        return $entry;
    }

    /**
    * Alias function of shiftEntry() for perl-ldap interface
    *
    * @see shiftEntry()
    * @return Net_LDAP2_Entry|false
    */
    public function shift_entry()
    {
        $args = func_get_args();
        return call_user_func_array(array( $this, 'shiftEntry' ), $args);
    }

    /**
    * Retrieve the next entry in the searchresult, but starting from last entry
    *
    * This is the opposite to {@link shiftEntry()} and is also very useful
    * to be used inside a while loop.
    *
    * @return Net_LDAP2_Entry|false
    */
    public function popEntry()
    {
        if (false === $this->_entry_cache) {
            // fetch entries into cache if not done so far
            $this->_entry_cache = $this->entries();
        }

        $return = array_pop($this->_entry_cache);
        return (null === $return)? false : $return;
    }

    /**
    * Alias function of popEntry() for perl-ldap interface
    *
    * @see popEntry()
    * @return Net_LDAP2_Entry|false
    */
    public function pop_entry()
    {
        $args = func_get_args();
        return call_user_func_array(array( $this, 'popEntry' ), $args);
    }

    /**
    * Return entries sorted as array
    *
    * This returns a array with sorted entries and the values.
    * Sorting is done with PHPs {@link array_multisort()}.
    * This method relies on {@link as_struct()} to fetch the raw data of the entries.
    *
    * Please note that attribute names are case sensitive!
    *
    * Usage example:
    * <code>
    *   // to sort entries first by location, then by surename, but descending:
    *   $entries = $search->sorted_as_struct(array('locality','sn'), SORT_DESC);
    * </code>
    *
    * @param array $attrs Array of attribute names to sort; order from left to right.
    * @param int   $order Ordering direction, either constant SORT_ASC or SORT_DESC
    *
    * @return array|Net_LDAP2_Error   Array with sorted entries or error
    * @todo what about server side sorting as specified in http://www.ietf.org/rfc/rfc2891.txt?
    */
    public function sorted_as_struct($attrs = array('cn'), $order = SORT_ASC)
    {
        /*
        * Old Code, suitable and fast for single valued sorting
        * This code should be used if we know that single valued sorting is desired,
        * but we need some method to get that knowledge...
        */
        /*
        $attrs = array_reverse($attrs);
        foreach ($attrs as $attribute) {
            if (!ldap_sort($this->_link, $this->_search, $attribute)){
                $this->raiseError("Sorting failed for Attribute " . $attribute);
            }
        }

        $results = ldap_get_entries($this->_link, $this->_search);

        unset($results['count']); //for tidier output
        if ($order) {
            return array_reverse($results);
        } else {
            return $results;
        }*/

        /*
        * New code: complete "client side" sorting
        */
        // first some parameterchecks
        if (!is_array($attrs)) {
            return PEAR::raiseError("Sorting failed: Parameterlist must be an array!");
        }
        if ($order != SORT_ASC && $order != SORT_DESC) {
            return PEAR::raiseError("Sorting failed: sorting direction not understood! (neither constant SORT_ASC nor SORT_DESC)");
        }

        // fetch the entries data
        $entries = $this->as_struct();

        // now sort each entries attribute values
        // this is neccessary because later we can only sort by one value,
        // so we need the highest or lowest attribute now, depending on the
        // selected ordering for that specific attribute
        foreach ($entries as $dn => $entry) {
            foreach ($entry as $attr_name => $attr_values) {
                sort($entries[$dn][$attr_name]);
                if ($order == SORT_DESC) {
                    array_reverse($entries[$dn][$attr_name]);
                }
            }
        }

        // reformat entrys array for later use with array_multisort()
        $to_sort = array(); // <- will be a numeric array similar to ldap_get_entries
        foreach ($entries as $dn => $entry_attr) {
            $row       = array();
            $row['dn'] = $dn;
            foreach ($entry_attr as $attr_name => $attr_values) {
                $row[$attr_name] = $attr_values;
            }
            $to_sort[] = $row;
        }

        // Build columns for array_multisort()
        // each requested attribute is one row
        $columns = array();
        foreach ($attrs as $attr_name) {
            foreach ($to_sort as $key => $row) {
                $columns[$attr_name][$key] =& $to_sort[$key][$attr_name][0];
            }
        }

        // sort the colums with array_multisort, if there is something
        // to sort and if we have requested sort columns
        if (!empty($to_sort) && !empty($columns)) {
            $sort_params = '';
            foreach ($attrs as $attr_name) {
                $sort_params .= '$columns[\''.$attr_name.'\'], '.$order.', ';
            }
            eval("array_multisort($sort_params \$to_sort);"); // perform sorting
        }

        return $to_sort;
    }

    /**
    * Return entries sorted as objects
    *
    * This returns a array with sorted Net_LDAP2_Entry objects.
    * The sorting is actually done with {@link sorted_as_struct()}.
    *
    * Please note that attribute names are case sensitive!
    * Also note, that it is (depending on server capabilitys) possible to let
    * the server sort your results. This happens through search controls
    * and is described in detail at {@link http://www.ietf.org/rfc/rfc2891.txt}
    *
    * Usage example:
    * <code>
    *   // to sort entries first by location, then by surename, but descending:
    *   $entries = $search->sorted(array('locality','sn'), SORT_DESC);
    * </code>
    *
    * @param array $attrs Array of sort attributes to sort; order from left to right.
    * @param int   $order Ordering direction, either constant SORT_ASC or SORT_DESC
    *
    * @return array|Net_LDAP2_Error   Array with sorted Net_LDAP2_Entries or error
    * @todo Entry object construction could be faster. Maybe we could use one of the factorys instead of fetching the entry again
    */
    public function sorted($attrs = array('cn'), $order = SORT_ASC)
    {
        $return = array();
        $sorted = $this->sorted_as_struct($attrs, $order);
        if (PEAR::isError($sorted)) {
            return $sorted;
        }
        foreach ($sorted as $key => $row) {
            $entry = $this->_ldap->getEntry($row['dn'], $this->searchedAttrs());
            if (!PEAR::isError($entry)) {
                array_push($return, $entry);
            } else {
                return $entry;
            }
        }
        return $return;
    }

    /**
    * Return entries as array
    *
    * This method returns the entries and the selected attributes values as
    * array.
    * The first array level contains all found entries where the keys are the
    * DNs of the entries. The second level arrays contian the entries attributes
    * such that the keys is the lowercased name of the attribute and the values
    * are stored in another indexed array. Note that the attribute values are stored
    * in an array even if there is no or just one value.
    *
    * The array has the following structure:
    * <code>
    * $return = array(
    *           'cn=foo,dc=example,dc=com' => array(
    *                                                'sn'       => array('foo'),
    *                                                'multival' => array('val1', 'val2', 'valN')
    *                                             )
    *           'cn=bar,dc=example,dc=com' => array(
    *                                                'sn'       => array('bar'),
    *                                                'multival' => array('val1', 'valN')
    *                                             )
    *           )
    * </code>
    *
    * @return array      associative result array as described above
    */
    public function as_struct()
    {
        $return  = array();
        $entries = $this->entries();
        foreach ($entries as $entry) {
            $attrs            = array();
            $entry_attributes = $entry->attributes();
            foreach ($entry_attributes as $attr_name) {
                $attr_values = $entry->getValue($attr_name, 'all');
                if (!is_array($attr_values)) {
                    $attr_values = array($attr_values);
                }
                $attrs[$attr_name] = $attr_values;
            }
            $return[$entry->dn()] = $attrs;
        }
        return $return;
    }

    /**
    * Set the search objects resource link
    *
    * @param resource $search Search result identifier
    *
    * @access public
    * @return void
    */
    public function setSearch($search)
    {
        $this->_search = $search;
    }

    /**
    * Set the ldap ressource link
    *
    * @param resource $link Link identifier
    *
    * @access public
    * @return void
    */
    public function setLink($link)
    {
        $this->_link = $link;
    }

    /**
    * Returns the number of entries in the searchresult
    *
    * @return int Number of entries in search.
    */
    public function count()
    {
        // this catches the situation where OL returned errno 32 = no such object!
        if (!$this->_search) {
            return 0;
        }
        // ldap_count_entries is slow (see pear bug #18752) with large results,
        // so we cache the result internally.
        if ($this->_count_cache === null) {
            $this->_count_cache = @ldap_count_entries($this->_link, $this->_search);
        }

        return $this->_count_cache;
    }

    /**
    * Get the errorcode the object got in its search.
    *
    * @return int The ldap error number.
    */
    public function getErrorCode()
    {
        return $this->_errorCode;
    }

    /**
    * Destructor
    *
    * @access protected
    */
    public function _Net_LDAP2_Search()
    {
        @ldap_free_result($this->_search);
    }

    /**
    * Closes search result
    *
    * @return void
    */
    public function done()
    {
        $this->_Net_LDAP2_Search();
    }

    /**
    * Return the attribute names this search selected
    *
    * @return array
    * @see $_searchedAttrs
    * @access protected
    */
    protected function searchedAttrs()
    {
        return $this->_searchedAttrs;
    }

    /**
    * Tells if this search exceeds a sizelimit
    *
    * @return boolean
    */
    public function sizeLimitExceeded()
    {
        return ($this->getErrorCode() == 4);
    }


    /*
    * SPL Iterator interface methods.
    * This interface allows to use Net_LDAP2_Search
    * objects directly inside a foreach loop!
    */
    /**
    * SPL Iterator interface: Return the current element.
    *
    * The SPL Iterator interface allows you to fetch entries inside
    * a foreach() loop: <code>foreach ($search as $dn => $entry) { ...</code>
    *
    * Of course, you may call {@link current()}, {@link key()}, {@link next()},
    * {@link rewind()} and {@link valid()} yourself.
    *
    * If the search throwed an error, it returns false.
    * False is also returned, if the end is reached
    * In case no call to next() was made, we will issue one,
    * thus returning the first entry.
    *
    * @return Net_LDAP2_Entry|false
    */
    public function current()
    {
        if (count($this->_iteratorCache) == 0) {
            $this->next();
            reset($this->_iteratorCache);
        }
        $entry = current($this->_iteratorCache);
        return ($entry instanceof Net_LDAP2_Entry)? $entry : false;
    }

    /**
    * SPL Iterator interface: Return the identifying key (DN) of the current entry.
    *
    * @see current()
    * @return string|false DN of the current entry; false in case no entry is returned by current()
    */
    public function key()
    {
        $entry = $this->current();
        return ($entry instanceof Net_LDAP2_Entry)? $entry->dn() :false;
    }

    /**
    * SPL Iterator interface: Move forward to next entry.
    *
    * After a call to {@link next()}, {@link current()} will return
    * the next entry in the result set.
    *
    * @see current()
    * @return void
    */
    public function next()
    {
        // fetch next entry.
        // if we have no entrys anymore, we add false (which is
        // returned by shiftEntry()) so current() will complain.
        if (count($this->_iteratorCache) - 1 <= $this->count()) {
            $this->_iteratorCache[] = $this->shiftEntry();
        }

        // move on array pointer to current element.
        // even if we have added all entries, this will
        // ensure proper operation in case we rewind()
        next($this->_iteratorCache);
    }

    /**
    * SPL Iterator interface:  Check if there is a current element after calls to {@link rewind()} or {@link next()}.
    *
    * Used to check if we've iterated to the end of the collection.
    *
    * @see current()
    * @return boolean FALSE if there's nothing more to iterate over
    */
    public function valid()
    {
        return ($this->current() instanceof Net_LDAP2_Entry);
    }

    /**
    * SPL Iterator interface: Rewind the Iterator to the first element.
    *
    * After rewinding, {@link current()} will return the first entry in the result set.
    *
    * @see current()
    * @return void
    */
    public function rewind()
    {
        reset($this->_iteratorCache);
    }
}

?>
