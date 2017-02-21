<?php

/*
 +-----------------------------------------------------------------------+
 | Main configuration file with default settings                         |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 +-----------------------------------------------------------------------+
*/

$config = array();

// ----------------------------------
// SQL DATABASE
// ----------------------------------

// Database connection string (DSN) for read+write operations
// Format (compatible with PEAR MDB2): db_provider://user:password@host/database
// Currently supported db_providers: mysql, pgsql, sqlite, mssql, sqlsrv, oracle
// For examples see http://pear.php.net/manual/en/package.database.mdb2.intro-dsn.php
// NOTE: for SQLite use absolute path (Linux): 'sqlite:////full/path/to/sqlite.db?mode=0646'
//       or (Windows): 'sqlite:///C:/full/path/to/sqlite.db'
$config['db_dsnw'] = 'mysql://roundcube:@localhost/roundcubemail';

// Database DSN for read-only operations (if empty write database will be used)
// useful for database replication
$config['db_dsnr'] = '';

// Disable the use of already established dsnw connections for subsequent reads
$config['db_dsnw_noread'] = false;

// use persistent db-connections
// beware this will not "always" work as expected
// see: http://www.php.net/manual/en/features.persistent-connections.php
$config['db_persistent'] = false;

// you can define specific table (and sequence) names prefix
$config['db_prefix'] = '';

// Mapping of table names and connections to use for ALL operations.
// This can be used in a setup with replicated databases and a DB master
// where read/write access to cache tables should not go to master.
$config['db_table_dsn'] = array(
//    'cache' => 'r',
//    'cache_index' => 'r',
//    'cache_thread' => 'r',
//    'cache_messages' => 'r',
);

// It is possible to specify database variable values e.g. some limits here.
// Use them if your server is not MySQL or for better performance.
// For example Roundcube uses max_allowed_packet value (in bytes)
// which limits query size for database cache operations.
$config['db_max_allowed_packet'] = null;


// ----------------------------------
// LOGGING/DEBUGGING
// ----------------------------------

// system error reporting, sum of: 1 = log; 4 = show
$config['debug_level'] = 1;

// log driver:  'syslog' or 'file'.
$config['log_driver'] = 'file';

// date format for log entries
// (read http://php.net/manual/en/function.date.php for all format characters)  
$config['log_date_format'] = 'd-M-Y H:i:s O';

// length of the session ID to prepend each log line with
// set to 0 to avoid session IDs being logged.
$config['log_session_id'] = 8;

// Syslog ident string to use, if using the 'syslog' log driver.
$config['syslog_id'] = 'roundcube';

// Syslog facility to use, if using the 'syslog' log driver.
// For possible values see installer or http://php.net/manual/en/function.openlog.php
$config['syslog_facility'] = LOG_USER;

// Activate this option if logs should be written to per-user directories.
// Data will only be logged if a directry <log_dir>/<username>/ exists and is writable.
$config['per_user_logging'] = false;

// Log sent messages to <log_dir>/sendmail or to syslog
$config['smtp_log'] = true;

// Log successful/failed logins to <log_dir>/userlogins or to syslog
$config['log_logins'] = false;

// Log session authentication errors to <log_dir>/session or to syslog
$config['log_session'] = false;

// Log SQL queries to <log_dir>/sql or to syslog
$config['sql_debug'] = false;

// Log IMAP conversation to <log_dir>/imap or to syslog
$config['imap_debug'] = false;

// Log LDAP conversation to <log_dir>/ldap or to syslog
$config['ldap_debug'] = false;

// Log SMTP conversation to <log_dir>/smtp or to syslog
$config['smtp_debug'] = false;

// Log Memcache conversation to <log_dir>/memcache or to syslog
$config['memcache_debug'] = false;

// Log APC conversation to <log_dir>/apc or to syslog
$config['apc_debug'] = false;


// ----------------------------------
// IMAP
// ----------------------------------

// The mail host chosen to perform the log-in.
// Leave blank to show a textbox at login, give a list of hosts
// to display a pulldown menu or set one host as string.
// To use SSL/TLS connection, enter hostname with prefix ssl:// or tls://
// Supported replacement variables:
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %s - domain name after the '@' from e-mail address provided at login screen
// For example %n = mail.domain.tld, %t = domain.tld
// WARNING: After hostname change update of mail_host column in users table is
//          required to match old user data records with the new host.
$config['default_host'] = 'localhost';

// TCP port used for IMAP connections
$config['default_port'] = 143;

// IMAP AUTH type (DIGEST-MD5, CRAM-MD5, LOGIN, PLAIN or null to use
// best server supported one)
$config['imap_auth_type'] = null;

// IMAP socket context options
// See http://php.net/manual/en/context.ssl.php
// The example below enables server certificate validation
//$config['imap_conn_options'] = array(
//  'ssl'         => array(
//     'verify_peer'  => true,
//     'verify_depth' => 3,
//     'cafile'       => '/etc/openssl/certs/ca.crt',
//   ),
// );
// Note: These can be also specified as an array of options indexed by hostname
$config['imap_conn_options'] = null;

// IMAP connection timeout, in seconds. Default: 0 (use default_socket_timeout)
$config['imap_timeout'] = 0;

// Optional IMAP authentication identifier to be used as authorization proxy
$config['imap_auth_cid'] = null;

// Optional IMAP authentication password to be used for imap_auth_cid
$config['imap_auth_pw'] = null;

// If you know your imap's folder delimiter, you can specify it here.
// Otherwise it will be determined automatically
$config['imap_delimiter'] = null;

// If you know your imap's folder vendor, you can specify it here.
// Otherwise it will be determined automatically. Use lower-case
// identifiers, e.g. 'dovecot', 'cyrus', 'gmail', 'hmail', 'uw-imap'.
$config['imap_vendor'] = null;

// If IMAP server doesn't support NAMESPACE extension, but you're
// using shared folders or personal root folder is non-empty, you'll need to
// set these options. All can be strings or arrays of strings.
// Folders need to be ended with directory separator, e.g. "INBOX."
// (special directory "~" is an exception to this rule)
// These can be used also to overwrite server's namespaces
$config['imap_ns_personal'] = null;
$config['imap_ns_other']    = null;
$config['imap_ns_shared']   = null;

