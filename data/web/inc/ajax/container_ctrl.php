<?php
if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] !== 'empty') {
  header('HTTP/1.1 403 Forbidden');
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != 'admin') {
  exit();
}

if (!preg_match('/^[a-z\-]{0,}-mailcow/', $_GET['service'] ?? '')) {
  exit();
}

if (($_GET['action'] ?? '') !== 'restart') {
  exit();
}

$service = preg_replace('/-mailcow$/', '', $_GET['service']);
$node = isset($_GET['node']) ? preg_replace('/[^a-zA-Z0-9._\-]/', '', $_GET['node']) : '';

$args = ($node !== '') ? array('target_node' => $node) : array();
$resp = agent('request', $service, 'restart', $args, 60);
header('Content-Type: text/html; charset=utf-8');
if (agent('ok', $resp)) {
  echo '<b><span class="pull-right text-success">' . htmlspecialchars($lang['success']['service_restart_ok']) . '</span></b>';
}
else {
  $err_key = agent('error_lang', $resp);
  $err_msg = isset($lang['danger'][$err_key])
    ? sprintf($lang['danger'][$err_key], $service)
    : $lang['danger']['agent_unknown_error'];
  echo '<b><span class="pull-right text-danger">' . htmlspecialchars($err_msg) . '</span></b>';
}
