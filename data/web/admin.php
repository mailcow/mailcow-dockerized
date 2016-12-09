<?php
require_once("inc/prerequisites.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
<h4><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?=$lang['admin']['access'];?></h4>

<div class="panel-group" id="accordion_access">
	<div class="panel panel-danger">
		<div class="panel-heading"><?=$lang['admin']['admin_details'];?></div>
		<div class="panel-body">
			<form class="form-horizontal" autocapitalize="none" autocorrect="off" role="form" method="post">
			<?php
			try {
			$stmt = $pdo->prepare("SELECT `username` FROM `admin`
				WHERE `superadmin`='1' and active='1'");
			$stmt->execute();
			$AdminData = $stmt->fetch(PDO::FETCH_ASSOC);
			}
			catch(PDOException $e) {
				$_SESSION['return'] = array(
					'type' => 'danger',
					'msg' => 'MySQL: '.$e
				);
			}
			?>
				<input type="hidden" name="admin_user_now" value="<?=htmlspecialchars($AdminData['username']);?>">
				<div class="form-group">
					<label class="control-label col-sm-2" for="admin_user"><?=$lang['admin']['admin'];?>:</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" name="admin_user" id="admin_user" value="<?=htmlspecialchars($AdminData['username']);?>" required>
						&rdsh; <kbd>a-z A-Z - _ .</kbd>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-sm-2" for="admin_pass"><?=$lang['admin']['password'];?>:</label>
					<div class="col-sm-10">
					<input type="password" class="form-control" name="admin_pass" id="admin_pass" placeholder="<?=$lang['admin']['unchanged_if_empty'];?>">
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-sm-2" for="admin_pass2"><?=$lang['admin']['password_repeat'];?>:</label>
					<div class="col-sm-10">
					<input type="password" class="form-control" name="admin_pass2" id="admin_pass2">
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-offset-2 col-sm-10">
						<button type="submit" name="trigger_set_admin" class="btn btn-default"><?=$lang['admin']['save'];?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
	<div class="panel panel-default">
	<div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_access" data-target="#collapseDomAdmins">
		<span class="accordion-toggle"><?=$lang['admin']['domain_admins'];?></span>
	</div>
		<div id="collapseDomAdmins" class="panel-collapse collapse">
			<div class="panel-body">
				<form method="post">
					<div class="table-responsive">
					<table class="table table-striped sortable-theme-bootstrap" data-sortable id="domainadminstable">
						<thead>
						<tr>
							<th class="sort-table" style="min-width: 100px;"><?=$lang['admin']['username'];?></th>
							<th class="sort-table" style="min-width: 166px;"><?=$lang['admin']['admin_domains'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['admin']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['admin']['action'];?></th>
						</tr>
						</thead>
						<tbody>
							<?php
							try {
								$stmt = $pdo->query("SELECT DISTINCT
									`username`, 
									CASE WHEN `active`='1' THEN '".$lang['admin']['yes']."' ELSE '".$lang['admin']['no']."' END AS `active`
										FROM `domain_admins` 
											WHERE `username` IN (
												SELECT `username` FROM `admin`
													WHERE `superadmin`!='1'
											)");
								$rows_username = $stmt->fetchAll(PDO::FETCH_ASSOC);
							}
							catch(PDOException $e) {
								$_SESSION['return'] = array(
									'type' => 'danger',
									'msg' => 'MySQL: '.$e
								);
							}
							if(!empty($rows_username)):
							while ($row_user_state = array_shift($rows_username)):
							?>
							<tr id="data">
								<td><?=htmlspecialchars(strtolower($row_user_state['username']));?></td>
								<td>
								<?php
								try {
									$stmt = $pdo->prepare("SELECT `domain` FROM `domain_admins` WHERE `username` = :username");
									$stmt->execute(array('username' => $row_user_state['username']));
									$rows_domain = $stmt->fetchAll(PDO::FETCH_ASSOC);
								}
								catch(PDOException $e) {
									$_SESSION['return'] = array(
										'type' => 'danger',
										'msg' => 'MySQL: '.$e
									);
								}
								while ($row_domain = array_shift($rows_domain)) {
									echo htmlspecialchars($row_domain['domain']).'<br />';
								}
								?>
								</td>
								<td><?=$row_user_state['active'];?></td>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="edit.php?domainadmin=<?=$row_user_state['username'];?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['admin']['edit'];?></a>
										<a href="delete.php?domainadmin=<?=$row_user_state['username'];?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['admin']['remove'];?></a>
									</div>
								</td>
								</td>
							</tr>

							<?php
							endwhile;
							else:
							?>
								<tr id="no-data"><td colspan="4" style="text-align: center; font-style: italic;"><?=$lang['admin']['no_record'];?></td></tr>
							<?php
							endif;
							?>
						</tbody>
					</table>
					</div>
				</form>
				<small>
				<legend><?=$lang['admin']['add_domain_admin'];?></legend>
				<form class="form-horizontal" role="form" method="post">
					<div class="form-group">
						<label class="control-label col-sm-2" for="username"><?=$lang['admin']['username'];?>:</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="username" id="username" required>
							&rdsh; <kbd>a-z A-Z - _ .</kbd>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="name"><?=$lang['admin']['admin_domains'];?>:</label>
						<div class="col-sm-10">
							<select title="<?=$lang['admin']['search_domain_da'];?>" style="width:100%" name="domain[]" size="5" multiple>
							<?php
							try {
								$stmt = $pdo->query("SELECT domain FROM domain");
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
						<label class="control-label col-sm-2" for="password"><?=$lang['admin']['password'];?>:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password" id="password" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password2"><?=$lang['admin']['password_repeat'];?>:</label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password2" id="password2" placeholder="">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" name="active" checked> <?=$lang['admin']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" name="trigger_add_domain_admin" class="btn btn-default"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
				</small>
			</div>
		</div>
	</div>
</div>

<h4><span class="glyphicon glyphicon-wrench" aria-hidden="true"></span> <?=$lang['admin']['configuration'];?></h4>
<div class="panel panel-default">
<div class="panel-heading"><?=$lang['admin']['dkim_keys'];?></div>
<div id="collapseDKIM" class="panel-collapse">
<div class="panel-body">
	<?php
	$dnstxt_folder	= scandir($GLOBALS["MC_DKIM_TXTS"]);
	$dnstxt_files	= array_diff($dnstxt_folder, array('.', '..', '.dkim_pub_keys'));
	foreach($dnstxt_files as $file) {
		$str = file_get_contents($GLOBALS["MC_DKIM_TXTS"]."/".$file);
		$str = preg_replace('/\r|\t|\n/', '', $str);
		preg_match('/\(.*\)/im', $str, $matches);
		$domain = explode("_", $file)[1];
		$selector = explode("_", $file)[0];
		if(isset($matches[0])) {
			$str = str_replace(array(' ', '"', '(', ')'), '', $matches[0]);
		}
	?>
		<div class="row">
			<div class="col-xs-2">
				<p>Domain: <strong><?=htmlspecialchars($domain);?></strong> (<?=htmlspecialchars($selector);?>._domainkey)</p>
			</div>
			<div class="col-xs-9">
				<pre>v=DKIM1;k=rsa;t=s;s=email;p=<?=$str;?></pre>
			</div>
			<div class="col-xs-1">
				<form class="form-inline" role="form" method="post">
				<a href="#" onclick="$(this).closest('form').submit()"><span class="glyphicon glyphicon-remove-circle"></span></a>
				<input type="hidden" name="delete_dkim_record" value="<?=htmlspecialchars($file);?>">
                <input type="hidden" name="dkim[domain]" value="<?=$domain;?>">
                <input type="hidden" name="dkim[selector]" value="<?=$selector;?>">
				</form>
			</div>
		</div>
	<?php
	}
	?>
	<legend><?=$lang['admin']['dkim_add_key'];?></legend>
	<form class="form-inline" role="form" method="post">
		<div class="form-group">
			<label for="dkim_domain">Domain</label>
			<input class="form-control" id="dkim_domain" name="dkim[domain]" placeholder="example.org" required>
		</div>
		<div class="form-group">
			<label for="dkim_selector">Selector</label>
			<input class="form-control" id="dkim_selector" name="dkim[selector]" value="default" required>
		</div>
		<div class="form-group">
			<select class="form-control" id="dkim_key_size" name="dkim[key_size]" title="<?=$lang['admin']['dkim_key_length'];?>" required>
				<option>1024</option>
				<option>2048</option>
			</select>
		</div>
		<button type="submit" name="add_dkim_record" class="btn btn-default"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
	</form>
</div>
</div>
</div>

</div> <!-- /container -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js" integrity="sha384-YWP9O4NjmcGo4oEJFXvvYSEzuHIvey+LbXkBNJ1Kd0yfugEZN9NCQNpRYBVC1RvA" crossorigin="anonymous"></script>
<script src="js/sorttable.js"></script>
<script src="js/admin.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
