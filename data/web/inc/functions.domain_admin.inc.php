<?php

function domain_admin($_action, $_data = null) {
  global $pdo;
  global $lang;
  $_data_log = $_data;
  !isset($_data_log['password']) ?: $_data_log['password'] = '*';
  !isset($_data_log['password2']) ?: $_data_log['password2'] = '*';
  switch ($_action) {
    case 'add':
      $username		= strtolower(trim($_data['username']));
      $password		= $_data['password'];
      $password2  = $_data['password2'];
      $domains    = (array)$_data['domains'];
      $active     = intval($_data['active']);
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      if (empty($domains)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'domain_invalid'
        );
        return false;
      }
      if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username)) || empty ($username)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'username_invalid'
        );
        return false;
      }

      $stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
        WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      
      $stmt = $pdo->prepare("SELECT `username` FROM `admin`
        WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      
      $stmt = $pdo->prepare("SELECT `username` FROM `domain_admins`
        WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $num_results[] = count($stmt->fetchAll(PDO::FETCH_ASSOC));

      foreach ($num_results as $num_results_each) {
        if ($num_results_each != 0) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('object_exists', htmlspecialchars($username))
          );
          return false;
        }
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
        foreach ($domains as $domain) {
          if (!is_valid_domain_name($domain)) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => 'domain_invalid'
            );
            return false;
          }
          $stmt = $pdo->prepare("INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
              VALUES (:username, :domain, :created, :active)");
          $stmt->execute(array(
            ':username' => $username,
            ':domain' => $domain,
            ':created' => date('Y-m-d H:i:s'),
            ':active' => $active
          ));
        }
        $stmt = $pdo->prepare("INSERT INTO `admin` (`username`, `password`, `superadmin`, `active`)
          VALUES (:username, :password_hashed, '0', :active)");
        $stmt->execute(array(
          ':username' => $username,
          ':password_hashed' => $password_hashed,
          ':active' => $active
        ));
      }
      else {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'password_empty'
        );
        return false;
      }
      $stmt = $pdo->prepare("INSERT INTO `da_acl` (`username`) VALUES (:username)");
      $stmt->execute(array(
        ':username' => $username
      ));
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data_log),
        'msg' => array('domain_admin_added', htmlspecialchars($username))
      );
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      // Administrator
      if ($_SESSION['mailcow_cc_role'] == "admin") {
        if (!is_array($_data['username'])) {
          $usernames = array();
          $usernames[] = $_data['username'];
        }
        else {
          $usernames = $_data['username'];
        }
        foreach ($usernames as $username) {
          $is_now = domain_admin('details', $username);
          $domains = (isset($_data['domains'])) ? (array)$_data['domains'] : null;
          if (!empty($is_now)) {
            $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
            $domains = (!empty($domains)) ? $domains : $is_now['selected_domains'];
            $username_new = (!empty($_data['username_new'])) ? $_data['username_new'] : $is_now['username'];
          }
          else {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => 'access_denied'
            );
            continue;
          }
          $password     = $_data['password'];
          $password2    = $_data['password2'];
          if (!empty($domains)) {
            foreach ($domains as $domain) {
              if (!is_valid_domain_name($domain)) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_data_log),
                  'msg' => array('domain_invalid', htmlspecialchars($domain))
                );
                continue 2;
              }
            }
          }
          if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username_new))) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => array('username_invalid', $username_new)
            );
            continue;
          }
          if ($username_new != $username) {
            if (!empty(domain_admin('details', $username_new)['username'])) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_data_log),
                'msg' => array('username_invalid', $username_new)
              );
              continue;
            }
          }
          $stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `username` = :username");
          $stmt->execute(array(
            ':username' => $username,
          ));
          if (!empty($domains)) {
            foreach ($domains as $domain) {
              $stmt = $pdo->prepare("INSERT INTO `domain_admins` (`username`, `domain`, `created`, `active`)
                VALUES (:username_new, :domain, :created, :active)");
              $stmt->execute(array(
                ':username_new' => $username_new,
                ':domain' => $domain,
                ':created' => date('Y-m-d H:i:s'),
                ':active' => $active
              ));
            }
          }
          if (!empty($password) && !empty($password2)) {
            if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_data_log),
                'msg' => 'password_complexity'
              );
              continue;
            }
            if ($password != $password2) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_data_log),
                'msg' => 'password_mismatch'
              );
              continue;
            }
            $password_hashed = hash_password($password);
            $stmt = $pdo->prepare("UPDATE `admin` SET `username` = :username_new, `active` = :active, `password` = :password_hashed WHERE `username` = :username");
            $stmt->execute(array(
              ':password_hashed' => $password_hashed,
              ':username_new' => $username_new,
              ':username' => $username,
              ':active' => $active
            ));
            if (isset($_data['disable_tfa'])) {
              $stmt = $pdo->prepare("UPDATE `tfa` SET `active` = '0' WHERE `username` = :username");
              $stmt->execute(array(':username' => $username));
            }
            else {
              $stmt = $pdo->prepare("UPDATE `tfa` SET `username` = :username_new WHERE `username` = :username");
              $stmt->execute(array(':username_new' => $username_new, ':username' => $username));
            }
          }
          else {
            $stmt = $pdo->prepare("UPDATE `admin` SET `username` = :username_new, `active` = :active WHERE `username` = :username");
            $stmt->execute(array(
              ':username_new' => $username_new,
              ':username' => $username,
              ':active' => $active
            ));
            if (isset($_data['disable_tfa'])) {
              $stmt = $pdo->prepare("UPDATE `tfa` SET `active` = '0' WHERE `username` = :username");
              $stmt->execute(array(':username' => $username));
            }
            else {
              $stmt = $pdo->prepare("UPDATE `tfa` SET `username` = :username_new WHERE `username` = :username");
              $stmt->execute(array(':username_new' => $username_new, ':username' => $username));
            }
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('domain_admin_modified', htmlspecialchars($username))
          );
        }
        return true;
      }
      // Domain administrator
      // Can only edit itself
      elseif ($_SESSION['mailcow_cc_role'] == "domainadmin") {
        $username = $_SESSION['mailcow_cc_username'];
        $password_old		= $_data['user_old_pass'];
        $password_new	= $_data['user_new_pass'];
        $password_new2	= $_data['user_new_pass2'];

        $stmt = $pdo->prepare("SELECT `password` FROM `admin`
            WHERE `username` = :user");
        $stmt->execute(array(':user' => $username));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!verify_hash($row['password'], $password_old)) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => 'access_denied'
          );
          return false;
        }

        if (!empty($password_new2) && !empty($password_new)) {
          if ($password_new2 != $password_new) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => 'password_mismatch'
            );
            return false;
          }
          if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password_new)) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => 'password_complexity'
            );
            return false;
          }
          $password_hashed = hash_password($password_new);
          $stmt = $pdo->prepare("UPDATE `admin` SET `password` = :password_hashed WHERE `username` = :username");
          $stmt->execute(array(
            ':password_hashed' => $password_hashed,
            ':username' => $username
          ));
        }
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('domain_admin_modified', htmlspecialchars($username))
        );
      }
    break;
    case 'delete':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      $usernames = (array)$_data['username'];
      foreach ($usernames as $username) {
        if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('username_invalid', $username)
          );
          continue;
        }
        $stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `username` = :username");
        $stmt->execute(array(
          ':username' => $username,
        ));
        $stmt = $pdo->prepare("DELETE FROM `admin` WHERE `username` = :username");
        $stmt->execute(array(
          ':username' => $username,
        ));
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('domain_admin_removed', htmlspecialchars($username))
        );
      }
    break;
    case 'get':
      $domainadmins = array();
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }

      $stmt = $pdo->query("SELECT DISTINCT
        `username`
          FROM `domain_admins` 
            WHERE `username` IN (
              SELECT `username` FROM `admin`
                WHERE `superadmin`!='1'
            )");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while ($row = array_shift($rows)) {
        $domainadmins[] = $row['username'];
      }

      return $domainadmins;
    break;
    case 'details':
      $domainadmindata = array();

      if ($_SESSION['mailcow_cc_role'] == "domainadmin" && $_data != $_SESSION['mailcow_cc_username']) {
        return false;
      }
      elseif ($_SESSION['mailcow_cc_role'] != "admin" || !isset($_data)) {
        return false;
      }

      if (!ctype_alnum(str_replace(array('_', '.', '-'), '', $_data))) {
        return false;
      }

      $stmt = $pdo->prepare("SELECT
        `tfa`.`active` AS `tfa_active_int`,
        CASE `tfa`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `tfa_active`,
        `domain_admins`.`username`,
        `domain_admins`.`created`,
        `domain_admins`.`active` AS `active_int`,
        CASE `domain_admins`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
          FROM `domain_admins`
          LEFT OUTER JOIN `tfa` ON `tfa`.`username`=`domain_admins`.`username`
            WHERE `domain_admins`.`username`= :domain_admin");
      $stmt->execute(array(
        ':domain_admin' => $_data
      ));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (empty($row)) { 
        return false;
      }
      $domainadmindata['username'] = $row['username'];
      $domainadmindata['tfa_active'] = $row['tfa_active'];
      $domainadmindata['active'] = $row['active'];
      $domainadmindata['tfa_active_int'] = $row['tfa_active_int'];
      $domainadmindata['active_int'] = $row['active_int'];
      $domainadmindata['modified'] = $row['created'];
      // GET SELECTED
      $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
        WHERE `domain` IN (
          SELECT `domain` FROM `domain_admins`
            WHERE `username`= :domain_admin)");
      $stmt->execute(array(':domain_admin' => $_data));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $domainadmindata['selected_domains'][] = $row['domain'];
      }
      // GET UNSELECTED
      $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
        WHERE `domain` NOT IN (
          SELECT `domain` FROM `domain_admins`
            WHERE `username`= :domain_admin)");
      $stmt->execute(array(':domain_admin' => $_data));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $domainadmindata['unselected_domains'][] = $row['domain'];
      }
      if (!isset($domainadmindata['unselected_domains'])) {
        $domainadmindata['unselected_domains'] = "";
      }

      return $domainadmindata;
    break;
  }
}
