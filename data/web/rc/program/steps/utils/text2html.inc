<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/utils/text2html.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Convert plain text to HTML                                          |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$text = stream_get_contents(fopen('php://input', 'r'));

$converter = new rcube_text2html($text, false, array('wrap' => true));

header('Content-Type: text/html; charset=' . RCUBE_CHARSET);
print $converter->get_html();
exit;
