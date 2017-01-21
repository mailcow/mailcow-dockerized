<?php
function dkim_add_key($postarray) {
	global $lang;
	global $pdo;
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
  $domain	= $postarray['domain'];
  if (!is_valid_domain_name($domain) || !is_numeric($key_length)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
    );
    return false;
  }

  if (!empty(glob($GLOBALS['MC_DKIM_TXTS'] . '/' . $domain . '.dkim'))) {
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
    // Save public key to file
    file_put_contents($GLOBALS['MC_DKIM_TXTS'] . '/' . $domain . '.dkim', $pubKey);
    // Save private key to file
    openssl_pkey_export_to_file($keypair_ressource, $GLOBALS['MC_DKIM_KEYS'] . '/' . $domain . '.dkim');
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
  $data = array();
  if (hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
    $dkim_pubkey_file = escapeshellarg($GLOBALS["MC_DKIM_TXTS"]. "/" . $domain . "." . "dkim");
    if (file_exists(substr($dkim_pubkey_file, 1, -1))) {
      $data['pubkey'] = file_get_contents($GLOBALS["MC_DKIM_TXTS"]. "/" . $domain . "." . "dkim");
      $data['length'] = (strlen($data['pubkey']) < 391) ? 1024 : 2048;
      $data['dkim_txt'] = 'v=DKIM1;k=rsa;t=s;s=email;p=' . file_get_contents($GLOBALS["MC_DKIM_TXTS"]. "/" . $domain . "." . "dkim");
    }
  }
  return $data;
}
function dkim_get_blind_keys() {
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  $domains = array();
  $dnstxt_folder = scandir($GLOBALS["MC_DKIM_TXTS"]);
  $dnstxt_files = array_diff($dnstxt_folder, array('.', '..'));
  foreach($dnstxt_files as $file) {
    $domains[] = substr($file, 0, -5);
  }
  return array_diff($domains, array_merge(mailbox_get_domains(), mailbox_get_alias_domains()));
}
function dkim_delete_key($postarray) {
	global $lang;
  $domain	= $postarray['domain'];

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
  if (!is_valid_domain_name($domain)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_domain_or_sel_invalid'])
    );
    return false;
  }
  exec('rm ' . escapeshellarg($GLOBALS['MC_DKIM_TXTS'] . '/' . $domain . '.dkim'), $out, $return);
  if ($return != "0") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_remove_failed'])
    );
    return false;
  }
  exec('rm ' . escapeshellarg($GLOBALS['MC_DKIM_KEYS'] . '/' . $domain . '.dkim'), $out, $return);
  if ($return != "0") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['dkim_remove_failed'])
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['dkim_removed'])
  );
  return true;
}