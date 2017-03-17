<?php
require_once 'inc/prerequisites.inc.php';
error_reporting(E_ALL);
if (isset($_SESSION['mailcow_cc_role']) || isset($_SESSION['pending_mailcow_cc_username'])) {
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
      case "get_u2f_reg_challenge":
        if (
          ($_SESSION["mailcow_cc_role"] == "admin" || $_SESSION["mailcow_cc_role"] == "domainadmin")
          &&
          ($_SESSION["mailcow_cc_username"] == $object)
        ) {
          $data = $u2f->getRegisterData(get_u2f_registrations($object));
          list($req, $sigs) = $data;
          $_SESSION['regReq'] = json_encode($req);
          echo 'var req = ' . json_encode($req) . '; var sigs = ' . json_encode($sigs) . ';';
        }
        else {
          echo '{}';
        }
        break;
      case "get_u2f_auth_challenge":
        if (isset($_SESSION['pending_mailcow_cc_username']) && $_SESSION['pending_mailcow_cc_username'] == $object) {
          $reqs = json_encode($u2f->getAuthenticateData(get_u2f_registrations($object)));
          $_SESSION['authReq']  = $reqs;
          echo 'var req = ' . $reqs . ';';
        }
        else {
          echo '{}';
        }
        break;
      default:
        echo '{}';
        break;
    }
  }
}