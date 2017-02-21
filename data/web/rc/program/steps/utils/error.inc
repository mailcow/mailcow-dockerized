<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/utils/error.inc                                         |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Display error message page                                          |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$rcmail = rcmail::get_instance();

// browser is not compatible with this application
if ($ERROR_CODE == 409) {
    $user_agent    = htmlentities($_SERVER['HTTP_USER_AGENT']);
    $__error_title = 'Your browser does not suit the requirements for this application';
    $__error_text  = <<<EOF
<i>Supported browsers:</i><br />
&raquo; &nbsp;Microsoft Internet Explorer 7+<br />
&raquo; &nbsp;Mozilla Firefox 3+<br />
&raquo; &nbsp;Chrome 10+<br />
&raquo; &nbsp;Safari 4+<br />
&raquo; &nbsp;Opera 8+<br />
<br />
&raquo; &nbsp;JavaScript enabled<br />
&raquo; &nbsp;Support for XMLHTTPRequest<br />

<p><i>Your configuration:</i><br />
$user_agent</p>
EOF;
}

// authorization error
else if ($ERROR_CODE == 401) {
    $__error_title = mb_strtoupper($rcmail->gettext('errauthorizationfailed'));
    $__error_text  = nl2br($rcmail->gettext('errunauthorizedexplain') . "\n" .
                        $rcmail->gettext('errcontactserveradmin'));
}

// forbidden due to request check
else if ($ERROR_CODE == 403) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && $rcmail->request_status == rcube::REQUEST_ERROR_URL) {
        $url = $rcmail->url($_GET, true, false, true);
        $add = html::a($url, $rcmail->gettext('clicktoresumesession'));
    }
    else {
        $add = $rcmail->gettext('errcontactserveradmin');
    }

    $__error_title = mb_strtoupper($rcmail->gettext('errrequestcheckfailed'));
    $__error_text  = nl2br($rcmail->gettext('errcsrfprotectionexplain')) . '<p>' . $add . '</p>';
}

// failed request (wrong step in URL)
else if ($ERROR_CODE == 404) {
    $request_url   = htmlentities($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    $__error_title = mb_strtoupper($rcmail->gettext('errnotfound'));
    $__error_text  = nl2br($rcmail->gettext('errnotfoundexplain') . "\n" .
                        $rcmail->gettext('errcontactserveradmin'));

    $__error_text .= '<p><i>' . $rcmail->gettext('errfailedrequest') . ":</i><br />\n<tt>//$request_url</tt></p>";
}

// invalid compose ID
else if ($ERROR_CODE == 450 && $_SERVER['REQUEST_METHOD'] == 'GET' && $rcmail->action == 'compose') {
    $url = $rcmail->url('compose');

    $__error_title = mb_strtoupper($rcmail->gettext('errcomposesession'));
    $__error_text  = nl2br($rcmail->gettext('errcomposesessionexplain'))
        . '<p>' . html::a($url, $rcmail->gettext('clicktocompose')) . '</p>';
}

// database connection error
else if ($ERROR_CODE == 601) {
    $__error_title = "CONFIGURATION ERROR";
    $__error_text  =  nl2br($ERROR_MESSAGE) . "<br />Please read the INSTALL instructions!";
}

// database connection error
else if ($ERROR_CODE == 603) {
    $__error_title = "DATABASE ERROR: CONNECTION FAILED!";
    $__error_text  =  "Unable to connect to the database!<br />Please contact your server-administrator.";
}

// system error
else {
    $__error_title = "SERVICE CURRENTLY NOT AVAILABLE!";
    $__error_text  = "Please contact your server-administrator.";

    if (($rcmail->config->get('debug_level') & 4) && $ERROR_MESSAGE) {
        $__error_text = $ERROR_MESSAGE;
    }
    else {
        $__error_text = sprintf('Error No. [%s]', $ERROR_CODE);
    }
}

// inform plugins
if ($rcmail && $rcmail->plugins) {
    $plugin = $rcmail->plugins->exec_hook('error_page', array(
        'code' => $ERROR_CODE,
        'title' => $__error_title,
        'text' => $__error_text,
    ));

    if (!empty($plugin['title']))
        $__error_title = $plugin['title'];
    if (!empty($plugin['text']))
        $__error_text = $plugin['text'];
}

$HTTP_ERR_CODE = $ERROR_CODE && $ERROR_CODE < 600 ? $ERROR_CODE : 500;

// Ajax request
if ($rcmail->output && $rcmail->output->type == 'js') {
    header("HTTP/1.0 $HTTP_ERR_CODE $__error_title");
    die;
}

// compose page content
$__page_content = <<<EOF
<div>
<h3 class="error-title">$__error_title</h3>
<div class="error-text">$__error_text</div>
</div>
EOF;

if ($rcmail->output && $rcmail->output->template_exists('error')) {
    $rcmail->output->reset();
    $rcmail->output->set_env('server_error', $ERROR_CODE);
    $rcmail->output->set_env('comm_path', $rcmail->comm_path);
    $rcmail->output->send('error');
}

$__skin = $rcmail->config->get('skin', 'default');
$__productname = $rcmail->config->get('product_name', 'Roundcube Webmail');

// print system error page
print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
<title>$__productname :: ERROR</title>
<link rel="stylesheet" type="text/css" href="skins/$__skin/common.css" />
</head>
<body>

<table border="0" cellsapcing="0" cellpadding="0" width="100%" height="80%"><tr><td align="center">

$__page_content

</td></tr></table>

</body>
</html>
EOF;

exit;
