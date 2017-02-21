<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Logical representation of a vcard address record                    |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Logical representation of a vcard-based address record
 * Provides functions to parse and export vCard data format
 *
 * @package    Framework
 * @subpackage Addressbook
 */
class rcube_vcard
{
    private static $values_decoded = false;
    private $raw = array(
        'FN' => array(),
        'N'  => array(array('','','','','')),
    );
    private static $fieldmap = array(
        'phone'    => 'TEL',
        'birthday' => 'BDAY',
        'website'  => 'URL',
        'notes'    => 'NOTE',
        'email'    => 'EMAIL',
        'address'  => 'ADR',
        'jobtitle' => 'TITLE',
        'department'  => 'X-DEPARTMENT',
        'gender'      => 'X-GENDER',
        'maidenname'  => 'X-MAIDENNAME',
        'anniversary' => 'X-ANNIVERSARY',
        'assistant'   => 'X-ASSISTANT',
        'manager'     => 'X-MANAGER',
        'spouse'      => 'X-SPOUSE',
        'edit'        => 'X-AB-EDIT',
        'groups'      => 'CATEGORIES',
    );
    private $typemap = array(
        'IPHONE'   => 'mobile',
        'CELL'     => 'mobile',
        'WORK,FAX' => 'workfax',
    );
    private $phonetypemap = array(
        'HOME1'       => 'HOME',
        'BUSINESS1'   => 'WORK',
        'BUSINESS2'   => 'WORK2',
        'BUSINESSFAX' => 'WORK,FAX',
        'MOBILE'      => 'CELL',
    );
    private $addresstypemap = array(
        'BUSINESS' => 'WORK',
    );
    private $immap = array(
        'X-JABBER' => 'jabber',
        'X-ICQ'    => 'icq',
        'X-MSN'    => 'msn',
        'X-AIM'    => 'aim',
        'X-YAHOO'  => 'yahoo',
        'X-SKYPE'  => 'skype',
        'X-SKYPE-USERNAME' => 'skype',
    );

    public $business = false;
    public $displayname;
    public $surname;
    public $firstname;
    public $middlename;
    public $nickname;
    public $organization;
    public $email = array();

    public static $eol = "\r\n";


    /**
     * Constructor
     */
    public function __construct($vcard = null, $charset = RCUBE_CHARSET, $detect = false, $fieldmap = array())
    {
        if (!empty($fieldmap)) {
            $this->extend_fieldmap($fieldmap);
        }

        if (!empty($vcard)) {
            $this->load($vcard, $charset, $detect);
        }
    }

    /**
     * Load record from (internal, unfolded) vcard 3.0 format
     *
     * @param string vCard string to parse
     * @param string Charset of string values
     * @param boolean True if loading a 'foreign' vcard and extra heuristics for charset detection is required
     */
    public function load($vcard, $charset = RCUBE_CHARSET, $detect = false)
    {
        self::$values_decoded = false;
        $this->raw = self::vcard_decode(self::cleanup($vcard));

        // resolve charset parameters
        if ($charset == null) {
            $this->raw = self::charset_convert($this->raw);
        }
        // vcard has encoded values and charset should be detected
        else if ($detect && self::$values_decoded
            && ($detected_charset = self::detect_encoding(self::vcard_encode($this->raw)))
            && $detected_charset != RCUBE_CHARSET
        ) {
            $this->raw = self::charset_convert($this->raw, $detected_charset);
        }

        // find well-known address fields
        $this->displayname  = $this->raw['FN'][0][0];
        $this->surname      = $this->raw['N'][0][0];
        $this->firstname    = $this->raw['N'][0][1];
        $this->middlename   = $this->raw['N'][0][2];
        $this->nickname     = $this->raw['NICKNAME'][0][0];
        $this->organization = $this->raw['ORG'][0][0];
        $this->business     = ($this->raw['X-ABSHOWAS'][0][0] == 'COMPANY') || (join('', (array)$this->raw['N'][0]) == '' && !empty($this->organization));

        foreach ((array)$this->raw['EMAIL'] as $i => $raw_email) {
            $this->email[$i] = is_array($raw_email) ? $raw_email[0] : $raw_email;
        }

        // make the pref e-mail address the first entry in $this->email
        $pref_index = $this->get_type_index('EMAIL', 'pref');
        if ($pref_index > 0) {
            $tmp = $this->email[0];
            $this->email[0] = $this->email[$pref_index];
            $this->email[$pref_index] = $tmp;
        }

        // fix broken vcards from Outlook that only supply ORG but not the required N or FN properties
        if (!strlen(trim($this->displayname . $this->surname . $this->firstname)) && strlen($this->organization)) {
            $this->displayname = $this->organization;
        }
    }

