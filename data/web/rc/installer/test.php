<?php

if (!class_exists('rcmail_install', false) || !is_object($RCI)) {
    die("Not allowed! Please open installer/index.php instead.");
}

?>
<form action="index.php?_step=3" method="post">

<h3>Check config file</h3>
<?php

if ($read_config = is_readable(RCUBE_CONFIG_DIR . 'defaults.inc.php')) {
  $config = $RCI->load_config_file(RCUBE_CONFIG_DIR . 'defaults.inc.php');
  if (!empty($config)) {
    $RCI->pass('defaults.inc.php');
  }
  else {
    $RCI->fail('defaults.inc.php', 'Syntax error');
  }
}
else {
  $RCI->fail('defaults.inc.php', 'Unable to read default config file?');
}
echo '<br />';

if ($read_config = is_readable(RCUBE_CONFIG_DIR . 'config.inc.php')) {
  $config = $RCI->load_config_file(RCUBE_CONFIG_DIR . 'config.inc.php');
  if (!empty($config)) {
    $RCI->pass('config.inc.php');
  }
  else {
    $RCI->fail('config.inc.php', 'Syntax error');
  }
}
else {
  $RCI->fail('config.inc.php', 'Unable to read file. Did you create the config file?');
}
echo '<br />';


if ($RCI->configured && ($messages = $RCI->check_config())) {
  if (is_array($messages['replaced'])) {
    echo '<h3 class="warning">Replaced config options</h3>';
    echo '<p class="hint">The following config options have been replaced or renamed. ';
    echo 'Please update them accordingly in your config files.</p>';

    echo '<ul class="configwarings">';
    foreach ($messages['replaced'] as $msg) {
      echo html::tag('li', null, html::span('propname', $msg['prop']) .
        ' was replaced by ' . html::span('propname', $msg['replacement']));
    }
    echo '</ul>';
  }

  if (is_array($messages['obsolete'])) {
    echo '<h3>Obsolete config options</h3>';
    echo '<p class="hint">You still have some obsolete or inexistent properties set. This isn\'t a problem but should be noticed.</p>';

    echo '<ul class="configwarings">';
    foreach ($messages['obsolete'] as $msg) {
      echo html::tag('li', null, html::span('propname', $msg['prop']) . ($msg['name'] ? ':&nbsp;' . $msg['name'] : ''));
    }
    echo '</ul>';
  }

  echo '<p class="suggestion">OK, lazy people can download the updated config file here: ';
  echo html::a(array('href' => './?_mergeconfig=1'), 'config.inc.php') . ' &nbsp;';
  echo "</p>";

  if (is_array($messages['dependencies'])) {
    echo '<h3 class="warning">Dependency check failed</h3>';
    echo '<p class="hint">Some of your configuration settings require other options to be configured or additional PHP modules to be installed</p>';

    echo '<ul class="configwarings">';
    foreach ($messages['dependencies'] as $msg) {
      echo html::tag('li', null, html::span('propname', $msg['prop']) . ': ' . $msg['explain']);
    }
    echo '</ul>';
  }
}

?>

<h3>Check if directories are writable</h3>
<p>Roundcube may need to write/save files into these directories</p>
<?php

$dirs[] = $RCI->config['temp_dir'] ? $RCI->config['temp_dir'] : 'temp';
if ($RCI->config['log_driver'] != 'syslog')
    $dirs[] = $RCI->config['log_dir'] ? $RCI->config['log_dir'] : 'logs';

foreach ($dirs as $dir) {
    $dirpath = rcube_utils::is_absolute_path($dir) ? $dir : INSTALL_PATH . $dir;
    if (is_writable(realpath($dirpath))) {
        $RCI->pass($dir);
        $pass = true;
    }
    else {
        $RCI->fail($dir, 'not writeable for the webserver');
    }
    echo '<br />';
}

if (!$pass) {
    echo '<p class="hint">Use <tt>chmod</tt> or <tt>chown</tt> to grant write privileges to the webserver</p>';
}

