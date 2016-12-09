<?php
require_once "inc/prerequisites.inc.php";

if ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin") {
require_once "inc/header.inc.php";
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
				<h3 class="panel-title"><?=$lang['mailbox']['domains'];?> <span class="badge" id="numRowsDomain"></span></h3>
				<div class="pull-right">
					<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
						<i class="glyphicon glyphicon-filter"></i>
					</span>
				<?php
				if ($_SESSION['mailcow_cc_role'] == "admin"):
				?>
					<a href="/add.php?domain"><span class="glyphicon glyphicon-plus"></span></a>
				<?php
				endif;
				?>
				</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="domaintable-filter" data-action="filter" data-filters="#domaintable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped sortable-theme-bootstrap" data-sortable id="domaintable">
					<thead>
						<tr>
							<th class="sort-table" style="min-width: 86px;"><?=$lang['mailbox']['domain'];?></th>
							<th class="sort-table" style="min-width: 81px;"><?=$lang['mailbox']['aliases'];?></th>
							<th class="sort-table" style="min-width: 99px;"><?=$lang['mailbox']['mailboxes'];?></th>
							<th class="sort-table" style="min-width: 172px;"><?=$lang['mailbox']['mailbox_quota'];?></th>
							<th class="sort-table" style="min-width: 117px;"><?=$lang['mailbox']['domain_quota'];?></th>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<th class="sort-table" style="min-width: 105px;"><?=$lang['mailbox']['backup_mx'];?></th>
							<?php
							endif;
							?>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['mailbox']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					try {
						$stmt = $pdo->prepare("SELECT 
								`domain`,
								`aliases`,
								`mailboxes`, 
								`maxquota` * 1048576 AS `maxquota`,
								`quota` * 1048576 AS `quota`,
								CASE `backupmx` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `backupmx`,
								CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
									FROM `domain` WHERE
										`domain` IN (
											SELECT `domain` FROM `domain_admins` WHERE `username`= :username AND `active`='1'
										)
										OR 'admin'= :admin");
						$stmt->execute(array(
							':username' => $_SESSION['mailcow_cc_username'],
							':admin' => $_SESSION['mailcow_cc_role'],
						));
						$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
					}
					catch (PDOException $e) {
						$_SESSION['return'] = array(
							'type' => 'danger',
							'msg' => 'MySQL: '.$e
						);
						return false;
					}
	        if(!empty($rows)):
					while($row = array_shift($rows)):
						try {
							$stmt = $pdo->prepare("SELECT COUNT(*) AS `count` FROM `alias`
								WHERE `domain`= :domain
								AND `address` NOT IN (
									SELECT `username` FROM `mailbox`)");
							$stmt->execute(array(':domain' => $row['domain']));
							$AliasData = $stmt->fetch(PDO::FETCH_ASSOC);
			
							$stmt = $pdo->prepare("SELECT 
								COUNT(*) AS `count`,
								COALESCE(SUM(`quota`), '0') AS `quota`
									FROM `mailbox`
										WHERE `domain` = :domain");
							$stmt->execute(array(':domain' => $row['domain']));
							$MailboxData = $stmt->fetch(PDO::FETCH_ASSOC);
						}
						catch (PDOException $e) {
							$_SESSION['return'] = array(
								'type' => 'danger',
								'msg' => 'MySQL: '.$e
							);
							return false;
						}
					?>
						<tr id="data">
							<td><?=htmlspecialchars($row['domain']);?></td>
							<td><?=intval($AliasData['count']);?> / <?=intval($row['aliases']);?></td>
							<td><?=$MailboxData['count'];?> / <?=$row['mailboxes'];?></td>
							<td><?=formatBytes(intval($row['maxquota']), 2);?></td>
							<td><?=formatBytes(intval($MailboxData['quota']), 2);?> / <?=formatBytes(intval($row['quota']));?></td>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<td><?=$row['backupmx'];?></td>
							<?php
							endif;
							?>
							<td><?=$row['active'];?></td>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="/edit.php?domain=<?=urlencode($row['domain']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
										<a href="/delete.php?domain=<?=urlencode($row['domain']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
									</div>
								</td>
							<?php
							else:
							?>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="/edit.php?domain=<?=urlencode($row['domain']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									</div>
								</td>
						</tr>
							<?php
							endif;
							endwhile;
	            else:
							?>
							  <tr id="no-data"><td colspan="8" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['no_record'];?></td></tr>
							<?php
							endif;
							?>
					</tbody>
						<?php
						if ($_SESSION['mailcow_cc_role'] == "admin"):
						?>
					<tfoot>
						<tr id="no-data">
							<td colspan="8" style="text-align: center; font-style: normal; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?domain" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_domain'];?></a>
							</td>
						</tr>
					</tfoot>
						<?php
						endif;
						?>
				</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['domain_aliases'];?> <span class="badge" id="numRowsDomainAlias"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?aliasdomain"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="domainaliastable-filter" data-action="filter" data-filters="#domainaliastable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped sortable-theme-bootstrap" data-sortable id="domainaliastable">
					<thead>
						<tr>
							<th class="sort-table" style="min-width: 67px;"><?=$lang['mailbox']['alias'];?></th>
							<th class="sort-table" style="min-width: 127px;"><?=$lang['mailbox']['target_domain'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['mailbox']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					try {
						$stmt = $pdo->prepare("SELECT 
								`alias_domain`,
								`target_domain`,
								CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
									FROM `alias_domain`
										WHERE `target_domain` IN (
											SELECT `domain` FROM `domain_admins`
												WHERE `username`= :username 
												AND `active`='1'
										)
										OR 'admin' = :admin");
						$stmt->execute(array(
							':username' => $_SESSION['mailcow_cc_username'],
							':admin' => $_SESSION['mailcow_cc_role'],
						));
						$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
					} catch(PDOException $e) {
						$_SESSION['return'] = array(
							'type' => 'danger',
							'msg' => 'MySQL: '.$e
						);
					}
	        if(!empty($rows)):
					while($row = array_shift($rows)):
					?>
						<tr id="data">
							<td><?=htmlspecialchars($row['alias_domain']);?></td>
							<td><?=htmlspecialchars($row['target_domain']);?></td>
							<td><?=$row['active'];?></td>
							<td style="text-align: right;">
								<div class="btn-group">
									<a href="/edit.php?aliasdomain=<?=urlencode($row['alias_domain']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									<a href="/delete.php?aliasdomain=<?=urlencode($row['alias_domain']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
								</div>
							</td>
						</tr>
					<?php
					endwhile;
	        else:
	        ?>
						<tr id="no-data"><td colspan="4" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['no_record'];?></td></tr>
	        <?php
	        endif;
					?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="8" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?aliasdomain" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_domain_alias'];?></a>
							</td>
						</tr>
					</tfoot>
				</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['mailboxes'];?> <span class="badge" id="numRowsMailbox"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?mailbox"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="mailboxtable-filter" data-action="filter" data-filters="#mailboxtable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped sortable-theme-bootstrap" data-sortable id="mailboxtable">
					<thead>
						<tr>
							<th class="sort-table" style="min-width: 100px;"><?=$lang['mailbox']['username'];?></th>
							<th class="sort-table" style="min-width: 98px;"><?=$lang['mailbox']['fname'];?></th>
							<th class="sort-table" style="min-width: 86px;"><?=$lang['mailbox']['domain'];?></th>
							<th class="sort-table" style="min-width: 75px;"><?=$lang['mailbox']['quota'];?></th>
							<th class="sort-table" style="min-width: 99px;"><?=$lang['mailbox']['in_use'];?></th>
							<th class="sort-table" style="min-width: 100px;"><?=$lang['mailbox']['msg_num'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['mailbox']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						try {
							$stmt = $pdo->prepare("SELECT
									`domain`.`backupmx`,
									`mailbox`.`username`,
									`mailbox`.`name`,
									CASE `mailbox`.`active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`,
									`mailbox`.`domain`,
									`mailbox`.`quota`,
									`quota2`.`bytes`,
									`quota2`.`messages`
										FROM `mailbox`, `quota2`, `domain`
											WHERE (`mailbox`.`username` = `quota2`.`username`)
											AND (`domain`.`domain` = `mailbox`.`domain`)
											AND (`mailbox`.`domain` IN (
												SELECT `domain` FROM `domain_admins`
													WHERE `username`= :username
														AND `active`='1'
													)
													OR 'admin' = :admin)");
							$stmt->execute(array(
								':username' => $_SESSION['mailcow_cc_username'],
								':admin' => $_SESSION['mailcow_cc_role'],
							));
							$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
						}
						catch (PDOException $e) {
							$_SESSION['return'] = array(
								'type' => 'danger',
								'msg' => 'MySQL: '.$e
							);
							return false;
						}
	          if(!empty($rows)):
						while($row = array_shift($rows)):
						?>
						<tr id="data">
							<?php
							if ($row['backupmx'] == "0"):
							?>
								<td><?=htmlspecialchars($row['username']);?></td>
							<?php
							else:
							?>
								<td><span data-toggle="tooltip" title="Relayed"><i class="glyphicon glyphicon-forward"></i> <?=htmlspecialchars($row['username']);?></span></td>
							<?php
							endif;
							?>
							<td><?=htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');?></td>
							<td><?=htmlspecialchars($row['domain']);?></td>
							<td><?=formatBytes(intval($row['bytes']), 2);?> / <?=formatBytes(intval($row['quota']), 2);?></td>
							<td style="min-width:120px;">
								<?php
								$percentInUse = round((intval($row['bytes']) / intval($row['quota'])) * 100);
								if ($percentInUse >= 90) {
									$pbar = "progress-bar-danger";
								}
								elseif ($percentInUse >= 75) {
									$pbar = "progress-bar-warning";
								}
								else {
									$pbar = "progress-bar-success";
								}
								?>
								<div class="progress">
									<div class="progress-bar <?=$pbar;?>" role="progressbar" aria-valuenow="<?=$percentInUse;?>" aria-valuemin="0" aria-valuemax="100" style="min-width:2em;width: <?=$percentInUse;?>%;">
										<?=$percentInUse;?>%
									</div>
								</div>
							</td>
							<td><?=$row['messages'];?></td>
							<td><?=$row['active'];?></td>
							<td style="text-align: right;">
								<div class="btn-group">
									<a href="/edit.php?mailbox=<?=urlencode($row['username']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									<a href="/delete.php?mailbox=<?=urlencode($row['username']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
								</div>
							</td>
						</tr>
						<?php
						endwhile;
	          else:
						?>
						  <tr id="no-data"><td colspan="8" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['no_record'];?></td></tr>
						<?php
						endif;
						?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="8" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?mailbox" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_mailbox'];?></a>
							</td>
						</tr>
					</tfoot>
				</table>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['aliases'];?> <span class="badge" id="numRowsAlias"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?alias"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="aliastable-filter" data-action="filter" data-filters="#aliastable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped sortable-theme-bootstrap" data-sortable id="aliastable">
					<thead>
						<tr>
							<th class="sort-table" style="min-width: 67px;"><?=$lang['mailbox']['alias'];?></th>
							<th class="sort-table" style="min-width: 119px;"><?=$lang['mailbox']['target_address'];?></th>
							<th class="sort-table" style="min-width: 86px;"><?=$lang['mailbox']['domain'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['mailbox']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					try {
						$stmt = $pdo->prepare("SELECT
								`address`,
								`goto`,
								`domain`,
								CASE `active` WHEN 1 THEN '".$lang['mailbox']['yes']."' ELSE '".$lang['mailbox']['no']."' END AS `active`
									FROM alias
										WHERE (
											`address` NOT IN (
												SELECT `username` FROM `mailbox`
											)
											AND `address` != `goto`
										) AND (`domain` IN (
											SELECT `domain` FROM `domain_admins`
												WHERE `username` = :username 
												AND active='1'
											)
											OR 'admin' = :admin)");
						$stmt->execute(array(
							':username' => $_SESSION['mailcow_cc_username'],
							':admin' => $_SESSION['mailcow_cc_role'],
						));
						$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
					}
					catch (PDOException $e) {
						$_SESSION['return'] = array(
							'type' => 'danger',
							'msg' => 'MySQL: '.$e
						);
						return false;
					}
	        if(!empty($rows)):
					while($row = array_shift($rows)):
					?>
						<tr id="data">
							<td>
							<?php
							if(!filter_var($row['address'], FILTER_VALIDATE_EMAIL)):
							?>
								<span class="glyphicon glyphicon-pushpin" aria-hidden="true"></span> Catch-all @<?=htmlspecialchars($row['domain']);?>
							<?php
							else:
								echo htmlspecialchars($row['address']);
							endif;
							?>
							</td>
							<td>
							<?php
							foreach(explode(",", $row['goto']) as $goto) {
								echo nl2br(htmlspecialchars($goto.PHP_EOL));
							}
							?>
							</td>
							<td><?=htmlspecialchars($row['domain']);?></td>
							<td><?=$row['active'];?></td>
							<td style="text-align: right;">
								<div class="btn-group">
									<a href="/edit.php?alias=<?=urlencode($row['address']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									<a href="/delete.php?alias=<?=urlencode($row['address']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
								</div>
							</td>
						</tr>
					<?php
					endwhile;
	        else:
					?>
					  <tr id="no-data"><td colspan="5" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['no_record'];?></td></tr>
					<?php
					endif;	
					?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="8" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?alias" class="btn btn-xs btn-primary"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_alias'];?></a>
							</td>
						</tr>
					</tfoot>
				</table>
				</div>
			</div>
		</div>
	</div>
</div> <!-- /container -->
<script src="js/sorttable.js"></script>
<script src="js/mailbox.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
