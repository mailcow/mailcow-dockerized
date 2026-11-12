<?php
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
// Set CORS headers for browser extensions & web vaults
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Authorization, Authentication, Content-Type, Origin, X-Api-Key, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit();
}


// Authentication
$authenticated = false;
$username = null;

if (!empty($_SESSION['mailcow_cc_username']) && !empty($_SESSION['mailcow_cc_role'])) {
  $authenticated = true;
  $username = $_SESSION['mailcow_cc_username'];
} else {
  // Direct token check if session not set
  $req_token = '';
  if (!empty($_SERVER['HTTP_AUTHENTICATION'])) {
    $req_token = trim($_SERVER['HTTP_AUTHENTICATION']);
  } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
      $req_token = $m[1];
    } else {
      $req_token = trim($_SERVER['HTTP_AUTHORIZATION']);
    }
  } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $req_token = trim($_SERVER['HTTP_X_API_KEY']);
  }
  $clean_token = preg_replace('/[^a-zA-Z0-9-]/', '', $req_token);

  if (!empty($clean_token)) {
    try {
      $stmt = $pdo->prepare("SELECT * FROM `user_api` WHERE `api_key` = :api_key AND `active` = '1' LIMIT 1;");
      $stmt->execute(array(':api_key' => $clean_token));
      $uapi = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!empty($uapi['username'])) {
        $skip_ip = ((int)$uapi['skip_ip_check'] === 1);
        $remote = get_remote_ip(false);
        $allow_from = array_map('trim', preg_split("/( |,|;|\n)/", $uapi['allow_from']));
        if ($skip_ip || ip_acl($remote, $allow_from)) {
          $authenticated = true;
          $username = $uapi['username'];
          $_SESSION['mailcow_cc_username'] = $username;
          $_SESSION['mailcow_cc_role'] = 'user';
          $_SESSION['mailcow_cc_api'] = true;
          $_SESSION['mailcow_cc_api_access'] = 'rw';
          acl('to_session');
        }
      }
    } catch (PDOException $e) {
      // Ignore
    }

    // Fallback: check admin API key
    if (!$authenticated) {
      try {
        $stmt = $pdo->prepare("SELECT * FROM `api` WHERE `api_key` = :api_key AND `active` = '1' LIMIT 1;");
        $stmt->execute(array(':api_key' => $clean_token));
        $aapi = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($aapi['api_key'])) {
          $skip_ip = ((int)$aapi['skip_ip_check'] === 1);
          $remote = get_remote_ip(false);
          $allow_from = array_map('trim', preg_split("/( |,|;|\n)/", $aapi['allow_from']));
          if ($skip_ip || ip_acl($remote, $allow_from)) {
            $authenticated = true;
            $username = 'API';
            $_SESSION['mailcow_cc_username'] = 'API';
            $_SESSION['mailcow_cc_role'] = 'admin';
            $_SESSION['mailcow_cc_api'] = true;
            $_SESSION['mailcow_cc_api_access'] = ($aapi['access'] == 'ro') ? 'ro' : 'rw';
            acl('to_session');
          }
        }
      } catch (PDOException $e) {
        // Ignore
      }
    }
  }
}

if (!$authenticated || empty($username)) {
  http_response_code(401);
  echo json_encode(array('error' => 'Invalid API key'));
  exit();
}

// Parse request route
$raw_uri = $_GET['query'] ?? $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($raw_uri, PHP_URL_PATH);
$route = preg_replace('#^/api/(v[0-9]+/)?#', '', $path);
$route = trim($route, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Parse input body/params
$input_raw = file_get_contents('php://input');
$json_data = json_decode($input_raw, true);
if (!is_array($json_data)) {
  $json_data = array();
}
$params = array_merge($_GET, $json_data);

// Resolve target mailbox user if authenticated as admin
if ($username === 'API') {
  if (!empty($params['mailbox']) && filter_var($params['mailbox'], FILTER_VALIDATE_EMAIL)) {
    $username = $params['mailbox'];
  } elseif (!empty($params['username']) && filter_var($params['username'], FILTER_VALIDATE_EMAIL)) {
    $username = $params['username'];
  } else {
    // Pick first active mailbox
    $stmt = $pdo->query("SELECT `username` FROM `mailbox` WHERE `active` = '1' ORDER BY `created` ASC LIMIT 1;");
    $first_mbox = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($first_mbox['username'])) {
      $username = $first_mbox['username'];
    }
  }
}

// Helper: Get user's valid domains
$get_user_domains = function($user) use ($pdo) {
  $domains = array();
  $mbox = mailbox('get', 'mailbox_details', $user);
  if (!empty($mbox['domain'])) {
    $domains[] = $mbox['domain'];
  }
  $alias_details = user_get_alias_details($user);
  if (!empty($alias_details['alias_domains'])) {
    $domains = array_merge($domains, $alias_details['alias_domains']);
  }
  return array_values(array_unique($domains));
};

