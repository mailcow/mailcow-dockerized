<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.user.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {

  /*
  / USER
  */

  require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
  $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
  $username = $_SESSION['mailcow_cc_username'];
  $mailboxdata = mailbox('get', 'mailbox_details', $username);
  $pushover_data = pushover('get', $username);
  $tfa_data = get_tfa();
  $fido2_data = fido2(array("action" => "get_friendly_names"));

  $clientconfigstr = "host=" . urlencode($mailcow_hostname) . "&email=" . urlencode($username) . "&name=" . urlencode($mailboxdata['name']) . "&ui=" . urlencode(strtok($_SERVER['HTTP_HOST'], ':')) . "&port=" . urlencode($autodiscover_config['caldav']['port']);
  if ($autodiscover_config['useEASforOutlook'] == 'yes')
  $clientconfigstr .= "&outlookEAS=1";
  if (file_exists('thunderbird-plugins/version.csv')) {
    $fh = fopen('thunderbird-plugins/version.csv', 'r');
    if ($fh) {
      while (($row = fgetcsv($fh, 1000, ';')) !== FALSE) {
        if ($row[0] == 'sogo-connector@inverse.ca') {
          $clientconfigstr .= "&connector=" . urlencode($row[1]);
        }
      }
      fclose($fh);
    }
  }

  // Get user information about aliases
  $user_get_alias_details = user_get_alias_details($username);
  $user_get_alias_details['direct_aliases'] = array_filter($user_get_alias_details['direct_aliases']);
  $user_get_alias_details['shared_aliases'] = array_filter($user_get_alias_details['shared_aliases']);
  $user_domains[] = mailbox('get', 'mailbox_details', $username)['domain'];
  $user_alias_domains = $user_get_alias_details['alias_domains'];
  if (!empty($user_alias_domains)) {
    $user_domains = array_merge($user_domains, $user_alias_domains);
  }

  // get number of app passwords
  $number_of_app_passwords = 0;
  foreach (app_passwd("get") as $app_password)
  {
      $app_password = app_passwd("details", $app_password['id']);
      if ($app_password['active'])
      {
          $number_of_app_passwords++;
      }
  }

  $template = 'user.twig';
  $template_data = [
    'acl' => $_SESSION['acl'],
    'acl_json' => json_encode($_SESSION['acl']),
    'user_spam_score' => mailbox('get', 'spam_score', $username),
    'tfa_data' => $tfa_data,
    'tfa_id' => @$_SESSION['tfa_id'],
    'fido2_data' => $fido2_data,
    'mailboxdata' => $mailboxdata,
    'clientconfigstr' => $clientconfigstr,
    'user_get_alias_details' => $user_get_alias_details,
    'get_tagging_options' => mailbox('get', 'delimiter_action', $username),
    'get_tls_policy' => mailbox('get', 'tls_policy', $username),
    'quarantine_notification' => mailbox('get', 'quarantine_notification', $username),
    'quarantine_category' => mailbox('get', 'quarantine_category', $username),
    'user_domains' => $user_domains,
    'pushover_data' => $pushover_data,
    'lang_user' => json_encode($lang['user']),
    'number_of_app_passwords' => $number_of_app_passwords,
    'lang_datatables' => json_encode($lang['datatables']),
  ];
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
  header('Location: /admin/dashboard');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {
  header('Location: /domainadmin/mailbox');
  exit();
}
else {
  header('Location: /');
  exit();
}

$js_minifier->add('/web/js/site/user.js');
$js_minifier->add('/web/js/site/pwgen.js');

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
