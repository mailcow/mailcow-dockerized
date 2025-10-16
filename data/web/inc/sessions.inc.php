<?php
// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name($SESSION_NAME);
  ini_set("session.cookie_httponly", 1);
  ini_set("session.cookie_samesite", $SESSION_SAMESITE_POLICY);
  ini_set('session.gc_maxlifetime', $SESSION_LIFETIME);
}

$_SESSION['access_all_exception'] = '0';

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
  strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https") {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set("session.cookie_secure", 1);
  }
  $IS_HTTPS = true;
}
elseif (isset($_SERVER['HTTPS'])) {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set("session.cookie_secure", 1);
  }
  $IS_HTTPS = true;
}
else {
  $IS_HTTPS = false;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!isset($_SESSION['CSRF']['TOKEN'])) {
  $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
}

// Set session UA
if (!isset($_SESSION['SESS_REMOTE_UA'])) {
  $_SESSION['SESS_REMOTE_UA'] = $_SERVER['HTTP_USER_AGENT'];
}

// Keep session active
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $SESSION_LIFETIME)) {
  session_unset();
  session_destroy();
}
$_SESSION['LAST_ACTIVITY'] = time();

// API
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
  $stmt = $pdo->prepare("SELECT * FROM `api` WHERE `api_key` = :api_key AND `active` = '1';");
  $stmt->execute(array(
    ':api_key' => preg_replace('/[^a-zA-Z0-9-]/', '', $_SERVER['HTTP_X_API_KEY'])
  ));
  $api_return = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!empty($api_return['api_key'])) {
    $skip_ip_check = ($api_return['skip_ip_check'] == 1);
    $remote = get_remote_ip(false);
    $allow_from = array_map('trim', preg_split( "/( |,|;|\n)/", $api_return['allow_from']));
    if ($skip_ip_check === true || ip_acl($remote, $allow_from)) {
      $_SESSION['mailcow_cc_username'] = 'API';
      $_SESSION['mailcow_cc_role'] = 'admin';
      $_SESSION['mailcow_cc_api'] = true;
      if ($api_return['access'] == 'rw') {
        $_SESSION['mailcow_cc_api_access'] = 'rw';
      }
      else {
        $_SESSION['mailcow_cc_api_access'] = 'ro';
      }
    }
    else {
      $redis->publish("F2B_CHANNEL", "mailcow UI: Invalid password for API_USER by " . $_SERVER['REMOTE_ADDR']);
      error_log("mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
      http_response_code(401);
      echo json_encode(array(
        'type' => 'error',
        'msg' => 'api access denied for ip ' . $_SERVER['REMOTE_ADDR']
      ));
      unset($_POST);
      exit();
    }
  }
  else {
    $redis->publish("F2B_CHANNEL", "mailcow UI: Invalid password for API_USER by " . $_SERVER['REMOTE_ADDR']);
    error_log("mailcow UI: Invalid password for " . $user . " by " . $_SERVER['REMOTE_ADDR']);
    http_response_code(401);
    echo json_encode(array(
      'type' => 'error',
      'msg' => 'authentication failed'
    ));
    unset($_POST);
    exit();
  }
}

// Handle logouts
if (isset($_POST["logout"])) {
  if (isset($_SESSION["dual-login"])) {
    $_SESSION["mailcow_cc_username"] = $_SESSION["dual-login"]["username"];
    $_SESSION["mailcow_cc_role"] = $_SESSION["dual-login"]["role"];
    unset($_SESSION['sogo-sso-user-allowed']);
    unset($_SESSION['sogo-sso-pass']);
    unset($_SESSION["dual-login"]);
    if ($_SESSION["mailcow_cc_role"] == "admin"){
      header("Location: /admin/mailbox");
    } elseif ($_SESSION["mailcow_cc_role"] == "domainadmin") {
      header("Location: /domainadmin/mailbox");
    } else {
      header("Location: /");
    }
    exit();
  }
  else {
    $role = $_SESSION["mailcow_cc_role"];
    
    // Check if user was authenticated via OIDC and OIDC logout is enabled
    $oidc_logout_url = null;
    if (isset($_SESSION['iam_auth_source']) && ($_SESSION['iam_auth_source'] == 'keycloak' || $_SESSION['iam_auth_source'] == 'generic-oidc')) {
      require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.inc.php';
      
      // Determine the schema
      $schema = 'http';
      if ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https") || 
          isset($_SERVER['HTTPS'])) {
        $schema = 'https';
      }
      
      // Determine post-logout redirect URI based on role
      $post_logout_redirect_uri = $schema . '://' . $_SERVER['HTTP_HOST'];
      if ($role == "admin") {
        $post_logout_redirect_uri .= '/admin';
      } elseif ($role == "domainadmin") {
        $post_logout_redirect_uri .= '/domainadmin';
      } else {
        $post_logout_redirect_uri .= '/';
      }

      $iam_provider = identity_provider('init');
      $iam_settings = identity_provider('get');
      $oidc_logout_url = identity_provider('get-logout-url', array('post_logout_redirect_uri' => $post_logout_redirect_uri));
    }
    
    session_regenerate_id(true);
    session_unset();
    session_destroy();
    session_write_close();
    
    // Redirect to OIDC logout URL if available, otherwise use standard logout
    if ($oidc_logout_url) {
      header("Location: " . $oidc_logout_url);
    } elseif($role == "admin") {
        header("Location: /admin");
    }
    elseif ($role == "domainadmin") {
      header("Location: /domainadmin");
    }
    else {
      header("Location: /");
    }
  }
}

// Check session
function session_check() {
  if (isset($_SESSION['mailcow_cc_api']) && $_SESSION['mailcow_cc_api'] === true) {
    return true;
  }
  if (!isset($_SESSION['SESS_REMOTE_UA']) || ($_SESSION['SESS_REMOTE_UA'] != $_SERVER['HTTP_USER_AGENT'])) {
    $_SESSION['return'][] = array(
      'type' => 'warning',
      'msg' => 'session_ua'
    );
    return false;
  }
  if (!empty($_POST)) {
    if ($_SESSION['CSRF']['TOKEN'] != $_POST['csrf_token']) {
      $_SESSION['return'][] = array(
        'type' => 'warning',
        'msg' => 'session_token'
      );
      return false;
    }
    unset($_POST['csrf_token']);
    $_SESSION['CSRF']['TOKEN'] = bin2hex(random_bytes(32));
    $_SESSION['CSRF']['TIME'] = time();
  }
  return true;
}

if (isset($_SESSION['mailcow_cc_role']) && session_check() === false) {
  $_POST = array();
  $_FILES = array();
}
