<?php
require_once 'inc/prerequisites.inc.php';
require_once 'inc/spf.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");

$ch = curl_init('http://ipv4.mailcow.email');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
$ip = curl_exec($ch);
curl_close($ch);

$ch = curl_init('http://ipv6.mailcow.email');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
$ip6 = curl_exec($ch);
curl_close($ch);

$ptr = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
if (!empty($ip6)) {
  $ip6_full = str_replace('::', str_repeat(':', 9-substr_count($ip6, ':')), $ip6);
  $ip6_full = str_replace('::', ':0:', $ip6_full);
  $ip6_full = str_replace('::', ':0:', $ip6_full);
  $ptr6 = '';
  foreach (explode(':', $ip6_full) as $part) {
    $ptr6 .= str_pad($part, 4, '0', STR_PAD_LEFT);
  }
  $ptr6 = implode('.', array_reverse(str_split($ptr6, 1))) . '.ip6.arpa';
}

$records = array();
$records[] = array($mailcow_hostname, 'A', $ip);
$records[] = array($ptr, 'PTR', $mailcow_hostname);
if (!empty($ip6)) {
  $records[] = array($mailcow_hostname, 'AAAA', $ip6);
  $records[] = array($ptr6, 'PTR', $mailcow_hostname);
}
$tlsa_records = array();
$domains = mailbox('get', 'domains');
foreach(mailbox('get', 'domains') as $domain) {
  $domains = array_merge($domains, mailbox('get', 'alias_domains', $domain));
}

