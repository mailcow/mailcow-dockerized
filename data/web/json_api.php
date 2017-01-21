<?php
require_once 'inc/prerequisites.inc.php';
error_reporting(0);
if (isset($_SESSION['mailcow_cc_role'])) {
  if ($_GET['action'] && $_GET['object']) {
    $action = $_GET['action'];
    $object = $_GET['object'];
    switch ($action) {
      case "get_mailbox_details":
        $data = mailbox_get_mailbox_details($object);
        if (!$data || empty($data)) {
          echo '{}';
        }
        else {
          echo json_encode(mailbox_get_mailbox_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        break;
      case "get_domain_details":
        $data = mailbox_get_domain_details($object);
        if (!$data || empty($data)) {
          echo '{}';
        }
        else {
          echo json_encode(mailbox_get_domain_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        break;
      default:
        echo '{}';
        break;
    }
  }
}