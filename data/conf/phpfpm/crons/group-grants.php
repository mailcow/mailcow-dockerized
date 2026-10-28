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

function logMsg($priority, $message, $task = "Group Shares") {
  global $redis;
  $redis->lPush('CRON_LOG', json_encode(array(
    "time" => time(),
    "priority" => $priority,
    "task" => $task,
    "message" => $message,
  )));
}

require_once __DIR__ . '/../web/inc/functions.inc.php';
require_once __DIR__ . '/../web/inc/functions.docker.inc.php';

// One run at a time, released when the process ends
$lock = fopen('/tmp/group-grants.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
  exit(0);
}

$sharing = \Mailcow\MailboxGroup\MailboxGroupModule::create($pdo, \Mailcow\Core\Context::system())->sharing;

// Every minute: subscriptions of changed groups; failed ones are retried and logged by the full run
if ((int)date('i') % 15 !== 0) {
  $sharing->syncSubscriptions();
  exit(0);
}

// Every 15 minutes: IMAP ACLs on new top-level folders and failed grants, SOGo grants of owners that opened SOGo meanwhile
foreach ($sharing->reconcile() as $warning) {
  logMsg("warning", $warning[0] . ": " . html_entity_decode(implode(", ", array_slice($warning, 1))));
}
