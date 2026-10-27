<?php
if (isset($_POST["quick_release"])) {
	quarantine('quick_release', $_POST["quick_release"]);
}

if (isset($_POST["quick_delete"])) {
	quarantine('quick_delete', $_POST["quick_delete"]);
}

if (isset($_SESSION['mailcow_cc_role']) && (isset($_SESSION['acl']['login_as']) && $_SESSION['acl']['login_as'] == "1")) {
	if (isset($_GET["duallogin"])) {
    $is_dual = (!empty($_SESSION["dual-login"]["username"])) ? true : false;
    if (!$is_dual) {
      $duallogin = html_entity_decode(rawurldecode($_GET["duallogin"]));
      if (filter_var($duallogin, FILTER_VALIDATE_EMAIL)) {
        if (!empty(mailbox('get', 'mailbox_details', $duallogin))) {
          $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
          $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
          $_SESSION['mailcow_cc_username']    = $duallogin;
          $_SESSION['mailcow_cc_role']        = "user";
          header("Location: /user");
        }
      }
      else {
        if (!empty(domain_admin('details', $duallogin))) {
          $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
          $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
          $_SESSION['mailcow_cc_username']    = $duallogin;
          $_SESSION['mailcow_cc_role']        = "domainadmin";
          header("Location: /user");
        }
      }
    }
  }
}

if (isset($_SESSION['mailcow_cc_role'])) {
	if (isset($_POST["set_tfa"])) {
		$had_pending_tfa_setup = !empty($_SESSION['pending_tfa_setup']);
		set_tfa($_POST);
		// After TFA setup during forced enrollment
		if ($had_pending_tfa_setup && empty($_SESSION['pending_tfa_setup'])) {
			if ($_SESSION['mailcow_cc_role'] === 'admin') {
				header("Location: /admin/dashboard");
			} elseif ($_SESSION['mailcow_cc_role'] === 'domainadmin') {
				header("Location: /domainadmin/mailbox");
			} elseif ($_SESSION['mailcow_cc_role'] === 'user') {
				// Check if user should go to SOGo or /user
				$user_details = mailbox("get", "mailbox_details", $_SESSION['mailcow_cc_username']);
				$is_dual = (!empty($_SESSION["dual-login"]["username"])) ? true : false;
				if (intval($user_details['attributes']['sogo_access']) == 1 && !$is_dual && getenv('SKIP_SOGO') != "y") {
					header("Location: /SOGo/so/");
				} else {
					header("Location: /user");
				}
			}
			exit();
		}
	}
	if (isset($_POST["unset_tfa_key"])) {
		unset_tfa_key($_POST);
	}
	if (isset($_POST["unset_fido2_key"])) {
		fido2(array("action" => "unset_fido2_key", "post_data" => $_POST));
	}
}
?>
