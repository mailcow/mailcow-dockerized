<?php
function quarantine($_action, $_data = null) {
	global $pdo;
	global $redis;
	global $lang;
  switch ($_action) {
    case 'delete':
      if (!is_array($_data['id'])) {
        $ids = array();
        $ids[] = $_data['id'];
      }
      else {
        $ids = $_data['id'];
      }
      if (!isset($_SESSION['acl']['quarantine']) || $_SESSION['acl']['quarantine'] != "1" ) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      foreach ($ids as $id) {
        if (!is_numeric($id)) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        try {
          $stmt = $pdo->prepare('SELECT `rcpt` FROM `quarantine` WHERE `id` = :id');
          $stmt->execute(array(':id' => $id));
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if (hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['rcpt'])) {
            try {
              $stmt = $pdo->prepare("DELETE FROM `quarantine` WHERE `id` = :id");
              $stmt->execute(array(
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
          else {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
        }
        catch(PDOException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'MySQL: '.$e
          );
        }
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['items_deleted'], implode(', ', $ids))
      );
    break;
    case 'edit':
      if (!isset($_SESSION['acl']['quarantine']) || $_SESSION['acl']['quarantine'] != "1" ) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      // Edit settings
      if ($_data['action'] == 'settings') {
        if ($_SESSION['mailcow_cc_role'] != "admin") {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        $retention_size = $_data['retention_size'];
        $max_size = $_data['max_size'];
        $exclude_domains = (array)$_data['exclude_domains'];
        try {
          $redis->Set('Q_RETENTION_SIZE', intval($retention_size));
          $redis->Set('Q_MAX_SIZE', intval($max_size));
          $redis->Set('Q_EXCLUDE_DOMAINS', json_encode($exclude_domains));
        }
        catch (RedisException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Redis: '.$e
          );
          return false;
        }
        $_SESSION['return'] = array(
          'type' => 'success',
          'msg' => 'Saved settings'
        );
      }
      // Release item
      elseif ($_data['action'] == 'release') {
        if (!is_array($_data['id'])) {
          $ids = array();
          $ids[] = $_data['id'];
        }
        else {
          $ids = $_data['id'];
        }
        foreach ($ids as $id) {
          if (!is_numeric($id)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare('SELECT `msg`, `qid`, `sender`, `rcpt` FROM `quarantine` WHERE `id` = :id');
            $stmt->execute(array(':id' => $id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['rcpt'])) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
          }
          $sender = (isset($row['sender'])) ? $row['sender'] : 'sender-unknown@rspamd';
          try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->SMTPOptions = array(
              'ssl' => array(
                  'verify_peer' => false,
                  'verify_peer_name' => false,
                  'allow_self_signed' => true
              )
            );
            if (!empty(gethostbynamel('postfix-mailcow'))) {
              $postfix = 'postfix-mailcow';
            }
            if (!empty(gethostbynamel('postfix'))) {
              $postfix = 'postfix';
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'warning',
                'msg' => sprintf($lang['danger']['release_send_failed'], 'Cannot determine Postfix host')
              );
              return false;
            }
            $mail->Host = $postfix;
            $mail->Port = 590;
            $mail->setFrom($sender);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = sprintf($lang['quarantine']['release_subject'], $row['qid']);
            $mail->addAddress($row['rcpt']);
            $mail->IsHTML(false);
            $msg_tmpf = tempnam("/tmp", $row['qid']);
            file_put_contents($msg_tmpf, $row['msg']);
            $mail->addAttachment($msg_tmpf, $row['qid'] . '.eml');
            $mail->Body = sprintf($lang['quarantine']['release_body']);
            $mail->send();
            unlink($msg_tmpf);
          }
          catch (phpmailerException $e) {
            unlink($msg_tmpf);
            $_SESSION['return'] = array(
              'type' => 'warning',
              'msg' => sprintf($lang['danger']['release_send_failed'], $e->errorMessage())
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("DELETE FROM `quarantine` WHERE `id` = :id");
            $stmt->execute(array(
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
          'msg' => $lang['success']['items_released']
        );
      }
      return true;
    break;
    case 'get':
      try {
        if ($_SESSION['mailcow_cc_role'] == "user") {
          $stmt = $pdo->prepare('SELECT `id`, `qid`, `rcpt`, `sender`, UNIX_TIMESTAMP(`created`) AS `created` FROM `quarantine` WHERE `rcpt` = :mbox');
          $stmt->execute(array(':mbox' => $_SESSION['mailcow_cc_username']));
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          while($row = array_shift($rows)) {
            $q_meta[] = $row;
          }
        }
        elseif ($_SESSION['mailcow_cc_role'] == "admin") {
          $stmt = $pdo->query('SELECT `id`, `qid`, `rcpt`, `sender`, UNIX_TIMESTAMP(`created`) AS `created` FROM `quarantine`');
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          while($row = array_shift($rows)) {
            $q_meta[] = $row;
          }
        }
        else {
          $domains = array_merge(mailbox('get', 'domains'), mailbox('get', 'alias_domains'));
          foreach ($domains as $domain) {
            $stmt = $pdo->prepare('SELECT `id`, `qid`, `rcpt`, `sender`, UNIX_TIMESTAMP(`created`) AS `created` FROM `quarantine` WHERE `rcpt` REGEXP :domain');
            $stmt->execute(array(':domain' => '@' . $domain . '$'));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $q_meta[] = $row;
            }
          }
        }
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
      }
      return $q_meta;
    break;
    case 'settings':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      try {
        $settings['exclude_domains'] = json_decode($redis->Get('Q_EXCLUDE_DOMAINS'), true);
        $settings['max_size'] = $redis->Get('Q_MAX_SIZE');
        $settings['retention_size'] = $redis->Get('Q_RETENTION_SIZE');
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
      return $settings;
    break;
    case 'details':
      if (!is_numeric($_data) || empty($_data)) {
        return false;
      }
      try {
        $stmt = $pdo->prepare('SELECT `rcpt`, `symbols`, `msg`, `domain` FROM `quarantine` WHERE `id`= :id');
        $stmt->execute(array(':id' => $_data));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['rcpt'])) {
          return $row;
        }
        return false;
      }
      catch(PDOException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$e
        );
      }
      return false;
    break;
  }
}
