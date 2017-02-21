<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 | Copyright (C) 2000 Edmund Grimley Evans <edmundo@rano.org>            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide charset conversion functionality                            |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Character sets conversion functionality
 *
 * @package    Framework
 * @subpackage Core
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 * @author     Edmund Grimley Evans <edmundo@rano.org>
 */
class rcube_charset
{
    // Aliases: some of them from HTML5 spec.
    static public $aliases = array(
        'USASCII'       => 'WINDOWS-1252',
        'ANSIX31101983' => 'WINDOWS-1252',
        'ANSIX341968'   => 'WINDOWS-1252',
        'UNKNOWN8BIT'   => 'ISO-8859-15',
        'UNKNOWN'       => 'ISO-8859-15',
        'USERDEFINED'   => 'ISO-8859-15',
        'KSC56011987'   => 'EUC-KR',
        'GB2312'        => 'GBK',
        'GB231280'      => 'GBK',
        'UNICODE'       => 'UTF-8',
        'UTF7IMAP'      => 'UTF7-IMAP',
        'TIS620'        => 'WINDOWS-874',
        'ISO88599'      => 'WINDOWS-1254',
        'ISO885911'     => 'WINDOWS-874',
        'MACROMAN'      => 'MACINTOSH',
        '77'            => 'MAC',
        '128'           => 'SHIFT-JIS',
        '129'           => 'CP949',
        '130'           => 'CP1361',
        '134'           => 'GBK',
        '136'           => 'BIG5',
        '161'           => 'WINDOWS-1253',
        '162'           => 'WINDOWS-1254',
        '163'           => 'WINDOWS-1258',
        '177'           => 'WINDOWS-1255',
        '178'           => 'WINDOWS-1256',
        '186'           => 'WINDOWS-1257',
        '204'           => 'WINDOWS-1251',
        '222'           => 'WINDOWS-874',
        '238'           => 'WINDOWS-1250',
        'MS950'         => 'CP950',
        'WINDOWS949'    => 'UHC',
    );


    /**
     * Catch an error and throw an exception.
     *
     * @param int    $errno  Level of the error
     * @param string $errstr Error message
     */
    public static function error_handler($errno, $errstr)
    {
        throw new ErrorException($errstr, 0, $errno);
    }

    /**
     * Parse and validate charset name string (see #1485758).
     * Sometimes charset string is malformed, there are also charset aliases 
     * but we need strict names for charset conversion (specially utf8 class)
     *
     * @param string $input Input charset name
     *
     * @return string The validated charset name
     */
    public static function parse_charset($input)
    {
        static $charsets = array();
        $charset = strtoupper($input);

        if (isset($charsets[$input])) {
            return $charsets[$input];
        }

        $charset = preg_replace(array(
            '/^[^0-9A-Z]+/',    // e.g. _ISO-8859-JP$SIO
            '/\$.*$/',          // e.g. _ISO-8859-JP$SIO
            '/UNICODE-1-1-*/',  // RFC1641/1642
            '/^X-/',            // X- prefix (e.g. X-ROMAN8 => ROMAN8)
        ), '', $charset);

        if ($charset == 'BINARY') {
            return $charsets[$input] = null;
        }

        // allow A-Z and 0-9 only
        $str = preg_replace('/[^A-Z0-9]/', '', $charset);

        if (isset(self::$aliases[$str])) {
            $result = self::$aliases[$str];
        }
        // UTF
        else if (preg_match('/U[A-Z][A-Z](7|8|16|32)(BE|LE)*/', $str, $m)) {
            $result = 'UTF-' . $m[1] . $m[2];
        }
        // ISO-8859
        else if (preg_match('/ISO8859([0-9]{0,2})/', $str, $m)) {
            $iso = 'ISO-8859-' . ($m[1] ?: 1);
            // some clients sends windows-1252 text as latin1,
            // it is safe to use windows-1252 for all latin1
            $result = $iso == 'ISO-8859-1' ? 'WINDOWS-1252' : $iso;
        }
        // handle broken charset names e.g. WINDOWS-1250HTTP-EQUIVCONTENT-TYPE
        else if (preg_match('/(WIN|WINDOWS)([0-9]+)/', $str, $m)) {
            $result = 'WINDOWS-' . $m[2];
        }
        // LATIN
        else if (preg_match('/LATIN(.*)/', $str, $m)) {
            $aliases = array('2' => 2, '3' => 3, '4' => 4, '5' => 9, '6' => 10,
                '7' => 13, '8' => 14, '9' => 15, '10' => 16,
                'ARABIC' => 6, 'CYRILLIC' => 5, 'GREEK' => 7, 'GREEK1' => 7, 'HEBREW' => 8
            );

            // some clients sends windows-1252 text as latin1,
            // it is safe to use windows-1252 for all latin1
            if ($m[1] == 1) {
                $result = 'WINDOWS-1252';
            }
            // if iconv is not supported we need ISO labels, it's also safe for iconv
            else if (!empty($aliases[$m[1]])) {
                $result = 'ISO-8859-'.$aliases[$m[1]];
            }
            // iconv requires convertion of e.g. LATIN-1 to LATIN1
            else {
                $result = $str;
            }
        }
        else {
            $result = $charset;
        }

        $charsets[$input] = $result;

        return $result;
    }

