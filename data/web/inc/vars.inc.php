<?php
error_reporting(E_ERROR);
//error_reporting(E_ALL);

/*
PLEASE USE THE FILE "vars.local.inc.php" TO OVERWRITE SETTINGS AND MAKE THEM PERSISTENT!
This file will be reset on upgrades.
*/

// SQL database connection variables
$database_type = 'mysql';
$database_host = 'mysql';
$database_user = getenv('DBUSER');
$database_pass = getenv('DBPASS');
$database_name = getenv('DBNAME');

// Other variables
$mailcow_hostname = getenv('MAILCOW_HOSTNAME');

$autoconfig_hostname = $mailcow_hostname;

$additionalSan = getenv('ADDITIONAL_SAN');
if ($additionalSan) {
    $arrSan = explode(',', $additionalSan);

    foreach ($arrSan as $i => $san) {
        if ($san && $_SERVER['HTTP_HOST'] == $san) {
            $autoconfig_hostname = $san;

            $mapping = getEnv('AUTOCONFIG_MAPPING');
            $mappingLines = explode(",", $mapping);

            foreach ($mappingLines as $mappingLine) {
                if (false !== strstr($mappingLine, '->')) {
                    list($from, $to) = explode('->', trim($mappingLine));
                    if ($from == $autoconfig_hostname) {
                        $autoconfig_hostname = $to;
                        break;
                    }
                }
            }
            break;
        }
    }
}


// Autodiscover settings
// ===
// Auto-detect HTTPS port =>
$https_port = strpos($_SERVER['HTTP_HOST'], ':');
if ($https_port === FALSE) {
  $https_port = 443;
} else {
  $https_port = substr($_SERVER['HTTP_HOST'], $https_port+1);
}
// Alternatively select port here =>
//$https_port = 1234;
// Other settings =>
$autodiscover_config = array(
  // General autodiscover service type: "activesync" or "imap"
  // emClient uses autodiscover, but does not support ActiveSync. mailcow excludes emClient from ActiveSync.
  'autodiscoverType' => 'activesync',
  // If autodiscoverType => activesync, also use ActiveSync (EAS) for Outlook desktop clients (>= Outlook 2013 on Windows)
  // Outlook for Mac does not support ActiveSync
  'useEASforOutlook' => 'yes',
  // Please don't use STARTTLS-enabled service ports in the "port" variable.
  // The autodiscover service will always point to SMTPS and IMAPS (TLS-wrapped services).
  // The autoconfig service will additionally announce the STARTTLS-enabled ports, specified in the "tlsport" variable.
  'imap' => array(
    'server' => $autoconfig_hostname,
    'port' => array_pop(explode(':', getenv('IMAPS_PORT'))),
    'tlsport' => array_pop(explode(':', getenv('IMAP_PORT'))),
  ),
  'pop3' => array(
    'server' => $autoconfig_hostname,
    'port' => array_pop(explode(':', getenv('POPS_PORT'))),
    'tlsport' => array_pop(explode(':', getenv('POP_PORT'))),
  ),
  'smtp' => array(
    'server' => $autoconfig_hostname,
    'port' => array_pop(explode(':', getenv('SMTPS_PORT'))),
    'tlsport' => array_pop(explode(':', getenv('SUBMISSION_PORT'))),
  ),
  'activesync' => array(
    'url' => 'https://'.$autoconfig_hostname.($https_port == 443 ? '' : ':'.$https_port).'/Microsoft-Server-ActiveSync',
  ),
  'caldav' => array(
    'server' => $autoconfig_hostname,
    'port' => $https_port,
  ),
  'carddav' => array(
    'server' => $autoconfig_hostname,
    'port' => $https_port,
  ),
);
unset($https_port);

// If false, we will use DEFAULT_LANG
// Uses HTTP_ACCEPT_LANGUAGE header
$DETECT_LANGUAGE = true;

// Change default language, "de", "en", "es", "nl", "pt", "ru"
$DEFAULT_LANG = 'en';

// Available languages
$AVAILABLE_LANGUAGES = array('de', 'en', 'es', 'fr', 'lv', 'nl', 'pl', 'pt', 'ru', 'it', 'ca');

// Change theme (default: lumen)
// Needs to be one of those: cerulean, cosmo, cyborg, darkly, flatly, journal, lumen, paper, readable, sandstone,
// simplex, slate, spacelab, superhero, united, yeti
// See https://bootswatch.com/
// WARNING: Only lumen is loaded locally. Enabling any other theme, will download external sources.
$DEFAULT_THEME = 'lumen';

// Password complexity as regular expression
$PASSWD_REGEP = '.{4,}';

// Show DKIM private keys - false by default
$SHOW_DKIM_PRIV_KEYS = false;

// mailcow Apps - buttons on login screen
$MAILCOW_APPS = array(
  array(
    'name' => 'SOGo',
    'link' => '/SOGo/',
  )
);

// Rows until pagination begins
$PAGINATION_SIZE = 20;

// Default number of rows/lines to display (log table)
$LOG_LINES = 100;

// Rows until pagination begins (log table)
$LOG_PAGINATION_SIZE = 30;

// Session lifetime in seconds
$SESSION_LIFETIME = 3600;

// Label for OTP devices
$OTP_LABEL = "mailcow UI";

// Default "to" address in relay test tool
$RELAY_TO = "null@hosted.mailcow.de";

// How long to wait (in s) for cURL Docker requests
$DOCKER_TIMEOUT = 60;

// Anonymize IPs logged via UI
$ANONYMIZE_IPS = true;
