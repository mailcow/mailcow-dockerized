<?php
require_once 'inc/prerequisites.inc.php';
require_once 'inc/spf.inc.php';
require_once 'inc/clientconfig.inc.php';

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

function in_net($addr, $net) {
  $net = explode('/', $net);
  if (count($net) > 1) {
    $mask = $net[1];
  }
  $net = inet_pton($net[0]);
  $addr = inet_pton($addr);
  $length = strlen($net); // 4 for IPv4, 16 for IPv6
  if (strlen($net) != strlen($addr)) {
    return false;
  }
  if (!isset($mask)) {
    $mask = $length * 8;
  }
  $addr_bin = '';
  $net_bin = '';
  for ($i = 0; $i < $length; ++$i) {
    $addr_bin .= str_pad(decbin(ord(substr($addr, $i, $i+1))), 8, '0', STR_PAD_LEFT);
    $net_bin .= str_pad(decbin(ord(substr($net, $i, $i+1))), 8, '0', STR_PAD_LEFT);
  }
  return substr($addr_bin, 0, $mask) == substr($net_bin, 0, $mask);
}

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

$config = get_client_config();
if(file_exists('inc/vars.local.inc.php')) {
	include_once 'inc/vars.local.inc.php';
}

$records = array();
$records[] = array($mailcow_hostname, 'A', $ip);
$records[] = array($ptr, 'PTR', $mailcow_hostname);
if (!empty($ip6)) {
  $records[] = array($mailcow_hostname, 'AAAA', $ip6);
  $records[] = array($ptr6, 'PTR', $mailcow_hostname);
}
$records[] = array('_25._tcp.' . $config['smtp']['server'], 'TLSA', generate_tlsa_digest($config['smtp']['server'], 25, 1));
if (isset($config['http']['port']))
  $records[] = array('_' . $config['http']['port']    . '._tcp.' . $config['http']['server'], 'TLSA', generate_tlsa_digest($config['http']['server'], $config['http']['port']));
if (isset($config['pop3']['tlsport']))
  $records[] = array('_' . $config['pop3']['tlsport'] . '._tcp.' . $config['pop3']['server'], 'TLSA', generate_tlsa_digest($config['pop3']['server'], $config['pop3']['tlsport'], 1));
if (isset($config['imap']['tlsport']))
  $records[] = array('_' . $config['imap']['tlsport'] . '._tcp.' . $config['imap']['server'], 'TLSA', generate_tlsa_digest($config['imap']['server'], $config['imap']['tlsport'], 1));
if (isset($config['smtp']['port']))
  $records[] = array('_' . $config['smtp']['port']    . '._tcp.' . $config['smtp']['server'], 'TLSA', generate_tlsa_digest($config['smtp']['server'], $config['smtp']['port']));
if (isset($config['smtp']['tlsport']))
  $records[] = array('_' . $config['smtp']['tlsport'] . '._tcp.' . $config['smtp']['server'], 'TLSA', generate_tlsa_digest($config['smtp']['server'], $config['smtp']['tlsport'], 1));
if (isset($config['imap']['port']))
  $records[] = array('_' . $config['imap']['port']    . '._tcp.' . $config['imap']['server'], 'TLSA', generate_tlsa_digest($config['imap']['server'], $config['imap']['port']));
if (isset($config['pop3']['port']))
  $records[] = array('_' . $config['pop3']['port']    . '._tcp.' . $config['pop3']['server'], 'TLSA', generate_tlsa_digest($config['pop3']['server'], $config['pop3']['port']));
  $domains = mailbox('get', 'domains');
foreach(mailbox('get', 'domains') as $domain) {
  $domains = array_merge($domains, mailbox('get', 'alias_domains', $domain));
}

foreach ($domains as $domain) {
  $records[] = array($domain, 'MX', $mailcow_hostname);
  $records[] = array('autodiscover.' . $domain, 'CNAME', $mailcow_hostname);
  $records[] = array('autoconfig.' . $domain, 'CNAME', $mailcow_hostname);
  $records[] = array($domain, 'TXT', 'v=spf1 mx -all');
  $records[] = array('_dmarc.' . $domain, 'TXT', 'v=DMARC1; p=reject');
  
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
}

/*function record_compare($a, $b) {
  return strrev($a[0]) <=> strrev($b[0]);
}
usort($records, record_compare);*/

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
  <h3>DNS Records</h3>
  <div class="table-responsive" id="dnstable">
    <table class="table table-striped">
      <tr><th>Name</th><th>Type</th><th>Data</th><th>Status</th></tr>
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
      if (strpos($current[$data_field[$current['type']]], $record[2]) === 0)
        $state = state_good;
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
        $state = state_good;
    }
    else if (isset($data_field[$current['type']]) && $state != state_good) {
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
