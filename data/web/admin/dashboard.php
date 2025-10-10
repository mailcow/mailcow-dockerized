<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.admin.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {
  header('Location: /domainadmin/mailbox');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
  header('Location: /user');
  exit();
}
elseif (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != "admin") {
  header('Location: /admin');
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$clamd_status = (preg_match("/^([yY][eE][sS]|[yY])+$/", $_ENV["SKIP_CLAMD"])) ? false : true;
$olefy_status = (preg_match("/^([yY][eE][sS]|[yY])+$/", $_ENV["SKIP_OLEFY"])) ? false : true;


if (!isset($_SESSION['gal']) && $license_cache = $valkey->Get('LICENSE_STATUS_CACHE')) {
  $_SESSION['gal'] = json_decode($license_cache, true);
}

$js_minifier->add('/web/js/site/dashboard.js');

// vmail df
$exec_fields = array('cmd' => 'system', 'task' => 'df', 'dir' => '/var/vmail');
$vmail_df = explode(',', (string)json_decode(docker('post', 'dovecot-mailcow', 'exec', $exec_fields), true));

// containers
$containers_info = (array) docker('info');
if ($clamd_status === false) unset($containers_info['clamd-mailcow']);
if ($olefy_status === false) unset($containers_info['olefy-mailcow']);
ksort($containers_info);
$containers = array();
foreach ($containers_info as $container => $container_info) {
  if (!isset($container_info['State']) || !is_array($container_info['State']) || !isset($container_info['State']['StartedAt'])){
    continue;
  }
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
      $container_info['State']['StartedAtHR'] = $date->format('r');
    } catch(Exception $e) {
      $container_info['State']['StartedAtHR'] = '?';
    }
  }
  else {
    $container_info['State']['StartedAtHR'] = '?';
  }
  $containers[$container] = $container_info;
}

// get mailcow data
$hostname = getenv('MAILCOW_HOSTNAME');
$timezone = getenv('TZ');

$template = 'dashboard.twig';
$template_data = [
  'log_lines' => getenv('LOG_LINES'),
  'vmail_df' => $vmail_df,
  'hostname' => $hostname,
  'timezone' => $timezone,
  'gal' => @$_SESSION['gal'],
  'license_guid' => license('guid'),
  'clamd_status' => $clamd_status,
  'olefy_status' => $olefy_status,
  'containers' => $containers,
  'ip_check' => customize('get', 'ip_check'),
  'lang_admin' => json_encode($lang['admin']),
  'lang_debug' => json_encode($lang['debug']),
  'lang_datatables' => json_encode($lang['datatables']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';


