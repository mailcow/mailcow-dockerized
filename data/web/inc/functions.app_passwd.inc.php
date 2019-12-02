<?php
function app_passwd($_action, $_data = null) {
	global $pdo;
	global $lang;
  $_data_log = $_data;
  if (isset($_data['username']) && filter_var($_data['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data['username'])) {
      $_SESSION['return'][] = array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $_action, $_data_log),
        'msg' => 'access_denied'
      );
      return false;
    }
    else {
      $username = $_data['username'];
    }
  }
  else {
    $username = $_SESSION['mailcow_cc_username'];
  }
  switch ($_action) {
    case 'add':
      $name = trim($_data['name']);
      $password     = $_data['password'];
      $password2    = $_data['password2'];
      $active = intval($_data['active']);
      $domain = mailbox('get', 'mailbox_details', $username)['domain'];
      if (empty($domain)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      if (!empty($password) && !empty($password2)) {
        if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => 'password_complexity'
          );
          return false;
        }
        if ($password != $password2) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => 'password_mismatch'
          );
          return false;
        }
        $password_hashed = hash_password($password);
      }
      if (empty($name)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'app_name_empty'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `app_passwd` (`name`, `mailbox`, `domain`, `password`, `active`)
          VALUES (:name, :mailbox, :domain, :password, :active)");
        $stmt->execute(array(
          ':name' => $name,
          ':mailbox' => $mailbox,
          ':domain' => $domain,
          ':password' => $password,
          ':active' => $active
        ));
      }
      catch (PDOException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('mysql_error', $e)
        );
        return false;
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data_log),
        'msg' => 'app_passwd_added'
      );
    break;
    case 'edit':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $is_now = app_passwd('details', $id);
        if (!empty($is_now)) {
          $name = (!empty($_data['name'])) ? $_data['name'] : $is_now['name'];
          $password = (!empty($_data['password'])) ? $_data['password'] : null;
          $password2 = (!empty($_data['password2'])) ? $_data['password2'] : null;
          $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
        }
        else {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('settings_map_invalid', $id)
          );
          continue;
        }
        $name = trim($name);
        if (!empty($password) && !empty($password2)) {
          if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'password_complexity'
            );
            continue;
          }
          if ($password != $password2) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
              'msg' => 'password_mismatch'
            );
            continue;
          }
          $password_hashed = hash_password($password);
          $stmt = $pdo->prepare("UPDATE `app_passwd` SET
              `password` = :password_hashed
                WHERE `mailbox` = :username AND `id` = :id");
          $stmt->execute(array(
            ':password_hashed' => $password_hashed,
            ':username' => $username,
            ':id' => $id
          ));
        }
        try {
          $stmt = $pdo->prepare("UPDATE `app_passwd` SET
            `name` = :name,
            `mailbox` = :username,
            `active` = :active
              WHERE `id` = :id");
          $stmt->execute(array(
            ':name' => $name,
            ':username' => $username,
            ':active' => $active,
            ':id' => $id
          ));
        }
        catch (PDOException $e) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('mysql_error', $e)
          );
          continue;
        }
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('object_modified', htmlspecialchars($ids))
        );
      }
    break;
    case 'delete':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        try {
          $stmt = $pdo->prepare("DELETE FROM `app_passwd` WHERE `id`= :id AND `mailbox`= :username");
          $stmt->execute(array(':id' => $id, ':username' => $username));
        }
        catch (PDOException $e) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('mysql_error', $e)
          );
          return false;
        }
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('app_passwd_removed', htmlspecialchars($id))
        );
      }
    break;
    case 'get':
      $app_passwds = array();
      $stmt = $pdo->prepare("SELECT `id`, `name` FROM `app_passwd` WHERE `mailbox` = :username");
      $stmt->execute(array(':username' => $username));
      $app_passwds = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $app_passwds;
    break;
    case 'details':
      $app_passwd_data = array();
      $stmt = $pdo->prepare("SELECT `id`,
        `name`,
        `mailbox`,
        `domain`,
        `created`,
        `modified`,
        `active` AS `active_int`,
        CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
          FROM `app_passwd`
            WHERE `id` = :id
              AND `mailbox` = :username");
      $stmt->execute(array(':id' => $_data, ':username' => $username));
      $app_passwd_data = $stmt->fetch(PDO::FETCH_ASSOC);
      return $app_passwd_data;
    break;
  }
}