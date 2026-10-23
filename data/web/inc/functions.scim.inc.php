<?php

// ─── Constants ───────────────────────────────────────────────────────────────

const SCIM_URN_USER    = 'urn:ietf:params:scim:schemas:core:2.0:User';
const SCIM_URN_EXT     = 'urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User';
const SCIM_URN_ERROR   = 'urn:ietf:params:scim:api:messages:2.0:Error';
const SCIM_URN_LIST    = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
const SCIM_URN_PATCHOP = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';
const SCIM_URN_SEARCH  = 'urn:ietf:params:scim:api:messages:2.0:SearchRequest';
const SCIM_URN_SPC     = 'urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig';
const SCIM_URN_RTYPE   = 'urn:ietf:params:scim:schemas:core:2.0:ResourceType';

// ─── Helpers ────────────────────────────────────────────────────────────────

function scim_log(string $priority, string $message): void {
  global $redis;
  // functions.scim.inc.php is also loaded by the admin UI via prerequisites,
  // where token management runs without a SCIM request context
  if (!isset($redis) || !is_object($redis)) {
    return;
  }
  $redis->lPush('SCIM_LOG', json_encode([
    'time'     => time(),
    'priority' => $priority,
    'task'     => 'SCIM',
    'message'  => $message,
  ]));
}

/**
 * Output a RFC 7644 §3.12 error response and exit.
 * 401 responses always carry a WWW-Authenticate challenge (RFC 7235 §3.1 / RFC 6750 §3).
 */
function scim_error(int $status, string $detail, string $scimType = '', array $headers = []): never {
  // Log every error response. Without this an error is invisible in SCIM_LOG —
  // only the incoming request line appears, with no outcome — which makes a
  // failing provisioning run impossible to diagnose from the mailcow side.
  scim_log('err', ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?')
    . ' -> ' . $status . ($scimType !== '' ? ' ' . $scimType : '') . ': ' . $detail);
  if (ob_get_length()) {
    ob_clean();
  }
  if ($status === 401 && !preg_grep('/^WWW-Authenticate:/i', $headers)) {
    $headers[] = 'WWW-Authenticate: Bearer realm="mailcow SCIM"';
  }
  http_response_code($status);
  foreach ($headers as $h) {
    header($h);
  }
  $body = [
    'schemas' => [SCIM_URN_ERROR],
    'status'  => (string) $status,
    'detail'  => $detail,
  ];
  if ($scimType !== '') {
    $body['scimType'] = $scimType;
  }
  echo json_encode($body, JSON_UNESCAPED_SLASHES);
  exit;
}

/**
 * Emit a SCIM response and exit. Content-Type is set globally by scim.php.
 */
function scim_respond(?array $body, int $status = 200, array $headers = []): never {
  if (ob_get_length()) {
    ob_clean();
  }
  http_response_code($status);
  foreach ($headers as $h) {
    header($h);
  }
  if ($body !== null) {
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
  }
  exit;
}

/**
 * RFC 7643 §2.1: attribute names are case-insensitive.
 * Normalize all object keys in a decoded request body to lowercase so lookups
 * ("userName" vs "username" vs "USERNAME") behave identically.
 */
function scim_lower_keys(mixed $data): mixed {
  if (!is_array($data)) {
    return $data;
  }
  $out = [];
  foreach ($data as $k => $v) {
    $out[is_string($k) ? strtolower($k) : $k] = scim_lower_keys($v);
  }
  return $out;
}

/**
 * Case-insensitive key lookup in an associative array.
 */
function scim_ci_get(array $arr, string $key): mixed {
  if (array_key_exists($key, $arr)) {
    return $arr[$key];
  }
  foreach ($arr as $k => $v) {
    if (is_string($k) && strcasecmp($k, $key) === 0) {
      return $v;
    }
  }
  return null;
}

/**
 * Strict-ish boolean coercion for SCIM boolean attributes. Accepts real
 * booleans, 0/1, and the string forms IdPs actually send (Entra ID emits
 * "True"/"False"). Anything else is a 400 — a blind (bool) cast would turn
 * the string "False" into true and silently leave deactivated users active.
 */
function scim_to_bool(mixed $value, string $attr = 'active'): bool {
  if (is_bool($value)) {
    return $value;
  }
  if (is_int($value) && ($value === 0 || $value === 1)) {
    return $value === 1;
  }
  if (is_string($value)) {
    $v = strtolower(trim($value));
    if ($v === 'true' || $v === '1') {
      return true;
    }
    if ($v === 'false' || $v === '0') {
      return false;
    }
  }
  scim_error(400, "'$attr' must be a boolean", 'invalidValue');
}

/**
 * Read and validate the JSON request body (RFC 7644 §3.1).
 * - Wrong Content-Type → 415
 * - Missing or malformed JSON → 400 invalidSyntax
 * Returns the body with all keys lowercased.
 */
function scim_read_body(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
  if ($ct !== '') {
    $mime = strtolower(trim(explode(';', $ct)[0]));
    if (!in_array($mime, ['application/scim+json', 'application/json'], true)) {
      scim_error(415, "Unsupported media type '$mime'; use application/scim+json");
    }
  }
  $raw = file_get_contents('php://input');
  if (trim((string) $raw) === '') {
    scim_error(400, 'Request body is required', 'invalidSyntax');
  }
  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    scim_error(400, 'Request body is not valid JSON', 'invalidSyntax');
  }
  return scim_lower_keys($decoded);
}

/**
 * Require the request body to declare a schema URN (RFC 7644 §3.1).
 */
function scim_require_schema(array $body, string $urn): void {
  $schemas = $body['schemas'] ?? null;
  if (!is_array($schemas)) {
    scim_error(400, "'schemas' attribute is required", 'invalidSyntax');
  }
  foreach ($schemas as $s) {
    if (is_string($s) && strcasecmp($s, $urn) === 0) {
      return;
    }
  }
  scim_error(400, "'schemas' must include '$urn'", 'invalidValue');
}

function scim_uuid_v4(): string {
  $b = random_bytes(16);
  $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
  $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
  $hex = bin2hex($b);
  return sprintf('%s-%s-%s-%s-%s',
    substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4),
    substr($hex, 16, 4), substr($hex, 20, 12));
}

/**
 * Canonical base URL for SCIM resources. Uses MAILCOW_HOSTNAME rather than the
 * client-controlled Host header.
 */
function scim_base_url(): string {
  $host = getenv('MAILCOW_HOSTNAME');
  if (empty($host)) {
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
  }
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  return $scheme . '://' . $host . '/scim/v2';
}

// ─── Identity mapping (scim_maps: one row per SCIM-managed mailbox) ──────────

/**
 * Fetch (or lazily create) the scim_maps identity row for a mailbox.
 */
