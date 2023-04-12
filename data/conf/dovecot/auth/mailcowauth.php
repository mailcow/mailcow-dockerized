<?php
header('Content-Type: application/json');

$post = trim(file_get_contents('php://input'));
if ($post) {
  $post = json_decode($post, true);
}

$return = array("success" => false, "role" => false);
if(!isset($post['username']) || !isset($post['password'])){
  echo json_encode($return); 
  exit();
}

require_once('../../../web/inc/vars.inc.php');
if (file_exists('../../../web/inc/vars.local.inc.php')) {
  include_once('../../../web/inc/vars.local.inc.php');
}
require_once '../../../web/inc/lib/vendor/autoload.php';

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
  error_log("MAILCOWAUTH: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

// Load core functions first
require_once 'functions.inc.php';
require_once 'functions.auth.inc.php';
require_once 'sessions.inc.php';

// Init Keycloak Provider
$iam_provider = identity_provider('init');

$result = check_login($post['username'], $post['password'], $post['protocol'], true);
if ($result) {
  $return = array("success" => true, "role" => $result);
}

echo json_encode($return); 
exit();
