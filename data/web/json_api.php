<?php
/*
edit/alias => POST data:
  {
    address: {a, b, c},   (where a, b, c represent alias addresses)
    active: 1             (0 or 1)
  }

delete/alias => POST data:
  {
    address: {a, b, c},   (where a, b, c represent alias addresses)
  }

*/
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
error_reporting(0);

function api_log($postarray) {
  global $redis;
  $data_var = array();
  foreach ($postarray as $data => &$value) {
    if ($data == 'csrf_token') {
      continue;
    }
    if ($value = json_decode($value, true)) {
      unset($value["csrf_token"]);
      foreach ($value as $key => &$val) {
        if(preg_match("/pass/i", $key)) {
          $val = '********';
        }
      }
      $value = json_encode($value);
    }
    $data_var[] = $data . "='" . $value . "'";
  }
  try {
    $log_line = array(
      'time' => time(),
      'uri' => $_SERVER['REQUEST_URI'],
      'method' => $_SERVER['REQUEST_METHOD'],
      'remote' => $_SERVER['REMOTE_ADDR'],
      'data' => implode(', ', $data_var)
    );
    $redis->lPush('API_LOG', json_encode($log_line));
  }
  catch (RedisException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'Redis: '.$e
    );
    return false;
  }
}

api_log($_POST);

