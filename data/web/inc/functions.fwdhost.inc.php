<?php

function fwdhost($_action, $_data = null) {
  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/spf.inc.php';
	global $redis;
	global $lang;
  switch ($_action) {
    case 'add':
      global $lang;
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $source = $_data['hostname'];
      $host = trim($_data['hostname']);
      $filter_spam = (isset($_data['filter_spam']) && $_data['filter_spam'] == 1) ? 1 : 0;
      if (preg_match('/^[0-9a-fA-F:\/]+$/', $host)) { // IPv6 address
        $hosts = array($host);
      }
      elseif (preg_match('/^[0-9\.\/]+$/', $host)) { // IPv4 address
        $hosts = array($host);
      }
      else {
        $hosts = get_outgoing_hosts_best_guess($host);
      }
      if (empty($hosts)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Invalid host specified: '. htmlspecialchars($host)
        );
        return false;
      }
      foreach ($hosts as $host) {
        try {
          $redis->hSet('WHITELISTED_FWD_HOST', $host, $source);
          if ($filter_spam == 0) {
            $redis->hSet('KEEP_SPAM', $host, 1);
          }
          elseif ($redis->hGet('KEEP_SPAM', $host)) {
            $redis->hDel('KEEP_SPAM', $host);
          }
        }
        catch (RedisException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Redis: '.$e
          );
          return false;
        }
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['forwarding_host_added'], htmlspecialchars(implode(', ', $hosts)))
      );
    break;
    case 'edit':
      global $lang;
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $fwdhosts = (array)$_data['fwdhost'];
      foreach ($fwdhosts as $fwdhost) {
        $is_now = fwdhost('details', $fwdhost);
        if (!empty($is_now)) {
          $keep_spam = (isset($_data['keep_spam'])) ? $_data['keep_spam'] : $is_now['keep_spam'];
        }
        else {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => sprintf($lang['danger']['access_denied'])
          );
          return false;
        }
        try {
          if ($keep_spam == 1) {
            $redis->hSet('KEEP_SPAM', $fwdhost, 1);
          }
          else {
            $redis->hDel('KEEP_SPAM', $fwdhost);
          }
        }
        catch (RedisException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Redis: '.$e
          );
          return false;
        }
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['object_modified'], htmlspecialchars(implode(', ', $fwdhosts)))
      );
    break;
    case 'delete':
      $hosts = (array)$_data['forwardinghost'];
      foreach ($hosts as $host) {
        try {
          $redis->hDel('WHITELISTED_FWD_HOST', $host);
          $redis->hDel('KEEP_SPAM', $host);
        }
        catch (RedisException $e) {
          $_SESSION['return'] = array(
            'type' => 'danger',
            'msg' => 'Redis: '.$e
          );
          return false;
        }
      }
      $_SESSION['return'] = array(
        'type' => 'success',
        'msg' => sprintf($lang['success']['forwarding_host_removed'], htmlspecialchars(implode(', ', $hosts)))
      );
    break;
    case 'get':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        return false;
      }
      $fwdhostsdata = array();
      try {
        $fwd_hosts = $redis->hGetAll('WHITELISTED_FWD_HOST');
        if (!empty($fwd_hosts)) {
        foreach ($fwd_hosts as $fwd_host => $source) {
          $keep_spam = ($redis->hGet('KEEP_SPAM', $fwd_host)) ? "yes" : "no";
          $fwdhostsdata[] = array(
            'host' => $fwd_host,
            'source' => $source,
            'keep_spam' => $keep_spam
          );
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
      return $fwdhostsdata;
    break;
    case 'details':
      $fwdhostdetails = array();
      if (!isset($_data) || empty($_data)) {
        return false;
      }
      try {
        if ($source = $redis->hGet('WHITELISTED_FWD_HOST', $_data)) {
          $fwdhostdetails['host'] = $_data;
          $fwdhostdetails['source'] = $source;
          $fwdhostdetails['keep_spam'] = ($redis->hGet('KEEP_SPAM', $_data)) ? "yes" : "no";
        }
      }
      catch (RedisException $e) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'Redis: '.$e
        );
        return false;
      }
      return $fwdhostdetails;
    break;
  }
}