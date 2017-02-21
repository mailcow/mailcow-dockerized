<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/utils/spell.inc                                         |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2011, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Invoke the configured or default spell checking engine.             |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Kris Steinhoff <steinhof@umich.edu>                           |
 +-----------------------------------------------------------------------+
*/

// read input
$lang = rcube_utils::get_input_value('lang', rcube_utils::INPUT_GET);
$data = file_get_contents('php://input');

$learn_word = strpos($data, '<learnword>');

// Get data string
$left = strpos($data, '<text>');
$right = strrpos($data, '</text>');
$data = substr($data, $left+6, $right-($left+6));
$data = html_entity_decode($data, ENT_QUOTES, RCUBE_CHARSET);

$spellchecker = new rcube_spellchecker($lang);

if ($learn_word) {
    $spellchecker->add_word($data);
    $result = '<?xml version="1.0" encoding="'.RCUBE_CHARSET.'"?><learnwordresult></learnwordresult>';
}
else if (empty($data)) {
    $result = '<?xml version="1.0" encoding="'.RCUBE_CHARSET.'"?><spellresult charschecked="0"></spellresult>';
}
else {
    $spellchecker->check($data);
    $result = $spellchecker->get_xml();
}

if ($err = $spellchecker->error()) {
    rcube::raise_error(array('code' => 500, 'type' => 'php',
        'file' => __FILE__, 'line' => __LINE__,
        'message' => "Spell check engine error: " . trim($err)),
        true, false);

    header("HTTP/1.0 500 Internal Server Error");
    exit;
}

// set response length
header("Content-Length: " . strlen($result));

// Don't use server's default Content-Type charset (#1486406)
header("Content-Type: text/xml; charset=" . RCUBE_CHARSET);
print $result;
exit;
