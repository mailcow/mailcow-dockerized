<?php
function check_login($user, $pass, $app_passwd_data = false, $extra = null) {
  global $pdo;
  global $redis;

  $is_internal = $extra['is_internal'];
  $role = $extra['role'];

  // Try validate admin
  if (!isset($role) || $role == "admin") {
    $result = admin_login($user, $pass);
    if ($result !== false){
      return $result;
    }
  }

  // Try validate domain admin
  if (!isset($role) || $role == "domain_admin") {
    $result = domainadmin_login($user, $pass);
    if ($result !== false) {
      return $result;
    }
  }

  // Try validate user
  if (!isset($role) || $role == "user") {
    $result = user_login($user, $pass);
    if ($result !== false) {
      if ($app_passwd_data['eas'] === true) {
        $service = 'EAS';
      } elseif ($app_passwd_data['dav'] === true) {
        $service = 'DAV';
      } else {
        $service = 'MAILCOWUI';
      }
      $real_rip = ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR']);
      set_sasl_log($user, $real_rip, $service);
      return $result;
    }
  }

  // Try validate app password
  if (!isset($role) || $role == "app") {
    $result = apppass_login($user, $pass, $app_passwd_data);
    if ($result !== false) {
      if ($app_passwd_data['eas'] === true) {
        $service = 'EAS';
      } elseif ($app_passwd_data['dav'] === true) {
        $service = 'DAV';
      } else {
        $service = 'NONE';
      }
      $real_rip = ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR']);
      set_sasl_log($user, $real_rip, $service, $pass);
      return $result;
    }
  }

  // skip log and only return false if it's an internal request
  if ($is_internal == true) return false;

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
  $_SESSION['return'][] =  array(
    'type' => 'danger',
    'log' => array(__FUNCTION__, $user, '*'),
    'msg' => 'login_failed'
  );

  sleep($_SESSION['ldelay']);
  return false;
}

