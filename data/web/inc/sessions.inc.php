<?php
// Start session
ini_set("session.cookie_httponly", 1);
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
session_set_cookie_params($SESSION_LIFETIME, '/', '', $IS_HTTPS, true);
session_start();
if (!isset($_SESSION['CSRF']['TOKEN'])) {
  $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
}

// Set session UA
if (!isset($_SESSION['SESS_REMOTE_UA'])) {
  $_SESSION['SESS_REMOTE_UA'] = $_SERVER['HTTP_USER_AGENT'];
}

// Check session
function session_check() {
  if (!isset($_SESSION['SESS_REMOTE_UA'])) {
    return false;
  }
  if ($_SESSION['SESS_REMOTE_UA'] != $_SERVER['HTTP_USER_AGENT']) {
    return false;
  }
  if (!empty($_POST)) {
    if ($_SESSION['CSRF']['TOKEN'] != $_POST['csrf_token']) {
      return false;
    }
    $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
    $_SESSION['CSRF']['TIME'] = time();
  }
  return true;
}

if (isset($_SESSION['mailcow_cc_role']) && session_check() === false) {
  $_SESSION['return'] = array(
    'type' => 'warning',
    'msg' => 'Form token invalid or timed out'
  );
  $_POST = array();
}

// Handle logouts
if (isset($_POST["logout"])) {
  if (isset($_SESSION["dual-login"])) {
    $_SESSION["mailcow_cc_username"] = $_SESSION["dual-login"]["username"];
    $_SESSION["mailcow_cc_role"] = $_SESSION["dual-login"]["role"];
    unset($_SESSION["dual-login"]);
  }
  else {
    session_regenerate_id(true);
    session_unset();
    session_destroy();
    session_write_close();
    header("Location: /");
  }
}
