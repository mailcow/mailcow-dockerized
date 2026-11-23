<?php
// SCIM 2.0 endpoint /scim/v2 (RFC 7644) for identity providers, see inc/lib/mailcow/Scim

// Requests opened in a browser are no SCIM clients
if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document') {
  http_response_code(403);
  exit;
}

require_once __DIR__ . '/inc/vars.inc.php';
if (file_exists(__DIR__ . '/inc/vars.local.inc.php')) {
  include_once __DIR__ . '/inc/vars.local.inc.php';
}
require_once __DIR__ . '/inc/lib/vendor/autoload.php';

header('Content-Type: application/scim+json');

function scim_fail(string $detail) {
  http_response_code(500);
  echo json_encode(['schemas' => [\Mailcow\Scim\Model\Schema::ERROR], 'status' => '500', 'detail' => $detail]);
  exit;
}

try {
  $pdo = new PDO($database_type . ':unix_socket=' . $database_sock . ';dbname=' . $database_name, $database_user, $database_pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (PDOException $e) {
  scim_fail('Database connection failed');
}
$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  } else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv('REDISPASS'));
} catch (Exception $e) {
  scim_fail('Cache connection failed');
}

// Stateless: no PHP session, $_SESSION is a plain array for the legacy mailbox functions, which run as admin
// (like the identity-sync cron). The token is the only authorization.
$_SESSION = [
  'mailcow_cc_username' => 'SCIM',
  'mailcow_cc_role' => 'admin',
  'acl' => array_fill_keys(['tls_policy', 'quarantine_notification', 'quarantine_category', 'ratelimit', 'sogo_access',
    'protocol_access', 'mailbox_relayhost', 'unlimited_quota'], '1'),
  'access_all_exception' => '0',
];
require_once __DIR__ . '/inc/functions.inc.php';
require_once __DIR__ . '/inc/functions.auth.inc.php';
require_once __DIR__ . '/inc/functions.mailbox.inc.php';
require_once __DIR__ . '/inc/functions.ratelimit.inc.php';
require_once __DIR__ . '/inc/functions.acl.inc.php';
// a mailbox rename moves the maildir and the SOGo user through the Docker API
require_once __DIR__ . '/inc/functions.docker.inc.php';

// The path comes from REQUEST_URI: nginx re-injects the decoded URI into the query, which breaks %2B and %26
$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = preg_match('~^/scim/v2(?:/(.*))?$~', $path, $m) ? ($m[1] ?? '') : '';
$query = $_GET;
unset($query['path']);

// Legacy functions may print; only the SCIM response is sent
ob_start();
$response = \Mailcow\Scim\ScimModule::fromGlobals()->http->handle(
  $_SERVER['REQUEST_METHOD'] ?? 'GET',
  $path,
  $query,
  // read at most one byte past the limit, the controller answers 413
  (string)file_get_contents('php://input', false, null, 0, \Mailcow\Scim\Model\Schema::MAX_PAYLOAD + 1),
  (string)($_SERVER['CONTENT_TYPE'] ?? ''),
  (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
  (string)($_SERVER['REMOTE_ADDR'] ?? ''),
  ['if-match' => $_SERVER['HTTP_IF_MATCH'] ?? null, 'if-none-match' => $_SERVER['HTTP_IF_NONE_MATCH'] ?? null]
);
ob_end_clean();

http_response_code($response->status);
foreach ($response->headers as $header) {
  header($header);
}
if ($response->body !== null) {
  echo json_encode($response->body, JSON_UNESCAPED_SLASHES);
}
