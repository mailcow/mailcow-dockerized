<?php
require_once 'inc/vars.inc.php';
require_once 'inc/functions.inc.php';
$default_autodiscover_config = $autodiscover_config;
if(file_exists('inc/vars.local.inc.php')) {
  include_once 'inc/vars.local.inc.php';
}
$autodiscover_config = array_merge($default_autodiscover_config, $autodiscover_config);

header('Content-type: application/json');

$domain_dot = strpos($_SERVER['HTTP_HOST'], '.');
$domain = substr($_SERVER['HTTP_HOST'], $domain_dot+1);

if ($_GET['Protocol'] == 'ActiveSync') {
  echo '{"Protocol":"ActiveSync","Url":"' . $autodiscover_config['activesync']['url'] . '"}';
} elseif ($_GET['Protocol'] == 'AutodiscoverV1') {
  echo '{"Protocol":"AutodiscoverV1","Url":"https://autodiscover.' . $domain . '/autodiscover/autodiscover.xml"}';
} else {
  http_response_code(400);
  echo '{"ErrorCode":"InvalidProtocol","ErrorMessage":"The given protocol value \u0027' . $_GET['Protocol'] . '\u0027 is invalid. Supported values are \u0027ActiveSync,AutodiscoverV1\u0027"}';
}
?>