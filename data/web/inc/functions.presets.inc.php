<?php
function presets($_action, $_kind, $_object)
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
    $kind = strtolower(trim($_kind));
    $langSection = 'admin';
    $presetsPath = __DIR__ . '/presets/' . $kind;

    if (!in_array($kind, ['admin-rspamd', 'mailbox-sieve'], true)) {
      return [];
    }

    if ($kind === 'mailbox-sieve') {
      $langSection = 'mailbox';
    }

    if ($_object !== 'all') {
      return getPresetFromFilePath($presetsPath . '/' . $_object . '.yml', $langSection);
    }

    $presets = [];
    foreach (glob($presetsPath . '/*.yml') as $filename) {
      $presets[] = getPresetFromFilePath($filename, $langSection);
    }

    return $presets;
  }

  return [];
}

function getPresetFromFilePath($filePath, $langSection)
{
  global $lang;
  $preset = Spyc::YAMLLoad($filePath);
  $preset = ['name' => basename($filePath, '.yml')] + $preset;

  /* get translated headlines */
  if (isset($preset['headline']) && strpos($preset['headline'], 'lang.') === 0) {
    $langTextName = trim(substr($preset['headline'], 5));
    if (isset($lang[$langSection][$langTextName])) {
      $preset['headline'] = $lang[$langSection][$langTextName];
    }
  }
  return $preset;
}