?>

<h3>Check DB config</h3>
<?php

$db_working = false;
if ($RCI->configured) {
    if (!empty($RCI->config['db_dsnw'])) {
        $DB = rcube_db::factory($RCI->config['db_dsnw'], '', false);
        $DB->set_debug((bool)$RCI->config['sql_debug']);
        $DB->db_connect('w');

        if (!($db_error_msg = $DB->is_error())) {
            $RCI->pass('DSN (write)');
            echo '<br />';
            $db_working = true;
        }
        else {
            $RCI->fail('DSN (write)', $db_error_msg);
            echo '<p class="hint">Make sure that the configured database exists and that the user has write privileges<br />';
            echo 'DSN: ' . $RCI->config['db_dsnw'] . '</p>';
        }
    }
    else {
        $RCI->fail('DSN (write)', 'not set');
    }
}
else {
    $RCI->fail('DSN (write)', 'Could not read config file');
}

// initialize db with schema found in /SQL/*
if ($db_working && $_POST['initdb']) {
    if (!($success = $RCI->init_db($DB))) {
        $db_working = false;
        echo '<p class="warning">Please try to inizialize the database manually as described in the INSTALL guide.
          Make sure that the configured database extists and that the user as write privileges</p>';
    }
}

else if ($db_working && $_POST['updatedb']) {
    if (!($success = $RCI->update_db($_POST['version']))) {
        echo '<p class="warning">Database schema update failed.</p>';
    }
}

// test database
if ($db_working) {
    $db_read = $DB->query("SELECT count(*) FROM " . $DB->quote_identifier($RCI->config['db_prefix'] . 'users'));
    if ($DB->is_error()) {
        $RCI->fail('DB Schema', "Database not initialized");
        echo '<p><input type="submit" name="initdb" value="Initialize database" /></p>';
        $db_working = false;
    }
    else if ($err = $RCI->db_schema_check($DB, $update = !empty($_POST['updatedb']))) {
        $RCI->fail('DB Schema', "Database schema differs");
        echo '<ul style="margin:0"><li>' . join("</li>\n<li>", $err) . "</li></ul>";
        $select = $RCI->versions_select(array('name' => 'version'));
        $select->add('0.9 or newer', '');
        echo '<p class="suggestion">You should run the update queries to get the schema fixed.<br/><br/>Version to update from: ' . $select->show() . '&nbsp;<input type="submit" name="updatedb" value="Update" /></p>';
        $db_working = false;
    }
    else {
        $RCI->pass('DB Schema');
        echo '<br />';
    }
}

// more database tests
if ($db_working) {
    // write test
    $insert_id = md5(uniqid());
    $db_write = $DB->query("INSERT INTO " . $DB->quote_identifier($RCI->config['db_prefix'] . 'session')
        . " (`sess_id`, `changed`, `ip`, `vars`) VALUES (?, ".$DB->now().", '127.0.0.1', 'foo')", $insert_id);

    if ($db_write) {
      $RCI->pass('DB Write');
      $DB->query("DELETE FROM " . $DB->quote_identifier($RCI->config['db_prefix'] . 'session')
        . " WHERE `sess_id` = ?", $insert_id);
    }
    else {
      $RCI->fail('DB Write', $RCI->get_error());
    }
    echo '<br />';

    // check timezone settings
    $tz_db = 'SELECT ' . $DB->unixtimestamp($DB->now()) . ' AS tz_db';
    $tz_db = $DB->query($tz_db);
    $tz_db = $DB->fetch_assoc($tz_db);
    $tz_db = (int) $tz_db['tz_db'];
    $tz_local = (int) time();
    $tz_diff  = $tz_local - $tz_db;

    // sometimes db and web servers are on separate hosts, so allow a 30 minutes delta
    if (abs($tz_diff) > 1800) {
        $RCI->fail('DB Time', "Database time differs {$td_ziff}s from PHP time");
    }
    else {
        $RCI->pass('DB Time');
    }
}

