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

function logMsg($priority, $message, $task = "imapsync OAuth refresh") {
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
require_once __DIR__ . '/../web/inc/functions.syncjob.inc.php';

// File-lock to prevent concurrent runs
$lock_file = '/tmp/imapsync-oauth-refresh.lock';
if (file_exists($lock_file)) {
  $pid = (int)trim(@file_get_contents($lock_file));
  if ($pid > 0 && posix_kill($pid, 0)) {
    logMsg("info", "Previous refresh still running (pid=$pid), exiting");
    exit(0);
  }
  @unlink($lock_file);
}
file_put_contents($lock_file, getmypid());

try {
  // 1) client_credentials sources: refresh the shared source-level token (expires < 5 min or none)
  $stmt = $pdo->prepare("SELECT `id`, `name` FROM `imapsync_source`
    WHERE `active` = 1 AND `auth_type` = 'XOAUTH2' AND `oauth_flow` = 'client_credentials'
      AND (`oauth_token_expires` IS NULL OR `oauth_token_expires` < :soon)");
  $stmt->execute(array(':soon' => time() + 300));
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $src) {
    $ok = imapsync_source_refresh_token_internal($src['id']);
    if ($ok) {
      logMsg("info", "Refreshed token for source '" . $src['name'] . "' (id=" . $src['id'] . ")");
    } else {
      $e = $pdo->prepare("SELECT `oauth_last_refresh_error` FROM `imapsync_source` WHERE `id` = :id");
      $e->execute(array(':id' => $src['id']));
      logMsg("warning", "Refresh failed for source '" . $src['name'] . "' (id=" . $src['id'] . "): " . $e->fetchColumn());
    }
  }

  // 2) authorization_code sources: refresh each per-user token via its stored refresh_token
  $stmt = $pdo->prepare("SELECT t.`source_id`, t.`username`, s.`name`
    FROM `imapsync_source_oauth_token` t
    JOIN `imapsync_source` s ON s.`id` = t.`source_id`
    WHERE s.`active` = 1 AND s.`auth_type` = 'XOAUTH2' AND s.`oauth_flow` = 'authorization_code'
      AND t.`refresh_token` <> ''
      AND (t.`token_expires` IS NULL OR t.`token_expires` < :soon)");
  $stmt->execute(array(':soon' => time() + 300));
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tok) {
    $ok = imapsync_source_refresh_user_token_internal($tok['source_id'], $tok['username']);
    if ($ok) {
      logMsg("info", "Refreshed user token '" . $tok['username'] . "' on source '" . $tok['name'] . "' (id=" . $tok['source_id'] . ")");
    } else {
      $e = $pdo->prepare("SELECT `last_refresh_error` FROM `imapsync_source_oauth_token` WHERE `source_id` = :sid AND `username` = :user");
      $e->execute(array(':sid' => $tok['source_id'], ':user' => $tok['username']));
      logMsg("warning", "User-token refresh failed for '" . $tok['username'] . "' on source '" . $tok['name'] . "' (id=" . $tok['source_id'] . "): " . $e->fetchColumn());
    }
  }
} finally {
  @unlink($lock_file);
}
