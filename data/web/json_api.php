<?php
require_once 'inc/prerequisites.inc.php';
error_reporting(E_ALL);
if (isset($_SESSION['mailcow_cc_role']) || isset($_SESSION['pending_mailcow_cc_username'])) {
  if (isset($_GET['action']) && isset($_GET['object'])) {
    $action = filter_input(INPUT_GET, 'action',  FILTER_SANITIZE_STRING);
    $object   = filter_input(INPUT_GET, 'object',  FILTER_SANITIZE_STRING);
    switch ($action) {
      case "domain":
        switch ($object) {
          case "all":
            $domains = mailbox_get_domains();
            if (!empty($domains)) {
              foreach ($domains as $domain) {
                $data[] = mailbox_get_domain_details($domain);
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
              echo json_encode(mailbox_get_domain_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
                    $data[] = mailbox_get_mailbox_details($mailbox);
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
              echo json_encode(mailbox_get_mailbox_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
                    $data[] = mailbox_get_resource_details($resource);
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
              echo json_encode(mailbox_get_resource_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
                    $data[] = mailbox_get_alias_domain_details($alias_domain);
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
              echo json_encode(mailbox_get_alias_domains($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
                    $data[] = mailbox_get_alias_details($alias);
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
              echo json_encode(mailbox_get_alias_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
                $data[] = get_domain_admin_details($domain_admin);
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
              echo json_encode(get_domain_admin_details($object), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
          break;
        }
      break;
      case "u2f-registration":
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
  }
}