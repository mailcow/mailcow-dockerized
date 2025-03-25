<?php
// handle iam authentication
if ($iam_provider){
  if (isset($_GET['iam_sso'])){
    // redirect for sso
    $redirect_uri = identity_provider('get-redirect');
    $redirect_uri = !empty($redirect_uri) ? $redirect_uri : '/';
    header('Location: ' . $redirect_uri);
    die();
  }
  if ($_SESSION['iam_token'] && $_SESSION['iam_refresh_token']) {
    // Session found, try to refresh
    $isRefreshed = identity_provider('refresh-token');

    if (!$isRefreshed){
      // Session could not be refreshed, redirect to provider
      $redirect_uri = identity_provider('get-redirect');
      $redirect_uri = !empty($redirect_uri) ? $redirect_uri : '/';
      header('Location: ' . $redirect_uri);
      die();
    }
  } elseif ($_GET['code'] && $_GET['state'] === $_SESSION['oauth2state']) {
    // Check given state against previously stored one to mitigate CSRF attack
    // Received access token in $_GET['code']
    // extract info and verify user
    identity_provider('verify-sso');
  }
}

if (isset($_POST["pw_reset_request"]) && !empty($_POST['username'])) {
  reset_password("issue", $_POST['username']);
  header("Location: /");
  exit;
}
if (isset($_POST["pw_reset"])) {
  $username = reset_password("check", $_POST['token']);
  $reset_result = reset_password("reset", array(
    'new_password' => $_POST['new_password'],
    'new_password2' => $_POST['new_password2'],
    'token' => $_POST['token'],
    'username' => $username,
    'check_tfa' => True
  ));

  if ($reset_result){
    header("Location: /");
    exit;
  }
}
if (isset($_POST["verify_tfa_login"])) {
  if (verify_tfa_login($_SESSION['pending_mailcow_cc_username'], $_POST)) {
    if ($_SESSION['pending_mailcow_cc_role'] == "user") {
      if (isset($_SESSION['pending_pw_reset_token']) && isset($_SESSION['pending_pw_new_password'])) {
        reset_password("reset", array(
          'new_password' => $_SESSION['pending_pw_new_password'],
          'new_password2' => $_SESSION['pending_pw_new_password'],
          'token' => $_SESSION['pending_pw_reset_token'],
          'username' => $_SESSION['pending_mailcow_cc_username']
        ));
        unset($_SESSION['pending_pw_reset_token']);
        unset($_SESSION['pending_pw_new_password']);
        unset($_SESSION['pending_mailcow_cc_username']);
        unset($_SESSION['pending_tfa_methods']);

        header("Location: /");
        die();
      } else {
        set_user_loggedin_session($_SESSION['pending_mailcow_cc_username']);
        $user_details = mailbox("get", "mailbox_details", $_SESSION['mailcow_cc_username']);
        $is_dual = (!empty($_SESSION["dual-login"]["username"])) ? true : false;
        if (intval($user_details['attributes']['sogo_access']) == 1 && !$is_dual) {
          header("Location: /SOGo/so/{$_SESSION['mailcow_cc_username']}");
          die();
        } else {
          header("Location: /user");
          die();
        }
      }
    }
  }

  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);
}
if (isset($_POST["verify_fido2_login"])) {
  fido2(array(
    "action" => "verify",
    "token" => $_POST["token"],
    "user" => "user"
  ));
  exit;
}

if (isset($_GET["cancel_tfa_login"])) {
  unset($_SESSION['pending_pw_reset_token']);
  unset($_SESSION['pending_pw_new_password']);
  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);

  header("Location: /");
}

if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
  $login_user = strtolower(trim($_POST["login_user"]));
  $as = check_login($login_user, $_POST["pass_user"], false, array("role" => "user"));

  if ($as == "user") {
    set_user_loggedin_session($login_user);
    $http_parameters = explode('&', $_SESSION['index_query_string']);
    unset($_SESSION['index_query_string']);
    if (in_array('mobileconfig', $http_parameters)) {
        if (in_array('only_email', $http_parameters)) {
            header("Location: /mobileconfig.php?only_email");
            die();
        }
        header("Location: /mobileconfig.php");
        die();
    }

    $user_details = mailbox("get", "mailbox_details", $login_user);
    $is_dual = (!empty($_SESSION["dual-login"]["username"])) ? true : false;
    if (intval($user_details['attributes']['sogo_access']) == 1 && !$is_dual) {
      header("Location: /SOGo/so/{$login_user}");
      die();
    } else {
      header("Location: /user");
      die();
    }
	}
	elseif ($as != "pending") {
    unset($_SESSION['pending_mailcow_cc_username']);
    unset($_SESSION['pending_mailcow_cc_role']);
    unset($_SESSION['pending_tfa_methods']);
		unset($_SESSION['mailcow_cc_username']);
		unset($_SESSION['mailcow_cc_role']);
	}
}
?>
