<?php
/*
The match section performs AND operation on different matches: for example, if you have from and rcpt in the same rule,
then the rule matches only when from AND rcpt match. For similar matches, the OR rule applies: if you have multiple rcpt matches,
then any of these will trigger the rule. If a rule is triggered then no more rules are matched.
*/
ini_set('error_reporting', 'E_ALL');

header('Content-Type: text/plain');
require_once "vars.inc.php";

$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $database_user, $database_pass, $opt);
?>
settings {
<?php
$stmt = $pdo->query("SELECT DISTINCT `object` FROM `filterconf` WHERE `option` = 'highspamlevel' OR `option` = 'lowspamlevel'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

while ($row = array_shift($rows)) {
	$username_sane = preg_replace("/[^a-zA-Z0-9]+/", "", $row['object']);
?>
	score_<?=$username_sane;?> {
		priority = low;
<?php
	$stmt = $pdo->prepare("SELECT `option`, `value` FROM `filterconf` 
		WHERE (`option` = 'highspamlevel' OR `option` = 'lowspamlevel')
			AND `object`= :object");
	$stmt->execute(array(':object' => $row['object']));
	$spamscore = $stmt->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_GROUP);

	$stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(`value`, '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
		WHERE `object`= :object
			AND (`option` = 'blacklist_from' OR `option` = 'whitelist_from')");
	$stmt->execute(array(':object' => $row['object']));
	$grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
		from = "/^((?!<?=$value_sane;?>).)*$/";
		rcpt = "<?=$row['object'];?>";
<?php
	$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` = :object_goto AND `address` NOT LIKE '@%' AND `address` != :object_address");
	$stmt->execute(array(':object_goto' => $row['object'], ':object_address' => $row['object']));
	$rows_aliases_1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
	while ($row_aliases_1 = array_shift($rows_aliases_1)) {
?>
		rcpt = "<?=$row_aliases_1['address'];?>";
<?php
	}
	$stmt = $pdo->prepare("SELECT CONCAT(`local_part`, '@', `alias_domain`.`alias_domain`) AS `aliases` FROM `mailbox` 
		LEFT OUTER JOIN `alias_domain` on `mailbox`.`domain` = `alias_domain`.`target_domain`
		WHERE `mailbox`.`username` = :object");
	$stmt->execute(array(':object' => $row['object']));
	$rows_aliases_2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
  array_filter($rows_aliases_2);
	while ($row_aliases_2 = array_shift($rows_aliases_2)) {
?>
		rcpt = "<?=$row_aliases_2['aliases'];?>";
<?php
	}
?>
		apply "default" {
			actions {
				reject = <?=$spamscore['highspamlevel'][0];?>;
				greylist = <?=$spamscore['lowspamlevel'][0] - 1;?>;
				"add header" = <?=$spamscore['lowspamlevel'][0];?>;
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
	$stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(`value`, '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
		WHERE `object`= :object
			AND `option` = 'whitelist_from'");
	$stmt->execute(array(':object' => $row['object']));
	$grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
		from = "/(<?=$value_sane;?>)/";
<?php
	if (!filter_var(trim($row['object']), FILTER_VALIDATE_EMAIL)) {
?>
		priority = medium;
		rcpt = "/.*@<?=$row['object'];?>/";
<?php
		$stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain`
			WHERE `target_domain` = :object");
		$stmt->execute(array(':object' => $row['object']));
		$rows_domain_aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    array_filter($rows_domain_aliases);
		while ($row_domain_aliases = array_shift($rows_domain_aliases)) {
?>
		rcpt = "/.*@<?=$row_domain_aliases['alias_domain'];?>/";
<?php
		}
	}
	else {
?>
		priority = high;
		rcpt = "<?=$row['object'];?>";
<?php
	}
	$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` = :object_goto AND `address` NOT LIKE '@%' AND `address` != :object_address");
	$stmt->execute(array(':object_goto' => $row['object'], ':object_address' => $row['object']));
	$rows_aliases_wl_1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
  array_filter($rows_aliases_wl_1);
	while ($row_aliases_wl_1 = array_shift($rows_aliases_wl_1)) {
?>
		rcpt = "<?=$row_aliases_wl_1['address'];?>";
<?php
	}
	$stmt = $pdo->prepare("SELECT CONCAT(`local_part`, '@', `alias_domain`.`alias_domain`) AS `aliases` FROM `mailbox` 
		LEFT OUTER JOIN `alias_domain` on `mailbox`.`domain` = `alias_domain`.`target_domain`
		WHERE `mailbox`.`username` = :object");
	$stmt->execute(array(':object' => $row['object']));
	$rows_aliases_wl_2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
  array_filter($rows_aliases_wl_2);
	while ($row_aliases_wl_2 = array_shift($rows_aliases_wl_2)) {
?>
		rcpt = "<?=$row_aliases_wl_2['aliases'];?>";
<?php
	}
?>
		apply "default" {
			MAILCOW_MOO = -999.0;
		}
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
	$stmt = $pdo->prepare("SELECT GROUP_CONCAT(REPLACE(`value`, '*', '.*') SEPARATOR '|') AS `value` FROM `filterconf`
		WHERE `object`= :object
			AND `option` = 'blacklist_from'");
	$stmt->execute(array(':object' => $row['object']));
	$grouped_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$value_sane = preg_replace("/\.\./", ".", (preg_replace("/\*/", ".*", $grouped_lists[0])));
?>
		from = "/(<?=$value_sane;?>)/";
<?php
	if (!filter_var(trim($row['object']), FILTER_VALIDATE_EMAIL)) {
?>
		priority = medium;
		rcpt = "/.*@<?=$row['object'];?>/";
<?php
		$stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain`
			WHERE `target_domain` = :object");
		$stmt->execute(array(':object' => $row['object']));
		$rows_domain_aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    array_filter($rows_domain_aliases);
		while ($row_domain_aliases = array_shift($rows_domain_aliases)) {
?>
		rcpt = "/.*@<?=$row_domain_aliases['alias_domain'];?>/";
<?php
		}
	}
	else {
?>
		priority = high;
		rcpt = "<?=$row['object'];?>";
<?php
	}
	$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` = :object_goto AND `address` NOT LIKE '@%' AND `address` != :object_address");
	$stmt->execute(array(':object_goto' => $row['object'], ':object_address' => $row['object']));
	$rows_aliases_bl_1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
  array_filter($rows_aliases_bl_1);
	while ($row_aliases_bl_1 = array_shift($rows_aliases_bl_1)) {
?>
		rcpt = "<?=$row_aliases_bl_1['address'];?>";
<?php
	}
	$stmt = $pdo->prepare("SELECT CONCAT(`local_part`, '@', `alias_domain`.`alias_domain`) AS `aliases` FROM `mailbox` 
		LEFT OUTER JOIN `alias_domain` on `mailbox`.`domain` = `alias_domain`.`target_domain`
		WHERE `mailbox`.`username` = :object");
	$stmt->execute(array(':object' => $row['object']));
	$rows_aliases_bl_2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
  array_filter($rows_aliases_bl_2);
	while ($row_aliases_bl_2 = array_shift($rows_aliases_bl_2)) {
?>
		rcpt = "<?=$row_aliases_bl_2['aliases'];?>";
<?php
	}
?>
		apply "default" {
			MAILCOW_MOO = 999.0;
		}
	}
<?php
}
?>
}