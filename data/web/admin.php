<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != "admin") {
  header('Location: /');
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$tfa_data = get_tfa();
$fido2_data = fido2(array("action" => "get_friendly_names"));
if (!isset($_SESSION['gal']) && $license_cache = $redis->Get('LICENSE_STATUS_CACHE')) {
  $_SESSION['gal'] = json_decode($license_cache, true);
}

$js_minifier->add('/web/js/site/admin.js');
$js_minifier->add('/web/js/presets/rspamd.js');
$js_minifier->add('/web/js/site/pwgen.js');

// all domains
$domains = mailbox('get', 'domains');
$all_domains =  array_merge($domains, mailbox('get', 'alias_domains'));

// mailboxes
$mailboxes = [];
foreach ($all_domains as $domain) {
  foreach (mailbox('get', 'mailboxes', $domain) as $mailbox) {
    $mailboxes[] = $mailbox;
  }
}
$mailboxes = array_filter($mailboxes);

// DKIM domains
$dkim_domains = [];
$dkim_domains_with_keys = [];
foreach($domains as $domain) {
  $dkim_domains[$domain] = ['dkim' => null, 'alias_domains' => []];
  if (!empty($dkim = dkim('details', $domain))) {
    $dkim_domains_with_keys[] = $domain;
    if ($GLOBALS['SHOW_DKIM_PRIV_KEYS'] !== true) {
      $dkim['privkey'] = base64_encode('Please set $SHOW_DKIM_PRIV_KEYS to true to show DKIM private keys.');
    }
    $dkim_domains[$domain]['dkim'] = $dkim;
  }

  // get alias domains
  foreach (mailbox('get', 'alias_domains', $domain) as $alias_domain) {
    $dkim_domains[$domain]['alias_domains'][$alias_domain] = ['dkim' => null];
    if (!empty($dkim = dkim('details', $alias_domain))) {
      $dkim_domains_with_keys[] = $alias_domain;
      if ($GLOBALS['SHOW_DKIM_PRIV_KEYS'] !== true) {
        $dkim['privkey'] = base64_encode('Please set $SHOW_DKIM_PRIV_KEYS to true to show DKIM private keys.');
      }
      $dkim_domains[$domain]['alias_domains'][$alias_domain]['dkim'] = $dkim;
    }
  }
}
$dkim_blind_domains = [];
foreach(dkim('blind') as $blind) {
  $dkim_blind_domains[$blind] = ['dkim' => null];
  if (!empty($dkim = dkim('details', $blind))) {
    $dkim_domains_with_keys[] = $blind;
    if ($GLOBALS['SHOW_DKIM_PRIV_KEYS'] !== true) {
      $dkim['privkey'] = base64_encode('Please set $SHOW_DKIM_PRIV_KEYS to true to show DKIM private keys.');
    }
    $dkim_blind_domains[$blind]['dkim'] = $dkim;
  }
}

// rsettings
$rsettings = array_map(function ($rsetting){
  $rsetting['details'] = rsettings('details', $rsetting['id']);
  return $rsetting;
}, rsettings('get'));

// rspamd regex maps
$rspamd_regex_maps = [];
foreach ($RSPAMD_MAPS['regex'] as $rspamd_regex_desc => $rspamd_regex_map) {
  $rspamd_regex_maps[$rspamd_regex_desc] = [
    'map' => $rspamd_regex_map,
    'data' => file_get_contents('/rspamd_custom_maps/' . $rspamd_regex_map)
  ];
}


$template = 'admin.twig';
$template_data = [
  'tfa_data' => $tfa_data,
  'tfa_id' => @$_SESSION['tfa_id'],
  'fido2_cid' => @$_SESSION['fido2_cid'],
  'fido2_data' => $fido2_data,
  'gal' => @$_SESSION['gal'],
  'license_guid' => license('guid'),
  'api' => [
    'ro' => admin_api('ro', 'get'),
    'rw' => admin_api('rw', 'get'),
  ],
  'dkim_domains' => $dkim_domains,
  'dkim_domains_with_keys' => $dkim_domains_with_keys,
  'dkim_blind_domains' => $dkim_blind_domains,
  'domains' => $domains,
  'all_domains' => $all_domains,
  'mailboxes' => $mailboxes,
  'f2b_data' => fail2ban('get'),
  'q_data' => quarantine('settings'),
  'qn_data' => quota_notification('get'),
  'rsettings_map' => file_get_contents('http://nginx:8081/settings.php'),
  'rsettings' => $rsettings,
  'rspamd_regex_maps' => $rspamd_regex_maps,
  'logo_specs' => customize('get', 'main_logo_specs'),
  'password_complexity' => password_complexity('get'),
  'show_rspamd_global_filters' => @$_SESSION['show_rspamd_global_filters'],
  'lang_admin' => json_encode($lang['admin']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
