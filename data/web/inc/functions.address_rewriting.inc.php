<?php
function bcc($_action, $_data = null, $attr = null) {
	global $pdo;
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    return false;
  }
  switch ($_action) {
    case 'add':
      if (!isset($_SESSION['acl']['bcc_maps']) || $_SESSION['acl']['bcc_maps'] != "1" ) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $local_dest = strtolower(trim($_data['local_dest']));
      $bcc_dest = $_data['bcc_dest'];
      $active = intval($_data['active']);
      $type = $_data['type'];
      if ($type != 'sender' && $type != 'rcpt') {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Invalid BCC map type'
        );
        return false;
      }
      if (empty($bcc_dest)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'BCC destination cannot be empty'
        );
        return false;
      }
      if (is_valid_domain_name($local_dest)) {
        if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $local_dest)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        $domain = idn_to_ascii($local_dest);
        $local_dest_sane = '@' . idn_to_ascii($local_dest);
      }
      elseif (filter_var($local_dest, FILTER_VALIDATE_EMAIL)) {
        if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $local_dest)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        $domain = mailbox('get', 'mailbox_details', $local_dest)['domain'];
        if (empty($domain)) {
          return false;
        }
        $local_dest_sane = $local_dest;
      }
      else {
        return false;
      }
      if (!filter_var($bcc_dest, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'BCC map must be a valid email address'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("SELECT `id` FROM `bcc_maps`
          WHERE `local_dest` = :local_dest AND `type` = :type");
        $stmt->execute(array(':local_dest' => $local_dest_sane, ':type' => $type));
        $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      if ($num_results != 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'A BCC map entry "' . htmlspecialchars($local_dest_sane) . '" exists for type "' . $type . '"'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `bcc_maps` (`local_dest`, `bcc_dest`, `domain`, `active`, `type`) VALUES
          (:local_dest, :bcc_dest, :domain, :active, :type)");
        $stmt->execute(array(
          ':local_dest' => $local_dest_sane,
          ':bcc_dest' => $bcc_dest,
          ':domain' => $domain,
          ':active' => $active,
          ':type' => $type
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
        'msg' => 'BCC map entry saved'
      );
    break;
    case 'edit':
      if (!isset($_SESSION['acl']['bcc_maps']) || $_SESSION['acl']['bcc_maps'] != "1" ) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $is_now = bcc('details', $id);
        if (!empty($is_now)) {
          $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
          $bcc_dest = (!empty($_data['bcc_dest'])) ? $_data['bcc_dest'] : $is_now['bcc_dest'];
          $local_dest = $is_now['local_dest'];
          $type = (!empty($_data['type'])) ? $_data['type'] : $is_now['type'];
        }
        else {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        $active = intval($_data['active']);
        if (!filter_var($bcc_dest, FILTER_VALIDATE_EMAIL)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'BCC map must be a valid email address'
          );
          return false;
        }
        if (empty($bcc_dest)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'BCC map destination cannot be empty'
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare("SELECT `id` FROM `bcc_maps`
            WHERE `local_dest` = :local_dest AND `type` = :type");
          $stmt->execute(array(':local_dest' => $local_dest, ':type' => $type));
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
            'msg' => 'A BCC map entry ' . htmlspecialchars($local_dest) . ' exists for this type'
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `bcc_maps` SET `bcc_dest` = :bcc_dest, `active` = :active, `type` = :type WHERE `id`= :id");
          $stmt->execute(array(
            ':bcc_dest' => $bcc_dest,
            ':active' => $active,
            ':type' => $type,
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
        'msg' => 'BCC map entry edited'
      );
    break;
    case 'details':
      $bccdata = array();
      $id = intval($_data);
      try {
        $stmt = $pdo->prepare("SELECT `id`,
          `local_dest`,
          `bcc_dest`,
          `active` AS `active_int`,
          CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
          `type`,
          `created`,
          `domain`,
          `modified` FROM `bcc_maps`
            WHERE `id` = :id");
        $stmt->execute(array(':id' => $id));
        $bccdata = $stmt->fetch(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $bccdata['domain'])) {
        $bccdata = null;
        return false;
      }
      return $bccdata;
    break;
    case 'get':
      $bccdata = array();
      $all_items = array();
      $id = intval($_data);
      try {
        $stmt = $pdo->query("SELECT `id`, `domain` FROM `bcc_maps`");
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
          $bccdata[] = $i['id'];
        }
      }
      $all_items = null;
      return $bccdata;
    break;
    case 'delete':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        if (!is_numeric($id)) {
          return false;
        }
        try {
          $stmt = $pdo->prepare("SELECT `domain` FROM `bcc_maps` WHERE id = :id");
          $stmt->execute(array(':id' => $id));
          $domain = $stmt->fetch(PDO::FETCH_ASSOC)['domain'];
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          $stmt = $pdo->prepare("DELETE FROM `bcc_maps` WHERE `id`= :id");
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
        'msg' => 'Deleted BCC map id/s ' . implode(', ', $ids)
      );
      return true;
    break;
  }
}

function recipient_map($_action, $_data = null, $attr = null) {
	global $pdo;
	global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    return false;
  }
  switch ($_action) {
    case 'add':
      $old_dest = strtolower(trim($_data['recipient_map_old']));
      $new_dest = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['recipient_map_new']));
      $active = intval($_data['active']);
      if (empty($new_dest)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Recipient map destination cannot be empty'
        );
        return false;
      }
      if (is_valid_domain_name($old_dest)) {
        $old_dest_sane = '@' . idn_to_ascii($old_dest);
      }
      elseif (filter_var($old_dest, FILTER_VALIDATE_EMAIL)) {
        $old_dest_sane = $old_dest;
      }
      else {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Invalid original recipient specified: ' . $old_dest
        );
        return false;
      }
      foreach ($new_dest as &$new_dest_e) {
        if (!filter_var($new_dest_e, FILTER_VALIDATE_EMAIL)) {
          $new_dest_e = null;;
        }
        $new_dest_e = strtolower($new_dest_e);
      }
      $new_dest = array_filter($new_dest);
      $new_dest = implode(",", $new_dest);
      if (empty($new_dest)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Recipient map destination cannot be empty'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("SELECT `id` FROM `recipient_maps`
          WHERE `old_dest` = :old_dest");
        $stmt->execute(array(':old_dest' => $old_dest_sane));
        $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      if ($num_results != 0) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'A Recipient map entry "' . htmlspecialchars($old_dest_sane) . '" exists'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `recipient_maps` (`old_dest`, `new_dest`, `active`) VALUES
          (:old_dest, :new_dest, :active)");
        $stmt->execute(array(
          ':old_dest' => $old_dest_sane,
          ':new_dest' => $new_dest,
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
        'msg' => 'Recipient map entry saved'
      );
    break;
    case 'edit':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $is_now = recipient_map('details', $id);
        if (!empty($is_now)) {
          $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
          $new_dest = (!empty($_data['recipient_map_new'])) ? $_data['recipient_map_new'] : $is_now['recipient_map_new'];
          $old_dest = $is_now['old_dest'];
        }
        else {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        $new_dest = array_map('trim', preg_split( "/( |,|;|\n)/", $new_dest));
        $active = intval($_data['active']);
        foreach ($new_dest as &$new_dest_e) {
          if (!filter_var($new_dest_e, FILTER_VALIDATE_EMAIL)) {
            $new_dest_e = null;;
          }
          $new_dest_e = strtolower($new_dest_e);
        }
        $new_dest = array_filter($new_dest);
        $new_dest = implode(",", $new_dest);
        if (empty($new_dest)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Recipient map destination cannot be empty'
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare("SELECT `id` FROM `recipient_maps`
            WHERE `old_dest` = :old_dest");
          $stmt->execute(array(':old_dest' => $old_dest));
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
            'msg' => 'A Recipient map entry ' . htmlspecialchars($old_dest) . ' exists'
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `recipient_maps` SET `new_dest` = :new_dest, `active` = :active WHERE `id`= :id");
          $stmt->execute(array(
            ':new_dest' => $new_dest,
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
        'msg' => 'Recipient map entry edited'
      );
    break;
    case 'details':
      $mapdata = array();
      $id = intval($_data);
      try {
        $stmt = $pdo->prepare("SELECT `id`,
          `old_dest` AS `recipient_map_old`,
          `new_dest` AS `recipient_map_new`,
          `active` AS `active_int`,
          CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
          `created`,
          `modified` FROM `recipient_maps`
            WHERE `id` = :id");
        $stmt->execute(array(':id' => $id));
        $mapdata = $stmt->fetch(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
        return false;
      }
      return $mapdata;
    break;
    case 'get':
      $mapdata = array();
      $all_items = array();
      $id = intval($_data);
      try {
        $stmt = $pdo->query("SELECT `id` FROM `recipient_maps`");
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
        $mapdata[] = $i['id'];
      }
      $all_items = null;
      return $mapdata;
    break;
    case 'delete':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        if (!is_numeric($id)) {
          return false;
        }
        try {
          $stmt = $pdo->prepare("DELETE FROM `recipient_maps` WHERE `id`= :id");
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
        'msg' => 'Deleted Recipient map id/s ' . implode(', ', $ids)
      );
      return true;
    break;
  }
}
