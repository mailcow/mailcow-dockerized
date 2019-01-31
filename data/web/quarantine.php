<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role'])) {
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><?=$lang['quarantine']['quarantine'];?></h3>
        </div>
        <p style="margin:10px" class="help-block"><?=$lang['quarantine']['qinfo'];?></p>
        <p style="margin:10px">
        <?php
        if (empty(quarantine('settings')['retention_size']) || empty(quarantine('settings')['max_size'])):
        ?>
        <div class="panel-body"><div class="alert alert-info"><?=$lang['quarantine']['disabled_by_config'];?></div></div>
        <?php
        endif;
        ?>
        </p>
        <div class="table-responsive">
          <table id="quarantinetable" class="table table-striped"></table>
        </div>
        <div class="mass-actions-quarantine">
          <div class="btn-group" data-acl="<?=$_SESSION['acl']['quarantine'];?>">
            <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="qitems" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['quarantine']['toggle_all'];?></a>
            <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['quarantine']['quick_actions'];?> <span class="caret"></span></a>
            <ul class="dropdown-menu">
              <li><a data-action="edit_selected" data-id="qitems" data-api-url='edit/qitem' data-api-attr='{"action":"release"}' href="#"><?=$lang['quarantine']['release'];?></a></li>
              <li role="separator" class="divider"></li>
              <li><a data-action="edit_selected" data-id="qitems" data-api-url='edit/qitem' data-api-attr='{"action":"learnspam"}' href="#"><?=$lang['quarantine']['learn_spam_delete'];?></a></li>
              <li role="separator" class="divider"></li>
              <li><a data-action="delete_selected" data-id="qitems" data-api-url='delete/qitem' href="#"><?=$lang['quarantine']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <hr>
        <div class="panel-body help-block">
        <p><span class="dot-danger"></span> <?=$lang['quarantine']['high_danger'];?></p>
        <p><span class="dot-neutral"></span> <?=$lang['quarantine']['neutral_danger'];?></p>
        </div>
      </div>
    </div> <!-- /col-md-12 -->
  </div> <!-- /row -->
</div> <!-- /container -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/quarantine.php';
?>
<script type='text/javascript'>
<?php
$lang_mailbox = json_encode($lang['quarantine']);
echo "var acl = '". json_encode($_SESSION['acl']) . "';\n";
echo "var lang = ". $lang_mailbox . ";\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
$role = ($_SESSION['mailcow_cc_role'] == "admin") ? 'admin' : 'domainadmin';
echo "var role = '". $role . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<?php
$js_minifier->add('/web/js/site/quarantine.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
} else {
	header('Location: /');
	exit();
}
?>