// By default IMAP capabilities are readed after connection to IMAP server
// In some cases, e.g. when using IMAP proxy, there's a need to refresh the list
// after login. Set to True if you've got this case.
$config['imap_force_caps'] = false;

// By default list of subscribed folders is determined using LIST-EXTENDED
// extension if available. Some servers (dovecot 1.x) returns wrong results
// for shared namespaces in this case. https://github.com/roundcube/roundcubemail/issues/2474
// Enable this option to force LSUB command usage instead.
// Deprecated: Use imap_disabled_caps = array('LIST-EXTENDED')
$config['imap_force_lsub'] = false;

// Some server configurations (e.g. Courier) doesn't list folders in all namespaces
// Enable this option to force listing of folders in all namespaces
$config['imap_force_ns'] = false;

// Some servers return hidden folders (name starting witha dot)
// from user home directory. IMAP RFC does not forbid that.
// Enable this option to hide them and disable possibility to create such.
$config['imap_skip_hidden_folders'] = false;

// List of disabled imap extensions.
// Use if your IMAP server has broken implementation of some feature
// and you can't remove it from CAPABILITY string on server-side.
// For example UW-IMAP server has broken ESEARCH.
// Note: Because the list is cached, re-login is required after change.
$config['imap_disabled_caps'] = array();

// Log IMAP session identifers after each IMAP login.
// This is used to relate IMAP session with Roundcube user sessions
$config['imap_log_session'] = false;

// Type of IMAP indexes cache. Supported values: 'db', 'apc' and 'memcache'.
$config['imap_cache'] = null;

// Enables messages cache. Only 'db' cache is supported.
// This requires an IMAP server that supports QRESYNC and CONDSTORE
// extensions (RFC7162). See synchronize() in program/lib/Roundcube/rcube_imap_cache.php
// for further info, or if you experience syncing problems.
$config['messages_cache'] = false;

// Lifetime of IMAP indexes cache. Possible units: s, m, h, d, w
$config['imap_cache_ttl'] = '10d';

// Lifetime of messages cache. Possible units: s, m, h, d, w
$config['messages_cache_ttl'] = '10d';

// Maximum cached message size in kilobytes.
// Note: On MySQL this should be less than (max_allowed_packet - 30%)
$config['messages_cache_threshold'] = 50;


// ----------------------------------
// SMTP
// ----------------------------------

// SMTP server host (for sending mails).
// To use SSL/TLS connection, enter hostname with prefix ssl:// or tls://
// Supported replacement variables:
// %h - user's IMAP hostname
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %z - IMAP domain (IMAP hostname without the first part)
// For example %n = mail.domain.tld, %t = domain.tld
$config['smtp_server'] = 'localhost';

// SMTP port (default is 25; use 587 for STARTTLS or 465 for the
// deprecated SSL over SMTP (aka SMTPS))
$config['smtp_port'] = 25;

// SMTP username (if required) if you use %u as the username Roundcube
// will use the current username for login
$config['smtp_user'] = '';

// SMTP password (if required) if you use %p as the password Roundcube
// will use the current user's password for login
$config['smtp_pass'] = '';

// SMTP AUTH type (DIGEST-MD5, CRAM-MD5, LOGIN, PLAIN or empty to use
// best server supported one)
$config['smtp_auth_type'] = '';

// Optional SMTP authentication identifier to be used as authorization proxy
$config['smtp_auth_cid'] = null;

// Optional SMTP authentication password to be used for smtp_auth_cid
$config['smtp_auth_pw'] = null;

// SMTP HELO host 
// Hostname to give to the remote server for SMTP 'HELO' or 'EHLO' messages 
// Leave this blank and you will get the server variable 'server_name' or 
// localhost if that isn't defined.
$config['smtp_helo_host'] = '';

// SMTP connection timeout, in seconds. Default: 0 (use default_socket_timeout)
// Note: There's a known issue where using ssl connection with
// timeout > 0 causes connection errors (https://bugs.php.net/bug.php?id=54511)
$config['smtp_timeout'] = 0;

// SMTP socket context options
// See http://php.net/manual/en/context.ssl.php
// The example below enables server certificate validation, and
// requires 'smtp_timeout' to be non zero.
// $config['smtp_conn_options'] = array(
//   'ssl'         => array(
//     'verify_peer'  => true,
//     'verify_depth' => 3,
//     'cafile'       => '/etc/openssl/certs/ca.crt',
//   ),
// );
// Note: These can be also specified as an array of options indexed by hostname
$config['smtp_conn_options'] = null;


// ----------------------------------
// LDAP
// ----------------------------------

// Type of LDAP cache. Supported values: 'db', 'apc' and 'memcache'.
$config['ldap_cache'] = 'db';

// Lifetime of LDAP cache. Possible units: s, m, h, d, w
$config['ldap_cache_ttl'] = '10m';


// ----------------------------------
// CACHE(S)
// ----------------------------------

// Use these hosts for accessing memcached
// Define any number of hosts in the form of hostname:port or unix:///path/to/socket.file
$config['memcache_hosts'] = null; // e.g. array( 'localhost:11211', '192.168.1.12:11211', 'unix:///var/tmp/memcached.sock' );

// Controls the use of a persistent connections to memcache servers
// See http://php.net/manual/en/memcache.addserver.php
$config['memcache_pconnect'] = true;

// Value in seconds which will be used for connecting to the daemon
// See http://php.net/manual/en/memcache.addserver.php
$config['memcache_timeout'] = 1;

// Controls how often a failed server will be retried (value in seconds).
// Setting this parameter to -1 disables automatic retry.
// See http://php.net/manual/en/memcache.addserver.php
$config['memcache_retry_interval'] = 15;

