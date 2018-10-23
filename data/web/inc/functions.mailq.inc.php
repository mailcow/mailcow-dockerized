<?php
function mailq($_action, $_data = null) {
  if ($_SESSION['mailcow_cc_role'] != "admin") {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'log' => array(__FUNCTION__, $_action, $_data),
      'msg' => 'access_denied'
    );
    return false;
  }
  function process_mailq_output($returned_output, $_action, $_data) {
    if ($returned_output !== NULL) {
      if (isset($returned_output['type']) && $returned_output['type'] == 'danger') {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array('mailq', $_action, $_data),
          'msg' => 'Error: ' . $returned_output['msg']
        );
      }
      if (isset($returned_output['type']) && $returned_output['type'] == 'success') {
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array('mailq', $_action, $_data),
          'msg' => 'queue_command_success'
        );
      }
    }
    else {
      $_SESSION['return'][] = array(
        'type' => 'danger',
        'log' => array('mailq', $_action, $_data),
        'msg' => 'unknown'
      );
    }
  }
	global $lang;
  switch ($_action) {
    case 'delete':
      if (!is_array($_data['qid'])) {
        $qids = array();
        $qids[] = $_data['qid'];
      }
      else {
        $qids = $_data['qid'];
      }
      $docker_return = docker('post', 'postfix-mailcow', 'exec', array('cmd' => 'mailq', 'task' => 'delete', 'items' => $qids));
      process_mailq_output(json_decode($docker_return, true), $_action, $_data);
    break;
    case 'edit':
      if (in_array($_data['action'], array('hold', 'unhold', 'deliver'))) {
        if (!is_array($_data['qid'])) {
          $qids = array();
          $qids[] = $_data['qid'];
        }
        else {
          $qids = $_data['qid'];
        }
        if (!empty($qids)) {
          $docker_return = docker('post', 'postfix-mailcow', 'exec', array('cmd' => 'mailq', 'task' => $_data['action'], 'items' => $qids));
          process_mailq_output(json_decode($docker_return, true), $_action, $_data);
        }
      }
      if (in_array($_data['action'], array('flush', 'super_delete'))) {
        $docker_return = docker('post', 'postfix-mailcow', 'exec', array('cmd' => 'mailq', 'task' => $_data['action']));
        process_mailq_output(json_decode($docker_return, true), $_action, $_data);
      }
    break;
    case 'get':
      // todo: move get from json_api here
    break;
  }
}
