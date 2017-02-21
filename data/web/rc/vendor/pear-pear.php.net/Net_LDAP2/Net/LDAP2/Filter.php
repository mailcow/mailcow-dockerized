<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_Filter interface class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2009 Benedikt Hallinger
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* Includes
*/
require_once 'PEAR.php';
require_once 'Net/LDAP2/Util.php';
require_once 'Net/LDAP2/Entry.php';

/**
* Object representation of a part of a LDAP filter.
*
* This Class is not completely compatible to the PERL interface!
*
* The purpose of this class is, that users can easily build LDAP filters
* without having to worry about right escaping etc.
* A Filter is built using several independent filter objects
* which are combined afterwards. This object works in two
* modes, depending how the object is created.
* If the object is created using the {@link create()} method, then this is a leaf-object.
* If the object is created using the {@link combine()} method, then this is a container object.
*
* LDAP filters are defined in RFC-2254 and can be found under
* {@link http://www.ietf.org/rfc/rfc2254.txt}
*
* Here a quick copy&paste example:
* <code>
* $filter0 = Net_LDAP2_Filter::create('stars', 'equals', '***');
* $filter_not0 = Net_LDAP2_Filter::combine('not', $filter0);
*
* $filter1 = Net_LDAP2_Filter::create('gn', 'begins', 'bar');
* $filter2 = Net_LDAP2_Filter::create('gn', 'ends', 'baz');
* $filter_comp = Net_LDAP2_Filter::combine('or',array($filter_not0, $filter1, $filter2));
*
* echo $filter_comp->asString();
* // This will output: (|(!(stars=\0x5c0x2a\0x5c0x2a\0x5c0x2a))(gn=bar*)(gn=*baz))
* // The stars in $filter0 are treaten as real stars unless you disable escaping.
* </code>
*
* @category Net
* @package  Net_LDAP2
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP2/
*/
class Net_LDAP2_Filter extends PEAR
{
    /**
    * Storage for combination of filters
    *
    * This variable holds a array of filter objects
    * that should be combined by this filter object.
    *
    * @access protected
    * @var array
    */
    protected $_subfilters = array();

    /**
    * Match of this filter
    *
    * If this is a leaf filter, then a matching rule is stored,
    * if it is a container, then it is a logical operator
    *
    * @access protected
    * @var string
    */
    protected $_match;

    /**
    * Single filter
    *
    * If we operate in leaf filter mode,
    * then the constructing method stores
    * the filter representation here
    *
    * @acces private
    * @var string
    */
    protected $_filter;

    /**
    * Create a new Net_LDAP2_Filter object and parse $filter.
    *
    * This is for PERL Net::LDAP interface.
    * Construction of Net_LDAP2_Filter objects should happen through either
    * {@link create()} or {@link combine()} which give you more control.
    * However, you may use the perl iterface if you already have generated filters.
    *
    * @param string $filter LDAP filter string
    *
    * @see parse()
    */
    public function __construct($filter = false)
    {
        // The optional parameter must remain here, because otherwise create() crashes
        if (false !== $filter) {
            $filter_o = self::parse($filter);
            if (PEAR::isError($filter_o)) {
                $this->_filter = $filter_o; // assign error, so asString() can report it
            } else {
                $this->_filter = $filter_o->asString();
            }
        }
    }

