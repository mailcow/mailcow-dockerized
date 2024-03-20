<?php
  
require_once 'inc/prerequisites.inc.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';

/**
 * Check if a given ip is in a network
 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
 * @return boolean true if the ip is in this range / false if not.
 * https://gist.github.com/tott/7684443
 */
function ip_in_range( $ip, $range ) {
	if ( strpos( $range, '/' ) == false ) {
		$range .= '/32';
	}
	// $range is in IP/CIDR format eg 127.0.0.1/24
	list( $range, $netmask ) = explode( '/', $range, 2 );
	$range_decimal = ip2long( $range );
	$ip_decimal = ip2long( $ip );
	$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
	$netmask_decimal = ~ $wildcard_decimal;
	return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

// Redis
$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  }
  else {
    $redis->connect('redis-mailcow', 6379);
  }
}
catch (Exception $e) {
  exit;
}

// check if IP is banned
$ip = $_SERVER['REMOTE_ADDR'];
$bans = $redis->hkeys('F2B_ACTIVE_BANS');

$banned = false;
foreach($bans as $ban) {
  if (ip_in_range( $ip, $ban)) {
    $banned = $ban;
    break;
  }
}

if(!$banned) {
  header('Location: /');
  exit();
}

function check(){
  // Check if the honeypot field is filled
  if (!empty($_POST['hp'])) {
      return false;
  }
  
  // Validate the cryptographic token
  if (!hash_equals($_SESSION['token'], $_POST['token'])) {
      return false;
  }
  
  // Verify the timing
  $startTime = $_SESSION['startTime'];
  $endTime = isset($_POST['endTime']) ? $_POST['endTime'] / 1000 : 0; // Convert to seconds
  $elapsed = $endTime - $startTime;
  
  // Ensure the user waited for the randomized time, allowing some leeway
  if ($elapsed >= $_SESSION['waitTime'] && $elapsed <= ($_SESSION['waitTime'] + 5)) {
      return true;
  }
  
  return false;
}

$success = null;
if(isset($_POST['unban'])) {
  if($success = check()) {
    $redis->hSet('F2B_QUEUE_UNBAN', $banned, 1);
  }
}

if(!$success) {
  // Generate a random wait time and cryptographic token
  $_SESSION['waitTime'] = rand(10, 30);
  $_SESSION['startTime'] = microtime(true);
  $_SESSION['token'] = bin2hex(random_bytes(32));
}


$template = 'unban.twig';
$template_data = [
  'unban_success' => $success,
  'start_time' => round(microtime(true) * 1000),
  'wait_time' => $_SESSION['waitTime'],
  'token' => $_SESSION['token'],
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';