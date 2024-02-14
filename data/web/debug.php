<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != "admin") {
  header('Location: /');
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$solr_status = (preg_match("/^([yY][eE][sS]|[yY])+$/", $_ENV["SKIP_SOLR"])) ? false : solr_status();
$clamd_status = (preg_match("/^([yY][eE][sS]|[yY])+$/", $_ENV["SKIP_CLAMD"])) ? false : true;


if (!isset($_SESSION['gal']) && $license_cache = $redis->Get('LICENSE_STATUS_CACHE')) {
  $_SESSION['gal'] = json_decode($license_cache, true);
}

$js_minifier->add('/web/js/site/debug.js');

// vmail df
$exec_fields = array('cmd' => 'system', 'task' => 'df', 'dir' => '/var/vmail');
$vmail_df = explode(',', (string)json_decode(docker('post', 'dovecot-mailcow', 'exec', $exec_fields), true));

// containers
$containers = (array) docker('info');
if ($clamd_status === false) unset($containers['clamd-mailcow']);
if ($solr_status === false) unset($containers['solr-mailcow']);
ksort($containers);
foreach ($containers as $container => $container_info) {
  date_default_timezone_set('UTC');
  $StartedAt = date_parse($container_info['State']['StartedAt']);
  if ($StartedAt['hour'] !== false) {
    $date = new \DateTime();
    $date->setTimestamp(mktime(
      $StartedAt['hour'],
      $StartedAt['minute'],
      $StartedAt['second'],
      $StartedAt['month'],
      $StartedAt['day'],
      $StartedAt['year']));
    try {
      $user_tz = new DateTimeZone(getenv('TZ'));
      $date->setTimezone($user_tz);
      $started = $date->format('r');
    } catch(Exception $e) {
      $started = '?';
    }
  }
  else {
    $started = '?';
  }
  $containers[$container]['State']['StartedAtHR'] = $started;
}

// get mailcow data
$hostname = getenv('MAILCOW_HOSTNAME');
$timezone = getenv('TZ');

$template = 'debug.twig';
$template_data = [
  'log_lines' => getenv('LOG_LINES'),
  'vmail_df' => $vmail_df,
  'hostname' => $hostname,
  'timezone' => $timezone,
  'gal' => @$_SESSION['gal'],
  'license_guid' => license('guid'),
  'solr_status' => $solr_status,
  'solr_uptime' => round($solr_status['status']['dovecot-fts']['uptime'] / 1000 / 60 / 60),
  'clamd_status' => $clamd_status,
  'containers' => $containers,
  'ip_check' => customize('get', 'ip_check'),
  'lang_admin' => json_encode($lang['admin']),
  'lang_debug' => json_encode($lang['debug']),
  'lang_datatables' => json_encode($lang['datatables']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';


