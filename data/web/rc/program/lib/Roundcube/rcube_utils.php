<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Utility class providing common functions                            |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Utility class providing common functions
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_utils
{
    // define constants for input reading
    const INPUT_GET  = 0x0101;
    const INPUT_POST = 0x0102;
    const INPUT_GPC  = 0x0103;

    /**
     * Helper method to set a cookie with the current path and host settings
     *
     * @param string Cookie name
     * @param string Cookie value
     * @param string Expiration time
     */
    public static function setcookie($name, $value, $exp = 0)
    {
        if (headers_sent()) {
            return;
        }

        $cookie = session_get_cookie_params();
        $secure = $cookie['secure'] || self::https_check();

        setcookie($name, $value, $exp, $cookie['path'], $cookie['domain'], $secure, true);
    }

    /**
     * E-mail address validation.
     *
     * @param string $email Email address
     * @param boolean $dns_check True to check dns
     *
     * @return boolean True on success, False if address is invalid
     */
    public static function check_email($email, $dns_check=true)
    {
        // Check for invalid characters
        if (preg_match('/[\x00-\x1F\x7F-\xFF]/', $email)) {
            return false;
        }

        // Check for length limit specified by RFC 5321 (#1486453)
        if (strlen($email) > 254) {
            return false;
        }

        $email_array = explode('@', $email);

        // Check that there's one @ symbol
        if (count($email_array) < 2) {
            return false;
        }

        $domain_part = array_pop($email_array);
        $local_part  = implode('@', $email_array);

        // from PEAR::Validate
        $regexp = '&^(?:
            ("\s*(?:[^"\f\n\r\t\v\b\s]+\s*)+")|                             #1 quoted name
            ([-\w!\#\$%\&\'*+~/^`|{}=]+(?:\.[-\w!\#\$%\&\'*+~/^`|{}=]+)*))  #2 OR dot-atom (RFC5322)
            $&xi';

        if (!preg_match($regexp, $local_part)) {
            return false;
        }

        // Validate domain part
        if (preg_match('/^\[((IPv6:[0-9a-f:.]+)|([0-9.]+))\]$/i', $domain_part, $matches)) {
            return self::check_ip(preg_replace('/^IPv6:/i', '', $matches[1])); // valid IPv4 or IPv6 address
        }
        else {
            // If not an IP address
            $domain_array = explode('.', $domain_part);
            // Not enough parts to be a valid domain
            if (sizeof($domain_array) < 2) {
                return false;
            }

            foreach ($domain_array as $part) {
                if (!preg_match('/^((xn--)?([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]))$/', $part)) {
                    return false;
                }
            }

            // last domain part
            $last_part = array_pop($domain_array);
            if (strpos($last_part, 'xn--') !== 0 && preg_match('/[^a-zA-Z]/', $last_part)) {
                return false;
            }

            $rcube = rcube::get_instance();

            if (!$dns_check || !$rcube->config->get('email_dns_check')) {
                return true;
            }

            // find MX record(s)
            if (!function_exists('getmxrr') || getmxrr($domain_part, $mx_records)) {
                return true;
            }

            // find any DNS record
            if (!function_exists('checkdnsrr') || checkdnsrr($domain_part, 'ANY')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates IPv4 or IPv6 address
     *
     * @param string $ip IP address in v4 or v6 format
     *
     * @return bool True if the address is valid
     */
    public static function check_ip($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check whether the HTTP referer matches the current request
     *
     * @return boolean True if referer is the same host+path, false if not
     */
    public static function check_referer()
    {
        $uri     = parse_url($_SERVER['REQUEST_URI']);
        $referer = parse_url(self::request_header('Referer'));

        return $referer['host'] == self::request_header('Host') && $referer['path'] == $uri['path'];
    }

    /**
     * Replacing specials characters to a specific encoding type
     *
     * @param string  Input string
     * @param string  Encoding type: text|html|xml|js|url
     * @param string  Replace mode for tags: show|remove|strict
     * @param boolean Convert newlines
     *
     * @return string The quoted string
     */
    public static function rep_specialchars_output($str, $enctype = '', $mode = '', $newlines = true)
    {
        static $html_encode_arr = false;
        static $js_rep_table    = false;
        static $xml_rep_table   = false;

        if (!is_string($str)) {
            $str = strval($str);
        }

        // encode for HTML output
        if ($enctype == 'html') {
            if (!$html_encode_arr) {
                $html_encode_arr = get_html_translation_table(HTML_SPECIALCHARS);
                unset($html_encode_arr['?']);
            }

            $encode_arr = $html_encode_arr;

            if ($mode == 'remove') {
                $str = strip_tags($str);
            }
            else if ($mode != 'strict') {
                // don't replace quotes and html tags
                $ltpos = strpos($str, '<');
                if ($ltpos !== false && strpos($str, '>', $ltpos) !== false) {
                    unset($encode_arr['"']);
                    unset($encode_arr['<']);
                    unset($encode_arr['>']);
                    unset($encode_arr['&']);
                }
            }

            $out = strtr($str, $encode_arr);

            return $newlines ? nl2br($out) : $out;
        }

        // if the replace tables for XML and JS are not yet defined
        if ($js_rep_table === false) {
            $js_rep_table = $xml_rep_table = array();
            $xml_rep_table['&'] = '&amp;';

            // can be increased to support more charsets
            for ($c=160; $c<256; $c++) {
                $xml_rep_table[chr($c)] = "&#$c;";
            }

            $xml_rep_table['"'] = '&quot;';
            $js_rep_table['"']  = '\\"';
            $js_rep_table["'"]  = "\\'";
            $js_rep_table["\\"] = "\\\\";
            // Unicode line and paragraph separators (#1486310)
            $js_rep_table[chr(hexdec('E2')).chr(hexdec('80')).chr(hexdec('A8'))] = '&#8232;';
            $js_rep_table[chr(hexdec('E2')).chr(hexdec('80')).chr(hexdec('A9'))] = '&#8233;';
        }

        // encode for javascript use
        if ($enctype == 'js') {
            return preg_replace(array("/\r?\n/", "/\r/", '/<\\//'), array('\n', '\n', '<\\/'), strtr($str, $js_rep_table));
        }

        // encode for plaintext
        if ($enctype == 'text') {
            return str_replace("\r\n", "\n", $mode == 'remove' ? strip_tags($str) : $str);
        }

        if ($enctype == 'url') {
            return rawurlencode($str);
        }

        // encode for XML
        if ($enctype == 'xml') {
            return strtr($str, $xml_rep_table);
        }

        // no encoding given -> return original string
        return $str;
    }

    /**
     * Read input value and convert it for internal use
     * Performs stripslashes() and charset conversion if necessary
     *
     * @param string  Field name to read
     * @param int     Source to get value from (GPC)
     * @param boolean Allow HTML tags in field value
     * @param string  Charset to convert into
     *
     * @return string Field value or NULL if not available
     */
    public static function get_input_value($fname, $source, $allow_html = false, $charset = null)
    {
        $value = null;

        if ($source == self::INPUT_GET) {
            if (isset($_GET[$fname])) {
                $value = $_GET[$fname];
            }
        }
        else if ($source == self::INPUT_POST) {
            if (isset($_POST[$fname])) {
                $value = $_POST[$fname];
            }
        }
        else if ($source == self::INPUT_GPC) {
            if (isset($_POST[$fname])) {
                $value = $_POST[$fname];
            }
            else if (isset($_GET[$fname])) {
                $value = $_GET[$fname];
            }
            else if (isset($_COOKIE[$fname])) {
                $value = $_COOKIE[$fname];
            }
        }

        return self::parse_input_value($value, $allow_html, $charset);
    }

    /**
     * Parse/validate input value. See self::get_input_value()
     * Performs stripslashes() and charset conversion if necessary
     *
     * @param string  Input value
     * @param boolean Allow HTML tags in field value
     * @param string  Charset to convert into
     *
     * @return string Parsed value
     */
    public static function parse_input_value($value, $allow_html = false, $charset = null)
    {
        global $OUTPUT;

        if (empty($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $idx => $val) {
                $value[$idx] = self::parse_input_value($val, $allow_html, $charset);
            }
            return $value;
        }

        // remove HTML tags if not allowed
        if (!$allow_html) {
            $value = strip_tags($value);
        }

        $output_charset = is_object($OUTPUT) ? $OUTPUT->get_charset() : null;

        // remove invalid characters (#1488124)
        if ($output_charset == 'UTF-8') {
            $value = rcube_charset::clean($value);
        }

        // convert to internal charset
        if ($charset && $output_charset) {
            $value = rcube_charset::convert($value, $output_charset, $charset);
        }

        return $value;
    }

    /**
     * Convert array of request parameters (prefixed with _)
     * to a regular array with non-prefixed keys.
     *
     * @param int     $mode       Source to get value from (GPC)
     * @param string  $ignore     PCRE expression to skip parameters by name
     * @param boolean $allow_html Allow HTML tags in field value
     *
     * @return array Hash array with all request parameters
     */
    public static function request2param($mode = null, $ignore = 'task|action', $allow_html = false)
    {
        $out = array();
        $src = $mode == self::INPUT_GET ? $_GET : ($mode == self::INPUT_POST ? $_POST : $_REQUEST);

        foreach (array_keys($src) as $key) {
            $fname = $key[0] == '_' ? substr($key, 1) : $key;
            if ($ignore && !preg_match('/^(' . $ignore . ')$/', $fname)) {
                $out[$fname] = self::get_input_value($key, $mode, $allow_html);
            }
        }

        return $out;
    }

    /**
     * Convert the given string into a valid HTML identifier
     * Same functionality as done in app.js with rcube_webmail.html_identifier()
     */
    public static function html_identifier($str, $encode=false)
    {
        if ($encode) {
            return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
        }
        else {
            return asciiwords($str, true, '_');
        }
    }

    /**
     * Replace all css definitions with #container [def]
     * and remove css-inlined scripting, make position style safe
     *
     * @param string CSS source code
     * @param string Container ID to use as prefix
     * @param bool   Allow remote content
     *
     * @return string Modified CSS source
     */
    public static function mod_css_styles($source, $container_id, $allow_remote = false)
    {
        $last_pos     = 0;
        $replacements = new rcube_string_replacer;

        // ignore the whole block if evil styles are detected
        $source   = self::xss_entity_decode($source);
        $stripped = preg_replace('/[^a-z\(:;]/i', '', $source);
        $evilexpr = 'expression|behavior|javascript:|import[^a]' . (!$allow_remote ? '|url\(' : '');

        if (preg_match("/$evilexpr/i", $stripped)) {
            return '/* evil! */';
        }

        $strict_url_regexp = '!url\s*\([ "\'](https?:)//[a-z0-9/._+-]+["\' ]\)!Uims';

        // cut out all contents between { and }
        while (($pos = strpos($source, '{', $last_pos)) && ($pos2 = strpos($source, '}', $pos))) {
            $nested = strpos($source, '{', $pos+1);
            if ($nested && $nested < $pos2)  // when dealing with nested blocks (e.g. @media), take the inner one
                $pos = $nested;
            $length = $pos2 - $pos - 1;
            $styles = substr($source, $pos+1, $length);

            // Convert position:fixed to position:absolute (#5264)
            $styles = preg_replace('/position:[\s\r\n]*fixed/i', 'position: absolute', $styles);

            // check every line of a style block...
            if ($allow_remote) {
                $a_styles = preg_split('/;[\r\n]*/', $styles, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($a_styles as $line) {
                    $stripped = preg_replace('/[^a-z\(:;]/i', '', $line);
                    // ... and only allow strict url() values
                    if (stripos($stripped, 'url(') && !preg_match($strict_url_regexp, $line)) {
                        $a_styles = array('/* evil! */');
                        break;
                    }
                }

                $styles = join(";\n", $a_styles);
            }

            $key      = $replacements->add($styles);
            $repl     = $replacements->get_replacement($key);
            $source   = substr_replace($source, $repl, $pos+1, $length);
            $last_pos = $pos2 - ($length - strlen($repl));
        }

        // remove html comments and add #container to each tag selector.
        // also replace body definition because we also stripped off the <body> tag
        $source = preg_replace(
            array(
                '/(^\s*<\!--)|(-->\s*$)/m',
                '/(^\s*|,\s*|\}\s*)([a-z0-9\._#\*][a-z0-9\.\-_]*)/im',
                '/'.preg_quote($container_id, '/').'\s+body/i',
            ),
            array(
                '',
                "\\1#$container_id \\2",
                $container_id,
            ),
            $source);

        // put block contents back in
        $source = $replacements->resolve($source);

        return $source;
    }

    /**
     * Generate CSS classes from mimetype and filename extension
     *
     * @param string $mimetype Mimetype
     * @param string $filename Filename
     *
     * @return string CSS classes separated by space
     */
    public static function file2class($mimetype, $filename)
    {
        $mimetype = strtolower($mimetype);
        $filename = strtolower($filename);

        list($primary, $secondary) = explode('/', $mimetype);

        $classes = array($primary ?: 'unknown');

        if ($secondary) {
            $classes[] = $secondary;
        }

        if (preg_match('/\.([a-z0-9]+)$/', $filename, $m)) {
            if (!in_array($m[1], $classes)) {
                $classes[] = $m[1];
            }
        }

        return join(" ", $classes);
    }

    /**
     * Decode escaped entities used by known XSS exploits.
     * See http://downloads.securityfocus.com/vulnerabilities/exploits/26800.eml for examples
     *
     * @param string CSS content to decode
     *
     * @return string Decoded string
     */
    public static function xss_entity_decode($content)
    {
        $out = html_entity_decode(html_entity_decode($content));
        $out = preg_replace_callback('/\\\([0-9a-f]{4})/i',
            array(self, 'xss_entity_decode_callback'), $out);
        $out = preg_replace('#/\*.*\*/#Ums', '', $out);

        return $out;
    }

    /**
     * preg_replace_callback callback for xss_entity_decode
     *
     * @param array $matches Result from preg_replace_callback
     *
     * @return string Decoded entity
     */
    public static function xss_entity_decode_callback($matches)
    {
        return chr(hexdec($matches[1]));
    }

    /**
     * Check if we can process not exceeding memory_limit
     *
     * @param integer Required amount of memory
     *
     * @return boolean True if memory won't be exceeded, False otherwise
     */
    public static function mem_check($need)
    {
        $mem_limit = parse_bytes(ini_get('memory_limit'));
        $memory    = function_exists('memory_get_usage') ? memory_get_usage() : 16*1024*1024; // safe value: 16MB

        return $mem_limit > 0 && $memory + $need > $mem_limit ? false : true;
    }

    /**
     * Check if working in SSL mode
     *
     * @param integer $port      HTTPS port number
     * @param boolean $use_https Enables 'use_https' option checking
     *
     * @return boolean
     */
    public static function https_check($port=null, $use_https=true)
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https'
            && in_array($_SERVER['REMOTE_ADDR'], rcube::get_instance()->config->get('proxy_whitelist', array()))
        ) {
            return true;
        }
        if ($port && $_SERVER['SERVER_PORT'] == $port) {
            return true;
        }
        if ($use_https && rcube::get_instance()->config->get('use_https')) {
            return true;
        }

        return false;
    }

    /**
     * Replaces hostname variables.
     *
     * @param string $name Hostname
     * @param string $host Optional IMAP hostname
     *
     * @return string Hostname
     */
    public static function parse_host($name, $host = '')
    {
        if (!is_string($name)) {
            return $name;
        }

        // %n - host
        $n = preg_replace('/:\d+$/', '', $_SERVER['SERVER_NAME']);
        // %t - host name without first part, e.g. %n=mail.domain.tld, %t=domain.tld
        $t = preg_replace('/^[^\.]+\./', '', $n);
        // %d - domain name without first part
        $d = preg_replace('/^[^\.]+\./', '', $_SERVER['HTTP_HOST']);
        // %h - IMAP host
        $h = $_SESSION['storage_host'] ?: $host;
        // %z - IMAP domain without first part, e.g. %h=imap.domain.tld, %z=domain.tld
        $z = preg_replace('/^[^\.]+\./', '', $h);
        // %s - domain name after the '@' from e-mail address provided at login screen.
        //      Returns FALSE if an invalid email is provided
        if (strpos($name, '%s') !== false) {
            $user_email = self::get_input_value('_user', self::INPUT_POST);
            $user_email = self::idn_convert($user_email, true);
            $matches    = preg_match('/(.*)@([a-z0-9\.\-\[\]\:]+)/i', $user_email, $s);
            if ($matches < 1 || filter_var($s[1]."@".$s[2], FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
        }

        return str_replace(array('%n', '%t', '%d', '%h', '%z', '%s'), array($n, $t, $d, $h, $z, $s[2]), $name);
    }

    /**
     * Returns remote IP address and forwarded addresses if found
     *
     * @return string Remote IP address(es)
     */
    public static function remote_ip()
    {
        $address = $_SERVER['REMOTE_ADDR'];

        // append the NGINX X-Real-IP header, if set
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $remote_ip[] = 'X-Real-IP: ' . $_SERVER['HTTP_X_REAL_IP'];
        }

        // append the X-Forwarded-For header, if set
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $remote_ip[] = 'X-Forwarded-For: ' . $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if (!empty($remote_ip)) {
            $address .= '(' . implode(',', $remote_ip) . ')';
        }

        return $address;
    }

    /**
     * Returns the real remote IP address
     *
     * @return string Remote IP address
     */
    public static function remote_addr()
    {
        // Check if any of the headers are set first to improve performance
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $proxy_whitelist = rcube::get_instance()->config->get('proxy_whitelist', array());
            if (in_array($_SERVER['REMOTE_ADDR'], $proxy_whitelist)) {
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    foreach(array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $forwarded_ip) {
                        if (!in_array($forwarded_ip, $proxy_whitelist)) {
                            return $forwarded_ip;
                        }
                    }
                }

                if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                    return $_SERVER['HTTP_X_REAL_IP'];
                }
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '';
    }

    /**
     * Read a specific HTTP request header.
     *
     * @param string $name Header name
     *
     * @return mixed Header value or null if not available
     */
    public static function request_header($name)
    {
        if (function_exists('getallheaders')) {
            $hdrs = array_change_key_case(getallheaders(), CASE_UPPER);
            $key  = strtoupper($name);
        }
        else {
            $key  = 'HTTP_' . strtoupper(strtr($name, '-', '_'));
            $hdrs = array_change_key_case($_SERVER, CASE_UPPER);
        }

        return $hdrs[$key];
    }

    /**
     * Explode quoted string
     *
     * @param string Delimiter expression string for preg_match()
     * @param string Input string
     *
     * @return array String items
     */
    public static function explode_quoted_string($delimiter, $string)
    {
        $result = array();
        $strlen = strlen($string);

        for ($q=$p=$i=0; $i < $strlen; $i++) {
            if ($string[$i] == "\"" && $string[$i-1] != "\\") {
                $q = $q ? false : true;
            }
            else if (!$q && preg_match("/$delimiter/", $string[$i])) {
                $result[] = substr($string, $p, $i - $p);
                $p = $i + 1;
            }
        }

        $result[] = (string) substr($string, $p);

        return $result;
    }

    /**
     * Improved equivalent to strtotime()
     *
     * @param string       $date     Date string
     * @param DateTimeZone $timezone Timezone to use for DateTime object
     *
     * @return int Unix timestamp
     */
    public static function strtotime($date, $timezone = null)
    {
        $date   = self::clean_datestr($date);
        $tzname = $timezone ? ' ' . $timezone->getName() : '';

        // unix timestamp
        if (is_numeric($date)) {
            return (int) $date;
        }

        // if date parsing fails, we have a date in non-rfc format.
        // remove token from the end and try again
        while ((($ts = @strtotime($date . $tzname)) === false) || ($ts < 0)) {
            $d = explode(' ', $date);
            array_pop($d);
            if (!$d) {
                break;
            }
            $date = implode(' ', $d);
        }

        return (int) $ts;
    }

    /**
     * Date parsing function that turns the given value into a DateTime object
     *
     * @param string       $date     Date string
     * @param DateTimeZone $timezone Timezone to use for DateTime object
     *
     * @return DateTime instance or false on failure
     */
    public static function anytodatetime($date, $timezone = null)
    {
        if ($date instanceof DateTime) {
            return $date;
        }

        $dt   = false;
        $date = self::clean_datestr($date);

        // try to parse string with DateTime first
        if (!empty($date)) {
            try {
                $dt = $timezone ? new DateTime($date, $timezone) : new DateTime($date);
            }
            catch (Exception $e) {
                // ignore
            }
        }

        // try our advanced strtotime() method
        if (!$dt && ($timestamp = self::strtotime($date, $timezone))) {
            try {
                $dt = new DateTime("@".$timestamp);
                if ($timezone) {
                    $dt->setTimezone($timezone);
                }
            }
            catch (Exception $e) {
                // ignore
            }
        }

        return $dt;
    }

    /**
     * Clean up date string for strtotime() input
     *
     * @param string $date Date string
     *
     * @return string Date string
     */
    public static function clean_datestr($date)
    {
        $date = trim($date);

        // check for MS Outlook vCard date format YYYYMMDD
        if (preg_match('/^([12][90]\d\d)([01]\d)([0123]\d)$/', $date, $m)) {
            return sprintf('%04d-%02d-%02d 00:00:00', intval($m[1]), intval($m[2]), intval($m[3]));
        }

        // Clean malformed data
        $date = preg_replace(
            array(
                '/GMT\s*([+-][0-9]+)/',                     // support non-standard "GMTXXXX" literal
                '/[^a-z0-9\x20\x09:+-\/]/i',                // remove any invalid characters
                '/\s*(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s*/i',   // remove weekday names
            ),
            array(
                '\\1',
                '',
                '',
            ), $date);

        $date = trim($date);

        // try to fix dd/mm vs. mm/dd discrepancy, we can't do more here
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})(\s.*)?$/', $date, $m)) {
            $mdy   = $m[2] > 12 && $m[1] <= 12;
            $day   = $mdy ? $m[2] : $m[1];
            $month = $mdy ? $m[1] : $m[2];
            $date  = sprintf('%04d-%02d-%02d%s', $m[3], $month, $day, $m[4] ?: ' 00:00:00');
        }
        // I've found that YYYY.MM.DD is recognized wrong, so here's a fix
        else if (preg_match('/^(\d{4})\.(\d{1,2})\.(\d{1,2})(\s.*)?$/', $date, $m)) {
            $date  = sprintf('%04d-%02d-%02d%s', $m[1], $m[2], $m[3], $m[4] ?: ' 00:00:00');
        }

        return $date;
    }

    /**
     * Turns the given date-only string in defined format into YYYY-MM-DD format.
     *
     * Supported formats: 'Y/m/d', 'Y.m.d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'j.n.Y'
     *
     * @param string $date   Date string
     * @param string $format Input date format
     *
     * @return strin Date string in YYYY-MM-DD format, or the original string
     *               if format is not supported
     */
    public static function format_datestr($date, $format)
    {
        $format_items = preg_split('/[.-\/\\\\]/', $format);
        $date_items   = preg_split('/[.-\/\\\\]/', $date);
        $iso_format   = '%04d-%02d-%02d';

        if (count($format_items) == 3 && count($date_items) == 3) {
            if ($format_items[0] == 'Y') {
                $date = sprintf($iso_format, $date_items[0], $date_items[1], $date_items[2]);
            }
            else if (strpos('dj', $format_items[0]) !== false) {
                $date = sprintf($iso_format, $date_items[2], $date_items[1], $date_items[0]);
            }
            else if (strpos('mn', $format_items[0]) !== false) {
                $date = sprintf($iso_format, $date_items[2], $date_items[0], $date_items[1]);
            }
        }

        return $date;
    }

    /*
     * Idn_to_ascii wrapper.
     * Intl/Idn modules version of this function doesn't work with e-mail address
     */
    public static function idn_to_ascii($str)
    {
        return self::idn_convert($str, true);
    }

    /*
     * Idn_to_ascii wrapper.
     * Intl/Idn modules version of this function doesn't work with e-mail address
     */
    public static function idn_to_utf8($str)
    {
        return self::idn_convert($str, false);
    }

    public static function idn_convert($input, $is_utf = false)
    {
        if ($at = strpos($input, '@')) {
            $user   = substr($input, 0, $at);
            $domain = substr($input, $at+1);
        }
        else {
            $domain = $input;
        }

        $domain = $is_utf ? idn_to_ascii($domain) : idn_to_utf8($domain);

        if ($domain === false) {
            return '';
        }

        return $at ? $user . '@' . $domain : $domain;
    }

    /**
     * Split the given string into word tokens
     *
     * @param string Input to tokenize
     * @param integer Minimum length of a single token
     * @return array List of tokens
     */
    public static function tokenize_string($str, $minlen = 2)
    {
        $expr = array('/[\s;,"\'\/+-]+/ui', '/(\d)[-.\s]+(\d)/u');
        $repl = array(' ', '\\1\\2');

        if ($minlen > 1) {
            $minlen--;
            $expr[] = "/(^|\s+)\w{1,$minlen}(\s+|$)/u";
            $repl[] = ' ';
        }

        return array_filter(explode(" ", preg_replace($expr, $repl, $str)));
    }

    /**
     * Normalize the given string for fulltext search.
     * Currently only optimized for ISO-8859-1 and ISO-8859-2 characters; to be extended
     *
     * @param string  Input string (UTF-8)
     * @param boolean True to return list of words as array
     * @param integer Minimum length of tokens
     *
     * @return mixed Normalized string or a list of normalized tokens
     */
    public static function normalize_string($str, $as_array = false, $minlen = 2)
    {
        // replace 4-byte unicode characters with '?' character,
        // these are not supported in default utf-8 charset on mysql,
        // the chance we'd need them in searching is very low
        $str = preg_replace('/('
            . '\xF0[\x90-\xBF][\x80-\xBF]{2}'
            . '|[\xF1-\xF3][\x80-\xBF]{3}'
            . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'
            . ')/', '?', $str);

        // split by words
        $arr = self::tokenize_string($str, $minlen);

        // detect character set
        if (utf8_encode(utf8_decode($str)) == $str) {
            // ISO-8859-1 (or ASCII)
            preg_match_all('/./u', 'äâàåáãæçéêëèïîìíñöôòøõóüûùúýÿ', $keys);
            preg_match_all('/./',  'aaaaaaaceeeeiiiinoooooouuuuyy', $values);

            $mapping = array_combine($keys[0], $values[0]);
            $mapping = array_merge($mapping, array('ß' => 'ss', 'ae' => 'a', 'oe' => 'o', 'ue' => 'u'));
        }
        else if (rcube_charset::convert(rcube_charset::convert($str, 'UTF-8', 'ISO-8859-2'), 'ISO-8859-2', 'UTF-8') == $str) {
            // ISO-8859-2
            preg_match_all('/./u', 'ąáâäćçčéęëěíîłľĺńňóôöŕřśšşťţůúűüźžżý', $keys);
            preg_match_all('/./',  'aaaaccceeeeiilllnnooorrsssttuuuuzzzy', $values);

            $mapping = array_combine($keys[0], $values[0]);
            $mapping = array_merge($mapping, array('ß' => 'ss', 'ae' => 'a', 'oe' => 'o', 'ue' => 'u'));
        }

        foreach ($arr as $i => $part) {
            $part = mb_strtolower($part);

            if (!empty($mapping)) {
                $part = strtr($part, $mapping);
            }

            $arr[$i] = $part;
        }

        return $as_array ? $arr : join(" ", $arr);
    }

    /**
     * Compare two strings for matching words (order not relevant)
     *
     * @param string Haystack
     * @param string Needle
     *
     * @return boolean True if match, False otherwise
     */
    public static function words_match($haystack, $needle)
    {
        $a_needle  = self::tokenize_string($needle, 1);
        $_haystack = join(" ", self::tokenize_string($haystack, 1));
        $valid     = strlen($_haystack) > 0;
        $hits      = 0;

        foreach ($a_needle as $w) {
            if ($valid) {
                if (stripos($_haystack, $w) !== false) {
                    $hits++;
                }
            }
            else if (stripos($haystack, $w) !== false) {
                $hits++;
            }
        }

        return $hits >= count($a_needle);
    }

    /**
     * Parse commandline arguments into a hash array
     *
     * @param array $aliases Argument alias names
     *
     * @return array Argument values hash
     */
    public static function get_opt($aliases = array())
    {
        $args = array();
        $bool = array();

        // find boolean (no value) options
        foreach ($aliases as $key => $alias) {
            if ($pos = strpos($alias, ':')) {
                $aliases[$key] = substr($alias, 0, $pos);
                $bool[] = $key;
                $bool[] = $aliases[$key];
            }
        }

        for ($i=1; $i < count($_SERVER['argv']); $i++) {
            $arg   = $_SERVER['argv'][$i];
            $value = true;
            $key   = null;

            if ($arg[0] == '-') {
                $key = preg_replace('/^-+/', '', $arg);
                $sp  = strpos($arg, '=');

                if ($sp > 0) {
                    $key   = substr($key, 0, $sp - 2);
                    $value = substr($arg, $sp+1);
                }
                else if (in_array($key, $bool)) {
                    $value = true;
                }
                else if (strlen($_SERVER['argv'][$i+1]) && $_SERVER['argv'][$i+1][0] != '-') {
                    $value = $_SERVER['argv'][++$i];
                }

                $args[$key] = is_string($value) ? preg_replace(array('/^["\']/', '/["\']$/'), '', $value) : $value;
            }
            else {
                $args[] = $arg;
            }

            if ($alias = $aliases[$key]) {
                $args[$alias] = $args[$key];
            }
        }

        return $args;
    }

    /**
     * Safe password prompt for command line
     * from http://blogs.sitepoint.com/2009/05/01/interactive-cli-password-prompt-in-php/
     *
     * @return string Password
     */
    public static function prompt_silent($prompt = "Password:")
    {
        if (preg_match('/^win/i', PHP_OS)) {
            $vbscript  = sys_get_temp_dir() . 'prompt_password.vbs';
            $vbcontent = 'wscript.echo(InputBox("' . addslashes($prompt) . '", "", "password here"))';
            file_put_contents($vbscript, $vbcontent);

            $command  = "cscript //nologo " . escapeshellarg($vbscript);
            $password = rtrim(shell_exec($command));
            unlink($vbscript);

            return $password;
        }
        else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (rtrim(shell_exec($command)) !== 'OK') {
                echo $prompt;
                $pass = trim(fgets(STDIN));
                echo chr(8)."\r" . $prompt . str_repeat("*", strlen($pass))."\n";
                return $pass;
            }

            $command = "/usr/bin/env bash -c 'read -s -p \"" . addslashes($prompt) . "\" mypassword && echo \$mypassword'";
            $password = rtrim(shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    /**
     * Find out if the string content means true or false
     *
     * @param string $str Input value
     *
     * @return boolean Boolean value
     */
    public static function get_boolean($str)
    {
        $str = strtolower($str);

        return !in_array($str, array('false', '0', 'no', 'off', 'nein', ''), true);
    }

    /**
     * OS-dependent absolute path detection
     */
    public static function is_absolute_path($path)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
            return (bool) preg_match('!^[a-z]:[\\\\/]!i', $path);
        }
        else {
            return $path[0] == '/';
        }
    }

    /**
     * Resolve relative URL
     *
     * @param string $url Relative URL
     *
     * @return string Absolute URL
     */
    public static function resolve_url($url)
    {
        // prepend protocol://hostname:port
        if (!preg_match('|^https?://|', $url)) {
            $schema       = 'http';
            $default_port = 80;

            if (self::https_check()) {
                $schema       = 'https';
                $default_port = 443;
            }

            $prefix = $schema . '://' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
            if ($_SERVER['SERVER_PORT'] != $default_port) {
                $prefix .= ':' . $_SERVER['SERVER_PORT'];
            }

            $url = $prefix . ($url[0] == '/' ? '' : '/') . $url;
        }

        return $url;
    }

    /**
     * Generate a random string
     *
     * @param int  $length String length
     * @param bool $raw    Return RAW data instead of ascii
     *
     * @return string The generated random string
     */
    public static function random_bytes($length, $raw = false)
    {
        // Use PHP7 true random generator
        if (function_exists('random_bytes')) {
            // random_bytes() can throw an Error/TypeError/Exception in some cases
            try {
                $random = random_bytes($length);
            }
            catch (Throwable $e) {}
        }

        if (!$random) {
            $random = openssl_random_pseudo_bytes($length);
        }

        if ($raw) {
            return $random;
        }

        $random = self::bin2ascii($random);

        // truncate to the specified size...
        if ($length < strlen($random)) {
            $random = substr($random, 0, $length);
        }

        return $random;
    }

    /**
     * Convert binary data into readable form (containing a-zA-Z0-9 characters)
     *
     * @param string $input Binary input
     *
     * @return string Readable output
     */
    public static function bin2ascii($input)
    {
        // Above method returns "hexits".
        // Based on bin_to_readable() function in ext/session/session.c.
        // Note: removed ",-" characters from hextab
        $hextab = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $nbits  = 6; // can be 4, 5 or 6
        $length = strlen($input);
        $result = '';
        $char   = 0;
        $i      = 0;
        $have   = 0;
        $mask   = (1 << $nbits) - 1;

        while (true) {
            if ($have < $nbits) {
                if ($i < $length) {
                    $char |= ord($input[$i++]) << $have;
                    $have += 8;
                }
                else if (!$have) {
                    break;
                }
                else {
                    $have = $nbits;
                }
            }

            // consume nbits
            $result .= $hextab[$char & $mask];
            $char  >>= $nbits;
            $have   -= $nbits;
        }

        return $result;
    }

    /**
     * Format current date according to specified format.
     * This method supports microseconds (u).
     *
     * @param string $format Date format (default: 'd-M-Y H:i:s O')
     *
     * @return string Formatted date
     */
    public static function date_format($format = null)
    {
        if (empty($format)) {
            $format = 'd-M-Y H:i:s O';
        }

        if (strpos($format, 'u') !== false) {
            $dt  = number_format(microtime(true), 6, '.', '');
            $dt .=  '.' . date_default_timezone_get();

            if ($date = date_create_from_format('U.u.e', $dt)) {
                return $date->format($format);
            }
        }

        return date($format);
    }

    /**
     * Parses socket options and returns options for specified hostname.
     *
     * @param array  &$options Configured socket options
     * @param string $host     Hostname
     */
    public static function parse_socket_options(&$options, $host = null)
    {
        if (empty($host) || empty($options)) {
            return $options;
        }

        // get rid of schema and port from the hostname
        $host_url = parse_url($host);
        if (isset($host_url['host'])) {
            $host = $host_url['host'];
        }

        // find per-host options
        if (array_key_exists($host, $options)) {
            $options = $options[$host];
        }
    }

    /**
     * Get maximum upload size
     *
     * @return int Maximum size in bytes
     */
    public static function max_upload_size()
    {
        // find max filesize value
        $max_filesize = parse_bytes(ini_get('upload_max_filesize'));
        $max_postsize = parse_bytes(ini_get('post_max_size'));

        if ($max_postsize && $max_postsize < $max_filesize) {
            $max_filesize = $max_postsize;
        }

        return $max_filesize;
    }
}
