<?php

// ─── Helpers ────────────────────────────────────────────────────────────────

function scim_log(string $priority, string $message): void {
  global $redis;
  $redis->lPush('SCIM_LOG', json_encode([
    'time'     => time(),
    'priority' => $priority,
    'task'     => 'SCIM',
    'message'  => $message,
  ]));
}

/**
 * Output a RFC 7644 §3.12 error response and exit.
 */
function scim_error(int $status, string $detail, string $scimType = ''): never {
  http_response_code($status);
  $body = [
    'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
    'status'  => (string) $status,
    'detail'  => $detail,
  ];
  if ($scimType !== '') {
    $body['scimType'] = $scimType;
  }
  echo json_encode($body);
  exit;
}

/**
 * Map a mailbox DB row + optional externalId to a SCIM User object.
 */
function scim_user_to_response(array $row, ?string $external_id): array {
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $location = $scheme . '://' . $host . '/scim/v2/Users/' . rawurlencode($row['username']);

  $name_parts = explode(' ', $row['name'] ?? '', 2);
  $given  = $name_parts[0] ?? '';
  $family = $name_parts[1] ?? '';

  $obj = [
    'schemas'     => ['urn:ietf:params:scim:schemas:core:2.0:User'],
    'id'          => $row['username'],
    'userName'    => $row['username'],
    'displayName' => $row['name'] ?? '',
    'name'        => [
      'formatted'  => $row['name'] ?? '',
      'givenName'  => $given,
      'familyName' => $family,
    ],
    'active' => (bool)(int)$row['active'],
    'emails' => [
      ['value' => $row['username'], 'primary' => true],
    ],
    'meta' => [
      'resourceType' => 'User',
      'created'      => isset($row['created'])
        ? (new DateTime($row['created']))->format(DateTime::RFC3339)
        : null,
      'lastModified' => !empty($row['modified'])
        ? (new DateTime($row['modified']))->format(DateTime::RFC3339)
        : null,
      'location'     => $location,
    ],
  ];

  if ($external_id !== null) {
    $obj['externalId'] = $external_id;
  }

  return $obj;
}

/**
 * Resolve display name from a SCIM User request body.
 * Priority: displayName > name.formatted > givenName+familyName > local part of userName
 */
function scim_resolve_name(array $body): string {
  if (!empty($body['displayName'])) {
    return trim($body['displayName']);
  }
  if (!empty($body['name']['formatted'])) {
    return trim($body['name']['formatted']);
  }
  $given  = trim($body['name']['givenName'] ?? '');
  $family = trim($body['name']['familyName'] ?? '');
  if ($given !== '' || $family !== '') {
    return trim("$given $family");
  }
  // fallback to local part of userName
  $userName = $body['userName'] ?? '';
  return strstr($userName, '@', true) ?: $userName;
}

/**
 * Set up admin session so mailbox() calls have the required ACLs.
 * Mirrors the pattern in keycloak-sync.php.
 */
function scim_setup_session(): void {
  $_SESSION['mailcow_cc_username']         = 'SCIM';
  $_SESSION['mailcow_cc_role']             = 'admin';
  $_SESSION['acl']['tls_policy']           = '1';
  $_SESSION['acl']['quarantine_notification'] = '1';
  $_SESSION['acl']['quarantine_category']  = '1';
  $_SESSION['acl']['ratelimit']            = '1';
  $_SESSION['acl']['sogo_access']          = '1';
  $_SESSION['acl']['protocol_access']      = '1';
  $_SESSION['acl']['mailbox_relayhost']    = '1';
  $_SESSION['acl']['unlimited_quota']      = '1';
  $_SESSION['access_all_exception']        = '1';
}

// ─── Authentication ──────────────────────────────────────────────────────────

/**
 * Authenticate the SCIM request via Bearer token.
 * Returns the scim_tokens row on success, or exits with 401 on failure.
 */
