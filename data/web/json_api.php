<?php
/*
   see /api
*/
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
cors("set_headers");
header('Content-Type: application/json');
error_reporting(0);

function api_log($_data) {
  global $redis;
  $data_var = array();
  foreach ($_data as $data => &$value) {
    if ($data == 'csrf_token') {
      continue;
    }

    $value = json_decode($value, true);
    if ($value) {
      if (is_array($value)) unset($value["csrf_token"]);
      foreach ($value as $key => &$val) {
        if(preg_match("/pass/i", $key)) {
          $val = '*';
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
      'remote' => get_remote_ip(),
      'data' => implode(', ', $data_var)
    );
    $redis->lPush('API_LOG', json_encode($log_line));
  }
  catch (RedisException $e) {
    $_SESSION['return'][] = array(
      'type' => 'danger',
      'msg' => 'Redis: '.$e
    );
    return false;
  }
}

// Block requests not intended for direct API use by checking the 'Sec-Fetch-Dest' header.
if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] !== 'empty') {
  header('HTTP/1.1 403 Forbidden');
  exit;
}

if (isset($_GET['query'])) {

  $query = explode('/', $_GET['query']);
  $action =     (isset($query[0])) ? $query[0] : null;
  $category =   (isset($query[1])) ? $query[1] : null;
  $object =     (isset($query[2])) ? $query[2] : null;
  $extra =      (isset($query[3])) ? $query[3] : null;

  // accept json in request body
  if(strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) {
    $request = file_get_contents('php://input');
    $requestDecoded = json_decode($request, true);

    // check for valid json
    if ($action != 'get' && $requestDecoded === null) {
      http_response_code(400);
      echo json_encode(array(
          'type' => 'error',
          'msg' => 'Request body doesn\'t contain valid json!'
      ));
      exit;
    }

    // add
    if ($action == 'add') {
      $_POST['attr'] = $request;
    }

    // edit
    if ($action == 'edit') {
      $_POST['attr']  = json_encode($requestDecoded['attr']);
      $_POST['items'] = json_encode($requestDecoded['items']);
    }

    // delete
    if ($action == 'delete') {
      $_POST['items'] = $request;
    }
  }
  api_log($_POST);


  $request_incomplete = json_encode(array(
    'type' => 'error',
    'msg' => 'Cannot find attributes in post data'
  ));

  switch ($action) {
    case "add":
      if ($_SESSION['mailcow_cc_api_access'] == 'ro' || isset($_SESSION['pending_mailcow_cc_username'])) {
        http_response_code(403);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'API read/write access denied'
        ));
        exit();
      }
      function process_add_return($return) {
        $generic_failure = json_encode(array(
          'type' => 'error',
          'msg' => 'Cannot add item'
        ));
        $generic_success = json_encode(array(
          'type' => 'success',
          'msg' => 'Task completed'
        ));
        if ($return === false) {
          echo isset($_SESSION['return']) ? json_encode($_SESSION['return']) : $generic_failure;
        }
        else {
          echo isset($_SESSION['return']) ? json_encode($_SESSION['return']) : $generic_success;
        }
      }
      if (!isset($_POST['attr']) && $category != "fido2-registration" && $category != "webauthn-tfa-registration") {
        echo $request_incomplete;
        exit;
      }
      else {
        if ($category != "fido2-registration" && $category != "webauthn-tfa-registration") {
          $attr = (array)json_decode($_POST['attr'], true);
        }
        unset($attr['csrf_token']);
      }
      // only allow POST requests to POST API endpoints
      if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'only POST method is allowed'
        ));
        exit();
      }

      switch ($category) {
        // fido2-registration via POST
        case "fido2-registration":
          header('Content-Type: application/json');
          if (isset($_SESSION["mailcow_cc_role"])) {
            $post = trim(file_get_contents('php://input'));
            if ($post) {
              $post = json_decode($post);
            }
            $clientDataJSON = base64_decode($post->clientDataJSON);
            $attestationObject = base64_decode($post->attestationObject);
            $challenge = $_SESSION['challenge'];
            try {
              $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, $GLOBALS['FIDO2_UV_FLAG_REGISTER'], $GLOBALS['FIDO2_USER_PRESENT_FLAG']);
            }
            catch (Throwable $ex) {
              $return = new stdClass();
              $return->success = false;
              $return->msg = $ex->getMessage();
              echo json_encode($return);
              exit;
            }
            fido2(array("action" => "register", "registration" => $data));
            $return = new stdClass();
            $return->success = true;
            echo json_encode($return);
            exit;
          }
          else {
            echo $request_incomplete;
            exit;
          }
        break;
        case "webauthn-tfa-registration":
            if (isset($_SESSION["mailcow_cc_role"])) {
              // parse post data
              $post = trim(file_get_contents('php://input'));
              if ($post) $post = json_decode($post);

              // process registration data from authenticator
              try {
                // decode base64 strings
                $clientDataJSON = base64_decode($post->clientDataJSON);
                $attestationObject = base64_decode($post->attestationObject);

                // processCreate($clientDataJSON, $attestationObject, $challenge, $requireUserVerification=false, $requireUserPresent=true, $failIfRootMismatch=true)
                $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $_SESSION['challenge'], false, true);

                // safe authenticator in mysql `tfa` table
                $_data['tfa_method'] = $post->tfa_method;
                $_data['key_id'] = $post->key_id;
                $_data['confirm_password'] = $post->confirm_password;
                $_data['registration'] = $data;
                set_tfa($_data);
              }
              catch (Throwable $ex) {
                // err
                $return = new stdClass();
                $return->success = false;
                $return->msg = $ex->getMessage();
                echo json_encode($return);
                exit;
              }


              // send response
              $return = new stdClass();
              $return->success = true;
              echo json_encode($return);
              exit;
            }
            else {
              // err - request incomplete
              echo $request_incomplete;
              exit;
            }
        break;
        case "time_limited_alias":
          process_add_return(mailbox('add', 'time_limited_alias', $attr));
        break;
        case "relayhost":
          process_add_return(relayhost('add', $attr));
        break;
        case "transport":
          process_add_return(transport('add', $attr));
        break;
        case "rsetting":
          process_add_return(rsettings('add', $attr));
        break;
        case "mailbox":
          switch ($object) {
            case "template":
              process_add_return(mailbox('add', 'mailbox_templates', $attr));
            break;
            default:
              process_add_return(mailbox('add', 'mailbox', $attr));
            break;
          }
        break;
        case "oauth2-client":
          process_add_return(oauth2('add', 'client', $attr));
        break;
        case "domain":
          switch ($object) {
            case "template":
              process_add_return(mailbox('add', 'domain_templates', $attr));
            break;
            default:
              process_add_return(mailbox('add', 'domain', $attr));
            break;
          }
        break;
        case "resource":
          process_add_return(mailbox('add', 'resource', $attr));
        break;
        case "alias":
          process_add_return(mailbox('add', 'alias', $attr));
        break;
        case "filter":
          process_add_return(mailbox('add', 'filter', $attr));
        break;
        case "global-filter":
          process_add_return(mailbox('add', 'global_filter', $attr));
        break;
        case "domain-policy":
          process_add_return(policy('add', 'domain', $attr));
        break;
        case "mailbox-policy":
          process_add_return(policy('add', 'mailbox', $attr));
        break;
        case "alias-domain":
          process_add_return(mailbox('add', 'alias_domain', $attr));
        break;
        case "fwdhost":
          process_add_return(fwdhost('add', $attr));
        break;
        case "dkim":
          process_add_return(dkim('add', $attr));
        break;
        case "dkim_duplicate":
          process_add_return(dkim('duplicate', $attr));
        break;
        case "dkim_import":
          process_add_return(dkim('import', $attr));
        break;
        case "domain-admin":
          process_add_return(domain_admin('add', $attr));
        break;
        case "sso":
          switch ($object) {
            case "domain-admin":
              $data = domain_admin_sso('issue', $attr);
              if($data) {
                echo json_encode($data);
                exit(0);
              }
              process_add_return($data);
            break;
          }
        break;
        case "admin":
          process_add_return(admin('add', $attr));
        break;
        case "syncjob":
          process_add_return(mailbox('add', 'syncjob', $attr));
        break;
        case "bcc":
          process_add_return(bcc('add', $attr));
        break;
        case "recipient_map":
          process_add_return(recipient_map('add', $attr));
        break;
        case "tls-policy-map":
          process_add_return(tls_policy_maps('add', $attr));
        break;
        case "app-passwd":
          process_add_return(app_passwd('add', $attr));
        break;
        // return no route found if no case is matched
        default:
          http_response_code(404);
          echo json_encode(array(
            'type' => 'error',
            'msg' => 'route not found'
          ));
          exit();
      }
    break;
    case "process":
      // only allow POST requests to process API endpoints
      if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'only POST method is allowed'
        ));
        exit();
      }
      switch ($category) {
        case "fido2-args":
          header('Content-Type: application/json');
          $post = trim(file_get_contents('php://input'));
          if ($post) {
            $post = json_decode($post);
          }
          $clientDataJSON = base64_decode($post->clientDataJSON);
          $authenticatorData = base64_decode($post->authenticatorData);
          $signature = base64_decode($post->signature);
          $id = base64_decode($post->id);
          $challenge = $_SESSION['challenge'];
          $process_fido2 = fido2(array("action" => "get_by_b64cid", "cid" => $post->id));
          if ($process_fido2['pub_key'] === false) {
            $return = new stdClass();
            $return->success = false;
            echo json_encode($return);
            exit;
          }
          try {
            $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $process_fido2['pub_key'], $challenge, null, $GLOBALS['FIDO2_UV_FLAG_LOGIN'], $GLOBALS['FIDO2_USER_PRESENT_FLAG']);
          }
          catch (Throwable $ex) {
            unset($process_fido2);
            $return = new stdClass();
            $return->success = false;
            echo json_encode($return);
            exit;
          }
          $return = new stdClass();
          $return->success = true;
          $stmt = $pdo->prepare("SELECT `superadmin` FROM `admin` WHERE `username` = :username");
          $stmt->execute(array(':username' => $process_fido2['username']));
          $obj_props = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($obj_props['superadmin'] === 1) {
            $_SESSION["mailcow_cc_role"] = "admin";
          }
          elseif ($obj_props['superadmin'] === 0) {
            $_SESSION["mailcow_cc_role"] = "domainadmin";
          }
          else {
            $stmt = $pdo->prepare("SELECT `username` FROM `mailbox` WHERE `username` = :username");
            $stmt->execute(array(':username' => $process_fido2['username']));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row['username'] == $process_fido2['username']) {
              $_SESSION["mailcow_cc_role"] = "user";
            }
          }
          if (empty($_SESSION["mailcow_cc_role"])) {
            session_unset();
            session_destroy();
            exit;
          }
          $_SESSION["mailcow_cc_username"] = $process_fido2['username'];
          $_SESSION["fido2_cid"] = $process_fido2['cid'];
          unset($_SESSION["challenge"]);
          $_SESSION['return'][] =  array(
            'type' => 'success',
            'log' => array("fido2_login"),
            'msg' => array('logged_in_as', $process_fido2['username'])
          );
          echo json_encode($return);
        break;
      }
    break;
    case "get":
      function process_get_return($data, $object = true) {
        if ($object === true) {
          $ret_str = '{}';
        }
        else {
          $ret_str = '[]';
        }
        echo (!isset($data) || empty($data)) ? $ret_str : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      }
      // only allow GET requests to GET API endpoints
      if ($_SERVER['REQUEST_METHOD'] != 'GET') {
        http_response_code(405);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'only GET method is allowed'
        ));
        exit();
      }
      switch ($category) {
        // fido2
        case "fido2-registration":
          header('Content-Type: application/json');
          if (isset($_SESSION["mailcow_cc_role"])) {
              // Exclude existing CredentialIds, if any
              $excludeCredentialIds = fido2(array("action" => "get_user_cids"));
              $createArgs = $WebAuthn->getCreateArgs($_SESSION["mailcow_cc_username"], $_SESSION["mailcow_cc_username"], $_SESSION["mailcow_cc_username"], 30, true, $GLOBALS['FIDO2_UV_FLAG_REGISTER'], null, $excludeCredentialIds);
              print(json_encode($createArgs));
              $_SESSION['challenge'] = $WebAuthn->getChallenge();
              return;
          }
          else {
            return;
          }
        break;
        case "fido2-get-args":
          header('Content-Type: application/json');
          // Login without username, no ids!
          // $ids = fido2(array("action" => "get_all_cids"));
          // if (count($ids) == 0) {
            // return;
          // }
          $ids = NULL;

          $getArgs = $WebAuthn->getGetArgs($ids, 30, false, false, false, false, $GLOBALS['FIDO2_UV_FLAG_LOGIN']);
          print(json_encode($getArgs));
          $_SESSION['challenge'] = $WebAuthn->getChallenge();
          return;
        break;
        // webauthn two factor authentication
        case "webauthn-tfa-registration":
          if (isset($_SESSION["mailcow_cc_role"])) {
              // Exclude existing CredentialIds, if any
              $stmt = $pdo->prepare("SELECT `keyHandle` FROM `tfa` WHERE username = :username AND authmech = :authmech");
              $stmt->execute(array(
                ':username' => $_SESSION['mailcow_cc_username'],
                ':authmech' => 'webauthn'
              ));
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
              while($row = array_shift($rows)) {
                $excludeCredentialIds[] = base64_decode($row['keyHandle']);
              }
              // getCreateArgs($userId, $userName, $userDisplayName, $timeout=20, $requireResidentKey=false, $requireUserVerification=false, $crossPlatformAttachment=null, $excludeCredentialIds=array())
              // cross-platform: true, if type internal is not allowed
              //        false, if only internal is allowed
              //        null, if internal and cross-platform is allowed
              $createArgs = $WebAuthn->getCreateArgs($_SESSION["mailcow_cc_username"], $_SESSION["mailcow_cc_username"], $_SESSION["mailcow_cc_username"], 30, false, $GLOBALS['WEBAUTHN_UV_FLAG_REGISTER'], null, $excludeCredentialIds);

              print(json_encode($createArgs));
              $_SESSION['challenge'] = $WebAuthn->getChallenge();
              return;

          }
          else {
            return;
          }
        break;
        case "webauthn-tfa-get-args":
          $stmt = $pdo->prepare("SELECT `keyHandle` FROM `tfa` WHERE username = :username AND authmech = :authmech");
          $stmt->execute(array(
            ':username' => $_SESSION['pending_mailcow_cc_username'],
            ':authmech' => 'webauthn'
          ));
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          if (count($rows) == 0) {
            print(json_encode(array(
                'type' => 'error',
                'msg' => 'Cannot find matching credentialIds'
            )));
            exit;
          }
          while($row = array_shift($rows)) {
            $cids[] = base64_decode($row['keyHandle']);
          }

          $getArgs = $WebAuthn->getGetArgs($cids, 30, false, false, false, false, $GLOBALS['WEBAUTHN_UV_FLAG_LOGIN']);
          $getArgs->publicKey->extensions = array('appid' => "https://".$getArgs->publicKey->rpId);
          print(json_encode($getArgs));
          $_SESSION['challenge'] = $WebAuthn->getChallenge();
          return;
        break;
        case "fail2ban":
          if (!isset($_SESSION['mailcow_cc_role'])){
            switch ($object) {
              case 'banlist':
                header('Content-Type: text/plain');
                echo fail2ban('banlist', 'get', $extra);
              break;
            }
          }
        break;
      }
      if (isset($_SESSION['mailcow_cc_role'])) {
        switch ($category) {
          case "rspamd":
            switch ($object) {
              case "actions":
                $data = rspamd_actions();
                if ($data) {
                  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                else {
                  echo '{}';
                }
              break;
            }
          break;

          case "domain":
            switch ($object) {
              case "datatables":
                $table = ['domain', 'd'];
                $primaryKey = 'domain';
                $columns = [
                  ['db' => 'domain', 'dt' => 2],
                  ['db' => 'aliases', 'dt' => 3, 'order_subquery' => "SELECT COUNT(*) FROM `alias` WHERE (`domain`= `d`.`domain` OR `domain` IN (SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = `d`.`domain`)) AND `address` NOT IN (SELECT `username` FROM `mailbox`)"],
                  ['db' => 'mailboxes', 'dt' => 4, 'order_subquery' => "SELECT COUNT(*) FROM `mailbox` WHERE `mailbox`.`domain` = `d`.`domain` AND (`mailbox`.`kind` = '' OR `mailbox`.`kind` = NULL)"],
                  ['db' => 'quota', 'dt' => 5, 'order_subquery' => "SELECT COALESCE(SUM(`mailbox`.`quota`), 0) FROM `mailbox` WHERE `mailbox`.`domain` = `d`.`domain` AND (`mailbox`.`kind` = '' OR `mailbox`.`kind` = NULL)"],
                  ['db' => 'stats', 'dt' => 6, 'dummy' => true, 'order_subquery' => "SELECT SUM(bytes) FROM `quota2` WHERE `quota2`.`username` IN (SELECT `username` FROM `mailbox` WHERE `domain` = `d`.`domain`)"],
                  ['db' => 'defquota', 'dt' => 7],
                  ['db' => 'maxquota', 'dt' => 8],
                  ['db' => 'backupmx', 'dt' => 10],
                  ['db' => 'tags', 'dt' => 14, 'dummy' => true, 'search' => ['join' => 'LEFT JOIN `tags_domain` AS `td` ON `td`.`domain` = `d`.`domain`', 'where_column' => '`td`.`tag_name`']],
                  ['db' => 'active', 'dt' => 15],
                ];

                require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/ssp.class.php';
                global $pdo;
                if($_SESSION['mailcow_cc_role'] === 'admin') {
                  $data = SSP::simple($_GET, $pdo, $table, $primaryKey, $columns);
                } elseif ($_SESSION['mailcow_cc_role'] === 'domainadmin') {
                  $data = SSP::complex($_GET, $pdo, $table, $primaryKey, $columns,
                    'INNER JOIN domain_admins as da ON da.domain = d.domain',
                    [
                      'condition' => 'da.active = 1 and da.username = :username',
                      'bindings' => ['username' => $_SESSION['mailcow_cc_username']]
                    ]);
                }

                if (!empty($data['data'])) {
                  $domainsData = [];
                  foreach ($data['data'] as $domain) {
                    if ($details = mailbox('get', 'domain_details', $domain[2])) {
                      $domainsData[] = $details;
                    }
                  }
                  $data['data'] = $domainsData;
                }

                process_get_return($data);
              break;
              case "all":
                $tags = null;
                if (isset($_GET['tags']) && $_GET['tags'] != '')
                  $tags = explode(',', $_GET['tags']);

                $domains = mailbox('get', 'domains', null, $tags);

                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    if ($details = mailbox('get', 'domain_details', $domain)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;
              case "template":
                switch ($extra){
                  case "all":
                    process_get_return(mailbox('get', 'domain_templates'));
                  break;
                  default:
                    process_get_return(mailbox('get', 'domain_templates', $extra));
                  break;
                }
              break;
              default:
                $data = mailbox('get', 'domain_details', $object);
                process_get_return($data);
              break;
            }
          break;

          case "passwordpolicy":
            switch ($object) {
              case "html":
                $password_complexity_rules = password_complexity('html');
                if ($password_complexity_rules !== false) {
                  process_get_return($password_complexity_rules);
                }
                else {
                  echo '{}';
                }
              break;
              default:
                $password_complexity_rules = password_complexity('get');
                if ($password_complexity_rules !== false) {
                  process_get_return($password_complexity_rules);
                }
                else {
                  echo '{}';
                }
              break;
            }
          break;

          case "app-passwd":
            switch ($object) {
              case "all":
                if (empty($extra)) {
                  $app_passwds = app_passwd('get');
                }
                else {
                  $app_passwds = app_passwd('get', array('username' => $extra));
                }
                if (!empty($app_passwds)) {
                  foreach ($app_passwds as $app_passwd) {
                    $details = app_passwd('details', $app_passwd['id']);
                    if ($details !== false) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = app_passwd('details', array('id' => $object['id']));
                process_get_return($data);
              break;
            }
          break;

          case "mailq":
            switch ($object) {
              case "all":
                $mailq = mailq('get');
                if (!empty($mailq)) {
                  echo $mailq;
                }
                else {
                  echo '[]';
                }
              break;
            }
          break;

          case "postcat":
            switch ($object) {
              default:
                $data = mailq('cat', array('qid' => $object));
                echo $data;
              break;
            }
          break;

          case "global_filters":
            $global_filters = mailbox('get', 'global_filter_details');
            switch ($object) {
              case "all":
                if (!empty($global_filters)) {
                  process_get_return($global_filters);
                }
                else {
                  echo '{}';
                }
              break;
              case "prefilter":
                if (!empty($global_filters['prefilter'])) {
                  process_get_return($global_filters['prefilter']);
                }
                else {
                  echo '{}';
                }
              break;
              case "postfilter":
                if (!empty($global_filters['postfilter'])) {
                  process_get_return($global_filters['postfilter']);
                }
                else {
                  echo '{}';
                }
              break;
            }
          break;

          case "rl-domain":
            switch ($object) {
              case "all":
                $domains = array_merge(mailbox('get', 'domains'), mailbox('get', 'alias_domains'));
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    if ($details = ratelimit('get', 'domain', $domain)) {
                      $details['domain'] = $domain;
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = ratelimit('get', 'domain', $object);
                process_get_return($data);
              break;
            }
          break;

          case "rl-mbox":
            switch ($object) {
              case "all":
                $domains = mailbox('get', 'domains');
                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $mailboxes = mailbox('get', 'mailboxes', $domain);
                    if (!empty($mailboxes)) {
                      foreach ($mailboxes as $mailbox) {
                        if ($details = ratelimit('get', 'mailbox', $mailbox)) {
                          $details['mailbox'] = $mailbox;
                          $data[] = $details;
                        }
                        else {
                          continue;
                        }
                      }
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = ratelimit('get', 'mailbox', $object);
                process_get_return($data);
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
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = relayhost('details', $object);
                process_get_return($data);
              break;
            }
          break;

          case "last-login":
            if ($object) {
              // extra == days
              if (isset($extra) && intval($extra) >= 1) {
                $data = last_login('get', $object, intval($extra));
              }
              else {
                $data = last_login('get', $object);
              }
              process_get_return($data);
            }
          break;

          // Todo: move to delete
          case "reset-last-login":
            if ($object) {
              $data = last_login('reset', $object);
              process_get_return($data);
            }
          break;

          case "transport":
            switch ($object) {
              case "all":
                $transports = transport('get');
                if (!empty($transports)) {
                  foreach ($transports as $transport) {
                    if ($details = transport('details', $transport['id'])) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = transport('details', $object);
                process_get_return($data);
              break;
            }
          break;

          case "rsetting":
            switch ($object) {
              case "all":
                $rsettings = rsettings('get');
                if (!empty($rsettings)) {
                  foreach ($rsettings as $rsetting) {
                    if ($details = rsettings('details', $rsetting['id'])) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = rsettings('details', $object);
                process_get_return($data);
              break;
            }
          break;

          case "oauth2-client":
            switch ($object) {
              case "all":
                $clients = oauth2('get', 'clients');
                if (!empty($clients)) {
                  foreach ($clients as $client) {
                    if ($details = oauth2('details', 'client', $client)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                $data = oauth2('details', 'client', $object);
                process_get_return($data);
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
              break;
              case "ratelimited":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('ratelimited', $extra);
                }
                else {
                  $logs = get_logs('ratelimited');
                }
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
              break;
              case "ui":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('mailcow-ui', $extra);
                }
                else {
                  $logs = get_logs('mailcow-ui');
                }
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
              break;
              case "sasl":
                // 0 is first record, so empty is fine
                if (isset($extra)) {
                  $extra = preg_replace('/[^\d\-]/i', '', $extra);
                  $logs = get_logs('sasl', $extra);
                }
                else {
                  $logs = get_logs('sasl');
                }
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
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
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
              break;
              case "rspamd-stats":
                $logs = get_logs('rspamd-stats');
                echo (isset($logs) && !empty($logs)) ? json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';
              break;
              // return no route found if no case is matched
              default:
                http_response_code(404);
                echo json_encode(array(
                  'type' => 'error',
                  'msg' => 'route not found'
                ));
                exit();
            }
          break;
          case "mailbox":
            switch ($object) {
              case "datatables":
                $table = ['mailbox', 'm'];
                $primaryKey = 'username';
                $columns = [
                  ['db' => 'username', 'dt' => 2],
                  ['db' => 'quota', 'dt' => 3],
                  ['db' => 'last_mail_login', 'dt' => 4, 'dummy' => true, 'order_subquery' => "SELECT MAX(`datetime`) FROM `sasl_log` WHERE `service` != 'SSO' AND `username` = `m`.`username`"],
                  ['db' => 'last_pw_change', 'dt' => 5, 'dummy' => true, 'order_subquery' => "JSON_EXTRACT(attributes, '$.passwd_update')"],
                  ['db' => 'in_use', 'dt' => 6, 'dummy' => true, 'order_subquery' => "(SELECT SUM(bytes) FROM `quota2` WHERE `quota2`.`username` = `m`.`username`) / `m`.`quota`"],
                  ['db' => 'messages', 'dt' => 17, 'dummy' => true, 'order_subquery' => "SELECT SUM(messages) FROM `quota2` WHERE `quota2`.`username` = `m`.`username`"],
                  ['db' => 'tags', 'dt' => 20, 'dummy' => true, 'search' => ['join' => 'LEFT JOIN `tags_mailbox` AS `tm` ON `tm`.`username` = `m`.`username`', 'where_column' => '`tm`.`tag_name`']],
                  ['db' => 'active', 'dt' => 21]
                ];

                require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/ssp.class.php';
                global $pdo;
                if($_SESSION['mailcow_cc_role'] === 'admin') {
                  $data = SSP::complex($_GET, $pdo, $table, $primaryKey, $columns, null, "(`m`.`kind` = '' OR `m`.`kind` = NULL)");
                } elseif ($_SESSION['mailcow_cc_role'] === 'domainadmin') {
                  $data = SSP::complex($_GET, $pdo, $table, $primaryKey, $columns,
                    'INNER JOIN domain_admins as da ON da.domain = m.domain',
                    [
                      'condition' => "(`m`.`kind` = '' OR `m`.`kind` = NULL) AND `da`.`active` = 1 AND `da`.`username` = :username",
                      'bindings' => ['username' => $_SESSION['mailcow_cc_username']]
                    ]);
                }

                if (!empty($data['data'])) {
                  $mailboxData = [];
                  foreach ($data['data'] as $mailbox) {
                    if ($details = mailbox('get', 'mailbox_details', $mailbox[2])) {
                      $mailboxData[] = $details;
                    }
                  }
                  $data['data'] = $mailboxData;
                }

                process_get_return($data);
              break;
              case "all":
              case "reduced":
                $tags = null;
                if (isset($_GET['tags']) && $_GET['tags'] != '')
                  $tags = explode(',', $_GET['tags']);

                if (empty($extra)) $domains = mailbox('get', 'domains');
                else $domains = explode(',', $extra);

                if (!empty($domains)) {
                  foreach ($domains as $domain) {
                    $mailboxes = mailbox('get', 'mailboxes', $domain, $tags);
                    if (!empty($mailboxes)) {
                      foreach ($mailboxes as $mailbox) {
                        if ($details = mailbox('get', 'mailbox_details', $mailbox, $object)) $data[] = $details;
                        else continue;
                      }
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;
              case "template":
                switch ($extra){
                  case "all":
                    process_get_return(mailbox('get', 'mailbox_templates'));
                  break;
                  default:
                    process_get_return(mailbox('get', 'mailbox_templates', $extra));
                  break;
                }
              break;
              default:
                $tags = null;
                if (isset($_GET['tags']) && $_GET['tags'] != '')
                  $tags = explode(',', $_GET['tags']);

                if ($tags === null) {
                  $data = mailbox('get', 'mailbox_details', $object);
                  process_get_return($data);
                } else {
                  $mailboxes = mailbox('get', 'mailboxes', $object, $tags);
                  if (is_array($mailboxes)) {
                    foreach ($mailboxes as $mailbox) {
                      if ($details = mailbox('get', 'mailbox_details', $mailbox))
                        $data[] = $details;
                    }
                  }
                  process_get_return($data, false);
                }
              break;
            }
          break;
          case "bcc-destination-options":
            $domains = mailbox('get', 'domains');
            $alias_domains = mailbox('get', 'alias_domains');
            $data = array();
            if (!empty($domains)) {
              foreach ($domains as $domain) {
                $data['domains'][] = $domain;
                $mailboxes = mailbox('get', 'mailboxes', $domain);
                foreach ($mailboxes as $mailbox) {
                  $data['mailboxes'][$mailbox][] = $mailbox;
                  $user_alias_details = user_get_alias_details($mailbox);
                  foreach ($user_alias_details['direct_aliases'] as $k => $v) {
                    $data['mailboxes'][$mailbox][] = $k;
                  }
                  foreach ($user_alias_details['shared_aliases'] as $k => $v) {
                    $data['mailboxes'][$mailbox][] = $k;
                  }
                }
              }
            }
            if (!empty($alias_domains)) {
              foreach ($alias_domains as $alias_domain) {
                $data['alias_domains'][] = $alias_domain;
              }
            }
            process_get_return($data);
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
                  process_get_return($data);
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
                process_get_return($data);
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
            process_get_return($data);
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
                  process_get_return($data);
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
                process_get_return($data);
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
                process_get_return($data);
              break;
              default:
                $data = bcc('details', $object);
                if (!empty($data)) {
                  $data[] = $details;
                }
                process_get_return($data);
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
                process_get_return($data);
              break;
              default:
                $data = recipient_map('details', $object);
                if (!empty($data)) {
                  $data[] = $details;
                }
                process_get_return($data);
              break;
            }
          break;
          case "tls-policy-map":
            switch ($object) {
              case "all":
                $tls_policy_maps_items = tls_policy_maps('get');
                if (!empty($tls_policy_maps_items)) {
                  foreach ($tls_policy_maps_items as $tls_policy_maps_item) {
                    if ($details = tls_policy_maps('details', $tls_policy_maps_item)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                }
                process_get_return($data);
              break;
              default:
                $data = tls_policy_maps('details', $object);
                if (!empty($data)) {
                  $data[] = $details;
                }
                process_get_return($data);
              break;
            }
          break;
          case "policy_wl_mailbox":
            switch ($object) {
              default:
                $data = policy('get', 'mailbox', $object)['whitelist'];
                process_get_return($data);
              break;
            }
          break;
          case "policy_bl_mailbox":
            switch ($object) {
              default:
                $data = policy('get', 'mailbox', $object)['blacklist'];
                process_get_return($data);
              break;
            }
          break;
          case "policy_wl_domain":
            switch ($object) {
              default:
                $data = policy('get', 'domain', $object)['whitelist'];
                process_get_return($data);
              break;
            }
          break;
          case "policy_bl_domain":
            switch ($object) {
              default:
                $data = policy('get', 'domain', $object)['blacklist'];
                process_get_return($data);
              break;
            }
          break;
          case "time_limited_aliases":
            switch ($object) {
              default:
                $data = mailbox('get', 'time_limited_aliases', $object);
                process_get_return($data);
              break;
            }
          break;
          case "fail2ban":
            switch ($object) {
              case 'banlist':
                header('Content-Type: text/plain');
                echo fail2ban('banlist', 'get', $extra);
              break;
              default:
                $data = fail2ban('get');
                process_get_return($data);
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
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;
              default:
                $data = mailbox('get', 'resource_details', $object);
                process_get_return($data);
              break;
            }
          break;
          case "fwdhost":
            switch ($object) {
              case "all":
                process_get_return(fwdhost('get'));
              break;
              default:
                process_get_return(fwdhost('details', $object));
              break;
            }
          break;
          case "quarantine":
            // "all" will not print details
            switch ($object) {
              case "all":
                process_get_return(quarantine('get'), false);
              break;
              default:
                process_get_return(quarantine('details', $object), false);
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
                process_get_return($data);
              break;
              default:
                process_get_return(mailbox('get', 'alias_domain_details', $object));
              break;
            }
          break;
          case "alias":
            switch ($object) {
              case "all":
                if (empty($extra)) {
                  $domains = array_merge(mailbox('get', 'domains'), mailbox('get', 'alias_domains'));
                }
                else {
                  $domains = explode(',', $extra);
                }
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
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                process_get_return(mailbox('get', 'alias_details', $object));
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
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                process_get_return(domain_admin('details', $object));
              break;
            }
          break;
          case "admin":
            switch ($object) {
              case "all":
                $admins = admin('get');
                if (!empty($admins)) {
                  foreach ($admins as $admin) {
                    if ($details = admin('details', $admin)) {
                      $data[] = $details;
                    }
                    else {
                      continue;
                    }
                  }
                  process_get_return($data);
                }
                else {
                  echo '{}';
                }
              break;

              default:
                process_get_return(admin('details', $object));
              break;
            }
          break;
          case "dkim":
            switch ($object) {
              default:
                $data = dkim('details', $object);
                process_get_return($data);
                break;
            }
          break;
          case "presets":
            switch ($object) {
              case "rspamd":
                process_get_return(presets('get', 'rspamd'));
              break;
              case "sieve":
                process_get_return(presets('get', 'sieve'));
              break;
            }
          break;
          case "status":
            if ($_SESSION['mailcow_cc_role'] == "admin") {
              switch ($object) {
                case "containers":
                  $containers = (docker('info'));
                  foreach ($containers as $container => $container_info) {
                    $container . ' (' . $container_info['Config']['Image'] . ')';
                    $containerstarttime = ($container_info['State']['StartedAt']);
                    $containerstate = ($container_info['State']['Status']);
                    $containerimage = ($container_info['Config']['Image']);
                    $temp[$container] = array(
                      'type' => 'info',
                      'container' => $container,
                      'state' => $containerstate,
                      'started_at' => $containerstarttime,
                      'image' => $containerimage
                    );
                  }
                  echo json_encode($temp, JSON_UNESCAPED_SLASHES);
                break;
                case "container":
                  $container_stats = docker('container_stats', $extra);
                  echo json_encode($container_stats);
                break;
                case "vmail":
                  $exec_fields_vmail = array('cmd' => 'system', 'task' => 'df', 'dir' => '/var/vmail');
                  $vmail_df = explode(',', json_decode(docker('post', 'dovecot-mailcow', 'exec', $exec_fields_vmail), true));
                  $temp = array(
                    'type' => 'info',
                    'disk' => $vmail_df[0],
                    'used' => $vmail_df[2],
                    'total'=> $vmail_df[1],
                    'used_percent' => $vmail_df[4]
                  );
                  echo json_encode($temp, JSON_UNESCAPED_SLASHES);
                break;
                case "solr":
                  $solr_status = solr_status();
                  $solr_size = ($solr_status['status']['dovecot-fts']['index']['size']);
                  $solr_documents = ($solr_status['status']['dovecot-fts']['index']['numDocs']);
                  if (strtolower(getenv('SKIP_SOLR')) != 'n') {
                    $solr_enabled = false;
                  }
                  else {
                    $solr_enabled = true;
                  }
                  echo json_encode(array(
                    'type' => 'info',
                    'solr_enabled' => $solr_enabled,
                    'solr_size' => $solr_size,
                    'solr_documents' => $solr_documents
                  ));
                break;
                case "host":
                  if (!$extra){
                    $stats = docker("host_stats");
                    echo json_encode($stats);
                  }
                  else if ($extra == "ip") {
                    // get public ips

                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_POST, 0);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 15);
                    curl_setopt($curl, CURLOPT_URL, 'http://ipv4.mailcow.email');
                    $ipv4 = curl_exec($curl);
                    curl_setopt($curl, CURLOPT_URL, 'http://ipv6.mailcow.email');
                    $ipv6 = curl_exec($curl);
                    $ips = array(
                      "ipv4" => $ipv4,
                      "ipv6" => $ipv6
                    );
                    curl_close($curl);
                    echo json_encode($ips);
                  }
                break;
                case "version":
                  echo json_encode(array(
                    'version' => $GLOBALS['MAILCOW_GIT_VERSION']
                  ));
                break;
              }
            }
          break;
          case "spam-score":
            $score = mailbox('get', 'spam_score', $object);
            if ($score)
              $score = array("score" => preg_replace("/\s+/", "", $score));
            process_get_return($score);
          break;
        break;
        // return no route found if no case is matched
        default:
          http_response_code(404);
          echo json_encode(array(
            'type' => 'error',
            'msg' => 'route not found'
          ));
          exit();
        }
      }
    break;
    case "delete":
      if ($_SESSION['mailcow_cc_api_access'] == 'ro' || isset($_SESSION['pending_mailcow_cc_username']) || !isset($_SESSION["mailcow_cc_username"])) {
        http_response_code(403);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'API read/write access denied'
        ));
        exit();
      }
      function process_delete_return($return) {
        $generic_failure = json_encode(array(
          'type' => 'error',
          'msg' => 'Cannot delete item'
        ));
        $generic_success = json_encode(array(
          'type' => 'success',
          'msg' => 'Task completed'
        ));
        if ($return === false) {
          echo isset($_SESSION['return']) ? json_encode($_SESSION['return']) : $generic_failure;
        }
        else {
          echo isset($_SESSION['return']) ? json_encode($_SESSION['return']) : $generic_success;
        }
      }
      if (!isset($_POST['items'])) {
        echo $request_incomplete;
        exit;
      }
      else {
        $items = (array)json_decode($_POST['items'], true);
      }
      // only allow POST requests to POST API endpoints
      if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'only POST method is allowed'
        ));
        exit();
      }
      switch ($category) {
        case "alias":
          process_delete_return(mailbox('delete', 'alias', array('id' => $items)));
        break;
        case "oauth2-client":
          process_delete_return(oauth2('delete', 'client', array('id' => $items)));
        break;
        case "app-passwd":
          process_delete_return(app_passwd('delete', array('id' => $items)));
        break;
        case "relayhost":
          process_delete_return(relayhost('delete', array('id' => $items)));
        break;
        case "transport":
          process_delete_return(transport('delete', array('id' => $items)));
        break;
        case "rsetting":
          process_delete_return(rsettings('delete', array('id' => $items)));
        break;
        case "syncjob":
          process_delete_return(mailbox('delete', 'syncjob', array('id' => $items)));
        break;
        case "filter":
          process_delete_return(mailbox('delete', 'filter', array('id' => $items)));
        break;
        case "mailq":
          process_delete_return(mailq('delete', array('qid' => $items)));
        break;
        case "qitem":
          process_delete_return(quarantine('delete', array('id' => $items)));
        break;
        case "bcc":
          process_delete_return(bcc('delete', array('id' => $items)));
        break;
        case "recipient_map":
          process_delete_return(recipient_map('delete', array('id' => $items)));
        break;
        case "tls-policy-map":
          process_delete_return(tls_policy_maps('delete', array('id' => $items)));
        break;
        case "fwdhost":
          process_delete_return(fwdhost('delete', array('forwardinghost' => $items)));
        break;
        case "dkim":
          process_delete_return(dkim('delete', array('domains' => $items)));
        break;
        case "domain":
          switch ($object){
            case "tag":
              process_delete_return(mailbox('delete', 'tags_domain', array('tags' => $items, 'domain' => $extra)));
            break;
            case "template":
              process_delete_return(mailbox('delete', 'domain_templates', array('ids' => $items)));
            break;
            default:
              process_delete_return(mailbox('delete', 'domain', array('domain' => $items)));
          }
        break;
        case "alias-domain":
          process_delete_return(mailbox('delete', 'alias_domain', array('alias_domain' => $items)));
        break;
        case "mailbox":
          switch ($object){
            case "tag":
              process_delete_return(mailbox('delete', 'tags_mailbox', array('tags' => $items, 'username' => $extra)));
            break;
            case "template":
              process_delete_return(mailbox('delete', 'mailbox_templates', array('ids' => $items)));
            break;
            default:
              process_delete_return(mailbox('delete', 'mailbox', array('username' => $items)));
          }
        break;
        case "resource":
          process_delete_return(mailbox('delete', 'resource', array('name' => $items)));
        break;
        case "mailbox-policy":
          process_delete_return(policy('delete', 'mailbox', array('prefid' => $items)));
        break;
        case "domain-policy":
          process_delete_return(policy('delete', 'domain', array('prefid' => $items)));
        break;
        case "time_limited_alias":
          process_delete_return(mailbox('delete', 'time_limited_alias', array('address' => $items)));
        break;
        case "eas_cache":
          process_delete_return(mailbox('delete', 'eas_cache', array('username' => $items)));
        break;
        case "sogo_profile":
          process_delete_return(mailbox('delete', 'sogo_profile', array('username' => $items)));
        break;
        case "domain-admin":
          process_delete_return(domain_admin('delete', array('username' => $items)));
        break;
        case "admin":
          process_delete_return(admin('delete', array('username' => $items)));
        break;
        case "rlhash":
          echo ratelimit('delete', null, implode($items));
        break;
        // return no route found if no case is matched
        default:
          http_response_code(404);
          echo json_encode(array(
            'type' => 'error',
            'msg' => 'route not found'
          ));
          exit();
      }
    break;
    case "edit":
      if ($_SESSION['mailcow_cc_api_access'] == 'ro' || isset($_SESSION['pending_mailcow_cc_username']) || !isset($_SESSION["mailcow_cc_username"])) {
        http_response_code(403);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'API read/write access denied'
        ));
        exit();
      }
      function process_edit_return($return) {
        $generic_failure = json_encode(array(
          'type' => 'error',
          'msg' => 'Cannot edit item'
        ));
        $generic_success = json_encode(array(
          'type' => 'success',
          'msg' => 'Task completed'
        ));
        if ($return === false) {
          echo isset($_SESSION['return']) ? json_encode($_SESSION['return']) : $generic_failure;
        }
        else {
          echo isset($_SESSION['return']) ? json_encode($_SESSION['return']) : $generic_success;
        }
      }
      if (!isset($_POST['attr'])) {
        echo $request_incomplete;
        exit;
      }
      else {
        $attr = (array)json_decode($_POST['attr'], true);
        unset($attr['csrf_token']);
        $items = isset($_POST['items']) ? (array)json_decode($_POST['items'], true) : null;
      }
      // only allow POST requests to POST API endpoints
      if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(array(
            'type' => 'error',
            'msg' => 'only POST method is allowed'
        ));
        exit();
      }
      switch ($category) {
        case "bcc":
          process_edit_return(bcc('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "pushover":
          process_edit_return(pushover('edit', array_merge(array('username' => $items), $attr)));
        break;
        case "pushover-test":
          process_edit_return(pushover('test', array_merge(array('username' => $items), $attr)));
        break;
        case "oauth2-client":
          process_edit_return(oauth2('edit', 'client', array_merge(array('id' => $items), $attr)));
        break;
        case "recipient_map":
          process_edit_return(recipient_map('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "app-passwd":
          process_edit_return(app_passwd('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "tls-policy-map":
          process_edit_return(tls_policy_maps('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "alias":
          process_edit_return(mailbox('edit', 'alias', array_merge(array('id' => $items), $attr)));
        break;
        case "rspamd-map":
          process_edit_return(rspamd_maps('edit', array_merge(array('map' => $items), $attr)));
        break;
        case "fido2-fn":
          process_edit_return(fido2(array('action' => 'edit_fn', 'fido2_attrs' => $attr)));
        break;
        case "app_links":
          process_edit_return(customize('edit', 'app_links', $attr));
        break;
        case "passwordpolicy":
          process_edit_return(password_complexity('edit', $attr));
        break;
        case "relayhost":
          process_edit_return(relayhost('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "transport":
          process_edit_return(transport('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "rsetting":
          process_edit_return(rsettings('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "delimiter_action":
          process_edit_return(mailbox('edit', 'delimiter_action', array_merge(array('username' => $items), $attr)));
        break;
        case "tls_policy":
          process_edit_return(mailbox('edit', 'tls_policy', array_merge(array('username' => $items), $attr)));
        break;
        case "quarantine_notification":
          process_edit_return(mailbox('edit', 'quarantine_notification', array_merge(array('username' => $items), $attr)));
        break;
        case "quarantine_category":
          process_edit_return(mailbox('edit', 'quarantine_category', array_merge(array('username' => $items), $attr)));
        break;
        case "qitem":
          process_edit_return(quarantine('edit', array_merge(array('id' => $items), $attr)));
        break;
        case "quarantine":
          process_edit_return(quarantine('edit', $attr));
        break;
        case "quota_notification":
          process_edit_return(quota_notification('edit', $attr));
        break;
        case "quota_notification_bcc":
          process_edit_return(quota_notification_bcc('edit', $attr));
        break;
        case "mailq":
          process_edit_return(mailq('edit', array_merge(array('qid' => $items), $attr)));
        break;
        case "time_limited_alias":
          process_edit_return(mailbox('edit', 'time_limited_alias', array_merge(array('address' => $items), $attr)));
        break;
        case "mailbox":
          switch ($object) {
            case "template":
              process_edit_return(mailbox('edit', 'mailbox_templates', array_merge(array('ids' => $items), $attr)));
            break;
            case "custom-attribute":
              process_edit_return(mailbox('edit', 'mailbox_custom_attribute', array_merge(array('mailboxes' => $items), $attr)));
            break;
            default:
              process_edit_return(mailbox('edit', 'mailbox', array_merge(array('username' => $items), $attr)));
            break;
          }
        break;
        case "syncjob":
          process_edit_return(mailbox('edit', 'syncjob', array_merge(array('id' => $items), $attr)));
        break;
        case "filter":
          process_edit_return(mailbox('edit', 'filter', array_merge(array('id' => $items), $attr)));
        break;
        case "resource":
          process_edit_return(mailbox('edit', 'resource', array_merge(array('name' => $items), $attr)));
        break;
        case "domain":
          switch ($object) {
            case "template":
              process_edit_return(mailbox('edit', 'domain_templates', array_merge(array('ids' => $items), $attr)));
            break;
            case "footer":
              process_edit_return(mailbox('edit', 'domain_wide_footer', array_merge(array('domains' => $items), $attr)));
            break;
            default:
              process_edit_return(mailbox('edit', 'domain', array_merge(array('domain' => $items), $attr)));
            break;
          }
        break;
        case "rl-domain":
          process_edit_return(ratelimit('edit', 'domain', array_merge(array('object' => $items), $attr)));
        break;
        case "rl-mbox":
          process_edit_return(ratelimit('edit', 'mailbox', array_merge(array('object' => $items), $attr)));
        break;
        case "rename-mbox":
          process_edit_return(mailbox('edit', 'mailbox_rename', array_merge(array('mailbox' => $items), $attr)));
        break;
        case "user-acl":
          process_edit_return(acl('edit', 'user', array_merge(array('username' => $items), $attr)));
        break;
        case "da-acl":
          process_edit_return(acl('edit', 'domainadmin', array_merge(array('username' => $items), $attr)));
        break;
        case "alias-domain":
          process_edit_return(mailbox('edit', 'alias_domain', array_merge(array('alias_domain' => $items), $attr)));
        break;
        case "spam-score":
          process_edit_return(mailbox('edit', 'spam_score', array_merge(array('username' => $items), $attr)));
        break;
        case "domain-admin":
          process_edit_return(domain_admin('edit', array_merge(array('username' => $items), $attr)));
        break;
        case "admin":
          process_edit_return(admin('edit', array_merge(array('username' => $items), $attr)));
        break;
        case "fwdhost":
          process_edit_return(fwdhost('edit', array_merge(array('fwdhost' => $items), $attr)));
        break;
        case "fail2ban":
          switch ($object) {
            case 'banlist':
              process_edit_return(fail2ban('banlist', 'refresh', $items));
            break;
            default:
              process_edit_return(fail2ban('edit', array_merge(array('network' => $items), $attr)));
            break;
          }
        break;
        case "ui_texts":
          process_edit_return(customize('edit', 'ui_texts', $attr));
        break;
        case "ip_check":
          process_edit_return(customize('edit', 'ip_check', $attr));
        break;
        case "self":
          if ($_SESSION['mailcow_cc_role'] == "domainadmin") {
            process_edit_return(domain_admin('edit', $attr));
          }
          elseif ($_SESSION['mailcow_cc_role'] == "user") {
            process_edit_return(edit_user_account($attr));
          }
        break;
        case "cors":
          process_edit_return(cors('edit', $attr));
        break;
        case "reset-password-notification":
          process_edit_return(reset_password('edit_notification', $attr));
        break;
        // return no route found if no case is matched
        default:
          http_response_code(404);
          echo json_encode(array(
            'type' => 'error',
            'msg' => 'route not found'
          ));
          exit();
      }
    break;
    // return no route found if no case is matched
    default:
      http_response_code(404);
      echo json_encode(array(
        'type' => 'error',
        'msg' => 'route not found'
      ));
      exit();
  }
}
if (array_key_exists('mailcow_cc_api', $_SESSION) && $_SESSION['mailcow_cc_api'] === true) {
  if (isset($_SESSION['mailcow_cc_api']) && $_SESSION['mailcow_cc_api'] === true) {
    unset($_SESSION['return']);
  }
}
