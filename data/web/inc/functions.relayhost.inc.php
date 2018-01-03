<?php
function relayhost($_action, $_data = null) {
	global $pdo;
	global $lang;
  switch ($_action) {
    case 'add':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $hostname = trim($_data['hostname']);
      $username = str_replace(':', '\:', trim($_data['username']));
      $password = str_replace(':', '\:', trim($_data['password']));
      if (empty($hostname)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Invalid host specified: '. htmlspecialchars($host)
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
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['relayhost_added'], htmlspecialchars(implode(', ', $hosts)))
      );
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
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
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Relayhost invalid'
          );
          return false;
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
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'MySQL: '.$e
          );
          return false;
        }
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['object_modified'], htmlspecialchars(implode(', ', $hostnames)))
      );
    break;
    case 'delete':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
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
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'MySQL: '.$e
          );
          return false;
        }
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['relayhost_removed'], htmlspecialchars(implode(', ', $hostnames)))
      );
    break;
    case 'get':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      $relayhosts = array();
      try {
        $stmt = $pdo->query("SELECT `id`, `hostname`, `username` FROM `relayhosts`");
        $relayhosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
      }
      return $relayhosts;
    break;
    case 'details':
      if ($_SESSION['mailcow_cc_role'] != "admin" || !isset($_data)) {
        return false;
      }
      $relayhostdata = array();
      try {
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
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
      }
      return $relayhostdata;
    break;
  }
}