<?php
error_reporting(E_ERROR | E_WARNING);
//error_reporting(E_ALL);

/*
PLEASE USE THE FILE "vars.local.inc.php" TO OVERWRITE SETTINGS AND MAKE THEM PERSISTENT!
This file will be reset on upgrades.
*/

// SQL database connection variables
$database_type = "mysql";
$database_host = "mysql";
$database_user = getenv('DBUSER');
$database_pass = getenv('DBPASS');
$database_name = getenv('DBNAME');

// Other variables
$mailcow_hostname = getenv('MAILCOW_HOSTNAME');

// Where to go after adding and editing objects
// Can be "form" or "previous"
// "form" will stay in the current form, "previous" will redirect to previous page
$FORM_ACTION = "previous";

// File locations should not be changed
$MC_DKIM_TXTS = "/data/dkim/txt";
$MC_DKIM_KEYS = "/data/dkim/keys";

// Change default language, "en", "pt", "de" or "nl"
$DEFAULT_LANG = "en";

// Change theme (default: lumen)
// Needs to be one of those: cerulean, cosmo, cyborg, darkly, flatly, journal, lumen, paper, readable, sandstone,
// simplex, slate, spacelab, superhero, united, yeti
// See https://bootswatch.com/
$DEFAULT_THEME = "lumen";

// Password complexity as regular expression
$PASSWD_REGEP = '.{4,}';
?>
