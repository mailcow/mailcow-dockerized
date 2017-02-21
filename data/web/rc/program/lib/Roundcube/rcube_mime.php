<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2016, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   MIME message parsing utilities                                      |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Class for parsing MIME messages
 *
 * @package    Framework
 * @subpackage Storage
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 */
class rcube_mime
{
    private static $default_charset;


    /**
     * Object constructor.
     */
    function __construct($default_charset = null)
    {
        self::$default_charset = $default_charset;
    }

    /**
     * Returns message/object character set name
     *
     * @return string Characted set name
     */
    public static function get_charset()
    {
        if (self::$default_charset) {
            return self::$default_charset;
        }

        if ($charset = rcube::get_instance()->config->get('default_charset')) {
            return $charset;
        }

        return RCUBE_CHARSET;
    }

    /**
     * Parse the given raw message source and return a structure
     * of rcube_message_part objects.
     *
     * It makes use of the rcube_mime_decode library
     *
     * @param string $raw_body The message source
     *
     * @return object rcube_message_part The message structure
     */
    public static function parse_message($raw_body)
    {
        $conf = array(
            'include_bodies'  => true,
            'decode_bodies'   => true,
            'decode_headers'  => false,
            'default_charset' => self::get_charset(),
        );

        $mime = new rcube_mime_decode($conf);

        return $mime->decode($raw_body);
    }

    /**
     * Split an address list into a structured array list
     *
     * @param string  $input    Input string
     * @param int     $max      List only this number of addresses
     * @param boolean $decode   Decode address strings
     * @param string  $fallback Fallback charset if none specified
     * @param boolean $addronly Return flat array with e-mail addresses only
     *
     * @return array Indexed list of addresses
     */
    static function decode_address_list($input, $max = null, $decode = true, $fallback = null, $addronly = false)
    {
        $a   = self::parse_address_list($input, $decode, $fallback);
        $out = array();
        $j   = 0;

        // Special chars as defined by RFC 822 need to in quoted string (or escaped).
        $special_chars = '[\(\)\<\>\\\.\[\]@,;:"]';

        if (!is_array($a)) {
            return $out;
        }

        foreach ($a as $val) {
            $j++;
            $address = trim($val['address']);

            if ($addronly) {
                $out[$j] = $address;
            }
            else {
                $name = trim($val['name']);
                if ($name && $address && $name != $address)
                    $string = sprintf('%s <%s>', preg_match("/$special_chars/", $name) ? '"'.addcslashes($name, '"').'"' : $name, $address);
                else if ($address)
                    $string = $address;
                else if ($name)
                    $string = $name;

                $out[$j] = array('name' => $name, 'mailto' => $address, 'string' => $string);
            }

            if ($max && $j==$max)
                break;
        }

        return $out;
    }

    /**
     * Decode a message header value
     *
     * @param string  $input    Header value
     * @param string  $fallback Fallback charset if none specified
     *
     * @return string Decoded string
     */
    public static function decode_header($input, $fallback = null)
    {
        $str = self::decode_mime_string((string)$input, $fallback);

        return $str;
    }

