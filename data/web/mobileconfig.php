<?php
require_once 'inc/prerequisites.inc.php';

if (empty($mailcow_hostname)) {
  exit();
}

error_reporting(0);

// mobileconfig.php?user=sales,admin@samedomain.com   (valid=sales@samedomain.com & admin@samedomain.com)
if (isset($_GET['user']) && (substr_count($_GET['user'], '@') == 1  ) && ( substr_count($_GET['user'], ',') >= 1 )) {
  list($accounts, $domain) = explode('@', strtolower(trim($_GET['user'])));

  $email_list=[];
  $domain = preg_replace('/[^\w-.]/', '', $domain);
  if (($domain === "") || (strpos($domain,'.') === FALSE)) { //validate filtered domain
    exit();
  }
  foreach (explode(',', preg_replace('/[^\w-,]/', '', $accounts)) as $account) {  //generate valid filtered email address list
    if ($account !== "") {
      $email_list[] = $account.'@'. $domain;
    }
  }
// mobileconfig.php?user=sales@domain.com,admin@otherdomain.com,,@domain.com   (valid=sales@domain.com & admin@otherdomain.com)
} elseif (isset($_GET['user']) && (substr_count($_GET['user'], '@') >= 1 ) && ( substr_count($_GET['user'], ',') >= 1 )) {
  $accounts = explode(',', preg_replace('/[^\w-@.,]/', '', strtolower(trim($_GET['user']))));

  $email_list=[];
  foreach ($accounts as $account) {  //generate valid filtered email address list
    list($account, $domain) = explode('@', $account);
    if (($domain !== "") && (strpos($domain,'.') !== FALSE) && ($account !== "")) { //validate the email address
      $email_list[] = $account.'@'.$domain;
    }
  }
  $domain = strtolower($mailcow_hostname);
//mobileconfig.php or mobileconfig.php?user=sales@domain.com (valid=sales@domain.com)
} else {
  $email_list=[];
  if (isset($_GET['user'])) {
    $account=preg_replace('/[^\w-@.]/', '', strtolower(trim( $_GET['user'])));
  } else {
    if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != 'user') { //registered users only
      header("Location: index.php");
      die("This page is only available to logged-in users, not admins.");
    }
     $account=preg_replace('/[^\w-@.]/', '', strtolower(trim( $_SESSION['mailcow_cc_username'])));
  }
  list($account, $domain) = explode('@',$account);
  if (($domain !== "") && (strpos($domain,'.') !== FALSE) && ($account !== "")) { //validate the email address
    $email_list[] = $account.'@'.$domain;
  }
}
if (count($email_list) < 1) { //validate email_list contains atleast 1 usable email address
  exit();
}

