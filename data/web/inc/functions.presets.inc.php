<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/Spyc.php';

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
    if (!in_array($kind, ['rspamd', 'sieve'], true)) {
      return [];
    }

    $presets = [];
    foreach (glob(__DIR__ . '/presets/' . $kind . '/*.yml') as $filename) {
      $preset = Spyc::YAMLLoad($filename);

      /* get translated headlines */
      if (isset($preset['headline']) && strpos($preset['headline'], 'lang.') === 0) {
        $textName = trim(substr($preset['headline'], 5));

        if ($kind === 'rspamd') {
          $preset['headline'] = $lang['admin'][$textName];
        } elseif ($kind === 'sieve') {
          $preset['headline'] = $lang['mailbox'][$textName];
        }
      }

      $presets[] = $preset;
    }

    return $presets;
  }

  return [];
}