?>

<h3>Test filetype detection</h3>

<?php

if ($errors = $RCI->check_mime_detection()) {
  $RCI->fail('Fileinfo/mime_content_type configuration');
  if (!empty($RCI->config['mime_magic'])) {
    echo '<p class="hint">Try setting the <tt>mime_magic</tt> config option to <tt>null</tt>.</p>';
  }
  else {
    echo '<p class="hint">Check the <a href="http://www.php.net/manual/en/function.finfo-open.php">Fileinfo functions</a> of your PHP installation.<br/>';
    echo 'The path to the magic.mime file can be set using the <tt>mime_magic</tt> config option in Roundcube.</p>';
  }
}
else {
  $RCI->pass('Fileinfo/mime_content_type configuration');
  echo "<br/>";
}


if ($errors = $RCI->check_mime_extensions()) {
  $RCI->fail('Mimetype to file extension mapping');
  echo '<p class="hint">Please set a valid path to your webserver\'s mime.types file to the <tt>mime_types</tt> config option.<br/>';
  echo 'If you can\'t find such a file, download it from <a href="http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types">svn.apache.org</a>.</p>';
}
else {
  $RCI->pass('Mimetype to file extension mapping');
  echo "<br/>";
}

?>


<h3>Test SMTP config</h3>

<p>
Server: <?php echo rcube_utils::parse_host($RCI->getprop('smtp_server', 'localhost')); ?><br />
Port: <?php echo $RCI->getprop('smtp_port'); ?><br />

<?php

if ($RCI->getprop('smtp_server')) {
  $user = $RCI->getprop('smtp_user', '(none)');
  $pass = $RCI->getprop('smtp_pass', '(none)');

  if ($user == '%u') {
    $user_field = new html_inputfield(array('name' => '_smtp_user'));
    $user = $user_field->show($_POST['_smtp_user']);
  }
  if ($pass == '%p') {
    $pass_field = new html_passwordfield(array('name' => '_smtp_pass'));
    $pass = $pass_field->show();
  }

  echo "User: $user<br />";
  echo "Password: $pass<br />";
}

$from_field = new html_inputfield(array('name' => '_from', 'id' => 'sendmailfrom'));
$to_field = new html_inputfield(array('name' => '_to', 'id' => 'sendmailto'));

?>
</p>

<?php

if (isset($_POST['sendmail'])) {

  echo '<p>Trying to send email...<br />';

  $from = idn_to_ascii(trim($_POST['_from']));
  $to   = idn_to_ascii(trim($_POST['_to']));

  if (preg_match('/^' . $RCI->email_pattern . '$/i', $from) &&
      preg_match('/^' . $RCI->email_pattern . '$/i', $to)
  ) {
    $headers = array(
      'From'    => $from,
      'To'      => $to,
      'Subject' => 'Test message from Roundcube',
    );

    $body = 'This is a test to confirm that Roundcube can send email.';

    // send mail using configured SMTP server
    $CONFIG = $RCI->config;

    if (!empty($_POST['_smtp_user'])) {
      $CONFIG['smtp_user'] = $_POST['_smtp_user'];
    }
    if (!empty($_POST['_smtp_pass'])) {
      $CONFIG['smtp_pass'] = $_POST['_smtp_pass'];
    }

    $mail_object  = new Mail_mime();
    $send_headers = $mail_object->headers($headers);
    $head         = $mail_object->txtHeaders($send_headers);

    $SMTP = new rcube_smtp();
    $SMTP->connect(rcube_utils::parse_host($RCI->getprop('smtp_server')),
      $RCI->getprop('smtp_port'), $CONFIG['smtp_user'], $CONFIG['smtp_pass']);

    $status        = $SMTP->send_mail($headers['From'], $headers['To'], $head, $body);
    $smtp_response = $SMTP->get_response();

    if ($status) {
        $RCI->pass('SMTP send');
    }
    else {
        $RCI->fail('SMTP send', join('; ', $smtp_response));
    }
  }
  else {
    $RCI->fail('SMTP send', 'Invalid sender or recipient');
  }

  echo '</p>';
}

