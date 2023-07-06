<?php

require_once(__DIR__ . '/../web/inc/vars.inc.php');
if (file_exists(__DIR__ . '/../web/inc/vars.local.inc.php')) {
  include_once(__DIR__ . '/../web/inc/vars.local.inc.php');
}
require_once __DIR__ . '/../web/inc/lib/vendor/autoload.php';

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
  logMsg("danger", $e->getMessage());
  session_destroy();
  exit;
}

// Init Redis
$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  }
  else {
    $redis->connect('redis-mailcow', 6379);
  }
}
catch (Exception $e) {
  echo "Exiting: " . $e->getMessage();
  session_destroy();
  exit;
}

function logMsg($priority, $message, $task = "Keycloak Sync") {
  global $redis;

  $finalMsg = array(
    "time" => time(),
    "priority" => $priority,
    "task" => $task,
    "message" => $message
  );
  $redis->lPush('CRON_LOG', json_encode($finalMsg));
}

// Load core functions first
require_once __DIR__ . '/../web/inc/functions.inc.php';
require_once __DIR__ . '/../web/inc/functions.auth.inc.php';
require_once __DIR__ . '/../web/inc/sessions.inc.php';
require_once __DIR__ . '/../web/inc/functions.mailbox.inc.php';
require_once __DIR__ . '/../web/inc/functions.ratelimit.inc.php';
require_once __DIR__ . '/../web/inc/functions.acl.inc.php';

$_SESSION['mailcow_cc_username'] = "admin";
$_SESSION['mailcow_cc_role'] = "admin";
$_SESSION['acl']['tls_policy'] = "1";
$_SESSION['acl']['quarantine_notification'] = "1";
$_SESSION['acl']['quarantine_category'] = "1";
$_SESSION['acl']['ratelimit'] = "1";
$_SESSION['acl']['sogo_access'] = "1";
$_SESSION['acl']['protocol_access'] = "1";
$_SESSION['acl']['mailbox_relayhost'] = "1";

// Init Keycloak Provider
$iam_provider = identity_provider('init');
$iam_settings = identity_provider('get');
if (intval($iam_settings['periodic_sync']) != 1 && $iam_settings['import_users'] != 1) {
  logMsg("warning", "IAM Sync is disabled");
  session_destroy();
  exit;
}

// Set pagination variables
$start = 0;
$max = 25;

// lock sync if already running
$lock_file = '/tmp/iam-sync.lock';
if (file_exists($lock_file)) {
  $lock_file_parts = explode("\n", file_get_contents($lock_file));
  $pid = $lock_file_parts[0];
  if (count($lock_file_parts) > 1){
    $last_execution = $lock_file_parts[1];
    $elapsed_time = (time() - $last_execution) / 60;
    if ($elapsed_time < intval($iam_settings['sync_interval'])) {
      logMsg("warning", "Sync Interval not ready (".number_format((float)$elapsed_time, 2, '.', '')."min / ".$iam_settings['sync_interval']."min)");
      session_destroy();
      exit;
    }
  }

  if (posix_kill($pid, 0)) {
    logMsg("warning", "Sync is already running");
    session_destroy();
    exit;
  } else {
    unlink($lock_file);
  }
}
$lock_file_handle = fopen($lock_file, 'w');
fwrite($lock_file_handle, getmypid());
fclose($lock_file_handle);

// Loop until all users have been retrieved
while (true) {
  // Get admin access token
  $admin_token = identity_provider("get-keycloak-admin-token");
  
  // Make the API request to retrieve the users
  $url = "{$iam_settings['server_url']}/admin/realms/{$iam_settings['realm']}/users?first=$start&max=$max";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $admin_token
  ]);
  $response = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($code != 200){
    logMsg("danger", "Recieved HTTP {$code}");
    session_destroy();
    exit;
  }
  try {
    $response = json_decode($response, true);
  } catch (Exception $e) {
    logMsg("danger", $e->getMessage());
    break;
  }
  if (!is_array($response)){
    logMsg("danger", "Recieved malformed response from keycloak api");
    break;
  }
  if (count($response) == 0) {
    break;
  }

  // Process the batch of users
  foreach ($response as $user) {
    if (empty($user['email'])){
      logMsg("warning", "No email address in keycloak found for user " . $user['name']);
      continue;
    }
    if (!isset($user['attributes'])){
      logMsg("warning", "No attributes in keycloak found for user " . $user['email']);
      continue;
    }
    if (count($user['attributes']['mailcow_template']) == 0) {
      logMsg("warning", "No mailcow_template in keycloak found for user " . $user['email']);
      continue;
    };
    $mailcow_template = $user['attributes']['mailcow_template'];

    // try get mailbox user
    $stmt = $pdo->prepare("SELECT `mailbox`.* FROM `mailbox`
    INNER JOIN domain on mailbox.domain = domain.domain
    WHERE `kind` NOT REGEXP 'location|thing|group'
      AND `domain`.`active`='1'
      AND `username` = :user");
    $stmt->execute(array(':user' => $user['email']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // check if matching attribute mapping exists
    $mbox_template = null;
    foreach ($iam_settings['mappers'] as $index => $mapper){
      if (in_array($mapper, $user['attributes']['mailcow_template'])) {
        $mbox_template = $mapper;
        break;
      }
    }
    if (!$mbox_template){
      logMsg("warning", "No matching mapper found for mailbox_template");
      continue;
    }

    if (!$row && intval($iam_settings['import_users']) == 1){
      // mailbox user does not exist, create...
      logMsg("info", "Creating user " . $user['email']);
      mailbox('add', 'mailbox_from_template', array(
        'domain' => explode('@', $user['email'])[1],
        'local_part' => explode('@', $user['email'])[0],
        'authsource' => 'keycloak',
        'template' => $mbox_template
      ));
    } else if ($row) {
      // mailbox user does exist, sync attribtues...
      logMsg("info", "Syncing attributes for user " . $user['email']);
      mailbox('edit', 'mailbox_from_template', array(
        'username' => $user['email'],
        'template' => $mbox_template
      ));
    } else {
      // skip mailbox user
      logMsg("info", "Skipping user " . $user['email']);
    }

    sleep(0.025);
  }
  
  // Update the pagination variables for the next batch
  $start += $max;
  sleep(1);
}

logMsg("info", "DONE!");
// add last execution time to lock file
$lock_file_handle = fopen($lock_file, 'w');
fwrite($lock_file_handle, getmypid() . "\n" . time());
fclose($lock_file_handle);
session_destroy();
