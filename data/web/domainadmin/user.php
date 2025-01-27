<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.domainadmin.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {

  /*
  / DOMAIN ADMIN
  */

  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
  $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
  $tfa_data = get_tfa();
  $fido2_data = fido2(array("action" => "get_friendly_names"));
  $username = $_SESSION['mailcow_cc_username'];

  $template = 'domainadmin.twig';
  $template_data = [
    'acl' => $_SESSION['acl'],
    'acl_json' => json_encode($_SESSION['acl']),
    'user_spam_score' => mailbox('get', 'spam_score', $username),
    'tfa_data' => $tfa_data,
    'fido2_data' => $fido2_data,
    'lang_user' => json_encode($lang['user']),
    'lang_datatables' => json_encode($lang['datatables']),
  ];
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
  header('Location: /admin/dashboard');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
  header('Location: /user');
  exit();
}
else {
  header('Location: /domainadmin');
  exit();
}

$js_minifier->add('/web/js/site/user.js');
$js_minifier->add('/web/js/site/pwgen.js');

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
