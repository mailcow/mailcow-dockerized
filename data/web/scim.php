<?php

// Buffer all output so an exception mid-response can never append an error
// envelope to an already-emitted body
ob_start();

// Block browser-initiated requests
if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'document') {
  http_response_code(403);
  exit;
}

// Always respond with SCIM content type
header('Content-Type: application/scim+json');

// ─── Minimal bootstrap ───────────────────────────────────────────────────────

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

// SCIM is stateless: no PHP session is started. $_SESSION is used as a plain
// array because mailbox() reads/writes it ($_SESSION['return'], ACLs).
$_SESSION = [];

// Load required functions
require_once __DIR__ . '/inc/functions.inc.php';
require_once __DIR__ . '/inc/functions.auth.inc.php';
require_once __DIR__ . '/inc/functions.mailbox.inc.php';
require_once __DIR__ . '/inc/functions.ratelimit.inc.php';
require_once __DIR__ . '/inc/functions.acl.inc.php';
require_once __DIR__ . '/inc/functions.scim.inc.php';

// ─── Path resolution ─────────────────────────────────────────────────────────
// Derive the path from REQUEST_URI rather than the nginx-rewritten query
// string: try_files re-injects the decoded URI into $args, which corrupts
// percent-encoded characters ('+' in mailbox local parts, '&', '#').

$uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($uri_path)) {
  $uri_path = '';
}
if (preg_match('~^/scim/v2(?:/(.*))?$~', $uri_path, $m)) {
  $path = trim($m[1] ?? '', '/');
} else {
  // Fallback (direct /scim.php?path=... invocation)
  $path = trim((string) ($_GET['path'] ?? ''), '/');
}
$segments = $path === '' ? [] : array_map('rawurldecode', explode('/', $path));

$method = $_SERVER['REQUEST_METHOD'];

// Query parameters, case-insensitively keyed (path helper param removed)
$SCIM_QUERY = array_change_key_case($_GET, CASE_LOWER);
unset($SCIM_QUERY['path']);

// Handle OPTIONS before authentication: CORS preflights carry no Authorization
if ($method === 'OPTIONS') {
  header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
  http_response_code(204);
  exit;
}

// ─── Authentication ──────────────────────────────────────────────────────────

$scim_token = scim_authenticate();

// ─── Routing ─────────────────────────────────────────────────────────────────

$resource    = $segments[0] ?? '';
$resource_id = $segments[1] ?? null;
$extra_depth = count($segments) > 2;

// Log the request
$redis->lPush('SCIM_LOG', json_encode([
  'time'     => time(),
  'priority' => 'info',
  'task'     => 'SCIM',
  'message'  => $method . ' /scim/v2/' . $path . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' (token ID ' . $scim_token['id'] . ')',
]));

// Reset session return buffer
$_SESSION['return'] = [];

/**
 * 405 with a proper error envelope and Allow header (RFC 7231 §6.5.5).
 */
function scim_method_not_allowed(string $allow): never {
  scim_error(405, 'Method not allowed', '', ['Allow: ' . $allow . ', OPTIONS']);
}

/**
 * RFC 7644 §4: filtering SHOULD be rejected with 403 on configuration endpoints.
 */
function scim_reject_config_filter(): void {
  global $SCIM_QUERY;
  if (isset($SCIM_QUERY['filter']) && $SCIM_QUERY['filter'] !== '') {
    scim_error(403, 'Filtering is not supported on configuration endpoints', 'invalidFilter');
  }
}

