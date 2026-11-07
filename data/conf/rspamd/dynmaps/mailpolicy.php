<?php
// Mail policy check for one recipient, asked by the Postfix policy service (mailcow-policy in master.cf)
// Request: POST with the attributes of the Postfix policy request; answer: the action (DUNNO or the rejection)
header('Content-Type: text/plain');
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
  error_log("MAILPOLICY: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

if (!isset($_POST['request']) || $_POST['request'] !== 'smtpd_access_policy') {
  http_response_code(400);
  exit;
}
$value = fn(string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';

try {
  $policy = \Mailcow\MailPolicy\MailPolicyModule::create($pdo, \Mailcow\Core\Context::system());
  echo $policy->api->check($value('sender'), $value('recipient'), $value('sasl_username'), (int)$value('server_port'));
}
catch (Throwable $e) {
  // the policy service answers with a temporary error
  error_log("MAILPOLICY: " . $e->getMessage() . PHP_EOL);
  http_response_code(500);
}
