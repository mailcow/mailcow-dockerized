<?php

/**
 +-----------------------------------------------------------------------+
 | program/include/rcmail_html_page.php                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2006-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Render a simple HTML page with the given contents                   |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Class to create an empty HTML page with some default styles
 *
 * @package    Webmail
 * @subpackage View
 */
class rcmail_html_page extends rcmail_output_html
{
    public function write($contents = '')
    {
        self::reset(true);

        // load embed.css from skin folder (if exists)
        if ($embed_css = $this->get_skin_file('/embed.css')) {
            $this->include_css($embed_css);
        }
        else {  // set default styles for warning blocks inside the attachment part frame
            $this->add_header(html::tag('style', array('type' => 'text/css'),
                ".rcmail-inline-message { font-family: sans-serif; border:2px solid #ffdf0e;"
                                        . "background:#fef893; padding:0.6em 1em; margin-bottom:0.6em }\n" .
                ".rcmail-inline-buttons { margin-bottom:0 }"
            ));
        }

        parent::write($contents);
    }
}
