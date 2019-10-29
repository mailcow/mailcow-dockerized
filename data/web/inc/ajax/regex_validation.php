<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header('Content-Type: application/json');
if (!isset($_SESSION['mailcow_cc_role'])) {
  exit();
}
if (isset($_GET['regex'])) {
  $regex_lines = preg_split("/(\r\n|\n|\r)/", $_GET['regex']);
  foreach ($regex_lines as $line => $regex) {
    if (empty($regex) || substr($regex, 0, 1) == "#") {
      continue;
    }
    if (empty($regex) || substr($regex, 0, 1) != "/") {
      echo json_encode(array('type' => 'danger', 'msg' => 'Line ' . ($line + 1) . ': Invalid regex'));
      exit();
    }    
    if (@preg_match($regex, 'Lorem Ipsum') === false) {
      echo json_encode(array('type' => 'danger', 'msg' => 'Line ' . ($line + 1) . ': Invalid regex "' . $regex . '"'));
      exit();
    }
  }
  echo json_encode(array('type' => 'success', 'msg' => $lang['add']['validation_success']));
}
?>
