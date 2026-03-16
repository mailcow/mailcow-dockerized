<?php
/**
 * SimpleLogin-Compatible API for Mailcow
 * Implements SimpleLogin API endpoints for automated alias creation
 * 
 * Endpoints:
 * - POST /api/v1/alias/random/new - Create a random alias
 * - POST /api/v1/alias/custom/new - Create a custom alias
 * - GET /api/v1/alias/{alias_id} - Get alias information
 * - DELETE /api/v1/alias/{alias_id} - Delete an alias
 * - GET /api/v1/domains - Get available domains
 * 
 * @author Bounty Hunter
 * @license GNU General Public License v3.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

header('Content-Type: application/json');
error_reporting(0);

// SimpleLogger for API
function sl_api_log($message) {
    error_log('[SimpleLoginAPI] ' . $message . PHP_EOL);
}

// Response helper
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Error response helper
function error_response($error, $message, $status_code = 400) {
    json_response([
        'error' => $error,
        'message' => $message
    ], $status_code);
}

// Authentication check
function authenticate_request() {
    global $redis;
    
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : 
               (isset($headers['x-api-key']) ? $headers['x-api-key'] : null);
    
    if (empty($api_key)) {
        error_response('authentication_failed', 'Missing API key', 401);
    }
    
    // Validate API key against mailcow database
    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT * FROM `api` WHERE `api_key` = ? AND `active` = 1');
        $stmt->execute([$api_key]);
        $api_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$api_data) {
            error_response('authentication_failed', 'Invalid API key', 401);
        }
        
        // Get user info from API key
        $stmt = $GLOBALS['pdo']->prepare('SELECT `username`, `domain` FROM `mailbox` WHERE `username` = ?');
        $stmt->execute([$api_data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_response('authentication_failed', 'User not found', 401);
        }
        
        return $user;
        
    } catch (PDOException $e) {
        sl_api_log('Database error: ' . $e->getMessage());
        error_response('server_error', 'Internal server error', 500);
    }
}

// Parse request
$request_method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Route handling
if (count($path_parts) < 4 || $path_parts[0] !== 'api' || $path_parts[1] !== 'v1') {
    error_response('not_found', 'Endpoint not found', 404);
}

$endpoint = isset($path_parts[2]) ? $path_parts[2] : null;
$action = isset($path_parts[3]) ? $path_parts[3] : null;
$resource_id = isset($path_parts[4]) ? $path_parts[4] : null;

// Authenticate for all endpoints
$user = authenticate_request();

// Main router
try {
    switch ($endpoint) {
        case 'alias':
            handle_alias_endpoint($request_method, $action, $resource_id, $user);
            break;
            
        case 'domains':
            handle_domains_endpoint($request_method, $user);
            break;
            
        default:
            error_response('not_found', 'Endpoint not found', 404);
    }
} catch (Exception $e) {
    sl_api_log('Unhandled exception: ' . $e->getMessage());
    error_response('server_error', 'Internal server error', 500);
}

/**
 * Handle alias endpoints
 */
function handle_alias_endpoint($method, $action, $alias_id, $user) {
    global $pdo;
    
    if ($method === 'POST') {
        // Create alias
        if ($action === 'random') {
            create_random_alias($user);
        } elseif ($action === 'custom') {
            create_custom_alias($user);
        } else {
            error_response('bad_request', 'Invalid action', 400);
        }
    } elseif ($method === 'GET' && !empty($alias_id)) {
        // Get alias info
        get_alias_info($alias_id, $user);
    } elseif ($method === 'DELETE' && !empty($alias_id)) {
        // Delete alias
        delete_alias($alias_id, $user);
    } else {
        error_response('bad_request', 'Invalid request', 400);
    }
}

/**
 * Create a random alias (SimpleLogin compatible)
 * POST /api/v1/alias/random/new
 */
