<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
$AuthUsers = array("admin", "domainadmin", "user");
if (!isset($_SESSION['mailcow_cc_role']) OR !in_array($_SESSION['mailcow_cc_role'], $AuthUsers)) {
  header('Location: /');
  exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';

$template = 'edit.twig';
$template_data = [];
$result = null;
if (isset($_SESSION['mailcow_cc_role'])) {
  if ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin") {
    if (isset($_GET["alias"]) &&
      !empty($_GET["alias"])) {
        $alias = html_entity_decode(rawurldecode($_GET["alias"]));
        $result = mailbox('get', 'alias_details', $alias);

        $template = 'edit/alias.twig';
        $template_data = [
          'alias' => $alias,
          'goto' => (preg_match('/^(null|ham|spam)@localhost$/i', $result['goto'])) ? null : $result['goto'],
        ];
    }
    elseif (isset($_GET['domainadmin'])) {
      $domain_admin = $_GET["domainadmin"];
      $result = domain_admin('details', $domain_admin);
      $template = 'edit/domainadmin.twig';
      $template_data = [
        'domain_admin' => $domain_admin,
        'da_acls' => acl('get', 'domainadmin', $domain_admin),
      ];
    }
    elseif (isset($_GET['admin'])) {
      $admin = $_GET["admin"];
      $result = admin('details', $admin);
      $template = 'edit/admin.twig';
      $template_data = ['admin' => $admin];
    }
    elseif (isset($_GET['domain'])) {
      if (is_valid_domain_name($_GET["domain"]) &&
        !empty($_GET["domain"])) {
          // edit domain
          $domain = $_GET["domain"];
          $result = mailbox('get', 'domain_details', $domain);
          $quota_notification_bcc = quota_notification_bcc('get', $domain);
          $rl = ratelimit('get', 'domain', $domain);
          $rlyhosts = relayhost('get');
          $domain_footer = mailbox('get', 'domain_wide_footer', $domain);
          $template = 'edit/domain.twig';
          $template_data = [
            'acl' => $_SESSION['acl'],
            'domain' => $domain,
            'quota_notification_bcc' => $quota_notification_bcc,
            'rl' => $rl,
            'rlyhosts' => $rlyhosts,
            'dkim' => dkim('details', $domain),
            'domain_details' => $result,
            'domain_footer' => $domain_footer,
            'mailboxes' => mailbox('get', 'mailboxes', $_GET["domain"]),
            'aliases' => mailbox('get', 'aliases', $_GET["domain"], 'address'),
            'alias_domains' => mailbox('get', 'alias_domains', $_GET["domain"])
          ];
      }
    }
    elseif (isset($_GET['template'])){
      $domain_template = mailbox('get', 'domain_templates', $_GET['template']);
      if ($domain_template){
        $template_data = [
          'template' => $domain_template,
          'rl' => ['frame' => $domain_template['attributes']['rl_frame']],
        ];
        $template = 'edit/domain-templates.twig';
        $result = true;
      }
      else {
        $mailbox_template = mailbox('get', 'mailbox_templates', $_GET['template']);
        if ($mailbox_template){
          $template_data = [
            'template' => $mailbox_template,
            'rl' => ['frame' => $mailbox_template['attributes']['rl_frame']],
          ];
          $template = 'edit/mailbox-templates.twig';
          $result = true;
        }
      }
    }
    elseif (isset($_GET['oauth2client']) &&
      is_numeric($_GET["oauth2client"]) &&
      !empty($_GET["oauth2client"])) {
        $oauth2client = $_GET["oauth2client"];
        $result = oauth2('details', 'client', $oauth2client);
        $template = 'edit/oauth2client.twig';
        $template_data = ['oauth2client' => $oauth2client];
    }
    elseif (isset($_GET['aliasdomain']) &&
      is_valid_domain_name(html_entity_decode(rawurldecode($_GET["aliasdomain"]))) &&
      !empty($_GET["aliasdomain"])) {
        $alias_domain = html_entity_decode(rawurldecode($_GET["aliasdomain"]));
        $result = mailbox('get', 'alias_domain_details', $alias_domain);
        $rl = ratelimit('get', 'domain', $alias_domain);
        $template = 'edit/aliasdomain.twig';
        $template_data = [
          'alias_domain' => $alias_domain,
          'rl' => $rl,
          'domains' => mailbox('get', 'domains'),
          'dkim' => dkim('details', $alias_domain),
        ];
    }
    elseif (isset($_GET['mailbox'])){
      if(filter_var(html_entity_decode(rawurldecode($_GET["mailbox"])), FILTER_VALIDATE_EMAIL) && !empty($_GET["mailbox"])) {
        // edit mailbox
        $mailbox = html_entity_decode(rawurldecode($_GET["mailbox"]));
        $result = mailbox('get', 'mailbox_details', $mailbox);
        $rl = ratelimit('get', 'mailbox', $mailbox);
        $pushover_data = pushover('get', $mailbox);
        $quarantine_notification = mailbox('get', 'quarantine_notification', $mailbox);
        $quarantine_category = mailbox('get', 'quarantine_category', $mailbox);
        $get_tls_policy = mailbox('get', 'tls_policy', $mailbox);
        $rlyhosts = relayhost('get');
        $iam_settings = identity_provider('get');
        $template = 'edit/mailbox.twig';
        $template_data = [
          'acl' => $_SESSION['acl'],
          'mailbox' => $mailbox,
          'rl' => $rl,
          'pushover_data' => $pushover_data,
          'quarantine_notification' => $quarantine_notification,
          'quarantine_category' => $quarantine_category,
          'get_tls_policy' => $get_tls_policy,
          'rlyhosts' => $rlyhosts,
          'sender_acl_handles' => mailbox('get', 'sender_acl_handles', $mailbox),
          'user_acls' => acl('get', 'user', $mailbox),
          'mailbox_details' => $result,
          'iam_settings' => $iam_settings,
        ];
      }
    }
    elseif (isset($_GET['relayhost']) && is_numeric($_GET["relayhost"]) && !empty($_GET["relayhost"])) {
        $relayhost = intval($_GET["relayhost"]);
        $result = relayhost('details', $relayhost);
        $template = 'edit/relayhost.twig';
        $template_data = ['relayhost' => $relayhost];
    }
    elseif (isset($_GET['transport']) && is_numeric($_GET["transport"]) && !empty($_GET["transport"])) {
        $transport = intval($_GET["transport"]);
        $result = transport('details', $transport);
        $template = 'edit/transport.twig';
        $template_data = ['transport' => $transport];
    }
    elseif (isset($_GET['resource']) && filter_var(html_entity_decode(rawurldecode($_GET["resource"])), FILTER_VALIDATE_EMAIL) && !empty($_GET["resource"])) {
        $resource = html_entity_decode(rawurldecode($_GET["resource"]));
        $result = mailbox('get', 'resource_details', $resource);
        $template = 'edit/resource.twig';
    }
    elseif (isset($_GET['bcc']) && !empty($_GET["bcc"])) {
        $bcc = intval($_GET["bcc"]);
        $result = bcc('details', $bcc);
        $template = 'edit/bcc.twig';
        $template_data = ['bcc' => $bcc];
    }
    elseif (isset($_GET['recipient_map']) &&
      !empty($_GET["recipient_map"]) &&
      $_SESSION['mailcow_cc_role'] == "admin") {
        $map = intval($_GET["recipient_map"]);
        $result = recipient_map('details', $map);
        if (substr($result['recipient_map_old'], 0, 1) == '@') {
          $result['recipient_map_old'] = substr($result['recipient_map_old'], 1);
        }
        $template = 'edit/recipient_map.twig';
        $template_data = ['map' => $map];
    }
    elseif (isset($_GET['tls_policy_map']) &&
      !empty($_GET["tls_policy_map"]) &&
      $_SESSION['mailcow_cc_role'] == "admin") {
        $map = intval($_GET["tls_policy_map"]);
        $result = tls_policy_maps('details', $map);
        $template = 'edit/tls_policy_map.twig';
        $template_data = [
          'map' => $map,
          'policy_options' => [
            'none',
            'may',
            'encrypt',
            'dane',
            'dane-only',
            'fingerprint',
            'verify',
            'secure',
          ],
        ];
    }
  }
  if ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin" || $_SESSION['mailcow_cc_role'] == "user") {
    if (isset($_GET['syncjob']) &&
      is_numeric($_GET['syncjob'])) {
        $id = $_GET["syncjob"];
        $result = mailbox('get', 'syncjob_details', $id);
        $template = 'edit/syncjob.twig';
      }
    elseif (isset($_GET['filter']) &&
      is_numeric($_GET['filter'])) {
        $id = $_GET["filter"];
        $result = mailbox('get', 'filter_details', $id);
        $template = 'edit/filter.twig';
    }
    elseif (isset($_GET['app-passwd']) &&
      is_numeric($_GET['app-passwd'])) {
        $id = $_GET["app-passwd"];
        $result = app_passwd('details', $id);
        $template = 'edit/app-passwd.twig';
    }
  }
}
else {
  $template_data['access_denied'] = true;
}

$js_minifier->add('/web/js/site/edit.js');
$js_minifier->add('/web/js/site/pwgen.js');

$template_data['result'] = $result;
$template_data['return_to'] = $_SESSION['return_to'];
$template_data['lang_user'] = json_encode($lang['user']);
$template_data['lang_admin'] = json_encode($lang['admin']);
$template_data['lang_datatables'] = json_encode($lang['datatables']);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
