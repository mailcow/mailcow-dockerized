#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/installto.sh                                                      |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2014-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Update an existing Roundcube installation with files from           |
 |   this version                                                        |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require_once INSTALL_PATH . 'program/include/clisetup.php';

$target_dir = unslashify($_SERVER['argv'][1]);

if (empty($target_dir) || !is_dir(realpath($target_dir)))
  rcube::raise_error("Invalid target: not a directory\nUsage: installto.sh <TARGET>", false, true);

// read version from iniset.php
$iniset = @file_get_contents($target_dir . '/program/include/iniset.php');
if (!preg_match('/define\(.RCMAIL_VERSION.,\s*.([0-9.]+[a-z-]*)/', $iniset, $m))
  rcube::raise_error("No valid Roundcube installation found at $target_dir", false, true);

$oldversion = $m[1];

if (version_compare(version_parse($oldversion), version_parse(RCMAIL_VERSION), '>='))
  rcube::raise_error("Installation at target location is up-to-date!", false, true);

echo "Upgrading from $oldversion. Do you want to continue? (y/N)\n";
$input = trim(fgets(STDIN));

if (strtolower($input) == 'y') {
  echo "Copying files to target location...";

  // Save a copy of original .htaccess file (#1490623)
  if (file_exists("$target_dir/.htaccess")) {
    $htaccess_copied = copy("$target_dir/.htaccess", "$target_dir/.htaccess.orig");
  }

  $dirs = array('program','installer','bin','SQL','plugins','skins');
  if (is_dir(INSTALL_PATH . 'vendor') && !is_file(INSTALL_PATH . 'composer.json')) {
    $dirs[] = 'vendor';
  }
  foreach ($dirs as $dir) {
    // @FIXME: should we use --delete for all directories?
    $delete  = in_array($dir, array('program', 'installer')) ? '--delete ' : '';
    $command = "rsync -aC --out-format \"%n\" " . $delete . INSTALL_PATH . "$dir/* $target_dir/$dir/";
    if (!system($command, $ret) || $ret > 0) {
      rcube::raise_error("Failed to execute command: $command", false, true);
    }
  }
  foreach (array('index.php','.htaccess','config/defaults.inc.php','composer.json-dist','CHANGELOG','README.md','UPGRADING','LICENSE','INSTALL') as $file) {
    $command = "rsync -a --out-format \"%n\" " . INSTALL_PATH . "$file $target_dir/$file";
    if (file_exists(INSTALL_PATH . $file) && (!system($command, $ret) || $ret > 0)) {
      rcube::raise_error("Failed to execute command: $command", false, true);
    }
  }

  // remove old (<1.0) .htaccess file
  @unlink("$target_dir/program/.htaccess");
  echo "done.";

  // Inform the user about .htaccess change
  if (!empty($htaccess_copied)) {
    if (file_get_contents("$target_dir/.htaccess") != file_get_contents("$target_dir/.htaccess.orig")) {
      echo "\n!! Old .htaccess file saved as .htaccess.orig !!";
    }
    else {
      @unlink("$target_dir/.htaccess.orig");
    }
  }

  echo "\n\n";

  if (is_dir("$target_dir/skins/default")) {
      echo "Removing old default skin...";
      system("rm -rf $target_dir/skins/default $target_dir/plugins/jqueryui/themes/default");
      foreach (glob(INSTALL_PATH . "plugins/*/skins") as $plugin_skin_dir) {
          $plugin_skin_dir = preg_replace('!^.*' . INSTALL_PATH . '!', '', $plugin_skin_dir);
          if (is_dir("$target_dir/$plugin_skin_dir/classic"))
            system("rm -rf $target_dir/$plugin_skin_dir/default");
      }
      echo "done.\n\n";
  }

  echo "Running update script at target...\n";
  system("cd $target_dir && php bin/update.sh --version=$oldversion");
  echo "All done.\n";
}
else {
  echo "Update cancelled. See ya!\n";
}

?>
