<?php
require_once "inc/prerequisites.inc.php";

if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")) {
require_once "inc/header.inc.php";
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
      
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#tab-domains" aria-controls="tab-domains" role="tab" data-toggle="tab"><?=$lang['mailbox']['domains'];?></a></li>
    <li role="presentation"><a href="#tab-mailboxes" aria-controls="tab-mailboxes" role="tab" data-toggle="tab"><?=$lang['mailbox']['mailboxes'];?></a></li>
    <li role="presentation"><a href="#tab-resources" aria-controls="tab-resources" role="tab" data-toggle="tab"><?=$lang['mailbox']['resources'];?></a></li>
    <li class="dropdown">
      <a class="dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['aliases'];?>
      <span class="caret"></span></a>
      <ul class="dropdown-menu">
        <li role="presentation"><a href="#tab-mbox-aliases" aria-controls="tab-mbox-aliases" role="tab" data-toggle="tab"><?=$lang['mailbox']['aliases'];?></a></li>
        <li role="presentation"><a href="#tab-domain-aliases" aria-controls="tab-domain-aliases" role="tab" data-toggle="tab"><?=$lang['mailbox']['domain_aliases'];?></a></li>
      </ul>
    </li>
  </ul>

	<div class="row">
		<div class="col-md-12">
      <div class="tab-content" style="padding-top:20px">
        <div role="tabpanel" class="tab-pane active" id="tab-domains">
          <div class="panel panel-default">
            <div class="panel-heading">
            <div class="pull-right">
            <?php
            if ($_SESSION['mailcow_cc_role'] == "admin"):
            ?>
              <a href="/add.php?domain"><span class="glyphicon glyphicon-plus"></span></a>
            <?php
            endif;
            ?>
            </div>
            <h3 class="panel-title"><?=$lang['mailbox']['domains'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="domain_table" class="table table-striped"></table>
            </div>
            <span class="footer-add-item"><a href="/add.php?domain"><?=$lang['mailbox']['add_domain'];?></a></span>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-mailboxes">
          <div class="panel panel-default">
            <div class="panel-heading">
              <div class="pull-right">
                <a href="/add.php?mailbox"><span class="glyphicon glyphicon-plus"></span></a>
              </div>
              <h3 class="panel-title"><?=$lang['mailbox']['mailboxes'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="mailbox_table" class="table table-striped"></table>
            </div>
            <span class="footer-add-item"><a href="/add.php?mailbox"><?=$lang['mailbox']['add_mailbox'];?></a></span>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-resources">
          <div class="panel panel-default">
            <div class="panel-heading">
              <div class="pull-right">
                <a href="/add.php?resource"><span class="glyphicon glyphicon-plus"></span></a>
              </div>
              <h3 class="panel-title"><?=$lang['mailbox']['resources'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="resources_table" class="table table-striped"></table>
            </div>
            <span class="footer-add-item"><a href="/add.php?resource"><?=$lang['mailbox']['add_resource'];?></a></span>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-domain-aliases">
          <div class="panel panel-default">
            <div class="panel-heading">
              <div class="pull-right">
                <a href="/add.php?aliasdomain"><span class="glyphicon glyphicon-plus"></span></a>
              </div>
              <h3 class="panel-title"><?=$lang['mailbox']['domain_aliases'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="aliasdomain_table" class="table table-striped"></table>
            </div>
            <span class="footer-add-item"><a href="/add.php?aliasdomain"><?=$lang['mailbox']['add_domain_alias'];?></a></span>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-mbox-aliases">
          <div class="panel panel-default">
            <div class="panel-heading">
              <a class="pull-right" href="/add.php?alias"><span class="glyphicon glyphicon-plus"></span></a>
              <h3 class="panel-title"><?=$lang['mailbox']['aliases'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="alias_table" class="table table-striped"></table>
            </div>
            <div class="mass-actions">
              <p id="select_all_aliases" class="mass-select-all">
                ↪ <?=$lang['mailbox']['toggle_all'];?>
              </p>
            </div>
            <div class="footer-add-item">
              <a class="pull-right" href="/add.php?alias"><span class="glyphicon glyphicon-plus"></span></a>
              <b><?=$lang['mailbox']['quick_actions'];?>:</b>
              <a id="delete_selected_alias" href="#" class="mass-each-action"><?=$lang['mailbox']['remove'];?></a> |
              <a id="activate_selected_alias" href="#" class="mass-each-action"><?=$lang['mailbox']['activate'];?></a> |
              <a id="deactivate_selected_alias" href="#" class="mass-each-action"><?=$lang['mailbox']['deactivate'];?></a>
            </div>
          </div>
        </div>

      </div> <!-- /tab-content -->
    </div> <!-- /col-md-12 -->
  </div> <!-- /row -->
</div> <!-- /container -->

<script type='text/javascript'>
<?php
$lang_mailbox = json_encode($lang['mailbox']);
echo "var lang = ". $lang_mailbox . ";\n";
$role = ($_SESSION['mailcow_cc_role'] == "admin") ? 'admin' : 'domainadmin';
echo "var role = '". $role . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
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
