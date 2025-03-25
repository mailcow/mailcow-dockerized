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

$mailcow_apps_processed = $MAILCOW_APPS;
$app_links = customize('get', 'app_links');
$app_links_processed = $app_links;
$hide_mailcow_apps = true;
for ($i = 0; $i < count($mailcow_apps_processed); $i++) {
  if ($hide_mailcow_apps && !$mailcow_apps_processed[$i]['hide']){
    $hide_mailcow_apps = false;
  }
  if (!empty($_SESSION['mailcow_cc_username'])){
    if ($app_links_processed[$i]['user_link']) {
      $mailcow_apps_processed[$i]['user_link'] = str_replace('%u', $_SESSION['mailcow_cc_username'], $mailcow_apps_processed[$i]['user_link']);
    } else {
      $mailcow_apps_processed[$i]['user_link'] = $mailcow_apps_processed[$i]['link'];
    }
  }
}
if ($app_links_processed){
  for ($i = 0; $i < count($app_links_processed); $i++) {
    $key = array_key_first($app_links_processed[$i]);
    if ($hide_mailcow_apps && !$app_links_processed[$i][$key]['hide']){
      $hide_mailcow_apps = false;
    }
    if (!empty($_SESSION['mailcow_cc_username'])){
      if ($app_links_processed[$i][$key]['user_link']) {
        $app_links_processed[$i][$key]['user_link'] = str_replace('%u', $_SESSION['mailcow_cc_username'], $app_links_processed[$i][$key]['user_link']);
      } else {
        $app_links_processed[$i][$key]['user_link'] = $app_links_processed[$i][$key]['link'];
      }
    }
  }
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
  'logo' => customize('get', 'main_logo'),
  'logo_dark' => customize('get', 'main_logo_dark'),
  'available_languages' => $AVAILABLE_LANGUAGES,
  'lang' => $lang,
  'skip_sogo' => (getenv('SKIP_SOGO') == 'y'),
  'allow_admin_email_login' => (getenv('ALLOW_ADMIN_EMAIL_LOGIN') == 'n'),
  'hide_mailcow_apps' => $hide_mailcow_apps,
  'mailcow_apps' => $MAILCOW_APPS,
  'mailcow_apps_processed' => $mailcow_apps_processed,
  'app_links' => $app_links,
  'app_links_processed' => $app_links_processed,
  'is_root_uri' => (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) == '/'),
  'uri' => $_SERVER['REQUEST_URI'],
];

foreach ($globalVariables as $globalVariableName => $globalVariableValue) {
  $twig->addGlobal($globalVariableName, $globalVariableValue);
}