?>

<table>
<tbody>
  <tr>
    <td><label for="sendmailfrom">Sender</label></td>
    <td><?php echo $from_field->show($_POST['_from']); ?></td>
  </tr>
  <tr>
    <td><label for="sendmailto">Recipient</label></td>
    <td><?php echo $to_field->show($_POST['_to']); ?></td>
  </tr>
</tbody>
</table>

<p><input type="submit" name="sendmail" value="Send test mail" /></p>


<h3>Test IMAP config</h3>

<?php

$default_hosts = $RCI->get_hostlist();
if (!empty($default_hosts)) {
  $host_field = new html_select(array('name' => '_host', 'id' => 'imaphost'));
  $host_field->add($default_hosts);
}
else {
  $host_field = new html_inputfield(array('name' => '_host', 'id' => 'imaphost'));
}

$user_field = new html_inputfield(array('name' => '_user', 'id' => 'imapuser'));
$pass_field = new html_passwordfield(array('name' => '_pass', 'id' => 'imappass'));

?>

<table>
<tbody>
  <tr>
    <td><label for="imaphost">Server</label></td>
    <td><?php echo $host_field->show($_POST['_host']); ?></td>
  </tr>
  <tr>
    <td>Port</td>
    <td><?php echo $RCI->getprop('default_port'); ?></td>
  </tr>
    <tr>
      <td><label for="imapuser">Username</label></td>
      <td><?php echo $user_field->show($_POST['_user']); ?></td>
    </tr>
    <tr>
      <td><label for="imappass">Password</label></td>
      <td><?php echo $pass_field->show(); ?></td>
    </tr>
</tbody>
</table>

<?php

if (isset($_POST['imaptest']) && !empty($_POST['_host']) && !empty($_POST['_user'])) {

  echo '<p>Connecting to ' . rcube::Q($_POST['_host']) . '...<br />';

  $imap_host = trim($_POST['_host']);
  $imap_port = $RCI->getprop('default_port');
  $a_host    = parse_url($imap_host);

  if ($a_host['host']) {
    $imap_host = $a_host['host'];
    $imap_ssl  = (isset($a_host['scheme']) && in_array($a_host['scheme'], array('ssl','imaps','tls'))) ? $a_host['scheme'] : null;
    if (isset($a_host['port']))
      $imap_port = $a_host['port'];
    else if ($imap_ssl && $imap_ssl != 'tls' && (!$imap_port || $imap_port == 143))
      $imap_port = 993;
  }

  $imap_host = idn_to_ascii($imap_host);
  $imap_user = idn_to_ascii($_POST['_user']);

  $imap = new rcube_imap(null);
  $imap->set_options(array(
    'auth_type' => $RCI->getprop('imap_auth_type'),
    'debug'     => $RCI->getprop('imap_debug'),
    'socket_options' => $RCI->getprop('imap_conn_options'),
  ));

  if ($imap->connect($imap_host, $imap_user, $_POST['_pass'], $imap_port, $imap_ssl)) {
    $RCI->pass('IMAP connect', 'SORT capability: ' . ($imap->get_capability('SORT') ? 'yes' : 'no'));
    $imap->close();
  }
  else {
    $RCI->fail('IMAP connect', $RCI->get_error());
  }
}

?>

<p><input type="submit" name="imaptest" value="Check login" /></p>

</form>

<hr />

<p class="warning">

After completing the installation and the final tests please <b>remove</b> the whole
installer folder from the document root of the webserver or make sure that
<tt>enable_installer</tt> option in <tt>config.inc.php</tt> is disabled.<br />
<br />

These files may expose sensitive configuration data like server passwords and encryption keys
to the public. Make sure you cannot access this installer from your browser.

</p>