function scim_authenticate(): array {
  global $pdo, $redis;

  $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $m)) {
    scim_log('err', 'Authentication failed: missing or malformed Authorization header');
    scim_error(401, 'Bearer token required');
  }

  $raw_token  = $m[1];
  $token_hash = hash('sha256', $raw_token);

  $stmt = $pdo->prepare("SELECT * FROM `scim_tokens` WHERE `token_hash` = :hash AND `active` = '1'");
  $stmt->execute([':hash' => $token_hash]);
  $token = $stmt->fetch(PDO::FETCH_ASSOC);

  if (empty($token)) {
    $redis->publish('F2B_CHANNEL', 'mailcow SCIM: Invalid token from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    scim_log('err', 'Authentication failed: invalid or inactive token from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    scim_error(401, 'Invalid or inactive token');
  }

  // IP ACL check
  $remote     = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
  $allow_from = array_map('trim', preg_split('/[ ,;\n]+/', $token['allow_from']));
  $allow_from = array_filter($allow_from);
  if (!empty($allow_from) && !ip_acl($remote, $allow_from)) {
    $redis->publish('F2B_CHANNEL', 'mailcow SCIM: IP denied for token from ' . $remote);
    scim_log('err', 'Authentication failed: IP ' . $remote . ' not in allow list for token ID ' . $token['id']);
    scim_error(401, 'IP address not allowed');
  }

  return $token;
}

// ─── Token management (admin operations) ────────────────────────────────────

