<?php
function check_login($user, $pass, $app_passwd_data = false) {
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
  if ($result){
    return $result;
  }

  // Validate domain admin
  $result = mailcow_domainadmin_login($user, $pass);
  if ($result){
    return $result;
  }

  // Validate mailbox user
  // skip log & ldelay if requests comes from dovecot
  $is_dovecot = false;
  $request_ip =  ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR']);
  if ($request_ip == getenv('IPV4_NETWORK').'.250'){
    $is_dovecot = true;
  }
  // check authsource
  $stmt = $pdo->prepare("SELECT authsource FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `mailbox`.`active`='1'
        AND `domain`.`active`='1'
        AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row['authsource'] == 'keycloak'){
    $result = keycloak_mbox_login($user, $pass, $is_dovecot);
    if ($result){
      return $result;
    }
  } else {
    $result = mailcow_mbox_login($user, $pass, $app_passwd_data, $is_dovecot);
    if ($result){
      return $result;
    }
  }

  // skip log and only return false
  // netfilter uses dovecot error log for banning
  if ($is_dovecot){
    return false;
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
  $_SESSION['return'][] =  array(
    'type' => 'danger',
    'log' => array(__FUNCTION__, $user, '*'),
    'msg' => 'login_failed'
  );

  sleep($_SESSION['ldelay']);
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

  // check if password is app password
  $is_app_passwd = false;
  if ($app_passwd_data['eas']){
    $is_app_passwd = 'eas';
  } else if ($app_passwd_data['dav']){
    $is_app_passwd = 'dav';
  } else if ($app_passwd_data['smtp']){
    $is_app_passwd = 'smtp';
  } else if ($app_passwd_data['imap']){
    $is_app_passwd = 'imap';
  } else if ($app_passwd_data['sieve']){
    $is_app_passwd = 'sieve';
  } else if ($app_passwd_data['pop3']){
    $is_app_passwd = 'pop3';
  }
  if ($is_app_passwd){
    // fetch app password data
    $app_passwd_query = "SELECT `app_passwd`.`password` as `password`, `app_passwd`.`id` as `app_passwd_id` FROM `app_passwd`
      INNER JOIN `mailbox` ON `mailbox`.`username` = `app_passwd`.`mailbox`
      INNER JOIN `domain` ON `mailbox`.`domain` = `domain`.`domain`
      WHERE `mailbox`.`kind` NOT REGEXP 'location|thing|group'
        AND `mailbox`.`active` = '1'
        AND `domain`.`active` = '1'
        AND `app_passwd`.`active` = '1'
        AND `app_passwd`.`mailbox` = :user";
    // check if app password has protocol access
    // skip if $app_passwd_data['ignore_hasaccess'] is true and the call is not external
    if (!$app_passwd_data['ignore_hasaccess'] || !$is_internal){
      $app_passwd_query = $app_passwd_query . " AND `app_passwd`.`" . $is_app_passwd . "_access` = '1'";
    }
    // fetch password data
    $stmt = $pdo->prepare($app_passwd_query);
    $stmt->execute(array(':user' => $user));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  foreach ($rows as $row) { 
    // verify password
    if (verify_hash($row['password'], $pass) !== false) {
      if (!$is_app_passwd){ 
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
              // skip log
              $_SESSION['return'][] =  array(
                'type' => 'success',
                'log' => array(__FUNCTION__, $user, '*'),
                'msg' => array('logged_in_as', $user)
              );
          }
          return "user";
        }
      } elseif ($is_app_passwd) {
        // password is a app password
        if ($is_internal){
          // skip log
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

function keycloak_mbox_login($user, $pass, $is_internal = false){
  return false;
}
