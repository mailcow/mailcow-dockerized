<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/vars.inc.php';

error_reporting(0);
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
  $relayhost_id = intval($_GET['relayhost_id']);
  if (isset($_GET['mail_from']) && filter_var($_GET['mail_from'], FILTER_VALIDATE_EMAIL)) {
    $mail_from = $_GET['mail_from'];
  }
  else {
    $mail_from = "relay@example.org";
  }
  $relayhost_details = relayhost('details', $relayhost_id);
  if (!empty($relayhost_details)) {
    // Remove [ and ]
    $hostname_w_port = preg_replace('/\[|\]/', '', $relayhost_details['hostname']);
    // Explode to hostname and port
    list($hostname, $port) = explode(':', $hostname_w_port);
    // Use port 25 if no port was given
    $port = (empty($port)) ? 25 : $port;
    $username = $relayhost_details['username'];
    $password = $relayhost_details['password'];

    $mail = new PHPMailer;
    $mail->Timeout = 10;
    $mail->SMTPDebug = 3;
    $mail->Debugoutput = function($str, $level) {
      foreach(preg_split("/((\r?\n)|(\r\n?)|\n)/", $str) as $line){
        if (empty($line)) { continue; }
        if (preg_match("/SERVER \-\> CLIENT: 2\d\d.+/i", $line)) {
          echo '<span style="color:darkgreen;font-weight:bold">' . htmlspecialchars($line) . '</span><br>';
        }
        elseif (preg_match("/SERVER \-\> CLIENT: 3\d\d.+/i", $line)) {
          echo '<span style="color:lightgreen;font-weight:bold">' . htmlspecialchars($line) . '</span><br>';
        }
        elseif (preg_match("/SERVER \-\> CLIENT: 4\d\d.+/i", $line)) {
          echo '<span style="color:yellow;font-weight:bold">' . htmlspecialchars($line) . '</span><br>';
        }
        elseif (preg_match("/SERVER \-\> CLIENT: 5\d\d.+/i", $line)) {
          echo '<span style="color:red;font-weight:bold">' . htmlspecialchars($line) . '</span><br>';
        }
        elseif (preg_match("/CLIENT \-\> SERVER:.+/i", $line)) {
          echo '<span style="color:#999;font-weight:bold">' . htmlspecialchars($line) . '</span><br>';
        }
        elseif (preg_match("/^(?!SERVER|CLIENT|Connection:|\)).+$/i", $line)) {
          echo '<span>&nbsp;&nbsp;&nbsp;&nbsp;↪ ' . htmlspecialchars($line) . '</span><br>';
        }
        else {
          echo htmlspecialchars($line) . '<br>';
        }
      }
    };
    $mail->isSMTP();
    $mail->Host = $hostname;
    if (!empty($username)) {
      $mail->SMTPAuth = true;
      $mail->Username = $username;
      $mail->Password = $password;
    }
    $mail->Port = $port;
    $mail->setFrom($mail_from, 'Mailer');
    $mail->Subject = 'A subject for a SMTP test';
    $mail->addAddress($RELAY_TO, 'Joe Null');
    $mail->Body = 'This is our test body';
    $mail->send();
  }
  else {
    echo "Unknown relayhost.";
  }
}
else {
  echo "Permission denied.";
}