// Route matching
if ($route === 'user_info' && $method === 'GET') {
  $mbox_details = mailbox('get', 'mailbox_details', $username);
  $display_name = (!empty($mbox_details['name'])) ? $mbox_details['name'] : $username;
  echo json_encode(array(
    'name' => $display_name,
    'email' => $username,
    'is_premium' => true,
    'in_trial' => false,
    'trial_end_timestamp' => null,
    'profile_picture_url' => null,
    'max_alias_free_plan' => null
  ));
  exit();
}

if (($route === 'alias/options' || $route === 'options') && $method === 'GET') {
  $domains = $get_user_domains($username);
  $suffixes = array();
  foreach ($domains as $d) {
    $suffixes[] = array(
      'suffix' => '@' . $d,
      'signed_suffix' => '@' . $d,
      'is_custom' => false,
      'is_premium' => false
    );
  }
  $prefix_parts = explode('@', $username);
  $prefix_suggestion = $prefix_parts[0];
  echo json_encode(array(
    'can_create' => true,
    'prefix_suggestion' => $prefix_suggestion,
    'suffixes' => $suffixes
  ));
  exit();
}

if ($route === 'alias/random/new' && $method === 'POST') {
  $domains = $get_user_domains($username);
  if (empty($domains)) {
    http_response_code(400);
    echo json_encode(array('error' => 'No active domains available for user'));
    exit();
  }

  $domain = $domains[0];
  if (!empty($params['domain']) && in_array($params['domain'], $domains)) {
    $domain = $params['domain'];
  }

  // Generate prefix
  $hostname = $params['hostname'] ?? '';
  $mode = $params['mode'] ?? '';

  $prefix = '';
  if (!empty($hostname)) {
    $host_slug = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $hostname)[0]));
    if (strlen($host_slug) >= 2) {
      $prefix = $host_slug . '.' . readable_random_string(6);
    }
  }

  if (empty($prefix)) {
    if ($mode === 'uuid') {
      $prefix = bin2hex(random_bytes(6));
    } else {
      $prefix = readable_random_string(rand(4, 7)) . '.' . readable_random_string(rand(4, 7));
    }
  }

  $address = strtolower($prefix . '@' . $domain);

  // Ensure unique address
  $retry = 0;
  while ($retry < 5) {
    $stmt = $pdo->prepare("SELECT 1 FROM `spamalias` WHERE `address` = :addr UNION SELECT 1 FROM `alias` WHERE `address` = :addr UNION SELECT 1 FROM `mailbox` WHERE `username` = :addr;");
    $stmt->execute(array(':addr' => $address));
    if (!$stmt->fetch()) {
      break;
    }
    $address = strtolower($prefix . '.' . readable_random_string(4) . '@' . $domain);
    $retry++;
  }

  $description = $params['note'] ?? '';
  if (empty($description) && !empty($hostname)) {
    $description = 'Generated for ' . $hostname;
  }
  if (empty($description)) {
    $description = 'Generated via SimpleLogin API';
  }

  $permanent = 1;
  $validity = 0;
  if (isset($params['validity']) && filter_var($params['validity'], FILTER_VALIDATE_INT)) {
    $validity_hours = intval($params['validity']);
    $validity = time() + ($validity_hours * 3600);
    $permanent = 0;
  } elseif (isset($params['permanent']) && !filter_var($params['permanent'], FILTER_VALIDATE_BOOL)) {
    $permanent = 0;
    $validity = time() + (8760 * 3600); // 1 year default
  }

  $stmt = $pdo->prepare("INSERT INTO `spamalias` (`address`, `description`, `goto`, `validity`, `permanent`) VALUES (:address, :description, :goto, :validity, :permanent)");
  $stmt->execute(array(
    ':address' => $address,
    ':description' => $description,
    ':goto' => $username,
    ':validity' => $validity,
    ':permanent' => $permanent
  ));

  http_response_code(201);
  echo json_encode(format_simplelogin_alias($address, $description, $username, $validity, $permanent, time()));
  exit();
}

