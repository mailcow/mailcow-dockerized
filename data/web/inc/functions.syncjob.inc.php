<?php
// Sync jobs + their reusable IMAP sources.
//
// Source visibility is driven by `scope` + the target tables `imapsync_source_domain`
// and `imapsync_source_user`; `created_by` records the creator (provenance + edit right).
//
// scope values:
//   'all'    → visible to everyone (admin-created only)
//   'domain' → visible to the listed domains (their domain admins + users)
//   'user'   → visible to the listed users (+ their domain admins)
//
// Read rules:
//   admin       → all
//   domainadmin → own + 'all' + sources targeting his domains/users + sources created by his mailboxes
//   user        → own + 'all' + sources targeting him or his domain
// Edit rules:
//   admin → all; creator → own; domainadmin → sources created by his mailboxes.
//
// File-private helpers stay top-level because the OAuth refresh helper is invoked
// from cron context without an active session. The `imapsync_source_*` prefix marks
// them as module-local; do not call them from outside this file.

function imapsync_source_visible_sql(&$params) {
  // Returns a SQL fragment scoped to the current session, to be ANDed onto a WHERE
  // clause over `imapsync_source s`. Fills $params with the required binds.
  $role = $_SESSION['mailcow_cc_role'] ?? null;
  $username = $_SESSION['mailcow_cc_username'] ?? null;
  if ($role == 'admin') {
    return '1=1';
  }
  // EMULATE_PREPARES is off, so a placeholder may appear only once — bind the same
  // username under a distinct name per use. `$mgd` is the caller's managed-domain set.
  $mgd = "SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = ";
  if ($role == 'domainadmin') {
    $params[':vis_self'] = $username;
    $params[':vis_d1'] = $username;
    $params[':vis_d2'] = $username;
    $params[':vis_d3'] = $username;
    return "(s.`created_by` = :vis_self
             OR s.`scope` = 'all'
             OR (s.`scope` = 'domain' AND EXISTS (
                   SELECT 1 FROM `imapsync_source_domain` t
                     WHERE t.`source_id` = s.`id` AND t.`domain` IN ($mgd :vis_d1)))
             OR (s.`scope` = 'user' AND EXISTS (
                   SELECT 1 FROM `imapsync_source_user` t
                     JOIN `mailbox` mb ON mb.`username` = t.`username`
                     WHERE t.`source_id` = s.`id` AND mb.`domain` IN ($mgd :vis_d2)))
             OR s.`created_by` IN (
                   SELECT `username` FROM `mailbox` WHERE `domain` IN ($mgd :vis_d3)))";
  }
  if ($role == 'user') {
    $params[':vis_self'] = $username;
    $params[':vis_u1'] = $username;
    $params[':vis_u2'] = $username;
    return "(s.`created_by` = :vis_self
             OR s.`scope` = 'all'
             OR (s.`scope` = 'user' AND EXISTS (
                   SELECT 1 FROM `imapsync_source_user` t
                     WHERE t.`source_id` = s.`id` AND t.`username` = :vis_u1))
             OR (s.`scope` = 'domain' AND EXISTS (
                   SELECT 1 FROM `imapsync_source_domain` t
                     JOIN `mailbox` mb ON mb.`domain` = t.`domain`
                     WHERE t.`source_id` = s.`id` AND mb.`username` = :vis_u2)))";
  }
  return '0=1';
}

function imapsync_source_can_edit_row($row) {
  // True if the current session may edit/delete the given source row.
  $role = $_SESSION['mailcow_cc_role'] ?? null;
  $username = $_SESSION['mailcow_cc_username'] ?? null;
  if (isset($_SESSION['access_all_exception']) && $_SESSION['access_all_exception'] == "1") {
    return true;
  }
  if ($role == 'admin') {
    return true;
  }
  if (empty($row)) {
    return false;
  }
  if ($row['created_by'] === $username) {
    return true;
  }
  if ($role == 'domainadmin' && $row['created_by'] !== '') {
    // A domainadmin may manage sources created by mailboxes of his domains.
    return hasMailboxObjectAccess($username, $role, $row['created_by']);
  }
  return false;
}

function imapsync_source_apply_scope($source_id, $role, $scope, $domains, $users, $_data_log) {
  // (Re)writes the scope target tables for a source and validates the requested
  // scope/targets against the session role. Returns true on success, false (and
  // appends a $_SESSION['return'] error) otherwise. Wipes targets not matching scope.
  global $pdo;
  $username = $_SESSION['mailcow_cc_username'] ?? null;
  $deny = function() use (&$_data_log) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array('imapsync_source_apply_scope', 'edit', 'source', $_data_log),
      'msg' => 'access_denied'
    );
    return false;
  };

  if (!in_array($scope, array('all', 'domain', 'user'))) return $deny();
  // Only admins may create globally visible sources; users are always private.
  if ($scope == 'all' && $role != 'admin') return $deny();
  if ($role == 'user') { $scope = 'user'; $domains = array(); $users = array(); }

  // Guardrail: an XOAUTH2 client_credentials source uses one app-wide token that can reach
  // every mailbox — it must never be shareable. Force it private to its creator.
  $src = $pdo->prepare("SELECT `auth_type`, `oauth_flow` FROM `imapsync_source` WHERE `id` = :id");
  $src->execute(array(':id' => $source_id));
  $src = $src->fetch(PDO::FETCH_ASSOC);
  if ($src && $src['auth_type'] == 'XOAUTH2' && $src['oauth_flow'] == 'client_credentials') {
    $scope = 'user'; $domains = array(); $users = array();
  }

  $domains = ($scope == 'domain') ? array_unique(array_filter((array)$domains)) : array();
  $users = ($scope == 'user') ? array_unique(array_filter((array)$users)) : array();

  // A user-scoped source with no explicit targets is private to its creator (valid).
  // A domain-scoped source must name at least one domain.
  if ($scope == 'domain' && empty($domains)) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array('imapsync_source_apply_scope', 'edit', 'source', $_data_log),
      'msg' => 'imapsync_source_scope_empty'
    );
    return false;
  }

  foreach ($domains as $d) {
    if (!hasDomainAccess($username, $role, $d)) return $deny();
  }
  foreach ($users as $u) {
    if (!hasMailboxObjectAccess($username, $role, $u)) return $deny();
  }

  $pdo->prepare("DELETE FROM `imapsync_source_domain` WHERE `source_id` = :id")->execute(array(':id' => $source_id));
  $pdo->prepare("DELETE FROM `imapsync_source_user` WHERE `source_id` = :id")->execute(array(':id' => $source_id));
  $ins_d = $pdo->prepare("INSERT INTO `imapsync_source_domain` (`source_id`, `domain`) VALUES (:id, :domain)");
  foreach ($domains as $d) { $ins_d->execute(array(':id' => $source_id, ':domain' => $d)); }
  $ins_u = $pdo->prepare("INSERT INTO `imapsync_source_user` (`source_id`, `username`) VALUES (:id, :username)");
  foreach ($users as $u) { $ins_u->execute(array(':id' => $source_id, ':username' => $u)); }
  // apply_scope owns the scope column so the guardrail/coercion above stays consistent with the targets
  $pdo->prepare("UPDATE `imapsync_source` SET `scope` = :scope WHERE `id` = :id")
    ->execute(array(':scope' => $scope, ':id' => $source_id));
  return true;
}

