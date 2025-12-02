<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/vars.inc.php';
$default_autodiscover_config = $autodiscover_config;
if(file_exists('inc/vars.local.inc.php')) {
  include_once 'inc/vars.local.inc.php';
}
$autodiscover_config = array_merge($default_autodiscover_config, $autodiscover_config);

error_reporting(0);

if (empty($mailcow_hostname)) {
  exit();
}

$domain_dot = strpos($_SERVER['HTTP_HOST'], '.');
$domain_port = strpos($_SERVER['HTTP_HOST'], ':');
if ($domain_port === FALSE) {
  $domain = substr($_SERVER['HTTP_HOST'], $domain_dot+1);
  $port = 443;
}
else {
  $domain = substr($_SERVER['HTTP_HOST'], $domain_dot+1, $domain_port-$domain_dot-1);
  $port = substr($_SERVER['HTTP_HOST'], $domain_port+1);
}

if (isset($_GET['emailaddress'])) {
  $emailaddress_at = strpos($_GET['emailaddress'], '@');
  if ($emailaddress_at !== FALSE) {
    $domain = substr($_GET['emailaddress'], $emailaddress_at + 1);
  }
}

function autoconfig_service_enabled($_service_type) {
  global $autodiscover_config;
  global $domain;
  $_disabled = FALSE;
  switch ($_service_type) {
    // TODO Check autodiscover_config
    case 'imap':
      $_disabled = isset($autodiscover_config['imap']['tlsportDisabled']) && $autodiscover_config['imap']['tlsportDisabled'] === TRUE;
      break;
    case 'imaps':
      $_disabled = isset($autodiscover_config['imap']['portDisabled']) && $autodiscover_config['imap']['portDisabled'] === TRUE;
      break;
    case 'pop3':
      $_disabled = isset($autodiscover_config['pop3']['tlsportDisabled']) && $autodiscover_config['pop3']['tlsportDisabled'] === TRUE;
      break;
    case 'pop3s':
      $_disabled = isset($autodiscover_config['pop3']['portDisabled']) && $autodiscover_config['pop3']['portDisabled'] === TRUE;
      break;
    case 'smtps':
      $_disabled = isset($autodiscover_config['smtp']['portDisabled']) && $autodiscover_config['smtp']['portDisabled'] === TRUE;
      break;
    case 'submission':
      $_disabled = isset($autodiscover_config['smtp']['tlsportDisabled']) && $autodiscover_config['smtp']['tlsportDisabled'] === TRUE;
      break;
  }
  // If the port is disabled in the config, do not even bother to check the DNS records.
  if ($_disabled === TRUE) {
    return FALSE;
  }
  // Check whether the service is announced as "not provided" via a SRV record.
  $_records = dns_get_record('_' . $_service_type .'._tcp.' . $domain, DNS_SRV);
  return $_records === FALSE || count($_records) == 0 || $_records[0]['target'] != '';
}

header('Content-Type: application/xml');
?>
<?= '<?xml version="1.0"?>'; ?>
<clientConfig version="1.1">
    <emailProvider id="<?=$mailcow_hostname; ?>">
      <domain>%EMAILDOMAIN%</domain>
      <displayName>A mailcow mail server</displayName>
      <displayShortName>mail server</displayShortName>

<?php
if (autoconfig_service_enabled('imaps')) { ?>
      <incomingServer type="imap">
         <hostname><?=$autodiscover_config['imap']['server']; ?></hostname>
         <port><?=$autodiscover_config['imap']['port']; ?></port>
         <socketType>SSL</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>
<?php
if (autoconfig_service_enabled('imap')) { ?>
      <incomingServer type="imap">
         <hostname><?=$autodiscover_config['imap']['server']; ?></hostname>
         <port><?=$autodiscover_config['imap']['tlsport']; ?></port>
         <socketType>STARTTLS</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>

<?php
if (autoconfig_service_enabled('pop3s')) { ?>
      <incomingServer type="pop3">
         <hostname><?=$autodiscover_config['pop3']['server']; ?></hostname>
         <port><?=$autodiscover_config['pop3']['port']; ?></port>
         <socketType>SSL</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>
<?php
if (autoconfig_service_enabled('pop3')) { ?>
      <incomingServer type="pop3">
         <hostname><?=$autodiscover_config['pop3']['server']; ?></hostname>
         <port><?=$autodiscover_config['pop3']['tlsport']; ?></port>
         <socketType>STARTTLS</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
<?php } ?>

<?php
if (autoconfig_service_enabled('smtps')) { ?>
      <outgoingServer type="smtp">
         <hostname><?=$autodiscover_config['smtp']['server']; ?></hostname>
         <port><?=$autodiscover_config['smtp']['port']; ?></port>
         <socketType>SSL</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </outgoingServer>
<?php } ?>
<?php
if (autoconfig_service_enabled('submission')) { ?>
      <outgoingServer type="smtp">
         <hostname><?=$autodiscover_config['smtp']['server']; ?></hostname>
         <port><?=$autodiscover_config['smtp']['tlsport']; ?></port>
         <socketType>STARTTLS</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </outgoingServer>
<?php } ?>

      <enable visiturl="https://<?=$mailcow_hostname; ?><?php if ($port != 443) echo ':'.$port; ?>/admin">
         <instruction>If you didn't change the password given to you by the administrator or if you didn't change it in a long time, please consider doing that now.</instruction>
         <instruction lang="de">Sollten Sie das Ihnen durch den Administrator vergebene Passwort noch nicht geändert haben, empfehlen wir dies nun zu tun. Auch ein altes Passwort sollte aus Sicherheitsgründen geändert werden.</instruction>
      </enable>

    </emailProvider>

    <webMail>
      <loginPage url="https://<?=$mailcow_hostname; ?><?php if ($port != 443) echo ':'.$port; ?>/SOGo/" />
    </webMail>
</clientConfig>
