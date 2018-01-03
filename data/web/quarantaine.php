<?php
require_once "inc/prerequisites.inc.php";

if (isset($_SESSION['mailcow_cc_role'])) {
require_once "inc/header.inc.php";
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><?=$lang['quarantaine']['quarantaine'];?></h3>
        </div>
        <p style="margin:10px" class="help-block"><?=$lang['quarantaine']['qinfo'];?></p>
        <div class="table-responsive">
          <table id="quarantainetable" class="table table-striped"></table>
        </div>
        <div class="mass-actions-quarantaine">
          <div class="btn-group">
            <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="qitems" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['quarantaine']['toggle_all'];?></a>
            <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['quarantaine']['quick_actions'];?> <span class="caret"></span></a>
            <ul class="dropdown-menu">
              <li><a id="edit_selected" data-id="qitems" data-api-url='edit/qitem' data-api-attr='{"action":"release"}' href="#"><?=$lang['quarantaine']['release'];?></a></li>
              <li role="separator" class="divider"></li>
              <li><a id="delete_selected" data-id="qitems" data-api-url='delete/qitem' href="#"><?=$lang['quarantaine']['remove'];?></a></li>
            </ul>
          </div>
        </div>
      </div>
    </div> <!-- /col-md-12 -->
  </div> <!-- /row -->
</div> <!-- /container -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/quarantaine.php';
?>
<script type='text/javascript'>
<?php
$lang_mailbox = json_encode($lang['quarantaine']);
echo "var lang = ". $lang_mailbox . ";\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
$role = ($_SESSION['mailcow_cc_role'] == "admin") ? 'admin' : 'domainadmin';
echo "var role = '". $role . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/quarantaine.js"></script>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
} else {
	header('Location: /');
	exit();
}
?>
