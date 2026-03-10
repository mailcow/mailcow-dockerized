<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/vars.inc.php';
if(file_exists('inc/vars.local.inc.php')) {
  include_once 'inc/vars.local.inc.php';
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.auth.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/sessions.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.mailbox.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.ratelimit.inc.php';
$default_autodiscover_config = $autodiscover_config;
$autodiscover_config = array_merge($default_autodiscover_config, $autodiscover_config);

// Redis
$redis = new Redis();
try {
  if (!empty(getenv('REDIS_SLAVEOF_IP'))) {
    $redis->connect(getenv('REDIS_SLAVEOF_IP'), getenv('REDIS_SLAVEOF_PORT'));
  }
  else {
    $redis->connect('redis-mailcow', 6379);
  }
  $redis->auth(getenv("REDISPASS"));
}
catch (Exception $e) {
  exit;
}

error_reporting(0);

$data = trim(file_get_contents("php://input"));

if (strpos($data, 'autodiscover/outlook/responseschema') !== false) {
  $autodiscover_config['autodiscoverType'] = 'imap';
  if ($autodiscover_config['useEASforOutlook'] == 'yes' &&
    // Office for macOS does not support EAS
    strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') === false &&
    // Outlook 2013 (version 15) or higher
    preg_match('/(Outlook|Office).+1[5-9]\./', $_SERVER['HTTP_USER_AGENT'])
  ) {
    $autodiscover_config['autodiscoverType'] = 'activesync';
  }
}

if (getenv('SKIP_SOGO') == "y") {
  $autodiscover_config['autodiscoverType'] = 'imap';
}

//$dsn = $database_type . ":host=" . $database_host . ";dbname=" . $database_name;
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $database_user, $database_pass, $opt);

// Init Identity Provider
$iam_provider = identity_provider('init');
$iam_settings = identity_provider('get');

// Passwordless autodiscover - no authentication required
// Email will be extracted from the request body
$login_user = null;
$login_role = null;

header("Content-Type: application/xml");
echo '<?xml version="1.0" encoding="utf-8" ?>' . PHP_EOL;
?>
<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
<?php
if(!$data) {
  try {
    $json = json_encode(
      array(
        "time" => time(),
        "ua" => $_SERVER['HTTP_USER_AGENT'],
        "user" => "none",
        "ip" => $_SERVER['REMOTE_ADDR'],
        "service" => "Error: invalid or missing request data"
      )
    );
    $redis->lPush('AUTODISCOVER_LOG', $json);
    $redis->lTrim('AUTODISCOVER_LOG', 0, 100);
    $redis->publish("F2B_CHANNEL", "Autodiscover: Invalid request by " . $_SERVER['REMOTE_ADDR']);
    error_log("Autodiscover: Invalid request by " . $_SERVER['REMOTE_ADDR']);
  }
  catch (RedisException $e) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'msg' => 'Redis: '.$e
    );
    return false;
  }
  list($usec, $sec) = explode(' ', microtime());
?>
  <Response>
    <Error Time="<?=date('H:i:s', $sec) . substr($usec, 0, strlen($usec) - 2);?>" Id="<?=rand(1000000000, 9999999999);?>">
      <ErrorCode>600</ErrorCode>
      <Message>Invalid Request</Message>
      <DebugData />
    </Error>
  </Response>
</Autodiscover>
<?php
  exit(0);
}
try {
  $discover = new SimpleXMLElement($data);
  $email = $discover->Request->EMailAddress;
} catch (Exception $e) {
  // If parsing fails, return error
  try {
    $json = json_encode(
      array(
        "time" => time(),
        "ua" => $_SERVER['HTTP_USER_AGENT'],
        "user" => "none",
        "ip" => $_SERVER['REMOTE_ADDR'],
        "service" => "Error: could not parse email from request"
      )
    );
    $redis->lPush('AUTODISCOVER_LOG', $json);
    $redis->lTrim('AUTODISCOVER_LOG', 0, 100);
    $redis->publish("F2B_CHANNEL", "Autodiscover: Malformed XML by " . $_SERVER['REMOTE_ADDR']);
    error_log("Autodiscover: Malformed XML by " . $_SERVER['REMOTE_ADDR']);
  }
  catch (RedisException $e) {
    // Silently fail
  }
  list($usec, $sec) = explode(' ', microtime());
?>
  <Response>
    <Error Time="<?=date('H:i:s', $sec) . substr($usec, 0, strlen($usec) - 2);?>" Id="<?=rand(1000000000, 9999999999);?>">
      <ErrorCode>600</ErrorCode>
      <Message>Invalid Request</Message>
      <DebugData />
    </Error>
  </Response>
</Autodiscover>
<?php
  exit(0);
}

