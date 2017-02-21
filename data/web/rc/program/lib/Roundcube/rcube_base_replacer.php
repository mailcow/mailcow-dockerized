<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide basic functions for base URL replacement                    |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Helper class to turn relative urls into absolute ones
 * using a predefined base
 *
 * @package    Framework
 * @subpackage Utils
 * @author     Thomas Bruederli <roundcube@gmail.com>
 */
class rcube_base_replacer
{
    private $base_url;


    /**
     * Class constructor
     *
     * @param string $base Base URL
     */
    public function __construct($base)
    {
        $this->base_url = $base;
    }

    /**
     * Replace callback
     *
     * @param array $matches Matching entries
     *
     * @return string Replaced text with absolute URL
     */
    public function callback($matches)
    {
        return $matches[1] . '="' . self::absolute_url($matches[3], $this->base_url) . '"';
    }

    /**
     * Convert base URLs to absolute ones
     *
     * @param string $body Text body
     *
     * @return string Replaced text
     */
    public function replace($body)
    {
        $regexp = array(
            '/(src|background|href)=(["\']?)([^"\'\s>]+)(\2|\s|>)/i',
            '/(url\s*\()(["\']?)([^"\'\)\s]+)(\2)\)/i',
        );

        return preg_replace_callback($regexp, array($this, 'callback'), $body);
    }

    /**
     * Convert paths like ../xxx to an absolute path using a base url
     *
     * @param string $path     Relative path
     * @param string $base_url Base URL
     *
     * @return string Absolute URL
     */
    public static function absolute_url($path, $base_url)
    {
        // check if path is an absolute URL
        if (preg_match('/^[fhtps]+:\/\//', $path)) {
            return $path;
        }

        // check if path is a content-id scheme
        if (strpos($path, 'cid:') === 0) {
            return $path;
        }

        $host_url = $base_url;
        $abs_path = $path;

        // cut base_url to the last directory
        if (strrpos($base_url, '/') > 7) {
            $host_url = substr($base_url, 0, strpos($base_url, '/', 7));
            $base_url = substr($base_url, 0, strrpos($base_url, '/'));
        }

        // $path is absolute
        if ($path[0] == '/') {
            $abs_path = $host_url.$path;
        }
        else {
            // strip './' because its the same as ''
            $path = preg_replace('/^\.\//', '', $path);

            if (preg_match_all('/\.\.\//', $path, $matches, PREG_SET_ORDER)) {
                $cnt = count($matches);
                while ($cnt--) {
                    if ($pos = strrpos($base_url, '/')) {
                        $base_url = substr($base_url, 0, $pos);
                    }
                    $path = substr($path, 3);
                }
            }

            $abs_path = $base_url.'/'.$path;
        }

        return $abs_path;
    }
}
