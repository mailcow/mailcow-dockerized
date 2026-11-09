<?php
// Archive decision for an accepted mail, asked by the MAILCOW_ARCHIVE symbol of rspamd
// Request: JSON {from, rcpt[], user, bcc, qid, received, sha256}; answer: JSON {archive, rcpt, sender, headers}
header('Content-Type: application/json');
require_once "vars.inc.php";
require_once __DIR__ . '/../web/inc/lib/vendor/autoload.php';
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
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
  error_log("ARCHIVE: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}
// Init Redis, it keeps the global switch; without it nothing is archived
$redis = new Redis();
try {
  $redis->connect('redis-mailcow', 6379);
  $redis->auth(getenv("REDISPASS"));
}
catch (Exception $e) {
  $redis = null;
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request)) {
  http_response_code(400);
  exit;
}

try {
  $archive = \Mailcow\Archive\ArchiveModule::create($pdo, \Mailcow\Core\Context::system(), $redis);
  echo json_encode($archive->journal->prepare($request));
}
catch (Throwable $e) {
  error_log("ARCHIVE: " . $e->getMessage() . PHP_EOL);
  http_response_code(502);
}
