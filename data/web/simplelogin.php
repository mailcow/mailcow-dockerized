<?php

declare(strict_types=1);

/*
 * Minimal SimpleLogin-compatible alias endpoint for Bitwarden/Vaultwarden.
 *
 * The forwarder only needs one call: POST /api/alias/random/new with an `Authentication`
 * header. The value is `mailbox@example.org:app-password`; no global mailcow API key and no
 * second credential store are involved. A successful response is `{"alias": "..."}`.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

use Mailcow\SimpleLogin\SimpleLoginException;
use Mailcow\SimpleLogin\SimpleLoginService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authentication, Authorization, Content-Type, X-API-Key');
header('Access-Control-Max-Age: 600');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['error' => 'method_not_allowed', 'message' => 'Use POST.']);
    exit;
}

$apiKey = $_SERVER['HTTP_AUTHENTICATION']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['HTTP_X_API_KEY']
    ?? '';

$rawBody = file_get_contents('php://input');
$payload = [];
if ($rawBody !== false && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json', 'message' => 'The request body must be a JSON object.']);
        exit;
    }
    $payload = $decoded;
}

try {
    $service = new SimpleLoginService($pdo);
    $alias = $service->createAlias($apiKey, $payload['domain'] ?? null, $payload['note'] ?? null);

    if (getenv('SKIP_SOGO') !== 'y' && function_exists('update_sogo_static_view')) {
        try {
            update_sogo_static_view($alias['mailbox']);
        } catch (Throwable $e) {
            // The alias exists already; a stale SOGo view must not turn the creation into a failure.
            error_log('SimpleLogin alias created, SOGo view update failed: ' . $e->getMessage());
        }
    }

    http_response_code(201);
    echo json_encode([
        'alias' => $alias['alias'],
        'note' => $alias['note'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
} catch (SimpleLoginException $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->error, 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('SimpleLogin alias creation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal_error', 'message' => 'The alias could not be created.']);
    exit;
}
