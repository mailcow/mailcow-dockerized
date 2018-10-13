<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (empty($mailcow_hostname)) {
  exit();
}
if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != 'user') {
  header("Location: index.php");
  die("This page is only available to logged-in users, not admins.");
}

error_reporting(0);

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="Mailcow.mobileconfig"');

$email = $_SESSION['mailcow_cc_username'];
$domain = explode('@', $_SESSION['mailcow_cc_username'])[1];
$identifier = implode('.', array_reverse(explode('.', $domain))) . '.iphoneprofile.mailcow';

try {
  $stmt = $pdo->prepare("SELECT `name` FROM `mailbox` WHERE `username`= :username");
  $stmt->execute(array(':username' => $email));
  $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  die("Failed to determine name from SQL");
}
if (!empty($MailboxData['name'])) {
  $displayname = $MailboxData['name'];
}
else {
  $displayname = $email;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>PayloadContent</key>
	<array>
		<dict>
			<key>CalDAVAccountDescription</key>
			<string><?php echo $domain; ?></string>
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
			<string>FC898573-EBA8-48AF-93BD-BFA0C9778FA7</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
		</dict>
		<dict>
			<key>EmailAccountDescription</key>
			<string><?php echo $domain; ?></string>
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
			<string>00294FBB-1016-413E-87B9-652D856D6875</string>
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
			<string><?php echo $domain; ?></string>
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
			<string>0797EF2B-B1F1-4BC7-ABCD-4580862252B4</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
		</dict>
	</array>
	<key>PayloadDescription</key>
	<string>IMAP, CalDAV, CardDAV</string>
	<key>PayloadDisplayName</key>
	<string><?php echo $domain; ?> Mailcow</string>
	<key>PayloadIdentifier</key>
	<string><?php echo $identifier; ?></string>
	<key>PayloadOrganization</key>
	<string></string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string>5EE248C5-ACCB-42D8-9199-8F8ED08D5624</string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>