foreach ($domains as $domain) {
  $config = get_client_config($domain);
  
  if (!in_array('_25._tcp.' . $config['smtp']['server'], $tlsa_records)) {
    $records[] = array('_25._tcp.' . $config['smtp']['server'], 'TLSA', generate_tlsa_digest($config['smtp']['server'], 25, 1));
    $tlsa_records[] = '_25._tcp.' . $config['smtp']['server'];
  }
  if (isset($config['http']['port']) && !in_array('_' . $config['http']['port']    . '._tcp.' . $config['http']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['http']['port']    . '._tcp.' . $config['http']['server'], 'TLSA', generate_tlsa_digest($config['http']['server'], $config['http']['port']));
    $tlsa_records[] = '_' . $config['http']['port']    . '._tcp.' . $config['http']['server'];
  }
  if (isset($config['pop3']['tlsport']) && !in_array('_' . $config['pop3']['tlsport'] . '._tcp.' . $config['pop3']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['pop3']['tlsport'] . '._tcp.' . $config['pop3']['server'], 'TLSA', generate_tlsa_digest($config['pop3']['server'], $config['pop3']['tlsport'], 1));
    $tlsa_records[] = '_' . $config['pop3']['tlsport'] . '._tcp.' . $config['pop3']['server'];
  }
  if (isset($config['imap']['tlsport']) && !in_array('_' . $config['imap']['tlsport'] . '._tcp.' . $config['imap']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['imap']['tlsport'] . '._tcp.' . $config['imap']['server'], 'TLSA', generate_tlsa_digest($config['imap']['server'], $config['imap']['tlsport'], 1));
    $tlsa_records[] = '_' . $config['imap']['tlsport'] . '._tcp.' . $config['imap']['server'];
  }
  if (isset($config['smtp']['port']) && !in_array('_' . $config['smtp']['port']    . '._tcp.' . $config['smtp']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['smtp']['port']    . '._tcp.' . $config['smtp']['server'], 'TLSA', generate_tlsa_digest($config['smtp']['server'], $config['smtp']['port']));
    $tlsa_records[] = '_' . $config['smtp']['port']    . '._tcp.' . $config['smtp']['server'];
  }
  if (isset($config['smtp']['tlsport']) && !in_array('_' . $config['smtp']['tlsport'] . '._tcp.' . $config['smtp']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['smtp']['tlsport'] . '._tcp.' . $config['smtp']['server'], 'TLSA', generate_tlsa_digest($config['smtp']['server'], $config['smtp']['tlsport'], 1));
    $tlsa_records[] = '_' . $config['smtp']['tlsport'] . '._tcp.' . $config['smtp']['server'];
  }
  if (isset($config['imap']['port']) && !in_array('_' . $config['imap']['port']    . '._tcp.' . $config['imap']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['imap']['port']    . '._tcp.' . $config['imap']['server'], 'TLSA', generate_tlsa_digest($config['imap']['server'], $config['imap']['port']));
    $tlsa_records[] = '_' . $config['imap']['port']    . '._tcp.' . $config['imap']['server'];
  }
  if (isset($config['pop3']['port']) && !in_array('_' . $config['pop3']['port']    . '._tcp.' . $config['pop3']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['pop3']['port']    . '._tcp.' . $config['pop3']['server'], 'TLSA', generate_tlsa_digest($config['pop3']['server'], $config['pop3']['port']));
    $tlsa_records[] = '_' . $config['pop3']['port']    . '._tcp.' . $config['pop3']['server'];
  }
  if (isset($config['sieve']['port']) && !in_array('_' . $config['sieve']['port']    . '._tcp.' . $config['sieve']['server'], $tlsa_records)) {
    $records[] = array('_' . $config['sieve']['port']    . '._tcp.' . $config['sieve']['server'], 'TLSA', generate_tlsa_digest($config['sieve']['server'], $config['sieve']['port'], 1));
    $tlsa_records[] = '_' . $config['sieve']['port']    . '._tcp.' . $config['sieve']['server'];
  }
  
  $records[] = array($domain, 'MX', $mailcow_hostname);
  $records[] = array('autodiscover.' . $domain, 'CNAME', $mailcow_hostname);
  $records[] = array('_autodiscover._tcp.' . $domain, 'SRV', $config['http']['server'] . ' ' . $config['http']['port']);
  $records[] = array('autoconfig.' . $domain, 'CNAME', $mailcow_hostname);
  $records[] = array($domain, 'TXT', 'v=spf1 mx -all');
  $records[] = array('_dmarc.' . $domain, 'TXT', 'v=DMARC1; p=reject', 'v=DMARC1; p=');
  
  if (!empty($dkim = dkim('details', $domain))) {
    $records[] = array($dkim['dkim_selector'] . '._domainkey.' . $domain, 'TXT', $dkim['dkim_txt']);
  }

  if (isset($config['pop3']['tlsport'])) {
    if ($config['pop3']['tlsport'] != '110')  {
      $records[] = array('_pop3._tcp.' . $domain, 'SRV', $config['pop3']['server'] . ' ' . $config['pop3']['tlsport']);
    }
  } else {
      $records[] = array('_pop3._tcp.' . $domain, 'SRV', '. 0');
  }
  if (isset($config['pop3']['port'])) {
    if ($config['pop3']['port'] != '995')  {
      $records[] = array('_pop3s._tcp.' . $domain, 'SRV', $config['pop3']['server'] . ' ' . $config['pop3']['port']);
    }
  } else {
      $records[] = array('_pop3s._tcp.' . $domain, 'SRV', '. 0');
  }
  if (isset($config['imap']['tlsport'])) {
    if ($config['imap']['tlsport'] != '143')  {
      $records[] = array('_imap._tcp.' . $domain, 'SRV', $config['imap']['server'] . ' ' . $config['imap']['tlsport']);
    }
  } else {
      $records[] = array('_imap._tcp.' . $domain, 'SRV', '. 0');
  }
  if (isset($config['imap']['port'])) {
    if ($config['imap']['port'] != '993')  {
      $records[] = array('_imaps._tcp.' . $domain, 'SRV', $config['imap']['server'] . ' ' . $config['imap']['port']);
    }
  } else {
      $records[] = array('_imaps._tcp.' . $domain, 'SRV', '. 0');
  }
  if (isset($config['smtp']['tlsport'])) {
    if ($config['smtp']['tlsport'] != '587')  {
      $records[] = array('_submission._tcp.' . $domain, 'SRV', $config['smtp']['server'] . ' ' . $config['smtp']['tlsport']);
    }
  } else {
      $records[] = array('_submission._tcp.' . $domain, 'SRV', '. 0');
  }
  if (isset($config['smtp']['port'])) {
    if ($config['smtp']['port'] != '465')  {
      $records[] = array('_smtps._tcp.' . $domain, 'SRV', $config['smtp']['server'] . ' ' . $config['smtp']['port']);
    }
  } else {
      $records[] = array('_smtps._tcp.' . $domain, 'SRV', '. 0');
  }
  if (isset($config['sieve']['port'])) {
    if ($config['sieve']['port'] != '4190')  {
      $records[] = array('_sieve._tcp.' . $domain, 'SRV', $config['sieve']['server'] . ' ' . $config['sieve']['port']);
    }
  } else {
      $records[] = array('_sieve._tcp.' . $domain, 'SRV', '. 0');
  }
}

