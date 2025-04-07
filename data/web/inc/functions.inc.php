<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
function is_valid_regex($exp) {
  return @preg_match($exp, '') !== false;
}
function isset_has_content($var) {
  if (isset($var) && $var != "") {
    return true;
  }
  else {
    return false;
  }
}
function readable_random_string($length = 8) {
  $string = '';
  $vowels = array('a', 'e', 'i', 'o', 'u');
  $consonants = array('b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z');
  $max = $length / 2;
  for ($i = 1; $i <= $max; $i++) {
    $string .= $consonants[rand(0,19)];
    $string .= $vowels[rand(0,4)];
  }
  return $string;
}
// Validates ips and cidrs
function valid_network($network) {
  if (filter_var($network, FILTER_VALIDATE_IP)) {
    return true;
  }
  $parts = explode('/', $network);
  if (count($parts) != 2) {
    return false;
  }
  $ip = $parts[0];
  $netmask = $parts[1];
  if (!preg_match("/^\d+$/", $netmask)){
    return false;
  }
  $netmask = intval($parts[1]);
  if ($netmask < 0) {
    return false;
  }
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return $netmask <= 32;
  }
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    return $netmask <= 128;
  }
  return false;
}
function valid_hostname($hostname) {
  return filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
}
// Thanks to https://stackoverflow.com/a/49373789
// Validates exact ip matches and ip-in-cidr, ipv4 and ipv6
function ip_acl($ip, $networks) {
  foreach($networks as $network) {
    if (filter_var($network, FILTER_VALIDATE_IP)) {
      if ($ip == $network) {
        return true;
      }
      else {
        continue;
      }
    }
    $ipb = inet_pton($ip);
    $iplen = strlen($ipb);
    if (strlen($ipb) < 4) {
      continue;
    }
    $ar = explode('/', $network);
    $ip1 = $ar[0];
    $ip1b = inet_pton($ip1);
    $ip1len = strlen($ip1b);
    if ($ip1len != $iplen) {
      continue;
    }
    if (count($ar)>1) {
      $bits=(int)($ar[1]);
    }
    else {
      $bits = $iplen * 8;
    }
    for ($c=0; $bits>0; $c++) {
      $bytemask = ($bits < 8) ? 0xff ^ ((1 << (8-$bits))-1) : 0xff;
      if (((ord($ipb[$c]) ^ ord($ip1b[$c])) & $bytemask) != 0) {
        continue 2;
      }
      $bits-=8;
    }
    return true;
  }
  return false;
}
function hash_password($password) {
  // default_pass_scheme is determined in vars.inc.php (or corresponding local file)
  // in case default pass scheme is not defined, falling back to BLF-CRYPT.
  global $default_pass_scheme;
  $pw_hash = NULL;
  // support pre-hashed passwords
  if (preg_match('/^{(ARGON2I|ARGON2ID|BLF-CRYPT|CLEAR|CLEARTEXT|CRYPT|DES-CRYPT|LDAP-MD5|MD5|MD5-CRYPT|PBKDF2|PLAIN|PLAIN-MD4|PLAIN-MD5|PLAIN-TRUNC|PLAIN-TRUNC|SHA|SHA1|SHA256|SHA256-CRYPT|SHA512|SHA512-CRYPT|SMD5|SSHA|SSHA256|SSHA512)}/i', $password)) {
    $pw_hash = $password;
  }
  else {
    switch (strtoupper($default_pass_scheme)) {
      case "SSHA":
        $salt_str = bin2hex(openssl_random_pseudo_bytes(8));
        $pw_hash = "{SSHA}".base64_encode(hash('sha1', $password . $salt_str, true) . $salt_str);
        break;
      case "SSHA256":
        $salt_str = bin2hex(openssl_random_pseudo_bytes(8));
        $pw_hash = "{SSHA256}".base64_encode(hash('sha256', $password . $salt_str, true) . $salt_str);
        break;
      case "SSHA512":
        $salt_str = bin2hex(openssl_random_pseudo_bytes(8));
        $pw_hash = "{SSHA512}".base64_encode(hash('sha512', $password . $salt_str, true) . $salt_str);
        break;
      case "BLF-CRYPT":
      default:
        $pw_hash = "{BLF-CRYPT}" . password_hash($password, PASSWORD_BCRYPT);
        break;
    }
  }
  return $pw_hash;
}
function password_complexity($_action, $_data = null) {
  global $redis;
  global $lang;
  switch ($_action) {
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      $is_now = password_complexity('get');
      if (!empty($is_now)) {
        $length = (isset($_data['length']) && intval($_data['length']) >= 3) ? intval($_data['length']) : $is_now['length'];
        $chars = (isset($_data['chars'])) ? intval($_data['chars']) : $is_now['chars'];
        $lowerupper = (isset($_data['lowerupper'])) ? intval($_data['lowerupper']) : $is_now['lowerupper'];
        $special_chars = (isset($_data['special_chars'])) ? intval($_data['special_chars']) : $is_now['special_chars'];
        $numbers = (isset($_data['numbers'])) ? intval($_data['numbers']) : $is_now['numbers'];
      }
      try {
        $redis->hMSet('PASSWD_POLICY', [
          'length' => $length,
          'chars' => $chars,
          'special_chars' => $special_chars,
          'lowerupper' => $lowerupper,
          'numbers' => $numbers
        ]);
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => array('redis_error', $e)
        );
        return false;
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data),
        'msg' => 'password_policy_saved'
      );
    break;
    case 'get':
      try {
        $length = $redis->hGet('PASSWD_POLICY', 'length');
        $chars = $redis->hGet('PASSWD_POLICY', 'chars');
        $special_chars = $redis->hGet('PASSWD_POLICY', 'special_chars');
        $lowerupper = $redis->hGet('PASSWD_POLICY', 'lowerupper');
        $numbers = $redis->hGet('PASSWD_POLICY', 'numbers');
        return array(
          'length' => $length,
          'chars' => $chars,
          'special_chars' => $special_chars,
          'lowerupper' => $lowerupper,
          'numbers' => $numbers
        );
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => array('redis_error', $e)
        );
        return false;
      }
      return false;
    break;
    case 'html':
      $policies = password_complexity('get');
      foreach ($policies as $name => $value) {
        if ($value != 0) {
          $policy_text[] = sprintf($lang['admin']["password_policy_$name"], $value);
        }
      }
      return '<p class="help-block small">- ' . implode('<br>- ', (array)$policy_text) . '</p>';
    break;
  }
}
function password_check($password1, $password2) {
  $password_complexity = password_complexity('get');

  if (empty($password1) || empty($password2)) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_action, $_type),
      'msg' => 'password_complexity'
    );
    return false;
  }

  if ($password1 != $password2) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_action, $_type),
      'msg' => 'password_mismatch'
    );
    return false;
  }

  $given_password['length'] = strlen($password1);
  $given_password['special_chars'] = preg_match('/[^a-zA-Z\d]/', $password1);
  $given_password['chars'] = preg_match('/[a-zA-Z]/',$password1);
  $given_password['numbers'] = preg_match('/\d/', $password1);
  $lower = strlen(preg_replace("/[^a-z]/", '', $password1));
  $upper = strlen(preg_replace("/[^A-Z]/", '', $password1));
  $given_password['lowerupper'] = ($lower > 0 && $upper > 0) ? true : false;

  if (
    ($given_password['length'] < $password_complexity['length']) ||
    ($password_complexity['special_chars'] == 1 && (intval($given_password['special_chars']) != $password_complexity['special_chars'])) ||
    ($password_complexity['chars'] == 1 && (intval($given_password['chars']) != $password_complexity['chars'])) ||
    ($password_complexity['numbers'] == 1 && (intval($given_password['numbers']) != $password_complexity['numbers'])) ||
    ($password_complexity['lowerupper'] == 1 && (intval($given_password['lowerupper']) != $password_complexity['lowerupper']))
  ) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_action, $_type),
      'msg' => 'password_complexity'
    );
    return false;
  }

  return true;
}
function last_login($action, $username, $sasl_limit_days = 7, $ui_offset = 1) {
  global $pdo;
  global $redis;
  $sasl_limit_days = intval($sasl_limit_days);
  switch ($action) {
    case 'get':
      if (filter_var($username, FILTER_VALIDATE_EMAIL) && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
        $stmt = $pdo->prepare('SELECT `real_rip`, MAX(`datetime`) as `datetime`, `service`, `app_password`, MAX(`app_passwd`.`name`) as `app_password_name` FROM `sasl_log`
          LEFT OUTER JOIN `app_passwd` on `sasl_log`.`app_password` = `app_passwd`.`id`
          WHERE `username` = :username
            AND HOUR(TIMEDIFF(NOW(), `datetime`)) < :sasl_limit_days
              GROUP BY `real_rip`, `service`, `app_password`
              ORDER BY `datetime` DESC;');
        $stmt->execute(array(':username' => $username, ':sasl_limit_days' => ($sasl_limit_days * 24)));
        $sasl = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sasl as $k => $v) {
          if (!filter_var($sasl[$k]['real_rip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $sasl[$k]['real_rip'] = 'Web/EAS/Internal (' . $sasl[$k]['real_rip'] . ')';
          }
          elseif (filter_var($sasl[$k]['real_rip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            try {
              $sasl[$k]['location'] = $redis->hGet('IP_SHORTCOUNTRY', $sasl[$k]['real_rip']);
            }
            catch (RedisException $e) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_data_log),
                'msg' => array('redis_error', $e)
              );
              return false;
            }
            if (!$sasl[$k]['location']) {
              $curl = curl_init();
              curl_setopt($curl, CURLOPT_URL,"https://dfdata.bella.network/country/" . $sasl[$k]['real_rip']);
              curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($curl, CURLOPT_USERAGENT, 'Moocow');
              curl_setopt($curl, CURLOPT_TIMEOUT, 5);
              $ip_data = curl_exec($curl);
              if (!curl_errno($curl)) {
                $ip_data_array = json_decode($ip_data, true);
                if ($ip_data_array !== false and !empty($ip_data_array['shortcountry'])) {
                  $sasl[$k]['location'] = $ip_data_array['shortcountry'];
                    try {
                      $redis->hSet('IP_SHORTCOUNTRY', $sasl[$k]['real_rip'], $ip_data_array['shortcountry']);
                    }
                    catch (RedisException $e) {
                      $_SESSION['return'][] = array(
                        'type' => 'danger',
                        'log' => array(__FUNCTION__, $_action, $_data_log),
                        'msg' => array('redis_error', $e)
                      );
                      curl_close($curl);
                      return false;
                    }
                }
              }
              curl_close($curl);
            }
          }
        }
      }
      else {
        $sasl = array();
      }
      if ($_SESSION['mailcow_cc_role'] == "admin" || $username == $_SESSION['mailcow_cc_username']) {
        $stmt = $pdo->prepare('SELECT `remote`, `time` FROM `logs`
          WHERE JSON_EXTRACT(`call`, "$[0]") = "check_login"
            AND JSON_EXTRACT(`call`, "$[1]") = :username
            AND `type` = "success" ORDER BY `time` DESC LIMIT 1 OFFSET :offset');
        $stmt->execute(array(
          ':username' => $username,
          ':offset' => $ui_offset
        ));
        $ui = $stmt->fetch(PDO::FETCH_ASSOC);
      }
      else {
        $ui = array();
      }

      return array('ui' => $ui, 'sasl' => $sasl);
    break;
    case 'reset':
      if (filter_var($username, FILTER_VALIDATE_EMAIL) && hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
        $stmt = $pdo->prepare('DELETE FROM `sasl_log`
          WHERE `username` = :username');
        $stmt->execute(array(':username' => $username));
      }
      if ($_SESSION['mailcow_cc_role'] == "admin" || $username == $_SESSION['mailcow_cc_username']) {
        $stmt = $pdo->prepare('DELETE FROM `logs`
          WHERE JSON_EXTRACT(`call`, "$[0]") = "check_login"
            AND JSON_EXTRACT(`call`, "$[1]") = :username
            AND `type` = "success"');
        $stmt->execute(array(':username' => $username));
      }
      return true;
    break;
  }

}
function set_sasl_log($username, $real_rip, $service){
  global $pdo;

  try {
    if (!empty($_SESSION['app_passwd_id'])) {
      $app_password = $_SESSION['app_passwd_id'];
    } else {
      $app_password = 0;
    }

    $stmt = $pdo->prepare('REPLACE INTO `sasl_log` (`username`, `real_rip`, `service`, `app_password`) VALUES (:username, :real_rip, :service, :app_password)');
    $stmt->execute(array(
      ':username' => $username,
      ':real_rip' => $real_rip,
      ':service' => $service,
      ':app_password' => $app_password
    ));
  } catch (PDOException $e) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_data_log),
      'msg' => array('mysql_error', $e)
    );
    return false;
  }

  return true;
}
function flush_memcached() {
  try {
    $m = new Memcached();
    $m->addServer('memcached', 11211);
    $m->flush();
  }
  catch ( Exception $e ) {
    // Dunno
  }
}
function sys_mail($_data) {
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'access_denied'
    );
    return false;
  }
  $excludes = $_data['mass_exclude'];
  $includes = $_data['mass_include'];
  $mailboxes = array();
  $mass_from = $_data['mass_from'];
  $mass_text = $_data['mass_text'];
  $mass_html = $_data['mass_html'];
  $mass_subject = $_data['mass_subject'];
  if (!filter_var($mass_from, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'from_invalid'
    );
    return false;
  }
  if (empty($mass_subject)) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'subject_empty'
    );
    return false;
  }
  if (empty($mass_text)) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'text_empty'
    );
    return false;
  }
  $domains = array_merge(mailbox('get', 'domains'), mailbox('get', 'alias_domains'));
  foreach ($domains as $domain) {
    foreach (mailbox('get', 'mailboxes', $domain) as $mailbox) {
      $mailboxes[] = $mailbox;
    }
  }
  if (!empty($includes)) {
    $rcpts = array_intersect($mailboxes, $includes);
  }
  elseif (!empty($excludes)) {
    $rcpts = array_diff($mailboxes, $excludes);
  }
  else {
    $rcpts = $mailboxes;
  }
  if (!empty($rcpts)) {
    ini_set('max_execution_time', 0);
    ini_set('max_input_time', 0);
    $mail = new PHPMailer;
    $mail->Timeout = 10;
    $mail->SMTPOptions = array(
      'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
      )
    );
    $mail->isSMTP();
    $mail->Host = 'dovecot-mailcow';
    $mail->SMTPAuth = false;
    $mail->Port = 24;
    $mail->setFrom($mass_from);
    $mail->Subject = $mass_subject;
    $mail->CharSet ="UTF-8";
    if (!empty($mass_html)) {
      $mail->Body = $mass_html;
      $mail->AltBody = $mass_text;
    }
    else {
      $mail->Body = $mass_text;
    }
    $mail->XMailer = 'MooMassMail';
    foreach ($rcpts as $rcpt) {
      $mail->AddAddress($rcpt);
      if (!$mail->send()) {
        $_SESSION['return'][] =  array(
          'type' => 'warning',
          'log' => array(__FUNCTION__),
          'msg' => 'Mailer error (RCPT "' . htmlspecialchars($rcpt) . '"): ' . str_replace('https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting', '', $mail->ErrorInfo)
        );
      }
      $mail->ClearAllRecipients();
    }
  }
  $_SESSION['return'][] =  array(
    'type' => 'success',
    'log' => array(__FUNCTION__),
    'msg' => 'Mass mail job completed, sent ' . count($rcpts) . ' mails'
  );
}
function logger($_data = false) {
  /*
  logger() will be called as last function
  To manually log a message, logger needs to be called like below.

  logger(array(
    'return' => array(
      array(
        'type' => 'danger',
        'log' => array(__FUNCTION__),
        'msg' => $err
      )
    )
  ));

  These messages will not be printed as alert box.
  To do so, push them to $_SESSION['return'] and do not call logger as they will be included automatically:

  $_SESSION['return'][] =  array(
    'type' => 'danger',
    'log' => array(__FUNCTION__, $user, '*'),
    'msg' => $err
  );
  */
  global $pdo;
  if (!$_data) {
    $_data = $_SESSION;
  }
  if (!empty($_data['return'])) {
    $task = substr(strtoupper(md5(uniqid(rand(), true))), 0, 6);
    foreach ($_data['return'] as $return) {
      $type = $return['type'];
      $msg = null;
      if (isset($return['msg'])) {
        $msg = json_encode($return['msg'], JSON_UNESCAPED_UNICODE);
      }
      $call = null;
      if (isset($return['log'])) {
        $call = json_encode($return['log'], JSON_UNESCAPED_UNICODE);
      }
      if (!empty($_SESSION["dual-login"]["username"])) {
        $user = $_SESSION["dual-login"]["username"] . ' => ' . $_SESSION['mailcow_cc_username'];
        $role = $_SESSION["dual-login"]["role"] . ' => ' . $_SESSION['mailcow_cc_role'];
      }
      elseif (!empty($_SESSION['mailcow_cc_username'])) {
        $user = $_SESSION['mailcow_cc_username'];
        $role = $_SESSION['mailcow_cc_role'];
      }
      else {
        $user = 'unauthenticated';
        $role = 'unauthenticated';
      }
      // We cannot log when logs is missing...
      try {
        $stmt = $pdo->prepare("INSERT INTO `logs` (`type`, `task`, `msg`, `call`, `user`, `role`, `remote`, `time`) VALUES
          (:type, :task, :msg, :call, :user, :role, :remote, UNIX_TIMESTAMP())");
        $stmt->execute(array(
          ':type' => $type,
          ':task' => $task,
          ':call' => $call,
          ':msg' => $msg,
          ':user' => $user,
          ':role' => $role,
          ':remote' => get_remote_ip()
        ));
      }
      catch (PDOException $e) {
        # handle the exception here, as the exception handler function results in a white page
        error_log($e->getMessage(), 0);
      }
    }
  }
  else {
    return true;
  }
}
function hasDomainAccess($username, $role, $domain) {
  global $pdo;
  if (empty($domain) || !is_valid_domain_name($domain)) {
    return false;
  }
  if (isset($_SESSION['access_all_exception']) && $_SESSION['access_all_exception'] == "1") {
    return true;
  }
  if (!filter_var($username, FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
    return false;
  }
  if ($role != 'admin' && $role != 'domainadmin') {
    return false;
  }
  if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT `domain` FROM `domain`
      WHERE `domain` = :domain");
    $stmt->execute(array(':domain' => $domain));
    $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    $stmt = $pdo->prepare("SELECT `alias_domain` FROM `alias_domain`
      WHERE `alias_domain` = :domain");
    $stmt->execute(array(':domain' => $domain));
    $num_results = $num_results + count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($num_results != 0) {
      return true;
    }
  }
  elseif ($role == 'domainadmin') {
    $stmt = $pdo->prepare("SELECT `domain` FROM `domain_admins`
    WHERE (
      `active`='1'
      AND `username` = :username
      AND (`domain` = :domain1 OR `domain` = (SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain2))
    )");
    $stmt->execute(array(':username' => $username, ':domain1' => $domain, ':domain2' => $domain));
    $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if (!empty($num_results)) {
      return true;
    }
  }
  return false;
}
function hasMailboxObjectAccess($username, $role, $object) {
  global $pdo;
  if (isset($_SESSION['access_all_exception']) && $_SESSION['access_all_exception'] == "1") {
    return true;
  }
  if (empty($username) || empty($role) || empty($object)) {
    return false;
  }
  if (!filter_var(html_entity_decode(rawurldecode($username)), FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
    return false;
  }
  if ($role != 'admin' && $role != 'domainadmin' && $role != 'user') {
    return false;
  }
  if ($username == $object) {
    return true;
  }
  $stmt = $pdo->prepare("SELECT `domain` FROM `mailbox` WHERE `username` = :object");
  $stmt->execute(array(':object' => $object));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (isset($row['domain']) && hasDomainAccess($username, $role, $row['domain'])) {
    return true;
  }
  return false;
}
// does also verify mailboxes as a mailbox is a alias == goto
function hasAliasObjectAccess($username, $role, $object) {
  global $pdo;
  if (isset($_SESSION['access_all_exception']) && $_SESSION['access_all_exception'] == "1") {
    return true;
  }
  if (empty($username) || empty($role) || empty($object)) {
    return false;
  }
  if (!filter_var(html_entity_decode(rawurldecode($username)), FILTER_VALIDATE_EMAIL) && !ctype_alnum(str_replace(array('_', '.', '-'), '', $username))) {
    return false;
  }
  if ($role != 'admin' && $role != 'domainadmin' && $role != 'user') {
    return false;
  }
  $stmt = $pdo->prepare("SELECT `domain` FROM `alias` WHERE `address` = :object");
  $stmt->execute(array(':object' => $object));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (isset($row['domain']) && hasDomainAccess($username, $role, $row['domain'])) {
    return true;
  }
  return false;
}
function hasACLAccess($type) {
  if (isset($_SESSION['access_all_exception']) && $_SESSION['access_all_exception'] == "1") {
    return true;
  }
  if (isset($_SESSION['acl'][$type]) && $_SESSION['acl'][$type] == "1") {
    return true;
  }

  return false;
}
function pem_to_der($pem_key) {
  // Need to remove BEGIN/END PUBLIC KEY
  $lines = explode("\n", trim($pem_key));
  unset($lines[count($lines)-1]);
  unset($lines[0]);
  return base64_decode(implode('', $lines));
}
function expand_ipv6($ip) {
  $hex = unpack("H*hex", inet_pton($ip));
  $ip = substr(preg_replace("/([A-f0-9]{4})/", "$1:", $hex['hex']), 0, -1);
  return $ip;
}
function generate_tlsa_digest($hostname, $port, $starttls = null) {
  if (!is_valid_domain_name($hostname)) {
    return "Not a valid hostname";
  }
  if (empty($starttls)) {
    $context = stream_context_create(array("ssl" => array("capture_peer_cert" => true, 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)));
    $stream = stream_socket_client('ssl://' . $hostname . ':' . $port, $error_nr, $error_msg, 5, STREAM_CLIENT_CONNECT, $context);
    if (!$stream) {
      $error_msg = isset($error_msg) ? $error_msg : '-';
      return $error_nr . ': ' . $error_msg;
    }
  }
  else {
    $stream = stream_socket_client('tcp://' . $hostname . ':' . $port, $error_nr, $error_msg, 5);
    if (!$stream) {
      return $error_nr . ': ' . $error_msg;
    }
    $banner = fread($stream, 512 );
    if (preg_match("/^220/i", $banner)) { // SMTP
      fwrite($stream,"HELO tlsa.generator.local\r\n");
      fread($stream, 512);
      fwrite($stream,"STARTTLS\r\n");
      fread($stream, 512);
    }
    elseif (preg_match("/imap.+starttls/i", $banner)) { // IMAP
      fwrite($stream,"A1 STARTTLS\r\n");
      fread($stream, 512);
    }
    elseif (preg_match("/^\+OK/", $banner)) { // POP3
      fwrite($stream,"STLS\r\n");
      fread($stream, 512);
    }
    elseif (preg_match("/^OK/m", $banner)) { // Sieve
      fwrite($stream,"STARTTLS\r\n");
      fread($stream, 512);
    }
    else {
      return 'Unknown banner: "' . htmlspecialchars(trim($banner)) . '"';
    }
    // Upgrade connection
    stream_set_blocking($stream, true);
    stream_context_set_option($stream, 'ssl', 'capture_peer_cert', true);
    stream_context_set_option($stream, 'ssl', 'verify_peer', false);
    stream_context_set_option($stream, 'ssl', 'verify_peer_name', false);
    stream_context_set_option($stream, 'ssl', 'allow_self_signed', true);
    stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
    stream_set_blocking($stream, false);
  }
  $params = stream_context_get_params($stream);
  if (!empty($params['options']['ssl']['peer_certificate'])) {
    $key_resource = openssl_pkey_get_public($params['options']['ssl']['peer_certificate']);
    // We cannot get ['rsa']['n'], the binary data would contain BEGIN/END PUBLIC KEY
    $key_data = openssl_pkey_get_details($key_resource)['key'];
    return '3 1 1 ' . openssl_digest(pem_to_der($key_data), 'sha256');
  }
  else {
    return 'Error: Cannot read peer certificate';
  }
}
function alertbox_log_parser($_data) {
  global $lang;
  if (isset($_data['return'])) {
    foreach ($_data['return'] as $return) {
      // Get type
      $type = $return['type'];
      // If a lang[type][msg] string exists, use it as message
      if (isset($return['type']) && isset($return['msg']) && !is_array($return['msg'])) {
        if (isset($lang[$return['type']][$return['msg']])) {
          $msg = $lang[$return['type']][$return['msg']];
        }
        else {
          $msg = $return['msg'];
        }
      }
      // If msg is an array, use first element as language string and run printf on it with remaining array elements
      elseif (is_array($return['msg'])) {
        $msg = array_shift($return['msg']);
        $msg = vsprintf(
          $lang[$return['type']][$msg],
          $return['msg']
        );
      }
      else {
        $msg = '-';
      }
      $log_array[] = array('msg' => $msg, 'type' => json_encode($type));
    }
    if (!empty($log_array)) {
      return $log_array;
    }
  }
  return false;
}
function verify_salted_hash($hash, $password, $algo, $salt_length) {
  // Decode hash
  $dhash = base64_decode($hash);
  // Get first n bytes of binary which equals a SSHA hash
  $ohash = substr($dhash, 0, $salt_length);
  // Remove SSHA hash from decoded hash to get original salt string
  $osalt = str_replace($ohash, '', $dhash);
  // Check single salted SSHA hash against extracted hash
  if (hash_equals(hash($algo, $password . $osalt, true), $ohash)) {
    return true;
  }
  return false;
}
function verify_hash($hash, $password) {
  if (preg_match('/^{(.+)}(.+)/i', $hash, $hash_array)) {
    $scheme = strtoupper($hash_array[1]);
    $hash = $hash_array[2];
    switch ($scheme) {
      case "ARGON2I":
      case "ARGON2ID":
      case "BLF-CRYPT":
      case "CRYPT":
      case "DES-CRYPT":
      case "MD5-CRYPT":
      case "MD5":
      case "SHA256-CRYPT":
      case "SHA512-CRYPT":
        return password_verify($password, $hash);

      case "CLEAR":
      case "CLEARTEXT":
      case "PLAIN":
        return $password == $hash;

      case "LDAP-MD5":
        $hash = base64_decode($hash);
        return hash_equals(hash('md5', $password, true), $hash);

      case "PBKDF2":
        $components = explode('$', $hash);
        $salt = $components[2];
        $rounds = $components[3];
        $hash = $components[4];
        return hash_equals(hash_pbkdf2('sha1', $password, $salt, $rounds), $hash);

      case "PLAIN-MD4":
        return hash_equals(hash('md4', $password), $hash);

      case "PLAIN-MD5":
        return md5($password) == $hash;

      case "PLAIN-TRUNC":
        $components = explode('-', $hash);
        if (count($components) > 1) {
          $trunc_len = $components[0];
          $trunc_password = $components[1];

          return substr($password, 0, $trunc_len) == $trunc_password;
        } else {
          return $password == $hash;
        }

      case "SHA":
      case "SHA1":
      case "SHA256":
      case "SHA512":
        // SHA is an alias for SHA1
        $scheme = $scheme == "SHA" ? "sha1" : strtolower($scheme);
        $hash = base64_decode($hash);
        return hash_equals(hash($scheme, $password, true), $hash);

      case "SMD5":
        return verify_salted_hash($hash, $password, 'md5', 16);

      case "SSHA":
        return verify_salted_hash($hash, $password, 'sha1', 20);

      case "SSHA256":
        return verify_salted_hash($hash, $password, 'sha256', 32);

      case "SSHA512":
        return verify_salted_hash($hash, $password, 'sha512', 64);

      default:
        return false;
    }
  }
  return false;
}
function formatBytes($size, $precision = 2) {
  if(!is_numeric($size)) {
    return "0";
  }
  $base = log($size, 1024);
  $suffixes = array(' Byte', ' KiB', ' MiB', ' GiB', ' TiB');
  if ($size == "0") {
    return "0";
  }
  return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}
function update_sogo_static_view($mailbox = null) {
  if (getenv('SKIP_SOGO') == "y") {
    return true;
  }
  global $pdo;
  global $lang;

  $mailbox_exists = false;
  if ($mailbox !== null) {
    // Check if the mailbox exists
    $stmt = $pdo->prepare("SELECT username FROM mailbox WHERE username = :mailbox AND active = '1'");
    $stmt->execute(array(':mailbox' => $mailbox));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row){
      $mailbox_exists = true;
    }
  }

  // generate random password for sogo to deny direct login
  $random_password = base64_encode(openssl_random_pseudo_bytes(24));
  $random_salt = base64_encode(openssl_random_pseudo_bytes(16));
  $random_hash = '{SSHA256}' . base64_encode(hash('sha256', base64_decode($password) . $salt, true) . $salt);

  $subquery = "GROUP BY mailbox.username";
  if ($mailbox_exists) {
    $subquery = "AND mailbox.username = :mailbox";
  }
  $query = "INSERT INTO _sogo_static_view (`c_uid`, `domain`, `c_name`, `c_password`, `c_cn`, `mail`, `aliases`, `ad_aliases`, `ext_acl`, `kind`, `multiple_bookings`)
      SELECT
        mailbox.username,
        mailbox.domain,
        mailbox.username,
        :random_hash,
        mailbox.name,
        mailbox.username,
        IFNULL(GROUP_CONCAT(ga.aliases ORDER BY ga.aliases SEPARATOR ' '), ''),
        IFNULL(gda.ad_alias, ''),
        IFNULL(external_acl.send_as_acl, ''),
        mailbox.kind,
        mailbox.multiple_bookings
      FROM
        mailbox
        LEFT OUTER JOIN grouped_mail_aliases ga ON ga.username REGEXP CONCAT('(^|,)', mailbox.username, '($|,)')
        LEFT OUTER JOIN grouped_domain_alias_address gda ON gda.username = mailbox.username
        LEFT OUTER JOIN grouped_sender_acl_external external_acl ON external_acl.username = mailbox.username
      WHERE
        mailbox.active = '1'
        $subquery
      ON DUPLICATE KEY UPDATE
        `domain` = VALUES(`domain`),
        `c_name` = VALUES(`c_name`),
        `c_password` = VALUES(`c_password`),
        `c_cn` = VALUES(`c_cn`),
        `mail` = VALUES(`mail`),
        `aliases` = VALUES(`aliases`),
        `ad_aliases` = VALUES(`ad_aliases`),
        `ext_acl` = VALUES(`ext_acl`),
        `kind` = VALUES(`kind`),
        `multiple_bookings` = VALUES(`multiple_bookings`)";


  if ($mailbox_exists) {
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
      ':random_hash' => $random_hash,
      ':mailbox' => $mailbox
    ));
  } else {
    $stmt = $pdo->prepare($query);
    $stmt->execute(array(
      ':random_hash' => $random_hash
    ));
  }

  $stmt = $pdo->query("DELETE FROM _sogo_static_view WHERE `c_uid` NOT IN (SELECT `username` FROM `mailbox` WHERE `active` = '1');");

  flush_memcached();
}
function edit_user_account($_data) {
  global $lang;
  global $pdo;

  $_data_log = $_data;
  !isset($_data_log['user_new_pass']) ?: $_data_log['user_new_pass'] = '*';
  !isset($_data_log['user_new_pass2']) ?: $_data_log['user_new_pass2'] = '*';
  !isset($_data_log['user_old_pass']) ?: $_data_log['user_old_pass'] = '*';

  $username = $_SESSION['mailcow_cc_username'];
  $role = $_SESSION['mailcow_cc_role'];
  $password_old = $_data['user_old_pass'];
  $pw_recovery_email = $_data['pw_recovery_email'];

  if (filter_var($username, FILTER_VALIDATE_EMAIL === false) || $role != 'user') {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_data_log),
      'msg' => 'access_denied'
    );
    return false;
  }

  // edit password
  if (!empty($password_old) && !empty($_data['user_new_pass']) && !empty($_data['user_new_pass2'])) {
    $stmt = $pdo->prepare("SELECT `password` FROM `mailbox`
        WHERE `kind` NOT REGEXP 'location|thing|group'
          AND `username` = :user AND authsource = 'mailcow'");
    $stmt->execute(array(':user' => $username));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!verify_hash($row['password'], $password_old)) {
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => 'access_denied'
      );
      return false;
    }

    $password_new = $_data['user_new_pass'];
    $password_new2  = $_data['user_new_pass2'];
    if (password_check($password_new, $password_new2) !== true) {
      return false;
    }
    $password_hashed = hash_password($password_new);
    $stmt = $pdo->prepare("UPDATE `mailbox` SET `password` = :password_hashed,
      `attributes` = JSON_SET(`attributes`, '$.force_pw_update', '0'),
      `attributes` = JSON_SET(`attributes`, '$.passwd_update', NOW())
        WHERE `username` = :username AND authsource = 'mailcow'");
    $stmt->execute(array(
      ':password_hashed' => $password_hashed,
      ':username' => $username
    ));

    update_sogo_static_view();
  }
  // edit password recovery email
  elseif (isset($pw_recovery_email)) {
    if (!isset($_SESSION['acl']['pw_reset']) || $_SESSION['acl']['pw_reset'] != "1" ) {
      $_SESSION['return'][] = array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $_action, $_type, $_data_log, $_attr),
        'msg' => 'access_denied'
      );
      return false;
    }

    $pw_recovery_email = (!filter_var($pw_recovery_email, FILTER_VALIDATE_EMAIL)) ? '' : $pw_recovery_email;
    $stmt = $pdo->prepare("UPDATE `mailbox` SET `attributes` = JSON_SET(`attributes`, '$.recovery_email', :recovery_email)
      WHERE `username` = :username AND authsource = 'mailcow'");
    $stmt->execute(array(
      ':recovery_email' => $pw_recovery_email,
      ':username' => $username
    ));
  }

  $_SESSION['return'][] =  array(
    'type' => 'success',
    'log' => array(__FUNCTION__, $_data_log),
    'msg' => array('mailbox_modified', htmlspecialchars($username))
  );
}
function user_get_alias_details($username) {
  global $pdo;
  global $lang;
  $data['direct_aliases'] = array();
  $data['shared_aliases'] = array();
  if ($_SESSION['mailcow_cc_role'] == "user") {
    $username = $_SESSION['mailcow_cc_username'];
  }
  if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    return false;
  }
  if (!hasMailboxObjectAccess($username, $_SESSION['mailcow_cc_role'], $username)) {
    return false;
  }
  $data['address'] = $username;
  $stmt = $pdo->prepare("SELECT `address` AS `shared_aliases`, `public_comment` FROM `alias`
    WHERE `goto` REGEXP :username_goto
    AND `address` NOT LIKE '@%'
    AND `goto` != :username_goto2
    AND `address` != :username_address");
  $stmt->execute(array(
    ':username_goto' => '(^|,)'.preg_quote($username, '/').'($|,)',
    ':username_goto2' => $username,
    ':username_address' => $username
    ));
  $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($run)) {
    $data['shared_aliases'][$row['shared_aliases']]['public_comment'] = htmlspecialchars($row['public_comment']);
    //$data['shared_aliases'][] = $row['shared_aliases'];
  }

  $stmt = $pdo->prepare("SELECT `address` AS `direct_aliases`, `public_comment` FROM `alias`
    WHERE `goto` = :username_goto
    AND `address` NOT LIKE '@%'
    AND `address` != :username_address");
  $stmt->execute(
    array(
    ':username_goto' => $username,
    ':username_address' => $username
  ));
  $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($run)) {
    $data['direct_aliases'][$row['direct_aliases']]['public_comment'] = htmlspecialchars($row['public_comment']);
  }
  $stmt = $pdo->prepare("SELECT CONCAT(local_part, '@', alias_domain) AS `ad_alias`, `alias_domain` FROM `mailbox`
    LEFT OUTER JOIN `alias_domain` on `target_domain` = `domain`
      WHERE `username` = :username ;");
  $stmt->execute(array(':username' => $username));
  $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($run)) {
    if (empty($row['ad_alias'])) {
      continue;
    }
    $data['direct_aliases'][$row['ad_alias']]['public_comment'] = $lang['add']['alias_domain'];
    $data['alias_domains'][] = $row['alias_domain'];
  }
  $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`send_as` SEPARATOR ', '), '') AS `send_as` FROM `sender_acl` WHERE `logged_in_as` = :username AND `send_as` NOT LIKE '@%';");
  $stmt->execute(array(':username' => $username));
  $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($run)) {
    $data['aliases_also_send_as'] = $row['send_as'];
  }
  $stmt = $pdo->prepare("SELECT CONCAT_WS(', ', IFNULL(GROUP_CONCAT(DISTINCT `send_as` SEPARATOR ', '), ''), GROUP_CONCAT(DISTINCT CONCAT('@',`alias_domain`) SEPARATOR ', ')) AS `send_as` FROM `sender_acl` LEFT JOIN `alias_domain` ON `alias_domain`.`target_domain` =  TRIM(LEADING '@' FROM `send_as`) WHERE `logged_in_as` = :username AND `send_as` LIKE '@%';");
  $stmt->execute(array(':username' => $username));
  $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($run)) {
    $data['aliases_send_as_all'] = $row['send_as'];
  }
  $stmt = $pdo->prepare("SELECT IFNULL(GROUP_CONCAT(`address` SEPARATOR ', '), '') as `address` FROM `alias` WHERE `goto` REGEXP :username AND `address` LIKE '@%';");
  $stmt->execute(array(':username' => '(^|,)'.preg_quote($username, '/').'($|,)'));
  $run = $stmt->fetchAll(PDO::FETCH_ASSOC);
  while ($row = array_shift($run)) {
    $data['is_catch_all'] = $row['address'];
  }
  return $data;
}
function is_valid_domain_name($domain_name) {
  if (empty($domain_name)) {
    return false;
  }
  $domain_name = idn_to_ascii($domain_name, 0, INTL_IDNA_VARIANT_UTS46);
  return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name)
       && preg_match("/^.{1,253}$/", $domain_name)
       && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name));
}
function set_tfa($_data) {
  global $pdo;
  global $yubi;
  global $tfa;
  global $iam_settings;

  $_data_log = $_data;
  $access_denied = null;
  !isset($_data_log['confirm_password']) ?: $_data_log['confirm_password'] = '*';
  $username = $_SESSION['mailcow_cc_username'];

  // check for empty user and role
  if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) $access_denied = true;

  // check admin confirm password
  if ($access_denied === null) {
    $stmt = $pdo->prepare("SELECT `password` FROM `admin`
        WHERE `username` = :username");
    $stmt->execute(array(':username' => $username));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      if (!verify_hash($row['password'], $_data["confirm_password"])) $access_denied = true;
      else $access_denied = false;
    }
  }

  // check mailbox confirm password
  if ($access_denied === null) {
    $stmt = $pdo->prepare("SELECT `password`, `authsource` FROM `mailbox`
        WHERE `username` = :username");
    $stmt->execute(array(':username' => $username));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      if ($row['authsource'] == 'ldap'){
        if (!ldap_mbox_login($username, $_data["confirm_password"], $iam_settings)) $access_denied = true;
        else $access_denied = false;
      } else {
        if (!verify_hash($row['password'], $_data["confirm_password"])) $access_denied = true;
        else $access_denied = false;
      }
    }
  }

  // set access_denied error
  if ($access_denied){
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_data_log),
      'msg' => 'access_denied'
    );
    return false;
  }

  switch ($_data["tfa_method"]) {
    case "yubi_otp":
      $key_id = (!isset($_data["key_id"])) ? 'unidentified' : $_data["key_id"];
      $yubico_id = $_data['yubico_id'];
      $yubico_key = $_data['yubico_key'];
      $yubi = new Auth_Yubico($yubico_id, $yubico_key);
      if (!$yubi) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      if (!ctype_alnum($_data["otp_token"]) || strlen($_data["otp_token"]) != 44) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data_log),
          'msg' => 'tfa_token_invalid'
        );
        return false;
      }
      $yauth = $yubi->verify($_data["otp_token"]);
      if (PEAR::isError($yauth)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data_log),
          'msg' => array('yotp_verification_failed', $yauth->getMessage())
        );
        return false;
      }
      try {
        // We could also do a modhex translation here
        $yubico_modhex_id = substr($_data["otp_token"], 0, 12);
        $stmt = $pdo->prepare("DELETE FROM `tfa`
          WHERE `username` = :username
            AND (`authmech` = 'yubi_otp' AND `secret` LIKE :modhex)");
        $stmt->execute(array(':username' => $username, ':modhex' => '%' . $yubico_modhex_id));
        $stmt = $pdo->prepare("INSERT INTO `tfa` (`key_id`, `username`, `authmech`, `active`, `secret`) VALUES
          (:key_id, :username, 'yubi_otp', '1', :secret)");
        $stmt->execute(array(':key_id' => $key_id, ':username' => $username, ':secret' => $yubico_id . ':' . $yubico_key . ':' . $yubico_modhex_id));
      }
      catch (PDOException $e) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data_log),
          'msg' => array('mysql_error', $e)
        );
        return false;
      }
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => array('object_modified', htmlspecialchars($username))
      );
    break;
    case "totp":
      $key_id = (!isset($_data["key_id"])) ? 'unidentified' : $_data["key_id"];
      if ($tfa->verifyCode($_POST['totp_secret'], $_POST['totp_confirm_token']) === true) {
        //$stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username");
        //$stmt->execute(array(':username' => $username));
        $stmt = $pdo->prepare("INSERT INTO `tfa` (`username`, `key_id`, `authmech`, `secret`, `active`) VALUES (?, ?, 'totp', ?, '1')");
        $stmt->execute(array($username, $key_id, $_POST['totp_secret']));
        $_SESSION['return'][] =  array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_data_log),
          'msg' => array('object_modified', $username)
        );
      }
      else {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data_log),
          'msg' => 'totp_verification_failed'
        );
      }
    break;
    case "webauthn":
        $key_id = (!isset($_data["key_id"])) ? 'unidentified' : $_data["key_id"];

        $stmt = $pdo->prepare("INSERT INTO `tfa` (`username`, `key_id`, `authmech`, `keyHandle`, `publicKey`, `certificate`, `counter`, `active`)
        VALUES (?, ?, 'webauthn', ?, ?, ?, ?, '1')");
        $stmt->execute(array(
            $username,
            $key_id,
            base64_encode($_data['registration']->credentialId),
            $_data['registration']->credentialPublicKey,
            $_data['registration']->certificate,
            0
        ));

        $_SESSION['return'][] =  array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_data_log),
            'msg' => array('object_modified', $username)
        );
    break;
    case "none":
      $stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => array('object_modified', htmlspecialchars($username))
      );
    break;
  }
}
function fido2($_data) {
  global $pdo;
  global $WebAuthn;
  $_data_log = $_data;
  // Not logging registration data, only actions
  // Silent errors for "get" requests
  switch ($_data["action"]) {
    case "register":
      $username = $_SESSION['mailcow_cc_username'];
      if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) {
          $_SESSION['return'][] =  array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_data["action"]),
            'msg' => 'access_denied'
          );
          return false;
      }
      $stmt = $pdo->prepare("INSERT INTO `fido2` (`username`, `rpId`, `credentialPublicKey`, `certificateChain`, `certificate`, `certificateIssuer`, `certificateSubject`, `signatureCounter`, `AAGUID`, `credentialId`)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute(array(
        $username,
        $_data['registration']->rpId,
        $_data['registration']->credentialPublicKey,
        $_data['registration']->certificateChain,
        $_data['registration']->certificate,
        $_data['registration']->certificateIssuer,
        $_data['registration']->certificateSubject,
        $_data['registration']->signatureCounter,
        $_data['registration']->AAGUID,
        $_data['registration']->credentialId)
      );
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_data["action"]),
        'msg' => array('object_modified', $username)
      );
    break;
    case "get_user_cids":
      // Used to exclude existing CredentialIds while registering
      $username = $_SESSION['mailcow_cc_username'];
      if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) {
        return false;
      }
      $stmt = $pdo->prepare("SELECT `credentialId` FROM `fido2` WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $cids[] = $row['credentialId'];
      }
      return $cids;
    break;
    case "get_all_cids":
      // Only needed when using fido2 with username
      $stmt = $pdo->query("SELECT `credentialId` FROM `fido2`");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $cids[] = $row['credentialId'];
      }
      return $cids;
    break;
    case "get_by_b64cid":
      if (!isset($_data['cid']) || empty($_data['cid'])) {
        return false;
      }
      $stmt = $pdo->prepare("SELECT `certificateSubject`, `username`, `credentialPublicKey`, SHA2(`credentialId`, 256) AS `cid` FROM `fido2` WHERE `credentialId` = :cid");
      $stmt->execute(array(':cid' => base64_decode($_data['cid'])));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (empty($row) || empty($row['credentialPublicKey']) || empty($row['username'])) {
        return false;
      }
      $data['pub_key'] = $row['credentialPublicKey'];
      $data['username'] = $row['username'];
      $data['subject'] = $row['certificateSubject'];
      $data['cid'] = $row['cid'];
      return $data;
    break;
    case "get_friendly_names":
      $username = $_SESSION['mailcow_cc_username'];
      if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) {
        return false;
      }
      $stmt = $pdo->prepare("SELECT SHA2(`credentialId`, 256) AS `cid`, `created`, `certificateSubject`, `friendlyName` FROM `fido2` WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      while($row = array_shift($rows)) {
        $fns[] = array(
          "subject" => (empty($row['certificateSubject']) ? 'Unknown (' . $row['created'] . ')' : $row['certificateSubject']),
          "fn" => $row['friendlyName'],
          "cid" => $row['cid']
        );
      }
      return $fns;
    break;
    case "unset_fido2_key":
      $username = $_SESSION['mailcow_cc_username'];
      if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data["action"]),
          'msg' => 'access_denied'
        );
        return false;
      }
      $stmt = $pdo->prepare("DELETE FROM `fido2` WHERE `username` = :username AND SHA2(`credentialId`, 256) = :cid");
      $stmt->execute(array(
        ':username' => $username,
        ':cid' => $_data['post_data']['unset_fido2_key']
      ));
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => array('object_modified', htmlspecialchars($username))
      );
    break;
    case "edit_fn":
      $username = $_SESSION['mailcow_cc_username'];
      if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_data["action"]),
          'msg' => 'access_denied'
        );
        return false;
      }
      $stmt = $pdo->prepare("UPDATE `fido2` SET `friendlyName` = :friendlyName WHERE SHA2(`credentialId`, 256) = :cid AND `username` = :username");
      $stmt->execute(array(
        ':username' => $username,
        ':friendlyName' => $_data['fido2_attrs']['fido2_fn'],
        ':cid' => $_data['fido2_attrs']['fido2_cid']
      ));
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => array('object_modified', htmlspecialchars($username))
      );
    break;
    case "verify":
      $role = "";
      $tokenData = json_decode($_data['token']);
      $clientDataJSON = base64_decode($tokenData->clientDataJSON);
      $authenticatorData = base64_decode($tokenData->authenticatorData);
      $signature = base64_decode($tokenData->signature);
      $id = base64_decode($tokenData->id);
      $challenge = $_SESSION['challenge'];
      $process_fido2 = fido2(array("action" => "get_by_b64cid", "cid" => $tokenData->id));
      if ($process_fido2['pub_key'] === false) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array("fido2_login", $_data['user'], $process_fido2['username']),
          'msg' => "login_failed"
        );
        return false;
      }
      try {
        $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $process_fido2['pub_key'], $challenge, null, $GLOBALS['FIDO2_UV_FLAG_LOGIN'], $GLOBALS['FIDO2_USER_PRESENT_FLAG']);
      }
      catch (Throwable $ex) {
        unset($process_fido2);
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array("fido2_login", $_data['user'], $process_fido2['username'], $ex->getMessage()),
          'msg' => "login_failed"
        );
        return false;
      }
      $return = new stdClass();
      $return->success = true;
      $stmt = $pdo->prepare("SELECT `superadmin` FROM `admin` WHERE `username` = :username");
      $stmt->execute(array(':username' => $process_fido2['username']));
      $obj_props = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($obj_props['superadmin'] === 1 && (!$_data['user'] || $_data['user'] == "admin")) {
        $role = "admin";
      }
      elseif ($obj_props['superadmin'] === 0 && (!$_data['user'] || $_data['user'] == "domainadmin")) {
        $role = "domainadmin";
      }
      elseif (!isset($obj_props['superadmin']) && (!$_data['user'] || $_data['user'] == "user")) {
        $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :username");
        $stmt->execute(array(':username' => $process_fido2['username']));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row['username'] == $process_fido2['username']) {
          $role = "user";
        }
      }
      else {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array("fido2_login", $_data['user'], $process_fido2['username']),
          'msg' => 'login_failed'
        );
        return false;
      }
      if (empty($role)) {
        session_unset();
        session_destroy();
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array("fido2_login", $_data['user'], $process_fido2['username']),
          'msg' => 'login_failed'
        );
        return false;
      }
      unset($_SESSION["challenge"]);
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array("fido2_login", $_data['user'], $process_fido2['username']),
        'msg' => array('logged_in_as', $process_fido2['username'])
      );
      return array(
        "role" => $role,
        "username" => $process_fido2['username'],
        "cid" => $process_fido2['cid']
      );
    break;
  }
}
function unset_tfa_key($_data) {
  // Can only unset own keys
  // Needs at least one key left
  global $pdo;
  global $lang;
  $_data_log = $_data;
  $access_denied = null;
  $id = intval($_data['unset_tfa_key']);
  $username = $_SESSION['mailcow_cc_username'];

  // check for empty user and role
  if (!isset($_SESSION['mailcow_cc_role']) || empty($username)) $access_denied = true;

  try {
    if (!is_numeric($id)) $access_denied = true;

    // set access_denied error
    if ($access_denied){
      $_SESSION['return'][] = array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => 'access_denied'
      );
      return false;
    }

    // check if it's last key
    $stmt = $pdo->prepare("SELECT COUNT(*) AS `keys` FROM `tfa`
      WHERE `username` = :username AND `active` = '1'");
    $stmt->execute(array(':username' => $username));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['keys'] == "1") {
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $_data_log),
        'msg' => 'last_key'
      );
      return false;
    }

    // delete key
    $stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `username` = :username AND `id` = :id");
    $stmt->execute(array(':username' => $username, ':id' => $id));
    $_SESSION['return'][] =  array(
      'type' => 'success',
      'log' => array(__FUNCTION__, $_data_log),
      'msg' => array('object_modified', $username)
    );
  }
  catch (PDOException $e) {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_data_log),
      'msg' => array('mysql_error', $e)
    );
    return false;
  }
}
function get_tfa($username = null, $id = null) {
  global $pdo;
  if (empty($username) && isset($_SESSION['mailcow_cc_username'])) {
    $username = $_SESSION['mailcow_cc_username'];
  }
  elseif (empty($username)) {
    return false;
  }

  if (!isset($id)){
    // fetch all tfa methods - just get information about possible authenticators
    $stmt = $pdo->prepare("SELECT `id`, `key_id`, `authmech` FROM `tfa`
        WHERE `username` = :username AND `active` = '1'");
    $stmt->execute(array(':username' => $username));
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // no tfa methods found
    if (count($results) == 0) {
        $data['name'] = 'none';
        $data['pretty'] = "-";
        $data['additional'] = array();
        return $data;
    }

    $data['additional'] = $results;
    return $data;
  } else {
    // fetch specific authenticator details by id
    $stmt = $pdo->prepare("SELECT * FROM `tfa`
    WHERE `username` = :username AND `id` = :id AND `active` = '1'");
    $stmt->execute(array(':username' => $username, ':id' => $id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (isset($row["authmech"])) {
        switch ($row["authmech"]) {
          case "yubi_otp":
            $data['name'] = "yubi_otp";
            $data['pretty'] = "Yubico OTP";
            $stmt = $pdo->prepare("SELECT `id`, `key_id`, RIGHT(`secret`, 12) AS 'modhex' FROM `tfa` WHERE `authmech` = 'yubi_otp' AND `username` = :username AND `id` = :id");
            $stmt->execute(array(
              ':username' => $username,
              ':id' => $id
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $data['additional'][] = $row;
            }
            return $data;
          break;
          // u2f - deprecated, should be removed
          case "u2f":
            $data['name'] = "u2f";
            $data['pretty'] = "Fido U2F";
            $stmt = $pdo->prepare("SELECT `id`, `key_id` FROM `tfa` WHERE `authmech` = 'u2f' AND `username` = :username AND `id` = :id");
            $stmt->execute(array(
              ':username' => $username,
              ':id' => $id
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $data['additional'][] = $row;
            }
            return $data;
          break;
          case "hotp":
            $data['name'] = "hotp";
            $data['pretty'] = "HMAC-based OTP";
            return $data;
          break;
          case "totp":
            $data['name'] = "totp";
            $data['pretty'] = "Time-based OTP";
            $stmt = $pdo->prepare("SELECT `id`, `key_id`, `secret` FROM `tfa` WHERE `authmech` = 'totp' AND `username` = :username AND `id` = :id");
            $stmt->execute(array(
              ':username' => $username,
              ':id' => $id
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $data['additional'][] = $row;
            }
            return $data;
          break;
          case "webauthn":
            $data['name'] = "webauthn";
            $data['pretty'] = "WebAuthn";
            $stmt = $pdo->prepare("SELECT `id`, `key_id` FROM `tfa` WHERE `authmech` = 'webauthn' AND `username` = :username AND `id` = :id");
            $stmt->execute(array(
              ':username' => $username,
              ':id' => $id
            ));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            while($row = array_shift($rows)) {
              $data['additional'][] = $row;
            }
            return $data;
          break;
          default:
            $data['name'] = 'none';
            $data['pretty'] = "-";
            return $data;
          break;
        }
      }
      else {
        $data['name'] = 'none';
        $data['pretty'] = "-";
        return $data;
      }
    }
}
function verify_tfa_login($username, $_data) {
  global $pdo;
  global $yubi;
  global $u2f;
  global $tfa;
  global $WebAuthn;

  if ($_data['tfa_method'] != 'u2f'){

    switch ($_data["tfa_method"]) {
        case "yubi_otp":
            if (!ctype_alnum($_data['token']) || strlen($_data['token']) != 44) {
                $_SESSION['return'][] =  array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $username, '*'),
                    'msg' => array('yotp_verification_failed', 'token length error')
                );
                return false;
            }
            $yubico_modhex_id = substr($_data['token'], 0, 12);
            $stmt = $pdo->prepare("SELECT `id`, `secret` FROM `tfa`
                WHERE `username` = :username
                AND `authmech` = 'yubi_otp'
                AND `active` = '1'
                AND `secret` LIKE :modhex");
            $stmt->execute(array(':username' => $username, ':modhex' => '%' . $yubico_modhex_id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $yubico_auth = explode(':', $row['secret']);
            $yubi = new Auth_Yubico($yubico_auth[0], $yubico_auth[1]);
            $yauth = $yubi->verify($_data['token']);
            if (PEAR::isError($yauth)) {
                $_SESSION['return'][] =  array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $username, '*'),
                    'msg' => array('yotp_verification_failed', $yauth->getMessage())
                );
                return false;
            }
            else {
                $_SESSION['tfa_id'] = $row['id'];
                $_SESSION['return'][] =  array(
                    'type' => 'success',
                    'log' => array(__FUNCTION__, $username, '*'),
                    'msg' => 'verified_yotp_login'
                );
                return true;
            }
            $_SESSION['return'][] =  array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $username, '*'),
                'msg' => array('yotp_verification_failed', 'unknown')
            );
            return false;
        break;
        case "hotp":
            return false;
        break;
        case "totp":
          try {
            $stmt = $pdo->prepare("SELECT `id`, `secret` FROM `tfa`
                WHERE `username` = :username
                AND `authmech` = 'totp'
                AND `id` = :id
                AND `active`='1'");
            $stmt->execute(array(':username' => $username, ':id' => $_data['id']));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
              if ($tfa->verifyCode($row['secret'], $_data['token']) === true) {
                $_SESSION['tfa_id'] = $row['id'];
                $_SESSION['return'][] =  array(
                    'type' => 'success',
                    'log' => array(__FUNCTION__, $username, '*'),
                    'msg' => 'verified_totp_login'
                );
                return true;
              }
            }
            $_SESSION['return'][] =  array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $username, '*'),
                'msg' => 'totp_verification_failed'
            );
            return false;
          }
          catch (PDOException $e) {
            $_SESSION['return'][] =  array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $username, '*'),
                'msg' => array('mysql_error', $e)
            );
            return false;
          }
        break;
        case "webauthn":
            $tokenData = json_decode($_data['token']);
            $clientDataJSON = base64_decode($tokenData->clientDataJSON);
            $authenticatorData = base64_decode($tokenData->authenticatorData);
            $signature = base64_decode($tokenData->signature);
            $id = base64_decode($tokenData->id);
            $challenge = $_SESSION['challenge'];

            $stmt = $pdo->prepare("SELECT `id`, `key_id`, `keyHandle`, `username`, `publicKey` FROM `tfa` WHERE `id` = :id AND `active`='1'");
            $stmt->execute(array(':id' => $_data['id']));
            $process_webauthn = $stmt->fetch(PDO::FETCH_ASSOC);

            if (empty($process_webauthn)){
              $_SESSION['return'][] =  array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $username, '*'),
                  'msg' => array('webauthn_authenticator_failed')
              );
              return false;
            }

            if (empty($process_webauthn['publicKey']) || $process_webauthn['publicKey'] === false) {
                $_SESSION['return'][] =  array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $username, '*'),
                    'msg' => array('webauthn_publickey_failed')
                );
                return false;
            }

            if ($process_webauthn['username'] != $_SESSION['pending_mailcow_cc_username']){
              $_SESSION['return'][] =  array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $username, '*'),
                  'msg' => array('webauthn_username_failed')
              );
              return false;
            }

            try {
                $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $process_webauthn['publicKey'], $challenge, null, $GLOBALS['WEBAUTHN_UV_FLAG_LOGIN'], $GLOBALS['WEBAUTHN_USER_PRESENT_FLAG']);
            }
            catch (Throwable $ex) {
                $_SESSION['return'][] =  array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $username, '*'),
                    'msg' => array('webauthn_verification_failed', $ex->getMessage())
                );
                return false;
            }

            $stmt = $pdo->prepare("SELECT `superadmin` FROM `admin` WHERE `username` = :username");
            $stmt->execute(array(':username' => $process_webauthn['username']));
            $obj_props = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($obj_props['superadmin'] === 1) {
              $_SESSION["mailcow_cc_role"] = "admin";
            }
            elseif ($obj_props['superadmin'] === 0) {
              $_SESSION["mailcow_cc_role"] = "domainadmin";
            }
            else {
              $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :username");
              $stmt->execute(array(':username' => $process_webauthn['username']));
              $row = $stmt->fetch(PDO::FETCH_ASSOC);
              if (!empty($row['username'])) {
                $_SESSION["mailcow_cc_role"] = "user";
              } else {
                $_SESSION['return'][] =  array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $username, '*'),
                  'msg' => array('webauthn_role_failed')
                );
                return false;
              }
            }

            $_SESSION["mailcow_cc_username"] = $process_webauthn['username'];
            $_SESSION['tfa_id'] = $process_webauthn['id'];
            $_SESSION['authReq'] = null;
            unset($_SESSION["challenge"]);
            $_SESSION['return'][] =  array(
                'type' => 'success',
                'log' => array("webauthn_login"),
                'msg' => array('logged_in_as', $process_webauthn['username'])
            );
            return true;
        break;
        default:
            $_SESSION['return'][] =  array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $username, '*'),
            'msg' => 'unknown_tfa_method'
            );
            return false;
        break;
    }

    return false;
  } else {
    // delete old keys that used u2f
    $stmt = $pdo->prepare("SELECT * FROM `tfa` WHERE `authmech` = 'u2f' AND `username` = :username");
    $stmt->execute(array(':username' => $username));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) == 0) return false;

    $stmt = $pdo->prepare("DELETE FROM `tfa` WHERE `authmech` = 'u2f' AND `username` = :username");
    $stmt->execute(array(':username' => $username));
    return true;
  }
}
function admin_api($access, $action, $data = null) {
  global $pdo;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'access_denied'
    );
    return false;
  }
  if ($access !== "ro" && $access !== "rw") {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'invalid access type'
    );
    return false;
  }
  if ($action == "edit") {
    $active = (!empty($data['active'])) ? 1 : 0;
    $skip_ip_check = (isset($data['skip_ip_check'])) ? 1 : 0;
    $allow_from = array();
    if (isset($data['allow_from'])) {
      $allow_from = array_map('trim', preg_split( "/( |,|;|\n)/", $data['allow_from']));
    }
    foreach ($allow_from as $key => $val) {
      if (empty($val)) {
        unset($allow_from[$key]);
        continue;
      }
      if (valid_network($val) !== true) {
        $_SESSION['return'][] =  array(
          'type' => 'warning',
          'log' => array(__FUNCTION__, $data),
          'msg' => array('ip_invalid', htmlspecialchars($allow_from[$key]))
        );
        unset($allow_from[$key]);
        continue;
      }
    }
    $allow_from = implode(',', array_unique(array_filter($allow_from)));
    if (empty($allow_from) && $skip_ip_check == 0) {
      $_SESSION['return'][] =  array(
        'type' => 'danger',
        'log' => array(__FUNCTION__, $data),
        'msg' => 'ip_list_empty'
      );
      return false;
    }
    $api_key = implode('-', array(
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3)))
    ));
    $stmt = $pdo->query("SELECT `api_key` FROM `api` WHERE `access` = '" . $access . "'");
    $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
    if (empty($num_results)) {
      $stmt = $pdo->prepare("INSERT INTO `api` (`api_key`, `skip_ip_check`, `active`, `allow_from`, `access`)
        VALUES (:api_key, :skip_ip_check, :active, :allow_from, :access);");
      $stmt->execute(array(
        ':api_key' => $api_key,
        ':skip_ip_check' => $skip_ip_check,
        ':active' => $active,
        ':allow_from' => $allow_from,
        ':access' => $access
      ));
    }
    else {
      if ($skip_ip_check == 0) {
        $stmt = $pdo->prepare("UPDATE `api` SET `skip_ip_check` = :skip_ip_check,
          `active` = :active,
          `allow_from` = :allow_from
            WHERE `access` = :access;");
        $stmt->execute(array(
          ':active' => $active,
          ':skip_ip_check' => $skip_ip_check,
          ':allow_from' => $allow_from,
          ':access' => $access
        ));
      }
      else {
        $stmt = $pdo->prepare("UPDATE `api` SET `skip_ip_check` = :skip_ip_check,
          `active` = :active
            WHERE `access` = :access;");
        $stmt->execute(array(
          ':active' => $active,
          ':skip_ip_check' => $skip_ip_check,
          ':access' => $access
        ));
      }
    }
  }
  elseif ($action == "regen_key") {
    $api_key = implode('-', array(
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3))),
      strtoupper(bin2hex(random_bytes(3)))
    ));
    $stmt = $pdo->prepare("UPDATE `api` SET `api_key` = :api_key WHERE `access` = :access");
    $stmt->execute(array(
      ':api_key' => $api_key,
      ':access' => $access
    ));
  }
  elseif ($action == "get") {
    $stmt = $pdo->query("SELECT * FROM `api` WHERE `access` = '" . $access . "'");
    $apidata = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($apidata !== false) {
      $apidata['allow_from'] = str_replace(',', PHP_EOL, $apidata['allow_from']);
    }
    return $apidata;
  }
  $_SESSION['return'][] =  array(
    'type' => 'success',
    'log' => array(__FUNCTION__, $data),
    'msg' => 'admin_api_modified'
  );
}
function license($action, $data = null) {
  global $pdo;
  global $redis;
  global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'access_denied'
    );
    return false;
  }
  switch ($action) {
    case "verify":
      // Keep result until revalidate button is pressed or session expired
      $stmt = $pdo->query("SELECT `version` FROM `versions` WHERE `application` = 'GUID'");
      $versions = $stmt->fetch(PDO::FETCH_ASSOC);
      $post = array('guid' => $versions['version']);
      $curl = curl_init('https://verify.mailcow.email');
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
      $response = curl_exec($curl);
      curl_close($curl);
      $json_return = json_decode($response, true);
      if ($response && $json_return) {
        if ($json_return['response'] === "ok") {
          $_SESSION['gal']['valid'] = "true";
          $_SESSION['gal']['c'] = $json_return['c'];
          $_SESSION['gal']['s'] = $json_return['s'];
          if ($json_return['m'] == 'NoMoore') {
            $_SESSION['gal']['m'] = '🐄';
          }
          else {
            $_SESSION['gal']['m'] = str_repeat('🐄', substr_count($json_return['m'], 'o'));
          }
        }
        elseif ($json_return['response'] === "invalid") {
          $_SESSION['gal']['valid'] = "false";
          $_SESSION['gal']['c'] = $lang['mailbox']['no'];
          $_SESSION['gal']['s'] = $lang['mailbox']['no'];
          $_SESSION['gal']['m'] = $lang['mailbox']['no'];
        }
      }
      else {
        $_SESSION['gal']['valid'] = "false";
        $_SESSION['gal']['c'] = $lang['danger']['temp_error'];
        $_SESSION['gal']['s'] = $lang['danger']['temp_error'];
        $_SESSION['gal']['m'] = $lang['danger']['temp_error'];
      }
      try {
        // json_encode needs "true"/"false" instead of true/false, to not encode it to 0 or 1
        $redis->Set('LICENSE_STATUS_CACHE', json_encode($_SESSION['gal']));
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('redis_error', $e)
        );
        return false;
      }
      return $_SESSION['gal']['valid'];
    break;
    case "guid":
      $stmt = $pdo->query("SELECT `version` FROM `versions` WHERE `application` = 'GUID'");
      $versions = $stmt->fetch(PDO::FETCH_ASSOC);
      return $versions['version'];
    break;
  }
}
function rspamd_ui($action, $data = null) {
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] =  array(
      'type' => 'danger',
      'log' => array(__FUNCTION__),
      'msg' => 'access_denied'
    );
    return false;
  }
  switch ($action) {
    case "edit":
      $rspamd_ui_pass = $data['rspamd_ui_pass'];
      $rspamd_ui_pass2 = $data['rspamd_ui_pass2'];
      if (empty($rspamd_ui_pass) || empty($rspamd_ui_pass2)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, '*', '*'),
          'msg' => 'password_empty'
        );
        return false;
      }
      if ($rspamd_ui_pass != $rspamd_ui_pass2) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, '*', '*'),
          'msg' => 'password_mismatch'
        );
        return false;
      }
      if (strlen($rspamd_ui_pass) < 6) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, '*', '*'),
          'msg' => 'rspamd_ui_pw_length'
        );
        return false;
      }
      $docker_return = docker('post', 'rspamd-mailcow', 'exec', array('cmd' => 'rspamd', 'task' => 'worker_password', 'raw' => $rspamd_ui_pass), array('Content-Type: application/json'));
      if ($docker_return_array = json_decode($docker_return, true)) {
        if ($docker_return_array['type'] == 'success') {
          $_SESSION['return'][] =  array(
            'type' => 'success',
            'log' => array(__FUNCTION__, '*', '*'),
            'msg' => 'rspamd_ui_pw_set'
          );
          return true;
        }
        else {
          $_SESSION['return'][] =  array(
            'type' => $docker_return_array['type'],
            'log' => array(__FUNCTION__, '*', '*'),
            'msg' => $docker_return_array['msg']
          );
          return false;
        }
      }
      else {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, '*', '*'),
          'msg' => 'unknown'
        );
        return false;
      }
    break;
  }
}
function cors($action, $data = null) {
  global $redis;

  switch ($action) {
    case "edit":
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $data),
          'msg' => 'access_denied'
        );
        return false;
      }

      $allowed_origins = isset($data['allowed_origins']) ? $data['allowed_origins'] : array($_SERVER['SERVER_NAME']);
      $allowed_origins = !is_array($allowed_origins) ? array_filter(array_map('trim', explode("\n", $allowed_origins))) : $allowed_origins;
      foreach ($allowed_origins as $origin) {
        if (!filter_var($origin, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && $origin != '*') {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $action, $data),
            'msg' => 'cors_invalid_origin'
          );
          return false;
        }
      }

      $allowed_methods = isset($data['allowed_methods']) ? $data['allowed_methods'] : array('GET', 'POST', 'PUT', 'DELETE');
      $allowed_methods  = !is_array($allowed_methods) ? array_map('trim', preg_split( "/( |,|;|\n)/", $allowed_methods)) : $allowed_methods;
      $available_methods = array('GET', 'POST', 'PUT', 'DELETE');
      foreach ($allowed_methods as $method) {
        if (!in_array($method, $available_methods)) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $action, $data),
            'msg' => 'cors_invalid_method'
          );
          return false;
        }
      }

      try {
        $redis->hMSet('CORS_SETTINGS', array(
          'allowed_origins' => implode(', ', $allowed_origins),
          'allowed_methods' => implode(', ', $allowed_methods)
        ));
      } catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $data),
          'msg' => array('redis_error', $e)
        );
        return false;
      }

      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $action, $data),
        'msg' => 'cors_headers_edited'
      );
      return true;
    break;
    case "get":
      try {
        $cors_settings                  = $redis->hMGet('CORS_SETTINGS', array('allowed_origins', 'allowed_methods'));
      } catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $data),
          'msg' => array('redis_error', $e)
        );
      }

      $cors_settings                    = !$cors_settings ? array('allowed_origins' => $_SERVER['SERVER_NAME'], 'allowed_methods' => 'GET, POST, PUT, DELETE') : $cors_settings;
      $cors_settings['allowed_origins'] = empty($cors_settings['allowed_origins']) ? $_SERVER['SERVER_NAME'] : $cors_settings['allowed_origins'];
      $cors_settings['allowed_methods'] = empty($cors_settings['allowed_methods']) ? 'GET, POST, PUT, DELETE, OPTION' : $cors_settings['allowed_methods'];

      return $cors_settings;
    break;
    case "set_headers":
      $cors_settings = cors('get');
      // check if requested origin is in allowed origins
      $allowed_origins = explode(', ', $cors_settings['allowed_origins']);
      $cors_settings['allowed_origins'] = $allowed_origins[0];
      if (in_array('*', $allowed_origins)){
        $cors_settings['allowed_origins'] = '*';
      } else if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        $cors_settings['allowed_origins'] = $_SERVER['HTTP_ORIGIN'];
      }
      // always allow OPTIONS for preflight request
      $cors_settings["allowed_methods"] = empty($cors_settings["allowed_methods"]) ? 'OPTIONS' : $cors_settings["allowed_methods"] . ', ' . 'OPTIONS';

      header('Access-Control-Allow-Origin: ' . $cors_settings['allowed_origins']);
      header('Access-Control-Allow-Methods: '. $cors_settings['allowed_methods']);
      header('Access-Control-Allow-Headers: Accept, Content-Type, X-Api-Key, Origin');

      // Access-Control settings requested, this is just a preflight request
      if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS' &&
        isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) &&
        isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {

        $allowed_methods = explode(', ', $cors_settings["allowed_methods"]);
        if (in_array($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'], $allowed_methods, true))
          // method allowed send 200 OK
          http_response_code(200);
        else
          // method not allowed send 405 METHOD NOT ALLOWED
          http_response_code(405);

        exit;
      }
    break;
  }
}
function getBaseURL($protocol = null) {
  // Get current server name
  $host = strtolower($_SERVER['SERVER_NAME']);

  // craft allowed server name list
  $mailcow_hostname = strtolower(getenv("MAILCOW_HOSTNAME"));
  $additional_server_names = strtolower(getenv("ADDITIONAL_SERVER_NAMES")) ?: "";
  $additional_server_names = preg_replace('/\s+/', '', $additional_server_names);
  $allowed_server_names = $additional_server_names !== "" ? explode(',', $additional_server_names) : array();
  array_push($allowed_server_names, $mailcow_hostname);

  // Fallback to MAILCOW HOSTNAME if current server name is not in allowed list
  if (!in_array($host, $allowed_server_names)) {
    $host = $mailcow_hostname;
  }

  if (!isset($protocol)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
  }
  $base_url = $protocol . '://' . $host;

  return $base_url;
}
function uuid4() {
  $data = openssl_random_pseudo_bytes(16);

  $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
  $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function identity_provider($_action = null, $_data = null, $_extra = null) {
  global $pdo;
  global $iam_provider;
  global $iam_settings;

  $data_log = $_data;
  if (isset($data_log['client_secret'])) $data_log['client_secret'] = '*';
  if (isset($data_log['access_token'])) $data_log['access_token'] = '*';

  switch ($_action) {
    case 'get':
      $settings = array();
      $stmt = $pdo->prepare("SELECT * FROM `identity_provider`;");
      $stmt->execute();
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach($rows as $row){
        switch ($row["key"]) {
          case "mappers":
          case "templates":
            $settings[$row["key"]] = json_decode($row["value"]);
          break;
          case "use_ssl":
          case "use_tls":
          case "ignore_ssl_errors":
            $settings[$row["key"]] = boolval($row["value"]);
          break;
          default:
            $settings[$row["key"]] = $row["value"];
          break;
        }
      }
      // return default client_scopes for generic-oidc if none is set
      if ($settings["authsource"] == "generic-oidc" && empty($settings["client_scopes"])){
        $settings["client_scopes"] = "openid profile email mailcow_template";
      }
      if ($_extra['hide_sensitive']){
        $settings['client_secret'] = '';
        $settings['access_token'] = '';
      }
      // return default ldap options
      if ($settings["authsource"] == "ldap"){
        $settings['use_ssl'] = !isset($settings['use_ssl']) ? false : $settings['use_ssl'];
        $settings['use_tls'] = !isset($settings['use_tls']) ? false : $settings['use_tls'];
        $settings['ignore_ssl_errors'] = !isset($settings['ignore_ssl_errors']) ? false : $settings['ignore_ssl_errors'];
      }
      return $settings;
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      if (!isset($_data['authsource'])){
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $data_log),
          'msg' => array('required_data_missing', '')
        );
        return false;
      }

      $available_authsources = array(
        "keycloak",
        "generic-oidc",
        "ldap"
      );
      $_data['authsource'] = strtolower($_data['authsource']);
      if (!in_array($_data['authsource'], $available_authsources)){
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $data_log),
          'msg' => array('invalid_authsource', $setting)
        );
        return false;
      }

      $stmt = $pdo->prepare("SELECT * FROM `mailbox`
          WHERE `authsource` != 'mailcow'
          AND `authsource` IS NOT NULL
          AND `authsource` != :authsource");
      $stmt->execute(array(':authsource' => $_data['authsource']));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if ($rows) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $data_log),
          'msg' => array('authsource_in_use', $setting)
        );
        return false;
      }

      $_data['ignore_ssl_error']  = isset($_data['ignore_ssl_error']) ? boolval($_data['ignore_ssl_error']) : false;
      switch ($_data['authsource']) {
        case "keycloak":
          $_data['server_url']        = (!empty($_data['server_url'])) ? rtrim($_data['server_url'], '/') : null;
          $_data['mailpassword_flow'] = isset($_data['mailpassword_flow']) ? intval($_data['mailpassword_flow']) : 0;
          $_data['periodic_sync']     = isset($_data['periodic_sync']) ? intval($_data['periodic_sync']) : 0;
          $_data['import_users']      = isset($_data['import_users']) ? intval($_data['import_users']) : 0;
          $_data['sync_interval']     = (!empty($_data['sync_interval'])) ? intval($_data['sync_interval']) : 15;
          $_data['sync_interval']     = $_data['sync_interval'] < 1 ? 1 : $_data['sync_interval'];
          $required_settings          = array('authsource', 'server_url', 'realm', 'client_id', 'client_secret', 'redirect_url', 'version', 'mailpassword_flow', 'periodic_sync', 'import_users', 'sync_interval', 'ignore_ssl_error');
        break;
        case "generic-oidc":
          $_data['authorize_url']     = (!empty($_data['authorize_url'])) ? $_data['authorize_url'] : null;
          $_data['token_url']         = (!empty($_data['token_url'])) ? $_data['token_url'] : null;
          $_data['userinfo_url']      = (!empty($_data['userinfo_url'])) ? $_data['userinfo_url'] : null;
          $_data['client_scopes']     = (!empty($_data['client_scopes'])) ? $_data['client_scopes'] : "openid profile email mailcow_template";
          $required_settings          = array('authsource', 'authorize_url', 'token_url', 'client_id', 'client_secret', 'redirect_url', 'userinfo_url', 'client_scopes', 'ignore_ssl_error');
        break;
        case "ldap":
          $_data['host']              = (!empty($_data['host'])) ? str_replace(" ", "", $_data['host']) : "";
          $_data['port']              = (!empty($_data['port'])) ? intval($_data['port']) : 389;
          $_data['username_field']    = (!empty($_data['username_field'])) ? strtolower($_data['username_field']) : "mail";
          $_data['attribute_field']   = (!empty($_data['attribute_field'])) ? strtolower($_data['attribute_field']) : "";
          $_data['filter']            = (!empty($_data['filter'])) ? $_data['filter'] : "";
          $_data['periodic_sync']     = isset($_data['periodic_sync']) ? intval($_data['periodic_sync']) : 0;
          $_data['import_users']      = isset($_data['import_users']) ? intval($_data['import_users']) : 0;
          $_data['use_ssl']           = isset($_data['use_ssl']) ? boolval($_data['use_ssl']) : false;
          $_data['use_tls']           = isset($_data['use_tls']) && !$_data['use_ssl'] ? boolval($_data['use_tls']) : false;
          $_data['sync_interval']     = (!empty($_data['sync_interval'])) ? intval($_data['sync_interval']) : 15;
          $_data['sync_interval']     = $_data['sync_interval'] < 1 ? 1 : $_data['sync_interval'];
          $required_settings          = array('authsource', 'host', 'port', 'basedn', 'username_field', 'filter', 'attribute_field', 'binddn', 'bindpass', 'periodic_sync', 'import_users', 'sync_interval', 'use_ssl', 'use_tls', 'ignore_ssl_error');
        break;
      }

      $pdo->beginTransaction();
      $stmt = $pdo->prepare("INSERT INTO identity_provider (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");
      // add connection settings
      foreach($required_settings as $setting){
        if (!isset($_data[$setting])){
          $_SESSION['return'][] =  array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $data_log),
            'msg' => array('required_data_missing', $setting)
          );
          $pdo->rollback();
          return false;
        }

        $stmt->bindParam(':key', $setting);
        $stmt->bindParam(':value', $_data[$setting]);
        $stmt->execute();
      }
      $pdo->commit();

      // add default template
      if (isset($_data['default_template'])) {
        $_data['default_template'] = (empty($_data['default_template'])) ? "" : $_data['default_template'];
        $stmt = $pdo->prepare("INSERT INTO identity_provider (`key`, `value`) VALUES ('default_template', :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");
        $stmt->bindParam(':value', $_data['default_template']);
        $stmt->execute();
      }

      // add mappers
      if (isset($_data['mappers']) && isset($_data['templates'])){
        $_data['mappers'] = (!is_array($_data['mappers'])) ? array($_data['mappers']) : $_data['mappers'];
        $_data['templates'] = (!is_array($_data['templates'])) ? array($_data['templates']) : $_data['templates'];

        $mappers = array_filter($_data['mappers']);
        $templates = array_filter($_data['templates']);
        if (count($mappers) == count($templates)){
          $mappers = json_encode($mappers);
          $templates = json_encode($templates);

          $stmt = $pdo->prepare("INSERT INTO identity_provider (`key`, `value`) VALUES ('mappers', :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");
          $stmt->bindParam(':value', $mappers);
          $stmt->execute();
          $stmt = $pdo->prepare("INSERT INTO identity_provider (`key`, `value`) VALUES ('templates', :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");
          $stmt->bindParam(':value', $templates);
          $stmt->execute();
        }
      }

      // delete old access_token
      $stmt = $pdo->query("INSERT INTO identity_provider (`key`, `value`) VALUES ('access_token', '') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");

      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $data_log),
        'msg' => array('object_modified', '')
      );
      return true;
    break;
    case 'test':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }

      switch ($_data['authsource']) {
        case 'keycloak':
          $url = "{$_data['server_url']}/realms/{$_data['realm']}/protocol/openid-connect/token";
          $req = http_build_query(array(
            'grant_type'    => 'client_credentials',
            'client_id'     => $_data['client_id'],
            'client_secret' => $_data['client_secret']
          ));
          $curl = curl_init();
          curl_setopt($curl, CURLOPT_URL, $url);
          curl_setopt($curl, CURLOPT_TIMEOUT, 7);
          curl_setopt($curl, CURLOPT_POST, 1);
          curl_setopt($curl, CURLOPT_POSTFIELDS, $req);
          curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          if ($_data['ignore_ssl_error'] == "1"){
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
          }
          $res = curl_exec($curl);
          $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
          curl_close ($curl);

          if ($code != 200) {
            return false;
          }
        break;
        case 'generic-oidc':
          $url = $_data['token_url'];
          $curl = curl_init();
          curl_setopt($curl, CURLOPT_URL, $url);
          curl_setopt($curl, CURLOPT_TIMEOUT, 7);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "OPTIONS");
          if ($_data['ignore_ssl_error'] == "1"){
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
          }
          $res = curl_exec($curl);
          $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
          curl_close ($curl);

          if ($code != 200) {
            return false;
          }
        break;
        case 'ldap':
          if (!$_data['host'] || !$_data['port'] || !$_data['basedn'] ||
            !$_data['binddn'] || !$_data['bindpass']){
              return false;
          }
          $_data['use_ssl'] = isset($_data['use_ssl']) ? boolval($_data['use_ssl']) : false;
          $_data['use_tls'] = isset($_data['use_tls']) && !$_data['use_ssl'] ? boolval($_data['use_tls']) : false;
          $_data['ignore_ssl_error'] = isset($_data['ignore_ssl_error']) ? boolval($_data['ignore_ssl_error']) : false;
          $options = array();
          if ($_data['ignore_ssl_error']) {
            $options[LDAP_OPT_X_TLS_REQUIRE_CERT] = LDAP_OPT_X_TLS_NEVER;
          }
          $provider = new \LdapRecord\Connection([
            'hosts'                     => explode(",", $_data['host']),
            'port'                      => $_data['port'],
            'base_dn'                   => $_data['basedn'],
            'username'                  => $_data['binddn'],
            'password'                  => $_data['bindpass'],
            'use_ssl'                   => $_data['use_ssl'],
            'use_tls'                   => $_data['use_tls'],
            'options'                   => $options
          ]);
          try {
            $provider->connect();
          } catch (Throwable $e) {
            return false;
          }
        break;
      }

      return true;
    break;
    case "delete":
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }

      $stmt = $pdo->query("SELECT * FROM `mailbox`
          WHERE `authsource` != 'mailcow'
          AND `authsource` IS NOT NULL");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if ($rows) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $data_log),
          'msg' => array('authsource_in_use', $setting)
        );
        return false;
      }

      $stmt = $pdo->query("DELETE FROM identity_provider;");

      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $data_log),
        'msg' => array('item_deleted', '')
      );
      return true;
    break;
    case "init":
      $settings = identity_provider('get');
      $provider = null;

      switch ($settings['authsource']) {
        case "keycloak":
          if ($settings['server_url'] && $settings['realm'] && $settings['client_id'] &&
            $settings['client_secret'] && $settings['redirect_url'] && $settings['version']){
            $guzzyClient = new GuzzleHttp\Client([
              'defaults' => [
                \GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => 5,
                \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => true],
                \GuzzleHttp\RequestOptions::VERIFY => !$settings['ignore_ssl_error'],
              ]
            );
            $provider = new Stevenmaguire\OAuth2\Client\Provider\Keycloak([
              'authServerUrl'         => $settings['server_url'],
              'realm'                 => $settings['realm'],
              'clientId'              => $settings['client_id'],
              'clientSecret'          => $settings['client_secret'],
              'redirectUri'           => $settings['redirect_url'],
              'version'               => $settings['version'],
              // 'encryptionAlgorithm'   => 'RS256',                             // optional
              // 'encryptionKeyPath'     => '../key.pem'                         // optional
              // 'encryptionKey'         => 'contents_of_key_or_certificate'     // optional
            ]);
            $provider->setHttpClient($guzzyClient);
          }
        break;
        case "generic-oidc":
          if ($settings['client_id'] && $settings['client_secret'] && $settings['redirect_url'] &&
            $settings['authorize_url'] && $settings['token_url'] && $settings['userinfo_url']){
            $guzzyClient = new GuzzleHttp\Client([
              'defaults' => [
                \GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => 5,
                \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => true],
                \GuzzleHttp\RequestOptions::VERIFY => !$settings['ignore_ssl_error'],
              ]
            );
            $provider = new \League\OAuth2\Client\Provider\GenericProvider([
              'clientId'                => $settings['client_id'],
              'clientSecret'            => $settings['client_secret'],
              'redirectUri'             => $settings['redirect_url'],
              'urlAuthorize'            => $settings['authorize_url'],
              'urlAccessToken'          => $settings['token_url'],
              'urlResourceOwnerDetails' => $settings['userinfo_url'],
              'scopes'                  => $settings['client_scopes']
            ]);
            $provider->setHttpClient($guzzyClient);
          }
        break;
        case "ldap":
          if ($settings['host'] && $settings['port'] && $settings['basedn'] &&
            $settings['binddn'] && $settings['bindpass']){
            $options = array();
            if ($settings['ignore_ssl_error']) {
              $options[LDAP_OPT_X_TLS_REQUIRE_CERT] = LDAP_OPT_X_TLS_NEVER;
            }
            $provider = new \LdapRecord\Connection([
              'hosts'                     => explode(",", $settings['host']),
              'port'                      => $settings['port'],
              'base_dn'                   => $settings['basedn'],
              'username'                  => $settings['binddn'],
              'password'                  => $settings['bindpass'],
              'use_ssl'                   => $settings['use_ssl'],
              'use_tls'                   => $settings['use_tls'],
              'options'                   => $options
            ]);
            try {
              $provider->connect();
            } catch (Throwable $e) {
              $provider = null;
            }
          }
        break;
      }
      return $provider;
    break;
    case "verify-sso":
      if ($iam_settings['authsource'] != 'keycloak' && $iam_settings['authsource'] != 'generic-oidc'){
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, "no OIDC provider configured"),
          'msg' => 'login_failed'
        );
        return false;
      }

      try {
        $token = $iam_provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
        $plain_token = $token->getToken();
        $plain_refreshtoken = $token->getRefreshToken();
        $info = $iam_provider->getResourceOwner($token)->toArray();
      } catch (Throwable $e) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $e->getMessage()),
          'msg' => 'login_failed'
        );
        return false;
      }
      // check if email address is given
      if (empty($info['email'])) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, 'No email address found for user'),
          'msg' => 'login_failed'
        );
        return false;
      }

      // get mapped template
      $user_template = $info['mailcow_template'];
      $mapper_key = array_search($user_template, $iam_settings['mappers']);

      // token valid, get mailbox
      $stmt = $pdo->prepare("SELECT
        mailbox.*,
        domain.active AS d_active
        FROM `mailbox`
        INNER JOIN domain on mailbox.domain = domain.domain
        WHERE `kind` NOT REGEXP 'location|thing|group'
          AND `username` = :user");
      $stmt->execute(array(':user' => $info['email']));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row){
        if (!in_array($row['authsource'], array("keycloak", "generic-oidc"))) {
          clear_session();
          $_SESSION['return'][] =  array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $info['email'], "The user's authentication source is not of type OIDC"),
            'msg' => 'login_failed'
          );
          return false;
        }
        if ($mapper_key !== false) {
          // update user
          $_SESSION['access_all_exception'] = '1';
          mailbox('edit', 'mailbox_from_template', array(
            'username' => $info['email'],
            'name' => $info['name'],
            'template' => $iam_settings['templates'][$mapper_key]
          ));
          $_SESSION['access_all_exception'] = '0';

          // get updated row
          $stmt = $pdo->prepare("SELECT
            mailbox.*,
            domain.active AS d_active
            FROM `mailbox`
            INNER JOIN domain on mailbox.domain = domain.domain
            WHERE `kind` NOT REGEXP 'location|thing|group'
              AND `username` = :user");
          $stmt->execute(array(':user' => $info['email']));
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($row['active'] != 1 || $row['d_active'] != 1) {
          clear_session();
          $_SESSION['return'][] =  array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $info['email'], 'Domain or mailbox is inactive'),
            'msg' => 'login_failed'
          );
          return false;
        }
        set_user_loggedin_session($info['email']);
        $_SESSION['iam_token'] = $plain_token;
        $_SESSION['iam_refresh_token'] = $plain_refreshtoken;
        $_SESSION['return'][] =  array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
          'msg' => array('logged_in_as', $_SESSION['mailcow_cc_username'])
        );
        return true;
      }

      if (empty($iam_settings['mappers']) || empty($user_template) || $mapper_key === false){
        if (!empty($iam_settings['default_template'])) {
          $mbox_template = $iam_settings['default_template'];
        } else {
          clear_session();
          $_SESSION['return'][] =  array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $info['email'], 'No matching attribute mapping was found'),
            'msg' => 'login_failed'
          );
          return false;
        }
      } else {
        $mbox_template = $iam_settings['templates'][$mapper_key];
      }

      // create mailbox
      $_SESSION['access_all_exception'] = '1';
      $create_res = mailbox('add', 'mailbox_from_template', array(
        'domain' => explode('@', $info['email'])[1],
        'local_part' => explode('@', $info['email'])[0],
        'name' => $info['name'],
        'authsource' => $iam_settings['authsource'],
        'template' => $mbox_template
      ));
      $_SESSION['access_all_exception'] = '0';
      if (!$create_res){
        clear_session();
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $info['email'], 'Could not create mailbox on login'),
          'msg' => 'login_failed'
        );
        return false;
      }

      // double check if mailbox and domain is active
      $stmt = $pdo->prepare("SELECT * FROM `mailbox`
      INNER JOIN domain on mailbox.domain = domain.domain
      WHERE `kind` NOT REGEXP 'location|thing|group'
        AND `mailbox`.`active`='1'
        AND `domain`.`active`='1'
        AND `username` = :user");
      $stmt->execute(array(':user' => $info['email']));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (empty($row)) {
        clear_session();
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $info['email'], 'Domain or mailbox is inactive'),
          'msg' => 'login_failed'
        );
        return false;
      }

      set_user_loggedin_session($info['email']);
      $_SESSION['iam_token'] = $plain_token;
      $_SESSION['iam_refresh_token'] = $plain_refreshtoken;
      $_SESSION['return'][] =  array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
        'msg' => array('logged_in_as', $_SESSION['mailcow_cc_username'])
      );
      return true;
    break;
    case "refresh-token":
      try {
        $token = $iam_provider->getAccessToken('refresh_token', ['refresh_token' => $_SESSION['iam_refresh_token']]);
        $plain_token = $token->getToken();
        $plain_refreshtoken = $token->getRefreshToken();
        $info = $iam_provider->getResourceOwner($token)->toArray();
      } catch (Throwable $e) {
        clear_session();
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__),
          'msg' => array('refresh_login_failed', $e->getMessage())
        );
        return false;
      }

      if (empty($info['email'])){
        clear_session();
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role']),
          'msg' => 'refresh_login_failed'
        );
        return false;
      }

      set_user_loggedin_session($info['email']);
      $_SESSION['iam_token'] = $plain_token;
      $_SESSION['iam_refresh_token'] = $plain_refreshtoken;
      return true;
    break;
    case "get-redirect":
      if ($iam_settings['authsource'] != 'keycloak' && $iam_settings['authsource'] != 'generic-oidc')
        return false;
      $authUrl = $iam_provider->getAuthorizationUrl();
      $_SESSION['oauth2state'] = $iam_provider->getState();
      return $authUrl;
    break;
    case "get-keycloak-admin-token":
      // get access_token for service account of mailcow client
      if ($iam_settings['authsource'] !== 'keycloak') return false;
      if (isset($iam_settings['access_token'])) {
        // check if access_token is valid
        $url = "{$iam_settings['server_url']}/realms/{$iam_settings['realm']}/protocol/openid-connect/token/introspect";
        $req = http_build_query(array(
          'token'    => $iam_settings['access_token'],
          'client_id'     => $iam_settings['client_id'],
          'client_secret' => $iam_settings['client_secret']
        ));
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 7);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $req);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        $res = json_decode(curl_exec($curl), true);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close ($curl);
        if ($code == 200 && $res['active'] == true) {
          // token is valid
          return $iam_settings['access_token'];
        }
      }

      $url = "{$iam_settings['server_url']}/realms/{$iam_settings['realm']}/protocol/openid-connect/token";
      $req = http_build_query(array(
        'grant_type'    => 'client_credentials',
        'client_id'     => $iam_settings['client_id'],
        'client_secret' => $iam_settings['client_secret']
      ));
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_TIMEOUT, 7);
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $req);
      curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_TIMEOUT, 5);
      $res = json_decode(curl_exec($curl), true);
      $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      curl_close ($curl);
      if ($code != 200) {
        return false;
      }

      $stmt = $pdo->prepare("INSERT INTO identity_provider (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");
      $stmt->execute(array(
        ':key' => 'access_token',
        ':value' => $res['access_token']
      ));
      return $res['access_token'];
    break;
  }
}
function reset_password($action, $data = null) {
  global $pdo;
  global $redis;
  global $mailcow_hostname;
  global $PW_RESET_TOKEN_LIMIT;
  global $PW_RESET_TOKEN_LIFETIME;

	$_data_log = $data;
  if (isset($_data_log['new_password'])) $_data_log['new_password'] = '*';
  if (isset($_data_log['new_password2'])) $_data_log['new_password2'] = '*';

  switch ($action) {
    case 'check':
      $token = $data;

      $stmt = $pdo->prepare("SELECT `t1`.`username` FROM `reset_password` AS `t1` JOIN `mailbox` AS `t2` ON `t1`.`username` = `t2`.`username` WHERE `t1`.`token` = :token AND `t1`.`created` > DATE_SUB(NOW(), INTERVAL :lifetime MINUTE) AND `t2`.`active` = 1 AND `t2`.`authsource` = 'mailcow';");
      $stmt->execute(array(
        ':token' => preg_replace('/[^a-zA-Z0-9-]/', '', $token),
        ':lifetime' => $PW_RESET_TOKEN_LIFETIME
      ));
      $return = $stmt->fetch(PDO::FETCH_ASSOC);
      return empty($return['username']) ? false : $return['username'];
    break;
    case 'issue':
      $username = $data;

      // perform cleanup
      $stmt = $pdo->prepare("DELETE FROM `reset_password` WHERE created < DATE_SUB(NOW(), INTERVAL :lifetime MINUTE);");
      $stmt->execute(array(':lifetime' => $PW_RESET_TOKEN_LIFETIME));

      if (filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }

      $pw_reset_notification = reset_password('get_notification', 'raw');
      if (!$pw_reset_notification) return false;
      if (empty($pw_reset_notification['from']) || empty($pw_reset_notification['subject'])) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'password_reset_na'
        );
        return false;
      }

      $stmt = $pdo->prepare("SELECT * FROM `mailbox`
        WHERE `username` = :username AND authsource = 'mailcow'");
      $stmt->execute(array(':username' => $username));
      $mailbox_data = $stmt->fetch(PDO::FETCH_ASSOC);

      if (empty($mailbox_data)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'password_reset_invalid_user'
        );
        return false;
      }

      $mailbox_attr = json_decode($mailbox_data['attributes'], true);
      if (empty($mailbox_attr['recovery_email']) || filter_var($mailbox_attr['recovery_email'], FILTER_VALIDATE_EMAIL) === false) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => "password_reset_invalid_user"
        );
        return false;
      }

      $stmt = $pdo->prepare("SELECT * FROM `reset_password`
        WHERE `username` = :username");
      $stmt->execute(array(':username' => $username));
      $generated_token_count = count($stmt->fetchAll(PDO::FETCH_ASSOC));
      if ($generated_token_count >= $PW_RESET_TOKEN_LIMIT) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => "reset_token_limit_exceeded"
        );
        return false;
      }

      $token = implode('-', array(
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3))),
        strtoupper(bin2hex(random_bytes(3)))
      ));

      $stmt = $pdo->prepare("INSERT INTO `reset_password` (`username`, `token`)
        VALUES (:username, :token)");
      $stmt->execute(array(
        ':username' => $username,
        ':token' => $token
      ));

      $reset_link = getBaseURL() . "/reset-password?token=" . $token;

      $request_date = new DateTime();
      $locale_date = locale_get_default();
      $date_formatter = new IntlDateFormatter(
        $locale_date,
        IntlDateFormatter::FULL,
        IntlDateFormatter::FULL
      );
      $formatted_request_date = $date_formatter->format($request_date);

      // set template vars
      // subject
      $pw_reset_notification['subject'] = str_replace('{{hostname}}', $mailcow_hostname, $pw_reset_notification['subject']);
      $pw_reset_notification['subject'] = str_replace('{{link}}', $reset_link, $pw_reset_notification['subject']);
      $pw_reset_notification['subject'] = str_replace('{{username}}', $username, $pw_reset_notification['subject']);
      $pw_reset_notification['subject'] = str_replace('{{username2}}', $mailbox_attr['recovery_email'], $pw_reset_notification['subject']);
      $pw_reset_notification['subject'] = str_replace('{{date}}', $formatted_request_date, $pw_reset_notification['subject']);
      $pw_reset_notification['subject'] = str_replace('{{token_lifetime}}', $PW_RESET_TOKEN_LIFETIME, $pw_reset_notification['subject']);
      // text
      $pw_reset_notification['text_tmpl'] = str_replace('{{hostname}}', $mailcow_hostname, $pw_reset_notification['text_tmpl']);
      $pw_reset_notification['text_tmpl'] = str_replace('{{link}}', $reset_link, $pw_reset_notification['text_tmpl']);
      $pw_reset_notification['text_tmpl'] = str_replace('{{username}}', $username, $pw_reset_notification['text_tmpl']);
      $pw_reset_notification['text_tmpl'] = str_replace('{{username2}}', $mailbox_attr['recovery_email'], $pw_reset_notification['text_tmpl']);
      $pw_reset_notification['text_tmpl'] = str_replace('{{date}}', $formatted_request_date, $pw_reset_notification['text_tmpl']);
      $pw_reset_notification['text_tmpl'] = str_replace('{{token_lifetime}}', $PW_RESET_TOKEN_LIFETIME, $pw_reset_notification['text_tmpl']);
      // html
      $pw_reset_notification['html_tmpl'] = str_replace('{{hostname}}', $mailcow_hostname, $pw_reset_notification['html_tmpl']);
      $pw_reset_notification['html_tmpl'] = str_replace('{{link}}', $reset_link, $pw_reset_notification['html_tmpl']);
      $pw_reset_notification['html_tmpl'] = str_replace('{{username}}', $username, $pw_reset_notification['html_tmpl']);
      $pw_reset_notification['html_tmpl'] = str_replace('{{username2}}', $mailbox_attr['recovery_email'], $pw_reset_notification['html_tmpl']);
      $pw_reset_notification['html_tmpl'] = str_replace('{{date}}', $formatted_request_date, $pw_reset_notification['html_tmpl']);
      $pw_reset_notification['html_tmpl'] = str_replace('{{token_lifetime}}', $PW_RESET_TOKEN_LIFETIME, $pw_reset_notification['html_tmpl']);


      $email_sent = reset_password('send_mail', array(
        "from" => $pw_reset_notification['from'],
        "to" => $mailbox_attr['recovery_email'],
        "subject" => $pw_reset_notification['subject'],
        "text" => $pw_reset_notification['text_tmpl'],
        "html" => $pw_reset_notification['html_tmpl']
      ));

      if (!$email_sent){
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => "recovery_email_failed"
        );
        return false;
      }

      list($localPart, $domainPart) = explode('@', $mailbox_attr['recovery_email']);
      if (strlen($localPart) > 1) {
        $maskedLocalPart = $localPart[0] . str_repeat('*', strlen($localPart) - 1);
      } else {
        $maskedLocalPart = "*";
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $action, $_data_log),
        'msg' => array("recovery_email_sent", $maskedLocalPart . '@' . $domainPart)
      );
      return array(
        "username" => $username,
        "issue" => "success"
      );
    break;
    case 'reset':
      $token = $data['token'];
      $new_password = $data['new_password'];
      $new_password2 = $data['new_password2'];
      $username = $data['username'];
      $check_tfa = $data['check_tfa'];

      if (!$username || !$token) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'invalid_reset_token'
        );
        return false;
      }

      # check new password
      if (!password_check($new_password, $new_password2)) {
        return false;
      }

      if ($check_tfa){
        // check for tfa authenticators
        $authenticators = get_tfa($username);
        if (isset($authenticators['additional']) && is_array($authenticators['additional']) && count($authenticators['additional']) > 0) {
          $_SESSION['pending_mailcow_cc_username'] = $username;
          $_SESSION['pending_pw_reset_token'] = $token;
          $_SESSION['pending_pw_new_password'] = $new_password;
          $_SESSION['pending_tfa_methods'] = $authenticators['additional'];
          $_SESSION['return'][] =  array(
            'type' => 'info',
            'log' => array(__FUNCTION__, $user, '*'),
            'msg' => 'awaiting_tfa_confirmation'
          );
          return false;
        }
      }

      # set new password
      $password_hashed = hash_password($new_password);
      $stmt = $pdo->prepare("UPDATE `mailbox` SET
        `password` = :password_hashed,
        `attributes` = JSON_SET(`attributes`, '$.passwd_update', NOW())
        WHERE `username` = :username AND authsource = 'mailcow'");
      $stmt->execute(array(
        ':password_hashed' => $password_hashed,
        ':username' => $username
      ));

      // perform cleanup
      $stmt = $pdo->prepare("DELETE FROM `reset_password` WHERE `username` = :username;");
      $stmt->execute(array(
        ':username' => $username
      ));

      update_sogo_static_view($username);

      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $action, $_data_log),
        'msg' => 'password_changed_success'
      );
      return true;
    break;
    case 'get_notification':
      $type = $data;

      try {
        $settings['from'] = $redis->Get('PW_RESET_FROM');
        $settings['subject'] = $redis->Get('PW_RESET_SUBJ');
        $settings['html_tmpl'] = $redis->Get('PW_RESET_HTML');
        $settings['text_tmpl'] = $redis->Get('PW_RESET_TEXT');
        if (empty($settings['html_tmpl']) && empty($settings['text_tmpl'])) {
          $settings['html_tmpl'] = file_get_contents("/tpls/pw_reset_html.tpl");
          $settings['text_tmpl'] = file_get_contents("/tpls/pw_reset_text.tpl");
        }

        if ($type != "raw") {
          $settings['html_tmpl'] = htmlspecialchars($settings['html_tmpl']);
          $settings['text_tmpl'] = htmlspecialchars($settings['text_tmpl']);
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => array('redis_error', $e)
        );
        return false;
      }

      return $settings;
    break;
    case 'send_mail':
      $from = $data['from'];
      $to = $data['to'];
      $text = $data['text'];
      $html = $data['html'];
      $subject = $data['subject'];

      if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'from_invalid'
        );
        return false;
      }
      if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'to_invalid'
        );
        return false;
      }
      if (empty($subject)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'subject_empty'
        );
        return false;
      }
      if (empty($text)) {
        $_SESSION['return'][] =  array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => 'text_empty'
        );
        return false;
      }

      ini_set('max_execution_time', 0);
      ini_set('max_input_time', 0);
      $mail = new PHPMailer;
      $mail->Timeout = 10;
      $mail->SMTPOptions = array(
        'ssl' => array(
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed' => true
        )
      );
      $mail->isSMTP();
      $mail->Host = 'postfix-mailcow';
      $mail->SMTPAuth = false;
      $mail->Port = 25;
      $mail->setFrom($from);
      $mail->Subject = $subject;
      $mail->CharSet ="UTF-8";
      if (!empty($html)) {
        $mail->Body = $html;
        $mail->AltBody = $text;
      }
      else {
        $mail->Body = $text;
      }
      $mail->XMailer = 'MooMail';
      $mail->AddAddress($to);
      if (!$mail->send()) {
        return false;
      }
      $mail->ClearAllRecipients();

      return true;
    break;
  }

  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $action, $_data_log),
      'msg' => 'access_denied'
    );
    return false;
  }

  switch ($action) {
    case 'edit_notification':
      $subject = $data['subject'];
      $from = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data['from']);

      $from = (!filter_var($from, FILTER_VALIDATE_EMAIL)) ? "" : $from;
      $subject = (empty($subject)) ? "" : $subject;
      $text = (empty($data['text_tmpl'])) ? "" : $data['text_tmpl'];
      $html = (empty($data['html_tmpl'])) ? "" : $data['html_tmpl'];

      try {
        $redis->Set('PW_RESET_FROM', $from);
        $redis->Set('PW_RESET_SUBJ', $subject);
        $redis->Set('PW_RESET_HTML', $html);
        $redis->Set('PW_RESET_TEXT', $text);
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $action, $_data_log),
          'msg' => array('redis_error', $e)
        );
        return false;
      }

      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $action, $_data_log),
        'msg' => 'saved_settings'
      );
    break;
  }
}
function clear_session(){
  session_regenerate_id(true);
  session_unset();
  session_destroy();
  session_write_close();
}
function set_user_loggedin_session($user) {
  session_regenerate_id(true);
  $_SESSION['mailcow_cc_username'] = $user;
  $_SESSION['mailcow_cc_role'] = 'user';
  $sogo_sso_pass = file_get_contents("/etc/sogo-sso/sogo-sso.pass");
  $_SESSION['sogo-sso-user-allowed'][] = $user;
  $_SESSION['sogo-sso-pass'] = $sogo_sso_pass;
  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);
}
function get_logs($application, $lines = false) {
  if ($lines === false) {
    $lines = $GLOBALS['LOG_LINES'] - 1;
  }
  elseif(is_numeric($lines) && $lines >= 1) {
    $lines = abs(intval($lines) - 1);
  }
  else {
    list ($from, $to) = explode('-', $lines);
    $from = intval($from);
    $to = intval($to);
    if ($from < 1 || $to < $from) { return false; }
  }
  global $redis;
  global $pdo;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    return false;
  }
  // SQL
  if ($application == "mailcow-ui") {
    if (isset($from) && isset($to)) {
      $stmt = $pdo->prepare("SELECT * FROM `logs` ORDER BY `id` DESC LIMIT :from, :to");
      $stmt->execute(array(
        ':from' => $from - 1,
        ':to' => $to
      ));
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else {
      $stmt = $pdo->prepare("SELECT * FROM `logs` ORDER BY `id` DESC LIMIT :lines");
      $stmt->execute(array(
        ':lines' => $lines + 1,
      ));
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (is_array($data)) {
      return $data;
    }
  }
  if ($application == "sasl") {
    if (isset($from) && isset($to)) {
      $stmt = $pdo->prepare("SELECT * FROM `sasl_log` ORDER BY `datetime` DESC LIMIT :from, :to");
      $stmt->execute(array(
        ':from' => $from - 1,
        ':to' => $to
      ));
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else {
      $stmt = $pdo->prepare("SELECT * FROM `sasl_log` ORDER BY `datetime` DESC LIMIT :lines");
      $stmt->execute(array(
        ':lines' => $lines + 1,
      ));
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (is_array($data)) {
      return $data;
    }
  }
  // Redis
  if ($application == "dovecot-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('DOVECOT_MAILLOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('DOVECOT_MAILLOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "cron-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('CRON_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('CRON_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "postfix-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('POSTFIX_MAILLOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('POSTFIX_MAILLOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "sogo-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('SOGO_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('SOGO_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "watchdog-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('WATCHDOG_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('WATCHDOG_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "acme-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('ACME_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('ACME_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "ratelimited") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('RL_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('RL_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "api-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('API_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('API_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "netfilter-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('NETFILTER_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('NETFILTER_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "autodiscover-mailcow") {
    if (isset($from) && isset($to)) {
      $data = $redis->lRange('AUTODISCOVER_LOG', $from - 1, $to - 1);
    }
    else {
      $data = $redis->lRange('AUTODISCOVER_LOG', 0, $lines);
    }
    if ($data) {
      foreach ($data as $json_line) {
        $data_array[] = json_decode($json_line, true);
      }
      return $data_array;
    }
  }
  if ($application == "rspamd-history") {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, '/var/lib/rspamd/rspamd.sock');
    if (!is_numeric($lines)) {
      list ($from, $to) = explode('-', $lines);
      curl_setopt($curl, CURLOPT_URL,"http://rspamd/history?from=" . intval($from) . "&to=" . intval($to));
    }
    else {
      curl_setopt($curl, CURLOPT_URL,"http://rspamd/history?to=" . intval($lines));
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $history = curl_exec($curl);
    if (!curl_errno($curl)) {
      $data_array = json_decode($history, true);
      curl_close($curl);
      return $data_array['rows'];
    }
    curl_close($curl);
    return false;
  }
  if ($application == "rspamd-stats") {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, '/var/lib/rspamd/rspamd.sock');
    curl_setopt($curl, CURLOPT_URL,"http://rspamd/stat");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $stats = curl_exec($curl);
    if (!curl_errno($curl)) {
      $data_array = json_decode($stats, true);
      curl_close($curl);
      return $data_array;
    }
    curl_close($curl);
    return false;
  }
  return false;
}
function getGUID() {
  if (function_exists('com_create_guid')) {
    return com_create_guid();
  }
  mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
  $charid = strtoupper(md5(uniqid(rand(), true)));
  $hyphen = chr(45);// "-"
  return substr($charid, 0, 8).$hyphen
        .substr($charid, 8, 4).$hyphen
        .substr($charid,12, 4).$hyphen
        .substr($charid,16, 4).$hyphen
        .substr($charid,20,12);
}

function cleanupJS($ignore = '', $folder = '/tmp/*.js') {
  $now = time();
  foreach (glob($folder) as $filename) {
    if(strpos($filename, $ignore) !== false) {
      continue;
    }
    if (is_file($filename)) {
      if ($now - filemtime($filename) >= 60 * 60) {
        unlink($filename);
      }
    }
  }
}

function cleanupCSS($ignore = '', $folder = '/tmp/*.css') {
  $now = time();
  foreach (glob($folder) as $filename) {
    if(strpos($filename, $ignore) !== false) {
      continue;
    }
    if (is_file($filename)) {
      if ($now - filemtime($filename) >= 60 * 60) {
        unlink($filename);
      }
    }
  }
}

?>
