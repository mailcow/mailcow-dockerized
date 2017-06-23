<?php
require_once 'inc/clientconfig.inc.php';

if (empty($mailcow_hostname)) {
  exit();
}

$config = get_client_config();
if(file_exists('vars.local.inc.php')) {
  include_once 'vars.local.inc.php';
}

header('Content-Type: application/xml');
?>
<?= '<?xml version="1.0"?>'; ?>
<clientConfig version="1.1">
    <emailProvider id="<?= $mailcow_hostname; ?>">
      <domain>%EMAILDOMAIN%</domain>
      <displayName>A mailcow mail server</displayName>
      <displayShortName>mail server</displayShortName>

<?php if (isset($config['imap']['port']) ) { ?>
      <incomingServer type="imap">
         <hostname><?= $config['imap']['server']; ?></hostname>
         <port><?= $config['imap']['port']; ?></port>
<?php if ($config['imap']['ssl'] == 'on') { ?>
         <socketType>SSL</socketType>
<?php } else { ?>
         <socketType>plain</socketType>
<?php } ?>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>
<?php if (isset($config['imap']['tlsport']) ) { ?>
      <incomingServer type="imap">
         <hostname><?= $config['imap']['server']; ?></hostname>
         <port><?= $config['imap']['tlsport']; ?></port>
<?php if ($config['imap']['ssl'] == 'on') { ?>
         <socketType>STARTTLS</socketType>
<?php } else { ?>
         <socketType>plain</socketType>
<?php } ?>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>

<?php if (isset($config['pop3']['port']) ) { ?>
      <incomingServer type="pop3">
         <hostname><?= $config['pop3']['server']; ?></hostname>
         <port><?= $config['pop3']['port']; ?></port>
<?php if ($config['pop3']['ssl'] == 'on') { ?>
         <socketType>SSL</socketType>
<?php } else { ?>
         <socketType>plain</socketType>
<?php } ?>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>
<?php if (isset($config['pop3']['tlsport']) ) { ?>
      <incomingServer type="pop3">
         <hostname><?= $config['pop3']['server']; ?></hostname>
         <port><?= $config['pop3']['tlsport']; ?></port>
<?php if ($config['pop3']['ssl'] == 'on') { ?>
         <socketType>STARTTLS</socketType>
<?php } else { ?>
         <socketType>plain</socketType>
<?php } ?>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>

<?php if (isset($config['smtp']['port']) ) { ?>
      <outgoingServer type="smtp">
         <hostname><?= $config['smtp']['server']; ?></hostname>
         <port><?= $config['smtp']['port']; ?></port>
<?php if ($config['smtp']['ssl'] == 'on') { ?>
         <socketType>SSL</socketType>
<?php } else { ?>
         <socketType>plain</socketType>
<?php } ?>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </outgoingServer>
<?php } ?>
<?php if (isset($config['smtp']['tlsport']) ) { ?>
      <outgoingServer type="smtp">
         <hostname><?= $config['smtp']['server']; ?></hostname>
         <port><?= $config['smtp']['tlsport']; ?></port>
<?php if ($config['smtp']['ssl'] == 'on') { ?>
         <socketType>STARTTLS</socketType>
<?php } else { ?>
         <socketType>plain</socketType>
<?php } ?>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </outgoingServer>
<?php } ?>

      <enable visiturl="http<?php if ($config['http']['ssl'] == 'on') echo 's' ?>://<?= $config['http']['server'] . ':' . $config['http']['port']; ?>/admin.php">
         <instruction>If you didn't change the password given to you by the administrator or if you didn't change it in a long time, please consider doing that now.</instruction>
         <instruction lang="de">Sollten Sie das Ihnen durch den Administrator vergebene Passwort noch nicht geändert haben, empfehlen wir dies nun zu tun. Auch ein altes Passwort sollte aus Sicherheitsgründen geändert werden.</instruction>
      </enable>

    </emailProvider>

    <webMail>
      <loginPage url="http<?php if ($config['sogo']['ssl'] == 'on') echo 's' ?>://<?= $config['sogo']['server'] . ':' . $config['sogo']['port']; ?>/SOGo/" />
    </webMail>
</clientConfig>
