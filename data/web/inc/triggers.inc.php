<?php
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
	else {
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
	if (isset($_POST["trigger_set_admin"])) {
		set_admin_account($_POST);
	}
	if (isset($_POST["delete_dkim_record"])) {
		dkim_table("delete", $_POST);
	}
	if (isset($_POST["add_dkim_record"])) {
		dkim_table("add", $_POST);
	}
	if (isset($_POST["trigger_add_domain_admin"])) {
		add_domain_admin($_POST);
	}
	if (isset($_POST["trigger_delete_domain_admin"])) {
		delete_domain_admin($_POST);
	}
	if (isset($_POST["trigger_edit_domain_admin"])) {
		edit_domain_admin($_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
	if (isset($_POST["trigger_set_user_account"])) {
		set_user_account($_POST);
	}
	if (isset($_POST["trigger_set_spam_score"])) {
		set_spam_score($_POST);
	}
	if (isset($_POST["trigger_set_tagging_options"])) {
		tagging_options('set', $_POST);
	}
	if (isset($_POST["trigger_set_policy_list"])) {
		set_policy_list($_POST);
	}
	if (isset($_POST["trigger_set_tls_policy"])) {
		set_tls_policy($_POST);
	}
	if (isset($_POST["trigger_add_syncjob"])) {
		set_syncjob($_POST, "add");
	}
	if (isset($_POST["trigger_edit_syncjob"])) {
		set_syncjob($_POST, "edit");
	}
	if (isset($_POST["trigger_delete_syncjob"])) {
		set_syncjob($_POST, "delete");
	}
	if (isset($_POST["trigger_set_time_limited_aliases"])) {
		set_time_limited_aliases($_POST);
	}
}
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
	if (isset($_GET["js"])) {
		switch ($_GET["js"]) {
			case "remaining_specs":
				remaining_specs($_GET['domain'], $_GET['object'], "y");
			break;
		}
	}
	if (isset($_POST["trigger_mailbox_action"])) {
		switch ($_POST["trigger_mailbox_action"]) {
			case "adddomain":
				mailbox_add_domain($_POST);
			break;
			case "addalias":
				mailbox_add_alias($_POST);
			break;
			case "editalias":
				mailbox_edit_alias($_POST);
			break;
			case "addaliasdomain":
				mailbox_add_alias_domain($_POST);
			break;
			case "addmailbox":
				mailbox_add_mailbox($_POST);
			break;
			case "editdomain":
				mailbox_edit_domain($_POST);
			break;
			case "editmailbox":
				mailbox_edit_mailbox($_POST);
			break;
			case "deletedomain":
				mailbox_delete_domain($_POST);
			break;
			case "deletealias":
				mailbox_delete_alias($_POST);
			break;
			case "deletealiasdomain":
				mailbox_delete_alias_domain($_POST);
			break;
			case "editaliasdomain":
				mailbox_edit_alias_domain($_POST);
			break;
			case "deletemailbox":
				mailbox_delete_mailbox($_POST);
			break;
		}
	}
}
?>
