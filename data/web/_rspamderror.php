<?php
$valkey = new Redis();
try {
  if (!empty(getenv('VALKEY_SLAVEOF_IP'))) {
    $valkey->connect(getenv('VALKEY_SLAVEOF_IP'), getenv('VALKEY_SLAVEOF_PORT'));
  }
  else {
    $valkey->connect('valkey-mailcow', 6379);
  }
  $valkey->auth(getenv("VALKEYPASS"));
}
catch (Exception $e) {
  exit;
}
header('Content-Type: application/json');
echo '{"error":"Unauthorized"}';
error_log("Rspamd UI: Invalid password by " . $_SERVER['REMOTE_ADDR']);
$valkey->publish("F2B_CHANNEL", "Rspamd UI: Invalid password by " . $_SERVER['REMOTE_ADDR']);
