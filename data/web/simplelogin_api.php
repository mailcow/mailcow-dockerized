<?php
/*
  SimpleLogin-compatible alias endpoint used by Bitwarden/Vaultwarden.
  This intentionally implements the small API surface Bitwarden needs instead
  of cloning the full SimpleLogin API.
*/
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

cors("set_headers");
header('Content-Type: application/json');
error_reporting(0);

function simplelogin_api_reply($status, $payload) {
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

function simplelogin_api_error($status, $message) {
  simplelogin_api_reply($status, array(
    'error' => $message,
    'type' => 'error',
    'msg' => $message
  ));
}

// Block requests not intended for direct API use, matching json_api.php.
if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] !== 'empty') {
  simplelogin_api_error(403, 'access denied');
}

function simplelogin_api_log($request) {
  global $redis;

  $data = array();
  foreach (array('hostname', 'domain', 'mode') as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
      $data[] = $key . "='" . $_GET[$key] . "'";
    }
  }
  if (isset($request['note']) && $request['note'] !== '') {
    $data[] = "note='*'";
  }

  try {
    $log_line = array(
      'time' => time(),
      'uri' => $_SERVER['REQUEST_URI'],
      'method' => $_SERVER['REQUEST_METHOD'],
      'remote' => get_remote_ip(),
      'data' => implode(', ', $data)
    );
    $redis->lPush('API_LOG', json_encode($log_line));
  }
  catch (Throwable $e) {
    // API logging must never block alias creation.
  }
}

