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
      $bcc_dest = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['bcc_dest']));
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
      foreach ($bcc_dest as &$bcc_dest_e) {
        if (!filter_var($bcc_dest_e, FILTER_VALIDATE_EMAIL)) {
          $bcc_dest_e = null;;
        }
        $bcc_dest_e = strtolower($bcc_dest_e);
      }
      $bcc_dest = array_filter($bcc_dest);
      $bcc_dest = implode(",", $bcc_dest);
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
        $bcc_dest = array_map('trim', preg_split( "/( |,|;|\n)/", $bcc_dest));
        $active = intval($_data['active']);
        foreach ($bcc_dest as &$bcc_dest_e) {
          if (!filter_var($bcc_dest_e, FILTER_VALIDATE_EMAIL)) {
            $bcc_dest_e = null;;
          }
          $bcc_dest_e = strtolower($bcc_dest_e);
        }
        $bcc_dest = array_filter($bcc_dest);
        $bcc_dest = implode(",", $bcc_dest);
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