    /**
     * Decode a mime-encoded string to internal charset
     *
     * @param string $input    Header value
     * @param string $fallback Fallback charset if none specified
     *
     * @return string Decoded string
     */
    public static function decode_mime_string($input, $fallback = null)
    {
        $default_charset = $fallback ?: self::get_charset();

        // rfc: all line breaks or other characters not found
        // in the Base64 Alphabet must be ignored by decoding software
        // delete all blanks between MIME-lines, differently we can
        // receive unnecessary blanks and broken utf-8 symbols
        $input = preg_replace("/\?=\s+=\?/", '?==?', $input);

        // encoded-word regexp
        $re = '/=\?([^?]+)\?([BbQq])\?([^\n]*?)\?=/';

        // Find all RFC2047's encoded words
        if (preg_match_all($re, $input, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            // Initialize variables
            $tmp   = array();
            $out   = '';
            $start = 0;

            foreach ($matches as $idx => $m) {
                $pos      = $m[0][1];
                $charset  = $m[1][0];
                $encoding = $m[2][0];
                $text     = $m[3][0];
                $length   = strlen($m[0][0]);

                // Append everything that is before the text to be decoded
                if ($start != $pos) {
                    $substr = substr($input, $start, $pos-$start);
                    $out   .= rcube_charset::convert($substr, $default_charset);
                    $start  = $pos;
                }
                $start += $length;

                // Per RFC2047, each string part "MUST represent an integral number
                // of characters . A multi-octet character may not be split across
                // adjacent encoded-words." However, some mailers break this, so we
                // try to handle characters spanned across parts anyway by iterating
                // through and aggregating sequential encoded parts with the same
                // character set and encoding, then perform the decoding on the
                // aggregation as a whole.

                $tmp[] = $text;
                if ($next_match = $matches[$idx+1]) {
                    if ($next_match[0][1] == $start
                        && $next_match[1][0] == $charset
                        && $next_match[2][0] == $encoding
                    ) {
                        continue;
                    }
                }

                $count = count($tmp);
                $text  = '';

                // Decode and join encoded-word's chunks
                if ($encoding == 'B' || $encoding == 'b') {
                    // base64 must be decoded a segment at a time
                    for ($i=0; $i<$count; $i++)
                        $text .= base64_decode($tmp[$i]);
                }
                else { //if ($encoding == 'Q' || $encoding == 'q') {
                    // quoted printable can be combined and processed at once
                    for ($i=0; $i<$count; $i++)
                        $text .= $tmp[$i];

                    $text = str_replace('_', ' ', $text);
                    $text = quoted_printable_decode($text);
                }

                $out .= rcube_charset::convert($text, $charset);
                $tmp = array();
            }

            // add the last part of the input string
            if ($start != strlen($input)) {
                $out .= rcube_charset::convert(substr($input, $start), $default_charset);
            }

            // return the results
            return $out;
        }

        // no encoding information, use fallback
        return rcube_charset::convert($input, $default_charset);
    }

    /**
     * Decode a mime part
     *
     * @param string $input    Input string
     * @param string $encoding Part encoding
     *
     * @return string Decoded string
     */
    public static function decode($input, $encoding = '7bit')
    {
        switch (strtolower($encoding)) {
        case 'quoted-printable':
            return quoted_printable_decode($input);
        case 'base64':
            return base64_decode($input);
        case 'x-uuencode':
        case 'x-uue':
        case 'uue':
        case 'uuencode':
            return convert_uudecode($input);
        case '7bit':
        default:
            return $input;
        }
    }

    /**
     * Split RFC822 header string into an associative array
     */
    public static function parse_headers($headers)
    {
        $a_headers = array();
        $headers   = preg_replace('/\r?\n(\t| )+/', ' ', $headers);
        $lines     = explode("\n", $headers);
        $count     = count($lines);

        for ($i=0; $i<$count; $i++) {
            if ($p = strpos($lines[$i], ': ')) {
                $field = strtolower(substr($lines[$i], 0, $p));
                $value = trim(substr($lines[$i], $p+1));
                if (!empty($value)) {
                    $a_headers[$field] = $value;
                }
            }
        }

        return $a_headers;
    }

    /**
     * E-mail address list parser
     */
    private static function parse_address_list($str, $decode = true, $fallback = null)
    {
        // remove any newlines and carriage returns before
        $str = preg_replace('/\r?\n(\s|\t)?/', ' ', $str);

        // extract list items, remove comments
        $str = self::explode_header_string(',;', $str, true);
        $result = array();

        // simplified regexp, supporting quoted local part
        $email_rx = '(\S+|("\s*(?:[^"\f\n\r\t\v\b\s]+\s*)+"))@\S+';

        foreach ($str as $key => $val) {
            $name    = '';
            $address = '';
            $val     = trim($val);

            if (preg_match('/(.*)<('.$email_rx.')>$/', $val, $m)) {
                $address = $m[2];
                $name    = trim($m[1]);
            }
            else if (preg_match('/^('.$email_rx.')$/', $val, $m)) {
                $address = $m[1];
                $name    = '';
            }
            // special case (#1489092)
            else if (preg_match('/(\s*<MAILER-DAEMON>)$/', $val, $m)) {
                $address = 'MAILER-DAEMON';
                $name    = substr($val, 0, -strlen($m[1]));
            }
            else if (preg_match('/('.$email_rx.')/', $val, $m)) {
                $name = $m[1];
            }
            else {
                $name = $val;
            }

            // dequote and/or decode name
            if ($name) {
                if ($name[0] == '"' && $name[strlen($name)-1] == '"') {
                    $name = substr($name, 1, -1);
                    $name = stripslashes($name);
                }
                if ($decode) {
                    $name = self::decode_header($name, $fallback);
                    // some clients encode addressee name with quotes around it
                    if ($name[0] == '"' && $name[strlen($name)-1] == '"') {
                        $name = substr($name, 1, -1);
                    }
                }
            }

            if (!$address && $name) {
                $address = $name;
                $name    = '';
            }

            if ($address) {
                $address      = self::fix_email($address);
                $result[$key] = array('name' => $name, 'address' => $address);
            }
        }

        return $result;
    }

    /**
     * Explodes header (e.g. address-list) string into array of strings
     * using specified separator characters with proper handling
     * of quoted-strings and comments (RFC2822)
     *
     * @param string $separator       String containing separator characters
     * @param string $str             Header string
     * @param bool   $remove_comments Enable to remove comments
     *
     * @return array Header items
     */
    public static function explode_header_string($separator, $str, $remove_comments = false)
    {
        $length  = strlen($str);
        $result  = array();
        $quoted  = false;
        $comment = 0;
        $out     = '';

        for ($i=0; $i<$length; $i++) {
            // we're inside a quoted string
            if ($quoted) {
                if ($str[$i] == '"') {
                    $quoted = false;
                }
                else if ($str[$i] == "\\") {
                    if ($comment <= 0) {
                        $out .= "\\";
                    }
                    $i++;
                }
            }
            // we are inside a comment string
            else if ($comment > 0) {
                if ($str[$i] == ')') {
                    $comment--;
                }
                else if ($str[$i] == '(') {
                    $comment++;
                }
                else if ($str[$i] == "\\") {
                    $i++;
                }
                continue;
            }
            // separator, add to result array
            else if (strpos($separator, $str[$i]) !== false) {
                if ($out) {
                    $result[] = $out;
                }
                $out = '';
                continue;
            }
            // start of quoted string
            else if ($str[$i] == '"') {
                $quoted = true;
            }
            // start of comment
            else if ($remove_comments && $str[$i] == '(') {
                $comment++;
            }

            if ($comment <= 0) {
                $out .= $str[$i];
            }
        }

        if ($out && $comment <= 0) {
            $result[] = $out;
        }

        return $result;
    }

    /**
     * Interpret a format=flowed message body according to RFC 2646
     *
     * @param string $text Raw body formatted as flowed text
     * @param string $mark Mark each flowed line with specified character
     *
     * @return string Interpreted text with unwrapped lines and stuffed space removed
     */
    public static function unfold_flowed($text, $mark = null)
    {
        $text    = preg_split('/\r?\n/', $text);
        $last    = -1;
        $q_level = 0;
        $marks   = array();

        foreach ($text as $idx => $line) {
            if ($q = strspn($line, '>')) {
                // remove quote chars
                $line = substr($line, $q);
                // remove (optional) space-staffing
                if ($line[0] === ' ') $line = substr($line, 1);

                // The same paragraph (We join current line with the previous one) when:
                // - the same level of quoting
                // - previous line was flowed
                // - previous line contains more than only one single space (and quote char(s))
                if ($q == $q_level
                    && isset($text[$last]) && $text[$last][strlen($text[$last])-1] == ' '
                    && !preg_match('/^>+ {0,1}$/', $text[$last])
                ) {
                    $text[$last] .= $line;
                    unset($text[$idx]);

                    if ($mark) {
                        $marks[$last] = true;
                    }
                }
                else {
                    $last = $idx;
                }
            }
            else {
                if ($line == '-- ') {
                    $last = $idx;
                }
                else {
                    // remove space-stuffing
                    if ($line[0] === ' ') $line = substr($line, 1);

                    if (isset($text[$last]) && $line && !$q_level
                        && $text[$last] != '-- '
                        && $text[$last][strlen($text[$last])-1] == ' '
                    ) {
                        $text[$last] .= $line;
                        unset($text[$idx]);

                        if ($mark) {
                            $marks[$last] = true;
                        }
                    }
                    else {
                        $text[$idx] = $line;
                        $last = $idx;
                    }
                }
            }
            $q_level = $q;
        }

        if (!empty($marks)) {
            foreach (array_keys($marks) as $mk) {
                $text[$mk] = $mark . $text[$mk];
            }
        }

        return implode("\r\n", $text);
    }

    /**
     * Wrap the given text to comply with RFC 2646
     *
     * @param string $text    Text to wrap
     * @param int    $length  Length
     * @param string $charset Character encoding of $text
     *
     * @return string Wrapped text
     */
    public static function format_flowed($text, $length = 72, $charset=null)
    {
        $text = preg_split('/\r?\n/', $text);

        foreach ($text as $idx => $line) {
            if ($line != '-- ') {
                if ($level = strspn($line, '>')) {
                    // remove quote chars
                    $line = substr($line, $level);
                    // remove (optional) space-staffing and spaces before the line end
                    $line = rtrim($line, ' ');
                    if ($line[0] === ' ') $line = substr($line, 1);

                    $prefix = str_repeat('>', $level) . ' ';
                    $line   = $prefix . self::wordwrap($line, $length - $level - 2, " \r\n$prefix", false, $charset);
                }
                else if ($line) {
                    $line = self::wordwrap(rtrim($line), $length - 2, " \r\n", false, $charset);
                    // space-stuffing
                    $line = preg_replace('/(^|\r\n)(From| |>)/', '\\1 \\2', $line);
                }

                $text[$idx] = $line;
            }
        }

        return implode("\r\n", $text);
    }

    /**
     * Improved wordwrap function with multibyte support.
     * The code is based on Zend_Text_MultiByte::wordWrap().
     *
     * @param string $string      Text to wrap
     * @param int    $width       Line width
     * @param string $break       Line separator
     * @param bool   $cut         Enable to cut word
     * @param string $charset     Charset of $string
     * @param bool   $wrap_quoted When enabled quoted lines will not be wrapped
     *
     * @return string Text
     */
    public static function wordwrap($string, $width=75, $break="\n", $cut=false, $charset=null, $wrap_quoted=true)
    {
        // Note: Never try to use iconv instead of mbstring functions here
        //       Iconv's substr/strlen are 100x slower (#1489113)

        if ($charset && $charset != RCUBE_CHARSET) {
            mb_internal_encoding($charset);
        }

        // Convert \r\n to \n, this is our line-separator
        $string       = str_replace("\r\n", "\n", $string);
        $separator    = "\n"; // must be 1 character length
        $result       = array();

        while (($stringLength = mb_strlen($string)) > 0) {
            $breakPos = mb_strpos($string, $separator, 0);

            // quoted line (do not wrap)
            if ($wrap_quoted && $string[0] == '>') {
                if ($breakPos === $stringLength - 1 || $breakPos === false) {
                    $subString = $string;
                    $cutLength = null;
                }
                else {
                    $subString = mb_substr($string, 0, $breakPos);
                    $cutLength = $breakPos + 1;
                }
            }
            // next line found and current line is shorter than the limit
            else if ($breakPos !== false && $breakPos < $width) {
                if ($breakPos === $stringLength - 1) {
                    $subString = $string;
                    $cutLength = null;
                }
                else {
                    $subString = mb_substr($string, 0, $breakPos);
                    $cutLength = $breakPos + 1;
                }
            }
            else {
                $subString = mb_substr($string, 0, $width);

                // last line
                if ($breakPos === false && $subString === $string) {
                    $cutLength = null;
                }
                else {
                    $nextChar = mb_substr($string, $width, 1);

                    if ($nextChar === ' ' || $nextChar === $separator) {
                        $afterNextChar = mb_substr($string, $width + 1, 1);

                        // Note: mb_substr() does never return False
                        if ($afterNextChar === false || $afterNextChar === '') {
                            $subString .= $nextChar;
                        }

                        $cutLength = mb_strlen($subString) + 1;
                    }
                    else {
                        $spacePos = mb_strrpos($subString, ' ', 0);

                        if ($spacePos !== false) {
                            $subString = mb_substr($subString, 0, $spacePos);
                            $cutLength = $spacePos + 1;
                        }
                        else if ($cut === false) {
                            $spacePos = mb_strpos($string, ' ', 0);

                            if ($spacePos !== false && ($breakPos === false || $spacePos < $breakPos)) {
                                $subString = mb_substr($string, 0, $spacePos);
                                $cutLength = $spacePos + 1;
                            }
                            else if ($breakPos === false) {
                                $subString = $string;
                                $cutLength = null;
                            }
                            else {
                                $subString = mb_substr($string, 0, $breakPos);
                                $cutLength = $breakPos + 1;
                            }
                        }
                        else {
                            $cutLength = $width;
                        }
                    }
                }
            }

            $result[] = $subString;

            if ($cutLength !== null) {
                $string = mb_substr($string, $cutLength, ($stringLength - $cutLength));
            }
            else {
                break;
            }
        }

        if ($charset && $charset != RCUBE_CHARSET) {
            mb_internal_encoding(RCUBE_CHARSET);
        }

        return implode($break, $result);
    }

    /**
     * A method to guess the mime_type of an attachment.
     *
     * @param string  $path        Path to the file or file contents
     * @param string  $name        File name (with suffix)
     * @param string  $failover    Mime type supplied for failover
     * @param boolean $is_stream   Set to True if $path contains file contents
     * @param boolean $skip_suffix Set to True if the config/mimetypes.php mappig should be ignored
     *
     * @return string
     * @author Till Klampaeckel <till@php.net>
     * @see    http://de2.php.net/manual/en/ref.fileinfo.php
     * @see    http://de2.php.net/mime_content_type
     */
    public static function file_content_type($path, $name, $failover = 'application/octet-stream', $is_stream = false, $skip_suffix = false)
    {
        static $mime_ext = array();

        $mime_type  = null;
        $config     = rcube::get_instance()->config;
        $mime_magic = $config->get('mime_magic');

        if (!$skip_suffix && empty($mime_ext)) {
            foreach ($config->resolve_paths('mimetypes.php') as $fpath) {
                $mime_ext = array_merge($mime_ext, (array) @include($fpath));
            }
        }

        // use file name suffix with hard-coded mime-type map
        if (!$skip_suffix && is_array($mime_ext) && $name) {
            if ($suffix = substr($name, strrpos($name, '.')+1)) {
                $mime_type = $mime_ext[strtolower($suffix)];
            }
        }

        // try fileinfo extension if available
        if (!$mime_type && function_exists('finfo_open')) {
            // null as a 2nd argument should be the same as no argument
            // this however is not true on all systems/versions
            if ($mime_magic) {
                $finfo = finfo_open(FILEINFO_MIME, $mime_magic);
            }
            else {
                $finfo = finfo_open(FILEINFO_MIME);
            }

            if ($finfo) {
                if ($is_stream)
                    $mime_type = finfo_buffer($finfo, $path);
                else
                    $mime_type = finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }

        // try PHP's mime_content_type
        if (!$mime_type && !$is_stream && function_exists('mime_content_type')) {
            $mime_type = @mime_content_type($path);
        }

        // fall back to user-submitted string
        if (!$mime_type) {
            $mime_type = $failover;
        }
        else {
            // Sometimes (PHP-5.3?) content-type contains charset definition,
            // Remove it (#1487122) also "charset=binary" is useless
            $mime_type = array_shift(preg_split('/[; ]/', $mime_type));
        }

        return $mime_type;
    }

    /**
     * Get mimetype => file extension mapping
     *
     * @param string Mime-Type to get extensions for
     *
     * @return array List of extensions matching the given mimetype or a hash array
     *               with ext -> mimetype mappings if $mimetype is not given
     */
    public static function get_mime_extensions($mimetype = null)
    {
        static $mime_types, $mime_extensions;

        // return cached data
        if (is_array($mime_types)) {
            return $mimetype ? $mime_types[$mimetype] : $mime_extensions;
        }

        // load mapping file
        $file_paths = array();

        if ($mime_types = rcube::get_instance()->config->get('mime_types')) {
            $file_paths[] = $mime_types;
        }

        // try common locations
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
            $file_paths[] = 'C:/xampp/apache/conf/mime.types.';
        }
        else {
            $file_paths[] = '/etc/mime.types';
            $file_paths[] = '/etc/httpd/mime.types';
            $file_paths[] = '/etc/httpd2/mime.types';
            $file_paths[] = '/etc/apache/mime.types';
            $file_paths[] = '/etc/apache2/mime.types';
            $file_paths[] = '/etc/nginx/mime.types';
            $file_paths[] = '/usr/local/etc/httpd/conf/mime.types';
            $file_paths[] = '/usr/local/etc/apache/conf/mime.types';
            $file_paths[] = '/usr/local/etc/apache24/mime.types';
        }

        foreach ($file_paths as $fp) {
            if (@is_readable($fp)) {
                $lines = file($fp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                break;
            }
        }

        $mime_types = $mime_extensions = array();
        $regex = "/([\w\+\-\.\/]+)\s+([\w\s]+)/i";
        foreach((array)$lines as $line) {
             // skip comments or mime types w/o any extensions
            if ($line[0] == '#' || !preg_match($regex, $line, $matches))
                continue;

            $mime = $matches[1];
            foreach (explode(' ', $matches[2]) as $ext) {
                $ext = trim($ext);
                $mime_types[$mime][] = $ext;
                $mime_extensions[$ext] = $mime;
            }
        }

        // fallback to some well-known types most important for daily emails
        if (empty($mime_types)) {
            foreach (rcube::get_instance()->config->resolve_paths('mimetypes.php') as $fpath) {
                $mime_extensions = array_merge($mime_extensions, (array) @include($fpath));
            }

            foreach ($mime_extensions as $ext => $mime) {
                $mime_types[$mime][] = $ext;
            }
        }

        // Add some known aliases that aren't included by some mime.types (#1488891)
        // the order is important here so standard extensions have higher prio
        $aliases = array(
            'image/gif'      => array('gif'),
            'image/png'      => array('png'),
            'image/x-png'    => array('png'),
            'image/jpeg'     => array('jpg', 'jpeg', 'jpe'),
            'image/jpg'      => array('jpg', 'jpeg', 'jpe'),
            'image/pjpeg'    => array('jpg', 'jpeg', 'jpe'),
            'image/tiff'     => array('tif'),
            'message/rfc822' => array('eml'),
            'text/x-mail'    => array('eml'),
        );

        foreach ($aliases as $mime => $exts) {
            $mime_types[$mime] = array_unique(array_merge((array) $mime_types[$mime], $exts));

            foreach ($exts as $ext) {
                if (!isset($mime_extensions[$ext])) {
                    $mime_extensions[$ext] = $mime;
                }
            }
        }

        return $mimetype ? $mime_types[$mimetype] : $mime_extensions;
    }

    /**
     * Detect image type of the given binary data by checking magic numbers.
     *
     * @param string $data  Binary file content
     *
     * @return string Detected mime-type or jpeg as fallback
     */
    public static function image_content_type($data)
    {
        $type = 'jpeg';
        if      (preg_match('/^\x89\x50\x4E\x47/', $data)) $type = 'png';
        else if (preg_match('/^\x47\x49\x46\x38/', $data)) $type = 'gif';
        else if (preg_match('/^\x00\x00\x01\x00/', $data)) $type = 'ico';
    //  else if (preg_match('/^\xFF\xD8\xFF\xE0/', $data)) $type = 'jpeg';

        return 'image/' . $type;
    }

    /**
     * Try to fix invalid email addresses
     */
    public static function fix_email($email)
    {
        $parts = rcube_utils::explode_quoted_string('@', $email);
        foreach ($parts as $idx => $part) {
            // remove redundant quoting (#1490040)
            if ($part[0] == '"' && preg_match('/^"([a-zA-Z0-9._+=-]+)"$/', $part, $m)) {
                $parts[$idx] = $m[1];
            }
        }

        return implode('@', $parts);
    }
}
