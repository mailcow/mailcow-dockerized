<?php
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

	isset($postarray['active'])               ? $active = '1'                 : $active = '0';
	isset($postarray['relay_all_recipients'])	? $relay_all_recipients = '1'   : $relay_all_recipients = '0';
	isset($postarray['backupmx'])             ? $backupmx = '1'               : $backupmx = '0';
	isset($postarray['relay_all_recipients']) ? $backupmx = '1'               : true;

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
		$stmt = $pdo->prepare("INSERT INTO `domain` (`domain`, `description`, `aliases`, `mailboxes`, `maxquota`, `quota`, `transport`, `backupmx`, `created`, `modified`, `active`, `relay_all_recipients`)
			VALUES (:domain, :description, :aliases, :mailboxes, :maxquota, :quota, 'virtual', :backupmx, :created, :modified, :active, :relay_all_recipients)");
		$stmt->execute(array(
			':domain' => $domain,
			':description' => $description,
			':aliases' => $aliases,
			':mailboxes' => $mailboxes,
			':maxquota' => $maxquota,
			':quota' => $quota,
			':backupmx' => $backupmx,
			':active' => $active,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':relay_all_recipients' => $relay_all_recipients
		));
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
	isset($postarray['active']) ? $active = '1' : $active = '0';
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

		$domain       = idn_to_ascii(substr(strstr($address, '@'), 1));
		$local_part   = strstr($address, '@', true);
		$address      = $local_part.'@'.$domain;

		try {
			$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
				WHERE `domain`= :domain1 OR `domain` = (SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain2)");
			$stmt->execute(array(':domain1' => $domain, ':domain2' => $domain));
			$num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($num_results == 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['domain_not_found'], $domain)
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
			$stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
				VALUES (:address, :goto, :domain, :created, :modified, :active)");

			if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
				$stmt->execute(array(
					':address' => '@'.$domain,
					':goto' => $goto,
					':domain' => $domain,
					':created' => date('Y-m-d H:i:s'),
					':modified' => date('Y-m-d H:i:s'),
					':active' => $active
				));
			}
			else {
				$stmt->execute(array(
					':address' => $address,
					':goto' => $goto,
					':domain' => $domain,
					':created' => date('Y-m-d H:i:s'),
					':modified' => date('Y-m-d H:i:s'),
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
	isset($postarray['active']) ? $active = '1' : $active = '0';
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
		$stmt = $pdo->prepare("INSERT INTO `alias_domain` (`alias_domain`, `target_domain`, `created`, `modified`, `active`)
			VALUES (:alias_domain, :target_domain, :created, :modified, :active)");
		$stmt->execute(array(
			':alias_domain' => $alias_domain,
			':target_domain' => $target_domain,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
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

	isset($postarray['active']) ? $active = '1' : $active = '0';

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
					WHERE `domain` = :domain");
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
		$stmt = $pdo->prepare("INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `created`, `modified`, `active`) 
			VALUES (:username, :password_hashed, :name, :maildir, :quota_b, :local_part, :domain, :created, :modified, :active)");
		$stmt->execute(array(
			':username' => $username,
			':password_hashed' => $password_hashed,
			':name' => $name,
			':maildir' => $maildir,
			':quota_b' => $quota_b,
			':local_part' => $local_part,
			':domain' => $domain,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));

		$stmt = $pdo->prepare("INSERT INTO `quota2` (`username`, `bytes`, `messages`)
			VALUES (:username, '0', '0')");
		$stmt->execute(array(':username' => $username));

		$stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `created`, `modified`, `active`)
			VALUES (:username1, :username2, :domain, :created, :modified, :active)");
		$stmt->execute(array(
			':username1' => $username,
			':username2' => $username,
			':domain' => $domain,
			':created' => date('Y-m-d H:i:s'),
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));

		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_added'], htmlspecialchars($username))
		);
	}
	catch (PDOException $e) {
    mailbox_delete_mailbox(array('address' => $username));
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_edit_alias_domain($postarray) {
  // Array elements
  // active             int
  // alias_domain_now   string
  // alias_domain       string
	global $lang;
	global $pdo;
	isset($postarray['active']) ? $active = '1' : $active = '0';
	$alias_domain       = idn_to_ascii(strtolower(trim($postarray['alias_domain'])));
	$alias_domain_now   = strtolower(trim($postarray['alias_domain_now']));
	if (!is_valid_domain_name($alias_domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	if (!is_valid_domain_name($alias_domain_now)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_domain_invalid'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
				WHERE `alias_domain`= :alias_domain_now");
		$stmt->execute(array(':alias_domain_now' => $alias_domain_now));
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
		$stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
		WHERE `target_domain`= :alias_domain");
		$stmt->execute(array(':alias_domain' => $alias_domain));
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
			'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("UPDATE `alias_domain` SET
      `alias_domain` = :alias_domain,
      `active` = :active,
      `modified` = :modified,
        WHERE `alias_domain` = :alias_domain_now");
		$stmt->execute(array(
			':alias_domain' => $alias_domain,
      ':modified' => date('Y-m-d H:i:s'),
			':alias_domain_now' => $alias_domain_now,
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

	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['aliasd_modified'], htmlspecialchars($alias_domain))
	);
}
function mailbox_edit_alias($postarray) {
  // Array elements
  // address            string
  // goto               string    (separated by " ", "," ";" "\n") - email address or domain
  // active             int
	global $lang;
	global $pdo;
	$address      = $postarray['address'];
	$domain       = idn_to_ascii(substr(strstr($address, '@'), 1));
	$local_part   = strstr($address, '@', true);
	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	if (empty($postarray['goto'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['goto_empty'])
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
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['alias_invalid'])
		);
		return false;
	}

	try {
		$stmt = $pdo->prepare("UPDATE `alias` SET
      `goto` = :goto,
      `active`= :active,
      `modified` = :modified,
        WHERE `address` = :address");
		$stmt->execute(array(
			':goto' => $goto,
			':active' => $active,
			':address' => $address,
      ':modified' => date('Y-m-d H:i:s'),
		));
		$_SESSION['return'] = array(
			'type' => 'success',
		'msg' => sprintf($lang['success']['alias_modified'], htmlspecialchars($address))
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
  // quota                  float     (Byte)
  // active                 int

	global $lang;
	global $pdo;
  
  $domain       = idn_to_ascii($postarray['domain']);
	if (!is_valid_domain_name($domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}

	if ($_SESSION['mailcow_cc_role'] == "domainadmin" && 	hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    $description  = $postarray['description'];
    isset($postarray['active']) ? $active = '1' : $active = '0';
    try {
      $stmt = $pdo->prepare("UPDATE `domain` SET 
      `modified`= :modified,
      `description` = :description
        WHERE `domain` = :domain");
      $stmt->execute(array(
        ':modified' => date('Y-m-d H:i:s'),
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
    $description  = $postarray['description'];
    isset($postarray['active']) ? $active = '1' : $active = '0';
    $aliases		= filter_var($postarray['aliases'], FILTER_SANITIZE_NUMBER_FLOAT);
    $mailboxes  = filter_var($postarray['mailboxes'], FILTER_SANITIZE_NUMBER_FLOAT);
    $maxquota		= filter_var($postarray['maxquota'], FILTER_SANITIZE_NUMBER_FLOAT);
    $quota			= filter_var($postarray['quota'], FILTER_SANITIZE_NUMBER_FLOAT);
    isset($postarray['relay_all_recipients']) ? $relay_all_recipients = '1' : $relay_all_recipients = '0';
    isset($postarray['backupmx']) ? $backupmx = '1' : $backupmx = '0';
    isset($postarray['relay_all_recipients']) ? $backupmx = '1' : true;
    try {
      // GET MAILBOX DATA
      $stmt = $pdo->prepare("SELECT 
          COUNT(*) AS count,
          MAX(COALESCE(ROUND(`quota`/1048576), 0)) AS `maxquota`,
          COALESCE(ROUND(SUM(`quota`)/1048576), 0) AS `quota`
            FROM `mailbox`
              WHERE domain= :domain");
      $stmt->execute(array(':domain' => $domain));
      $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
      // GET ALIAS DATA
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

    if ($MailboxData['maxquota'] > $maxquota) {
      echo $MailboxData['maxquota'];
      die();
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['max_quota_in_use'], $MailboxData['maxquota'])
      );
      return false;
    }

    if ($MailboxData['quota'] > $quota) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['domain_quota_m_in_use'], $MailboxData['quota'])
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
      `modified`= :modified,
      `relay_all_recipients` = :relay_all_recipients,
      `backupmx` = :backupmx,
      `active` = :active,
      `quota` = :quota,
      `maxquota` = :maxquota,
      `modified` = :modified,
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
        ':modified' => date('Y-m-d H:i:s'),
        ':mailboxes' => $mailboxes,
        ':aliases' => $aliases,
        ':modified' => date('Y-m-d H:i:s'),
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
function mailbox_edit_mailbox($postarray) {
	global $lang;
	global $pdo;
	isset($postarray['active']) ? $active = '1' : $active = '0';
	if (!filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['username_invalid'])
		);
		return false;
	}
	$quota_m      = $postarray['quota'];
	$quota_b      = $quota_m*1048576;
	$username     = $postarray['username'];
	$name         = $postarray['name'];
	$password     = $postarray['password'];
	$password2    = $postarray['password2'];

	try {
		$stmt = $pdo->prepare("SELECT `domain`
			FROM `mailbox`
				WHERE username = :username");
		$stmt->execute(array(':username' => $username));
		$MailboxData1 = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT 
			COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota_m_now`
				FROM `mailbox`
					WHERE `username` = :username");
		$stmt->execute(array(':username' => $username));
		$MailboxData2 = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT 
			COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota_m_in_use`
				FROM `mailbox`
					WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $MailboxData1['domain']));
		$MailboxData3 = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $pdo->prepare("SELECT `quota`, `maxquota`
			FROM `domain`
				WHERE `domain` = :domain");
		$stmt->execute(array(':domain' => $MailboxData1['domain']));
		$DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

	if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $MailboxData1['domain'])) {
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
	if (($MailboxData3['quota_m_in_use'] - $MailboxData2['quota_m_now'] + $quota_m) > $DomainData['quota']) {
		$quota_left_m = ($DomainData['quota'] - $MailboxData3['quota_m_in_use'] + $MailboxData2['quota_m_now']);
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
		);
		return false;
	}

  // Get sender_acl items set by admin
  $sender_acl_admin = array_merge(
    mailbox_get_sender_acl_handles($username)['sender_acl_domains']['ro'],
    mailbox_get_sender_acl_handles($username)['sender_acl_addresses']['ro']
  );

  // Get sender_acl items from POST array
  (isset($postarray['sender_acl'])) ? $sender_acl_domain_admin = $postarray['sender_acl'] : $sender_acl_domain_admin = array();

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
	if (!empty($password) && !empty($password2)) {
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
					`modified` = :modified,
					`active` = :active
						WHERE `address` = :address");
			$stmt->execute(array(
				':address' => $username,
				':modified' => date('Y-m-d H:i:s'),
				':active' => $active
			));
			$stmt = $pdo->prepare("UPDATE `mailbox` SET
					`modified` = :modified,
					`active` = :active,
					`password` = :password_hashed,
					`name`= :name,
					`quota` = :quota_b
						WHERE `username` = :username");
			$stmt->execute(array(
				':modified' => date('Y-m-d H:i:s'),
				':password_hashed' => $password_hashed,
				':active' => $active,
				':name' => $name,
				':quota_b' => $quota_b,
				':username' => $username
			));
			$_SESSION['return'] = array(
				'type' => 'success',
				'msg' => sprintf($lang['success']['mailbox_modified'], $username)
			);
			return true;
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
				`modified` = :modified,
				`active` = :active
					WHERE `address` = :address");
		$stmt->execute(array(
			':address' => $username,
			':modified' => date('Y-m-d H:i:s'),
			':active' => $active
		));
		$stmt = $pdo->prepare("UPDATE `mailbox` SET
				`modified` = :modified,
				`active` = :active,
				`name`= :name,
				`quota` = :quota_b
					WHERE `username` = :username");
		$stmt->execute(array(
			':active' => $active,
			':modified' => date('Y-m-d H:i:s'),
			':name' => $name,
			':quota_b' => $quota_b,
			':username' => $username
		));
		$_SESSION['return'] = array(
			'type' => 'success',
			'msg' => sprintf($lang['success']['mailbox_modified'], $username)
		);
		return true;
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}
}
function mailbox_get_mailboxes($domain = null) {
	global $lang;
	global $pdo;
  $mailboxes = array();
	if (isset($domain) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
  elseif (isset($domain) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    try {
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `domain` != 'ALL' AND `domain` = :domain");
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
      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role");
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
function mailbox_get_alias_domains($domain = null) {
  // Get all domains assigned to mailcow_cc_username or domain, if set
  // Domain admin needs to be active
  // Domain does not need to be active
	global $lang;
	global $pdo;
  $aliasdomains = array();
	if (isset($domain) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
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
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
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
    $aliasdata['domain'] = $row['domain'];
    $aliasdata['goto'] = $row['goto'];
    $aliasdata['address'] = $row['address'];
    (!filter_var($aliasdata['address'], FILTER_VALIDATE_EMAIL)) ? $aliasdata['is_catch_all'] = 1 : $aliasdata['is_catch_all'] = 0;
    $aliasdata['active'] = $row['active'];
    $aliasdata['active_int'] = $row['active_int'];
    $aliasdata['created'] = $row['created'];
    $aliasdata['modified'] = $row['modified'];
    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $aliasdata['domain'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
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
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
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

  try {
    $domains = array();
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
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}

  try {
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
      ':domain' => $domain,
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS `count`, COALESCE(SUM(`quota`), 0) as `in_use` FROM `mailbox` WHERE `domain` = :domain");
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
      WHERE `domain`= :domain
        AND `address` NOT IN (
          SELECT `username` FROM `mailbox`
        )");
    $stmt->execute(array(
      ':domain' => $domain,
    ));
    $AliasData = $stmt->fetch(PDO::FETCH_ASSOC);
    (isset($AliasData['alias_count'])) ? $domaindata['aliases_in_domain'] = $AliasData['alias_count'] : $domaindata['aliases_in_domain'] = "0";
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
            WHERE `mailbox`.`username` = `quota2`.`username` AND `domain`.`domain` = `mailbox`.`domain` AND `mailbox`.`username` = :mailbox");
    $stmt->execute(array(
      ':mailbox' => $mailbox,
    ));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT `maxquota`, `quota` FROM  `domain` WHERE `domain` = :domain");
    $stmt->execute(array(':domain' => $row['domain']));
    $DomainQuota  = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS `count`, COALESCE(SUM(`quota`), 0) as `in_use` FROM `mailbox` WHERE `domain` = :domain AND `username` != :username");
    $stmt->execute(array(':domain' => $row['domain'], ':username' => $row['username']));
    $MailboxUsage	= $stmt->fetch(PDO::FETCH_ASSOC);

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
  if (!isset($mailboxdata['domain']) ||
    (isset($mailboxdata['domain']) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $mailboxdata['domain']))) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  
  return $mailboxdata;
}
function mailbox_delete_domain($postarray) {
	global $lang;
	global $pdo;
	$domain = $postarray['domain'];
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
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
	$domain	= strtolower(trim($domain));


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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['domain_removed'], htmlspecialchars($domain))
	);
	return true;
}
function mailbox_delete_alias($postarray) {
	global $lang;
	global $pdo;
	$address		= $postarray['address'];
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_removed'], htmlspecialchars($address))
	);

}
function mailbox_delete_alias_domain($postarray) {
	global $lang;
	global $pdo;
	if (!is_valid_domain_name($postarray['alias_domain'])) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['domain_invalid'])
		);
		return false;
	}
	$alias_domain = $postarray['alias_domain'];
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['alias_domain_removed'], htmlspecialchars($alias_domain))
	);
}
function mailbox_delete_mailbox($postarray) {
	global $lang;
	global $pdo;
	$username	= $postarray['username'];
  $domain = mailbox_get_mailbox_details($username)['domain'];
	if (!filter_var($postarray['username'], FILTER_VALIDATE_EMAIL)) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
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
		$stmt = $pdo->prepare("SELECT `address`, `goto` FROM `alias`
				WHERE `goto` LIKE :username");
		$stmt->execute(array(':username' => '%'.$username.'%'));
		$GotoData = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($GotoData as $gotos) {
			$goto_exploded = explode(',', $gotos['goto']);
			if (($key = array_search($username, $goto_exploded)) !== false) {
				unset($goto_exploded[$key]);
			}
			$gotos_rebuild = implode(',', $goto_exploded);
			$stmt = $pdo->prepare("UPDATE `alias` SET
        `goto` = :goto,
        `modified` = :modified,
          WHERE `address` = :address");
			$stmt->execute(array(
				':goto' => $gotos_rebuild,
        ':modified' => date('Y-m-d H:i:s'),
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
	$_SESSION['return'] = array(
		'type' => 'success',
		'msg' => sprintf($lang['success']['mailbox_removed'], htmlspecialchars($username))
	);
}
function mailbox_get_sender_acl_handles($mailbox) {
	global $pdo;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
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
    $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` = :goto AND `address` NOT LIKE '@%'");
    $stmt->execute(array(':goto' => $mailbox));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($rows)) {
      $data['fixed_sender_aliases'][] = $row['address'];
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($row = array_shift($rows)) {
      if (is_valid_domain_name($row['domain']) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['domain'])) {
        $data['sender_acl_domains']['selectable'][] = $row['domain'];
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
    while ($row = array_shift($rows)) {
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