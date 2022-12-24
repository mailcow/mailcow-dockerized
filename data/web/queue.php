<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] != "admin") {
  header('Location: /');
  exit();
}


require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$js_minifier->add('/web/js/site/queue.js');
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

$role = ($_SESSION['mailcow_cc_role'] == "admin") ? 'admin' : 'domainadmin';

$template = 'queue.twig';
$template_data = [
  'acl' => $_SESSION['acl'],
  'acl_json' => json_encode($_SESSION['acl']),
  'role' => $role,
  'lang_admin' => json_encode($lang['admin']),
  'lang_queue' => json_encode($lang['queue']),
  'lang_datatables' => json_encode($lang['datatables'])
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
