<?php
// Start session
ini_set("session.cookie_httponly", 1);
ini_set('session.gc_maxlifetime', $SESSION_LIFETIME);

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
  strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https") {
  ini_set("session.cookie_secure", 1);
  $IS_HTTPS = true;
}
elseif (isset($_SERVER['HTTPS'])) {
  ini_set("session.cookie_secure", 1);
  $IS_HTTPS = true;
}
else {
  $IS_HTTPS = false;
}
// session_set_cookie_params($SESSION_LIFETIME, '/', '', $IS_HTTPS, true);
session_start();
if (!isset($_SESSION['CSRF']['TOKEN'])) {
  $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
}

// Set session UA
if (!isset($_SESSION['SESS_REMOTE_UA'])) {
  $_SESSION['SESS_REMOTE_UA'] = $_SERVER['HTTP_USER_AGENT'];
}

// API
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
  $stmt = $pdo->prepare("SELECT `username`, `allow_from` FROM `api` WHERE `api_key` = :api_key AND `active` = '1';");
  $stmt->execute(array(
    ':api_key' => preg_replace('/[^A-Z0-9-]/i', '', $_SERVER['HTTP_X_API_KEY'])
  ));
  $api_return = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!empty($api_return['username'])) {
    $remote = get_remote_ip(false);
    $allow_from = array_map('trim', preg_split( "/( |,|;|\n)/", $api_return['allow_from']));
    if (in_array($remote, $allow_from)) {
      $_SESSION['mailcow_cc_username'] = $api_return['username'];
      $_SESSION['mailcow_cc_role'] = 'admin';
      $_SESSION['mailcow_cc_api'] = true;
    }
  }
}
// Update session cookie
// setcookie(session_name() ,session_id(), time() + $SESSION_LIFETIME);

// Check session
function session_check() {
  if ($_SESSION['mailcow_cc_api'] === true) {
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

// Handle logouts
if (isset($_POST["logout"])) {
  if (isset($_SESSION["dual-login"])) {
    $_SESSION["mailcow_cc_username"] = $_SESSION["dual-login"]["username"];
    $_SESSION["mailcow_cc_role"] = $_SESSION["dual-login"]["role"];
    unset($_SESSION["dual-login"]);
    header("Location: /mailbox.php");
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
