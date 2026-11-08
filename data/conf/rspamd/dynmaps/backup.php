<?php
// Backup with restic: endpoint of the runner in the restic container (Mailcow\Backup)
// GET ?domains=1: {"domains": [...]}, all domains
// GET ?export=<domain>[&after=<username>]: a page of the export of a domain (the first with domain_data and aliases,
//   every page with a part of the mailboxes and "next_after", the last username to ask for the next page with, null
//   after the last), incl. password hashes, DKIM keys and secrets; the runner merges the pages into
//   /mailcow/export/<domain>.json of the snapshot.
// POST {"action": "restore_domain", "job": <id>, "data": <domain_data>}: recreates a deleted domain
// POST {"action": "restore_aliases", "job": <id>, "aliases": [...]}: a part (at most 500) of the aliases of a domain restore
// POST {"action": "restore", "job": <id>, "username": <mailbox>, "data": <its section of the export>}: one mailbox
//   (created first when it was deleted); all answer {"result": {<component>: {"added", "skipped", "errors"}}}
// All need the header X-Backup-Token: HMAC-SHA256 of "backup" with the database password as key
header('Content-Type: application/json');
require_once "vars.inc.php";
require_once __DIR__ . '/../web/inc/lib/vendor/autoload.php';
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
if (empty($database_pass) || !hash_equals(hash_hmac('sha256', 'backup', $database_pass), (string)($_SERVER['HTTP_X_BACKUP_TOKEN'] ?? ''))) {
  http_response_code(403);
  exit;
}
// Init database
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
  error_log("BACKUP: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

try {
  // DKIM keys, rate limits and the domain map are kept in Redis
  $redis = new Redis();
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  }
  else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv("REDISPASS"));
  $config = array(
    'timezone' => getenv('TZ') ?: 'UTC',
    'alias_disable_external_domains' => ($ALIAS_DISABLE_EXTERNAL_DOMAINS ?? false) === true,
  );
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
      http_response_code(400);
      exit;
    }
    // The restore has the side effects of the UI: SOGo view and restart, Dovecot auth cache, group shares (legacy code)
    require_once __DIR__ . '/../web/inc/functions.inc.php';
    require_once __DIR__ . '/../web/inc/functions.auth.inc.php';
    require_once __DIR__ . '/../web/inc/functions.docker.inc.php';
    require_once __DIR__ . '/../web/inc/functions.mailbox.inc.php';
    require_once __DIR__ . '/../web/inc/functions.ratelimit.inc.php';
    require_once __DIR__ . '/../web/inc/functions.acl.inc.php';
    // The legacy mailbox functions run as admin, without a session
    $_SESSION = array(
      'mailcow_cc_username' => 'admin',
      'mailcow_cc_role' => 'admin',
      'access_all_exception' => '0',
      'acl' => array_fill_keys(array('tls_policy', 'quarantine_notification', 'quarantine_category', 'ratelimit', 'sogo_access',
        'protocol_access', 'mailbox_relayhost', 'unlimited_quota'), '1'),
    );
    $backup = \Mailcow\Backup\BackupModule::create($pdo, \Mailcow\Core\Context::system(), $config, $redis);
    echo json_encode($backup->api->post($body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  else {
    $backup = \Mailcow\Backup\BackupModule::create($pdo, \Mailcow\Core\Context::system(), $config, $redis);
    if (isset($_GET['export'])) {
      echo json_encode($backup->api->export((string)$_GET['export'], (isset($_GET['after']) && is_string($_GET['after'])) ? $_GET['after'] : null),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    elseif (isset($_GET['domains'])) {
      echo json_encode($backup->api->domains());
    }
    else {
      http_response_code(400);
    }
  }
}
catch (\Mailcow\Core\UserError $e) {
  http_response_code(($e->key === 'domain_not_found') ? 404 : 400);
  echo json_encode(array('error' => $e->key, 'args' => $e->args));
}
catch (Throwable $e) {
  error_log("BACKUP: " . $e->getMessage() . PHP_EOL);
  http_response_code(500);
}
