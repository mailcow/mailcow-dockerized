<?php
require_once 'inc/prerequisites.inc.php';
require_once 'inc/spf.inc.php';

define('state_good',  "&#10003;");
define('state_missing',   "&#x2717;");
define('state_nomatch', "?");
define('state_optional', "(optional)");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");

$ch = curl_init('http://ip4.mailcow.email');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
$ip = curl_exec($ch);
curl_close($ch);

$ch = curl_init('http://ip6.mailcow.email');
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

$https_port = strpos($_SERVER['HTTP_HOST'], ':');
if ($https_port === FALSE) {
  $https_port = 443;
} else {
  $https_port = substr($_SERVER['HTTP_HOST'], $https_port+1);
}

$records = array();
$records[] = array($mailcow_hostname, 'A', $ip);
$records[] = array($ptr, 'PTR', $mailcow_hostname);
if (!empty($ip6)) {
  $records[] = array($mailcow_hostname, 'AAAA', $ip6);
  $records[] = array($ptr6, 'PTR', $mailcow_hostname);
}
$domains = mailbox('get', 'domains');
foreach(mailbox('get', 'domains') as $domain) {
  $domains = array_merge($domains, mailbox('get', 'alias_domains', $domain));
}

if (!isset($autodiscover_config['sieve'])) {
  $autodiscover_config['sieve'] = array('server' => $mailcow_hostname, 'port' => array_pop(explode(':', getenv('SIEVE_PORT'))));
}

$records[] = array('_25._tcp.'                                              . $autodiscover_config['smtp']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['smtp']['server'], 25, 1));
$records[] = array('_' . $https_port    . '._tcp.' . $mailcow_hostname, 'TLSA', generate_tlsa_digest($mailcow_hostname, $https_port));
$records[] = array('_' . $autodiscover_config['pop3']['tlsport'] . '._tcp.' . $autodiscover_config['pop3']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['pop3']['server'], $autodiscover_config['pop3']['tlsport'], 1));
$records[] = array('_' . $autodiscover_config['imap']['tlsport'] . '._tcp.' . $autodiscover_config['imap']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['imap']['server'], $autodiscover_config['imap']['tlsport'], 1));
$records[] = array('_' . $autodiscover_config['smtp']['port']    . '._tcp.' . $autodiscover_config['smtp']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['smtp']['server'], $autodiscover_config['smtp']['port']));
$records[] = array('_' . $autodiscover_config['smtp']['tlsport'] . '._tcp.' . $autodiscover_config['smtp']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['smtp']['server'], $autodiscover_config['smtp']['tlsport'], 1));
$records[] = array('_' . $autodiscover_config['imap']['port']    . '._tcp.' . $autodiscover_config['imap']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['imap']['server'], $autodiscover_config['imap']['port']));
$records[] = array('_' . $autodiscover_config['pop3']['port']    . '._tcp.' . $autodiscover_config['pop3']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['pop3']['server'], $autodiscover_config['pop3']['port']));
$records[] = array('_' . $autodiscover_config['sieve']['port']   . '._tcp.' . $autodiscover_config['sieve']['server'], 'TLSA', generate_tlsa_digest($autodiscover_config['sieve']['server'], $autodiscover_config['sieve']['port'], 1));

