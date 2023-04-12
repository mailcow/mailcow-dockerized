<?php
function unset_auth_session(){
  unset($_SESSION['keycloak_token']);
  unset($_SESSION['keycloak_refresh_token']);
  unset($_SESSION['mailcow_cc_username']);
  unset($_SESSION['mailcow_cc_role']);
  unset($_SESSION['oauth2state']);
  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);
}
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
  if ($result){
    return $result;
  }

  // Validate domain admin
  $result = mailcow_domainadmin_login($user, $pass);
  if ($result){
    return $result;
  }

  // Validate mailbox user
  // check authsource
  $stmt = $pdo->prepare("SELECT authsource FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `mailbox`.`active`='1'
        AND `domain`.`active`='1'
        AND `username` = :user");
  $stmt->execute(array(':user' => $user));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row){
    // mbox does not exist, call keycloak login and create mbox if possible
    $identity_provider_settings = identity_provider('get');
    if ($identity_provider_settings['login_flow'] == 'ropc'){
      $result = keycloak_mbox_login_ropc($user, $pass, $identity_provider_settings, $is_internal, true);
    } else {
      $result = keycloak_mbox_login_rest($user, $pass, $identity_provider_settings, $is_internal, true);
    }
    if ($result){
      return $result;
    }
  } else if ($row['authsource'] == 'keycloak'){
    if ($app_passwd_data){
      // first check if password is app_password
      $result = mailcow_mbox_apppass_login($user, $pass, $app_passwd_data, $is_internal);
      if ($result){
        return $result;
      }
    }

    $identity_provider_settings = identity_provider('get');
    if ($identity_provider_settings['login_flow'] == 'ropc'){
      $result = keycloak_mbox_login_ropc($user, $pass, $identity_provider_settings, $is_internal);
    } else {
      $result = keycloak_mbox_login_rest($user, $pass, $identity_provider_settings, $is_internal);
    }
    if ($result){
      return $result;
    }
  } else {
    if ($app_passwd_data){
      // first check if password is app_password
      $result = mailcow_mbox_apppass_login($user, $pass, $app_passwd_data, $is_internal);
      if ($result){
        return $result;
      }
    }

    $result = mailcow_mbox_login($user, $pass, $app_passwd_data, $is_internal);
    if ($result){
      return $result;
    }
  }

  // skip log and only return false if it's an internal request
  if ($is_internal){
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
  // skip if protocol is false and the call is not external
  $has_access_query = '';
  if (!$is_internal || ($is_internal && !empty($protocol))){
    $has_access_query = " AND `app_passwd`.`" . $protocol . "_access` = '1'";
  }
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

// ROPC Flow (deprecated oAuth2.1)
// uses direct user credentials for UI, IMAP and SMTP Auth
function keycloak_mbox_login_ropc($user, $pass, $iam_settings, $is_internal = false, $create = false){
  global $pdo;

  $url = "{$iam_settings['server_url']}/realms/{$iam_settings['realm']}/protocol/openid-connect/token";
  $req = http_build_query(array(
    'grant_type'    => 'password',
    'client_id'     => $iam_settings['client_id'],
    'client_secret' => $iam_settings['client_secret'],
    'username'      => $user,
    'password'      => $pass,
  ));
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_POST, 1);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $req);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $res = json_decode(curl_exec($curl), true);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close ($curl);

  if ($code == 200) {
    // decode jwt
    $user_data = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $res['access_token'])[1]))), true);
    if ($user != $user_data['email']){
      // check if $user is email address, only accept email address as username
      return false;
    }
    if ($create && !empty($iam_settings['mappers'])){
      // try to create mbox on successfull login
      $mbox_template = null;
      // check if matching attribute mapping exists
      foreach ($iam_settings['mappers'] as $index => $mapper){
        if (in_array($mapper, $iam_settings['mappers'])) {
          $mbox_template = $iam_settings['templates'][$index];
          break;
        }
      }
      if (!$mbox_template){
        // no matching template found
        return false;
      }
      
      $stmt = $pdo->prepare("SELECT * FROM `templates` 
      WHERE `template` = :template AND type = 'mailbox'");
      $stmt->execute(array(
        ":template" => $mbox_template
      ));
      $mbox_template_data = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!empty($mbox_template_data)){
        $mbox_template_data = json_decode($mbox_template_data["attributes"], true);
        $mbox_template_data['domain'] = explode('@', $user)[1];
        $mbox_template_data['local_part'] = explode('@', $user)[0];
        $mbox_template_data['authsource'] = 'keycloak';
        $_SESSION['iam_create_login'] = true;
        $create_res = mailbox('add', 'mailbox', $mbox_template_data);
        $_SESSION['iam_create_login'] = false;
        if (!$create_res){
          return false;
        }
      }
    }

    $_SESSION['return'][] =  array(
      'type' => 'success',
      'log' => array(__FUNCTION__, $user, '*'),
      'msg' => array('logged_in_as', $user)
    );
    return 'user';
  } else {
    return false;
  }
}
// Keycloak REST Api Flow - auth user by mailcow_password attribute
// This password will be used for direct UI, IMAP and SMTP Auth
// To use direct user credentials, only Authorization Code Flow is valid
function keycloak_mbox_login_rest($user, $pass, $iam_settings, $is_internal = false, $create = false){
  global $pdo;

  // get access_token for service account of mailcow client
  $url = "{$iam_settings['server_url']}/realms/{$iam_settings['realm']}/protocol/openid-connect/token";
  $req = http_build_query(array(
    'grant_type'    => 'client_credentials',
    'client_id'     => $iam_settings['client_id'],
    'client_secret' => $iam_settings['client_secret']
  ));
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_POST, 1);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $req);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $mcclient_res = json_decode(curl_exec($curl), true);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close ($curl);
  if ($code != 200) {
    return false;
  }

  // get the mailcow_password attribute from keycloak user
  $url = "{$iam_settings['server_url']}/admin/realms/{$iam_settings['realm']}/users";
  $queryParams = array('email' => $user, 'exact' => true);
  $queryString = http_build_query($queryParams);
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url . '?' . $queryString);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $mcclient_res['access_token'],
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

  // try to create mbox on successfull login
  $mbox_template = null;
  // check if matching attribute mapping exists
  foreach ($iam_settings['mappers'] as $index => $mapper){
    if (in_array($mapper, $iam_settings['mappers'])) {
      $mbox_template = $iam_settings['templates'][$index];
      break;
    }
  }
  if (!$mbox_template){
    // no matching template found
    return false;
  }
  $stmt = $pdo->prepare("SELECT * FROM `templates` 
  WHERE `template` = :template AND type = 'mailbox'");
  $stmt->execute(array(
    ":template" => $mbox_template
  ));
  $mbox_template_data = $stmt->fetch(PDO::FETCH_ASSOC);
  if (empty($mbox_template_data)){
    return false;
  }

  $mbox_template_data = json_decode($mbox_template_data["attributes"], true);
  $mbox_template_data['domain'] = explode('@', $user)[1];
  $mbox_template_data['local_part'] = explode('@', $user)[0];
  $mbox_template_data['authsource'] = 'keycloak';
  $_SESSION['iam_create_login'] = true;
  $create_res = mailbox('add', 'mailbox', $mbox_template_data);
  $_SESSION['iam_create_login'] = false;
  if (!$create_res){
    return false;
  }

  $_SESSION['return'][] =  array(
    'type' => 'success',
    'log' => array(__FUNCTION__, $user, '*'),
    'msg' => array('logged_in_as', $user)
  );
  return 'user';
}
// Authorization Code Flow
function keycloak_verify_token(){
  global $keycloak_provider;
  global $pdo;

  try {
    $token = $keycloak_provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
  } catch (Exception $e) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => array('login_failed', $e->getMessage())
    );
    return false;
  }
  
  $_SESSION['keycloak_token'] = $token->getToken();
  $_SESSION['keycloak_refresh_token'] = $token->getRefreshToken();
  // decode jwt data
  $info = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $token->getToken())[1]))), true);
  // check if email address is given
  if (!$info['email']){
    return false;
  }


  // token valid, get mailbox
  $stmt = $pdo->prepare("SELECT * FROM `mailbox`
    INNER JOIN domain on mailbox.domain = domain.domain
    WHERE `kind` NOT REGEXP 'location|thing|group'
      AND `mailbox`.`active`='1'
      AND `domain`.`active`='1'
      AND `username` = :user
      AND `authsource`='keycloak'");
  $stmt->execute(array(':user' => $info['email']));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row){
    // success
    $_SESSION['mailcow_cc_username'] = $info['email'];
    $_SESSION['mailcow_cc_role'] = "user";
    $_SESSION['return'][] =  array(
      'type' => 'success',
      'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
      'msg' => array('logged_in_as', $_SESSION['mailcow_cc_username'])
    );
    return true;
  }
  // get mapped template, if not set return false
  // also return false if no mappers were defined
  $identity_provider_settings = identity_provider('get');
  $user_template = $info['mailcow_template'];
  if (empty($identity_provider_settings['mappers']) || empty($user_template)){
    unset_auth_session();  
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
      'msg' => 'login_failed'
    );
    return false;
  }
  $mbox_template = null;
  // check if matching rolemapping exist
  foreach ($identity_provider_settings['mappers'] as $index => $mapper){
    if ($user_template == $mapper) {
      $mbox_template = $identity_provider_settings['templates'][$index];
      break;
    }
  }
  if (!$mbox_template){
    // no matching template found
    unset_auth_session();  
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
      'msg' => 'login_failed'
    );
    return false;
  }
  $stmt = $pdo->prepare("SELECT * FROM `templates` 
  WHERE `template` = :template AND type = 'mailbox'");
  $stmt->execute(array(
    ":template" => $mbox_template
  ));
  $mbox_template_data = $stmt->fetch(PDO::FETCH_ASSOC);
  if (empty($mbox_template_data)){
    unset_auth_session();  
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
      'msg' => 'login_failed'
    );
    return false;
  }

  // create mailbox
  $mbox_template_data = json_decode($mbox_template_data["attributes"], true);
  $mbox_template_data['domain'] = explode('@', $info['email'])[1];
  $mbox_template_data['local_part'] = explode('@', $info['email'])[0];
  $mbox_template_data['authsource'] = 'keycloak';
  $_SESSION['iam_create_login'] = true;
  $create_res = mailbox('add', 'mailbox', $mbox_template_data);
  $_SESSION['iam_create_login'] = false;
  if (!$create_res){
    unset_auth_session();  
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
      'msg' => 'login_failed'
    );
    return false;
  }

  $_SESSION['mailcow_cc_username'] = $info['email'];
  $_SESSION['mailcow_cc_role'] = "user";
  $_SESSION['return'][] =  array(
    'type' => 'success',
    'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
    'msg' => array('logged_in_as', $_SESSION['mailcow_cc_username'])
  );
  return true;
}
function keycloak_refresh(){
  global $keycloak_provider;

  try {
    $token = $keycloak_provider->getAccessToken('refresh_token', ['refresh_token' => $_SESSION['keycloak_refresh_token']]);
  } catch (Exception $e) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => array('login_failed', $e->getMessage())
    );
    return false;
  }

  $_SESSION['keycloak_token'] = $token->getToken();
  $_SESSION['keycloak_refresh_token'] = $token->getRefreshToken();
  // decode jwt data
  $info = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $_SESSION['keycloak_token'])[1]))), true);
  if (!$info['email']){
    unset_auth_session();  
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
      'msg' => 'refresh_login_failed'
    );
  }

  $_SESSION['mailcow_cc_username'] = $info['email'];
  $_SESSION['mailcow_cc_role'] = "user";
  return true;
}
function keycloak_get_redirect(){
  global $keycloak_provider;

  $authUrl = $keycloak_provider->getAuthorizationUrl();
  $_SESSION['oauth2state'] = $keycloak_provider->getState();

  return $authUrl;
}
function keycloak_get_logout(){
  global $keycloak_provider;

  $logoutUrl = $keycloak_provider->getLogoutUrl();
  $logoutUrl = $logoutUrl . "&post_logout_redirect_uri=https://" . $_SERVER['SERVER_NAME'];

  return $logoutUrl;
}