    /**
     * Convert a string from one charset to another.
     * Uses mbstring and iconv functions if possible
     *
     * @param string $str  Input string
     * @param string $from Suspected charset of the input string
     * @param string $to   Target charset to convert to; defaults to RCUBE_CHARSET
     *
     * @return string Converted string
     */
    public static function convert($str, $from, $to = null)
    {
        static $iconv_options = null;
        static $mbstring_sc   = null;

        $to   = empty($to) ? RCUBE_CHARSET : strtoupper($to);
        $from = self::parse_charset($from);

        // It is a common case when UTF-16 charset is used with US-ASCII content (#1488654)
        // In that case we can just skip the conversion (use UTF-8)
        if ($from == 'UTF-16' && !preg_match('/[^\x00-\x7F]/', $str)) {
            $from = 'UTF-8';
        }

        if ($from == $to || empty($str) || empty($from)) {
            return $str;
        }

        if ($iconv_options === null) {
            if (function_exists('iconv')) {
                // ignore characters not available in output charset
                $iconv_options = '//IGNORE';
                if (iconv('', $iconv_options, '') === false) {
                    // iconv implementation does not support options
                    $iconv_options = '';
                }
            }
            else {
                $iconv_options = false;
            }
        }

        // convert charset using iconv module
        if ($iconv_options !== false && $from != 'UTF7-IMAP' && $to != 'UTF7-IMAP') {
            // throw an exception if iconv reports an illegal character in input
            // it means that input string has been truncated
            set_error_handler(array('rcube_charset', 'error_handler'), E_NOTICE);
            try {
                $out = iconv($from, $to . $iconv_options, $str);
            }
            catch (ErrorException $e) {
                $out = false;
            }
            restore_error_handler();

            if ($out !== false) {
                return $out;
            }
        }

        if ($mbstring_sc === null) {
            $mbstring_sc = extension_loaded('mbstring') ? mb_substitute_character() : false;
        }

        // convert charset using mbstring module
        if ($mbstring_sc !== false) {
            $aliases = array(
                'WINDOWS-1257' => 'ISO-8859-13',
                'US-ASCII'     => 'ASCII',
            );

            $mb_from = $aliases[$from] ?: $from;
            $mb_to   = $aliases[$to] ?: $to;

            // Do the same as //IGNORE with iconv
            mb_substitute_character('none');

            // throw an exception if mbstring reports an illegal character in input
            // using mb_check_encoding() is much slower
            set_error_handler(array('rcube_charset', 'error_handler'), E_WARNING);
            try {
                $out = mb_convert_encoding($str, $mb_to, $mb_from);
            }
            catch (ErrorException $e) {
                $out = false;
            }
            restore_error_handler();

            mb_substitute_character($mbstring_sc);

            if ($out !== false) {
                return $out;
            }
        }

        // convert charset using bundled classes/functions
        if ($to == 'UTF-8') {
            if ($from == 'UTF7-IMAP') {
                if ($out = self::utf7imap_to_utf8($str)) {
                    return $out;
                }
            }
            else if ($from == 'UTF-7') {
                if ($out = self::utf7_to_utf8($str)) {
                    return $out;
                }
            }
        }

        // encode string for output
        if ($from == 'UTF-8') {
            // @TODO: we need a function for UTF-7 (RFC2152) conversion
            if ($to == 'UTF7-IMAP' || $to == 'UTF-7') {
                if ($out = self::utf8_to_utf7imap($str)) {
                    return $out;
                }
            }
        }

        if (!isset($out)) {
            trigger_error("No suitable function found for '$from' to '$to' conversion");
        }

        // return original string
        return $str;
    }

