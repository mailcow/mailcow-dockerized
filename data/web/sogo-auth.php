<?php

$session_var_user_allowed = 'sogo-sso-user-allowed';
$session_var_pass = 'sogo-sso-pass';

// validate credentials for basic auth requests
if (isset($_SERVER['PHP_AUTH_USER'])) {
  // load prerequisites only when required
  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
  $username = $_SERVER['PHP_AUTH_USER'];
  $password = $_SERVER['PHP_AUTH_PW'];
  $login_check = check_login($username, $password);
  if ($login_check === 'user') {
    header("X-User: $username");
    header("X-Auth: Basic ".base64_encode("$username:$password"));
    header("X-Auth-Type: Basic");
    exit;
  } else {
    header('HTTP/1.0 401 Unauthorized');
    echo 'Invalid login';
    exit;
  }
}
// check permissions and redirect for direct GET ?login=xy requests
elseif (isset($_GET['login'])) {
  // load prerequisites only when required
  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
  // check if dual_login is active
  $is_dual = (!empty($_SESSION["dual-login"]["username"])) ? true : false;
  // check permissions (if dual_login is active, deny sso when acl is not given)
  $login = html_entity_decode(rawurldecode($_GET["login"]));
  if (isset($_SESSION['mailcow_cc_role']) &&
    ($_SESSION['acl']['login_as'] == "1" || ($is_dual === false && $login == $_SESSION['mailcow_cc_username']))) {
    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
      if (user_get_alias_details($login) !== false) {
        // load master password
        $sogo_sso_pass = file_get_contents("/etc/sogo-sso/sogo-sso.pass");
        // register username and password in session
        $_SESSION[$session_var_user_allowed][] = $login;
        $_SESSION[$session_var_pass] = $sogo_sso_pass;
        // redirect to sogo (sogo will get the correct credentials via nginx auth_request
        header("Location: /SOGo/so/${login}");
        exit;
      }
    }
  }
  header('HTTP/1.0 403 Forbidden');
  echo "Access is forbidden";
  exit;
}
// only check for admin-login on sogo GUI requests
elseif (
  strcasecmp(substr($_SERVER['HTTP_X_ORIGINAL_URI'], 0, 9), "/SOGo/so/") === 0
) {
  // this is an nginx auth_request call, we check for existing sogo-sso session variables
  session_start();
  // extract email address from "/SOGo/so/user@domain/xy"
  $url_parts = explode("/", $_SERVER['HTTP_X_ORIGINAL_URI']);
  $email = $url_parts[3];
  // check if this email is in session allowed list
  if (
      !empty($email) &&
      filter_var($email, FILTER_VALIDATE_EMAIL) &&
      is_array($_SESSION[$session_var_user_allowed]) &&
      in_array($email, $_SESSION[$session_var_user_allowed])
  ) {
    $username = $email;
    $password = $_SESSION[$session_var_pass];
    header("X-User: $username");
    header("X-Auth: Basic ".base64_encode("$username:$password"));
    header("X-Auth-Type: Basic");
    exit;
  }
}

// if username is empty, SOGo will use the normal login methods / login form
header("X-User: ");
header("X-Auth: ");
header("X-Auth-Type: ");
