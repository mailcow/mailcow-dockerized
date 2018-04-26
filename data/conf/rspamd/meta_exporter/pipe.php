<?php
// File size is limited by Nginx site to 10M
// To speed things up, we do not include prerequisites
header('Content-Type: text/plain');
require_once "vars.inc.php";
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
// Init database
$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
  http_response_code(501);
  exit;
}
// Init Redis
$redis = new Redis();
$redis->connect('redis-mailcow', 6379);

// Functions
function parse_email($email) {
  if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
  $a = strrpos($email, '@');
  return array('local' => substr($email, 0, $a), 'domain' => substr(substr($email, $a), 1));
}
if (!function_exists('getallheaders'))  {
  function getallheaders() {
    if (!is_array($_SERVER)) {
      return array();
    }
    $headers = array();
    foreach ($_SERVER as $name => $value) {
      if (substr($name, 0, 5) == 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
      }
    }
    return $headers;
  }
}

$raw_data_content = file_get_contents('php://input');
$raw_data = mb_convert_encoding($raw_data_content, 'HTML-ENTITIES', "UTF-8");
$headers = getallheaders();

$qid      = $headers['X-Rspamd-Qid'];
$score    = $headers['X-Rspamd-Score'];
$rcpts    = $headers['X-Rspamd-Rcpt'];
$user     = $headers['X-Rspamd-User'];
$ip       = $headers['X-Rspamd-Ip'];
$action   = $headers['X-Rspamd-Action'];
$sender   = $headers['X-Rspamd-From'];
$symbols  = $headers['X-Rspamd-Symbols'];

$raw_size = (int)$_SERVER['CONTENT_LENGTH'];

try {
  if ($max_size = $redis->Get('Q_MAX_SIZE')) {
    if (!empty($max_size) && ($max_size * 1048576) < $raw_size) {
      error_log(sprintf("Message too large: %d exceeds %d", $raw_size, ($max_size * 1048576)));
      http_response_code(505);
      exit;
    }
  }
  if ($exclude_domains = $redis->Get('Q_EXCLUDE_DOMAINS')) {
    $exclude_domains = json_decode($exclude_domains, true);
  }
  $retention_size = (int)$redis->Get('Q_RETENTION_SIZE');
}
catch (RedisException $e) {
  error_log($e);
  http_response_code(504);
  exit;
}

$rcpt_final_mailboxes = array();

// Loop through all rcpts
foreach (json_decode($rcpts, true) as $rcpt) {
  // Break rcpt into local part and domain part
  $parsed_rcpt = parse_email($rcpt);
  
  // Skip if not a mailcow handled domain
  try {
    if (!$redis->hGet('DOMAIN_MAP', $parsed_rcpt['domain'])) {
      continue;
    }
  }
  catch (RedisException $e) {
    error_log($e);
    http_response_code(504);
    exit;
  }

  // Skip if domain is excluded
  if (in_array($parsed_rcpt['domain'], $exclude_domains)) {
    error_log(sprintf("Skipped domain %s", $parsed_rcpt['domain']));
    continue;
  }

  // Always assume rcpt is not a final mailbox but an alias for a mailbox or further aliases
  //
  //             rcpt
  //              |
  // mailbox <-- goto ---> alias1, alias2, mailbox2
  //                          |       |
  //                      mailbox3    |
  //                                  |
  //                               alias3 ---> mailbox4
  //
  try {
    $stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :rcpt AND `active` = '1'");
    $stmt->execute(array(
      ':rcpt' => $rcpt
    ));
    $gotos = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
    if (empty($gotos)) {
      $stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :rcpt AND `active` = '1'");
      $stmt->execute(array(
        ':rcpt' => '@' . $parsed_rcpt['domain']
      ));
      $gotos = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
    }
    $gotos_array = explode(',', $gotos);

    $loop_c = 0;

    while (count($gotos_array) != 0 && $loop_c <= 20) {

      // Loop through all found gotos
      foreach ($gotos_array as $index => &$goto) {
        error_log("quarantine pipe: query " . $goto . " as username from mailbox");
        $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :goto AND `active`= '1';");
        $stmt->execute(array(':goto' => $goto));
        $username = $stmt->fetch(PDO::FETCH_ASSOC)['username'];
        if (!empty($username)) {
          error_log("quarantine pipe: mailbox found: " . $username);
          // Current goto is a mailbox, save to rcpt_final_mailboxes if not a duplicate
          if (!in_array($username, $rcpt_final_mailboxes)) {
            $rcpt_final_mailboxes[] = $username;
          }
        }
        else {
          $parsed_goto = parse_email($goto);
          if (!$redis->hGet('DOMAIN_MAP', $parsed_goto['domain'])) {
            error_log($goto . " is not a mailcow handled mailbox or alias address");
          }
          else {
            $stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :goto AND `active` = '1'");
            $stmt->execute(array(':goto' => $goto));
            $goto_branch = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
            error_log("quarantine pipe: goto address " . $goto . " is a alias branch for " . $goto_branch);
            $goto_branch_array = explode(',', $goto_branch);
          }
        }
        // goto item was processed, unset
        unset($gotos_array[$index]);
      }

      // Merge goto branch array derived from previous loop (if any), filter duplicates and unset goto branch array
      if (!empty($goto_branch_array)) {
        $gotos_array = array_unique(array_merge($gotos_array, $goto_branch_array));
        unset($goto_branch_array);
      }

      // Reindex array
      $gotos_array = array_values($gotos_array);

      // Force exit if loop cannot be solved
      // Postfix does not allow for alias loops, so this should never happen.
      $loop_c++;
      error_log("quarantine pipe: goto array count on loop #". $loop_c . " is " . count($gotos_array));
    }
  }
  catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(502);
    exit;
  }
}

foreach ($rcpt_final_mailboxes as $rcpt) {
  error_log("quarantine pipe: processing quarantine message for rcpt " . $rcpt);
  try {
    $stmt = $pdo->prepare("INSERT INTO `quarantine` (`qid`, `score`, `sender`, `rcpt`, `symbols`, `user`, `ip`, `msg`, `action`)
      VALUES (:qid, :score, :sender, :rcpt, :symbols, :user, :ip, :msg, :action)");
    $stmt->execute(array(
      ':qid' => $qid,
      ':score' => $score,
      ':sender' => $sender,
      ':rcpt' => $rcpt,
      ':symbols' => $symbols,
      ':user' => $user,
      ':ip' => $ip,
      ':msg' => $raw_data,
      ':action' => $action
    ));
    $stmt = $pdo->prepare('DELETE FROM `quarantine` WHERE `rcpt` = :rcpt AND `id` NOT IN (
      SELECT `id`
      FROM (
        SELECT `id`
        FROM `quarantine`
        WHERE `rcpt` = :rcpt2
        ORDER BY id DESC
        LIMIT :retention_size
      ) x 
    );');
    $stmt->execute(array(
      ':rcpt' => $rcpt,
      ':rcpt2' => $rcpt,
      ':retention_size' => $retention_size
    ));
  }
  catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit;
  }
}

