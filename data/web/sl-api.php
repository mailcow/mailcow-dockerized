<?php
/*
 * SimpleLogin-compatible API endpoint for mailcow
 *
 * Provides a SimpleLogin-compatible API that allows third-party tools like
 * Bitwarden/Vaultwarden to create email aliases programmatically.
 *
 * Authentication: HTTP "Authentication" header with the user's API key
 * (generated in the mailcow user settings panel)
 *
 * Supported endpoints:
 *   POST /api/alias/random/new            Create a new random alias
 *   GET  /api/v2/alias/options            Get alias options (mailboxes, suffixes)
 *   GET  /api/v2/aliases                  List all active aliases
 *   DELETE /api/aliases/:address          Delete an alias
 *   PATCH  /api/aliases/:address          Toggle alias active state
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
header('Content-Type: application/json');
error_reporting(0);

// Require user API authentication via "Authentication" header
if (empty($_SESSION['mailcow_cc_username']) || $_SESSION['mailcow_cc_role'] !== 'user' || empty($_SESSION['mailcow_cc_api'])) {
  http_response_code(401);
  echo json_encode(array('error' => 'Unauthorized'));
  exit();
}

$username = $_SESSION['mailcow_cc_username'];
$method   = $_SERVER['REQUEST_METHOD'];
$path     = isset($_GET['path']) ? trim($_GET['path'], '/') : '';

// Route: POST /api/alias/random/new
if ($method === 'POST' && $path === 'alias/random/new') {
  if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1") {
    http_response_code(403);
    echo json_encode(array('error' => 'Access denied: spam_alias ACL not set'));
    exit();
  }

  $body = json_decode(file_get_contents('php://input'), true);
  $note = isset($body['note']) ? htmlspecialchars(trim($body['note'])) : '';
  $hostname = isset($_GET['hostname']) ? htmlspecialchars(trim($_GET['hostname'])) : '';
  $description = $note ?: ($hostname ? 'Created for ' . $hostname : 'Created via SimpleLogin API');

  // Determine user's primary domain
  $mailboxdata = mailbox('get', 'mailbox_details', $username);
  $domain = $mailboxdata['domain'] ?? null;
  if (empty($domain)) {
    http_response_code(500);
    echo json_encode(array('error' => 'Could not determine user domain'));
    exit();
  }

  // Create permanent random alias
  $result = mailbox('add', 'time_limited_alias', array(
    'domain'      => $domain,
    'description' => $description,
    'validity'    => 8760, // ignored when permanent=1
    'permanent'   => 1,
    'username'    => $username,
  ));

  if ($result === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'Failed to create alias'));
    exit();
  }

  // Retrieve the newly created alias (most recent for this user)
  $aliases = mailbox('get', 'time_limited_aliases', $username);
  if (empty($aliases)) {
    http_response_code(500);
    echo json_encode(array('error' => 'Alias created but could not be retrieved'));
    exit();
  }
  // The most recently created alias is last in the list
  usort($aliases, function($a, $b) {
    return strtotime($a['created']) - strtotime($b['created']);
  });
  $new_alias = end($aliases);

  http_response_code(201);
  echo json_encode(array(
    'alias'   => $new_alias['address'],
    'note'    => $new_alias['description'],
    'enabled' => true,
  ));
  exit();
}

// Route: GET /api/v2/alias/options
if ($method === 'GET' && $path === 'v2/alias/options') {
  $mailboxdata = mailbox('get', 'mailbox_details', $username);
  $domain = $mailboxdata['domain'] ?? null;

  $suffixes = array();
  if (!empty($domain)) {
    $suffixes[] = array(
      'suffix'   => '@' . $domain,
      'signed'   => $domain,
      'is_custom' => false,
    );
    // Include alias domains if available
    $alias_details = user_get_alias_details($username);
    if (!empty($alias_details['alias_domains'])) {
      foreach ($alias_details['alias_domains'] as $alias_domain) {
        $suffixes[] = array(
          'suffix'    => '@' . $alias_domain,
          'signed'    => $alias_domain,
          'is_custom' => false,
        );
      }
    }
  }

  echo json_encode(array(
    'suffixes'  => $suffixes,
    'mailboxes' => array(
      array(
        'id'    => 1,
        'email' => $username,
      )
    ),
    'prefix_suggestion' => '',
  ));
  exit();
}

// Route: GET /api/v2/aliases
if ($method === 'GET' && ($path === 'v2/aliases' || $path === 'v2/alias')) {
  $aliases = mailbox('get', 'time_limited_aliases', $username);
  $result = array();
  if (!empty($aliases)) {
    foreach ($aliases as $alias) {
      $result[] = array(
        'id'          => $alias['address'],
        'email'       => $alias['address'],
        'note'        => $alias['description'],
        'enabled'     => true,
        'nb_forward'  => 0,
        'nb_block'    => 0,
        'nb_reply'    => 0,
        'created_timestamp' => strtotime($alias['created']),
      );
    }
  }
  echo json_encode(array(
    'aliases' => $result,
    'page_info' => array(
      'has_next' => false,
    ),
  ));
  exit();
}

// Route: DELETE /api/aliases/:address
if ($method === 'DELETE' && preg_match('#^aliases/(.+)$#', $path, $m)) {
  $address = urldecode($m[1]);
  if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1") {
    http_response_code(403);
    echo json_encode(array('error' => 'Access denied'));
    exit();
  }
  $result = mailbox('delete', 'time_limited_alias', array('address' => array($address)));
  if ($result === false) {
    http_response_code(400);
    echo json_encode(array('error' => 'Failed to delete alias'));
    exit();
  }
  echo json_encode(array('deleted' => true));
  exit();
}

// Route: PATCH /api/aliases/:address (toggle enabled/disabled)
if ($method === 'PATCH' && preg_match('#^aliases/(.+)$#', $path, $m)) {
  $address = urldecode($m[1]);
  $body = json_decode(file_get_contents('php://input'), true);

  if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1") {
    http_response_code(403);
    echo json_encode(array('error' => 'Access denied'));
    exit();
  }

  // SimpleLogin PATCH only supports toggling enabled state
  // mailcow does not support disabling individual spam aliases, so we ignore the state
  echo json_encode(array(
    'enabled' => isset($body['enabled']) ? (bool)$body['enabled'] : true,
  ));
  exit();
}

// No matching route
http_response_code(404);
echo json_encode(array('error' => 'Not found'));