if ($route === 'alias/custom/new' && $method === 'POST') {
  $prefix = trim($params['alias_prefix'] ?? '');
  $signed_suffix = trim($params['signed_suffix'] ?? '');
  $suffix_domain = ltrim($signed_suffix, '@');

  $domains = $get_user_domains($username);
  if (!in_array($suffix_domain, $domains)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid domain suffix'));
    exit();
  }

  if (!preg_match('/^[a-zA-Z0-9._+-]+$/', $prefix)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid alias prefix format'));
    exit();
  }

  $address = strtolower($prefix . '@' . $suffix_domain);

  // Check collision
  $stmt = $pdo->prepare("SELECT 1 FROM `spamalias` WHERE `address` = :addr UNION SELECT 1 FROM `alias` WHERE `address` = :addr UNION SELECT 1 FROM `mailbox` WHERE `username` = :addr;");
  $stmt->execute(array(':addr' => $address));
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(array('error' => 'Alias already exists'));
    exit();
  }

  $description = $params['note'] ?? '';
  if (empty($description)) {
    $description = 'Custom alias generated via SimpleLogin API';
  }

  $permanent = 1;
  $validity = 0;
  if (isset($params['validity']) && filter_var($params['validity'], FILTER_VALIDATE_INT)) {
    $validity_hours = intval($params['validity']);
    $validity = time() + ($validity_hours * 3600);
    $permanent = 0;
  }

  $stmt = $pdo->prepare("INSERT INTO `spamalias` (`address`, `description`, `goto`, `validity`, `permanent`) VALUES (:address, :description, :goto, :validity, :permanent)");
  $stmt->execute(array(
    ':address' => $address,
    ':description' => $description,
    ':goto' => $username,
    ':validity' => $validity,
    ':permanent' => $permanent
  ));

  http_response_code(201);
  echo json_encode(format_simplelogin_alias($address, $description, $username, $validity, $permanent, time()));
  exit();
}

if ($route === 'aliases' && ($method === 'GET' || $method === 'POST')) {
  $page_id = isset($params['page_id']) ? max(0, intval($params['page_id'])) : 0;
  $offset = $page_id * 20;

  $stmt = $pdo->prepare("SELECT * FROM `spamalias` WHERE `goto` = :goto ORDER BY `created` DESC LIMIT 20 OFFSET :offset;");
  $stmt->bindValue(':goto', $username, PDO::PARAM_STR);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $aliases = array();
  foreach ($rows as $row) {
    $aliases[] = format_simplelogin_alias(
      $row['address'],
      $row['description'],
      $row['goto'],
      $row['validity'],
      $row['permanent'],
      $row['created']
    );
  }

  echo json_encode(array('aliases' => $aliases));
  exit();
}

// Toggle alias: /api/aliases/{id}/toggle
if (preg_match('#^aliases/([^/]+)/toggle$#', $route, $matches) && $method === 'POST') {
  $alias_identifier = $matches[1];

  $stmt = $pdo->prepare("SELECT * FROM `spamalias` WHERE `goto` = :goto AND (CRC32(`address`) = :id_num OR `address` = :id_str) LIMIT 1;");
  $stmt->execute(array(
    ':goto' => $username,
    ':id_num' => is_numeric($alias_identifier) ? $alias_identifier : -1,
    ':id_str' => $alias_identifier
  ));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(array('error' => 'alias not found'));
    exit();
  }

  $is_currently_enabled = ($row['permanent'] != 0 || (int)$row['validity'] >= time());
  $new_status = !$is_currently_enabled;

  if ($new_status) {
    // Re-enable as permanent
    $stmt = $pdo->prepare("UPDATE `spamalias` SET `permanent` = 1, `validity` = 0 WHERE `address` = :addr;");
    $stmt->execute(array(':addr' => $row['address']));
  } else {
    // Disable by setting permanent to 0 and validity to the past
    $stmt = $pdo->prepare("UPDATE `spamalias` SET `permanent` = 0, `validity` = :past WHERE `address` = :addr;");
    $stmt->execute(array(
      ':past' => time() - 3600,
      ':addr' => $row['address']
    ));
  }

  echo json_encode(array('enabled' => $new_status));
  exit();
}

// Delete alias: /api/aliases/{id}
if (preg_match('#^aliases/([^/]+)$#', $route, $matches) && $method === 'DELETE') {
  $alias_identifier = $matches[1];

  $stmt = $pdo->prepare("SELECT `address` FROM `spamalias` WHERE `goto` = :goto AND (CRC32(`address`) = :id_num OR `address` = :id_str) LIMIT 1;");
  $stmt->execute(array(
    ':goto' => $username,
    ':id_num' => is_numeric($alias_identifier) ? $alias_identifier : -1,
    ':id_str' => $alias_identifier
  ));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    echo json_encode(array('error' => 'alias not found'));
    exit();
  }

  $stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `address` = :addr AND `goto` = :goto;");
  $stmt->execute(array(
    ':addr' => $row['address'],
    ':goto' => $username
  ));

  echo json_encode(array('deleted' => true));
  exit();
}

// Unhandled route
http_response_code(404);
echo json_encode(array('error' => 'Route not found: ' . $route));
exit();
