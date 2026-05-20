<?php
define('AGENT_ERR_NOT_FOUND',   'not_found');
define('AGENT_ERR_TIMEOUT',     'timeout');
define('AGENT_ERR_VALIDATION',  'validation');
define('AGENT_ERR_UNSUPPORTED', 'unsupported_command');
define('AGENT_ERR_INTERNAL',    'internal');

function agent($_action, $_service = null, $_data = null, $_args = array(), $_timeout = 10) {
  global $redis;
  switch ($_action) {
    case 'request_id':
      return sprintf('%013d%s', (int)(microtime(true) * 1000), substr(bin2hex(random_bytes(10)), 0, 16));
    break;
    case 'services':
      $list = array(
        'unbound', 'clamd', 'rspamd', 'php-fpm', 'sogo',
        'dovecot', 'postfix', 'postfix-tlspol', 'nginx', 'acme',
        'netfilter', 'watchdog', 'olefy', 'host'
      );
      if (preg_match('/^([yY][eE][sS]|[yY])+$/', isset($_ENV['SKIP_CLAMD']) ? $_ENV['SKIP_CLAMD'] : '')) {
        $list = array_values(array_diff($list, array('clamd')));
      }
      if (preg_match('/^([yY][eE][sS]|[yY])+$/', isset($_ENV['SKIP_OLEFY']) ? $_ENV['SKIP_OLEFY'] : '')) {
        $list = array_values(array_diff($list, array('olefy')));
      }
      sort($list);
      return $list;
    break;
    case 'live_nodes':
      try {
        $members = $redis->zRangeByScore('mailcow.nodes.' . $_service, (string)(time() - 30), '+inf');
      }
      catch (RedisException $e) {
        return array();
      }
      return is_array($members) ? $members : array();
    break;
    case 'node_meta':
      try {
        $h = $redis->hGetAll('mailcow.node.' . $_service . '.' . $_data);
      }
      catch (RedisException $e) {
        return null;
      }
      return $h ?: null;
    break;
    case 'node_stats':
      try {
        $h = $redis->hGetAll('mailcow.stats.' . $_service . '.' . $_data);
      }
      catch (RedisException $e) {
        return null;
      }
      return $h ?: null;
    break;
    case 'stats':
      $out = array();
      foreach (agent('live_nodes', $_service) as $node_id) {
        $stats = agent('node_stats', $_service, $node_id);
        if ($stats) {
          $out[$node_id] = $stats;
        }
      }
      return $out;
    break;
    case 'publish':
      $env = array(
        'cmd' => $_data,
        'request_id' => agent('request_id'),
        'args' => (object)(is_array($_args) ? $_args : array()),
        'issued_by' => 'mailcow-php'
      );
      try {
        $redis->publish('mailcow.control.' . $_service, json_encode($env));
      }
      catch (RedisException $e) {
        return false;
      }
      return true;
    break;
    case 'request':
      $rid = agent('request_id');
      $reply_to = 'mailcow.reply.' . $rid;
      $env = array(
        'cmd' => $_data,
        'request_id' => $rid,
        'args' => (object)(is_array($_args) ? $_args : array()),
        'reply_to' => $reply_to,
        'deadline' => gmdate('Y-m-d\TH:i:s\Z', time() + $_timeout),
        'issued_by' => 'mailcow-php'
      );
      try {
        $subs = $redis->publish('mailcow.control.' . $_service, json_encode($env));
        if ($subs === 0) {
          return array('ok' => false, 'result' => null, 'error' => $_service, 'error_code' => AGENT_ERR_NOT_FOUND, 'node' => '', 'duration_ms' => 0);
        }
        $popped = $redis->blPop(array($reply_to), $_timeout);
      }
      catch (RedisException $e) {
        return array('ok' => false, 'result' => null, 'error' => $e->getMessage(), 'error_code' => AGENT_ERR_INTERNAL, 'node' => '', 'duration_ms' => 0);
      }
      if (!$popped || count($popped) < 2) {
        return array('ok' => false, 'result' => null, 'error' => '', 'error_code' => AGENT_ERR_TIMEOUT, 'node' => '', 'duration_ms' => 0);
      }
      $resp = json_decode($popped[1], true);
      if (!is_array($resp)) {
        return array('ok' => false, 'result' => null, 'error' => 'malformed reply', 'error_code' => AGENT_ERR_INTERNAL, 'node' => '', 'duration_ms' => 0);
      }
      return array(
        'ok' => !empty($resp['ok']),
        'result' => isset($resp['result']) ? $resp['result'] : null,
        'error' => isset($resp['error']) ? $resp['error'] : '',
        'error_code' => isset($resp['error_code']) ? $resp['error_code'] : '',
        'node' => isset($resp['node']) ? $resp['node'] : '',
        'duration_ms' => isset($resp['duration_ms']) ? $resp['duration_ms'] : 0
      );
    break;
    case 'request_all':
      $rid = agent('request_id');
      $reply_to = 'mailcow.reply.' . $rid;
      $env = array(
        'cmd' => $_data,
        'request_id' => $rid,
        'args' => (object)(is_array($_args) ? $_args : array()),
        'reply_to' => $reply_to,
        'deadline' => gmdate('Y-m-d\TH:i:s\Z', time() + $_timeout),
        'issued_by' => 'mailcow-php'
      );
      $expected = max(1, count(agent('live_nodes', $_service)));
      try {
        $subs = (int)$redis->publish('mailcow.control.' . $_service, json_encode($env));
      }
      catch (RedisException $e) {
        return array('responses' => array(), 'expected_nodes' => $expected, 'received_nodes' => array(), 'missing_nodes' => array(), 'error' => $e->getMessage());
      }
      if ($subs === 0) {
        return array('responses' => array(), 'expected_nodes' => 0, 'received_nodes' => array(), 'missing_nodes' => array());
      }
      $responses = array();
      $deadline = microtime(true) + $_timeout;
      for ($i = 0; $i < $subs; $i++) {
        $remaining = (int)ceil($deadline - microtime(true));
        if ($remaining <= 0) break;
        try {
          $popped = $redis->blPop(array($reply_to), $remaining);
        }
        catch (RedisException $e) {
          break;
        }
        if (!$popped || count($popped) < 2) break;
        $resp = json_decode($popped[1], true);
        if (is_array($resp)) {
          $responses[] = array(
            'ok' => !empty($resp['ok']),
            'result' => isset($resp['result']) ? $resp['result'] : null,
            'error' => isset($resp['error']) ? $resp['error'] : '',
            'error_code' => isset($resp['error_code']) ? $resp['error_code'] : '',
            'node' => isset($resp['node']) ? $resp['node'] : '',
            'duration_ms' => isset($resp['duration_ms']) ? $resp['duration_ms'] : 0
          );
        }
      }
      $received_nodes = array();
      foreach ($responses as $r) {
        if (!empty($r['node'])) {
          $received_nodes[] = $r['node'];
        }
      }
      $live = agent('live_nodes', $_service);
      return array(
        'responses' => $responses,
        'expected_nodes' => $expected,
        'received_nodes' => array_values(array_unique($received_nodes)),
        'missing_nodes' => array_values(array_diff($live, $received_nodes))
      );
    break;
    case 'ok':
      if (isset($_service['responses'])) {
        foreach ($_service['responses'] as $r) {
          if (!empty($r['ok'])) return true;
        }
        return false;
      }
      return !empty($_service['ok']);
    break;
    case 'first_error':
      foreach (isset($_service['responses']) ? $_service['responses'] : array() as $r) {
        if (empty($r['ok']) && !empty($r['error'])) return $r['error'];
      }
      return '';
    break;
    case 'error_lang':
      $code = is_array($_service) && isset($_service['error_code']) ? $_service['error_code'] : '';
      switch ($code) {
        case AGENT_ERR_NOT_FOUND:
          return 'no_live_agent';
        case AGENT_ERR_TIMEOUT:
          return 'agent_timeout';
        default:
          return 'agent_unknown_error';
      }
    break;
  }
}

