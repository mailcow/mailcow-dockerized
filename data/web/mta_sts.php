<?php
error_reporting(0);
header('Content-Type: text/plain');

echo $_SERVER['HTTP_HOST'];

foreach (dns_get_record('mailcow.email', DNS_MX) as $mx_r) {
  $mx_s[] = $mx_r['target'];
}

!empty($mx_s) ?: exit();

echo 'version: STSv1' . PHP_EOL;
echo 'mode: enforce' . PHP_EOL;
foreach ($mx_s as $mx_r) {
  printf('mx: %s' . PHP_EOL, $mx_r);
}
echo 'max_age: 86400' . PHP_EOL;
