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
	if (!filter_var(html_entity_decode(rawurldecode($username)), FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
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
function pem_to_der($pem_key) {
  // Need to remove BEGIN/END PUBLIC KEY
  $lines = explode("\n", trim($pem_key));
  unset($lines[count($lines)-1]);
  unset($lines[0]);
  return base64_decode(implode('', $lines));
}
function generate_tlsa_digest($hostname, $port, $starttls = null) {
  if (!is_valid_domain_name($hostname)) {
    return "Not a valid hostname";
  }
  if (empty($starttls)) {
    $context = stream_context_create(array("ssl" => array("capture_peer_cert" => true, 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)));
    $stream = stream_socket_client('ssl://' . $hostname . ':' . $port, $error_nr, $error_msg, 5, STREAM_CLIENT_CONNECT, $context);
    if (!$stream) {
      $error_msg = isset($error_msg) ? $error_msg : '-';
      return $error_nr . ': ' . $error_msg;
    }
  }
  else {
    $stream = stream_socket_client('tcp://' . $hostname . ':' . $port, $error_nr, $error_msg, 5);
    if (!$stream) {
      return $error_nr . ': ' . $error_msg;
    }
    $banner = fread($stream, 512 );
    if (preg_match("/^220/i", $banner)) { // SMTP
      fwrite($stream,"HELO tlsa.generator.local\r\n");
      fread($stream, 512);
      fwrite($stream,"STARTTLS\r\n");
      fread($stream, 512);
    }
    elseif (preg_match("/imap.+starttls/i", $banner)) { // IMAP
      fwrite($stream,"A1 STARTTLS\r\n");
      fread($stream, 512);
    }
    elseif (preg_match("/^\+OK/", $banner)) { // POP3
      fwrite($stream,"STLS\r\n");
      fread($stream, 512);
    }
    elseif (preg_match("/^OK/m", $banner)) { // Sieve
      fwrite($stream,"STARTTLS\r\n");
      fread($stream, 512);
    }
    else {
      return 'Unknown banner: "' . htmlspecialchars(trim($banner)) . '"';
    }
    // Upgrade connection
    stream_set_blocking($stream, true);
    stream_context_set_option($stream, 'ssl', 'capture_peer_cert', true);
    stream_context_set_option($stream, 'ssl', 'verify_peer', false);
    stream_context_set_option($stream, 'ssl', 'verify_peer_name', false);
    stream_context_set_option($stream, 'ssl', 'allow_self_signed', true);
    stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
    stream_set_blocking($stream, false);
  }
  $params = stream_context_get_params($stream);
  if (!empty($params['options']['ssl']['peer_certificate'])) {
    $key_resource = openssl_pkey_get_public($params['options']['ssl']['peer_certificate']);
    // We cannot get ['rsa']['n'], the binary data would contain BEGIN/END PUBLIC KEY
    $key_data = openssl_pkey_get_details($key_resource)['key'];
    return '3 1 1 ' . openssl_digest(pem_to_der($key_data), 'sha256');
  }
  else {
    return 'Error: Cannot read peer certificate';
  }
}
function verify_ssha256($hash, $password) {
	// Remove tag if any
  if (substr($hash, 0, strlen('{SSHA256}')) == '{SSHA256}') {
    $hash = substr($hash, strlen('{SSHA256}'));
  }
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
function check_login($user, $pass) {
	global $pdo;
	global $redis;
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
    $redis->publish("F2B_CHANNEL", "mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
    error_log("mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
	}
	elseif (!isset($_SESSION['mailcow_cc_username'])) {
		$_SESSION['ldelay'] = $_SESSION['ldelay']+0.5;
    $redis->publish("F2B_CHANNEL", "mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
		error_log("mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
	}
	sleep($_SESSION['ldelay']);
}
function set_acl() {
	global $pdo;
	if (!isset($_SESSION['mailcow_cc_username'])) {
		return false;
	}
	if ($_SESSION['mailcow_cc_role'] == 'admin' || $_SESSION['mailcow_cc_role'] == 'domainadmin') {
    $stmt = $pdo->query("SHOW COLUMNS FROM `user_acl` WHERE `Field` != 'username';");
    $acl_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($acl_all)) {
      $acl['acl'][$row['Field']] = 1;
    }
	}
  else {
    $username = strtolower(trim($_SESSION['mailcow_cc_username']));
    $stmt = $pdo->prepare("SELECT * FROM `user_acl` WHERE `username` = :username");
    $stmt->execute(array(':username' => $username));
    $acl['acl'] = $stmt->fetch(PDO::FETCH_ASSOC);
    unset($acl['acl']['username']);
  }
  if (!empty($acl)) {
    $_SESSION = array_merge($_SESSION, $acl);
  }
  else {
    return false;
  }
}
function get_acl($username) {
	global $pdo;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		return false;
	}
  $username = strtolower(trim($username));
  $stmt = $pdo->prepare("SELECT * FROM `user_acl` WHERE `username` = :username");
  $stmt->execute(array(':username' => $username));
  $acl = $stmt->fetch(PDO::FETCH_ASSOC);
  unset($acl['username']);
  if (!empty($acl)) {
    return $acl;
  }
  else {
    return false;
  }
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
	$username_now   = $_SESSION['mailcow_cc_username'];
	$username       = $postarray['admin_user'];
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
function edit_user_account($postarray) {
	global $lang;
	global $pdo;
  $username = $_SESSION['mailcow_cc_username'];
  $role = $_SESSION['mailcow_cc_role'];
	$password_old = $postarray['user_old_pass'];
  if (filter_var($username, FILTER_VALIDATE_EMAIL === false) || $role != 'user') {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
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
				$stmt = $pdo->prepare("UPDATE `mailbox` SET `password` = :password_hashed, `attributes` = JSON_SET(`attributes`, '$.force_pw_update', '0') WHERE `username` = :username");
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
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`address` SEPARATOR ', '), '&#10008;') AS `shared_aliases` FROM `alias`
      WHERE `goto` REGEXP :username_goto
      AND `address` NOT LIKE '@%'
      AND `goto` != :username_goto2
      AND `address` != :username_address");
    $stmt->execute(array(
      ':username_goto' => '(^|,)'.$username.'($|,)',
      ':username_goto2' => $username,
      ':username_address' => $username
      ));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['shared_aliases'] = $row['shared_aliases'];
    }
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(`address` SEPARATOR ', ') AS `direct_aliases` FROM `alias`
      WHERE `goto` = :username_goto
      AND `address` NOT LIKE '@%'
      AND `address` != :username_address");
    $stmt->execute(
      array(
      ':username_goto' => $username,
      ':username_address' => $username
      ));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['direct_aliases'][] = $row['direct_aliases'];
    }
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(local_part, '@', alias_domain SEPARATOR ', ') AS `ad_alias` FROM `mailbox`
      LEFT OUTER JOIN `alias_domain` on `target_domain` = `domain`
        WHERE `username` = :username ;");
    $stmt->execute(array(':username' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['direct_aliases'][] = $row['ad_alias'];
    }
    $data['direct_aliases'] = implode(', ', array_filter($data['direct_aliases']));
    $data['direct_aliases'] = empty($data['direct_aliases']) ? '&#10008;' : $data['direct_aliases'];
    $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`send_as` SEPARATOR ', '), '&#10008;') AS `send_as` FROM `sender_acl` WHERE `logged_in_as` = :username AND `send_as` NOT LIKE '@%';");
    $stmt->execute(array(':username' => $username));
    $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($run)) {
      $data['aliases_also_send_as'] = $row['send_as'];
    }
    $stmt = $pdo->prepare("SELECT IFNULL(CONCAT(GROUP_CONCAT(DISTINCT `send_as` SEPARATOR ', '), ', ', GROUP_CONCAT(DISTINCT CONCAT('@',`alias_domain`) SEPARATOR ', ')), '&#10008;') AS `send_as` FROM `sender_acl` LEFT JOIN `alias_domain` ON `alias_domain`.`target_domain` =  TRIM(LEADING '@' FROM `send_as`) WHERE `logged_in_as` = :username AND `send_as` LIKE '@%';");
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
function admin_api($action, $data = null) {
	global $pdo;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	switch ($action) {
		case "edit":
      $regen_key = $data['admin_api_regen_key'];
      $active = (isset($data['active'])) ? 1 : 0;
      $allow_from = array_map('trim', preg_split( "/( |,|;|\n)/", $data['allow_from']));
      foreach ($allow_from as $key => $val) {
        if (!filter_var($val, FILTER_VALIDATE_IP)) {
          unset($allow_from[$key]);
          continue;
        }
      }
      $allow_from = implode(',', array_unique(array_filter($allow_from)));
      if (empty($allow_from)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'List of allowed IPs cannot be empty'
        );
        return false;
      }
      $api_key = implode('-', array(
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3)))
      ));
      $stmt = $pdo->prepare("INSERT INTO `api` (`username`, `api_key`, `active`, `allow_from`)
        SELECT `username`, :api_key, :active, :allow_from FROM `admin` WHERE `superadmin`='1' AND `active`='1'
        ON DUPLICATE KEY UPDATE `active` = :active_u, `allow_from` = :allow_from_u ;");
      $stmt->execute(array(
        ':api_key' => $api_key,
        ':active' => $active,
        ':active_u' => $active,
        ':allow_from' => $allow_from,
        ':allow_from_u' => $allow_from
      ));
    break;
    case "regen_key":
      $api_key = implode('-', array(
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3)))
      ));
      $stmt = $pdo->prepare("UPDATE `api` SET `api_key` = :api_key WHERE `username` IN
        (SELECT `username` FROM `admin` WHERE `superadmin`='1' AND `active`='1')");
      $stmt->execute(array(
        ':api_key' => $api_key
      ));
    break;
  }
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['admin_modified'])
	);
}
function rspamd_ui($action, $data = null) {
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	switch ($action) {
		case "edit":
      $rspamd_ui_pass = $data['rspamd_ui_pass'];
      $rspamd_ui_pass2 = $data['rspamd_ui_pass2'];
      if (empty($rspamd_ui_pass) || empty($rspamd_ui_pass2)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Password cannot be empty'
        );
        return false;
      }
      if ($rspamd_ui_pass != $rspamd_ui_pass2) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Passwords do not match'
        );
        return false;
      }
      if (strlen($rspamd_ui_pass) < 6) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Please use at least 6 characters for your password'
        );
        return false;
      }
      $docker_return = docker('rspamd-mailcow', 'post', 'exec', array('cmd' => 'worker_password', 'raw' => $rspamd_ui_pass), array('Content-Type: application/json'));
      if ($docker_return_array = json_decode($docker_return, true)) {
        if ($docker_return_array['type'] == 'success') {
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => 'Rspamd UI password set successfully'
          );
          return true;
        }
        else {
          $_SESSION['return'] = array(
            'type' => $docker_return_array['type'],
            'msg' => $docker_return_array['msg']
          );
          return false;
        }
      }
      else {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Unknown error'
        );
        return false;
      }
    break;
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
    $stmt = $pdo->query("SELECT `admin`.`username`, `api`.`active` AS `api_active`, `api`.`api_key`, `api`.`allow_from` FROM `admin`
      INNER JOIN `api` ON `admin`.`username` = `api`.`username`
      WHERE `admin`.`superadmin`='1'
        AND `admin`.`active`='1'");
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
function get_u2f_registrations($username) {
  global $pdo;
  $sel = $pdo->prepare("SELECT * FROM `tfa` WHERE `authmech` = 'u2f' AND `username` = ? AND `active` = '1'");
  $sel->execute(array($username));
  return $sel->fetchAll(PDO::FETCH_OBJ);
}
function get_logs($container, $lines = false) {
  if ($lines === false) {
    $lines = $GLOBALS['LOG_LINES'] - 1; 
  }
	global $lang;
	global $redis;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		return false;
	}
  if ($container == "dovecot-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('DOVECOT_MAILLOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('DOVECOT_MAILLOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "postfix-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('POSTFIX_MAILLOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('POSTFIX_MAILLOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "sogo-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('SOGO_LOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('SOGO_LOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "watchdog-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('WATCHDOG_LOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('WATCHDOG_LOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "acme-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('ACME_LOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('ACME_LOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "api-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('API_LOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('API_LOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "netfilter-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('NETFILTER_LOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('NETFILTER_LOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "autodiscover-mailcow") {
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      $data = $redis->lRange('AUTODISCOVER_LOG', intval($from), intval($to));
    }
    else {
      $data = $redis->lRange('AUTODISCOVER_LOG', 0, intval($lines));
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($container == "rspamd-history") {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, '/rspamd-sock/rspamd.sock');
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      curl_setopt($curl, CURLOPT_URL,"http://rspamd/history?from=" . intval($from) . "&to=" . intval($to));
    }
    else {
      curl_setopt($curl, CURLOPT_URL,"http://rspamd/history?to=" . intval($lines));
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $history = curl_exec($curl);
    if (!curl_errno($curl)) {
      $data_array = json_decode($history, true);
      curl_close($curl);
      return $data_array['rows'];
    }
    curl_close($curl);
    return false;
  }
  return false;
}
?>
