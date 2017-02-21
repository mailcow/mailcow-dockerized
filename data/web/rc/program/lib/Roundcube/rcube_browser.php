<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2007-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Class representing the client browser's properties                  |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Provide details about the client's browser based on the User-Agent header
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_browser
{
    function __construct()
    {
        $HTTP_USER_AGENT = strtolower($_SERVER['HTTP_USER_AGENT']);

        $this->ver   = 0;
        $this->win   = strpos($HTTP_USER_AGENT, 'win') != false;
        $this->mac   = strpos($HTTP_USER_AGENT, 'mac') != false;
        $this->linux = strpos($HTTP_USER_AGENT, 'linux') != false;
        $this->unix  = strpos($HTTP_USER_AGENT, 'unix') != false;

        $this->webkit = strpos($HTTP_USER_AGENT, 'applewebkit') !== false;
        $this->opera  = strpos($HTTP_USER_AGENT, 'opera') !== false || ($this->webkit && strpos($HTTP_USER_AGENT, 'opr/') !== false);
        $this->ns     = strpos($HTTP_USER_AGENT, 'netscape') !== false;
        $this->chrome = !$this->opera && strpos($HTTP_USER_AGENT, 'chrome') !== false;
        $this->ie     = !$this->opera && (strpos($HTTP_USER_AGENT, 'compatible; msie') !== false || strpos($HTTP_USER_AGENT, 'trident/') !== false);
        $this->safari = !$this->opera && !$this->chrome && ($this->webkit || strpos($HTTP_USER_AGENT, 'safari') !== false);
        $this->mz     = !$this->ie && !$this->safari && !$this->chrome && !$this->ns && !$this->opera && strpos($HTTP_USER_AGENT, 'mozilla') !== false;

        if ($this->opera) {
            if (preg_match('/(opera|opr)\/([0-9.]+)/', $HTTP_USER_AGENT, $regs)) {
                $this->ver = (float) $regs[2];
            }
        }
        else if (preg_match('/(chrome|msie|version|khtml)(\s*|\/)([0-9.]+)/', $HTTP_USER_AGENT, $regs)) {
            $this->ver = (float) $regs[3];
        }
        else if (preg_match('/rv:([0-9.]+)/', $HTTP_USER_AGENT, $regs)) {
            $this->ver = (float) $regs[1];
        }

        if (preg_match('/ ([a-z]{2})-([a-z]{2})/', $HTTP_USER_AGENT, $regs)) {
            $this->lang =  $regs[1];
        }
        else {
            $this->lang =  'en';
        }

        $this->dom      = $this->mz || $this->safari || ($this->ie && $this->ver>=5) || ($this->opera && $this->ver>=7);
        $this->pngalpha = $this->mz || $this->safari || ($this->ie && $this->ver>=5.5) ||
            ($this->ie && $this->ver>=5 && $this->mac) || ($this->opera && $this->ver>=7);
        $this->imgdata  = !$this->ie;
    }
}
