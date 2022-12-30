<?php
function netfilter($_action, $_data = null) {
  global $redis;
  $_data_log = $_data;
  switch ($_action) {
    case 'get':
      $netfilter_options = array();
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      try {
        $netfilter_options = json_decode($redis->Get('NETFILTER_OPTIONS'), true);
        $netfilter_options['regex'] = json_decode($redis->Get('NETFILTER_REGEX'), true);
        $wl = $redis->hGetAll('NETFILTER_WHITELIST');
        if (is_array($wl)) {
          foreach ($wl as $key => $value) {
            $tmp_wl_data[] = $key;
          }
          if (isset($tmp_wl_data)) {
            natsort($tmp_wl_data);
            $netfilter_options['whitelist'] = implode(PHP_EOL, (array)$tmp_wl_data);
          }
          else {
            $netfilter_options['whitelist'] = "";
          }
        }
        else {
          $netfilter_options['whitelist'] = "";
        }
        $bl = $redis->hGetAll('NETFILTER_BLACKLIST');
        if (is_array($bl)) {
          foreach ($bl as $key => $value) {
            $tmp_bl_data[] = $key;
          }
          if (isset($tmp_bl_data)) {
            natsort($tmp_bl_data);
            $netfilter_options['blacklist'] = implode(PHP_EOL, (array)$tmp_bl_data);
          }
          else {
            $netfilter_options['blacklist'] = "";
          }
        }
        else {
          $netfilter_options['blacklist'] = "";
        }
        $pb = $redis->hGetAll('NETFILTER_PERM_BANS');
        if (is_array($pb)) {
          foreach ($pb as $key => $value) {
            $netfilter_options['perm_bans'][] = array(
                'network'=>$key,
                'ip' => strtok($key,'/')
            );

          }
        }
        else {
          $netfilter_options['perm_bans'] = "";
        }
        $active_bans = $redis->hGetAll('NETFILTER_ACTIVE_BANS');
        $queue_unban = $redis->hGetAll('NETFILTER_QUEUE_UNBAN');
        if (is_array($active_bans)) {
          foreach ($active_bans as $network => $banned_until) {
            $queued_for_unban = (isset($queue_unban[$network]) && $queue_unban[$network] == 1) ? 1 : 0;
            $difference = $banned_until - time();
            $netfilter_options['active_bans'][] = array(
              'queued_for_unban' => $queued_for_unban,
              'network' => $network,
              'ip' => strtok($network,'/'),
              'banned_until' => sprintf('%02dh %02dm %02ds', ($difference/3600), ($difference/60%60), $difference%60)
            );
          }
        }
        else {
          $netfilter_options['active_bans'] = "";
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('redis_error', $e)
        );
        return false;
      }
      return $netfilter_options;
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      // Start to read actions, if any
      if (isset($_data['action'])) {
        // Reset regex filters
        if ($_data['action'] == "reset-regex") {
          try {
            $redis->Del('NETFILTER_REGEX');
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
          // Rules will also be recreated on log events, but rules may seem empty for a second in the UI
          docker('post', 'netfilter-mailcow', 'restart');
          $fail_count = 0;
          $regex_result = json_decode($redis->Get('NETFILTER_REGEX'), true);
          while (empty($regex_result) && $fail_count < 10) {
            $regex_result = json_decode($redis->Get('NETFILTER_REGEX'), true);
            $fail_count++;
            sleep(1);
          }
          if ($fail_count >= 10) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => array('reset_netfilter_regex')
            );
            return false;
          }
        }
        elseif ($_data['action'] == "edit-regex") {
          if (!empty($_data['regex'])) {
            $rule_id = 1;
            $regex_array = array();
            foreach($_data['regex'] as $regex) {
              $regex_array[$rule_id] = $regex;
              $rule_id++;
            }
            if (!empty($regex_array)) {
              $redis->Set('NETFILTER_REGEX', json_encode($regex_array, JSON_UNESCAPED_SLASHES));
            }
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_data_log),
            'msg' => array('object_modified', htmlspecialchars($network))
          );
          return true;
        }

        // Start actions in dependency of network
        if (!empty($_data['network'])) {
          $networks = (array)$_data['network'];
          foreach ($networks as $network) {
            // Unban network
            if ($_data['action'] == "unban") {
              if (valid_network($network)) {
                try {
                  $redis->hSet('NETFILTER_QUEUE_UNBAN', $network, 1);
                }
                catch (RedisException $e) {
                  $_SESSION['return'][] = array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $_action, $_data_log),
                    'msg' => array('redis_error', $e)
                  );
                  continue;
                }
              }
            }
            // Whitelist network
            elseif ($_data['action'] == "whitelist") {
              if (empty($network)) { continue; }
              if (valid_network($network)) {
                try {
                  $redis->hSet('NETFILTER_WHITELIST', $network, 1);
                  $redis->hDel('NETFILTER_BLACKLIST', $network, 1);
                  $redis->hSet('NETFILTER_QUEUE_UNBAN', $network, 1);
                }
                catch (RedisException $e) {
                  $_SESSION['return'][] = array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $_action, $_data_log),
                    'msg' => array('redis_error', $e)
                  );
                  continue;
                }
              }
              else  {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_data_log),
                  'msg' => array('network_host_invalid', $network)
                );
                continue;
              }
            }
            // Blacklist network
            elseif ($_data['action'] == "blacklist") {
              if (empty($network)) { continue; }
              if (valid_network($network) && !in_array($network, array(
                '0.0.0.0',
                '0.0.0.0/0',
                getenv('IPV4_NETWORK') . '0/24',
                getenv('IPV4_NETWORK') . '0',
                getenv('IPV6_NETWORK')
              ))) {
                try {
                  $redis->hSet('NETFILTER_BLACKLIST', $network, 1);
                  $redis->hDel('NETFILTER_WHITELIST', $network, 1);
                  //$response = docker('post', 'netfilter-mailcow', 'restart');
                }
                catch (RedisException $e) {
                  $_SESSION['return'][] = array(
                    'type' => 'danger',
                    'log' => array(__FUNCTION__, $_action, $_data_log),
                    'msg' => array('redis_error', $e)
                  );
                  continue;
                }
              }
              else  {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_data_log),
                  'msg' => array('network_host_invalid', $network)
                );
                continue;
              }
            }
            $_SESSION['return'][] = array(
              'type' => 'success',
              'log' => array(__FUNCTION__, $_action, $_data_log),
              'msg' => array('object_modified', htmlspecialchars($network))
            );
          }
          return true;
        }
      }
      // Start default edit without specific action
      $is_now = netfilter('get');
      if (!empty($is_now)) {
        $ban_time = intval((isset($_data['ban_time'])) ? $_data['ban_time'] : $is_now['ban_time']);
        $max_attempts = intval((isset($_data['max_attempts'])) ? $_data['max_attempts'] : $is_now['max_attempts']);
        $retry_window = intval((isset($_data['retry_window'])) ? $_data['retry_window'] : $is_now['retry_window']);
        $netban_ipv4 = intval((isset($_data['netban_ipv4'])) ? $_data['netban_ipv4'] : $is_now['netban_ipv4']);
        $netban_ipv6 = intval((isset($_data['netban_ipv6'])) ? $_data['netban_ipv6'] : $is_now['netban_ipv6']);
        $wl = (isset($_data['whitelist'])) ? $_data['whitelist'] : $is_now['whitelist'];
        $bl = (isset($_data['blacklist'])) ? $_data['blacklist'] : $is_now['blacklist'];
      }
      else {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => 'access_denied'
        );
        return false;
      }
      $netfilter_options = array();
      $netfilter_options['ban_time'] = ($ban_time < 60) ? 60 : $ban_time;
      $netfilter_options['netban_ipv4'] = ($netban_ipv4 < 8) ? 8 : $netban_ipv4;
      $netfilter_options['netban_ipv6'] = ($netban_ipv6 < 8) ? 8 : $netban_ipv6;
      $netfilter_options['netban_ipv4'] = ($netban_ipv4 > 32) ? 32 : $netban_ipv4;
      $netfilter_options['netban_ipv6'] = ($netban_ipv6 > 128) ? 128 : $netban_ipv6;
      $netfilter_options['max_attempts'] = ($max_attempts < 1) ? 1 : $max_attempts;
      $netfilter_options['retry_window'] = ($retry_window < 1) ? 1 : $retry_window;
      try {
        $redis->Set('NETFILTER_OPTIONS', json_encode($netfilter_options));
        $redis->Del('NETFILTER_WHITELIST');
        $redis->Del('NETFILTER_BLACKLIST');
        if(!empty($wl)) {
          $wl_array = array_map('trim', preg_split( "/( |,|;|\n)/", $wl));
          $wl_array = array_filter($wl_array);
          if (is_array($wl_array)) {
            foreach ($wl_array as $wl_item) {
              if (valid_network($wl_item) || valid_hostname($wl_item)) {
                $redis->hSet('NETFILTER_WHITELIST', $wl_item, 1);
              }
              else {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_data_log),
                  'msg' => array('network_host_invalid', $wl_item)
                );
                continue;
              }
            }
          }
        }
        if(!empty($bl)) {
          $bl_array = array_map('trim', preg_split( "/( |,|;|\n)/", $bl));
          $bl_array = array_filter($bl_array);
          if (is_array($bl_array)) {
            foreach ($bl_array as $bl_item) {
              if (valid_network($bl_item) && !in_array($bl_item, array(
                '0.0.0.0',
                '0.0.0.0/0',
                getenv('IPV4_NETWORK') . '0/24',
                getenv('IPV4_NETWORK') . '0',
                getenv('IPV6_NETWORK')
              ))) {
                $redis->hSet('NETFILTER_BLACKLIST', $bl_item, 1);
              }
              else {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_data_log),
                  'msg' => array('network_host_invalid', $bl_item)
                );
                continue;
              }
            }
          }
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data_log),
          'msg' => array('redis_error', $e)
        );
        return false;
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data_log),
        'msg' => 'netfilter_modified'
      );
    break;
  }
}
