<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2011, The Roundcube Dev Team                            |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   PHP stream filter to detect evil content in mail attachments        |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * PHP stream filter to detect html/javascript code in attachments
 *
 * @package    Framework
 * @subpackage Utils
 */
class rcube_content_filter extends php_user_filter
{
    private $buffer = '';
    private $cutoff = 2048;

    function onCreate()
    {
        $this->cutoff = rand(2048, 3027);
        return true;
    }

    function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->buffer .= $bucket->data;

            // check for evil content and abort
            if (preg_match('/<(script|iframe|object)/i', $this->buffer)) {
                return PSFS_ERR_FATAL;
            }

            // keep buffer small enough
            if (strlen($this->buffer) > 4096) {
                $this->buffer = substr($this->buffer, $this->cutoff);
            }

            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
