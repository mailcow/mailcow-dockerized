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
    <li role="presentation"><a href="#tab-syncjobs" aria-controls="tab-syncjobs" role="tab" data-toggle="tab"><?=$lang['mailbox']['sync_jobs'];?></a></li>
    <li role="presentation"><a href="#tab-filters" aria-controls="tab-filters" role="tab" data-toggle="tab"><?=$lang['mailbox']['filters'];?></a></li>
    <li role="presentation"><a href="#tab-bcc" aria-controls="tab-filters" role="tab" data-toggle="tab"><?=$lang['mailbox']['address_rewriting'];?></a></li>
  </ul>

	<div class="row">
		<div class="col-md-12">
      <div class="tab-content" style="padding-top:20px">
        <div role="tabpanel" class="tab-pane active" id="tab-domains">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['domains'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="domain_table" class="table table-striped"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="domain" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <? if($_SESSION['mailcow_cc_role'] == "admin"): ?>
                    <li><a id="edit_selected" data-id="domain" data-api-url='edit/domain' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                    <li><a id="edit_selected" data-id="domain" data-api-url='edit/domain' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                    <li role="separator" class="divider"></li>
                    <li><a id="delete_selected" data-id="domain" data-api-url='delete/domain' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                  <? endif; ?>
                </ul>
                <? if($_SESSION['mailcow_cc_role'] == "admin"): ?>
                  <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addDomainModal"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_domain'];?></a>
                <? endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-mailboxes">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['mailboxes'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="mailbox_table" class="table table-striped"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="mailbox" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="mailbox" data-api-url='edit/mailbox' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="mailbox" data-api-url='edit/mailbox' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-id="mailbox" data-api-url='delete/mailbox' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addMailboxModal"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_mailbox'];?></a>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-resources">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['resources'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="resource_table" class="table table-striped"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="resource" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="resource" data-api-url='edit/resource' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="resource" data-api-url='edit/resource' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-id="resource" data-api-url='delete/resource' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addResourceModal"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_resource'];?></a>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-domain-aliases">
          <div class="panel panel-default">
            <div class="panel-heading">
              <div class="pull-right">
                <a href="#" data-toggle="modal" data-target="#addAliasDomainModal" ><span class="glyphicon glyphicon-plus"></span></a>
              </div>
              <h3 class="panel-title"><?=$lang['mailbox']['domain_aliases'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="aliasdomain_table" class="table table-striped"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="alias-domain" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="alias-domain" data-api-url='edit/alias-domain' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="alias-domain" data-api-url='edit/alias-domain' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-id="alias-domain" data-api-url='delete/alias-domain' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addAliasDomainModal"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_domain_alias'];?></a>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-mbox-aliases">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['aliases'];?></h3>
            </div>
            <div class="table-responsive">
              <table id="alias_table" class="table table-striped"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="alias" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="alias" data-api-url='edit/alias' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="alias" data-api-url='edit/alias' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-id="alias" data-api-url='delete/alias' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addAliasModal"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_alias'];?></a>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-syncjobs">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['sync_jobs'];?></h3>
            </div>
            <div class="table-responsive">
              <table class="table table-striped" id="sync_job_table"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="syncjob" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"last_run":""}' href="#"><?=$lang['mailbox']['last_run_reset'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="syncjob" data-api-url='edit/syncjob' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-text="<?=$lang['user']['eas_reset'];?>?" data-id="syncjob" data-api-url='delete/syncjob' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addSyncJobModalAdmin"><span class="glyphicon glyphicon-plus"></span> <?=$lang['user']['create_syncjob'];?></a>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-filters">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['filters'];?></h3>
            </div>
            <p style="margin:10px" class="help-block"><?=$lang['mailbox']['sieve_info'];?></p>
            <div class="table-responsive">
              <table class="table table-striped" id="filter_table"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="filter_item" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="filter_item" data-api-url='edit/filter' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="filter_item" data-api-url='edit/filter' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="edit_selected" data-id="filter_item" data-api-url='edit/filter' data-api-attr='{"filter_type":"prefilter"}' href="#"><?=$lang['mailbox']['set_prefilter'];?></a></li>
                  <li><a id="edit_selected" data-id="filter_item" data-api-url='edit/filter' data-api-attr='{"filter_type":"postfilter"}' href="#"><?=$lang['mailbox']['set_postfilter'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-text="<?=$lang['user']['eas_reset'];?>?" data-id="filter_item" data-api-url='delete/filter' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addFilterModalAdmin"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_filter'];?></a>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="tab-bcc">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['bcc_maps'];?></h3>
            </div>
            <p style="margin:10px" class="help-block"><?=$lang['mailbox']['bcc_info'];?></p>
            <div class="table-responsive">
              <table class="table table-striped" id="bcc_table"></table>
            </div>
            <div class="mass-actions-mailbox">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="bcc" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="bcc" data-api-url='edit/bcc' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="bcc" data-api-url='edit/bcc' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="edit_selected" data-id="bcc" data-api-url='edit/bcc' data-api-attr='{"type":"sender"}' href="#"><?=$lang['mailbox']['bcc_to_sender'];?></a></li>
                  <li><a id="edit_selected" data-id="bcc" data-api-url='edit/bcc' data-api-attr='{"type":"rcpt"}' href="#"><?=$lang['mailbox']['bcc_to_rcpt'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-id="bcc" data-api-url='delete/bcc' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addBCCModalAdmin"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_bcc_entry'];?></a>
              </div>
            </div>
          </div>
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><?=$lang['mailbox']['recipient_maps'];?></h3>
            </div>
            <p style="margin:10px" class="help-block"><?=$lang['mailbox']['recipient_map_info'];?></p>
            <div class="table-responsive">
              <table class="table table-striped" id="recipient_map_table"></table>
            </div>
<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin"))
  $display = 'block';
else
  $display = 'none';
?>
            <div class="mass-actions-mailbox" style="display: <?php echo $display; ?>">
              <div class="btn-group">
                <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="recipient_map" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
                <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
                <ul class="dropdown-menu">
                  <li><a id="edit_selected" data-id="recipient_map" data-api-url='edit/recipient_map' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                  <li><a id="edit_selected" data-id="recipient_map" data-api-url='edit/recipient_map' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                  <li role="separator" class="divider"></li>
                  <li><a id="delete_selected" data-id="recipient_map" data-api-url='delete/recipient_map' href="#"><?=$lang['mailbox']['remove'];?></a></li>
                </ul>
                <a class="btn btn-sm btn-success" href="#" data-toggle="modal" data-target="#addRecipientMapModalAdmin"><span class="glyphicon glyphicon-plus"></span> <?=$lang['mailbox']['add_recipient_map_entry'];?></a>
              </div>
            </div>
          </div>
        </div>
      </div> <!-- /tab-content -->
    </div> <!-- /col-md-12 -->
  </div> <!-- /row -->
</div> <!-- /container -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/mailbox.php';
?>
<script type='text/javascript'>
<?php
$lang_mailbox = json_encode($lang['mailbox']);
echo "var lang = ". $lang_mailbox . ";\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
$role = ($_SESSION['mailcow_cc_role'] == "admin") ? 'admin' : 'domainadmin';
echo "var role = '". $role . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/mailbox.js"></script>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
} else {
	header('Location: /');
	exit();
}
?>
