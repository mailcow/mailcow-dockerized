<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

header('Content-Type: application/json');

// Admins only
if (!isset($_SESSION['mailcow_cc_role']) || $_SESSION['mailcow_cc_role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(array('status' => 'error', 'message' => 'access denied'));
  exit;
}

$owner   = $GLOBALS['MAILCOW_GIT_OWNER'];
$repo    = $GLOBALS['MAILCOW_GIT_REPO'];
$current = $GLOBALS['MAILCOW_GIT_VERSION'];

// Cache key is bound to the running version, so an update invalidates it naturally
$cache_key = 'MAILCOW_UPDATE_CHECK/' . $current;

// Serve cached result if present (one GitHub call per TTL, not one per page load)
$cached = $redis->get($cache_key);
if ($cached !== false) {
  echo $cached;
  exit;
}

// GitHub requires a User-Agent and rate-limits unauthenticated requests to 60/h per IP
function github_get($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => 'mailcow-update-check',
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => array('Accept: application/vnd.github+json'),
  ));
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($body === false || $code !== 200) return false;
  $json = json_decode($body, true);
  return is_array($json) ? $json : false;
}

$latest  = github_get("https://api.github.com/repos/{$owner}/{$repo}/releases/latest");
$release = github_get("https://api.github.com/repos/{$owner}/{$repo}/releases/tags/{$current}");

// Any failure (rate limit, offline, unexpected payload) -> honest error, cached briefly
if ($latest === false || $release === false ||
    empty($latest['tag_name']) || empty($latest['created_at']) || empty($release['created_at'])) {
  $result = json_encode(array('status' => 'error', 'message' => 'could not reach GitHub API'));
  $redis->setex($cache_key, 300, $result);
  echo $result;
  exit;
}

$date_latest  = strtotime($latest['created_at']);
$date_current = strtotime($release['created_at']);

if ($date_latest <= $date_current) {
  $result = json_encode(array('status' => 'no_update'));
} else {
  $result = json_encode(array('status' => 'update_available', 'latest_tag' => $latest['tag_name']));
}

// Cache the positive result for an hour
$redis->setex($cache_key, 3600, $result);
echo $result;
