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
					<h3 class="panel-title"><?=$lang['edit']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
		if (isset($_GET["alias"]) &&
			!empty($_GET["alias"])) {
				$alias = $_GET["alias"];
				$domain = substr(strrchr($alias, "@"), 1);
				try {
					$stmt = $pdo->prepare("SELECT * FROM `alias`
						WHERE `address`= :address 
						AND `goto` != :goto
						AND (
							`domain` IN (
								SELECT `domain` FROM `domain_admins`
									WHERE `active`='1'
									AND `username`= :username
							)
							OR 'admin'= :admin
						)");
					$stmt->execute(array(
						':address' => $alias,
						':goto' => $alias,
						':username' => $_SESSION['mailcow_cc_username'],
						':admin' => $_SESSION['mailcow_cc_role']
					));
					$result = $stmt->fetch(PDO::FETCH_ASSOC);
				}
				catch(PDOException $e) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => 'MySQL: '.$e
					);
				}
				if ($result !== false) {
				?>
					<h4><?=$lang['edit']['alias'];?></h4>
					<br />
					<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
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
								<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
								</div>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<button type="submit" name="trigger_mailbox_action" value="editalias" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
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
				try {
					$stmt = $pdo->prepare("SELECT * FROM `domain_admins` WHERE `username`= :domain_admin");
					$stmt->execute(array(
						':domain_admin' => $domain_admin
					));
					$result = $stmt->fetch(PDO::FETCH_ASSOC);
				}
				catch(PDOException $e) {
					$_SESSION['return'] = array(
						'type' => 'danger',
						'msg' => 'MySQL: '.$e
					);
				}
				if ($result !== false) {
				?>
				<h4><?=$lang['edit']['domain_admin'];?></h4>
				<br />
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
				<input type="hidden" name="username" value="<?=htmlspecialchars($domain_admin);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['edit']['domains'];?></label>
						<div class="col-sm-10">
							<select id="domain" name="domain[]" multiple>
							<?php
							try {
								$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
									WHERE `domain` IN (
										SELECT `domain` FROM `domain_admins`
											WHERE `username`= :domain_admin)");
								$stmt->execute(array(':domain_admin' => $domain_admin));
								$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
							}
							catch(PDOException $e) {
								$_SESSION['return'] = array(
									'type' => 'danger',
									'msg' => 'MySQL: '.$e
								);
							}
							while ($row_selected = array_shift($rows)):
							?>
								<option selected><?=htmlspecialchars($row_selected['domain']);?></option>
							<?php
							endwhile;
							try {
								$stmt = $pdo->prepare("SELECT `domain` FROM `domain`
									WHERE `domain` NOT IN (
										SELECT `domain` FROM `domain_admins`
											WHERE `username`= :domain_admin)");
								$stmt->execute(array(':domain_admin' => $domain_admin));
								$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
							}
							catch(PDOException $e) {
								$_SESSION['return'] = array(
									'type' => 'danger',
									'msg' => 'MySQL: '.$e
								);
							}
							while ($row_unselected = array_shift($rows)):
							?>
								<option><?=htmlspecialchars($row_unselected['domain']);?></option>
							<?php
							endwhile;
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
							<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_edit_domain_admin" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
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
			try {
				$stmt = $pdo->prepare("SELECT * FROM `domain` WHERE `domain`='".$domain."'
				AND (
					`domain` IN (
						SELECT `domain` from `domain_admins`
							WHERE `active`='1'
							AND `username` = :username
					)
					OR 'admin'= :admin
				)");
				$stmt->execute(array(
					':username' => $_SESSION['mailcow_cc_username'],
					':admin' => $_SESSION['mailcow_cc_role']
				));
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
			}
			if ($result !== false) {
			?>
				<h4><?=$lang['edit']['domain'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
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
							<input type="number" class="form-control" name="aliases" id="aliases" value="<?=intval($result['aliases']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes"><?=$lang['edit']['max_mailboxes'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="<?=intval($result['mailboxes']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota"><?=$lang['edit']['max_quota'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="maxquota" id="maxquota" value="<?=intval($result['maxquota']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['edit']['domain_quota'];?></label>
						<div class="col-sm-10">
							<input type="number" class="form-control" name="quota" id="quota" value="<?=intval($result['quota']);?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2"><?=$lang['edit']['backup_mx_options'];?></label>
						<div class="col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" name="backupmx" <?php if (isset($result['backupmx']) && $result['backupmx']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['relay_domain'];?></label>
								<br />
								<label><input type="checkbox" name="relay_all_recipients" <?php if (isset($result['relay_all_recipients']) && $result['relay_all_recipients']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['relay_all'];?></label>
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
								<label><input type="checkbox" name="active" <?php if (isset($result['active']) && $result['active']=="1") { echo "checked "; }; if ($_SESSION['mailcow_cc_role']=="domainadmin") { echo "disabled"; }; ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editdomain" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
				<?php
				$dnstxt_folder	= scandir($GLOBALS["MC_DKIM_TXTS"]);
				$dnstxt_files	= array_diff($dnstxt_folder, array('.', '..', '.dkim_pub_keys'));
				foreach($dnstxt_files as $file) {
					if (explode("_", $file)[1] == $domain) {
						$str = file_get_contents($GLOBALS["MC_DKIM_TXTS"]."/".$file);
						$str = preg_replace('/\r|\t|\n/', '', $str);
						preg_match('/\(.*\)/im', $str, $matches);
						if(isset($matches[0])) {
							$str = str_replace(array(' ', '"', '(', ')'), '', $matches[0]);
						}
				?>
						<div class="row">
							<div class="col-xs-2">
								<p class="text-right"><?=$lang['edit']['dkim_signature'];?></p>
							</div>
							<div class="col-xs-10">
								<div class="col-md-2"><b><?=$lang['edit']['dkim_txt_name'];?></b></div>
								<div class="col-md-10">
									<pre><?=htmlspecialchars(explode("_", $file)[0]);?>._domainkey</pre>
								</div>
								<div class="col-md-2"><b><?=$lang['edit']['dkim_txt_value'];?></b></div>
								<div class="col-md-10">
									<pre>v=DKIM1;k=rsa;t=s;s=email;p=<?=htmlspecialchars($str);?></pre>
									<?=$lang['edit']['dkim_record_info'];?>
								</div>
							</div>
						</div>
				<?php
					}
				}
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
			try {
				$stmt = $pdo->prepare("SELECT * FROM `alias_domain`
					WHERE `alias_domain`= :alias_domain 
					AND (
						`target_domain` IN (
							SELECT `domain` FROM `domain_admins`
								WHERE `active`='1'
								AND `username`= :username
						)
						OR 'admin'= :admin
					)");
				$stmt->execute(array(
					':alias_domain' => $alias_domain,
					':username' => $_SESSION['mailcow_cc_username'],
					':admin' => $_SESSION['mailcow_cc_role']
				));
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
			}
			if ($result !== false) {
			?>
				<h4><?=$lang['edit']['edit_alias_domain'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
					<input type="hidden" name="alias_domain_now" value="<?=htmlspecialchars($alias_domain);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="alias_domain"><?=$lang['edit']['alias_domain'];?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="alias_domain" id="alias_domain" value="<?=htmlspecialchars($result['alias_domain']);?>">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" name="active" <?= (isset($result['active']) && $result['active']=="1") ?  "checked" : null ?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editaliasdomain" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
						</div>
					</div>
				</form>
				<?php
				$dnstxt_folder = scandir($GLOBALS["MC_DKIM_TXTS"]);
				$dnstxt_files = array_diff($dnstxt_folder, array('.', '..'));
				foreach($dnstxt_files as $file) {
					if (explode("_", $file)[1] == $domain) {
						$str = file_get_contents($GLOBALS["MC_DKIM_TXTS"]."/".$file);
						$str = preg_replace('/\r|\t|\n/', '', $str);
						preg_match('/\(.*\)/im', $str, $matches);
						if(isset($matches[0])) {
							$str = str_replace(array(' ', '"', '(', ')'), '', $matches[0]);
						}
				?>
						<div class="row">
							<div class="col-xs-2">
								<p class="text-right"><?=$lang['edit']['dkim_signature'];?></p>
							</div>
							<div class="col-xs-10">
								<div class="col-md-2"><b><?=$lang['edit']['dkim_txt_name'];?></b></div>
								<div class="col-md-10">
									<pre><?=htmlspecialchars(explode("_", $file)[0]);?>._domainkey</pre>
								</div>
								<div class="col-md-2"><b><?=$lang['edit']['dkim_txt_value'];?></b></div>
								<div class="col-md-10">
									<pre><?=htmlspecialchars($str);?></pre>
									<?=$lang['edit']['dkim_record_info'];?>
								</div>
							</div>
						</div>
				<?php
					}
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
			try {
				$stmt = $pdo->prepare("SELECT `username`, `domain`, `name`, `quota`, `active` FROM `mailbox` WHERE `username` = :username1");
				$stmt->execute(array(
					':username1' => $mailbox,
				));
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
			}
			if ($result !== false && hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $result['domain'])) {
				$left_m = remaining_specs($result['domain'], $_GET['mailbox'])['left_m'];
			?>
				<h4><?=$lang['edit']['mailbox'];?></h4>
				<form class="form-horizontal" role="form" method="post" action="<?=($FORM_ACTION == "previous") ? $_SESSION['return_to'] : null;?>">
				<input type="hidden" name="username" value="<?=htmlspecialchars($result['username']);?>">
					<div class="form-group">
						<label class="control-label col-sm-2" for="name"><?=$lang['edit']['full_name'];?>:</label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="name" id="name" value="<?=htmlspecialchars($result['name'], ENT_QUOTES, 'UTF-8');?>">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['edit']['quota_mb'];?>:
							<br /><span id="quotaBadge" class="badge">max. <?=intval($left_m)?> MiB</span>
						</label>
						<div class="col-sm-10">
							<input type="number" name="quota" id="quota" id="destroyable" style="width:100%" min="1" max="<?=intval($left_m);?>" value="<?=intval($result['quota']) / 1048576;?>" class="form-control">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="sender_acl"><?=$lang['edit']['sender_acl'];?>:</label>
						<div class="col-sm-10">
							<select style="width:100%" id="sender_acl" name="sender_acl[]" size="10" multiple>
							<?php
							$rows = get_sender_acl_handles($mailbox, "preselected");
							while ($row_goto_from_alias = array_shift($rows)):
							?>
								<option disabled selected><?=htmlspecialchars($row_goto_from_alias['address']);?></option>
							<?php
							endwhile;

							// All manual selected
							$rows = get_sender_acl_handles($mailbox, "selected");
							while ($row_selected_sender_acl = array_shift($rows)):
									if (!filter_var($row_selected_sender_acl['send_as'], FILTER_VALIDATE_EMAIL)):
									?>
										<option data-divider="true"></option>
											<option value="<?=htmlspecialchars($row_selected_sender_acl['send_as']);?>" selected><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], str_replace('@', '', $row_selected_sender_acl['send_as'])));?></option>
										<option data-divider="true"></option>
									<?php
									else:
									?>
										<option selected><?=htmlspecialchars($row_selected_sender_acl['send_as']);?></option>
									<?php
									endif;
							endwhile;
							
							// Unselected domains
							$rows = get_sender_acl_handles($mailbox, "unselected-domains");
							while ($row_unselected_sender_acl = array_shift($rows)):
							?>
								<option data-divider="true"></option>
									<option value="@<?=htmlspecialchars($row_unselected_sender_acl['domain']);?>"><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $row_unselected_sender_acl['domain']));?></option>
								<option data-divider="true"></option>
							<?php
							endwhile;

							// Unselected addresses
							$rows = get_sender_acl_handles($mailbox, "unselected-addresses");
							while ($row_unselected_sender_acl = array_shift($rows)):
							?>
								<option><?=htmlspecialchars($row_unselected_sender_acl['address']);?></option>
							<?php
							endwhile;
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
							<label><input type="checkbox" name="active" <?=($result['active']=="1") ? "checked" : "";?>> <?=$lang['edit']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_mailbox_action" value="editmailbox" class="btn btn-success btn-sm"><?=$lang['edit']['save'];?></button>
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
<?php
require_once("inc/footer.inc.php");
?>