    /**
     * Converts string from standard UTF-7 (RFC 2152) to UTF-8.
     *
     * @param string $str Input string (UTF-7)
     *
     * @return string Converted string (UTF-8)
     */
    public static function utf7_to_utf8($str)
    {
        $Index_64 = array(
            0,0,0,0, 0,0,0,0, 0,0,0,0, 0,0,0,0,
            0,0,0,0, 0,0,0,0, 0,0,0,0, 0,0,0,0,
            0,0,0,0, 0,0,0,0, 0,0,0,1, 0,0,0,0,
            1,1,1,1, 1,1,1,1, 1,1,0,0, 0,0,0,0,
            0,1,1,1, 1,1,1,1, 1,1,1,1, 1,1,1,1,
            1,1,1,1, 1,1,1,1, 1,1,1,0, 0,0,0,0,
            0,1,1,1, 1,1,1,1, 1,1,1,1, 1,1,1,1,
            1,1,1,1, 1,1,1,1, 1,1,1,0, 0,0,0,0,
        );

        $u7len = strlen($str);
        $str   = strval($str);
        $res   = '';

        for ($i=0; $u7len > 0; $i++, $u7len--) {
            $u7 = $str[$i];
            if ($u7 == '+') {
                $i++;
                $u7len--;
                $ch = '';

                for (; $u7len > 0; $i++, $u7len--) {
                    $u7 = $str[$i];

                    if (!$Index_64[ord($u7)]) {
                        break;
                    }

                    $ch .= $u7;
                }

                if ($ch == '') {
                    if ($u7 == '-') {
                        $res .= '+';
                    }

                    continue;
                }

                $res .= self::utf16_to_utf8(base64_decode($ch));
            }
            else {
                $res .= $u7;
            }
        }

        return $res;
    }

    /**
     * Converts string from UTF-16 to UTF-8 (helper for utf-7 to utf-8 conversion)
     *
     * @param string $str Input string
     *
     * @return string The converted string
     */
    public static function utf16_to_utf8($str)
    {
        $len = strlen($str);
        $dec = '';

        for ($i = 0; $i < $len; $i += 2) {
            $c = ord($str[$i]) << 8 | ord($str[$i + 1]);
            if ($c >= 0x0001 && $c <= 0x007F) {
                $dec .= chr($c);
            }
            else if ($c > 0x07FF) {
                $dec .= chr(0xE0 | (($c >> 12) & 0x0F));
                $dec .= chr(0x80 | (($c >>  6) & 0x3F));
                $dec .= chr(0x80 | (($c >>  0) & 0x3F));
            }
            else {
                $dec .= chr(0xC0 | (($c >>  6) & 0x1F));
                $dec .= chr(0x80 | (($c >>  0) & 0x3F));
            }
        }

        return $dec;
    }

