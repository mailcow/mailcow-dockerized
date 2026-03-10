<?php
/*
 * SimpleLogin-compatible API for mailcow
 *
 * Implements the SimpleLogin alias API, allowing third-party tools like
 * Bitwarden and Vaultwarden to create email aliases automatically.
 *
 * Authentication: Authorization: apikey <YOUR_USER_API_KEY>
 *
 * Endpoints:
 *   GET  /api/v2/alias/options     - Get available domains for alias creation
 *   POST /api/v2/alias/random/new  - Create a new random permanent alias
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

cors("set_headers");
// Override CORS Allow-Headers to include Authorization for SimpleLogin API
header('Access-Control-Allow-Headers: Accept, Content-Type, X-Api-Key, Authorization, Origin');
header('Content-Type: application/json');
error_reporting(0);

// Block requests not intended for direct API use by checking the 'Sec-Fetch-Dest' header.
if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] !== 'empty') {
  header('HTTP/1.1 403 Forbidden');
  exit;
}

// Only allow authenticated users (via user API key from sessions.inc.php)
if (empty($_SESSION['mailcow_cc_username']) || $_SESSION['mailcow_cc_role'] !== 'user') {
  http_response_code(401);
  echo json_encode(array(
    'error' => 'authentication failed',
    'code' => 'UNAUTHORIZED',
  ));
  exit;
}

$username = $_SESSION['mailcow_cc_username'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Parse query from nginx rewrite: /simplelogin.php?query=alias/options
$query = isset($_GET['query']) ? trim($_GET['query'], '/') : '';
// Also support direct path detection
if (empty($query)) {
  // Try to extract from REQUEST_URI
  if (preg_match('#/api/v2/(.+)$#', $request_uri, $m)) {
    $query = $m[1];
  }
}

// Route: GET /api/v2/alias/options
if ($method === 'GET' && $query === 'alias/options') {
  // Get user's available domains
  $mailbox_details = mailbox('get', 'mailbox_details', $username);
  $primary_domain = $mailbox_details['domain'] ?? '';

  $alias_details = user_get_alias_details($username);
  $alias_domains = $alias_details['alias_domains'] ?? array();

  $all_domains = array_unique(array_filter(array_merge(array($primary_domain), $alias_domains)));
  $suffixes = array();
  foreach ($all_domains as $domain) {
    $suffixes[] = array(
      'suffix' => '@' . $domain,
      'signed_suffix' => '@' . $domain,
    );
  }

  echo json_encode(array(
    'prefixes' => array(),
    'suffixes' => $suffixes,
    'can_create' => (isset($_SESSION['acl']['spam_alias']) && $_SESSION['acl']['spam_alias'] == '1'),
  ));
  exit;
}

// Route: POST /api/v2/alias/random/new
if ($method === 'POST' && $query === 'alias/random/new') {
  // Check ACL
  if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != '1') {
    http_response_code(403);
    echo json_encode(array(
      'error' => 'access denied',
      'code' => 'FORBIDDEN',
    ));
    exit;
  }

  // Parse request body
  $body = json_decode(file_get_contents('php://input'), true);
  $note = isset($body['note']) ? htmlspecialchars(trim($body['note'])) : '';
  $hostname = isset($_GET['hostname']) ? htmlspecialchars(trim($_GET['hostname'])) : '';

  // Build description from note and/or hostname
  $description = '';
  if (!empty($note)) {
    $description = $note;
  }
  elseif (!empty($hostname)) {
    $description = $hostname;
  }

  // Determine domain to use
  $mailbox_details = mailbox('get', 'mailbox_details', $username);
  $primary_domain = $mailbox_details['domain'] ?? '';

  $alias_details = user_get_alias_details($username);
  $alias_domains = $alias_details['alias_domains'] ?? array();

  $all_domains = array_unique(array_filter(array_merge(array($primary_domain), $alias_domains)));

  if (empty($all_domains)) {
    http_response_code(422);
    echo json_encode(array(
      'error' => 'no valid domain available',
      'code' => 'UNPROCESSABLE_ENTITY',
    ));
    exit;
  }

  // Use the primary domain by default
  $domain = $primary_domain;

  // Create a permanent random alias via the existing mailbox function
  $alias_data = array(
    'username' => $username,
    'domain' => $domain,
    'description' => $description,
    'permanent' => true,
  );

  $result = mailbox('add', 'time_limited_alias', $alias_data);

  if ($result === false) {
    http_response_code(500);
    $return_msgs = isset($_SESSION['return']) ? $_SESSION['return'] : array();
    $error_msg = 'failed to create alias';
    foreach ($return_msgs as $msg) {
      if ($msg['type'] === 'danger') {
        $error_msg = is_array($msg['msg']) ? implode(': ', $msg['msg']) : $msg['msg'];
        break;
      }
    }
    echo json_encode(array(
      'error' => $error_msg,
      'code' => 'INTERNAL_SERVER_ERROR',
    ));
    exit;
  }

  // Retrieve the newly created alias
  $aliases = mailbox('get', 'time_limited_aliases', $username);
  $new_alias = null;
  if (!empty($aliases)) {
    // The newest alias will be the one with the most recent creation time matching our description
    usort($aliases, function($a, $b) {
      return strtotime($b['created']) - strtotime($a['created']);
    });
    foreach ($aliases as $alias) {
      if (!empty($alias['permanent']) && ($description === '' || $alias['description'] === $description)) {
        $new_alias = $alias;
        break;
      }
    }
    if ($new_alias === null) {
      $new_alias = $aliases[0];
    }
  }

  if ($new_alias === null) {
    http_response_code(500);
    echo json_encode(array(
      'error' => 'alias created but could not be retrieved',
      'code' => 'INTERNAL_SERVER_ERROR',
    ));
    exit;
  }

  $created_ts = strtotime($new_alias['created']);
  $created_date = date('Y-m-d H:i:s', $created_ts);

  http_response_code(201);
  echo json_encode(array(
    'id' => $new_alias['address'],
    'email' => $new_alias['address'],
    'creation_date' => $created_date,
    'creation_timestamp' => $created_ts,
    'nb_forward' => 0,
    'nb_block' => 0,
    'nb_reply' => 0,
    'enabled' => true,
    'note' => $new_alias['description'] ?: null,
    'name' => null,
    'support_pgp' => false,
    'disable_pgp' => false,
    'latest_activity' => null,
    'pinned' => false,
    'mailboxes' => array(
      array(
        'id' => 1,
        'email' => $username,
      )
    ),
  ));
  exit;
}

// No matching route
http_response_code(404);
echo json_encode(array(
  'error' => 'route not found',
  'code' => 'NOT_FOUND',
));
