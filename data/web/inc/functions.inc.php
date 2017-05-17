<?php
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
		if (verify_ssha256($row['password'], $pass)) {
      if (get_tfa($user)['name'] != "none") {
        $_SESSION['pending_mailcow_cc_username'] = $user;
        $_SESSION['pending_mailcow_cc_role'] = "admin";
        $_SESSION['pending_tfa_method'] = get_tfa($user)['name'];
        unset($_SESSION['ldelay']);
        return "pending";
      }
      else {
        unset($_SESSION['ldelay']);
        return "admin";
      }
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
      if (get_tfa($user)['name'] != "none") {
        $_SESSION['pending_mailcow_cc_username'] = $user;
        $_SESSION['pending_mailcow_cc_role'] = "domainadmin";
        $_SESSION['pending_tfa_method'] = get_tfa($user)['name'];
        unset($_SESSION['ldelay']);
        return "pending";
      }
      else {
        unset($_SESSION['ldelay']);
        $stmt = $pdo->prepare("UPDATE `tfa` SET `active`='1' WHERE `username` = :user");
        $stmt->execute(array(':user' => $user));
        return "domainadmin";
      }
		}
	}
	$stmt = $pdo->prepare("SELECT `password` FROM `mailbox`
			WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `active`='1'
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
function edit_admin_account($postarray) {
	global $lang;
	global $pdo;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$username       = $postarray['admin_user'];
	$username_now   = $_SESSION['mailcow_cc_username'];
  $password       = $postarray['admin_pass'];
  $password2      = $postarray['admin_pass2'];

	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username)) || empty ($username)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}

	if (!empty($password) && !empty($password2)) {
    if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['password_complexity'])
      );
      return false;
    }
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET 
				`password` = :password_hashed,
				`username` = :username1
					WHERE `username` = :username2");
			$stmt->execute(array(
				':password_hashed' => $password_hashed,
				':username1' => $username,
				':username2' => $username_now
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
				`username` = :username1
					WHERE `username` = :username2");
			$stmt->execute(array(
				':username1' => $username,
				':username2' => $username_now
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
		$stmt = $pdo->prepare("UPDATE `domain_admins` SET `domain` = 'ALL', `username` = :username1 WHERE `username` = :username2");
		$stmt->execute(array(':username1' => $username, ':username2' => $username_now));
		$stmt = $pdo->prepare("UPDATE `tfa` SET `username` = :username1 WHERE `username` = :username2");
		$stmt->execute(array(':username1' => $username, ':username2' => $username_now));
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
  $_SESSION['mailcow_cc_username'] = $username;
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

	switch ($postarray["set_time_limited_aliases"]) {
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

	if (isset($postarray['user_new_pass']) && isset($postarray['user_new_pass2'])) {
		$password_new	= $postarray['user_new_pass'];
		$password_new2	= $postarray['user_new_pass2'];
	}

	$stmt = $pdo->prepare("SELECT `password` FROM `mailbox`
			WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `username` = :user");
	$stmt->execute(array(':user' => $username));
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!verify_ssha256($row['password'], $password_old)) {
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
			if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password_new)) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => sprintf($lang['danger']['password_complexity'])
					);
					return false;
			}
			$password_hashed = hash_password($password_new);
			try {
				$stmt = $pdo->prepare("UPDATE `mailbox` SET `password` = :password_hashed WHERE `username` = :username");
				$stmt->execute(array(
					':password_hashed' => $password_hashed,
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
		'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($username))
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
        return false;
      }
    }
    elseif (filter_var($object, FILTER_VALIDATE_EMAIL)) {
      if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
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
  $object = (isset($postarray['username'])) ? $postarray['username'] : null;
  $object = (isset($postarray['domain'])) ? $postarray['domain'] : null;
  (!isset($object)) ? $object = $_SESSION['mailcow_cc_username'] : null;

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
  $object = (isset($postarray['username'])) ? $postarray['username'] : null;
  $object = (isset($postarray['domain'])) ? $postarray['domain'] : null;
  (!isset($object)) ? $object = $_SESSION['mailcow_cc_username'] : null;

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
  // 'username' can be set, if not set, defaults to mailcow_cc_username
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
    $stmt = $pdo->prepare("SELECT *,
      CONCAT(LEFT(`password1`, 3), '...') AS `password1_short`,
      `active` AS `active_int`,
      CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
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
			return false;
	}
  if (!is_numeric($id)) {
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

	$active  = intval($postarray['active']);
	$delete2duplicates = intval($postarray['delete2duplicates']);
	$delete1  = intval($postarray['delete1']);
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
    $stmt = $pdo->prepare("INSERT INTO `imapsync` (`user2`, `exclude`, `delete1`, `maxage`, `subfolder2`, `host1`, `authmech1`, `user1`, `password1`, `mins_interval`, `port1`, `enc1`, `delete2duplicates`, `active`)
      VALUES (:user2, :exclude, :maxage, :delete1, :subfolder2, :host1, :authmech1, :user1, :password1, :mins_interval, :port1, :enc1, :delete2duplicates, :active)");
    $stmt->execute(array(
      ':user2' => $username,
      ':exclude' => $exclude,
      ':maxage' => $maxage,
      ':delete1' => $delete1,
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
	global $lang;
	global $pdo;
  if (!is_array($postarray['id'])) {
    $ids = array();
    $ids[] = $postarray['id'];
  }
  else {
    $ids = $postarray['id'];
  }

  foreach ($ids as $id) {
    $is_now = get_syncjob_details($id);
    if (!empty($is_now)) {
      $username = $is_now['user2'];
      $user1 = (!empty($postarray['user1'])) ? $postarray['user1'] : $is_now['user1'];
      $active = (isset($postarray['active'])) ? $postarray['active'] : $is_now['active_int'];
      $delete2duplicates = (isset($postarray['delete2duplicates'])) ? $postarray['delete2duplicates'] : $is_now['delete2duplicates'];
      $delete1 = (isset($postarray['delete1'])) ? $postarray['delete1'] : $is_now['delete1'];
      $port1 = (!empty($postarray['port1'])) ? $postarray['port1'] : $is_now['port1'];
      $password1 = (!empty($postarray['password1'])) ? $postarray['password1'] : $is_now['password1'];
      $host1 = (!empty($postarray['host1'])) ? $postarray['host1'] : $is_now['host1'];
      $subfolder2 = (!empty($postarray['subfolder2'])) ? $postarray['subfolder2'] : $is_now['subfolder2'];
      $enc1 = (!empty($postarray['enc1'])) ? $postarray['enc1'] : $is_now['enc1'];
      $mins_interval = (!empty($postarray['mins_interval'])) ? $postarray['mins_interval'] : $is_now['mins_interval'];
      $exclude = (!empty($postarray['exclude'])) ? $postarray['exclude'] : $is_now['exclude'];
      $maxage = (!empty($postarray['maxage'])) ? $postarray['maxage'] : $is_now['maxage'];
    }
    else {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
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
      $stmt = $pdo->prepare("UPDATE `imapsync` SET `delete1` = :delete1, `maxage` = :maxage, `subfolder2` = :subfolder2, `exclude` = :exclude, `host1` = :host1, `user1` = :user1, `password1` = :password1, `mins_interval` = :mins_interval, `port1` = :port1, `enc1` = :enc1, `delete2duplicates` = :delete2duplicates, `active` = :active
        WHERE `id` = :id");
      $stmt->execute(array(
        ':delete1' => $delete1,
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
  $tls_out = intval($postarray['tls_out']);
  $tls_in = intval($postarray['tls_in']);
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
	global $redis;
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
  if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['username_invalid'])
    );
    return false;
  }
  if (isset($postarray['tagged_mail_handler']) && $postarray['tagged_mail_handler'] == "subject") {
    try {
      $redis->hSet('RCPT_WANTS_SUBJECT_TAG', $username, 1);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
  }
  else {
    try {
      $redis->hDel('RCPT_WANTS_SUBJECT_TAG', $username);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
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
	global $redis;
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
    if ($redis->hGet('RCPT_WANTS_SUBJECT_TAG', $username)) {
      return "subject";
    }
    else {
      return "subfolder";
    }
  }
  catch (RedisException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'Redis: '.$e
    );
    return false;
  }
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
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`address` SEPARATOR ', '), '&#10008;') AS `aliases` FROM `alias`
      WHERE `goto` REGEXP :username_goto
      AND `address` NOT LIKE '@%'
      AND `address` != :username_address");
    $stmt->execute(array(':username_goto' => '(^|,)'.$username.'($|,)', ':username_address' => $username));
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
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`address` SEPARATOR ', '), '&#10008;') as `address` FROM `alias` WHERE `goto` REGEXP :username AND `address` LIKE '@%';");
    $stmt->execute(array(':username' => '(^|,)'.$username.'($|,)'));
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
function add_domain_admin($postarray) {
	global $lang;
	global $pdo;
	$username		= strtolower(trim($postarray['username']));
	$password		= $postarray['password'];
	$password2  = $postarray['password2'];
  $active  = intval($postarray['active']);

	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (empty($postarray['domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username)) || empty ($username)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
			WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		
		$stmt = $pdo->prepare("SELECT `username` FROM `admin`
			WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		
		$stmt = $pdo->prepare("SELECT `username` FROM `domain_admins`
			WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
	foreach ($num_results as $num_results_each) {
		if ($num_results_each != 0) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
			);
			return false;
		}
	}
	if (!empty($password) && !empty($password2)) {
    if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['password_complexity'])
      );
      return false;
    }
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
		foreach ($postarray['domain'] as $domain) {
			if (!is_valid_domain_name($domain)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['domain_invalid'])
				);
				return false;
			}
			try {
				$stmt = $pdo->prepare("INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
						VALUES (:username, :domain, :created, :active)");
				$stmt->execute(array(
					':username' => $username,
					':domain' => $domain,
					':created' => date('Y-m-d H:i:s'),
					':active' => $active
				));
			}
			catch (PDOException $e) {
        delete_domain_admin(array('username' => $username));
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
				return false;
			}
		}
		try {
			$stmt = $pdo->prepare("INSERT INTO `admin` (`username`, `password`, `superadmin`, `active`)
				VALUES (:username, :password_hashed, '0', :active)");
			$stmt->execute(array(
				':username' => $username,
				':password_hashed' => $password_hashed,
				':active' => $active
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
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['password_empty'])
		);
		return false;
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_admin_added'], htmlspecialchars($username))
	);
}
function delete_domain_admin($postarray) {
	global $pdo;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$username = $postarray['username'];
	if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	try {
		$stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username,
		));
		$stmt = $pdo->prepare("DELETE FROM `admin` WHERE `username` = :username");
		$stmt->execute(array(
			':username' => $username,
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
		'msg' => sprintf($lang['success']['domain_admin_removed'], htmlspecialchars($username))
	);
}
function get_domain_admins() {
	global $pdo;
	global $lang;
  $domainadmins = array();
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
  try {
    $stmt = $pdo->query("SELECT DISTINCT
      `username`
        FROM `domain_admins` 
          WHERE `username` IN (
            SELECT `username` FROM `admin`
              WHERE `superadmin`!='1'
          )");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($rows)) {
      $domainadmins[] = $row['username'];
    }
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $domainadmins;
}
function get_domain_admin_details($domain_admin) {
	global $pdo;

	global $lang;
  $domainadmindata = array();
	if (isset($domain_admin) && $_SESSION['mailcow_cc_role'] != "admin") {
		return false;
	}
  if (!isset($domain_admin) && $_SESSION['mailcow_cc_role'] != "domainadmin") {
		return false;
	}
  (!isset($domain_admin)) ? $domain_admin = $_SESSION['mailcow_cc_username'] : null;
  
  if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $domain_admin))) {
		return false;
	}
  try {
    $stmt = $pdo->prepare("SELECT
      `tfa`.`active` AS `tfa_active_int`,
      CASE `tfa`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `tfa_active`,
      `domain_admins`.`username`,
      `domain_admins`.`created`,
      `domain_admins`.`active` AS `active_int`,
      CASE `domain_admins`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
        FROM `domain_admins`
        LEFT OUTER JOIN `tfa` ON `tfa`.`username`=`domain_admins`.`username`
          WHERE `domain_admins`.`username`= :domain_admin");
    $stmt->execute(array(
      ':domain_admin' => $domain_admin
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (empty($row)) { 
      return false;
    }
    $domainadmindata['username'] = $row['username'];
    $domainadmindata['tfa_active'] = $row['tfa_active'];
    $domainadmindata['active'] = $row['active'];
    $domainadmindata['tfa_active_int'] = $row['tfa_active_int'];
    $domainadmindata['active_int'] = $row['active_int'];
    $domainadmindata['modified'] = $row['created'];
    // GET SELECTED
    $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
      WHERE `domain` IN (
        SELECT `domain` FROM `domain_admins`
          WHERE `username`= :domain_admin)");
    $stmt->execute(array(':domain_admin' => $domain_admin));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while($row = array_shift($rows)) {
      $domainadmindata['selected_domains'][] = $row['domain'];
    }
    // GET UNSELECTED
    $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
      WHERE `domain` NOT IN (
        SELECT `domain` FROM `domain_admins`
          WHERE `username`= :domain_admin)");
    $stmt->execute(array(':domain_admin' => $domain_admin));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while($row = array_shift($rows)) {
      $domainadmindata['unselected_domains'][] = $row['domain'];
    }
    if (!isset($domainadmindata['unselected_domains'])) {
      $domainadmindata['unselected_domains'] = "";
    }
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $domainadmindata;
}
function set_tfa($postarray) {
	global $lang;
	global $pdo;
	global $yubi;
	global $u2f;
	global $tfa;

  if ($_SESSION['mailcow_cc_role'] != "domainadmin" &&
    $_SESSION['mailcow_cc_role'] != "admin") {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
  }
  $username = $_SESSION['mailcow_cc_username'];
  
  $stmt = $pdo->prepare("SELECT `password` FROM `admin`
      WHERE `username` = :user");
  $stmt->execute(array(':user' => $username));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!verify_ssha256($row['password'], $postarray["confirm_password"])) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  
	switch ($postarray["tfa_method"]) {
		case "yubi_otp":
      $key_id = (!isset($postarray["key_id"])) ? 'unidentified' : $postarray["key_id"];
      $yubico_id = $postarray['yubico_id'];
      $yubico_key = $postarray['yubico_key'];
      $yubi = new Auth_Yubico($yubico_id, $yubico_key);
      if (!$yubi) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
			if (!ctype_alnum($postarray["otp_token"]) || strlen($postarray["otp_token"]) != 44) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['tfa_token_invalid'])
				);
				return false;
			}
      $yauth = $yubi->verify($postarray["otp_token"]);
      if (PEAR::isError($yauth)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'Yubico API: ' . $yauth->getMessage()
				);
				return false;
      }
			try {
        // We could also do a modhex translation here
        $yubico_modhex_id = substr($postarray["otp_token"], 0, 12);
        $stmt = $pdo->prepare("DELETE FROM `tfa` 
          WHERE `username` = :username
            AND (`authmech` != 'yubi_otp')
            OR (`authmech` = 'yubi_otp' AND `secret` LIKE :modhex)");
				$stmt->execute(array(':username' => $username, ':modhex' => '%' . $yubico_modhex_id));
				$stmt = $pdo->prepare("INSERT INTO `tfa` (`key_id`, `username`, `authmech`, `active`, `secret`) VALUES
					(:key_id, :username, 'yubi_otp', '1', :secret)");
				$stmt->execute(array(':key_id' => $key_id, ':username' => $username, ':secret' => $yubico_id . ':' . $yubico_key . ':' . $yubico_modhex_id));
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
				'msg' => sprintf($lang['success']['object_modified'], htmlspecialchars($username))
			);
		break;

		case "u2f":
      $key_id = (!isset($postarray["key_id"])) ? 'unidentified' : $postarray["key_id"];
      try {
        $reg = $u2f->doRegister(json_decode($_SESSION['regReq']), json_decode($postarray['token']));
        $stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username AND `authmech` != 'u2f'");
				$stmt->execute(array(':username' => $username));
        $stmt = $pdo->prepare("INSERT INTO `tfa` (`username`, `key_id`, `authmech`, `keyHandle`, `publicKey`, `certificate`, `counter`, `active`) VALUES (?, ?, 'u2f', ?, ?, ?, ?, '1')");
        $stmt->execute(array($username, $key_id, $reg->keyHandle, $reg->publicKey, $reg->certificate, $reg->counter));
        $_SESSION['return'] = array(
          'type' => 'success',
          'msg' => sprintf($lang['success']['object_modified'], $username)
        );
        $_SESSION['regReq'] = null;
      }
      catch (Exception $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => "U2F: " . $e->getMessage()
        );
        $_SESSION['regReq'] = null;
        return false;
      }
		break;

		case "totp":
      $key_id = (!isset($postarray["key_id"])) ? 'unidentified' : $postarray["key_id"];
      if ($tfa->verifyCode($_POST['totp_secret'], $_POST['totp_confirm_token']) === true) {
        try {
        $stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username");
        $stmt->execute(array(':username' => $username));
        $stmt = $pdo->prepare("INSERT INTO `tfa` (`username`, `key_id`, `authmech`, `secret`, `active`) VALUES (?, ?, 'totp', ?, '1')");
        $stmt->execute(array($username, $key_id, $_POST['totp_secret']));
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
          'msg' => sprintf($lang['success']['object_modified'], $username)
        );
      }
      else {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'TOTP verification failed'
        );
      }
		break;

		case "none":
			try {
				$stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username");
				$stmt->execute(array(':username' => $username));
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
				'msg' => sprintf($lang['success']['object_modified'], htmlspecialchars($username))
			);
		break;
	}
}
function unset_tfa_key($postarray) {
  // Can only unset own keys
  // Needs at least one key left
  global $pdo;
  global $lang;
  $id = intval($postarray['unset_tfa_key']);
  if ($_SESSION['mailcow_cc_role'] != "domainadmin" &&
    $_SESSION['mailcow_cc_role'] != "admin") {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
  }
  $username = $_SESSION['mailcow_cc_username'];
  try {
    if (!is_numeric($id)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) AS `keys` FROM `tfa`
      WHERE `username` = :username AND `active` = '1'");
    $stmt->execute(array(':username' => $username));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['keys'] == "1") {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['last_key'])
      );
      return false;
    }
    $stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username AND `id` = :id");
    $stmt->execute(array(':username' => $username, ':id' => $id));
    $_SESSION['return'] = array(
      'type' => 'success',
      'msg' => sprintf($lang['success']['object_modified'], $username)
    );
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
}
function get_tfa($username = null) {
	global $pdo;
  if (isset($_SESSION['mailcow_cc_username'])) {
    $username = $_SESSION['mailcow_cc_username'];
  }
  elseif (empty($username)) {
    return false;
  }

  $stmt = $pdo->prepare("SELECT * FROM `tfa`
      WHERE `username` = :username AND `active` = '1'");
  $stmt->execute(array(':username' => $username));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  
	switch ($row["authmech"]) {
		case "yubi_otp":
      $data['name'] = "yubi_otp";
      $data['pretty'] = "Yubico OTP";
      $stmt = $pdo->prepare("SELECT `id`, `key_id`, RIGHT(`secret`, 12) AS 'modhex' FROM `tfa` WHERE `authmech` = 'yubi_otp' AND `username` = :username");
      $stmt->execute(array(
        ':username' => $username,
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $data['additional'][] = $row;
      }
      return $data;
    break;
		case "u2f":
      $data['name'] = "u2f";
      $data['pretty'] = "Fido U2F";
      $stmt = $pdo->prepare("SELECT `id`, `key_id` FROM `tfa` WHERE `authmech` = 'u2f' AND `username` = :username");
      $stmt->execute(array(
        ':username' => $username,
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $data['additional'][] = $row;
      }
      return $data;
    break;
		case "hotp":
      $data['name'] = "hotp";
      $data['pretty'] = "HMAC-based OTP";
      return $data;
		break;
 		case "totp":
      $data['name'] = "totp";
      $data['pretty'] = "Time-based OTP";
      $stmt = $pdo->prepare("SELECT `id`, `key_id`, `secret` FROM `tfa` WHERE `authmech` = 'totp' AND `username` = :username");
      $stmt->execute(array(
        ':username' => $username,
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $data['additional'][] = $row;
      }
      return $data;
      break;
    default:
      $data['name'] = 'none';
      $data['pretty'] = "-";
      return $data;
    break;
	}
}
function verify_tfa_login($username, $token) {
	global $pdo;
	global $lang;
	global $yubi;
	global $u2f;
	global $tfa;

  $stmt = $pdo->prepare("SELECT `authmech` FROM `tfa`
      WHERE `username` = :username AND `active` = '1'");
  $stmt->execute(array(':username' => $username));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  
	switch ($row["authmech"]) {
		case "yubi_otp":
			if (!ctype_alnum($token) || strlen($token) != 44) {
        return false;
      }
      $yubico_modhex_id = substr($token, 0, 12);
      $stmt = $pdo->prepare("SELECT `id`, `secret` FROM `tfa`
          WHERE `username` = :username
          AND `authmech` = 'yubi_otp'
          AND `active`='1'
          AND `secret` LIKE :modhex");
      $stmt->execute(array(':username' => $username, ':modhex' => '%' . $yubico_modhex_id));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $yubico_auth = explode(':', $row['secret']);
      $yubi = new Auth_Yubico($yubico_auth[0], $yubico_auth[1]);
      $yauth = $yubi->verify($token);
      if (PEAR::isError($yauth)) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'Yubico Authentication error: ' . $yauth->getMessage()
				);
				return false;
      }
      else {
        $_SESSION['tfa_id'] = $row['id'];
        return true;
      }
    return false;
  break;
  case "u2f":
    try {
      $reg = $u2f->doAuthenticate(json_decode($_SESSION['authReq']), get_u2f_registrations($username), json_decode($token));
      $stmt = $pdo->prepare("UPDATE `tfa` SET `counter` = ? WHERE `id` = ?");
      $stmt->execute(array($reg->counter, $reg->id));
      $_SESSION['tfa_id'] = $reg->id;
      $_SESSION['authReq'] = null;
      return true;
    }
    catch (Exception $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => "U2F: " . $e->getMessage()
      );
      $_SESSION['regReq'] = null;
      return false;
    }
    return false;
  break;
  case "hotp":
      return false;
  break;
  case "totp":
    try {
      $stmt = $pdo->prepare("SELECT `id`, `secret` FROM `tfa`
          WHERE `username` = :username
          AND `authmech` = 'totp'
          AND `active`='1'");
      $stmt->execute(array(':username' => $username));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($tfa->verifyCode($row['secret'], $_POST['token']) === true) {
        $_SESSION['tfa_id'] = $row['id'];
        return true;
      }
      return false;
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
  break;
  default:
      return false;
  break;
	}
  return false;
}
function edit_domain_admin($postarray) {
	global $lang;
	global $pdo;

	if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	// Administrator
  if ($_SESSION['mailcow_cc_role'] == "admin") {
    $username     = $postarray['username'];
    $username_now = $postarray['username_now'];
    $password     = $postarray['password'];
    $password2    = $postarray['password2'];
    $active       = intval($postarray['active']);

    if(isset($postarray['domain'])) {
      foreach ($postarray['domain'] as $domain) {
        if (!is_valid_domain_name($domain)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['domain_invalid'])
          );
          return false;
        }
      }
    }

    if (empty($postarray['domain'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_invalid'])
      );
      return false;
    }

    if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['username_invalid'])
      );
      return false;
    }
    if ($username != $username_now) {
      if (empty(get_domain_admin_details($username_now)['username']) || !empty(get_domain_admin_details($username)['username'])) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['username_invalid'])
        );
        return false;
      }
    }
    try {
      $stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `username` = :username");
      $stmt->execute(array(
        ':username' => $username_now,
      ));
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }

    if (isset($postarray['domain'])) {
      foreach ($postarray['domain'] as $domain) {
        try {
          $stmt = $pdo->prepare("INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
            VALUES (:username, :domain, :created, :active)");
          $stmt->execute(array(
            ':username' => $username,
            ':domain' => $domain,
            ':created' => date('Y-m-d H:i:s'),
            ':active' => $active
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

    if (!empty($password) && !empty($password2)) {
      if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['password_complexity'])
        );
        return false;
      }
      if ($password != $password2) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['password_mismatch'])
        );
        return false;
      }
      $password_hashed = hash_password($password);
      try {
        $stmt = $pdo->prepare("UPDATE `admin` SET `username` = :username1, `active` = :active, `password` = :password_hashed WHERE `username` = :username2");
        $stmt->execute(array(
          ':password_hashed' => $password_hashed,
          ':username1' => $username,
          ':username2' => $username_now,
          ':active' => $active
        ));
        if (isset($postarray['disable_tfa'])) {
          $stmt = $pdo->prepare("UPDATE `tfa` SET `active` = '0' WHERE `username` = :username");
          $stmt->execute(array(':username' => $username_now));
        }
        else {
          $stmt = $pdo->prepare("UPDATE `tfa` SET `username` = :username WHERE `username` = :username_now");
          $stmt->execute(array(':username' => $username, ':username_now' => $username_now));
        }
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
        $stmt = $pdo->prepare("UPDATE `admin` SET `username` = :username1, `active` = :active WHERE `username` = :username2");
        $stmt->execute(array(
          ':username1' => $username,
          ':username2' => $username_now,
          ':active' => $active
        ));
        if (isset($postarray['disable_tfa'])) {
          $stmt = $pdo->prepare("UPDATE `tfa` SET `active` = '0' WHERE `username` = :username");
          $stmt->execute(array(':username' => $username));
        }
        else {
          $stmt = $pdo->prepare("UPDATE `tfa` SET `username` = :username WHERE `username` = :username_now");
          $stmt->execute(array(':username' => $username, ':username_now' => $username_now));
        }
      }
      catch (PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
    }
    $_SESSION['return'] = array(
      'type' => 'success',
      'msg' => sprintf($lang['success']['domain_admin_modified'], htmlspecialchars($username))
    );
  }
  // Domain administrator
  // Can only edit itself
  elseif ($_SESSION['mailcow_cc_role'] == "domainadmin") {
    $username = $_SESSION['mailcow_cc_username'];
    $password_old		= $postarray['user_old_pass'];
    $password_new	= $postarray['user_new_pass'];
    $password_new2	= $postarray['user_new_pass2'];

    $stmt = $pdo->prepare("SELECT `password` FROM `admin`
        WHERE `username` = :user");
    $stmt->execute(array(':user' => $username));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!verify_ssha256($row['password'], $password_old)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }

    if (!empty($password_new2) && !empty($password_new)) {
      if ($password_new2 != $password_new) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['password_mismatch'])
        );
        return false;
      }
      if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password_new)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['password_complexity'])
        );
        return false;
      }
      $password_hashed = hash_password($password_new);
      try {
        $stmt = $pdo->prepare("UPDATE `admin` SET `password` = :password_hashed WHERE `username` = :username");
        $stmt->execute(array(
          ':password_hashed' => $password_hashed,
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
    
    $_SESSION['return'] = array(
      'type' => 'success',
      'msg' => sprintf($lang['success']['domain_admin_modified'], htmlspecialchars($username))
    );
  }
}
function get_admin_details() {
  // No parameter to be given, only one admin should exist
	global $pdo;
	global $lang;
  $data = array();
  if ($_SESSION['mailcow_cc_role'] != 'admin') {
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT `username`, `modified`, `created` FROM `admin` WHERE `superadmin`='1' AND active='1'");
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
  }
  return $data;
}
function dkim_add_key($postarray) {
	global $lang;
	global $pdo;
	global $redis;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  // if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    // $_SESSION['return'] = array(
      // 'type' => 'danger',
      // 'msg' => sprintf($lang['danger']['access_denied'])
    // );
    // return false;
  // }
  $key_length	= intval($postarray['key_size']);
  $dkim_selector = (isset($postarray['dkim_selector'])) ? $postarray['dkim_selector'] : 'dkim';
  $domain	= $postarray['domain'];
  if (!is_valid_domain_name($domain) || !is_numeric($key_length)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
    );
    return false;
  }

  if (!empty(glob($GLOBALS['MC_DKIM_TXTS'] . '/' . $domain . '.dkim')) ||
    $redis->hGet('DKIM_PUB_KEYS', $domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
      );
      return false;
  }

  if (!ctype_alnum($dkim_selector)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
    );
    return false;
  }

  $config = array(
    "digest_alg" => "sha256",
    "private_key_bits" => $key_length,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
  );
  if ($keypair_ressource = openssl_pkey_new($config)) {
    $key_details = openssl_pkey_get_details($keypair_ressource);
    $pubKey = implode(array_slice(
        array_filter(
          explode(PHP_EOL, $key_details['key'])
        ), 1, -1)
      );
    // Save public key and selector to redis
    try {
      $redis->hSet('DKIM_PUB_KEYS', $domain, $pubKey);
      $redis->hSet('DKIM_SELECTORS', $domain, $dkim_selector);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
    // Export private key and save private key to redis
    openssl_pkey_export($keypair_ressource, $privKey);
    if (isset($privKey) && !empty($privKey)) {
      try {
        $redis->hSet('DKIM_PRIV_KEYS', $dkim_selector . '.' . $domain, trim($privKey));
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
    }
    $_SESSION['return'] = array(
      'type' => 'success',
      'msg' => sprintf($lang['success']['dkim_added'])
    );
    return true;
  }
  else {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
    );
    return false;
  }
}
function dkim_get_key_details($domain) {
  global $redis;
  if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    return false;
  }
  $data = array();
  if ($redis_dkim_key_data = $redis->hGet('DKIM_PUB_KEYS', $domain)) {
    $data['pubkey'] = $redis_dkim_key_data;
    $data['length'] = (strlen($data['pubkey']) < 391) ? 1024 : 2048;
    $data['dkim_txt'] = 'v=DKIM1;k=rsa;t=s;s=email;p=' . $redis_dkim_key_data;
    $data['dkim_selector'] = $redis->hGet('DKIM_SELECTORS', $domain);
  }
  return $data;
}
function dkim_get_blind_keys() {
  global $redis;
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    return false;
  }
  $domains = array();
  foreach ($redis->hKeys('DKIM_PUB_KEYS') as $redis_dkim_domain) {
    $domains[] = $redis_dkim_domain;
  }
  return array_diff($domains, array_merge(mailbox_get_domains(), mailbox_get_alias_domains()));
}
function dkim_delete_key($postarray) {
	global $redis;
	global $lang;
  if (!is_array($postarray['domains'])) {
    $domains = array();
    $domains[] = $postarray['domains'];
  }
  else {
    $domains = $postarray['domains'];
  }
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  foreach ($domains as $domain) {
    if (!is_valid_domain_name($domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
      );
      return false;
    }
    try {
      $selector = $redis->hGet('DKIM_SELECTORS', $domain);
      $redis->hDel('DKIM_PUB_KEYS', $domain);
      $redis->hDel('DKIM_PRIV_KEYS', $selector . '.' . $domain);
      $redis->hDel('DKIM_SELECTORS', $domain);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['dkim_removed'], htmlspecialchars(implode(', ', $domains)))
  );
  return true;
}
function mailbox_add_domain($postarray) {
  // Array elements
  // domain                 string
  // description            string
  // aliases                int
  // mailboxes              int
  // maxquota               int
  // quota                  int
  // active                 int
  // relay_all_recipients   int
  // backupmx               int
	global $pdo;
	global $redis;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$domain				= idn_to_ascii(strtolower(trim($postarray['domain'])));
	$description  = $postarray['description'];
	$aliases			= $postarray['aliases'];
	$mailboxes    = $postarray['mailboxes'];
	$maxquota			= $postarray['maxquota'];
	$quota				= $postarray['quota'];

	if ($maxquota > $quota) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
		);
		return false;
	}

	if ($maxquota == "0" || empty($maxquota)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['maxquota_empty'])
		);
		return false;
	}

  $active = intval($postarray['active']);
  $relay_all_recipients = intval($postarray['relay_all_recipients']);
  $backupmx = intval($postarray['backupmx']);
  ($relay_all_recipients == 1) ? $backupmx = '1' : null;

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	foreach (array($quota, $maxquota, $mailboxes, $aliases) as $data) {
		if (!is_numeric($data)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['object_is_not_numeric'], htmlspecialchars($data))
			);
			return false;
		}
	}

	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
			WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
		$stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain`
			WHERE `alias_domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = $num_results + count($stmt->fetchAll(PDO::FETCH_ASSOC));
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
			'msg' => sprintf($lang['danger']['domain_exists'], htmlspecialchars($domain))
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO `domain` (`domain`, `description`, `aliases`, `mailboxes`, `maxquota`, `quota`, `transport`, `backupmx`, `active`, `relay_all_recipients`)
			VALUES (:domain, :description, :aliases, :mailboxes, :maxquota, :quota, 'virtual', :backupmx, :active, :relay_all_recipients)");
		$stmt->execute(array(
			':domain' => $domain,
			':description' => $description,
			':aliases' => $aliases,
			':mailboxes' => $mailboxes,
			':maxquota' => $maxquota,
			':quota' => $quota,
			':backupmx' => $backupmx,
			':active' => $active,
			':relay_all_recipients' => $relay_all_recipients
		));
    try {
      $redis->hSet('DOMAIN_MAP', $domain, 1);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['domain_added'], htmlspecialchars($domain))
		);
	}
	catch (PDOException $e) {
    mailbox_delete_domain(array('domain' => $domain));
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_add_alias($postarray) {
  // Array elements
  // address  string  (separated by " ", "," ";" "\n") - email address or domain
  // goto     string  (separated by " ", "," ";" "\n")
  // active   int
	global $lang;
	global $pdo;
	$addresses  = array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['address']));
	$gotos      = array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['goto']));
  $active = intval($postarray['active']);
	if (empty($addresses[0])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_empty'])
		);
		return false;
	}

	if (empty($gotos[0])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['goto_empty'])
		);
		return false;
	}
	foreach ($addresses as $address) {
		if (empty($address)) {
			continue;
		}

    $stmt = $pdo->prepare("SELECT `address` FROM `alias`
      WHERE `address`= :address");
    $stmt->execute(array(':address' => $address));
    $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['is_alias_or_mailbox'], htmlspecialchars($address))
      );
      return false;
    }

		$domain       = idn_to_ascii(substr(strstr($address, '@'), 1));
		$local_part   = strstr($address, '@', true);
		$address      = $local_part.'@'.$domain;

    $domaindata = mailbox_get_domain_details($domain);
    if (is_array($domaindata) && $domaindata['aliases_left'] == "0") {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['max_alias_exceeded'])
      );
      return false;
    }

		try {
			$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
				WHERE `domain`= :domain1 OR `domain` = (SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain2)");
			$stmt->execute(array(':domain1' => $domain, ':domain2' => $domain));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($num_results == 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['domain_not_found'], htmlspecialchars($domain))
        );
        return false;
      }

			$stmt = $pdo->prepare("SELECT `address` FROM `alias`
				WHERE `address`= :address");
			$stmt->execute(array(':address' => $address));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($num_results != 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['is_alias_or_mailbox'], htmlspecialchars($address))
        );
        return false;
      }

			$stmt = $pdo->prepare("SELECT `address` FROM `spamalias`
				WHERE `address`= :address");
			$stmt->execute(array(':address' => $address));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($num_results != 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($address))
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

		if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['alias_invalid'])
			);
			return false;
		}

		if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['access_denied'])
			);
			return false;
		}

		foreach ($gotos as &$goto) {
			if (empty($goto)) {
				continue;
			}

			$goto_domain		= idn_to_ascii(substr(strstr($goto, '@'), 1));
			$goto_local_part	= strstr($goto, '@', true);
			$goto				= $goto_local_part.'@'.$goto_domain;

			$stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
				WHERE `kind` REGEXP 'location|thing|group'
          AND `username`= :goto");
			$stmt->execute(array(':goto' => $goto));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($num_results != 0) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['goto_invalid'])
				);
				return false;
      }

			if (!filter_var($goto, FILTER_VALIDATE_EMAIL) === true) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['goto_invalid'])
				);
				return false;
			}
			if ($goto == $address) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => sprintf($lang['danger']['alias_goto_identical'])
				);
				return false;
			}
		}

		$gotos = array_filter($gotos);
		$goto = implode(",", $gotos);

		try {
			$stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `active`)
				VALUES (:address, :goto, :domain, :active)");

			if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
				$stmt->execute(array(
					':address' => '@'.$domain,
					':goto' => $goto,
					':domain' => $domain,
					':active' => $active
				));
			}
			else {
				$stmt->execute(array(
					':address' => $address,
					':goto' => $goto,
					':domain' => $domain,
					':active' => $active
				));
			}
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['alias_added'])
			);
		}
		catch (PDOException $e) {
      mailbox_delete_alias(array('address' => $address));
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => 'MySQL: '.$e
			);
			return false;
		}
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_added'])
	);
}
function mailbox_add_alias_domain($postarray) {
  // Array elements
  // active         int
  // alias_domain   string
  // target_domain  string
	global $lang;
	global $pdo;
  $active = intval($postarray['active']);
	$alias_domain     = idn_to_ascii(strtolower(trim($postarray['alias_domain'])));
	$target_domain    = idn_to_ascii(strtolower(trim($postarray['target_domain'])));

	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	if (!is_valid_domain_name($target_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['target_domain_invalid'])
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $target_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	if ($alias_domain == $target_domain) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
			WHERE `domain`= :target_domain");
		$stmt->execute(array(':target_domain' => $target_domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results == 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['targetd_not_found'])
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain`= :alias_domain
			UNION
			SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain`= :alias_domain_in_domain");
		$stmt->execute(array(':alias_domain' => $alias_domain, ':alias_domain_in_domain' => $alias_domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['aliasd_exists'])
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
		$stmt = $pdo->prepare("INSERT INTO `alias_domain` (`alias_domain`, `target_domain`, `active`)
			VALUES (:alias_domain, :target_domain, :active)");
		$stmt->execute(array(
			':alias_domain' => $alias_domain,
			':target_domain' => $target_domain,
			':active' => $active
		));
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['aliasd_added'], htmlspecialchars($alias_domain))
		);
	}
	catch (PDOException $e) {
    mailbox_delete_alias_domain(array('alias_domain' => $alias_domain));
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_add_mailbox($postarray) {
  // Array elements
  // active             int
  // local_part         string
  // domain             string
  // name               string    (username if empty)
  // password           string
  // password2          string
  // quota              int       (MiB)
  // active             int

	global $pdo;
	global $lang;
	$local_part   = strtolower(trim($postarray['local_part']));
	$domain       = idn_to_ascii(strtolower(trim($postarray['domain'])));
  $username     = $local_part . '@' . $domain;
	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	if (empty($postarray['local_part'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_invalid'])
		);
		return false;
	}
	$password     = $postarray['password'];
	$password2    = $postarray['password2'];
	$name         = $postarray['name'];
  $quota_m			= filter_var($postarray['quota'], FILTER_SANITIZE_NUMBER_FLOAT);

	if (empty($name)) {
		$name = $local_part;
	}

  $active = intval($postarray['active']);

	$quota_b		= ($quota_m * 1048576);
	$maildir		= $domain."/".$local_part."/";

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `mailboxes`, `maxquota`, `quota` FROM `domain`
			WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$DomainData = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT 
			COUNT(*) as count,
			COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota`
				FROM `mailbox`
					WHERE `kind` NOT REGEXP 'location|thing|group'
            AND `domain` = :domain");
		$stmt->execute(array(':domain' => $domain));
		$MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT `local_part` FROM `mailbox` WHERE `local_part` = :local_part and `domain`= :domain");
		$stmt->execute(array(':local_part' => $local_part, ':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE address= :username");
		$stmt->execute(array(':username' => $username));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($username))
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `address` FROM `spamalias` WHERE `address`= :username");
		$stmt->execute(array(':username' => $username));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($username))
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain`= :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results == 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_not_found'], $domain)
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

	if (!is_numeric($quota_m) || $quota_m == "0") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'])
		);
		return false;
	}

	if (!empty($password) && !empty($password2)) {
    if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['password_complexity'])
      );
      return false;
    }
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
	}
	else {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['password_empty'])
		);
		return false;
	}

	if ($MailboxData['count'] >= $DomainData['mailboxes']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['max_mailbox_exceeded'], $MailboxData['count'], $DomainData['mailboxes'])
		);
		return false;
	}

	if ($quota_m > $DomainData['maxquota']) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $DomainData['maxquota'])
		);
		return false;
	}

	if (($MailboxData['quota'] + $quota_m) > $DomainData['quota']) {
		$quota_left_m = ($DomainData['quota'] - $MailboxData['quota']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `active`) 
			VALUES (:username, :password_hashed, :name, :maildir, :quota_b, :local_part, :domain, :active)");
		$stmt->execute(array(
			':username' => $username,
			':password_hashed' => $password_hashed,
			':name' => $name,
			':maildir' => $maildir,
			':quota_b' => $quota_b,
			':local_part' => $local_part,
			':domain' => $domain,
			':active' => $active
		));

		$stmt = $pdo->prepare("INSERT INTO `quota2` (`username`, `bytes`, `messages`)
			VALUES (:username, '0', '0')");
		$stmt->execute(array(':username' => $username));

		$stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `active`)
			VALUES (:username1, :username2, :domain, :active)");
		$stmt->execute(array(
			':username1' => $username,
			':username2' => $username,
			':domain' => $domain,
			':active' => $active
		));

		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_added'], htmlspecialchars($username))
		);
	}
	catch (PDOException $e) {
    mailbox_delete_mailbox(array('username' => $username));
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_add_resource($postarray) {
  // Array elements
  // active             int
  // domain             string
  // description        string
  // multiple_bookings  int
  // kind               string

	global $pdo;
	global $lang;
	$domain             = idn_to_ascii(strtolower(trim($postarray['domain'])));
  $description        = $postarray['description'];
  $local_part         = preg_replace('/[^\da-z]/i', '', preg_quote($description, '/'));
  $name               = $local_part . '@' . $domain;
  $kind               = $postarray['kind'];
  $active = intval($postarray['active']);
  $multiple_bookings = intval($postarray['multiple_bookings']);

	if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['resource_invalid'])
		);
		return false;
	}

	if (empty($description)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['description_invalid'])
		);
		return false;
  }
  
	if ($kind != 'location' && $kind != 'group' && $kind != 'thing') {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['resource_invalid'])
		);
		return false;
	}

	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :name");
		$stmt->execute(array(':name' => $name));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($name))
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE address= :name");
		$stmt->execute(array(':name' => $name));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($name))
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `address` FROM `spamalias` WHERE `address`= :name");
		$stmt->execute(array(':name' => $name));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($name))
      );
      return false;
    }

		$stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain`= :domain");
		$stmt->execute(array(':domain' => $domain));
		$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results == 0) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_not_found'], $domain)
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
		$stmt = $pdo->prepare("INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `active`, `multiple_bookings`, `kind`) 
			VALUES (:name, 'RESOURCE', :description, 'RESOURCE', 0, :local_part, :domain, :active, :multiple_bookings, :kind)");
		$stmt->execute(array(
			':name' => $name,
			':description' => $description,
			':local_part' => $local_part,
			':domain' => $domain,
			':active' => $active,
			':kind' => $kind,
			':multiple_bookings' => $multiple_bookings
		));

		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['resource_added'], htmlspecialchars($name))
		);
	}
	catch (PDOException $e) {
    mailbox_delete_resource(array('name' => $name));
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_edit_alias_domain($postarray) {
  // active         int
  // target_domain  string
  // alias_domain   string/array of strings
	global $lang;
	global $pdo;
  if (!is_array($postarray['alias_domain'])) {
    $alias_domains = array();
    $alias_domains[] = $postarray['alias_domain'];
  }
  else {
    $alias_domains = $postarray['alias_domain'];
  }
  foreach ($alias_domains as $alias_domain) {
    $alias_domain = idn_to_ascii(strtolower(trim($alias_domain)));
    $is_now = mailbox_get_alias_domain_details($alias_domain);
    if (!empty($is_now)) {
      $active         = (isset($postarray['active'])) ? $postarray['active'] : $is_now['active_int'];
      $target_domain  = (!empty($postarray['target_domain'])) ? idn_to_ascii(strtolower(trim($postarray['target_domain']))) : $is_now['target_domain'];
    }
    else {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['alias_domain_invalid'])
      );
      return false;
    }
    if (!is_valid_domain_name($target_domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['target_domain_invalid'])
      );
      return false;
    }
    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $target_domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    if (empty(mailbox_get_domain_details($target_domain))) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['target_domain_invalid'])
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("UPDATE `alias_domain` SET
        `target_domain` = :target_domain,
        `active` = :active
          WHERE `alias_domain` = :alias_domain");
      $stmt->execute(array(
        ':alias_domain' => $alias_domain,
        ':target_domain' => $target_domain,
        ':active' => $active
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['aliasd_modified'], htmlspecialchars(implode(', ', $alias_domains)))
	);
}
function mailbox_edit_alias($postarray) {
  // We can edit multiple addresses at once, but only set one "goto" and/or "active" attribute for all
  // address    string or array containing strings | email | required
  // goto       string | separated by " ", "," ";" "\n", email or domain | optional
  // active     set (active) or unset (inactive)
	global $lang;
	global $pdo;
  if (!is_array($postarray['address'])) {
    $addresses = array();
    $addresses[] = $postarray['address'];
  }
  else {
    $addresses = $postarray['address'];
  }
  foreach ($addresses as $address) {
    $is_now = mailbox_get_alias_details($address);
    if (!empty($is_now)) {
      $active = (isset($postarray['active'])) ? $postarray['active'] : $is_now['active_int'];
      $goto   = (!empty($postarray['goto'])) ? $postarray['goto'] : $is_now['goto'];
    }
    else {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['alias_invalid'])
      );
      return false;
    }
    
    $gotos = array_map('trim', preg_split( "/( |,|;|\n)/", $postarray['goto']));
    foreach ($gotos as &$goto) {
      if (empty($goto)) {
        continue;
      }
      if (!filter_var($goto, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' =>sprintf($lang['danger']['goto_invalid'])
        );
        return false;
      }
      if ($goto == $address) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['alias_goto_identical'])
        );
        return false;
      }
    }
    $gotos = array_filter($gotos);
    $goto = implode(",", $gotos);
    
    $domain = idn_to_ascii(substr(strstr($address, '@'), 1));
    $local_part = strstr($address, '@', true);

    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }

    if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['alias_invalid'])
      );
      return false;
    }

    try {
      if (!empty($goto)) {
        $stmt = $pdo->prepare("UPDATE `alias` SET
          `goto` = :goto,
          `active`= :active
            WHERE `address` = :address");
        $stmt->execute(array(
          ':goto' => $goto,
          ':active' => $active,
          ':address' => $address
        ));
      }
      else {
        $stmt = $pdo->prepare("UPDATE `alias` SET
          `active`= :active
            WHERE `address` = :address");
        $stmt->execute(array(
          ':active' => $active,
          ':address' => $address
        ));
      }
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
	}
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['alias_modified'], htmlspecialchars(implode(', ', $addresses)))
  );
}
function mailbox_edit_domain($postarray) {
  // Array elements
  // domain                 string
  // description            string
  // active                 int
  // relay_all_recipients   int
  // backupmx               int
  // aliases                float
  // mailboxes              float
  // maxquota               float
  // quota                  float (Byte)
  // active                 int

	global $lang;
	global $pdo;
  
  if (!is_array($postarray['domain'])) {
    $domains = array();
    $domains[] = $postarray['domain'];
  }
  else {
    $domains = $postarray['domain'];
  }
  foreach ($domains as $domain) {
    $domain = idn_to_ascii($domain);
    if (!is_valid_domain_name($domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_invalid'])
      );
      return false;
    }
    if ($_SESSION['mailcow_cc_role'] == "domainadmin" &&
    hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
      $description  = $postarray['description'];
      $active = intval($postarray['active']);
      try {
        $stmt = $pdo->prepare("UPDATE `domain` SET 
        `description` = :description
          WHERE `domain` = :domain");
        $stmt->execute(array(
          ':description' => $description,
          ':domain' => $domain
        ));
        $_SESSION['return'] = array(
          'type' => 'success',
          'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars($domain))
        );
      }
      catch (PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
    }
    elseif ($_SESSION['mailcow_cc_role'] == "admin") {
      $is_now = mailbox_get_domain_details($domain);
      if (!empty($is_now)) {
        $active               = (isset($postarray['active'])) ? $postarray['active'] : $is_now['active_int'];
        $backupmx             = (isset($postarray['backupmx'])) ? $postarray['backupmx'] : $is_now['backupmx_int'];
        $relay_all_recipients = (isset($postarray['relay_all_recipients'])) ? $postarray['relay_all_recipients'] : $is_now['relay_all_recipients_int'];
        $aliases              = (!empty($postarray['aliases'])) ? $postarray['aliases'] : $is_now['max_num_aliases_for_domain'];
        $mailboxes            = (!empty($postarray['mailboxes'])) ? $postarray['mailboxes'] : $is_now['max_num_mboxes_for_domain'];
        $maxquota             = (!empty($postarray['maxquota'])) ? $postarray['maxquota'] : ($is_now['max_new_mailbox_quota'] / 1048576);
        $quota                = (!empty($postarray['quota'])) ? $postarray['quota'] : ($is_now['max_quota_for_domain'] / 1048576);
        $description          = (!empty($postarray['description'])) ? $postarray['description'] : $is_now['description'];
        ($relay_all_recipients == '1') ? $backupmx = '1' : null;
      }
      else {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['domain_invalid'])
        );
        return false;
      }
      try {
        // todo: should be using api here
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) AS count,
            MAX(COALESCE(ROUND(`quota`/1048576), 0)) AS `biggest_mailbox`,
            COALESCE(ROUND(SUM(`quota`)/1048576), 0) AS `quota_all`
              FROM `mailbox`
                WHERE `kind` NOT REGEXP 'location|thing|group'
                  AND domain = :domain");
        $stmt->execute(array(':domain' => $domain));
        $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
        // todo: should be using api here
        $stmt = $pdo->prepare("SELECT COUNT(*) AS `count` FROM `alias`
            WHERE domain = :domain
            AND address NOT IN (
              SELECT `username` FROM `mailbox`
            )");
        $stmt->execute(array(':domain' => $domain));
        $AliasData = $stmt->fetch(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }

      if ($maxquota > $quota) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
        );
        return false;
      }

      if ($maxquota == "0" || empty($maxquota)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['maxquota_empty'])
        );
        return false;
      }

      if ($MailboxData['biggest_mailbox'] > $maxquota) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['max_quota_in_use'], $MailboxData['biggest_mailbox'])
        );
        return false;
      }

      if ($MailboxData['quota_all'] > $quota) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['domain_quota_m_in_use'], $MailboxData['quota_all'])
        );
        return false;
      }

      if ($MailboxData['count'] > $mailboxes) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['mailboxes_in_use'], $MailboxData['count'])
        );
        return false;
      }

      if ($AliasData['count'] > $aliases) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['aliases_in_use'], $AliasData['count'])
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("UPDATE `domain` SET 
        `relay_all_recipients` = :relay_all_recipients,
        `backupmx` = :backupmx,
        `active` = :active,
        `quota` = :quota,
        `maxquota` = :maxquota,
        `mailboxes` = :mailboxes,
        `aliases` = :aliases,
        `description` = :description
          WHERE `domain` = :domain");
        $stmt->execute(array(
          ':relay_all_recipients' => $relay_all_recipients,
          ':backupmx' => $backupmx,
          ':active' => $active,
          ':quota' => $quota,
          ':maxquota' => $maxquota,
          ':mailboxes' => $mailboxes,
          ':aliases' => $aliases,
          ':description' => $description,
          ':domain' => $domain
        ));
        $_SESSION['return'] = array(
          'type' => 'success',
          'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars($domain))
        );
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
}
function mailbox_edit_mailbox($postarray) {
	global $lang;
	global $pdo;
  if (!is_array($postarray['username'])) {
    $usernames = array();
    $usernames[] = $postarray['username'];
  }
  else {
    $usernames = $postarray['username'];
  }
  foreach ($usernames as $username) {
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['username_invalid'])
      );
      return false;
    }
    $is_now = mailbox_get_mailbox_details($username);
    if (!empty($is_now)) {
      $active     = (isset($postarray['active'])) ? $postarray['active'] : $is_now['active_int'];
      $name       = (!empty($postarray['name'])) ? $postarray['name'] : $is_now['name'];
      $domain     = $is_now['domain'];
      $quota_m    = (!empty($postarray['quota'])) ? $postarray['quota'] : ($is_now['quota'] / 1048576);
      $quota_b    = $quota_m * 1048576;
      $password   = (!empty($postarray['password'])) ? $postarray['password'] : null;
      $password2  = (!empty($postarray['password2'])) ? $postarray['password2'] : null; 
    }
    else {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("SELECT `quota`, `maxquota`
        FROM `domain`
          WHERE `domain` = :domain");
      $stmt->execute(array(':domain' => $domain));
      $DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    catch(PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }

    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    if (!is_numeric($quota_m) || $quota_m == "0") {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'], htmlspecialchars($quota_m))
      );
      return false;
    }
    if ($quota_m > $DomainData['maxquota']) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $DomainData['maxquota'])
      );
      return false;
    }
    if (((($is_now['quota_used'] / 1048576) - $quota_m) + $quota_m) > $DomainData['quota']) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], ($is_now['max_new_quota'] / 1048576))
      );
      return false;
    }

    if (isset($postarray['sender_acl'])) {
      // Get sender_acl items set by admin
      $sender_acl_admin = array_merge(
        mailbox_get_sender_acl_handles($username)['sender_acl_domains']['ro'],
        mailbox_get_sender_acl_handles($username)['sender_acl_addresses']['ro']
      );

      // Get sender_acl items from POST array
      $sender_acl_domain_admin = ($postarray['sender_acl'] == "0") ? array() : $postarray['sender_acl'];

      if (!empty($sender_acl_domain_admin) || !empty($sender_acl_admin)) {
        // Check items in POST array
        foreach ($sender_acl_domain_admin as $sender_acl) {
          if (!filter_var($sender_acl, FILTER_VALIDATE_EMAIL) && !is_valid_domain_name(ltrim($sender_acl, '@'))) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['sender_acl_invalid'])
              );
              return false;
          }
          if (is_valid_domain_name(ltrim($sender_acl, '@'))) {
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], ltrim($sender_acl, '@'))) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['sender_acl_invalid'])
              );
              return false;
            }
          }
          if (filter_var($sender_acl, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $sender_acl)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['sender_acl_invalid'])
              );
              return false;
            }
          }
        }

        // Merge both arrays
        $sender_acl_merged = array_merge($sender_acl_domain_admin, $sender_acl_admin);

        try {
          $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
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

        foreach ($sender_acl_merged as $sender_acl) {
          $domain = ltrim($sender_acl, '@');
          if (is_valid_domain_name($domain)) {
            $sender_acl = '@' . $domain;
          }
          try {
            $stmt = $pdo->prepare("INSERT INTO `sender_acl` (`send_as`, `logged_in_as`)
              VALUES (:sender_acl, :username)");
            $stmt->execute(array(
              ':sender_acl' => $sender_acl,
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
      else {
        try {
          $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
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
      }
    }
    if (!empty($password) && !empty($password2)) {
      if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['password_complexity'])
        );
        return false;
      }
      if ($password != $password2) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['password_mismatch'])
        );
        return false;
      }
      $password_hashed = hash_password($password);
      try {
        $stmt = $pdo->prepare("UPDATE `alias` SET
            `active` = :active
              WHERE `address` = :address");
        $stmt->execute(array(
          ':address' => $username,
          ':active' => $active
        ));
        $stmt = $pdo->prepare("UPDATE `mailbox` SET
            `active` = :active,
            `password` = :password_hashed,
            `name`= :name,
            `quota` = :quota_b
              WHERE `username` = :username");
        $stmt->execute(array(
          ':password_hashed' => $password_hashed,
          ':active' => $active,
          ':name' => $name,
          ':quota_b' => $quota_b,
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
    try {
      $stmt = $pdo->prepare("UPDATE `alias` SET
          `active` = :active
            WHERE `address` = :address");
      $stmt->execute(array(
        ':address' => $username,
        ':active' => $active
      ));
      $stmt = $pdo->prepare("UPDATE `mailbox` SET
          `active` = :active,
          `name`= :name,
          `quota` = :quota_b
            WHERE `username` = :username");
      $stmt->execute(array(
        ':active' => $active,
        ':name' => $name,
        ':quota_b' => $quota_b,
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
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['mailbox_modified'], implode(', ', $usernames))
  );
  return true;
}
function mailbox_edit_resource($postarray) {
	global $lang;
	global $pdo;

  if (!is_array($postarray['name'])) {
    $names = array();
    $names[] = $postarray['name'];
  }
  else {
    $names = $postarray['name'];
  }
  foreach ($names as $name) {
    $is_now = mailbox_get_resource_details($name);
    if (!empty($is_now)) {
      $active             = (isset($postarray['active'])) ? $postarray['active'] : $is_now['active_int'];
      $multiple_bookings  = (isset($postarray['multiple_bookings'])) ? $postarray['multiple_bookings'] : $is_now['multiple_bookings_int'];
      $description        = (!empty($postarray['description'])) ? $postarray['description'] : $is_now['description'];
      $kind               = (!empty($postarray['kind'])) ? $postarray['kind'] : $is_now['kind'];
    }
    else {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['resource_invalid'])
      );
      return false;
    }

    if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['resource_invalid'])
      );
      return false;
    }

    if (empty($description)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['description_invalid'])
      );
      return false;
    }

    if ($kind != 'location' && $kind != 'group' && $kind != 'thing') {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['resource_invalid'])
      );
      return false;
    }

    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $name)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }

    try {
      $stmt = $pdo->prepare("UPDATE `mailbox` SET
          `active` = :active,
          `name`= :description,
          `kind`= :kind,
          `multiple_bookings`= :multiple_bookings
            WHERE `username` = :name");
      $stmt->execute(array(
        ':active' => $active,
        ':description' => $description,
        ':multiple_bookings' => $multiple_bookings,
        ':kind' => $kind,
        ':name' => $name
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
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['resource_modified'], implode(', ', $names))
  );
  return true;
}
function mailbox_get_mailboxes($domain = null) {
	global $lang;
	global $pdo;
  $mailboxes = array();
	if (isset($domain) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		return false;
	}
  elseif (isset($domain) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    try {
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` NOT REGEXP 'location|thing|group' AND `domain` != 'ALL' AND `domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $mailboxes[] = $row['username'];
      }
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
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` NOT REGEXP 'location|thing|group' AND `domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role");
      $stmt->execute(array(
        ':username' => $_SESSION['mailcow_cc_username'],
        ':role' => $_SESSION['mailcow_cc_role'],
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $mailboxes[] = $row['username'];
      }
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
  }
  return $mailboxes;
}
function mailbox_get_resources($domain = null) {
	global $lang;
	global $pdo;
  $resources = array();
	if (isset($domain) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		return false;
	}
  elseif (isset($domain) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    try {
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` REGEXP 'location|thing|group' AND `domain` != 'ALL' AND `domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $resources[] = $row['username'];
      }
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
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` REGEXP 'location|thing|group' AND `domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role");
      $stmt->execute(array(
        ':username' => $_SESSION['mailcow_cc_username'],
        ':role' => $_SESSION['mailcow_cc_role'],
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $resources[] = $row['username'];
      }
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
  }
  return $resources;
}
function mailbox_get_alias_domains($domain = null) {
  // Get all domains assigned to mailcow_cc_username or domain, if set
  // Domain admin needs to be active
  // Domain does not need to be active
	global $lang;
	global $pdo;
  $aliasdomains = array();
	if (isset($domain) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		return false;
  }
  elseif (isset($domain) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    try {
      $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $aliasdomains[] = $row['alias_domain'];
      }
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
      $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role");
      $stmt->execute(array(
        ':username' => $_SESSION['mailcow_cc_username'],
        ':role' => $_SESSION['mailcow_cc_role'],
      ));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $aliasdomains[] = $row['alias_domain'];
      }
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
  }
  return $aliasdomains;
}
function mailbox_get_aliases($domain) {
	global $lang;
	global $pdo;
  $aliases = array();
	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		return false;
	}

  try {
    $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `address` != `goto` AND `domain` = :domain");
    $stmt->execute(array(
      ':domain' => $domain,
    ));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while($row = array_shift($rows)) {
      $aliases[] = $row['address'];
    }
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  return $aliases;
}
function mailbox_get_alias_details($address) {
	global $lang;
	global $pdo;
  $aliasdata = array();
  try {
    $stmt = $pdo->prepare("SELECT
      `domain`,
      `goto`,
      `address`,
      `active` as `active_int`,
      CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
      `created`,
      `modified`
        FROM `alias`
            WHERE `address` = :address AND `address` != `goto`");
    $stmt->execute(array(
      ':address' => $address,
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain");
    $stmt->execute(array(
      ':domain' => $row['domain'],
    ));
    $row_alias_domain = $stmt->fetch(PDO::FETCH_ASSOC);
    if (isset($row_alias_domain['target_domain']) && !empty($row_alias_domain['target_domain'])) {
      $aliasdata['in_primary_domain'] = $row_alias_domain['target_domain'];
    }
    else {
      $aliasdata['in_primary_domain'] = "";
    }
    $aliasdata['domain'] = $row['domain'];
    $aliasdata['goto'] = $row['goto'];
    $aliasdata['address'] = $row['address'];
    (!filter_var($aliasdata['address'], FILTER_VALIDATE_EMAIL)) ? $aliasdata['is_catch_all'] = 1 : $aliasdata['is_catch_all'] = 0;
    $aliasdata['active'] = $row['active'];
    $aliasdata['active_int'] = $row['active_int'];
    $aliasdata['created'] = $row['created'];
    $aliasdata['modified'] = $row['modified'];
    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $aliasdata['domain'])) {
      return false;
    }
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  return $aliasdata;
}
function mailbox_get_alias_domain_details($aliasdomain) {
	global $lang;
	global $pdo;
  $aliasdomaindata = array();
  try {
    $stmt = $pdo->prepare("SELECT
      `alias_domain`,
      `target_domain`,
      `active` AS `active_int`,
      CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
      `created`,
      `modified`
        FROM `alias_domain`
            WHERE `alias_domain` = :aliasdomain");
    $stmt->execute(array(
      ':aliasdomain' => $aliasdomain,
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $aliasdomaindata['alias_domain'] = $row['alias_domain'];
    $aliasdomaindata['target_domain'] = $row['target_domain'];
    $aliasdomaindata['active'] = $row['active'];
    $aliasdomaindata['active_int'] = $row['active_int'];
    $aliasdomaindata['created'] = $row['created'];
    $aliasdomaindata['modified'] = $row['modified'];
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $aliasdomaindata['target_domain'])) {
    return false;
  }
  return $aliasdomaindata;
}
function mailbox_get_domains() {
  // Get all domains assigned to mailcow_cc_username
  // Domain admin needs to be active
  // Domain does not need to be active
	global $lang;
	global $pdo;
  $domains = array();
	if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    return false;
	}
  try {
    $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
      WHERE (`domain` IN (
        SELECT `domain` from `domain_admins`
          WHERE (`active`='1' AND `username` = :username))
        )
        OR ('admin'= :role)
        AND `domain` != 'ALL'");
    $stmt->execute(array(
      ':username' => $_SESSION['mailcow_cc_username'],
      ':role' => $_SESSION['mailcow_cc_role'],
    ));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while($row = array_shift($rows)) {
      $domains[] = $row['domain'];
    }
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  return $domains;
}
function mailbox_get_domain_details($domain) {
	global $lang;
	global $pdo;

  $domaindata = array();
	$domain = idn_to_ascii(strtolower(trim($domain)));

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		return false;
	}

  try {
    $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` =  :domain");
    $stmt->execute(array(
      ':domain' => $domain
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($row)) { 
      $domain = $row['target_domain'];
    }

    $stmt = $pdo->prepare("SELECT 
        `domain`,
        `description`,
        `aliases`,
        `mailboxes`, 
        `maxquota`,
        `quota`,
        `relay_all_recipients` as `relay_all_recipients_int`,
        `backupmx` as `backupmx_int`,
        `active` as `active_int`,
        CASE `relay_all_recipients` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `relay_all_recipients`,
        CASE `backupmx` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `backupmx`,
        CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
          FROM `domain` WHERE `domain`= :domain");
    $stmt->execute(array(
      ':domain' => $domain
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (empty($row)) { 
      return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) AS `count`,
      COALESCE(SUM(`quota`), 0) AS `in_use`
        FROM `mailbox`
          WHERE `kind` NOT REGEXP 'location|thing|group'
            AND `domain` = :domain");
    $stmt->execute(array(':domain' => $row['domain']));
    $MailboxDataDomain	= $stmt->fetch(PDO::FETCH_ASSOC);

    $domaindata['max_new_mailbox_quota']	= ($row['quota'] * 1048576) - $MailboxDataDomain['in_use'];
    if ($domaindata['max_new_mailbox_quota'] > ($row['maxquota'] * 1048576)) {
      $domaindata['max_new_mailbox_quota'] = ($row['maxquota'] * 1048576);
    }
    $domaindata['quota_used_in_domain'] = $MailboxDataDomain['in_use'];
    $domaindata['mboxes_in_domain'] = $MailboxDataDomain['count'];
    $domaindata['mboxes_left'] = $row['mailboxes']	- $MailboxDataDomain['count'];
    $domaindata['domain_name'] = $row['domain'];
    $domaindata['description'] = $row['description'];
    $domaindata['max_num_aliases_for_domain'] = $row['aliases'];
    $domaindata['max_num_mboxes_for_domain'] = $row['mailboxes'];
    $domaindata['max_quota_for_mbox'] = $row['maxquota'] * 1048576;
    $domaindata['max_quota_for_domain'] = $row['quota'] * 1048576;
    $domaindata['backupmx'] = $row['backupmx'];
    $domaindata['backupmx_int'] = $row['backupmx_int'];
    $domaindata['active'] = $row['active'];
    $domaindata['active_int'] = $row['active_int'];
    $domaindata['relay_all_recipients'] = $row['relay_all_recipients'];
    $domaindata['relay_all_recipients_int'] = $row['relay_all_recipients_int'];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS `alias_count` FROM `alias`
      WHERE (`domain`= :domain OR `domain` IN (SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :domain2))
        AND `address` NOT IN (
          SELECT `username` FROM `mailbox`
        )");
    $stmt->execute(array(
      ':domain' => $domain,
      ':domain2' => $domain
    ));
    $AliasDataDomain = $stmt->fetch(PDO::FETCH_ASSOC);
    (isset($AliasDataDomain['alias_count'])) ? $domaindata['aliases_in_domain'] = $AliasDataDomain['alias_count'] : $domaindata['aliases_in_domain'] = "0";
    $domaindata['aliases_left'] = $row['aliases']	- $AliasDataDomain['alias_count'];
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }

  return $domaindata;
}
function mailbox_get_mailbox_details($mailbox) {
	global $lang;
	global $pdo;
  if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $mailbox)) {
    return false;
  }
  $mailboxdata = array();
  try {
    $stmt = $pdo->prepare("SELECT
        `domain`.`backupmx`,
        `mailbox`.`username`,
        `mailbox`.`name`,
        `mailbox`.`active` AS `active_int`,
        CASE `mailbox`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
        `mailbox`.`domain`,
        `mailbox`.`quota`,
        `quota2`.`bytes`,
        `quota2`.`messages`
          FROM `mailbox`, `quota2`, `domain`
            WHERE `mailbox`.`kind` NOT REGEXP 'location|thing|group' AND `mailbox`.`username` = `quota2`.`username` AND `domain`.`domain` = `mailbox`.`domain` AND `mailbox`.`username` = :mailbox");
    $stmt->execute(array(
      ':mailbox' => $mailbox,
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT `maxquota`, `quota` FROM  `domain` WHERE `domain` = :domain");
    $stmt->execute(array(':domain' => $row['domain']));
    $DomainQuota  = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(`quota`), 0) as `in_use` FROM `mailbox` WHERE `kind` NOT REGEXP 'location|thing|group' AND `domain` = :domain AND `username` != :username");
    $stmt->execute(array(':domain' => $row['domain'], ':username' => $mailbox));
    $MailboxUsage	= $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT IFNULL(COUNT(`address`), 0) AS `sa_count` FROM `spamalias` WHERE `goto` = :address AND `validity` >= :unixnow");
    $stmt->execute(array(':address' => $mailbox, ':unixnow' => time()));
    $SpamaliasUsage	= $stmt->fetch(PDO::FETCH_ASSOC);

    $mailboxdata['max_new_quota'] = ($DomainQuota['quota'] * 1048576) - $MailboxUsage['in_use'];
    if ($mailboxdata['max_new_quota'] > ($DomainQuota['maxquota'] * 1048576)) {
      $mailboxdata['max_new_quota'] = ($DomainQuota['maxquota'] * 1048576);
    }

    $mailboxdata['username'] = $row['username'];
    $mailboxdata['is_relayed'] = $row['backupmx'];
    $mailboxdata['name'] = $row['name'];
    $mailboxdata['active'] = $row['active'];
    $mailboxdata['active_int'] = $row['active_int'];
    $mailboxdata['domain'] = $row['domain'];
    $mailboxdata['quota'] = $row['quota'];
    $mailboxdata['quota_used'] = intval($row['bytes']);
    $mailboxdata['percent_in_use'] = round((intval($row['bytes']) / intval($row['quota'])) * 100);
    $mailboxdata['messages'] = $row['messages'];
    $mailboxdata['spam_aliases'] = $SpamaliasUsage['sa_count'];
    if ($mailboxdata['percent_in_use'] >= 90) {
      $mailboxdata['percent_class'] = "danger";
    }
    elseif ($mailboxdata['percent_in_use'] >= 75) {
      $mailboxdata['percent_class'] = "warning";
    }
    else {
      $mailboxdata['percent_class'] = "success";
    }
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  return $mailboxdata;
}
function mailbox_get_resource_details($resource) {
	global $lang;
	global $pdo;
  $resourcedata = array();
  if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $resource)) {
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT
        `username`,
        `name`,
        `kind`,
        `multiple_bookings` AS `multiple_bookings_int`,
        `local_part`,
        `active` AS `active_int`,
        CASE `multiple_bookings` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `multiple_bookings`,
        CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
        `domain`
          FROM `mailbox` WHERE `kind` REGEXP 'location|thing|group' AND `username` = :resource");
    $stmt->execute(array(
      ':resource' => $resource,
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $resourcedata['name'] = $row['username'];
    $resourcedata['kind'] = $row['kind'];
    $resourcedata['multiple_bookings'] = $row['multiple_bookings'];
    $resourcedata['multiple_bookings_int'] = $row['multiple_bookings_int'];
    $resourcedata['description'] = $row['name'];
    $resourcedata['active'] = $row['active'];
    $resourcedata['active_int'] = $row['active_int'];
    $resourcedata['domain'] = $row['domain'];
    $resourcedata['local_part'] = $row['local_part'];
  }
  catch (PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  if (!isset($resourcedata['domain']) ||
    (isset($resourcedata['domain']) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $resourcedata['domain']))) {
    return false;
  }
  
  return $resourcedata;
}
function mailbox_delete_domain($postarray) {
	global $lang;
	global $redis;
	global $pdo;
  if (!is_array($postarray['domain'])) {
    $domains = array();
    $domains[] = $postarray['domain'];
  }
  else {
    $domains = $postarray['domain'];
  }
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
  foreach ($domains as $domain) {
    if (!is_valid_domain_name($domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_invalid'])
      );
      return false;
    }
    $domain	= idn_to_ascii(strtolower(trim($domain)));

    try {
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
        WHERE `domain` = :domain");
      $stmt->execute(array(':domain' => $domain));
      $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    catch(PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
    if ($num_results != 0 || !empty($num_results)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_not_empty'])
      );
      return false;
    }

    try {
      $stmt = $pdo->prepare("DELETE FROM `domain` WHERE `domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `alias_domain` WHERE `target_domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `domain` = :domain");
      $stmt->execute(array(
        ':domain' => $domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` LIKE :domain");
      $stmt->execute(array(
        ':domain' => '%@'.$domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `quota2` WHERE `username` = :domain");
      $stmt->execute(array(
        ':domain' => '%@'.$domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `address` = :domain");
      $stmt->execute(array(
        ':domain' => '%@'.$domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :domain");
      $stmt->execute(array(
        ':domain' => '%@'.$domain,
      ));
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
    try {
      $redis->hDel('DOMAIN_MAP', $domain);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
  }
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_removed'], htmlspecialchars($domain))
	);
	return true;
}
function mailbox_delete_alias($postarray) {
  // $postarray['address'] can be a single element or an array
	global $lang;
	global $pdo;
  if (!is_array($postarray['address'])) {
    $addresses = array();
    $addresses[] = $postarray['address'];
  }
  else {
    $addresses = $postarray['address'];
  }
  foreach ($addresses as $address) {
    $local_part		= strstr($address, '@', true);
    $domain = mailbox_get_alias_details($address)['domain'];
    try {
      $stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :address");
      $stmt->execute(array(':address' => $address));
      $gotos = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    catch(PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
    $goto_array = explode(',', $gotos['goto']);

    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `address` = :address AND `address` NOT IN (SELECT `username` FROM `mailbox`)");
      $stmt->execute(array(
        ':address' => $address
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_removed'], htmlspecialchars(implode(', ', $addresses)))
	);

}
function mailbox_delete_alias_domain($postarray) {
	global $lang;
	global $pdo;
  if (!is_array($postarray['alias_domain'])) {
    $alias_domains = array();
    $alias_domains[] = $postarray['alias_domain'];
  }
  else {
    $alias_domains = $postarray['alias_domain'];
  }
  foreach ($alias_domains as $alias_domain) {
    if (!is_valid_domain_name($alias_domain)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_invalid'])
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
        WHERE `alias_domain`= :alias_domain");
      $stmt->execute(array(':alias_domain' => $alias_domain));
      $DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    catch(PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $DomainData['target_domain'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("DELETE FROM `alias_domain` WHERE `alias_domain` = :alias_domain");
      $stmt->execute(array(
        ':alias_domain' => $alias_domain,
      ));
      $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `domain` = :alias_domain");
      $stmt->execute(array(
        ':alias_domain' => $alias_domain,
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_domain_removed'], htmlspecialchars(implode(', ', $alias_domains)))
	);
}
function mailbox_delete_mailbox($postarray) {
	global $lang;
	global $pdo;
  if (!is_array($postarray['username'])) {
    $usernames = array();
    $usernames[] = $postarray['username'];
  }
  else {
    $usernames = $postarray['username'];
  }
  foreach ($usernames as $username) {
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }

    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }

    try {
      $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `goto` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `quota2` WHERE `username` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `username` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `imapsync` WHERE `user2` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_user_profile` WHERE `c_uid` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_cache_folder` WHERE `c_uid` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_acl` WHERE `c_object` LIKE '%/" . $username . "/%' OR `c_uid` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_store` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_quick_contact` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_quick_appointment` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_folder_info` WHERE `c_path2` = :username");
      $stmt->execute(array(
        ':username' => $username
      ));
      $stmt = $pdo->prepare("SELECT `address`, `goto` FROM `alias`
          WHERE `goto` REGEXP :username");
      $stmt->execute(array(':username' => '(^|,)'.$username.'($|,)'));
      $GotoData = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($GotoData as $gotos) {
        $goto_exploded = explode(',', $gotos['goto']);
        if (($key = array_search($username, $goto_exploded)) !== false) {
          unset($goto_exploded[$key]);
        }
        $gotos_rebuild = implode(',', $goto_exploded);
        $stmt = $pdo->prepare("UPDATE `alias` SET
          `goto` = :goto
            WHERE `address` = :address");
        $stmt->execute(array(
          ':goto' => $gotos_rebuild,
          ':address' => $gotos['address']
        ));
      }
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_removed'], htmlspecialchars(implode(', ', $usernames)))
	);
}
function mailbox_reset_eas($username) {
	global $lang;
	global $pdo;

  (isset($postarray['username'])) ? $username = $postarray['username'] : $username = $_SESSION['mailcow_cc_username'];

	if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

	try {
    $stmt = $pdo->prepare("DELETE FROM `sogo_cache_folder` WHERE `c_uid` = :username");
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
		'msg' => sprintf($lang['success']['eas_reset'], htmlspecialchars($username))
	);
}
function mailbox_delete_resource($postarray) {
	global $lang;
	global $pdo;
  if (!is_array($postarray['name'])) {
    $names = array();
    $names[] = $postarray['name'];
  }
  else {
    $names = $postarray['name'];
  }
  foreach ($names as $name) {
    if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $name)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `username` = :username");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_user_profile` WHERE `c_uid` = :username");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_cache_folder` WHERE `c_uid` = :username");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_acl` WHERE `c_object` LIKE '%/" . $name . "/%' OR `c_uid` = :username");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_store` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_quick_contact` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_quick_appointment` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
      $stmt->execute(array(
        ':username' => $name
      ));
      $stmt = $pdo->prepare("DELETE FROM `sogo_folder_info` WHERE `c_path2` = :username");
      $stmt->execute(array(
        ':username' => $name
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['resource_removed'], htmlspecialchars(implode(', ', $names)))
	);
}
function mailbox_get_sender_acl_handles($mailbox) {
	global $pdo;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    return false;
	}

  $data['sender_acl_domains']['ro']               = array();
  $data['sender_acl_domains']['rw']               = array();
  $data['sender_acl_domains']['selectable']       = array();
  $data['sender_acl_addresses']['ro']             = array();
  $data['sender_acl_addresses']['rw']             = array();
  $data['sender_acl_addresses']['selectable']     = array();
  $data['fixed_sender_aliases']                   = array();
  
  try {
    // Fixed addresses
    $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` REGEXP :goto AND `address` NOT LIKE '@%'");
    $stmt->execute(array(':goto' => '(^|,)'.$mailbox.'($|,)'));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($rows)) {
      $data['fixed_sender_aliases'][] = $row['address'];
    }
    $stmt = $pdo->prepare("SELECT CONCAT(`local_part`, '@', `alias_domain`.`alias_domain`) AS `alias_domain_alias` FROM `mailbox`, `alias_domain`
      WHERE `alias_domain`.`target_domain` = `mailbox`.`domain`
      AND `mailbox`.`username` = :username");
    $stmt->execute(array(':username' => $mailbox));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($rows)) {
      if (!empty($row['alias_domain_alias'])) {
        $data['fixed_sender_aliases'][] = $row['alias_domain_alias'];
      }
    }

    // Return array $data['sender_acl_domains/addresses']['ro'] with read-only objects
    // Return array $data['sender_acl_domains/addresses']['rw'] with read-write objects (can be deleted)
    $stmt = $pdo->prepare("SELECT REPLACE(`send_as`, '@', '') AS `send_as` FROM `sender_acl` WHERE `logged_in_as` = :logged_in_as AND `send_as` LIKE '@%'");
    $stmt->execute(array(':logged_in_as' => $mailbox));
    $domain_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($domain_row = array_shift($domain_rows)) {
      if (is_valid_domain_name($domain_row['send_as']) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain_row['send_as'])) {
        $data['sender_acl_domains']['ro'][] = $domain_row['send_as'];
        continue;
      }
      if (is_valid_domain_name($domain_row['send_as']) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain_row['send_as'])) {
        $data['sender_acl_domains']['rw'][] = $domain_row['send_as'];
        continue;
      }
    }

    $stmt = $pdo->prepare("SELECT `send_as` FROM `sender_acl` WHERE `logged_in_as` = :logged_in_as AND `send_as` NOT LIKE '@%'");
    $stmt->execute(array(':logged_in_as' => $mailbox));
    $address_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($address_row = array_shift($address_rows)) {
      if (filter_var($address_row['send_as'], FILTER_VALIDATE_EMAIL) && !hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $address_row['send_as'])) {
        $data['sender_acl_addresses']['ro'][] = $address_row['send_as'];
        continue;
      }
      if (filter_var($address_row['send_as'], FILTER_VALIDATE_EMAIL) && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $address_row['send_as'])) {
        $data['sender_acl_addresses']['rw'][] = $address_row['send_as'];
        continue;
      }
    }

    $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
      WHERE `domain` NOT IN (
        SELECT REPLACE(`send_as`, '@', '') FROM `sender_acl` 
          WHERE `logged_in_as` = :logged_in_as
            AND `send_as` LIKE '@%')");
    $stmt->execute(array(
      ':logged_in_as' => $mailbox,
    ));
    $rows_domain = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row_domain = array_shift($rows_domain)) {
      if (is_valid_domain_name($row_domain['domain']) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row_domain['domain'])) {
        $data['sender_acl_domains']['selectable'][] = $row_domain['domain'];
      }
    }

    $stmt = $pdo->prepare("SELECT `address` FROM `alias`
      WHERE `goto` != :goto
        AND `address` NOT IN (
          SELECT `send_as` FROM `sender_acl` 
            WHERE `logged_in_as` = :logged_in_as
              AND `send_as` NOT LIKE '@%')");
    $stmt->execute(array(
      ':logged_in_as' => $mailbox,
      ':goto' => $mailbox
    ));
    $rows_mbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($rows_mbox)) {
      if (filter_var($row['address'], FILTER_VALIDATE_EMAIL) && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['address'])) {
        $data['sender_acl_addresses']['selectable'][] = $row['address'];
      }
    }
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
function get_u2f_registrations($username) {
  global $pdo;
  $sel = $pdo->prepare("SELECT * FROM `tfa` WHERE `authmech` = 'u2f' AND `username` = ? AND `active` = '1'");
  $sel->execute(array($username));
  return $sel->fetchAll(PDO::FETCH_OBJ);
}
function get_forwarding_hosts() {
	global $redis;
  $data = array();
  try {
    $fwd_hosts = $redis->hGetAll('WHITELISTED_FWD_HOST');
    if (!empty($fwd_hosts)) {
      foreach ($fwd_hosts as $fwd_host => $source) {
        $data[] = $fwd_host;
      }
    }
  }
  catch (RedisException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'Redis: '.$e
		);
		return false;
  }
  return $data;
}
function get_forwarding_host_details($host) {
	global $redis;
  $data = array();
  if (!isset($host) || empty($host)) {
    return false;
  }
  try {
    if ($source = $redis->hGet('WHITELISTED_FWD_HOST', $host)) {
      $data['host'] = $host;
      $data['source'] = $source;
      $data['keep_spam'] = ($redis->hGet('KEEP_SPAM', $host)) ? "yes" : "no";
    }
  }
  catch (RedisException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'Redis: '.$e
		);
		return false;
  }
  return $data;
}
function add_forwarding_host($postarray) {
	require_once 'spf.inc.php';
	global $redis;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	$source = $postarray['hostname'];
	$host = trim($postarray['hostname']);
  $filter_spam = $postarray['filter_spam'];
  if (isset($postarray['filter_spam']) && $postarray['filter_spam'] == 1) {
    $filter_spam = 1;
  }
  else {
    $filter_spam = 0;
  }
	if (preg_match('/^[0-9a-fA-F:\/]+$/', $host)) { // IPv6 address
		$hosts = array($host);
	}
	elseif (preg_match('/^[0-9\.\/]+$/', $host)) { // IPv4 address
		$hosts = array($host);
	}
	else {
		$hosts = get_outgoing_hosts_best_guess($host);
	}
	if (empty($hosts)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'Invalid host specified: '. htmlspecialchars($host)
		);
		return false;
	}
	foreach ($hosts as $host) {
    try {
      $redis->hSet('WHITELISTED_FWD_HOST', $host, $source);
      if ($filter_spam == 0) {
        $redis->hSet('KEEP_SPAM', $host, 1);
      }
      elseif ($redis->hGet('KEEP_SPAM', $host)) {
        $redis->hDel('KEEP_SPAM', $host);
      }
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
	}
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['forwarding_host_added'], htmlspecialchars(implode(', ', $hosts)))
	);
}
function delete_forwarding_host($postarray) {
	global $redis;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
  if (!is_array($postarray['forwardinghost'])) {
    $hosts = array();
    $hosts[] = $postarray['forwardinghost'];
  }
  else {
    $hosts = $postarray['forwardinghost'];
  }
  foreach ($hosts as $host) {
    try {
      $redis->hDel('WHITELISTED_FWD_HOST', $host);
      $redis->hDel('KEEP_SPAM', $host);
    }
    catch (RedisException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'Redis: '.$e
      );
      return false;
    }
  }
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['forwarding_host_removed'], htmlspecialchars(implode(', ', $hosts)))
	);
}
function get_logs($container, $lines = 100) {
	global $lang;
	global $redis;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		return false;
	}
  $lines = intval($lines);
  if ($container == "dovecot-mailcow") {
    if ($data = $redis->lRange('DOVECOT_MAILLOG', 1, $lines)) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "postfix-mailcow") {
    if ($data = $redis->lRange('POSTFIX_MAILLOG', 1, $lines)) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "sogo-mailcow") {
    if ($data = $redis->lRange('SOGO_LOG', 1, $lines)) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  return false;
}
?>