$identifier = implode('.', array_reverse(explode('.', $domain))) . '.iphoneprofile';

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename='.str_replace(array(".", "_"), $domain).'mobileconfig');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>PayloadContent</key>
	<array>
  <?php foreach ($email_list as $email) { ?>
    <?
    try {
      $stmt = $pdo->prepare("SELECT `name` FROM `mailbox` WHERE `username`= :username");
      $stmt->execute(array(':username' => $email));
      $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    catch(PDOException $e) {
      die("Failed to determine name from SQL");
    }
    if (!empty($MailboxData['name'])) {
      $displayname = utf8_encode($MailboxData['name']);
    }
    else {
      $displayname = $email;
    }
    ?>
		<dict>
			<key>CalDAVAccountDescription</key>
			<string><?php echo $email; ?></string>
			<key>CalDAVHostName</key>
			<string><?php echo $autodiscover_config['caldav']['server']; ?></string>
			<key>CalDAVPort</key>
			<real><?php echo $autodiscover_config['caldav']['port']; ?></real>
			<key>CalDAVPrincipalURL</key>
			<string>/SOGo/dav/<?php echo $email; ?></string>
			<key>CalDAVUseSSL</key>
			<true/>
			<key>CalDAVUsername</key>
			<string><?php echo $email; ?></string>
			<key>PayloadDescription</key>
			<string>Configures CalDAV account.</string>
			<key>PayloadDisplayName</key>
			<string>CalDAV (<?php echo $domain; ?>)</string>
			<key>PayloadIdentifier</key>
			<string><?php echo $identifier; ?>.CalDAV</string>
			<key>PayloadOrganization</key>
			<string></string>
			<key>PayloadType</key>
			<string>com.apple.caldav.account</string>
			<key>PayloadUUID</key>
			<string><?php echo bin2hex(random_bytes(4)); ?>-ACCB-42D8-9199-<?php echo bin2hex(random_bytes(6)); ?></string>
			<key>PayloadVersion</key>
			<integer>1</integer>
		</dict>
		<dict>
			<key>EmailAccountDescription</key>
			<string><?php echo $email; ?></string>
			<key>EmailAccountType</key>
			<string>EmailTypeIMAP</string>
			<key>EmailAccountName</key>
			<string><?php echo $displayname; ?></string>
			<key>EmailAddress</key>
			<string><?php echo $email; ?></string>
			<key>IncomingMailServerAuthentication</key>
			<string>EmailAuthPassword</string>
			<key>IncomingMailServerHostName</key>
			<string><?php echo $autodiscover_config['imap']['server']; ?></string>
			<key>IncomingMailServerPortNumber</key>
			<integer><?php echo $autodiscover_config['imap']['port']; ?></integer>
			<key>IncomingMailServerUseSSL</key>
			<true/>
			<key>IncomingMailServerUsername</key>
			<string><?php echo $email; ?></string>
			<key>OutgoingMailServerAuthentication</key>
			<string>EmailAuthPassword</string>
			<key>OutgoingMailServerHostName</key>
			<string><?php echo $autodiscover_config['smtp']['server']; ?></string>
			<key>OutgoingMailServerPortNumber</key>
			<integer><?php echo $autodiscover_config['smtp']['port']; ?></integer>
			<key>OutgoingMailServerUseSSL</key>
			<true/>
			<key>OutgoingMailServerUsername</key>
			<string><?php echo $email; ?></string>
			<key>OutgoingPasswordSameAsIncomingPassword</key>
			<true/>
			<key>PayloadDescription</key>
			<string>Configures email account.</string>
			<key>PayloadDisplayName</key>
			<string>IMAP Account (<?php echo $domain; ?>)</string>
			<key>PayloadIdentifier</key>
			<string><?php echo $identifier; ?>.email</string>
			<key>PayloadOrganization</key>
			<string></string>
			<key>PayloadType</key>
			<string>com.apple.mail.managed</string>
			<key>PayloadUUID</key>
			<string><?php echo bin2hex(random_bytes(4)); ?>-ACCB-42D8-9199-<?php echo bin2hex(random_bytes(6)); ?></string>
			<key>PayloadVersion</key>
			<integer>1</integer>
			<key>PreventAppSheet</key>
			<false/>
			<key>PreventMove</key>
			<false/>
			<key>SMIMEEnabled</key>
			<false/>
		</dict>
		<dict>
			<key>CardDAVAccountDescription</key>
			<string><?php echo $email; ?></string>
			<key>CardDAVHostName</key>
			<string><?php echo $autodiscover_config['carddav']['server']; ?></string>
			<key>CardDAVPort</key>
			<integer><?php echo $autodiscover_config['carddav']['port']; ?></integer>
			<key>CardDAVPrincipalURL</key>
			<string>/SOGo/dav/<?php echo $email; ?></string>
			<key>CardDAVUseSSL</key>
			<true/>
			<key>CardDAVUsername</key>
			<string><?php echo $email; ?></string>
			<key>PayloadDescription</key>
			<string>Configures CardDAV accounts</string>
			<key>PayloadDisplayName</key>
			<string>CardDAV (<?php echo $domain; ?>)</string>
			<key>PayloadIdentifier</key>
			<string><?php echo $identifier; ?>.carddav</string>
			<key>PayloadOrganization</key>
			<string></string>
			<key>PayloadType</key>
			<string>com.apple.carddav.account</string>
			<key>PayloadUUID</key>
			<string><?php echo bin2hex(random_bytes(4)); ?>-ACCB-42D8-9199-<?php echo bin2hex(random_bytes(6)); ?></string>
			<key>PayloadVersion</key>
			<integer>1</integer>
		</dict>
    <?php } ?>
	</array>
	<key>PayloadDescription</key>
	<string>IMAP, CalDAV, CardDAV</string>
	<key>PayloadDisplayName</key>
	<string><?php echo $domain ?></string>
	<key>PayloadIdentifier</key>
	<string><?php echo $identifier; ?></string>
	<key>PayloadOrganization</key>
	<string></string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string><?php echo bin2hex(random_bytes(4)); ?>-ACCB-42D8-9199-<?php echo bin2hex(random_bytes(6)); ?></string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>