    /**
    * Constructor of a new part of a LDAP filter.
    *
    * The following matching rules exists:
    *    - equals:         One of the attributes values is exactly $value
    *                      Please note that case sensitiviness is depends on the
    *                      attributes syntax configured in the server.
    *    - begins:         One of the attributes values must begin with $value
    *    - ends:           One of the attributes values must end with $value
    *    - contains:       One of the attributes values must contain $value
    *    - present | any:  The attribute can contain any value but must be existent
    *    - greater:        The attributes value is greater than $value
    *    - less:           The attributes value is less than $value
    *    - greaterOrEqual: The attributes value is greater or equal than $value
    *    - lessOrEqual:    The attributes value is less or equal than $value
    *    - approx:         One of the attributes values is similar to $value
    *
    * Negation ("not") can be done by prepending the above operators with the
    * "not" or "!" keyword, see example below. 
    *
    * If $escape is set to true (default) then $value will be escaped
    * properly. If it is set to false then $value will be treaten as raw filter value string.
    * You should escape yourself using {@link Net_LDAP2_Util::escape_filter_value()}!
    *
    * Examples:
    * <code>
    *   // This will find entries that contain an attribute "sn" that ends with "foobar":
    *   $filter = Net_LDAP2_Filter::create('sn', 'ends', 'foobar');
    *
    *   // This will find entries that contain an attribute "sn" that has any value set:
    *   $filter = Net_LDAP2_Filter::create('sn', 'any');
    *
    *   // This will build a negated equals filter:
    *   $filter = Net_LDAP2_Filter::create('sn', 'not equals', 'foobar');
    * </code>
    *
    * @param string  $attr_name Name of the attribute the filter should apply to
    * @param string  $match     Matching rule (equals, begins, ends, contains, greater, less, greaterOrEqual, lessOrEqual, approx, any)
    * @param string  $value     (optional) if given, then this is used as a filter
    * @param boolean $escape    Should $value be escaped? (default: yes, see {@link Net_LDAP2_Util::escape_filter_value()} for detailed information)
    *
    * @return Net_LDAP2_Filter|Net_LDAP2_Error
    */
    public static function create($attr_name, $match, $value = '', $escape = true)
    {
        $leaf_filter = new Net_LDAP2_Filter();
        if ($escape) {
            $array = Net_LDAP2_Util::escape_filter_value(array($value));
            $value = $array[0];
        }

        $match = strtolower($match);

        // detect negation
        $neg_matches   = array();
        $negate_filter = false;
        if (preg_match('/^(?:not|!)[\s_-](.+)/', $match, $neg_matches)) {
            $negate_filter = true;
            $match         = $neg_matches[1];
        }

        // build basic filter
        switch ($match) {
        case 'equals':
        case '=':
        case '==':
            $leaf_filter->_filter = '(' . $attr_name . '=' . $value . ')';
            break;
        case 'begins':
            $leaf_filter->_filter = '(' . $attr_name . '=' . $value . '*)';
            break;
        case 'ends':
            $leaf_filter->_filter = '(' . $attr_name . '=*' . $value . ')';
            break;
        case 'contains':
            $leaf_filter->_filter = '(' . $attr_name . '=*' . $value . '*)';
            break;
        case 'greater':
        case '>':
            $leaf_filter->_filter = '(' . $attr_name . '>' . $value . ')';
            break;
        case 'less':
        case '<':
            $leaf_filter->_filter = '(' . $attr_name . '<' . $value . ')';
            break;
        case 'greaterorequal':
        case '>=':
            $leaf_filter->_filter = '(' . $attr_name . '>=' . $value . ')';
            break;
        case 'lessorequal':
        case '<=':
            $leaf_filter->_filter = '(' . $attr_name . '<=' . $value . ')';
            break;
        case 'approx':
        case '~=':
            $leaf_filter->_filter = '(' . $attr_name . '~=' . $value . ')';
            break;
        case 'any':
        case 'present': // alias that may improve user code readability
            $leaf_filter->_filter = '(' . $attr_name . '=*)';
            break;
        default:
            return PEAR::raiseError('Net_LDAP2_Filter create error: matching rule "' . $match . '" not known!');
        }
        
        // negate if requested
        if ($negate_filter) {
           $leaf_filter = Net_LDAP2_Filter::combine('!', $leaf_filter);
        }

        return $leaf_filter;
    }

