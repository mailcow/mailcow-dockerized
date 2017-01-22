<?php
require_once 'dkim.inc.php';
require_once 'mailbox.inc.php';
require_once 'domainadmin.inc.php';
require_once 'admin.inc.php';
function hash_password($password) {
	$salt_str = bin2hex(openssl_random_pseudo_bytes(8));
	return "{SSHA256}".base64_encode(hash('sha256', $password . $salt_str, true) . $salt_str);
}
function hasDomainAccess($username, $role, $domain) {
	global $pdo;
	if (!filter_var($username, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		return false;
	}
	if (empty($domain) || !is_valid_domain_name($domain)) {
		return false;
	}
	if ($role != 'admin' && $role != 'domainadmin' && $role != 'user') {
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain_admins`
		WHERE (
			`active`='1'
			AND `username` = :username
			AND (`domain` = :domain1 OR `domain` = (SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain2))
		)
    OR 'admin' = :role");
		$stmt->execute(array(':username' => $username, ':domain1' => $domain, ':domain2' => $domain, ':role' => $role));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
	if (!empty($num_results)) {
		return true;
	}
	return false;
}
function hasMailboxObjectAccess($username, $role, $object) {
	global $pdo;
	if (!filter_var($username, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		return false;
	}
	if ($role != 'admin' && $role != 'domainadmin' && $role != 'user') {
		return false;
	}
	if ($username == $object) {
		return true;
	}
	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `mailbox` WHERE `username` = :object");
		$stmt->execute(array(':object' => $object));
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (isset($row['domain']) && hasDomainAccess($username, $role, $row['domain'])) {
      return true;
    }
	}
  catch(PDOException $e) {
		error_log($e);
		return false;
	}
	return false;
}
function init_db_schema() {
	global $pdo;
	try {
		$stmt = $pdo->prepare("SELECT NULL FROM `admin`, `imapsync`");
		$stmt->execute();
	}
	catch (Exception $e) {
		$lines = file('/web/inc/init.sql');
		$data = '';
		foreach ($lines as $line) {
			if (substr($line, 0, 2) == '--' || $line == '') {
				continue;
			}
			$data .= $line;
			if (substr(trim($line), -1, 1) == ';') {
				$pdo->query($data);
				$data = '';
			}
		}
    // Create index if not exists
		$stmt = $pdo->query("SHOW INDEX FROM sogo_acl WHERE KEY_NAME = 'sogo_acl_c_folder_id_idx'");
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		if ($num_results == 0) {
			$pdo->query("CREATE INDEX sogo_acl_c_folder_id_idx ON sogo_acl(c_folder_id)");
		}
		$stmt = $pdo->query("SHOW INDEX FROM sogo_acl WHERE KEY_NAME = 'sogo_acl_c_uid_idx'");
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		if ($num_results == 0) {
			$pdo->query("CREATE INDEX sogo_acl_c_uid_idx ON sogo_acl(c_uid)");
		}
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => 'Database initialization completed.'
		);
	}
  // Add newly added columns
  $stmt = $pdo->query("SHOW COLUMNS FROM `mailbox` LIKE 'kind'");
  $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
  if ($num_results == 0) {
    $pdo->query("ALTER TABLE `mailbox` ADD `kind` varchar(100) NOT NULL DEFAULT ''");
  }
  $stmt = $pdo->query("SHOW COLUMNS FROM `mailbox` LIKE 'multiple_bookings'");
  $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
  if ($num_results == 0) {
    $pdo->query("ALTER TABLE `mailbox` ADD `multiple_bookings` tinyint(1) NOT NULL DEFAULT '0'");
  }
  $stmt = $pdo->query("SHOW COLUMNS FROM `mailbox` LIKE 'wants_tagged_subject'");
  $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
  if ($num_results == 0) {
    $pdo->query("ALTER TABLE `mailbox` ADD `wants_tagged_subject` tinyint(1) NOT NULL DEFAULT '0'");
  }
}
function verify_ssha256($hash, $password) {
	// Remove tag if any
	$hash = ltrim($hash, '{SSHA256}');
	// Decode hash
	$dhash = base64_decode($hash);
	// Get first 32 bytes of binary which equals a SHA256 hash
	$ohash = substr($dhash, 0, 32);
	// Remove SHA256 hash from decoded hash to get original salt string
	$osalt = str_replace($ohash, '', $dhash);
	// Check single salted SHA256 hash against extracted hash
	if (hash('sha256', $password . $osalt, true) == $ohash) {
		return true;
	}
	else {
		return false;
	}
}
function doveadm_authenticate($hash, $algorithm, $password) {
	$descr = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$pipes = array();
	$process = proc_open("/usr/bin/doveadm pw -s ".$algorithm." -t '".$hash."'", $descr, $pipes);
	if (is_resource($process)) {
		fputs($pipes[0], $password);
		fclose($pipes[0]);
		while ($f = fgets($pipes[1])) {
			if (preg_match('/(verified)/', $f)) {
				proc_close($process);
				return true;
			}
			return false;
		}
		fclose($pipes[1]);
		while ($f = fgets($pipes[2])) {
			proc_close($process);
			return false;
		}
		fclose($pipes[2]);
		proc_close($process);
	}
	return false;
}
function check_login($user, $pass) {
	global $pdo;
	if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
		return false;
	}
	$user = strtolower(trim($user));
	$stmt = $pdo->prepare("SELECT `password` FROM `admin`
			WHERE `superadmin` = '1'
			AND `username` = :user");
	$stmt->execute(array(':user' => $user));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		if (verify_ssha256($row['password'], $pass) !== false) {
			unset($_SESSION['ldelay']);
			return "admin";
		}
	}
	$stmt = $pdo->prepare("SELECT `password` FROM `admin`
			WHERE `superadmin` = '0'
			AND `active`='1'
			AND `username` = :user");
	$stmt->execute(array(':user' => $user));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		if (verify_ssha256($row['password'], $pass) !== false) {
			unset($_SESSION['ldelay']);
			return "domainadmin";
		}
	}
	$stmt = $pdo->prepare("SELECT `password` FROM `mailbox`
			WHERE `active`='1'
			AND `username` = :user");
	$stmt->execute(array(':user' => $user));
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		if (verify_ssha256($row['password'], $pass) !== false) {
			unset($_SESSION['ldelay']);
			return "user";
		}
	}
	if (!isset($_SESSION['ldelay'])) {
		$_SESSION['ldelay'] = "0";
	}
	elseif (!isset($_SESSION['mailcow_cc_username'])) {
		$_SESSION['ldelay'] = $_SESSION['ldelay']+0.5;
	}
	sleep($_SESSION['ldelay']);
}
function formatBytes($size, $precision = 2) {
	if(!is_numeric($size)) {
		return "0";
	}
	$base = log($size, 1024);
	$suffixes = array(' Byte', ' KiB', ' MiB', ' GiB', ' TiB');
	if ($size == "0") {
		return "0";
	}
	return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}
function set_admin_account($postarray) {
	global $lang;
	global $pdo;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$name		= $postarray['admin_user'];
	$name_now	= $postarray['admin_user_now'];

	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $name)) || empty ($name)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $name_now)) || empty ($name_now)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	if (!empty($postarray['admin_pass']) && !empty($postarray['admin_pass2'])) {
		if ($postarray['admin_pass'] != $postarray['admin_pass2']) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($postarray['admin_pass']);
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET 
				`modified` = :modified,
				`password` = :password_hashed,
				`username` = :name
					WHERE `username` = :username");
			$stmt->execute(array(
				':password_hashed' => $password_hashed,
				':modified' => date('Y-m-d H:i:s'),
				':name' => $name,
				':username' => $name_now
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	else {
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET 
				`modified` = :modified,
				`username` = :name
					WHERE `username` = :name_now");
			$stmt->execute(array(
				':name' => $name,
				':modified' => date('Y-m-d H:i:s'),
				':name_now' => $name_now
			));
		}
		catch (PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	try {
		$stmt = $pdo->prepare("UPDATE `domain_admins` SET 
			`domain` = :domain,
			`username` = :name
				WHERE `username` = :name_now");
		$stmt->execute(array(
			':domain' => 'ALL',
			':name' => $name,
			':name_now' => $name_now
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['admin_modified'])
	);
}
function set_time_limited_aliases($postarray) {
	global $lang;
	global $pdo;
  (isset($postarray['username'])) ? $username = $postarray['username'] : $username = $_SESSION['mailcow_cc_username'];

  if ($_SESSION['mailcow_cc_role'] != "user" &&
    $_SESSION['mailcow_cc_role'] != "admin") {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
  }
  if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
  }

	try {
    $stmt = $pdo->prepare("SELECT `domain` FROM `mailbox` WHERE `username` = :username");
    $stmt->execute(array(':username' => $username));
    $domain = $stmt->fetch(PDO::FETCH_ASSOC)['domain'];
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }

	switch ($postarray["trigger_set_time_limited_aliases"]) {
		case "generate":
			if (!is_numeric($postarray["validity"]) || $postarray["validity"] > 672) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['validity_missing'])
				);
				return false;
			}
			$validity = strtotime("+".$postarray["validity"]." hour"); 
			$letters = 'abcefghijklmnopqrstuvwxyz1234567890';
			$random_name = substr(str_shuffle($letters), 0, 24);
			try {
				$stmt = $pdo->prepare("INSERT INTO `spamalias` (`address`, `goto`, `validity`) VALUES
					(:address, :goto, :validity)");
				$stmt->execute(array(
					':address' => $random_name . '@' . $domain,
					':goto' => $username,
					':validity' => $validity
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "deleteall":
			try {
				$stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username");
				$stmt->execute(array(
					':username' => $username
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}	
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "delete":
			if (empty($postarray['item']) || !filter_var($postarray['item'], FILTER_VALIDATE_EMAIL)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['access_denied'])
				);
				return false;
			}
      $item	= $postarray['item'];
			try {
				$stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username AND `address` = :item");
				$stmt->execute(array(
					':username' => $username,
					':item' => $item
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}	
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "extendall":
			try {
				$stmt = $pdo->prepare("UPDATE `spamalias` SET `validity` = (`validity` + 3600) WHERE
          `goto` = :username AND
					`validity` >= :validity");
				$stmt->execute(array(
					':username' => $username,
					':validity' => time(),
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
		case "extend":
			if (empty($postarray['item']) || !filter_var($postarray['item'], FILTER_VALIDATE_EMAIL)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['access_denied'])
				);
				return false;
			}
      $item	= $postarray['item'];
			try {
				$stmt = $pdo->prepare("UPDATE `spamalias` SET `validity` = (`validity` + 3600) WHERE 
          `goto` = :username AND
					`address` = :item AND
					`validity` >= :validity");
				$stmt->execute(array(
					':username' => $username,
					':item' => $item,
					':validity' => time(),
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
			);
		break;
	}
}
function get_time_limited_aliases($username = null) {
  // 'username' can be be set, if not, default to mailcow_cc_username
  global $lang;
	global $pdo;
  $data = array();
  if (isset($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  try {
    $stmt = $pdo->prepare("SELECT `address`,
      `goto`,
      `validity`
        FROM `spamalias`
          WHERE `goto` = :username
            AND `validity` >= :unixnow");
    $stmt->execute(array(':username' => $username, ':unixnow' => time()));
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $data;
}
function edit_user_account($postarray) {
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
	$password_old		= $postarray['user_old_pass'];
	isset($postarray['togglePwNew']) ? $pwnew_active = '1' : $pwnew_active = '0';

	if (isset($pwnew_active) && $pwnew_active == "1") {
		$password_new	= $postarray['user_new_pass'];
		$password_new2	= $postarray['user_new_pass2'];
	}

	if (!check_login($username, $password_old) == "user") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	if (isset($password_new) && isset($password_new2)) {
		if (!empty($password_new2) && !empty($password_new)) {
			if ($password_new2 != $password_new) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['password_mismatch'])
				);
				return false;
			}
			if (strlen($password_new) < "6" ||
				!preg_match('/[A-Za-z]/', $password_new) ||
				!preg_match('/[0-9]/', $password_new)) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => sprintf($lang['danger']['password_complexity'])
					);
					return false;
			}
			$password_hashed = hash_password($password_new);
			try {
				$stmt = $pdo->prepare("UPDATE `mailbox` SET `modified` = :modified, `password` = :password_hashed WHERE `username` = :username");
				$stmt->execute(array(
					':password_hashed' => $password_hashed,
					':modified' => date('Y-m-d H:i:s'),
					':username' => $username
				));
			}
			catch (PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function get_spam_score($username = null) {
	global $pdo;
	$default = "5, 15";
  if (isset($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      return false;
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
	try {
		$stmt = $pdo->prepare("SELECT `value` FROM `filterconf` WHERE `object` = :username AND
			(`option` = 'lowspamlevel' OR `option` = 'highspamlevel')");
		$stmt->execute(array(':username' => $username));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	if (empty($num_results)) {
		return $default;
	}
	else {
		try {
			$stmt = $pdo->prepare("SELECT `value` FROM `filterconf` WHERE `option` = 'highspamlevel' AND `object` = :username");
			$stmt->execute(array(':username' => $username));
			$highspamlevel = $stmt->fetch(PDO::FETCH_ASSOC);

			$stmt = $pdo->prepare("SELECT `value` FROM `filterconf` WHERE `option` = 'lowspamlevel' AND `object` = :username");
			$stmt->execute(array(':username' => $username));
			$lowspamlevel = $stmt->fetch(PDO::FETCH_ASSOC);

			return $lowspamlevel['value'].', '.$highspamlevel['value'];
		}
		catch(PDOException $e) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
}
function edit_spam_score($postarray) {
  // Array items
  // 'username' can be set, defaults to mailcow_cc_username
  // 'lowspamlevel'
  // 'highspamlevel'
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
	$lowspamlevel	= explode(',', $postarray['score'])[0];
	$highspamlevel	= explode(',', $postarray['score'])[1];

	if (!is_numeric($lowspamlevel) || !is_numeric($highspamlevel)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :username
			AND (`option` = 'lowspamlevel' OR `option` = 'highspamlevel')");
		$stmt->execute(array(
			':username' => $username
		));

		$stmt = $pdo->prepare("INSERT INTO `filterconf` (`object`, `option`, `value`)
			VALUES (:username, 'highspamlevel', :highspamlevel)");
		$stmt->execute(array(
			':username' => $username,
			':highspamlevel' => $highspamlevel
		));

		$stmt = $pdo->prepare("INSERT INTO `filterconf` (`object`, `option`, `value`)
			VALUES (:username, 'lowspamlevel', :lowspamlevel)");
		$stmt->execute(array(
			':username' => $username,
			':lowspamlevel' => $lowspamlevel
		));
	}
	catch (PDOException $e) {
		$stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :username
			AND (`option` = 'lowspamlevel' OR `option` = 'highspamlevel')");
		$stmt->execute(array(
			':username' => $username
		));
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function get_policy_list($object = null) {
  // 'object' can be be set, if not, default to mailcow_cc_username
	global $lang;
	global $pdo;
  if (isset($object)) {
    if (!filter_var($object, FILTER_VALIDATE_EMAIL) && is_valid_domain_name($object)) {
      $object = idn_to_ascii(strtolower(trim($object)));
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
    }
    elseif (filter_var($object, FILTER_VALIDATE_EMAIL)) {
      if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
    }
  }
  else {
     $object = $_SESSION['mailcow_cc_username'];
  }
  try {
    // WHITELIST
    $stmt = $pdo->prepare("SELECT `object`, `value`, `prefid` FROM `filterconf` WHERE `option`='whitelist_from' AND (`object` = :username OR `object` = SUBSTRING_INDEX(:username_domain, '@' ,-1))");
    $stmt->execute(array(':username' => $object, ':username_domain' => $object));
    $rows['whitelist'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // BLACKLIST
    $stmt = $pdo->prepare("SELECT `object`, `value`, `prefid` FROM `filterconf` WHERE `option`='blacklist_from' AND (`object` = :username OR `object` = SUBSTRING_INDEX(:username_domain, '@' ,-1))");
    $stmt->execute(array(':username' => $object, ':username_domain' => $object));
    $rows['blacklist'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $rows;
}
function add_policy_list_item($postarray) {
  // Array data
  // Either 'domain' or 'username' can be be set
  // If none of the above is set, default to mailcow_cc_username
  //
  // If 'delete_prefid' then delete item id
	global $lang;
	global $pdo;
  (isset($postarray['username'])) ? $object = $postarray['username'] : null;
  (isset($postarray['domain']))   ? $object = $postarray['domain'] : null;
  (!isset($object))               ? $object = $_SESSION['mailcow_cc_username'] : null;

  if (is_valid_domain_name($object)) {
		if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}
    $object = idn_to_ascii(strtolower(trim($object)));
  }
  else {
		if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}
  }

	($postarray['object_list'] == "bl") ? $object_list = "blacklist_from" : null;
	($postarray['object_list'] == "wl") ? $object_list = "whitelist_from" : null;
	$object_from = preg_replace('/\.+/', '.', rtrim(preg_replace("/\.\*/", "*", trim(strtolower($postarray['object_from']))), '.'));
  if (!ctype_alnum(str_replace(array('@', '.', '-', '*'), '', $object_from))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['policy_list_from_invalid'])
		);
		return false;
	}
	if ($object_list != "blacklist_from" && $object_list != "whitelist_from") {
    $_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `object` FROM `filterconf`
			WHERE (`option` = 'whitelist_from'  OR `option` = 'blacklist_from')
				AND `object` = :object
				AND `value` = :object_from");
		$stmt->execute(array(':object' => $object, ':object_from' => $object_from));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['policy_list_from_exists'])
      );
      return false;
    }
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO `filterconf` (`object`, `option` ,`value`)
			VALUES (:object, :object_list, :object_from)");
		$stmt->execute(array(
			':object' => $object,
			':object_list' => $object_list,
			':object_from' => $object_from
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['object_modified'], $object)
	);
}
function delete_policy_list_item($postarray) {
  // Array data
  // Either 'domain' or 'username' can be be set
  // If none of the above is set, default to mailcow_cc_username
  //
  // 'delete_prefid' is item to be deleted
	global $lang;
	global $pdo;
  (isset($postarray['username'])) ? $object = $postarray['username'] : null;
  (isset($postarray['domain']))   ? $object = $postarray['domain'] : null;
  (!isset($object))               ? $object = $_SESSION['mailcow_cc_username'] : null;

  if (is_valid_domain_name($object)) {
		if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}
    $object = idn_to_ascii(strtolower(trim($object)));
  }
  else {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
  }

  if (!is_numeric($postarray['delete_prefid'])) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }

  try {
    $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :object AND `prefid` = :prefid");
    $stmt->execute(array(
      ':object' => $object,
      ':prefid' => $postarray['delete_prefid']
    ));
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['object_modified'], $object)
  );
  return true;
}
function get_syncjobs($username = null) {
  // 'username' can be be set, if not, default to mailcow_cc_username
	global $lang;
	global $pdo;
  $data = array();
  if (isset($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  try {
    $stmt = $pdo->prepare("SELECT *, CONCAT(LEFT(`password1`, 3), '…') as `password1_short`
        FROM `imapsync`
          WHERE `user2` = :username");
    $stmt->execute(array(':username' => $username));
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $data;
}
function get_syncjob_details($id) {
	global $lang;
	global $pdo;
  $syncjobdetails = array();
	if ($_SESSION['mailcow_cc_role'] != "user" &&
		$_SESSION['mailcow_cc_role'] != "admin") {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
	}
  if (!is_numeric($id)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT * FROM `imapsync` WHERE (`user2` = :username OR 'admin' = :role) AND id = :id");
    $stmt->execute(array(':id' => $id, ':role' => $_SESSION['mailcow_cc_role'], ':username' => $_SESSION['mailcow_cc_username']));
    $syncjobdetails = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $syncjobdetails;
}
function delete_syncjob($postarray) {
  // Array items
  // 'username' can be set, defaults to mailcow_cc_username
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  $id = $postarray['id'];
  if (!is_numeric($id)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("DELETE FROM `imapsync` WHERE `user2` = :username AND `id`= :id");
    $stmt->execute(array(
      ':username' => $username,
      ':id' => $id,
    ));
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
  );
  return true;
}
function add_syncjob($postarray) {
  // Array items
  // 'username' can be set, defaults to mailcow_cc_username
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  isset($postarray['active']) ? $active = '1' : $active = '0';
  isset($postarray['delete2duplicates']) ? $delete2duplicates = '1' : $delete2duplicates = '0';
  $port1            = $postarray['port1'];
  $host1            = $postarray['host1'];
  $password1        = $postarray['password1'];
  $exclude          = $postarray['exclude'];
  $maxage           = $postarray['maxage'];
  $subfolder2       = $postarray['subfolder2'];
  $user1            = $postarray['user1'];
  $mins_interval    = $postarray['mins_interval'];
  $enc1             = $postarray['enc1'];

  if (empty($subfolder2)) {
    $subfolder2 = "";
  }
  if (!isset($maxage) || !filter_var($maxage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32767)))) {
    $maxage = "0";
  }
  if (!filter_var($port1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)))) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (!filter_var($mins_interval, FILTER_VALIDATE_INT, array('options' => array('min_range' => 10, 'max_range' => 3600)))) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (!is_valid_domain_name($host1)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if ($enc1 != "TLS" && $enc1 != "SSL" && $enc1 != "PLAIN") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (@preg_match("/" . $exclude . "/", null) === false) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT `user2`, `user1` FROM `imapsync`
      WHERE `user2` = :user2 AND `user1` = :user1");
    $stmt->execute(array(':user1' => $user1, ':user2' => $username));
    $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  if ($num_results != 0) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($host1 . ' / ' . $user1))
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("INSERT INTO `imapsync` (`user2`, `exclude`, `maxage`, `subfolder2`, `host1`, `authmech1`, `user1`, `password1`, `mins_interval`, `port1`, `enc1`, `delete2duplicates`, `active`)
      VALUES (:user2, :exclude, :maxage, :subfolder2, :host1, :authmech1, :user1, :password1, :mins_interval, :port1, :enc1, :delete2duplicates, :active)");
    $stmt->execute(array(
      ':user2' => $username,
      ':exclude' => $exclude,
      ':maxage' => $maxage,
      ':subfolder2' => $subfolder2,
      ':host1' => $host1,
      ':authmech1' => 'PLAIN',
      ':user1' => $user1,
      ':password1' => $password1,
      ':mins_interval' => $mins_interval,
      ':port1' => $port1,
      ':enc1' => $enc1,
      ':delete2duplicates' => $delete2duplicates,
      ':active' => $active,
    ));
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['mailbox_modified'], $username)
  );
  return true;
}
function edit_syncjob($postarray) {
  // Array items
  // 'username' can be set, defaults to mailcow_cc_username
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  isset($postarray['active']) ? $active = '1' : $active = '0';
  isset($postarray['delete2duplicates']) ? $delete2duplicates = '1' : $delete2duplicates = '0';
  $id               = $postarray['id'];
  $port1            = $postarray['port1'];
  $host1            = $postarray['host1'];
  $password1        = $postarray['password1'];
  $exclude          = $postarray['exclude'];
  $maxage           = $postarray['maxage'];
  $subfolder2       = $postarray['subfolder2'];
  $user1            = $postarray['user1'];
  $mins_interval    = $postarray['mins_interval'];
  $enc1             = $postarray['enc1'];

  if (empty($subfolder2)) {
    $subfolder2 = "";
  }
  if (!isset($maxage) || !filter_var($maxage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32767)))) {
    $maxage = "0";
  }
  if (!filter_var($port1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)))) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (!filter_var($mins_interval, FILTER_VALIDATE_INT, array('options' => array('min_range' => 10, 'max_range' => 3600)))) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (!is_valid_domain_name($host1)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if ($enc1 != "TLS" && $enc1 != "SSL" && $enc1 != "PLAIN") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (@preg_match("/" . $exclude . "/", null) === false) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT `user2` FROM `imapsync`
      WHERE `user2` = :user2 AND `id` = :id");
    $stmt->execute(array(':user2' => $username, ':id' => $id));
    $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  if (empty($num_results)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("UPDATE `imapsync` set `maxage` = :maxage, `subfolder2` = :subfolder2, `exclude` = :exclude, `host1` = :host1, `user1` = :user1, `password1` = :password1, `mins_interval` = :mins_interval, `port1` = :port1, `enc1` = :enc1, `delete2duplicates` = :delete2duplicates, `active` = :active
      WHERE `user2` = :user2 AND `id` = :id");
    $stmt->execute(array(
      ':user2' => $username,
      ':id' => $id,
      ':exclude' => $exclude,
      ':maxage' => $maxage,
      ':subfolder2' => $subfolder2,
      ':host1' => $host1,
      ':user1' => $user1,
      ':password1' => $password1,
      ':mins_interval' => $mins_interval,
      ':port1' => $port1,
      ':enc1' => $enc1,
      ':delete2duplicates' => $delete2duplicates,
      ':active' => $active,
    ));
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['mailbox_modified'], $username)
  );
  return true;
}
function edit_tls_policy($postarray) {
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
	isset($postarray['tls_in']) ? $tls_in = '1' : $tls_in = '0';
	isset($postarray['tls_out']) ? $tls_out = '1' : $tls_out = '0';
	$username = $_SESSION['mailcow_cc_username'];
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("UPDATE `mailbox` SET `tls_enforce_out` = :tls_out, `tls_enforce_in` = :tls_in WHERE `username` = :username");
		$stmt->execute(array(
			':tls_out' => $tls_out,
			':tls_in' => $tls_in,
			':username' => $username
		));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_modified'], $username)
	);
}
function get_tls_policy($username = null) {
	global $lang;
	global $pdo;
  $data = array();
  if (isset($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
	try {
		$stmt = $pdo->prepare("SELECT `tls_enforce_out`, `tls_enforce_in` FROM `mailbox` WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	return $data;
}
function edit_delimiter_action($postarray) {
  // Array items
  // 'username' can be set, defaults to mailcow_cc_username
	global $lang;
	global $pdo;
  if (isset($postarray['username']) && filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $postarray['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $postarray['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  ($postarray['tagged_mail_handler'] == "subject") ? $wants_tagged_subject = '1' : $wants_tagged_subject = '0';
  if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['username_invalid'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("UPDATE `mailbox` SET `wants_tagged_subject` = :wants_tagged_subject WHERE `username` = :username");
    $stmt->execute(array(':username' => $username, ':wants_tagged_subject' => $wants_tagged_subject));
    $SelectData = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['mailbox_modified'], $username)
  );
  return true;
}
function get_delimiter_action($username = null) {
  // 'username' can be set, defaults to mailcow_cc_username
	global $lang;
	global $pdo;
	$data = array();
  if (isset($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      return false;
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  try {
    $stmt = $pdo->prepare("SELECT `wants_tagged_subject` FROM `mailbox` WHERE `username` = :username");
    $stmt->execute(array(':username' => $username));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  return $data;
}
function user_get_alias_details($username) {
	global $lang;
	global $pdo;
  if ($_SESSION['mailcow_cc_role'] == "user") {
    $username	= $_SESSION['mailcow_cc_username'];
  }
  if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    return false;
  }
  try {
    $data['address'] = $username;
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`address` SEPARATOR ', '), '&#10008;') AS `aliases` FROM `alias` WHERE `goto` = :username_goto AND `address` NOT LIKE '@%' AND `address` != :username_address");
    $stmt->execute(array(':username_goto' => $username, ':username_address' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['aliases'] = $row['aliases'];
    }
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(local_part, '@', alias_domain SEPARATOR ', '), '&#10008;') AS `ad_alias` FROM `mailbox`
      LEFT OUTER JOIN `alias_domain` on `target_domain` = `domain`
        WHERE `username` = :username ;");
    $stmt->execute(array(':username' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['ad_alias'] = $row['ad_alias'];
    }
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`send_as` SEPARATOR ', '), '&#10008;') AS `send_as` FROM `sender_acl` WHERE `logged_in_as` = :username AND `send_as` NOT LIKE '@%';");
    $stmt->execute(array(':username' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['aliases_also_send_as'] = $row['send_as'];
    }
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`send_as` SEPARATOR ', '), '&#10008;') AS `send_as` FROM `sender_acl` WHERE `logged_in_as` = :username AND `send_as` LIKE '@%';");
    $stmt->execute(array(':username' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['aliases_send_as_all'] = $row['send_as'];
    }
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`address` SEPARATOR ', '), '&#10008;') as `address` FROM `alias` WHERE `goto` = :username AND `address` LIKE '@%';");
    $stmt->execute(array(':username' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['is_catch_all'] = $row['address'];
    }
    return $data;
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
}
function is_valid_domain_name($domain_name) { 
	if (empty($domain_name)) {
		return false;
	}
	$domain_name = idn_to_ascii($domain_name);
	return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name)
		   && preg_match("/^.{1,253}$/", $domain_name)
		   && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name));
}
?>