    /**
     * Return vCard data as associative array to be unsed in Roundcube address books
     *
     * @return array Hash array with key-value pairs
     */
    public function get_assoc()
    {
        $out     = array('name' => $this->displayname);
        $typemap = $this->typemap;

        // copy name fields to output array
        foreach (array('firstname','surname','middlename','nickname','organization') as $col) {
            if (strlen($this->$col)) {
                $out[$col] = $this->$col;
            }
        }

        if ($this->raw['N'][0][3])
            $out['prefix'] = $this->raw['N'][0][3];
        if ($this->raw['N'][0][4])
            $out['suffix'] = $this->raw['N'][0][4];

        // convert from raw vcard data into associative data for Roundcube
        foreach (array_flip(self::$fieldmap) as $tag => $col) {
            foreach ((array)$this->raw[$tag] as $i => $raw) {
                if (is_array($raw)) {
                    $k       = -1;
                    $key     = $col;
                    $subtype = '';

                    if (!empty($raw['type'])) {
                        $combined = join(',', self::array_filter((array)$raw['type'], 'internet,pref', true));
                        $combined = strtoupper($combined);

                        if ($typemap[$combined]) {
                            $subtype = $typemap[$combined];
                        }
                        else if ($typemap[$raw['type'][++$k]]) {
                            $subtype = $typemap[$raw['type'][$k]];
                        }
                        else {
                            $subtype = strtolower($raw['type'][$k]);
                        }

                        while ($k < count($raw['type']) && ($subtype == 'internet' || $subtype == 'pref')) {
                            $subtype = $typemap[$raw['type'][++$k]] ?: strtolower($raw['type'][$k]);
                        }
                    }

                    // read vcard 2.1 subtype
                    if (!$subtype) {
                        foreach ($raw as $k => $v) {
                            if (!is_numeric($k) && $v === true && ($k = strtolower($k))
                                && !in_array($k, array('pref','internet','voice','base64'))
                            ) {
                                $k_uc    = strtoupper($k);
                                $subtype = $typemap[$k_uc] ?: $k;
                                break;
                            }
                        }
                    }

                    // force subtype if none set
                    if (!$subtype && preg_match('/^(email|phone|address|website)/', $key)) {
                        $subtype = 'other';
                    }

                    if ($subtype) {
                        $key .= ':' . $subtype;
                    }

                    // split ADR values into assoc array
                    if ($tag == 'ADR') {
                        list(,, $value['street'], $value['locality'], $value['region'], $value['zipcode'], $value['country']) = $raw;
                        $out[$key][] = $value;
                    }
                    else {
                        $out[$key][] = $raw[0];
                    }
                }
                else {
                    $out[$col][] = $raw;
                }
            }
        }

        // handle special IM fields as used by Apple
        foreach ($this->immap as $tag => $type) {
            foreach ((array)$this->raw[$tag] as $i => $raw) {
                $out['im:'.$type][] = $raw[0];
            }
        }

        // copy photo data
        if ($this->raw['PHOTO']) {
            $out['photo'] = $this->raw['PHOTO'][0][0];
        }

        return $out;
    }

