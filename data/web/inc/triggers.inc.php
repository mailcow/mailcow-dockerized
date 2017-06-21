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
    if (filter_var($_GET["duallogin"], FILTER_VALIDATE_EMAIL)) {
      if (!empty(mailbox('get', 'mailbox_details', $_GET["duallogin"]))) {
        $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
        $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
        $_SESSION['mailcow_cc_username']    = $_GET["duallogin"];
        $_SESSION['mailcow_cc_role']        = "user";
        header("Location: /user.php");
      }
    }
  }
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
	if (isset($_POST["edit_user_account"])) {
		edit_user_account($_POST);
	}
	if (isset($_POST["edit_syncjob"])) {
		mailbox('edit', 'syncjob', $_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
	if (isset($_POST["edit_domain_admin"])) {
		edit_domain_admin($_POST);
	}
	if (isset($_POST["set_tfa"])) {
		set_tfa($_POST);
	}
	if (isset($_POST["unset_tfa_key"])) {
		unset_tfa_key($_POST);
	}
	if (isset($_POST["mailbox_edit_alias"])) {
		mailbox('edit', 'alias', $_POST);
	}
	if (isset($_POST["mailbox_edit_domain"])) {
		mailbox('edit', 'domain', $_POST);
	}
	if (isset($_POST["mailbox_edit_mailbox"])) {
		mailbox('edit', 'mailbox', $_POST);
	}
	if (isset($_POST["mailbox_edit_alias_domain"])) {
		mailbox('edit', 'alias_domain', $_POST);
	}
	if (isset($_POST["mailbox_edit_resource"])) {
		mailbox('edit', 'resource', $_POST);
	}
	if (isset($_POST["mailbox_delete_domain"])) {
		mailbox('delete', 'domain', $_POST);
	}
	if (isset($_POST["mailbox_delete_alias"])) {
		mailbox('delete', 'delete_alias', $_POST);
	}
	if (isset($_POST["mailbox_delete_alias_domain"])) {
		mailbox('delete', 'alias_domain', $_POST);
	}
	if (isset($_POST["mailbox_delete_mailbox"])) {
		mailbox('delete', 'mailbox', $_POST);
	}
	if (isset($_POST["mailbox_delete_resource"])) {
		mailbox('delete', 'resource', $_POST);
	}
}
?>
