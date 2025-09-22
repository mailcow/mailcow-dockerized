<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SERVER['HTTP_HOST']) || strpos($_SERVER['HTTP_HOST'], 'mta-sts.') !== 0) {
  http_response_code(404);
  exit;
}

$host = preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']);
$domain = str_replace('mta-sts.', '', $host);
$mta_sts = mailbox('get', 'mta_sts', $domain);

if (count($mta_sts) == 0 ||
    !isset($mta_sts['version']) ||
    !isset($mta_sts['mode']) ||
    !isset($mta_sts['max_age']) ||
    !isset($mta_sts['mx']) ||
    $mta_sts['active'] != 1) {
  http_response_code(404);
  exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "version: {$mta_sts['version']}\n";
echo "mode: {$mta_sts['mode']}\n";
echo "max_age: {$mta_sts['max_age']}\n";
foreach ($mta_sts['mx'] as $mx) {
  echo "mx: {$mx}\n";
}

?>