define('state_good',  "&#10003;");
define('state_missing',   "&#x2717;");
define('state_nomatch', "?");

$record_types = array(
  'A' => DNS_A,
  'AAAA' => DNS_AAAA,
  'CNAME' => DNS_CNAME,
  'MX' => DNS_MX,
  'PTR' => DNS_PTR,
  'SRV' => DNS_SRV,
  'TXT' => DNS_TXT,
);
$data_field = array(
  'A' => 'ip',
  'AAAA' => 'ipv6',
  'CNAME' => 'target',
  'MX' => 'target',
  'PTR' => 'target',
  'SRV' => 'data',
  'TLSA' => 'data',
  'TXT' => 'txt',
);
?>
<div class="container">
  <h3><?=$lang['diagnostics']['dns_records'];?></h3>
  <p><?=$lang['diagnostics']['dns_records_24hours'];?></p>
  <div class="table-responsive" id="dnstable">
    <table class="table table-striped">
      <tr> <th><?=$lang['diagnostics']['dns_records_name'];?></th> <th><?=$lang['diagnostics']['dns_records_type'];?></th> <th><?=$lang['diagnostics']['dns_records_data'];?></th ><th><?=$lang['diagnostics']['dns_records_status'];?></th> </tr>
<?php
foreach ($records as $record)
{
  $record[1] = strtoupper($record[1]);
  $state = state_missing;
  if ($record[1] == 'TLSA') {
    $currents = dns_get_record($record[0], 52, $_, $_, TRUE);
    foreach ($currents as &$current) {
      $current['type'] = 'TLSA';
      $current['cert_usage'] = hexdec(bin2hex($current['data']{0}));
      $current['selector'] = hexdec(bin2hex($current['data']{1}));
      $current['match_type'] = hexdec(bin2hex($current['data']{2}));
      $current['cert_data'] = bin2hex(substr($current['data'], 3));
      $current['data'] = $current['cert_usage'] . ' ' . $current['selector'] . ' ' . $current['match_type'] . ' ' . $current['cert_data'];
    }
    unset($current);
  }
  else {
    $currents = dns_get_record($record[0], $record_types[$record[1]]);
    if ($record[1] == 'SRV') {
      foreach ($currents as &$current) {
        if ($current['target'] == '') {
          $current['target'] = '.';
          $current['port'] = '0';
        }
        $current['data'] = $current['target'] . ' ' . $current['port'];
      }
      unset($current);
    }
  }
  
  foreach ($currents as $current) {
    $current['type'] == strtoupper($current['type']);
    if ($current['type'] != $record[1])
    {
      continue;
    }
    
    if ($current['type'] == 'TXT' && strpos($record[0], '_dmarc.') === 0) {
      $state = state_nomatch;
      if (strpos($current[$data_field[$current['type']]], $record[3]) === 0)
        $state = state_good . ' (' . current[$data_field[$current['type']]] . ')';
    }
    else if ($current['type'] == 'TXT' && strpos($current['txt'], 'v=spf1') === 0) {
      $allowed = get_spf_allowed_hosts($record[0]);
      $spf_ok = FALSE;
      $spf_ok6 = FALSE;
      foreach ($allowed as $net)
      {
        if (in_net($ip, $net))
          $spf_ok = TRUE;
        if (in_net($ip6, $net))
          $spf_ok6 = TRUE;
      }
      if ($spf_ok && (empty($ip6) || $spf_ok6))
        $state = state_good . ' (' . $current[$data_field[$current['type']]] . ')';
    }
    else if ($current['type'] != 'TXT' && isset($data_field[$current['type']]) && $state != state_good) {
      $state = state_nomatch;
      if ($current[$data_field[$current['type']]] == $record[2])
        $state = state_good;
    }
  }
  
  if ($state == state_nomatch) {
    $state = array();
    foreach ($currents as $current) {
      $state[] = $current[$data_field[$current['type']]];
    }
    $state = implode('<br />', $state);
  }
  
  echo sprintf('<tr><td>%s</td><td>%s</td><td style="max-width: 300px; word-break: break-all">%s</td><td style="max-width: 150px; word-break: break-all">%s</td></tr>', $record[0], $record[1], $record[2], $state);
}
?>
    </table>
	</div>
</div>
<?php
require_once("inc/footer.inc.php");
} else {
  header('Location: index.php');
  exit();
}
?>
