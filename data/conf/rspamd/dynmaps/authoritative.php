<?php
ini_set('error_reporting', 0);
header('Content-Type: text/plain');
require_once "vars.inc.php";
$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $database_user, $database_pass, $opt);
$stmt = $pdo->query("SELECT `domain` FROM `domain`");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
while ($row = array_shift($rows)) {
  echo strtolower(trim($row['domain'])) . PHP_EOL;
}
$stmt = $pdo->query("SELECT `alias_domain` FROM `alias_domain`");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
while ($row = array_shift($rows)) {
  echo strtolower(trim($row['alias_domain'])) . PHP_EOL;
}
?>