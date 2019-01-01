<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/vars.inc.php';

error_reporting(0);
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
  $transport_id = intval($_GET['transport_id']);
  $transport_type = $_GET['transport_type'];
  if (isset($_GET['mail_from']) && filter_var($_GET['mail_from'], FILTER_VALIDATE_EMAIL)) {
    $mail_from = $_GET['mail_from'];
  }
  else {
    $mail_from = "relay@example.org";
  }
  if ($transport_type == 'transport-map') {
    $transport_details = transport('details', $transport_id);
    $nexthop = $transport_details['nexthop'];
  }
  elseif ($transport_type == 'sender-dependent') {
    $transport_details = relayhost('details', $transport_id);
    $nexthop = $transport_details['hostname'];
  }
  if (!empty($transport_details)) {
    // Remove [ and ]
    $hostname_w_port = preg_replace('/\[|\]/', '', $nexthop);
    $skip_lookup_mx = strpos($nexthop, '[');
    // Explode to hostname and port
    list($hostname, $port) = explode(':', $hostname_w_port);
    // Try to get MX if host is not [host]
    if ($skip_lookup_mx === false) {
      getmxrr($hostname, $mx_records, $mx_weight);
      if (!empty($mx_records)) {
        for ($i = 0; $i < count($mx_records); $i++) {
          $mxs[$mx_records[$i]] = $mx_weight[$i];
        }
        asort ($mxs);
        $records = array_keys($mxs);
        echo 'Using first matched primary MX for "' . $hostname . '": ';
        $hostname = $records[0];
        echo $hostname . '<br>';
      }
      else {
        echo 'No MX records for ' . $hostname . ' were found in DNS, skipping and using hostname as next-hop.<br>';
      }
    }
    // Use port 25 if no port was given
    $port = (empty($port)) ? 25 : $port;
    $username = $transport_details['username'];
    $password = $transport_details['password'];

    $mail = new PHPMailer;
    $mail->Timeout = 10;
    $mail->SMTPOptions = array(
      'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
      )
    );
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
    echo "Unknown transport.";
  }
}
else {
  echo "Permission denied.";
}
