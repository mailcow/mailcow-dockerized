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
				<h3 class="panel-title"><?=$lang['mailbox']['domains'];?></h3>
				<div class="pull-right">
				<?php
				if ($_SESSION['mailcow_cc_role'] == "admin"):
				?>
					<a href="/add.php?domain"><span class="glyphicon glyphicon-plus"></span></a>
				<?php
				endif;
				?>
				</div>
				</div>
          <div class="table-responsive">
            <table id="domain_table" class="table table-striped"></table>
          </div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['mailboxes'];?></h3>
					<div class="pull-right">
						<a href="/add.php?mailbox"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
          <div class="table-responsive">
            <table id="mailbox_table" class="table table-striped"></table>
          </div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['resources'];?></h3>
					<div class="pull-right">
						<a href="/add.php?resource"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
          <div class="table-responsive">
            <table id="resources_table" class="table table-striped"></table>
          </div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['domain_aliases'];?></h3>
					<div class="pull-right">
						<a href="/add.php?aliasdomain"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
          <div class="table-responsive">
            <table id="aliasdomain_table" class="table table-striped"></table>
          </div>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['mailbox']['aliases'];?></h3>
					<div class="pull-right">
						<a href="/add.php?alias"><span class="glyphicon glyphicon-plus"></span></a>
					</div>
				</div>
				<div class="panel-body">
          <div class="table-responsive">
            <table id="alias_table" class="table table-striped"></table>
          </div>
				</div>
			</div>
		</div>
	</div>
</div> <!-- /container -->
<script>
var lang_domain = '<?=$lang['mailbox']['domain'];?>';
var lang_aliases = '<?=$lang['mailbox']['aliases'];?>';
var lang_mailboxes = '<?=$lang['mailbox']['mailboxes'];?>';
var lang_mailbox_quota = '<?=$lang['mailbox']['mailbox_quota'];?>';
var lang_domain_quota = '<?=$lang['mailbox']['domain_quota'];?>';
var lang_backup_mx = '<?=$lang['mailbox']['backup_mx'];?>';
var lang_active = '<?=$lang['mailbox']['active'];?>';
var lang_username = '<?=$lang['mailbox']['username'];?>';
var lang_fname = '<?=$lang['mailbox']['fname'];?>';
var lang_spam_aliases = '<?=$lang['mailbox']['spam_aliases'];?>';
var lang_in_use = '<?=$lang['mailbox']['in_use'];?>';
var lang_msg_num = '<?=$lang['mailbox']['msg_num'];?>';
</script>
<script src="js/footable.min.js"></script>
<script src="js/mailbox.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
