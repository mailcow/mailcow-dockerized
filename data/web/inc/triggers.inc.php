<?php
if (isset($_POST["verify_tfa_login"])) {
  if (verify_tfa_login($_SESSION['pending_mailcow_cc_username'], $_POST["token"])) {
    $_SESSION['mailcow_cc_username'] = $_SESSION['pending_mailcow_cc_username'];
    $_SESSION['mailcow_cc_role'] = $_SESSION['pending_mailcow_cc_role'];
    unset($_SESSION['pending_mailcow_cc_username']);
    unset($_SESSION['pending_mailcow_cc_role']);
    unset($_SESSION['pending_tfa_method']);
		header("Location: /user.php");
  }
}

if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	$login_user = strtolower(trim($_POST["login_user"]));
	$as = check_login($login_user, $_POST["pass_user"]);
	if ($as == "admin") {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "admin";
		header("Location: /admin.php");
	}
	elseif ($as == "domainadmin") {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "domainadmin";
		header("Location: /mailbox.php");
	}
	elseif ($as == "user") {
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "user";
		header("Location: /user.php");
	}
	elseif ($as != "pending") {
    unset($_SESSION['pending_mailcow_cc_username']);
    unset($_SESSION['pending_mailcow_cc_role']);
    unset($_SESSION['pending_tfa_method']);
		unset($_SESSION['mailcow_cc_username']);
		unset($_SESSION['mailcow_cc_role']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => $lang['danger']['login_failed']
		);
	}
}

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
	if (isset($_GET["duallogin"])) {
    $duallogin = html_entity_decode(rawurldecode($_GET["duallogin"]));
    if (filter_var($duallogin, FILTER_VALIDATE_EMAIL)) {
      if (!empty(mailbox('get', 'mailbox_details', $duallogin))) {
        $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
        $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
        $_SESSION['mailcow_cc_username']    = $duallogin;
        $_SESSION['mailcow_cc_role']        = "user";
        header("Location: /user.php");
      }
    }
  }
}

if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
	if (isset($_POST["set_tfa"])) {
		set_tfa($_POST);
	}
	if (isset($_POST["unset_tfa_key"])) {
		unset_tfa_key($_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
  // TODO: Move file upload to API?
	if (isset($_POST["submit_main_logo"])) {
    if ($_FILES['main_logo']['error'] == 0) {
      customize('add', 'main_logo', $_FILES);
    }
	}
	if (isset($_POST["reset_main_logo"])) {
    customize('delete', 'main_logo');
	}
  // API cannot be controlled by API
	if (isset($_POST["admin_api"])) {
		admin_api('edit', $_POST);
	}
	if (isset($_POST["admin_api_regen_key"])) {
		admin_api('regen_key', $_POST);
	}
  // Not available via API
	if (isset($_POST["rspamd_ui"])) {
		rspamd_ui('edit', $_POST);
	}
}
?>
