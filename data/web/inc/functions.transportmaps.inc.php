<?php
function transport_map($_action, $_data = null, $attr = null) {
	global $pdo;
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    return false;
  }
  switch ($_action) {
    case 'add':
      $local_dest = strtolower(trim($_data['local_dest']));
      $protocol = strtolower(trim($_data['protocol']));
      $ip = strtolower(trim($_data['ip']));
      $port = strtolower(trim($_data['port']));
      $active = intval($_data['active']);

      if (empty($local_dest)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Local destination cannot be empty'
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
          'msg' => 'Domain/IP cannot be empty'
        );
        return false;
      }
      try {
        $nexthop = empty($port) ? $protocol . $ip : $protocol . $ip . ':' . $port;
        $stmt = $pdo->prepare("INSERT INTO `transport_maps` (`local_dest`, `nexthop`, `active`) VALUES
          (:local_dest, :nexthop, :active)");
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
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => 'Transport map entry saved'
      );
    break;
    case 'edit':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $is_now = transport_map('details', $id);
        if (!empty($is_now)) {
          $local_dest = (isset($_data['local_dest'])) ? $_data['local_dest'] : $is_now['local_dest'];
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
          $stmt = $pdo->prepare("SELECT `id` FROM `transport_maps`
            WHERE `local_dest` = :local_dest AND `nexthop` = :nexthop");
          $stmt->execute(array(':local_dest' => $local_dest, ':nexthop' => $nexthop));
          $id_now = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        }
        catch(PDOException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'MySQL: '.$e
          );
          return false;
        }
        if (isset($id_now) && $id_now != $id) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'A transport map entry for' . htmlspecialchars($local_dest) . ' exists'
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `transport_maps` SET `local_dest` = :local_dest, `nexthop` = :nexthop, `active` = :active WHERE `id`= :id");
          $stmt->execute(array(
            ':local_dest' => $local_dest,
            ':nexthop' => $nexthop,
						':active' => $active,
            ':id' => $id
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
      $id = intval($_data);
      try {
        $stmt = $pdo->prepare("SELECT `id`,
					`local_dest`,
					`nexthop`,
					`active` AS `active_int`,
					CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
					`created`,
					`modified` FROM `transport_maps`
						WHERE `id` = :id");
        $stmt->execute(array(':id' => $id));
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
      $id = intval($_data);
      try {
        $stmt = $pdo->query("SELECT `id` FROM `transport_maps`");
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
        $relaydata[] = $i['id'];
      }
      $all_items = null;
      return $relaydata;
    break;
    case 'delete':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        if (!is_numeric($id)) {
          return false;
        }
        try {
          $stmt = $pdo->prepare("DELETE FROM `transport_maps` WHERE `id`= :id");
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
        'msg' => 'Deleted transport map id/s ' . implode(', ', $ids)
      );
      return true;
    break;
  }
}