    /**
     * Convert the data structure into a vcard 3.0 string
     */
    public function export($folded = true)
    {
        $vcard = self::vcard_encode($this->raw);
        return $folded ? self::rfc2425_fold($vcard) : $vcard;
    }

    /**
     * Clear the given fields in the loaded vcard data
     *
     * @param array List of field names to be reset
     */
    public function reset($fields = null)
    {
        if (!$fields) {
            $fields = array_merge(array_values(self::$fieldmap), array_keys($this->immap),
                array('FN','N','ORG','NICKNAME','EMAIL','ADR','BDAY'));
        }

        foreach ($fields as $f) {
            unset($this->raw[$f]);
        }

        if (!$this->raw['N']) {
            $this->raw['N'] = array(array('','','','',''));
        }
        if (!$this->raw['FN']) {
            $this->raw['FN'] = array();
        }

        $this->email = array();
    }

    /**
     * Setter for address record fields
     *
     * @param string Field name
     * @param string Field value
     * @param string Type/section name
     */
    public function set($field, $value, $type = 'HOME')
    {
        $field   = strtolower($field);
        $type_uc = strtoupper($type);

        switch ($field) {
        case 'name':
        case 'displayname':
            $this->raw['FN'][0][0] = $this->displayname = $value;
            break;

        case 'surname':
            $this->raw['N'][0][0] = $this->surname = $value;
            break;

        case 'firstname':
            $this->raw['N'][0][1] = $this->firstname = $value;
            break;

        case 'middlename':
            $this->raw['N'][0][2] = $this->middlename = $value;
            break;

        case 'prefix':
            $this->raw['N'][0][3] = $value;
            break;

        case 'suffix':
            $this->raw['N'][0][4] = $value;
            break;

        case 'nickname':
            $this->raw['NICKNAME'][0][0] = $this->nickname = $value;
            break;

        case 'organization':
            $this->raw['ORG'][0][0] = $this->organization = $value;
            break;

        case 'photo':
            if (strpos($value, 'http:') === 0) {
                // TODO: fetch file from URL and save it locally?
                $this->raw['PHOTO'][0] = array(0 => $value, 'url' => true);
            }
            else {
                $this->raw['PHOTO'][0] = array(0 => $value, 'base64' => (bool) preg_match('![^a-z0-9/=+-]!i', $value));
            }
            break;

        case 'email':
            $this->raw['EMAIL'][] = array(0 => $value, 'type' => array_filter(array('INTERNET', $type_uc)));
            $this->email[] = $value;
            break;

        case 'im':
            // save IM subtypes into extension fields
            $typemap = array_flip($this->immap);
            if ($field = $typemap[strtolower($type)]) {
                $this->raw[$field][] = array(0 => $value);
            }
            break;

        case 'birthday':
        case 'anniversary':
            if (($val = rcube_utils::anytodatetime($value)) && ($fn = self::$fieldmap[$field])) {
                $this->raw[$fn][] = array(0 => $val->format('Y-m-d'), 'value' => array('date'));
            }
            break;

        case 'address':
            if ($this->addresstypemap[$type_uc]) {
                $type = $this->addresstypemap[$type_uc];
            }

            $value = $value[0] ? $value : array('', '', $value['street'], $value['locality'], $value['region'], $value['zipcode'], $value['country']);

            // fall through if not empty
            if (!strlen(join('', $value))) {
                break;
            }

        default:
            if ($field == 'phone' && $this->phonetypemap[$type_uc]) {
                $type = $this->phonetypemap[$type_uc];
            }

            if (($tag = self::$fieldmap[$field]) && (is_array($value) || strlen($value))) {
                $index = count($this->raw[$tag]);
                $this->raw[$tag][$index] = (array)$value;
                if ($type) {
                    $typemap = array_flip($this->typemap);
                    $this->raw[$tag][$index]['type'] = explode(',', $typemap[$type_uc] ?: $type);
                }
            }
            else {
                unset($this->raw[$tag]);
            }

            break;
        }
    }

