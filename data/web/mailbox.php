<?php
require_once "inc/prerequisites.inc.php";

if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
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
          $domains = mailbox_get_domains();
	        if (!empty($domains)):
					foreach ($domains as $domain):
            $domaindata = mailbox_get_domain_details($domain);
					?>
						<tr id="data">
							<td><?=htmlspecialchars($domaindata['domain_name']);?></td>
							<td><?=$domaindata['aliases_in_domain'];?> / <?=$domaindata['max_num_aliases_for_domain'];?></td>
							<td><?=$domaindata['mboxes_in_domain'];?> / <?=$domaindata['max_num_mboxes_for_domain'];?></td>
							<td><?=formatBytes($domaindata['max_quota_for_mbox']);?></td>
							<td><?=formatBytes($domaindata['quota_used_in_domain'], 2);?> / <?=formatBytes($domaindata['max_quota_for_domain'], 2);?></td>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<td><?=$domaindata['backupmx'];?></td>
							<?php
							endif;
							?>
							<td><?=$domaindata['active'];?></td>
							<?php
							if ($_SESSION['mailcow_cc_role'] == "admin"):
							?>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="/edit.php?domain=<?=urlencode($domaindata['domain_name']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
										<a href="/delete.php?domain=<?=urlencode($domaindata['domain_name']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
									</div>
								</td>
							<?php
							else:
							?>
								<td style="text-align: right;">
									<div class="btn-group">
										<a href="/edit.php?domain=<?=urlencode($domaindata['domain_name']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									</div>
								</td>
						</tr>
              <?php
              endif;
            endforeach;
            else:
							?>
              <tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['no_record_single'];?></td></tr>
            <?php
            endif;
            ?>
					</tbody>
						<?php
						if ($_SESSION['mailcow_cc_role'] == "admin"):
						?>
					<tfoot>
						<tr id="no-data">
							<td colspan="999" style="text-align: center; font-style: normal; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?domain"><?=$lang['mailbox']['add_domain'];?></a>
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
							<th class="sort-table" style="min-width: 75px;"><?=$lang['mailbox']['spam_aliases'];?></th>
							<th class="sort-table" style="min-width: 99px;"><?=$lang['mailbox']['in_use'];?></th>
							<th class="sort-table" style="min-width: 100px;"><?=$lang['mailbox']['msg_num'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['mailbox']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
						<?php
					if (!empty($domains)) {
            foreach (mailbox_get_domains() as $domain) {
              $mailboxes = mailbox_get_mailboxes($domain);
              if (!empty($mailboxes)) {
                foreach ($mailboxes as $mailbox) {
                  $mailboxdata = mailbox_get_mailbox_details($mailbox);
						?>
						<tr id="data">
							<td><?=($mailboxdata['is_relayed'] == "0") ? htmlspecialchars($mailboxdata['username']) : '<span data-toggle="tooltip" title="Relayed"><i class="glyphicon glyphicon-forward"></i>' . htmlspecialchars($mailboxdata['username']) . '</span>';?></td>
							<td><?=htmlspecialchars($mailboxdata['name'], ENT_QUOTES, 'UTF-8');?></td>
							<td><?=htmlspecialchars($mailboxdata['domain']);?></td>
							<td><?=formatBytes($mailboxdata['quota_used'], 2);?> / <?=formatBytes($mailboxdata['quota'], 2);?></td>
							<td><?=$mailboxdata['spam_aliases'];?></td>
							<td style="min-width:120px;">
								<div class="progress">
									<div class="progress-bar progress-bar-<?=$mailboxdata['percent_class'];?>" role="progressbar" aria-valuenow="<?=$mailboxdata['percent_in_use'];?>" aria-valuemin="0" aria-valuemax="100" style="min-width:2em;width: <?=$mailboxdata['percent_in_use'];?>%;">
										<?=$mailboxdata['percent_in_use'];?>%
									</div>
								</div>
							</td>
							<td><?=$mailboxdata['messages'];?></td>
							<td><?=$mailboxdata['active'];?></td>
							<td style="text-align: right;">
								<div class="btn-group">
									<a href="/edit.php?mailbox=<?=urlencode($mailboxdata['username']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									<a href="/delete.php?mailbox=<?=urlencode($mailboxdata['username']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
									<?php if ($_SESSION['mailcow_cc_role'] == "admin"): ?>
                  <a href="/index.php?duallogin=<?=urlencode($mailboxdata['username']);?>" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-user"></span> Login</a>
                  <?php endif; ?>
								</div>
							</td>
						</tr>
						<?php
                }
              }
              else {
                  ?>
                  <tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=sprintf($lang['mailbox']['no_record'], $domain);?></td></tr>
                  <?php
              }
            }
					} else {
					?>
						<tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['add_domain_record_first'];?></td></tr>
						<?php
					}
						?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="999" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?mailbox"><?=$lang['mailbox']['add_mailbox'];?></a>
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
					<h3 class="panel-title"><?=$lang['mailbox']['resources'];?> <span class="badge" id="numRowsResource"></span></h3>
					<div class="pull-right">
						<span class="clickable filter" data-toggle="tooltip" title="<?=$lang['mailbox']['filter_table'];?>" data-container="body">
							<i class="glyphicon glyphicon-filter"></i>
						</span>
						<a href="/add.php?resource"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
					<input type="text" class="form-control" id="resourcetable-filter" data-action="filter" data-filters="#resourcetable" placeholder="Filter" />
				</div>
				<div class="table-responsive">
				<table class="table table-striped sortable-theme-bootstrap" data-sortable id="resourcetable">
					<thead>
						<tr>
							<th class="sort-table" style="min-width: 98px;"><?=$lang['mailbox']['description'];?></th>
							<th class="sort-table" style="min-width: 98px;"><?=$lang['mailbox']['kind'];?></th>
							<th class="sort-table" style="min-width: 86px;"><?=$lang['mailbox']['domain'];?></th>
							<th class="sort-table" style="min-width: 98px;"><?=$lang['mailbox']['multiple_bookings'];?></th>
							<th class="sort-table" style="min-width: 76px;"><?=$lang['mailbox']['active'];?></th>
							<th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['mailbox']['action'];?></th>
						</tr>
					</thead>
					<tbody>
						<?php
					if (!empty($domains)) {
            foreach (mailbox_get_domains() as $domain) {
              $resources = mailbox_get_resources($domain);
              if (!empty($resources)) {
                foreach ($resources as $resource) {
                  $resourcedata = mailbox_get_resource_details($resource);
						?>
						<tr id="data">
							<td><?=htmlspecialchars($resourcedata['description'], ENT_QUOTES, 'UTF-8');?></td>
							<td><?=$resourcedata['kind'];?></td>
							<td><?=htmlspecialchars($resourcedata['domain']);?></td>
							<td><?=$resourcedata['multiple_bookings'];?></td>
							<td><?=$resourcedata['active'];?></td>
							<td style="text-align: right;">
								<div class="btn-group">
									<a href="/edit.php?resource=<?=urlencode($resourcedata['name']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									<a href="/delete.php?resource=<?=urlencode($resourcedata['name']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
								</div>
							</td>
						</tr>
						<?php
                }
              }
              else {
                  ?>
                  <tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=sprintf($lang['mailbox']['no_record'], $domain);?></td></tr>
                  <?php
              }
            }
					} else {
						?>
						<tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['add_domain_record_first'];?></td></tr>
						<?php
					}
						?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="999" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?resource"><?=$lang['mailbox']['add_resource'];?></a>
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
				if (!empty($domains)) {
          foreach (mailbox_get_domains() as $domain) {
            $alias_domains = mailbox_get_alias_domains($domain);
            if (!empty($alias_domains)) {
              foreach ($alias_domains as $alias_domain) {
                $aliasdomaindata = mailbox_get_alias_domain_details($alias_domain);
                ?>
                <tr id="data">
                  <td><?=htmlspecialchars($aliasdomaindata['alias_domain']);?></td>
                  <td><?=htmlspecialchars($aliasdomaindata['target_domain']);?></td>
                  <td><?=$aliasdomaindata['active'];?></td>
                  <td style="text-align: right;">
                    <div class="btn-group">
                      <a href="/edit.php?aliasdomain=<?=urlencode($aliasdomaindata['alias_domain']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
                      <a href="/delete.php?aliasdomain=<?=urlencode($aliasdomaindata['alias_domain']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
                    </div>
                  </td>
                </tr>
                <?php
              }
            }
            else {
	        ?>
                  <tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=sprintf($lang['mailbox']['no_record'], $domain);?></td></tr>
	        <?php
            }
          }
				} else {
          ?>
						<tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['add_domain_record_first'];?></td></tr>
					<?php
				}
					?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="999" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?aliasdomain"><?=$lang['mailbox']['add_domain_alias'];?></a>
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
				if (!empty($domains)) {
          foreach (array_merge(mailbox_get_domains(), mailbox_get_alias_domains()) as $domain) {
            $aliases = mailbox_get_aliases($domain);
            if (!empty($aliases)) {
              foreach ($aliases as $alias) {
                $aliasdata = mailbox_get_alias_details($alias);
					?>
						<tr id="data">
							<td>
							<?= ($aliasdata['is_catch_all'] == "1") ? '<span class="glyphicon glyphicon-pushpin" aria-hidden="true"></span> Catch-all ' . htmlspecialchars($aliasdata['address']) : htmlspecialchars($aliasdata['address']); ?>
							</td>
							<td>
							<?php
							foreach(explode(",", $aliasdata['goto']) as $goto) {
								echo nl2br(htmlspecialchars($goto.PHP_EOL));
							}
							?>
							</td>
							<td><?=htmlspecialchars($aliasdata['domain']);?></td>
							<td><?=$aliasdata['active'];?></td>
							<td style="text-align: right;">
								<div class="btn-group">
									<a href="/edit.php?alias=<?=urlencode($aliasdata['address']);?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['mailbox']['edit'];?></a>
									<a href="/delete.php?alias=<?=urlencode($aliasdata['address']);?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['mailbox']['remove'];?></a>
								</div>
							</td>
						</tr>
						<?php
							}
						}
						else {
								?>
								<tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=sprintf($lang['mailbox']['no_record'], $domain);?></td></tr>
								<?php
						}
          }
				} else {
						?>
						<tr id="no-data"><td colspan="999" style="text-align: center; font-style: italic;"><?=$lang['mailbox']['add_domain_record_first'];?></td></tr>
						<?php
				}
						?>
					</tbody>
					<tfoot>
						<tr id="no-data">
							<td colspan="999" style="text-align: center; border-top: 1px solid #e7e7e7;">
								<a href="/add.php?alias"><?=$lang['mailbox']['add_alias'];?></a>
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
