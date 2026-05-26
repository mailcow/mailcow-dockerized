<?php
// Server-side per-mailbox signatures.
// Looks up the highest-priority active signature template for the calling mailbox
// and returns rendered html/plain to the rspamd Lua postfilter.
header('Content-Type: application/json');
require_once "vars.inc.php";
ini_set('error_reporting', 0);

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
  error_log("SIGNATURE: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

// /web is mounted into the php-fpm container alongside /dynmaps (see docker-compose.yml).
require_once '/web/inc/functions.signatures.inc.php';

if (!function_exists('getallheaders')) {
  function getallheaders() {
    if (!is_array($_SERVER)) return array();
    $headers = array();
    foreach ($_SERVER as $name => $value) {
      if (substr($name, 0, 5) == 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
      }
    }
    return $headers;
  }
}

$empty = json_encode(array('html' => '', 'plain' => '', 'skip_replies' => 0));
$headers = getallheaders();
$username = strtolower(trim($headers['Username'] ?? ''));
$domain = strtolower(trim($headers['Domain'] ?? ''));
$from = strtolower(trim($headers['From'] ?? ''));

if ($username === '' || strpos($username, '@') === false) {
  echo $empty;
  exit;
}

try {
  // Resolve alias_domain → target_domain so signatures attached to the canonical
  // mailbox domain still apply when the mail is sent through an alias domain.
  $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :d");
  $stmt->execute(array(':d' => $domain));
  $alias = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($alias) {
    list($lp,) = explode('@', $username, 2);
    $resolved_username = $lp . '@' . $alias['target_domain'];
  } else {
    $resolved_username = $username;
  }

  $sig = signature_resolve_for_mailbox($resolved_username);
  if (!$sig) {
    echo $empty;
    exit;
  }
  echo json_encode(array(
    'html' => $sig['html'],
    'plain' => $sig['plain'],
    'skip_replies' => (int)$sig['skip_replies'],
    'template_id' => (int)$sig['template_id'],
  ));
}
catch (Exception $e) {
  error_log("SIGNATURE: " . $e->getMessage() . PHP_EOL);
  http_response_code(502);
  exit;
}
