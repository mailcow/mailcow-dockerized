<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!$oauth2_server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
  $oauth2_server->getResponse()->send();
  die;
}
$token = $oauth2_server->getAccessTokenData(OAuth2\Request::createFromGlobals());
$stmt = $pdo->prepare("SELECT * FROM `mailbox` WHERE `username` = :username AND `active` = '1'");
$stmt->execute(array(':username' => $token['user_id']));
$mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
if (!empty($mailbox)) {
  // Require profile while allowing additional scopes.
  if ($oauth2_server->getScopeUtil()->checkScope('profile', $token['scope'])) {
    $name = trim((string)($mailbox['name'] ?? ''));
    $name_parts = $name === '' ? array() : preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $given_name = $name_parts[0] ?? '';
    $family_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';
    header('Content-Type: application/json');
    echo json_encode(array(
      'success' => true,
      'username' => $token['user_id'],
      'id' => $token['user_id'],
      'identifier' => $token['user_id'],
      'email' => (!empty($mailbox['username']) ? $mailbox['username'] : ''),
      'email_verified' => true,
      'full_name' => ($name !== '' ? $name : 'mailcow administrative user'),
      'displayName' => ($name !== '' ? $name : 'mailcow administrative user'),
      'given_name' => $given_name,
      'family_name' => $family_name,
      'created' => (!empty($mailbox['created']) ? $mailbox['created'] : ''),
      'modified' => (!empty($mailbox['modified']) ? $mailbox['modified'] : ''),
      'active' => (!empty($mailbox['active']) ? $mailbox['active'] : ''),
    ));
    exit;
  }
}
echo json_encode(array(
  'success' => false
));