    /**
     * Setter for individual vcard properties
     *
     * @param string VCard tag name
     * @param array Value-set of this vcard property
     * @param boolean Set to true if the value-set should be appended instead of replacing any existing value-set
     */
    public function set_raw($tag, $value, $append = false)
    {
        $index = $append ? count($this->raw[$tag]) : 0;
        $this->raw[$tag][$index] = (array)$value;
    }

    /**
     * Find index with the '$type' attribute
     *
     * @param string Field name
     *
     * @return int Field index having $type set
     */
    private function get_type_index($field)
    {
        $result = 0;
        if ($this->raw[$field]) {
            foreach ($this->raw[$field] as $i => $data) {
                if (is_array($data['type']) && in_array_nocase('pref', $data['type'])) {
                    $result = $i;
                }
            }
        }

        return $result;
    }

    /**
     * Convert a whole vcard (array) to UTF-8.
     * If $force_charset is null, each member value that has a charset parameter will be converted
     */
    private static function charset_convert($card, $force_charset = null)
    {
        foreach ($card as $key => $node) {
            foreach ($node as $i => $subnode) {
                if (is_array($subnode) && (($charset = $force_charset) || ($subnode['charset'] && ($charset = $subnode['charset'][0])))) {
                    foreach ($subnode as $j => $value) {
                        if (is_numeric($j) && is_string($value)) {
                            $card[$key][$i][$j] = rcube_charset::convert($value, $charset);
                        }
                    }
                    unset($card[$key][$i]['charset']);
                }
            }
        }

        return $card;
    }

    /**
     * Extends fieldmap definition
     */
    public function extend_fieldmap($map)
    {
        if (is_array($map)) {
            self::$fieldmap = array_merge($map, self::$fieldmap);
        }
    }

    /**
     * Factory method to import a vcard file
     *
     * @param string vCard file content
     *
     * @return array List of rcube_vcard objects
     */
    public static function import($data)
    {
        $out = array();

        // check if charsets are specified (usually vcard version < 3.0 but this is not reliable)
        if (preg_match('/charset=/i', substr($data, 0, 2048))) {
            $charset = null;
        }
        // detect charset and convert to utf-8
        else if (($charset = self::detect_encoding($data)) && $charset != RCUBE_CHARSET) {
            $data = rcube_charset::convert($data, $charset);
            $data = preg_replace(array('/^[\xFE\xFF]{2}/', '/^\xEF\xBB\xBF/', '/^\x00+/'), '', $data); // also remove BOM
            $charset = RCUBE_CHARSET;
        }

        $vcard_block    = '';
        $in_vcard_block = false;

        foreach (preg_split("/[\r\n]+/", $data) as $line) {
            if ($in_vcard_block && !empty($line)) {
                $vcard_block .= $line . "\n";
            }

            $line = trim($line);

            if (preg_match('/^END:VCARD$/i', $line)) {
                // parse vcard
                $obj = new rcube_vcard($vcard_block, $charset, true, self::$fieldmap);
                // FN and N is required by vCard format (RFC 2426)
                // on import we can be less restrictive, let's addressbook decide
                if (!empty($obj->displayname) || !empty($obj->surname) || !empty($obj->firstname) || !empty($obj->email)) {
                    $out[] = $obj;
                }

                $in_vcard_block = false;
            }
            else if (preg_match('/^BEGIN:VCARD$/i', $line)) {
                $vcard_block    = $line . "\n";
                $in_vcard_block = true;
            }
        }

        return $out;
    }

