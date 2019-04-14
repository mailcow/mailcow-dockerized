<?php
// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set("session.cookie_httponly", 1);
  ini_set('session.gc_maxlifetime', $SESSION_LIFETIME);
}

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
  strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https") {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set("session.cookie_secure", 1);
  }
  $IS_HTTPS = true;
}
elseif (isset($_SERVER['HTTPS'])) {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set("session.cookie_secure", 1);
  }
  $IS_HTTPS = true;
}
else {
  $IS_HTTPS = false;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!isset($_SESSION['CSRF']['TOKEN'])) {
  $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
}

// Set session UA
if (!isset($_SESSION['SESS_REMOTE_UA'])) {
  $_SESSION['SESS_REMOTE_UA'] = $_SERVER['HTTP_USER_AGENT'];
}

// Keep session active
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $SESSION_LIFETIME)) {
  session_unset();
  session_destroy();
}
$_SESSION['LAST_ACTIVITY'] = time();

// API
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
  $stmt = $pdo->prepare("SELECT `allow_from` FROM `api` WHERE `api_key` = :api_key AND `active` = '1';");
  $stmt->execute(array(
    ':api_key' => preg_replace('/[^a-zA-Z0-9-]/', '', $_SERVER['HTTP_X_API_KEY'])
  ));
  $api_return = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!empty($api_return['allow_from'])) {
    $remote = get_remote_ip(false);
    $allow_from = array_map('trim', preg_split( "/( |,|;|\n)/", $api_return['allow_from']));
    if (in_array($remote, $allow_from)) {
      $_SESSION['mailcow_cc_username'] = 'API';
      $_SESSION['mailcow_cc_role'] = 'admin';
      $_SESSION['mailcow_cc_api'] = true;
    }
    else {
      $redis->publish("F2B_CHANNEL", "mailcow UI: Invalid password for API_USER by " . $_SERVER['REMOTE_ADDR']);
      error_log("mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
      echo json_encode(array(
        'type' => 'error',
        'msg' => 'api access denied for ip ' . $_SERVER['REMOTE_ADDR']
      ));
      unset($_POST);
      die();
    }
  }
  else {
    $redis->publish("F2B_CHANNEL", "mailcow UI: Invalid password for API_USER by " . $_SERVER['REMOTE_ADDR']);
    error_log("mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(array(
      'type' => 'error',
      'msg' => 'authentication failed'
    ));
    unset($_POST);
    die();
  }
}

// Handle logouts
if (isset($_POST["logout"])) {
  if (isset($_SESSION["dual-login"])) {
    $_SESSION["mailcow_cc_username"] = $_SESSION["dual-login"]["username"];
    $_SESSION["mailcow_cc_role"] = $_SESSION["dual-login"]["role"];
    unset($_SESSION["dual-login"]);
    header("Location: /mailbox");
    exit();
  }
  else {
    session_regenerate_id(true);
    session_unset();
    session_destroy();
    session_write_close();
    header("Location: /");
  }
}

// Check session
function session_check() {
  if (isset($_SESSION['mailcow_cc_api']) && $_SESSION['mailcow_cc_api'] === true) {
    return true;
  }
  if (!isset($_SESSION['SESS_REMOTE_UA']) || ($_SESSION['SESS_REMOTE_UA'] != $_SERVER['HTTP_USER_AGENT'])) {
    $_SESSION['return'][] = array(
      'type' => 'warning',
      'msg' => 'session_ua'
    );
    return false;
  }
  if (!empty($_POST)) {
    if ($_SESSION['CSRF']['TOKEN'] != $_POST['csrf_token']) {
      $_SESSION['return'][] = array(
        'type' => 'warning',
        'msg' => 'session_token'
      );
      return false;
    }
    unset($_POST['csrf_token']);
    $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
    $_SESSION['CSRF']['TIME'] = time();
  }
  return true;
}

if (isset($_SESSION['mailcow_cc_role']) && session_check() === false) {
  $_POST = array();
  $_FILES = array();
}
