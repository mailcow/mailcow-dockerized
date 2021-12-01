<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header('Content-Type: application/json');
if (!isset($_SESSION['mailcow_cc_role'])) {
  exit();
}
if (isset($_GET['script'])) {
  $sieve = new Sieve\SieveParser();
  try {
    if (empty($_GET['script'])) {
      echo json_encode(array('type' => 3, 'msg' => $lang['danger']['script_empty']));
      exit();
    }
    $sieve->parse($_GET['script']);
  }
  catch (Exception $e) {
    echo json_encode(array('type' => 3, 'msg' => $e->getMessage()));
    exit();
  }
  echo json_encode(array('type' => 1, 'msg' => $lang['add']['validation_success']));
}
?>
