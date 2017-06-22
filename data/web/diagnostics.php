<?php
require_once("inc/prerequisites.inc.php");
require_once("inc/spf.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");

$ip = file_get_contents('http://v4.ipv6-test.com/api/myip.php');
$ip6 = @file_get_contents('http://v6.ipv6-test.com/api/myip.php');

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

function get_tlsa($host, $port, $starttls = '') {
  if ($starttls)
    $starttls = ' -starttls ' . $starttls;
  return '3 1 1 ' . trim(shell_exec('echo | openssl s_client -connect ' . $host . ':' . $port . ' -servername ' . $host . $starttls . ' 2>/dev/null | openssl x509 -noout -pubkey | openssl pkey -pubin -outform DER | openssl sha256 | awk \'{print $2}\''));
}

$records = array();
$records[] = array($mailcow_hostname, 'A', $ip);
$records[] = array($ptr, 'PTR', $mailcow_hostname);
if (!empty($ip6)) {
  $records[] = array($mailcow_hostname, 'AAAA', $ip6);
  $records[] = array($ptr6, 'PTR', $mailcow_hostname);
}
$records[] = array('_25._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 25, 'smtp'));
$records[] = array('_110._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 110, 'pop3'));
$records[] = array('_143._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 143, 'imap'));
$records[] = array('_443._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 443));
$records[] = array('_465._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 465));
$records[] = array('_587._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 587, 'smtp'));
$records[] = array('_993._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 993));
$records[] = array('_995._tcp.' . $mailcow_hostname, 'TLSA', get_tlsa($mailcow_hostname, 995));

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
  'SRV' => 'target',
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