function infra($_action, $_service = null) {
  global $redis;
  global $pdo;
  switch ($_action) {
    case 'health':
      switch ($_service) {
        case 'redis':
          try {
            if ($redis instanceof Redis && $redis->ping()) {
              $info = $redis->info('server');
              $ver = is_array($info) && isset($info['redis_version']) ? $info['redis_version'] : '';
              return array('ok' => true, 'image' => 'redis ' . $ver, 'error' => '');
            }
          }
          catch (RedisException $e) {
            return array('ok' => false, 'image' => 'redis', 'error' => $e->getMessage());
          }
          return array('ok' => false, 'image' => 'redis', 'error' => 'PING returned false');
        break;
        case 'mysql':
          try {
            if ($pdo instanceof PDO) {
              $row = $pdo->query('SELECT VERSION() AS v')->fetch(PDO::FETCH_ASSOC);
              $ver = $row && isset($row['v']) ? $row['v'] : '';
              return array('ok' => true, 'image' => 'mariadb/mysql ' . $ver, 'error' => '');
            }
          }
          catch (Exception $e) {
            return array('ok' => false, 'image' => 'mariadb/mysql', 'error' => $e->getMessage());
          }
          return array('ok' => false, 'image' => 'mariadb/mysql', 'error' => 'no PDO handle');
        break;
        case 'memcached':
          $sock = @fsockopen('memcached', 11211, $errno, $errstr, 2);
          if (!$sock) {
            return array('ok' => false, 'image' => 'memcached', 'error' => $errstr ?: 'connection refused');
          }
          stream_set_timeout($sock, 2);
          fwrite($sock, "version\r\n");
          $line = fgets($sock, 64);
          fclose($sock);
          if (is_string($line) && strpos($line, 'VERSION') === 0) {
            return array('ok' => true, 'image' => 'memcached ' . trim(substr($line, strlen('VERSION '))), 'error' => '');
          }
          return array('ok' => false, 'image' => 'memcached', 'error' => 'no VERSION reply');
        break;
      }
    break;
    case 'status':
      $out = array();
      $defs = array(
        'redis-mailcow' => 'redis',
        'mysql-mailcow' => 'mysql',
        'memcached-mailcow' => 'memcached'
      );
      foreach ($defs as $key => $svc) {
        $h = infra('health', $svc);
        $out[$key] = array(
          'Service' => $svc,
          'State' => array(
            'Running' => $h['ok'] ? 1 : 0,
            'NodeCount' => $h['ok'] ? 1 : 0,
            'StartedAt' => '',
            'StartedAtHR' => '—',
            'Error' => $h['error']
          ),
          'Config' => array('Image' => $h['image']),
          'Id' => $svc,
          'Nodes' => array(),
          'External' => true
        );
      }
      return $out;
    break;
  }
}
