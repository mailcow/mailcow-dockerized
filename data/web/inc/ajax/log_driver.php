<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header('Content-Type: text/plain');
if (!isset($_SESSION['mailcow_cc_role'])) {
	exit();
}
if (isset($_GET['type']) && isset($_GET['msg'])) {
  global $mailcow_hostname;
  //empty
}
?>
