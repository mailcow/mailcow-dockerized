<?php

function mailcow_dns_absolute_name($name) {
  $name = trim($name);

  if ($name === '' || $name === '.' || substr($name, -1) === '.') {
    return $name;
  }

  return $name . '.';
}

function mailcow_dns_recommended_value($type, $value) {
  $type = strtoupper($type);

  if (in_array($type, array('CNAME', 'MX', 'PTR'), true)) {
    return mailcow_dns_absolute_name($value);
  }

  if ($type == 'SRV') {
    $parts = preg_split('/\s+/', trim($value));

    if (!empty($parts[0])) {
      $parts[0] = mailcow_dns_absolute_name($parts[0]);
    }

    return implode(' ', $parts);
  }

  return $value;
}
