<?php

if (isset($_POST["verify_tfa_login"])) {
  if (verify_tfa_login($_SESSION['pending_mailcow_cc_username'], $_POST)) {
    if ($_SESSION['pending_mailcow_cc_role'] == "admin") {
      $_SESSION['mailcow_cc_username'] = $_SESSION['pending_mailcow_cc_username'];
      $_SESSION['mailcow_cc_role'] = "admin";
      unset($_SESSION['pending_mailcow_cc_username']);
      unset($_SESSION['pending_mailcow_cc_role']);
      unset($_SESSION['pending_tfa_methods']);

		  header("Location: /admin/dashboard");
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
    "user" => "admin"
  ));
  exit;
}

if (isset($_GET["cancel_tfa_login"])) {
  unset($_SESSION['pending_pw_reset_token']);
  unset($_SESSION['pending_pw_new_password']);
  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);

  header("Location: /admin");
}

if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
  $login_user = strtolower(trim($_POST["login_user"]));
  $as = check_login($login_user, $_POST["pass_user"], false, array("role" => "admin"));

  if ($as == "admin") {
    session_regenerate_id(true);
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "admin";
		header("Location: /admin/dashboard");
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

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin" && !isset($_SESSION['mailcow_cc_api'])) {
  // TODO: Move file upload to API?
	if (isset($_POST["submit_main_logo"])) {
    if ($_FILES['main_logo']['error'] == 0) {
      customize('add', 'main_logo', $_FILES);
    }
    if ($_FILES['main_logo_dark']['error'] == 0) {
      customize('add', 'main_logo_dark', $_FILES);
    }
	}
	if (isset($_POST["reset_main_logo"])) {
    customize('delete', 'main_logo');
    customize('delete', 'main_logo_dark');
	}
  // Some actions will not be available via API
	if (isset($_POST["license_validate_now"])) {
		license('verify');
	}
  if (isset($_POST["admin_api"])) {
    if (isset($_POST["admin_api"]["ro"])) {
      admin_api('ro', 'edit', $_POST);
    }
    elseif (isset($_POST["admin_api"]["rw"])) {
      admin_api('rw', 'edit', $_POST);
    }
	}
  if (isset($_POST["admin_api_regen_key"])) {
    if (isset($_POST["admin_api_regen_key"]["ro"])) {
      admin_api('ro', 'regen_key', $_POST);
    }
    elseif (isset($_POST["admin_api_regen_key"]["rw"])) {
      admin_api('rw', 'regen_key', $_POST);
    }
	}
	if (isset($_POST["rspamd_ui"])) {
		rspamd_ui('edit', $_POST);
	}
	if (isset($_POST["mass_send"])) {
		sys_mail($_POST);
	}
}
?>
