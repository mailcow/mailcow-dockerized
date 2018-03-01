<?php
function fail2ban($_action, $_data = null) {
  global $redis;
  global $lang;
  switch ($_action) {
    case 'get':
      $f2b_options = array();
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      try {
        $f2b_options = json_decode($redis->Get('F2B_OPTIONS'), true);
        $wl = $redis->hGetAll('F2B_WHITELIST');
        if (is_array($wl)) {
          foreach ($wl as $key => $value) {
            $tmp_data[] = $key;
          }
          if (isset($tmp_data)) {
            $f2b_options['whitelist'] = implode(PHP_EOL, $tmp_data);
          }
          else {
            $f2b_options['whitelist'] = "";
          }
        }
        else {
          $f2b_options['whitelist'] = "";
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
      return $f2b_options;
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $is_now = fail2ban('get');
      if (!empty($is_now)) {
        $ban_time = intval((isset($_data['ban_time'])) ? $_data['ban_time'] : $is_now['ban_time']);
        $max_attempts = intval((isset($_data['max_attempts'])) ? $_data['max_attempts'] : $is_now['active_int']);
        $retry_window = intval((isset($_data['retry_window'])) ? $_data['retry_window'] : $is_now['retry_window']);
        $netban_ipv4 = intval((isset($_data['netban_ipv4'])) ? $_data['netban_ipv4'] : $is_now['netban_ipv4']);
        $netban_ipv6 = intval((isset($_data['netban_ipv6'])) ? $_data['netban_ipv6'] : $is_now['netban_ipv6']);
      }
      else {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $wl = $_data['whitelist'];
      $f2b_options = array();
      $f2b_options['ban_time'] = ($ban_time < 60) ? 60 : $ban_time;
      $f2b_options['netban_ipv4'] = ($netban_ipv4 < 8) ? 8 : $netban_ipv4;
      $f2b_options['netban_ipv6'] = ($netban_ipv6 < 8) ? 8 : $netban_ipv6;
      $f2b_options['netban_ipv4'] = ($netban_ipv4 > 32) ? 32 : $netban_ipv4;
      $f2b_options['netban_ipv6'] = ($netban_ipv6 > 128) ? 128 : $netban_ipv6;
      $f2b_options['max_attempts'] = ($max_attempts < 1) ? 1 : $max_attempts;
      $f2b_options['retry_window'] = ($retry_window < 1) ? 1 : $retry_window;
      try {
        $redis->Set('F2B_OPTIONS', json_encode($f2b_options));
        $redis->Del('F2B_WHITELIST');
        if(!empty($wl)) {
          $wl_array = array_map('trim', preg_split( "/( |,|;|\n)/", $wl));
          if (is_array($wl_array)) {
            foreach ($wl_array as $wl_item) {
              $cidr = explode('/', $wl_item);
              if (filter_var($cidr[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (!isset($cidr[1]) || ($cidr[1] >= 0 && $cidr[1] <= 32))) {
                $redis->hSet('F2B_WHITELIST', $wl_item, 1);
              }
              elseif (filter_var($cidr[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && (!isset($cidr[1]) || ($cidr[1] >= 0 && $cidr[1] <= 128))) {
                $redis->hSet('F2B_WHITELIST', $wl_item, 1);
              }
            }
          }
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['f2b_modified'])
      );
    break;
  }
}