    /**
     * Normalize vcard data for better parsing
     *
     * @param string vCard block
     *
     * @return string Cleaned vcard block
     */
    public static function cleanup($vcard)
    {
        // convert Apple X-ABRELATEDNAMES into X-* fields for better compatibility
        $vcard = preg_replace_callback(
            '/item(\d+)\.(X-ABRELATEDNAMES)([^:]*?):(.*?)item\1.X-ABLabel:(?:_\$!<)?([\w-() ]*)(?:>!\$_)?./s',
            array('self', 'x_abrelatednames_callback'),
            $vcard);

        // Cleanup
        $vcard = preg_replace(array(
                // convert special types (like Skype) to normal type='skype' classes with this simple regex ;)
                '/item(\d+)\.(TEL|EMAIL|URL)([^:]*?):(.*?)item\1.X-ABLabel:(?:_\$!<)?([\w-() ]*)(?:>!\$_)?./si',
                '/^item\d*\.X-AB.*$/mi',  // remove cruft like item1.X-AB*
                '/^item\d*\./mi',         // remove item1.ADR instead of ADR
                '/\n+/',                 // remove empty lines
                '/^(N:[^;\R]*)$/m',      // if N doesn't have any semicolons, add some
            ),
            array(
                '\2;type=\5\3:\4',
                '',
                '',
                "\n",
                '\1;;;;',
            ), $vcard);

        // convert X-WAB-GENDER to X-GENDER
        if (preg_match('/X-WAB-GENDER:(\d)/', $vcard, $matches)) {
            $value = $matches[1] == '2' ? 'male' : 'female';
            $vcard = preg_replace('/X-WAB-GENDER:\d/', 'X-GENDER:' . $value, $vcard);
        }

        return $vcard;
    }

    private static function x_abrelatednames_callback($matches)
    {
        return 'X-' . strtoupper($matches[5]) . $matches[3] . ':'. $matches[4];
    }

    private static function rfc2425_fold_callback($matches)
    {
        // chunk_split string and avoid lines breaking multibyte characters
        $c = 71;
        $out .= substr($matches[1], 0, $c);
        for ($n = $c; $c < strlen($matches[1]); $c++) {
            // break if length > 75 or mutlibyte character starts after position 71
            if ($n > 75 || ($n > 71 && ord($matches[1][$c]) >> 6 == 3)) {
                $out .= "\r\n ";
                $n = 0;
            }
            $out .= $matches[1][$c];
            $n++;
        }

        return $out;
    }

    public static function rfc2425_fold($val)
    {
        return preg_replace_callback('/([^\n]{72,})/', array('self', 'rfc2425_fold_callback'), $val);
    }

