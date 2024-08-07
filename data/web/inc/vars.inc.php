<?php
error_reporting(E_ERROR);
//error_reporting(E_ALL);

/*
PLEASE USE THE FILE "vars.local.inc.php" TO OVERWRITE SETTINGS AND MAKE THEM PERSISTENT!
This file will be reset on upgrades.
*/

// SQL database connection variables
$database_type = 'mysql';
$database_sock = '/var/run/mysqld/mysqld.sock';
$database_host = 'mysql';
$database_user = getenv('DBUSER');
$database_pass = getenv('DBPASS');
$database_name = getenv('DBNAME');

// Other variables
$mailcow_hostname = getenv('MAILCOW_HOSTNAME');
$default_pass_scheme = getenv('MAILCOW_PASS_SCHEME');

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
  // With SOGo disabled, the type will always fallback to imap. CalDAV and CardDAV will be excluded, too.
  'autodiscoverType' => 'activesync',
  // If autodiscoverType => activesync, also use ActiveSync (EAS) for Outlook desktop clients (>= Outlook 2013 on Windows)
  // Outlook for Mac does not support ActiveSync
  'useEASforOutlook' => 'no',
  // Please don't use STARTTLS-enabled service ports in the "port" variable.
  // The autodiscover service will always point to SMTPS and IMAPS (TLS-wrapped services).
  // The autoconfig service will additionally announce the STARTTLS-enabled ports, specified in the "tlsport" variable.
  'imap' => array(
    'server' => $mailcow_hostname,
    'port' => (int)filter_var(substr(getenv('IMAPS_PORT'), strrpos(getenv('IMAPS_PORT'), ':')), FILTER_SANITIZE_NUMBER_INT),
    'tlsport' => (int)filter_var(substr(getenv('IMAP_PORT'), strrpos(getenv('IMAP_PORT'), ':')), FILTER_SANITIZE_NUMBER_INT)
  ),
  'pop3' => array(
    'server' => $mailcow_hostname,
    'port' => (int)filter_var(substr(getenv('POPS_PORT'), strrpos(getenv('POPS_PORT'), ':')), FILTER_SANITIZE_NUMBER_INT),
    'tlsport' => (int)filter_var(substr(getenv('POP_PORT'), strrpos(getenv('POP_PORT'), ':')), FILTER_SANITIZE_NUMBER_INT)
  ),
  'smtp' => array(
    'server' => $mailcow_hostname,
    'port' => (int)filter_var(substr(getenv('SMTPS_PORT'), strrpos(getenv('SMTPS_PORT'), ':')), FILTER_SANITIZE_NUMBER_INT),
    'tlsport' => (int)filter_var(substr(getenv('SUBMISSION_PORT'), strrpos(getenv('SUBMISSION_PORT'), ':')), FILTER_SANITIZE_NUMBER_INT)
  ),
  'activesync' => array(
    'url' => 'https://' . $mailcow_hostname . ($https_port == 443 ? '' : ':' . $https_port) . '/Microsoft-Server-ActiveSync',
  ),
  'caldav' => array(
    'server' => $mailcow_hostname,
    'port' => $https_port,
  ),
  'carddav' => array(
    'server' => $mailcow_hostname,
    'port' => $https_port,
  ),
);

// If false, we will use DEFAULT_LANG
// Uses HTTP_ACCEPT_LANGUAGE header
$DETECT_LANGUAGE = true;

// Change default language
$DEFAULT_LANG = 'en-gb';

// Available languages
// https://www.iso.org/obp/ui/#search
// https://en.wikipedia.org/wiki/IETF_language_tag
$AVAILABLE_LANGUAGES = array(
  // 'ca-es' => 'Català (Catalan)',
  'cs-cz' => 'Čeština (Czech)',
  'da-dk' => 'Danish (Dansk)',
  'de-de' => 'Deutsch (German)',
  'en-gb' => 'English',
  'es-es' => 'Español (Spanish)',
  'fi-fi' => 'Suomi (Finish)',
  'fr-fr' => 'Français (French)',
  'gr-gr' => 'Ελληνικά (Greek)',
  'hu-hu' => 'Magyar (Hungarian)',
  'it-it' => 'Italiano (Italian)',
  'ko-kr' => '한국어 (Korean)',
  'lv-lv' => 'latviešu (Latvian)',
  'lt-lt' => 'Lietuvių (Lithuanian)',
  'nb-no' => 'Norsk (Norwegian)',
  'nl-nl' => 'Nederlands (Dutch)',
  'pl-pl' => 'Język Polski (Polish)',
  'pt-br' => 'Português brasileiro (Brazilian Portuguese)',
  'pt-pt' => 'Português (Portuguese)',
  'ro-ro' => 'Română (Romanian)',
  'ru-ru' => 'Pусский (Russian)',
  'si-si' => 'Slovenščina (Slovenian)',
  'sk-sk' => 'Slovenčina (Slovak)',
  'sv-se' => 'Svenska (Swedish)',
  'tr-tr' => 'Türkçe (Turkish)',
  'uk-ua' => 'Українська (Ukrainian)',
  'zh-cn' => '简体中文 (Simplified Chinese)',
  'zh-tw' => '繁體中文 (Traditional Chinese)',
);

