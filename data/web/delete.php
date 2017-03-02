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
					<h3 class="panel-title"><?=$lang['delete']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
		// DELETE DOMAIN
		if (isset($_GET["domain"]) &&
			is_valid_domain_name($_GET["domain"]) &&
			!empty($_GET["domain"]) &&
			$_SESSION['mailcow_cc_role'] == "admin") {
			$domain = $_GET["domain"];
			?>
				<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_domain_warning'], htmlspecialchars($_GET["domain"]));?></div>
				<p><?=$lang['delete']['remove_domain_details'];?></p>
				<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
				<input type="hidden" name="domain" value="<?php echo htmlspecialchars($domain) ?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="mailbox_delete_domain" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
						</div>
					</div>
				</form>
			<?php
		}
		// DELETE ALIAS
		elseif (isset($_GET["alias"]) &&
			(filter_var($_GET["alias"], FILTER_VALIDATE_EMAIL) || is_valid_domain_name(substr(strrchr($_GET["alias"], "@"), 1))) &&
			!empty($_GET["alias"])) {
				$domain = substr(strrchr($_GET["alias"], "@"), 1);
				if (hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
				?>
					<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_alias_warning'], htmlspecialchars($_GET["alias"]));?></div>
					<p><?=$lang['delete']['remove_alias_details'];?></p>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="address" value="<?php echo htmlspecialchars($_GET["alias"]) ?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="mailbox_delete_alias" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
		// DELETE ALIAS DOMAIN
		elseif (
			isset($_GET["aliasdomain"]) &&
			is_valid_domain_name($_GET["aliasdomain"]) && 
			!empty($_GET["aliasdomain"])) {
        $alias_domain = $_GET["aliasdomain"];
        $result = mailbox_get_alias_domain_details($alias_domain);
				if (!empty($result)) {
				?>
					<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_domainalias_warning'], htmlspecialchars($_GET["aliasdomain"]));?></div>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="alias_domain" value="<?php echo htmlspecialchars($alias_domain) ?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="mailbox_delete_alias_domain" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
		// DELETE DOMAIN ADMIN
		elseif (isset($_GET["domainadmin"]) &&
			ctype_alnum(str_replace(array('_', '.', '-'), '', $_GET["domainadmin"])) &&
			!empty($_GET["domainadmin"]) &&
        $_SESSION['mailcow_cc_role'] == "admin") {
				$domain_admin = $_GET["domainadmin"];
				?>
				<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_domainadmin_warning'], htmlspecialchars($_GET["domainadmin"]));?></div>
				<form class="form-horizontal" role="form" method="post" action="/admin.php">
				<input type="hidden" name="username" value="<?=htmlspecialchars($domain_admin);?>">
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-10">
							<button type="submit" name="delete_domain_admin" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
						</div>
					</div>
				</form>
				<?php
		}
		// DELETE MAILBOX
		elseif (isset($_GET["mailbox"]) &&
			filter_var($_GET["mailbox"], FILTER_VALIDATE_EMAIL) &&
			!empty($_GET["mailbox"])) {
				$mailbox = $_GET["mailbox"];
				if (hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $mailbox)) {
				?>
					<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_mailbox_warning'], htmlspecialchars($mailbox));?></div>
					<p><?=$lang['delete']['remove_mailbox_details'];?></p>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="username" value="<?=htmlspecialchars($mailbox);?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="mailbox_delete_mailbox" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
		// DELETE RESOURCE
		elseif (isset($_GET["resource"]) &&
			filter_var($_GET["resource"], FILTER_VALIDATE_EMAIL) &&
			!empty($_GET["resource"])) {
				$resource = $_GET["resource"];
				if (hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $resource)) {
				?>
					<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_resource_warning'], htmlspecialchars($resource));?></div>
					<p><?=$lang['delete']['remove_resource_details'];?></p>
					<form class="form-horizontal" role="form" method="post" action="/mailbox.php">
					<input type="hidden" name="name" value="<?=htmlspecialchars($resource);?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<button type="submit" name="mailbox_delete_resource" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
		// DELETE SYNCJOB
		if (isset($_GET["syncjob"]) &&
			is_numeric($_GET["syncjob"]) &&
      filter_var($_SESSION['mailcow_cc_username'], FILTER_VALIDATE_EMAIL)) {
        $id = $_GET["syncjob"];
        $result = get_syncjob_details($id);
        if (!empty($result)) {
				?>
					<div class="alert alert-warning" role="alert"><?=sprintf($lang['delete']['remove_syncjob_warning'], htmlspecialchars($result['user2']));?></div>
					<p><?=$lang['delete']['remove_syncjob_details'];?></p>
					<form class="form-horizontal" role="form" method="post" action="/user.php">
					<input type="hidden" name="username" value="<?=htmlspecialchars($mailbox);?>">
						<div class="form-group">
							<div class="col-sm-offset-1 col-sm-10">
								<input type="hidden" name="id" value="<?=$_GET["syncjob"];?>">
								<button type="submit" name="delete_syncjob" value="1" class="btn btn-default btn-sm"><?=$lang['delete']['remove_button'];?></button>
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
<a href="<?=$_SESSION['return_to'];?>">&#8592; <?=$lang['delete']['previous'];?></a>
</div> <!-- /container -->
<?php
require_once("inc/footer.inc.php");
?>
