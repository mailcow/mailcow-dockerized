<?php

require_once(__DIR__ . '/../web/inc/vars.inc.php');
if (file_exists(__DIR__ . '/../web/inc/vars.local.inc.php')) {
  include_once(__DIR__ . '/../web/inc/vars.local.inc.php');
}
require_once __DIR__ . '/../web/inc/lib/vendor/autoload.php';

$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
} catch (PDOException $e) {
  fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
  exit(1);
}

$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  } else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv("REDISPASS"));
} catch (Exception $e) {
  fwrite(STDERR, "Redis connect failed: " . $e->getMessage() . "\n");
  exit(1);
}

function logMsg($priority, $message, $task = "Identity Sync") {
  global $redis;
  $redis->lPush('CRON_LOG', json_encode(array(
    "time" => time(),
    "priority" => $priority,
    "task" => $task,
    "message" => $message,
  )));
}

require_once __DIR__ . '/../web/inc/functions.inc.php';
require_once __DIR__ . '/../web/inc/functions.auth.inc.php';
require_once __DIR__ . '/../web/inc/sessions.inc.php';
require_once __DIR__ . '/../web/inc/functions.mailbox.inc.php';
require_once __DIR__ . '/../web/inc/functions.ratelimit.inc.php';
require_once __DIR__ . '/../web/inc/functions.acl.inc.php';

// The legacy mailbox functions run as admin
$_SESSION['mailcow_cc_username'] = "admin";
$_SESSION['mailcow_cc_role'] = "admin";
$_SESSION['acl']['tls_policy'] = "1";
$_SESSION['acl']['quarantine_notification'] = "1";
$_SESSION['acl']['quarantine_category'] = "1";
$_SESSION['acl']['ratelimit'] = "1";
$_SESSION['acl']['sogo_access'] = "1";
$_SESSION['acl']['protocol_access'] = "1";
$_SESSION['acl']['mailbox_relayhost'] = "1";
$_SESSION['acl']['unlimited_quota'] = "1";

// One run at a time, released when the process ends
$lock = fopen('/tmp/identity-sync.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
  exit(0);
}

// Users, offboarding and groups of Keycloak or LDAP; requested runs first, else every sync_interval minutes.
// The IdP is only contacted when a run is due.
$iam_settings = identity_provider('get');
$identity = \Mailcow\Identity\IdentityModule::create($pdo, \Mailcow\Core\Context::system(), $iam_settings, function () {
  return identity_provider('init');
});
$run = $identity->sync->runDue();
if ($run !== null) {
  $summary = $run['summary'];
  if ($run['status'] == 'error') {
    logMsg("err", ucfirst($run['mode']) . " failed: " . $summary['error']);
  } else {
    $counts = array();
    foreach ($summary['counts'] as $key => $count) {
      if ($count > 0) $counts[] = $key . "=" . $count;
    }
    logMsg(($run['status'] == 'blocked') ? "warning" : "info", ucfirst($run['mode']) . " " . $run['status'] . ": " . (empty($counts) ? "no changes" : implode(", ", $counts)));
    foreach ($summary['details']['conflicts'] as $conflict) {
      logMsg("warning", $conflict);
    }
    foreach ($summary['errors'] ?? array() as $error) {
      logMsg("err", $error);
    }
  }
}
session_destroy();
