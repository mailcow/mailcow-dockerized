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
  // client_credentials source tokens, unused per-user tokens (1 day grace), authorization_code per-user tokens;
  // failing tokens are retried 15 minutes after their last attempt (C2), errors are sanitized
  $imapsync = \Mailcow\Imapsync\ImapsyncModule::create($pdo, \Mailcow\Core\Context::system(), [
    'hostname' => (string)getenv('MAILCOW_HOSTNAME'),
  ]);
  foreach ($imapsync->oauth->refreshDue() as $e) {
    logMsg($e['priority'], $e['message']);
  }
} finally {
  @unlink($lock_file);
}
