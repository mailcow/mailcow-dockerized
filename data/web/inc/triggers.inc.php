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
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :duallogin");
      $stmt->execute(array(':duallogin' => $_GET["duallogin"]));
      $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($num_results != 0) {
        $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
        $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
        $_SESSION['mailcow_cc_username']    = $_GET["duallogin"];
        $_SESSION['mailcow_cc_role']        = "user";
        header("Location: /user.php");
      }
    }
  }

	if (isset($_POST["edit_admin_account"])) {
		edit_admin_account($_POST);
	}
	if (isset($_POST["dkim_delete_key"])) {
		dkim_delete_key($_POST);
	}
	if (isset($_POST["dkim_add_key"])) {
		dkim_add_key($_POST);
	}
	if (isset($_POST["add_domain_admin"])) {
		add_domain_admin($_POST);
	}
	if (isset($_POST["delete_domain_admin"])) {
		delete_domain_admin($_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
	if (isset($_POST["edit_user_account"])) {
		edit_user_account($_POST);
	}
	if (isset($_POST["mailbox_reset_eas"])) {
		mailbox_reset_eas($_POST);
	}
	if (isset($_POST["edit_spam_score"])) {
		edit_spam_score($_POST);
	}
	if (isset($_POST["edit_delimiter_action"])) {
		edit_delimiter_action($_POST);
	}
	if (isset($_POST["add_policy_list_item"])) {
		add_policy_list_item($_POST);
	}
	if (isset($_POST["delete_policy_list_item"])) {
		delete_policy_list_item($_POST);
	}
	if (isset($_POST["edit_tls_policy"])) {
		edit_tls_policy($_POST);
	}
	if (isset($_POST["add_syncjob"])) {
		add_syncjob($_POST);
	}
	if (isset($_POST["edit_syncjob"])) {
		edit_syncjob($_POST);
	}
	if (isset($_POST["delete_syncjob"])) {
		delete_syncjob($_POST);
	}
	if (isset($_POST["set_time_limited_aliases"])) {
		set_time_limited_aliases($_POST);
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
	if (isset($_POST["add_policy_list_item"])) {
		add_policy_list_item($_POST);
	}
	if (isset($_POST["delete_policy_list_item"])) {
		delete_policy_list_item($_POST);
	}
	if (isset($_POST["mailbox_add_domain"])) {
		mailbox_add_domain($_POST);
	}
	if (isset($_POST["mailbox_add_alias"])) {
		mailbox_add_alias($_POST);
	}
	if (isset($_POST["mailbox_add_alias_domain"])) {
		mailbox_add_alias_domain($_POST);
	}
	if (isset($_POST["mailbox_add_mailbox"])) {
		mailbox_add_mailbox($_POST);
	}
	if (isset($_POST["mailbox_add_resource"])) {
		mailbox_add_resource($_POST);
	}
	if (isset($_POST["mailbox_edit_alias"])) {
		mailbox_edit_alias($_POST);
	}
	if (isset($_POST["mailbox_edit_domain"])) {
		mailbox_edit_domain($_POST);
	}
	if (isset($_POST["mailbox_edit_mailbox"])) {
		mailbox_edit_mailbox($_POST);
	}
	if (isset($_POST["mailbox_edit_alias_domain"])) {
		mailbox_edit_alias_domain($_POST);
	}
	if (isset($_POST["mailbox_edit_resource"])) {
		mailbox_edit_resource($_POST);
	}
	if (isset($_POST["mailbox_delete_domain"])) {
		mailbox_delete_domain($_POST);
	}
	if (isset($_POST["mailbox_delete_alias"])) {
		mailbox_delete_alias($_POST);
	}
	if (isset($_POST["mailbox_delete_alias_domain"])) {
		mailbox_delete_alias_domain($_POST);
	}
	if (isset($_POST["mailbox_delete_mailbox"])) {
		mailbox_delete_mailbox($_POST);
	}
	if (isset($_POST["mailbox_delete_resource"])) {
		mailbox_delete_resource($_POST);
	}
}
?>
