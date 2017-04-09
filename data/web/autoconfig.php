<?php
require_once "inc/vars.inc.php";
if (empty($mailcow_hostname)) { exit(); }
header("Content-Type: application/xml");
?>
<?='<?xml version="1.0"?>';?>
<clientConfig version="1.1">
    <emailProvider id="<?=$mailcow_hostname;?>">
      <domain>%EMAILDOMAIN%</domain>
      <displayName>A mailcow mail server</displayName>
      <displayShortName>mail server</displayShortName>

      <incomingServer type="imap">
         <hostname><?=$mailcow_hostname;?></hostname>
         <port>993</port>
         <socketType>SSL</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
      <incomingServer type="imap">
         <hostname><?=$mailcow_hostname;?></hostname>
         <port>143</port>
         <socketType>STARTTLS</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>

      <incomingServer type="pop3">
         <hostname><?=$mailcow_hostname;?></hostname>
         <port>995</port>
         <socketType>SSL</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>
      <incomingServer type="pop3">
         <hostname><?=$mailcow_hostname;?></hostname>
         <port>110</port>
         <socketType>STARTTLS</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </incomingServer>

      <outgoingServer type="smtp">
         <hostname><?=$mailcow_hostname;?></hostname>
         <port>465</port>
         <socketType>SSL</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </outgoingServer>

      <outgoingServer type="smtp">
         <hostname><?=$mailcow_hostname;?></hostname>
         <port>587</port>
         <socketType>STARTTLS</socketType>
         <username>%EMAILADDRESS%</username>
         <authentication>password-cleartext</authentication>
      </outgoingServer>

      <enable visiturl="https://<?=$mailcow_hostname;?>/admin.php">
         <instruction>If you didn't change the password given to you by the administrator or if you didn't change it in a long time, please consider doing that now.</instruction>
         <instruction lang="de">Sollten Sie das Ihnen durch den Administrator vergebene Passwort noch nicht geändert haben, empfehlen wir dies nun zu tun. Auch ein altes Passwort sollte aus Sicherheitsgründen geändert werden.</instruction>
      </enable>

    </emailProvider>

    <webMail>
      <loginPage url="https://<?=$mailcow_hostname;?>/SOGo/" />
    </webMail>
</clientConfig>