function scim_ensure_map(string $username, ?int $token_id): array {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM `scim_maps` WHERE `username` = :username");
  $stmt->execute([':username' => $username]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && !empty($row['scim_id'])) {
    return $row;
  }
  $scim_id = scim_uuid_v4();
  try {
    if ($row) {
      // Legacy row created before scim_id existed — backfill it
      $stmt = $pdo->prepare("UPDATE `scim_maps` SET `scim_id` = :sid WHERE `username` = :username");
      $stmt->execute([':sid' => $scim_id, ':username' => $username]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO `scim_maps` (`scim_id`, `username`, `token_id`) VALUES (:sid, :username, :tid)");
      $stmt->execute([':sid' => $scim_id, ':username' => $username, ':tid' => $token_id]);
    }
  } catch (PDOException $e) {
    // Lost a race against a concurrent request — fall through to re-select
  }
  $stmt = $pdo->prepare("SELECT * FROM `scim_maps` WHERE `username` = :username");
  $stmt->execute([':username' => $username]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['scim_id' => $scim_id, 'username' => $username, 'external_id' => null, 'template' => null];
}

/**
 * Fetch a mailbox row (joined with its identity row) by SCIM id.
 * Returns null when not found or outside the token's domain restriction —
 * callers translate that to 404 so out-of-scope resources are indistinguishable
 * from nonexistent ones.
 */
function scim_fetch_row_by_scim_id(string $scim_id, array $token): ?array {
  global $pdo;
  $stmt = $pdo->prepare(
    "SELECT m.*, sm.scim_id, sm.external_id, sm.template AS scim_template
     FROM `scim_maps` sm
     INNER JOIN `mailbox` m ON m.username = sm.username
     WHERE sm.scim_id = :sid AND m.authsource = 'scim'"
  );
  $stmt->execute([':sid' => $scim_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    return null;
  }
  if (!empty($token['domain_restriction']) && strcasecmp($row['domain'], $token['domain_restriction']) !== 0) {
    return null;
  }
  return $row;
}

// ─── Representation ─────────────────────────────────────────────────────────

/**
 * Map a mailbox DB row (joined with scim_maps) to a SCIM User resource.
 */
function scim_user_to_response(array $row): array {
  $name_parts = explode(' ', trim($row['name'] ?? ''), 2);
  $given  = $name_parts[0] ?? '';
  $family = $name_parts[1] ?? '';

  $schemas = [SCIM_URN_USER];
  $obj = [
    'schemas'  => $schemas,
    'id'       => $row['scim_id'],
    'userName' => $row['username'],
  ];
  if (!empty($row['external_id'])) {
    $obj['externalId'] = $row['external_id'];
  }
  $obj['displayName'] = $row['name'] ?? '';
  $obj['name'] = [
    'formatted'  => $row['name'] ?? '',
    'givenName'  => $given,
    'familyName' => $family,
  ];
  $obj['active'] = (bool) (int) $row['active'];
  $obj['emails'] = [
    ['value' => $row['username'], 'type' => 'work', 'primary' => true],
  ];

  if (!empty($row['scim_template'])) {
    $obj['schemas'][] = SCIM_URN_EXT;
    $obj[SCIM_URN_EXT] = ['template' => $row['scim_template']];
  }

  $meta = [
    'resourceType' => 'User',
    'location'     => scim_base_url() . '/Users/' . rawurlencode($row['scim_id']),
  ];
  if (!empty($row['created'])) {
    $meta['created'] = (new DateTime($row['created']))->format(DateTime::RFC3339);
  }
  if (!empty($row['modified'])) {
    $meta['lastModified'] = (new DateTime($row['modified']))->format(DateTime::RFC3339);
  }
  $obj['meta'] = $meta;

  return $obj;
}

/**
 * Resolve display name from a (key-lowercased) SCIM User request body.
 * Priority: displayName > name.formatted > givenName+familyName > local part of userName
 */
function scim_resolve_name(array $body): string {
  if (!empty($body['displayname']) && is_string($body['displayname'])) {
    return trim($body['displayname']);
  }
  $name = is_array($body['name'] ?? null) ? $body['name'] : [];
  if (!empty($name['formatted']) && is_string($name['formatted'])) {
    return trim($name['formatted']);
  }
  $given  = trim((string) ($name['givenname'] ?? ''));
  $family = trim((string) ($name['familyname'] ?? ''));
  if ($given !== '' || $family !== '') {
    return trim("$given $family");
  }
  $userName = (string) ($body['username'] ?? '');
  return strstr($userName, '@', true) ?: $userName;
}

// ─── Attribute registry / paths ─────────────────────────────────────────────

/**
 * Known attributes of our User representation, lowercased.
 * Value = list of sub-attributes ([] = none / not complex).
 */
function scim_known_attrs(): array {
  return [
    'schemas'     => [],
    'id'          => [],
    'externalid'  => [],
    'username'    => [],
    'displayname' => [],
    'active'      => [],
    'name'        => ['formatted', 'givenname', 'familyname'],
    'emails'      => ['value', 'type', 'primary'],
    'meta'        => ['resourcetype', 'created', 'lastmodified', 'location'],
  ];
}

/**
 * Split an attribute specifier ("userName", "name.givenName",
 * "urn:...:User:userName", "urn:mailcow:...:User:template") into
 * ['urn' => 'core'|'ext', 'parts' => [lowercased path segments]].
 * Returns null for unknown URNs.
 */
function scim_split_attr(string $word): ?array {
  $lower = strtolower($word);
  $core_prefix = strtolower(SCIM_URN_USER) . ':';
  $ext_prefix  = strtolower(SCIM_URN_EXT) . ':';
  if ($lower === strtolower(SCIM_URN_EXT)) {
    return ['urn' => 'ext', 'parts' => []];
  }
  if ($lower === strtolower(SCIM_URN_USER)) {
    return ['urn' => 'core', 'parts' => []];
  }
  if (str_starts_with($lower, $core_prefix)) {
    $rest = substr($lower, strlen($core_prefix));
    $urn = 'core';
  } elseif (str_starts_with($lower, $ext_prefix)) {
    $rest = substr($lower, strlen($ext_prefix));
    $urn = 'ext';
  } elseif (str_contains($lower, ':')) {
    return null; // unknown URN
  } else {
    $rest = $lower;
    $urn = 'core';
  }
  $parts = $rest === '' ? [] : explode('.', $rest);
  return ['urn' => $urn, 'parts' => $parts];
}

/**
 * Validate a split attribute spec against the registry.
 */
function scim_attr_is_known(array $spec): bool {
  if ($spec['urn'] === 'ext') {
    return $spec['parts'] === [] || $spec['parts'] === ['template'];
  }
  $known = scim_known_attrs();
  $parts = $spec['parts'];
  if (count($parts) === 0 || count($parts) > 2) {
    return false;
  }
  if (!array_key_exists($parts[0], $known)) {
    return false;
  }
  if (count($parts) === 2 && !in_array($parts[1], $known[$parts[0]], true)) {
    return false;
  }
  return true;
}

// ─── Filter parser (RFC 7644 §3.4.2.2 grammar) ──────────────────────────────

/**
 * Tokenize a SCIM filter / PATCH path expression.
 * Throws RuntimeException on lexical errors (callers map to invalidFilter/invalidPath).
 */
function scim_filter_tokenize(string $s): array {
  $tokens = [];
  $i = 0;
  $n = strlen($s);
  while ($i < $n) {
    $c = $s[$i];
    if (ctype_space($c)) { $i++; continue; }
    if (in_array($c, ['(', ')', '[', ']'], true)) {
      $tokens[] = ['t' => $c, 'v' => null];
      $i++;
      continue;
    }
    if ($c === '"') {
      $j = $i + 1;
      while ($j < $n) {
        if ($s[$j] === '\\') { $j += 2; continue; }
        if ($s[$j] === '"') { break; }
        $j++;
      }
      if ($j >= $n) {
        throw new RuntimeException('Unterminated string literal');
      }
      $lit = substr($s, $i, $j - $i + 1);
      $val = json_decode($lit);
      if (!is_string($val)) {
        throw new RuntimeException('Invalid string literal');
      }
      $tokens[] = ['t' => 'str', 'v' => $val];
      $i = $j + 1;
      continue;
    }
    $j = $i;
    while ($j < $n && !ctype_space($s[$j]) && !in_array($s[$j], ['(', ')', '[', ']', '"'], true)) {
      $j++;
    }
    $tokens[] = ['t' => 'word', 'v' => substr($s, $i, $j - $i)];
    $i = $j;
  }
  return $tokens;
}

/**
 * Recursive-descent parser producing an AST.
 * Node shapes:
 *   ['op'=>'or'|'and', 'l'=>node, 'r'=>node]
 *   ['op'=>'not', 'f'=>node]
 *   ['op'=>'pr',  'attr'=>spec]
 *   ['op'=>'cmp', 'attr'=>spec, 'cmp'=>string, 'val'=>mixed]
 *   ['op'=>'valpath', 'attr'=>spec, 'filter'=>node, 'sub'=>?string, 'cmp'=>?string, 'val'=>mixed, 'pr'=>bool]
 *
 * $ctx: when non-null, we're inside a valuePath's brackets — attribute names
 * resolve as sub-attributes of $ctx and nested valuePaths are rejected.
 */
function scim_filter_parse(array $tokens, int &$pos, ?string $ctx = null): array {
  $node = scim_filter_parse_and($tokens, $pos, $ctx);
  while (isset($tokens[$pos]) && $tokens[$pos]['t'] === 'word' && strcasecmp($tokens[$pos]['v'], 'or') === 0) {
    $pos++;
    $right = scim_filter_parse_and($tokens, $pos, $ctx);
    $node = ['op' => 'or', 'l' => $node, 'r' => $right];
  }
  return $node;
}

function scim_filter_parse_and(array $tokens, int &$pos, ?string $ctx): array {
  $node = scim_filter_parse_unary($tokens, $pos, $ctx);
  while (isset($tokens[$pos]) && $tokens[$pos]['t'] === 'word' && strcasecmp($tokens[$pos]['v'], 'and') === 0) {
    $pos++;
    $right = scim_filter_parse_unary($tokens, $pos, $ctx);
    $node = ['op' => 'and', 'l' => $node, 'r' => $right];
  }
  return $node;
}

function scim_filter_parse_unary(array $tokens, int &$pos, ?string $ctx): array {
  $tok = $tokens[$pos] ?? null;
  if ($tok !== null && $tok['t'] === 'word' && strcasecmp($tok['v'], 'not') === 0
      && isset($tokens[$pos + 1]) && $tokens[$pos + 1]['t'] === '(') {
    $pos += 2;
    $inner = scim_filter_parse($tokens, $pos, $ctx);
    if (!isset($tokens[$pos]) || $tokens[$pos]['t'] !== ')') {
      throw new RuntimeException("Expected ')' after not(...)");
    }
    $pos++;
    return ['op' => 'not', 'f' => $inner];
  }
  if ($tok !== null && $tok['t'] === '(') {
    $pos++;
    $inner = scim_filter_parse($tokens, $pos, $ctx);
    if (!isset($tokens[$pos]) || $tokens[$pos]['t'] !== ')') {
      throw new RuntimeException("Expected ')'");
    }
    $pos++;
    return $inner;
  }
  return scim_filter_parse_attrexp($tokens, $pos, $ctx);
}

function scim_filter_parse_attrexp(array $tokens, int &$pos, ?string $ctx): array {
  $tok = $tokens[$pos] ?? null;
  if ($tok === null || $tok['t'] !== 'word') {
    throw new RuntimeException('Expected attribute expression');
  }
  $pos++;
  $attr_word = $tok['v'];

  if ($ctx !== null) {
    // Inside valuePath brackets: names are sub-attributes of the parent
    $spec = scim_split_attr($ctx . '.' . $attr_word);
    if ($spec === null || !scim_attr_is_known($spec)) {
      throw new RuntimeException("Unknown attribute '$attr_word'");
    }
    // Evaluation happens against each item of the multi-valued attribute,
    // so the resolved path must be relative to the item
    $spec['parts'] = array_slice($spec['parts'], 1);
  } else {
    $spec = scim_split_attr($attr_word);
    if ($spec === null || !scim_attr_is_known($spec)) {
      throw new RuntimeException("Unknown attribute '$attr_word'");
    }
  }

  // valuePath: attrPath "[" valFilter "]" — optionally followed by
  // ".sub op value" (not in the strict grammar, but emitted by Entra ID)
  if ($ctx === null && isset($tokens[$pos]) && $tokens[$pos]['t'] === '[') {
    if ($spec['urn'] !== 'core' || $spec['parts'] !== ['emails']) {
      throw new RuntimeException("Attribute '$attr_word' does not support value filters");
    }
    $pos++;
    $inner = scim_filter_parse($tokens, $pos, $spec['parts'][0]);
    if (!isset($tokens[$pos]) || $tokens[$pos]['t'] !== ']') {
      throw new RuntimeException("Expected ']'");
    }
    $pos++;
    $node = ['op' => 'valpath', 'attr' => $spec, 'filter' => $inner, 'sub' => null, 'cmp' => null, 'val' => null, 'pr' => false];
    // Lenient extension: emails[type eq "work"].value eq "x"
    if (isset($tokens[$pos]) && $tokens[$pos]['t'] === 'word' && str_starts_with($tokens[$pos]['v'], '.')) {
      $sub = strtolower(substr($tokens[$pos]['v'], 1));
      $subspec = scim_split_attr($spec['parts'][0] . '.' . $sub);
      if ($subspec === null || !scim_attr_is_known($subspec)) {
        throw new RuntimeException("Unknown sub-attribute '$sub'");
      }
      $pos++;
      $node['sub'] = $sub;
      [$cmp, $val, $pr] = scim_filter_parse_op($tokens, $pos);
      $node['cmp'] = $cmp;
      $node['val'] = $val;
      $node['pr']  = $pr;
    }
    return $node;
  }

  [$cmp, $val, $pr] = scim_filter_parse_op($tokens, $pos);
  if ($pr) {
    return ['op' => 'pr', 'attr' => $spec];
  }
  return ['op' => 'cmp', 'attr' => $spec, 'cmp' => $cmp, 'val' => $val];
}

/**
 * Parse "pr" or "<compareOp> <compValue>". Returns [op, value, isPr].
 */
function scim_filter_parse_op(array $tokens, int &$pos): array {
  $tok = $tokens[$pos] ?? null;
  if ($tok === null || $tok['t'] !== 'word') {
    throw new RuntimeException('Expected operator');
  }
  $op = strtolower($tok['v']);
  $pos++;
  if ($op === 'pr') {
    return ['pr', null, true];
  }
  if (!in_array($op, ['eq', 'ne', 'co', 'sw', 'ew', 'gt', 'ge', 'lt', 'le'], true)) {
    throw new RuntimeException("Unknown operator '$op'");
  }
  $vtok = $tokens[$pos] ?? null;
  if ($vtok === null) {
    throw new RuntimeException('Expected comparison value');
  }
  $pos++;
  if ($vtok['t'] === 'str') {
    return [$op, $vtok['v'], false];
  }
  if ($vtok['t'] === 'word') {
    $w = strtolower($vtok['v']);
    if ($w === 'true')  { return [$op, true, false]; }
    if ($w === 'false') { return [$op, false, false]; }
    if ($w === 'null')  { return [$op, null, false]; }
    if (is_numeric($vtok['v'])) {
      return [$op, $vtok['v'] + 0, false];
    }
  }
  throw new RuntimeException('Invalid comparison value');
}

/**
 * Parse a full filter string → AST. Exits with 400 invalidFilter on error.
 */
function scim_parse_filter(string $filter): array {
  try {
    $tokens = scim_filter_tokenize($filter);
    if (empty($tokens)) {
      throw new RuntimeException('Empty filter');
    }
    $pos = 0;
    $ast = scim_filter_parse($tokens, $pos);
    if ($pos !== count($tokens)) {
      throw new RuntimeException('Unexpected trailing tokens in filter');
    }
    return $ast;
  } catch (RuntimeException $e) {
    scim_error(400, 'Invalid filter: ' . $e->getMessage(), 'invalidFilter');
  }
}

// ─── Filter evaluation ──────────────────────────────────────────────────────

/**
 * Resolve an attribute spec against a resource → flat list of values.
 */
function scim_resolve_values(array $spec, array $resource): array {
  if ($spec['urn'] === 'ext') {
    $node = scim_ci_get($resource, SCIM_URN_EXT);
    if (!is_array($node)) {
      return [];
    }
    if ($spec['parts'] === []) {
      return [$node];
    }
  } else {
    $node = $resource;
  }
  foreach ($spec['parts'] as $part) {
    if (is_array($node) && array_is_list($node)) {
      $collected = [];
      foreach ($node as $item) {
        if (is_array($item)) {
          $v = scim_ci_get($item, $part);
          if ($v !== null) {
            $collected[] = $v;
          }
        }
      }
      $node = $collected;
      continue;
    }
    if (!is_array($node)) {
      return [];
    }
    $node = scim_ci_get($node, $part);
    if ($node === null) {
      return [];
    }
  }
  if (is_array($node) && array_is_list($node)) {
    return $node;
  }
  return [$node];
}

/**
 * Compare one actual value against a filter literal.
 */
function scim_compare_value(string $op, mixed $actual, mixed $expected): bool {
  // Complex value: RFC 7644 §3.4.2.2 — compare against its "value" sub-attribute
  if (is_array($actual)) {
    $actual = scim_ci_get($actual, 'value');
    if ($actual === null) {
      return false;
    }
  }
  if ($expected === null) {
    return match ($op) {
      'eq' => $actual === null,
      'ne' => $actual !== null,
      default => false,
    };
  }
  if (is_bool($expected) || is_bool($actual)) {
    // Only true/false (and their string spellings) may match a boolean
    // attribute — (bool)"false" === true would invert the filter
    $to_bool = static function ($v): ?bool {
      if (is_bool($v)) {
        return $v;
      }
      if (is_string($v)) {
        if (strcasecmp($v, 'true') === 0) {
          return true;
        }
        if (strcasecmp($v, 'false') === 0) {
          return false;
        }
      }
      return null;
    };
    $a = $to_bool($actual);
    $e = $to_bool($expected);
    if ($a === null || $e === null) {
      return false;
    }
    return match ($op) {
      'eq' => $a === $e,
      'ne' => $a !== $e,
      default => false,
    };
  }
  if (is_int($expected) || is_float($expected)) {
    if (!is_numeric($actual)) {
      return false;
    }
    $a = (float) $actual;
    $e = (float) $expected;
    return match ($op) {
      'eq' => $a == $e,
      'ne' => $a != $e,
      'gt' => $a > $e,
      'ge' => $a >= $e,
      'lt' => $a < $e,
      'le' => $a <= $e,
      default => false,
    };
  }
  // String comparison — all our string attributes are caseExact=false
  $a = (string) $actual;
  $e = (string) $expected;
  // DateTime values compare as instants, not lexically — the server emits
  // '+00:00' offsets while clients typically send 'Z'
  if ($op !== 'co' && $op !== 'sw' && $op !== 'ew'
      && preg_match('/^\d{4}-\d{2}-\d{2}T/', $a) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $e)) {
    $ta = strtotime($a);
    $te = strtotime($e);
    if ($ta !== false && $te !== false) {
      return match ($op) {
        'eq' => $ta === $te,
        'ne' => $ta !== $te,
        'gt' => $ta > $te,
        'ge' => $ta >= $te,
        'lt' => $ta < $te,
        'le' => $ta <= $te,
        default => false,
      };
    }
  }
  return match ($op) {
    'eq' => strcasecmp($a, $e) === 0,
    'ne' => strcasecmp($a, $e) !== 0,
    'co' => $e === '' || stripos($a, $e) !== false,
    'sw' => $e === '' || stripos($a, $e) === 0,
    'ew' => $e === '' || (strlen($a) >= strlen($e) && strcasecmp(substr($a, -strlen($e)), $e) === 0),
    'gt' => strcasecmp($a, $e) > 0,
    'ge' => strcasecmp($a, $e) >= 0,
    'lt' => strcasecmp($a, $e) < 0,
    'le' => strcasecmp($a, $e) <= 0,
    default => false,
  };
}

function scim_filter_eval(array $node, array $resource): bool {
  switch ($node['op']) {
    case 'or':
      return scim_filter_eval($node['l'], $resource) || scim_filter_eval($node['r'], $resource);
    case 'and':
      return scim_filter_eval($node['l'], $resource) && scim_filter_eval($node['r'], $resource);
    case 'not':
      return !scim_filter_eval($node['f'], $resource);
    case 'pr':
      $values = scim_resolve_values($node['attr'], $resource);
      foreach ($values as $v) {
        if ($v !== null && $v !== '' && $v !== []) {
          return true;
        }
      }
      return false;
    case 'cmp':
      $values = scim_resolve_values($node['attr'], $resource);
      foreach ($values as $v) {
        if (scim_compare_value($node['cmp'], $v, $node['val'])) {
          return true;
        }
      }
      return false;
    case 'valpath':
      $items = scim_resolve_values($node['attr'], $resource);
      $matched = [];
      foreach ($items as $item) {
        if (is_array($item) && scim_filter_eval($node['filter'], $item)) {
          $matched[] = $item;
        }
      }
      if ($node['sub'] === null) {
        return count($matched) > 0;
      }
      foreach ($matched as $item) {
        $v = scim_ci_get($item, $node['sub']);
        if ($node['pr']) {
          if ($v !== null && $v !== '') {
            return true;
          }
        } elseif (scim_compare_value($node['cmp'], $v, $node['val'])) {
          return true;
        }
      }
      return false;
  }
  return false;
}

// ─── PATCH path parser (RFC 7644 §3.5.2: path = attrPath / valuePath [subAttr]) ──

/**
 * Parse a PATCH "path" → ['spec'=>attrspec, 'filter'=>?node, 'sub'=>?string].
 * Exits with 400 invalidPath on error.
 */
function scim_parse_path(string $path): array {
  try {
    $tokens = scim_filter_tokenize($path);
    if (empty($tokens) || $tokens[0]['t'] !== 'word') {
      throw new RuntimeException('Expected attribute path');
    }
    // Unstored-but-standard core attributes (title, phoneNumbers[...]...) are
    // accepted and ignored rather than 400'd — see scim_ignored_attrs()
    $base_word = strtolower($tokens[0]['v']);
    $base_name = str_contains($base_word, ':') ? substr($base_word, strrpos($base_word, ':') + 1) : $base_word;
    $base_name = explode('.', $base_name, 2)[0];
    if (in_array($base_name, scim_ignored_attrs(), true)) {
      return ['ignored' => true, 'spec' => null, 'filter' => null, 'sub' => null];
    }
    $spec = scim_split_attr($tokens[0]['v']);
    if ($spec === null || !scim_attr_is_known($spec)) {
      throw new RuntimeException("Unknown attribute '{$tokens[0]['v']}'");
    }
    $pos = 1;
    $filter = null;
    $sub = null;
    if (isset($tokens[$pos]) && $tokens[$pos]['t'] === '[') {
      if ($spec['urn'] !== 'core' || $spec['parts'] !== ['emails']) {
        throw new RuntimeException('Value filters are only supported on multi-valued attributes');
      }
      $pos++;
      $filter = scim_filter_parse($tokens, $pos, $spec['parts'][0]);
      if (!isset($tokens[$pos]) || $tokens[$pos]['t'] !== ']') {
        throw new RuntimeException("Expected ']'");
      }
      $pos++;
    }
    if (isset($tokens[$pos]) && $tokens[$pos]['t'] === 'word' && str_starts_with($tokens[$pos]['v'], '.')) {
      $sub = strtolower(substr($tokens[$pos]['v'], 1));
      $base = $spec['parts'][count($spec['parts']) - 1] ?? '';
      $subspec = scim_split_attr($base . '.' . $sub);
      if ($subspec === null || !scim_attr_is_known($subspec)) {
        throw new RuntimeException("Unknown sub-attribute '$sub'");
      }
      $pos++;
    }
    if ($pos !== count($tokens)) {
      throw new RuntimeException('Unexpected trailing tokens in path');
    }
    return ['spec' => $spec, 'filter' => $filter, 'sub' => $sub];
  } catch (RuntimeException $e) {
    scim_error(400, 'Invalid path: ' . $e->getMessage(), 'invalidPath');
  }
}

// ─── Attribute projection (RFC 7644 §3.9) ───────────────────────────────────

/**
 * Parse an attributes/excludedAttributes parameter (CSV string or array of
 * strings) into a list of attr specs. Unknown attributes are ignored.
 */
function scim_parse_attr_list(mixed $param): ?array {
  if ($param === null || $param === '') {
    return null;
  }
  $items = is_array($param) ? $param : explode(',', (string) $param);
  $specs = [];
  foreach ($items as $item) {
    $item = trim((string) $item);
    if ($item === '') {
      continue;
    }
    $spec = scim_split_attr($item);
    if ($spec !== null && scim_attr_is_known($spec)) {
      $specs[] = $spec;
    }
  }
  return $specs;
}

/**
 * Apply attributes/excludedAttributes projection to a resource.
 * "schemas" and "id" are returned=always and can never be removed.
 */
function scim_apply_projection(array $res, ?array $attrs, ?array $excluded): array {
  if ($attrs !== null && $excluded !== null) {
    scim_error(400, "'attributes' and 'excludedAttributes' are mutually exclusive", 'invalidValue');
  }
  if ($attrs === null && $excluded === null) {
    return $res;
  }

  if ($attrs !== null) {
    $out = ['schemas' => $res['schemas'], 'id' => $res['id']];
    foreach ($attrs as $spec) {
      if ($spec['urn'] === 'ext') {
        $ext = scim_ci_get($res, SCIM_URN_EXT);
        if (!is_array($ext)) {
          continue;
        }
        if ($spec['parts'] === [] || $spec['parts'] === ['template']) {
          $out[SCIM_URN_EXT] = $ext;
        }
        continue;
      }
      $parts = $spec['parts'];
      $canonical = scim_find_key($res, $parts[0]);
      if ($canonical === null) {
        continue;
      }
      $val = $res[$canonical];
      if (count($parts) === 1) {
        $out[$canonical] = $val;
        continue;
      }
      // sub-attribute request: name.givenName / emails.value / meta.created
      if (is_array($val) && array_is_list($val)) {
        $filtered = [];
        foreach ($val as $item) {
          if (!is_array($item)) { continue; }
          $k = scim_find_key($item, $parts[1]);
          if ($k !== null) {
            $filtered[] = [$k => $item[$k]];
          }
        }
        $existing = (isset($out[$canonical]) && is_array($out[$canonical])) ? $out[$canonical] : null;
        if ($existing !== null) {
          foreach ($filtered as $i => $item) {
            $existing[$i] = array_merge($existing[$i] ?? [], $item);
          }
          $out[$canonical] = $existing;
        } else {
          $out[$canonical] = $filtered;
        }
      } elseif (is_array($val)) {
        $k = scim_find_key($val, $parts[1]);
        if ($k !== null) {
          $out[$canonical] = array_merge($out[$canonical] ?? [], [$k => $val[$k]]);
        }
      }
    }
    return $out;
  }

  // excludedAttributes
  $out = $res;
  foreach ($excluded as $spec) {
    if ($spec['urn'] === 'ext') {
      $k = scim_find_key($out, SCIM_URN_EXT);
      if ($k !== null && ($spec['parts'] === [] || $spec['parts'] === ['template'])) {
        unset($out[$k]);
      }
      continue;
    }
    $parts = $spec['parts'];
    if ($parts[0] === 'schemas' || $parts[0] === 'id') {
      continue; // returned=always
    }
    $canonical = scim_find_key($out, $parts[0]);
    if ($canonical === null) {
      continue;
    }
    if (count($parts) === 1) {
      unset($out[$canonical]);
      continue;
    }
    $val = $out[$canonical];
    if (is_array($val) && array_is_list($val)) {
      foreach ($val as $i => $item) {
        if (is_array($item)) {
          $k = scim_find_key($item, $parts[1]);
          if ($k !== null) {
            unset($val[$i][$k]);
          }
        }
      }
      $out[$canonical] = array_values($val);
    } elseif (is_array($val)) {
      $k = scim_find_key($val, $parts[1]);
      if ($k !== null) {
        unset($val[$k]);
        $out[$canonical] = $val;
      }
    }
  }
  return $out;
}

/**
 * Find the canonical (original-case) key matching $key case-insensitively.
 */
function scim_find_key(array $arr, string $key): ?string {
  if (array_key_exists($key, $arr)) {
    return $key;
  }
  foreach ($arr as $k => $_) {
    if (is_string($k) && strcasecmp($k, $key) === 0) {
      return $k;
    }
  }
  return null;
}

/**
 * Project a resource using the request's attributes/excludedAttributes query params.
 */
function scim_project_from_query(array $res): array {
  global $SCIM_QUERY;
  $attrs = scim_parse_attr_list($SCIM_QUERY['attributes'] ?? null);
  $excl  = scim_parse_attr_list($SCIM_QUERY['excludedattributes'] ?? null);
  return scim_apply_projection($res, $attrs, $excl);
}

// ─── mailbox() wrapper (fails loudly instead of silently) ───────────────────

/**
 * Call mailbox() and translate any 'danger' result into a SCIM error.
 */
function scim_mailbox_call(string $action, string $type, array $data): void {
  if (!isset($_SESSION['return']) || !is_array($_SESSION['return'])) {
    $_SESSION['return'] = [];
  }
  $before = count($_SESSION['return']);
  mailbox($action, $type, $data);
  $entries = array_slice($_SESSION['return'], $before);
  foreach ($entries as $ret) {
    if (($ret['type'] ?? '') === 'danger') {
      $msg = $ret['msg'] ?? 'unknown error';
      if (is_array($msg)) {
        $msg = implode(': ', array_map(
          fn($x) => is_array($x) ? implode(', ', array_map('strval', $x)) : (string) $x,
          $msg
        ));
      }
      scim_error(400, 'mailcow rejected the operation: ' . $msg, 'invalidValue');
    }
  }
}

/**
 * Set up admin session context so mailbox() calls have the required ACLs.
 * $_SESSION is a plain array here — scim.php never starts a real PHP session.
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

// ─── Template mapping ───────────────────────────────────────────────────────

/**
 * Map a raw template attribute value through the token's mapper list.
 * Returns the mapped mailbox template name, or null when no mapping matches.
 */
function scim_map_attr_to_template(?string $attr, array $token): ?string {
  if ($attr === null || $attr === '') {
    return null;
  }
  $mappers   = json_decode($token['mappers']   ?? '[]', true) ?? [];
  $templates = json_decode($token['templates'] ?? '[]', true) ?? [];
  $key = array_search($attr, $mappers, true);
  if ($key !== false && isset($templates[$key]) && $templates[$key] !== '') {
    return $templates[$key];
  }
  return null;
}

/**
 * Extract the raw template attribute value from a (key-lowercased) body.
 */
function scim_body_template_attr(array $body): ?string {
  $ext = $body[strtolower(SCIM_URN_EXT)] ?? null;
  if (is_array($ext) && isset($ext['template']) && is_string($ext['template']) && trim($ext['template']) !== '') {
    return trim($ext['template']);
  }
  return null;
}

/**
 * Resolve the mailbox template for a new SCIM-provisioned user:
 * mapped value from the extension attribute, else the token default.
 */
function scim_resolve_template(array $body, array $token): ?string {
  $mapped = scim_map_attr_to_template(scim_body_template_attr($body), $token);
  if ($mapped !== null) {
    return $mapped;
  }
  return !empty($token['default_template']) ? $token['default_template'] : null;
}

/**
 * Enforce immutability of the extension 'template' attribute on PUT/PATCH.
 * Setting an initial value is allowed; changing an existing one is not.
 */
function scim_template_immutable_check(?string $attr, string $op_name, array $row, array $token): void {
  global $pdo;
  $stored = $row['scim_template'] ?? null;
  if ($op_name === 'remove') {
    if (!empty($stored)) {
      scim_error(400, "'template' is immutable and cannot be removed", 'mutability');
    }
    return;
  }
  if ($attr === null) {
    return; // omitted — immutable attributes retain their value
  }
  $resolved = scim_map_attr_to_template($attr, $token) ?? (!empty($token['default_template']) ? $token['default_template'] : null);
  if (empty($stored)) {
    if ($resolved !== null) {
      $stmt = $pdo->prepare("UPDATE `scim_maps` SET `template` = :tpl WHERE `username` = :username");
      $stmt->execute([':tpl' => $resolved, ':username' => $row['username']]);
    }
    return;
  }
  // Accept the server's own echoed resolved name as a no-op: GET→modify→PUT
  // round-trips send back the resolved template ("Employees"), not the mapper key
  if ($resolved !== $stored && $attr !== $stored) {
    scim_error(400, "'template' is immutable; it is applied at creation time only", 'mutability');
  }
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
    scim_error(401, 'Bearer token required', '', ['WWW-Authenticate: Bearer realm="mailcow SCIM"']);
  }

  $raw_token  = $m[1];
  $token_hash = hash('sha256', $raw_token);

  $stmt = $pdo->prepare("SELECT * FROM `scim_tokens` WHERE `token_hash` = :hash AND `active` = '1'");
  $stmt->execute([':hash' => $token_hash]);
  $token = $stmt->fetch(PDO::FETCH_ASSOC);

  if (empty($token)) {
    $redis->publish('F2B_CHANNEL', 'mailcow SCIM: Invalid token from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    scim_log('err', 'Authentication failed: invalid or inactive token from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    scim_error(401, 'Invalid or inactive token', '', ['WWW-Authenticate: Bearer realm="mailcow SCIM", error="invalid_token"']);
  }

  // IP ACL check
  $remote     = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
  $allow_from = array_map('trim', preg_split('/[ ,;\n]+/', $token['allow_from']));
  $allow_from = array_filter($allow_from);
  if (!empty($allow_from) && !ip_acl($remote, $allow_from)) {
    $redis->publish('F2B_CHANNEL', 'mailcow SCIM: IP denied for token from ' . $remote);
    scim_log('err', 'Authentication failed: IP ' . $remote . ' not in allow list for token ID ' . $token['id']);
    scim_error(401, 'IP address not allowed', '', ['WWW-Authenticate: Bearer realm="mailcow SCIM", error="invalid_token"']);
  }

  return $token;
}

// ─── Token management (admin operations) ────────────────────────────────────

/**
 * Parse and JSON-encode the mappers/templates arrays from POST data.
 * Returns [mappers_json, templates_json] with only non-empty paired entries kept.
 */
function scim_encode_mappers(array $data): array {
  $mappers_raw   = $data['mappers']   ?? [];
  $templates_raw = $data['templates'] ?? [];
  // The active-toggle form re-posts the stored JSON strings; decode them so
  // repeated round-trips stay idempotent instead of double-encoding
  if (!is_array($mappers_raw)) {
    $decoded = json_decode((string) $mappers_raw, true);
    $mappers_raw = is_array($decoded) ? $decoded : [$mappers_raw];
  }
  if (!is_array($templates_raw)) {
    $decoded = json_decode((string) $templates_raw, true);
    $templates_raw = is_array($decoded) ? $decoded : [$templates_raw];
  }

  $m = [];
  $t = [];
  foreach ($mappers_raw as $i => $mapper) {
    $mapper   = trim((string) $mapper);
    $template = trim((string) ($templates_raw[$i] ?? ''));
    if ($mapper !== '' && $template !== '') {
      $m[] = $mapper;
      $t[] = $template;
    }
  }

  return [
    count($m) > 0 ? json_encode($m) : null,
    count($t) > 0 ? json_encode($t) : null,
  ];
}

function scim_token(string $_action, array $_data = []): mixed {
  global $pdo;

  switch ($_action) {
    case 'add':
      // Stored raw — Twig autoescapes on output; escaping on input double-encodes on every round-trip
      $description        = trim($_data['description'] ?? '');
      $domain_restriction = !empty($_data['domain_restriction']) ? strtolower(trim($_data['domain_restriction'])) : null;
      $default_template   = !empty($_data['default_template']) ? trim($_data['default_template']) : null;
      $allow_from         = trim($_data['allow_from'] ?? '');
      $allow_claim        = (isset($_data['allow_claim']) && intval($_data['allow_claim']) == 1) ? 1 : 0;
      [$mappers_json, $templates_json] = scim_encode_mappers($_data);

      $raw_token  = bin2hex(random_bytes(32));
      $token_hash = hash('sha256', $raw_token);

      $stmt = $pdo->prepare("INSERT INTO `scim_tokens`
        (`description`, `token_hash`, `domain_restriction`, `default_template`, `allow_from`, `mappers`, `templates`, `allow_claim`, `active`)
        VALUES (:description, :token_hash, :domain_restriction, :default_template, :allow_from, :mappers, :templates, :allow_claim, '1')");
      $stmt->execute([
        ':description'        => $description,
        ':token_hash'         => $token_hash,
        ':domain_restriction' => $domain_restriction,
        ':default_template'   => $default_template,
        ':allow_from'         => $allow_from,
        ':mappers'            => $mappers_json,
        ':templates'          => $templates_json,
        ':allow_claim'        => $allow_claim,
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
      $id               = intval($_data['id'] ?? 0);
      $description      = trim($_data['description'] ?? '');
      $allow_from       = trim($_data['allow_from'] ?? '');
      $active           = (isset($_data['active']) && intval($_data['active']) == 1) ? 1 : 0;
      $allow_claim      = (isset($_data['allow_claim']) && intval($_data['allow_claim']) == 1) ? 1 : 0;
      $default_template = !empty($_data['default_template']) ? trim($_data['default_template']) : null;
      [$mappers_json, $templates_json] = scim_encode_mappers($_data);

      $domain_restriction = !empty($_data['domain_restriction']) ? strtolower(trim($_data['domain_restriction'])) : null;

      $stmt = $pdo->prepare("UPDATE `scim_tokens`
        SET `description` = :description,
            `domain_restriction` = :domain_restriction,
            `default_template` = :default_template,
            `allow_from` = :allow_from,
            `mappers` = :mappers,
            `templates` = :templates,
            `allow_claim` = :allow_claim,
            `active` = :active
        WHERE `id` = :id");
      $stmt->execute([
        ':description'        => $description,
        ':domain_restriction' => $domain_restriction,
        ':default_template'   => $default_template,
        ':allow_from'         => $allow_from,
        ':mappers'            => $mappers_json,
        ':templates'          => $templates_json,
        ':allow_claim'        => $allow_claim,
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
      $stmt = $pdo->query("SELECT `id`, `description`, `domain_restriction`, `default_template`,
        `allow_from`, `mappers`, `templates`, `allow_claim`, `active`, `created`, `modified`
        FROM `scim_tokens` ORDER BY `created` DESC");
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  return false;
}

// ─── Discovery endpoints ─────────────────────────────────────────────────────

function scim_service_provider_config(): array {
  return [
    'schemas'               => [SCIM_URN_SPC],
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
        'specUri'     => 'http://www.rfc-editor.org/info/rfc6750',
        'type'        => 'oauthbearertoken',
        'primary'     => true,
      ],
    ],
    'meta' => [
      'resourceType' => 'ServiceProviderConfig',
      'location'     => scim_base_url() . '/ServiceProviderConfig',
    ],
  ];
}

/**
 * Full RFC 7643 §7 attribute metadata for the schemas we serve.
 */
function scim_schema_definitions(): array {
  $base = scim_base_url();

  $string_attr = function (string $name, string $description, array $overrides = []) {
    return array_merge([
      'name'        => $name,
      'type'        => 'string',
      'multiValued' => false,
      'description' => $description,
      'required'    => false,
      'caseExact'   => false,
      'mutability'  => 'readWrite',
      'returned'    => 'default',
      'uniqueness'  => 'none',
    ], $overrides);
  };

  $user_schema = [
    'id'          => SCIM_URN_USER,
    'name'        => 'User',
    'description' => 'User account (mapped to a mailcow mailbox)',
    'attributes'  => [
      $string_attr('userName',
        'Unique identifier for the user, equal to the primary email address of the mailbox. Immutable: mailcow cannot rename mailboxes.',
        ['required' => true, 'mutability' => 'immutable', 'uniqueness' => 'server']),
      $string_attr('displayName',
        'Human-readable display name. When omitted at creation time or cleared, defaults to the local part of userName.'),
      [
        'name'        => 'name',
        'type'        => 'complex',
        'multiValued' => false,
        'description' => 'The components of the user\'s name. Stored as a single display name; givenName and familyName are derived by splitting on the first space.',
        'required'    => false,
        'mutability'  => 'readWrite',
        'returned'    => 'default',
        'subAttributes' => [
          $string_attr('formatted', 'Full name.'),
          $string_attr('givenName', 'Given name (first token of the stored name).'),
          $string_attr('familyName', 'Family name (remainder of the stored name).'),
        ],
      ],
      [
        'name'        => 'active',
        'type'        => 'boolean',
        'multiValued' => false,
        'description' => 'Mailbox active state.',
        'required'    => false,
        'mutability'  => 'readWrite',
        'returned'    => 'default',
      ],
      [
        'name'        => 'emails',
        'type'        => 'complex',
        'multiValued' => true,
        'description' => 'Email addresses. Read-only: derived from userName.',
        'required'    => false,
        'mutability'  => 'readOnly',
        'returned'    => 'default',
        'subAttributes' => [
          $string_attr('value', 'Email address.', ['mutability' => 'readOnly']),
          $string_attr('type', 'Address classification.', ['mutability' => 'readOnly', 'canonicalValues' => ['work']]),
          [
            'name'        => 'primary',
            'type'        => 'boolean',
            'multiValued' => false,
            'description' => 'Whether this is the primary address.',
            'required'    => false,
            'mutability'  => 'readOnly',
            'returned'    => 'default',
          ],
        ],
      ],
    ],
    'meta' => [
      'resourceType' => 'Schema',
      'location'     => $base . '/Schemas/' . SCIM_URN_USER,
    ],
  ];

  $ext_schema = [
    'id'          => SCIM_URN_EXT,
    'name'        => 'MailcowUser',
    'description' => 'mailcow extension attributes for SCIM-provisioned users',
    'attributes'  => [
      $string_attr('template',
        'Mailbox template selector. The value is resolved through the SCIM token\'s attribute mapping to a mailcow mailbox template and applied at creation time only; responses return the resolved template name.',
        ['mutability' => 'immutable']),
    ],
    'meta' => [
      'resourceType' => 'Schema',
      'location'     => $base . '/Schemas/' . SCIM_URN_EXT,
    ],
  ];

  return [$user_schema, $ext_schema];
}

function scim_schemas(): array {
  $schemas = scim_schema_definitions();
  return [
    'schemas'      => [SCIM_URN_LIST],
    'totalResults' => count($schemas),
    'startIndex'   => 1,
    'itemsPerPage' => count($schemas),
    'Resources'    => $schemas,
  ];
}

function scim_schema_by_id(string $id): ?array {
  foreach (scim_schema_definitions() as $schema) {
    if (strcasecmp($schema['id'], $id) === 0) {
      return $schema;
    }
  }
  return null;
}

function scim_resource_type_definitions(): array {
  $base = scim_base_url();
  return [
    [
      'schemas'          => [SCIM_URN_RTYPE],
      'id'               => 'User',
      'name'             => 'User',
      'endpoint'         => '/Users',
      'description'      => 'User account',
      'schema'           => SCIM_URN_USER,
      'schemaExtensions' => [
        ['schema' => SCIM_URN_EXT, 'required' => false],
      ],
      'meta'             => [
        'resourceType' => 'ResourceType',
        'location'     => $base . '/ResourceTypes/User',
      ],
    ],
  ];
}

function scim_resource_types(): array {
  $types = scim_resource_type_definitions();
  return [
    'schemas'      => [SCIM_URN_LIST],
    'totalResults' => count($types),
    'startIndex'   => 1,
    'itemsPerPage' => count($types),
    'Resources'    => $types,
  ];
}

function scim_resource_type_by_id(string $id): ?array {
  foreach (scim_resource_type_definitions() as $type) {
    if (strcasecmp($type['id'], $id) === 0) {
      return $type;
    }
  }
  return null;
}

// ─── User operations ─────────────────────────────────────────────────────────

/**
 * Shared list/query implementation for GET /Users and POST /.search.
 * $q keys (lowercased): filter, startindex, count, attributes, excludedattributes.
 */
function scim_query_users(array $token, array $q): never {
  global $pdo;

  $filter_str = isset($q['filter']) && $q['filter'] !== '' && $q['filter'] !== null ? (string) $q['filter'] : null;
  $ast = $filter_str !== null ? scim_parse_filter($filter_str) : null;

  $start_index = isset($q['startindex']) ? max(1, intval($q['startindex'])) : 1;
  // RFC 7644 §3.4.2.4: negative count → 0; count=0 returns totalResults only
  $count = isset($q['count']) && $q['count'] !== '' && $q['count'] !== null
    ? min(500, max(0, intval($q['count'])))
    : 100;

  $attrs = scim_parse_attr_list($q['attributes'] ?? null);
  $excl  = scim_parse_attr_list($q['excludedattributes'] ?? null);

  $where  = ["m.authsource = 'scim'"];
  $params = [];
  if (!empty($token['domain_restriction'])) {
    $where[] = 'm.domain = :domain_restriction';
    $params[':domain_restriction'] = $token['domain_restriction'];
  }
  $where_sql = implode(' AND ', $where);

  $stmt = $pdo->prepare(
    "SELECT m.*, sm.scim_id, sm.external_id, sm.template AS scim_template
     FROM `mailbox` m
     LEFT JOIN `scim_maps` sm ON sm.username = m.username
     WHERE $where_sql
     ORDER BY m.username"
  );
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Lazily backfill identity rows for mailboxes pre-created via the admin UI
  foreach ($rows as &$row) {
    if (empty($row['scim_id'])) {
      $map = scim_ensure_map($row['username'], (int) $token['id']);
      $row['scim_id']       = $map['scim_id'];
      $row['external_id']   = $map['external_id'] ?? null;
      $row['scim_template'] = $map['template'] ?? null;
    }
  }
  unset($row);

  $resources = array_map('scim_user_to_response', $rows);

  if ($ast !== null) {
    $resources = array_values(array_filter($resources, fn($r) => scim_filter_eval($ast, $r)));
  }

  $total = count($resources);
  $page  = $count > 0 ? array_slice($resources, $start_index - 1, $count) : [];
  $page  = array_map(fn($r) => scim_apply_projection($r, $attrs, $excl), $page);

  scim_respond([
    'schemas'      => [SCIM_URN_LIST],
    'totalResults' => $total,
    'startIndex'   => $start_index,
    'itemsPerPage' => count($page),
    'Resources'    => $page,
  ]);
}

/**
 * POST /Users/.search and POST /.search (RFC 7644 §3.4.3).
 */
function scim_search(array $body, array $token): never {
  scim_require_schema($body, SCIM_URN_SEARCH);
  // sortBy/sortOrder: sorting is unsupported and declared as such — ignored per §3.4.2.3
  scim_query_users($token, [
    'filter'             => $body['filter'] ?? null,
    'startindex'         => $body['startindex'] ?? null,
    'count'              => $body['count'] ?? null,
    'attributes'         => $body['attributes'] ?? null,
    'excludedattributes' => $body['excludedattributes'] ?? null,
  ]);
}

function scim_get_user(string $scim_id, array $token): never {
  $row = scim_fetch_row_by_scim_id($scim_id, $token);
  if (!$row) {
    scim_error(404, 'User not found');
  }
  scim_respond(scim_project_from_query(scim_user_to_response($row)));
}

function scim_create_user(array $body, array $token): never {
  global $pdo;

  scim_require_schema($body, SCIM_URN_USER);

  $userName = trim((string) ($body['username'] ?? ''));
  $at = strrpos($userName, '@');
  if ($at === false || $at === 0 || $at === strlen($userName) - 1) {
    scim_error(400, 'userName must be a valid email address', 'invalidValue');
  }

  // Normalize exactly like mailbox('add') does (functions.mailbox.inc.php),
  // so the scim_maps row is byte-identical to the mailbox row and IDN
  // domains resolve against their stored punycode form
  $local_part = strtolower(substr($userName, 0, $at));
  $domain     = idn_to_ascii(strtolower(substr($userName, $at + 1)), 0, INTL_IDNA_VARIANT_UTS46);
  $username   = $local_part . '@' . $domain;
  if ($domain === false || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
    scim_error(400, 'userName must be a valid email address', 'invalidValue');
  }

  // Domain restriction check FIRST — a restricted token must not be able to
  // probe which other domains exist
  if (!empty($token['domain_restriction']) && $domain !== $token['domain_restriction']) {
    scim_error(403, "Token is restricted to domain '{$token['domain_restriction']}'");
  }

  // Validate domain exists
  $stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain` = :domain");
  $stmt->execute([':domain' => $domain]);
  if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    scim_error(400, "Domain '$domain' does not exist in mailcow", 'invalidValue');
  }

  // Duplicate check
  $stmt = $pdo->prepare("SELECT `username`, `authsource` FROM `mailbox` WHERE `username` = :username");
  $stmt->execute([':username' => $username]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);

  $external_id = isset($body['externalid']) && $body['externalid'] !== null ? (string) $body['externalid'] : null;

  if ($existing) {
    if ($existing['authsource'] !== 'scim') {
      scim_error(409,
        "User '$username' is managed by '{$existing['authsource']}'. " .
        "To transfer SCIM management, change the mailbox authsource to 'scim' in the mailcow admin panel first.",
        'uniqueness');
    }
    if (empty($token['allow_claim']) || intval($token['allow_claim']) !== 1) {
      scim_error(409, "User '$username' already exists", 'uniqueness');
    }

    // Claiming enabled for this token: adopt the pre-created mailbox
    if ($external_id !== null) {
      $stmt = $pdo->prepare("SELECT `username` FROM `scim_maps` WHERE `external_id` = :eid AND `username` != :username");
      $stmt->execute([':eid' => $external_id, ':username' => $username]);
      if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        scim_error(409, "externalId '$external_id' is already mapped to a different user", 'uniqueness');
      }
    }

    $name   = scim_resolve_name($body);
    $active = array_key_exists('active', $body) ? (int) scim_to_bool($body['active']) : 1;

    scim_setup_session();

    // Bring the adopted mailbox under template control, mirroring what the OIDC
    // login flow does for an existing mailbox (functions.inc.php). mailbox()
    // short-circuits when the template's attribute_hash and the name already
    // match, so repeat POSTs are a no-op. This runs BEFORE the name/active edit
    // below so the SCIM values stay authoritative over the template's own
    // name/active. Note this overwrites quota, ACLs and protocol access on the
    // existing mailbox with the template's values — claiming a mailbox means
    // handing it to the IdP's template.
    $resolved_template = scim_resolve_template($body, $token);
    if ($resolved_template !== null) {
      scim_mailbox_call('edit', 'mailbox_from_template', [
        'username' => $username,
        'name'     => $name,
        'template' => $resolved_template,
      ]);
    }

    scim_mailbox_call('edit', 'mailbox', [
      'username' => [$username],
      'name'     => $name,
      'active'   => $active,
    ]);

    // POST is the creation of the SCIM resource even when the mailbox predates
    // it, so the resolved template is recorded here the same way as on a native
    // create — otherwise the extension attribute would be absent from the
    // representation of an adopted user for no visible reason.
    $map = scim_ensure_map($username, (int) $token['id']);
    $stmt = $pdo->prepare("UPDATE `scim_maps` SET `template` = :tpl WHERE `username` = :username");
    $stmt->execute([':tpl' => $resolved_template, ':username' => $username]);
    if ($external_id !== null) {
      $stmt = $pdo->prepare("UPDATE `scim_maps` SET `external_id` = :eid, `token_id` = :tid WHERE `username` = :username");
      $stmt->execute([':eid' => $external_id, ':tid' => (int) $token['id'], ':username' => $username]);
    }

    scim_log('info', "Claimed existing mailbox '$username' via SCIM POST (token ID {$token['id']})");
    // The SCIM resource is created at this moment (identity row minted) —
    // RFC 7644 §3.3 requires 201 + Location even though the mailbox predated it
    $row = scim_fetch_row_by_scim_id($map['scim_id'], $token);
    $resource = scim_user_to_response($row);
    scim_respond(scim_project_from_query($resource), 201, ['Location: ' . $resource['meta']['location']]);
  }

  // externalId duplicate check (new user path)
  if ($external_id !== null) {
    $stmt = $pdo->prepare("SELECT `id` FROM `scim_maps` WHERE `external_id` = :eid");
    $stmt->execute([':eid' => $external_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
      scim_error(409, "externalId '$external_id' is already mapped to a user", 'uniqueness');
    }
  }

  $name   = scim_resolve_name($body);
  $active = array_key_exists('active', $body) ? (int) scim_to_bool($body['active']) : 1;

  scim_setup_session();

  $resolved_template = scim_resolve_template($body, $token);
  if ($resolved_template !== null) {
    scim_mailbox_call('add', 'mailbox_from_template', [
      'domain'     => $domain,
      'local_part' => $local_part,
      'name'       => $name,
      'authsource' => 'scim',
      'template'   => $resolved_template,
      'active'     => $active,
    ]);
  } else {
    scim_mailbox_call('add', 'mailbox', [
      'domain'     => $domain,
      'local_part' => $local_part,
      'name'       => $name,
      'authsource' => 'scim',
      'password'   => '',
      'password2'  => '',
      'active'     => $active,
    ]);
  }

  // Create the identity row
  $scim_id = scim_uuid_v4();
  $stmt = $pdo->prepare("INSERT INTO `scim_maps` (`scim_id`, `external_id`, `username`, `token_id`, `template`)
    VALUES (:sid, :eid, :username, :tid, :tpl)");
  $stmt->execute([
    ':sid'      => $scim_id,
    ':eid'      => $external_id,
    ':username' => $username,
    ':tid'      => (int) $token['id'],
    ':tpl'      => $resolved_template,
  ]);

  scim_log('info', "Created mailbox '$username' via SCIM (token ID {$token['id']})");

  $row = scim_fetch_row_by_scim_id($scim_id, $token);
  $resource = scim_user_to_response($row);
  scim_respond(scim_project_from_query($resource), 201, ['Location: ' . $resource['meta']['location']]);
}

function scim_replace_user(string $scim_id, array $body, array $token): never {
  global $pdo;

  $row = scim_fetch_row_by_scim_id($scim_id, $token);
  if (!$row) {
    scim_error(404, 'User not found');
  }

  scim_require_schema($body, SCIM_URN_USER);

  // userName is required and immutable (mailcow cannot rename mailboxes)
  $userName = trim((string) ($body['username'] ?? ''));
  if ($userName === '') {
    scim_error(400, 'userName is required', 'invalidValue');
  }
  if (strcasecmp($userName, $row['username']) !== 0) {
    scim_error(400, 'userName is immutable: mailcow cannot rename mailboxes', 'mutability');
  }

  // 'template' extension attribute is immutable — applied at creation only
  scim_template_immutable_check(scim_body_template_attr($body), 'replace', $row, $token);

  $name   = scim_resolve_name($body);
  $active = array_key_exists('active', $body) ? (int) scim_to_bool($body['active']) : 1;

  scim_setup_session();
  scim_mailbox_call('edit', 'mailbox', [
    'username' => [$row['username']],
    'name'     => $name,
    'active'   => $active,
  ]);

  // externalId: PUT is a full replace — omitted means cleared (RFC 7644 §3.5.1)
  $external_id = isset($body['externalid']) && $body['externalid'] !== null ? (string) $body['externalid'] : null;
  if ($external_id !== null) {
    $stmt = $pdo->prepare("SELECT `username` FROM `scim_maps` WHERE `external_id` = :eid AND `username` != :username");
    $stmt->execute([':eid' => $external_id, ':username' => $row['username']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
      scim_error(409, "externalId '$external_id' is already mapped to a different user", 'uniqueness');
    }
  }
  $stmt = $pdo->prepare("UPDATE `scim_maps` SET `external_id` = :eid WHERE `username` = :username");
  $stmt->execute([':eid' => $external_id, ':username' => $row['username']]);

  scim_log('info', "Replaced mailbox '{$row['username']}' via SCIM (token ID {$token['id']})");
  $fresh = scim_fetch_row_by_scim_id($scim_id, $token);
  scim_respond(scim_project_from_query(scim_user_to_response($fresh)));
}

// ─── PATCH ──────────────────────────────────────────────────────────────────

/**
 * Set both PATCH name components from a full display name (split on first space).
 * Tracking given/family separately (composed once at write time) keeps any op
 * ordering within one PATCH convergent — collapsing to a single string after
 * each op made "remove name, add familyName, add givenName" lose the family name.
 */
function scim_state_set_full(array &$state, string $full): void {
  $parts = explode(' ', trim($full), 2);
  $state['given']  = $parts[0] ?? '';
  $state['family'] = $parts[1] ?? '';
}

/**
 * RFC 7643 core User attributes mailcow does not store. They are accepted and
 * ignored on PATCH (mirroring POST/PUT tolerance) instead of 400 invalidPath —
 * default Entra/Okta attribute maps (jobTitle→title, phoneNumbers, …) would
 * otherwise create users successfully and then quarantine on every update.
 * 'password' and 'groups' are deliberately absent: silently ignoring those
 * would be worse than a visible error.
 */
function scim_ignored_attrs(): array {
  return [
    'title', 'nickname', 'profileurl', 'usertype', 'preferredlanguage',
    'locale', 'timezone', 'phonenumbers', 'ims', 'photos', 'addresses',
    'entitlements', 'roles', 'x509certificates',
  ];
}

/**
 * Enforce read-only semantics on emails: a modification whose result equals the
 * current derived value is a no-op; anything else is a mutability error.
 */
function scim_patch_check_emails(mixed $value, ?string $sub, string $op_name, array $row): void {
  if ($op_name === 'remove') {
    scim_error(400, "'emails' is read-only (derived from userName)", 'mutability');
  }
  $candidates = [];
  if ($sub === 'value' || is_string($value)) {
    $candidates[] = (string) $value;
  } elseif (is_array($value)) {
    $items = array_is_list($value) ? $value : [$value];
    foreach ($items as $item) {
      if (is_array($item)) {
        $v = scim_ci_get($item, 'value');
        if ($v !== null) {
          $candidates[] = (string) $v;
        }
      } elseif (is_string($item)) {
        $candidates[] = $item;
      }
    }
  }
  foreach ($candidates as $cand) {
    if (strcasecmp(trim($cand), $row['username']) !== 0) {
      scim_error(400, "'emails' is read-only (derived from userName); update userName is not supported as mailcow cannot rename mailboxes", 'mutability');
    }
  }
}

/**
 * Apply one PATCH operation given as a path-less value object (keys lowercased).
 */
function scim_patch_apply_object(array $obj, string $op_name, array &$state, array $row, array $token): void {
  foreach ($obj as $key => $value) {
    if (!is_string($key)) {
      scim_error(400, 'Invalid attribute in value object', 'invalidPath');
    }
    switch ($key) {
      case 'schemas':
        break;
      case 'id':
        if (!is_string($value) || strcasecmp($value, (string) $row['scim_id']) !== 0) {
          scim_error(400, "'id' is read-only", 'mutability');
        }
        break;
      case 'meta':
        // read-only, server-controlled — ignored
        break;
      case 'username':
        if (!is_string($value) || strcasecmp(trim($value), $row['username']) !== 0) {
          scim_error(400, 'userName is immutable: mailcow cannot rename mailboxes', 'mutability');
        }
        break;
      case 'displayname':
        scim_state_set_full($state, trim((string) $value));
        $state['name_dirty'] = true;
        break;
      case 'name':
        if (!is_array($value)) {
          scim_error(400, "'name' must be a complex object", 'invalidValue');
        }
        if (array_key_exists('formatted', $value)) {
          scim_state_set_full($state, trim((string) $value['formatted']));
          $state['name_dirty'] = true;
        }
        if (array_key_exists('givenname', $value)) {
          $state['given'] = trim((string) $value['givenname']);
          $state['name_dirty'] = true;
        }
        if (array_key_exists('familyname', $value)) {
          $state['family'] = trim((string) $value['familyname']);
          $state['name_dirty'] = true;
        }
        break;
      case 'name.formatted':
        scim_state_set_full($state, trim((string) $value));
        $state['name_dirty'] = true;
        break;
      case 'name.givenname':
        $state['given'] = trim((string) $value);
        $state['name_dirty'] = true;
        break;
      case 'name.familyname':
        $state['family'] = trim((string) $value);
        $state['name_dirty'] = true;
        break;
      case 'active':
        $state['active'] = (int) scim_to_bool($value);
        $state['active_dirty'] = true;
        break;
      case 'externalid':
        $state['external_id'] = $value === null ? null : (string) $value;
        $state['external_dirty'] = true;
        break;
      case 'emails':
        scim_patch_check_emails($value, null, $op_name, $row);
        break;
      default:
        if ($key === strtolower(SCIM_URN_EXT)) {
          if (!is_array($value)) {
            scim_error(400, 'Extension value must be a complex object', 'invalidValue');
          }
          $attr = isset($value['template']) && is_string($value['template']) ? trim($value['template']) : null;
          scim_template_immutable_check($attr, $op_name, $row, $token);
          break;
        }
        if ($key === strtolower(SCIM_URN_USER)) {
          if (!is_array($value)) {
            scim_error(400, 'Schema value must be a complex object', 'invalidValue');
          }
          scim_patch_apply_object($value, $op_name, $state, $row, $token);
          break;
        }
        if (in_array($key, scim_ignored_attrs(), true)) {
          break; // unstored-but-standard core attribute — accepted and ignored
        }
        // Entra ID sends path-less operations with flattened path-like keys,
        // e.g. {"emails[type eq \"work\"].value": "..."} — delegate to the
        // path parser (which 400s on genuinely unknown attributes)
        if (str_contains($key, '.') || str_contains($key, '[') || str_contains($key, ':')) {
          $parsed = scim_parse_path($key);
          if (!empty($parsed['ignored'])) {
            break;
          }
          scim_patch_apply_path($parsed, $value, $op_name, $state, $row, $token);
          break;
        }
        scim_error(400, "Unknown attribute '$key'", 'invalidPath');
    }
  }
}

/**
 * Apply one PATCH operation with an explicit path.
 */
function scim_patch_apply_path(array $parsed, mixed $value, string $op_name, array &$state, array $row, array $token): void {
  $spec   = $parsed['spec'];
  $filter = $parsed['filter'];
  $sub    = $parsed['sub'];

  if ($spec['urn'] === 'ext') {
    if ($spec['parts'] === ['template'] || ($spec['parts'] === [] && $sub === 'template')) {
      $attr = is_string($value) ? trim($value) : null;
      scim_template_immutable_check($attr, $op_name, $row, $token);
      return;
    }
    if ($spec['parts'] === []) {
      if ($op_name === 'remove') {
        scim_template_immutable_check(null, 'remove', $row, $token);
        return;
      }
      if (!is_array($value)) {
        scim_error(400, 'Extension value must be a complex object', 'invalidValue');
      }
      $attr = isset($value['template']) && is_string($value['template']) ? trim($value['template']) : null;
      scim_template_immutable_check($attr, $op_name, $row, $token);
      return;
    }
    scim_error(400, 'Unsupported extension path', 'invalidPath');
  }

  $base = $spec['parts'][0];
  $path_sub = $spec['parts'][1] ?? $sub;

  switch ($base) {
    case 'username':
      if ($op_name === 'remove') {
        scim_error(400, "userName is required and cannot be removed", 'mutability');
      }
      if (!is_string($value) || strcasecmp(trim($value), $row['username']) !== 0) {
        scim_error(400, 'userName is immutable: mailcow cannot rename mailboxes', 'mutability');
      }
      break;

    case 'displayname':
      scim_state_set_full($state, $op_name === 'remove' ? '' : trim((string) $value));
      $state['name_dirty'] = true;
      break;

    case 'name':
      if ($path_sub === null) {
        if ($op_name === 'remove') {
          $state['given'] = '';
          $state['family'] = '';
          $state['name_dirty'] = true;
          break;
        }
        if (!is_array($value)) {
          scim_error(400, "'name' must be a complex object", 'invalidValue');
        }
        scim_patch_apply_object(['name' => $value], $op_name, $state, $row, $token);
        break;
      }
      $val = $op_name === 'remove' ? '' : trim((string) $value);
      if ($path_sub === 'formatted') {
        scim_state_set_full($state, $val);
      } elseif ($path_sub === 'givenname') {
        $state['given'] = $val;
      } elseif ($path_sub === 'familyname') {
        $state['family'] = $val;
      }
      $state['name_dirty'] = true;
      break;

    case 'active':
      if ($op_name === 'remove') {
        scim_error(400, "'active' cannot be removed", 'invalidValue');
      }
      $state['active'] = (int) scim_to_bool($value);
      $state['active_dirty'] = true;
      break;

    case 'externalid':
      if ($op_name === 'remove') {
        $state['external_id'] = null;
      } else {
        $state['external_id'] = $value === null ? null : (string) $value;
      }
      $state['external_dirty'] = true;
      break;

    case 'emails':
      // $filter (if present) selects target items; since emails are derived
      // from userName, only no-op modifications are accepted regardless
      scim_patch_check_emails($value, $path_sub, $op_name, $row);
      break;

    case 'id':
      scim_error(400, "'id' is read-only", 'mutability');

    case 'meta':
      // read-only, server-controlled — ignored
      break;

    case 'schemas':
      break;

    default:
      scim_error(400, "Unsupported PATCH path", 'invalidPath');
  }
}

function scim_patch_user(string $scim_id, array $body, array $token): never {
  global $pdo;

  $row = scim_fetch_row_by_scim_id($scim_id, $token);
  if (!$row) {
    scim_error(404, 'User not found');
  }

  scim_require_schema($body, SCIM_URN_PATCHOP);

  $operations = $body['operations'] ?? null;
  if (!is_array($operations) || !array_is_list($operations) || count($operations) === 0) {
    scim_error(400, "'Operations' must be a non-empty array", 'invalidSyntax');
  }

  $name_parts = explode(' ', trim((string) ($row['name'] ?? '')), 2);
  $state = [
    'given'          => $name_parts[0] ?? '',
    'family'         => $name_parts[1] ?? '',
    'name_dirty'     => false,
    'active'         => (int) $row['active'],
    'active_dirty'   => false,
    'external_id'    => $row['external_id'],
    'external_dirty' => false,
  ];

  foreach ($operations as $op) {
    if (!is_array($op)) {
      scim_error(400, 'Each operation must be an object', 'invalidSyntax');
    }
    $op_name = strtolower((string) ($op['op'] ?? ''));
    if (!in_array($op_name, ['add', 'replace', 'remove'], true)) {
      scim_error(400, "Unsupported operation '{$op_name}'", 'invalidSyntax');
    }
    $path      = $op['path'] ?? null;
    $has_value = array_key_exists('value', $op);
    $value     = $op['value'] ?? null;

    if ($op_name !== 'remove' && !$has_value) {
      scim_error(400, "'value' is required for $op_name operations", 'invalidValue');
    }

    if ($path === null) {
      if ($op_name === 'remove') {
        scim_error(400, "'path' is required for remove operations", 'noTarget');
      }
      if (!is_array($value) || array_is_list($value)) {
        scim_error(400, "'value' must be an object when 'path' is omitted", 'invalidValue');
      }
      scim_patch_apply_object($value, $op_name, $state, $row, $token);
      continue;
    }

    if (!is_string($path) || trim($path) === '') {
      scim_error(400, "'path' must be a non-empty string", 'invalidPath');
    }
    $parsed = scim_parse_path($path);
    if (!empty($parsed['ignored'])) {
      continue; // unstored-but-standard core attribute — accepted and ignored
    }
    scim_patch_apply_path($parsed, $value, $op_name, $state, $row, $token);
  }

  // Validate externalId uniqueness BEFORE any write — RFC 7644 §3.5.2 requires
  // PATCH to be atomic; a 409 must not leave earlier operations applied
  if ($state['external_dirty'] && $state['external_id'] !== null) {
    $stmt = $pdo->prepare("SELECT `username` FROM `scim_maps` WHERE `external_id` = :eid AND `username` != :username");
    $stmt->execute([':eid' => $state['external_id'], ':username' => $row['username']]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
      scim_error(409, "externalId '{$state['external_id']}' is already mapped to a different user", 'uniqueness');
    }
  }

  $update = [];
  if ($state['name_dirty']) {
    $new_name = trim($state['given'] . ' ' . $state['family']);
    if ($new_name === '') {
      // mailbox() treats an empty name as "keep current" — fall back to the
      // local part (mirrors creation defaults) so clears are not silent no-ops
      $new_name = strstr($row['username'], '@', true) ?: $row['username'];
    }
    $update['name'] = $new_name;
  }
  if ($state['active_dirty']) {
    $update['active'] = $state['active'];
  }
  if (!empty($update)) {
    scim_setup_session();
    scim_mailbox_call('edit', 'mailbox', array_merge(['username' => [$row['username']]], $update));
  }

  if ($state['external_dirty']) {
    $stmt = $pdo->prepare("UPDATE `scim_maps` SET `external_id` = :eid WHERE `username` = :username");
    $stmt->execute([':eid' => $state['external_id'], ':username' => $row['username']]);
  }

  scim_log('info', "Patched mailbox '{$row['username']}' via SCIM (token ID {$token['id']})");
  $fresh = scim_fetch_row_by_scim_id($scim_id, $token);
  scim_respond(scim_project_from_query(scim_user_to_response($fresh)));
}

function scim_delete_user(string $scim_id, array $token): never {
  $row = scim_fetch_row_by_scim_id($scim_id, $token);
  if (!$row) {
    scim_error(404, 'User not found');
  }

  scim_setup_session();
  // Deliberate deviation from RFC 7644 §3.6: DELETE deactivates the mailbox
  // instead of destroying it, to preserve mail data. The resource remains
  // retrievable with active=false.
  scim_mailbox_call('edit', 'mailbox', [
    'username' => [$row['username']],
    'active'   => 0,
  ]);

  // scim_maps row intentionally kept for audit trail

  scim_log('info', "Deactivated mailbox '{$row['username']}' via SCIM DELETE (token ID {$token['id']})");
  scim_respond(null, 204);
}
