<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/utils/html2text.inc                                     |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Convert HTML message to plain text                                  |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$html = stream_get_contents(fopen('php://input', 'r'));

$params['links'] = (bool) rcube_utils::get_input_value('_do_links', rcube_utils::INPUT_GET);
$params['width'] = (int) rcube_utils::get_input_value('_width', rcube_utils::INPUT_GET);

$text = $RCMAIL->html2text($html, $params);

header('Content-Type: text/plain; charset=' . RCUBE_CHARSET);
print $text;
exit;
