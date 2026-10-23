<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (!isset($_SERVER['HTTP_HOST']) || strpos($_SERVER['HTTP_HOST'], 'mta-sts.') !== 0) {
  http_response_code(404);
  exit;
}

$host = preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']);
$domain = idn_to_ascii(strtolower(str_replace('mta-sts.', '', $host)), 0, INTL_IDNA_VARIANT_UTS46);

// Validate domain or return 404 on error
if ($domain === false || empty($domain)) {
  http_response_code(404);
  exit;
}

// Check if domain is an alias domain and resolve to target domain
try {
  $stmt = $pdo->prepare("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain");
  $stmt->execute(array(':domain' => $domain));
  $alias_row = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if ($alias_row !== false && !empty($alias_row['target_domain'])) {
    // This is an alias domain, use the target domain for MTA-STS lookup
    $domain = $alias_row['target_domain'];
  }
} catch (PDOException $e) {
  // On database error, return 404
  http_response_code(404);
  exit;
}

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
