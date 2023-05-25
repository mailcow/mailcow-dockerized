<?php
function check_login($user, $pass, $app_passwd_data = false, $is_internal = false) {
  global $pdo;
  global $redis;

  if (!filter_var($user, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $user))) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $user, '*'),
      'msg' => 'malformed_username'
    );
    return false;
  }

  // Validate admin
  $result = mailcow_admin_login($user, $pass);
  if ($result !== false) return $result;

  // Validate domain admin
  $result = mailcow_domainadmin_login($user, $pass);
  if ($result !== false) return $result;

  // Validate mailbox user
  // check authsource
  $stmt = $pdo->prepare("SELECT authsource, mailbox.active AS mailbox_active, domain.active AS domain_active FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row && $row['domain_active'] == 1){
    // mbox does not exist, call keycloak login and create mbox if possible via rest flow
    $iam_settings = identity_provider('get');
    if ($iam_settings['authsource'] == 'keycloak' && intval($iam_settings['mailboxpassword_flow']) == 1){
      $result = keycloak_mbox_login_rest($user, $pass, $iam_settings, $is_internal, true);
      if ($result !== false) return $result;
    }
  } else if ($row && $row['mailbox_active'] == 1 && $row['domain_active'] == 1) {
    // mbox does exist and is active
    if (isset($app_passwd_data)){
      // first check if password is app_password
      $result = mailcow_mbox_apppass_login($user, $pass, $app_passwd_data, $is_internal);
      if ($result !== false) return $result;
    }

    if ($row['authsource'] == 'mailcow') {
      // mbox authsource is mailcow
      $result = mailcow_mbox_login($user, $pass, $app_passwd_data, $is_internal);
      if ($result !== false) return $result;
    } else if ($row['authsource'] == 'keycloak'){
      // mbox authsource is keycloak, try using via rest flow
      $iam_settings = identity_provider('get');
      if (intval($iam_settings['mailboxpassword_flow']) == 1){
        $result = keycloak_mbox_login_rest($user, $pass, $iam_settings, $is_internal);
        if ($result !== false) return $result;
      }
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

function mailcow_admin_login($user, $pass){
  global $pdo;

  $user = strtolower(trim($user));
  $stmt = $pdo->prepare("SELECT `password` FROM `admin`
      WHERE `superadmin` = '1'
      AND `active` = '1'
      AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
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
  }

  return false;
}
function mailcow_domainadmin_login($user, $pass){
  global $pdo;

  $stmt = $pdo->prepare("SELECT `password` FROM `admin`
      WHERE `superadmin` = '0'
      AND `active`='1'
      AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
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
  }

  return false;
}
function mailcow_mbox_login($user, $pass, $app_passwd_data = false, $is_internal = false){
  global $pdo;

  $stmt = $pdo->prepare("SELECT * FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `mailbox`.`active`='1'
        AND `domain`.`active`='1'
        AND (`mailbox`.`authsource`='mailcow' OR `mailbox`.`authsource` IS NULL)
        AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) { 
    // verify password
    if (verify_hash($row['password'], $pass) !== false) {
      if (!array_key_exists("app_passwd_id", $row)){ 
        // password is not a app password
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
            'log' => array(__FUNCTION__, $user, '*'),
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
              'log' => array(__FUNCTION__, $user, '*'),
              'msg' => array('logged_in_as', $user)
            );
          }
          return "user";
        }
      }
    }
  }

  return false;
}
function mailcow_mbox_apppass_login($user, $pass, $app_passwd_data, $is_internal = false){
  global $pdo;

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
  $stmt = $pdo->prepare("SELECT `app_passwd`.`password` as `password`, `app_passwd`.`id` as `app_passwd_id` FROM `app_passwd`
    INNER JOIN `mailbox` ON `mailbox`.`username` = `app_passwd`.`mailbox`
    INNER JOIN `domain` ON `mailbox`.`domain` = `domain`.`domain`
    WHERE `mailbox`.`kind` NOT REGEXP 'location|thing|group'
      AND `mailbox`.`active` = '1'
      AND `domain`.`active` = '1'
      AND `app_passwd`.`active` = '1'
      AND `app_passwd`.`mailbox` = :user
      :has_access_query"
  );
  // check if app password has protocol access
  // skip if protocol is false and the call is internal
  $has_access_query = ($is_internal && $protocol === false) ? "" : " AND `app_passwd`.`" . $protocol . "_access` = '1'";
  // fetch password data
  $stmt->execute(array(
    ':user' => $user,
    ':has_access_query' => $has_access_query
  ));
  $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
  
  foreach ($rows as $row) { 
    // verify password
    if (verify_hash($row['password'], $pass) !== false) {
      if ($is_internal){
        // skip sasl_log, dovecot does the job
        return "user";
      }

      $service = strtoupper($is_app_passwd);
      $stmt = $pdo->prepare("REPLACE INTO sasl_log (`service`, `app_password`, `username`, `real_rip`) VALUES (:service, :app_id, :username, :remote_addr)");
      $stmt->execute(array(
        ':service' => $service,
        ':app_id' => $row['app_passwd_id'],
        ':username' => $user,
        ':remote_addr' => ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'])
      ));

      unset($_SESSION['ldelay']);
      return "user";
    }
  }

  return false;
}
// Keycloak REST Api Flow - auth user by mailcow_password attribute
// This password will be used for direct UI, IMAP and SMTP Auth
// To use direct user credentials, only Authorization Code Flow is valid
function keycloak_mbox_login_rest($user, $pass, $iam_settings, $is_internal = false, $create = false){
  global $pdo;

  // get access_token for service account of mailcow client
  $admin_token = identity_provider("get-keycloak-admin-token");

  // get the mailcow_password attribute from keycloak user
  $url = "{$iam_settings['server_url']}/admin/realms/{$iam_settings['realm']}/users";
  $queryParams = array('email' => $user, 'exact' => true);
  $queryString = http_build_query($queryParams);
  $curl = curl_init();
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
    return false;
  }

  // validate mailcow_password
  $mailcow_password = $user_res['attributes']['mailcow_password'][0];
  if (!verify_hash($mailcow_password, $pass)) {
    return false;
  }

  // get mapped template, if not set return false
  // also return false if no mappers were defined
  $user_template = $user_data['attributes']['mailcow_template'][0];
  if ($create && (empty($iam_settings['mappers']) || $user_template)){
    return false;
  } else if (!$create) {
    // login success - dont create mailbox
    $_SESSION['return'][] =  array(
      'type' => 'success',
      'log' => array(__FUNCTION__, $user, '*'),
      'msg' => array('logged_in_as', $user)
    );
    return 'user';
  }

  // check if matching attribute exist
  $mapper_key = array_search($user_template, $iam_settings['mappers']);
  if ($mapper_key === false) return false;

  // create mailbox
  $create_res = mailbox('add', 'mailbox_from_template', array(
    'domain' => explode('@', $user)[1],
    'local_part' => explode('@', $user)[0],
    'authsource' => 'keycloak',
    'template' => $iam_settings['mappers'][$mapper_key]
  ));
  if (!$create_res) return false;

  // check if created mailbox from template is even active
  // maybe dont even create it if active != 1
  if ($mailbox_attributes['active'] != 1){
    return false;
  }

  $_SESSION['return'][] =  array(
    'type' => 'success',
    'log' => array(__FUNCTION__, $user, '*'),
    'msg' => array('logged_in_as', $user)
  );
  return 'user';
}