    /**
     * Decodes a vcard block (vcard 3.0 format, unfolded)
     * into an array structure
     *
     * @param string vCard block to parse
     *
     * @return array Raw data structure
     */
    private static function vcard_decode($vcard)
    {
        // Perform RFC2425 line unfolding and split lines
        $vcard  = preg_replace(array("/\r/", "/\n\s+/"), '', $vcard);
        $lines  = explode("\n", $vcard);
        $result = array();

        for ($i=0; $i < count($lines); $i++) {
            if (!($pos = strpos($lines[$i], ':'))) {
                continue;
            }

            $prefix = substr($lines[$i], 0, $pos);
            $data   = substr($lines[$i], $pos+1);

            if (preg_match('/^(BEGIN|END)$/i', $prefix)) {
                continue;
            }

            // convert 2.1-style "EMAIL;internet;home:" to 3.0-style "EMAIL;TYPE=internet;TYPE=home:"
            if ($result['VERSION'][0] == "2.1"
                && preg_match('/^([^;]+);([^:]+)/', $prefix, $regs2)
                && !preg_match('/^TYPE=/i', $regs2[2])
            ) {
                $prefix = $regs2[1];
                foreach (explode(';', $regs2[2]) as $prop) {
                    $prefix .= ';' . (strpos($prop, '=') ? $prop : 'TYPE='.$prop);
                }
            }

            if (preg_match_all('/([^\\;]+);?/', $prefix, $regs2)) {
                $entry = array();
                $field = strtoupper($regs2[1][0]);
                $enc   = null;

                foreach($regs2[1] as $attrid => $attr) {
                    $attr = preg_replace('/[\s\t\n\r\0\x0B]/', '', $attr);
                    if ((list($key, $value) = explode('=', $attr)) && $value) {
                        if ($key == 'ENCODING') {
                            $value = strtoupper($value);
                            // add next line(s) to value string if QP line end detected
                            if ($value == 'QUOTED-PRINTABLE') {
                                while (preg_match('/=$/', $lines[$i])) {
                                    $data .= "\n" . $lines[++$i];
                                }
                            }
                            $enc = $value == 'BASE64' ? 'B' : $value;
                        }
                        else {
                            $lc_key = strtolower($key);
                            $entry[$lc_key] = array_merge((array)$entry[$lc_key], (array)self::vcard_unquote($value, ','));
                        }
                    }
                    else if ($attrid > 0) {
                        $entry[strtolower($key)] = true;  // true means attr without =value
                    }
                }

                // decode value
                if ($enc || !empty($entry['base64'])) {
                    // save encoding type (#1488432)
                    if ($enc == 'B') {
                        $entry['encoding'] = 'B';
                        // should we use vCard 3.0 instead?
                        // $entry['base64'] = true;
                    }

                    $data = self::decode_value($data, $enc ?: 'base64');
                }
                else if ($field == 'PHOTO') {
                    // vCard 4.0 data URI, "PHOTO:data:image/jpeg;base64,..."
                    if (preg_match('/^data:[a-z\/_-]+;base64,/i', $data, $m)) {
                        $entry['encoding'] = $enc = 'B';
                        $data = substr($data, strlen($m[0]));
                        $data = self::decode_value($data, 'base64');
                    }
                }

                if ($enc != 'B' && empty($entry['base64'])) {
                    $data = self::vcard_unquote($data);
                }

                $entry = array_merge($entry, (array) $data);
                $result[$field][] = $entry;
            }
        }

        unset($result['VERSION']);

        return $result;
    }

    /**
     * Decode a given string with the encoding rule from ENCODING attributes
     *
     * @param string String to decode
     * @param string Encoding type (quoted-printable and base64 supported)
     *
     * @return string Decoded 8bit value
     */
    private static function decode_value($value, $encoding)
    {
        switch (strtolower($encoding)) {
        case 'quoted-printable':
            self::$values_decoded = true;
            return quoted_printable_decode($value);

        case 'base64':
        case 'b':
            self::$values_decoded = true;
            return base64_decode($value);

        default:
            return $value;
        }
    }

    /**
     * Encodes an entry for storage in our database (vcard 3.0 format, unfolded)
     *
     * @param array Raw data structure to encode
     *
     * @return string vCard encoded string
     */
    static function vcard_encode($data)
    {
        foreach ((array)$data as $type => $entries) {
            // valid N has 5 properties
            while ($type == "N" && is_array($entries[0]) && count($entries[0]) < 5) {
                $entries[0][] = "";
            }

            // make sure FN is not empty (required by RFC2426)
            if ($type == "FN" && empty($entries)) {
                $entries[0] = $data['EMAIL'][0][0];
            }

            foreach ((array)$entries as $entry) {
                $attr = '';
                if (is_array($entry)) {
                    $value = array();
                    foreach ($entry as $attrname => $attrvalues) {
                        if (is_int($attrname)) {
                            if (!empty($entry['base64']) || $entry['encoding'] == 'B') {
                                $attrvalues = base64_encode($attrvalues);
                            }
                            $value[] = $attrvalues;
                        }
                        else if (is_bool($attrvalues)) {
                            // true means just a tag, not tag=value, as in PHOTO;BASE64:...
                            if ($attrvalues) {
                                // vCard v3 uses ENCODING=b (#1489183)
                                if ($attrname == 'base64') {
                                    $attr .= ";ENCODING=b";
                                }
                                else {
                                    $attr .= strtoupper(";$attrname");
                                }
                            }
                        }
                        else {
                            foreach((array)$attrvalues as $attrvalue) {
                                $attr .= strtoupper(";$attrname=") . self::vcard_quote($attrvalue, ',');
                            }
                        }
                    }
                }
                else {
                    $value = $entry;
                }

                // skip empty entries
                if (self::is_empty($value)) {
                    continue;
                }

                $vcard .= self::vcard_quote($type) . $attr . ':' . self::vcard_quote($value) . self::$eol;
            }
        }

        return 'BEGIN:VCARD' . self::$eol . 'VERSION:3.0' . self::$eol . $vcard . 'END:VCARD';
    }

