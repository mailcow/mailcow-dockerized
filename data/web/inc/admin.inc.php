<?php
function get_admin_details() {
  // No parameter to be given, only one admin should exist
	global $pdo;
	global $lang;
  $data = array();
  if ($_SESSION['mailcow_cc_role'] != 'admin') {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT `username`, `modified`, `created` FROM `admin`WHERE `superadmin`='1' AND active='1'");
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
function edit_admin($postarray) {
	global $lang;
	global $pdo;
	$username     = $postarray['username'];
	$password     = $postarray['password'];
	$password2    = $postarray['password2'];
	isset($postarray['active']) ? $active = '1' : $active = '0';

	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
	
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
	}
	catch (PDOException $e) {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => 'MySQL: '.$e
		);
		return false;
	}

  if(isset($postarray['domain'])) {
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
		if ($password != $password2) {
			$_SESSION['return'] = array(
				'type' => 'danger',
				'msg' => sprintf($lang['danger']['password_mismatch'])
			);
			return false;
		}
		$password_hashed = hash_password($password);
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET `modified` = :modified, `active` = :active, `password` = :password_hashed WHERE `username` = :username");
			$stmt->execute(array(
				':password_hashed' => $password_hashed,
				':username' => $username,
				':modified' => date('Y-m-d H:i:s'),
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
		try {
			$stmt = $pdo->prepare("UPDATE `admin` SET `modified` = :modified, `active` = :active WHERE `username` = :username");
			$stmt->execute(array(
				':username' => $username,
				':modified' => date('Y-m-d H:i:s'),
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
		'msg' => sprintf($lang['success']['domain_admin_modified'], htmlspecialchars($username))
	);
}