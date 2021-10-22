<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SESSION['mailcow_cc_role'])) {
  $_SESSION['oauth2_request'] = $_SERVER['REQUEST_URI'];
  header('Location: /?oauth');
}

$request = OAuth2\Request::createFromGlobals();
$response = new OAuth2\Response();

if (!$oauth2_server->validateAuthorizeRequest($request, $response)) {
  $response->send();
  exit;
}

if (!isset($_POST['authorized'])) {
  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';

  $template = 'oauth/authorize.twig';
  $template_data = [];

  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
  exit;
}

// print the authorization code if the user has authorized your client
$is_authorized = ($_POST['authorized'] == '1');
$oauth2_server->handleAuthorizeRequest($request, $response, $is_authorized, $_SESSION['mailcow_cc_username']);
if ($is_authorized) {
  unset($_SESSION['oauth2_request']);
  if ($GLOBALS['OAUTH2_FORGET_SESSION_AFTER_LOGIN'] === true) {
    session_unset();
    session_destroy();
  }
  header('Location: ' . $response->getHttpHeader('Location'));
  exit;
}
