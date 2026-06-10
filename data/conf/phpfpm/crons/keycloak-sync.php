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
  logMsg("err", $e->getMessage());
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
  $redis->auth(getenv("REDISPASS"));
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
$_SESSION['acl']['unlimited_quota'] = "1";

$iam_settings = identity_provider('get');
if ($iam_settings['authsource'] != "keycloak" || (intval($iam_settings['periodic_sync']) != 1 && intval($iam_settings['import_users']) != 1)) {
  session_destroy();
  exit;
}

// Set pagination variables
$start = 0;
$max = 100;

// lock sync if already running
$lock_file = '/tmp/iam-sync.lock';
if (file_exists($lock_file)) {
  $lock_file_parts = explode("\n", file_get_contents($lock_file));
  $pid = $lock_file_parts[0];
  if (count($lock_file_parts) > 1){
    $last_execution = $lock_file_parts[1];
    $elapsed_time = (time() - $last_execution) / 60;
    if ($elapsed_time < intval($iam_settings['sync_interval'])) {
      logMsg("warning", "Sync not ready (".number_format((float)$elapsed_time, 2, '.', '')."min / ".$iam_settings['sync_interval']."min)");
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

// Init Keycloak Provider
$iam_provider = identity_provider('init');

function kc_admin_get($url, $token) {
  return identity_provider("keycloak-admin-get", array('url' => $url, 'admin_token' => $token));
}

function kc_fetch_all($base_url, $token, $max = 100) {
  $items = array();
  $start = 0;
  while (true) {
    $url = "{$base_url}" . (strpos($base_url, '?') !== false ? '&' : '?') . "first={$start}&max={$max}";
    $res = kc_admin_get($url, $token);

    if (!$res || $res['code'] != 200) {
      logMsg("err", "Received HTTP " . ($res ? $res['code'] : 0) . " from {$url}");
      return false;
    }
    $batch = json_decode($res['body'], true);
    if (!is_array($batch)) {
      logMsg("err", "Received malformed response from keycloak api");
      return false;
    }
    if (count($batch) == 0) break;

    $items = array_merge($items, $batch);
    if (count($batch) < $max) break;
    $start += $max;
    sleep(1);
  }
  return $items;
}

function kc_fetch_all_users($base_url, $token, $max = 100) {
  $users = array();
  $keycloak_users = kc_fetch_all($base_url, $token, $max);
  if ($keycloak_users === false) {
    return false;
  }
  foreach ($keycloak_users as $user) {
    if (!empty($user['email'])) {
      if (!isset($users[$user['email']])) {
        $users[$user['email']] = $user;
      }
    } else {
      logMsg("warning", "No email address in keycloak found for user " . ($user['username'] ?? $user['name'] ?? 'unknown'));
    }
  }
  return $users;
}

$admin_token = identity_provider("get-keycloak-admin-token");
if (!$admin_token) {
  logMsg("err", "Cannot obtain admin token");
  session_destroy();
  exit;
}

$all_users = array();
$role_filter_active = !empty($iam_settings['role_filter_type']) && $iam_settings['role_filter_type'] !== 'none' && !empty($iam_settings['role_filter_role_name']);
$base_users_path = "/admin/realms/{$iam_settings['realm']}/users";
$client_uuid = false;

if ($role_filter_active) {
  $role_name = rawurlencode($iam_settings['role_filter_role_name']);
  if ($iam_settings['role_filter_type'] === 'realm') {
    $base_users_path = "/admin/realms/{$iam_settings['realm']}/roles/{$role_name}/users";
    logMsg("info", "Filtering users by realm role '{$iam_settings['role_filter_role_name']}'");
  } elseif ($iam_settings['role_filter_type'] === 'client') {
    $client_uuid = identity_provider("get-keycloak-client-uuid", array('admin_token' => $admin_token));
    if (!$client_uuid) {
      logMsg("err", "Could not resolve client UUID for client_id '{$iam_settings['client_id']}'");
      session_destroy();
      exit;
    }
    $base_users_path = "/admin/realms/{$iam_settings['realm']}/clients/{$client_uuid}/roles/{$role_name}/users";
    logMsg("info", "Filtering users by client role '{$iam_settings['role_filter_role_name']}'");
  }
}

$direct_url = "{$iam_settings['server_url']}{$base_users_path}";
$direct_users = kc_fetch_all_users($direct_url, $admin_token, $max);
if ($direct_users === false) {
  session_destroy();
  exit;
}
$all_users = $direct_users;

if ($role_filter_active) {
  $role_name_enc = rawurlencode($iam_settings['role_filter_role_name']);
  if ($iam_settings['role_filter_type'] === 'realm') {
    $groups_path = "/admin/realms/{$iam_settings['realm']}/roles/{$role_name_enc}/groups";
  } elseif ($iam_settings['role_filter_type'] === 'client') {
    $groups_path = "/admin/realms/{$iam_settings['realm']}/clients/{$client_uuid}/roles/{$role_name_enc}/groups";
  } else {
    $groups_path = null;
  }

  if ($groups_path) {
    $groups_url = "{$iam_settings['server_url']}{$groups_path}";
    $groups = kc_fetch_all($groups_url, $admin_token, $max);

    if ($groups === false) {
      session_destroy();
      exit;
    }
    foreach ($groups as $group) {
      if (empty($group['id'])) {
        continue;
      }
      $members_url = "{$iam_settings['server_url']}/admin/realms/{$iam_settings['realm']}/groups/{$group['id']}/members";
      $group_users = kc_fetch_all_users($members_url, $admin_token, $max);
      if ($group_users === false) {
        session_destroy();
        exit;
      }
      foreach ($group_users as $email => $user) {
        if (!isset($all_users[$email])) {
          $all_users[$email] = $user;
        }
      }
    }
  }
}

logMsg("info", "Total unique users to process: " . count($all_users));

// Process all collected users
foreach ($all_users as $user) {
  // try get mailbox user
  $stmt = $pdo->prepare("SELECT
    mailbox.*,
    domain.active AS d_active
    FROM `mailbox`
    INNER JOIN domain on mailbox.domain = domain.domain
    WHERE `kind` NOT REGEXP 'location|thing|group'
      AND `username` = :user");
  $stmt->execute(array(':user' => $user['email']));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // check if matching attribute mapping exists
  $user_template = $user['attributes']['mailcow_template'][0] ?? null;
  $mapper_key = array_search($user_template, $iam_settings['mappers']);

  $_SESSION['access_all_exception'] = '1';
  if (!$row && intval($iam_settings['import_users']) == 1){
    if ($mapper_key === false){
      if (!empty($iam_settings['default_template'])) {
        $mbox_template = $iam_settings['default_template'];
        logMsg("warning", "Using default template for user " . $user['email']);
      } else {
        logMsg("warning", "No matching attribute mapping found for user " . $user['email']);
        $_SESSION['access_all_exception'] = '0';
        continue;
      }
    } else {
      $mbox_template = $iam_settings['templates'][$mapper_key];
    }
    // mailbox user does not exist, create...
    logMsg("info", "Creating user " . $user['email']);
    $create_res = mailbox('add', 'mailbox_from_template', array(
      'domain' => explode('@', $user['email'])[1],
      'local_part' => explode('@', $user['email'])[0],
      'name' => $user['firstName'] . " " . $user['lastName'],
      'authsource' => 'keycloak',
      'template' => $mbox_template
    ));
    if (!$create_res){
      logMsg("err", "Could not create user " . $user['email']);
      $_SESSION['access_all_exception'] = '0';
      continue;
    }
  } else if ($row && intval($iam_settings['periodic_sync']) == 1 && $row['authsource'] == "keycloak") {
    if ($mapper_key === false){
      logMsg("warning", "No matching attribute mapping found for user " . $user['email']);
      $_SESSION['access_all_exception'] = '0';
      continue;
    }
    $mbox_template = $iam_settings['templates'][$mapper_key];
    // mailbox user does exist, sync attributes...
    logMsg("info", "Syncing attributes for user " . $user['email']);
    mailbox('edit', 'mailbox_from_template', array(
      'username' => $user['email'],
      'name' => $user['firstName'] . " " . $user['lastName'],
      'template' => $mbox_template
    ));
  } else {
    // skip mailbox user
    logMsg("info", "Skipping user " . $user['email']);
  }
  $_SESSION['access_all_exception'] = '0';

  sleep(0.025);
}

logMsg("info", "DONE!");
// add last execution time to lock file
$lock_file_handle = fopen($lock_file, 'w');
fwrite($lock_file_handle, getmypid() . "\n" . time());
fclose($lock_file_handle);
session_destroy();
