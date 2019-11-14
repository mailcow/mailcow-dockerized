<?php
function presets($_action, $_data = null)
{
  if ($_SESSION['mailcow_cc_role'] !== 'admin') {
    $_SESSION['return'][] = [
      'type' => 'danger',
      'log' => [__FUNCTION__, $_action, $_data_log],
      'msg' => 'access_denied',
    ];

    return false;
  }

  global $lang;
  if ($_action === 'get') {
    $kind = strtolower(trim($_data));
    $langSection = 'admin';

    if (!in_array($kind, ['admin-rspamd', 'mailbox-sieve'], true)) {
      return [];
    }

    if ($kind === 'mailbox-sieve') {
      $langSection = 'mailbox';
    }

    $presets = [];
    foreach (glob(__DIR__ . '/presets/' . $kind . '/*.yml') as $filename) {
      $preset = Spyc::YAMLLoad($filename);

      /* get translated headlines */
      if (isset($preset['headline']) && strpos($preset['headline'], 'lang.') === 0) {
        $langTextName = trim(substr($preset['headline'], 5));
        if (isset($lang[$langSection][$langTextName])) {
          $preset['headline'] = $lang[$langSection][$langTextName];
        }
      }

      $presets[] = $preset;
    }

    return $presets;
  }

  return [];
}
