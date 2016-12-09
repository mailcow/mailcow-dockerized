<?php
require_once("inc/prerequisites.inc.php");
$AuthUsers = array("admin", "domainadmin");
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
					<h3 class="panel-title"><?=$lang['add']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
	if (isset($_GET['domain']) && $_SESSION['mailcow_cc_role'] == "admin") {
?>
				<h4><?=$lang['add']['domain'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['add']['domain'];?>:</label>
						<div class="col-sm-10">
						<input type="text" autocorrect="off" autocapitalize="none" class="form-control" name="domain" id="domain">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="description"><?=$lang['add']['description'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="description" id="description">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="aliases"><?=$lang['add']['max_aliases'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="aliases" id="aliases" value="400">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes"><?=$lang['add']['max_mailboxes'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="10">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota"><?=$lang['add']['mailbox_quota_m'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxquota" id="maxquota" value="3072">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['add']['domain_quota_m'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="10240">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2"><?=$lang['add']['backup_mx_options'];?></label>
						<div class="col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="backupmx"> <?=$lang['add']['relay_domain'];?></label>
							<br />
							<label><input type="checkbox" name="relay_all_recipients"> <?=$lang['add']['relay_all'];?></label>
							<p><?=$lang['add']['relay_all_info'];?></p>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="adddomain" class="btn btn-success"><?=$lang['add']['save'];?></button>
						</div>
					</div>
				</form>
<?php
	}
	elseif (isset($_GET['alias'])) {
?>
				<h4><?=$lang['add']['alias'];?></h4>
				<p><?=$lang['add']['alias_spf_fail'];?></p>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="address"><?=$lang['add']['alias_address'];?></label>
						<div class="col-sm-10">
							<textarea autocorrect="off" autocapitalize="none" class="form-control" rows="5" name="address" id="address"></textarea>
							<p><?=$lang['add']['alias_address_info'];?></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="goto"><?=$lang['add']['target_address'];?></label>
						<div class="col-sm-10">
							<textarea autocorrect="off" autocapitalize="none" class="form-control" rows="5" id="goto" name="goto"></textarea>
							<p><?=$lang['add']['target_address_info'];?></p>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="addalias" class="btn btn-success "><?=$lang['add']['save'];?></button>
						</div>
					</div>
				</form>
	<?php
	}
	elseif (isset($_GET['aliasdomain'])) {
	?>
				<h4><?=$lang['add']['alias_domain'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="alias_domain"><?=$lang['add']['alias_domain'];?></label>
						<div class="col-sm-10">
							<textarea autocorrect="off" autocapitalize="none" class="form-control" rows="5" name="alias_domain" id="alias_domain"></textarea>
							<p><?=$lang['add']['alias_domain_info'];?></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="target_domain"><?=$lang['add']['target_domain'];?></label>
						<div class="col-sm-10">
							<select name="target_domain" id="target_domain" title="<?=$lang['add']['select'];?>">
								<?php
								try {
									$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
											WHERE `domain` IN (
													SELECT `domain` FROM `domain_admins`
															WHERE `username`= :username
															AND `active`='1'
													)
											OR 'admin' = :admin");
									$stmt->execute(array(':username' => $_SESSION['mailcow_cc_username'], ':admin' => $_SESSION['mailcow_cc_role']));
									$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
								}
								catch(PDOException $e) {
									$_SESSION['return'] = array(
										'type' => 'danger',
										'msg' => 'MySQL: '.$e
									);
								}
								while ($row = array_shift($rows)) {
										echo "<option>".htmlspecialchars($row['domain'])."</option>";
								}
								?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="addaliasdomain" class="btn btn-success "><?=$lang['add']['save'];?></button>
						</div>
					</div>
				</form>
	<?php
	}
	elseif (isset($_GET['mailbox'])) {
	?>
				<h4><?=$lang['add']['mailbox'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="local_part"><?=$lang['add']['mailbox_username'];?></label>
						<div class="col-sm-10">
							<input type="text" pattern="[A-Za-z0-9\.!#$%&'*+/=?^_`{|}~-]+" autocorrect="off" autocapitalize="none" class="form-control" name="local_part" id="local_part" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['add']['domain'];?>:</label>
						<div class="col-sm-10">
							<select id="addSelectDomain" name="domain" id="domain" title="<?=$lang['add']['select'];?>" required>
							<?php
							try {
								$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
										WHERE `domain` IN (
											SELECT `domain` FROM `domain_admins`
													WHERE `username`= :username
													AND `active`='1'
											)
											OR 'admin' = :admin");
								$stmt->execute(array(':username' => $_SESSION['mailcow_cc_username'], ':admin' => $_SESSION['mailcow_cc_role']));
								$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
							}
							catch(PDOException $e) {
								$_SESSION['return'] = array(
									'type' => 'danger',
									'msg' => 'MySQL: '.$e
								);
							}
							while ($row = array_shift($rows)) {
								echo "<option>".htmlspecialchars($row['domain'])."</option>";
							}
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="name"><?=$lang['add']['full_name'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="name" id="name">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="addInputQuota"><?=$lang['add']['quota_mb'];?>
							<br /><span id="quotaBadge" class="badge">max. - MiB</span>
						</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="quota" min="1" max="" id="addInputQuota" disabled value="<?=$lang['add']['select_domain'];?>" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password"><?=$lang['add']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2"><?=$lang['add']['password_repeat'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="addmailbox" class="btn btn-success "><?=$lang['add']['save'];?></button>
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
				<div class="alert alert-danger" role="alert"><?=$lang['danger']['access_denied'];?></div>
<?php
}
?>
				</div>
			</div>
		</div>
	</div>
<a href="<?=$_SESSION['return_to'];?>">&#8592; <?=$lang['add']['previous'];?></a>
</div> <!-- /container -->
<script src="js/add.js"></script>
<?php
require_once("inc/footer.inc.php");
?>
