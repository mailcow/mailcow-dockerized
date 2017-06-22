<?php
require_once 'prerequisites.inc.php';
require_once 'vars.inc.php';

$client_config = array(
     'useEASforOutlook' => 'yes',
     'imap' => array(
       'server' => $mailcow_hostname,
       'port' => '993',
       'ssl' => 'on',
       'tlsport' => '143',
     ),
     'pop3' => array(
       'server' => $mailcow_hostname,
       'port' => '995',
       'ssl' => 'on',
       'tlsport' => '110',
     ),
     'smtp' => array(
       'server' => $mailcow_hostname,
       'port' => '465',
       'ssl' => 'on',
       'tlsport' => '587',
     ),
     'sogo' => array(
       'server' => $mailcow_hostname,
       'port' => '443',
       'ssl' => 'on',
     ),
     'http' => array(
       'port' => '443',
       'ssl' => 'on',
     ),
     'activesync' => array(
       'url' => 'https://'.$mailcow_hostname.'/Microsoft-Server-ActiveSync',
     ),
);

function get_client_config() {
  global $pdo, $client_config;
  $config = $client_config;
  
  // use the SRV records of the first domain to obtain the correct port numbers
  $stmt = $pdo->prepare("SELECT `domain` FROM `domain` LIMIT 1");
  $stmt->execute();
  $domain = $stmt->fetchColumn();
  
  // IMAP
  $records = dns_get_record('_imaps._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['imap']['port'] = $records[0]['port'];
  } else {
    $records = dns_get_record('_imap._tcp.' . $domain, DNS_SRV);
    if (count($records)) {
      $config['imap']['port'] = $records[0]['port'];
      $config['imap']['ssl'] = 'off';
    }
  }
  $records = dns_get_record('_imap._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['imap']['tlsport'] = $records[0]['port'];
  }
  
  // POP3
  $records = dns_get_record('_pop3s._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['pop3']['port'] = $records[0]['port'];
  } else {
    $records = dns_get_record('_pop3._tcp.' . $domain, DNS_SRV);
    if (count($records)) {
      $config['pop3']['port'] = $records[0]['port'];
      $config['pop3']['ssl'] = 'off';
    }
  }
  $records = dns_get_record('_pop3._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['pop3']['tlsport'] = $records[0]['port'];
  }
  
  // SMTP
  $records = dns_get_record('_smtps._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['smtp']['port'] = $records[0]['port'];
  } else {
    $records = dns_get_record('_submission._tcp.' . $domain, DNS_SRV);
    if (count($records)) {
      $config['smtp']['port'] = $records[0]['port'];
      $config['smtp']['ssl'] = 'off';
    }
  }
  $records = dns_get_record('_submission._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['smtp']['tlsport'] = $records[0]['port'];
  }
  
  // Web server port from Autodiscovery
  $records = dns_get_record('_autodiscovery._tcp.' . $domain, DNS_SRV);
  if (count($records)) {
    $config['sogo']['port'] = $records[0]['port'];
    $config['http']['port'] = $records[0]['port'];
    $config['activesync']['url'] = 'https://'.$mailcow_hostname.':'.$records[0]['port'].'/Microsoft-Server-ActiveSync';
  }
  
  return $config;
}
?>
