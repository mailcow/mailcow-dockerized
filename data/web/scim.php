<?php

// Block browser-initiated requests
if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'document') {
  http_response_code(403);
  exit;
}

// Always respond with SCIM content type
header('Content-Type: application/scim+json');

// ─── Minimal bootstrap (mirrors keycloak-sync.php pattern) ──────────────────

require_once __DIR__ . '/inc/vars.inc.php';
if (file_exists(__DIR__ . '/inc/vars.local.inc.php')) {
  include_once __DIR__ . '/inc/vars.local.inc.php';
}
require_once __DIR__ . '/inc/lib/vendor/autoload.php';

// Init database
$dsn = $database_type . ':unix_socket=' . $database_sock . ';dbname=' . $database_name;
$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
    'status'  => '500',
    'detail'  => 'Database connection failed',
  ]);
  exit;
}

// Init Redis
$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  } else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv('REDISPASS'));
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
    'status'  => '500',
    'detail'  => 'Cache connection failed',
  ]);
  exit;
}

// Start session so mailbox() can use $_SESSION
session_name('MAILCOW_SCIM');
session_start();

// Load required functions
require_once __DIR__ . '/inc/functions.inc.php';
require_once __DIR__ . '/inc/functions.auth.inc.php';
require_once __DIR__ . '/inc/functions.mailbox.inc.php';
require_once __DIR__ . '/inc/functions.ratelimit.inc.php';
require_once __DIR__ . '/inc/functions.acl.inc.php';
require_once __DIR__ . '/inc/functions.scim.inc.php';

// ─── Authentication ──────────────────────────────────────────────────────────

$scim_token = scim_authenticate();

// ─── Routing ─────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_GET['path'] ?? '', '/');
// Normalize empty path
if ($path === '') {
  http_response_code(404);
  echo json_encode([
    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
    'status'  => '404',
    'detail'  => 'Not found',
    'scimType'=> 'notFound',
  ]);
  exit;
}

// Split path into segments
$segments = explode('/', $path, 2);
$resource = $segments[0];
$resource_id = isset($segments[1]) ? rawurldecode($segments[1]) : null;

// Log the request
$redis->lPush('SCIM_LOG', json_encode([
  'time'     => time(),
  'priority' => 'info',
  'task'     => 'SCIM',
  'message'  => $method . ' /scim/v2/' . $path . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' (token ID ' . $scim_token['id'] . ')',
]));

// Reset session return buffer
$_SESSION['return'] = [];

try {
  // Handle OPTIONS (CORS preflight)
  if ($method === 'OPTIONS') {
    header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    http_response_code(204);
    exit;
  }

  // Read JSON body for mutating methods
  $body = [];
  if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
  }

  // Dispatch
  switch ($resource) {
    case 'ServiceProviderConfig':
      if ($method !== 'GET') { http_response_code(405); exit; }
      echo json_encode(scim_service_provider_config());
      break;

    case 'Schemas':
      if ($method !== 'GET') { http_response_code(405); exit; }
      echo json_encode(scim_schemas());
      break;

    case 'ResourceTypes':
      if ($method !== 'GET') { http_response_code(405); exit; }
      echo json_encode(scim_resource_types());
      break;

    case 'Users':
      if ($resource_id === null) {
        // Collection endpoints
        if ($method === 'GET') {
          echo json_encode(scim_list_users($scim_token));
        } elseif ($method === 'POST') {
          echo json_encode(scim_create_user($body, $scim_token));
        } else {
          http_response_code(405);
        }
      } else {
        // Individual resource endpoints
        if ($method === 'GET') {
          echo json_encode(scim_get_user($resource_id, $scim_token));
        } elseif ($method === 'PUT') {
          echo json_encode(scim_replace_user($resource_id, $body, $scim_token));
        } elseif ($method === 'PATCH') {
          echo json_encode(scim_patch_user($resource_id, $body, $scim_token));
        } elseif ($method === 'DELETE') {
          scim_delete_user($resource_id, $scim_token);
        } else {
          http_response_code(405);
        }
      }
      break;

    default:
      http_response_code(404);
      echo json_encode([
        'schemas'  => ['urn:ietf:params:scim:api:messages:2.0:Error'],
        'status'   => '404',
        'detail'   => "Resource type '$resource' not found",
        'scimType' => 'notFound',
      ]);
      break;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
    'status'  => '500',
    'detail'  => 'Internal server error',
  ]);
  $redis->lPush('SCIM_LOG', json_encode([
    'time'     => time(),
    'priority' => 'err',
    'task'     => 'SCIM',
    'message'  => 'Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
  ]));
}
