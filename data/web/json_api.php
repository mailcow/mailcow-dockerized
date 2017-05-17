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
      case "get":
        switch ($category) {
          case "domain":
            switch ($object) {
              case "all":
                $domains = mailbox_get_domains();
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    if ($details = mailbox_get_domain_details($domain)) {
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
                $data = mailbox_get_domain_details($object);
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
            }
          break;
          case "mailbox":
            switch ($object) {
              case "all":
                $domains = mailbox_get_domains();
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $mailboxes = mailbox_get_mailboxes($domain);
                    if (!empty($mailboxes)) {
                      foreach ($mailboxes as $mailbox) {
                        if ($details = mailbox_get_mailbox_details($mailbox)) {
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
                $data = mailbox_get_mailbox_details($object);
                if (!isset($data) || empty($data)) {
                  echo '{}';
                }
                else {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
              break;
            }
          break;
          case "syncjob":
            switch ($object) {
              default:
                $data = get_syncjobs($object);
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
                $domains = mailbox_get_domains();
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $resources = mailbox_get_resources($domain);
                    if (!empty($resources)) {
                      foreach ($resources as $resource) {
                        if ($details = mailbox_get_resource_details($resource)) {
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
                $data = mailbox_get_resource_details($object);
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
                $fwdhosts = get_forwarding_hosts();
                if (!empty($fwdhosts)) {
                  foreach ($fwdhosts as $fwdhost) {
                    if ($details = get_forwarding_host_details($fwdhost)) {
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
                $data = get_forwarding_host_details($object);
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
                $domains = mailbox_get_domains();
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $alias_domains = mailbox_get_alias_domains($domain);
                    if (!empty($alias_domains)) {
                      foreach ($alias_domains as $alias_domain) {
                        if ($details = mailbox_get_alias_domain_details($alias_domain)) {
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
                $data = mailbox_get_alias_domains($object);
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
                $domains = array_merge(mailbox_get_domains(), mailbox_get_alias_domains());
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $aliases = mailbox_get_aliases($domain);
                    if (!empty($aliases)) {
                      foreach ($aliases as $alias) {
                        if ($details = mailbox_get_alias_details($alias)) {
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
                $data = mailbox_get_alias_details($object);
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
                if (mailbox_delete_alias(array('address' => $items)) === false) {
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
          case "fwdhost":
            if (isset($_POST['items'])) {
              $items = (array)json_decode($_POST['items'], true);
              if (is_array($items)) {
                if (delete_forwarding_host(array('forwardinghost' => $items)) === false) {
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
                if (dkim_delete_key(array('domains' => $items)) === false) {
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
                if (mailbox_delete_domain(array('domain' => $items)) === false) {
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
                if (mailbox_delete_alias_domain(array('alias_domain' => $items)) === false) {
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
                if (mailbox_delete_mailbox(array('username' => $items)) === false) {
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
                if (mailbox_delete_resource(array('name' => $items)) === false) {
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
                if (mailbox_edit_alias($postarray) === false) {
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
                if (mailbox_edit_mailbox($postarray) === false) {
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
                if (edit_syncjob($postarray) === false) {
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
                if (mailbox_edit_resource($postarray) === false) {
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
                if (mailbox_edit_domain($postarray) === false) {
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
                if (mailbox_edit_alias_domain($postarray) === false) {
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
        }
      break;
    }
  }
}
