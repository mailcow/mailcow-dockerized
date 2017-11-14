<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != 'admin') {
	exit();
}

if ($_GET['ACTION'] == "start") {
  $retry = 0;
  while (docker('sogo-mailcow', 'info')['State']['Running'] != 1 && $retry <= 3) {
    $response = docker('sogo-mailcow', 'post', 'start');
    $response = json_decode($response, true);
    $last_response = ($response['type'] == "success") ? '<b><span class="pull-right text-success">OK</span></b>' : '<b><span class="pull-right text-danger">Error: ' . $response['msg'] . '</span></b>';
    if ($response['type'] == "success") {
      break;
    }
    usleep(1500000);
    $retry++;
  }
  echo (!isset($last_response)) ? '<b><span class="pull-right text-warning">Already running</span></b>' : $last_response;
}

if ($_GET['ACTION'] == "stop") {
  $retry = 0;
  while (docker('sogo-mailcow', 'info')['State']['Running'] == 1 && $retry <= 3) {
    $response = docker('sogo-mailcow', 'post', 'stop');
    $response = json_decode($response, true);
    $last_response = ($response['type'] == "success") ? '<b><span class="pull-right text-success">OK</span></b>' : '<b><span class="pull-right text-danger">Error: ' . $response['msg'] . '</span></b>';
    if ($response['type'] == "success") {
      break;
    }
    usleep(1500000);
    $retry++;
  }
  echo (!isset($last_response)) ? '<b><span class="pull-right text-warning">Not running</span></b>' : $last_response;
}

?>
