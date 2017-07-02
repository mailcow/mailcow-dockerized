<?php
function fail2ban($_action, $_data = null) {
  global $redis;
  global $lang;
  switch ($_action) {
    case 'get':
      $data = array();
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      try {
        $data['ban_time'] = $redis->Get('F2B_BAN_TIME');
        $data['max_attempts'] = $redis->Get('F2B_MAX_ATTEMPTS');
        $data['retry_window'] = $redis->Get('F2B_RETRY_WINDOW');
        $wl = $redis->hGetAll('F2B_WHITELIST');
        if (is_array($wl)) {
          foreach ($wl as $key => $value) {
            $tmp_data[] = $key;
          }
          $data['whitelist'] = implode(PHP_EOL, $tmp_data);
        }
        else {
          $data['whitelist'] = "";
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
      return $data;
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
      }
      else {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $wl = $_data['whitelist'];
      $ban_time = ($ban_time < 60) ? 60 : $ban_time;
      $max_attempts = ($max_attempts < 1) ? 1 : $max_attempts;
      $retry_window = ($retry_window < 1) ? 1 : $retry_window;
      try {
        $redis->Set('F2B_BAN_TIME', $ban_time);
        $redis->Set('F2B_MAX_ATTEMPTS', $max_attempts);
        $redis->Set('F2B_RETRY_WINDOW', $retry_window);
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