// use these hosts for accessing Redis.
// Currently only one host is supported. cluster support may come in a future release.
// You can pass 4 fields, host, port, database and password.
// Unset fields will be set to the default values host=127.0.0.1, port=6379, database=0, password=  (empty)
$config['redis_hosts'] = null; // e.g. array( 'localhost:6379' );  array( '192.168.1.1:6379:1:secret' );

// Maximum size of an object in memcache (in bytes). Default: 2MB
$config['memcache_max_allowed_packet'] = '2M';

// Maximum size of an object in APC cache (in bytes). Default: 2MB
$config['apc_max_allowed_packet'] = '2M';


// ----------------------------------
// SYSTEM
// ----------------------------------

// THIS OPTION WILL ALLOW THE INSTALLER TO RUN AND CAN EXPOSE SENSITIVE CONFIG DATA.
// ONLY ENABLE IT IF YOU'RE REALLY SURE WHAT YOU'RE DOING!
$config['enable_installer'] = false;

// don't allow these settings to be overriden by the user
$config['dont_override'] = array();

// List of disabled UI elements/actions
$config['disabled_actions'] = array();

// define which settings should be listed under the 'advanced' block
// which is hidden by default
$config['advanced_prefs'] = array();

// provide an URL where a user can get support for this Roundcube installation
// PLEASE DO NOT LINK TO THE ROUNDCUBE.NET WEBSITE HERE!
$config['support_url'] = '';

// replace Roundcube logo with this image
// specify an URL relative to the document root of this Roundcube installation
// an array can be used to specify different logos for specific template files, '*' for default logo
// for example array("*" => "/images/roundcube_logo.png", "messageprint" => "/images/roundcube_logo_print.png")
$config['skin_logo'] = null;

// automatically create a new Roundcube user when log-in the first time.
// a new user will be created once the IMAP login succeeds.
// set to false if only registered users can use this service
$config['auto_create_user'] = true;

// Enables possibility to log in using email address from user identities
$config['user_aliases'] = false;

// use this folder to store log files
// must be writeable for the user who runs PHP process (Apache user if mod_php is being used)
// This is used by the 'file' log driver.
$config['log_dir'] = RCUBE_INSTALL_PATH . 'logs/';

// use this folder to store temp files
// must be writeable for the user who runs PHP process (Apache user if mod_php is being used)
$config['temp_dir'] = RCUBE_INSTALL_PATH . 'temp/';

// expire files in temp_dir after 48 hours
// possible units: s, m, h, d, w
$config['temp_dir_ttl'] = '48h';

// Enforce connections over https
// With this option enabled, all non-secure connections will be redirected.
// It can be also a port number, hostname or hostname:port if they are
// different than default HTTP_HOST:443
$config['force_https'] = false;

// tell PHP that it should work as under secure connection
// even if it doesn't recognize it as secure ($_SERVER['HTTPS'] is not set)
// e.g. when you're running Roundcube behind a https proxy
// this option is mutually exclusive to 'force_https' and only either one of them should be set to true.
$config['use_https'] = false;

// Allow browser-autocompletion on login form.
// 0 - disabled, 1 - username and host only, 2 - username, host, password
$config['login_autocomplete'] = 0;

// Forces conversion of logins to lower case.
// 0 - disabled, 1 - only domain part, 2 - domain and local part.
// If users authentication is case-insensitive this must be enabled.
// Note: After enabling it all user records need to be updated, e.g. with query:
//       UPDATE users SET username = LOWER(username);
$config['login_lc'] = 2;

// Maximum length (in bytes) of logon username and password.
$config['login_username_maxlen'] = 1024;
$config['login_password_maxlen'] = 1024;

// Logon username filter. Regular expression for use with preg_match().
// Example: '/^[a-z0-9_@.-]+$/'
$config['login_username_filter'] = null;

// Brute-force attacks prevention.
// The value specifies maximum number of failed logon attempts per minute.
$config['login_rate_limit'] = 3;

// Includes should be interpreted as PHP files
$config['skin_include_php'] = false;

// display software version on login screen
$config['display_version'] = false;

// Session lifetime in minutes
$config['session_lifetime'] = 10;

// Session domain: .example.org
$config['session_domain'] = '';

// Session name. Default: 'roundcube_sessid'
$config['session_name'] = null;

// Session authentication cookie name. Default: 'roundcube_sessauth'
$config['session_auth_name'] = null;

// Session path. Defaults to PHP session.cookie_path setting.
$config['session_path'] = null;

// Backend to use for session storage. Can either be 'db' (default), 'redis', 'memcache', or 'php'
//
// If set to 'memcache', a list of servers need to be specified in 'memcache_hosts'
// Make sure the Memcache extension (http://pecl.php.net/package/memcache) version >= 2.0.0 is installed
//
// If set to 'redis', a server needs to be specified in 'redis_hosts'
// Make sure the Redis extension (http://pecl.php.net/package/redis) version >= 2.0.0 is installed
//
// Setting this value to 'php' will use the default session save handler configured in PHP
$config['session_storage'] = 'db';

// check client IP in session authorization
$config['ip_check'] = false;

// List of trusted proxies
// X_FORWARDED_* and X_REAL_IP headers are only accepted from these IPs
$config['proxy_whitelist'] = array();

// check referer of incoming requests
$config['referer_check'] = false;

// X-Frame-Options HTTP header value sent to prevent from Clickjacking.
// Possible values: sameorigin|deny|allow-from <uri>.
// Set to false in order to disable sending the header.
$config['x_frame_options'] = 'sameorigin';

// This key is used for encrypting purposes, like storing of imap password
// in the session. For historical reasons it's called DES_key, but it's used
// with any configured cipher_method (see below).
$config['des_key'] = 'rcmail-!24ByteDESkey*Str';

// Encryption algorithm. You can use any method supported by openssl.
// Default is set for backward compatibility to DES-EDE3-CBC,
// but you can choose e.g. AES-256-CBC which we consider a better choice.
$config['cipher_method'] = 'DES-EDE3-CBC';