function create_random_alias($user) {
    global $pdo;
    
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    $note = isset($input['note']) ? $input['note'] : '';
    $name = isset($input['name']) ? $input['name'] : '';
    $hosted = isset($input['hosted']) ? filter_var($input['hosted'], FILTER_VALIDATE_BOOLEAN) : true;
    
    // Generate random alias suffix
    $alias_suffix = generate_random_string(8);
    
    // Get user's domain or default domain
    $stmt = $pdo->prepare('SELECT `domain` FROM `domain` WHERE `domain` = ? AND `active` = 1 LIMIT 1');
    $stmt->execute([$user['domain']]);
    $domain_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$domain_data) {
        // Use system default domain
        $stmt = $pdo->query('SELECT `domain` FROM `domain` WHERE `active` = 1 LIMIT 1');
        $domain_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$domain_data) {
        error_response('no_domain', 'No active domain available', 400);
    }
    
    $domain = $domain_data['domain'];
    $alias_address = $alias_suffix . '@' . $domain;
    
    // Check if alias already exists
    $stmt = $pdo->prepare('SELECT `address` FROM `alias` WHERE `address` = ?');
    $stmt->execute([$alias_address]);
    if ($stmt->fetch()) {
        error_response('alias_exists', 'Alias already exists', 409);
    }
    
    // Create alias
    $goto = $user['username'];
    $stmt = $pdo->prepare('INSERT INTO `alias` (`address`, `goto`, `domain`, `active`, `created`, `modified`) VALUES (?, ?, ?, 1, NOW(), NOW())');
    $result = $stmt->execute([$alias_address, $goto, $domain]);
    
    if (!$result) {
        error_response('creation_failed', 'Failed to create alias', 500);
    }
    
    // Log creation
    sl_api_log("Created random alias: $alias_address -> $goto");
    
    // SimpleLogin compatible response
    json_response([
        'id' => base64_encode($alias_address),
        'email' => $alias_address,
        'creation_date' => date('Y-m-d H:i:s\Z'),
        'nb_block' => 0,
        'nb_forward' => 0,
        'nb_reply' => 0,
        'enabled' => true,
        'note' => $note,
        'name' => $name,
        'pgp_enabled' => false,
        'pgp_verified' => false,
        'pinned' => false,
        'custom' => false
    ]);
}

/**
 * Create a custom alias (SimpleLogin compatible)
 * POST /api/v1/alias/custom/new
 */
function create_custom_alias($user) {
    global $pdo;
    
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['alias_prefix'])) {
        error_response('missing_parameter', 'alias_prefix is required', 400);
    }
    
    $alias_prefix = preg_replace('/[^a-zA-Z0-9._-]/', '', $input['alias_prefix']);
    $note = isset($input['note']) ? $input['note'] : '';
    $name = isset($input['name']) ? $input['name'] : '';
    $mailbox_id = isset($input['mailbox_id']) ? $input['mailbox_id'] : null;
    
    // Determine target domain
    $domain = isset($input['signed_suffix']) ? parseDomainFromSignedSuffix($input['signed_suffix']) : null;
    
    if (!$domain) {
        // Use user's domain
        $stmt = $pdo->prepare('SELECT `domain` FROM `domain` WHERE `domain` = ? AND `active` = 1 LIMIT 1');
        $stmt->execute([$user['domain']]);
        $domain_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$domain_data) {
            $stmt = $pdo->query('SELECT `domain` FROM `domain` WHERE `active` = 1 LIMIT 1');
            $domain_data = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$domain_data) {
            error_response('no_domain', 'No active domain available', 400);
        }
        
        $domain = $domain_data['domain'];
    }
    
    // Validate alias prefix
    if (strlen($alias_prefix) < 1 || strlen($alias_prefix) > 64) {
        error_response('invalid_alias', 'Alias prefix must be 1-64 characters', 400);
    }
    
    $alias_address = $alias_prefix . '@' . $domain;
    
    // Check if alias already exists
    $stmt = $pdo->prepare('SELECT `address` FROM `alias` WHERE `address` = ?');
    $stmt->execute([$alias_address]);
    if ($stmt->fetch()) {
        error_response('alias_exists', 'Alias already exists', 409);
    }
    
    // Determine goto address
    $goto = isset($mailbox_id) ? $mailbox_id : $user['username'];
    
    // Create alias
    $stmt = $pdo->prepare('INSERT INTO `alias` (`address`, `goto`, `domain`, `active`, `created`, `modified`) VALUES (?, ?, ?, 1, NOW(), NOW())');
    $result = $stmt->execute([$alias_address, $goto, $domain]);
    
    if (!$result) {
        error_response('creation_failed', 'Failed to create alias', 500);
    }
    
    // Log creation
    sl_api_log("Created custom alias: $alias_address -> $goto");
    
    // SimpleLogin compatible response
    json_response([
        'id' => base64_encode($alias_address),
        'email' => $alias_address,
        'creation_date' => date('Y-m-d H:i:s\Z'),
        'nb_block' => 0,
        'nb_forward' => 0,
        'nb_reply' => 0,
        'enabled' => true,
        'note' => $note,
        'name' => $name,
        'pgp_enabled' => false,
        'pgp_verified' => false,
        'pinned' => false,
        'custom' => true
    ]);
}

