<?php
// Per-user OAuth2 authorization_code flow for IMAP sync sources.
// Opened as a popup from the sync-job modal:
//   ?action=start&source_id=N&mailbox=M -> redirect the logged-in user to the provider consent screen
//   (callback, ?code&state)             -> exchange code, store the token for mailbox M, message the opener
// The token is bound to the local mailbox M (owner) and the authenticated remote identity (syncjob user1).
// The redirect URI is always https://MAILCOW_HOSTNAME/syncjob-oauth, so the UI must be used on that host.
// postMessage only carries short error codes, the UI translates them:
//   access_denied, mailbox_denied, source_unavailable, provider_denied, invalid_state, token_failed, no_identity
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

function syncjob_oauth_finish($ok, $payload) {
  // Renders a tiny page that hands the result back to the opener modal and closes the popup.
  global $lang;
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store');
  $json = json_encode(array_merge(array('type' => 'syncjob_oauth', 'ok' => $ok), $payload),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  $title = $lang['syncjobs']['oauth_popup_title'] ?? 'Sign in with the provider';
  $text = $ok
    ? ($lang['syncjobs']['oauth_popup_success'] ?? 'Connected. You can close this window.')
    : ($lang['syncjobs']['oauth_popup_failed'] ?? 'Authentication failed. You can close this window.');
  echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title></head>'
     . '<body><p>' . htmlspecialchars($text) . '</p><script>'
     . 'try { if (window.opener) window.opener.postMessage(' . $json . ', window.location.origin); } catch (e) {}'
     . 'window.close();'
     . '</script></body></html>';
  exit;
}

// Must be an authenticated session with the syncjobs ACL (user_acl / da_acl; always set for admins).
if (empty($_SESSION['mailcow_cc_username']) || empty($_SESSION['mailcow_cc_role'])) {
  header('Location: /');
  exit;
}
$context = \Mailcow\Core\Context::fromSession($_SESSION);
$imapsync = \Mailcow\Imapsync\ImapsyncModule::fromGlobals($context);
if (!$context->hasAcl('syncjobs')) {
  syncjob_oauth_finish(false, array('error' => 'access_denied'));
}

$action = isset($_GET['action']) ? $_GET['action'] : ((isset($_GET['code']) || isset($_GET['error'])) ? 'callback' : '');

if ($action == 'start') {
  $source_id = intval($_GET['source_id'] ?? 0);
  // C1: the token is stored for this local mailbox; default is the own mailbox (role user)
  $mailbox = (isset($_GET['mailbox']) && is_string($_GET['mailbox']) && $_GET['mailbox'] !== '')
    ? strtolower(trim($_GET['mailbox'])) : $_SESSION['mailcow_cc_username'];
  if (!filter_var($mailbox, FILTER_VALIDATE_EMAIL) || !$context->canAccessMailbox($mailbox)) {
    syncjob_oauth_finish(false, array('error' => 'mailbox_denied'));
  }
  // findConnectable enforces visibility for the current session
  $state = bin2hex(random_bytes(16));
  $source = $imapsync->sources->findConnectable($source_id);
  $url = $source ? $imapsync->oauth->authorizeUrl($source, $state) : null;
  if ($url === null) {
    syncjob_oauth_finish(false, array('error' => 'source_unavailable'));
  }
  $_SESSION['syncjob_oauth2state'] = $state;
  $_SESSION['syncjob_oauth_source_id'] = $source_id;
  $_SESSION['syncjob_oauth_mailbox'] = $mailbox;
  header('Location: ' . $url);
  exit;
}

// --- callback ---
// CSRF: state must match the one stored at 'start'
$state_ok = !empty($_GET['state']) && is_string($_GET['state'])
  && hash_equals((string)($_SESSION['syncjob_oauth2state'] ?? ''), $_GET['state']);
$source_id = intval($_SESSION['syncjob_oauth_source_id'] ?? 0);
$mailbox = (string)($_SESSION['syncjob_oauth_mailbox'] ?? '');
if ($state_ok) {
  unset($_SESSION['syncjob_oauth2state'], $_SESSION['syncjob_oauth_source_id'], $_SESSION['syncjob_oauth_mailbox']);
}
if (isset($_GET['error'])) {
  // Provider error codes are not passed through (only a fixed code)
  syncjob_oauth_finish(false, array('error' => 'provider_denied'));
}
if (!$state_ok || empty($_GET['code']) || !is_string($_GET['code']) || $source_id <= 0 || $mailbox === '') {
  syncjob_oauth_finish(false, array('error' => 'invalid_state'));
}
// Re-check access to the owner mailbox (session could have changed in between)
if (!$context->canAccessMailbox($mailbox)) {
  syncjob_oauth_finish(false, array('error' => 'mailbox_denied'));
}

$source = $imapsync->sources->findConnectable($source_id);
if (!$source) {
  syncjob_oauth_finish(false, array('error' => 'source_unavailable'));
}

// C1: the token is bound to the local owner mailbox and the remote identity (id_token claims, else userinfo).
// Sanitized details go to the php-fpm log only, the opener gets a fixed code.
try {
  $user1 = $imapsync->oauth->connectUser($source, $mailbox, $_GET['code']);
}
catch (\Mailcow\Core\UserError $e) {
  if ($e->args) {
    error_log('syncjob-oauth: ' . $e->args[0]);
  }
  syncjob_oauth_finish(false, array('error' => $e->key));
}
syncjob_oauth_finish(true, array('source_id' => $source_id, 'mailbox' => $mailbox, 'user1' => $user1));