// Automatically add this domain to user names for login
// Only for IMAP servers that require full e-mail addresses for login
// Specify an array with 'host' => 'domain' values to support multiple hosts
// Supported replacement variables:
// %h - user's IMAP hostname
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %z - IMAP domain (IMAP hostname without the first part)
// For example %n = mail.domain.tld, %t = domain.tld
$config['username_domain'] = '';

// Force domain configured in username_domain to be used for login.
// Any domain in username will be replaced by username_domain.
$config['username_domain_forced'] = false;

// This domain will be used to form e-mail addresses of new users
// Specify an array with 'host' => 'domain' values to support multiple hosts
// Supported replacement variables:
// %h - user's IMAP hostname
// %n - http hostname ($_SERVER['SERVER_NAME'])
// %d - domain (http hostname without the first part)
// %z - IMAP domain (IMAP hostname without the first part)
// For example %n = mail.domain.tld, %t = domain.tld
$config['mail_domain'] = '';

// Password character set.
// If your authentication backend supports it, use "UTF-8".
// Otherwise, use the appropriate character set.
// Defaults to ISO-8859-1 for backward compatibility.
$config['password_charset'] = 'ISO-8859-1';

// How many seconds must pass between emails sent by a user
$config['sendmail_delay'] = 0;

// Message size limit. Note that SMTP server(s) may use a different value.
// This limit is verified when user attaches files to a composed message.
// Size in bytes (possible unit suffix: K, M, G)
$config['max_message_size'] = '100M';

// Maximum number of recipients per message (including To, Cc, Bcc).
// Default: 0 (no limit)
$config['max_recipients'] = 0;

// Maximum number of recipients per message exluding Bcc header.
// This is a soft limit, which means we only display a warning to the user.
// Default: 5
$config['max_disclosed_recipients'] = 5;

// Maximum allowed number of members of an address group. Default: 0 (no limit)
// If 'max_recipients' is set this value should be less or equal
$config['max_group_members'] = 0;

// Name your service. This is displayed on the login screen and in the window title
$config['product_name'] = 'Roundcube Webmail';

// Add this user-agent to message headers when sending
$config['useragent'] = 'Roundcube Webmail/'.RCMAIL_VERSION;

// try to load host-specific configuration
// see https://github.com/roundcube/roundcubemail/wiki/Configuration:-Multi-Domain-Setup
// for more details
$config['include_host_config'] = false;

// path to a text file which will be added to each sent message
// paths are relative to the Roundcube root folder
$config['generic_message_footer'] = '';

// path to a text file which will be added to each sent HTML message
// paths are relative to the Roundcube root folder
$config['generic_message_footer_html'] = '';

// add a received header to outgoing mails containing the creators IP and hostname
$config['http_received_header'] = false;

// Whether or not to encrypt the IP address and the host name
// these could, in some circles, be considered as sensitive information;
// however, for the administrator, these could be invaluable help
// when tracking down issues.
$config['http_received_header_encrypt'] = false;

// number of chars allowed for line when wrapping text.
// text wrapping is done when composing/sending messages
$config['line_length'] = 72;

// send plaintext messages as format=flowed
$config['send_format_flowed'] = true;

// According to RFC2298, return receipt envelope sender address must be empty.
// If this option is true, Roundcube will use user's identity as envelope sender for MDN responses.
$config['mdn_use_from'] = false;

// Set identities access level:
// 0 - many identities with possibility to edit all params
// 1 - many identities with possibility to edit all params but not email address
// 2 - one identity with possibility to edit all params
// 3 - one identity with possibility to edit all params but not email address
// 4 - one identity with possibility to edit only signature
$config['identities_level'] = 0;

// Maximum size of uploaded image in kilobytes
// Images (in html signatures) are stored in database as data URIs
$config['identity_image_size'] = 64;

// Mimetypes supported by the browser.
// attachments of these types will open in a preview window
// either a comma-separated list or an array: 'text/plain,text/html,text/xml,image/jpeg,image/gif,image/png,application/pdf'
$config['client_mimetypes'] = null;  # null == default

// Path to a local mime magic database file for PHPs finfo extension.
// Set to null if the default path should be used.
$config['mime_magic'] = null;

// Absolute path to a local mime.types mapping table file.
// This is used to derive mime-types from the filename extension or vice versa.
// Such a file is usually part of the apache webserver. If you don't find a file named mime.types on your system,
// download it from http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
$config['mime_types'] = null;

// path to imagemagick identify binary (if not set we'll use Imagick or GD extensions)
$config['im_identify_path'] = null;

// path to imagemagick convert binary (if not set we'll use Imagick or GD extensions)
$config['im_convert_path'] = null;

// Size of thumbnails from image attachments displayed below the message content.
// Note: whether images are displayed at all depends on the 'inline_images' option.
// Set to 0 to display images in full size.
$config['image_thumbnail_size'] = 240;

// maximum size of uploaded contact photos in pixel
$config['contact_photo_size'] = 160;

// Enable DNS checking for e-mail address validation
$config['email_dns_check'] = false;

// Disables saving sent messages in Sent folder (like gmail) (Default: false)
// Note: useful when SMTP server stores sent mail in user mailbox
$config['no_save_sent_messages'] = false;

// Improve system security by using special URL with security token.
// This can be set to a number defining token length. Default: 16.
// Warning: This requires http server configuration. Sample:
//    RewriteRule ^/roundcubemail/[a-zA-Z0-9]{16}/(.*) /roundcubemail/$1 [PT]
//    Alias /roundcubemail /var/www/roundcubemail/
// Note: Use assets_path to not prevent the browser from caching assets
$config['use_secure_urls'] = false;

// Allows to define separate server/path for image/js/css files
// Warning: If the domain is different cross-domain access to some
// resources need to be allowed
// Sample:
//    <FilesMatch ".(eot|ttf|woff)">
//    Header set Access-Control-Allow-Origin "*"
//    </FilesMatch>
$config['assets_path'] = '';

// While assets_path is for the browser, assets_dir informs
// PHP code about the location of asset files in filesystem
$config['assets_dir'] = '';


// ----------------------------------
// PLUGINS
// ----------------------------------

// List of active plugins (in plugins/ directory)
$config['plugins'] = array();

