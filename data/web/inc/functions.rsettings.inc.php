<?php
function rsettings($_action, $_data = null) {
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
      $content = $_data['content'];
      $desc = $_data['desc'];
      $active = intval($_data['active']);
      if (empty($content)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Content cannot be empty'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `settingsmap` (`content`, `desc`, `active`)
          VALUES (:content, :desc, :active)");
        $stmt->execute(array(
          ':content' => $content,
          ':desc' => $desc,
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
        'msg' => 'Added settings map entry'
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
        $is_now = rsettings('details', $id);
        if (!empty($is_now)) {
          $content = (!empty($_data['content'])) ? $_data['content'] : $is_now['content'];
          $desc = (!empty($_data['desc'])) ? $_data['desc'] : $is_now['desc'];
          $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
        }
        else {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Settings map invalid'
          );
          return false;
        }
        $content = trim($content);
        try {
          $stmt = $pdo->prepare("UPDATE `settingsmap` SET
            `content` = :content,
            `desc` = :desc,
            `active` = :active
              WHERE `id` = :id");
          $stmt->execute(array(
            ':content' => $content,
            ':desc' => $desc,
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
        'msg' => sprintf($lang['success']['object_modified'], htmlspecialchars(implode(', ', $ids)))
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
          $stmt = $pdo->prepare("DELETE FROM `settingsmap` WHERE `id`= :id");
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
        'msg' => 'Removed settings map ID'
      );
    break;
    case 'get':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      $settingsmaps = array();
      try {
        $stmt = $pdo->query("SELECT `id`, `desc`, `active` FROM `settingsmap`");
        $settingsmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
      }
      return $settingsmaps;
    break;
    case 'details':
      if ($_SESSION['mailcow_cc_role'] != "admin" || !isset($_data)) {
        return false;
      }
      $settingsmapdata = array();
      try {
        $stmt = $pdo->prepare("SELECT `id`,
          `desc`,
          `content`,
          `active` AS `active_int`,
          CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
            FROM `settingsmap`
              WHERE `id` = :id");
        $stmt->execute(array(':id' => $_data));
        $settingsmapdata = $stmt->fetch(PDO::FETCH_ASSOC);
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
      }
      return $settingsmapdata;
    break;
  }
}