// default theme is lumen
// additional themes can be found here: https://bootswatch.com/
// copy them to data/web/css/themes/{THEME-NAME}-bootstrap.css
$UI_THEME = "lumen";

// Show DKIM private keys - false by default
$SHOW_DKIM_PRIV_KEYS = false;

// mailcow Apps - buttons on login screen
$MAILCOW_APPS = array(
  array(
    'name' => 'Webmail',
    'link' => '/SOGo/so',
    'user_link' => '/SOGo/so',
    'hide' => true
  )
);

// Logo max file size in bytes
$LOGO_LIMITS['max_size'] = 15 * 1024 * 1024; // 15MB

// Logo max width in pixels
$LOGO_LIMITS['max_width'] = 1920;

// Logo max height in pixels
$LOGO_LIMITS['max_height'] = 1920;

// Rows until pagination begins
$PAGINATION_SIZE = 25;

// Default number of rows/lines to display (log table)
$LOG_LINES = 1000;

// Rows until pagination begins (log table)
$LOG_PAGINATION_SIZE = 50;

// Session lifetime in seconds
$SESSION_LIFETIME = 10800;

// Label for OTP devices
$OTP_LABEL = "mailcow UI";

// How long to wait (in s) for cURL Docker requests
$DOCKER_TIMEOUT = 60;

// Split DKIM key notation (bind format)
$SPLIT_DKIM_255 = false;

// OAuth2 settings
$REFRESH_TOKEN_LIFETIME = 2678400;
$ACCESS_TOKEN_LIFETIME = 86400;
// Logout from mailcow after first OAuth2 session profile request
$OAUTH2_FORGET_SESSION_AFTER_LOGIN = false;

// Set a limit for mailbox and domain tagging
$TAGGING_LIMIT = 25;

// MAILBOX_DEFAULT_ATTRIBUTES define default attributes for new mailboxes
// These settings will not change existing mailboxes

// Force incoming TLS for new mailboxes by default
$MAILBOX_DEFAULT_ATTRIBUTES['tls_enforce_in'] = false;

// Force outgoing TLS for new mailboxes by default
$MAILBOX_DEFAULT_ATTRIBUTES['tls_enforce_out'] = false;

// Force password change on next login (only allows login to mailcow UI)
$MAILBOX_DEFAULT_ATTRIBUTES['force_pw_update'] = false;

// Enable SOGo access - Users will be redirected to SOGo after login (set to false to disable redirect by default)
$MAILBOX_DEFAULT_ATTRIBUTES['sogo_access'] = true;

// Send notification when quarantine is not empty (never, hourly, daily, weekly)
$MAILBOX_DEFAULT_ATTRIBUTES['quarantine_notification'] = 'hourly';

// Mailbox has IMAP access by default
$MAILBOX_DEFAULT_ATTRIBUTES['imap_access'] = true;

// Mailbox has POP3 access by default
$MAILBOX_DEFAULT_ATTRIBUTES['pop3_access'] = true;

// Mailbox has SMTP access by default
$MAILBOX_DEFAULT_ATTRIBUTES['smtp_access'] = true;

// Mailbox has sieve access by default
$MAILBOX_DEFAULT_ATTRIBUTES['sieve_access'] = true;

// Mailbox receives notifications about...
// "add_header" - mail that was put into the Junk folder
// "reject" - mail that was rejected
// "all" - mail that was rejected and put into the Junk folder
$MAILBOX_DEFAULT_ATTRIBUTES['quarantine_category'] = 'reject';

// Default mailbox format, should not be changed unless you know exactly, what you do, keep the trailing ":"
// Check dovecot.conf for further changes (e.g. shared namespace)
$MAILBOX_DEFAULT_ATTRIBUTES['mailbox_format'] = 'maildir:';

// Show last IMAP and POP3 logins
$SHOW_LAST_LOGIN = true;

// UV flag handling in FIDO2/WebAuthn - defaults to false to allow iOS logins
// true = required
// false = preferred
// string 'required' 'preferred' 'discouraged'
$WEBAUTHN_UV_FLAG_REGISTER = false;
$WEBAUTHN_UV_FLAG_LOGIN = false;
$WEBAUTHN_USER_PRESENT_FLAG = true;