    /**
     * Convert the data ($str) from RFC 2060's UTF-7 to UTF-8.
     * If input data is invalid, return the original input string.
     * RFC 2060 obviously intends the encoding to be unique (see
     * point 5 in section 5.1.3), so we reject any non-canonical
     * form, such as &ACY- (instead of &-) or &AMA-&AMA- (instead
     * of &AMAAwA-).
     *
     * Translated from C to PHP by Thomas Bruederli <roundcube@gmail.com>
     *
     * @param string $str Input string (UTF7-IMAP)
     *
     * @return string Output string (UTF-8)
     */
    public static function utf7imap_to_utf8($str)
    {
        $Index_64 = array(
            -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1,
            -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,-1,
            -1,-1,-1,-1, -1,-1,-1,-1, -1,-1,-1,62, 63,-1,-1,-1,
            52,53,54,55, 56,57,58,59, 60,61,-1,-1, -1,-1,-1,-1,
            -1, 0, 1, 2,  3, 4, 5, 6,  7, 8, 9,10, 11,12,13,14,
            15,16,17,18, 19,20,21,22, 23,24,25,-1, -1,-1,-1,-1,
            -1,26,27,28, 29,30,31,32, 33,34,35,36, 37,38,39,40,
            41,42,43,44, 45,46,47,48, 49,50,51,-1, -1,-1,-1,-1
        );

        $u7len = strlen($str);
        $str   = strval($str);
        $p     = '';
        $err   = '';

        for ($i=0; $u7len > 0; $i++, $u7len--) {
            $u7 = $str[$i];
            if ($u7 == '&') {
                $i++;
                $u7len--;
                $u7 = $str[$i];

                if ($u7len && $u7 == '-') {
                    $p .= '&';
                    continue;
                }

                $ch = 0;
                $k = 10;
                for (; $u7len > 0; $i++, $u7len--) {
                    $u7 = $str[$i];

                    if ((ord($u7) & 0x80) || ($b = $Index_64[ord($u7)]) == -1) {
                        break;
                    }

                    if ($k > 0) {
                        $ch |= $b << $k;
                        $k -= 6;
                    }
                    else {
                        $ch |= $b >> (-$k);
                        if ($ch < 0x80) {
                            // Printable US-ASCII
                            if (0x20 <= $ch && $ch < 0x7f) {
                                return $err;
                            }
                            $p .= chr($ch);
                        }
                        else if ($ch < 0x800) {
                            $p .= chr(0xc0 | ($ch >> 6));
                            $p .= chr(0x80 | ($ch & 0x3f));
                        }
                        else {
                            $p .= chr(0xe0 | ($ch >> 12));
                            $p .= chr(0x80 | (($ch >> 6) & 0x3f));
                            $p .= chr(0x80 | ($ch & 0x3f));
                        }

                        $ch = ($b << (16 + $k)) & 0xffff;
                        $k += 10;
                    }
                }

                // Non-zero or too many extra bits
                if ($ch || $k < 6) {
                    return $err;
                }

                // BASE64 not properly terminated
                if (!$u7len || $u7 != '-') {
                    return $err;
                }

                // Adjacent BASE64 sections
                if ($u7len > 2 && $str[$i+1] == '&' && $str[$i+2] != '-') {
                    return $err;
                }
            }
            // Not printable US-ASCII
            else if (ord($u7) < 0x20 || ord($u7) >= 0x7f) {
                return $err;
            }
            else {
                $p .= $u7;
            }
        }

        return $p;
    }

    /**
     * Convert the data ($str) from UTF-8 to RFC 2060's UTF-7.
     * Unicode characters above U+FFFF are replaced by U+FFFE.
     * If input data is invalid, return an empty string.
     *
     * Translated from C to PHP by Thomas Bruederli <roundcube@gmail.com>
     *
     * @param string $str Input string (UTF-8)
     *
     * @return string Output string (UTF7-IMAP)
     */
    public static function utf8_to_utf7imap($str)
    {
        $B64Chars = array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd',
            'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7',
            '8', '9', '+', ','
        );

        $u8len  = strlen($str);
        $base64 = 0;
        $i      = 0;
        $p      = '';
        $err    = '';

