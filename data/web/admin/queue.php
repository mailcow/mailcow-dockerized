<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.admin.inc.php';

protect_route(['admin']);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$js_minifier->add('/web/js/site/queue.js');
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

$role = "admin";

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
