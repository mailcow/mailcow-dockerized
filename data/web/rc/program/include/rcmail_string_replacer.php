<?php

/**
 +-----------------------------------------------------------------------+
 | program/include/rcmail_string_replacer.php                            |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2012-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Turn URLs and email addresses into clickable links                  |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Helper class for turning URLs and email addresses in plaintext content
 * into clickable links.
 *
 * @package    Webmail
 * @subpackage Utils
 */
class rcmail_string_replacer extends rcube_string_replacer
{
    /**
     * Callback function used to build mailto: links around e-mail strings
     *
     * This also adds an onclick-handler to open the Rouncube compose message screen on such links
     *
     * @param array $matches Matches result from preg_replace_callback
     *
     * @return int Index of saved string value
     * @see rcube_string_replacer::mailto_callback()
     */
    public function mailto_callback($matches)
    {
        $href   = $matches[1];
        $suffix = $this->parse_url_brackets($href);
        $email  = $href;

        if (strpos($email, '?')) {
            list($email,) = explode('?', $email);
        }

        // skip invalid emails
        if (!rcube_utils::check_email($email, false)) {
            return $matches[1];
        }

        $i = $this->add(html::a(array(
            'href'    => 'mailto:' . $href,
            'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".command('compose','".rcube::JQ($href)."',this)",
            ),
            rcube::Q($href)) . $suffix);

        return $i >= 0 ? $this->get_replacement($i) : '';
    }
}