        while ($u8len) {
            $u8 = $str[$i];
            $c  = ord($u8);

            if ($c < 0x80) {
                $ch = $c;
                $n  = 0;
            }
            else if ($c < 0xc2) {
                return $err;
            }
            else if ($c < 0xe0) {
                $ch = $c & 0x1f;
                $n  = 1;
            }
            else if ($c < 0xf0) {
                $ch = $c & 0x0f;
                $n  = 2;
            }
            else if ($c < 0xf8) {
                $ch = $c & 0x07;
                $n  = 3;
            }
            else if ($c < 0xfc) {
                $ch = $c & 0x03;
                $n  = 4;
            }
            else if ($c < 0xfe) {
                $ch = $c & 0x01;
                $n  = 5;
            }
            else {
                return $err;
            }

            $i++;
            $u8len--;

            if ($n > $u8len) {
                return $err;
            }

            for ($j=0; $j < $n; $j++) {
                $o = ord($str[$i+$j]);
                if (($o & 0xc0) != 0x80) {
                    return $err;
                }
                $ch = ($ch << 6) | ($o & 0x3f);
            }

            if ($n > 1 && !($ch >> ($n * 5 + 1))) {
                return $err;
            }

            $i += $n;
            $u8len -= $n;

            if ($ch < 0x20 || $ch >= 0x7f) {
                if (!$base64) {
                    $p .= '&';
                    $base64 = 1;
                    $b = 0;
                    $k = 10;
                }
                if ($ch & ~0xffff) {
                    $ch = 0xfffe;
                }

                $p .= $B64Chars[($b | $ch >> $k)];
                $k -= 6;
                for (; $k >= 0; $k -= 6) {
                    $p .= $B64Chars[(($ch >> $k) & 0x3f)];
                }

                $b = ($ch << (-$k)) & 0x3f;
                $k += 16;
            }
            else {
                if ($base64) {
                    if ($k > 10) {
                        $p .= $B64Chars[$b];
                    }
                    $p .= '-';
                    $base64 = 0;
                }

                $p .= chr($ch);
                if (chr($ch) == '&') {
                    $p .= '-';
                }
            }
        }

        if ($base64) {
            if ($k > 10) {
                $p .= $B64Chars[$b];
            }
            $p .= '-';
        }