function simplelogin_api_auth_header() {
  $token = $_SERVER['HTTP_AUTHENTICATION'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = trim($token);
  if (stripos($token, 'Bearer ') === 0) {
    $token = trim(substr($token, 7));
  }
  return $token;
}

function simplelogin_api_fail_auth($username = null) {
  global $redis;

  try {
    $subject = empty($username) ? 'unknown user' : $username;
    $redis->publish("F2B_CHANNEL", "mailcow API: Invalid SimpleLogin-compatible API key for " . $subject . " by " . get_remote_ip());
  }
  catch (Throwable $e) {
    // Keep the public error generic even if logging fails.
  }

  simplelogin_api_error(401, 'authentication failed');
}

function simplelogin_api_authenticate($token) {
  global $pdo;

  if (strpos($token, ':') === false) {
    simplelogin_api_fail_auth();
  }

  list($username, $password) = explode(':', $token, 2);
  $username = strtolower(trim($username));
  if (!filter_var($username, FILTER_VALIDATE_EMAIL) || empty($password)) {
    simplelogin_api_fail_auth($username);
  }

  $stmt = $pdo->prepare("SELECT `id`, `name`, `password`
    FROM `app_passwd`
      WHERE `mailbox` = :username
        AND `active` = '1'
        AND `alias_api_access` = '1'");
  $stmt->execute(array(':username' => $username));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    if (verify_hash($row['password'], $password) !== false) {
      $stmt = $pdo->prepare("SELECT `username`, `domain`
        FROM `mailbox`
          WHERE `username` = :username
            AND `active` = '1'
            AND (`kind` = '' OR `kind` IS NULL)");
      $stmt->execute(array(':username' => $username));
      $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
      if (empty($mailbox)) {
        simplelogin_api_fail_auth($username);
      }

      $_SESSION['mailcow_cc_username'] = $username;
      $_SESSION['mailcow_cc_role'] = 'user';
      $_SESSION['app_passwd_id'] = $row['id'];
      acl('to_session');

      if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1") {
        simplelogin_api_error(403, 'alias creation access denied');
      }

      return $mailbox;
    }
  }

  simplelogin_api_fail_auth($username);
}

function simplelogin_api_request_json() {
  $raw = file_get_contents('php://input');
  if (empty($raw)) {
    return array();
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    simplelogin_api_error(400, 'Request body does not contain valid json');
  }

  return $decoded;
}

function simplelogin_api_user_domains($username, $mailbox_domain) {
  $domains = array($mailbox_domain);
  $alias_details = user_get_alias_details($username);
  if (!empty($alias_details['alias_domains'])) {
    $domains = array_merge($domains, $alias_details['alias_domains']);
  }

  $domains = array_map(function($domain) {
    return idn_to_ascii(strtolower(trim($domain)), 0, INTL_IDNA_VARIANT_UTS46);
  }, $domains);

  return array_values(array_unique(array_filter($domains)));
}

function simplelogin_api_quota_domain($domain) {
  global $pdo;

  $stmt = $pdo->prepare("SELECT `target_domain`
    FROM `alias_domain`
      WHERE `alias_domain` = :domain
        AND `active` = '1'");
  $stmt->execute(array(':domain' => $domain));
  $alias_domain = $stmt->fetch(PDO::FETCH_ASSOC);
  return empty($alias_domain['target_domain']) ? $domain : $alias_domain['target_domain'];
}

function simplelogin_api_aliases_left($quota_domain) {
  global $pdo;

  $stmt = $pdo->prepare("SELECT `aliases`
    FROM `domain`
      WHERE `domain` = :domain
        AND `active` = '1'");
  $stmt->execute(array(':domain' => $quota_domain));
  $domain = $stmt->fetch(PDO::FETCH_ASSOC);
  if (empty($domain)) {
    return 0;
  }

  $stmt = $pdo->prepare("SELECT COUNT(`address`) AS `alias_count`
    FROM `alias`
      WHERE (`domain` = :domain OR `domain` IN (
        SELECT `alias_domain`
          FROM `alias_domain`
            WHERE `target_domain` = :domain2
      ))
      AND `address` NOT IN (
        SELECT `username` FROM `mailbox`
      )");
  $stmt->execute(array(
    ':domain' => $quota_domain,
    ':domain2' => $quota_domain
  ));
  $count = $stmt->fetch(PDO::FETCH_ASSOC);

  return intval($domain['aliases']) - intval($count['alias_count']);
}

function simplelogin_api_hostname_slug($hostname) {
  $hostname = strtolower(trim((string)$hostname));
  if (empty($hostname)) {
    return 'alias';
  }

  if (strpos($hostname, '://') === false) {
    $hostname = 'https://' . $hostname;
  }
  $host = parse_url($hostname, PHP_URL_HOST);
  $host = empty($host) ? $hostname : $host;
  $ascii_host = idn_to_ascii(strtolower($host), 0, INTL_IDNA_VARIANT_UTS46);
  $host = $ascii_host === false ? $host : $ascii_host;
  $host = preg_replace('/^www\./', '', $host);
  $labels = explode('.', $host);
  $slug = $labels[0] ?? 'alias';
  $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
  $slug = trim($slug, '-');
  if (empty($slug)) {
    $slug = 'alias';
  }

  return substr($slug, 0, 32);
}

function simplelogin_api_random_token($length = 10) {
  $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
  $token = '';
  $max = strlen($alphabet) - 1;
  for ($i = 0; $i < $length; $i++) {
    $token .= $alphabet[random_int(0, $max)];
  }
  return $token;
}

function simplelogin_api_random_local_part($hostname, $mode) {
  if ($mode === 'uuid') {
    return uuid4();
  }

  $slug = simplelogin_api_hostname_slug($hostname);
  $suffix = simplelogin_api_random_token();
  $local_part = $slug . '-' . $suffix;

  return substr($local_part, 0, 63);
}

function simplelogin_api_alias_exists($address, $local_part, $domain) {
  global $pdo;

  $stmt = $pdo->prepare("SELECT `address`
    FROM `alias`
      WHERE `address` = :address
        OR `address` IN (
          SELECT `username`
            FROM `mailbox`, `alias_domain`
              WHERE `alias_domain`.`alias_domain` = :address_d
                AND `mailbox`.`username` = CONCAT(:address_l, '@', alias_domain.target_domain)
        )
    UNION
    SELECT `address`
      FROM `spamalias`
        WHERE `address` = :address2
    UNION
    SELECT `username` AS `address`
      FROM `mailbox`
        WHERE `username` = :address3");
  $stmt->execute(array(
    ':address' => $address,
    ':address_l' => $local_part,
    ':address_d' => $domain,
    ':address2' => $address,
    ':address3' => $address
  ));

  return count($stmt->fetchAll(PDO::FETCH_ASSOC)) !== 0;
}

function simplelogin_api_comment($request) {
  $note = trim((string)($request['note'] ?? ''));
  if (empty($note)) {
    $note = 'Created via SimpleLogin-compatible API';
  }

  return substr($note, 0, 160);
}

function simplelogin_api_create_alias($username, $mailbox_domain, $request) {
  global $pdo;

  $hostname = $_GET['hostname'] ?? '';
  $mode = strtolower(trim((string)($_GET['mode'] ?? 'word')));
  $mode = $mode === 'uuid' ? 'uuid' : 'word';
  $domain = $_GET['domain'] ?? $mailbox_domain;
  $domain = idn_to_ascii(strtolower(trim($domain)), 0, INTL_IDNA_VARIANT_UTS46);
  if ($domain === false || $domain === '') {
    simplelogin_api_error(400, 'domain_invalid');
  }

  $valid_domains = simplelogin_api_user_domains($username, $mailbox_domain);
  if (!in_array($domain, $valid_domains, true)) {
    simplelogin_api_error(400, 'domain_invalid');
  }

  $quota_domain = simplelogin_api_quota_domain($domain);
  if (simplelogin_api_aliases_left($quota_domain) <= 0) {
    simplelogin_api_error(409, 'max_alias_exceeded');
  }

  for ($attempt = 0; $attempt < 20; $attempt++) {
    $local_part = simplelogin_api_random_local_part($hostname, $mode);
    $address = $local_part . '@' . $domain;

    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
      continue;
    }
    if (simplelogin_api_alias_exists($address, $local_part, $domain)) {
      continue;
    }

    $public_comment = simplelogin_api_comment($request);
    $private_comment = 'Created via SimpleLogin-compatible API';
    if (!empty($hostname)) {
      $private_comment = substr($private_comment . ' for ' . $hostname, 0, 160);
    }

    $stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `public_comment`, `private_comment`, `goto`, `domain`, `sogo_visible`, `internal`, `sender_allowed`, `active`)
      VALUES (:address, :public_comment, :private_comment, :goto, :domain, '1', '0', '1', '1')");
    $stmt->execute(array(
      ':address' => $address,
      ':public_comment' => $public_comment,
      ':private_comment' => $private_comment,
      ':goto' => $username,
      ':domain' => $domain
    ));
    $id = $pdo->lastInsertId();

    if (getenv('SKIP_SOGO') != "y") {
      update_sogo_static_view($username);
    }

    return array(
      'id' => intval($id),
      'alias' => $address,
      'email' => $address,
      'name' => simplelogin_api_hostname_slug($hostname),
      'enabled' => true,
      'creation_timestamp' => time(),
      'note' => $public_comment,
      'nb_forward' => 0,
      'nb_block' => 0,
      'nb_reply' => 0,
      'mailcow' => array(
        'goto' => $username,
        'domain' => $domain,
        'sender_allowed' => true,
        'sogo_visible' => true
      )
    );
  }

  simplelogin_api_error(500, 'Cannot generate unique alias');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  simplelogin_api_error(405, 'only POST method is allowed');
}

$mailbox = simplelogin_api_authenticate(simplelogin_api_auth_header());
$request = simplelogin_api_request_json();
simplelogin_api_log($request);
$response = simplelogin_api_create_alias($mailbox['username'], $mailbox['domain'], $request);

simplelogin_api_reply(201, $response);
