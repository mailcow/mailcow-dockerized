<?php

require_once(__DIR__ . '/../web/inc/vars.inc.php');
if (file_exists(__DIR__ . '/../web/inc/vars.local.inc.php')) {
  include_once(__DIR__ . '/../web/inc/vars.local.inc.php');
}
require_once __DIR__ . '/../web/inc/lib/vendor/autoload.php';

$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
  fwrite(STDERR, "[demo-seed] Database connection failed: {$e->getMessage()}\n");
  exit(1);
}

$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  }
  else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv('REDISPASS'));
}
catch (Exception $e) {
  fwrite(STDERR, "[demo-seed] Redis connection failed: {$e->getMessage()}\n");
  exit(1);
}

require_once __DIR__ . '/../web/inc/functions.inc.php';
require_once __DIR__ . '/../web/inc/functions.auth.inc.php';
require_once __DIR__ . '/../web/inc/sessions.inc.php';
require_once __DIR__ . '/../web/inc/functions.mailbox.inc.php';
require_once __DIR__ . '/../web/inc/functions.ratelimit.inc.php';
require_once __DIR__ . '/../web/inc/functions.acl.inc.php';
require_once __DIR__ . '/../web/inc/functions.admin.inc.php';
require_once __DIR__ . '/../web/inc/functions.domain_admin.inc.php';

function demo_log(string $message): void {
  echo "[demo-seed] {$message}\n";
}

function env_value(string $name, ?string $default = null): ?string {
  $value = getenv($name);
  if ($value === false) {
    return $default;
  }

  $value = trim($value);
  return $value === '' ? $default : $value;
}

function clear_return_messages(): void {
  $_SESSION['return'] = [];
}

function collect_return_messages(): string {
  $messages = [];
  foreach ($_SESSION['return'] ?? [] as $entry) {
    $msg = $entry['msg'] ?? 'unknown';
    if (is_array($msg)) {
      $msg = implode(': ', array_map('strval', $msg));
    }
    $messages[] = sprintf('%s (%s)', (string) $msg, $entry['type'] ?? 'info');
  }
  return empty($messages) ? 'no details' : implode('; ', $messages);
}

function disable_tfa_for_user(PDO $pdo, string $username): void {
  $stmt = $pdo->prepare("UPDATE `tfa` SET `active` = 0 WHERE `username` = :username");
  $stmt->execute([':username' => $username]);
}

$demoDomain = strtolower((string) env_value('DEMO_DOMAIN', ''));
$demoAdminUser = strtolower((string) env_value('DEMO_ADMIN_USER', 'admin'));
$demoAdminPass = env_value('DEMO_ADMIN_PASS', '');
$demoDomainAdminUser = strtolower((string) env_value('DEMO_DOMAIN_ADMIN_USER', ''));
$demoDomainAdminPass = env_value('DEMO_DOMAIN_ADMIN_PASS', '');
$demoUserEmail = strtolower((string) env_value('DEMO_USER_EMAIL', ''));
$demoUserName = (string) env_value('DEMO_USER_NAME', 'Demo User');
$demoUserPass = env_value('DEMO_USER_PASS', '');

$requiredValues = [
  'DEMO_DOMAIN' => $demoDomain,
  'DEMO_ADMIN_PASS' => $demoAdminPass,
  'DEMO_DOMAIN_ADMIN_USER' => $demoDomainAdminUser,
  'DEMO_DOMAIN_ADMIN_PASS' => $demoDomainAdminPass,
  'DEMO_USER_EMAIL' => $demoUserEmail,
  'DEMO_USER_PASS' => $demoUserPass,
];

$missingValues = [];
foreach ($requiredValues as $name => $value) {
  if ($value === '') {
    $missingValues[] = $name;
  }
}

if (!empty($missingValues)) {
  demo_log('Skipping demo account seed because these env vars are missing: ' . implode(', ', $missingValues));
  session_destroy();
  exit(0);
}

if (!filter_var($demoUserEmail, FILTER_VALIDATE_EMAIL)) {
  demo_log('Skipping demo account seed because DEMO_USER_EMAIL is invalid.');
  session_destroy();
  exit(1);
}

$demoUserDomain = substr(strrchr($demoUserEmail, '@'), 1);
if ($demoUserDomain !== $demoDomain) {
  demo_log('Skipping demo account seed because DEMO_USER_EMAIL must belong to DEMO_DOMAIN.');
  session_destroy();
  exit(1);
}

$_SESSION['mailcow_cc_username'] = 'admin';
$_SESSION['mailcow_cc_role'] = 'admin';
$_SESSION['access_all_exception'] = '1';
$_SESSION['acl']['tls_policy'] = '1';
$_SESSION['acl']['quarantine_notification'] = '1';
$_SESSION['acl']['quarantine_category'] = '1';
$_SESSION['acl']['ratelimit'] = '1';
$_SESSION['acl']['sogo_access'] = '1';
$_SESSION['acl']['protocol_access'] = '1';
$_SESSION['acl']['mailbox_relayhost'] = '1';
$_SESSION['acl']['domain_relayhost'] = '1';
$_SESSION['acl']['login_as'] = '1';
$_SESSION['acl']['unlimited_quota'] = '1';

