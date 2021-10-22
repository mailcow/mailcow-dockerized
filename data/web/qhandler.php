<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
if (quarantine('hash_details', $_GET['hash']) === false && !isset($_POST)) {
  header('Location: /admin');
  exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';

$js_minifier->add('/web/js/site/qhandler.js');

$template = 'qhandler.twig';
$template_data = [
  'quick_release' => preg_match("/^([a-f0-9]{64})$/", $_POST['quick_release']),
  'quick_delete' => preg_match("/^([a-f0-9]{64})$/", $_POST['quick_delete']),
  'is_action_release_delete' => in_array($_GET['action'], array('release', 'delete')),
  'is_hash_present' => preg_match("/^([a-f0-9]{64})$/", $_GET['hash']),
  'action' => $_GET['action'],
  'hash' => $_GET['hash'],
  'lang_quarantine' => json_encode($lang['quarantine']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';

