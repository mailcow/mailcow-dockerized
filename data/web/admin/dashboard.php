<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.admin.inc.php';

protect_route(['admin']);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$clamd_status = (preg_match("/^([yY][eE][sS]|[yY])+$/", $_ENV["SKIP_CLAMD"])) ? false : true;
$olefy_status = (preg_match("/^([yY][eE][sS]|[yY])+$/", $_ENV["SKIP_OLEFY"])) ? false : true;


if (!isset($_SESSION['gal']) && $license_cache = $redis->Get('LICENSE_STATUS_CACHE')) {
  $_SESSION['gal'] = json_decode($license_cache, true);
}

$js_minifier->add('/web/js/site/dashboard.js');

$vmail_df_resp = agent('request', 'dovecot', 'exec.df', array('dir' => '/var/vmail'), 5);
$vmail_df = (!empty($vmail_df_resp['ok']) && is_string($vmail_df_resp['result']))
  ? explode(',', $vmail_df_resp['result'])
  : array('', '', '', '', '', '/var/vmail');

$known_services = agent('services');

try {
  $tz_obj = new DateTimeZone(getenv('TZ') ?: 'UTC');
}
catch (Exception $e) {
  $tz_obj = new DateTimeZone('UTC');
}

$containers = array();
foreach ($known_services as $svc) {
  $live_nodes = agent('live_nodes', $svc);
  $running = !empty($live_nodes);
  $first_node = $running ? $live_nodes[0] : '';
  $first_meta = $running ? (agent('node_meta', $svc, $first_node) ?: array()) : array();

  $started_at_hr = '—';
  $started_at_iso = isset($first_meta['started_at']) ? $first_meta['started_at'] : '';
  if ($started_at_iso !== '') {
    try {
      $d = new DateTime($started_at_iso);
      $d->setTimezone($tz_obj);
      $started_at_hr = $d->format('r');
    }
    catch (Exception $e) {}
  }

  $nodes = array();
  $unhealthy_nodes = 0;
  $first_unhealthy_detail = '';
  foreach ($live_nodes as $n) {
    $m = agent('node_meta', $svc, $n) ?: array();
    $s = agent('node_stats', $svc, $n) ?: array();
    $node_health = isset($m['health']) ? $m['health'] : '';
    $node_health_detail = isset($m['health_detail']) ? $m['health_detail'] : '';
    if ($node_health === 'fail') {
      $unhealthy_nodes++;
      if ($first_unhealthy_detail === '') {
        $first_unhealthy_detail = $node_health_detail;
      }
    }
    $nodes[] = array(
      'NodeId' => $n,
      'Image' => isset($m['image']) ? $m['image'] : '',
      'StartedAt' => isset($m['started_at']) ? $m['started_at'] : '',
      'Version' => isset($m['version']) ? $m['version'] : '',
      'CPUPercent' => isset($s['cpu_percent']) ? $s['cpu_percent'] : '',
      'MemoryBytes' => isset($s['memory_bytes']) ? $s['memory_bytes'] : '',
      'Health' => $node_health,
      'HealthDetail' => $node_health_detail
    );
  }

  $service_health = 'unknown';
  if ($running) {
    $service_health = ($unhealthy_nodes === 0) ? 'ok' : (($unhealthy_nodes === count($live_nodes)) ? 'fail' : 'degraded');
  }

  $containers[$svc . '-mailcow'] = array(
    'Service' => $svc,
    'State' => array(
      'Running' => $running ? 1 : 0,
      'NodeCount' => count($live_nodes),
      'UnhealthyCount' => $unhealthy_nodes,
      'Health' => $service_health,
      'HealthDetail' => $first_unhealthy_detail,
      'StartedAt' => $started_at_iso,
      'StartedAtHR' => $started_at_hr
    ),
    'Config' => array(
      'Image' => isset($first_meta['image']) ? $first_meta['image'] : ''
    ),
    'Id' => $first_node,
    'Nodes' => $nodes,
    'External' => false
  );
}

$infra_containers = infra('status');
ksort($containers);

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
  'infra_containers' => $infra_containers,
  'ip_check' => customize('get', 'ip_check'),
  'lang_admin' => json_encode($lang['admin']),
  'lang_debug' => json_encode($lang['debug']),
  'lang_datatables' => json_encode($lang['datatables']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