function imapsync_source_refresh_token_internal($source_id) {
  // Context-free: invoked by web UI (with ACL upstream) and by cron (no session).
  // Performs OAuth2 Client-Credentials Grant against source.oauth_token_endpoint,
  // stores the resulting access_token + absolute expiry timestamp, or records the error.
  global $pdo;

  $stmt = $pdo->prepare("SELECT * FROM `imapsync_source` WHERE `id` = :id AND `auth_type` = 'XOAUTH2'");
  $stmt->execute(array(':id' => $source_id));
  $src = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$src) return false;

  $body = array(
    'grant_type' => 'client_credentials',
    'client_id' => $src['oauth_client_id'],
    'client_secret' => $src['oauth_client_secret'],
    'scope' => $src['oauth_scope'],
  );
  if (!empty($src['oauth_extra_params'])) {
    $extra = json_decode($src['oauth_extra_params'], true);
    if (is_array($extra)) {
      $body = array_merge($body, $extra);
    }
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $src['oauth_token_endpoint']);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_err = curl_error($ch);
  curl_close($ch);

  $record_error = function($message) use ($pdo, $source_id) {
    $stmt = $pdo->prepare("UPDATE `imapsync_source` SET `oauth_last_refresh_error` = :err WHERE `id` = :id");
    $stmt->execute(array(':err' => $message, ':id' => $source_id));
  };

  if ($response === false) {
    $record_error("curl error: " . $curl_err);
    return false;
  }
  if ($http_code < 200 || $http_code >= 300) {
    $record_error("HTTP $http_code: " . substr($response, 0, 500));
    return false;
  }
  $data = json_decode($response, true);
  if (!is_array($data) || empty($data['access_token']) || !isset($data['expires_in'])) {
    $record_error("malformed token response: " . substr($response, 0, 500));
    return false;
  }
  $expires_at = time() + intval($data['expires_in']);

  $stmt = $pdo->prepare("UPDATE `imapsync_source`
    SET `oauth_access_token` = :tok,
        `oauth_token_expires` = :exp,
        `oauth_last_refresh_error` = NULL
    WHERE `id` = :id");
  $stmt->execute(array(
    ':tok' => $data['access_token'],
    ':exp' => $expires_at,
    ':id' => $source_id,
  ));
  return true;
}

function imapsync_source_token_post($endpoint, $body) {
  // Shared token-endpoint POST (application/x-www-form-urlencoded). Returns the decoded
  // token array on success, or array('error' => '...') on failure. Used by the
  // authorization_code exchange and the per-user refresh_token grant.
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_err = curl_error($ch);
  curl_close($ch);

  if ($response === false) return array('error' => "curl error: " . $curl_err);
  if ($http_code < 200 || $http_code >= 300) return array('error' => "HTTP $http_code: " . substr($response, 0, 500));
  $data = json_decode($response, true);
  if (!is_array($data) || empty($data['access_token']) || !isset($data['expires_in'])) {
    return array('error' => "malformed token response: " . substr($response, 0, 500));
  }
  return $data;
}

function imapsync_source_oauth_authorize_url($source, $redirect_uri, $state) {
  // Builds the provider authorization URL for the authorization_code flow.
  $params = array(
    'client_id' => $source['oauth_client_id'],
    'response_type' => 'code',
    'redirect_uri' => $redirect_uri,
    'response_mode' => 'query',
    'scope' => $source['oauth_scope'],
    'state' => $state,
  );
  $sep = (strpos($source['oauth_authorize_endpoint'], '?') === false) ? '?' : '&';
  return $source['oauth_authorize_endpoint'] . $sep . http_build_query($params);
}