clear_return_messages();

$domainDetails = mailbox('get', 'domain_details', $demoDomain);
if ($domainDetails === false) {
  $domainCreated = mailbox('add', 'domain', [
    'domain' => $demoDomain,
    'description' => 'Demo domain',
    'aliases' => 25,
    'mailboxes' => 25,
    'defquota' => 512,
    'maxquota' => 2048,
    'quota' => 10240,
    'active' => 1,
    'gal' => 1,
    'key_size' => 0,
    'dkim_selector' => '',
    'restart_sogo' => 0,
  ]);

  if ($domainCreated === false) {
    demo_log('Failed to create demo domain: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Created demo domain {$demoDomain}");
  clear_return_messages();
}
else {
  demo_log("Using existing demo domain {$demoDomain}");
}

$adminDetails = admin('details', $demoAdminUser);
clear_return_messages();
if ($adminDetails === false) {
  $adminCreated = admin('add', [
    'username' => $demoAdminUser,
    'password' => $demoAdminPass,
    'password2' => $demoAdminPass,
    'active' => 1,
  ]);

  if ($adminCreated === false) {
    demo_log('Failed to create demo admin: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Created demo admin {$demoAdminUser}");
}
else {
  $adminUpdated = admin('edit', [
    'username' => $demoAdminUser,
    'password' => $demoAdminPass,
    'password2' => $demoAdminPass,
    'active' => 1,
    'force_tfa' => 0,
    'force_pw_update' => 0,
    'disable_tfa' => 1,
  ]);

  if ($adminUpdated === false) {
    demo_log('Failed to update demo admin: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Updated demo admin {$demoAdminUser}");
}
disable_tfa_for_user($pdo, $demoAdminUser);
clear_return_messages();

$domainAdminDetails = domain_admin('details', $demoDomainAdminUser);
clear_return_messages();
if ($domainAdminDetails === false) {
  $domainAdminCreated = domain_admin('add', [
    'username' => $demoDomainAdminUser,
    'password' => $demoDomainAdminPass,
    'password2' => $demoDomainAdminPass,
    'domains' => [$demoDomain],
    'active' => 1,
  ]);

  if ($domainAdminCreated === false) {
    demo_log('Failed to create demo domain admin: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Created demo domain admin {$demoDomainAdminUser}");
}
else {
  $domainAdminDomains = $domainAdminDetails['selected_domains'] ?? [];
  if (!in_array($demoDomain, $domainAdminDomains, true)) {
    $domainAdminDomains[] = $demoDomain;
  }

  $domainAdminUpdated = domain_admin('edit', [
    'username' => $demoDomainAdminUser,
    'username_new' => $demoDomainAdminUser,
    'password' => $demoDomainAdminPass,
    'password2' => $demoDomainAdminPass,
    'domains' => $domainAdminDomains,
    'active' => 1,
    'force_tfa' => 0,
    'force_pw_update' => 0,
    'disable_tfa' => 1,
  ]);

  if ($domainAdminUpdated === false) {
    demo_log('Failed to update demo domain admin: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Updated demo domain admin {$demoDomainAdminUser}");
}
disable_tfa_for_user($pdo, $demoDomainAdminUser);
clear_return_messages();

$demoUserLocalPart = strstr($demoUserEmail, '@', true);
$mailboxDetails = mailbox('get', 'mailbox_details', $demoUserEmail);
clear_return_messages();
if ($mailboxDetails === false) {
  $mailboxCreated = mailbox('add', 'mailbox', [
    'domain' => $demoDomain,
    'local_part' => $demoUserLocalPart,
    'name' => $demoUserName,
    'password' => $demoUserPass,
    'password2' => $demoUserPass,
    'quota' => 512,
    'active' => 1,
    'force_tfa' => 0,
    'force_pw_update' => 0,
    'sogo_access' => 1,
    'imap_access' => 1,
    'pop3_access' => 1,
    'smtp_access' => 1,
    'sieve_access' => 1,
    'eas_access' => 1,
    'dav_access' => 1,
    'authsource' => 'mailcow',
  ]);

  if ($mailboxCreated === false) {
    demo_log('Failed to create demo mailbox user: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Created demo mailbox user {$demoUserEmail}");
}
else {
  $mailboxUpdated = mailbox('edit', 'mailbox', [
    'username' => $demoUserEmail,
    'name' => $demoUserName,
    'password' => $demoUserPass,
    'password2' => $demoUserPass,
    'quota' => 512,
    'active' => 1,
    'force_tfa' => 0,
    'force_pw_update' => 0,
    'sogo_access' => 1,
    'imap_access' => 1,
    'pop3_access' => 1,
    'smtp_access' => 1,
    'sieve_access' => 1,
    'eas_access' => 1,
    'dav_access' => 1,
    'authsource' => 'mailcow',
  ]);

  if ($mailboxUpdated === false) {
    demo_log('Failed to update demo mailbox user: ' . collect_return_messages());
    session_destroy();
    exit(1);
  }

  demo_log("Updated demo mailbox user {$demoUserEmail}");
}
disable_tfa_for_user($pdo, $demoUserEmail);
clear_return_messages();

demo_log('Demo accounts are ready.');
session_destroy();
exit(0);