$FIDO2_UV_FLAG_REGISTER = 'preferred';
$FIDO2_UV_FLAG_LOGIN = 'preferred'; // iOS ignores the key via NFC if required - known issue
$FIDO2_USER_PRESENT_FLAG = true;

$FIDO2_FORMATS = array('apple', 'android-key', 'android-safetynet', 'fido-u2f', 'none', 'packed', 'tpm');


// Set visible Rspamd maps in mailcow UI, do not change unless you know what you are doing
$RSPAMD_MAPS = array(
  'regex' => array(
    'Header-From: Blacklist' => 'global_mime_from_blacklist.map',
    'Header-From: Whitelist' => 'global_mime_from_whitelist.map',
    'Envelope Sender Blacklist' => 'global_smtp_from_blacklist.map',
    'Envelope Sender Whitelist' => 'global_smtp_from_whitelist.map',
    'Recipient Blacklist' => 'global_rcpt_blacklist.map',
    'Recipient Whitelist' => 'global_rcpt_whitelist.map',
    'Fishy TLDS (only fired in combination with bad words)' => 'fishy_tlds.map',
    'Bad Words (only fired in combination with fishy TLDs)' => 'bad_words.map',
    'Bad Words DE (only fired in combination with fishy TLDs)' => 'bad_words_de.map',
    'Bad Languages' => 'bad_languages.map',
    'Bulk Mail Headers' => 'bulk_header.map',
    'Bad (Junk) Mail Headers' => 'bad_header.map',
    'Monitoring Hosts' => 'monitoring_nolog.map'
  )
);


$IMAPSYNC_OPTIONS = array(
  'whitelist' => array(
    'abort',     
    'authmd51',        
    'authmd52',           
    'authmech1',
    'authmech2',
    'authuser1', 
    'authuser2', 
    'debug',   
    'debugcontent', 
    'debugcrossduplicates', 
    'debugflags',    
    'debugfolders',            
    'debugimap',    
    'debugimap1',     
    'debugimap2',   
    'debugmemory',       
    'debugssl',              
    'delete1emptyfolders',
    'delete2folders',    
    'disarmreadreceipts', 
    'domain1',
    'domain2',
    'domino1',   
    'domino2',  
    'dry',
    'errorsmax',
    'exchange1',   
    'exchange2',   
    'exitwhenover',
    'expunge1',
    'f1f2',  
    'filterbuggyflags',  
    'folder',
    'folderfirst',
    'folderlast',
    'folderrec',
    'gmail1',     
    'gmail2',    
    'idatefromheader',   
    'include',
    'inet4',
    'inet6',
    'justconnect',  
    'justfolders',  
    'justfoldersizes',  
    'justlogin',   
    'keepalive1',   
    'keepalive2',   
    'log',
    'logdir',
    'logfile',        
    'maxbytesafter',
    'maxlinelength',
    'maxmessagespersecond',
    'maxsize',
    'maxsleep',
    'minage',
    'minsize',
    'noabletosearch', 
    'noabletosearch1',  
    'noabletosearch2',   
    'noexpunge1',        
    'noexpunge2',   
    'nofoldersizesatend',
    'noid',       
    'nolog',  
    'nomixfolders',          
    'noresyncflags',   
    'nossl1',   
    'nossl2',            
    'nosyncacls',      
    'notls1', 
    'notls2',              
    'nouidexpunge2',   
    'nousecache',      
    'oauthaccesstoken1',
    'oauthaccesstoken2',
    'oauthdirect1',
    'oauthdirect2',
    'office1',    
    'office2',      
    'pidfile', 
    'pidfilelocking', 
    'prefix1',
    'prefix2',
    'proxyauth1',  
    'proxyauth2',         
    'resyncflags',     
    'resynclabels',     
    'search', 
    'search1',
    'search2', 
    'sep1',
    'sep2',
    'showpasswords',
    'skipemptyfolders',
    'ssl2',            
    'sslargs1',
    'sslargs2', 
    'subfolder1',
    'subscribe',   
    'subscribed',
    'syncacls',
    'syncduplicates',
    'syncinternaldates',
    'synclabels', 
    'tests',     
    'testslive',       
    'testslive6',     
    'tls2',             
    'truncmess',  
    'usecache', 
    'useheader',  
    'useuid'    
  ),
  'blacklist' => array(
    'skipmess',
    'delete2foldersonly',
    'delete2foldersbutnot',
    'regexflag',
    'regexmess',
    'pipemess',
    'regextrans2',
    'maxlinelengthcmd'
  )
);
