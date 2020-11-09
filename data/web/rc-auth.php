<?php

$ALLOW_ADMIN_EMAIL_LOGIN_ROUNDCUBE = (preg_match(
  "/^(yes|y)+$/i",
  $_ENV["ALLOW_ADMIN_EMAIL_LOGIN_ROUNDCUBE"]
));

// prevent if feature is disabled
if (!$ALLOW_ADMIN_EMAIL_LOGIN_ROUNDCUBE) {
  header('HTTP/1.0 403 Forbidden');
  echo "this feature is disabled";
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
$AuthUsers = array("admin", "domainadmin");
if (!isset($_SESSION['mailcow_cc_role']) || !in_array($_SESSION['mailcow_cc_role'], $AuthUsers) || $_SESSION['acl']['login_as'] != "1") {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$login = html_entity_decode(rawurldecode($_GET["login"]));
if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $login)){
    header('HTTP/1.0 403 Forbidden');
    exit();
}

// find roundcube installation

if (empty($MAILCOW_APPS)){
    header('HTTP/1.0 501 Not Implemented');
    echo "Roundcube is not installed";
    exit();
}

$rc_path = null;
foreach ($MAILCOW_APPS as $app) {
    $filename = $_SERVER['DOCUMENT_ROOT'] . $app['link'] . 'README.md';
    if (is_file($filename) && file_get_contents($filename, false, null, 0, 9) == 'Roundcube'){
        $rc_path = $app['link'];
        break;
    }
}

if (!$rc_path){
    header('HTTP/1.0 501 Not Implemented');
    echo "Roundcube is not installed";
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/RoundcubeAutoLogin.php';

$url = "https://$mailcow_hostname:" . $_SERVER['SERVER_PORT'] . $rc_path;
$rc = new RoundcubeAutoLogin($url);

list($master_user, $master_passwd) = explode(':', trim(file_get_contents('/etc/sogo/sieve.creds')));

$cookies = $rc->login($login . '*' . $master_user, $master_passwd);

foreach ($cookies as $cookie_name => $cookie_value) {
    setcookie($cookie_name, $cookie_value, 0, '/', '');
}

$rc->redirect();