foreach ($domains as $domain) {
  $records[] = array($domain, 'MX', $mailcow_hostname);
  $records[] = array('autodiscover.' . $domain, 'CNAME', $mailcow_hostname);
  $records[] = array('_autodiscover._tcp.' . $domain, 'SRV', $mailcow_hostname . ' ' . $https_port);
  $records[] = array('autoconfig.' . $domain, 'CNAME', $mailcow_hostname);
  $records[] = array($domain, 'TXT', '<a href="http://www.openspf.org/SPF_Record_Syntax" target="_blank">SPF Record Syntax</a>', state_optional);
  $records[] = array('_dmarc.' . $domain, 'TXT', '<a href="http://www.kitterman.com/dmarc/assistant.html" target="_blank">DMARC Assistant</a>', state_optional);
  
  if (!empty($dkim = dkim('details', $domain))) {
    $records[] = array($dkim['dkim_selector'] . '._domainkey.' . $domain, 'TXT', $dkim['dkim_txt']);
  }

  $current_records = dns_get_record('_pop3._tcp.' . $domain, DNS_SRV);
  if (count($current_records) == 0 || $current_records[0]['target'] != '') {
    if ($autodiscover_config['pop3']['tlsport'] != '110')  {
      $records[] = array('_pop3._tcp.' . $domain, 'SRV', $autodiscover_config['pop3']['server'] . ' ' . $autodiscover_config['pop3']['tlsport']);
    }
  } else {
      $records[] = array('_pop3._tcp.' . $domain, 'SRV', '. 0');
  }
  $current_records = dns_get_record('_pop3s._tcp.' . $domain, DNS_SRV);
  if (count($current_records) == 0 || $current_records[0]['target'] != '') {
    if ($autodiscover_config['pop3']['port'] != '995')  {
      $records[] = array('_pop3s._tcp.' . $domain, 'SRV', $autodiscover_config['pop3']['server'] . ' ' . $autodiscover_config['pop3']['port']);
    }
  } else {
      $records[] = array('_pop3s._tcp.' . $domain, 'SRV', '. 0');
  }
  if ($autodiscover_config['imap']['tlsport'] != '143')  {
    $records[] = array('_imap._tcp.' . $domain, 'SRV', $autodiscover_config['imap']['server'] . ' ' . $autodiscover_config['imap']['tlsport']);
  }
  if ($autodiscover_config['imap']['port'] != '993')  {
    $records[] = array('_imaps._tcp.' . $domain, 'SRV', $autodiscover_config['imap']['server'] . ' ' . $autodiscover_config['imap']['port']);
  }
  if ($autodiscover_config['smtp']['tlsport'] != '587')  {
    $records[] = array('_submission._tcp.' . $domain, 'SRV', $autodiscover_config['smtp']['server'] . ' ' . $autodiscover_config['smtp']['tlsport']);
  }
  if ($autodiscover_config['smtp']['port'] != '465')  {
    $records[] = array('_smtps._tcp.' . $domain, 'SRV', $autodiscover_config['smtp']['server'] . ' ' . $autodiscover_config['smtp']['port']);
  }
  if ($autodiscover_config['sieve']['port'] != '4190')  {
    $records[] = array('_sieve._tcp.' . $domain, 'SRV', $autodiscover_config['sieve']['server'] . ' ' . $autodiscover_config['sieve']['port']);
  }
}

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
  
  if ($record[1] == 'CNAME' && count($currents) == 0) {
    // A and AAAA are also valid instead of CNAME
    $a = dns_get_record($record[0], DNS_A);
    $cname = dns_get_record($record[2], DNS_A);
    if (count($a) > 0 && count($cname) > 0) {
      if ($a[0]['ip'] == $cname[0]['ip']) {
        $currents = array(array('host' => $record[0], 'class' => 'IN', 'type' => 'CNAME', 'target' => $record[2]));
      
        $aaaa = dns_get_record($record[0], DNS_AAAA);
        $cname = dns_get_record($record[2], DNS_AAAA);
        if (count($aaaa) == 0 || count($cname) == 0 || $aaaa[0]['ipv6'] != $cname[0]['ipv6']) {
          $currents[0]['target'] = $aaaa[0]['ipv6'];
        }
      } else {
        $currents = array(array('host' => $record[0], 'class' => 'IN', 'type' => 'CNAME', 'target' => $a[0]['ip']));
      }
    }
  }
  
  foreach ($currents as $current) {
    $current['type'] == strtoupper($current['type']);
    if ($current['type'] != $record[1])
    {
      continue;
    }
    
    elseif ($current['type'] == 'TXT' && strpos($record[0], '_dmarc.') === 0) {
      $state = state_optional . '<br />' . $current[$data_field[$current['type']]];
    }
    else if ($current['type'] == 'TXT' && strpos($current['txt'], 'v=spf1') === 0) {
      $state = state_optional . '<br />' . $current[$data_field[$current['type']]];
    }
    else if ($current['type'] != 'TXT' && isset($data_field[$current['type']]) && $state != state_good) {
      $state = state_nomatch;
      if ($current[$data_field[$current['type']]] == $record[2])
        $state = state_good;
    }
  }
  
  if (isset($record[3]) && $record[3] == state_optional && ($state == state_missing || $state == state_nomatch)) {
    $state = state_optional;
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