function scim_token(string $_action, array $_data = []): mixed {
  global $pdo;

  switch ($_action) {
    case 'add':
      $description        = htmlspecialchars(trim($_data['description'] ?? ''), ENT_QUOTES);
      $domain_restriction = !empty($_data['domain_restriction']) ? strtolower(trim($_data['domain_restriction'])) : null;
      $template           = !empty($_data['template']) ? trim($_data['template']) : null;
      $allow_from         = trim($_data['allow_from'] ?? '');

      // Validate domain_restriction if provided
      if ($domain_restriction !== null) {
        $stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain` = :domain");
        $stmt->execute([':domain' => $domain_restriction]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
          $_SESSION['return'][] = [
            'type' => 'danger',
            'log'  => [__FUNCTION__, $_action],
            'msg'  => 'scim_domain_not_found',
          ];
          return false;
        }
      }

      $raw_token  = bin2hex(random_bytes(32));
      $token_hash = hash('sha256', $raw_token);

      $stmt = $pdo->prepare("INSERT INTO `scim_tokens`
        (`description`, `token_hash`, `domain_restriction`, `template`, `allow_from`, `active`)
        VALUES (:description, :token_hash, :domain_restriction, :template, :allow_from, '1')");
      $stmt->execute([
        ':description'        => $description,
        ':token_hash'         => $token_hash,
        ':domain_restriction' => $domain_restriction,
        ':template'           => $template,
        ':allow_from'         => $allow_from,
      ]);

      $id = $pdo->lastInsertId();
      $_SESSION['return'][] = [
        'type' => 'success',
        'log'  => [__FUNCTION__, $_action],
        'msg'  => array('scim_token_added', $id),
      ];
      // Return raw token — shown once to the admin, never stored
      return $raw_token;

    case 'edit':
      $id            = intval($_data['id'] ?? 0);
      $description   = htmlspecialchars(trim($_data['description'] ?? ''), ENT_QUOTES);
      $allow_from    = trim($_data['allow_from'] ?? '');
      $active        = (isset($_data['active']) && intval($_data['active']) == 1) ? 1 : 0;
      $template      = !empty($_data['template']) ? trim($_data['template']) : null;

      $domain_restriction = !empty($_data['domain_restriction']) ? strtolower(trim($_data['domain_restriction'])) : null;
      if ($domain_restriction !== null) {
        $stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain` = :domain");
        $stmt->execute([':domain' => $domain_restriction]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
          $_SESSION['return'][] = [
            'type' => 'danger',
            'log'  => [__FUNCTION__, $_action],
            'msg'  => 'scim_domain_not_found',
          ];
          return false;
        }
      }

      $stmt = $pdo->prepare("UPDATE `scim_tokens`
        SET `description` = :description,
            `domain_restriction` = :domain_restriction,
            `template` = :template,
            `allow_from` = :allow_from,
            `active` = :active
        WHERE `id` = :id");
      $stmt->execute([
        ':description'        => $description,
        ':domain_restriction' => $domain_restriction,
        ':template'           => $template,
        ':allow_from'         => $allow_from,
        ':active'             => $active,
        ':id'                 => $id,
      ]);
      $_SESSION['return'][] = [
        'type' => 'success',
        'log'  => [__FUNCTION__, $_action],
        'msg'  => array('scim_token_updated', $id),
      ];
      return true;

    case 'delete':
      $id   = intval($_data['id'] ?? 0);
      $stmt = $pdo->prepare("DELETE FROM `scim_tokens` WHERE `id` = :id");
      $stmt->execute([':id' => $id]);
      $_SESSION['return'][] = [
        'type' => 'success',
        'log'  => [__FUNCTION__, $_action],
        'msg'  => array('scim_token_deleted', $id),
      ];
      return true;

    case 'get_all':
      $stmt = $pdo->query("SELECT `id`, `description`, `domain_restriction`, `template`,
        `allow_from`, `skip_ip_check`, `active`, `created`, `modified`
        FROM `scim_tokens` ORDER BY `created` DESC");
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  return false;
}

// ─── Discovery endpoints ─────────────────────────────────────────────────────

function scim_service_provider_config(): array {
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $base   = $scheme . '://' . $host . '/scim/v2';

  return [
    'schemas'               => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
    'documentationUri'      => '',
    'patch'                 => ['supported' => true],
    'bulk'                  => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
    'filter'                => ['supported' => true, 'maxResults' => 500],
    'changePassword'        => ['supported' => false],
    'sort'                  => ['supported' => false],
    'etag'                  => ['supported' => false],
    'authenticationSchemes' => [
      [
        'name'        => 'OAuth Bearer Token',
        'description' => 'Authentication scheme using the OAuth Bearer Token standard',
        'type'        => 'oauthbearertoken',
        'primary'     => true,
      ],
    ],
    'meta' => [
      'resourceType' => 'ServiceProviderConfig',
      'location'     => $base . '/ServiceProviderConfig',
    ],
  ];
}

function scim_schemas(): array {
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $base   = $scheme . '://' . $host . '/scim/v2';

  $user_schema = [
    'id'          => 'urn:ietf:params:scim:schemas:core:2.0:User',
    'name'        => 'User',
    'description' => 'User account',
    'attributes'  => [
      ['name' => 'userName',    'type' => 'string',  'required' => true,  'uniqueness' => 'server'],
      ['name' => 'displayName', 'type' => 'string',  'required' => false, 'uniqueness' => 'none'],
      ['name' => 'name',        'type' => 'complex', 'required' => false, 'uniqueness' => 'none',
        'subAttributes' => [
          ['name' => 'formatted',  'type' => 'string', 'required' => false],
          ['name' => 'givenName',  'type' => 'string', 'required' => false],
          ['name' => 'familyName', 'type' => 'string', 'required' => false],
        ],
      ],
      ['name' => 'emails',  'type' => 'complex', 'multiValued' => true, 'required' => false],
      ['name' => 'active',  'type' => 'boolean', 'required' => false, 'uniqueness' => 'none'],
    ],
    'meta' => [
      'resourceType' => 'Schema',
      'location'     => $base . '/Schemas/urn:ietf:params:scim:schemas:core:2.0:User',
    ],
  ];

  return [
    'schemas'     => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
    'totalResults' => 1,
    'Resources'   => [$user_schema],
  ];
}

function scim_resource_types(): array {
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $base   = $scheme . '://' . $host . '/scim/v2';

  return [
    'schemas'      => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
    'totalResults' => 1,
    'Resources'    => [
      [
        'schemas'          => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
        'id'               => 'User',
        'name'             => 'User',
        'endpoint'         => '/Users',
        'description'      => 'User account',
        'schema'           => 'urn:ietf:params:scim:schemas:core:2.0:User',
        'schemaExtensions' => [],
        'meta'             => [
          'resourceType' => 'ResourceType',
          'location'     => $base . '/ResourceTypes/User',
        ],
      ],
    ],
  ];
}

// ─── User operations ─────────────────────────────────────────────────────────

function scim_list_users(array $token): array {
  global $pdo;

  $start_index = max(1, intval($_GET['startIndex'] ?? 1));
  $count       = min(500, max(1, intval($_GET['count'] ?? 100)));
  $offset      = $start_index - 1;

  // Parse simple filter: userName eq "..."
  $filter_username = null;
  $filter_str = $_GET['filter'] ?? '';
  if ($filter_str !== '') {
    if (preg_match('/^userName\s+eq\s+"([^"]+)"/i', $filter_str, $fm)) {
      $filter_username = $fm[1];
    } else {
      scim_error(400, 'Only "userName eq" filter is supported', 'invalidFilter');
    }
  }

  $where  = ['m.authsource = \'scim\''];
  $params = [];

  if (!empty($token['domain_restriction'])) {
    $where[]                   = 'm.domain = :domain_restriction';
    $params[':domain_restriction'] = $token['domain_restriction'];
  }
  if ($filter_username !== null) {
    $where[]             = 'm.username = :filter_username';
    $params[':filter_username'] = $filter_username;
  }

  $where_sql = implode(' AND ', $where);

  // Count total
  $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM `mailbox` m WHERE $where_sql");
  $count_stmt->execute($params);
  $total = (int) $count_stmt->fetchColumn();

  // Fetch page
  $params[':limit']  = $count;
  $params[':offset'] = $offset;
  $stmt = $pdo->prepare(
    "SELECT m.*, sm.external_id
     FROM `mailbox` m
     LEFT JOIN `scim_maps` sm ON m.username = sm.username AND sm.token_id = :token_id
     WHERE $where_sql
     ORDER BY m.username
     LIMIT :limit OFFSET :offset"
  );
  $params[':token_id'] = (int) $token['id'];
  // PDO needs int type for LIMIT/OFFSET with named params
  $stmt->bindValue(':limit',  $count,  PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  foreach ($params as $key => $val) {
    if (in_array($key, [':limit', ':offset'])) continue;
    $stmt->bindValue($key, $val);
  }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $resources = array_map(fn($row) => scim_user_to_response($row, $row['external_id'] ?? null), $rows);

  return [
    'schemas'      => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
    'totalResults' => $total,
    'startIndex'   => $start_index,
    'itemsPerPage' => count($resources),
    'Resources'    => $resources,
  ];
}

function scim_get_user(string $id, array $token): array {
  global $pdo;

  $stmt = $pdo->prepare(
    "SELECT m.*, sm.external_id
     FROM `mailbox` m
     LEFT JOIN `scim_maps` sm ON m.username = sm.username AND sm.token_id = :token_id
     WHERE m.username = :username AND m.authsource = 'scim'"
  );
  $stmt->execute([':username' => $id, ':token_id' => (int) $token['id']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    scim_error(404, 'User not found', 'notFound');
  }

  if (!empty($token['domain_restriction']) && $row['domain'] !== $token['domain_restriction']) {
    scim_error(403, 'Token is restricted to a different domain');
  }

  return scim_user_to_response($row, $row['external_id'] ?? null);
}

function scim_create_user(array $body, array $token): array {
  global $pdo;

  $userName = trim($body['userName'] ?? '');
  if (!filter_var($userName, FILTER_VALIDATE_EMAIL)) {
    scim_error(400, 'userName must be a valid email address', 'invalidValue');
  }

  $parts      = explode('@', $userName, 2);
  $local_part = $parts[0];
  $domain     = strtolower($parts[1]);
  $username   = $local_part . '@' . $domain;

  // Validate domain exists
  $stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain` = :domain");
  $stmt->execute([':domain' => $domain]);
  if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    scim_error(400, "Domain '$domain' does not exist in mailcow", 'invalidValue');
  }

  // Domain restriction check
  if (!empty($token['domain_restriction']) && $domain !== $token['domain_restriction']) {
    scim_error(403, "Token is restricted to domain '{$token['domain_restriction']}'");
  }

  // Duplicate check
  $stmt = $pdo->prepare("SELECT `username`, `authsource` FROM `mailbox` WHERE `username` = :username");
  $stmt->execute([':username' => $username]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);

  $external_id = $body['externalId'] ?? null;

  if ($existing) {
    if ($existing['authsource'] !== 'scim') {
      scim_error(409,
        "User '$username' is managed by '{$existing['authsource']}'. " .
        "To transfer SCIM management, change the mailbox authsource to 'scim' in the mailcow admin panel first.",
        'uniqueness');
    }

    // User was pre-created with authsource='scim' (e.g. via the admin UI).
    // Claim them: update attributes and link the externalId, then return 200.
    if ($external_id !== null) {
      // Ensure the externalId isn't already mapped to a different user
      $stmt = $pdo->prepare("SELECT `username` FROM `scim_maps` WHERE `external_id` = :eid AND `token_id` = :tid AND `username` != :username");
      $stmt->execute([':eid' => $external_id, ':tid' => (int) $token['id'], ':username' => $username]);
      if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        scim_error(409, "externalId '$external_id' is already mapped to a different user", 'uniqueness');
      }

      $stmt = $pdo->prepare("SELECT `id` FROM `scim_maps` WHERE `username` = :username AND `token_id` = :tid");
      $stmt->execute([':username' => $username, ':tid' => (int) $token['id']]);
      if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmt = $pdo->prepare("UPDATE `scim_maps` SET `external_id` = :eid WHERE `username` = :username AND `token_id` = :tid");
        $stmt->execute([':eid' => $external_id, ':username' => $username, ':tid' => (int) $token['id']]);
      } else {
        $stmt = $pdo->prepare("INSERT INTO `scim_maps` (`external_id`, `username`, `token_id`) VALUES (:eid, :username, :tid)");
        $stmt->execute([':eid' => $external_id, ':username' => $username, ':tid' => (int) $token['id']]);
      }
    }

    $name   = scim_resolve_name($body);
    $active = isset($body['active']) ? (int)(bool)$body['active'] : 1;

    scim_setup_session();
    mailbox('edit', 'mailbox', [
      'username' => [$username],
      'name'     => $name,
      'active'   => $active,
    ]);

    scim_log('info', "Claimed existing mailbox '$username' via SCIM POST (token ID {$token['id']})");
    http_response_code(200);
    return scim_get_user($username, $token);
  }

  // externalId duplicate check for this token (new user path)
  if ($external_id !== null) {
    $stmt = $pdo->prepare("SELECT `id` FROM `scim_maps` WHERE `external_id` = :eid AND `token_id` = :tid");
    $stmt->execute([':eid' => $external_id, ':tid' => (int) $token['id']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
      scim_error(409, "externalId '$external_id' is already mapped to a user", 'uniqueness');
    }
  }

  $name   = scim_resolve_name($body);
  $active = isset($body['active']) ? (int)(bool)$body['active'] : 1;

  scim_setup_session();

  if (!empty($token['template'])) {
    mailbox('add', 'mailbox_from_template', [
      'domain'     => $domain,
      'local_part' => $local_part,
      'name'       => $name,
      'authsource' => 'scim',
      'template'   => $token['template'],
      'active'     => $active,
    ]);
  } else {
    mailbox('add', 'mailbox', [
      'domain'     => $domain,
      'local_part' => $local_part,
      'name'       => $name,
      'authsource' => 'scim',
      'password'   => '',
      'password2'  => '',
      'active'     => $active,
    ]);
  }

  // Check for errors from mailbox()
  foreach ($_SESSION['return'] as $ret) {
    if ($ret['type'] === 'danger') {
      $msg = is_array($ret['msg']) ? implode(': ', $ret['msg']) : $ret['msg'];
      scim_error(400, 'Failed to create mailbox: ' . $msg, 'invalidValue');
    }
  }

  // Insert scim_maps entry
  if ($external_id !== null) {
    $stmt = $pdo->prepare("INSERT INTO `scim_maps` (`external_id`, `username`, `token_id`)
      VALUES (:eid, :username, :tid)");
    $stmt->execute([':eid' => $external_id, ':username' => $username, ':tid' => (int) $token['id']]);
  }

  scim_log('info', "Created mailbox '$username' via SCIM (token ID {$token['id']})");

  http_response_code(201);
  return scim_get_user($username, $token);
}

function scim_replace_user(string $id, array $body, array $token): array {
  global $pdo;

  // Verify user exists and belongs to this token's domain restriction
  scim_get_user($id, $token); // exits with 404 if not found

  $name   = scim_resolve_name($body);
  $active = isset($body['active']) ? (int)(bool)$body['active'] : 1;

  scim_setup_session();
  mailbox('edit', 'mailbox', [
    'username' => [$id],
    'name'     => $name,
    'active'   => $active,
  ]);

  // Update externalId if provided
  $external_id = $body['externalId'] ?? null;
  if ($external_id !== null) {
    // Check if a map entry already exists for this username
    $stmt = $pdo->prepare("SELECT `id` FROM `scim_maps` WHERE `username` = :username");
    $stmt->execute([':username' => $id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
      $stmt = $pdo->prepare("UPDATE `scim_maps` SET `external_id` = :eid WHERE `username` = :username AND `token_id` = :tid");
      $stmt->execute([':eid' => $external_id, ':username' => $id, ':tid' => (int) $token['id']]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO `scim_maps` (`external_id`, `username`, `token_id`) VALUES (:eid, :username, :tid)");
      $stmt->execute([':eid' => $external_id, ':username' => $id, ':tid' => (int) $token['id']]);
    }
  }

  scim_log('info', "Replaced mailbox '$id' via SCIM (token ID {$token['id']})");
  return scim_get_user($id, $token);
}

function scim_patch_user(string $id, array $body, array $token): array {
  global $pdo;

  // Verify user exists
  scim_get_user($id, $token);

  $operations = $body['Operations'] ?? [];
  if (empty($operations) || !is_array($operations)) {
    scim_error(400, 'Operations array is required', 'invalidSyntax');
  }

  $update = []; // fields to update in mailbox

  foreach ($operations as $op) {
    $op_name = strtolower($op['op'] ?? '');
    $path    = $op['path'] ?? null;
    $value   = $op['value'] ?? null;

    if (!in_array($op_name, ['add', 'replace', 'remove'])) {
      scim_error(400, "Unsupported operation '$op_name'", 'invalidSyntax');
    }

    // Handle path-less value object (e.g., {"op":"replace","value":{"active":false}})
    if ($path === null && is_array($value)) {
      foreach ($value as $attr => $val) {
        $update = array_merge($update, scim_patch_resolve_attr($attr, $val, $op_name));
      }
      continue;
    }

    if ($path === null) {
      scim_error(400, 'path is required for this operation', 'invalidSyntax');
    }

    $update = array_merge($update, scim_patch_resolve_attr($path, $value, $op_name));
  }

  if (empty($update)) {
    // No-op — return current state
    return scim_get_user($id, $token);
  }

  scim_setup_session();
  $edit_data = array_merge(['username' => [$id]], $update);
  mailbox('edit', 'mailbox', $edit_data);

  scim_log('info', "Patched mailbox '$id' via SCIM (token ID {$token['id']})");
  return scim_get_user($id, $token);
}

/**
 * Translate a single SCIM PATCH path+value into mailbox() edit parameters.
 */
function scim_patch_resolve_attr(string $path, mixed $value, string $op): array {
  $supported = [
    'active'          => 'active',
    'displayname'     => 'name',
    'name.formatted'  => 'name',
    'name.givenname'  => null, // handled specially
    'name.familyname' => null, // handled specially
  ];

  $path_lower = strtolower($path);

  if (!array_key_exists($path_lower, $supported)) {
    scim_error(400, "Unsupported PATCH path '$path'", 'invalidPath');
  }

  if ($op === 'remove' && in_array($path_lower, ['active'])) {
    scim_error(400, "Cannot remove required attribute '$path'", 'noTarget');
  }

  switch ($path_lower) {
    case 'active':
      return ['active' => (int)(bool)$value];
    case 'displayname':
    case 'name.formatted':
      return ['name' => trim((string)$value)];
    case 'name.givenname':
    case 'name.familyname':
      // We can only update the full name; return a placeholder that signals partial update
      // The caller must handle this by fetching current name and merging
      // For simplicity, if only one part is provided, use it as the full name
      return ['name' => trim((string)$value)];
  }

  return [];
}

function scim_delete_user(string $id, array $token): void {
  // Verify user exists (exits with 404 if not found or domain restricted)
  scim_get_user($id, $token);

  scim_setup_session();
  // Soft deactivate — preserve mail data
  mailbox('edit', 'mailbox', [
    'username' => [$id],
    'active'   => 0,
  ]);

  // scim_maps row intentionally kept for audit trail

  scim_log('info', "Deactivated mailbox '$id' via SCIM DELETE (token ID {$token['id']})");
  http_response_code(204);
  exit;
}
