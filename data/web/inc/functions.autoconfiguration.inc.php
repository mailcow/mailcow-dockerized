<?php
function autoconfiguration($_action, $_type, $_data = null) {
	global $pdo;
	global $lang;
  switch ($_action) {
    case 'edit':
      if (!isset($_SESSION['acl']['eas_autoconfig']) || $_SESSION['acl']['eas_autoconfig'] != "1" ) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_type, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      switch ($_type) {
        case 'autodiscover':
          $objects = (array)$_data['object'];
          foreach ($objects as $object) {
            if (is_valid_domain_name($object) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
              $exclude_regex = (isset($_data['exclude_regex'])) ? $_data['exclude_regex'] : null;
              $exclude_regex = (isset($_data['exclude_regex'])) ? $_data['exclude_regex'] : null;
              try {
                $stmt = $pdo->prepare("SELECT COUNT(`domain`) AS `domain_c` FROM `autodiscover`
                  WHERE `domain` = :domain");
                $stmt->execute(array(':domain' => $object));
                $num_results = $stmt->fetchColumn();
                if ($num_results > 0) {
                  $stmt = $pdo->prepare("SELECT COUNT(`domain`) AS `domain_c` FROM `autodiscover`
                    WHERE `domain` = :domain");
                }
              }
              catch(PDOException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data),
                  'msg' => array('mysql_error', $e)
                );
                return false;
              }
            }
            elseif (filter_var($object, FILTER_VALIDATE_EMAIL) === true && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {

            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_type, $_data),
            'msg' => array('domain_modified', htmlspecialchars(implode(', ', $objects)))
          );
        break;
      }
    break;
    case 'get':
      switch ($_type) {
        case 'autodiscover':
          $autodiscover = array();
            if (is_valid_domain_name($_data) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              try {
                $stmt = $pdo->prepare("SELECT * FROM `autodiscover`
                  WHERE `domain` = :domain");
                $stmt->execute(array(':domain' => $_data));
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                while($row = array_shift($rows)) {
                  $autodiscover['mailbox'] = $row['mailbox'];
                  $autodiscover['domain'] = $row['domain'];
                  $autodiscover['service'] = $row['service'];
                  $autodiscover['exclude_regex'] = $row['exclude_regex'];
                  $autodiscover['created'] = $row['created'];
                  $autodiscover['modified'] = $row['modified'];
                }
              }
              catch(PDOException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data),
                  'msg' => array('mysql_error', $e)
                );
                return false;
              }
            }
            elseif (filter_var($_data, FILTER_VALIDATE_EMAIL) === true && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              try {
                $stmt = $pdo->prepare("SELECT * FROM `autodiscover`
                  WHERE `mailbox` = :mailbox");
                $stmt->execute(array(':mailbox' => $_data));
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                while($row = array_shift($rows)) {
                  $autodiscover['mailbox'] = $row['mailbox'];
                  $autodiscover['domain'] = $row['domain'];
                  $autodiscover['service'] = $row['service'];
                  $autodiscover['exclude_regex'] = $row['exclude_regex'];
                  $autodiscover['created'] = $row['created'];
                  $autodiscover['modified'] = $row['modified'];
                }
              }
              catch(PDOException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_type, $_data),
                  'msg' => array('mysql_error', $e)
                );
                return false;
              }
            }
          return $autodiscover;
        break;
      }
    break;
    case 'reset':
      switch ($_type) {
        case 'autodiscover':
          if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
            return false;
          }
        break;
      }
    break;
  }
}