    /**
    * Combine two or more filter objects using a logical operator
    *
    * This static method combines two or more filter objects and returns one single
    * filter object that contains all the others.
    * Call this method statically: $filter = Net_LDAP2_Filter::combine('or', array($filter1, $filter2))
    * If the array contains filter strings instead of filter objects, we will try to parse them.
    *
    * @param string                 $log_op  The locical operator. May be "and", "or", "not" or the subsequent logical equivalents "&", "|", "!"
    * @param array|Net_LDAP2_Filter $filters array with Net_LDAP2_Filter objects
    *
    * @return Net_LDAP2_Filter|Net_LDAP2_Error
    * @static
    */
    public static function &combine($log_op, $filters)
    {
        if (PEAR::isError($filters)) {
            return $filters;
        }

        // substitude named operators to logical operators
        if ($log_op == 'and') $log_op = '&';
        if ($log_op == 'or')  $log_op = '|';
        if ($log_op == 'not') $log_op = '!';

        // tests for sane operation
        if ($log_op == '!') {
            // Not-combination, here we only accept one filter object or filter string
            if ($filters instanceof Net_LDAP2_Filter) {
                $filters = array($filters); // force array
            } elseif (is_string($filters)) {
                $filter_o = self::parse($filters);
                if (PEAR::isError($filter_o)) {
                    $err = PEAR::raiseError('Net_LDAP2_Filter combine error: '.$filter_o->getMessage());
                    return $err;
                } else {
                    $filters = array($filter_o);
                }
            } elseif (is_array($filters)) {
                if (count($filters) != 1) {
                    $err = PEAR::raiseError('Net_LDAP2_Filter combine error: operator is "not" but $filter is an array!');
                    return $err;
                } elseif (!($filters[0] instanceof Net_LDAP2_Filter)) {
                     $err = PEAR::raiseError('Net_LDAP2_Filter combine error: operator is "not" but $filter is not a valid Net_LDAP2_Filter nor a filter string!');
                     return $err;
                }
            } else {
                $err = PEAR::raiseError('Net_LDAP2_Filter combine error: operator is "not" but $filter is not a valid Net_LDAP2_Filter nor a filter string!');
                return $err;
            }
        } elseif ($log_op == '&' || $log_op == '|') {
            if (!is_array($filters) || count($filters) < 2) {
                $err = PEAR::raiseError('Net_LDAP2_Filter combine error: parameter $filters is not an array or contains less than two Net_LDAP2_Filter objects!');
                return $err;
            }
        } else {
            $err = PEAR::raiseError('Net_LDAP2_Filter combine error: logical operator is not known!');
            return $err;
        }

        $combined_filter = new Net_LDAP2_Filter();
        foreach ($filters as $key => $testfilter) {     // check for errors
            if (PEAR::isError($testfilter)) {
                return $testfilter;
            } elseif (is_string($testfilter)) {
                // string found, try to parse into an filter object
                $filter_o = self::parse($testfilter);
                if (PEAR::isError($filter_o)) {
                    return $filter_o;
                } else {
                    $filters[$key] = $filter_o;
                }
            } elseif (!$testfilter instanceof Net_LDAP2_Filter) {
                $err = PEAR::raiseError('Net_LDAP2_Filter combine error: invalid object passed in array $filters!');
                return $err;
            }
        }

        $combined_filter->_subfilters = $filters;
        $combined_filter->_match      = $log_op;
        return $combined_filter;
    }