try {
  if ($resource === '' || $extra_depth) {
    scim_error(404, 'Not found');
  }

  switch ($resource) {
    case 'ServiceProviderConfig':
      if ($resource_id !== null) {
        scim_error(404, 'Not found');
      }
      scim_reject_config_filter();
      if ($method !== 'GET') {
        scim_method_not_allowed('GET');
      }
      scim_respond(scim_service_provider_config());
      break;

    case 'Schemas':
      scim_reject_config_filter();
      if ($method !== 'GET') {
        scim_method_not_allowed('GET');
      }
      if ($resource_id === null) {
        scim_respond(scim_schemas());
      }
      $schema = scim_schema_by_id($resource_id);
      if ($schema === null) {
        scim_error(404, "Schema '$resource_id' not found");
      }
      scim_respond($schema);
      break;

    case 'ResourceTypes':
      scim_reject_config_filter();
      if ($method !== 'GET') {
        scim_method_not_allowed('GET');
      }
      if ($resource_id === null) {
        scim_respond(scim_resource_types());
      }
      $type = scim_resource_type_by_id($resource_id);
      if ($type === null) {
        scim_error(404, "ResourceType '$resource_id' not found");
      }
      scim_respond($type);
      break;

    case 'Users':
      if ($resource_id === '.search') {
        if ($method !== 'POST') {
          scim_method_not_allowed('POST');
        }
        scim_search(scim_read_body(), $scim_token);
      }
      if ($resource_id === null) {
        // Collection endpoints
        if ($method === 'GET') {
          scim_query_users($scim_token, $SCIM_QUERY);
        } elseif ($method === 'POST') {
          scim_create_user(scim_read_body(), $scim_token);
        } else {
          scim_method_not_allowed('GET, POST');
        }
      }
      // Individual resource endpoints
      if ($method === 'GET') {
        scim_get_user($resource_id, $scim_token);
      } elseif ($method === 'PUT') {
        scim_replace_user($resource_id, scim_read_body(), $scim_token);
      } elseif ($method === 'PATCH') {
        scim_patch_user($resource_id, scim_read_body(), $scim_token);
      } elseif ($method === 'DELETE') {
        scim_delete_user($resource_id, $scim_token);
      } else {
        scim_method_not_allowed('GET, PUT, PATCH, DELETE');
      }
      break;

    case '.search':
      if ($resource_id !== null) {
        scim_error(404, 'Not found');
      }
      if ($method !== 'POST') {
        scim_method_not_allowed('POST');
      }
      scim_search(scim_read_body(), $scim_token);
      break;

    case 'Me':
      // RFC 7644 §3.11: /Me is OPTIONAL; 501 signals "not implemented"
      scim_error(501, 'The /Me endpoint is not implemented', '');
      break;

    case 'Groups':
      // Groups are not implemented: mailcow has no native group-of-users
      // object, and /ResourceTypes advertises User only — that remains the
      // authoritative capability statement.
      //
      // Reads are nonetheless answered with an empty collection rather than a
      // 404, because IdPs run a group discovery pass unconditionally without
      // consulting /ResourceTypes (Authentik, Entra ID) and a hard error there
      // fails the whole sync, users included. An empty ListResponse is a
      // normal query result per RFC 7644 §3.4.2 and is truthful: no group
      // resources exist here. Writes stay 501, so the pair reads as "there are
      // no groups and none can be created".
      //
      // The filter is deliberately not parsed: an empty collection matches
      // nothing whatever the filter says, and Group attributes (displayName,
      // members) are absent from our attribute registry, so parsing one would
      // wrongly yield 400 invalidFilter.
      if ($method !== 'GET') {
        scim_error(501, 'Group provisioning is not implemented; this provider manages Users only (see /scim/v2/ResourceTypes)', '');
      }
      if ($resource_id !== null) {
        scim_error(404, 'Group not found');
      }
      scim_respond([
        'schemas'      => [SCIM_URN_LIST],
        'totalResults' => 0,
        'startIndex'   => isset($SCIM_QUERY['startindex']) ? max(1, intval($SCIM_QUERY['startindex'])) : 1,
        'itemsPerPage' => 0,
        'Resources'    => [],
      ]);
      break;

    default:
      scim_error(404, "Resource type '$resource' not found");
  }
} catch (Throwable $e) {
  if (ob_get_length()) {
    ob_clean();
  }
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
