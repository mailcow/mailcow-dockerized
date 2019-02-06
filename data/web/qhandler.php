<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
if (preg_match("/^([a-f0-9]{64})$/", $_POST['quick_release']) || preg_match("/^([a-f0-9]{64})$/", $_POST['quick_delete'])) {
?>
<div class="container">
  <div class="row">
    <div class="col-md-offset-2 col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading"><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span> <?= $lang['header']['quarantine']; ?></div>
        <div class="panel-body">
          <legend><?=(isset($_POST['quick_release'])) ? $lang['quarantine']['release'] : $lang['quarantine']['remove'];?></legend>
            <p><?=$lang['quarantine']['qhandler_success'];?></p>
        </div>
      </div>
    </div>
  </div> <!-- /row -->
</div> <!-- /container -->
<?php
}
elseif (in_array($_GET['action'], array('release', 'delete'))) {
  if (preg_match("/^([a-f0-9]{64})$/", $_GET['hash'])) {
    if ($_GET['action'] == "release"):
?>
<div class="container">
  <div class="row">
    <div class="col-md-offset-2 col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading"><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span> <?= $lang['header']['quarantine']; ?></div>
        <div class="panel-body">
          <legend><?=$lang['quarantine']['release'];?></legend>
            <form method="post" autofill="off">
            <div class="form-group">
              <button type="submit" class="btn btn-success" name="quick_release" value="<?=$_GET['hash'];?>"><?= $lang['tfa']['confirm']; ?></button>
            </div>
            </form>
        </div>
      </div>
    </div>
  </div> <!-- /row -->
</div> <!-- /container -->
<?php
    elseif ($_GET['action'] == "delete"):
?>
<div class="container">
  <div class="row">
    <div class="col-md-offset-2 col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading"><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span> <?= $lang['header']['quarantine']; ?></div>
        <div class="panel-body">
          <legend><?=$lang['quarantine']['remove'];?></legend>
            <form method="post" autofill="off">
            <div class="form-group">
              <button type="submit" class="btn btn-success" name="quick_delete" value="<?=$_GET['hash'];?>"><?= $lang['tfa']['confirm']; ?></button>
            </div>
            </form>
        </div>
      </div>
    </div>
  </div> <!-- /row -->
</div> <!-- /container -->
<?php
    endif;
  }
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
?>
