<?php
function mailbox($_action, $_type, $_data = null, $attr = null) {
	global $pdo;
	global $redis;
	global $lang;
  switch ($_action) {
    case 'add':
      switch ($_type) {
        case 'time_limited_alias':
          if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (isset($_data['username']) && filter_var($_data['username'], FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data['username'])) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            else {
              $username = $_data['username'];
            }
          }
          else {
            $username = $_SESSION['mailcow_cc_username'];
          }
          if (!is_numeric($_data["validity"]) || $_data["validity"] > 672) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['validity_missing'])
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT `domain` FROM `mailbox` WHERE `username` = :username");
            $stmt->execute(array(':username' => $_SESSION['mailcow_cc_username']));
            $domain = $stmt->fetch(PDO::FETCH_ASSOC)['domain'];
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          $validity = strtotime("+".$_data["validity"]." hour"); 
          $letters = 'abcefghijklmnopqrstuvwxyz1234567890';
          $random_name = substr(str_shuffle($letters), 0, 24);
          try {
            $stmt = $pdo->prepare("INSERT INTO `spamalias` (`address`, `goto`, `validity`) VALUES
              (:address, :goto, :validity)");
            $stmt->execute(array(
              ':address' => $random_name . '@' . $domain,
              ':goto' => $username,
              ':validity' => $validity
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
            'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($usernames))
          );
        break;
        case 'filter':
          $sieve = new Sieve\SieveParser();
          if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (isset($_data['username']) && filter_var($_data['username'], FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data['username'])) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            else {
              $username = $_data['username'];
            }
          }
          elseif ($_SESSION['mailcow_cc_role'] == "user") {
            $username = $_SESSION['mailcow_cc_username'];
          }
          else {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'No user defined'
            );
            return false;
          }
          $active     = intval($_data['active']);
          $script_data = $_data['script_data'];
          $script_desc = $_data['script_desc'];
          $filter_type = $_data['filter_type'];
          if (empty($script_data)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Script cannot be empty'
            );
            return false;
          }
          try {
            $sieve->parse($script_data);
          }
          catch (Exception $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Sieve parser error: ' . $e->getMessage()
            );
            return false;
          }
          if (empty($script_data) || empty($script_desc)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Please define values for all fields'
            );
            return false;
          }
          if ($filter_type != 'postfilter' && $filter_type != 'prefilter') {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Wrong filter type'
            );
            return false;
          }
          if (!empty($active)) {
            $script_name = 'active';
            try {
              $stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_name` = 'inactive' WHERE `username` = :username AND `filter_type` = :filter_type");
              $stmt->execute(array(
                ':username' => $username,
                ':filter_type' => $filter_type
              ));
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          else {
            $script_name = 'inactive';
          }
          try {
            $stmt = $pdo->prepare("INSERT INTO `sieve_filters` (`username`, `script_data`, `script_desc`, `script_name`, `filter_type`)
              VALUES (:username, :script_data, :script_desc, :script_name, :filter_type)");
            $stmt->execute(array(
              ':username' => $username,
              ':script_data' => $script_data,
              ':script_desc' => $script_desc,
              ':script_name' => $script_name,
              ':filter_type' => $filter_type
            ));
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['mailbox_modified'], $username)
          );
          return true;
        break;
        case 'syncjob':
          if (!isset($_SESSION['acl']['syncjobs']) || $_SESSION['acl']['syncjobs'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (isset($_data['username']) && filter_var($_data['username'], FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data['username'])) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            else {
              $username = $_data['username'];
            }
          }
          elseif ($_SESSION['mailcow_cc_role'] == "user") {
            $username = $_SESSION['mailcow_cc_username'];
          }
          else {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'No user defined'
            );
            return false;
          }
          $active  = intval($_data['active']);
          $delete2duplicates    = intval($_data['delete2duplicates']);
          $delete1              = intval($_data['delete1']);
          $delete2              = intval($_data['delete2']);
          $skipcrossduplicates  = intval($_data['skipcrossduplicates']);
          $automap              = intval($_data['automap']);
          $port1                = $_data['port1'];
          $host1                = strtolower($_data['host1']);
          $password1            = $_data['password1'];
          $exclude              = $_data['exclude'];
          $maxage               = $_data['maxage'];
          $maxbytespersecond    = $_data['maxbytespersecond'];
          $subfolder2           = $_data['subfolder2'];
          $user1                = $_data['user1'];
          $mins_interval        = $_data['mins_interval'];
          $enc1                = $_data['enc1'];
          if (empty($subfolder2)) {
            $subfolder2 = "";
          }
          if (!isset($maxage) || !filter_var($maxage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32767)))) {
            $maxage = "0";
          }
          if (!isset($maxbytespersecond) || !filter_var($maxbytespersecond, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 125000000)))) {
            $maxbytespersecond = "0";
          }
          if (!filter_var($port1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)))) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (!filter_var($mins_interval, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 3600)))) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (!is_valid_domain_name($host1)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if ($enc1 != "TLS" && $enc1 != "SSL" && $enc1 != "PLAIN") {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (@preg_match("/" . $exclude . "/", null) === false) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT '1' FROM `imapsync`
              WHERE `user2` = :user2 AND `user1` = :user1 AND `host1` = :host1");
            $stmt->execute(array(':user1' => $user1, ':user2' => $username, ':host1' => $host1));
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
              'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($host1 . ' / ' . $user1))
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("INSERT INTO `imapsync` (`user2`, `exclude`, `delete1`, `delete2`, `automap`, `skipcrossduplicates`, `maxbytespersecond`, `maxage`, `subfolder2`, `host1`, `authmech1`, `user1`, `password1`, `mins_interval`, `port1`, `enc1`, `delete2duplicates`, `active`)
              VALUES (:user2, :exclude, :delete1, :delete2, :automap, :skipcrossduplicates, :maxbytespersecond, :maxage, :subfolder2, :host1, :authmech1, :user1, :password1, :mins_interval, :port1, :enc1, :delete2duplicates, :active)");
            $stmt->execute(array(
              ':user2' => $username,
              ':exclude' => $exclude,
              ':maxage' => $maxage,
              ':delete1' => $delete1,
              ':delete2' => $delete2,
              ':automap' => $automap,
              ':skipcrossduplicates' => $skipcrossduplicates,
              ':maxbytespersecond' => $maxbytespersecond,
              ':subfolder2' => $subfolder2,
              ':host1' => $host1,
              ':authmech1' => 'PLAIN',
              ':user1' => $user1,
              ':password1' => $password1,
              ':mins_interval' => $mins_interval,
              ':port1' => $port1,
              ':enc1' => $enc1,
              ':delete2duplicates' => $delete2duplicates,
              ':active' => $active,
            ));
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['mailbox_modified'], $username)
          );
          return true;
        break;
        case 'domain':
          if ($_SESSION['mailcow_cc_role'] != "admin") {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          $domain				= idn_to_ascii(strtolower(trim($_data['domain'])));
          $description  = $_data['description'];
          $aliases			= $_data['aliases'];
          $mailboxes    = $_data['mailboxes'];
          $maxquota			= $_data['maxquota'];
          $restart_sogo = $_data['restart_sogo'];
          $quota				= $_data['quota'];
          if ($maxquota > $quota) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
            );
            return false;
          }
          if ($maxquota == "0" || empty($maxquota)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['maxquota_empty'])
            );
            return false;
          }
          $active = intval($_data['active']);
          $relay_all_recipients = intval($_data['relay_all_recipients']);
          $backupmx = intval($_data['backupmx']);
          ($relay_all_recipients == 1) ? $backupmx = '1' : null;
          if (!is_valid_domain_name($domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['domain_invalid'])
            );
            return false;
          }
          foreach (array($quota, $maxquota, $mailboxes, $aliases) as $data) {
            if (!is_numeric($data)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['object_is_not_numeric'], htmlspecialchars($data))
              );
              return false;
            }
          }
          try {
            $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
              WHERE `domain` = :domain");
            $stmt->execute(array(':domain' => $domain));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain`
              WHERE `alias_domain` = :domain");
            $stmt->execute(array(':domain' => $domain));
            $num_results = $num_results + count($stmt->fetchAll(PDO::FETCH_ASSOC));
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
              'msg' => sprintf($lang['danger']['domain_exists'], htmlspecialchars($domain))
            );
            return false;
          }
          if ($domain == $MAILCOW_HOSTNAME) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['domain_matches_hostname'], htmlspecialchars($domain))
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("INSERT INTO `domain` (`domain`, `description`, `aliases`, `mailboxes`, `maxquota`, `quota`, `backupmx`, `active`, `relay_all_recipients`)
              VALUES (:domain, :description, :aliases, :mailboxes, :maxquota, :quota, :backupmx, :active, :relay_all_recipients)");
            $stmt->execute(array(
              ':domain' => $domain,
              ':description' => $description,
              ':aliases' => $aliases,
              ':mailboxes' => $mailboxes,
              ':maxquota' => $maxquota,
              ':quota' => $quota,
              ':backupmx' => $backupmx,
              ':active' => $active,
              ':relay_all_recipients' => $relay_all_recipients
            ));
            try {
              $redis->hSet('DOMAIN_MAP', $domain, 1);
            }
            catch (RedisException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Redis: '.$e
              );
              return false;
            }
            if (!empty($restart_sogo)) {
              $restart_reponse = json_decode(docker('sogo-mailcow', 'post', 'restart'), true);
              if ($restart_reponse['type'] == "success") {
                $_SESSION['return'] = array(
                  'type' => 'success',
                  'msg' => sprintf($lang['success']['domain_added'], htmlspecialchars($domain))
                );
              }
              else {
                $_SESSION['return'] = array(
                  'type' => 'warning',
                  'msg' => 'Added domain but failed to restart SOGo, please check your server logs.'
                );
              }
            }
          }
          catch (PDOException $e) {
            mailbox('delete', 'domain', array('domain' => $domain));
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
        break;
        case 'alias':
          $addresses  = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['address']));
          $gotos      = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['goto']));
          $active = intval($_data['active']);
          $goto_null = intval($_data['goto_null']);
          if (empty($addresses[0])) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['alias_empty'])
            );
            return false;
          }
          if (empty($gotos[0]) && $goto_null == 0) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['goto_empty'])
            );
            return false;
          }
          if ($goto_null == "1") {
            $goto = "null@localhost";
          }
          else {
            foreach ($gotos as &$goto) {
              if (empty($goto)) {
                continue;
              }
              $goto_domain = idn_to_ascii(substr(strstr($goto, '@'), 1));
              $goto_local_part = strstr($goto, '@', true);
              $goto = $goto_local_part.'@'.$goto_domain;
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
                WHERE `kind` REGEXP 'location|thing|group'
                  AND `username`= :goto");
              $stmt->execute(array(':goto' => $goto));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
              if ($num_results != 0) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['goto_invalid'])
                );
                return false;
              }
              if (!filter_var($goto, FILTER_VALIDATE_EMAIL) === true) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['goto_invalid'])
                );
                return false;
              }
            }
            $gotos = array_filter($gotos);
            $goto = implode(",", $gotos);
          }
          foreach ($addresses as $address) {
            if (empty($address)) {
              continue;
            }
            if (in_array($address, $gotos)) {
              continue;
            }
            $domain       = idn_to_ascii(substr(strstr($address, '@'), 1));
            $local_part   = strstr($address, '@', true);
            $address      = $local_part.'@'.$domain;
            $stmt = $pdo->prepare("SELECT `address` FROM `alias`
              WHERE `address`= :address OR `address` IN (
                SELECT `username` FROM `mailbox`, `alias_domain`
                  WHERE (
                    `alias_domain`.`alias_domain` = :address_d
                      AND `mailbox`.`username` = CONCAT(:address_l, '@', alias_domain.target_domain)))");
            $stmt->execute(array(
              ':address' => $address,
              ':address_l' => $local_part,
              ':address_d' => $domain
            ));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['is_alias_or_mailbox'], htmlspecialchars($address))
              );
              return false;
            }
            $domaindata = mailbox('get', 'domain_details', $domain);
            if (is_array($domaindata) && $domaindata['aliases_left'] == "0") {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['max_alias_exceeded'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
                WHERE `domain`= :domain1 OR `domain` = (SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain2)");
              $stmt->execute(array(':domain1' => $domain, ':domain2' => $domain));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
              if ($num_results == 0) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['domain_not_found'], htmlspecialchars($domain))
                );
                return false;
              }
              $stmt = $pdo->prepare("SELECT `address` FROM `alias`
                WHERE `address`= :address");
              $stmt->execute(array(':address' => $address));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
              if ($num_results != 0) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['is_alias_or_mailbox'], htmlspecialchars($address))
                );
                return false;
              }
              $stmt = $pdo->prepare("SELECT `address` FROM `spamalias`
                WHERE `address`= :address");
              $stmt->execute(array(':address' => $address));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
              if ($num_results != 0) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($address))
                );
                return false;
              }
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['alias_invalid'])
              );
              return false;
            }
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `active`)
                VALUES (:address, :goto, :domain, :active)");
              if (!filter_var($address, FILTER_VALIDATE_EMAIL) === true) {
                $stmt->execute(array(
                  ':address' => '@'.$domain,
                  ':goto' => $goto,
                  ':domain' => $domain,
                  ':active' => $active
                ));
              }
              else {
                $stmt->execute(array(
                  ':address' => $address,
                  ':goto' => $goto,
                  ':domain' => $domain,
                  ':active' => $active
                ));
              }
              $_SESSION['return'] = array(
                'type' => 'success',
                'msg' => sprintf($lang['success']['alias_added'])
              );
            }
            catch (PDOException $e) {
              mailbox('delete', 'alias', array('address' => $address));
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['alias_added'])
          );
        break;
        case 'alias_domain':
          $active = intval($_data['active']);
          $alias_domains  = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['alias_domain']));
          $target_domain = idn_to_ascii(strtolower(trim($_data['target_domain'])));
          if (!is_valid_domain_name($target_domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['target_domain_invalid'])
            );
            return false;
          }
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $target_domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($alias_domains as $alias_domain) {
            $alias_domain = idn_to_ascii(strtolower(trim($alias_domain)));
            if (!is_valid_domain_name($alias_domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['alias_domain_invalid'])
              );
              return false;
            }
            if ($alias_domain == $target_domain) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['aliasd_targetd_identical'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
                WHERE `domain`= :target_domain");
              $stmt->execute(array(':target_domain' => $target_domain));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
              if ($num_results == 0) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['targetd_not_found'])
                );
                return false;
              }
              $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain`= :alias_domain
                UNION
                SELECT `domain` FROM `domain` WHERE `domain`= :alias_domain_in_domain");
              $stmt->execute(array(':alias_domain' => $alias_domain, ':alias_domain_in_domain' => $alias_domain));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
              if ($num_results != 0) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['alias_domain_invalid'])
                );
                return false;
              }
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("INSERT INTO `alias_domain` (`alias_domain`, `target_domain`, `active`)
                VALUES (:alias_domain, :target_domain, :active)");
              $stmt->execute(array(
                ':alias_domain' => $alias_domain,
                ':target_domain' => $target_domain,
                ':active' => $active
              ));
            }
            catch (PDOException $e) {
              mailbox('delete', 'alias_domain', array('alias_domain' => $alias_domain));
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            try {
              $redis->hSet('DOMAIN_MAP', $alias_domain, 1);
            }
            catch (RedisException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Redis: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['aliasd_added'], htmlspecialchars(implode(', ', $alias_domains)))
          );
        break;
        case 'mailbox':
          $local_part   = strtolower(trim($_data['local_part']));
          $domain       = idn_to_ascii(strtolower(trim($_data['domain'])));
          $username     = $local_part . '@' . $domain;
          if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['mailbox_invalid'])
            );
            return false;
          }
          if (empty($_data['local_part'])) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['mailbox_invalid'])
            );
            return false;
          }
          $password     = $_data['password'];
          $password2    = $_data['password2'];
          $name         = $_data['name'];
          $quota_m			= filter_var($_data['quota'], FILTER_SANITIZE_NUMBER_FLOAT);
          if (empty($name)) {
            $name = $local_part;
          }
          $active = intval($_data['active']);
          $quota_b		= ($quota_m * 1048576);
          $maildir		= $domain . "/" . $local_part . "/";
          if (!is_valid_domain_name($domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['domain_invalid'])
            );
            return false;
          }
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT `mailboxes`, `maxquota`, `quota` FROM `domain`
              WHERE `domain` = :domain");
            $stmt->execute(array(':domain' => $domain));
            $DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT 
              COUNT(*) as count,
              COALESCE(ROUND(SUM(`quota`)/1048576), 0) as `quota`
                FROM `mailbox`
                  WHERE `kind` NOT REGEXP 'location|thing|group'
                    AND `domain` = :domain");
            $stmt->execute(array(':domain' => $domain));
            $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT `local_part` FROM `mailbox` WHERE `local_part` = :local_part and `domain`= :domain");
            $stmt->execute(array(':local_part' => $local_part, ':domain' => $domain));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($username))
              );
              return false;
            }
            $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE address= :username");
            $stmt->execute(array(':username' => $username));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($username))
              );
              return false;
            }
            $stmt = $pdo->prepare("SELECT `address` FROM `spamalias` WHERE `address`= :username");
            $stmt->execute(array(':username' => $username));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($username))
              );
              return false;
            }
            $stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain`= :domain");
            $stmt->execute(array(':domain' => $domain));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results == 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['domain_not_found'], htmlspecialchars($domain))
              );
              return false;
            }
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          if (!is_numeric($quota_m) || $quota_m == "0") {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'])
            );
            return false;
          }
          if (!empty($password) && !empty($password2)) {
            if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['password_complexity'])
              );
              return false;
            }
            if ($password != $password2) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['password_mismatch'])
              );
              return false;
            }
            $password_hashed = hash_password($password);
          }
          else {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['password_empty'])
            );
            return false;
          }
          if ($MailboxData['count'] >= $DomainData['mailboxes']) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['max_mailbox_exceeded'], $MailboxData['count'], $DomainData['mailboxes'])
            );
            return false;
          }
          if ($quota_m > $DomainData['maxquota']) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $DomainData['maxquota'])
            );
            return false;
          }
          if (($MailboxData['quota'] + $quota_m) > $DomainData['quota']) {
            $quota_left_m = ($DomainData['quota'] - $MailboxData['quota']);
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], $quota_left_m)
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `attributes`, `active`) 
              VALUES (:username, :password_hashed, :name, :maildir, :quota_b, :local_part, :domain, '{\"force_pw_update\": \"0\", \"tls_enforce_in\": \"0\", \"tls_enforce_out\": \"0\"}', :active)");
            $stmt->execute(array(
              ':username' => $username,
              ':password_hashed' => $password_hashed,
              ':name' => $name,
              ':maildir' => $maildir,
              ':quota_b' => $quota_b,
              ':local_part' => $local_part,
              ':domain' => $domain,
              ':active' => $active
            ));
            $stmt = $pdo->prepare("INSERT INTO `quota2` (`username`, `bytes`, `messages`)
              VALUES (:username, '0', '0') ON DUPLICATE KEY UPDATE `bytes` = '0', `messages` = '0';");
            $stmt->execute(array(':username' => $username));
            $stmt = $pdo->prepare("INSERT INTO `alias` (`address`, `goto`, `domain`, `active`)
              VALUES (:username1, :username2, :domain, :active)");
            $stmt->execute(array(
              ':username1' => $username,
              ':username2' => $username,
              ':domain' => $domain,
              ':active' => $active
            ));
            $stmt = $pdo->prepare("INSERT INTO `user_acl` (`username`) VALUES (:username)");
            $stmt->execute(array(
              ':username' => $username
            ));
            $_SESSION['return'] = array(
              'type' => 'success',
              'msg' => sprintf($lang['success']['mailbox_added'], htmlspecialchars($username))
            );
          }
          catch (PDOException $e) {
            mailbox('delete', 'mailbox', array('username' => $username));
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
        break;
        case 'resource':
          $domain             = idn_to_ascii(strtolower(trim($_data['domain'])));
          $description        = $_data['description'];
          $local_part         = preg_replace('/[^\da-z]/i', '', preg_quote($description, '/'));
          $name               = $local_part . '@' . $domain;
          $kind               = $_data['kind'];
          $multiple_bookings  = intval($_data['multiple_bookings']);
          $active = intval($_data['active']);
          if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['resource_invalid'])
            );
            return false;
          }
          if (empty($description)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['description_invalid'])
            );
            return false;
          }
          if (!isset($multiple_bookings) || $multiple_bookings < -1) {
            $multiple_bookings = -1;
          }
          if ($kind != 'location' && $kind != 'group' && $kind != 'thing') {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['resource_invalid'])
            );
            return false;
          }
          if (!is_valid_domain_name($domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['domain_invalid'])
            );
            return false;
          }
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :name");
            $stmt->execute(array(':name' => $name));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['object_exists'], htmlspecialchars($name))
              );
              return false;
            }
            $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE address= :name");
            $stmt->execute(array(':name' => $name));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['is_alias'], htmlspecialchars($name))
              );
              return false;
            }
            $stmt = $pdo->prepare("SELECT `address` FROM `spamalias` WHERE `address`= :name");
            $stmt->execute(array(':name' => $name));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results != 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['is_spam_alias'], htmlspecialchars($name))
              );
              return false;
            }
            $stmt = $pdo->prepare("SELECT `domain` FROM `domain` WHERE `domain`= :domain");
            $stmt->execute(array(':domain' => $domain));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($num_results == 0) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['domain_not_found'], htmlspecialchars($domain))
              );
              return false;
            }
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          try {
            $stmt = $pdo->prepare("INSERT INTO `mailbox` (`username`, `password`, `name`, `maildir`, `quota`, `local_part`, `domain`, `active`, `multiple_bookings`, `kind`) 
              VALUES (:name, 'RESOURCE', :description, 'RESOURCE', 0, :local_part, :domain, :active, :multiple_bookings, :kind)");
            $stmt->execute(array(
              ':name' => $name,
              ':description' => $description,
              ':local_part' => $local_part,
              ':domain' => $domain,
              ':active' => $active,
              ':kind' => $kind,
              ':multiple_bookings' => $multiple_bookings
            ));
            $_SESSION['return'] = array(
              'type' => 'success',
              'msg' => sprintf($lang['success']['resource_added'], htmlspecialchars($name))
            );
          }
          catch (PDOException $e) {
            mailbox('delete', 'resource', array('name' => $name));
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
        break;
      }
    break;
    case 'edit':
      switch ($_type) {
        case 'alias_domain':
          $alias_domains = (array)$_data['alias_domain'];
          foreach ($alias_domains as $alias_domain) {
            $alias_domain = idn_to_ascii(strtolower(trim($alias_domain)));
            $is_now = mailbox('get', 'alias_domain_details', $alias_domain);
            if (!empty($is_now)) {
              $active         = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
              $target_domain  = (!empty($_data['target_domain'])) ? idn_to_ascii(strtolower(trim($_data['target_domain']))) : $is_now['target_domain'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['alias_domain_invalid'])
              );
              return false;
            }
            if (!is_valid_domain_name($target_domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['target_domain_invalid'])
              );
              return false;
            }
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $target_domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (empty(mailbox('get', 'domain_details', $target_domain)) || !empty(mailbox('get', 'alias_domain_details', $target_domain))) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['target_domain_invalid'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("UPDATE `alias_domain` SET
                `target_domain` = :target_domain,
                `active` = :active
                  WHERE `alias_domain` = :alias_domain");
              $stmt->execute(array(
                ':alias_domain' => $alias_domain,
                ':target_domain' => $target_domain,
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
            'msg' => sprintf($lang['success']['aliasd_modified'], htmlspecialchars(implode(', ', $alias_domains)))
          );
        break;
        case 'tls_policy':
          if (!is_array($_data['username'])) {
            $usernames = array();
            $usernames[] = $_data['username'];
          }
          else {
            $usernames = $_data['username'];
          }
          if (!isset($_SESSION['acl']['tls_policy']) || $_SESSION['acl']['tls_policy'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($usernames as $username) {
            if (!filter_var($username, FILTER_VALIDATE_EMAIL) || !hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            $is_now = mailbox('get', 'tls_policy', $username);
            if (!empty($is_now)) {
              $tls_enforce_in = (isset($_data['tls_enforce_in'])) ? intval($_data['tls_enforce_in']) : $is_now['tls_enforce_in'];
              $tls_enforce_out = (isset($_data['tls_enforce_out'])) ? intval($_data['tls_enforce_out']) : $is_now['tls_enforce_out'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("UPDATE `mailbox` SET `attributes` = JSON_SET(`attributes`, '$.tls_enforce_out', :tls_out), `attributes` = JSON_SET(`attributes`, '$.tls_enforce_in', :tls_in) WHERE `username` = :username");
              $stmt->execute(array(
                ':tls_out' => intval($tls_enforce_out),
                ':tls_in' => intval($tls_enforce_in),
                ':username' => $username
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
            'msg' => sprintf($lang['success']['mailbox_modified'], implode(', ', $usernames))
          );
        break;
        case 'spam_score':
          if (!is_array($_data['username'])) {
            $usernames = array();
            $usernames[] = $_data['username'];
          }
          else {
            $usernames = $_data['username'];
          }
          if (!isset($_SESSION['acl']['spam_score']) || $_SESSION['acl']['spam_score'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($usernames as $username) {
            $lowspamlevel	= explode(',', $_data['spam_score'])[0];
            $highspamlevel	= explode(',', $_data['spam_score'])[1];
            if (!is_numeric($lowspamlevel) || !is_numeric($highspamlevel)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :username
                AND (`option` = 'lowspamlevel' OR `option` = 'highspamlevel')");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("INSERT INTO `filterconf` (`object`, `option`, `value`)
                VALUES (:username, 'highspamlevel', :highspamlevel)");
              $stmt->execute(array(
                ':username' => $username,
                ':highspamlevel' => $highspamlevel
              ));
              $stmt = $pdo->prepare("INSERT INTO `filterconf` (`object`, `option`, `value`)
                VALUES (:username, 'lowspamlevel', :lowspamlevel)");
              $stmt->execute(array(
                ':username' => $username,
                ':lowspamlevel' => $lowspamlevel
              ));
            }
            catch (PDOException $e) {
              $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :username
                AND (`option` = 'lowspamlevel' OR `option` = 'highspamlevel')");
              $stmt->execute(array(
                ':username' => $username
              ));
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['mailbox_modified'], implode(', ', $usernames))
          );
        break;
        case 'time_limited_alias':
          if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (!is_array($_data['address'])) {
            $addresses = array();
            $addresses[] = $_data['address'];
          }
          else {
            $addresses = $_data['address'];
          }
          foreach ($addresses as $address) {
            try {
              $stmt = $pdo->prepare("SELECT `goto` FROM `spamalias` WHERE `address` = :address");
              $stmt->execute(array(':address' => $address));
              $goto = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $goto)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("UPDATE `spamalias` SET `validity` = (`validity` + 3600) WHERE 
                `address` = :address AND
                `validity` >= :validity");
              $stmt->execute(array(
                ':address' => $address,
                ':validity' => time()
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
            'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars(implode(', ', $usernames)))
          );
        break;
        case 'delimiter_action':
          if (!is_array($_data['username'])) {
            $usernames = array();
            $usernames[] = $_data['username'];
          }
          else {
            $usernames = $_data['username'];
          }
          if (!isset($_SESSION['acl']['delimiter_action']) || $_SESSION['acl']['delimiter_action'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($usernames as $username) {
            if (!filter_var($username, FILTER_VALIDATE_EMAIL) || !hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (isset($_data['tagged_mail_handler']) && $_data['tagged_mail_handler'] == "subject") {
              try {
                $redis->hSet('RCPT_WANTS_SUBJECT_TAG', $username, 1);
                $redis->hDel('RCPT_WANTS_SUBFOLDER_TAG', $username);
              }
              catch (RedisException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Redis: '.$e
                );
                return false;
              }
            }
            else if (isset($_data['tagged_mail_handler']) && $_data['tagged_mail_handler'] == "subfolder") {
              try {
                $redis->hSet('RCPT_WANTS_SUBFOLDER_TAG', $username, 1);
                $redis->hDel('RCPT_WANTS_SUBJECT_TAG', $username);
              }
              catch (RedisException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Redis: '.$e
                );
                return false;
              }
            }
            else {
              try {
                $redis->hDel('RCPT_WANTS_SUBJECT_TAG', $username);
                $redis->hDel('RCPT_WANTS_SUBFOLDER_TAG', $username);
              }
              catch (RedisException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Redis: '.$e
                );
                return false;
              }
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['mailbox_modified'], implode(', ', $usernames))
          );
        break;
        case 'ratelimit':
          $rl_value = intval($_data['rl_value']);
          $rl_frame = $_data['rl_frame'];
          if (!in_array($rl_frame, array('s', 'm', 'h'))) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Ratelimit time frame is incorrect'
              );
              return false;
          }
          if (!is_array($_data['object'])) {
            $objects = array();
            $objects[] = $_data['object'];
          }
          else {
            $objects = $_data['object'];
          }
          foreach ($objects as $object) {
            if (is_valid_domain_name($object)) {
              if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['access_denied'])
                );
                return false;
              }
            }
            elseif (filter_var($object, FILTER_VALIDATE_EMAIL)) {
              if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $object)) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['access_denied'])
                );
                return false;
              }
            }
            else {
              return false;
            }
            if (empty($rl_value)) {
              try {
                $redis->hDel('RL_VALUE', $object);
              }
              catch (RedisException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Redis: '.$e
                );
                return false;
              }
            }
            else {
              try {
                $redis->hSet('RL_VALUE', $object, $rl_value . ' / 1' . $rl_frame);
              }
              catch (RedisException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Redis: '.$e
                );
                return false;
              }
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['domain_modified'], implode(', ', $objects))
          );
        break;
        case 'syncjob':
          if (!is_array($_data['id'])) {
            $ids = array();
            $ids[] = $_data['id'];
          }
          else {
            $ids = $_data['id'];
          }
          if (!isset($_SESSION['acl']['syncjobs']) || $_SESSION['acl']['syncjobs'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($ids as $id) {
            $is_now = mailbox('get', 'syncjob_details', $id, array('with_password'));
            if (!empty($is_now)) {
              $username = $is_now['user2'];
              $user1 = (!empty($_data['user1'])) ? $_data['user1'] : $is_now['user1'];
              $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
              $last_run = (isset($_data['last_run'])) ? NULL : $is_now['last_run'];
              $delete2duplicates = (isset($_data['delete2duplicates'])) ? intval($_data['delete2duplicates']) : $is_now['delete2duplicates'];
              $delete1 = (isset($_data['delete1'])) ? intval($_data['delete1']) : $is_now['delete1'];
              $delete2 = (isset($_data['delete2'])) ? intval($_data['delete2']) : $is_now['delete2'];
              $automap = (isset($_data['automap'])) ? intval($_data['automap']) : $is_now['automap'];
              $skipcrossduplicates = (isset($_data['skipcrossduplicates'])) ? intval($_data['skipcrossduplicates']) : $is_now['skipcrossduplicates'];
              $port1 = (!empty($_data['port1'])) ? $_data['port1'] : $is_now['port1'];
              $password1 = (!empty($_data['password1'])) ? $_data['password1'] : $is_now['password1'];
              $host1 = (!empty($_data['host1'])) ? $_data['host1'] : $is_now['host1'];
              $subfolder2 = (isset($_data['subfolder2'])) ? $_data['subfolder2'] : $is_now['subfolder2'];
              $enc1 = (!empty($_data['enc1'])) ? $_data['enc1'] : $is_now['enc1'];
              $mins_interval = (!empty($_data['mins_interval'])) ? $_data['mins_interval'] : $is_now['mins_interval'];
              $exclude = (isset($_data['exclude'])) ? $_data['exclude'] : $is_now['exclude'];
              $maxage = (isset($_data['maxage']) && $_data['maxage'] != "") ? intval($_data['maxage']) : $is_now['maxage'];
              $maxbytespersecond = (isset($_data['maxbytespersecond']) && $_data['maxbytespersecond'] != "") ? intval($_data['maxbytespersecond']) : $is_now['maxbytespersecond'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (empty($subfolder2)) {
              $subfolder2 = "";
            }
            if (!isset($maxage) || !filter_var($maxage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 32767)))) {
              $maxage = "0";
            }
            if (!isset($maxbytespersecond) || !filter_var($maxbytespersecond, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 125000000)))) {
              $maxbytespersecond = "0";
            }
            if (!filter_var($port1, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)))) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (!filter_var($mins_interval, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 3600)))) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (!is_valid_domain_name($host1)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if ($enc1 != "TLS" && $enc1 != "SSL" && $enc1 != "PLAIN") {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (@preg_match("/" . $exclude . "/", null) === false) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("UPDATE `imapsync` SET `delete1` = :delete1,
                `delete2` = :delete2,
                `automap` = :automap,
                `skipcrossduplicates` = :skipcrossduplicates,
                `maxage` = :maxage,
                `maxbytespersecond` = :maxbytespersecond,
                `subfolder2` = :subfolder2,
                `exclude` = :exclude,
                `host1` = :host1,
                `last_run` = :last_run,
                `user1` = :user1,
                `password1` = :password1,
                `mins_interval` = :mins_interval,
                `port1` = :port1,
                `enc1` = :enc1,
                `delete2duplicates` = :delete2duplicates,
                `active` = :active
                  WHERE `id` = :id");
              $stmt->execute(array(
                ':delete1' => $delete1,
                ':delete2' => $delete2,
                ':automap' => $automap,
                ':skipcrossduplicates' => $skipcrossduplicates,
                ':id' => $id,
                ':exclude' => $exclude,
                ':maxage' => $maxage,
                ':maxbytespersecond' => $maxbytespersecond,
                ':subfolder2' => $subfolder2,
                ':host1' => $host1,
                ':user1' => $user1,
                ':password1' => $password1,
                ':last_run' => $last_run,
                ':mins_interval' => $mins_interval,
                ':port1' => $port1,
                ':enc1' => $enc1,
                ':delete2duplicates' => $delete2duplicates,
                ':active' => $active,
              ));
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['mailbox_modified'], $username)
          );
          return true;
        break;
        case 'filter':
          $sieve = new Sieve\SieveParser();
          if (!is_array($_data['id'])) {
            $ids = array();
            $ids[] = $_data['id'];
          }
          else {
            $ids = $_data['id'];
          }
          if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($ids as $id) {
            $is_now = mailbox('get', 'filter_details', $id);
            if (!empty($is_now)) {
              $username = $is_now['username'];
              $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
              $script_desc = (!empty($_data['script_desc'])) ? $_data['script_desc'] : $is_now['script_desc'];
              $script_data = (!empty($_data['script_data'])) ? $_data['script_data'] : $is_now['script_data'];
              $filter_type = (!empty($_data['filter_type'])) ? $_data['filter_type'] : $is_now['filter_type'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $sieve->parse($script_data);
            }
            catch (Exception $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Sieve parser error: ' . $e->getMessage()
              );
              return false;
            }
            if ($filter_type != 'postfilter' && $filter_type != 'prefilter') {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Wrong filter type'
              );
              return false;
            }
            if ($active == '1') {
              $script_name = 'active';
              try {
                $stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_name` = 'inactive' WHERE `username` = :username AND `filter_type` = :filter_type");
                $stmt->execute(array(
                  ':username' => $username,
                  ':filter_type' => $filter_type
                ));
              }
              catch(PDOException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'MySQL: '.$e
                );
                return false;
              }
            }
            else {
              $script_name = 'inactive';
            }
            try {
              $stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_desc` = :script_desc, `script_data` = :script_data, `script_name` = :script_name, `filter_type` = :filter_type
                WHERE `id` = :id");
              $stmt->execute(array(
                ':script_desc' => $script_desc,
                ':script_data' => $script_data,
                ':script_name' => $script_name,
                ':filter_type' => $filter_type,
                ':id' => $id
              ));
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['mailbox_modified'], $username)
          );
          return true;
        break;
        case 'alias':
          if (!is_array($_data['address'])) {
            $addresses = array();
            $addresses[] = $_data['address'];
          }
          else {
            $addresses = $_data['address'];
          }
          foreach ($addresses as $address) {
            $is_now = mailbox('get', 'alias_details', $address);
            if (!empty($is_now)) {
              $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
              $goto_null = (isset($_data['goto_null'])) ? intval($_data['goto_null']) : $is_now['goto_null'];
              $goto   = (!empty($_data['goto'])) ? $_data['goto'] : $is_now['goto'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['alias_invalid'])
              );
              return false;
            }
            if ($goto_null == "1") {
              $goto = "null@localhost";
            }
            else {
              $gotos = array_map('trim', preg_split( "/( |,|;|\n)/", $_data['goto']));
              foreach ($gotos as &$goto) {
                if (empty($goto)) {
                  continue;
                }
                if (!filter_var($goto, FILTER_VALIDATE_EMAIL)) {
                  $_SESSION['return'] = array(
                    'type' => 'danger',
                    'msg' =>sprintf($lang['danger']['goto_invalid'])
                  );
                  return false;
                }
                if ($goto == $address) {
                  $_SESSION['return'] = array(
                    'type' => 'danger',
                    'msg' => sprintf($lang['danger']['alias_goto_identical'])
                  );
                  return false;
                }
              }
              $gotos = array_filter($gotos);
              $goto = implode(",", $gotos);
            }
            $domain = idn_to_ascii(substr(strstr($address, '@'), 1));
            $local_part = strstr($address, '@', true);
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if ((!filter_var($address, FILTER_VALIDATE_EMAIL) === true) && !empty($local_part)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['alias_invalid'])
              );
              return false;
            }
            try {
              if (!empty($goto)) {
                $stmt = $pdo->prepare("UPDATE `alias` SET
                  `goto` = :goto,
                  `active`= :active
                    WHERE `address` = :address");
                $stmt->execute(array(
                  ':goto' => $goto,
                  ':active' => $active,
                  ':address' => $address
                ));
              }
              else {
                $stmt = $pdo->prepare("UPDATE `alias` SET
                  `active`= :active
                    WHERE `address` = :address");
                $stmt->execute(array(
                  ':active' => $active,
                  ':address' => $address
                ));
              }
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
            'msg' => sprintf($lang['success']['alias_modified'], htmlspecialchars(implode(', ', $addresses)))
          );
        break;
        case 'domain':
          if (!is_array($_data['domain'])) {
            $domains = array();
            $domains[] = $_data['domain'];
          }
          else {
            $domains = $_data['domain'];
          }
          foreach ($domains as $domain) {
            $domain = idn_to_ascii($domain);
            if (!is_valid_domain_name($domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['domain_invalid'])
              );
              return false;
            }
            if ($_SESSION['mailcow_cc_role'] == "domainadmin" &&
            hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
              $description  = $_data['description'];
              $active = intval($_data['active']);
              try {
                $stmt = $pdo->prepare("UPDATE `domain` SET 
                `description` = :description
                  WHERE `domain` = :domain");
                $stmt->execute(array(
                  ':description' => $description,
                  ':domain' => $domain
                ));
                $_SESSION['return'] = array(
                  'type' => 'success',
                  'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars($domain))
                );
              }
              catch (PDOException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'MySQL: '.$e
                );
                return false;
              }
            }
            elseif ($_SESSION['mailcow_cc_role'] == "admin") {
              $is_now = mailbox('get', 'domain_details', $domain);
              if (!empty($is_now)) {
                $active               = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
                $backupmx             = (isset($_data['backupmx'])) ? intval($_data['backupmx']) : $is_now['backupmx_int'];
                $relay_all_recipients = (isset($_data['relay_all_recipients'])) ? intval($_data['relay_all_recipients']) : $is_now['relay_all_recipients_int'];
                $relayhost            = (isset($_data['relayhost'])) ? intval($_data['relayhost']) : $is_now['relayhost'];
                $aliases              = (!empty($_data['aliases'])) ? $_data['aliases'] : $is_now['max_num_aliases_for_domain'];
                $mailboxes            = (!empty($_data['mailboxes'])) ? $_data['mailboxes'] : $is_now['max_num_mboxes_for_domain'];
                $maxquota             = (!empty($_data['maxquota'])) ? $_data['maxquota'] : ($is_now['max_quota_for_mbox'] / 1048576);
                $quota                = (!empty($_data['quota'])) ? $_data['quota'] : ($is_now['max_quota_for_domain'] / 1048576);
                $description          = (!empty($_data['description'])) ? $_data['description'] : $is_now['description'];
                ($relay_all_recipients == '1') ? $backupmx = '1' : null;
              }
              else {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['domain_invalid'])
                );
                return false;
              }
              try {
                // todo: should be using api here
                $stmt = $pdo->prepare("SELECT 
                    COUNT(*) AS count,
                    MAX(COALESCE(ROUND(`quota`/1048576), 0)) AS `biggest_mailbox`,
                    COALESCE(ROUND(SUM(`quota`)/1048576), 0) AS `quota_all`
                      FROM `mailbox`
                        WHERE `kind` NOT REGEXP 'location|thing|group'
                          AND domain = :domain");
                $stmt->execute(array(':domain' => $domain));
                $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
                // todo: should be using api here
                $stmt = $pdo->prepare("SELECT COUNT(*) AS `count` FROM `alias`
                    WHERE domain = :domain
                    AND address NOT IN (
                      SELECT `username` FROM `mailbox`
                    )");
                $stmt->execute(array(':domain' => $domain));
                $AliasData = $stmt->fetch(PDO::FETCH_ASSOC);
              }
              catch(PDOException $e) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'MySQL: '.$e
                );
                return false;
              }
              if ($maxquota > $quota) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['mailbox_quota_exceeds_domain_quota'])
                );
                return false;
              }
              if ($maxquota == "0" || empty($maxquota)) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['maxquota_empty'])
                );
                return false;
              }
              if ($MailboxData['biggest_mailbox'] > $maxquota) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['max_quota_in_use'], $MailboxData['biggest_mailbox'])
                );
                return false;
              }
              if ($MailboxData['quota_all'] > $quota) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['domain_quota_m_in_use'], $MailboxData['quota_all'])
                );
                return false;
              }
              if ($MailboxData['count'] > $mailboxes) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['mailboxes_in_use'], $MailboxData['count'])
                );
                return false;
              }
              if ($AliasData['count'] > $aliases) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['aliases_in_use'], $AliasData['count'])
                );
                return false;
              }
              try {
                $stmt = $pdo->prepare("UPDATE `domain` SET 
                `relay_all_recipients` = :relay_all_recipients,
                `backupmx` = :backupmx,
                `active` = :active,
                `quota` = :quota,
                `maxquota` = :maxquota,
                `relayhost` = :relayhost,
                `mailboxes` = :mailboxes,
                `aliases` = :aliases,
                `description` = :description
                  WHERE `domain` = :domain");
                $stmt->execute(array(
                  ':relay_all_recipients' => $relay_all_recipients,
                  ':backupmx' => $backupmx,
                  ':active' => $active,
                  ':quota' => $quota,
                  ':maxquota' => $maxquota,
                  ':relayhost' => $relayhost,
                  ':mailboxes' => $mailboxes,
                  ':aliases' => $aliases,
                  ':description' => $description,
                  ':domain' => $domain
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
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['domain_modified'], htmlspecialchars(implode(', ', $domains)))
          );
        break;
        case 'mailbox':
          if (!is_array($_data['username'])) {
            $usernames = array();
            $usernames[] = $_data['username'];
          }
          else {
            $usernames = $_data['username'];
          }
          foreach ($usernames as $username) {
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['username_invalid'])
              );
              return false;
            }
            $is_now = mailbox('get', 'mailbox_details', $username);
            if (!empty($is_now)) {
              $active     = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
              (int)$force_pw_update = (isset($_data['force_pw_update'])) ? intval($_data['force_pw_update']) : intval($is_now['attributes']['force_pw_update']);
              $name       = (!empty($_data['name'])) ? $_data['name'] : $is_now['name'];
              $domain     = $is_now['domain'];
              $quota_m    = (!empty($_data['quota'])) ? $_data['quota'] : ($is_now['quota'] / 1048576);
              $quota_b    = $quota_m * 1048576;
              $password   = (!empty($_data['password'])) ? $_data['password'] : null;
              $password2  = (!empty($_data['password2'])) ? $_data['password2'] : null; 
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("SELECT `quota`, `maxquota`
                FROM `domain`
                  WHERE `domain` = :domain");
              $stmt->execute(array(':domain' => $domain));
              $DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (!is_numeric($quota_m) || $quota_m == "0") {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['quota_not_0_not_numeric'], htmlspecialchars($quota_m))
              );
              return false;
            }
            if ($quota_m > $DomainData['maxquota']) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['mailbox_quota_exceeded'], $DomainData['maxquota'])
              );
              return false;
            }
            if (((($is_now['quota_used'] / 1048576) - $quota_m) + $quota_m) > $DomainData['quota']) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['mailbox_quota_left_exceeded'], ($is_now['max_new_quota'] / 1048576))
              );
              return false;
            }
            if (isset($_data['sender_acl'])) {
              // Get sender_acl items set by admin
              $sender_acl_admin = array_merge(
                mailbox('get', 'sender_acl_handles', $username)['sender_acl_domains']['ro'],
                mailbox('get', 'sender_acl_handles', $username)['sender_acl_addresses']['ro']
              );
              // Get sender_acl items from POST array
              $sender_acl_domain_admin = ($_data['sender_acl'] == "0") ? array() : (array)$_data['sender_acl'];
              if (!empty($sender_acl_domain_admin) || !empty($sender_acl_admin)) {
                // Check items in POST array and skip invalid
                foreach ($sender_acl_domain_admin as $key => $val) {
                  if (!filter_var($val, FILTER_VALIDATE_EMAIL) && !is_valid_domain_name(ltrim($val, '@'))) {
                    unset($sender_acl_domain_admin[$key]);
                  }
                  if (is_valid_domain_name(ltrim($val, '@'))) {
                    if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], ltrim($val, '@'))) {
                      $_SESSION['return'] = array(
                        'type' => 'danger',
                        'msg' => sprintf($lang['danger']['sender_acl_invalid'])
                      );
                      return false;
                    }
                  }
                  if (filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $val)) {
                      $_SESSION['return'] = array(
                        'type' => 'danger',
                        'msg' => sprintf($lang['danger']['sender_acl_invalid'])
                      );
                      return false;
                    }
                  }
                }
                // Merge both arrays
                $sender_acl_merged = array_merge($sender_acl_domain_admin, $sender_acl_admin);
                try {
                  $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
                  $stmt->execute(array(
                    ':username' => $username
                  ));
                }
                catch (PDOException $e) {
                  $_SESSION['return'] = array(
                    'type' => 'danger',
                    'msg' => 'MySQL: '.$e
                  );
                  return false;
                }
                foreach ($sender_acl_merged as $sender_acl) {
                  $domain = ltrim($sender_acl, '@');
                  if (is_valid_domain_name($domain)) {
                    $sender_acl = '@' . $domain;
                  }
                  try {
                    $stmt = $pdo->prepare("INSERT INTO `sender_acl` (`send_as`, `logged_in_as`)
                      VALUES (:sender_acl, :username)");
                    $stmt->execute(array(
                      ':sender_acl' => $sender_acl,
                      ':username' => $username
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
              }
              else {
                try {
                  $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
                  $stmt->execute(array(
                    ':username' => $username
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
            }
            if (!empty($password) && !empty($password2)) {
              if (!preg_match('/' . $GLOBALS['PASSWD_REGEP'] . '/', $password)) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['password_complexity'])
                );
                return false;
              }
              if ($password != $password2) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['password_mismatch'])
                );
                return false;
              }
              $password_hashed = hash_password($password);
              try {
                $stmt = $pdo->prepare("UPDATE `mailbox` SET
                    `password` = :password_hashed
                      WHERE `username` = :username");
                $stmt->execute(array(
                  ':password_hashed' => $password_hashed,
                  ':username' => $username
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
            try {
              $stmt = $pdo->prepare("UPDATE `alias` SET
                  `active` = :active
                    WHERE `address` = :address");
              $stmt->execute(array(
                ':address' => $username,
                ':active' => $active
              ));
              $stmt = $pdo->prepare("UPDATE `mailbox` SET
                  `active` = :active,
                  `name`= :name,
                  `quota` = :quota_b,
                  `attributes` = JSON_SET(`attributes`, '$.force_pw_update', :force_pw_update)
                    WHERE `username` = :username");
              $stmt->execute(array(
                ':active' => $active,
                ':name' => $name,
                ':quota_b' => $quota_b,
                ':force_pw_update' => $force_pw_update,
                ':username' => $username
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
            'msg' => sprintf($lang['success']['mailbox_modified'], implode(', ', $usernames))
          );
        break;
        case 'resource':
          if (!is_array($_data['name'])) {
            $names = array();
            $names[] = $_data['name'];
          }
          else {
            $names = $_data['name'];
          }
          foreach ($names as $name) {
            $is_now = mailbox('get', 'resource_details', $name);
            if (!empty($is_now)) {
              $active             = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active_int'];
              $multiple_bookings  = (isset($_data['multiple_bookings'])) ? intval($_data['multiple_bookings']) : $is_now['multiple_bookings'];
              $description        = (!empty($_data['description'])) ? $_data['description'] : $is_now['description'];
              $kind               = (!empty($_data['kind'])) ? $_data['kind'] : $is_now['kind'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['resource_invalid'])
              );
              return false;
            }
            if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['resource_invalid'])
              );
              return false;
            }
            if (!isset($multiple_bookings) || $multiple_bookings < -1) {
              $multiple_bookings = -1;
            }
            if (empty($description)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['description_invalid'])
              );
              return false;
            }
            if ($kind != 'location' && $kind != 'group' && $kind != 'thing') {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['resource_invalid'])
              );
              return false;
            }
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $name)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("UPDATE `mailbox` SET
                  `active` = :active,
                  `name`= :description,
                  `kind`= :kind,
                  `multiple_bookings`= :multiple_bookings
                    WHERE `username` = :name");
              $stmt->execute(array(
                ':active' => $active,
                ':description' => $description,
                ':multiple_bookings' => $multiple_bookings,
                ':kind' => $kind,
                ':name' => $name
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
            'msg' => sprintf($lang['success']['resource_modified'], implode(', ', $names))
          );
        break;
      }
    break;
    case 'get':
      switch ($_type) {
        case 'sender_acl_handles':
          if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
            return false;
          }
          $data['sender_acl_domains']['ro']               = array();
          $data['sender_acl_domains']['rw']               = array();
          $data['sender_acl_domains']['selectable']       = array();
          $data['sender_acl_addresses']['ro']             = array();
          $data['sender_acl_addresses']['rw']             = array();
          $data['sender_acl_addresses']['selectable']     = array();
          $data['fixed_sender_aliases']                   = array();
          try {
            // Fixed addresses
            $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `goto` REGEXP :goto AND `address` NOT LIKE '@%'");
            $stmt->execute(array(':goto' => '(^|,)'.$_data.'($|,)'));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while ($row = array_shift($rows)) {
              $data['fixed_sender_aliases'][] = $row['address'];
            }
            $stmt = $pdo->prepare("SELECT CONCAT(`local_part`, '@', `alias_domain`.`alias_domain`) AS `alias_domain_alias` FROM `mailbox`, `alias_domain`
              WHERE `alias_domain`.`target_domain` = `mailbox`.`domain`
              AND `mailbox`.`username` = :username");
            $stmt->execute(array(':username' => $_data));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while ($row = array_shift($rows)) {
              if (!empty($row['alias_domain_alias'])) {
                $data['fixed_sender_aliases'][] = $row['alias_domain_alias'];
              }
            }
            // Return array $data['sender_acl_domains/addresses']['ro'] with read-only objects
            // Return array $data['sender_acl_domains/addresses']['rw'] with read-write objects (can be deleted)
            $stmt = $pdo->prepare("SELECT REPLACE(`send_as`, '@', '') AS `send_as` FROM `sender_acl` WHERE `logged_in_as` = :logged_in_as AND `send_as` LIKE '@%'");
            $stmt->execute(array(':logged_in_as' => $_data));
            $domain_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while ($domain_row = array_shift($domain_rows)) {
              if (is_valid_domain_name($domain_row['send_as']) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain_row['send_as'])) {
                $data['sender_acl_domains']['ro'][] = $domain_row['send_as'];
                continue;
              }
              if (is_valid_domain_name($domain_row['send_as']) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain_row['send_as'])) {
                $data['sender_acl_domains']['rw'][] = $domain_row['send_as'];
                continue;
              }
            }
            $stmt = $pdo->prepare("SELECT `send_as` FROM `sender_acl` WHERE `logged_in_as` = :logged_in_as AND `send_as` NOT LIKE '@%'");
            $stmt->execute(array(':logged_in_as' => $_data));
            $address_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while ($address_row = array_shift($address_rows)) {
              if (filter_var($address_row['send_as'], FILTER_VALIDATE_EMAIL) && !hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $address_row['send_as'])) {
                $data['sender_acl_addresses']['ro'][] = $address_row['send_as'];
                continue;
              }
              if (filter_var($address_row['send_as'], FILTER_VALIDATE_EMAIL) && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $address_row['send_as'])) {
                $data['sender_acl_addresses']['rw'][] = $address_row['send_as'];
                continue;
              }
            }
            $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
              WHERE `domain` NOT IN (
                SELECT REPLACE(`send_as`, '@', '') FROM `sender_acl` 
                  WHERE `logged_in_as` = :logged_in_as
                    AND `send_as` LIKE '@%')");
            $stmt->execute(array(
              ':logged_in_as' => $_data,
            ));
            $rows_domain = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while ($row_domain = array_shift($rows_domain)) {
              if (is_valid_domain_name($row_domain['domain']) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row_domain['domain'])) {
                $data['sender_acl_domains']['selectable'][] = $row_domain['domain'];
              }
            }
            $stmt = $pdo->prepare("SELECT `address` FROM `alias`
              WHERE `goto` != :goto
                AND `address` NOT IN (
                  SELECT `send_as` FROM `sender_acl` 
                    WHERE `logged_in_as` = :logged_in_as
                      AND `send_as` NOT LIKE '@%')");
            $stmt->execute(array(
              ':logged_in_as' => $_data,
              ':goto' => $_data
            ));
            $rows_mbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while ($row = array_shift($rows_mbox)) {
              if (filter_var($row['address'], FILTER_VALIDATE_EMAIL) && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['address'])) {
                $data['sender_acl_addresses']['selectable'][] = $row['address'];
              }
            }
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          return $data;
        break;
        case 'mailboxes':
          $mailboxes = array();
          if (isset($_data) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          elseif (isset($_data) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            try {
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` NOT REGEXP 'location|thing|group' AND `domain` != 'ALL' AND `domain` = :domain");
              $stmt->execute(array(
                ':domain' => $_data,
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $mailboxes[] = $row['username'];
              }
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
            try {
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` NOT REGEXP 'location|thing|group' AND (`domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role)");
              $stmt->execute(array(
                ':username' => $_SESSION['mailcow_cc_username'],
                ':role' => $_SESSION['mailcow_cc_role'],
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $mailboxes[] = $row['username'];
              }
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          return $mailboxes;
        break;
        case 'tls_policy':
          $attrs = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          try {
            $stmt = $pdo->prepare("SELECT `attributes` FROM `mailbox` WHERE `username` = :username");
            $stmt->execute(array(':username' => $_data));
            $attrs = $stmt->fetch(PDO::FETCH_ASSOC);
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          $attrs = json_decode($attrs['attributes'], true);
          return array(
            'tls_enforce_in' => $attrs['tls_enforce_in'],
            'tls_enforce_out' => $attrs['tls_enforce_out']
          );
        break;
        case 'filters':
          $filters = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          try {
            $stmt = $pdo->prepare("SELECT `id` FROM `sieve_filters` WHERE `username` = :username");
            $stmt->execute(array(':username' => $_data));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $filters[] = $row['id'];
            }
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
          }
          return $filters;
        break;
        case 'filter_details':
          $filter_details = array();
          if (!is_numeric($_data)) {
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT CASE `script_name` WHEN 'active' THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
              CASE `script_name` WHEN 'active' THEN 1 ELSE 0 END AS `active_int`,
              id,
              username,
              filter_type,
              script_data,
              script_desc
              FROM `sieve_filters`
                WHERE `id` = :id");
            $stmt->execute(array(':id' => $_data));
            $filter_details = $stmt->fetch(PDO::FETCH_ASSOC);
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $filter_details['username'])) {
            return false;
          }
          return $filter_details;
        break;
        case 'active_user_sieve':
          $filter_details = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          $exec_fields = array(
            'cmd' => 'sieve_list',
            'username' => $_data
          );
          $filters = json_decode(docker('dovecot-mailcow', 'post', 'exec', $exec_fields), true);
          $filters = array_filter(explode(PHP_EOL, $filters));
          foreach ($filters as $filter) {
            if (preg_match('/.+ ACTIVE/i', $filter)) {
              $exec_fields = array(
                'cmd' => 'sieve_print',
                'script_name' => substr($filter, 0, -7),
                'username' => $_data
              );
              $filters = json_decode(docker('dovecot-mailcow', 'post', 'exec', $exec_fields), true);
              return preg_replace('/^.+\n/', '', $filters);
            }
          }
          return false;
        break;
        case 'syncjob_details':
          $syncjobdetails = array();
          if (!is_numeric($_data)) {
            return false;
          }
          try {
            if (isset($attr) && in_array('no_log', $attr)) {
              $field_query = $pdo->query('SHOW FIELDS FROM `imapsync` WHERE FIELD NOT IN ("returned_text", "password1")');
              $fields = $field_query->fetchAll(PDO::FETCH_ASSOC);
              while($field = array_shift($fields)) {
                $shown_fields[] = $field['Field'];
              }
              $stmt = $pdo->prepare("SELECT " . implode(',', $shown_fields) . ",
                `active` AS `active_int`,
                CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
                  FROM `imapsync` WHERE id = :id");
            }
            elseif (isset($attr) && in_array('with_password', $attr)) {
              $stmt = $pdo->prepare("SELECT *,
                `active` AS `active_int`,
                CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
                  FROM `imapsync` WHERE id = :id");
            }
            else {
              $field_query = $pdo->query('SHOW FIELDS FROM `imapsync` WHERE FIELD NOT IN ("password1")');
              $fields = $field_query->fetchAll(PDO::FETCH_ASSOC);
              while($field = array_shift($fields)) {
                $shown_fields[] = $field['Field'];
              }
              $stmt = $pdo->prepare("SELECT " . implode(',', $shown_fields) . ",
                `active` AS `active_int`,
                CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
                  FROM `imapsync` WHERE id = :id");
            }
            $stmt->execute(array(':id' => $_data));
            $syncjobdetails = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($syncjobdetails['returned_text'])) {
              $syncjobdetails['log'] = $syncjobdetails['returned_text'];
            }
            else {
              $syncjobdetails['log'] = '';
            }
            unset($syncjobdetails['returned_text']);
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
          }
          if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $syncjobdetails['user2'])) {
            return false;
          }
          return $syncjobdetails;
        break;
        case 'syncjobs':
          $syncjobdata = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          try {
            $stmt = $pdo->prepare("SELECT `id` FROM `imapsync` WHERE `user2` = :username");
            $stmt->execute(array(':username' => $_data));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $syncjobdata[] = $row['id'];
            }
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
          }
          return $syncjobdata;
        break;
        case 'spam_score':
          $default = "5, 15";
          $policydata = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          try {
            $stmt = $pdo->prepare("SELECT `value` FROM `filterconf` WHERE `object` = :username AND
              (`option` = 'lowspamlevel' OR `option` = 'highspamlevel')");
            $stmt->execute(array(':username' => $_data));
            $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          if (empty($num_results)) {
            return $default;
          }
          else {
            try {
              $stmt = $pdo->prepare("SELECT `value` FROM `filterconf` WHERE `option` = 'highspamlevel' AND `object` = :username");
              $stmt->execute(array(':username' => $_data));
              $highspamlevel = $stmt->fetch(PDO::FETCH_ASSOC);

              $stmt = $pdo->prepare("SELECT `value` FROM `filterconf` WHERE `option` = 'lowspamlevel' AND `object` = :username");
              $stmt->execute(array(':username' => $_data));
              $lowspamlevel = $stmt->fetch(PDO::FETCH_ASSOC);

              return $lowspamlevel['value'].', '.$highspamlevel['value'];
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
        break;
        case 'time_limited_aliases':
          $tladata = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          try {
            $stmt = $pdo->prepare("SELECT `address`,
              `goto`,
              `validity`
                FROM `spamalias`
                  WHERE `goto` = :username
                    AND `validity` >= :unixnow");
            $stmt->execute(array(':username' => $_data, ':unixnow' => time()));
            $tladata = $stmt->fetchAll(PDO::FETCH_ASSOC);
          }
          catch(PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
          }
          return $tladata;
        break;
        case 'delimiter_action':
          $policydata = array();
          if (isset($_data) && filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              return false;
            }
          }
          else {
            $_data = $_SESSION['mailcow_cc_username'];
          }
          try {
            if ($redis->hGet('RCPT_WANTS_SUBJECT_TAG', $_data)) {
              return "subject";
            }
            elseif ($redis->hGet('RCPT_WANTS_SUBFOLDER_TAG', $_data)) {
              return "subfolder";
            }
            else {
              return "none";
            }
          }
          catch (RedisException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Redis: '.$e
            );
            return false;
          }
        break;
        case 'resources':
          $resources = array();
          if (isset($_data) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          elseif (isset($_data) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            try {
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` REGEXP 'location|thing|group' AND `domain` != 'ALL' AND `domain` = :domain");
              $stmt->execute(array(
                ':domain' => $_data,
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $resources[] = $row['username'];
              }
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
            try {
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `kind` REGEXP 'location|thing|group' AND `domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role");
              $stmt->execute(array(
                ':username' => $_SESSION['mailcow_cc_username'],
                ':role' => $_SESSION['mailcow_cc_role'],
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $resources[] = $row['username'];
              }
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          return $resources;
        break;
        case 'alias_domains':
          $aliasdomains = array();
          if (isset($_data) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          elseif (isset($_data) && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            try {
              $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :domain");
              $stmt->execute(array(
                ':domain' => $_data,
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $aliasdomains[] = $row['alias_domain'];
              }
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
            try {
              $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :username) OR 'admin' = :role");
              $stmt->execute(array(
                ':username' => $_SESSION['mailcow_cc_username'],
                ':role' => $_SESSION['mailcow_cc_role'],
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $aliasdomains[] = $row['alias_domain'];
              }
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
          }
          return $aliasdomains;
        break;
        case 'aliases':
          $aliases = array();
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT `address` FROM `alias` WHERE `address` != `goto` AND `domain` = :domain");
            $stmt->execute(array(
              ':domain' => $_data,
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $aliases[] = $row['address'];
            }
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          return $aliases;
        break;
        case 'ratelimit':
          if (is_valid_domain_name($_data)) {
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
          }
          elseif (filter_var($_data, FILTER_VALIDATE_EMAIL)) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
          }
          else {
            return false;
          }
          try {
            if ($rl_value = $redis->hGet('RL_VALUE', $_data)) {
              $rl = explode(' / 1', $rl_value);
              $data['value'] = $rl[0];
              $data['frame'] = $rl[1];
              return $data;
            }
            else {
              return false;
            }
          }
          catch (RedisException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Redis: '.$e
            );
            return false;
          }
          return false;
        break;
        case 'alias_details':
          $aliasdata = array();
          try {
            $stmt = $pdo->prepare("SELECT
              `domain`,
              `goto`,
              `address`,
              `active` as `active_int`,
              CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
              `created`,
              `modified`
                FROM `alias`
                    WHERE `address` = :address AND `address` != `goto`");
            $stmt->execute(array(
              ':address' => $_data,
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain");
            $stmt->execute(array(
              ':domain' => $row['domain'],
            ));
            $row_alias_domain = $stmt->fetch(PDO::FETCH_ASSOC);
            if (isset($row_alias_domain['target_domain']) && !empty($row_alias_domain['target_domain'])) {
              $aliasdata['in_primary_domain'] = $row_alias_domain['target_domain'];
            }
            else {
              $aliasdata['in_primary_domain'] = "";
            }
            $aliasdata['domain'] = $row['domain'];
            $aliasdata['goto'] = $row['goto'];
            $aliasdata['address'] = $row['address'];
            (!filter_var($aliasdata['address'], FILTER_VALIDATE_EMAIL)) ? $aliasdata['is_catch_all'] = 1 : $aliasdata['is_catch_all'] = 0;
            $aliasdata['active'] = $row['active'];
            $aliasdata['active_int'] = $row['active_int'];
            $aliasdata['created'] = $row['created'];
            $aliasdata['modified'] = $row['modified'];
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $aliasdata['domain'])) {
              return false;
            }
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          return $aliasdata;
        break;
        case 'alias_domain_details':
          $aliasdomaindata = array();
          try {
            $stmt = $pdo->prepare("SELECT
              `alias_domain`,
              `target_domain`,
              `active` AS `active_int`,
              CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
              `created`,
              `modified`
                FROM `alias_domain`
                    WHERE `alias_domain` = :aliasdomain");
            $stmt->execute(array(
              ':aliasdomain' => $_data,
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $aliasdomaindata['alias_domain'] = $row['alias_domain'];
            $aliasdomaindata['target_domain'] = $row['target_domain'];
            $aliasdomaindata['active'] = $row['active'];
            $aliasdomaindata['active_int'] = $row['active_int'];
            $aliasdomaindata['created'] = $row['created'];
            $aliasdomaindata['modified'] = $row['modified'];
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $aliasdomaindata['target_domain'])) {
            return false;
          }
          return $aliasdomaindata;
        break;
        case 'domains':
          $domains = array();
          if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
              WHERE (`domain` IN (
                SELECT `domain` from `domain_admins`
                  WHERE (`active`='1' AND `username` = :username))
                )
                OR ('admin'= :role)
                AND `domain` != 'ALL'");
            $stmt->execute(array(
              ':username' => $_SESSION['mailcow_cc_username'],
              ':role' => $_SESSION['mailcow_cc_role'],
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $domains[] = $row['domain'];
            }
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          return $domains;
        break;
        case 'domain_details':
          $domaindata = array();
          $_data = idn_to_ascii(strtolower(trim($_data)));
          if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` =  :domain");
            $stmt->execute(array(
              ':domain' => $_data
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row)) {
              $_data = $row['target_domain'];
            }
            $stmt = $pdo->prepare("SELECT 
                `domain`,
                `description`,
                `aliases`,
                `mailboxes`, 
                `maxquota`,
                `quota`,
                `relayhost`,
                `relay_all_recipients` as `relay_all_recipients_int`,
                `backupmx` as `backupmx_int`,
                `active` as `active_int`,
                CASE `relay_all_recipients` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `relay_all_recipients`,
                CASE `backupmx` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `backupmx`,
                CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
                  FROM `domain` WHERE `domain`= :domain");
            $stmt->execute(array(
              ':domain' => $_data
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (empty($row)) { 
              return false;
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) AS `count`,
              COALESCE(SUM(`quota`), 0) AS `in_use`
                FROM `mailbox`
                  WHERE `kind` NOT REGEXP 'location|thing|group'
                    AND `domain` = :domain");
            $stmt->execute(array(':domain' => $row['domain']));
            $MailboxDataDomain	= $stmt->fetch(PDO::FETCH_ASSOC);
            $domaindata['max_new_mailbox_quota']	= ($row['quota'] * 1048576) - $MailboxDataDomain['in_use'];
            if ($domaindata['max_new_mailbox_quota'] > ($row['maxquota'] * 1048576)) {
              $domaindata['max_new_mailbox_quota'] = ($row['maxquota'] * 1048576);
            }
            $domaindata['quota_used_in_domain'] = $MailboxDataDomain['in_use'];
            $domaindata['mboxes_in_domain'] = $MailboxDataDomain['count'];
            $domaindata['mboxes_left'] = $row['mailboxes']	- $MailboxDataDomain['count'];
            $domaindata['domain_name'] = $row['domain'];
            $domaindata['description'] = $row['description'];
            $domaindata['max_num_aliases_for_domain'] = $row['aliases'];
            $domaindata['max_num_mboxes_for_domain'] = $row['mailboxes'];
            $domaindata['max_quota_for_mbox'] = $row['maxquota'] * 1048576;
            $domaindata['max_quota_for_domain'] = $row['quota'] * 1048576;
            $domaindata['relayhost'] = $row['relayhost'];
            $domaindata['backupmx'] = $row['backupmx'];
            $domaindata['backupmx_int'] = $row['backupmx_int'];
            $domaindata['active'] = $row['active'];
            $domaindata['active_int'] = $row['active_int'];
            $domaindata['relay_all_recipients'] = $row['relay_all_recipients'];
            $domaindata['relay_all_recipients_int'] = $row['relay_all_recipients_int'];
            $stmt = $pdo->prepare("SELECT COUNT(*) AS `alias_count` FROM `alias`
              WHERE (`domain`= :domain OR `domain` IN (SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :domain2))
                AND `address` NOT IN (
                  SELECT `username` FROM `mailbox`
                )");
            $stmt->execute(array(
              ':domain' => $_data,
              ':domain2' => $_data
            ));
            $AliasDataDomain = $stmt->fetch(PDO::FETCH_ASSOC);
            (isset($AliasDataDomain['alias_count'])) ? $domaindata['aliases_in_domain'] = $AliasDataDomain['alias_count'] : $domaindata['aliases_in_domain'] = "0";
            $domaindata['aliases_left'] = $row['aliases']	- $AliasDataDomain['alias_count'];
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          return $domaindata;
        break;
        case 'mailbox_details':
          if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          $mailboxdata = array();
          try {
            $stmt = $pdo->prepare("SELECT
                `domain`.`backupmx`,
                `mailbox`.`username`,
                `mailbox`.`name`,
                `mailbox`.`active` AS `active_int`,
                CASE `mailbox`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
                `mailbox`.`domain`,
                `mailbox`.`quota`,
                `quota2`.`bytes`,
                `attributes`,
                `quota2`.`messages`
                  FROM `mailbox`, `quota2`, `domain`
                    WHERE `mailbox`.`kind` NOT REGEXP 'location|thing|group' AND `mailbox`.`username` = `quota2`.`username` AND `domain`.`domain` = `mailbox`.`domain` AND `mailbox`.`username` = :mailbox");
            $stmt->execute(array(
              ':mailbox' => $_data,
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT `maxquota`, `quota` FROM  `domain` WHERE `domain` = :domain");
            $stmt->execute(array(':domain' => $row['domain']));
            $DomainQuota  = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(`quota`), 0) as `in_use` FROM `mailbox` WHERE `kind` NOT REGEXP 'location|thing|group' AND `domain` = :domain AND `username` != :username");
            $stmt->execute(array(':domain' => $row['domain'], ':username' => $_data));
            $MailboxUsage	= $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT IFNULL(COUNT(`address`), 0) AS `sa_count` FROM `spamalias` WHERE `goto` = :address AND `validity` >= :unixnow");
            $stmt->execute(array(':address' => $_data, ':unixnow' => time()));
            $SpamaliasUsage	= $stmt->fetch(PDO::FETCH_ASSOC);
            $mailboxdata['max_new_quota'] = ($DomainQuota['quota'] * 1048576) - $MailboxUsage['in_use'];
            if ($mailboxdata['max_new_quota'] > ($DomainQuota['maxquota'] * 1048576)) {
              $mailboxdata['max_new_quota'] = ($DomainQuota['maxquota'] * 1048576);
            }
            $mailboxdata['username'] = $row['username'];
            $mailboxdata['is_relayed'] = $row['backupmx'];
            $mailboxdata['name'] = $row['name'];
            $mailboxdata['active'] = $row['active'];
            $mailboxdata['active_int'] = $row['active_int'];
            $mailboxdata['domain'] = $row['domain'];
            $mailboxdata['quota'] = $row['quota'];
            $mailboxdata['attributes'] = json_decode($row['attributes'], true);
            $mailboxdata['quota_used'] = intval($row['bytes']);
            $mailboxdata['percent_in_use'] = round((intval($row['bytes']) / intval($row['quota'])) * 100);
            $mailboxdata['messages'] = $row['messages'];
            $mailboxdata['spam_aliases'] = $SpamaliasUsage['sa_count'];
            if ($mailboxdata['percent_in_use'] >= 90) {
              $mailboxdata['percent_class'] = "danger";
            }
            elseif ($mailboxdata['percent_in_use'] >= 75) {
              $mailboxdata['percent_class'] = "warning";
            }
            else {
              $mailboxdata['percent_class'] = "success";
            }
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          return $mailboxdata;
        break;
        case 'resource_details':
          $resourcedata = array();
          if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data)) {
            return false;
          }
          try {
            $stmt = $pdo->prepare("SELECT
                `username`,
                `name`,
                `kind`,
                `multiple_bookings`,
                `local_part`,
                `active` AS `active_int`,
                CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
                `domain`
                  FROM `mailbox` WHERE `kind` REGEXP 'location|thing|group' AND `username` = :resource");
            $stmt->execute(array(
              ':resource' => $_data,
            ));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $resourcedata['name'] = $row['username'];
            $resourcedata['kind'] = $row['kind'];
            $resourcedata['multiple_bookings'] = $row['multiple_bookings'];
            $resourcedata['description'] = $row['name'];
            $resourcedata['active'] = $row['active'];
            $resourcedata['active_int'] = $row['active_int'];
            $resourcedata['domain'] = $row['domain'];
            $resourcedata['local_part'] = $row['local_part'];
          }
          catch (PDOException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'MySQL: '.$e
            );
            return false;
          }
          if (!isset($resourcedata['domain']) ||
            (isset($resourcedata['domain']) && !hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $resourcedata['domain']))) {
            return false;
          }
          return $resourcedata;
        break;
      }
    break;
    case 'delete':
      switch ($_type) {
        case 'syncjob':
          if (!is_array($_data['id'])) {
            $ids = array();
            $ids[] = $_data['id'];
          }
          else {
            $ids = $_data['id'];
          }
          if (!isset($_SESSION['acl']['syncjobs']) || $_SESSION['acl']['syncjobs'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($ids as $id) {
            if (!is_numeric($id)) {
              return false;
            }
            try {
              $stmt = $pdo->prepare("SELECT `user2` FROM `imapsync` WHERE id = :id");
              $stmt->execute(array(':id' => $id));
              $user2 = $stmt->fetch(PDO::FETCH_ASSOC)['user2'];
              if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $user2)) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['access_denied'])
                );
                return false;
              }
              $stmt = $pdo->prepare("DELETE FROM `imapsync` WHERE `id`= :id");
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
            'msg' => 'Deleted syncjob id/s ' . implode(', ', $ids)
          );
          return true;
        break;
        case 'filter':
          if (!is_array($_data['id'])) {
            $ids = array();
            $ids[] = $_data['id'];
          }
          else {
            $ids = $_data['id'];
          }
          if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($ids as $id) {
            if (!is_numeric($id)) {
              return false;
            }
            try {
              $stmt = $pdo->prepare("SELECT `username` FROM `sieve_filters` WHERE id = :id");
              $stmt->execute(array(':id' => $id));
              $usr = $stmt->fetch(PDO::FETCH_ASSOC)['username'];
              if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $usr)) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => sprintf($lang['danger']['access_denied'])
                );
                return false;
              }
              $stmt = $pdo->prepare("DELETE FROM `sieve_filters` WHERE `id`= :id");
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
            'msg' => 'Deleted filter id/s ' . implode(', ', $ids)
          );
          return true;
        break;
        case 'time_limited_alias':
          if (!is_array($_data['address'])) {
            $addresses = array();
            $addresses[] = $_data['address'];
          }
          else {
            $addresses = $_data['address'];
          }
          if (!isset($_SESSION['acl']['spam_alias']) || $_SESSION['acl']['spam_alias'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($addresses as $address) {
            try {
              $stmt = $pdo->prepare("SELECT `goto` FROM `spamalias` WHERE `address` = :address");
              $stmt->execute(array(':address' => $address));
              $goto = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $goto)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username AND `address` = :item");
              $stmt->execute(array(
                ':username' => $goto,
                ':item' => $address
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
            'msg' => sprintf($lang['success']['mailbox_modified'], htmlspecialchars($usernames))
          );
        break;
        case 'eas_cache':
          if (!is_array($_data['username'])) {
            $usernames = array();
            $usernames[] = $_data['username'];
          }
          else {
            $usernames = $_data['username'];
          }
          if (!isset($_SESSION['acl']['eas_reset']) || $_SESSION['acl']['eas_reset'] != "1" ) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($usernames as $username) {
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `sogo_cache_folder` WHERE `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $username
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
            'msg' => sprintf($lang['success']['eas_reset'], htmlspecialchars(implode(', ', $usernames)))
          );
        break;
        case 'domain':
          if (!is_array($_data['domain'])) {
            $domains = array();
            $domains[] = $_data['domain'];
          }
          else {
            $domains = $_data['domain'];
          }
          if ($_SESSION['mailcow_cc_role'] != "admin") {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          foreach ($domains as $domain) {
            if (!is_valid_domain_name($domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['domain_invalid'])
              );
              return false;
            }
            $domain	= idn_to_ascii(strtolower(trim($domain)));
            try {
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox`
                WHERE `domain` = :domain");
              $stmt->execute(array(':domain' => $domain));
              $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            if ($num_results != 0 || !empty($num_results)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['domain_not_empty'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `domain` WHERE `domain` = :domain");
              $stmt->execute(array(
                ':domain' => $domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `domain_admins` WHERE `domain` = :domain");
              $stmt->execute(array(
                ':domain' => $domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `domain` = :domain");
              $stmt->execute(array(
                ':domain' => $domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `alias_domain` WHERE `target_domain` = :domain");
              $stmt->execute(array(
                ':domain' => $domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `domain` = :domain");
              $stmt->execute(array(
                ':domain' => $domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` LIKE :domain");
              $stmt->execute(array(
                ':domain' => '%@'.$domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `quota2` WHERE `username` = :domain");
              $stmt->execute(array(
                ':domain' => '%@'.$domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `address` = :domain");
              $stmt->execute(array(
                ':domain' => '%@'.$domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :domain");
              $stmt->execute(array(
                ':domain' => '%@'.$domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `bcc_maps` WHERE `local_dest` = :domain");
              $stmt->execute(array(
                ':domain' => $domain,
              ));
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            try {
              $redis->hDel('DOMAIN_MAP', $domain);
            }
            catch (RedisException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Redis: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['domain_removed'], htmlspecialchars(implode(', ', $domains)))
          );
          return true;
        break;
        case 'alias':
          if (!is_array($_data['address'])) {
            $addresses = array();
            $addresses[] = $_data['address'];
          }
          else {
            $addresses = $_data['address'];
          }
          foreach ($addresses as $address) {
            $local_part		= strstr($address, '@', true);
            $domain = mailbox('get', 'alias_details', $address)['domain'];
            try {
              $stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :address");
              $stmt->execute(array(':address' => $address));
              $gotos = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            $goto_array = explode(',', $gotos['goto']);
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `address` = :address AND `address` NOT IN (SELECT `username` FROM `mailbox`)");
              $stmt->execute(array(
                ':address' => $address
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
            'msg' => sprintf($lang['success']['alias_removed'], htmlspecialchars(implode(', ', $addresses)))
          );
        break;
        case 'alias_domain':
          if (!is_array($_data['alias_domain'])) {
            $alias_domains = array();
            $alias_domains[] = $_data['alias_domain'];
          }
          else {
            $alias_domains = $_data['alias_domain'];
          }
          foreach ($alias_domains as $alias_domain) {
            if (!is_valid_domain_name($alias_domain)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['domain_invalid'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain`
                WHERE `alias_domain`= :alias_domain");
              $stmt->execute(array(':alias_domain' => $alias_domain));
              $DomainData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $DomainData['target_domain'])) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `alias_domain` WHERE `alias_domain` = :alias_domain");
              $stmt->execute(array(
                ':alias_domain' => $alias_domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `domain` = :alias_domain");
              $stmt->execute(array(
                ':alias_domain' => $alias_domain,
              ));
              $stmt = $pdo->prepare("DELETE FROM `bcc_maps` WHERE `local_dest` = :alias_domain");
              $stmt->execute(array(
                ':alias_domain' => $alias_domain,
              ));
            }
            catch (PDOException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'MySQL: '.$e
              );
              return false;
            }
            try {
              $redis->hDel('DOMAIN_MAP', $alias_domain);
            }
            catch (RedisException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Redis: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['alias_domain_removed'], htmlspecialchars(implode(', ', $alias_domains)))
          );
        break;
        case 'mailbox':
          if (!is_array($_data['username'])) {
            $usernames = array();
            $usernames[] = $_data['username'];
          }
          else {
            $usernames = $_data['username'];
          }
          foreach ($usernames as $username) {
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `alias` WHERE `goto` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `quota2` WHERE `username` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `username` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sender_acl` WHERE `logged_in_as` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `spamalias` WHERE `goto` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `imapsync` WHERE `user2` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `filterconf` WHERE `object` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_user_profile` WHERE `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_cache_folder` WHERE `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_acl` WHERE `c_object` LIKE '%/" . $username . "/%' OR `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_store` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_quick_contact` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_quick_appointment` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_folder_info` WHERE `c_path2` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("DELETE FROM `bcc_maps` WHERE `local_dest` = :username");
              $stmt->execute(array(
                ':username' => $username
              ));
              $stmt = $pdo->prepare("SELECT `address`, `goto` FROM `alias`
                  WHERE `goto` REGEXP :username");
              $stmt->execute(array(':username' => '(^|,)'.$username.'($|,)'));
              $GotoData = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($GotoData as $gotos) {
                $goto_exploded = explode(',', $gotos['goto']);
                if (($key = array_search($username, $goto_exploded)) !== false) {
                  unset($goto_exploded[$key]);
                }
                $gotos_rebuild = implode(',', $goto_exploded);
                $stmt = $pdo->prepare("UPDATE `alias` SET
                  `goto` = :goto
                    WHERE `address` = :address");
                $stmt->execute(array(
                  ':goto' => $gotos_rebuild,
                  ':address' => $gotos['address']
                ));
              }
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
            'msg' => sprintf($lang['success']['mailbox_removed'], htmlspecialchars(implode(', ', $usernames)))
          );
        break;
        case 'resource':
          if (!is_array($_data['name'])) {
            $names = array();
            $names[] = $_data['name'];
          }
          else {
            $names = $_data['name'];
          }
          foreach ($names as $name) {
            if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $name)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            try {
              $stmt = $pdo->prepare("DELETE FROM `mailbox` WHERE `username` = :username");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_user_profile` WHERE `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_cache_folder` WHERE `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_acl` WHERE `c_object` LIKE '%/" . $name . "/%' OR `c_uid` = :username");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_store` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_quick_contact` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_quick_appointment` WHERE `c_folder_id` IN (SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path2` = :username)");
              $stmt->execute(array(
                ':username' => $name
              ));
              $stmt = $pdo->prepare("DELETE FROM `sogo_folder_info` WHERE `c_path2` = :username");
              $stmt->execute(array(
                ':username' => $name
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
            'msg' => sprintf($lang['success']['resource_removed'], htmlspecialchars(implode(', ', $names)))
          );
        break;
      }
    break;
  }
}
