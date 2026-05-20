<?php
function mailq($_action, $_data = null) {
  global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_action, $_data),
      'msg' => 'access_denied'
    );
    return false;
  }
  switch ($_action) {
    case 'get':
      $agg = agent('request_all', 'postfix', 'exec.mailq', array(), 15);
      $lines = array();
      foreach ($agg['responses'] as $r) {
        if (empty($r['ok'])) continue;
        $queue = isset($r['result']['queue']) ? $r['result']['queue'] : array();
        foreach ($queue as $entry) {
          if (is_array($entry)) {
            $entry['node'] = $r['node'];
            if (!empty($entry['recipients']) && is_array($entry['recipients'])) {
              $rcpts = array();
              foreach ($entry['recipients'] as $rcpt) {
                $addr = isset($rcpt['address']) ? $rcpt['address'] : '';
                if (isset($rcpt['delay_reason'])) {
                  $rcpts[] = $addr . ' (' . $rcpt['delay_reason'] . ')';
                }
                else {
                  $rcpts[] = $addr;
                }
              }
              $entry['recipients'] = $rcpts;
            }
            $lines[] = $entry;
          }
          if (count($lines) >= 10000) break 2;
        }
      }
      return empty($lines) ? '[]' : json_encode($lines);
    break;
    case 'delete':
      $qids = isset($_data['qid']) && is_array($_data['qid']) ? $_data['qid'] : array($_data['qid']);
      $ok_count = 0;
      $failed = 0;
      foreach ($qids as $qid) {
        $agg = agent('request_all', 'postfix', 'exec.delete-from-queue', array('queue_id' => $qid), 10);
        if (agent('ok', $agg)) {
          $ok_count++;
        }
        else {
          $failed++;
        }
      }
      $ok = ($ok_count > 0 && $failed === 0);
      $_SESSION['return'][] = array(
        'type' => $ok ? 'success' : 'danger',
        'log' => array(__FUNCTION__, $_action, $_data),
        'msg' => $ok ? 'queue_command_success' : 'queue_command_failed'
      );
      return $ok;
    break;
    case 'cat':
      $qids = isset($_data['qid']) && is_array($_data['qid']) ? $_data['qid'] : array($_data['qid']);
      $body = '';
      foreach ($qids as $qid) {
        $agg = agent('request_all', 'postfix', 'exec.cat-queue', array('queue_id' => $qid), 15);
        foreach ($agg['responses'] as $r) {
          if (!empty($r['ok']) && !empty($r['result']['body'])) {
            $body .= $r['result']['body'];
          }
        }
      }
      if ($body === '') {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => 'queue_cat_empty'
        );
        return null;
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data),
        'msg' => 'queue_cat_success'
      );
      return $body;
    break;
    case 'edit':
      $cmd_map = array(
        'hold' => 'exec.hold-queue',
        'unhold' => 'exec.unhold-queue',
        'deliver' => 'exec.deliver-now'
      );
      if (isset($cmd_map[$_data['action']])) {
        $qids = isset($_data['qid']) && is_array($_data['qid']) ? $_data['qid'] : array($_data['qid']);
        $ok_count = 0;
        $failed = 0;
        foreach ($qids as $qid) {
          $agg = agent('request_all', 'postfix', $cmd_map[$_data['action']], array('queue_id' => $qid), 10);
          if (agent('ok', $agg)) {
            $ok_count++;
          }
          else {
            $failed++;
          }
        }
        $ok = ($ok_count > 0 && $failed === 0);
        $_SESSION['return'][] = array(
          'type' => $ok ? 'success' : 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => $ok ? 'queue_command_success' : 'queue_command_failed'
        );
        return $ok;
      }
      if ($_data['action'] == 'flush') {
        $agg = agent('request_all', 'postfix', 'exec.flush-queue', array(), 30);
        $ok = agent('ok', $agg);
        $_SESSION['return'][] = array(
          'type' => $ok ? 'success' : 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => $ok ? 'queue_command_success' : 'queue_command_failed'
        );
        return $ok;
      }
      if ($_data['action'] == 'super_delete') {
        $agg = agent('request_all', 'postfix', 'exec.super-delete', array(), 30);
        $ok = agent('ok', $agg);
        $_SESSION['return'][] = array(
          'type' => $ok ? 'success' : 'danger',
          'log' => array(__FUNCTION__, $_action, $_data),
          'msg' => $ok ? 'queue_command_success' : 'queue_command_failed'
        );
        return $ok;
      }
    break;
  }
}
