<?php
function transport_map($_action, $_data = null, $attr = null) {
	global $pdo;
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    return false;
  }
  switch ($_action) {
    case 'add':
      $mailbox_name = strtolower(trim($_data['mailbox_name']));
      $domain = strtolower(trim($_data['domain']));
      $protocol = strtolower(trim($_data['protocol']));
      $ip = strtolower(trim($_data['ip']));
      $port = strtolower(trim($_data['port']));
      $active = intval($_data['active']);

      if (empty($domain)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Local domain part cannot be empty'
        );
        return false;
      } elseif (empty($protocol)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Protocol cannot be empty'
        );
        return false;
      } elseif (empty($ip)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Domain part cannot be empty'
        );
        return false;
      } elseif (empty($mailbox_name)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'User part cannot be empty'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("SELECT `local_dest` FROM `transport_maps` WHERE `local_dest` = :local_dest");
        $stmt->execute(array(':local_dest' => $mailbox_name . '@' . $domain));
        $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      }
      catch (PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      if ($num_results != 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Entry for this transport map already exists'
        );
        return false;
      }
      try {
        $nexthop = empty($port) ? $protocol . $ip : $protocol . $ip . ':' . $port;
        $stmt = $pdo->prepare("INSERT INTO `transport_maps` (`local_dest`, `nexthop`, `active`) VALUES
          (:local_dest, :nexthop, :active)");
        $stmt->execute(array(
          ':local_dest' => $mailbox_name . '@' . $domain,
          ':nexthop' => $nexthop,
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
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => 'Transport map entry saved'
      );
    break;
    case 'edit':
      $transport_maps = (array)$_data['transport_map'];
      foreach ($transport_maps as $transport_map) {
        $is_now = transport_map('details', $transport_map);
        if (!empty($is_now)) {
          $local_dest = $is_now['local_dest'];
          if (isset($_data['protocol']) && isset($_data['ip']) && (isset($_data['port']) && ! empty($_data['port']) )) {
            $nexthop = $_data['protocol'] . $_data['ip'] . ':' . $_data['port'];
          } elseif (isset($_data['protocol']) && isset($_data['ip'])) {
            $nexthop = $_data['protocol'] . $_data['ip'];
          }  else {
            $nexthop = $is_now['nexthop'];
          }
          $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
        }
        else {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        $active = intval($_data['active']);
        try {
          $stmt = $pdo->prepare("SELECT `local_dest` FROM `transport_maps`
            WHERE `local_dest` = :local_dest AND `nexthop` = :nexthop");
          $stmt->execute(array(':local_dest' => $local_dest, ':nexthop' => $nexthop));
          $transport_map_now = $stmt->fetch(PDO::FETCH_ASSOC)['local_dest'];
        }
        catch(PDOException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'MySQL: '.$e
          );
          return false;
        }
        if (isset($transport_map_now) && $transport_map_now != $transport_map) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'A transport map entry for' . htmlspecialchars($local_dest) . ' exists'
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `transport_maps` SET `nexthop` = :nexthop, `active` = :active WHERE `local_dest`= :local_dest");
          $stmt->execute(array(
            ':local_dest' => $local_dest,
            ':nexthop' => $nexthop,
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
        'msg' => 'Transport map entry edited'
      );
    break;
    case 'details':
      $relaydata = array();
      $transport_map = $_data;
      try {
        $stmt = $pdo->prepare("SELECT
					`local_dest`,
					`nexthop`,
					`active` AS `active_int`,
					CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
					`created`,
					`modified` FROM `transport_maps`
						WHERE `local_dest` = :local_dest");
        $stmt->execute(array(':local_dest' => $transport_map));
        $relaydata = $stmt->fetch(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      return $relaydata;
    break;
    case 'get':
      $relaydata = array();
      $all_items = array();
      try {
        $stmt = $pdo->query("SELECT `local_dest` FROM `transport_maps`");
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      foreach ($all_items as $i) {
        $relaydata[] = $i['local_dest'];
      }
      $all_items = null;
      return $relaydata;
    break;
    case 'delete':
      $transport_maps = (array)$_data['transport_maps'];
      foreach ($transport_maps as $transport_map) {
        try {
          $stmt = $pdo->prepare("DELETE FROM `transport_maps` WHERE `local_dest`= :transport_map");
          $stmt->execute(array(':transport_map' => $transport_map));
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
        'msg' => 'Deleted transport map(s) ' . implode(', ', $transport_maps)
      );
      return true;
    break;
  }
}
