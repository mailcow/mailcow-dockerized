#!/usr/bin/env php
<?php
/*
 +-----------------------------------------------------------------------+
 | bin/update.sh                                                         |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2010-2015, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Check local configuration and database schema after upgrading       |
 |   to a new version                                                    |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

define('INSTALL_PATH', realpath(__DIR__ . '/..') . '/' );

require_once INSTALL_PATH . 'program/include/clisetup.php';

// get arguments
$opts = rcube_utils::get_opt(array('v' => 'version', 'y' => 'accept:bool'));

// ask user if no version is specified
if (!$opts['version']) {
  echo "What version are you upgrading from? Type '?' if you don't know.\n";
  if (($input = trim(fgets(STDIN))) && preg_match('/^[0-9.]+[a-z-]*$/', $input))
    $opts['version'] = $input;
  else
    $opts['version'] = RCMAIL_VERSION;
}

$RCI = rcmail_install::get_instance();
$RCI->load_config();

if ($RCI->configured) {
  $success = true;

  if (($messages = $RCI->check_config()) || $RCI->legacy_config) {
    $success = false;
    $err = 0;

    // list old/replaced config options
    if (is_array($messages['replaced'])) {
      echo "WARNING: Replaced config options:\n";
      echo "(These config options have been replaced or renamed)\n";

      foreach ($messages['replaced'] as $msg) {
        echo "- '" . $msg['prop'] . "' was replaced by '" . $msg['replacement'] . "'\n";
        $err++;
      }
      echo "\n";
    }

    // list obsolete config options (just a notice)
    if (is_array($messages['obsolete'])) {
      echo "NOTICE: Obsolete config options:\n";
      echo "(You still have some obsolete or inexistent properties set. This isn't a problem but should be noticed)\n";

      foreach ($messages['obsolete'] as $msg) {
        echo "- '" . $msg['prop'] . ($msg['name'] ? "': " . $msg['name'] : "'") . "\n";
        $err++;
      }
      echo "\n";
    }

    if (!$err && $RCI->legacy_config) {
      echo "WARNING: Your configuration needs to be migrated!\n";
      echo "We changed the configuration files structure and your two config files main.inc.php and db.inc.php have to be merged into one single file.\n";
      $err++;
    }

    // ask user to update config files
    if ($err) {
      if (!$opts['accept']) {
        echo "Do you want me to fix your local configuration? (y/N)\n";
        $input = trim(fgets(STDIN));
      }

      // positive: let's merge the local config with the defaults
      if ($opts['accept'] || strtolower($input) == 'y') {
        $error = $written = false;

        // backup current config
        echo ". backing up the current config file(s)...\n";

        foreach (array('config', 'main', 'db') as $file) {
          if (file_exists(RCMAIL_CONFIG_DIR . '/' . $file . '.inc.php')) {
            if (!copy(RCMAIL_CONFIG_DIR . '/' . $file . '.inc.php', RCMAIL_CONFIG_DIR . '/' . $file . '.old.php')) {
              $error = true;
            }
          }
        }

        if (!$error) {
          $RCI->merge_config();
          echo ". writing " . RCMAIL_CONFIG_DIR . "/config.inc.php...\n";
          $written = $RCI->save_configfile($RCI->create_config());
        }

        // Success!
        if ($written) {
          echo "Done.\n";
          echo "Your configuration files are now up-to-date!\n";

          if ($messages['missing']) {
            echo "But you still need to add the following missing options:\n";
            foreach ($messages['missing'] as $msg)
              echo "- '" . $msg['prop'] . ($msg['name'] ? "': " . $msg['name'] : "'") . "\n";
          }

          if ($RCI->legacy_config) {
            foreach (array('main', 'db') as $file) {
              @unlink(RCMAIL_CONFIG_DIR . '/' . $file . '.inc.php');
            }
          }
        }
        else {
          echo "Failed to write config file(s)!\n";
          echo "Grant write privileges to the current user or update the files manually according to the above messages.\n";
        }
      }
      else {
        echo "Please update your config files manually according to the above messages.\n";
      }
    }

    // check dependencies based on the current configuration
    if (is_array($messages['dependencies'])) {
      echo "WARNING: Dependency check failed!\n";
      echo "(Some of your configuration settings require other options to be configured or additional PHP modules to be installed)\n";

      foreach ($messages['dependencies'] as $msg) {
        echo "- " . $msg['prop'] . ': ' . $msg['explain'] . "\n";
      }
      echo "Please fix your config files and run this script again!\n";
      echo "See ya.\n";
    }
  }

  // check file type detection
  if ($RCI->check_mime_detection()) {
    echo "WARNING: File type detection doesn't work properly!\n";
    echo "Please check the 'mime_magic' config option or the finfo functions of PHP and run this script again.\n";
  }
  if ($RCI->check_mime_extensions()) {
    echo "WARNING: Mimetype to file extension mapping doesn't work properly!\n";
    echo "Please check the 'mime_types' config option and run this script again.\n";
  }

  // check database schema
  if ($RCI->config['db_dsnw']) {
    echo "Executing database schema update.\n";
    $success = rcmail_utils::db_update(INSTALL_PATH . 'SQL', 'roundcube', $opts['version'],
        array('errors' => true));
  }

  // update composer dependencies
  if (is_file(INSTALL_PATH . 'composer.json') && is_readable(INSTALL_PATH . 'composer.json-dist')) {
    $composer_data = json_decode(file_get_contents(INSTALL_PATH . 'composer.json'), true);
    $composer_template = json_decode(file_get_contents(INSTALL_PATH . 'composer.json-dist'), true);
    $comsposer_json = null;

    // update the require section with the new dependencies
    if (is_array($composer_data['require']) && is_array($composer_template['require'])) {
      $composer_data['require'] = array_merge($composer_data['require'], $composer_template['require']);

      // remove obsolete packages
      $old_packages = array(
        'pear/mail_mime',
        'pear/mail_mime-decode',
        'pear/net_smtp',
        'pear/net_sieve',
        'pear-pear.php.net/net_sieve',
      );
      foreach ($old_packages as $pkg) {
        if (array_key_exists($pkg, $composer_data['require'])) {
          unset($composer_data['require'][$pkg]);
        }
      }
    }

    // update the repositories section with the new dependencies
    if (is_array($composer_template['repositories'])) {
      if (!is_array($composer_data['repositories'])) {
        $composer_data['repositories'] = array();
      }

      foreach ($composer_template['repositories'] as $repo) {
        $rkey = $repo['type'] . preg_replace('/^https?:/', '', $repo['url']) . $repo['package']['name'];
        $existing = false;
        foreach ($composer_data['repositories'] as $k =>  $_repo) {
          if ($rkey == $_repo['type'] . preg_replace('/^https?:/', '', $_repo['url']) . $_repo['package']['name']) {
            // switch to https://
            if (isset($_repo['url']) && strpos($_repo['url'], 'http://') === 0)
              $composer_data['repositories'][$k]['url'] = 'https:' . substr($_repo['url'], 5);
            $existing = true;
            break;
          }
          // remove old repos
          else if (strpos($_repo['url'], 'git://git.kolab.org') === 0) {
            unset($composer_data['repositories'][$k]);
          }
          else if ($_repo['type'] == 'package' && $_repo['package']['name'] == 'Net_SMTP') {
            unset($composer_data['repositories'][$k]);
          }
        }
        if (!$existing) {
          $composer_data['repositories'][] = $repo;
        }
      }

      $composer_data['repositories'] = array_values($composer_data['repositories']);
    }

    // use the JSON encoder from the Composer package
    if (is_file('composer.phar')) {
      include 'phar://composer.phar/src/Composer/Json/JsonFile.php';
      $comsposer_json = \Composer\Json\JsonFile::encode($composer_data);
    }
    // PHP 5.4's json_encode() does the job, too
    else if (defined('JSON_PRETTY_PRINT')) {
      $comsposer_json = json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    else {
      $success = false;
      $comsposer_json = null;
    }

    // write updated composer.json back to disk
    if ($comsposer_json && is_writeable(INSTALL_PATH . 'composer.json')) {
      $success &= (bool)file_put_contents(INSTALL_PATH . 'composer.json', $comsposer_json);
    }
    else {
      echo "WARNING: unable to update composer.json!\n";
      echo "Please replace the 'require' section in your composer.json with the following:\n";

      $require_json = '';
      foreach ($composer_data['require'] as $pkg => $ver) {
        $require_json .= sprintf('        "%s": "%s",'."\n", $pkg, $ver);
      }

      echo '    "require": {'."\n";
      echo rtrim($require_json, ",\n");
      echo "\n    }\n\n";
    }

    echo "NOTE: Update dependencies by running `php composer.phar update --no-dev`\n";
  }

  // index contacts for fulltext searching
  if ($opts['version'] && version_compare(version_parse($opts['version']), '0.6.0', '<')) {
    rcmail_utils::indexcontacts();
  }

  if ($success) {
    echo "This instance of Roundcube is up-to-date.\n";
    echo "Have fun!\n";
  }
}
else {
  echo "This instance of Roundcube is not yet configured!\n";
  echo "Open http://url-to-roundcube/installer/ in your browser and follow the instuctions.\n";
}

?>
