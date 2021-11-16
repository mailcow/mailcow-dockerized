<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SESSION['mailcow_cc_role'])) {
  header('Location: /');
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$quarantine_settings = quarantine('settings');

$js_minifier->add('/web/js/site/quarantine.js');

$role = ($_SESSION['mailcow_cc_role'] == "admin") ? 'admin' : 'domainadmin';

$template = 'quarantine.twig';
$template_data = [
  'acl' => $_SESSION['acl'],
  'acl_json' => json_encode($_SESSION['acl']),
  'role' => $role,
  'quarantine_settings' => $quarantine_settings,
  'lang_quarantine' => json_encode($lang['quarantine']),
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
