<?php
header('Content-Type: text/plain');
ini_set('error_reporting', 0);

$redis = new Redis();
$redis->connect('redis-mailcow', 6379);

function in_net($addr, $net) {
  $net = explode('/', $net);
  if (count($net) > 1) {
    $mask = $net[1];
  }
  $net = inet_pton($net[0]);
  $addr = inet_pton($addr);
  $length = strlen($net); // 4 for IPv4, 16 for IPv6
  if (strlen($net) != strlen($addr)) {
    return false;
  }
  if (!isset($mask)) {
    $mask = $length * 8;
  }
  $addr_bin = '';
  $net_bin = '';
  for ($i = 0; $i < $length; ++$i) {
    $addr_bin .= str_pad(decbin(ord(substr($addr, $i, $i+1))), 8, '0', STR_PAD_LEFT);
    $net_bin .= str_pad(decbin(ord(substr($net, $i, $i+1))), 8, '0', STR_PAD_LEFT);
  }
  return substr($addr_bin, 0, $mask) == substr($net_bin, 0, $mask);
}

if (isset($_GET['host'])) {
  try {
    foreach ($redis->hGetAll('WHITELISTED_FWD_HOST') as $host => $source) {
      if (in_net($_GET['host'], $host)) {
        echo '200 PERMIT';
        exit;
      }
    }
    echo '200 DUNNO';
  }
  catch (RedisException $e) {
    echo '200 DUNNO';
    exit;
  }
} else {
  try {
    echo '240.240.240.240' . PHP_EOL;
    foreach ($redis->hGetAll('WHITELISTED_FWD_HOST') as $host => $source) {
      echo $host . PHP_EOL;
    }
  }
  catch (RedisException $e) {
    echo '240.240.240.240' . PHP_EOL;
    exit;
  }
}
?>
