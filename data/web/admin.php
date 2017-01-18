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
              foreach (get_domain_admins() as $domain_admin) {
                $da_data = get_domain_admin_details($domain_admin); 
                if (!empty($da_data)):
							?>
							<tr id="data">
								<td><?=htmlspecialchars(strtolower($domain_admin));?></td>
								<td>
								<?php
								foreach ($da_data['selected_domains'] as $domain) {
									echo htmlspecialchars($domain).'<br />';
								}
								?>
								</td>
								<td><?=$da_data['active'];?></td>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="edit.php?domainadmin=<?=$domain_admin;?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['admin']['edit'];?></a>
										<a href="delete.php?domainadmin=<?=$domain_admin;?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['admin']['remove'];?></a>
									</div>
								</td>
								</td>
							</tr>

							<?php
							else:
							?>
								<tr id="no-data"><td colspan="4" style="text-align: center; font-style: italic;"><?=$lang['admin']['no_record'];?></td></tr>
							<?php
							endif;
              }
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
  <p style="margin-bottom:40px"><?=$lang['admin']['dkim_key_hint'];?></p>
	<?php
	foreach(mailbox_get_domains() as $domain) {
    if ($pubkey = dkim_table('get', $domain)) {
    ?>
      <div class="row">
        <div class="col-xs-3">
          <p>Domain: <strong><?=htmlspecialchars($domain);?></strong><br /><span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span></p>
        </div>
        <div class="col-xs-8">
          <pre><?=$pubkey;?></pre>
        </div>
        <div class="col-xs-1">
          <form class="form-inline" method="post">
            <input type="hidden" name="dkim[domain]" value="<?=$domain;?>">
            <input type="hidden" name="delete_dkim_record" value="1">
            <a href="#" onclick="$(this).closest('form').submit()"><span class="glyphicon glyphicon-remove-circle"></span></a>
          </form>
        </div>
      </div>
    <?php
    }
    foreach(mailbox_get_alias_domains($domain) as $alias_domain) {
      if ($pubkey = dkim_table('get', $alias_domain)) {
      ?>
        <div class="row">
          <div class="col-xs-offset-1 col-xs-2">
            <p><small>↳ Alias-Domain: <strong><?=htmlspecialchars($alias_domain);?></strong><br /></small><span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span></p>
          </div>
          <div class="col-xs-8">
            <pre><?=$pubkey;?></pre>
          </div>
          <div class="col-xs-1">
            <form class="form-inline" method="post">
              <input type="hidden" name="dkim[domain]" value="<?=$alias_domain;?>">
              <input type="hidden" name="delete_dkim_record" value="1">
              <a href="#" onclick="$(this).closest('form').submit()"><span class="glyphicon glyphicon-remove-circle"></span></a>
            </form>
          </div>
        </div>
      <?php
      }
    }
	}
  ?><hr><?php
  foreach(dkim_table('keys-without-domain', null) as $key_wo_domain) {
    if ($pubkey = dkim_table('get', $key_wo_domain)) {
    ?>
      <div class="row">
        <div class="col-xs-3">
          <p>Domain: <strong><?=htmlspecialchars($key_wo_domain);?></strong><br /><span class="label label-warning"><?=$lang['admin']['dkim_key_unused'];?></span></p>
        </div>
          <div class="col-xs-8">
            <pre><?=$pubkey;?></pre>
          </div>
          <div class="col-xs-1">
            <form class="form-inline" method="post">
              <input type="hidden" name="dkim[domain]" value="<?=$key_wo_domain;?>">
              <input type="hidden" name="delete_dkim_record" value="1">
              <a href="#" onclick="$(this).closest('form').submit()"><span class="glyphicon glyphicon-remove-circle"></span></a>
            </form>
          </div>
      </div>
    <?php
    }
  }
  ?><hr><?php
  foreach(dkim_table('domains-without-key', null) as $domain_wo_key) {
  ?>
    <div class="row">
      <div class="col-xs-12">
        <p>(Alias-)Domain: <strong><?=htmlspecialchars($domain_wo_key);?></strong><br /><span class="label label-danger"><?=$lang['admin']['dkim_key_missing'];?></span></p>
      </div>
    </div>
  <?php
  }
	?>
	<legend style="margin-top:40px"><?=$lang['admin']['dkim_add_key'];?></legend>
	<form class="form-inline" role="form" method="post">
		<div class="form-group">
			<label for="dkim_domain">Domain</label>
			<input class="form-control" id="dkim_domain" name="dkim[domain]" placeholder="example.org" required>
		</div>
		<div class="form-group">
			<select data-width="200px" class="form-control" id="dkim_key_size" name="dkim[key_size]" title="<?=$lang['admin']['dkim_key_length'];?>" required>
				<option data-subtext="bits">1024</option>
				<option data-subtext="bits">2048</option>
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
