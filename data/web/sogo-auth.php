<?php

$ALLOW_ADMIN_EMAIL_LOGIN = (preg_match(
  "/^([yY][eE][sS]|[yY])+$/",
  $_ENV["ALLOW_ADMIN_EMAIL_LOGIN"]
));

$session_var_user = 'sogo-sso-user';
$session_var_pass = 'sogo-sso-pass';

if (!$ALLOW_ADMIN_EMAIL_LOGIN) {
  header('HTTP/1.0 401 Forbidden');
  echo "this feature is disabled";
  exit;
}
elseif (isset($_GET['login'])) {
  // load prerequisites only when required
  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
  // check permissions
  if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['acl']['login_as'] == "1") {
    $login = html_entity_decode(rawurldecode($_GET["login"]));
    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
      if (!empty(mailbox('get', 'mailbox_details', $login))) {
        // load master password
        $sogo_sso_pass = file_get_contents("/etc/sogo-sso/sogo-sso.pass");
        // register username and password in session
        $_SESSION[$session_var_user] = $login;
        $_SESSION[$session_var_pass] = $sogo_sso_pass;
        // redirect to sogo (sogo will get the correct credentials via nginx auth_request
        header("Location: /SOGo/");
        exit;
      }
    }
  }
  header('HTTP/1.0 401 Forbidden');
  exit;
}
else {
  // this is an nginx auth_request call, we check for existing sogo-sso session variables
  session_start();
  if (isset($_SESSION[$session_var_user]) && filter_var($_SESSION[$session_var_user], FILTER_VALIDATE_EMAIL)) {
      $username = $_SESSION[$session_var_user];
      $password = $_SESSION[$session_var_pass];
      header("X-User: $username");
      header("X-Auth: Basic ".base64_encode("$username:$password"));
      header("X-Auth-Type: Basic");
  } else {
      // if username is empty, SOGo will display the normal login form
      header("X-User: ");
      header("X-Auth: ");
      header("X-Auth-Type: ");
  }
  exit;
}