if (isset($_SESSION['mailcow_cc_role']) || isset($_SESSION['pending_mailcow_cc_username'])) {
  if (isset($_GET['query'])) {

    $query = explode('/', $_GET['query']);
    $action =     (isset($query[0])) ? $query[0] : null;
    $category =   (isset($query[1])) ? $query[1] : null;
    $object =     (isset($query[2])) ? $query[2] : null;
    $extra =      (isset($query[3])) ? $query[3] : null;

    switch ($action) {
      case "add":
        switch ($category) {
          case "time_limited_alias":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'time_limited_alias', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "relayhost":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (relayhost('add', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "mailbox":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'mailbox', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "domain":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'domain', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "resource":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'resource', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "alias":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'alias', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "filter":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'filter', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "domain-policy":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (policy('add', 'domain', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "mailbox-policy":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (policy('add', 'mailbox', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "alias-domain":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'alias_domain', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "fwdhost":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (fwdhost('add', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "dkim":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (dkim('add', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "dkim_import":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (dkim('import', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "domain-admin":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (domain_admin('add', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "syncjob":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (mailbox('add', 'syncjob', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "bcc":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (bcc('add', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
          case "recipient_map":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (recipient_map('add', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Cannot add item'
                  ));
                }
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find attributes in post data'
              ));
            }
          break;
        }
      break;
      case "get":
        switch ($category) {
          case "rspamd":
            switch ($object) {
              case "stat":
                $data = file_get_contents('http://rspamd-mailcow:11334/stat');
                if (!empty($data)) {
                  echo $data;
                }
                elseif (!isset($data) || empty($data)) {
                  echo '{}';
                }
              break;
              case "graph":
                switch ($extra) {
                  case "hourly":
                    $data = file_get_contents('http://rspamd-mailcow:11334/graph?type=hourly');
                    if (!empty($data)) {
                      $data_array = json_decode($data, true);
                      $rejected['label'] = "reject";
                      foreach ($data_array[0] as $dataset) {
                        $rejected['data'][] = $dataset;
                      }
                      $temp_reject['label'] = "temp_reject";
                      foreach ($data_array[1] as $dataset) {
                        $temp_reject['data'][] = $dataset;
                      }
                      $add_header['label'] = "add_header";
                      foreach ($data_array[2] as $dataset) {
                        $add_header['data'][] = $dataset;
                      }
                      $prob_spam['label'] = "prob_spam";
                      foreach ($data_array[3] as $dataset) {
                        $prob_spam['data'][] = $dataset;
                      }
                      $greylist['label'] = "greylist";
                      foreach ($data_array[4] as $dataset) {
                        $greylist['data'][] = $dataset;
                      }
                      $clean['label'] = "clean";
                      $clean['pointStyle'] = "cross";
                      foreach ($data_array[5] as $dataset) {
                        $clean['data'][] = $dataset;
                      }
                      echo json_encode(array($rejected, $temp_reject, $add_header, $prob_spam, $greylist, $clean), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                    elseif (!isset($data) || empty($data)) {
                      echo '{}';
                    }
                  break;
                  case "daily":
                    $data = file_get_contents('http://rspamd-mailcow:11334/graph?type=daily');
                    if (!empty($data)) {
                      $data_array = json_decode($data, true);
                      $rejected['label'] = "reject";
                      foreach ($data_array[0] as $dataset) {
                        $rejected['data'][] = $dataset;
                      }
                      $temp_reject['label'] = "temp_reject";
                      foreach ($data_array[1] as $dataset) {
                        $temp_reject['data'][] = $dataset;
                      }
                      $add_header['label'] = "add_header";
                      foreach ($data_array[2] as $dataset) {
                        $add_header['data'][] = $dataset;
                      }
                      $prob_spam['label'] = "prob_spam";
                      foreach ($data_array[3] as $dataset) {
                        $prob_spam['data'][] = $dataset;
                      }
                      $greylist['label'] = "greylist";
                      foreach ($data_array[4] as $dataset) {
                        $greylist['data'][] = $dataset;
                      }
                      $clean['label'] = "clean";
                      $clean['pointStyle'] = "cross";
                      foreach ($data_array[5] as $dataset) {
                        $clean['data'][] = $dataset;
                      }
                      echo json_encode(array($rejected, $temp_reject, $add_header, $prob_spam, $greylist, $clean), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                    elseif (!isset($data) || empty($data)) {
                      echo '{}';
                    }
                  break;
                  case "weekly":
                    $data = file_get_contents('http://rspamd-mailcow:11334/graph?type=weekly');
                    if (!empty($data)) {
                      $data_array = json_decode($data, true);
                      $rejected['label'] = "reject";
                      foreach ($data_array[0] as $dataset) {
                        $rejected['data'][] = $dataset;
                      }
                      $temp_reject['label'] = "temp_reject";
                      foreach ($data_array[1] as $dataset) {
                        $temp_reject['data'][] = $dataset;
                      }
                      $add_header['label'] = "add_header";
                      foreach ($data_array[2] as $dataset) {
                        $add_header['data'][] = $dataset;
                      }
                      $prob_spam['label'] = "prob_spam";
                      foreach ($data_array[3] as $dataset) {
                        $prob_spam['data'][] = $dataset;
                      }
                      $greylist['label'] = "greylist";
                      foreach ($data_array[4] as $dataset) {
                        $greylist['data'][] = $dataset;
                      }
                      $clean['label'] = "clean";
                      $clean['pointStyle'] = "cross";
                      foreach ($data_array[5] as $dataset) {
                        $clean['data'][] = $dataset;
                      }
                      echo json_encode(array($rejected, $temp_reject, $add_header, $prob_spam, $greylist, $clean), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                    elseif (!isset($data) || empty($data)) {
                      echo '{}';
                    }
                  break;
                  case "monthly":
                    $data = file_get_contents('http://rspamd-mailcow:11334/graph?type=monthly');
                    if (!empty($data)) {
                      $data_array = json_decode($data, true);
                      $rejected['label'] = "reject";
                      foreach ($data_array[0] as $dataset) {
                        $rejected['data'][] = $dataset;
                      }
                      $temp_reject['label'] = "temp_reject";
                      foreach ($data_array[1] as $dataset) {
                        $temp_reject['data'][] = $dataset;
                      }
                      $add_header['label'] = "add_header";
                      foreach ($data_array[2] as $dataset) {
                        $add_header['data'][] = $dataset;
                      }
                      $prob_spam['label'] = "prob_spam";
                      foreach ($data_array[3] as $dataset) {
                        $prob_spam['data'][] = $dataset;
                      }
                      $greylist['label'] = "greylist";
                      foreach ($data_array[4] as $dataset) {
                        $greylist['data'][] = $dataset;
                      }
                      $clean['label'] = "clean";
                      $clean['pointStyle'] = "cross";
                      foreach ($data_array[5] as $dataset) {
                        $clean['data'][] = $dataset;
                      }
                      echo json_encode(array($rejected, $temp_reject, $add_header, $prob_spam, $greylist, $clean), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                    elseif (!isset($data) || empty($data)) {
                      echo '{}';
                    }
                  break;
                }
              break;
            }
          break;
          case "domain":
            switch ($object) {
              case "all":
                $domains = mailbox('get', 'domains');
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    if ($details = mailbox('get', 'domain_details', $domain)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = mailbox('get', 'domain_details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;

          case "relayhost":
            switch ($object) {
              case "all":
                $relayhosts = relayhost('get');
                if (!empty($relayhosts)) {
                  foreach ($relayhosts as $relayhost) {
                    if ($details = relayhost('details', $relayhost['id'])) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = relayhost('details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "logs":
            switch ($object) {
              case "dovecot":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('dovecot-mailcow', $extra);
                }
                else {
                  $logs = get_logs('dovecot-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "netfilter":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('netfilter-mailcow', $extra);
                }
                else {
                  $logs = get_logs('netfilter-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "postfix":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('postfix-mailcow', $extra);
                }
                else {
                  $logs = get_logs('postfix-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "autodiscover":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('autodiscover-mailcow', $extra);
                }
                else {
                  $logs = get_logs('autodiscover-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "sogo":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('sogo-mailcow', $extra);
                }
                else {
                  $logs = get_logs('sogo-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "watchdog":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('watchdog-mailcow', $extra);
                }
                else {
                  $logs = get_logs('watchdog-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "acme":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('acme-mailcow', $extra);
                }
                else {
                  $logs = get_logs('acme-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "api":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('api-mailcow', $extra);
                }
                else {
                  $logs = get_logs('api-mailcow');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "rspamd-history":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('rspamd-history', $extra);
                }
                else {
                  $logs = get_logs('rspamd-history');
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
            }
          break;
          case "mailbox":
            switch ($object) {
              case "all":
                $domains = mailbox('get', 'domains');
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $mailboxes = mailbox('get', 'mailboxes', $domain);
                    if (!empty($mailboxes)) {
                      foreach ($mailboxes as $mailbox) {
                        if ($details = mailbox('get', 'mailbox_details', $mailbox)) {
                          $data[] = $details;
                        }
                        else {
                          continue;
                        }
                      }
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = mailbox('get', 'mailbox_details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "syncjobs":
            switch ($object) {
              case "all":
                $domains = mailbox('get', 'domains');
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $mailboxes = mailbox('get', 'mailboxes', $domain);
                    if (!empty($mailboxes)) {
                      foreach ($mailboxes as $mailbox) {
                        $syncjobs = mailbox('get', 'syncjobs', $mailbox);
                        if (!empty($syncjobs)) {
                          foreach ($syncjobs as $syncjob) {
                            if (isset($extra)) {
                              $details = mailbox('get', 'syncjob_details', $syncjob, explode(',', $extra));
                            }
                            else {
                              $details = mailbox('get', 'syncjob_details', $syncjob);
                            }
                            if ($details) {
                              $data[] = $details;
                            }
                            else {
                              continue;
                            }
                          }
                        }
                      }
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $syncjobs = mailbox('get', 'syncjobs', $object);
                if (!empty($syncjobs)) {
                  foreach ($syncjobs as $syncjob) {
                    if (isset($extra)) {
                      $details = mailbox('get', 'syncjob_details', $syncjob, explode(',', $extra));
                    }
                    else {
                      $details = mailbox('get', 'syncjob_details', $syncjob);
                    }
                    if ($details) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "active-user-sieve":
            if (isset($object)) {
              $sieve_filter = mailbox('get', 'active_user_sieve', $object);
              if (!empty($sieve_filter)) {
                $data[] = $sieve_filter;
              }
            }
            if (!isset($data) || empty($data)) {
              echo '{}';
            }
            else {
              echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
          break;
          case "filters":
            switch ($object) {
              case "all":
                $domains = mailbox('get', 'domains');
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $mailboxes = mailbox('get', 'mailboxes', $domain);
                    if (!empty($mailboxes)) {
                      foreach ($mailboxes as $mailbox) {
                        $filters = mailbox('get', 'filters', $mailbox);
                        if (!empty($filters)) {
                          foreach ($filters as $filter) {
                            if ($details = mailbox('get', 'filter_details', $filter)) {
                              $data[] = $details;
                            }
                            else {
                              continue;
                            }
                          }
                        }
                      }
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $filters = mailbox('get', 'filters', $object);
                if (!empty($filters)) {
                  foreach ($filters as $filter) {
                    if ($details = mailbox('get', 'filter_details', $filter)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "bcc":
            switch ($object) {
              case "all":
                $bcc_items = bcc('get');
                if (!empty($bcc_items)) {
                  foreach ($bcc_items as $bcc_item) {
                    if ($details = bcc('details', $bcc_item)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
              default:
                $data = bcc('details', $object);
                if (!empty($data)) {
                  $data[] = $details;
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "recipient_map":
            switch ($object) {
              case "all":
                $recipient_map_items = recipient_map('get');
                if (!empty($recipient_map_items)) {
                  foreach ($recipient_map_items as $recipient_map_item) {
                    if ($details = recipient_map('details', $recipient_map_item)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
              default:
                $data = recipient_map('details', $object);
                if (!empty($data)) {
                  $data[] = $details;
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "policy_wl_mailbox":
            switch ($object) {
              default:
                $data = policy('get', 'mailbox', $object)['whitelist'];
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "policy_bl_mailbox":
            switch ($object) {
              default:
                $data = policy('get', 'mailbox', $object)['blacklist'];
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "policy_wl_domain":
            switch ($object) {
              default:
                $data = policy('get', 'domain', $object)['whitelist'];
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "policy_bl_domain":
            switch ($object) {
              default:
                $data = policy('get', 'domain', $object)['blacklist'];
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "time_limited_aliases":
            switch ($object) {
              default:
                $data = mailbox('get', 'time_limited_aliases', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "resource":
            switch ($object) {
              case "all":
                $domains = mailbox('get', 'domains');
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $resources = mailbox('get', 'resources', $domain);
                    if (!empty($resources)) {
                      foreach ($resources as $resource) {
                        if ($details = mailbox('get', 'resource_details', $resource)) {
                          $data[] = $details;
                        }
                        else {
                          continue;
                        }
                      }
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;
              default:
                $data = mailbox('get', 'resource_details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "fwdhost":
            switch ($object) {
              case "all":
                $data = fwdhost('get');
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
              default:
                $data = fwdhost('details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "quarantine":
            // "all" will not print details
            switch ($object) {
              case "all":
                $data = quarantine('get');
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
              default:
                $data = quarantine('details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "alias-domain":
            switch ($object) {
              case "all":
                $alias_domains = mailbox('get', 'alias_domains');
                if (!empty($alias_domains)) {
                  foreach ($alias_domains as $alias_domain) {
                    if ($details = mailbox('get', 'alias_domain_details', $alias_domain)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                }
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
              default:
                $data = mailbox('get', 'alias_domain_details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "alias":
            switch ($object) {
              case "all":
                $domains = array_merge(mailbox('get', 'domains'),mailbox('get', 'alias_domains'));
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $aliases = mailbox('get', 'aliases', $domain);
                    if (!empty($aliases)) {
                      foreach ($aliases as $alias) {
                        if ($details = mailbox('get', 'alias_details', $alias)) {
                          $data[] = $details;
                        }
                        else {
                          continue;
                        }
                      }
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = mailbox('get', 'alias_details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "domain-admin":
            switch ($object) {
              case "all":
                $domain_admins = domain_admin('get');
                if (!empty($domain_admins)) {
                  foreach ($domain_admins as $domain_admin) {
                    if ($details = domain_admin('details', $domain_admin)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  if (!isset($data) || empty($data)) {
                    echo '{}';
                  }
                  else {
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                  }
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = domain_admin('details', $object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "u2f-registration":
            header('Content-Type: application/javascript');
            if (($_SESSION["mailcow_cc_role"] == "admin" || $_SESSION["mailcow_cc_role"] == "domainadmin") && $_SESSION["mailcow_cc_username"] == $object) {
              list($req, $sigs) = $u2f->getRegisterData(get_u2f_registrations($object));
              $_SESSION['regReq'] = json_encode($req);
              $_SESSION['regSigs'] = json_encode($sigs);
              echo 'var req = ' . json_encode($req) . ';';
              echo 'var registeredKeys = ' . json_encode($sigs) . ';';
              echo 'var appId = req.appId;';
              echo 'var registerRequests = [{version: req.version, challenge: req.challenge}];';
            }
            else {
              return;
            }
          break;
          case "u2f-authentication":
            header('Content-Type: application/javascript');
            if (isset($_SESSION['pending_mailcow_cc_username']) && $_SESSION['pending_mailcow_cc_username'] == $object) {
              $auth_data = $u2f->getAuthenticateData(get_u2f_registrations($object));
              $challenge = $auth_data[0]->challenge;
              $appId = $auth_data[0]->appId;
              foreach ($auth_data as $each) {
                $key = array(); // Empty array
                $key['version']   = $each->version;
                $key['keyHandle'] = $each->keyHandle;
                $registeredKey[]  = $key;
              }
              $_SESSION['authReq']  = json_encode($auth_data);
              echo 'var appId = "' . $appId . '";';
              echo 'var challenge = ' . json_encode($challenge) . ';';
              echo 'var registeredKeys = ' . json_encode($registeredKey) . ';';
            }
            else {
              return;
            }
          break;
          default:
            echo '{}';
          break;
        }
      break;
      case "delete":
        switch ($category) {
          case "alias":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'alias', array('address' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find address array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "relayhost":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (relayhost('delete', array('id' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find id array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "syncjob":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'syncjob', array('id' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find id array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "filter":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'filter', array('id' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find id array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "qitem":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (quarantine('delete', array('id' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find id array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "bcc":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (bcc('delete', array('id' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find id array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "recipient_map":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (recipient_map('delete', array('id' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find id array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "fwdhost":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (fwdhost('delete', array('forwardinghost' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find forwardinghost array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "dkim":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (dkim('delete', array('domains' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Deletion of items/s failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find domains array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "domain":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'domain', array('domain' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find domain array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "alias-domain":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'alias_domain', array('alias_domain' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find alias_domain array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "mailbox":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'mailbox', array('username' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find username array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "resource":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'resource', array('name' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find name array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "mailbox-policy":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (policy('delete', 'mailbox', array('prefid' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find name array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "domain-policy":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (policy('delete', 'domain', array('prefid' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find name array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "time_limited_alias":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'time_limited_alias', array('address' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find name array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "eas_cache":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (mailbox('delete', 'eas_cache', array('username' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find name array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
          case "domain-admin":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (domain_admin('delete', array('username' => $items)) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Task failed'
                    ));
                  }
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Cannot find name array in post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find items in post data'
              ));
            }
          break;
        }
      break;
      case "edit":
        switch ($category) {
          case "bcc":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('id' => $items), $attr);
              if (is_array($postarray['id'])) {
                if (bcc('edit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "recipient_map":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('id' => $items), $attr);
              if (is_array($postarray['id'])) {
                if (recipient_map('edit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "alias":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('address' => $items), $attr);
              if (is_array($postarray['address'])) {
                if (mailbox('edit', 'alias', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "app_links":
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (is_array($attr)) {
                if (customize('edit', 'app_links', $attr) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "relayhost":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('id' => $items), $attr);
              if (is_array($postarray['id'])) {
                if (relayhost('edit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "delimiter_action":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('username' => $items), $attr);
              if (is_array($postarray['username'])) {
                if (mailbox('edit', 'delimiter_action', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "tls_policy":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('username' => $items), $attr);
              if (is_array($postarray['username'])) {
                if (mailbox('edit', 'tls_policy', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "qitem":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('id' => $items), $attr);
              if (is_array($postarray['id'])) {
                if (quarantine('edit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "quarantine":
            // Edit settings, does not need IDs
            if (isset($_POST['attr'])) {
              $postarray = json_decode($_POST['attr'], true);
              if (quarantine('edit', $postarray) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Edit failed'
                  ));
                }
                exit();
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "time_limited_alias":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('address' => $items), $attr);
              if (is_array($postarray['address'])) {
                if (mailbox('edit', 'time_limited_alias', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "mailbox":
            // sender_acl:0 removes all entries
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('username' => $items), $attr);
              if (is_array($postarray['username'])) {
                if (mailbox('edit', 'mailbox', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "syncjob":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('id' => $items), $attr);
              if (is_array($postarray['id'])) {
                if (mailbox('edit', 'syncjob', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "filter":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('id' => $items), $attr);
              if (is_array($postarray['id'])) {
                if (mailbox('edit', 'filter', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;          
          case "resource":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('name' => $items), $attr);
              if (is_array($postarray['name'])) {
                if (mailbox('edit', 'resource', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "domain":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('domain' => $items), $attr);
              if (is_array($postarray['domain'])) {
                if (mailbox('edit', 'domain', $postarray)) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "ratelimit":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('object' => $items), $attr);
              if (is_array($postarray['object'])) {
                if (mailbox('edit', 'ratelimit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "alias-domain":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('alias_domain' => $items), $attr);
              if (is_array($postarray['alias_domain'])) {
                if (mailbox('edit', 'alias_domain', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "spam-score":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('username' => $items), $attr);
              if (is_array($postarray['username'])) {
                if (mailbox('edit', 'spam_score', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "domain-admin":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('username' => $items), $attr);
              if (is_array($postarray['username'])) {
                if (domain_admin('edit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "fwdhost":
            if (isset($_POST['items']) && isset($_POST['attr'])) {
              $items = (array)json_decode($_POST['items'], true);
              $attr = (array)json_decode($_POST['attr'], true);
              $postarray = array_merge(array('fwdhost' => $items), $attr);
              if (is_array($postarray['fwdhost'])) {
                if (fwdhost('edit', $postarray) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "fail2ban":
            // No items
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (fail2ban('edit', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Edit failed'
                  ));
                }
                exit();
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "ui_texts":
            // No items
            if (isset($_POST['attr'])) {
              $attr = (array)json_decode($_POST['attr'], true);
              if (customize('edit', 'ui_texts', $attr) === false) {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'error',
                    'msg' => 'Edit failed'
                  ));
                }
                exit();
              }
              else {
                if (isset($_SESSION['return'])) {
                  echo json_encode($_SESSION['return']);
                }
                else {
                  echo json_encode(array(
                    'type' => 'success',
                    'msg' => 'Task completed'
                  ));
                }
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'msg' => 'Incomplete post data'
              ));
            }
          break;
          case "self":
            // No items, logged-in user, users and domain admins
            if ($_SESSION['mailcow_cc_role'] == "domainadmin") {
              if (isset($_POST['attr'])) {
                $attr = (array)json_decode($_POST['attr'], true);
                if (domain_admin('edit', $attr) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            elseif ($_SESSION['mailcow_cc_role'] == "user") {
              if (isset($_POST['attr'])) {
                $attr = (array)json_decode($_POST['attr'], true);
                if (edit_user_account($attr) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
            elseif ($_SESSION['mailcow_cc_role'] == "admin") {
              if (isset($_POST['attr'])) {
                $attr = (array)json_decode($_POST['attr'], true);
                if (edit_admin_account($attr) === false) {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'error',
                      'msg' => 'Edit failed'
                    ));
                  }
                  exit();
                }
                else {
                  if (isset($_SESSION['return'])) {
                    echo json_encode($_SESSION['return']);
                  }
                  else {
                    echo json_encode(array(
                      'type' => 'success',
                      'msg' => 'Task completed'
                    ));
                  }
                }
              }
              else {
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'Incomplete post data'
                ));
              }
            }
          break;
        }
      break;
    }
  }
}
