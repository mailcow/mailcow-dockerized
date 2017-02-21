<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube PHP suite                          |
 | Copyright (C) 2005-2017, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | CONTENTS:                                                             |
 |   Roundcube Framework Initialization                                  |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Roundcube Framework Initialization
 *
 * @package    Framework
 * @subpackage Core
 */

$config = array(
    'error_reporting'         => E_ALL & ~E_NOTICE & ~E_STRICT,
    // Some users are not using Installer, so we'll check some
    // critical PHP settings here. Only these, which doesn't provide
    // an error/warning in the logs later. See (#1486307).
    'mbstring.func_overload'  => 0,
);

// check these additional ini settings if not called via CLI
if (php_sapi_name() != 'cli') {
    $config += array(
        'suhosin.session.encrypt' => false,
        'file_uploads'            => true,
    );
}

foreach ($config as $optname => $optval) {
    $ini_optval = filter_var(ini_get($optname), is_bool($optval) ? FILTER_VALIDATE_BOOLEAN : FILTER_VALIDATE_INT);
    if ($optval != $ini_optval && @ini_set($optname, $optval) === false) {
        $optval = !is_bool($optval) ? $optval : ($optval ? 'On' : 'Off');
        $error  = "ERROR: Wrong '$optname' option value and it wasn't possible to set it to required value ($optval).\n"
            . "Check your PHP configuration (including php_admin_flag).";

        if (defined('STDERR')) fwrite(STDERR, $error); else echo $error;
        exit(1);
    }
}

// framework constants
define('RCUBE_VERSION', '1.3-beta');
define('RCUBE_CHARSET', 'UTF-8');

if (!defined('RCUBE_LIB_DIR')) {
    define('RCUBE_LIB_DIR', __DIR__ . '/');
}

if (!defined('RCUBE_INSTALL_PATH')) {
    define('RCUBE_INSTALL_PATH', RCUBE_LIB_DIR);
}

if (!defined('RCUBE_CONFIG_DIR')) {
    define('RCUBE_CONFIG_DIR', RCUBE_INSTALL_PATH . 'config/');
}

if (!defined('RCUBE_PLUGINS_DIR')) {
    define('RCUBE_PLUGINS_DIR', RCUBE_INSTALL_PATH . 'plugins/');
}

if (!defined('RCUBE_LOCALIZATION_DIR')) {
    define('RCUBE_LOCALIZATION_DIR', RCUBE_INSTALL_PATH . 'localization/');
}

// set internal encoding for mbstring extension
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding(RCUBE_CHARSET);
}
if (function_exists('mb_regex_encoding')) {
    mb_regex_encoding(RCUBE_CHARSET);
}

// make sure the Roundcube lib directory is in the include_path
$rcube_path = realpath(RCUBE_LIB_DIR . '..');
$sep        = PATH_SEPARATOR;
$regexp     = "!(^|$sep)" . preg_quote($rcube_path, '!') . "($sep|\$)!";
$path       = ini_get('include_path');

if (!preg_match($regexp, $path)) {
    set_include_path($path . PATH_SEPARATOR . $rcube_path);
}

// Register autoloader
spl_autoload_register('rcube_autoload');

// set PEAR error handling (will also load the PEAR main class)
PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'rcube_pear_error');


/**
 * Similar function as in_array() but case-insensitive with multibyte support.
 *
 * @param string $needle   Needle value
 * @param array  $heystack Array to search in
 *
 * @return boolean True if found, False if not
 */
