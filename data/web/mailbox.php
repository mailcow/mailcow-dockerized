<?php
require_once "inc/prerequisites.inc.php";

if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
require_once "inc/header.inc.php";
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<style>
table.footable>tbody>tr.footable-empty>td {
  font-size:15px !important;
  font-style:italic;
}
.pagination a {
  text-decoration: none !important;
}
</style>
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
        <div class="table-responsive">
          <table id="resources_table" class="table table-striped"></table>
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
        <div class="table-responsive">
          <table id="aliasdomain_table" class="table table-striped"></table>
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
        <div class="table-responsive">
          <table id="alias_table" class="table table-striped"></table>
        </div>
			</div>
		</div>
	</div>
</div> <!-- /container -->
<script type='text/javascript'>
<?php
$lang_mailbox = json_encode($lang['mailbox']);
echo "var lang = ". $lang_mailbox . ";\n";
?>
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