$username = trim((string)$email);
try {
  $stmt = $pdo->prepare("SELECT `mailbox`.`name`, `mailbox`.`active` FROM `mailbox` 
    INNER JOIN `domain` ON `mailbox`.`domain` = `domain`.`domain`
    WHERE `mailbox`.`username` = :username 
    AND `mailbox`.`active` = '1'
    AND `domain`.`active` = '1'");
  $stmt->execute(array(':username' => $username));
  $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  // Database error - return error response with complete XML
  list($usec, $sec) = explode(' ', microtime());
?>
  <Response>
    <Error Time="<?=date('H:i:s', $sec) . substr($usec, 0, strlen($usec) - 2);?>" Id="<?=rand(1000000000, 9999999999);?>">
      <ErrorCode>500</ErrorCode>
      <Message>Database Error</Message>
      <DebugData />
    </Error>
  </Response>
</Autodiscover>
<?php
  exit(0);
}

// Mailbox not found or not active - return generic error to prevent user enumeration
if (empty($MailboxData)) {
  try {
    $json = json_encode(
      array(
        "time" => time(),
        "ua" => $_SERVER['HTTP_USER_AGENT'],
        "user" => $email,
        "ip" => $_SERVER['REMOTE_ADDR'],
        "service" => "Error: mailbox not found or inactive"
      )
    );
    $redis->lPush('AUTODISCOVER_LOG', $json);
    $redis->lTrim('AUTODISCOVER_LOG', 0, 100);
    $redis->publish("F2B_CHANNEL", "Autodiscover: Invalid mailbox attempt by " . $_SERVER['REMOTE_ADDR']);
    error_log("Autodiscover: Invalid mailbox attempt by " . $_SERVER['REMOTE_ADDR']);
  }
  catch (RedisException $e) {
    // Silently fail
  }
  list($usec, $sec) = explode(' ', microtime());
?>
  <Response>
    <Error Time="<?=date('H:i:s', $sec) . substr($usec, 0, strlen($usec) - 2);?>" Id="<?=rand(1000000000, 9999999999);?>">
      <ErrorCode>600</ErrorCode>
      <Message>Invalid Request</Message>
      <DebugData />
    </Error>
  </Response>
</Autodiscover>
<?php
  exit(0);
}

if (!empty($MailboxData['name'])) {
  $displayname = $MailboxData['name'];
}
else {
  $displayname = $email;
}
try {
  $json = json_encode(
    array(
      "time" => time(),
      "ua" => $_SERVER['HTTP_USER_AGENT'],
      "user" => $email,
      "ip" => $_SERVER['REMOTE_ADDR'],
      "service" => $autodiscover_config['autodiscoverType']
    )
  );
  $redis->lPush('AUTODISCOVER_LOG', $json);
  $redis->lTrim('AUTODISCOVER_LOG', 0, 100);
}
catch (RedisException $e) {
  $_SESSION['return'][] = array(
    'type' => 'danger',
    'msg' => 'Redis: '.$e
  );
  return false;
}
if ($autodiscover_config['autodiscoverType'] == 'imap') {
?>
  <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a">
    <User>
      <DisplayName><?=htmlspecialchars($displayname, ENT_XML1 | ENT_QUOTES, 'UTF-8');?></DisplayName>
    </User>
    <Account>
      <AccountType>email</AccountType>
      <Action>settings</Action>
      <Protocol>
        <Type>IMAP</Type>
        <Server><?=$autodiscover_config['imap']['server'];?></Server>
        <Port><?=$autodiscover_config['imap']['port'];?></Port>
        <DomainRequired>off</DomainRequired>
        <LoginName><?=$email;?></LoginName>
        <SPA>off</SPA>
        <SSL>on</SSL>
        <AuthRequired>on</AuthRequired>
      </Protocol>
      <Protocol>
        <Type>SMTP</Type>
        <Server><?=$autodiscover_config['smtp']['server'];?></Server>
        <Port><?=$autodiscover_config['smtp']['port'];?></Port>
        <DomainRequired>off</DomainRequired>
        <LoginName><?=$email;?></LoginName>
        <SPA>off</SPA>
        <SSL>on</SSL>
        <AuthRequired>on</AuthRequired>
        <UsePOPAuth>on</UsePOPAuth>
        <SMTPLast>off</SMTPLast>
      </Protocol>
    <?php
    if (getenv('SKIP_SOGO') != "y") {
    ?>
      <Protocol>
        <Type>CalDAV</Type>
        <Server>https://<?=$autodiscover_config['caldav']['server'];?><?php if ($autodiscover_config['caldav']['port'] != 443) echo ':'.$autodiscover_config['caldav']['port']; ?>/SOGo/dav/<?=$email;?>/</Server>
        <DomainRequired>off</DomainRequired>
        <LoginName><?=$email;?></LoginName>
      </Protocol>
      <Protocol>
        <Type>CardDAV</Type>
        <Server>https://<?=$autodiscover_config['carddav']['server'];?><?php if ($autodiscover_config['caldav']['port'] != 443) echo ':'.$autodiscover_config['carddav']['port']; ?>/SOGo/dav/<?=$email;?>/</Server>
        <DomainRequired>off</DomainRequired>
        <LoginName><?=$email;?></LoginName>
      </Protocol>
    <?php
    }
    ?>
    </Account>
  </Response>
<?php
  }
  else if ($autodiscover_config['autodiscoverType'] == 'activesync') {
?>
  <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006">
    <Culture>en:en</Culture>
    <User>
      <DisplayName><?=htmlspecialchars($displayname, ENT_XML1 | ENT_QUOTES, 'UTF-8');?></DisplayName>
      <EMailAddress><?=$email;?></EMailAddress>
    </User>
    <Action>
      <Settings>
        <Server>
        <Type>MobileSync</Type>
        <Url><?=$autodiscover_config['activesync']['url'];?></Url>
        <Name><?=$autodiscover_config['activesync']['url'];?></Name>
        </Server>
      </Settings>
    </Action>
  </Response>
<?php
  }
?>
</Autodiscover>
