<?php
require_once 'inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
require_once("inc/header.inc.php");

if (file_exists('thunderbird-plugins/version.csv'))
{
  $fh = fopen('thunderbird-plugins/version.csv', 'r');
  if ($fh)
  {
    while (($row = fgetcsv($fh, 1000, ';')) !== FALSE)
    {
      if ($row[0] == 'sogo-integrator@inverse.ca') {
        $integrator_file = $row[2];
      }
    }
    fclose($fh);
  }
}

$email = $_SESSION['mailcow_cc_username'];
$domain = explode('@', $_SESSION['mailcow_cc_username'])[1];

$config = get_client_config($domain);

$username = trim($email);
try {
  $stmt = $pdo->prepare("SELECT `name` FROM `mailbox` WHERE `username`= :username");
  $stmt->execute(array(':username' => $username));
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
<div class="container">
  <h2>Client Configuration Guide</h2>
  <p>Select your email client or operating system below to view a step-by-step guide to setting up your email account.</p>
  
  <h3><a href="#" onclick="document.getElementById('client_apple').style.display = 'block'">Apple macOS / iOS</a></h3>
  <div  id="client_apple" style="display: none">
    <ol>
      <li>Download and open <a href="mobileconfig.php">Mailcow.mobileconfig</a>.</li>
      <li>Enter the unlock code (iPhone) or computer password (Mac).</li>
      <li>Enter your email password three times when prompted.</li>
    </ol>
    <p>On iOS, Exchange is also supported as an alternative to the procedure above. It has the advantage of supporting push email (i.e. you are immediately notified of incoming messages), but has some limitations, e.g. it does not support more than three email addresses per contact in your address book. Follow the steps below if you decide to use Exchange instead.</p>
    <ol>
      <li>Open the <em>Settings</em> app, tap <em>Mail</em>, tap <em>Accounts</em>, tap <em>Add Acccount</em>, select <em>Exchange</em>.</li>
      <li>Enter your email address (<code><?php echo $email; ?></code>) and tap <em>Next</em>.</li>
      <li>Enter your password, tap <em>Next</em> again.</li>
      <li>Finally, tap <em>Save</em>.</li>
    </ol>
  </div>
  
  <h3><a href="#" onclick="document.getElementById('client_android').style.display = 'block'">Android</a></h3>
  <ol id="client_android" style="display: none">
    <li>Open the <em>Email</em> app.</li>
    <li>If this is your first email account, tap <em>Add Account</em>; if not, tap <em>More</em> and <em>Settings</em> and then <em>Add account</em>.</li>
    <li>Select <em>Microsoft Exchange ActiveSync</em>.</li>
    <li>Enter your email address (<code><?php echo $email; ?></code>) and password.</li>
    <li>Tap <em>Sign in</em>.</li>
  </ol>
  
  <h3><a href="#" onclick="document.getElementById('client_emclient').style.display = 'block'">eM Client</a></h3>
  <ol id="client_emclient" style="display: none">
    <li>Launch eM Client.</li>
    <li>If this is the first time you launched eM Client, it asks you to set up your account. Proceed to step 4.</li>
    <li>Go to <em>Menu</em> at the top, select <em>Tools</em> and <em>Accounts</em>.</li>
    <li>Enter your email address (<code><?php echo $email; ?></code>) and click <em>Start Now</em>.</li>
    <li>Enter your password and click <em>Continue</em>.</li>
    <li>Enter your name (<code><?php echo $displayname; ?></code>) and click <em>Next</em>.</li>
    <li>Click <em>Finish</em>.</li>
  </ol>
  
  <h3><a href="#" onclick="document.getElementById('client_kontact').style.display = 'block'">KDE Kontact</a></h3>
  <div id="client_kontact" style="display: none">
    <ol>
      <li>Launch Kontact.</li>
      <li>If this is the first time you launched Kontact or KMail, it asks you to set up your account. Proceed to step 4.</li>
      <li>Go to <em>Mail</em> in the sidebar. Go to the <em>Tools</em> menu and select <em>Account Wizard</em>.</li>
      <li>Enter your name (<code><?php echo $displayname; ?></code>), email address (<code><?php echo $email; ?></code>) and your password. Click <em>Next</em>.</li>
      <li>Click <em>Create Account</em>. If prompted, re-enter your password and click <em>OK</em>.</li>
      <li>Close the window by clicking <em>Finish</em>.</li>
      <li>Go to <em>Calendar</em> in the sidebar.</li>
      <li>Go to the <em>Settings</em> menu and select <em>Configure KOrganizer</em>.</li>
      <li>Go to the <em>Calendars</em> tab and click the <em>Add</em> button.</li>
      <li>Choose <em>DAV groupware resource</em> and click <em>OK</em>.</li>
      <li>Enter your email address (<code><?php echo $email; ?></code>) and your password. Click <em>Next</em>.</li>
      <li>Select <em>ScalableOGo</em> from the dropdown menu and click <em>Next</em>.</li>
      <li>Enter <code><?php echo $config['sogo']['server']; if ($config['sogo']['port'] != '443') echo ':'.$config['sogo']['port']; ?></code> into the <em>Host</em> field and click <em>Next</em>.</li>
      <li>Click <em>Test Connection</em> and then <em>Finish</em>. Finally, click <em>OK</em> twice.</li>
    </ol>
    <p>Once you have set up Kontact, you can also use KMail, KOrganizer and KAddressBook individually.</p>
  </div>
  
  <h3><a href="#" onclick="document.getElementById('client_outlook').style.display = 'block'">Microsoft Outlook</a></h3>
  <div id="client_outlook" style="display: none">
<?php if ($config['useEASforOutlook'] == 'yes') { ?>
    <h4>Outlook 2013 or higher on Windows</h4>
    <ol>
      <li>Launch Outlook.</li>
      <li>If this is the first time you launched Outlook, it asks you to set up your account. Proceed to step 4.</li>
      <li>Go to the <em>File</em> menu and click <em>Add Account</em>.</li>
      <li>Enter your name (<code><?php echo $displayname; ?></code>), email address (<code><?php echo $email; ?></code>) and your password. Click <em>Next</em>.</li>
      <li>When prompted, enter your password again, check <em>Remember my credentials</em> and click <em>OK</em>.</li>
      <li>Click the <em>Allow</em> button.</li>
      <li>Click <em>Finish</em>.</li>
    </ol>
    <h4>Outlook 2007 or 2010 on Windows</h4>
<?php } else { ?>
    <h4>Outlook 2007 or higher on Windows</h4>
<?php } ?>
    <ol>
      <li>Download and install <a href="https://caldavsynchronizer.org" target="_blank">Outlook CalDav Synchronizer</a>.</li>
      <li>Launch Outlook.</li>
      <li>If this is the first time you launched Outlook, it asks you to set up your account. Proceed to step 5.</li>
      <li>Go to the <em>File</em> menu and click <em>Add Account</em>.</li>
      <li>Enter your name (<code><?php echo $displayname; ?></code>), email address (<code><?php echo $email; ?></code>) and your password. Click <em>Next</em>.</li>
      <li>Click <em>Finish</em>.</li>
      <li>Go to the <em>CalDav Synchronizer</em> ribbon, click <em>Synchronization Profiles</em>.</li>
      <li>Click the second button at top (<em>Add multiple profiles</em>), select <em>Sogo</em>, click <em>Ok</em>.</li>
      <li>Click the <em>Get IMAP/POP3 account settings</em> button.</li>
      <li>Click <em>Discover resources and assign to Outlook folders</em>.</li>
      <li>In the <em>Select Resource</em> window that pops up, select your main calendar (usually <em>Personal Calendar</em>), click the <em>...</em> button, assign it to <em>Calendar</em>, and click <em>OK</em>. Go to the <em>Address Books</em> and <em>Tasks</em> tabs and repeat repeat the process accordingly. Do not assign multiple calendars, address books or task lists!</li>
      <li>Close all windows with the <em>OK</em> buttons.</li>
    </ol>
    <h4>Outlook 2011 or higher on macOS</h4>
    <p>The Mac version of Outlook does not synchronize calendars and contacts and therefore is not supported.</p>
  </div>
  
  <h3><a href="#" onclick="document.getElementById('client_thunderbird').style.display = 'block'">Mozilla Thunderbird</a></h3>
  <div id="client_thunderbird" style="display: none">
    <ol>
      <li>Launch Thunderbird.</li>
      <li>If this is the first time you launched Thunderbird, it asks you whether you would like a new email address. Click <em>Skip this and use my existing email</em> and proceed to step 4.</li>
      <li>Go to the <em>Tools</em> menu and select <em>Account Settings</em>.</li>
      <li>Click the <em>Account Actions</em> dropdown menu at the bottom left and select <em>Add Mail Account</em>.</li>
      <li>Enter your name (<code><?php echo $displayname; ?></code>), email address (<code><?php echo $email; ?></code>) and your password. Make sure the <em>Remember password</em> checkbox is selected and click <em>Continue</em>.</li>
      <li>Once the configuration has been automatically detected, click <em>Done</em>.</li>
      <li>If you already had other accounts configured in Thunderbird, select the new one (<?php echo $email; ?>) on the left, click the <em>Account Actions</em> dropdown and select <em>Set as Default</em>.</li>
      <li>Close the account settings window with the <em>OK</em> button.</li>
      <li>In your web browser, download <a href="thunderbird-plugins/<?php echo str_replace('__DOMAIN__', $domain, $integrator_file); ?>">SOGo Integrator</a>.</li>
      <li>Back in Thunderbird, go to the <em>Tools</em> menu and select <em>Add-ons</em>.</li>
      <li>Click <em>Extensions</em> on the left, click the little gear icon at the top and select <em>Install Add-on From File</em>. Select the file you downloaded in step 9, click <em>Open</em> and, after waiting for a few seconds, <em>Install Now</em>.</li>
      <li>Click the <em>Restart Now</em> button at the top that appears.</li>
      <li>Thunderbird briefly shows a message that it is updating extensions, then restarts automatically once more.</li>
      <li>When you are prompted to authenticate for https://<?php echo $config['sogo']['server']; if ($config['sogo']['port'] != '443') echo ':'.$config['sogo']['port']; ?>, enter your email address and password, check <em>Use Password Manager</em> and click <em>OK</em>.</li>
    </ol>
  </div>
  
  <h3><a href="#" onclick="document.getElementById('client_windows').style.display = 'block'">Windows</a></h3>
  <div id="client_windows" style="display: none">
    <ol>
    <li>Open the <em>Mail</em> app.</li>
    <li>If you have not previously used Mail, you can click <em>Add Account</em> in the main window. Proceed to step 4.</li>
    <li>Click <em>Accounts</em> in the sidebar on the left, then click <em>Add Account</em> on the far right.</li>
    <li>Select <em>Exchange</em>.</li>
    <li>Enter your email address (<code><?php echo $email; ?></code>) and click Next.</li>
    <li>Enter your password and click <em>Log in</em>.</li>
    </ol>
    <p>Once you have set up the Mail app, you can also use the People and Calendar apps.</p>
  </div>
  
  <h3><a href="#" onclick="document.getElementById('client_windowsphone').style.display = 'block'">Windows Phone</a></h3>
  <ol id="client_windowsphone" style="display: none">
    <li>Open the <em>Settings</em> app. Select <em>email + accounts</em> and tap <em>add an account</em>.</li>
    <li>Tap Exchange.</li>
    <li>Enter your email address (<code><?php echo $email; ?></code>) and your password. Tap <em>Sign in</em>.</li>
    <li>Tap <em>done</em>.</li>
  </ol>
</div>

<?php
require_once("inc/footer.inc.php");
} else {
  header('Location: index.php');
  exit();
}
?>