function in_array_nocase($needle, $haystack)
{
    // use much faster method for ascii
    if (is_ascii($needle)) {
        foreach ((array) $haystack as $value) {
            if (strcasecmp($value, $needle) === 0) {
                return true;
            }
        }
    }
    else {
        $needle = mb_strtolower($needle);
        foreach ((array) $haystack as $value) {
            if ($needle === mb_strtolower($value)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Parse a human readable string for a number of bytes.
 *
 * @param string $str Input string
 *
 * @return float Number of bytes
 */
function parse_bytes($str)
{
    if (is_numeric($str)) {
        return floatval($str);
    }

    if (preg_match('/([0-9\.]+)\s*([a-z]*)/i', $str, $regs)) {
        $bytes = floatval($regs[1]);
        switch (strtolower($regs[2])) {
        case 'g':
        case 'gb':
            $bytes *= 1073741824;
            break;
        case 'm':
        case 'mb':
            $bytes *= 1048576;
            break;
        case 'k':
        case 'kb':
            $bytes *= 1024;
            break;
        }
    }

    return floatval($bytes);
}

/**
 * Make sure the string ends with a slash
 */
function slashify($str)
{
  return unslashify($str).'/';
}

/**
 * Remove slashes at the end of the string
 */
function unslashify($str)
{
  return preg_replace('/\/+$/', '', $str);
}

/**
 * Returns number of seconds for a specified offset string.
 *
 * @param string $str String representation of the offset (e.g. 20min, 5h, 2days, 1week)
 *
 * @return int Number of seconds
 */
function get_offset_sec($str)
{
    if (preg_match('/^([0-9]+)\s*([smhdw])/i', $str, $regs)) {
        $amount = (int) $regs[1];
        $unit   = strtolower($regs[2]);
    }
    else {
        $amount = (int) $str;
        $unit   = 's';
    }

    switch ($unit) {
    case 'w':
        $amount *= 7;
    case 'd':
        $amount *= 24;
    case 'h':
        $amount *= 60;
    case 'm':
        $amount *= 60;
    }

    return $amount;
}

/**
 * Create a unix timestamp with a specified offset from now.
 *
 * @param string $offset_str String representation of the offset (e.g. 20min, 5h, 2days)
 * @param int    $factor     Factor to multiply with the offset
 *
 * @return int Unix timestamp
 */
function get_offset_time($offset_str, $factor = 1)
{
    return time() + get_offset_sec($offset_str) * $factor;
}

/**
 * Truncate string if it is longer than the allowed length.
 * Replace the middle or the ending part of a string with a placeholder.
 *
 * @param string $str         Input string
 * @param int    $maxlength   Max. length
 * @param string $placeholder Replace removed chars with this
 * @param bool   $ending      Set to True if string should be truncated from the end
 *
 * @return string Abbreviated string
 */
function abbreviate_string($str, $maxlength, $placeholder = '...', $ending = false)
{
    $length = mb_strlen($str);

    if ($length > $maxlength) {
        if ($ending) {
            return mb_substr($str, 0, $maxlength) . $placeholder;
        }

        $placeholder_length = mb_strlen($placeholder);
        $first_part_length  = floor(($maxlength - $placeholder_length)/2);
        $second_starting_location = $length - $maxlength + $first_part_length + $placeholder_length;

        $prefix = mb_substr($str, 0, $first_part_length);
        $suffix = mb_substr($str, $second_starting_location);
        $str    = $prefix . $placeholder . $suffix;
    }

    return $str;
}

/**
 * Get all keys from array (recursive).
 *
 * @param array $array Input array
 *
 * @return array List of array keys
 */
function array_keys_recursive($array)
{
    $keys = array();

    if (!empty($array) && is_array($array)) {
        foreach ($array as $key => $child) {
            $keys[] = $key;
            foreach (array_keys_recursive($child) as $val) {
                $keys[] = $val;
            }
        }
    }

    return $keys;
}

/**
 * Remove all non-ascii and non-word chars except ., -, _
 */
function asciiwords($str, $css_id = false, $replace_with = '')
{
    $allowed = 'a-z0-9\_\-' . (!$css_id ? '\.' : '');
    return preg_replace("/[^$allowed]/i", $replace_with, $str);
}

/**
 * Check if a string contains only ascii characters
 *
 * @param string $str           String to check
 * @param bool   $control_chars Includes control characters
 *
 * @return bool
 */
function is_ascii($str, $control_chars = true)
{
    $regexp = $control_chars ? '/[^\x00-\x7F]/' : '/[^\x20-\x7E]/';
    return preg_match($regexp, $str) ? false : true;
}

/**
 * Compose a valid representation of name and e-mail address
 *
 * @param string $email E-mail address
 * @param string $name  Person name
 *
 * @return string Formatted string
 */
function format_email_recipient($email, $name = '')
{
    $email = trim($email);

    if ($name && $name != $email) {
        // Special chars as defined by RFC 822 need to in quoted string (or escaped).
        if (preg_match('/[\(\)\<\>\\\.\[\]@,;:"]/', $name)) {
            $name = '"'.addcslashes($name, '"').'"';
        }

        return "$name <$email>";
    }

    return $email;
}

/**
 * Format e-mail address
 *
 * @param string $email E-mail address
 *
 * @return string Formatted e-mail address
 */
function format_email($email)
{
    $email = trim($email);
    $parts = explode('@', $email);
    $count = count($parts);

    if ($count > 1) {
        $parts[$count-1] = mb_strtolower($parts[$count-1]);

        $email = implode('@', $parts);
    }

    return $email;
}

/**
 * Fix version number so it can be used correctly in version_compare()
 *
 * @param string $version Version number string
 *
 * @param return Version number string
 */
function version_parse($version)
{
    return str_replace(
        array('-stable', '-git'),
        array('.0', '.99'),
        $version
    );
}

/**
 * intl replacement functions
 */

if (!function_exists('idn_to_utf8'))
{
    function idn_to_utf8($domain)
    {
        static $idn, $loaded;

        if (!$loaded) {
            $idn    = new Net_IDNA2();
            $loaded = true;
        }

        if ($idn && $domain && preg_match('/(^|\.)xn--/i', $domain)) {
            try {
                $domain = $idn->decode($domain);
            }
            catch (Exception $e) {
            }
        }

        return $domain;
    }
}

if (!function_exists('idn_to_ascii'))
{
    function idn_to_ascii($domain)
    {
        static $idn, $loaded;

        if (!$loaded) {
            $idn    = new Net_IDNA2();
            $loaded = true;
        }

        if ($idn && $domain && preg_match('/[^\x20-\x7E]/', $domain)) {
            try {
                $domain = $idn->encode($domain);
            }
            catch (Exception $e) {
            }
        }

        return $domain;
    }
}

/**
 * Use PHP5 autoload for dynamic class loading
 *
 * @todo Make Zend, PEAR etc play with this
 * @todo Make our classes conform to a more straight forward CS.
 */
function rcube_autoload($classname)
{
    if (strpos($classname, 'rcube') === 0) {
        $classname = 'Roundcube/' . $classname;
    }
    else if (strpos($classname, 'html_') === 0 || $classname === 'html') {
        $classname = 'Roundcube/html';
    }
    else if (strpos($classname, 'Mail_') === 0) {
        $classname = 'Mail/' . substr($classname, 5);
    }
    else if (strpos($classname, 'Net_') === 0) {
        $classname = 'Net/' . substr($classname, 4);
    }
    else if (strpos($classname, 'Auth_') === 0) {
        $classname = 'Auth/' . substr($classname, 5);
    }

    // Translate PHP namespaces into directories,
    // i.e. use \Sabre\VObject; $vcf = VObject\Reader::read(...)
    //      -> Sabre/VObject/Reader.php
    $classname = str_replace('\\', '/', $classname);

    if ($fp = @fopen("$classname.php", 'r', true)) {
        fclose($fp);
        include_once "$classname.php";
        return true;
    }

    return false;
}

/**
 * Local callback function for PEAR errors
 */
function rcube_pear_error($err)
{
    $msg = sprintf("ERROR: %s (%s)", $err->getMessage(), $err->getCode());

    if ($info = $err->getUserinfo()) {
        $msg .= ': ' . $info;
    }

    error_log($msg, 0);
}
