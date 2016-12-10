<?php
require_once("inc/prerequisites.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
	require_once("inc/header.inc.php");
	$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
	$username = $_SESSION['mailcow_cc_username'];
	$get_tls_policy = get_tls_policy($_SESSION['mailcow_cc_username']);
?>
<div class="container">
<h3><?=$lang['user']['mailbox_settings'];?></h3>
<p class="help-block"><?=$lang['user']['did_you_know'];?></p>

<div class="panel panel-default">
<div class="panel-heading"><?=$lang['user']['mailbox_details'];?></div>
<div class="panel-body">
<form class="form-horizontal" role="form" method="post" autocomplete="off">
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-10">
			<div class="checkbox">
				<label><input type="checkbox" name="togglePwNew" id="togglePwNew"> <?=$lang['user']['change_password'];?></label>
			</div>
		</div>
	</div>
	<div class="passFields">
		<div class="form-group">
			<label class="control-label col-sm-3" for="user_new_pass"><?=$lang['user']['new_password'];?></label>
			<div class="col-sm-5">
			<input type="password" class="form-control" pattern="(?=.*[A-Za-z])(?=.*[0-9])\w{6,}" name="user_new_pass" id="user_new_pass" autocomplete="off" disabled="disabled">
			</div>
		</div>
		<div class="form-group">
			<label class="control-label col-sm-3" for="user_new_pass2"><?=$lang['user']['new_password_repeat'];?></label>
			<div class="col-sm-5">
			<input type="password" class="form-control" pattern="(?=.*[A-Za-z])(?=.*[0-9])\w{6,}" name="user_new_pass2" id="user_new_pass2" disabled="disabled" autocomplete="off">
			<p class="help-block"><?=$lang['user']['new_password_description'];?></p>
			</div>
		</div>
		<hr>
	</div>
	<div class="form-group">
		<label class="control-label col-sm-3" for="user_old_pass"><?=$lang['user']['password_now'];?></label>
		<div class="col-sm-5">
		<input type="password" class="form-control" name="user_old_pass" id="user_old_pass" autocomplete="off" required>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9">
			<button type="submit" name="trigger_set_user_account" class="btn btn-success btn-default"><?=$lang['user']['save_changes'];?></button>
		</div>
	</div>
</form>
</div>
</div>

<!-- Nav tabs -->
<ul class="nav nav-pills nav-justified" role="tablist">
	<li role="presentation" class="active"><a href="#SpamAliases" aria-controls="SpamAliases" role="tab" data-toggle="tab"><?=$lang['user']['spam_aliases'];?></a></li>
	<li role="presentation"><a href="#Spamfilter" aria-controls="Spamfilter" role="tab" data-toggle="tab"><?=$lang['user']['spamfilter'];?></a></li>
	<li role="presentation"><a href="#TLSPolicy" aria-controls="TLSPolicy" role="tab" data-toggle="tab"><?=$lang['user']['tls_policy'];?></a></li>
</ul>
<hr>

<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="SpamAliases">
		<form class="form-horizontal" role="form" method="post">
		<div class="table-responsive">
		<table class="table table-striped sortable-theme-bootstrap" data-sortable id="timelimitedaliases">
			<thead>
			<tr>
				<th class="sort-table" style="min-width: 96px;"><?=$lang['user']['alias'];?></th>
				<th class="sort-table" style="min-width: 135px;"><?=$lang['user']['alias_valid_until'];?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			try {
				$stmt = $pdo->prepare("SELECT `address`,
					`goto`,
					`validity`
						FROM `spamalias`
							WHERE `goto` = :username
								AND `validity` >= :unixnow");
				$stmt->execute(array(':username' => $username, ':unixnow' => time()));
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
			}
			if(!empty($rows)):
			while ($row = array_shift($rows)):
			?>
				<tr id="data">
				<td><?=htmlspecialchars($row['address']);?></td>
				<td><?=htmlspecialchars(date($lang['user']['alias_full_date'], $row['validity']));?></td>
				</tr>
			<?php
			endwhile;
			else:
			?>
				<tr id="no-data"><td colspan="2" style="text-align: center; font-style: italic;"><?=$lang['user']['no_record'];?></td></tr>
			<?php
			endif;	
			?>
			</tbody>
		</table>
		</div>
		<div class="form-group">
			<div class="col-sm-9">
				<select id="validity" name="validity" title="<?=$lang['user']['alias_select_validity'];?>">
					<option value="1">1 <?=$lang['user']['hour'];?></option>
					<option value="6">6 <?=$lang['user']['hours'];?></option>
					<option value="24">1 <?=$lang['user']['day'];?></option>
					<option value="168">1 <?=$lang['user']['week'];?></option>
					<option value="672">4 <?=$lang['user']['weeks'];?></option>
				</select>
				<button type="submit" id="trigger_set_time_limited_aliases" name="trigger_set_time_limited_aliases" value="generate" class="btn btn-success"><?=$lang['user']['alias_create_random'];?></button>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-12">
				<button style="border-color:#f5f5f5;background:none;color:red" type="submit" name="trigger_set_time_limited_aliases" value="delete" class="btn btn-sm">
					<span class="glyphicon glyphicon-remove" aria-hidden="true"></span> <?=$lang['user']['alias_remove_all'];?>
				</button>
				<button style="border-color:#f5f5f5;background:none;color:grey" type="submit" name="trigger_set_time_limited_aliases" value="extend" class="btn btn-sm">
					<span class="glyphicon glyphicon-hourglass" aria-hidden="true"></span> <?=$lang['user']['alias_extend_all'];?>
				</button>
			</div>
		</div>
		</form>
	</div>
	<div role="tabpanel" class="tab-pane" id="Spamfilter">
		<h4><?=$lang['user']['spamfilter_behavior'];?></h4>
		<form class="form-horizontal" role="form" method="post">
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<input name="score" id="score" type="text" 
						data-provide="slider"
						data-slider-min="1"
						data-slider-max="30"
						data-slider-step="0.5"
						data-slider-range="true"
						data-slider-tooltip='always'
						data-slider-id="slider1"
						data-slider-value="[<?=get_spam_score($_SESSION['mailcow_cc_username']);?>]"
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
					<button type="submit" id="trigger_set_spam_score" name="trigger_set_spam_score" class="btn btn-success"><?=$lang['user']['save_changes'];?></button>
				</div>
			</div>
		</form>
		<hr>
		<div class="row">
			<div class="col-sm-6">
				<h4><span class="glyphicon glyphicon-thumbs-up" aria-hidden="true"></span> <?=$lang['user']['spamfilter_wl'];?></h4>
				<p><?=$lang['user']['spamfilter_wl_desc'];?></p>
				<div class="row">
					<div class="col-sm-6"><b><?=$lang['user']['spamfilter_table_rule'];?></b></div>
					<div class="col-sm-6"><b><?=$lang['user']['spamfilter_table_action'];?></b></div>
				</div>
				<?php
				try {
					$stmt = $pdo->prepare("SELECT `value`, `prefid` FROM `filterconf` WHERE `option`='whitelist_from' AND `object`= :username");
					$stmt->execute(array(':username' => $username));
					$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				}
				catch(PDOException $e) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => 'MySQL: '.$e
					);
				}
				while ($whitelistRow = array_shift($rows)):
				?>
				<div class="row striped">
					<form class="form-inline" method="post">
					<div class="col-xs-6"><code><?=$whitelistRow['value'];?></code></div>
					<div class="col-xs-6">
						<input type="hidden" name="prefid" value="<?=$whitelistRow['prefid'];?>">
						<?php
						if ($whitelistRow['username'] != array_pop(explode('@', $username))):
						?>
							<input type="hidden" name="trigger_set_policy_list">
							<a href="#n" onclick="$(this).closest('form').submit()"><?=$lang['user']['spamfilter_table_remove'];?></a>
						<?php
						else:
						?>
							<span style="cursor:not-allowed"><?=$lang['user']['spamfilter_table_domain_policy'];?></span>
						<?php
						endif;
						?>
					</div>
					</form>
				</div>

				<?php
				endwhile;
				?>
				<hr style="margin:5px 0px 7px 0px">
				<div class="row">
					<form class="form-inline" method="post">
					<div class="col-xs-6">
						<input type="text" class="form-control input-sm" name="object_from" id="object_from" placeholder="*@example.org" required>
						<input type="hidden" name="object_list" value="wl">
					</div>
					<div class="col-xs-6">
						<button type="submit" id="trigger_set_policy_list" name="trigger_set_policy_list" class="btn btn-xs btn-default"><?=$lang['user']['spamfilter_table_add'];?></button>
					</div>
					</form>
				</div>
			</div>
			<div class="col-sm-6">
				<h4><span class="glyphicon glyphicon-thumbs-down" aria-hidden="true"></span> <?=$lang['user']['spamfilter_bl'];?></h4>
				<p><?=$lang['user']['spamfilter_bl_desc'];?></p>
				<div class="row">
					<div class="col-sm-6"><b><?=$lang['user']['spamfilter_table_rule'];?></b></div>
					<div class="col-sm-6"><b><?=$lang['user']['spamfilter_table_action'];?></b></div>
				</div>
				<?php
				try {
					$stmt = $pdo->prepare("SELECT `value`, `prefid` FROM `filterconf` WHERE `option`='blacklist_from' AND `object`= :username");
					$stmt->execute(array(':username' => $username));
					$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				}
				catch(PDOException $e) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => 'MySQL: '.$e
					);
				}
				if (count($rows) == 0):
				?>
					<div class="row">
						<div class="col-sm-12"><i><?=$lang['user']['spamfilter_table_empty'];?></i></div>
					</div>
				<?php
				endif;
				while ($blacklistRow = array_shift($rows)):
				?>
				<div class="row striped">
					<form class="form-inline" method="post">
					<div class="col-xs-6"><code><?=$blacklistRow['value'];?></code></div>
					<div class="col-xs-6">
						<input type="hidden" name="prefid" value="<?=$blacklistRow['prefid'];?>">
						<?php
						if ($blacklistRow['username'] != array_pop(explode('@', $username))):
						?>
							<input type="hidden" name="trigger_set_policy_list">
							<a href="#n" onclick="$(this).closest('form').submit()"><?=$lang['user']['spamfilter_table_remove'];?></a>
						<?php
						else:
						?>
							<span style="cursor:not-allowed"><?=$lang['user']['spamfilter_table_domain_policy'];?></span>
						<?php
						endif;
						?>
					</div>
					</form>
				</div>
				<?php
				endwhile;
				?>
				<hr style="margin:5px 0px 7px 0px">
				<div class="row">
					<form class="form-inline" method="post">
					<div class="col-xs-6">
						<input type="text" class="form-control input-sm" name="object_from" id="object_from" placeholder="*@example.org" required>
						<input type="hidden" name="object_list" value="bl">
					</div>
					<div class="col-xs-6">
						<button type="submit" id="trigger_set_policy_list" name="trigger_set_policy_list" class="btn btn-xs btn-default"><?=$lang['user']['spamfilter_table_add'];?></button>
					</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div role="tabpanel" class="tab-pane" id="TLSPolicy">
		<form class="form-horizontal" role="form" method="post">
			<p class="help-block"><?=$lang['user']['tls_policy_warning'];?></p>
			<div class="form-group">
				<div class="col-sm-6">
					<div class="checkbox">
						<h4><span class="glyphicon glyphicon-download" aria-hidden="true"></span> <?=$lang['user']['tls_enforce_in'];?></h4>
						<input type="checkbox" id="tls_in" name="tls_in" <?=($get_tls_policy['tls_enforce_in'] == "1") ? "checked" : null;?> data-on-text="<?=$lang['user']['on'];?>" data-off-text="<?=$lang['user']['off'];?>">
					</div>
				</div>
				<div class="col-sm-6">
					<div class="checkbox">
						<h4><span class="glyphicon glyphicon-upload" aria-hidden="true"></span> <?=$lang['user']['tls_enforce_out'];?></h4>
						<input type="checkbox" id="tls_out" name="tls_out" <?=($get_tls_policy['tls_enforce_out'] == "1") ? "checked" : null;?> data-on-text="<?=$lang['user']['on'];?>" data-off-text="<?=$lang['user']['off'];?>">
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-12">
					<button type="submit" id="trigger_set_tls_policy" name="trigger_set_tls_policy" class="btn btn-default"><?=$lang['user']['save_changes'];?></button>
				</div>
			</div>
		</form>
	</div>
</div>


</div> <!-- /container -->
<script src="js/sorttable.js"></script>
<script src="js/user.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
