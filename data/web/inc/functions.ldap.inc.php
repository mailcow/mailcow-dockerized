<?php

function ldap_do_bind() {
	global $ldap_config;
	$version         = isset($ldap_config['version']) ? $ldap_config['version'] : 3;
	$ldap_uri        = $ldap_config['uri'];
	$base_dn         = $ldap_config['base_dn'];
	$bind_admin_dn   = isset($ldap_config['bind_admin_dn']) ? $ldap_config['bind_admin_dn'] : null;
	$bind_admin_pass = isset($ldap_config['bind_admin_pass']) ? $ldap_config['bind_admin_pass'] : null;

	// When OpenLDAP 2.x.x is used, ldap_connect() will always return a resource as it does not actually connect but just
	// initializes the connecting parameters. The actual connect happens with the next calls to ldap_* funcs, usually with ldap_bind().
	// See http://php.net/manual/en/function.ldap-connect.php
	$ldap = ldap_connect($ldap_uri);
	if( ! $ldap) {
		error_log("mailcow UI: Cannot connect to LDAP server \"$ldap_uri\"");

		return false;
	}

	// The following is recommended here http://php.net/manual/en/ref.ldap.php
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, $version);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

	// Admin bind
	if($bind_admin_dn && $bind_admin_pass) {
		$bind_rdn = join(',', array($bind_admin_dn, $base_dn));
		if( ! ldap_bind($ldap, $bind_rdn, $bind_admin_pass)) {
			error_log("mailcow UI: Cannot bind to LDAP server \"$ldap_uri\" using \"$bind_rdn\"");

			return false;
		}
	}

	// Anonymous bind
	if( ! ldap_bind($ldap)) {
		error_log("mailcow UI: Cannot bind to LDAP server \"$ldap_uri\"");

		return false;
	}

	return $ldap;
}

function ldap_get_user_info($ldap, $user, $attr = array('dn', 'uid', 'mail')) {
	global $ldap_config;
	$base_dn = $ldap_config['base_dn'];
	$filter  = str_replace('%', $user, isset($ldap_config['user_filter']) ? $ldap_config['user_filter'] : '(|(uid=%*)(mail=%*))');
	$result  = ldap_search($ldap, $base_dn, $filter, $attr);
	$entries = ldap_get_entries($ldap, $result);

	return isset($entries['count']) && $entries['count'] > 0 ? $entries[0] : null;
}

function ldap_do_login($ldap, $info, $pass) {
	return isset($info['dn']) && ldap_bind($ldap, $info['dn'], $pass);
}

function ldap_sync_db($info, $user, $pass) {
	global $pdo;

	$mail = array();
	if(isset($info['mail']['count'])) {
		for($i = 0; $i < $info['mail']['count']; $i ++) {
			array_push($mail, $info['mail'][ $i ]);
		}
	}

	// Update table "mailbox" using the "mail" attribute
	$stmt = $pdo->prepare("
		UPDATE `mailbox` SET password = ? 
		WHERE `username` IN (".join(',', array_fill(0, count($mail), '?')).")");
	$stmt->execute(array_merge(array(hash_password($pass)), $mail));

	// Update table "admin"
	$stmt = $pdo->prepare("UPDATE `admin` SET password = :pass WHERE `username` LIKE :user");
	$stmt->execute(array(
		'user' => $user,
		'pass' => hash_password($pass)
	));
}

function ldap_check_login($user, $pass) {

	$ldap = ldap_do_bind();
	if( ! $ldap) {
		return false;
	}

	$info = ldap_get_user_info($ldap, $user);
	if( ! $info) {
		return false;
	}

	if( ! ldap_do_login($ldap, $info, $pass)) {
		return false;
	}

	// We are successfully logged in. Update db and pass back to normal login
	ldap_sync_db($info, $user, $pass);

	return check_login($user, $pass, true);
}