<?php
/*
The match section performs AND operation on different matches: for example, if you have from and rcpt in the same rule,
then the rule matches only when from AND rcpt match. For similar matches, the OR rule applies: if you have multiple rcpt matches,
then any of these will trigger the rule. If a rule is triggered then no more rules are matched.
*/
header('Content-Type: text/plain');
require_once "vars.inc.php";

ini_set('error_reporting', 0);

//$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
  $stmt = $pdo->query("SELECT '1' FROM `filterconf`");
}
catch (PDOException $e) {
  echo 'settings { }';
  exit;
}

function parse_email($email) {
  if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
  $a = strrpos($email, '@');
  return array('local' => substr($email, 0, $a), 'domain' => substr($email, $a));
}

function ucl_rcpts($object, $type) {
  global $pdo;
  if ($type == 'mailbox') {
    // Standard aliases
    $stmt = $pdo->prepare("SELECT `address` FROM `alias`
      WHERE `goto` = :object_goto
        AND `address` NOT LIKE '@%'");
    $stmt->execute(array(
      ':object_goto' => $object
    ));
    $standard_aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($standard_aliases)) {
      $local = parse_email($row['address'])['local'];
      $domain = parse_email($row['address'])['domain'];
      if (!empty($local) && !empty($domain)) {
        $rcpt[] = '/^' . str_replace('/', '\/', $local) . '[+].*' . str_replace('/', '\/', $domain) . '$/i';
      }
      $rcpt[] = '/^' . str_replace('/', '\/', $row['address']) . '$/i';
    }
    // Aliases by alias domains
    $stmt = $pdo->prepare("SELECT CONCAT(`local_part`, '@', `alias_domain`.`alias_domain`) AS `alias` FROM `mailbox` 
      LEFT OUTER JOIN `alias_domain` ON `mailbox`.`domain` = `alias_domain`.`target_domain`
      WHERE `mailbox`.`username` = :object");
    $stmt->execute(array(
      ':object' => $object
    ));
    $by_domain_aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    array_filter($by_domain_aliases);
    while ($row = array_shift($by_domain_aliases)) {
      if (!empty($row['alias'])) {
        $local = parse_email($row['alias'])['local'];
        $domain = parse_email($row['alias'])['domain'];
        if (!empty($local) && !empty($domain)) {
          $rcpt[] = '/^' . str_replace('/', '\/', $local) . '[+].*' . str_replace('/', '\/', $domain) . '$/i';
        }
      $rcpt[] = '/^' . str_replace('/', '\/', $row['alias']) . '$/i';
      }
    }
  }
  elseif ($type == 'domain') {
    // Domain self
    $rcpt[] = '/.*@' . $object . '/i';
    $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain`
      WHERE `target_domain` = :object");
    $stmt->execute(array(':object' => $object));
    $alias_domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    array_filter($alias_domains);
    while ($row = array_shift($alias_domains)) {
      $rcpt[] = '/.*@' . $row['alias_domain'] . '/i';
    }
  }
  if (!empty($rcpt)) {
    return $rcpt;
  }
  return false;
}
?>
settings {
  watchdog {
    priority = 10;
    rcpt = "/null@localhost/i";
    from = "/watchdog@localhost/i";
    apply "default" {
      actions {
        reject = 9999.0;
        greylist = 9998.0;
        "add header" = 9997.0;
      }
    }
  }
<?php

/*
// Start custom scores for users
*/

$stmt = $pdo->query("SELECT DISTINCT `object` FROM `filterconf` WHERE `option` = 'highspamlevel' OR `option` = 'lowspamlevel'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

while ($row = array_shift($rows)) {
  $username_sane = preg_replace("/[^a-zA-Z0-9]+/", "", $row['object']);
?>
  score_<?=$username_sane;?> {
    priority = 4;
<?php
  foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
  }
  $stmt = $pdo->prepare("SELECT `option`, `value` FROM `filterconf` 
    WHERE (`option` = 'highspamlevel' OR `option` = 'lowspamlevel')
      AND `object`= :object");
  $stmt->execute(array(':object' => $row['object']));
  $spamscore = $stmt->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);
?>
    apply "default" {
      actions {
        reject = <?=$spamscore['highspamlevel'][0];?>;
        greylist = <?=$spamscore['lowspamlevel'][0] - 1;?>;
        "add header" = <?=$spamscore['lowspamlevel'][0];?>;
      }
    }
  }
<?php
}

/*
// Start whitelist
*/

$stmt = $pdo->query("SELECT DISTINCT `object` FROM `filterconf` WHERE `option` = 'whitelist_from'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
while ($row = array_shift($rows)) {
  $username_sane = preg_replace("/[^a-zA-Z0-9]+/", "", $row['object']);
?>
  whitelist_<?=$username_sane;?> {
<?php
  $stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(CONCAT('^', `value`, '$'), '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
    WHERE `object`= :object
      AND `option` = 'whitelist_from'");
  $stmt->execute(array(':object' => $row['object']));
  $grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
    from = "/(<?=$value_sane;?>)/i";
<?php
  if (!filter_var(trim($row['object']), FILTER_VALIDATE_EMAIL)) {
?>
    priority = 5;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
  else {
?>
    priority = 6;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
?>
    apply "default" {
      MAILCOW_WHITE = -999.0;
    }
    symbols [
      "MAILCOW_WHITE"
    ]
  }
  whitelist_header_<?=$username_sane;?> {
<?php
  $stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(CONCAT('\<', `value`, '\>'), '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
    WHERE `object`= :object
      AND `option` = 'whitelist_from'");
  $stmt->execute(array(':object' => $row['object']));
  $grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
    header = {
      "From" = "/(<?=$value_sane;?>)/i";
    }
<?php
  if (!filter_var(trim($row['object']), FILTER_VALIDATE_EMAIL)) {
?>
    priority = 5;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
  else {
?>
    priority = 6;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
?>
    apply "default" {
      MAILCOW_WHITE = -999.0;
    }
    symbols [
      "MAILCOW_WHITE"
    ]
  }
<?php
}

/*
// Start blacklist
*/

$stmt = $pdo->query("SELECT DISTINCT `object` FROM `filterconf` WHERE `option` = 'blacklist_from'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
while ($row = array_shift($rows)) {
  $username_sane = preg_replace("/[^a-zA-Z0-9]+/", "", $row['object']);
?>
  blacklist_<?=$username_sane;?> {
<?php
  $stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(CONCAT('^', `value`, '$'), '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
    WHERE `object`= :object
      AND `option` = 'blacklist_from'");
  $stmt->execute(array(':object' => $row['object']));
  $grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
    from = "/(<?=$value_sane;?>)/i";
<?php
  if (!filter_var(trim($row['object']), FILTER_VALIDATE_EMAIL)) {
?>
    priority = 5;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
  else {
?>
    priority = 6;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
?>
    apply "default" {
      MAILCOW_BLACK = 999.0;
    }
    symbols [
      "MAILCOW_BLACK"
    ]
  }
  blacklist_header_<?=$username_sane;?> {
<?php
  $stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(CONCAT('\<', `value`, '\>'), '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
    WHERE `object`= :object
      AND `option` = 'blacklist_from'");
  $stmt->execute(array(':object' => $row['object']));
  $grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
    header = {
      "From" = "/(<?=$value_sane;?>)/i";
    }
<?php
  if (!filter_var(trim($row['object']), FILTER_VALIDATE_EMAIL)) {
?>
    priority = 5;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
  else {
?>
    priority = 6;
<?php
    foreach (ucl_rcpts($row['object'], strpos($row['object'], '@') === FALSE ? 'domain' : 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
    }
  }
?>
    apply "default" {
      MAILCOW_BLACK = 999.0;
    }
    symbols [
      "MAILCOW_BLACK"
    ]
  }
<?php
}

/*
// Start traps
*/

?>
  traps {
<?php
  foreach (ucl_rcpts('spam@localhost', 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
  }
  foreach (ucl_rcpts('ham@localhost', 'mailbox') as $rcpt) {
?>
    rcpt = <?=json_encode($rcpt, JSON_UNESCAPED_SLASHES);?>;
<?php
  }
?>
    priority = 9;
    want_spam = yes;
  }

<?php
// Start additional content

$stmt = $pdo->query("SELECT `id`, `content` FROM `settingsmap` WHERE `active` = '1'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
while ($row = array_shift($rows)) {
  $username_sane = preg_replace("/[^a-zA-Z0-9]+/", "", $row['id']);
?>
  additional_settings_<?=intval($row['id']);?> {
<?php
    $content = preg_split('/\r\n|\r|\n/', $row['content']);
    foreach ($content as $line) {
      echo '    ' . $line . PHP_EOL;
    }
}
?>
}
