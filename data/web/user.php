<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {

  /*
  / DOMAIN ADMIN
  */

	require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
	$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
  $tfa_data = get_tfa();
  $fido2_data = fido2(array("action" => "get_friendly_names"));
	$username = $_SESSION['mailcow_cc_username'];

?>
<div class="container">

  <h3><?=$lang['user']['user_settings'];?></h3>
  <div class="panel panel-default">
  <div class="panel-heading"><?=$lang['user']['user_settings'];?></div>
  <div class="panel-body">
    <div class="row">
      <div class="col-sm-offset-3 col-sm-9">
        <p><a href="#pwChangeModal" data-toggle="modal">[<?=$lang['user']['change_password'];?>]</a></p>
        <div class="last-login"></div>
        <span class="clear-last-logins"><?=$lang['user']['clear_recent_successful_connections'];?></span>
      </div>
    </div>
    <hr>

    <? // TFA ?>
    <div class="row">
      <div class="col-sm-3 col-xs-5 text-right"><?=$lang['tfa']['tfa'];?></div>
        <div class="col-sm-9 col-xs-7">
          <p id="tfa_pretty"><?=$tfa_data['pretty'];?></p>
            <div id="tfa_keys">
              <?php
              if (!empty($tfa_data['additional'])) {
                foreach ($tfa_data['additional'] as $key_info) { ?>
                <form style="display:inline;" method="post">
                  <input type="hidden" name="unset_tfa_key" value="<?=$key_info['id'];?>" />
                  <div class="label label-default"><i class="bi bi-key-fill"></i> <?=$key_info['key_id'];?> <a href="#" style="font-weight:bold;color:white" onClick='return confirm("<?=$lang['user']['delete_ays'];?>")?$(this).closest("form").submit():"";'>[<?=strtolower($lang['admin']['remove']);?>]</a></div>
                </form>
                <?php
                }
              }
              ?>
            </div>
            <br>
        </div>
    </div>
    <div class="row">
      <div class="col-sm-3 col-xs-5 text-right"><?=$lang['tfa']['set_tfa'];?></div>
      <div class="col-sm-9 col-xs-7">
        <select id="selectTFA" class="selectpicker" title="<?=$lang['tfa']['select'];?>">
          <option value="yubi_otp"><?=$lang['tfa']['yubi_otp'];?></option>
          <option value="u2f"><?=$lang['tfa']['u2f'];?></option>
          <option value="totp"><?=$lang['tfa']['totp'];?></option>
          <option value="none"><?=$lang['tfa']['none'];?></option>
        </select>
      </div>
    </div>

    <hr>
    <? // FIDO2 ?>
    <div class="row">
      <div class="col-sm-3 col-xs-5 text-right">
        <p><i class="bi bi-shield-fill-check"></i> <?=$lang['fido2']['fido2_auth'];?></p>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-3 col-xs-5 text-right"><?=$lang['fido2']['known_ids'];?>:</div>
      <div class="col-sm-9 col-xs-7">
          <div class="table-responsive">
          <table class="table table-striped table-hover table-condensed" id="fido2_keys">
            <tr>
              <th>ID</th>
              <th style="min-width:240px;text-align: right"><?=$lang['admin']['action'];?></th>
            </tr>
            <?php
            if (!empty($fido2_data)) {
              foreach ($fido2_data as $key_info) {
            ?>
            <tr>
              <td>
                <?=($_SESSION['fido2_cid'] == $key_info['cid']) ? '→ ' : NULL; ?><?=(!empty($key_info['fn']))?$key_info['fn']:$key_info['subject'];?>
              </td>
              <td style="min-width:240px;text-align: right">
                <form style="display:inline;" method="post">
                <input type="hidden" name="unset_fido2_key" value="<?=$key_info['cid'];?>" />
                <div class="btn-group">
                <a href="#" class="btn btn-xs btn-default" data-cid="<?=$key_info['cid'];?>" data-subject="<?=base64_encode($key_info['subject']);?>" data-toggle="modal" data-target="#fido2ChangeFn"><i class="bi bi-pencil-fill"></i> <?=strtolower($lang['fido2']['rename']);?></a>
                <a href="#" onClick='return confirm("<?=$lang['user']['delete_ays'];?>")?$(this).closest("form").submit():"";' class="btn btn-xs btn-danger"><i class="bi bi-trash"></i> <?=strtolower($lang['admin']['remove']);?></a>
                </form>
                </div>
              </td>
            </tr>
            <?php
              }
            }
            ?>
          </table>
          </div>
          <br>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-offset-3 col-sm-9">
        <button class="btn btn-sm btn-primary" id="register-fido2"><?=$lang['fido2']['set_fido2'];?></button>
      </div>
    </div>
    <br>
    <div class="row" id="status-fido2">
      <div class="col-sm-3 col-xs-5 text-right"><?=$lang['fido2']['register_status'];?>:</div>
      <div class="col-sm-9 col-xs-7">
        <div id="fido2-alerts">-</div>
      </div>
      <br>
    </div>

  </div>
  </div>
</div>
<?php
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {

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
?>
<div class="container">

  <!-- Nav tabs -->
  <ul class="nav nav-tabs responsive-tabs" role="tablist">
    <li class="dropdown active">
      <a class="dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['user']['mailbox'];?><span class="caret"></span></a>
      <ul class="dropdown-menu">
        <li role="presentation" class="active" data-dont-remember="1"><a href="#tab-user-auth" aria-controls="tab-user-auth" role="tab" data-toggle="tab"><?=$lang['user']['mailbox_general'];?></a></li>
        <li role="presentation"><a href="#tab-user-details" aria-controls="tab-config-fwdhosts" role="tab" data-toggle="tab"><?=$lang['user']['mailbox_details'];?></a></li>
        <li role="presentation"><a href="#tab-user-settings" aria-controls="tab-config-f2b" role="tab" data-toggle="tab"><?=$lang['user']['mailbox_settings'];?></a></li>
      </ul>
    </li>
    <li role="presentation"><a href="#SpamAliases" aria-controls="SpamAliases" role="tab" data-toggle="tab"><?=$lang['user']['spam_aliases'];?></a></li>
    <li role="presentation"><a href="#Spamfilter" aria-controls="Spamfilter" role="tab" data-toggle="tab"><?=$lang['user']['spamfilter'];?></a></li>
    <li role="presentation"><a href="#Syncjobs" aria-controls="Syncjobs" role="tab" data-toggle="tab"><?=$lang['user']['sync_jobs'];?></a></li>
    <li role="presentation"><a href="#AppPasswds" aria-controls="AppPasswds" role="tab" data-toggle="tab"><?=$lang['user']['app_passwds'];?></a></li>
    <li role="presentation"><a href="#Pushover" aria-controls="Pushover" role="tab" data-toggle="tab">Pushover API</a></li>
  </ul>
  <hr>

  <div class="tab-content">

  <div role="tabpanel" class="tab-pane active" id="tab-user-auth">
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['user']['mailbox_general'];?></div>
      <div class="panel-body">
        <?php if (getenv('SKIP_SOGO') != "y") { ?>
        <div class="row">
          <div class="hidden-xs col-md-3 col-xs-5 text-right"></div>
          <div class="col-md-3 col-xs-12">
            <a target="_blank" href="/sogo-auth.php?login=<?=$username;?>" role="button" class="btn btn-default btn-block btn-xs-lg">
              <i class="bi bi-inbox-fill"></i> <?=$lang['user']['open_webmail_sso'];?>
            </a>
          </div>
        </div>
        <hr>
        <?php } ?>
        <div class="row">
          <div class="col-md-3 col-xs-12 text-right text-xs-left space20"><?=$lang['user']['in_use'];?>:</div>
          <div class="col-md-5 col-xs-12">
            <div class="progress">
              <div class="progress-bar progress-bar-<?=$mailboxdata['percent_class'];?>" role="progressbar" aria-valuenow="<?=$mailboxdata['percent_in_use'];?>" aria-valuemin="0" aria-valuemax="100" style="min-width:2em;width: <?=$mailboxdata['percent_in_use'];?>%;">
                <?=$mailboxdata['percent_in_use'];?>%
              </div>
            </div>
            <p><?=formatBytes($mailboxdata['quota_used'], 2);?> / <?=($mailboxdata['quota'] == 0) ? '∞' : formatBytes($mailboxdata['quota'], 2);?><br><?=$mailboxdata['messages'];?> <?=$lang['user']['messages'];?></p>
            <hr>
            <p><a href="#pwChangeModal" data-toggle="modal"><i class="bi bi-pencil-fill"></i> <?=$lang['user']['change_password'];?></a></p>
          </div>
        </div>
        <hr>
        <? // FIDO2 ?>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left">
            <p><i class="bi bi-shield-fill-check"></i> <?=$lang['fido2']['fido2_auth'];?></p>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left space20">
          <?=$lang['fido2']['known_ids'];?>:
          </div>
          <div class="col-sm-9 col-xs-12">
            <div class="table-responsive">
            <table class="table table-striped table-hover table-condensed" id="fido2_keys">
              <tr>
                <th>ID</th>
                <th style="min-width:240px;text-align: right"><?=$lang['admin']['action'];?></th>
              </tr>
              <?php
              if (!empty($fido2_data)) {
                foreach ($fido2_data as $key_info) {
              ?>
              <tr>
                <td>
                  <?=($_SESSION['fido2_cid'] == $key_info['cid']) ? '<i class="bi bi-unlock-fill"></i> ' : NULL; ?><?=(!empty($key_info['fn']))?$key_info['fn']:$key_info['subject'];?>
                </td>
                <td style="min-width:240px;text-align: right">
                  <form style="display:inline;" method="post">
                  <input type="hidden" name="unset_fido2_key" value="<?=$key_info['cid'];?>" />
                  <div class="btn-group">
                  <a href="#" class="btn btn-xs btn-default" data-cid="<?=$key_info['cid'];?>" data-subject="<?=base64_encode($key_info['subject']);?>" data-toggle="modal" data-target="#fido2ChangeFn"><i class="bi bi-pencil-fill"></i> <?=strtolower($lang['fido2']['rename']);?></a>
                  <a href="#" onClick='return confirm("<?=$lang['user']['delete_ays'];?>")?$(this).closest("form").submit():"";' class="btn btn-xs btn-danger"><i class="bi bi-trash"></i> <?=strtolower($lang['admin']['remove']);?></a>
                  </div>
                  </form>
                </td>
              </tr>
              <?php
                }
              }
              ?>
            </table>
            </div>
            <br>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-offset-3 col-sm-9">
            <button class="btn btn-sm btn-primary visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline" id="register-fido2"><?=$lang['fido2']['set_fido2'];?></button>
          </div>
        </div>
        <br>
        <div class="row" id="status-fido2">
          <div class="col-sm-3 col-xs-5 text-right"><?=$lang['fido2']['register_status'];?>:</div>
          <div class="col-sm-9 col-xs-7">
            <div id="fido2-alerts">-</div>
          </div>
          <br>
        </div>
        <hr>
        <div class="row">
          <div class="col-md-3 col-xs-12 text-right text-xs-left space20"><i class="bi bi-file-earmark-text"></i> <?=$lang['user']['apple_connection_profile'];?>:</div>
          <div class="col-md-9 col-xs-12">
            <p><i class="bi bi-file-earmark-post"></i> <a href="/mobileconfig.php?only_email"><?=$lang['user']['email'];?></a> <small>IMAP, SMTP</small></p>
            <p class="help-block"><?=$lang['user']['apple_connection_profile_mailonly'];?></p>
            <?php if (getenv('SKIP_SOGO') != "y") { ?>
            <p><i class="bi bi-file-earmark-post"></i> <a href="/mobileconfig.php"><?=$lang['user']['email_and_dav'];?></a> <small>IMAP, SMTP, Cal/CardDAV</small></p>
            <p class="help-block"><?=$lang['user']['apple_connection_profile_complete'];?></p>
            <?php } ?>
          </div>
        </div>
        <hr>
        <div class="row">
          <div class="col-sm-offset-3 col-sm-9">
            <?php if ($mailboxdata['attributes']['force_pw_update'] == "1"): ?>
            <div class="alert alert-danger"><?=$lang['user']['force_pw_update'];?></div>
            <?php endif; ?>
            <p><a target="_blank" href="https://mailcow.github.io/mailcow-dockerized-docs/client/#<?=$clientconfigstr;?>">[<?=$lang['user']['client_configuration'];?>]</a></p>
            <p><a href="#userFilterModal" data-toggle="modal">[<?=$lang['user']['show_sieve_filters'];?>]</a></p>
            <hr>
            <h4 class="recent-login-success pull-left"><?=$lang['user']['recent_successful_connections'];?></h4>
            <div class="dropdown pull-left pull-xs-right">
              <button class="btn btn-default btn-xs btn-xs-lg dropdown-toggle" type="button" id="history_sasl_days" data-toggle="dropdown"><?=$lang['user']['login_history'];?> <span class="caret"></span></button>
              <ul class="dropdown-menu">
                <li class="login-history active" data-days="1"><a href="#">1 <?=$lang['user']['day'];?></a></li>
                <li class="login-history" data-days="7"><a href="#">1 <?=$lang['user']['week'];?></a></li>
                <li class="login-history" data-days="14"><a href="#">2 <?=$lang['user']['weeks'];?></a></li>
                <li class="login-history" data-days="31"><a href="#">1 <?=$lang['user']['month'];?></a></li>
              </ul>
            </div>
            <div class="clearfix"></div>
            <div class="last-login"></div>
            <span class="clear-last-logins">
              <?=$lang['user']['clear_recent_successful_connections'];?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-user-details">
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['user']['mailbox_details'];?></div>
      <div class="panel-body">
        <?php // Get user information about aliases
        $user_get_alias_details = user_get_alias_details($username);
        $user_domains[] = mailbox('get', 'mailbox_details', $username)['domain'];
        $user_alias_domains = $user_get_alias_details['alias_domains'];
        if (!empty($user_alias_domains)) {
          $user_domains = array_merge($user_domains, $user_alias_domains);
        }
        ?>
        <div class="row">
          <div class="col-sm-4 col-md-3 col-xs-12 text-right text-xs-left"><i class="bi bi-pin-angle"></i> <?=$lang['user']['direct_aliases'];?>:
            <p class="small"><?=$lang['user']['direct_aliases_desc'];?></p>
          </div>
          <div class="col-sm-8 col-md-9 col-xs-12">
          <?php
          if (empty($user_get_alias_details['direct_aliases'])) {
            echo '<i class="bi bi-x-lg"></i>';
          }
          else {
            foreach (array_filter($user_get_alias_details['direct_aliases']) as $direct_alias => $direct_alias_meta) {
              (!empty($direct_alias_meta['public_comment'])) ?
                printf('%s &mdash; <i class="bi bi-chat-left"></i> %s<br>', $direct_alias, $direct_alias_meta['public_comment']) :
                printf('%s<br>', $direct_alias);
            }
          }
          ?>
          </div>
        </div>
        <br>
        <div class="row">
          <div class="col-sm-4 col-md-3 col-xs-12 text-right text-xs-left"><i class="bi bi-share"></i> <?=$lang['user']['shared_aliases'];?>:
            <p class="small"><?=$lang['user']['shared_aliases_desc'];?></p>
          </div>
          <div class="col-sm-8 col-md-9 col-xs-12">
          <?php
          if (empty($user_get_alias_details['shared_aliases'])) {
            echo '<i class="bi bi-x-lg"></i>';
          }
          else {
            foreach (array_filter($user_get_alias_details['shared_aliases']) as $shared_alias => $shared_alias_meta) {
              (!empty($shared_alias_meta['public_comment'])) ?
                printf('%s &mdash; <span class="bg-info">%s</span><br>', $shared_alias, $shared_alias_meta['public_comment']) :
                printf('%s<br>', $shared_alias);
            }
          }
          ?>
          </div>
        </div>
        <hr>
        <div class="row">
          <div class="col-sm-4 col-md-3 col-xs-12 text-right text-xs-left space20"><?=$lang['user']['aliases_also_send_as'];?>:</div>
          <div class="col-sm-8 col-md-9 col-xs-12">
          <p><?=($user_get_alias_details['aliases_also_send_as'] == '*') ? $lang['user']['sender_acl_disabled'] : (empty($user_get_alias_details['aliases_also_send_as'])) ? '<i class="bi bi-x-lg"></i>' : $user_get_alias_details['aliases_also_send_as'] ;?></p>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-4 col-md-3 col-xs-12 text-right text-xs-left space20"><?=$lang['user']['aliases_send_as_all'];?>:</div>
          <div class="col-sm-8 col-md-9 col-xs-12">
          <p><?=(empty($user_get_alias_details['aliases_send_as_all'])) ? '<i class="bi bi-x-lg"></i>' : '' ;?></p>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-4 col-md-3 col-xs-12 text-right text-xs-left space20"><?=$lang['user']['is_catch_all'];?>:</div>
          <div class="col-sm-8 col-md-9 col-xs-12">
          <p><?=(empty($user_get_alias_details['is_catch_all'])) ? '<i class="bi bi-x-lg"></i>' : '' ;?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-user-settings">
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['user']['mailbox_settings'];?></div>
      <div class="panel-body">
        <?php
        // Show tagging options
        $get_tagging_options = mailbox('get', 'delimiter_action', $username);
        ?>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left text-xs-bold space20"><?=$lang['user']['tag_handling'];?>:</div>
          <div class="col-sm-9 col-xs-12">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['delimiter_action'];?>">
            <button type="button" class="btn btn-sm btn-xs-third visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($get_tagging_options == "subfolder") ? 'active' : null; ?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="delimiter_action"
              data-api-url='edit/delimiter_action'
              data-api-attr='{"tagged_mail_handler":"subfolder"}'><?=$lang['user']['tag_in_subfolder'];?></button>
            <button type="button" class="btn btn-sm btn-xs-third visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($get_tagging_options == "subject") ? 'active' : null; ?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="delimiter_action"
              data-api-url='edit/delimiter_action'
              data-api-attr='{"tagged_mail_handler":"subject"}'><?=$lang['user']['tag_in_subject'];?></button>
            <button type="button" class="btn btn-sm btn-xs-third visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($get_tagging_options == "none") ? 'active' : null; ?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="delimiter_action"
              data-api-url='edit/delimiter_action'
              data-api-attr='{"tagged_mail_handler":"none"}'><?=$lang['user']['tag_in_none'];?></button>
              <div class="clearfix visible-xs"></div>
          </div>
          <p class="help-block"><?=$lang['user']['tag_help_explain'];?></p>
          <p class="help-block"><?=$lang['user']['tag_help_example'];?></p>
          </div>
        </div>
        <?php
        // Show TLS policy options
        $get_tls_policy = mailbox('get', 'tls_policy', $username);
        ?>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left text-xs-bold space20"><?=$lang['user']['tls_policy'];?>:</div>
          <div class="col-sm-9 col-xs-12">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['tls_policy'];?>">
            <button type="button" class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($get_tls_policy['tls_enforce_in'] == "1") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="tls_policy"
              data-api-url='edit/tls_policy'
              data-api-attr='{"tls_enforce_in":<?=($get_tls_policy['tls_enforce_in'] == "1") ? "0" : "1";?>}'><?=$lang['user']['tls_enforce_in'];?></button>
            <button type="button" class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($get_tls_policy['tls_enforce_out'] == "1") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="tls_policy"
              data-api-url='edit/tls_policy'
              data-api-attr='{"tls_enforce_out":<?=($get_tls_policy['tls_enforce_out'] == "1") ? "0" : "1";?>}'><?=$lang['user']['tls_enforce_out'];?></button>
              <div class="clearfix visible-xs"></div>
          </div>
          <p class="help-block"><?=$lang['user']['tls_policy_warning'];?></p>
          </div>
        </div>
        <?php
        // Show quarantine_notification options
        $quarantine_notification = mailbox('get', 'quarantine_notification', $username);
        $quarantine_category = mailbox('get', 'quarantine_category', $username);
        ?>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left text-xs-bold space20"><?=$lang['user']['quarantine_notification'];?>:</div>
          <div class="col-sm-9 col-xs-12">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['quarantine_notification'];?>">
            <button type="button" class="btn btn-sm btn-xs-quart visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_notification == "never") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_notification"
              data-api-url='edit/quarantine_notification'
              data-api-attr='{"quarantine_notification":"never"}'><?=$lang['user']['never'];?></button>
            <button type="button" class="btn btn-sm btn-xs-quart visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_notification == "hourly") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_notification"
              data-api-url='edit/quarantine_notification'
              data-api-attr='{"quarantine_notification":"hourly"}'><?=$lang['user']['hourly'];?></button>
            <button type="button" class="btn btn-sm btn-xs-quart visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_notification == "daily") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_notification"
              data-api-url='edit/quarantine_notification'
              data-api-attr='{"quarantine_notification":"daily"}'><?=$lang['user']['daily'];?></button>
            <button type="button" class="btn btn-sm btn-xs-quart visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_notification == "weekly") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_notification"
              data-api-url='edit/quarantine_notification'
              data-api-attr='{"quarantine_notification":"weekly"}'><?=$lang['user']['weekly'];?></button>
              <div class="clearfix visible-xs"></div>
          </div>
          <p class="help-block"><?=$lang['user']['quarantine_notification_info'];?></p>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left text-xs-bold space20"><?=$lang['user']['quarantine_category'];?>:</div>
          <div class="col-sm-9 col-xs-12">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['quarantine_category'];?>">
            <button type="button" class="btn btn-sm btn-xs-third visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_category == "reject") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_category"
              data-api-url='edit/quarantine_category'
              data-api-attr='{"quarantine_category":"reject"}'><?=$lang['user']['q_reject'];?></button>
            <button type="button" class="btn btn-sm btn-xs-third visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_category == "add_header") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_category"
              data-api-url='edit/quarantine_category'
              data-api-attr='{"quarantine_category":"add_header"}'><?=$lang['user']['q_add_header'];?></button>
            <button type="button" class="btn btn-sm btn-xs-third visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default <?=($quarantine_category == "all") ? "active" : null;?>"
              data-action="edit_selected"
              data-item="<?= htmlentities($username); ?>"
              data-id="quarantine_category"
              data-api-url='edit/quarantine_category'
              data-api-attr='{"quarantine_category":"all"}'><?=$lang['user']['q_all'];?></button>
              <div class="clearfix visible-xs"></div>
          </div>
          <p class="help-block"><?=$lang['user']['quarantine_category_info'];?></p>
          </div>
        </div>
        <?php if (getenv('SKIP_SOGO') != "y") { ?>
        <hr>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left text-xs-bold space20"><?=$lang['user']['eas_reset'];?>:</div>
          <div class="col-sm-9 col-xs-12">
          <button class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" data-acl="<?=$_SESSION['acl']['eas_reset'];?>" data-action="delete_selected" data-text="<?=$lang['user']['eas_reset'];?>?" data-item="<?= htmlentities($username); ?>" data-id="eas_cache" data-api-url='delete/eas_cache' href="#"><?=$lang['user']['eas_reset_now'];?></button>
          <p class="help-block"><?=$lang['user']['eas_reset_help'];?></p>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-3 col-xs-12 text-right text-xs-left text-xs-bold space20"><?=$lang['user']['sogo_profile_reset'];?>:</div>
          <div class="col-sm-9 col-xs-12">
          <button class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" data-acl="<?=$_SESSION['acl']['sogo_profile_reset'];?>" data-action="delete_selected" data-text="<?=$lang['user']['sogo_profile_reset'];?>?" data-item="<?= htmlentities($username); ?>" data-id="sogo_profile" data-api-url='delete/sogo_profile' href="#"><?=$lang['user']['sogo_profile_reset_now'];?></button>
          <p class="help-block"><?=$lang['user']['sogo_profile_reset_help'];?></p>
          </div>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>

	<div role="tabpanel" class="tab-pane" id="SpamAliases">
    <div class="row">
      <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="table-responsive">
          <table class="table table-striped" id="tla_table"></table>
        </div>
      </div>
		</div>
    <div class="mass-actions-user">
      <div class="btn-group" data-acl="<?=$_SESSION['acl']['spam_alias'];?>">
        <div class="btn-group">
          <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" id="toggle_multi_select_all" data-id="tla" href="#"><i class="bi bi-check-all"></i> <?=$lang['mailbox']['toggle_all'];?></a>
          <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a data-action="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{"validity":"1"}' href="#"><?=$lang['user']['expire_in'];?> 1 <?=$lang['user']['hour'];?></a></li>
            <li><a data-action="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{"validity":"24"}' href="#"><?=$lang['user']['expire_in'];?> 1 <?=$lang['user']['day'];?></a></li>
            <li><a data-action="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{"validity":"168"}' href="#"><?=$lang['user']['expire_in'];?> 1 <?=$lang['user']['week'];?></a></li>
            <li><a data-action="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{"validity":"744"}' href="#"><?=$lang['user']['expire_in'];?> 1 <?=$lang['user']['month'];?></a></li>
            <li><a data-action="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{"validity":"8760"}' href="#"><?=$lang['user']['expire_in'];?> 1 <?=$lang['user']['year'];?></a></li>
            <li><a data-action="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{"validity":"87600"}' href="#"><?=$lang['user']['expire_in'];?> 10 <?=$lang['user']['years'];?></a></li>
            <li role="separator" class="divider"></li>
            <li><a data-action="delete_selected" data-id="tla" data-api-url='delete/time_limited_alias' href="#"><?=$lang['mailbox']['remove'];?></a></li>
          </ul>
          <div class="clearfix visible-xs-block"></div>
        </div>
        <div class="btn-group">
          <a class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-success dropdown-toggle" data-toggle="dropdown" href="#"><i class="bi bi-plus-lg"></i> <?=$lang['user']['alias_create_random'];?>, 1 <?=$lang['user']['year'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
          <?php
          foreach($user_domains as $domain) {
          ?>
            <li>
              <a data-action="add_item" data-api-url='add/time_limited_alias' data-api-attr='{"domain":"<?=$domain;?>"}' href="#">
                @ <?=$domain;?>
              </a>
            </li>
          <?php
          }
          ?>
          </ul>
        </div>
      </div>
    </div>
	</div>

	<div role="tabpanel" class="tab-pane" id="Spamfilter">
		<h4><?=$lang['user']['spamfilter_behavior'];?></h4>
		<form class="form-horizontal" role="form" data-id="spam_score" method="post">
			<div class="form-group">
				<div class="col-lg-8 col-sm-12">
          <div id="spam_score" data-provide="slider" data-acl="<?=$_SESSION['acl']['spam_score'];?>"></div>
					<input id="spam_score_value" name="spam_score" type="hidden" value="<?=mailbox('get', 'spam_score', $username);?>">
					<ul class="list-group list-group-flush">
						<li class="list-group-item"><span class="label label-ham spam-ham-score"></span> <?=$lang['user']['spamfilter_green'];?></li>
						<li class="list-group-item"><span class="label label-spam spam-spam-score"></span> <?=$lang['user']['spamfilter_yellow'];?></li>
						<li class="list-group-item"><span class="label label-reject spam-reject-score"></span> <?=$lang['user']['spamfilter_red'];?></li>
					</ul>
				</div>
			</div>
      <div class="btn-group" data-acl="<?=$_SESSION['acl']['spam_policy'];?>">
        <a type="button" class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-success" data-action="edit_selected"
          data-item="<?= htmlentities($username); ?>"
          data-id="spam_score"
          data-api-url='edit/spam-score'
          data-api-attr='{}'><i class="bi bi-save"></i> <?=$lang['user']['save_changes'];?></a>
        <a type="button" class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" data-action="edit_selected"
          data-item="<?= htmlentities($username); ?>"
          data-id="spam_score_reset"
          data-api-url='edit/spam-score'
          data-api-attr='{"spam_score":"default"}'><?=$lang['user']['spam_score_reset'];?></a>
      </div>
		</form>
		<hr>
		<div class="row">
			<div class="col-sm-6">
				<h4><?=$lang['user']['spamfilter_wl'];?></h4>
        <p><?=$lang['user']['spamfilter_wl_desc'];?></p>
        <form class="form-inline space20" data-id="add_wl_policy_mailbox">
          <div class="input-group" data-acl="<?=$_SESSION['acl']['spam_policy'];?>">
            <input type="text" class="form-control" name="object_from" placeholder="*@example.org" required>
            <span class="input-group-btn">
              <button class="btn btn-default" data-action="add_item" data-id="add_wl_policy_mailbox" data-api-url='add/mailbox-policy' data-api-attr='{"username":<?= json_encode($username); ?>,"object_list":"wl"}' href="#"><i class="bi bi-plus-lg"></i> <?=$lang['user']['spamfilter_table_add'];?></button>
            </span>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="wl_policy_mailbox_table"></table>
        </div>
        <div class="mass-actions-user">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['spam_policy'];?>">
            <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" id="toggle_multi_select_all" data-id="policy_wl_mailbox" href="#"><i class="bi bi-check-all"></i> <?=$lang['mailbox']['toggle_all'];?></a>
            <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-danger" data-action="delete_selected" data-id="policy_wl_mailbox" data-api-url='delete/mailbox-policy' href="#"><?=$lang['mailbox']['remove'];?></a>
            <div class="clearfix visible-xs-block"></div>
          </div>
        </div>
      </div>
			<div class="col-sm-6">
				<h4><?=$lang['user']['spamfilter_bl'];?></h4>
        <p><?=$lang['user']['spamfilter_bl_desc'];?></p>
        <form class="form-inline space20" data-id="add_bl_policy_mailbox">
          <div class="input-group" data-acl="<?=$_SESSION['acl']['spam_policy'];?>">
            <input type="text" class="form-control" name="object_from" placeholder="*@example.org" required>
            <span class="input-group-btn">
              <button class="btn btn-default" data-action="add_item" data-id="add_bl_policy_mailbox" data-api-url='add/mailbox-policy' data-api-attr='{"username":<?= json_encode($username); ?>,"object_list":"bl"}' href="#"><i class="bi bi-plus-lg"></i> <?=$lang['user']['spamfilter_table_add'];?></button>
            </span>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="bl_policy_mailbox_table"></table>
        </div>
        <div class="mass-actions-user">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['spam_policy'];?>">
            <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" id="toggle_multi_select_all" data-id="policy_bl_mailbox" href="#"><i class="bi bi-check-all"></i> <?=$lang['mailbox']['toggle_all'];?></a>
            <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-danger" data-action="delete_selected" data-id="policy_bl_mailbox" data-api-url='delete/mailbox-policy' href="#"><?=$lang['mailbox']['remove'];?></a>
            <div class="clearfix visible-xs-block"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

	<div role="tabpanel" class="tab-pane" id="Syncjobs">
		<div class="table-responsive">
      <table class="table table-striped" id="sync_job_table"></table>
		</div>
    <div class="mass-actions-user">
      <div class="btn-group" data-acl="<?=$_SESSION['acl']['syncjobs'];?>">
	    <div class="btn-group">
        <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" id="toggle_multi_select_all" data-id="syncjob" href="#"><i class="bi bi-check-all"></i> <?=$lang['mailbox']['toggle_all'];?></a>
        <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a data-action="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
          <li><a data-action="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
          <li role="separator" class="divider"></li>
          <li><a data-action="delete_selected" data-id="syncjob" data-api-url='delete/syncjob' href="#"><?=$lang['mailbox']['remove'];?></a></li>
        </ul>
        <div class="clearfix visible-xs"></div>
	    </div>
	    <div class="btn-group">
        <a class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-success" href="#" data-toggle="modal" data-target="#addSyncJobModal"><i class="bi bi-plus-lg"></i> <?=$lang['user']['create_syncjob'];?></a>
	    </div>
      </div>
    </div>
  </div>

	<div role="tabpanel" class="tab-pane" id="AppPasswds">
	    <p><?=$lang['user']['app_hint'];?></p>
		<div class="table-responsive">
	      <table class="table table-striped" id="app_passwd_table"></table>
		</div>
	    <div class="mass-actions-user">
	      <div class="btn-group" data-acl="<?=$_SESSION['acl']['app_passwds'];?>">
		    <div class="btn-group">
	          <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" id="toggle_multi_select_all" data-id="apppasswd" href="#"><i class="bi bi-check-all"></i> <?=$lang['mailbox']['toggle_all'];?></a>
	          <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
	          <ul class="dropdown-menu">
	            <li><a data-action="edit_selected" data-id="apppasswd" data-api-url='edit/app-passwd' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
	            <li><a data-action="edit_selected" data-id="apppasswd" data-api-url='edit/app-passwd' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
	            <li role="separator" class="divider"></li>
	            <li><a data-action="delete_selected" data-id="apppasswd" data-api-url='delete/app-passwd' href="#"><?=$lang['mailbox']['remove'];?></a></li>
	          </ul>
	          <div class="clearfix visible-xs"></div>
		    </div>
		    <div class="btn-group">
	          <a class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-success" href="#" data-toggle="modal" data-target="#addAppPasswdModal"><i class="bi bi-plus-lg"></i> <?=$lang['user']['create_app_passwd'];?></a>
		    </div>
	      </div>
	    </div>
	</div>

	<div role="tabpanel" class="tab-pane" id="Pushover">
    <form data-id="pushover" class="form well" method="post">
      <input type="hidden" value="0" name="evaluate_x_prio">
      <input type="hidden" value="0" name="only_x_prio">
      <input type="hidden" value="0" name="active">
      <div class="row">
        <div class="col-sm-1">
          <p class="help-block"><a href="https://pushover.net" target="_blank"><img src="data:image/png;base64,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" class="img img-fluid"></a></p>
        </div>
        <div class="col-sm-10">
              <p class="help-block"><?=sprintf($lang['user']['pushover_info'], $username);?></p>
              <p class="help-block"><?=$lang['user']['pushover_vars'];?>: <code>{SUBJECT}</code>, <code>{SENDER}</code></p>
              <div class="form-group">
                <div class="row">
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label for="token">API Token/Key (Application)</label>
                      <input type="text" class="form-control" name="token" maxlength="30" value="<?=$pushover_data['token'];?>" required>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label for="key">User/Group Key</label>
                      <input type="text" class="form-control" name="key" maxlength="30" value="<?=$pushover_data['key'];?>" required>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label for="title"><?=$lang['user']['pushover_title'];?></label>
                      <input type="text" class="form-control" name="title" value="<?=$pushover_data['title'];?>" placeholder="Mail">
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="form-group">
                      <label for="text"><?=$lang['user']['pushover_text'];?></label>
                      <input type="text" class="form-control" name="text" value="<?=$pushover_data['text'];?>" placeholder="You've got mail 📧">
                    </div>
                  </div>
                  <div class="col-sm-12">
                    <div class="form-group">
                      <label for="text"><?=$lang['user']['pushover_sender_array'];?></label>
                      <input type="text" class="form-control" name="senders" value="<?=$pushover_data['senders'];?>" placeholder="sender1@example.com, sender2@example.com">
                    </div>
                  </div>
                  <div class="col-sm-12">
                    <div class="checkbox">
                    <label><input type="checkbox" value="1" name="active" <?=($pushover_data['active']=="1") ? "checked" : null;?>> <?=$lang['user']['active'];?></label>
                    </div>
                  </div>
                  <div class="col-sm-12">
                    <legend style="cursor:pointer;margin-top:10px" data-target="#po_advanced" unselectable="on" data-toggle="collapse">
                      <i style="font-size:10pt;" class="bi bi-plus-square"></i> <?=$lang['user']['advanced_settings'];?>
                    </legend>
                  </div>
                  <div class="col-sm-12">
                    <div id="po_advanced" class="collapse">
                      <div class="form-group">
                        <label for="text"><?=$lang['user']['pushover_sender_regex'];?></label>
                        <input type="text" class="form-control" name="senders_regex" value="<?=$pushover_data['senders_regex'];?>" placeholder="/(.*@example\.org$|^foo@example\.com$)/i" regex="true">
                        <div class="checkbox">
                          <label><input type="checkbox" value="1" name="evaluate_x_prio" <?=($pushover_data['attributes']['evaluate_x_prio']=="1") ? "checked" : null;?>> <?=$lang['user']['pushover_evaluate_x_prio'];?></label>
                        </div>
                        <div class="checkbox">
                          <label><input type="checkbox" value="1" name="only_x_prio" <?=($pushover_data['attributes']['only_x_prio']=="1") ? "checked" : null;?>> <?=$lang['user']['pushover_only_x_prio'];?></label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <div class="btn-group mass-actions-user" data-acl="<?=$_SESSION['acl']['pushover'];?>">
              <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-success" data-action="edit_selected" data-id="pushover" data-item="<?=htmlspecialchars($username);?>" data-api-url='edit/pushover' data-api-attr='{}' href="#"><?=$lang['user']['save'];?></a>
              <a class="btn btn-sm btn-xs-half visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-default" data-action="edit_selected" data-id="pushover-test" data-item="<?=htmlspecialchars($username);?>" data-api-url='edit/pushover-test' data-api-attr='{}' href="#"><i class="bi bi-check-all"></i> <?=$lang['user']['pushover_verify'];?></a>
              <div class="clearfix visible-xs"></div>
              <a id="pushover_delete" class="btn btn-sm visible-xs-block visible-sm-inline visible-md-inline visible-lg-inline btn-danger" data-action="edit_selected" data-id="pushover-delete" data-item="<?=htmlspecialchars($username);?>" data-api-url='edit/pushover' data-api-attr='{"delete":"true"}' href="#"><i class="bi bi-trash"></i> <?=$lang['user']['remove'];?></a>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
</div><!-- /container -->
<div style="margin-bottom:200px;"></div>
<?php
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] != 'admin') {
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/user.php';
?>
<script type='text/javascript'>
<?php
$lang_user = json_encode($lang['user']);
echo "var lang = " . $lang_user . ";\n";
echo "var user_spam_score = [" . mailbox('get', 'spam_score', $username) . "];\n";
echo "var acl = '" . json_encode($_SESSION['acl']) . "';\n";
echo "var csrf_token = '" . $_SESSION['CSRF']['TOKEN'] . "';\n";
echo "var mailcow_cc_username = '" . $_SESSION['mailcow_cc_username'] . "';\n";
echo "var pagination_size = '" . $PAGINATION_SIZE . "';\n";
?>
</script>
<?php
$js_minifier->add('/web/js/site/user.js');
$js_minifier->add('/web/js/site/pwgen.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
}
else {
	header('Location: /');
	exit();
}
?>
