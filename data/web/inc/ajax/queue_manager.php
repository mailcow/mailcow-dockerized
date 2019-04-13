<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header('Content-Type: text/plain');
if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != 'admin') {
  exit();
}
$docker_return = docker('post', 'postfix-mailcow', 'exec', array('cmd' => 'mailq'));

if (isset($docker_return['type']['danger'])) {
  echo "Cannot load mail queue: " . $docker_return['msg'];
}
else {
  echo $docker_return;
}
?>