function admin_login($user, $pass){
  global $pdo;

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    if (!$is_internal){
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'malformed_username'
      );
    }
    return false;
  }

  $user = strtolower(trim($user));
  $stmt = $pdo->prepare("SELECT `password` FROM `admin`
      WHERE `superadmin` = '1'
      AND `active` = '1'
      AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // verify password
  if (verify_hash($row['password'], $pass)) {
    // check for tfa authenticators
    $authenticators = get_tfa($user);
    if (isset($authenticators['additional']) && is_array($authenticators['additional']) && count($authenticators['additional']) > 0) {
      // active tfa authenticators found, set pending user login
      $_SESSION['pending_mailcow_cc_username'] = $user;
      $_SESSION['pending_mailcow_cc_role'] = "admin";
      $_SESSION['pending_tfa_methods'] = $authenticators['additional'];
      unset($_SESSION['ldelay']);
      $_SESSION['return'][] =  array(
        'type' => 'info',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'awaiting_tfa_confirmation'
      );
      return "pending";
    } else {
      unset($_SESSION['ldelay']);
      // Reactivate TFA if it was set to "deactivate TFA for next login"
      $stmt = $pdo->prepare("UPDATE `tfa` SET `active`='1' WHERE `username` = :user");
      $stmt->execute(array(':user' => $user));
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => array('logged_in_as', $user)
      );
      return "admin";
    }
  }

  return false;
}
function domainadmin_login($user, $pass){
  global $pdo;

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    if (!$is_internal){
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'malformed_username'
      );
    }
    return false;
  }

  $stmt = $pdo->prepare("SELECT `password` FROM `admin`
      WHERE `superadmin` = '0'
      AND `active`='1'
      AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // verify password
  if (verify_hash($row['password'], $pass) !== false) {
    // check for tfa authenticators
    $authenticators = get_tfa($user);
    if (isset($authenticators['additional']) && is_array($authenticators['additional']) && count($authenticators['additional']) > 0) {
      $_SESSION['pending_mailcow_cc_username'] = $user;
      $_SESSION['pending_mailcow_cc_role'] = "domainadmin";
      $_SESSION['pending_tfa_methods'] = $authenticators['additional'];
      unset($_SESSION['ldelay']);
      $_SESSION['return'][] =  array(
        'type' => 'info',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'awaiting_tfa_confirmation'
      );
      return "pending";
    }
    else {
      unset($_SESSION['ldelay']);
      // Reactivate TFA if it was set to "deactivate TFA for next login"
      $stmt = $pdo->prepare("UPDATE `tfa` SET `active`='1' WHERE `username` = :user");
      $stmt->execute(array(':user' => $user));
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => array('logged_in_as', $user)
      );
      return "domainadmin";
    }
  }

  return false;
}
function user_login($user, $pass, $extra = null){
  global $pdo;
  global $iam_provider;
  global $iam_settings;

  $is_internal = $extra['is_internal'];

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    if (!$is_internal){
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'malformed_username'
      );
    }
    return false;
  }

  $stmt = $pdo->prepare("SELECT
      mailbox.*,
      domain.active AS d_active
      FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // user does not exist, try call idp login and create user if possible via rest flow
  if (!$row){
    $result = false;
    if ($iam_settings['authsource'] == 'keycloak' && intval($iam_settings['mailpassword_flow']) == 1){
      $result = keycloak_mbox_login_rest($user, $pass, array('is_internal' => $is_internal, 'create' => true));
    } else if ($iam_settings['authsource'] == 'ldap') {
      $result = ldap_mbox_login($user, $pass, array('is_internal' => $is_internal, 'create' => true));
    }
    if ($result !== false){
      // double check if mailbox is active
      $stmt = $pdo->prepare("SELECT * FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `mailbox`.`active`='1'
        AND `domain`.`active`='1'
        AND `username` = :user");
      $stmt->execute(array(':user' => $user));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!empty($row)) {
        return true;
      }
    }
    clear_session();
    return false;
  }

  switch ($row['authsource']) {
    case 'keycloak':
      // user authsource is keycloak, try using via rest flow
      if (intval($iam_settings['mailpassword_flow']) == 1){
        $result = keycloak_mbox_login_rest($user, $pass, array('is_internal' => $is_internal));
        if ($result !== false) {
          // double check if mailbox and domain is active
          $stmt = $pdo->prepare("SELECT * FROM `mailbox`
          INNER JOIN domain on mailbox.domain = domain.domain
          WHERE `kind` NOT REGEXP 'location|thing|group'
            AND `mailbox`.`active`='1'
            AND `domain`.`active`='1'
            AND `username` = :user");
          $stmt->execute(array(':user' => $user));
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if (empty($row)) {
            return false;
          }

          // check for tfa authenticators
          $authenticators = get_tfa($user);
          if (isset($authenticators['additional']) && is_array($authenticators['additional']) && count($authenticators['additional']) > 0 && !$is_internal) {
            // authenticators found, init TFA flow
            $_SESSION['pending_mailcow_cc_username'] = $user;
            $_SESSION['pending_mailcow_cc_role'] = "user";
            $_SESSION['pending_tfa_methods'] = $authenticators['additional'];
            unset($_SESSION['ldelay']);
            $_SESSION['return'][] =  array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $user, '*', 'Provider: Keycloak'),
              'msg' => array('logged_in_as', $user)
            );
            return "pending";
          } else if (!isset($authenticators['additional']) || !is_array($authenticators['additional']) || count($authenticators['additional']) == 0) {
            // no authenticators found, login successfull
            if (!$is_internal){
              unset($_SESSION['ldelay']);
              // Reactivate TFA if it was set to "deactivate TFA for next login"
              $stmt = $pdo->prepare("UPDATE `tfa` SET `active`='1' WHERE `username` = :user");
              $stmt->execute(array(':user' => $user));
              $_SESSION['return'][] =  array(
                'type' => 'success',
                'log' => array(__FUNCTION__, $user, '*', 'Provider: Keycloak'),
                'msg' => array('logged_in_as', $user)
              );
            }
            return "user";
          }
        }
        return $result;
      } else {
        return false;
      }
    break;
    case 'ldap':
      // user authsource is ldap
      $result = ldap_mbox_login($user, $pass, array('is_internal' => $is_internal));
      if ($result !== false) {
        // double check if mailbox and domain is active
        $stmt = $pdo->prepare("SELECT * FROM `mailbox`
        INNER JOIN domain on mailbox.domain = domain.domain
        WHERE `kind` NOT REGEXP 'location|thing|group'
          AND `mailbox`.`active`='1'
          AND `domain`.`active`='1'
          AND `username` = :user");
        $stmt->execute(array(':user' => $user));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($row)) {
          return false;
        }

        // check for tfa authenticators
        $authenticators = get_tfa($user);
        if (isset($authenticators['additional']) && is_array($authenticators['additional']) && count($authenticators['additional']) > 0 && !$is_internal) {
          // authenticators found, init TFA flow
          $_SESSION['pending_mailcow_cc_username'] = $user;
          $_SESSION['pending_mailcow_cc_role'] = "user";
          $_SESSION['pending_tfa_methods'] = $authenticators['additional'];
          unset($_SESSION['ldelay']);
          $_SESSION['return'][] =  array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $user, '*', 'Provider: LDAP'),
            'msg' => array('logged_in_as', $user)
          );
          return "pending";
        } else if (!isset($authenticators['additional']) || !is_array($authenticators['additional']) || count($authenticators['additional']) == 0) {
          // no authenticators found, login successfull
          if (!$is_internal){
            unset($_SESSION['ldelay']);
            // Reactivate TFA if it was set to "deactivate TFA for next login"
            $stmt = $pdo->prepare("UPDATE `tfa` SET `active`='1' WHERE `username` = :user");
            $stmt->execute(array(':user' => $user));
            $_SESSION['return'][] =  array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $user, '*', 'Provider: LDAP'),
              'msg' => array('logged_in_as', $user)
            );
          }
          return "user";
        }
      }
      return $result;
    break;
    case 'mailcow':
      if ($row['active'] != 1 || $row['d_active'] != 1) {
        return false;
      }
      // verify password
      if (verify_hash($row['password'], $pass) !== false) {
        // check for tfa authenticators
        $authenticators = get_tfa($user);
        if (isset($authenticators['additional']) && is_array($authenticators['additional']) && count($authenticators['additional']) > 0 && !$is_internal) {
          // authenticators found, init TFA flow
          $_SESSION['pending_mailcow_cc_username'] = $user;
          $_SESSION['pending_mailcow_cc_role'] = "user";
          $_SESSION['pending_tfa_methods'] = $authenticators['additional'];
          unset($_SESSION['ldelay']);
          $_SESSION['return'][] =  array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $user, '*', 'Provider: mailcow'),
            'msg' => array('logged_in_as', $user)
          );
          return "pending";
        } else if (!isset($authenticators['additional']) || !is_array($authenticators['additional']) || count($authenticators['additional']) == 0) {
          // no authenticators found, login successfull
          if (!$is_internal){
            unset($_SESSION['ldelay']);
            // Reactivate TFA if it was set to "deactivate TFA for next login"
            $stmt = $pdo->prepare("UPDATE `tfa` SET `active`='1' WHERE `username` = :user");
            $stmt->execute(array(':user' => $user));
            $_SESSION['return'][] =  array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $user, '*', 'Provider: mailcow'),
              'msg' => array('logged_in_as', $user)
            );
          }
          return "user";
        }
      }
    break;
  }

  return false;
}
function apppass_login($user, $pass, $app_passwd_data, $extra = null){
  global $pdo;

  $is_internal = $extra['is_internal'];

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    if (!$is_internal){
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'malformed_username'
      );
    }
    return false;
  }

  $protocol = false;
  if ($app_passwd_data['eas']){
    $protocol = 'eas';
  } else if ($app_passwd_data['dav']){
    $protocol = 'dav';
  } else if ($app_passwd_data['smtp']){
    $protocol = 'smtp';
  } else if ($app_passwd_data['imap']){
    $protocol = 'imap';
  } else if ($app_passwd_data['sieve']){
    $protocol = 'sieve';
  } else if ($app_passwd_data['pop3']){
    $protocol = 'pop3';
  } else if (!$is_internal) {
    return false;
  }

  // fetch app password data
  $stmt = $pdo->prepare("SELECT `app_passwd`.*, `app_passwd`.`password` as `password`, `app_passwd`.`id` as `app_passwd_id` FROM `app_passwd`
    INNER JOIN `mailbox` ON `mailbox`.`username` = `app_passwd`.`mailbox`
    INNER JOIN `domain` ON `mailbox`.`domain` = `domain`.`domain`
    WHERE `mailbox`.`kind` NOT REGEXP 'location|thing|group'
      AND `mailbox`.`active` = '1'
      AND `domain`.`active` = '1'
      AND `app_passwd`.`active` = '1'
      AND `app_passwd`.`mailbox` = :user"
  );
  // fetch password data
  $stmt->execute(array(
    ':user' => $user,
  ));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    if ($protocol && $row[$protocol . '_access'] != '1'){
      continue;
    }

    // verify password
    if (verify_hash($row['password'], $pass) !== false) {
      $_SESSION['app_passwd_id'] = $row['app_passwd_id'];
      unset($_SESSION['ldelay']);
      return "user";
    }
  }

  return false;
}
// Keycloak REST Api Flow - auth user by mailcow_password attribute
// This password will be used for direct UI, IMAP and SMTP Auth
// To use direct user credentials, only Authorization Code Flow is valid
function keycloak_mbox_login_rest($user, $pass, $extra = null){
  global $pdo;
  global $iam_provider;
  global $iam_settings;

  $is_internal = $extra['is_internal'];
  $create = $extra['create'];

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    if (!$is_internal){
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'malformed_username'
      );
    }
    return false;
  }

  // get access_token for service account of mailcow client
  $admin_token = identity_provider("get-keycloak-admin-token");

  // get the mailcow_password attribute from keycloak user
  $url = "{$iam_settings['server_url']}/admin/realms/{$iam_settings['realm']}/users";
  $queryParams = array('email' => $user, 'exact' => true);
  $queryString = http_build_query($queryParams);
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_TIMEOUT, 7);
  curl_setopt($curl, CURLOPT_URL, $url . '?' . $queryString);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $admin_token,
      'Content-Type: application/json'
  ));
  $user_res = json_decode(curl_exec($curl), true)[0];
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  if ($code != 200) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', 'Identity Provider returned HTTP ' . $code),
      'msg' => 'generic_server_error'
    );
    return false;
  }
  if (!isset($user_res['attributes']['mailcow_password']) || !is_array($user_res['attributes']['mailcow_password'])){
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', 'User has no mailcow_password attribute'),
      'msg' => 'generic_server_error'
    );
    return false;
  }
  if (empty($user_res['attributes']['mailcow_password'][0])){
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', "User's mailcow_password attribute is empty"),
      'msg' => 'generic_server_error'
    );
    return false;
  }

  // validate mailcow_password
  $mailcow_password = $user_res['attributes']['mailcow_password'][0];
  if (!verify_hash($mailcow_password, $pass)) {
    return false;
  }

  // get mapped template
  $user_template = $user_res['attributes']['mailcow_template'][0];
  $mapper_key = array_search($user_template, $iam_settings['mappers']);

  if (!$create) {
    // login success
    if ($mapper_key !== false) {
      // update user
      $_SESSION['access_all_exception'] = '1';
      mailbox('edit', 'mailbox_from_template', array(
        'username' => $user,
        'name' => $user_res['name'],
        'template' => $iam_settings['templates'][$mapper_key]
      ));
      $_SESSION['access_all_exception'] = '0';
    }
    return 'user';
  }

  // check if matching attribute exist
  if (empty($iam_settings['mappers']) || !$user_template || $mapper_key === false) {
    if (!empty($iam_settings['default_template'])) {
      $mbox_template = $iam_settings['default_template'];
    } else {
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*', 'No matching attribute mapping was found'),
        'msg' => 'generic_server_error'
      );
      return false;
    }
  } else {
    $mbox_template = $iam_settings['templates'][$mapper_key];
  }

  // create mailbox
  $_SESSION['access_all_exception'] = '1';
  $create_res = mailbox('add', 'mailbox_from_template', array(
    'domain' => explode('@', $user)[1],
    'local_part' => explode('@', $user)[0],
    'name' => $user_res['name'],
    'authsource' => 'keycloak',
    'template' => $mbox_template
  ));
  $_SESSION['access_all_exception'] = '0';
  if (!$create_res){
    clear_session();
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', 'Could not create mailbox on login'),
      'msg' => 'generic_server_error'
    );
    return false;
  }

  return 'user';
}
function ldap_mbox_login($user, $pass, $extra = null){
  global $pdo;
  global $iam_provider;
  global $iam_settings;

  $is_internal = $extra['is_internal'];
  $create = $extra['create'];

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    if (!$is_internal){
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*'),
        'msg' => 'malformed_username'
      );
    }
    return false;
  }
  if (!$iam_provider) {
    return false;
  }

  try {
    $ldap_query = $iam_provider->query();
    if (!empty($iam_settings['filter'])) {
      $ldap_query = $ldap_query->rawFilter($iam_settings['filter']);
    }
    $ldap_query = $ldap_query->where($iam_settings['username_field'], '=', $user)
      ->select([$iam_settings['username_field'], $iam_settings['attribute_field'], 'displayname', 'distinguishedname', 'dn']);

    $user_res = $ldap_query->firstOrFail();
  } catch (Exception $e) {
    // clear $_SESSION['return'] to not leak data
    $_SESSION['return'] = array();
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', $e->getMessage()),
      'msg' => 'generic_server_error'
    );
    return false;
  }
  try {
    if (!$iam_provider->auth()->attempt($user_res['dn'], $pass)) {
      return false;
    }
  } catch (Exception $e) {
    // clear $_SESSION['return'] to not leak data
    $_SESSION['return'] = array();
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', $e->getMessage()),
      'msg' => 'generic_server_error'
    );
    return false;
  }

  // get mapped template
  $user_template = $user_res[$iam_settings['attribute_field']][0];
  $mapper_key = array_search($user_template, $iam_settings['mappers']);

  if (!$create) {
    // login success
    if ($mapper_key !== false) {
      // update user
      $_SESSION['access_all_exception'] = '1';
      mailbox('edit', 'mailbox_from_template', array(
        'username' => $user,
        'name' => $user_res['displayname'][0],
        'template' => $iam_settings['templates'][$mapper_key]
      ));
      $_SESSION['access_all_exception'] = '0';
    }
    return 'user';
  }

  // check if matching attribute exist
  if (empty($iam_settings['mappers']) || !$user_template || $mapper_key === false) {
    if (!empty($iam_settings['default_tempalte'])) {
      $mbox_template = $iam_settings['default_tempalte'];
    } else {
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $user, '*', 'No matching attribute mapping was found'),
        'msg' => 'generic_server_error'
      );
      return false;
    }
  } else {
    $mbox_template = $iam_settings['templates'][$mapper_key];
  }

  // create mailbox
  $_SESSION['access_all_exception'] = '1';
  $create_res = mailbox('add', 'mailbox_from_template', array(
    'domain' => explode('@', $user)[1],
    'local_part' => explode('@', $user)[0],
    'name' => $user_res['displayname'][0],
    'authsource' => 'ldap',
    'template' => $mbox_template
  ));
  $_SESSION['access_all_exception'] = '0';
  if (!$create_res){
    clear_session();
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*', 'Could not create mailbox on login'),
      'msg' => 'generic_server_error'
    );
    return false;
  }

  return 'user';
}