    /**
    * Parse FILTER into a Net_LDAP2_Filter object
    *
    * This parses an filter string into Net_LDAP2_Filter objects.
    *
    * @param string $FILTER The filter string
    *
    * @access static
    * @return Net_LDAP2_Filter|Net_LDAP2_Error
    * @todo Leaf-mode: Do we need to escape at all? what about *-chars?check for the need of encoding values, tackle problems (see code comments)
    */
    public static function parse($FILTER)
    {
        if (preg_match('/^\((.+?)\)$/', $FILTER, $matches)) {
            // Check for right bracket syntax: count of unescaped opening
            // brackets must match count of unescaped closing brackets.
            // At this stage we may have:
            //   1. one filter component with already removed outer brackets
            //   2. one or more subfilter components
            $c_openbracks  = preg_match_all('/(?<!\\\\)\(/' , $matches[1], $notrelevant);
            $c_closebracks = preg_match_all('/(?<!\\\\)\)/' , $matches[1], $notrelevant);
            if ($c_openbracks != $c_closebracks) {
                return PEAR::raiseError("Filter parsing error: invalid filter syntax - opening brackets do not match close brackets!");
            }

            if (in_array(substr($matches[1], 0, 1), array('!', '|', '&'))) {
                // Subfilter processing: pass subfilters to parse() and combine
                // the objects using the logical operator detected
                // we have now something like "&(...)(...)(...)" but at least one part ("!(...)").
                // Each subfilter could be an arbitary complex subfilter.

                // extract logical operator and filter arguments
                $log_op              = substr($matches[1], 0, 1);
                $remaining_component = substr($matches[1], 1);

                // split $remaining_component into individual subfilters
                // we cannot use split() for this, because we do not know the
                // complexiness of the subfilter. Thus, we look trough the filter
                // string and just recognize ending filters at the first level.
                // We record the index number of the char and use that information
                // later to split the string.
                $sub_index_pos = array();
                $prev_char     = ''; // previous character looked at
                $level         = 0;  // denotes the current bracket level we are,
                                     //   >1 is too deep, 1 is ok, 0 is outside any
                                     //   subcomponent
                for ($curpos = 0; $curpos < strlen($remaining_component); $curpos++) {
                    $cur_char = substr($remaining_component, $curpos, 1);

                    // rise/lower bracket level
                    if ($cur_char == '(' && $prev_char != '\\') {
                        $level++;
                    } elseif  ($cur_char == ')' && $prev_char != '\\') {
                        $level--;
                    }

                    if ($cur_char == '(' && $prev_char == ')' && $level == 1) {
                        array_push($sub_index_pos, $curpos); // mark the position for splitting
                    }
                    $prev_char = $cur_char;
                }

                // now perform the splits. To get also the last part, we
                // need to add the "END" index to the split array
                array_push($sub_index_pos, strlen($remaining_component));
                $subfilters = array();
                $oldpos = 0;
                foreach ($sub_index_pos as $s_pos) {
                    $str_part = substr($remaining_component, $oldpos, $s_pos - $oldpos);
                    array_push($subfilters, $str_part);
                    $oldpos = $s_pos;
                }

                // some error checking...
                if (count($subfilters) == 1) {
                    // only one subfilter found
                } elseif (count($subfilters) > 1) {
                    // several subfilters found
                    if ($log_op == "!") {
                        return PEAR::raiseError("Filter parsing error: invalid filter syntax - NOT operator detected but several arguments given!");
                    }
                } else {
                    // this should not happen unless the user specified a wrong filter
                    return PEAR::raiseError("Filter parsing error: invalid filter syntax - got operator '$log_op' but no argument!");
                }

                // Now parse the subfilters into objects and combine them using the operator
                $subfilters_o = array();
                foreach ($subfilters as $s_s) {
                    $o = self::parse($s_s);
                    if (PEAR::isError($o)) {
                        return $o;
                    } else {
                        array_push($subfilters_o, self::parse($s_s));
                    }
                }

                $filter_o = self::combine($log_op, $subfilters_o);
                return $filter_o;

            } else {
                // This is one leaf filter component, do some syntax checks, then escape and build filter_o
                // $matches[1] should be now something like "foo=bar"

                // detect multiple leaf components
                // [TODO] Maybe this will make problems with filters containing brackets inside the value
                if (stristr($matches[1], ')(')) {
                    return PEAR::raiseError("Filter parsing error: invalid filter syntax - multiple leaf components detected!");
                } else {
                    $filter_parts = Net_LDAP2_Util::split_attribute_string($matches[1], true, true);
                    if (count($filter_parts) != 3) {
                        return PEAR::raiseError("Filter parsing error: invalid filter syntax - unknown matching rule used");
                    } else {
                        $filter_o          = new Net_LDAP2_Filter();
                        // [TODO]: Do we need to escape at all? what about *-chars user provide and that should remain special?
                        //         I think, those prevent escaping! We need to check against PERL Net::LDAP!
                        // $value_arr         = Net_LDAP2_Util::escape_filter_value(array($filter_parts[2]));
                        // $value             = $value_arr[0];
                        $value             = $filter_parts[2];
                        $filter_o->_filter = '('.$filter_parts[0].$filter_parts[1].$value.')';
                        return $filter_o;
                    }
                }
            }
        } else {
               // ERROR: Filter components must be enclosed in round brackets
               return PEAR::raiseError("Filter parsing error: invalid filter syntax - filter components must be enclosed in round brackets");
        }
    }

