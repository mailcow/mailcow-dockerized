<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_Util interface class.
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

/**
* Utility Class for Net_LDAP2
*
* This class servers some functionality to the other classes of Net_LDAP2 but most of
* the methods can be used separately as well.
*
* @category Net
* @package  Net_LDAP2
* @author   Benedikt Hallinger <beni@php.net>
* @license  http://www.gnu.org/copyleft/lesser.html LGPL
* @link     http://pear.php.net/package/Net_LDAP22/
*/
class Net_LDAP2_Util extends PEAR
{
    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
         // We do nothing here, since all methods can be called statically.
         // In Net_LDAP <= 0.7, we needed a instance of Util, because
         // it was possible to do utf8 encoding and decoding, but this
         // has been moved to the LDAP class. The constructor remains only
         // here to document the downward compatibility of creating an instance.
    }

    /**
    * Explodes the given DN into its elements
    *
    * {@link http://www.ietf.org/rfc/rfc2253.txt RFC 2253} says, a Distinguished Name is a sequence
    * of Relative Distinguished Names (RDNs), which themselves
    * are sets of Attributes. For each RDN a array is constructed where the RDN part is stored.
    *
    * For example, the DN 'OU=Sales+CN=J. Smith,DC=example,DC=net' is exploded to:
    * <kbd>array( [0] => array([0] => 'OU=Sales', [1] => 'CN=J. Smith'), [2] => 'DC=example', [3] => 'DC=net' )</kbd>
    *
    * [NOT IMPLEMENTED] DNs might also contain values, which are the bytes of the BER encoding of
    * the X.500 AttributeValue rather than some LDAP string syntax. These values are hex-encoded
    * and prefixed with a #. To distinguish such BER values, ldap_explode_dn uses references to
    * the actual values, e.g. '1.3.6.1.4.1.1466.0=#04024869,DC=example,DC=com' is exploded to:
    * [ { '1.3.6.1.4.1.1466.0' => "\004\002Hi" }, { 'DC' => 'example' }, { 'DC' => 'com' } ];
    * See {@link http://www.vijaymukhi.com/vmis/berldap.htm} for more information on BER.
    *
    *  It also performs the following operations on the given DN:
    *   - Unescape "\" followed by ",", "+", """, "\", "<", ">", ";", "#", "=", " ", or a hexpair
    *     and strings beginning with "#".
    *   - Removes the leading 'OID.' characters if the type is an OID instead of a name.
    *   - If an RDN contains multiple parts, the parts are re-ordered so that the attribute type names are in alphabetical order.
    *
    * OPTIONS is a list of name/value pairs, valid options are:
    *   casefold    Controls case folding of attribute types names.
    *               Attribute values are not affected by this option.
    *               The default is to uppercase. Valid values are:
    *               lower        Lowercase attribute types names.
    *               upper        Uppercase attribute type names. This is the default.
    *               none         Do not change attribute type names.
    *   reverse     If TRUE, the RDN sequence is reversed.
    *   onlyvalues  If TRUE, then only attributes values are returned ('foo' instead of 'cn=foo')
    *

    * @param string $dn      The DN that should be exploded
    * @param array  $options Options to use
    *
    * @static
    * @return array   Parts of the exploded DN
    * @todo implement BER
    */
    public static function ldap_explode_dn($dn, $options = array('casefold' => 'upper'))
    {
        if (!isset($options['onlyvalues'])) $options['onlyvalues']  = false;
        if (!isset($options['reverse']))    $options['reverse']     = false;
        if (!isset($options['casefold']))   $options['casefold']    = 'upper';

        // Escaping of DN and stripping of "OID."
        $dn = self::canonical_dn($dn, array('casefold' => $options['casefold']));

        // splitting the DN
        $dn_array = preg_split('/(?<=[^\\\\]),/', $dn);

        // clear wrong splitting (possibly we have split too much)
        // /!\ Not clear, if this is neccessary here
        //$dn_array = self::correct_dn_splitting($dn_array, ',');

        // construct subarrays for multivalued RDNs and unescape DN value
        // also convert to output format and apply casefolding
        foreach ($dn_array as $key => $value) {
            $value_u = self::unescape_dn_value($value);
            $rdns    = self::split_rdn_multival($value_u[0]);
            if (count($rdns) > 1) {
                // MV RDN!
                foreach ($rdns as $subrdn_k => $subrdn_v) {
                    // Casefolding
                    if ($options['casefold'] == 'upper') {
                        $subrdn_v = preg_replace_callback(
                            "/^\w+=/",
                            function ($matches) {
                                return strtoupper($matches[0]);
                            },
                            $subrdn_v
                        );
                    } else if ($options['casefold'] == 'lower') {
                        $subrdn_v = preg_replace_callback(
                            "/^\w+=/",
                            function ($matches) {
                                return strtolower($matches[0]);
                            },
                            $subrdn_v
                        );
                    }

                    if ($options['onlyvalues']) {
                        preg_match('/(.+?)(?<!\\\\)=(.+)/', $subrdn_v, $matches);
                        $rdn_ocl         = $matches[1];
                        $rdn_val         = $matches[2];
                        $unescaped       = self::unescape_dn_value($rdn_val);
                        $rdns[$subrdn_k] = $unescaped[0];
                    } else {
                        $unescaped = self::unescape_dn_value($subrdn_v);
                        $rdns[$subrdn_k] = $unescaped[0];
                    }
                }

                $dn_array[$key] = $rdns;
            } else {
                // normal RDN

                // Casefolding
                if ($options['casefold'] == 'upper') {
                    $value = preg_replace_callback(
                        "/^\w+=/",
                        function ($matches) {
                            return strtoupper($matches[0]);
                        },
                        $value
                    );
                } else if ($options['casefold'] == 'lower') {
                    $value = preg_replace_callback(
                        "/^\w+=/",
                        function ($matches) {
                            return strtolower($matches[0]);
                        },
                        $value
                    );
                }

                if ($options['onlyvalues']) {
                    preg_match('/(.+?)(?<!\\\\)=(.+)/', $value, $matches);
                    $dn_ocl         = $matches[1];
                    $dn_val         = $matches[2];
                    $unescaped      = self::unescape_dn_value($dn_val);
                    $dn_array[$key] = $unescaped[0];
                } else {
                    $unescaped = self::unescape_dn_value($value);
                    $dn_array[$key] = $unescaped[0];
                }
            }
        }

        if ($options['reverse']) {
            return array_reverse($dn_array);
        } else {
            return $dn_array;
        }
    }

    /**
    * Escapes a DN value according to RFC 2253
    *
    * Escapes the given VALUES according to RFC 2253 so that they can be safely used in LDAP DNs.
    * The characters ",", "+", """, "\", "<", ">", ";", "#", "=" with a special meaning in RFC 2252
    * are preceeded by ba backslash. Control characters with an ASCII code < 32 are represented as \hexpair.
    * Finally all leading and trailing spaces are converted to sequences of \20.
    *
    * @param array $values An array containing the DN values that should be escaped
    *
    * @static
    * @return array The array $values, but escaped
    */
    public static function escape_dn_value($values = array())
    {
        // Parameter validation
        if (!is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $val) {
            // Escaping of filter meta characters
            $val = str_replace('\\', '\\\\', $val);
            $val = str_replace(',',    '\,', $val);
            $val = str_replace('+',    '\+', $val);
            $val = str_replace('"',    '\"', $val);
            $val = str_replace('<',    '\<', $val);
            $val = str_replace('>',    '\>', $val);
            $val = str_replace(';',    '\;', $val);
            $val = str_replace('#',    '\#', $val);
            $val = str_replace('=',    '\=', $val);

            // ASCII < 32 escaping
            $val = self::asc2hex32($val);

            // Convert all leading and trailing spaces to sequences of \20.
            if (preg_match('/^(\s*)(.+?)(\s*)$/', $val, $matches)) {
                $val = $matches[2];
                for ($i = 0; $i < strlen($matches[1]); $i++) {
                    $val = '\20'.$val;
                }
                for ($i = 0; $i < strlen($matches[3]); $i++) {
                    $val = $val.'\20';
                }
            }

            if (null === $val) $val = '\0';  // apply escaped "null" if string is empty

            $values[$key] = $val;
        }

        return $values;
    }

    /**
    * Undoes the conversion done by escape_dn_value().
    *
    * Any escape sequence starting with a baskslash - hexpair or special character -
    * will be transformed back to the corresponding character.
    *
    * @param array $values Array of DN Values
    *
    * @return array Same as $values, but unescaped
    * @static
    */
    public static function unescape_dn_value($values = array())
    {
        // Parameter validation
        if (!is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $val) {
            // strip slashes from special chars
            $val = str_replace('\\\\', '\\', $val);
            $val = str_replace('\,',    ',', $val);
            $val = str_replace('\+',    '+', $val);
            $val = str_replace('\"',    '"', $val);
            $val = str_replace('\<',    '<', $val);
            $val = str_replace('\>',    '>', $val);
            $val = str_replace('\;',    ';', $val);
            $val = str_replace('\#',    '#', $val);
            $val = str_replace('\=',    '=', $val);

            // Translate hex code into ascii
            $values[$key] = self::hex2asc($val);
        }

        return $values;
    }

    /**
    * Returns the given DN in a canonical form
    *
    * Returns false if DN is not a valid Distinguished Name.
    * DN can either be a string or an array
    * as returned by ldap_explode_dn, which is useful when constructing a DN.
    * The DN array may have be indexed (each array value is a OCL=VALUE pair)
    * or associative (array key is OCL and value is VALUE).
    *
    * It performs the following operations on the given DN:
    *     - Removes the leading 'OID.' characters if the type is an OID instead of a name.
    *     - Escapes all RFC 2253 special characters (",", "+", """, "\", "<", ">", ";", "#", "="), slashes ("/"), and any other character where the ASCII code is < 32 as \hexpair.
    *     - Converts all leading and trailing spaces in values to be \20.
    *     - If an RDN contains multiple parts, the parts are re-ordered so that the attribute type names are in alphabetical order.
    *
    * OPTIONS is a list of name/value pairs, valid options are:
    *     casefold    Controls case folding of attribute type names.
    *                 Attribute values are not affected by this option. The default is to uppercase.
    *                 Valid values are:
    *                 lower        Lowercase attribute type names.
    *                 upper        Uppercase attribute type names. This is the default.
    *                 none         Do not change attribute type names.
    *     [NOT IMPLEMENTED] mbcescape   If TRUE, characters that are encoded as a multi-octet UTF-8 sequence will be escaped as \(hexpair){2,*}.
    *     reverse     If TRUE, the RDN sequence is reversed.
    *     separator   Separator to use between RDNs. Defaults to comma (',').
    *
    * Note: The empty string "" is a valid DN, so be sure not to do a "$can_dn == false" test,
    *       because an empty string evaluates to false. Use the "===" operator instead.
    *
    * @param array|string $dn      The DN
    * @param array        $options Options to use
    *
    * @static
    * @return false|string The canonical DN or FALSE
    * @todo implement option mbcescape
    */
    public static function canonical_dn($dn, $options = array('casefold' => 'upper', 'separator' => ','))
    {
        if ($dn === '') return $dn;  // empty DN is valid!

        // options check
        if (!isset($options['reverse'])) {
            $options['reverse'] = false;
        } else {
            $options['reverse'] = true;
        }
        if (!isset($options['casefold']))  $options['casefold'] = 'upper';
        if (!isset($options['separator'])) $options['separator'] = ',';


        if (!is_array($dn)) {
            // It is not clear to me if the perl implementation splits by the user defined
            // separator or if it just uses this separator to construct the new DN
            $dn = preg_split('/(?<=[^\\\\])'.$options['separator'].'/', $dn);

            // clear wrong splitting (possibly we have split too much)
            $dn = self::correct_dn_splitting($dn, $options['separator']);
        } else {
            // Is array, check, if the array is indexed or associative
            $assoc = false;
            foreach ($dn as $dn_key => $dn_part) {
                if (!is_int($dn_key)) {
                    $assoc = true;
                }
            }
            // convert to indexed, if associative array detected
            if ($assoc) {
                $newdn = array();
                foreach ($dn as $dn_key => $dn_part) {
                    if (is_array($dn_part)) {
                        ksort($dn_part, SORT_STRING); // we assume here, that the rdn parts are also associative
                        $newdn[] = $dn_part;  // copy array as-is, so we can resolve it later
                    } else {
                        $newdn[] = $dn_key.'='.$dn_part;
                    }
                }
                $dn =& $newdn;
            }
        }

        // Escaping and casefolding
        foreach ($dn as $pos => $dnval) {
            if (is_array($dnval)) {
                // subarray detected, this means very surely, that we had
                // a multivalued dn part, which must be resolved
                $dnval_new = '';
                foreach ($dnval as $subkey => $subval) {
                    // build RDN part
                    if (!is_int($subkey)) {
                        $subval = $subkey.'='.$subval;
                    }
                    $subval_processed = self::canonical_dn($subval);
                    if (false === $subval_processed) return false;
                    $dnval_new .= $subval_processed.'+';
                }
                $dn[$pos] = substr($dnval_new, 0, -1); // store RDN part, strip last plus
            } else {
                // try to split multivalued RDNS into array
                $rdns = self::split_rdn_multival($dnval);
                if (count($rdns) > 1) {
                    // Multivalued RDN was detected!
                    // The RDN value is expected to be correctly split by split_rdn_multival().
                    // It's time to sort the RDN and build the DN!
                    $rdn_string = '';
                    sort($rdns, SORT_STRING); // Sort RDN keys alphabetically
                    foreach ($rdns as $rdn) {
                        $subval_processed = self::canonical_dn($rdn);
                        if (false === $subval_processed) return false;
                        $rdn_string .= $subval_processed.'+';
                    }

                    $dn[$pos] = substr($rdn_string, 0, -1); // store RDN part, strip last plus

                } else {
                    // no multivalued RDN!
                    // split at first unescaped "="
                    $dn_comp = preg_split('/(?<=[^\\\\])=/', $rdns[0], 2);
                    $ocl     = ltrim($dn_comp[0]);  // trim left whitespaces 'cause of "cn=foo, l=bar" syntax (whitespace after comma)
                    $val     = $dn_comp[1];

                    // strip 'OID.', otherwise apply casefolding and escaping
                    if (substr(strtolower($ocl), 0, 4) == 'oid.') {
                        $ocl = substr($ocl, 4);
                    } else {
                        if ($options['casefold'] == 'upper') $ocl = strtoupper($ocl);
                        if ($options['casefold'] == 'lower') $ocl = strtolower($ocl);
                        $ocl = self::escape_dn_value(array($ocl));
                        $ocl = $ocl[0];
                    }

                    // escaping of dn-value
                    $val = self::escape_dn_value(array($val));
                    $val = str_replace('/', '\/', $val[0]);

                    $dn[$pos] = $ocl.'='.$val;
                }
            }
        }

        if ($options['reverse']) $dn = array_reverse($dn);
        return implode($options['separator'], $dn);
    }

    /**
    * Escapes the given VALUES according to RFC 2254 so that they can be safely used in LDAP filters.
    *
    * Any control characters with an ACII code < 32 as well as the characters with special meaning in
    * LDAP filters "*", "(", ")", and "\" (the backslash) are converted into the representation of a
    * backslash followed by two hex digits representing the hexadecimal value of the character.
    *
    * @param array $values Array of values to escape
    *
    * @static
    * @return array Array $values, but escaped
    */
    public static function escape_filter_value($values = array())
    {
        // Parameter validation
        if (!is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $val) {
            // Escaping of filter meta characters
            $val = str_replace('\\', '\5c', $val);
            $val = str_replace('*',  '\2a', $val);
            $val = str_replace('(',  '\28', $val);
            $val = str_replace(')',  '\29', $val);

            // ASCII < 32 escaping
            $val = self::asc2hex32($val);

            if (null === $val) $val = '\0';  // apply escaped "null" if string is empty

            $values[$key] = $val;
        }

        return $values;
    }

    /**
    * Undoes the conversion done by {@link escape_filter_value()}.
    *
    * Converts any sequences of a backslash followed by two hex digits into the corresponding character.
    *
    * @param array $values Array of values to escape
    *
    * @static
    * @return array Array $values, but unescaped
    */
    public static function unescape_filter_value($values = array())
    {
        // Parameter validation
        if (!is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $value) {
            // Translate hex code into ascii
            $values[$key] = self::hex2asc($value);
        }

        return $values;
    }

    /**
    * Converts all ASCII chars < 32 to "\HEX"
    *
    * @param string $string String to convert
    *
    * @static
    * @return string
    */
    public static function asc2hex32($string)
    {
        for ($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            if (ord($char) < 32) {
                $hex = dechex(ord($char));
                if (strlen($hex) == 1) $hex = '0'.$hex;
                $string = str_replace($char, '\\'.$hex, $string);
            }
        }
        return $string;
    }

    /**
    * Converts all Hex expressions ("\HEX") to their original ASCII characters
    *
    * @param string $string String to convert
    *
    * @static
    * @author beni@php.net, heavily based on work from DavidSmith@byu.net
    * @return string
    */
    public static function hex2asc($string)
    {
        $string = preg_replace_callback(
            "/\\\[0-9A-Fa-f]{2}/",
            function ($matches) {
                return chr(hexdec($matches[0]));
            },
            $string
        );
        return $string;
    }

    /**
    * Split an multivalued RDN value into an Array
    *
    * A RDN can contain multiple values, spearated by a plus sign.
    * This function returns each separate ocl=value pair of the RDN part.
    *
    * If no multivalued RDN is detected, an array containing only
    * the original rdn part is returned.
    *
    * For example, the multivalued RDN 'OU=Sales+CN=J. Smith' is exploded to:
    * <kbd>array([0] => 'OU=Sales', [1] => 'CN=J. Smith')</kbd>
    *
    * The method trys to be smart if it encounters unescaped "+" characters, but may fail,
    * so ensure escaped "+"es in attr names and attr values.
    *
    * [BUG] If you have a multivalued RDN with unescaped plus characters
    *       and there is a unescaped plus sign at the end of an value followed by an
    *       attribute name containing an unescaped plus, then you will get wrong splitting:
    *         $rdn = 'OU=Sales+C+N=J. Smith';
    *       returns:
    *         array('OU=Sales+C', 'N=J. Smith');
    *       The "C+" is treaten as value of the first pair instead as attr name of the second pair.
    *       To prevent this, escape correctly.
    *
    * @param string $rdn Part of an (multivalued) escaped RDN (eg. ou=foo OR ou=foo+cn=bar)
    *
    * @static
    * @return array Array with the components of the multivalued RDN or Error
    */
    public static function split_rdn_multival($rdn)
    {
        $rdns = preg_split('/(?<!\\\\)\+/', $rdn);
        $rdns = self::correct_dn_splitting($rdns, '+');
        return array_values($rdns);
    }

    /**
    * Splits an attribute=value syntax into an array
    *
    * If escaped delimeters are used, they are returned escaped as well.
    * The split will occur at the first unescaped delimeter character.
    * In case an invalid delimeter is given, no split will be performed and an
    * one element array gets returned.
    * Optional also filter-assertion delimeters can be considered (>, <, >=, <=, ~=).
    *
    * @param string  $attr      Attribute and Value Syntax ("foo=bar")
    * @param boolean $extended  If set to true, also filter-assertion delimeter will be matched
    * @param boolean $withDelim If set to true, the return array contains the delimeter at index 1, putting the value to index 2
    *
    * @return array Indexed array: 0=attribute name, 1=attribute value OR ($withDelim=true): 0=attr, 1=delimeter, 2=value
    */
    public static function split_attribute_string($attr, $extended=false, $withDelim=false)
    {
	if ($withDelim) $withDelim = PREG_SPLIT_DELIM_CAPTURE;

        if (!$extended) {
            return preg_split('/(?<!\\\\)(=)/', $attr, 2, $withDelim);
        } else {
            return preg_split('/(?<!\\\\)(>=|<=|>|<|~=|=)/', $attr, 2, $withDelim);
        }
    }

    /**
    * Corrects splitting of dn parts
    *
    * @param array $dn        Raw DN array
    * @param array $separator Separator that was used when splitting
    *
    * @return array Corrected array
    * @access protected
    */
    protected static function correct_dn_splitting($dn = array(), $separator = ',')
    {
        foreach ($dn as $key => $dn_value) {
            $dn_value = $dn[$key]; // refresh value (foreach caches!)
            // if the dn_value is not in attr=value format, then we had an
            // unescaped separator character inside the attr name or the value.
            // We assume, that it was the attribute value.
            // [TODO] To solve this, we might ask the schema. Keep in mind, that UTIL class
            //        must remain independent from the other classes or connections.
            if (!preg_match('/.+(?<!\\\\)=.+/', $dn_value)) {
                unset($dn[$key]);
                if (array_key_exists($key-1, $dn)) {
                    $dn[$key-1] = $dn[$key-1].$separator.$dn_value; // append to previous attr value
                } else {
                    $dn[$key+1] = $dn_value.$separator.$dn[$key+1]; // first element: prepend to next attr name
                }
            }
        }
        return array_values($dn);
    }
}

?>