function imapsync_source_oauth_identity($source, $token) {
  // Resolves the authenticated remote address from an authorization_code token response.
  // Prefers the id_token claims (no extra HTTP call); falls back to the userinfo endpoint.
  $pick = function($claims) {
    foreach (array('email', 'preferred_username', 'upn', 'unique_name') as $k) {
      if (!empty($claims[$k]) && filter_var($claims[$k], FILTER_VALIDATE_EMAIL)) return strtolower($claims[$k]);
    }
    return null;
  };
  if (!empty($token['id_token'])) {
    $parts = explode('.', $token['id_token']);
    if (count($parts) === 3) {
      $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
      if (is_array($claims) && ($addr = $pick($claims))) return $addr;
    }
  }
  if (!empty($source['oauth_userinfo_endpoint'])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $source['oauth_userinfo_endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token['access_token']));
    $response = curl_exec($ch);
    curl_close($ch);
    $info = json_decode((string)$response, true);
    if (is_array($info) && ($addr = $pick($info))) return $addr;
  }
  return null;
}

function imapsync_source_store_user_token($source_id, $username, $token) {
  // Upserts a per-user delegated token row for an authorization_code source.
  global $pdo;
  $stmt = $pdo->prepare("INSERT INTO `imapsync_source_oauth_token`
      (`source_id`, `username`, `access_token`, `refresh_token`, `token_expires`, `last_refresh_error`)
    VALUES (:sid, :user, :acc, :ref, :exp, NULL)
    ON DUPLICATE KEY UPDATE
      `access_token` = VALUES(`access_token`),
      `refresh_token` = VALUES(`refresh_token`),
      `token_expires` = VALUES(`token_expires`),
      `last_refresh_error` = NULL");
  $stmt->execute(array(
    ':sid' => $source_id,
    ':user' => $username,
    ':acc' => $token['access_token'],
    // Microsoft only returns a refresh_token when offline_access was requested; keep old one otherwise
    ':ref' => $token['refresh_token'] ?? '',
    ':exp' => time() + intval($token['expires_in']),
  ));
}

function imapsync_source_refresh_user_token_internal($source_id, $username) {
  // Context-free per-user refresh (web + cron). Uses the stored refresh_token to obtain a
  // fresh access_token for one (source, user); records the error on the token row otherwise.
  global $pdo;
  $stmt = $pdo->prepare("SELECT s.*, t.`refresh_token`
    FROM `imapsync_source_oauth_token` t
    JOIN `imapsync_source` s ON s.`id` = t.`source_id`
    WHERE t.`source_id` = :sid AND t.`username` = :user
      AND s.`auth_type` = 'XOAUTH2' AND s.`oauth_flow` = 'authorization_code'");
  $stmt->execute(array(':sid' => $source_id, ':user' => $username));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['refresh_token'])) return false;

  $result = imapsync_source_token_post($row['oauth_token_endpoint'], array(
    'grant_type' => 'refresh_token',
    'client_id' => $row['oauth_client_id'],
    'client_secret' => $row['oauth_client_secret'],
    'refresh_token' => $row['refresh_token'],
    'scope' => $row['oauth_scope'],
  ));
  if (isset($result['error'])) {
    $upd = $pdo->prepare("UPDATE `imapsync_source_oauth_token` SET `last_refresh_error` = :err
      WHERE `source_id` = :sid AND `username` = :user");
    $upd->execute(array(':err' => $result['error'], ':sid' => $source_id, ':user' => $username));
    return false;
  }
  // Some providers omit a new refresh_token on refresh — keep the existing one then
  if (empty($result['refresh_token'])) $result['refresh_token'] = $row['refresh_token'];
  imapsync_source_store_user_token($source_id, $username, $result);
  return true;
}

function syncjob($_action, $_type, $_data = null, $_extra = null) {
  global $pdo;
  global $lang;
  $_data_log = $_data;

  switch ($_action) {
    case 'add':
      switch ($_type) {
        case 'job':
          if (!isset($_SESSION['acl']['syncjobs']) || $_SESSION['acl']['syncjobs'] != "1" ) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'access_denied'
            );
            return false;
          }
          if (isset($_data['username']) && filter_var($_data['username'], FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data['username'])) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'access_denied'
              );
              return false;
            }
            else {
              $username = $_data['username'];
            }
          }
          elseif ($_SESSION['mailcow_cc_role'] == "user") {
            $username = $_SESSION['mailcow_cc_username'];
          }
          else {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'no_user_defined'
            );
            return false;
          }
          $active               = intval($_data['active']);
          $subscribeall         = intval($_data['subscribeall']);
          $delete2duplicates    = intval($_data['delete2duplicates']);
          $delete1              = intval($_data['delete1']);
          $delete2              = intval($_data['delete2']);
          $timeout1             = intval($_data['timeout1']);
          $timeout2             = intval($_data['timeout2']);
          $skipcrossduplicates  = intval($_data['skipcrossduplicates']);
          $automap              = intval($_data['automap']);
          $dry                  = intval($_data['dry']);
          $source_id            = intval($_data['source_id'] ?? 0);
          $password1            = $_data['password1'] ?? '';
          $exclude              = $_data['exclude'];
          $maxage               = $_data['maxage'];
          $maxbytespersecond    = $_data['maxbytespersecond'];
          $subfolder2           = $_data['subfolder2'];
          $user1                = $_data['user1'];
          $mins_interval        = $_data['mins_interval'];
          $custom_params        = (empty(trim($_data['custom_params']))) ? '' : trim($_data['custom_params']);

          // Resolve and authorize the chosen sync source. Visibility is enforced by syncjob('get', 'source').
          $source = syncjob('get', 'source', array('id' => $source_id));
          if (empty($source) || intval($source['active']) != 1) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'imapsync_source_unavailable'
            );
            return false;
          }
          if ($source['auth_type'] == 'XOAUTH2') {
            // OAuth2 sources do not carry a password; the runner uses a cached access_token.
            $password1 = '';
            // authorization_code: the remote account must have completed the per-user login first.
            // This binds user1 to an actually-authenticated identity (no pulling foreign mailboxes).
            if ($source['oauth_flow'] == 'authorization_code') {
              $tok = $pdo->prepare("SELECT 1 FROM `imapsync_source_oauth_token`
                WHERE `source_id` = :sid AND `username` = :user");
              $tok->execute(array(':sid' => $source_id, ':user' => $user1));
              if (!$tok->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                  'msg' => 'imapsync_source_oauth_connect_required'
                );
                return false;
              }
            }
          } elseif ($password1 === '') {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'password_empty'
            );
            return false;
          }

          // validate custom params
          foreach (explode('-', $custom_params) as $param){
            if(empty($param)) continue;

            // extract option
            if (str_contains($param, '=')) $param = explode('=', $param)[0];
            else $param = rtrim($param, ' ');
            // remove first char if first char is -
            if ($param[0] == '-') $param = ltrim($param, $param[0]);

            if (str_contains($param, ' ')) {
              // bad char
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'bad character SPACE'
              );
              return false;
            }

            // check if param is whitelisted
            if (!in_array(strtolower($param), $GLOBALS["IMAPSYNC_OPTIONS"]["whitelist"])){
              // bad option
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'bad option '. $param
              );
              return false;
            }
          }
          if (empty($subfolder2)) {
            $subfolder2 = "";
          }
          if (!isset($maxage) || !filter_var($maxage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32000)))) {
            $maxage = "0";
          }
          if (!isset($timeout1) || !filter_var($timeout1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32000)))) {
            $timeout1 = "600";
          }
          if (!isset($timeout2) || !filter_var($timeout2, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32000)))) {
            $timeout2 = "600";
          }
          if (!isset($maxbytespersecond) || !filter_var($maxbytespersecond, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 125000000)))) {
            $maxbytespersecond = "0";
          }
          if (!filter_var($mins_interval, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 43800)))) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'access_denied'
            );
            return false;
          }
          if (@preg_match("/" . $exclude . "/", null) === false) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'access_denied'
            );
            return false;
          }
          $stmt = $pdo->prepare("SELECT '1' FROM `imapsync`
            WHERE `user2` = :user2 AND `user1` = :user1 AND `source_id` = :source_id");
          $stmt->execute(array(':user1' => $user1, ':user2' => $username, ':source_id' => $source_id));
          $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
          if ($num_results != 0) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => array('object_exists', htmlspecialchars($source['name'] . ' / ' . $user1))
            );
            return false;
          }
          $stmt = $pdo->prepare("INSERT INTO `imapsync` (`user2`, `exclude`, `delete1`, `delete2`, `timeout1`, `timeout2`, `automap`, `skipcrossduplicates`, `maxbytespersecond`, `subscribeall`, `dry`, `maxage`, `subfolder2`, `source_id`, `user1`, `password1`, `mins_interval`, `delete2duplicates`, `custom_params`, `active`)
            VALUES (:user2, :exclude, :delete1, :delete2, :timeout1, :timeout2, :automap, :skipcrossduplicates, :maxbytespersecond, :subscribeall, :dry, :maxage, :subfolder2, :source_id, :user1, :password1, :mins_interval, :delete2duplicates, :custom_params, :active)");
          $stmt->execute(array(
            ':user2' => $username,
            ':custom_params' => $custom_params,
            ':exclude' => $exclude,
            ':maxage' => $maxage,
            ':delete1' => $delete1,
            ':delete2' => $delete2,
            ':timeout1' => $timeout1,
            ':timeout2' => $timeout2,
            ':automap' => $automap,
            ':skipcrossduplicates' => $skipcrossduplicates,
            ':maxbytespersecond' => $maxbytespersecond,
            ':subscribeall' => $subscribeall,
            ':dry' => $dry,
            ':subfolder2' => $subfolder2,
            ':source_id' => $source_id,
            ':user1' => $user1,
            ':password1' => $password1,
            ':mins_interval' => $mins_interval,
            ':delete2duplicates' => $delete2duplicates,
            ':active' => $active,
          ));
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
            'msg' => array('mailbox_modified', $username)
          );
        break;
        case 'source':
          $role = $_SESSION['mailcow_cc_role'] ?? null;
          if (!in_array($role, array('admin', 'domainadmin', 'user'))) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'access_denied'
            );
            return false;
          }
          $name = trim($_data['name'] ?? '');
          $description = trim($_data['description'] ?? '');
          $host1 = strtolower(trim($_data['host1'] ?? ''));
          $port1 = intval($_data['port1'] ?? 0);
          $enc1 = $_data['enc1'] ?? 'TLS';
          $auth_type = $_data['auth_type'] ?? 'PLAIN';
          $active = isset($_data['active']) ? intval($_data['active']) : 1;

          // Creator owns the row; visibility is driven by scope + target tables.
          $created_by = $_SESSION['mailcow_cc_username'];
          $scope = $_data['scope'] ?? 'user';
          if ($role == 'user') $scope = 'user';
          $scope_domains = (array)($_data['domains'] ?? array());
          $scope_users = (array)($_data['users'] ?? array());

          if ($name === '' || strlen($name) > 100) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'imapsync_source_name_invalid'
            );
            return false;
          }
          if ($host1 === '') {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'imapsync_source_host_invalid'
            );
            return false;
          }
          if (!filter_var($port1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)))) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'imapsync_source_port_invalid'
            );
            return false;
          }
          if (!in_array($enc1, array('TLS', 'SSL', 'PLAIN'))) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'imapsync_source_enc_invalid'
            );
            return false;
          }
          if (!in_array($auth_type, array('PLAIN', 'LOGIN', 'CRAM-MD5', 'XOAUTH2'))) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'imapsync_source_auth_invalid'
            );
            return false;
          }

          $oauth_flow = 'client_credentials';
          $oauth_token_endpoint = null;
          $oauth_authorize_endpoint = null;
          $oauth_userinfo_endpoint = null;
          $oauth_client_id = null;
          $oauth_client_secret = null;
          $oauth_scope = null;
          $oauth_extra_params = null;
          if ($auth_type == 'XOAUTH2') {
            $oauth_flow = ($_data['oauth_flow'] ?? '') === 'authorization_code' ? 'authorization_code' : 'client_credentials';
            $oauth_token_endpoint = trim($_data['oauth_token_endpoint'] ?? '');
            $oauth_authorize_endpoint = trim($_data['oauth_authorize_endpoint'] ?? '');
            $oauth_userinfo_endpoint = trim($_data['oauth_userinfo_endpoint'] ?? '');
            $oauth_client_id = trim($_data['oauth_client_id'] ?? '');
            $oauth_client_secret = $_data['oauth_client_secret'] ?? '';
            $oauth_scope = trim($_data['oauth_scope'] ?? '');
            $oauth_extra_params = trim($_data['oauth_extra_params'] ?? '');
            if ($oauth_token_endpoint === '' || $oauth_client_id === '' || $oauth_client_secret === '' || $oauth_scope === '') {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'imapsync_source_oauth_required'
              );
              return false;
            }
            // authorization_code additionally needs the authorize endpoint (user login redirect)
            if ($oauth_flow == 'authorization_code' && !filter_var($oauth_authorize_endpoint, FILTER_VALIDATE_URL)) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'imapsync_source_oauth_endpoint_invalid'
              );
              return false;
            }
            if (!filter_var($oauth_token_endpoint, FILTER_VALIDATE_URL)
                || ($oauth_userinfo_endpoint !== '' && !filter_var($oauth_userinfo_endpoint, FILTER_VALIDATE_URL))) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'imapsync_source_oauth_endpoint_invalid'
              );
              return false;
            }
            if ($oauth_extra_params !== '' && json_decode($oauth_extra_params) === null) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'imapsync_source_oauth_extra_invalid'
              );
              return false;
            }
            if ($oauth_extra_params === '') $oauth_extra_params = null;
            if ($oauth_authorize_endpoint === '') $oauth_authorize_endpoint = null;
            if ($oauth_userinfo_endpoint === '') $oauth_userinfo_endpoint = null;
          }

          // Uniqueness on (created_by, name)
          $stmt = $pdo->prepare("SELECT `id` FROM `imapsync_source`
            WHERE `name` = :name AND `created_by` = :created_by");
          $stmt->execute(array(':name' => $name, ':created_by' => $created_by));
          if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => array('object_exists', htmlspecialchars($name))
            );
            return false;
          }

          $stmt = $pdo->prepare("INSERT INTO `imapsync_source`
            (`name`, `description`, `created_by`, `scope`, `host1`, `port1`, `enc1`, `auth_type`,
             `oauth_flow`, `oauth_token_endpoint`, `oauth_authorize_endpoint`, `oauth_userinfo_endpoint`,
             `oauth_client_id`, `oauth_client_secret`, `oauth_scope`,
             `oauth_extra_params`, `active`)
            VALUES (:name, :description, :created_by, :scope, :host1, :port1, :enc1, :auth_type,
             :oauth_flow, :oauth_token_endpoint, :oauth_authorize_endpoint, :oauth_userinfo_endpoint,
             :oauth_client_id, :oauth_client_secret, :oauth_scope,
             :oauth_extra_params, :active)");
          $stmt->execute(array(
            ':name' => $name,
            ':description' => $description,
            ':created_by' => $created_by,
            ':scope' => in_array($scope, array('all', 'domain', 'user')) ? $scope : 'user',
            ':host1' => $host1,
            ':port1' => $port1,
            ':enc1' => $enc1,
            ':auth_type' => $auth_type,
            ':oauth_flow' => $oauth_flow,
            ':oauth_token_endpoint' => $oauth_token_endpoint,
            ':oauth_authorize_endpoint' => $oauth_authorize_endpoint,
            ':oauth_userinfo_endpoint' => $oauth_userinfo_endpoint,
            ':oauth_client_id' => $oauth_client_id,
            ':oauth_client_secret' => $oauth_client_secret,
            ':oauth_scope' => $oauth_scope,
            ':oauth_extra_params' => $oauth_extra_params,
            ':active' => $active,
          ));
          $source_id = $pdo->lastInsertId();
          if (!imapsync_source_apply_scope($source_id, $role, $scope, $scope_domains, $scope_users, $_data_log)) {
            // Roll back the row so a rejected scope does not leave an orphan source
            $pdo->prepare("DELETE FROM `imapsync_source` WHERE `id` = :id")->execute(array(':id' => $source_id));
            return false;
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
            'msg' => array('imapsync_source_added', $name)
          );
          return true;
        break;
      }
    break;
    case 'edit':
      switch ($_type) {
        case 'job':
          if (!is_array($_data['id'])) {
            $ids = array();
            $ids[] = $_data['id'];
          }
          else {
            $ids = $_data['id'];
          }
          if (!isset($_SESSION['acl']['syncjobs']) || $_SESSION['acl']['syncjobs'] != "1" ) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'access_denied'
            );
            return false;
          }
          foreach ($ids as $id) {
            $is_now = syncjob('get', 'job', $id, array('with_password'));
            if (!empty($is_now)) {
              $username = $is_now['user2'];
              $user1 = (!empty($_data['user1'])) ? $_data['user1'] : $is_now['user1'];
              $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active'];
              $last_run = (isset($_data['last_run'])) ? NULL : $is_now['last_run'];
              $success = (isset($_data['success'])) ? NULL : $is_now['success'];
              $delete2duplicates = (isset($_data['delete2duplicates'])) ? intval($_data['delete2duplicates']) : $is_now['delete2duplicates'];
              $subscribeall = (isset($_data['subscribeall'])) ? intval($_data['subscribeall']) : $is_now['subscribeall'];
              $dry = (isset($_data['dry'])) ? intval($_data['dry']) : $is_now['dry'];
              $delete1 = (isset($_data['delete1'])) ? intval($_data['delete1']) : $is_now['delete1'];
              $delete2 = (isset($_data['delete2'])) ? intval($_data['delete2']) : $is_now['delete2'];
              $automap = (isset($_data['automap'])) ? intval($_data['automap']) : $is_now['automap'];
              $skipcrossduplicates = (isset($_data['skipcrossduplicates'])) ? intval($_data['skipcrossduplicates']) : $is_now['skipcrossduplicates'];
              $password1 = (!empty($_data['password1'])) ? $_data['password1'] : $is_now['password1'];
              $source_id = (!empty($_data['source_id'])) ? intval($_data['source_id']) : intval($is_now['source_id']);
              $subfolder2 = (isset($_data['subfolder2'])) ? $_data['subfolder2'] : $is_now['subfolder2'];
              $mins_interval = (!empty($_data['mins_interval'])) ? $_data['mins_interval'] : $is_now['mins_interval'];
              $exclude = (isset($_data['exclude'])) ? $_data['exclude'] : $is_now['exclude'];
              $custom_params = (isset($_data['custom_params'])) ? $_data['custom_params'] : $is_now['custom_params'];
              $maxage = (isset($_data['maxage']) && $_data['maxage'] != "") ? intval($_data['maxage']) : $is_now['maxage'];
              $maxbytespersecond = (isset($_data['maxbytespersecond']) && $_data['maxbytespersecond'] != "") ? intval($_data['maxbytespersecond']) : $is_now['maxbytespersecond'];
              $timeout1 = (isset($_data['timeout1']) && $_data['timeout1'] != "") ? intval($_data['timeout1']) : $is_now['timeout1'];
              $timeout2 = (isset($_data['timeout2']) && $_data['timeout2'] != "") ? intval($_data['timeout2']) : $is_now['timeout2'];
            }
            else {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'access_denied'
              );
              continue;
            }

            // Resolve and authorize the (possibly switched) source. Visibility enforced by syncjob('get', 'source').
            $source = syncjob('get', 'source', array('id' => $source_id));
            if (empty($source) || intval($source['active']) != 1) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'imapsync_source_unavailable'
              );
              continue;
            }
            if ($source['auth_type'] == 'XOAUTH2') {
              // Wipe any leftover password when bound to an OAuth2 source.
              $password1 = '';
            }

            // validate custom params
            foreach (explode('-', $custom_params) as $param){
              if(empty($param)) continue;

              // extract option
              if (str_contains($param, '=')) $param = explode('=', $param)[0];
              else $param = rtrim($param, ' ');
              // remove first char if first char is -
              if ($param[0] == '-') $param = ltrim($param, $param[0]);

              if (str_contains($param, ' ')) {
                // bad char
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                  'msg' => 'bad character SPACE'
                );
                return false;
              }

              // check if param is whitelisted
              if (!in_array(strtolower($param), $GLOBALS["IMAPSYNC_OPTIONS"]["whitelist"])){
                // bad option
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                  'msg' => 'bad option '. $param
                );
                return false;
              }
            }
            if (empty($subfolder2)) {
              $subfolder2 = "";
            }
            if (!isset($maxage) || !filter_var($maxage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32000)))) {
              $maxage = "0";
            }
            if (!isset($timeout1) || !filter_var($timeout1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32000)))) {
              $timeout1 = "600";
            }
            if (!isset($timeout2) || !filter_var($timeout2, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32000)))) {
              $timeout2 = "600";
            }
            if (!isset($maxbytespersecond) || !filter_var($maxbytespersecond, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 125000000)))) {
              $maxbytespersecond = "0";
            }
            if (!filter_var($mins_interval, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 43800)))) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'access_denied'
              );
              continue;
            }
            if (@preg_match("/" . $exclude . "/", null) === false) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'access_denied'
              );
              continue;
            }
            $stmt = $pdo->prepare("UPDATE `imapsync` SET `delete1` = :delete1,
              `delete2` = :delete2,
              `automap` = :automap,
              `skipcrossduplicates` = :skipcrossduplicates,
              `maxage` = :maxage,
              `maxbytespersecond` = :maxbytespersecond,
              `subfolder2` = :subfolder2,
              `exclude` = :exclude,
              `source_id` = :source_id,
              `last_run` = :last_run,
              `success` = :success,
              `user1` = :user1,
              `password1` = :password1,
              `mins_interval` = :mins_interval,
              `delete2duplicates` = :delete2duplicates,
              `custom_params` = :custom_params,
              `timeout1` = :timeout1,
              `timeout2` = :timeout2,
              `subscribeall` = :subscribeall,
              `dry` = :dry,
              `active` = :active
                WHERE `id` = :id");
            $stmt->execute(array(
              ':delete1' => $delete1,
              ':delete2' => $delete2,
              ':automap' => $automap,
              ':skipcrossduplicates' => $skipcrossduplicates,
              ':id' => $id,
              ':exclude' => $exclude,
              ':maxage' => $maxage,
              ':maxbytespersecond' => $maxbytespersecond,
              ':subfolder2' => $subfolder2,
              ':source_id' => $source_id,
              ':user1' => $user1,
              ':password1' => $password1,
              ':last_run' => $last_run,
              ':success' => $success,
              ':mins_interval' => $mins_interval,
              ':delete2duplicates' => $delete2duplicates,
              ':custom_params' => $custom_params,
              ':timeout1' => $timeout1,
              ':timeout2' => $timeout2,
              ':subscribeall' => $subscribeall,
              ':dry' => $dry,
              ':active' => $active,
            ));
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => array('mailbox_modified', $username)
            );
          }
        break;
        case 'source':
          $ids = (array)($_data['id'] ?? array());
          foreach ($ids as $id) {
            if (!is_numeric($id)) continue;
            $is_now = syncjob('get', 'source', array('id' => $id, 'with_secret' => true));
            if (empty($is_now) || !imapsync_source_can_edit_row($is_now)) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'access_denied'
              );
              continue;
            }

            $name = isset($_data['name']) ? trim($_data['name']) : $is_now['name'];
            $description = isset($_data['description']) ? trim($_data['description']) : $is_now['description'];
            $host1 = isset($_data['host1']) ? strtolower(trim($_data['host1'])) : $is_now['host1'];
            $port1 = isset($_data['port1']) ? intval($_data['port1']) : $is_now['port1'];
            $enc1 = $_data['enc1'] ?? $is_now['enc1'];
            $auth_type = $_data['auth_type'] ?? $is_now['auth_type'];
            $active = isset($_data['active']) ? intval($_data['active']) : $is_now['active'];

            if ($name === '' || strlen($name) > 100 || $host1 === ''
                || !filter_var($port1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)))
                || !in_array($enc1, array('TLS', 'SSL', 'PLAIN'))
                || !in_array($auth_type, array('PLAIN', 'LOGIN', 'CRAM-MD5', 'XOAUTH2'))) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'imapsync_source_invalid'
              );
              continue;
            }

            $oauth_flow = $is_now['oauth_flow'];
            $oauth_authorize_endpoint = $is_now['oauth_authorize_endpoint'];
            $oauth_userinfo_endpoint = $is_now['oauth_userinfo_endpoint'];
            if ($auth_type == 'XOAUTH2') {
              $oauth_flow = ($_data['oauth_flow'] ?? $is_now['oauth_flow']) === 'authorization_code' ? 'authorization_code' : 'client_credentials';
              $oauth_token_endpoint = isset($_data['oauth_token_endpoint']) ? trim($_data['oauth_token_endpoint']) : $is_now['oauth_token_endpoint'];
              $oauth_authorize_endpoint = isset($_data['oauth_authorize_endpoint']) ? trim($_data['oauth_authorize_endpoint']) : $is_now['oauth_authorize_endpoint'];
              $oauth_userinfo_endpoint = isset($_data['oauth_userinfo_endpoint']) ? trim($_data['oauth_userinfo_endpoint']) : $is_now['oauth_userinfo_endpoint'];
              // Empty client_secret on edit keeps the stored one (avoids accidental wipes from masked fields)
              $oauth_client_id = isset($_data['oauth_client_id']) ? trim($_data['oauth_client_id']) : $is_now['oauth_client_id'];
              $oauth_client_secret = (!empty($_data['oauth_client_secret'])) ? $_data['oauth_client_secret'] : $is_now['oauth_client_secret'];
              $oauth_scope = isset($_data['oauth_scope']) ? trim($_data['oauth_scope']) : $is_now['oauth_scope'];
              $oauth_extra_params = isset($_data['oauth_extra_params']) ? trim($_data['oauth_extra_params']) : $is_now['oauth_extra_params'];
              if ($oauth_extra_params === '') $oauth_extra_params = null;
              if (empty($oauth_token_endpoint) || empty($oauth_client_id) || empty($oauth_client_secret) || empty($oauth_scope)
                  || !filter_var($oauth_token_endpoint, FILTER_VALIDATE_URL)
                  || ($oauth_flow == 'authorization_code' && !filter_var($oauth_authorize_endpoint, FILTER_VALIDATE_URL))
                  || (!empty($oauth_userinfo_endpoint) && !filter_var($oauth_userinfo_endpoint, FILTER_VALIDATE_URL))
                  || ($oauth_extra_params !== null && json_decode($oauth_extra_params) === null)) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                  'msg' => 'imapsync_source_oauth_invalid'
                );
                continue;
              }
              if (empty($oauth_authorize_endpoint)) $oauth_authorize_endpoint = null;
              if (empty($oauth_userinfo_endpoint)) $oauth_userinfo_endpoint = null;
            } else {
              // Wipe OAuth fields when switching away from XOAUTH2
              $oauth_flow = 'client_credentials';
              $oauth_token_endpoint = null;
              $oauth_authorize_endpoint = null;
              $oauth_userinfo_endpoint = null;
              $oauth_client_id = null;
              $oauth_client_secret = null;
              $oauth_scope = null;
              $oauth_extra_params = null;
            }

            $stmt = $pdo->prepare("UPDATE `imapsync_source` SET
              `name` = :name, `description` = :description, `host1` = :host1,
              `port1` = :port1, `enc1` = :enc1, `auth_type` = :auth_type,
              `oauth_flow` = :oauth_flow,
              `oauth_token_endpoint` = :oauth_token_endpoint,
              `oauth_authorize_endpoint` = :oauth_authorize_endpoint,
              `oauth_userinfo_endpoint` = :oauth_userinfo_endpoint,
              `oauth_client_id` = :oauth_client_id,
              `oauth_client_secret` = :oauth_client_secret,
              `oauth_scope` = :oauth_scope,
              `oauth_extra_params` = :oauth_extra_params,
              `active` = :active
              WHERE `id` = :id");
            $stmt->execute(array(
              ':name' => $name,
              ':description' => $description,
              ':host1' => $host1,
              ':port1' => $port1,
              ':enc1' => $enc1,
              ':auth_type' => $auth_type,
              ':oauth_flow' => $oauth_flow,
              ':oauth_token_endpoint' => $oauth_token_endpoint,
              ':oauth_authorize_endpoint' => $oauth_authorize_endpoint,
              ':oauth_userinfo_endpoint' => $oauth_userinfo_endpoint,
              ':oauth_client_id' => $oauth_client_id,
              ':oauth_client_secret' => $oauth_client_secret,
              ':oauth_scope' => $oauth_scope,
              ':oauth_extra_params' => $oauth_extra_params,
              ':active' => $active,
              ':id' => $id,
            ));
            // Scope/targets are editable; only touched when the request carries `scope`.
            // apply_scope also (re)writes the scope column, incl. the client_credentials guardrail.
            if (isset($_data['scope'])) {
              $scope = ($role == 'user') ? 'user' : $_data['scope'];
              if (!imapsync_source_apply_scope($id, $role, $scope,
                    (array)($_data['domains'] ?? array()), (array)($_data['users'] ?? array()), $_data_log)) {
                continue;
              }
            }
            // Guardrail also on edit: switching to client_credentials must never leave a shared scope
            // (covers the case where the request changed the flow but did not carry `scope`).
            if ($auth_type == 'XOAUTH2' && $oauth_flow == 'client_credentials') {
              $pdo->prepare("DELETE FROM `imapsync_source_domain` WHERE `source_id` = :id")->execute(array(':id' => $id));
              $pdo->prepare("DELETE FROM `imapsync_source_user` WHERE `source_id` = :id")->execute(array(':id' => $id));
              $pdo->prepare("UPDATE `imapsync_source` SET `scope` = 'user' WHERE `id` = :id")->execute(array(':id' => $id));
            }
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => array('imapsync_source_modified', $name)
            );
          }
          return true;
        break;
        case 'refresh_token':
          $id = is_array($_data) ? ($_data['id'] ?? null) : $_data;
          if (is_array($id)) $id = reset($id);
          if (!is_numeric($id)) return false;
          $is_now = syncjob('get', 'source', array('id' => $id, 'with_secret' => true));
          if (empty($is_now) || !imapsync_source_can_edit_row($is_now)) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => 'access_denied'
            );
            return false;
          }
          $ok = imapsync_source_refresh_token_internal($id);
          if ($ok) {
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => array('imapsync_source_token_refreshed', $is_now['name'])
            );
          } else {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => array('imapsync_source_token_refresh_failed', $is_now['name'])
            );
          }
          return $ok;
        break;
      }
    break;
    case 'delete':
      switch ($_type) {
        case 'job':
          if (!is_array($_data['id'])) {
            $ids = array();
            $ids[] = $_data['id'];
          }
          else {
            $ids = $_data['id'];
          }
          if (!isset($_SESSION['acl']['syncjobs']) || $_SESSION['acl']['syncjobs'] != "1" ) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'access_denied'
            );
            return false;
          }
          foreach ($ids as $id) {
            if (!is_numeric($id)) {
              return false;
            }
            $stmt = $pdo->prepare("SELECT `user2` FROM `imapsync` WHERE id = :id");
            $stmt->execute(array(':id' => $id));
            $user2 = $stmt->fetch(PDO::FETCH_ASSOC)['user2'];
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $user2)) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
                'msg' => 'access_denied'
              );
              continue;
            }
            $stmt = $pdo->prepare("DELETE FROM `imapsync` WHERE `id`= :id");
            $stmt->execute(array(':id' => $id));
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => array('deleted_syncjob', $id)
            );
          }
        break;
        case 'source':
          $ids = (array)($_data['id'] ?? array());
          foreach ($ids as $id) {
            if (!is_numeric($id)) continue;
            $is_now = syncjob('get', 'source', array('id' => $id));
            if (empty($is_now) || !imapsync_source_can_edit_row($is_now)) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => 'access_denied'
              );
              continue;
            }
            // FK ON DELETE RESTRICT prevents removal while syncjobs reference it; emit a clean message
            $ref = $pdo->prepare("SELECT COUNT(*) AS c FROM `imapsync` WHERE `source_id` = :id");
            $ref->execute(array(':id' => $id));
            if (intval($ref->fetch(PDO::FETCH_ASSOC)['c']) > 0) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
                'msg' => array('imapsync_source_in_use', $is_now['name'])
              );
              continue;
            }
            $stmt = $pdo->prepare("DELETE FROM `imapsync_source` WHERE `id` = :id");
            $stmt->execute(array(':id' => $id));
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log),
              'msg' => array('imapsync_source_deleted', $is_now['name'])
            );
          }
          return true;
        break;
      }
    break;
    case 'get':
      switch ($_type) {
        case 'job':
          $syncjobdetails = array();
          if (!is_numeric($_data)) {
            return false;
          }
          // Join imapsync_source so the UI gets host/auth-type/creator of the bound source for display,
          // plus token-status fields for XOAUTH2 sources.
          $source_select = "s.`name` AS source_name, s.`description` AS source_description,
            s.`host1` AS source_host, s.`port1` AS source_port, s.`enc1` AS source_enc,
            s.`auth_type` AS source_auth_type, s.`created_by` AS source_created_by, s.`scope` AS source_scope,
            s.`oauth_flow` AS source_oauth_flow,
            s.`oauth_token_expires` AS source_oauth_token_expires,
            s.`oauth_last_refresh_error` AS source_oauth_last_refresh_error";
          if (isset($_extra) && in_array('no_log', $_extra)) {
            $field_query = $pdo->query('SHOW FIELDS FROM `imapsync` WHERE FIELD NOT IN ("returned_text", "password1")');
            $fields = $field_query->fetchAll(PDO::FETCH_ASSOC);
            while($field = array_shift($fields)) {
              $shown_fields[] = 'i.`' . $field['Field'] . '`';
            }
            $stmt = $pdo->prepare("SELECT " . implode(',', (array)$shown_fields) . ", $source_select
                FROM `imapsync` i LEFT JOIN `imapsync_source` s ON i.`source_id` = s.`id`
                WHERE i.`id` = :id");
          }
          elseif (isset($_extra) && in_array('with_password', $_extra)) {
            $stmt = $pdo->prepare("SELECT i.*, $source_select
                FROM `imapsync` i LEFT JOIN `imapsync_source` s ON i.`source_id` = s.`id`
                WHERE i.`id` = :id");
          }
          else {
            $field_query = $pdo->query('SHOW FIELDS FROM `imapsync` WHERE FIELD NOT IN ("password1")');
            $fields = $field_query->fetchAll(PDO::FETCH_ASSOC);
            while($field = array_shift($fields)) {
              $shown_fields[] = 'i.`' . $field['Field'] . '`';
            }
            $stmt = $pdo->prepare("SELECT " . implode(',', (array)$shown_fields) . ", $source_select
                FROM `imapsync` i LEFT JOIN `imapsync_source` s ON i.`source_id` = s.`id`
                WHERE i.`id` = :id");
          }
          $stmt->execute(array(':id' => $_data));
          $syncjobdetails = $stmt->fetch(PDO::FETCH_ASSOC);
          if (!empty($syncjobdetails['returned_text'])) {
            $syncjobdetails['log'] = $syncjobdetails['returned_text'];
          }
          else {
            $syncjobdetails['log'] = '';
          }
          unset($syncjobdetails['returned_text']);
          if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $syncjobdetails['user2'])) {
            return false;
          }
          return $syncjobdetails;
        break;
        case 'jobs':
          $syncjobdata = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          $stmt = $pdo->prepare("SELECT `id` FROM `imapsync` WHERE `user2` = :username");
          $stmt->execute(array(':username' => $_data));
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          while($row = array_shift($rows)) {
            $syncjobdata[] = $row['id'];
          }
          return $syncjobdata;
        break;
        case 'source':
          $id = is_array($_data) ? ($_data['id'] ?? null) : $_data;
          $with_secret = is_array($_data) && !empty($_data['with_secret']);
          if (!is_numeric($id)) return false;
          $params = array(':id' => $id);
          $visibility = imapsync_source_visible_sql($params);
          $stmt = $pdo->prepare("SELECT s.* FROM `imapsync_source` s WHERE s.`id` = :id AND " . $visibility);
          $stmt->execute($params);
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if (!$row) return false;
          // Attach scope targets for the UI (visibility column + edit form pre-fill)
          $dstmt = $pdo->prepare("SELECT `domain` FROM `imapsync_source_domain` WHERE `source_id` = :id ORDER BY `domain`");
          $dstmt->execute(array(':id' => $id));
          $row['domains'] = $dstmt->fetchAll(PDO::FETCH_COLUMN);
          $ustmt = $pdo->prepare("SELECT `username` FROM `imapsync_source_user` WHERE `source_id` = :id ORDER BY `username`");
          $ustmt->execute(array(':id' => $id));
          $row['users'] = $ustmt->fetchAll(PDO::FETCH_COLUMN);
          // Let the UI hide edit/delete for rows the session may not manage (server enforces too)
          $row['can_edit'] = imapsync_source_can_edit_row($row);
          if (!$with_secret) {
            // Never leak secrets / token cache to the UI consumer that does not explicitly request them
            $row['oauth_client_secret'] = '';
            $row['oauth_access_token'] = '';
          }
          return $row;
        break;
        case 'sources':
          // Returns a list of ids the current session may see. Used by UI tables / dropdowns.
          $params = array();
          $visibility = imapsync_source_visible_sql($params);
          $stmt = $pdo->prepare("SELECT s.`id` FROM `imapsync_source` s WHERE " . $visibility . " ORDER BY FIELD(s.`scope`, 'all', 'domain', 'user'), s.`name`");
          $stmt->execute($params);
          $ids = array();
          foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ids[] = $row['id'];
          }
          return $ids;
        break;
      }
    break;
  }
  return false;
}
