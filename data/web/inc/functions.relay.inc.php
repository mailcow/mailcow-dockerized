<?php
function relay($_action, $_data = null, $attr = null) {
	global $pdo;
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    return false;
  }
  switch ($_action) {
    case 'add':
      echo 'Add';
      $domain = strtolower(trim($_data['domain']));
      $nexthop = strtolower(trim($_data['nexthop']));
      if (empty($domain)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Domain cannot be empty'
        );
        return false;
      } elseif (empty($nexthop)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Nexthop cannot be empty'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `transport_maps` (`domain`, `nexthop`) VALUES
          (:domain, :nexthop");
        $stmt->execute(array(
          ':domain' => $domain,
          ':nexthop' => $nexthop
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
        'msg' => 'Relay domain entry saved'
      );
    break;
    // case 'edit':
    //   if (!isset($_SESSION['acl']['bcc_maps']) || $_SESSION['acl']['bcc_maps'] != "1" ) {
    //     $_SESSION['return'] = array(
    //       'type' => 'danger',
    //       'msg' => sprintf($lang['danger']['access_denied'])
    //     );
    //     return false;
    //   }
    //   $ids = (array)$_data['id'];
    //   foreach ($ids as $id) {
    //     $is_now = bcc('details', $id);
    //     if (!empty($is_now)) {
    //       $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
    //       $bcc_dest = (!empty($_data['bcc_dest'])) ? $_data['bcc_dest'] : $is_now['bcc_dest'];
    //       $local_dest = $is_now['local_dest'];
    //       $type = (!empty($_data['type'])) ? $_data['type'] : $is_now['type'];
    //     }
    //     else {
    //       $_SESSION['return'] = array(
    //         'type' => 'danger',
    //         'msg' => sprintf($lang['danger']['access_denied'])
    //       );
    //       return false;
    //     }
    //     $bcc_dest = array_map('trim', preg_split( "/( |,|;|\n)/", $bcc_dest));
    //     $active = intval($_data['active']);
    //     foreach ($bcc_dest as &$bcc_dest_e) {
    //       if (!filter_var($bcc_dest_e, FILTER_VALIDATE_EMAIL)) {
    //         $bcc_dest_e = null;;
    //       }
    //       $bcc_dest_e = strtolower($bcc_dest_e);
    //     }
    //     $bcc_dest = array_filter($bcc_dest);
    //     $bcc_dest = implode(",", $bcc_dest);
    //     if (empty($bcc_dest)) {
    //       $_SESSION['return'] = array(
    //         'type' => 'danger',
    //         'msg' => 'BCC map destination cannot be empty'
    //       );
    //       return false;
    //     }
    //     try {
    //       $stmt = $pdo->prepare("SELECT `id` FROM `bcc_maps`
    //         WHERE `local_dest` = :local_dest AND `type` = :type");
    //       $stmt->execute(array(':local_dest' => $local_dest, ':type' => $type));
    //       $id_now = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    //     }
    //     catch(PDOException $e) {
    //       $_SESSION['return'] = array(
    //         'type' => 'danger',
    //         'msg' => 'MySQL: '.$e
    //       );
    //       return false;
    //     }
    //     if (isset($id_now) && $id_now != $id) {
    //       $_SESSION['return'] = array(
    //         'type' => 'danger',
    //         'msg' => 'A BCC map entry ' . htmlspecialchars($local_dest) . ' exists for this type'
    //       );
    //       return false;
    //     }
    //     try {
    //       $stmt = $pdo->prepare("UPDATE `bcc_maps` SET `bcc_dest` = :bcc_dest, `active` = :active, `type` = :type WHERE `id`= :id");
    //       $stmt->execute(array(
    //         ':bcc_dest' => $bcc_dest,
    //         ':active' => $active,
    //         ':type' => $type,
    //         ':id' => $id
    //       ));
    //     }
    //     catch (PDOException $e) {
    //       $_SESSION['return'] = array(
    //         'type' => 'danger',
    //         'msg' => 'MySQL: '.$e
    //       );
    //       return false;
    //     }
    //   }
    //   $_SESSION['return'] = array(
    //     'type' => 'success',
    //     'msg' => 'BCC map entry edited'
    //   );
    // break;
    case 'details':
      $relaydata = array();
      $id = intval($_data);
      try {
        $stmt = $pdo->prepare("SELECT `id`, `domain`, `nexthop` FROM `transport_maps` WHERE `id` = :id");
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
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $relaydata['domain'])) {
        $relaydata = null;
        return false;
      }
      return $relaydata;
    break;
    case 'get':
      $relaydata = array();
      $all_items = array();
      $id = intval($_data);
      try {
        $stmt = $pdo->query("SELECT `id`, `domain`, `nexthop` FROM `transport_maps`");
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
        if (hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $i['domain'])) {
          $relaydata[] = $i['id'];
        }
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
          $stmt = $pdo->prepare("SELECT `domain` FROM `transport_maps` WHERE id = :id");
          $stmt->execute(array(':id' => $id));
          $domain = $stmt->fetch(PDO::FETCH_ASSOC)['domain'];
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
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