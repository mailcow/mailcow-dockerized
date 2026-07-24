<?php
// File size is limited by Nginx site to 10M
// To speed things up, we do not include prerequisites
header('Content-Type: text/plain');
require_once "vars.inc.php";
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
// Init database
//$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
  error_log("NOTIFY: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}
// Init Redis
$redis = new Redis();
$redis->connect('redis-mailcow', 6379);
$redis->auth(getenv("REDISPASS"));

// Functions
function parse_email($email) {
  if(!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
  $a = strrpos($email, '@');
  return array('local' => substr($email, 0, $a), 'domain' => substr(substr($email, $a), 1));
}
// rspamd metadata_exporter (multipart formatter): metadata JSON arrives as $_POST['metadata'].
if (empty($_POST['metadata'])) {
  error_log("NOTIFY: missing metadata part from rspamd" . PHP_EOL);
  http_response_code(400);
  exit;
}

$meta = json_decode($_POST['metadata'], true);
if (!is_array($meta)) {
  error_log("NOTIFY: cannot decode metadata JSON" . PHP_EOL);
  http_response_code(400);
  exit;
}

$qid      = $meta['qid']    ?? 'unknown';
$rcpts    = $meta['rcpt']   ?? array();
$sender   = $meta['from']   ?? '';
$ip       = $meta['ip']     ?? 'unknown';
$subject  = iconv_mime_decode($meta['subject'] ?? '');
$messageid= $meta['message_id'] ?? '';
$priority = 0;

$symbols_array = $meta['symbols'] ?? array();
if (is_array($symbols_array)) {
  foreach ($symbols_array as $symbol) {
    if (($symbol['name'] ?? null) == 'HAS_X_PRIO_ONE') {
      $priority = 1;
      break;
    }
  }
}

$sender_address = $meta['header_from'][0] ?? '';
$sender_name = '-';
if (preg_match('/(?<name>.*?)<(?<address>.*?)>/i', $sender_address, $matches)) {
	$sender_address = $matches['address'];
  $sender_name =  trim($matches['name'], '"\' ');
}

$to_address = $meta['header_to'][0] ?? '';
$to_name = '-';
if (preg_match('/(?<name>.*?)<(?<address>.*?)>/i', $to_address, $matches)) {
	$to_address = $matches['address'];
  $to_name =  trim($matches['name'], '"\' ');
}

$rcpt_final_mailboxes = array();

// Loop through all rcpts
foreach ($rcpts as $rcpt) {
  // Remove tag
  $rcpt = preg_replace('/^(.*?)\+.*(@.*)$/', '$1$2', $rcpt);

  // Break rcpt into local part and domain part
  $parsed_rcpt = parse_email($rcpt);

  // Skip if not a mailcow handled domain
  try {
    if (!$redis->hGet('DOMAIN_MAP', $parsed_rcpt['domain'])) {
      continue;
    }
  }
  catch (RedisException $e) {
    error_log("NOTIFY: " . $e . PHP_EOL);
    http_response_code(504);
    exit;
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
    if (empty($gotos)) {
      $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :rcpt AND `active` = '1'");
      $stmt->execute(array(':rcpt' => $parsed_rcpt['domain']));
      $goto_branch = $stmt->fetch(PDO::FETCH_ASSOC)['target_domain'];
      if ($goto_branch) {
        $gotos = $parsed_rcpt['local'] . '@' . $goto_branch;
      }
    }
    $gotos_array = explode(',', $gotos);

    $loop_c = 0;

    while (count($gotos_array) != 0 && $loop_c <= 20) {

      // Loop through all found gotos
      foreach ($gotos_array as $index => &$goto) {
        error_log("RCPT RESOVLER: http pipe: query " . $goto . " as username from mailbox" . PHP_EOL);
        $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :goto AND (`active`= '1' OR `active`= '2');");
        $stmt->execute(array(':goto' => $goto));
        $username = $stmt->fetch(PDO::FETCH_ASSOC)['username'];
        if (!empty($username)) {
          error_log("RCPT RESOVLER: http pipe: mailbox found: " . $username . PHP_EOL);
          // Current goto is a mailbox, save to rcpt_final_mailboxes if not a duplicate
          if (!in_array($username, $rcpt_final_mailboxes)) {
            $rcpt_final_mailboxes[] = $username;
          }
        }
        else {
          $parsed_goto = parse_email($goto);
          if (!$redis->hGet('DOMAIN_MAP', $parsed_goto['domain'])) {
            error_log("RCPT RESOVLER:" . $goto . " is not a mailcow handled mailbox or alias address" . PHP_EOL);
          }
          else {
            $stmt = $pdo->prepare("SELECT `goto` FROM `alias` WHERE `address` = :goto AND `active` = '1'");
            $stmt->execute(array(':goto' => $goto));
            $goto_branch = $stmt->fetch(PDO::FETCH_ASSOC)['goto'];
            if ($goto_branch) {
              error_log("RCPT RESOVLER: http pipe: goto address " . $goto . " is an alias branch for " . $goto_branch . PHP_EOL);
              $goto_branch_array = explode(',', $goto_branch);
            } else {
              $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain AND `active` = '1'");
              $stmt->execute(array(':domain' => $parsed_goto['domain']));
              $goto_branch = $stmt->fetch(PDO::FETCH_ASSOC)['target_domain'];
              if ($goto_branch) {
                error_log("RCPT RESOVLER: http pipe: goto domain " . $parsed_goto['domain'] . " is a domain alias branch for " . $goto_branch . PHP_EOL);
                $goto_branch_array = array($parsed_goto['local'] . '@' . $goto_branch);
              }
            }
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
      error_log("RCPT RESOVLER: http pipe: goto array count on loop #". $loop_c . " is " . count($gotos_array) . PHP_EOL);
    }
  }
  catch (PDOException $e) {
    error_log("RCPT RESOVLER: " . $e->getMessage() . PHP_EOL);
    http_response_code(502);
    exit;
  }
}


foreach ($rcpt_final_mailboxes as $rcpt_final) {
  error_log("NOTIFY: pushover pipe: processing pushover message for rcpt " . $rcpt_final . PHP_EOL);
  $stmt = $pdo->prepare("SELECT * FROM `pushover`
    WHERE `username` = :username AND `active` = '1'");
  $stmt->execute(array(
    ':username' => $rcpt_final
  ));
  $api_data = $stmt->fetch(PDO::FETCH_ASSOC);
  if (isset($api_data['key']) && isset($api_data['token'])) {
    $title = (!empty($api_data['title'])) ? $api_data['title'] : 'Mail';
    $text = (!empty($api_data['text'])) ? $api_data['text'] : 'You\'ve got mail 📧';
    $attributes = json_decode($api_data['attributes'], true);
    $senders = explode(',', $api_data['senders']);
    $senders = array_filter($senders);
    $senders_regex = $api_data['senders_regex'];
    $sender_validated = false;
    if (empty($senders) && empty($senders_regex)) {
      $sender_validated = true;
    }
    else {
      if (!empty($senders)) {
        if (in_array($sender, $senders)) {
          $sender_validated = true;
        }
      }
      if (!empty($senders_regex) && $sender_validated !== true) {
        if (preg_match($senders_regex, $sender)) {
          $sender_validated = true;
        }
      }
    }
    if ($sender_validated === false) {
      error_log("NOTIFY: pushover pipe: skipping unwanted sender " . $sender);
      continue;
    }
    if ($attributes['only_x_prio'] == "1" && $priority == 0) {
      error_log("NOTIFY: pushover pipe: mail has no X-Priority: 1 header, skipping");
      continue;
    }
    $post_fields = array(
      "token" => $api_data['token'],
      "user" => $api_data['key'],
      "title" => sprintf("%s", str_replace(
        array('{SUBJECT}', '{SENDER}', '{SENDER_NAME}', '{SENDER_ADDRESS}', '{TO_NAME}', '{TO_ADDRESS}', '{MSG_ID}'),
        array($subject, $sender, $sender_name, $sender_address, $to_name, $to_address, $messageid), $title)
      ),
      "priority" => $priority,
      "message" => sprintf("%s", str_replace(
        array('{SUBJECT}', '{SENDER}', '{SENDER_NAME}', '{SENDER_ADDRESS}', '{TO_NAME}', '{TO_ADDRESS}', '{MSG_ID}', '\n'),
        array($subject, $sender, $sender_name, $sender_address, $to_name, $to_address, $messageid, PHP_EOL), $text)
      ),
      "sound" => $attributes['sound'] ?? "pushover"
    );
    if ($attributes['evaluate_x_prio'] == "1" && $priority == 1) {
      $post_fields['expire'] = 600;
      $post_fields['retry'] = 120;
      $post_fields['priority'] = 2;
    }
    curl_setopt_array($ch = curl_init(), array(
      CURLOPT_URL => "https://api.pushover.net/1/messages.json",
      CURLOPT_POSTFIELDS => $post_fields,
      CURLOPT_SAFE_UPLOAD => true,
      CURLOPT_RETURNTRANSFER => true,
    ));
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log("NOTIFY: result: " . $httpcode . PHP_EOL);
  }
}
