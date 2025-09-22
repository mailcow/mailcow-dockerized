<?php

error_reporting(0);
header("Content-Type: application/xml");

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/vars.inc.php';
if(file_exists('inc/vars.local.inc.php')) {
  include_once 'inc/vars.local.inc.php';
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/sessions.inc.php';


function error_xml($error_id, $error_code, $message) {
  list($usec, $sec) = explode(' ', microtime());
  $time_string = date('H:i:s', $sec) . substr($usec, 0, strlen($usec) - 2);

  return <<<EOD
<?xml version="1.0" encoding="utf-8" ?>
<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
  <Response>
    <Error Time="$time_string" Id="$error_id">
      <ErrorCode>$error_code</ErrorCode>
      <Message>$message</Message>
      <DebugData />
    </Error>
  </Response>
</Autodiscover>
EOD;
}

function autodiscover_xml($type, $email, $displayname, $autodiscover_config) {
  $displayname = htmlspecialchars($displayname, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  $caldav_port = $autodiscover_config['caldav']['port'] != 443 ? ':' . $autodiscover_config['caldav']['port'] : '';
  $carddav_port = $autodiscover_config['carddav']['port'] != 443 ? ':' . $autodiscover_config['carddav']['port'] : '';
  $xml = '';
  $calcardav_xml = '';

  if (getenv('SKIP_SOGO') != "y") {
      $calcardav_xml .= <<<EOD

      <Protocol>
        <Type>CalDAV</Type>
        <Server>https://{$autodiscover_config['caldav']['server']}{$caldav_port}/SOGo/dav/{$email}/</Server>
        <DomainRequired>off</DomainRequired>
        <LoginName>{$email}</LoginName>
      </Protocol>
      <Protocol>
        <Type>CardDAV</Type>
        <Server>https://{$autodiscover_config['carddav']['server']}{$carddav_port}/SOGo/dav/{$email}/</Server>
        <DomainRequired>off</DomainRequired>
        <LoginName>{$email}</LoginName>
      </Protocol>
EOD;
  }

  if ($type == 'imap') {
    $xml .= <<<EOD
  <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a">
    <User>
      <DisplayName>{$displayname}</DisplayName>
    </User>
    <Account>
      <AccountType>email</AccountType>
      <Action>settings</Action>
      <Protocol>
        <Type>IMAP</Type>
        <Server>{$autodiscover_config['imap']['server']}</Server>
        <Port>{$autodiscover_config['imap']['port']}</Port>
        <DomainRequired>off</DomainRequired>
        <LoginName>{$email}</LoginName>
        <SPA>off</SPA>
        <SSL>on</SSL>
        <AuthRequired>on</AuthRequired>
      </Protocol>
      <Protocol>
        <Type>SMTP</Type>
        <Server>{$autodiscover_config['smtp']['server']}</Server>
        <Port>{$autodiscover_config['smtp']['port']}</Port>
        <DomainRequired>off</DomainRequired>
        <LoginName>{$email}</LoginName>
        <SPA>off</SPA>
        <SSL>on</SSL>
        <AuthRequired>on</AuthRequired>
        <UsePOPAuth>on</UsePOPAuth>
        <SMTPLast>off</SMTPLast>
      </Protocol>
      {$calcardav_xml}
    </Account>
  </Response>
EOD;
  }
  else if ($type == 'activesync') {
    $xml .= <<<EOD
  <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006">
    <Culture>en:en</Culture>
    <User>
      <DisplayName>{$displayname}</DisplayName>
      <EMailAddress>{$email}</EMailAddress>
    </User>
    <Action>
      <Settings>
        <Server>
        <Type>MobileSync</Type>
        <Url>{$autodiscover_config['activesync']['url']}</Url>
        <Name>{$autodiscover_config['activesync']['url']}</Name>
        </Server>
      </Settings>
    </Action>
  </Response>
EOD;
  }


  return <<<EOD
<?xml version="1.0" encoding="utf-8" ?>
<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
$xml
</Autodiscover>
EOD;
}

$default_autodiscover_config = $autodiscover_config;
$autodiscover_config = array_merge($default_autodiscover_config, $autodiscover_config);

// SQL
//$dsn = $database_type . ":host=" . $database_host . ";dbname=" . $database_name;
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $database_user, $database_pass, $opt);

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
  $_SESSION['return'][] = array(
    'type' => 'danger',
    'msg' => 'Redis: '.$e
  );

  echo error_xml("2477272013", "600", "Server Error");
  exit(0);
}


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


if(!$data) {
  try {
    $json = json_encode(
      array(
        "time" => time(),
        "ua" => $_SERVER['HTTP_USER_AGENT'],
        "user" => $_SERVER['PHP_AUTH_USER'],
        "ip" => $_SERVER['REMOTE_ADDR'],
        "service" => "Error: invalid or missing request data"
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
  }

  echo error_xml("2477272013", "600", "Invalid Request");
  exit(0);
}

try {
  $discover = new SimpleXMLElement($data);
  $email = $discover->Request->EMailAddress;
} catch (Exception $e) {
  // could not parse email address
  try {
    $json = json_encode(
      array(
        "time" => time(),
        "ua" => $_SERVER['HTTP_USER_AGENT'],
        "user" => $_SERVER['PHP_AUTH_USER'],
        "ip" => $_SERVER['REMOTE_ADDR'],
        "service" => "Error: missing email address in request data"
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
  }

  echo error_xml("2477272013", "600", "Invalid Request");
  exit(0);
}

$username = trim($email);
$displayname = $username;
try {
  $stmt = $pdo->prepare("SELECT `name` FROM `mailbox` WHERE `username`= :username");
  $stmt->execute(array(':username' => $username));
  $MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  $MailboxData = array("name" => "");
}
if (!empty($MailboxData['name'])) {
  $displayname = $MailboxData['name'];
}

try {
  $json = json_encode(
    array(
      "time" => time(),
      "ua" => $_SERVER['HTTP_USER_AGENT'],
      "user" => $_SERVER['PHP_AUTH_USER'],
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

  echo error_xml("2477272013", "600", "Server Error");
  exit(0);
}

echo autodiscover_xml($autodiscover_config['autodiscoverType'], $email, $displayname, $autodiscover_config);
exit(0);
