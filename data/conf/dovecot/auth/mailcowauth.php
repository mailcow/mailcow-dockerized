<?php
ini_set('error_reporting', 0);
header('Content-Type: application/json');

$post = trim(file_get_contents('php://input'));
if ($post) {
  $post = json_decode($post, true);
}


$return = array("success" => false);
if(!isset($post['username']) || !isset($post['password']) || !isset($post['real_rip'])){
  error_log("MAILCOWAUTH: Bad Request");
  http_response_code(400); // Bad Request
  echo json_encode($return); 
  exit();
}

require_once('../../../web/inc/vars.inc.php');
if (file_exists('../../../web/inc/vars.local.inc.php')) {
  include_once('../../../web/inc/vars.local.inc.php');
}
require_once '../../../web/inc/lib/vendor/autoload.php';

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
  error_log("MAILCOWAUTH: " . $e . PHP_EOL);
  http_response_code(500); // Internal Server Error
  echo json_encode($return); 
  exit;
}

// Load core functions first
require_once 'functions.inc.php';
require_once 'functions.auth.inc.php';
require_once 'sessions.inc.php';
require_once 'functions.mailbox.inc.php';

// Init provider
$iam_provider = identity_provider('init');


$protocol = $post['protocol'];
if ($post['real_rip'] == getenv('IPV4_NETWORK') . '.248') {
  $protocol = null;
}
$result = user_login($post['username'], $post['password'], $protocol, array('is_internal' => true));
if ($result === false){
  $result = apppass_login($post['username'], $post['password'], $protocol, array(
    'is_internal' => true,
    'remote_addr' => $post['real_rip']
  ));
}

if ($result) {
  http_response_code(200); // OK
  $return['success'] = true;
} else {
  error_log("MAILCOWAUTH: Login failed for user " . $post['username']);
  http_response_code(401); // Unauthorized
}


echo json_encode($return); 
session_destroy();
exit;
