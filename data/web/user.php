<?php
require_once("inc/prerequisites.inc.php");
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {

  /*
  / DOMAIN ADMIN
  */

	require_once("inc/header.inc.php");
	$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
  $tfa_data = get_tfa();
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
      </div>
    </div>
    <hr>
    <div class="row">
      <div class="col-md-3 col-xs-5 text-right"><?=$lang['tfa']['tfa'];?></div>
        <div class="col-sm-9 col-xs-7">
          <p id="tfa_pretty"><?=$tfa_data['pretty'];?></p>
            <div id="tfa_additional">
              <?php if (!empty($tfa_data['additional'])):
              foreach ($tfa_data['additional'] as $key_info): ?>
                <form style="display:inline;" method="post">
                <input type="hidden" name="unset_tfa_key" value="<?=$key_info['id'];?>" />
                <div class="label label-default">🔑 <?=$key_info['key_id'];?> <a href="#" style="font-weight:bold;color:white" onClick="$(this).closest('form').submit()">[<?=strtolower($lang['admin']['remove']);?>]</a></div>
              </form>
              <?php endforeach;
              endif;?>
            </div>
            <br />
        </div>
    </div>
    <div class="row">
      <div class="col-md-3 col-xs-5 text-right"><?=$lang['tfa']['set_tfa'];?></div>
      <div class="col-md-9 col-xs-7">
        <select id="selectTFA" class="selectpicker" title="<?=$lang['tfa']['select'];?>">
          <option value="yubi_otp"><?=$lang['tfa']['yubi_otp'];?></option>
          <option value="u2f"><?=$lang['tfa']['u2f'];?></option>
          <option value="totp"><?=$lang['tfa']['totp'];?></option>
          <option value="none"><?=$lang['tfa']['none'];?></option>
        </select>
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

	require_once("inc/header.inc.php");
	$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
	$username = $_SESSION['mailcow_cc_username'];
  $mailboxdata = mailbox('get', 'mailbox_details', $username);
?>
<div class="container">
<h3><?=$lang['user']['user_settings'];?></h3>

<div class="panel panel-default">
<div class="panel-heading"><?=$lang['user']['mailbox_details'];?></div>
<div class="panel-body">
  <div class="row">
    <div class="col-sm-offset-3 col-sm-9">
      <p><a href="#pwChangeModal" data-toggle="modal">[<?=$lang['user']['change_password'];?>]</a></p>
    </div>
  </div>
  <hr>
  <?php // Get user information about aliases
  $user_get_alias_details = user_get_alias_details($username);?>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['aliases'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <p><?=$user_get_alias_details['aliases'];?></p>
    </div>
  </div>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['domain_aliases'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <p><?=$user_get_alias_details['ad_alias'];?></p>
    </div>
  </div>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['aliases_also_send_as'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <p><?=$user_get_alias_details['aliases_also_send_as'];?></p>
    </div>
  </div>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['aliases_send_as_all'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <p><?=$user_get_alias_details['aliases_send_as_all'];?></p>
    </div>
  </div>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['is_catch_all'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <p><?=$user_get_alias_details['is_catch_all'];?></p>
    </div>
  </div>
  <hr>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['in_use'];?>:</div>
    <div class="col-md-5 col-xs-7">
      <div class="progress">
        <div class="progress-bar progress-bar-<?=$mailboxdata['percent_class'];?>" role="progressbar" aria-valuenow="<?=$mailboxdata['percent_in_use'];?>" aria-valuemin="0" aria-valuemax="100" style="min-width:2em;width: <?=$mailboxdata['percent_in_use'];?>%;">
          <?=$mailboxdata['percent_in_use'];?>%
        </div>
      </div>
      <p><?=formatBytes($mailboxdata['quota_used'], 2);?> / <?=formatBytes($mailboxdata['quota'], 2);?>, <?=$mailboxdata['messages'];?> <?=$lang['user']['messages'];?></p>
    </div>
  </div>
  <hr>
  <?php // Show tagging options ?>
  <?php $get_tagging_options = mailbox('get', 'delimiter_action', $username);?>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['tag_handling'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <div class="btn-group">

      <button type="button" class="btn btn-sm btn-default <?=($get_tagging_options == "subfolder") ? 'active' : null; ?>"
        id="edit_selected"
        data-item="<?= $username; ?>"
        data-id="delimiter_action"
        data-api-url='edit/delimiter_action'
        data-api-attr='{"tagged_mail_handler":"subfolder"}'><?=$lang['user']['tag_in_subfolder'];?></button>

      <button type="button" class="btn btn-sm btn-default <?=($get_tagging_options == "subject") ? 'active' : null; ?>"
        id="edit_selected"
        data-item="<?= $username; ?>"
        data-id="delimiter_action"
        data-api-url='edit/delimiter_action'
        data-api-attr='{"tagged_mail_handler":"subject"}'><?=$lang['user']['tag_in_subject'];?></button>

    </div>
    <p class="help-block"><?=$lang['user']['tag_help_explain'];?></p>
    <p class="help-block"><?=$lang['user']['tag_help_example'];?></p>
    </div>
  </div>
  <?php // Show TLS policy options ?>
  <?php $get_tls_policy = mailbox('get', 'tls_policy', $username); ?>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['tls_policy'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <div class="btn-group">

      <button type="button" class="btn btn-sm btn-default <?=($get_tls_policy['tls_enforce_in'] == "1") ? "active" : null;?>"
        id="edit_selected"
        data-item="<?= $username; ?>"
        data-id="tls_policy"
        data-api-url='edit/tls_policy'
        data-api-attr='{"tls_enforce_in":<?=($get_tls_policy['tls_enforce_in'] == "1") ? "0" : "1";?>}'><?=$lang['user']['tls_enforce_in'];?></button>

      <button type="button" class="btn btn-sm btn-default <?=($get_tls_policy['tls_enforce_out'] == "1") ? "active" : null;?>"
        id="edit_selected"
        data-item="<?= $username; ?>"
        data-id="tls_policy"
        data-api-url='edit/tls_policy'
        data-api-attr='{"tls_enforce_out":<?=($get_tls_policy['tls_enforce_out'] == "1") ? "0" : "1";?>}'><?=$lang['user']['tls_enforce_out'];?></button>

    </div>
    <p class="help-block"><?=$lang['user']['tls_policy_warning'];?></p>
    </div>
  </div>
  <?php // Rest EAS devices ?>
  <div class="row">
    <div class="col-md-3 col-xs-5 text-right"><?=$lang['user']['eas_reset'];?>:</div>
    <div class="col-md-9 col-xs-7">
    <button class="btn btn-xs btn-default" id="delete_selected" data-text="<?=$lang['user']['eas_reset'];?>?" data-item="<?= $username; ?>" data-id="eas_cache" data-api-url='delete/eas_cache' href="#"><?=$lang['user']['eas_reset_now'];?></button>
    <p class="help-block"><?=$lang['user']['eas_reset_help'];?></p>
    </div>
  </div>
</div>
</div>

<!-- Nav tabs -->
<ul class="nav nav-pills nav-justified" role="tablist">
	<li role="presentation" class="active"><a href="#SpamAliases" aria-controls="SpamAliases" role="tab" data-toggle="tab"><?=$lang['user']['spam_aliases'];?></a></li>
	<li role="presentation"><a href="#Spamfilter" aria-controls="Spamfilter" role="tab" data-toggle="tab"><?=$lang['user']['spamfilter'];?></a></li>
	<li role="presentation"><a href="#Syncjobs" aria-controls="Syncjobs" role="tab" data-toggle="tab"><?=$lang['user']['sync_jobs'];?></a></li>
</ul>
<hr>

<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="SpamAliases">
    <div class="row">
      <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="table-responsive">
          <table class="table table-striped" id="tla_table"></table>
        </div>
      </div>
		</div>
    <div class="mass-actions-user">
      <div class="btn-group">
        <div class="btn-group">
          <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="tla" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
          <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a id="edit_selected" data-id="tla" data-api-url='edit/time_limited_alias' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-time"></span> + 1h</a></li>
            <li role="separator" class="divider"></li>
            <li><a id="delete_selected" data-id="tla" data-api-url='delete/time_limited_alias' href="#"><?=$lang['mailbox']['remove'];?></a></li>
          </ul>
        </div>
        <div class="btn-group">
          <a class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['user']['alias_create_random'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a id="add_item" data-api-url='add/time_limited_alias' data-api-attr='{"validity":"1"}' href="#">1 <?=$lang['user']['hour'];?></a></li>
            <li><a id="add_item" data-api-url='add/time_limited_alias' data-api-attr='{"validity":"6"}' href="#">6 <?=$lang['user']['hours'];?></a></li>
            <li><a id="add_item" data-api-url='add/time_limited_alias' data-api-attr='{"validity":"24"}' href="#">1 <?=$lang['user']['day'];?></a></li>
            <li><a id="add_item" data-api-url='add/time_limited_alias' data-api-attr='{"validity":"168"}' href="#">1 <?=$lang['user']['week'];?></a></li>
            <li><a id="add_item" data-api-url='add/time_limited_alias' data-api-attr='{"validity":"672"}' href="#">4 <?=$lang['user']['weeks'];?></a></li>
          </ul>
        </div>
      </div>
    </div>
	</div>

	<div role="tabpanel" class="tab-pane" id="Spamfilter">
		<h4><?=$lang['user']['spamfilter_behavior'];?></h4>
		<form class="form-horizontal" role="form" data-id="spam_score" method="post">
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<input name="spam_score" id="spam_score" type="text" 
						data-provide="slider"
						data-slider-min="1"
						data-slider-max="30"
						data-slider-step="0.5"
						data-slider-range="true"
						data-slider-tooltip='always'
						data-slider-id="slider1"
						data-slider-value="[<?=mailbox('get', 'spam_score', $username);?>]"
						data-slider-step="1" />
					<br /><br />
					<ul>
						<li><?=$lang['user']['spamfilter_green'];?></li>
						<li><?=$lang['user']['spamfilter_yellow'];?></li>
						<li><?=$lang['user']['spamfilter_red'];?></li>
					</ul>
					<p><i><?=$lang['user']['spamfilter_default_score'];?> 5:15</i></p>
					<p><?=$lang['user']['spamfilter_hint'];?></p>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
        <button type="button" class="btn btn-sm btn-success" id="edit_selected"
          data-item="<?= $username; ?>"
          data-id="spam_score"
          data-api-url='edit/spam_score'
          data-api-attr='{}'><?=$lang['user']['save_changes'];?></button>
				</div>
			</div>
		</form>
		<hr>
		<div class="row">
			<div class="col-sm-6">
				<h4><?=$lang['user']['spamfilter_wl'];?></h4>
        <p><?=$lang['user']['spamfilter_wl_desc'];?></p>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="wl_policy_mailbox_table"></table>
        </div>
        <div class="mass-actions-user">
          <div class="btn-group">
            <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="policy_wl_mailbox" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
            <a class="btn btn-sm btn-danger" id="delete_selected" data-id="policy_wl_mailbox" data-api-url='delete/mailbox-policy' href="#"><?=$lang['mailbox']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <form class="form-inline" data-id="add_wl_policy_mailbox">
          <div class="input-group">
            <input type="text" class="form-control" name="object_from" id="object_from" placeholder="*@example.org" required>
            <span class="input-group-btn">
              <button class="btn btn-success" id="add_item" data-id="add_wl_policy_mailbox" data-api-url='add/mailbox-policy' data-api-attr='{"username":"<?= $username; ?>","object_list":"wl"}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['user']['spamfilter_table_add'];?></button>
            </span>
          </div>
        </form>
      </div>
			<div class="col-sm-6">
				<h4><?=$lang['user']['spamfilter_bl'];?></h4>
        <p><?=$lang['user']['spamfilter_bl_desc'];?></p>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="bl_policy_mailbox_table"></table>
        </div>
        <div class="mass-actions-user">
          <div class="btn-group">
            <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="policy_bl_mailbox" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
            <a class="btn btn-sm btn-danger" id="delete_selected" data-id="policy_bl_mailbox" data-api-url='delete/mailbox-policy' href="#"><?=$lang['mailbox']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <form class="form-inline" data-id="add_bl_policy_mailbox">
          <div class="input-group">
            <input type="text" class="form-control" name="object_from" id="object_from" placeholder="*@example.org" required>
            <input type="hidden" name="username" value="<?= $username ;?>">
            <input type="hidden" name="object_list" value="bl">
            <span class="input-group-btn">
              <button class="btn btn-success" id="add_item" data-id="add_bl_policy_mailbox" data-api-url='add/mailbox-policy' data-api-attr='{"username":"<?= $username; ?>","object_list":"bl"}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['user']['spamfilter_table_add'];?></button>
            </span>
          </div>
        </form>
      </div>
    </div>
  </div>

	<div role="tabpanel" class="tab-pane" id="Syncjobs">
		<div class="table-responsive">
      <table class="table table-striped" id="sync_job_table"></table>
		</div>
    <div class="mass-actions-user">
      <div class="btn-group">
        <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="syncjob" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
        <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a id="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
          <li><a id="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
          <li role="separator" class="divider"></li>
          <li><a id="delete_selected" data-text="<?=$lang['user']['eas_reset'];?>?" data-id="syncjob" data-api-url='delete/syncjob' href="#"><?=$lang['mailbox']['remove'];?></a></li>
        </ul>
        <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addSyncJobModal"><span class="glyphicon glyphicon-plus"></span> <?=$lang['user']['create_syncjob'];?></a>
      </div>
    </div>
		</div>
	</div>
</div>

<?php
}
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "user" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {

  /*
  / USER OR DOMAIN ADMIN
  */

?>
<div class="modal fade" id="logModal" tabindex="-1" role="dialog" aria-labelledby="logTextLabel">
  <div class="modal-dialog" style="width:90%" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <span id="logText"></span>
      </div>
    </div>
  </div>
</div>

<div style="margin-bottom:200px;"></div>
<div class="modal fade" id="pwChangeModal" tabindex="-1" role="dialog" aria-labelledby="pwChangeModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <form class="form-horizontal" role="form" method="post" autocomplete="off">
          <div class="form-group">
            <label class="control-label col-sm-3" for="user_new_pass"><?=$lang['user']['new_password'];?></label>
            <div class="col-sm-5">
            <input type="password" class="form-control" name="user_new_pass" id="user_new_pass" autocomplete="off" required>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-3" for="user_new_pass2"><?=$lang['user']['new_password_repeat'];?></label>
            <div class="col-sm-5">
            <input type="password" class="form-control" name="user_new_pass2" id="user_new_pass2" autocomplete="off" required>
            <p class="help-block"><?=$lang['user']['new_password_description'];?></p>
            </div>
          </div>
          <hr>
          <div class="form-group">
            <label class="control-label col-sm-3" for="user_old_pass"><?=$lang['user']['password_now'];?></label>
            <div class="col-sm-5">
            <input type="password" class="form-control" name="user_old_pass" id="user_old_pass" autocomplete="off" required>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
              <button type="submit" name="edit_<?=($_SESSION['mailcow_cc_role'] == "domainadmin") ? "domain_admin" : "user_account";?>" class="btn btn-sm btn-success"><?=$lang['user']['change_password'];?></button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
</div> <!-- /container -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/user.php';
?>
<script type='text/javascript'>
<?php
$lang_user = json_encode($lang['user']);
echo "var lang = ". $lang_user . ";\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/user.js"></script>
<?php
require_once("inc/footer.inc.php");
}
else {
	header('Location: /');
	exit();
}
?>
