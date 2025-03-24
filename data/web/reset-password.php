<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
  header('Location: /admin/dashboard');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {
  header('Location: /domainadmin/mailbox');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
  header('Location: /user');
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$_SESSION['index_query_string'] = $_SERVER['QUERY_STRING'];

if (isset($_GET['token'])) $is_reset_token_valid = reset_password("check", $_GET['token']);
else $is_reset_token_valid = False;

$template = 'reset-password.twig';
$template_data = [
  'is_mobileconfig' => str_contains($_SESSION['index_query_string'], 'mobileconfig'),
  'is_reset_token_valid' => $is_reset_token_valid,
  'reset_token' => $_GET['token']
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
