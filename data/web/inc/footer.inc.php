<?php
logger();

$hash = $js_minifier->getDataHash();
$JSPath = '/tmp/' . $hash . '.js';
if(!file_exists($JSPath)) {
  $js_minifier->minify($JSPath);
  cleanupJS($hash);
}

$alertbox_log_parser = alertbox_log_parser($_SESSION);
$alerts = [];
if (is_array($alertbox_log_parser)) {
  foreach ($alertbox_log_parser as $log) {
    $message = strtr($log['msg'], ["\n" => '', "\r" => '', "\t" => '<br>']);
    $alerts[trim($log['type'], '"')][] = trim($message, '"');
  }
  $alert = array_filter(array_unique($alerts));
  foreach($alert as $alert_type => $alert_msg) {
    // html breaks from mysql alerts, replace ` with '
    $alerts[$alert_type] = implode('<hr class="alert-hr">', str_replace("`", "'", $alert_msg));
  }
  unset($_SESSION['return']);
}

// globals
$globalVariables = [
  'mailcow_info' => array(
    'version_tag' => $GLOBALS['MAILCOW_GIT_VERSION'],
    'git_project_url' => $GLOBALS['MAILCOW_GIT_URL']
  ),
  'js_path' => '/cache/'.basename($JSPath),
  'pending_tfa_method' => @$_SESSION['pending_tfa_method'],
  'pending_mailcow_cc_username' => @$_SESSION['pending_mailcow_cc_username'],
  'lang_footer' => json_encode($lang['footer']),
  'lang_acl' => json_encode($lang['acl']),
  'lang_tfa' => json_encode($lang['tfa']),
  'lang_fido2' => json_encode($lang['fido2']),
  'docker_timeout' => $DOCKER_TIMEOUT,
  'session_lifetime' => (int)$SESSION_LIFETIME,
  'csrf_token' => $_SESSION['CSRF']['TOKEN'],
  'pagination_size' => $PAGINATION_SIZE,
  'log_pagination_size' => $LOG_PAGINATION_SIZE,
  'alerts' => $alerts,
  'totp_secret' => $tfa->createSecret(),
];

foreach ($globalVariables as $globalVariableName => $globalVariableValue) {
  $twig->addGlobal($globalVariableName, $globalVariableValue);
}

if (is_array($template_data)) {
  echo $twig->render($template, $template_data);
}

if (isset($_SESSION['mailcow_cc_api'])) {
  session_regenerate_id(true);
  session_unset();
  session_destroy();
  session_write_close();
  header("Location: /");
}
$stmt = null;
$pdo = null;
