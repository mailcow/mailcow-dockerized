<?php
function relayhost($_action, $_data = null) {
	global $pdo;
	global $lang;
  $_data_log = $_data;
  switch ($_action) {
    case 'add':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      $hostname = trim($_data['hostname']);
      $username = str_replace(':', '\:', trim($_data['username']));
      $password = str_replace(':', '\:', trim($_data['password']));
      if (empty($hostname)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('invalid_host', htmlspecialchars($host))
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `relayhosts` (`hostname`, `username` ,`password`, `active`)
          VALUES (:hostname, :username, :password, :active)");
        $stmt->execute(array(
          ':hostname' => $hostname,
          ':username' => $username,
          ':password' => str_replace(':', '\:', $password),
          ':active' => '1'
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
        'msg' => array('relayhost_added', htmlspecialchars(implode(', ', $hosts)))
      );
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $is_now = relayhost('details', $id);
        if (!empty($is_now)) {
          $hostname = (!empty($_data['hostname'])) ? trim($_data['hostname']) : $is_now['hostname'];
          $username = (isset($_data['username'])) ? trim($_data['username']) : $is_now['username'];
          $password = (isset($_data['password'])) ? trim($_data['password']) : $is_now['password'];
          $active   = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
        }
        else {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('relayhost_invalid', $id)
          );
          continue;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `relayhosts` SET
            `hostname` = :hostname,
            `username` = :username,
            `password` = :password,
            `active` = :active
              WHERE `id` = :id");
          $stmt->execute(array(
            ':id' => $id,
            ':hostname' => $hostname,
            ':username' => $username,
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
          continue;
        }
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('object_modified', htmlspecialchars(implode(', ', $hostnames)))
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
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        try {
          $stmt = $pdo->prepare("DELETE FROM `relayhosts` WHERE `id`= :id");
          $stmt->execute(array(':id' => $id));
          $stmt = $pdo->prepare("UPDATE `domain` SET `relayhost` = '0' WHERE `relayhost`= :id");
          $stmt->execute(array(':id' => $id));
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
          'msg' => array('relayhost_removed', htmlspecialchars($id))
        );
      }
    break;
    case 'get':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      $relayhosts = array();
      $stmt = $pdo->query("SELECT `id`, `hostname`, `username` FROM `relayhosts`");
      $relayhosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $relayhosts;
    break;
    case 'details':
      if ($_SESSION['mailcow_cc_role'] != "admin" || !isset($_data)) {
        return false;
      }
      $relayhostdata = array();
      $stmt = $pdo->prepare("SELECT `id`,
        `hostname`,
        `username`,
        `password`,
        `active` AS `active_int`,
        CONCAT(LEFT(`password`, 3), '...') AS `password_short`,
        CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
          FROM `relayhosts`
            WHERE `id` = :id");
      $stmt->execute(array(':id' => $_data));
      $relayhostdata = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!empty($relayhostdata)) {
        $stmt = $pdo->prepare("SELECT GROUP_CONCAT(`domain` SEPARATOR ', ') AS `used_by_domains` FROM `domain` WHERE `relayhost` = :id");
        $stmt->execute(array(':id' => $_data));
        $used_by_domains = $stmt->fetch(PDO::FETCH_ASSOC)['used_by_domains'];
        $used_by_domains = (empty($used_by_domains)) ? '' : $used_by_domains;
        $relayhostdata['used_by_domains'] = $used_by_domains;
      }
      return $relayhostdata;
    break;
  }
}
function transport($_action, $_data = null) {
	global $pdo;
	global $lang;
  $_data_log = $_data;
  switch ($_action) {
    case 'add':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      $destination = trim($_data['destination']);
      $nexthop = trim($_data['nexthop']);
      $username = str_replace(':', '\:', trim($_data['username']));
      $password = str_replace(':', '\:', trim($_data['password']));
      if (empty($destination) || (is_valid_domain_name(preg_replace('/^' . preg_quote('.', '/') . '/', '', $destination)) === false && $destination != '*')) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'invalid_destination'
        );
        return false;
      }
      if (empty($nexthop)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('invalid_nexthop')
        );
        return false;
      }
      if (!empty($username)) {
        $transports = transport('get');
        if (!empty($transports)) {
          foreach ($transports as $transport) {
            if (transport('details', $transport['id'])['nexthop'] == $nexthop && !empty(transport('details', $transport['id'])['username'])) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_data_log),
                'msg' => 'invalid_nexthop_authenticated'
              );
              return false;
            }
          }
        }
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `transports` (`nexthop`, `destination`, `username` ,`password`, `active`)
          VALUES (:nexthop, :destination, :username, :password, :active)");
        $stmt->execute(array(
          ':nexthop' => $nexthop,
          ':destination' => $destination,
          ':username' => $username,
          ':password' => str_replace(':', '\:', $password),
          ':active' => '1'
        ));
        $stmt = $pdo->prepare("UPDATE `transports` SET
          `username` = :username,
          `password` = :password
            WHERE `nexthop` = :nexthop");
        $stmt->execute(array(
          ':nexthop' => $nexthop,
          ':username' => $username,
          ':password' => $password
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
        'msg' => array('relayhost_added', htmlspecialchars(implode(', ', $hosts)))
      );
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $is_now = transport('details', $id);
        if (!empty($is_now)) {
          $destination = (!empty($_data['destination'])) ? trim($_data['destination']) : $is_now['destination'];
          $nexthop = (!empty($_data['nexthop'])) ? trim($_data['nexthop']) : $is_now['nexthop'];
          $username = (isset($_data['username'])) ? trim($_data['username']) : $is_now['username'];
          $password = (isset($_data['password'])) ? trim($_data['password']) : $is_now['password'];
          $active   = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
        }
        else {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('relayhost_invalid', $id)
          );
          continue;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `transports` SET
            `destination` = :destination,
            `nexthop` = :nexthop,
            `username` = :username,
            `password` = :password,
            `active` = :active
              WHERE `id` = :id");
          $stmt->execute(array(
            ':id' => $id,
            ':destination' => $destination,
            ':nexthop' => $nexthop,
            ':username' => $username,
            ':password' => $password,
            ':active' => $active
          ));
          $stmt = $pdo->prepare("UPDATE `transports` SET
            `username` = :username,
            `password` = :password
              WHERE `nexthop` = :nexthop");
          $stmt->execute(array(
            ':nexthop' => $nexthop,
            ':username' => $username,
            ':password' => $password
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
          'msg' => array('object_modified', htmlspecialchars(implode(', ', $hostnames)))
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
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        try {
          $stmt = $pdo->prepare("DELETE FROM `transports` WHERE `id`= :id");
          $stmt->execute(array(':id' => $id));
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
          'msg' => array('relayhost_removed', htmlspecialchars($id))
        );
      }
    break;
    case 'get':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      $transports = array();
      $stmt = $pdo->query("SELECT `id`, `destination`, `nexthop`, `username` FROM `transports`");
      $transports = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $transports;
    break;
    case 'details':
      if ($_SESSION['mailcow_cc_role'] != "admin" || !isset($_data)) {
        return false;
      }
      $transportdata = array();
      $stmt = $pdo->prepare("SELECT `id`,
        `destination`,
        `nexthop`,
        `username`,
        `password`,
        `active` AS `active_int`,
        CONCAT(LEFT(`password`, 3), '...') AS `password_short`,
        CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
          FROM `transports`
            WHERE `id` = :id");
      $stmt->execute(array(':id' => $_data));
      $transportdata = $stmt->fetch(PDO::FETCH_ASSOC);
      return $transportdata;
    break;
  }
}