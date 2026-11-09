<?php
// E-Mail > Restore of admins and domain admins (admin/restore.php, domainadmin/restore.php set $role after protect_route):
// restores from the snapshots of the backup, also of deleted mailboxes (and domains for admins)
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

$js_minifier->add('/web/js/site/backup.js');

$template = 'restore.twig';
$template_data = [
  'acl' => $_SESSION['acl'],
  'role' => $role,
  'lang_datatables' => json_encode($lang['datatables']),
  'backup_configured' => \Mailcow\Backup\BackupModule::fromGlobals()->settings->load()->configured(),
  'backup_timezone' => getenv('TZ') ?: 'UTC',
  'backup_demo_mode' => $DEMO_MODE,
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
