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

$raw_data = file_get_contents('php://input');
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

$filtered_rcpts = array();
foreach (json_decode($rcpts, true) as $rcpt) {
  $parsed_mail = parse_email($rcpt);
  if (in_array($parsed_mail['domain'], $exclude_domains)) {
    error_log(sprintf("Skipped domain %s", $parsed_mail['domain']));
    continue;
  }
  try {
    $stmt = $pdo->prepare("SELECT `goto` FROM `alias`
      WHERE
      (
        `address` = :rcpt
        OR
        `address` IN (
          SELECT username FROM mailbox, alias_domain
            WHERE (alias_domain.alias_domain = :domain_part
              AND mailbox.username = CONCAT(:local_part, '@', alias_domain.target_domain)
              AND mailbox.active = '1'
              AND alias_domain.active='1')
        )
      )
      AND `active`= '1';");
    $stmt->execute(array(
      ':rcpt' => $rcpt,
      ':local_part' => $parsed_mail['local'],
      ':domain_part' => $parsed_mail['domain']
    ));
    $gotos = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
    if (!empty($gotos)) {
      $filtered_rcpts  = array_unique(array_merge($filtered_rcpts, explode(',', $gotos)));
    }
  }
  catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(502);
    exit;
  }
}
foreach ($filtered_rcpts as $rcpt) {
  
  try {
    $stmt = $pdo->prepare("INSERT INTO `quarantaine` (`qid`, `score`, `sender`, `rcpt`, `symbols`, `user`, `ip`, `msg`, `action`)
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
    $stmt = $pdo->prepare('DELETE FROM `quarantaine` WHERE `id` NOT IN ( 
      SELECT `id`
      FROM (
        SELECT `id`
        FROM `quarantaine`
        WHERE `rcpt` = :rcpt
        ORDER BY id DESC
        LIMIT :retention_size
      ) x 
    );');
    $stmt->execute(array(
      ':rcpt' => $rcpt,
      ':retention_size' => $retention_size
    ));
  }
  catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit;
  }
}

