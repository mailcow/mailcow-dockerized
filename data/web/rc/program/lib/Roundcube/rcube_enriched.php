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
 |   Helper class to convert Enriched to HTML format (RFC 1523, 1896)    |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Ryo Chijiiwa (IlohaMail)                                      |
 +-----------------------------------------------------------------------+
*/

/**
 * Class for Enriched to HTML conversion
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_enriched
{
    protected static function convert_newlines($body)
    {
        // remove single newlines, convert N newlines to N-1
        $body = str_replace("\r\n", "\n", $body);
        $len  = strlen($body);
        $nl   = 0;
        $out  = '';

        for ($i=0; $i<$len; $i++) {
            $c = $body[$i];
            if (ord($c) == 10)
                $nl++;
            if ($nl && ord($c) != 10)
                $nl = 0;
            if ($nl != 1)
                $out .= $c;
            else
                $out .= ' ';
        }

        return $out;
    }

    protected static function convert_formatting($body)
    {
        $replace = array(
            '<bold>'        => '<b>',            '</bold>'   => '</b>',
            '<italic>'      => '<i>',            '</italic>' => '</i>',
            '<fixed>'       => '<tt>',           '</fixed>'  => '</tt>',
            '<smaller>'     => '<font size=-1>', '</smaller>'=> '</font>',
            '<bigger>'      => '<font size=+1>', '</bigger>' => '</font>',
            '<underline>'   => '<span style="text-decoration: underline">', '</underline>'   => '</span>',
            '<flushleft>'   => '<span style="text-align: left">',           '</flushleft>'   => '</span>',
            '<flushright>'  => '<span style="text-align: right">',          '</flushright>'  => '</span>',
            '<flushboth>'   => '<span style="text-align: justified">',      '</flushboth>'   => '</span>',
            '<indent>'      => '<span style="padding-left: 20px">',         '</indent>'      => '</span>',
            '<indentright>' => '<span style="padding-right: 20px">',        '</indentright>' => '</span>',
        );

        return str_ireplace(array_keys($replace), array_values($replace), $body);
    }

    protected static function convert_font($body)
    {
        $pattern = '/(.*)\<fontfamily\>\<param\>(.*)\<\/param\>(.*)\<\/fontfamily\>(.*)/ims';

        while (preg_match($pattern, $body, $a)) {
            if (count($a) != 5)
                continue;

            $body = $a[1].'<span style="font-family: '.$a[2].'">'.$a[3].'</span>'.$a[4];
        }

        return $body;
    }

    protected static function convert_color($body)
    {
        $pattern = '/(.*)\<color\>\<param\>(.*)\<\/param\>(.*)\<\/color\>(.*)/ims';

        while (preg_match($pattern, $body, $a)) {
            if (count($a) != 5)
                continue;

            // extract color (either by name, or ####,####,####)
            if (strpos($a[2],',')) {
                $rgb   = explode(',',$a[2]);
                $color = '#';
                for ($i=0; $i<3; $i++)
                    $color .= substr($rgb[$i], 0, 2); // just take first 2 bytes
            }
            else {
                $color = $a[2];
            }

            // put it all together
            $body = $a[1].'<span style="color: '.$color.'">'.$a[3].'</span>'.$a[4];
        }

        return $body;
    }

    protected static function convert_excerpt($body)
    {
        $pattern = '/(.*)\<excerpt\>(.*)\<\/excerpt\>(.*)/i';

        while (preg_match($pattern, $body, $a)) {
            if (count($a) != 4)
                continue;

            $quoted = '';
            $lines  = explode('<br>', $a[2]);

            foreach ($lines as $line)
                $quoted .= '&gt;'.$line.'<br>';

            $body = $a[1].'<span class="quotes">'.$quoted.'</span>'.$a[3];
        }

        return $body;
    }

    /**
     * Converts Enriched text into HTML format
     *
     * @param string $body Enriched text
     *
     * @return string HTML text
     */
    public static function to_html($body)
    {
        $body = str_replace('<<','&lt;',$body);
        $body = self::convert_newlines($body);
        $body = str_replace("\n", '<br>', $body);
        $body = self::convert_formatting($body);
        $body = self::convert_color($body);
        $body = self::convert_font($body);
        $body = self::convert_excerpt($body);
        //$body = nl2br($body);

        return $body;
    }
}