        return $p;
    }

    /**
     * A method to guess character set of a string.
     *
     * @param string $string   String
     * @param string $failover Default result for failover
     * @param string $language User language
     *
     * @return string Charset name
     */
    public static function detect($string, $failover = null, $language = null)
    {
        if (substr($string, 0, 4) == "\0\0\xFE\xFF") return 'UTF-32BE';  // Big Endian
        if (substr($string, 0, 4) == "\xFF\xFE\0\0") return 'UTF-32LE';  // Little Endian
        if (substr($string, 0, 2) == "\xFE\xFF")     return 'UTF-16BE';  // Big Endian
        if (substr($string, 0, 2) == "\xFF\xFE")     return 'UTF-16LE';  // Little Endian
        if (substr($string, 0, 3) == "\xEF\xBB\xBF") return 'UTF-8';

        // heuristics
        if ($string[0] == "\0" && $string[1] == "\0" && $string[2] == "\0" && $string[3] != "\0") return 'UTF-32BE';
        if ($string[0] != "\0" && $string[1] == "\0" && $string[2] == "\0" && $string[3] == "\0") return 'UTF-32LE';
        if ($string[0] == "\0" && $string[1] != "\0" && $string[2] == "\0" && $string[3] != "\0") return 'UTF-16BE';
        if ($string[0] != "\0" && $string[1] == "\0" && $string[2] != "\0" && $string[3] == "\0") return 'UTF-16LE';

        if (empty($language)) {
            $rcube    = rcube::get_instance();
            $language = $rcube->get_user_language();
        }

        // Prioritize charsets according to current language (#1485669)
        switch ($language) {
        case 'ja_JP':
            $prio = array('ISO-2022-JP', 'JIS', 'UTF-8', 'EUC-JP', 'eucJP-win', 'SJIS', 'SJIS-win');
            break;

        case 'zh_CN':
        case 'zh_TW':
            $prio = array('UTF-8', 'BIG-5', 'GB2312', 'EUC-TW');
            break;

        case 'ko_KR':
            $prio = array('UTF-8', 'EUC-KR', 'ISO-2022-KR');
            break;

        case 'ru_RU':
            $prio = array('UTF-8', 'WINDOWS-1251', 'KOI8-R');
            break;

        case 'tr_TR':
            $prio = array('UTF-8', 'ISO-8859-9', 'WINDOWS-1254');
            break;
        }

        // mb_detect_encoding() is not reliable for some charsets (#1490135)
        // use mb_check_encoding() to make charset priority lists really working
        if ($prio && function_exists('mb_check_encoding')) {
            foreach ($prio as $encoding) {
                if (mb_check_encoding($string, $encoding)) {
                    return $encoding;
                }
            }
        }

        if (function_exists('mb_detect_encoding')) {
            if (!$prio) {
                $prio = array('UTF-8', 'SJIS', 'GB2312',
                    'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4',
                    'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9',
                    'ISO-8859-10', 'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16',
                    'WINDOWS-1252', 'WINDOWS-1251', 'EUC-JP', 'EUC-TW', 'KOI8-R', 'BIG-5',
                    'ISO-2022-KR', 'ISO-2022-JP',
                );
            }

            $encodings = array_unique(array_merge($prio, mb_list_encodings()));

            if ($encoding = mb_detect_encoding($string, $encodings)) {
                return $encoding;
            }
        }

        // No match, check for UTF-8
        // from http://w3.org/International/questions/qa-forms-utf-8.html
        if (preg_match('/\A(
            [\x09\x0A\x0D\x20-\x7E]
            | [\xC2-\xDF][\x80-\xBF]
            | \xE0[\xA0-\xBF][\x80-\xBF]
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
            | \xED[\x80-\x9F][\x80-\xBF]
            | \xF0[\x90-\xBF][\x80-\xBF]{2}
            | [\xF1-\xF3][\x80-\xBF]{3}
            | \xF4[\x80-\x8F][\x80-\xBF]{2}
            )*\z/xs', substr($string, 0, 2048))
        ) {
            return 'UTF-8';
        }

        return $failover;
    }

    /**
     * Removes non-unicode characters from input.
     *
     * @param mixed $input String or array.
     *
     * @return mixed String or array
     */
    public static function clean($input)
    {
        // handle input of type array
        if (is_array($input)) {
            foreach ($input as $idx => $val) {
                $input[$idx] = self::clean($val);
            }
            return $input;
        }

        if (!is_string($input) || $input == '') {
            return $input;
        }

        // iconv/mbstring are much faster (especially with long strings)
        if (function_exists('mb_convert_encoding')) {
            $msch = mb_substitute_character();
            mb_substitute_character('none');
            $res = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
            mb_substitute_character($msch);

            if ($res !== false) {
                return $res;
            }
        }

        if (function_exists('iconv')) {
            if (($res = @iconv('UTF-8', 'UTF-8//IGNORE', $input)) !== false) {
                return $res;
            }
        }

        $seq    = '';
        $out    = '';
        $regexp = '/^('.
//          '[\x00-\x7F]'.                                  // UTF8-1
            '|[\xC2-\xDF][\x80-\xBF]'.                      // UTF8-2
            '|\xE0[\xA0-\xBF][\x80-\xBF]'.                  // UTF8-3
            '|[\xE1-\xEC][\x80-\xBF][\x80-\xBF]'.           // UTF8-3
            '|\xED[\x80-\x9F][\x80-\xBF]'.                  // UTF8-3
            '|[\xEE-\xEF][\x80-\xBF][\x80-\xBF]'.           // UTF8-3
            '|\xF0[\x90-\xBF][\x80-\xBF][\x80-\xBF]'.       // UTF8-4
            '|[\xF1-\xF3][\x80-\xBF][\x80-\xBF][\x80-\xBF]'.// UTF8-4
            '|\xF4[\x80-\x8F][\x80-\xBF][\x80-\xBF]'.       // UTF8-4
            ')$/';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $chr = $input[$i];
            $ord = ord($chr);

            // 1-byte character
            if ($ord <= 0x7F) {
                if ($seq !== '') {
                    $out .= preg_match($regexp, $seq) ? $seq : '';
                    $seq = '';
                }

                $out .= $chr;
            }
            // first byte of multibyte sequence
            else if ($ord >= 0xC0) {
                if ($seq !== '') {
                    $out .= preg_match($regexp, $seq) ? $seq : '';
                    $seq = '';
                }

                $seq = $chr;
            }
            // next byte of multibyte sequence
            else if ($seq !== '') {
                $seq .= $chr;
            }
        }

        if ($seq !== '') {
            $out .= preg_match($regexp, $seq) ? $seq : '';
        }

        return $out;
    }
}
