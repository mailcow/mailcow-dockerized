<?php
header('Content-Type: text/plain');
require_once "vars.inc.php";

ini_set('error_reporting', 0);

function in_net($addr, $net)
{
	$net = explode('/', $net);
	$mask = $net[1];
	$net = inet_pton($net[0]);
	$addr = inet_pton($addr);

	$length = strlen($net); // 4 for IPv4, 16 for IPv6
	if (strlen($net) != strlen($addr))
		return FALSE;

	$addr_bin = '';
	$net_bin = '';
	for ($i = 0; $i < $length; ++$i)
	{
		$addr_bin .= str_pad(decbin(ord(substr($addr, $i, $i+1))), 8, '0', STR_PAD_LEFT);
		$net_bin .= str_pad(decbin(ord(substr($net, $i, $i+1))), 8, '0', STR_PAD_LEFT);
	}

	return substr($addr_bin, 0, $mask) == substr($net_bin, 0, $mask);
}

$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
  $stmt = $pdo->query("SELECT * FROM `forwarding_hosts`");
  $networks = $stmt->fetchAll(PDO::FETCH_COLUMN);
  foreach ($networks as $network)
  {
    if (in_net($_GET['host'], $network))
    {
      echo '200 permit';
      exit;
    }
  }
  echo '200 dunno';
}
catch (PDOException $e) {
  echo 'settings { }';
  exit;
}
?>
