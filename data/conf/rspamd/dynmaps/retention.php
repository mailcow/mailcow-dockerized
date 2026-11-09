<?php
// Retention rules for the nightly run in the dovecot container (retention_runner.py)
// GET ?after=<username>&limit=<n>: a page of the plan, per mailbox the folders to clean up (JSON)
// POST (JSON {"results": [...], "summary": [...], "run": {...}}): the results
// Both need the header X-Retention-Token: HMAC-SHA256 of "retention" with the database password as key
header('Content-Type: application/json');
require_once "vars.inc.php";
require_once __DIR__ . '/../web/inc/lib/vendor/autoload.php';
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
if (empty($database_pass) || !hash_equals(hash_hmac('sha256', 'retention', $database_pass), (string)($_SERVER['HTTP_X_RETENTION_TOKEN'] ?? ''))) {
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
  error_log("RETENTION: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

try {
  $retention = \Mailcow\Retention\RetentionModule::create($pdo, \Mailcow\Core\Context::system());
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body) || !is_array($body['results'] ?? null)) {
      http_response_code(400);
      exit;
    }
    echo json_encode(['stored' => $retention->api->report($body['results'], is_array($body['summary'] ?? null) ? $body['summary'] : [],
      is_array($body['run'] ?? null) ? $body['run'] : null)]);
  }
  else {
    echo json_encode($retention->api->plan((string)($_GET['after'] ?? ''), (int)($_GET['limit'] ?? 0)));
  }
}
catch (\Mailcow\Core\UserError $e) {
  http_response_code(400);
}
catch (Throwable $e) {
  error_log("RETENTION: " . $e->getMessage() . PHP_EOL);
  http_response_code(500);
}
