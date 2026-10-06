<?php
// Serves the UI background uploaded in Admin > Configuration > Customize.
// The URL contains the image hash (?v=...), so the response can be cached forever.
// No prerequisites.inc.php on purpose: no session, no database, just Redis.

$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  }
  else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv("REDISPASS"));
  $data = $redis->get('UI_BACKGROUND_IMAGE');
}
catch (Exception $e) {
  http_response_code(500);
  exit;
}

$prefix = 'data:image/webp;base64,';
if (empty($data) || strpos($data, $prefix) !== 0) {
  http_response_code(404);
  exit;
}

$etag = '"' . sha1($data) . '"';
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
  http_response_code(304);
  exit;
}

$blob = base64_decode(substr($data, strlen($prefix)));
header('Content-Type: image/webp');
header('Content-Length: ' . strlen($blob));
echo $blob;
