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
            if (isset($_POST['address'])) {
              $address = json_decode($_POST['address'], true);
              if (is_array($address)) {
                if (mailbox_delete_alias(array('address' => $address)) === false) {
                  echo json_encode(array(
                    'type' => 'error',
                    'message' => 'Deletion of item/s failed'
                  ));
                  exit();
                }
                echo json_encode(array(
                  'type' => 'success',
                  'message' => 'Task completed'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'message' => 'Cannot find address array in post data'
              ));
            }
          break;
          case "fwdhost":
            if (isset($_POST['forwardinghost'])) {
              $forwardinghost = json_decode($_POST['forwardinghost'], true);
              if (is_array($forwardinghost)) {
                if (delete_forwarding_host(array('forwardinghost' => $forwardinghost)) === false) {
                  echo json_encode(array(
                    'type' => 'error',
                    'message' => 'Deletion of item/s failed'
                  ));
                  exit();
                }
                echo json_encode(array(
                  'type' => 'success',
                  'message' => 'Task completed'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'message' => 'Cannot find forwardinghost array in post data'
              ));
            }
          break;
        }
      break;
      case "edit":
        switch ($category) {
          case "alias":
            if (isset($_POST['address']) && isset($_POST['active'])) {
              $address = json_decode($_POST['address'], true);
              if (is_array($address)) {
                if (mailbox_edit_alias(array('address' => $address, 'active' => ($_POST['active'] == "1") ? $active = 1 : null)) === false) {
                  echo json_encode(array(
                    'type' => 'error',
                    'message' => 'Edit item failed'
                  ));
                  exit();
                }
                echo json_encode(array(
                  'type' => 'success',
                  'message' => 'Task completed'
                ));
              }
            }
            else {
              echo json_encode(array(
                'type' => 'error',
                'message' => 'Cannot find address array in post data'
              ));
            }
          break;
        }
      break;
    }
  }
}