/**
 * Get alias information (SimpleLogin compatible)
 * GET /api/v1/alias/{alias_id}
 */
function get_alias_info($alias_id, $user) {
    global $pdo;
    
    // Decode alias_id (base64 encoded email)
    $alias_address = base64_decode($alias_id);
    
    if (!$alias_address) {
        error_response('invalid_id', 'Invalid alias ID', 400);
    }
    
    // Get alias from database
    $stmt = $pdo->prepare('SELECT * FROM `alias` WHERE `address` = ? AND (`goto` = ? OR `goto` LIKE ?)');
    $stmt->execute([$alias_address, $user['username'], '%' . $user['username'] . '%']);
    $alias = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alias) {
        error_response('not_found', 'Alias not found', 404);
    }
    
    // Get alias statistics
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM `sasl_logs` WHERE `rcpt` = ?');
    $stmt->execute([$alias_address]);
    $forward_count = $stmt->fetchColumn() ?: 0;
    
    // SimpleLogin compatible response
    json_response([
        'id' => $alias_id,
        'email' => $alias['address'],
        'creation_date' => $alias['created'],
        'nb_block' => 0,
        'nb_forward' => $forward_count,
        'nb_reply' => 0,
        'enabled' => (bool)$alias['active'],
        'note' => '',
        'name' => '',
        'pgp_enabled' => false,
        'pgp_verified' => false,
        'pinned' => false,
        'custom' => true
    ]);
}

/**
 * Delete alias (SimpleLogin compatible)
 * DELETE /api/v1/alias/{alias_id}
 */
function delete_alias($alias_id, $user) {
    global $pdo;
    
    // Decode alias_id
    $alias_address = base64_decode($alias_id);
    
    if (!$alias_address) {
        error_response('invalid_id', 'Invalid alias ID', 400);
    }
    
    // Check permissions
    $stmt = $pdo->prepare('SELECT * FROM `alias` WHERE `address` = ? AND (`goto` = ? OR `goto` LIKE ?)');
    $stmt->execute([$alias_address, $user['username'], '%' . $user['username'] . '%']);
    $alias = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alias) {
        error_response('not_found', 'Alias not found', 404);
    }
    
    // Delete alias
    $stmt = $pdo->prepare('DELETE FROM `alias` WHERE `address` = ?');
    $result = $stmt->execute([$alias_address]);
    
    if (!$result) {
        error_response('deletion_failed', 'Failed to delete alias', 500);
    }
    
    // Log deletion
    sl_api_log("Deleted alias: $alias_address");
    
    json_response(['message' => 'Alias deleted successfully']);
}

/**
 * Handle domains endpoints
 * GET /api/v1/domains
 */
function handle_domains_endpoint($method, $user) {
    global $pdo;
    
    if ($method !== 'GET') {
        error_response('method_not_allowed', 'Method not allowed', 405);
    }
    
    // Get all active domains
    $stmt = $pdo->query('SELECT `domain` FROM `domain` WHERE `active` = 1');
    $domains = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // SimpleLogin compatible response
    $response = [];
    foreach ($domains as $domain) {
        $response[] = [
            'domain_name' => $domain,
            'is_custom_domain' => ($domain === $user['domain']),
            'nb_aliases' => 0,
            'nb_sent' => 0
        ];
    }
    
    json_response(['domains' => $response]);
}

/**
 * Helper function to generate random string
 */
function generate_random_string($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $random_string = '';
    $char_length = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[mt_rand(0, $char_length)];
    }
    
    return $random_string;
}

/**
 * Helper function to parse domain from signed suffix
 */
function parseDomainFromSignedSuffix($signed_suffix) {
    // Simple implementation - extract domain from suffix
    // Format: @domain.com.signature
    if (preg_match('/@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $signed_suffix, $matches)) {
        return $matches[1];
    }
    return null;
}
