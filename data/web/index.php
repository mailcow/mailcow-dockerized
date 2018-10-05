<?php
require_once 'inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
  header('Location: /admin');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {
  header('Location: /mailbox');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
  header('Location: /user');
  exit();
}
require_once 'inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];

?>
<div class="container">
  <div class="row">
    <div class="col-md-offset-3 col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?= $lang['login']['login']; ?></div>
        <div class="panel-body">
          <div class="text-center mailcow-logo"><img src="<?=($main_logo = customize('get', 'main_logo')) ? $main_logo : '/img/cow_mailcow.svg';?>" alt="mailcow"></div>
          <legend><?=$UI_TEXTS['main_name'];?></legend>
            <form method="post" autofill="off">
            <div class="form-group">
              <label class="sr-only" for="login_user"><?= $lang['login']['username']; ?></label>
              <div class="input-group">
                <div class="input-group-addon"><i class="glyphicon glyphicon-user"></i></div>
                <input name="login_user" autocorrect="off" autocapitalize="none" type="text" id="login_user" class="form-control" placeholder="<?= $lang['login']['username']; ?>" required="" autofocus="">
              </div>
            </div>
            <div class="form-group">
              <label class="sr-only" for="pass_user"><?= $lang['login']['password']; ?></label>
              <div class="input-group">
                <div class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></div>
                <input name="pass_user" type="password" id="pass_user" class="form-control" placeholder="<?= $lang['login']['password']; ?>" required="">
              </div>
            </div>
            <div class="form-group">
              <button type="submit" class="btn btn-success" value="Login"><?= $lang['login']['login']; ?></button>
              <div class="btn-group pull-right">
                <button type="button" <?=(isset($_SESSION['mailcow_locale']) && count($AVAILABLE_LANGUAGES) === 1) ? 'disabled="true"' : '' ?> class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <span class="lang-sm lang-lbl" lang="<?= $_SESSION['mailcow_locale']; ?>"></span> <span class="caret"></span>
                </button>
                <ul class="dropdown-menu">
                  <?php
                  foreach ($AVAILABLE_LANGUAGES as $language):
                  ?>
                  <li<?= ($_SESSION['mailcow_locale'] == $language) ? ' class="active"' : ''; ?>><a href="?<?= http_build_query(array_merge($_GET, array('lang' => $language))) ?>"><span class="lang-xs lang-lbl-full" lang="<?= $language; ?>"></span></a></li>
                  <?php
                  endforeach;
                  ?>
                </ul>
              </div>
            </div>
            </form>
            <?php
            if (isset($_SESSION['ldelay']) && $_SESSION['ldelay'] != '0'):
            ?>
            <p><div class="alert alert-info"><?= sprintf($lang['login']['delayed'], $_SESSION['ldelay']); ?></b></div></p>
            <?php
            endif;
            ?>
          <legend><?=$UI_TEXTS['apps_name'];?></legend>
          <?php
          foreach ($MAILCOW_APPS as $app):
          ?>
            <a href="<?= htmlspecialchars($app['link']); ?>" role="button" title="<?= htmlspecialchars($app['description']); ?>" class="btn btn-lg btn-default"><?= htmlspecialchars($app['name']); ?></a>&nbsp;
          <?php
          endforeach;
          $app_links = customize('get', 'app_links');
          if (!empty($app_links)) {
            foreach ($app_links as $row) {
              foreach ($row as $key => $val):
            ?>
              <a href="<?= htmlspecialchars($val); ?>" role="button" class="btn btn-lg btn-default"><?= htmlspecialchars($key); ?></a>&nbsp;
            <?php 
              endforeach;
            }
          }
          ?>
        </div>
      </div>
    </div>
    <div class="col-md-offset-3 col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading">
          <a data-toggle="collapse" href="#collapse1"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span> <?= $lang['start']['help']; ?></a>
        </div>
        <div id="collapse1" class="panel-collapse collapse">
          <div class="panel-body">
            <?php if ($UI_TEXTS['help_text']): ?>
            <p><?=$UI_TEXTS['help_text'];?></p>
            <?php else: ?>
            <p><span style="border-bottom: 1px dotted #999;"><?=$UI_TEXTS['main_name'];?></span></p>
            <p><?= $lang['start']['mailcow_panel_detail']; ?></p>
            <p><span style="border-bottom: 1px dotted #999;"><?=$UI_TEXTS['apps_name'];?></span></p>
            <p><?= $lang['start']['mailcow_apps_detail']; ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /.container -->
<script src="/js/index.js"></script>
<?php
require_once 'inc/footer.inc.php';
