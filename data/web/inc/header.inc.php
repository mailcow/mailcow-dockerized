<?php

// CSS
if (preg_match("/mailbox/i", $_SERVER['REQUEST_URI'])) {
  $css_minifier->add('/web/css/site/mailbox.css');
}
if (preg_match("/admin/i", $_SERVER['REQUEST_URI'])) {
  $css_minifier->add('/web/css/site/admin.css');
}
if (preg_match("/user/i", $_SERVER['REQUEST_URI'])) {
  $css_minifier->add('/web/css/site/user.css');
}
if (preg_match("/edit/i", $_SERVER['REQUEST_URI'])) {
  $css_minifier->add('/web/css/site/edit.css');
}
if (preg_match("/(quarantine|qhandler)/i", $_SERVER['REQUEST_URI'])) {
  $css_minifier->add('/web/css/site/quarantine.css');
}
if (preg_match("/debug/i", $_SERVER['REQUEST_URI'])) {
  $css_minifier->add('/web/css/site/debug.css');
}
if ($_SERVER['REQUEST_URI'] == '/') {
  $css_minifier->add('/web/css/site/index.css');
}

$hash = $css_minifier->getDataHash();
$CSSPath = '/tmp/' . $hash . '.css';
if(!file_exists($CSSPath)) {
  $css_minifier->minify($CSSPath);
  cleanupCSS($hash);
}

$globalVariables = [
  'mailcow_hostname' => getenv('MAILCOW_HOSTNAME'),
  'mailcow_locale' => @$_SESSION['mailcow_locale'],
  'mailcow_cc_role' => @$_SESSION['mailcow_cc_role'],
  'mailcow_cc_username' => @$_SESSION['mailcow_cc_username'],
  'is_master' => preg_match('/y|yes/i', getenv('MASTER')),
  'dual_login' => @$_SESSION['dual-login'],
  'ui_texts' => $UI_TEXTS,
  'css_path' => '/cache/'.basename($CSSPath),
  'theme' => strtolower(trim($DEFAULT_THEME)),
  'logo' => customize('get', 'main_logo'),
  'available_languages' => $AVAILABLE_LANGUAGES,
  'lang' => $lang,
  'skip_sogo' => (getenv('SKIP_SOGO') == 'y'),
  'allow_admin_email_login' => (getenv('ALLOW_ADMIN_EMAIL_LOGIN') == 'n'),
  'mailcow_apps' => $MAILCOW_APPS,
  'app_links' => customize('get', 'app_links'),
  'is_root_uri' => (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) == '/'),
  'uri' => $_SERVER['REQUEST_URI'],
];

foreach ($globalVariables as $globalVariableName => $globalVariableValue) {
  $twig->addGlobal($globalVariableName, $globalVariableValue);
}