    /**
    * Get the string representation of this filter
    *
    * This method runs through all filter objects and creates
    * the string representation of the filter. If this
    * filter object is a leaf filter, then it will return
    * the string representation of this filter.
    *
    * @return string|Net_LDAP2_Error
    */
    public function asString()
    {
        if ($this->isLeaf()) {
            $return = $this->_filter;
        } else {
            $return = '';
            foreach ($this->_subfilters as $filter) {
                $return = $return.$filter->asString();
            }
            $return = '(' . $this->_match . $return . ')';
        }
        return $return;
    }

    /**
    * Alias for perl interface as_string()
    *
    * @see asString()
    * @return string|Net_LDAP2_Error
    */
    public function as_string()
    {
        return $this->asString();
    }

    /**
    * Print the text representation of the filter to FH, or the currently selected output handle if FH is not given
    *
    * This method is only for compatibility to the perl interface.
    * However, the original method was called "print" but due to PHP language restrictions,
    * we can't have a print() method.
    *
    * @param resource $FH (optional) A filehandle resource
    *
    * @return true|Net_LDAP2_Error
    */
    public function printMe($FH = false)
    {
        if (!is_resource($FH)) {
            if (PEAR::isError($FH)) {
                return $FH;
            }
            $filter_str = $this->asString();
            if (PEAR::isError($filter_str)) {
                return $filter_str;
            } else {
                print($filter_str);
            }
        } else {
            $filter_str = $this->asString();
            if (PEAR::isError($filter_str)) {
                return $filter_str;
            } else {
                $res = @fwrite($FH, $this->asString());
                if ($res == false) {
                    return PEAR::raiseError("Unable to write filter string to filehandle \$FH!");
                }
            }
        }
        return true;
    }

    /**
    * This can be used to escape a string to provide a valid LDAP-Filter.
    *
    * LDAP will only recognise certain characters as the
    * character istself if they are properly escaped. This is
    * what this method does.
    * The method can be called statically, so you can use it outside
    * for your own purposes (eg for escaping only parts of strings)
    *
    * In fact, this is just a shorthand to {@link Net_LDAP2_Util::escape_filter_value()}.
    * For upward compatibiliy reasons you are strongly encouraged to use the escape
    * methods provided by the Net_LDAP2_Util class.
    *
    * @param string $value Any string who should be escaped
    *
    * @static
    * @return string         The string $string, but escaped
    * @deprecated  Do not use this method anymore, instead use Net_LDAP2_Util::escape_filter_value() directly
    */
    public static function escape($value)
    {
        $return = Net_LDAP2_Util::escape_filter_value(array($value));
        return $return[0];
    }

    /**
    * Is this a container or a leaf filter object?
    *
    * @access protected
    * @return boolean
    */
    protected function isLeaf()
    {
        if (count($this->_subfilters) > 0) {
            return false; // Container!
        } else {
            return true; // Leaf!
        }
    }