// ----------------------------------
// USER INTERFACE
// ----------------------------------

// default messages sort column. Use empty value for default server's sorting, 
// or 'arrival', 'date', 'subject', 'from', 'to', 'fromto', 'size', 'cc'
$config['message_sort_col'] = '';

// default messages sort order
$config['message_sort_order'] = 'DESC';

// These cols are shown in the message list. Available cols are:
// subject, from, to, fromto, cc, replyto, date, size, status, flag, attachment, priority
$config['list_cols'] = array('subject', 'status', 'fromto', 'date', 'size', 'flag', 'attachment');

// the default locale setting (leave empty for auto-detection)
// RFC1766 formatted language name like en_US, de_DE, de_CH, fr_FR, pt_BR
$config['language'] = null;

// use this format for date display (date or strftime format)
$config['date_format'] = 'Y-m-d';

// give this choice of date formats to the user to select from
// Note: do not use ambiguous formats like m/d/Y
$config['date_formats'] = array('Y-m-d', 'Y/m/d', 'Y.m.d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'j.n.Y');

// use this format for time display (date or strftime format)
$config['time_format'] = 'H:i';

// give this choice of time formats to the user to select from
$config['time_formats'] = array('G:i', 'H:i', 'g:i a', 'h:i A');

// use this format for short date display (derived from date_format and time_format)
$config['date_short'] = 'D H:i';

// use this format for detailed date/time formatting (derived from date_format and time_format)
$config['date_long'] = 'Y-m-d H:i';

// store draft message is this mailbox
// leave blank if draft messages should not be stored
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$config['drafts_mbox'] = 'Drafts';

// store spam messages in this mailbox
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$config['junk_mbox'] = 'Junk';

// store sent message is this mailbox
// leave blank if sent messages should not be stored
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$config['sent_mbox'] = 'Sent';

// move messages to this folder when deleting them
// leave blank if they should be deleted directly
// NOTE: Use folder names with namespace prefix (INBOX. on Courier-IMAP)
$config['trash_mbox'] = 'Trash';

// automatically create the above listed default folders on user login
$config['create_default_folders'] = false;

// protect the default folders from renames, deletes, and subscription changes
$config['protect_default_folders'] = true;

// Disable localization of the default folder names listed above
$config['show_real_foldernames'] = false;

// if in your system 0 quota means no limit set this option to true 
$config['quota_zero_as_unlimited'] = false;

// Make use of the built-in spell checker. It is based on GoogieSpell.
$config['enable_spellcheck'] = true;

// Enables spellchecker exceptions dictionary.
// Setting it to 'shared' will make the dictionary shared by all users.
$config['spellcheck_dictionary'] = false;

// Set the spell checking engine. Possible values:
// - 'googie'  - the default (also used for connecting to Nox Spell Server, see 'spellcheck_uri' setting)
// - 'pspell'  - requires the PHP Pspell module and aspell installed
// - 'enchant' - requires the PHP Enchant module
// - 'atd'     - install your own After the Deadline server or check with the people at http://www.afterthedeadline.com before using their API
// Since Google shut down their public spell checking service, the default settings
// connect to http://spell.roundcube.net which is a hosted service provided by Roundcube.
// You can connect to any other googie-compliant service by setting 'spellcheck_uri' accordingly.
$config['spellcheck_engine'] = 'googie';

// For locally installed Nox Spell Server or After the Deadline services,
// please specify the URI to call it.
// Get Nox Spell Server from http://orangoo.com/labs/?page_id=72 or
// the After the Deadline package from http://www.afterthedeadline.com.
// Leave empty to use the public API of service.afterthedeadline.com
$config['spellcheck_uri'] = '';

// These languages can be selected for spell checking.
// Configure as a PHP style hash array: array('en'=>'English', 'de'=>'Deutsch');
// Leave empty for default set of available language.
$config['spellcheck_languages'] = NULL;

// Makes that words with all letters capitalized will be ignored (e.g. GOOGLE)
$config['spellcheck_ignore_caps'] = false;

// Makes that words with numbers will be ignored (e.g. g00gle)
$config['spellcheck_ignore_nums'] = false;

// Makes that words with symbols will be ignored (e.g. g@@gle)
$config['spellcheck_ignore_syms'] = false;

// Use this char/string to separate recipients when composing a new message
$config['recipients_separator'] = ',';

// Number of lines at the end of a message considered to contain the signature.
// Increase this value if signatures are not properly detected and colored
$config['sig_max_lines'] = 15;

// don't let users set pagesize to more than this value if set
$config['max_pagesize'] = 200;

// Minimal value of user's 'refresh_interval' setting (in seconds)
$config['min_refresh_interval'] = 60;

// Enables files upload indicator. Requires APC installed and enabled apc.rfc1867 option.
// By default refresh time is set to 1 second. You can set this value to true
// or any integer value indicating number of seconds.
$config['upload_progress'] = false;

// Specifies for how many seconds the Undo button will be available
// after object delete action. Currently used with supporting address book sources.
// Setting it to 0, disables the feature.
$config['undo_timeout'] = 0;

// A static list of canned responses which are immutable for the user
$config['compose_responses_static'] = array(
//  array('name' => 'Canned Response 1', 'text' => 'Static Response One'),
//  array('name' => 'Canned Response 2', 'text' => 'Static Response Two'),
);

// ----------------------------------
// ADDRESSBOOK SETTINGS
// ----------------------------------

// This indicates which type of address book to use. Possible choises:
// 'sql' (default), 'ldap' and ''.
// If set to 'ldap' then it will look at using the first writable LDAP
// address book as the primary address book and it will not display the
// SQL address book in the 'Address Book' view.
// If set to '' then no address book will be displayed or only the
// addressbook which is created by a plugin (like CardDAV).
$config['address_book_type'] = 'sql';

// In order to enable public ldap search, configure an array like the Verisign
// example further below. if you would like to test, simply uncomment the example.
// Array key must contain only safe characters, ie. a-zA-Z0-9_
$config['ldap_public'] = array();

// If you are going to use LDAP for individual address books, you will need to 
// set 'user_specific' to true and use the variables to generate the appropriate DNs to access it.
//
// The recommended directory structure for LDAP is to store all the address book entries
// under the users main entry, e.g.:
//
//  o=root
//   ou=people
//    uid=user@domain
//  mail=contact@contactdomain
//
// So the base_dn would be uid=%fu,ou=people,o=root
// The bind_dn would be the same as based_dn or some super user login.
/* 
 * example config for Verisign directory
 *
$config['ldap_public']['Verisign'] = array(
  'name'          => 'Verisign.com',
  // Replacement variables supported in host names:
  // %h - user's IMAP hostname
  // %n - hostname ($_SERVER['SERVER_NAME'])
  // %t - hostname without the first part
  // %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
  // %z - IMAP domain (IMAP hostname without the first part)
  // For example %n = mail.domain.tld, %t = domain.tld
  'hosts'         => array('directory.verisign.com'),
  'port'          => 389,
  'use_tls'       => false,
  'ldap_version'  => 3,       // using LDAPv3
  'network_timeout' => 10,    // The timeout (in seconds) for connect + bind arrempts. This is only supported in PHP >= 5.3.0 with OpenLDAP 2.x
  'user_specific' => false,   // If true the base_dn, bind_dn and bind_pass default to the user's IMAP login.
  // When 'user_specific' is enabled following variables can be used in base_dn/bind_dn config:
  // %fu - The full username provided, assumes the username is an email
  //       address, uses the username_domain value if not an email address.
  // %u  - The username prior to the '@'.
  // %d  - The domain name after the '@'.
  // %dc - The domain name hierarchal string e.g. "dc=test,dc=domain,dc=com"
  // %dn - DN found by ldap search when search_filter/search_base_dn are used
  'base_dn'       => '',
  'bind_dn'       => '',
  'bind_pass'     => '',
  // It's possible to bind for an individual address book
  // The login name is used to search for the DN to bind with
  'search_base_dn' => '',
  'search_filter'  => '',   // e.g. '(&(objectClass=posixAccount)(uid=%u))'
  // DN and password to bind as before searching for bind DN, if anonymous search is not allowed
  'search_bind_dn' => '',
  'search_bind_pw' => '',
  // Base DN and filter used for resolving the user's domain root DN which feeds the %dc variables
  // Leave empty to skip this lookup and derive the root DN from the username domain
  'domain_base_dn' => '',
  'domain_filter'  => '',
  // Optional map of replacement strings => attributes used when binding for an individual address book
  'search_bind_attrib' => array(),  // e.g. array('%udc' => 'ou')
  // Default for %dn variable if search doesn't return DN value
  'search_dn_default' => '',
  // Optional authentication identifier to be used as SASL authorization proxy
  // bind_dn need to be empty
  'auth_cid'       => '',
  // SASL authentication method (for proxy auth), e.g. DIGEST-MD5
  'auth_method'    => '',
  // Indicates if the addressbook shall be hidden from the list.
  // With this option enabled you can still search/view contacts.
  'hidden'        => false,
  // Indicates if the addressbook shall not list contacts but only allows searching.
  'searchonly'    => false,
  // Indicates if we can write to the LDAP directory or not.
  // If writable is true then these fields need to be populated:
  // LDAP_Object_Classes, required_fields, LDAP_rdn
  'writable'       => false,
  // To create a new contact these are the object classes to specify
  // (or any other classes you wish to use).
  'LDAP_Object_Classes' => array('top', 'inetOrgPerson'),
  // The RDN field that is used for new entries, this field needs
  // to be one of the search_fields, the base of base_dn is appended
  // to the RDN to insert into the LDAP directory.
  'LDAP_rdn'       => 'cn',
  // The required fields needed to build a new contact as required by
  // the object classes (can include additional fields not required by the object classes).
  'required_fields' => array('cn', 'sn', 'mail'),
  'search_fields'   => array('mail', 'cn'),  // fields to search in
  // mapping of contact fields to directory attributes
  //   1. for every attribute one can specify the number of values (limit) allowed.
  //      default is 1, a wildcard * means unlimited
  //   2. another possible parameter is separator character for composite fields
  //   3. it's possible to define field format for write operations, e.g. for date fields
  //      example: 'birthday:date[YmdHis\\Z]'
  'fieldmap' => array(
    // Roundcube  => LDAP:limit
    'name'        => 'cn',
    'surname'     => 'sn',
    'firstname'   => 'givenName',
    'jobtitle'    => 'title',
    'email'       => 'mail:*',
    'phone:home'  => 'homePhone',
    'phone:work'  => 'telephoneNumber',
    'phone:mobile' => 'mobile',
    'phone:pager' => 'pager',
    'phone:workfax' => 'facsimileTelephoneNumber',
    'street'      => 'street',
    'zipcode'     => 'postalCode',
    'region'      => 'st',
    'locality'    => 'l',
    // if you country is a complex object, you need to configure 'sub_fields' below
    'country'      => 'c',
    'organization' => 'o',
    'department'   => 'ou',
    'jobtitle'     => 'title',
    'notes'        => 'description',
    'photo'        => 'jpegPhoto',
    // these currently don't work:
    // 'manager'       => 'manager',
    // 'assistant'     => 'secretary',
  ),
  // Map of contact sub-objects (attribute name => objectClass(es)), e.g. 'c' => 'country'
  'sub_fields' => array(),
  // Generate values for the following LDAP attributes automatically when creating a new record
  'autovalues' => array(
  // 'uid'  => 'md5(microtime())',               // You may specify PHP code snippets which are then eval'ed 
  // 'mail' => '{givenname}.{sn}@mydomain.com',  // or composite strings with placeholders for existing attributes
  ),
  'sort'           => 'cn',         // The field to sort the listing by.
  'scope'          => 'sub',        // search mode: sub|base|list
  'filter'         => '(objectClass=inetOrgPerson)',      // used for basic listing (if not empty) and will be &'d with search queries. example: status=act
  'fuzzy_search'   => true,         // server allows wildcard search
  'vlv'            => false,        // Enable Virtual List View to more efficiently fetch paginated data (if server supports it)
  'vlv_search'     => false,        // Use Virtual List View functions for autocompletion searches (if server supports it)
  'numsub_filter'  => '(objectClass=organizationalUnit)',   // with VLV, we also use numSubOrdinates to query the total number of records. Set this filter to get all numSubOrdinates attributes for counting
  'config_root_dn' => 'cn=config',  // Root DN to search config entries (e.g. vlv indexes)
  'sizelimit'      => '0',          // Enables you to limit the count of entries fetched. Setting this to 0 means no limit.
  'timelimit'      => '0',          // Sets the number of seconds how long is spend on the search. Setting this to 0 means no limit.
  'referrals'      => false,        // Sets the LDAP_OPT_REFERRALS option. Mostly used in multi-domain Active Directory setups
  'dereference'    => 0,            // Sets the LDAP_OPT_DEREF option. One of: LDAP_DEREF_NEVER, LDAP_DEREF_SEARCHING, LDAP_DEREF_FINDING, LDAP_DEREF_ALWAYS
                                    // Used where addressbook contains aliases to objects elsewhere in the LDAP tree.

  // definition for contact groups (uncomment if no groups are supported)
  // for the groups base_dn, the user replacements %fu, %u, $d and %dc work as for base_dn (see above)
  // if the groups base_dn is empty, the contact base_dn is used for the groups as well
  // -> in this case, assure that groups and contacts are separated due to the concernig filters! 
  'groups'  => array(
    'base_dn'           => '',
    'scope'             => 'sub',       // Search mode: sub|base|list
    'filter'            => '(objectClass=groupOfNames)',
    'object_classes'    => array('top', 'groupOfNames'),   // Object classes to be assigned to new groups
    'member_attr'       => 'member',   // Name of the default member attribute, e.g. uniqueMember
    'name_attr'         => 'cn',       // Attribute to be used as group name
    'email_attr'        => 'mail',     // Group email address attribute (e.g. for mailing lists)
    'member_filter'     => '(objectclass=*)',  // Optional filter to use when querying for group members
    'vlv'               => false,      // Use VLV controls to list groups
    'class_member_attr' => array(      // Mapping of group object class to member attribute used in these objects
      'groupofnames'       => 'member',
      'groupofuniquenames' => 'uniquemember'
    ),
  ),
  // this configuration replaces the regular groups listing in the directory tree with
  // a hard-coded list of groups, each listing entries with the configured base DN and filter.
  // if the 'groups' option from above is set, it'll be shown as the first entry with the name 'Groups'
  'group_filters' => array(
    'departments' => array(
      'name'    => 'Company Departments',
      'scope'   => 'list',
      'base_dn' => 'ou=Groups,dc=mydomain,dc=com',
      'filter'  => '(|(objectclass=groupofuniquenames)(objectclass=groupofurls))',
      'name_attr' => 'cn',
    ),
    'customers' => array(
      'name'    => 'Customers',
      'scope'   => 'sub',
      'base_dn' => 'ou=Customers,dc=mydomain,dc=com',
      'filter'  => '(objectClass=inetOrgPerson)',
      'name_attr' => 'sn',
    ),
  ),
);
*/

// An ordered array of the ids of the addressbooks that should be searched
// when populating address autocomplete fields server-side. ex: array('sql','Verisign');
$config['autocomplete_addressbooks'] = array('sql');

// The minimum number of characters required to be typed in an autocomplete field
// before address books will be searched. Most useful for LDAP directories that
// may need to do lengthy results building given overly-broad searches
$config['autocomplete_min_length'] = 1;

// Number of parallel autocomplete requests.
// If there's more than one address book, n parallel (async) requests will be created,
// where each request will search in one address book. By default (0), all address
// books are searched in one request.
$config['autocomplete_threads'] = 0;

// Max. numer of entries in autocomplete popup. Default: 15.
$config['autocomplete_max'] = 15;

// show address fields in this order
// available placeholders: {street}, {locality}, {zipcode}, {country}, {region}
$config['address_template'] = '{street}<br/>{locality} {zipcode}<br/>{country} {region}';

// Matching mode for addressbook search (including autocompletion)
// 0 - partial (*abc*), default
// 1 - strict (abc)
// 2 - prefix (abc*)
// Note: For LDAP sources fuzzy_search must be enabled to use 'partial' or 'prefix' mode
$config['addressbook_search_mode'] = 0;

// List of fields used on contacts list and for autocompletion searches
// Warning: These are field names not LDAP attributes (see 'fieldmap' setting)!
$config['contactlist_fields'] = array('name', 'firstname', 'surname', 'email');

// Template of contact entry on the autocompletion list.
// You can use contact fields as: name, email, organization, department, etc.
// See program/steps/addressbook/func.inc for a list
$config['contact_search_name'] = '{name} <{email}>';

// ----------------------------------
// USER PREFERENCES
// ----------------------------------

// Use this charset as fallback for message decoding
$config['default_charset'] = 'ISO-8859-1';

// skin name: folder from skins/
$config['skin'] = 'larry';

// Enables using standard browser windows (that can be handled as tabs)
// instead of popup windows
$config['standard_windows'] = false;

// show up to X items in messages list view
$config['mail_pagesize'] = 50;

// show up to X items in contacts list view
$config['addressbook_pagesize'] = 50;

// sort contacts by this col (preferably either one of name, firstname, surname)
$config['addressbook_sort_col'] = 'surname';

// the way how contact names are displayed in the list
// 0: display name
// 1: (prefix) firstname middlename surname (suffix)
// 2: (prefix) surname firstname middlename (suffix)
// 3: (prefix) surname, firstname middlename (suffix)
$config['addressbook_name_listing'] = 0;

// use this timezone to display date/time
// valid timezone identifers are listed here: php.net/manual/en/timezones.php
// 'auto' will use the browser's timezone settings
$config['timezone'] = 'auto';

// prefer displaying HTML messages
$config['prefer_html'] = true;

// display remote inline images
// 0 - Never, always ask
// 1 - Ask if sender is not in address book
// 2 - Always show inline images
$config['show_images'] = 0;

// open messages in new window
$config['message_extwin'] = false;

// open message compose form in new window
$config['compose_extwin'] = false;

// compose html formatted messages by default
//  0 - never,
//  1 - always,
//  2 - on reply to HTML message,
//  3 - on forward or reply to HTML message
//  4 - always, except when replying to plain text message
$config['htmleditor'] = 0;

// save copies of compose messages in the browser's local storage
// for recovery in case of browser crashes and session timeout.
$config['compose_save_localstorage'] = true;

// show pretty dates as standard
$config['prettydate'] = true;

// save compose message every 300 seconds (5min)
$config['draft_autosave'] = 300;

// Interface layout. Default: 'widescreen'.
//  'widescreen' - three columns
//  'desktop'    - two columns, preview on bottom
//  'list'       - two columns, no preview
$config['layout'] = 'widescreen';

// Mark as read when viewing a message (delay in seconds)
// Set to -1 if messages should not be marked as read
$config['mail_read_time'] = 0;

// Clear Trash on logout
$config['logout_purge'] = false;

// Compact INBOX on logout
$config['logout_expunge'] = false;

// Display attached images below the message body 
$config['inline_images'] = true;

// Encoding of long/non-ascii attachment names:
// 0 - Full RFC 2231 compatible
// 1 - RFC 2047 for 'name' and RFC 2231 for 'filename' parameter (Thunderbird's default)
// 2 - Full 2047 compatible
$config['mime_param_folding'] = 1;

// Set true if deleted messages should not be displayed
// This will make the application run slower
$config['skip_deleted'] = false;

// Set true to Mark deleted messages as read as well as deleted
// False means that a message's read status is not affected by marking it as deleted
$config['read_when_deleted'] = true;

// Set to true to never delete messages immediately
// Use 'Purge' to remove messages marked as deleted
$config['flag_for_deletion'] = false;

// Default interval for auto-refresh requests (in seconds)
// These are requests for system state updates e.g. checking for new messages, etc.
// Setting it to 0 disables the feature.
$config['refresh_interval'] = 60;

// If true all folders will be checked for recent messages
$config['check_all_folders'] = false;

// If true, after message delete/move, the next message will be displayed
$config['display_next'] = true;

// Default messages listing mode. One of 'threads' or 'list'.
$config['default_list_mode'] = 'list';

// 0 - Do not expand threads
// 1 - Expand all threads automatically
// 2 - Expand only threads with unread messages
$config['autoexpand_threads'] = 0;

// When replying:
// -1 - don't cite the original message
// 0  - place cursor below the original message
// 1  - place cursor above original message (top posting)
$config['reply_mode'] = 0;

// When replying strip original signature from message
$config['strip_existing_sig'] = true;

// Show signature:
// 0 - Never
// 1 - Always
// 2 - New messages only
// 3 - Forwards and Replies only
$config['show_sig'] = 1;

// By default the signature is placed depending on cursor position (reply_mode).
// Sometimes it might be convenient to start the reply on top but keep
// the signature below the quoted text (sig_below = true).
$config['sig_below'] = false;

// Enables adding of standard separator to the signature
$config['sig_separator'] = true;

// Use MIME encoding (quoted-printable) for 8bit characters in message body
$config['force_7bit'] = false;

// Defaults of the search field configuration.
// The array can contain a per-folder list of header fields which should be considered when searching
// The entry with key '*' stands for all folders which do not have a specific list set.
// Please note that folder names should to be in sync with $config['*_mbox'] options
$config['search_mods'] = null;  // Example: array('*' => array('subject'=>1, 'from'=>1), 'Sent' => array('subject'=>1, 'to'=>1));

// Defaults of the addressbook search field configuration.
$config['addressbook_search_mods'] = null;  // Example: array('name'=>1, 'firstname'=>1, 'surname'=>1, 'email'=>1, '*'=>1);

// 'Delete always'
// This setting reflects if mail should be always deleted
// when moving to Trash fails. This is necessary in some setups
// when user is over quota and Trash is included in the quota.
$config['delete_always'] = false;

// Directly delete messages in Junk instead of moving to Trash
$config['delete_junk'] = false;

// Behavior if a received message requests a message delivery notification (read receipt)
// 0 = ask the user, 1 = send automatically, 2 = ignore (never send or ask)
// 3 = send automatically if sender is in addressbook, otherwise ask the user
// 4 = send automatically if sender is in addressbook, otherwise ignore
$config['mdn_requests'] = 0;

// Return receipt checkbox default state
$config['mdn_default'] = 0;

// Delivery Status Notification checkbox default state
// Note: This can be used only if smtp_server is non-empty
$config['dsn_default'] = 0;

// Place replies in the folder of the message being replied to
$config['reply_same_folder'] = false;

// Sets default mode of Forward feature to "forward as attachment"
$config['forward_attachment'] = false;

// Defines address book (internal index) to which new contacts will be added
// By default it is the first writeable addressbook.
// Note: Use '0' for built-in address book.
$config['default_addressbook'] = null;

// Enables spell checking before sending a message.
$config['spellcheck_before_send'] = false;

// Skip alternative email addresses in autocompletion (show one address per contact)
$config['autocomplete_single'] = false;

// Default font for composed HTML message.
// Supported values: Andale Mono, Arial, Arial Black, Book Antiqua, Courier New,
// Georgia, Helvetica, Impact, Tahoma, Terminal, Times New Roman, Trebuchet MS, Verdana
$config['default_font'] = 'Verdana';

// Default font size for composed HTML message.
// Supported sizes: 8pt, 10pt, 12pt, 14pt, 18pt, 24pt, 36pt
$config['default_font_size'] = '10pt';

// Enables display of email address with name instead of a name (and address in title)
$config['message_show_email'] = false;

// Default behavior of Reply-All button:
// 0 - Reply-All always
// 1 - Reply-List if mailing list is detected
$config['reply_all_mode'] = 0;
