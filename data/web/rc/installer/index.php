<?php

/**
 +-------------------------------------------------------------------------+
 | Roundcube Webmail setup tool                                            |
 | Version 1.3-git                                                         |
 |                                                                         |
 | Copyright (C) 2009-2015, The Roundcube Dev Team                         |
 |                                                                         |
 | This program is free software: you can redistribute it and/or modify    |
 | it under the terms of the GNU General Public License (with exceptions   |
 | for skins & plugins) as published by the Free Software Foundation,      |
 | either version 3 of the License, or (at your option) any later version. |
 |                                                                         |
 | This file forms part of the Roundcube Webmail Software for which the    |
 | following exception is added: Plugins and Skins which merely make       |
 | function calls to the Roundcube Webmail Software, and for that purpose  |
 | include it by reference shall not be considered modifications of        |
 | the software.                                                           |
 |                                                                         |
 | If you wish to use this file in another project or create a modified    |
 | version that will not be part of the Roundcube Webmail Software, you    |
 | may remove the exception above and use this source code under the       |
 | original version of the license.                                        |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the            |
 | GNU General Public License for more details.                            |
 |                                                                         |
 | You should have received a copy of the GNU General Public License       |
 | along with this program.  If not, see http://www.gnu.org/licenses/.     |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                          |
 +-------------------------------------------------------------------------+
*/

ini_set('error_reporting', E_ALL &~ (E_NOTICE | E_STRICT));
ini_set('display_errors', 1);

define('INSTALL_PATH', realpath(__DIR__ . '/../').'/');
define('RCUBE_INSTALL_PATH', INSTALL_PATH);
define('RCUBE_CONFIG_DIR', INSTALL_PATH . 'config/');

$include_path  = INSTALL_PATH . 'program/lib' . PATH_SEPARATOR;
$include_path .= INSTALL_PATH . 'program/include' . PATH_SEPARATOR;
$include_path .= ini_get('include_path');

set_include_path($include_path);

// include composer autoloader (if available)
if (@file_exists(INSTALL_PATH . 'vendor/autoload.php')) {
    require INSTALL_PATH . 'vendor/autoload.php';
}

require_once 'Roundcube/bootstrap.php';

if (function_exists('session_start'))
  session_start();

$RCI = rcmail_install::get_instance();
$RCI->load_config();

if (isset($_GET['_getconfig'])) {
  $filename = 'config.inc.php';
  if (!empty($_SESSION['config']) && $_GET['_getconfig'] == 2) {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    @unlink($path);
    file_put_contents($path, $_SESSION['config']);
    exit;
  }
  else if (!empty($_SESSION['config'])) {
    header('Content-type: text/plain');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $_SESSION['config'];
    exit;
  }
  else {
    header('HTTP/1.0 404 Not found');
    die("The requested configuration was not found. Please run the installer from the beginning.");
  }
}

if ($RCI->configured && ($RCI->getprop('enable_installer') || $_SESSION['allowinstaller']) &&
    !empty($_GET['_mergeconfig'])) {
  $filename = 'config.inc.php';

  header('Content-type: text/plain');
  header('Content-Disposition: attachment; filename="'.$filename.'"');

  $RCI->merge_config();
  echo $RCI->create_config();
  exit;
}

// go to 'check env' step if we have a local configuration
if ($RCI->configured && empty($_REQUEST['_step'])) {
  header("Location: ./?_step=1");
  exit;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Roundcube Webmail Installer</title>
<meta name="Robots" content="noindex,nofollow" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="stylesheet" type="text/css" href="styles.css" />
<script type="text/javascript" src="client.js"></script>
</head>

<body>

<div id="banner">
  <div class="banner-bg"></div>
  <div class="banner-logo"><a href="http://roundcube.net"><img src="images/roundcube_logo.png" width="210" height="55" border="0" alt="Roundcube - open source webmail software" /></a></div>
</div>

<div id="topnav">
  <a href="https://github.com/roundcube/roundcubemail/wiki/Installation">How-to Wiki</a>
</div>

<div id="content">

<?php

  // exit if installation is complete
  if ($RCI->configured && !$RCI->getprop('enable_installer') && !$_SESSION['allowinstaller']) {
    // header("HTTP/1.0 404 Not Found");
    if ($RCI->configured && $RCI->legacy_config) {
      echo '<h2 class="error">Your configuration needs to be migrated!</h2>';
      echo '<p>We changed the configuration files structure and your installation needs to be updated accordingly.</p>';
      echo '<p>Please run the <tt>bin/update.sh</tt> script from the command line or set <p>&nbsp; <tt>$rcube_config[\'enable_installer\'] = true;</tt></p>';
      echo ' in your RCUBE_CONFIG_DIR/main.inc.php to let the installer help you migrating it.</p>';
    }
    else {
      echo '<h2 class="error">The installer is disabled!</h2>';
      echo '<p>To enable it again, set <tt>$config[\'enable_installer\'] = true;</tt> in RCUBE_CONFIG_DIR/config.inc.php</p>';
    }
    echo '</div></body></html>';
    exit;
  }

?>

<h1>Roundcube Webmail Installer</h1>

<ol id="progress">
<?php
  $include_steps = array(
    1 => './check.php',
    2 => './config.php',
    3 => './test.php',
  );

  if (!in_array($RCI->step, array_keys($include_steps))) {
    $RCI->step = 1;
  }

  foreach (array('Check environment', 'Create config', 'Test config') as $i => $item) {
    $j = $i + 1;
    $link = ($RCI->step >= $j || $RCI->configured) ? '<a href="./index.php?_step='.$j.'">' . rcube::Q($item) . '</a>' : rcube::Q($item);
    printf('<li class="step%d%s">%s</li>', $j+1, $RCI->step > $j ? ' passed' : ($RCI->step == $j ? ' current' : ''), $link);
  }
?>
</ol>

<?php

include $include_steps[$RCI->step];

?>
</div>

<div id="footer">
  Installer by the Roundcube Dev Team. Copyright &copy; 2008-2012 – Published under the GNU Public License;&nbsp;
  Icons by <a href="http://famfamfam.com">famfamfam</a>
</div>
</body>
</html>
