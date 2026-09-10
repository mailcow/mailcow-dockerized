<?php
// Per-user OAuth2 authorization_code flow for IMAP sync sources.
// Opened as a popup from the sync-job modal:
//   ?action=start&source_id=N  -> redirect the logged-in user to the provider consent screen
//   (callback, ?code&state)    -> exchange code, store the per-user token, message the opener
// The resulting token is bound to the authenticated remote identity, which fixes syncjob user1.
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

function syncjob_oauth_redirect_uri() {
  // Must match the redirect URI registered at the provider.
  return 'https://' . $_SERVER['HTTP_HOST'] . '/syncjob-oauth';
}

function syncjob_oauth_finish($ok, $payload) {
  // Renders a tiny page that hands the result back to the opener modal and closes the popup.
  header('Content-Type: text/html; charset=utf-8');
  $json = json_encode(array_merge(array('type' => 'syncjob_oauth', 'ok' => $ok), $payload));
  echo '<!doctype html><meta charset="utf-8"><title>OAuth</title><body><script>'
     . 'try { if (window.opener) window.opener.postMessage(' . $json . ', window.location.origin); } catch (e) {}'
     . 'window.close();'
     . 'document.body.innerText = ' . json_encode($ok ? 'Connected. You can close this window.' : 'Authentication failed. You can close this window.') . ';'
     . '</script></body>';
  exit;
}

// Must be an authenticated session; users additionally need the syncjobs ACL.
if (empty($_SESSION['mailcow_cc_username']) || empty($_SESSION['mailcow_cc_role'])) {
  header('Location: /');
  exit;
}
if ($_SESSION['mailcow_cc_role'] == 'user' && ($_SESSION['acl']['syncjobs'] ?? '0') != '1') {
  syncjob_oauth_finish(false, array('error' => 'access_denied'));
}

$action = isset($_GET['action']) ? $_GET['action'] : ((isset($_GET['code']) || isset($_GET['error'])) ? 'callback' : '');

if ($action == 'start') {
  $source_id = intval($_GET['source_id'] ?? 0);
  // get/source enforces visibility for the current session
  $source = syncjob('get', 'source', array('id' => $source_id, 'with_secret' => true));
  if (!$source || $source['auth_type'] != 'XOAUTH2' || $source['oauth_flow'] != 'authorization_code') {
    syncjob_oauth_finish(false, array('error' => 'source_unavailable'));
  }
  $state = bin2hex(random_bytes(16));
  $_SESSION['syncjob_oauth2state'] = $state;
  $_SESSION['syncjob_oauth_source_id'] = $source_id;
  header('Location: ' . imapsync_source_oauth_authorize_url($source, syncjob_oauth_redirect_uri(), $state));
  exit;
}

// --- callback ---
if (isset($_GET['error'])) {
  syncjob_oauth_finish(false, array('error' => substr((string)$_GET['error'], 0, 200)));
}
// CSRF: state must match the one stored at 'start'
if (empty($_GET['code']) || empty($_GET['state']) || !hash_equals((string)($_SESSION['syncjob_oauth2state'] ?? ''), (string)$_GET['state'])) {
  syncjob_oauth_finish(false, array('error' => 'invalid_state'));
}
$source_id = intval($_SESSION['syncjob_oauth_source_id'] ?? 0);
unset($_SESSION['syncjob_oauth2state'], $_SESSION['syncjob_oauth_source_id']);

$source = syncjob('get', 'source', array('id' => $source_id, 'with_secret' => true));
if (!$source || $source['auth_type'] != 'XOAUTH2' || $source['oauth_flow'] != 'authorization_code') {
  syncjob_oauth_finish(false, array('error' => 'source_unavailable'));
}

$token = imapsync_source_token_post($source['oauth_token_endpoint'], array(
  'grant_type' => 'authorization_code',
  'client_id' => $source['oauth_client_id'],
  'client_secret' => $source['oauth_client_secret'],
  'code' => $_GET['code'],
  'redirect_uri' => syncjob_oauth_redirect_uri(),
  'scope' => $source['oauth_scope'],
));
if (isset($token['error'])) {
  syncjob_oauth_finish(false, array('error' => $token['error']));
}

$email = imapsync_source_oauth_identity($source, $token);
if (!$email) {
  syncjob_oauth_finish(false, array('error' => 'no_identity'));
}

imapsync_source_store_user_token($source_id, $email, $token);
syncjob_oauth_finish(true, array('source_id' => $source_id, 'user1' => $email));
