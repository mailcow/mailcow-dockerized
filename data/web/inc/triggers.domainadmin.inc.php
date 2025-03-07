<?php
// SSO Domain Admin
if (!empty($_GET['sso_token'])) {
  $username = domain_admin_sso('check', $_GET['sso_token']);

  if ($username !== false) {
    session_regenerate_id(true);
    $_SESSION['mailcow_cc_username'] = $username;
    $_SESSION['mailcow_cc_role'] = 'domainadmin';
    header('Location: /domainadmin/mailbox');
  }
}

if (isset($_POST["verify_tfa_login"])) {
  if (verify_tfa_login($_SESSION['pending_mailcow_cc_username'], $_POST)) {
    if ($_SESSION['pending_mailcow_cc_role'] == "domainadmin") {
      $_SESSION['mailcow_cc_username'] = $_SESSION['pending_mailcow_cc_username'];
      $_SESSION['mailcow_cc_role'] = "domainadmin";
      unset($_SESSION['pending_mailcow_cc_username']);
      unset($_SESSION['pending_mailcow_cc_role']);
      unset($_SESSION['pending_tfa_methods']);

		  header("Location: /domainadmin/mailbox");
      die();
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
    "user" => "domainadmin"
  ));
  exit;
}

if (isset($_GET["cancel_tfa_login"])) {
  unset($_SESSION['pending_pw_reset_token']);
  unset($_SESSION['pending_pw_new_password']);
  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);

  header("Location: /domainadmin");
}

if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
  $login_user = strtolower(trim($_POST["login_user"]));
  $as = check_login($login_user, $_POST["pass_user"], false, array("role" => "domain_admin"));

  if ($as == "domainadmin") {
    session_regenerate_id(true);
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "domainadmin";
		header("Location: /domainadmin/mailbox");
    die();
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