    /**
    * Filter entries using this filter or see if a filter matches
    *
    * @todo Currently slow and naive implementation with preg_match, could be optimized (esp. begins, ends filters etc)
    * @todo Currently only "="-based matches (equals, begins, ends, contains, any) implemented; Implement all the stuff!
    * @todo Implement expert code with schema checks in case $entry is connected to a directory
    * @param array|Net_LDAP2_Entry The entry (or array with entries) to check
    * @param array                 If given, the array will be appended with entries who matched the filter. Return value is true if any entry matched.
    * @return int|Net_LDAP2_Error Returns the number of matched entries or error
    */
    function matches(&$entries, &$results=array()) {
        $numOfMatches = 0;

        if (!is_array($entries)) {
            $all_entries = array(&$entries);
        } else {
            $all_entries = &$entries;
        }

        foreach ($all_entries as $entry) {
            // look at the current entry and see if filter matches

            $entry_matched = false;
            // if this is not a single component, do calculate all subfilters,
            // then assert the partial results with the given combination modifier
            if (!$this->isLeaf()) {
        
                // get partial results from subfilters
                $partial_results = array();
                foreach ($this->_subfilters as $filter) {
                    $partial_results[] = $filter->matches($entry);
                }
            
                // evaluate partial results using this filters combination rule
                switch ($this->_match) {
                    case '!':
                        // result is the neagtive result of the assertion
                        $entry_matched = !$partial_results[0];
                    break;

                    case '&':
                        // all partial results have to be boolean-true
                        $entry_matched = !in_array(false, $partial_results);
                    break;
                
                    case '|':
                        // at least one partial result has to be true
                        $entry_matched = in_array(true, $partial_results);
                    break;
                }
            
            } else {
                // Leaf filter: assert given entry
                // [TODO]: Could be optimized to avoid preg_match especially with "ends", "begins" etc
            
                // Translate the LDAP-match to some preg_match expression and evaluate it
                list($attribute, $match, $assertValue) = $this->getComponents();
                switch ($match) {
                    case '=':
                        $regexp = '/^'.str_replace('*', '.*', $assertValue).'$/i'; // not case sensitive unless specified by schema
                        $entry_matched = $entry->pregMatch($regexp, $attribute);
                    break;
                
                    // -------------------------------------
                    // [TODO]: implement <, >, <=, >= and =~
                    // -------------------------------------
                
                    default:
                        $err = PEAR::raiseError("Net_LDAP2_Filter match error: unsupported match rule '$match'!");
                        return $err;
                }
            
            }

            // process filter matching result
            if ($entry_matched) {
                $numOfMatches++;
                $results[] = $entry;
            }

        }

        return $numOfMatches;
    }


    /**
    * Retrieve this leaf-filters attribute, match and value component.
    *
    * For leaf filters, this returns array(attr, match, value).
    * Match is be the logical operator, not the text representation,
    * eg "=" instead of "equals". Note that some operators are really
    * a combination of operator+value with wildcard, like
    * "begins": That will return "=" with the value "value*"!
    *
    * For non-leaf filters this will drop an error.
    *
    * @todo $this->_match is not always available and thus not usable here; it would be great if it would set in the factory methods and constructor.
    * @return array|Net_LDAP2_Error
    */
    function getComponents() {
        if ($this->isLeaf()) {
            $raw_filter = preg_replace('/^\(|\)$/', '', $this->_filter);
            $parts = Net_LDAP2_Util::split_attribute_string($raw_filter, true, true);
            if (count($parts) != 3) {
                return PEAR::raiseError("Net_LDAP2_Filter getComponents() error: invalid filter syntax - unknown matching rule used");
            } else {
                return $parts;
            }
        } else {
            return PEAR::raiseError('Net_LDAP2_Filter getComponents() call is invalid for non-leaf filters!');
        }
    }


}
?>
