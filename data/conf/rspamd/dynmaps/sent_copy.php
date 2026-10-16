<?php
// File size is limited by Nginx site to 10M
// To speed things up, we do not include prerequisites
header('Content-Type: text/plain');
require_once "vars.inc.php";
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
// Init database
//$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
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
  error_log("SENT COPY SQL ERROR: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

if (!function_exists('getallheaders'))  {
  function getallheaders() {
    if (!is_array($_SERVER)) {
      return array();
    }
    $headers = array();
    foreach ($_SERVER as $name => $value) {
      if (substr($name, 0, 5) == 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
      }
    }
    return $headers;
  }
}

// Read headers
$headers = getallheaders();
// Get authenticated SASL username
$username = strtolower(trim($headers['Username']));

if (empty($username) || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
    WHERE `username` = :username
      AND `kind` = ''
      AND `active` = '1'
      AND JSON_UNQUOTE(JSON_VALUE(`attributes`, '$.save_sent_copy')) = '1'");
  $stmt->execute(array(
    ':username' => $username
  ));
  $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!empty($mailbox['username'])) {
    // 201 = mailbox wants a copy of sent mail, body contains the mailbox to deliver the copy to
    http_response_code(201);
    echo trim($mailbox['username']);
    exit;
  }
}
catch (PDOException $e) {
  error_log("SENT COPY SQL ERROR: " . $e->getMessage() . PHP_EOL);
  http_response_code(502);
  exit;
}

http_response_code(200);
