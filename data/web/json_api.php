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
require_once 'inc/prerequisites.inc.php';
error_reporting(0);
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
              if (add_domain_admin($attr) === false) {
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
          case "logs":
            switch ($object) {
              case "dovecot":
                if (isset($extra) && !empty($extra)) {
                  $extra = intval($extra);
                  $logs = get_logs('dovecot-mailcow', $extra);
                }
                else {
                  $logs = get_logs('dovecot-mailcow', -1);
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "fail2ban":
                if (isset($extra) && !empty($extra)) {
                  $extra = intval($extra);
                  $logs = get_logs('fail2ban-mailcow', $extra);
                }
                else {
                  $logs = get_logs('fail2ban-mailcow', -1);
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "postfix":
                if (isset($extra) && !empty($extra)) {
                  $extra = intval($extra);
                  $logs = get_logs('postfix-mailcow', $extra);
                }
                else {
                  $logs = get_logs('postfix-mailcow', -1);
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "sogo":
                if (isset($extra) && !empty($extra)) {
                  $extra = intval($extra);
                  $logs = get_logs('sogo-mailcow', $extra);
                }
                else {
                  $logs = get_logs('sogo-mailcow', -1);
                }
                if (isset($logs) && !empty($logs)) {
                  echo json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
              case "rspamd-history":
                $logs = get_logs('rspamd-history');
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
              default:
                $data = mailbox('get', 'syncjobs', $object);
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
                $domain_admins = get_domain_admins();
                if (!empty($domain_admins)) {
                  foreach ($domain_admins as $domain_admin) {
                    if ($details = get_domain_admin_details($domain_admin)) {
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
                $data = get_domain_admin_details($object);
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
              $data = $u2f->getRegisterData(get_u2f_registrations($object));
              list($req, $sigs) = $data;
              $_SESSION['regReq'] = json_encode($req);
              echo 'var req = ' . json_encode($req) . '; var sigs = ' . json_encode($sigs) . ';';
            }
            else {
              return;
            }
          break;
          case "u2f-authentication":
            header('Content-Type: application/javascript');
            if (isset($_SESSION['pending_mailcow_cc_username']) && $_SESSION['pending_mailcow_cc_username'] == $object) {
              $reqs = json_encode($u2f->getAuthenticateData(get_u2f_registrations($object)));
              $_SESSION['authReq']  = $reqs;
              echo 'var req = ' . $reqs . ';';
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
                if (delete_domain_admin(array('username' => $items)) === false) {
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
                if (mailbox('edit', 'domain', $postarray) === false) {
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
          case "spam_score":
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
                if (edit_domain_admin($postarray) === false) {
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
          case "admin":
            // No items as there is only one admin
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
          break;
        }
      break;
    }
  }
}
