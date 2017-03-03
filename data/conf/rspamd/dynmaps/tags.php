<?php
require_once "vars.inc.php";
ini_set('error_reporting', 0);
$has_object = 0;
header('Content-Type: text/plain');
$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
  $stmt = $pdo->query("SELECT `username` FROM `mailbox` WHERE `wants_tagged_subject` = '1'");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($rows)) {
    $has_object = 1;
    echo strtolower(trim($row['username'])) . PHP_EOL;
  }
  $stmt = $pdo->query("SELECT CONCAT(mailbox.local_part, '@', alias_domain.alias_domain) as `tag_ad` FROM `mailbox` INNER JOIN `alias_domain` ON mailbox.domain = alias_domain.target_domain WHERE mailbox.wants_tagged_subject='1';");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($rows)) {
    $has_object = 1;
    echo strtolower(trim($row['tag_ad'])) . PHP_EOL;
  }
  if ($has_object == 0) {
    echo "dummy@domain.local";
  }
}
catch (PDOException $e) {
  echo "dummy@domain.local";
  exit;
}
?>
