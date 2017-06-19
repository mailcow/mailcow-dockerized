<?php
require_once("inc/prerequisites.inc.php");
$AuthUsers = array("admin", "domainadmin", "user");
if (!isset($_SESSION['mailcow_cc_role']) OR !in_array($_SESSION['mailcow_cc_role'], $AuthUsers)) {
	header('Location: /');
	exit();
}
require_once("inc/header.inc.php");
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['edit']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
		if (isset($_GET["alias"]) &&
			!empty($_GET["alias"])) {
				$alias = $_GET["alias"];
        $result = mailbox('get', 'alias_details', $alias);
				if (!empty($result)) {
				?>
					<h4><?=$lang['edit']['alias'];?></h4>
					<br />
					<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
						<input type="hidden" value="0" name="active">
						<input type="hidden" name="address" value="<?=htmlspecialchars($alias);?>">
						<div class="form-group">
							<label class="control-label col-sm-2" for="goto"><?=$lang['edit']['target_address'];?></label>
							<div class="col-sm-10">
								<textarea class="form-control" autocapitalize="none" autocorrect="off" rows="10" id="goto" name="goto"><?=htmlspecialchars($result['goto']) ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
								<label><input type="checkbox" value="1" name="active" <?php if (isset($result['active_int']) && $result['active_int']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<button type="submit" name="mailbox_edit_alias" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
							</div>
						</div>
					</form>
				<?php
				}
				else {
				?>
					<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
				<?php
				}
		}
		elseif (isset($_GET['domainadmin']) && 
			ctype_alnum(str_replace(array('_', '.', '-'), '', $_GET["domainadmin"])) &&
			!empty($_GET["domainadmin"]) &&
			$_GET["domainadmin"] != 'admin' &&
			$_SESSION['mailcow_cc_role'] == "admin") {
				$domain_admin = $_GET["domainadmin"];
        $result = get_domain_admin_details($domain_admin);
				if (!empty($result)) {
				?>
				<h4><?=$lang['edit']['domain_admin'];?></h4>
				<br />
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<input type="hidden" value="0" name="active">
					<input type="hidden" name="username" value="<?=htmlspecialchars($domain_admin);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="username_new"><?=$lang['edit']['username'];?></label>
						<div class="col-sm-10">
              <input class="form-control" type="text" name="username_new" value="<?=htmlspecialchars($domain_admin);?>" />
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="domains"><?=$lang['edit']['domains'];?></label>
						<div class="col-sm-10">
							<select id="domains" name="domains[]" multiple required>
							<?php
							foreach ($result['selected_domains'] as $domain):
							?>
								<option selected><?=htmlspecialchars($domain);?></option>
							<?php
							endforeach;
							foreach ($result['unselected_domains'] as $domain):
							?>
								<option><?=htmlspecialchars($domain);?></option>
							<?php
							endforeach;
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password"><?=$lang['edit']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2"><?=$lang['edit']['password_repeat'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" <?php if (isset($result['active_int']) && $result['active_int']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="disable_tfa"> <?=$lang['tfa']['disable_tfa'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="edit_domain_admin" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
			<?php
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	elseif (isset($_GET['domain']) &&
		is_valid_domain_name($_GET["domain"]) &&
		!empty($_GET["domain"])) {
			$domain = $_GET["domain"];
      $result = mailbox('get', 'domain_details', $domain);
			if (!empty($result)) {
			?>
				<h4><?=$lang['edit']['domain'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<input type="hidden" value="0" name="active">
					<input type="hidden" value="0" name="backupmx">
					<input type="hidden" value="0" name="relay_all_recipients">
					<input type="hidden" name="domain" value="<?=htmlspecialchars($domain);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="description"><?=$lang['edit']['description'];?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="description" id="description" value="<?=htmlspecialchars($result['description']);?>">
						</div>
					</div>
					<?php
					if ($_SESSION['mailcow_cc_role'] == "admin") {
					?>
					<div class="form-group">
						<label class="control-label col-sm-2" for="aliases"><?=$lang['edit']['max_aliases'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="aliases" id="aliases" value="<?=intval($result['max_num_aliases_for_domain']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes"><?=$lang['edit']['max_mailboxes'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="<?=intval($result['max_num_mboxes_for_domain']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota"><?=$lang['edit']['max_quota'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="maxquota" id="maxquota" value="<?=intval($result['max_quota_for_mbox'] / 1048576);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['edit']['domain_quota'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="quota" id="quota" value="<?=intval($result['max_quota_for_domain'] / 1048576);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2"><?=$lang['edit']['backup_mx_options'];?></label>
						<div class="col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" value="1" name="backupmx" <?=(isset($result['backupmx_int']) && $result['backupmx_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['relay_domain'];?></label>
								<br />
								<label><input type="checkbox" value="1" name="relay_all_recipients" <?=(isset($result['relay_all_recipients_int']) && $result['relay_all_recipients_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['relay_all'];?></label>
								<p><?=$lang['edit']['relay_all_info'];?></p>
							</div>
						</div>
					</div>
					<?php
					}
					?>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" value="1" name="active" <?=(isset($result['active_int']) && $result['active_int']=="1") ? "checked" : null;?> <?=($_SESSION['mailcow_cc_role'] == "admin") ? null : "disabled";?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="mailbox_edit_domain" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
				<?php
        if (!empty($dkim = dkim('details', $domain))) {
				?>
        <hr>
        <div class="row">
          <div class="col-xs-2">
            <p>Domain: <strong><?=htmlspecialchars($result['domain_name']);?></strong> (<?=$dkim['dkim_selector'];?>._domainkey)</p>
          </div>
          <div class="col-xs-10">
            <pre><?=$dkim['dkim_txt'];?></pre>
          </div>
        </div>
				<?php
				}
        ?>
		<hr>
		<div class="row">
			<div class="col-sm-6">
				<h4><?=$lang['user']['spamfilter_wl'];?></h4>
        <p><?=$lang['user']['spamfilter_wl_desc'];?></p>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="wl_policy_domain_table"></table>
        </div>
        <div class="mass-actions-user">
          <div class="btn-group">
            <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="policy_wl_domain" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
            <a class="btn btn-sm btn-danger" id="delete_selected" data-id="policy_wl_domain" data-api-url='delete/domain-policy' href="#"><?=$lang['mailbox']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <form class="form-inline" data-id="add_wl_policy_domain">
          <div class="input-group">
            <input type="text" class="form-control" name="object_from" id="object_from" placeholder="*@example.org" required>
            <span class="input-group-btn">
              <button class="btn btn-default" id="add_item" data-id="add_wl_policy_domain" data-api-url='add/domain-policy' data-api-attr='{"domain":"<?= $domain; ?>","object_list":"wl"}' href="#"><?=$lang['user']['spamfilter_table_add'];?></button>
            </span>
          </div>
        </form>
      </div>
			<div class="col-sm-6">
				<h4><?=$lang['user']['spamfilter_bl'];?></h4>
        <p><?=$lang['user']['spamfilter_bl_desc'];?></p>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="bl_policy_domain_table"></table>
        </div>
        <div class="mass-actions-user">
          <div class="btn-group">
            <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="policy_bl_domain" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
            <a class="btn btn-sm btn-danger" id="delete_selected" data-id="policy_bl_domain" data-api-url='delete/domain-policy' href="#"><?=$lang['mailbox']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <form class="form-inline" data-id="add_bl_policy_domain">
          <div class="input-group">
            <input type="text" class="form-control" name="object_from" id="object_from" placeholder="*@example.org" required>
            <span class="input-group-btn">
              <button class="btn btn-default" id="add_item" data-id="add_bl_policy_domain" data-api-url='add/domain-policy' data-api-attr='{"domain":"<?= $domain; ?>","object_list":"bl"}' href="#"><?=$lang['user']['spamfilter_table_add'];?></button>
            </span>
          </div>
        </form>
      </div>
    </div>
        <?php
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	elseif (isset($_GET['aliasdomain']) &&
		is_valid_domain_name($_GET["aliasdomain"]) &&
		!empty($_GET["aliasdomain"])) {
			$alias_domain = $_GET["aliasdomain"];
      $result = mailbox('get', 'alias_domain_details', $alias_domain);
      if (!empty($result)) {
			?>
				<h4><?=$lang['edit']['edit_alias_domain'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<input type="hidden" value="0" name="active">
					<input type="hidden" value="<?=$result['alias_domain'];?>" name="alias_domain">
					<div class="form-group">
						<label class="control-label col-sm-2" for="target_domain"><?=$lang['edit']['target_domain'];?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="target_domain" id="target_domain" value="<?=htmlspecialchars($result['target_domain']);?>">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" value="1" name="active" <?=(isset($result['active_int']) && $result['active_int']=="1") ?  "checked" : null ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="mailbox_edit_alias_domain" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
				<?php
        if (!empty($dkim = dkim('details', $alias_domain))) {
				?>
        <hr>
        <div class="row">
          <div class="col-xs-2">
            <p>Domain: <strong><?=htmlspecialchars($result['alias_domain']);?></strong> (<?=$dkim['dkim_selector'];?>._domainkey)</p>
          </div>
          <div class="col-xs-10">
          <pre><?=$dkim['dkim_txt'];?></pre>
          </div>
        </div>
				<?php
				}
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	elseif (isset($_GET['mailbox']) && filter_var($_GET["mailbox"], FILTER_VALIDATE_EMAIL) && !empty($_GET["mailbox"])) {
    $mailbox = $_GET["mailbox"];
    $result = mailbox('get', 'mailbox_details', $mailbox);
    if (!empty($result)) {
      ?>
      <h4><?=$lang['edit']['mailbox'];?></h4>
      <form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
				<input type="hidden" value="0" name="sender_acl">
				<input type="hidden" value="0" name="active">
				<input type="hidden" name="username" value="<?=htmlspecialchars($result['username']);?>">
        <div class="form-group">
          <label class="control-label col-sm-2" for="name"><?=$lang['edit']['full_name'];?>:</label>
          <div class="col-sm-10">
          <input type="text" class="form-control" name="name" id="name" value="<?=htmlspecialchars($result['name'], ENT_QUOTES, 'UTF-8');?>">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2" for="quota"><?=$lang['edit']['quota_mb'];?>:
            <br /><span id="quotaBadge" class="badge">max. <?=intval($result['max_new_quota'] / 1048576)?> MiB</span>
          </label>
          <div class="col-sm-10">
            <input type="number" name="quota" id="quota" id="destroyable" style="width:100%" min="1" max="<?=intval($result['max_new_quota'] / 1048576);?>" value="<?=intval($result['quota']) / 1048576;?>" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2" for="sender_acl"><?=$lang['edit']['sender_acl'];?>:</label>
          <div class="col-sm-10">
            <select data-width="100%" style="width:100%" id="sender_acl" name="sender_acl[]" size="10" multiple>
            <?php
            $sender_acl_handles = mailbox('get', 'sender_acl_handles', $mailbox);

            foreach ($sender_acl_handles['sender_acl_domains']['ro'] as $domain):
              ?>
              <option data-subtext="Admin" value="<?=htmlspecialchars($domain);?>" disabled selected><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $domain));?></option>
              <?php
            endforeach;

            foreach ($sender_acl_handles['sender_acl_addresses']['ro'] as $domain):
              ?>
            <option data-subtext="Admin" disabled selected><?=htmlspecialchars($alias);?></option>
              <?php
            endforeach;

            foreach ($sender_acl_handles['fixed_sender_aliases'] as $alias):
              ?>
              <option data-subtext="Alias" disabled selected><?=htmlspecialchars($alias);?></option>
              <?php
            endforeach;

            foreach ($sender_acl_handles['sender_acl_domains']['rw'] as $domain):
              ?>
              <option value="<?=htmlspecialchars($domain);?>" selected><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $domain));?></option>
              <?php
            endforeach;

            foreach ($sender_acl_handles['sender_acl_domains']['selectable'] as $domain):
              ?>
              <option value="<?=htmlspecialchars($domain);?>"><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $domain));?></option>
              <?php
            endforeach;

            foreach ($sender_acl_handles['sender_acl_addresses']['rw'] as $address):
              ?>
                <option selected><?=htmlspecialchars($address);?></option>
              <?php
            endforeach;

            foreach ($sender_acl_handles['sender_acl_addresses']['selectable'] as $address):
              ?>
                <option><?=htmlspecialchars($address);?></option>
              <?php
            endforeach;

            ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2" for="password"><?=$lang['edit']['password'];?></label>
          <div class="col-sm-10">
          <input type="password" class="form-control" name="password" id="password" placeholder="<?=$lang['edit']['unchanged_if_empty'];?>">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label col-sm-2" for="password2"><?=$lang['edit']['password_repeat'];?></label>
          <div class="col-sm-10">
          <input type="password" class="form-control" name="password2" id="password2">
          </div>
        </div>
        <div class="form-group">
          <div class="col-sm-offset-2 col-sm-10">
            <div class="checkbox">
            <label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['active'];?></label>
            </div>
          </div>
        </div>
        <div class="form-group">
          <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" name="mailbox_edit_mailbox" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
          </div>
        </div>
      </form>
    <?php
    }
  }
	elseif (isset($_GET['resource']) && filter_var($_GET["resource"], FILTER_VALIDATE_EMAIL) && !empty($_GET["resource"])) {
			$resource = $_GET["resource"];
      $result = mailbox('get', 'resource_details', $resource);
      if (!empty($result)) {
        ?>
				<h4><?=$lang['edit']['resource'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
          <input type="hidden" value="0" name="active">
          <input type="hidden" value="0" name="multiple_bookings">
          <input type="hidden" name="name" value="<?=htmlspecialchars($result['name']);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="description"><?=$lang['add']['description'];?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="description" id="description" value="<?=htmlspecialchars($result['description'], ENT_QUOTES, 'UTF-8');?>" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['edit']['kind'];?>:</label>
						<div class="col-sm-10">
							<select name="kind" id="kind" title="<?=$lang['edit']['select'];?>" required>
								<option value="location" <?=($result['kind'] == "location") ? "selected" : null;?>>Location</option>
								<option value="group" <?=($result['kind'] == "group") ? "selected" : null;?>>Group</option>
								<option value="thing" <?=($result['kind'] == "thing") ? "selected" : null;?>>Thing</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="multiple_bookings" <?=($result['multiple_bookings_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['multiple_bookings'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="mailbox_edit_resource" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
			<?php
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	else {
	?>
		<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
	<?php
	}
}
elseif (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "user")) {
	if (isset($_GET['syncjob']) &&
    is_numeric($_GET['syncjob'])) {
			$id = $_GET["syncjob"];
      $result = mailbox('get', 'syncjob_details', $id);
      if (!empty($result)) {
			?>
				<h4><?=$lang['edit']['syncjob'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
          <input type="hidden" value="0" name="delete2duplicates">
          <input type="hidden" value="0" name="delete1">
          <input type="hidden" value="0" name="active">
          <input type="hidden" name="id" value="<?=htmlspecialchars($result['id']);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="host1"><?=$lang['edit']['hostname'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="host1" id="host1" value="<?=htmlspecialchars($result['host1'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="port1">Port</label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="port1" id="port1" min="1" max="65535" value="<?=htmlspecialchars($result['port1'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="user1"><?=$lang['edit']['username'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="user1" id="user1" value="<?=htmlspecialchars($result['user1'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password1"><?=$lang['edit']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password1" id="password1" value="<?=htmlspecialchars($result['password1'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="enc1"><?=$lang['edit']['encryption'];?>:</label>
						<div class="col-sm-10">
							<select id="enc1" name="enc1">
								<option <?=($result['enc1'] == "TLS") ? "selected" : null;?>>TLS</option>
								<option <?=($result['enc1'] == "SSL") ? "selected" : null;?>>SSL</option>
								<option <?=($result['enc1'] == "PLAIN") ? "selected" : null;?>>PLAIN</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mins_interval"><?=$lang['edit']['mins_interval'];?></label>
						<div class="col-sm-10">
              <input type="number" class="form-control" name="mins_interval" min="10" max="3600" value="<?=htmlspecialchars($result['mins_interval'], ENT_QUOTES, 'UTF-8');?>" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="subfolder2"><?=$lang['edit']['subfolder2'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="subfolder2" id="subfolder2" value="<?=htmlspecialchars($result['subfolder2'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxage"><?=$lang['edit']['maxage'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxage" id="maxage" value="<?=htmlspecialchars($result['maxage'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="exclude"><?=$lang['edit']['exclude'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="exclude" id="exclude" value="<?=htmlspecialchars($result['exclude'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="delete2duplicates" <?=($result['delete2duplicates']=="1") ? "checked" : "";?>> <?=$lang['edit']['delete2duplicates'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="delete1" <?=($result['delete1']=="1") ? "checked" : "";?>> <?=$lang['edit']['delete1'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" <?=($result['active']=="1") ? "checked" : "";?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="edit_syncjob" value="1" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
			<?php
			}
			else {
			?>
				<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
			<?php
			}
	}
	else {
	?>
		<div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
	<?php
	}
}
else {
?>
	<div class="alert alert-danger" role="alert"><?=$lang['danger']['access_denied'];?></div>
<?php
}
?>
				</div>
			</div>
		</div>
	</div>
<a href="<?=$_SESSION['return_to'];?>">&#8592; <?=$lang['edit']['previous'];?></a>
</div> <!-- /container -->
<script type='text/javascript'>
<?php
$lang_user = json_encode($lang['user']);
echo "var lang = ". $lang_user . ";\n";
echo "var table_for_domain = '". ((isset($domain)) ? $domain : null) . "';\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/edit.js"></script>
<?php
require_once("inc/footer.inc.php");
?>