    /**
     * Join indexed data array to a vcard quoted string
     *
     * @param array Field data
     * @param string Separator
     *
     * @return string Joined and quoted string
     */
    public static function vcard_quote($s, $sep = ';')
    {
        if (is_array($s)) {
            foreach($s as $part) {
                $r[] = self::vcard_quote($part, $sep);
            }
            return(implode($sep, (array)$r));
        }

        return strtr($s, array('\\' => '\\\\', "\r" => '', "\n" => '\n', $sep => '\\'.$sep));
    }

    /**
     * Split quoted string
     *
     * @param string vCard string to split
     * @param string Separator char/string
     *
     * @return array List with splited values
     */
    private static function vcard_unquote($s, $sep = ';')
    {
        // break string into parts separated by $sep
        if (!empty($sep)) {
            // Handle properly backslash escaping (#1488896)
            $rep1 = array("\\\\" => "\010", "\\$sep" => "\007");
            $rep2 = array("\007" => "\\$sep", "\010" => "\\\\");

            if (count($parts = explode($sep, strtr($s, $rep1))) > 1) {
                foreach ($parts as $s) {
                    $result[] = self::vcard_unquote(strtr($s, $rep2));
                }
                return $result;
            }

            $s = trim(strtr($s, $rep2));
        }

        // some implementations (GMail) use non-standard backslash before colon (#1489085)
        // we will handle properly any backslashed character - removing dummy backslahes
        // return strtr($s, array("\r" => '', '\\\\' => '\\', '\n' => "\n", '\N' => "\n", '\,' => ',', '\;' => ';'));

        $s   = str_replace("\r", '', $s);
        $pos = 0;

        while (($pos = strpos($s, '\\', $pos)) !== false) {
            $next = substr($s, $pos + 1, 1);
            if ($next == 'n' || $next == 'N') {
                $s = substr_replace($s, "\n", $pos, 2);
            }
            else {
                $s = substr_replace($s, '', $pos, 1);
            }

            $pos += 1;
        }

        return $s;
    }

    /**
     * Check if vCard entry is empty: empty string or an array with
     * all entries empty.
     *
     * @param mixed $value Attribute value (string or array)
     *
     * @return bool True if the value is empty, False otherwise
     */
    private static function is_empty($value)
    {
        foreach ((array)$value as $v) {
            if (((string)$v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract array values by a filter
     *
     * @param array Array to filter
     * @param keys Array or comma separated list of values to keep
     * @param boolean Invert key selection: remove the listed values
     *
     * @return array The filtered array
     */
    private static function array_filter($arr, $values, $inverse = false)
    {
        if (!is_array($values)) {
            $values = explode(',', $values);
        }

        $result = array();
        $keep   = array_flip((array)$values);

        foreach ($arr as $key => $val) {
            if ($inverse != isset($keep[strtolower($val)])) {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * Returns UNICODE type based on BOM (Byte Order Mark)
     *
     * @param string Input string to test
     *
     * @return string Detected encoding
     */
    private static function detect_encoding($string)
    {
        $fallback = rcube::get_instance()->config->get('default_charset', 'ISO-8859-1'); // fallback to Latin-1

        return rcube_charset::detect($string, $fallback);
    }
}
