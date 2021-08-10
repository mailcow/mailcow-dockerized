<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && isset($_SESSION['oauth2_request'])) {
  $oauth2_request = $_SESSION['oauth2_request'];
  unset($_SESSION['oauth2_request']);
  header('Location: ' . $oauth2_request);
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$_SESSION['index_query_string'] = $_SERVER['QUERY_STRING'];

?>
<div class="container">
  <div class="row">
    <div class="col-md-offset-3 col-md-6">
      <div class="panel panel-default panel-login">
        <div class="panel-heading"><i class="bi bi-person-fill"></i> <?= $lang['login']['login']; ?></div>
        <div class="panel-body">
          <div class="text-center mailcow-logo"><img src="<?=($main_logo = customize('get', 'main_logo')) ? $main_logo : '/img/cow_mailcow.svg';?>" alt="mailcow"></div>
          <?php if (!empty($UI_TEXTS['ui_announcement_text']) && in_array($UI_TEXTS['ui_announcement_type'], array('info', 'warning', 'danger')) && $UI_TEXTS['ui_announcement_active'] == 1) { ?>
          <div class="alert alert-<?=$UI_TEXTS['ui_announcement_type'];?> rot-enc ui-announcement-alert"><?=str_rot13($UI_TEXTS['ui_announcement_text']);?></div>
          <?php } ?>
          <legend><?= isset($_SESSION['oauth2_request']) ? $lang['oauth2']['authorize_app'] : $UI_TEXTS['main_name'];?></legend>
            <?php
            if (strpos($_SESSION['index_query_string'], 'mobileconfig') !== false) {
            ?>
            <div class="alert alert-info"><?= $lang['login']['mobileconfig_info']; ?></div>
            <?php
            }
            ?>
            <form method="post" autofill="off">
            <div class="form-group">
              <label class="sr-only" for="login_user"><?= $lang['login']['username']; ?></label>
              <div class="input-group">
                <div class="input-group-addon"><i class="bi bi-person-fill"></i></div>
                <input name="login_user" autocorrect="off" autocapitalize="none" type="<?=(strpos($_SESSION['index_query_string'], 'mobileconfig') !== false) ? 'email' : 'text';?>" id="login_user" class="form-control" placeholder="<?= $lang['login']['username']; ?>" required="" autofocus="" autocomplete="username">
              </div>
            </div>
            <div class="form-group">
              <label class="sr-only" for="pass_user"><?= $lang['login']['password']; ?></label>
              <div class="input-group">
                <div class="input-group-addon"><i class="bi bi-lock-fill"></i></div>
                <input name="pass_user" type="password" id="pass_user" class="form-control" placeholder="<?= $lang['login']['password']; ?>" required="" autocomplete="current-password">
              </div>
            </div>
            <div class="form-group" style="position: relative">
              <div class="btn-group">
                <div class="btn-group">
                  <button type="submit" class="btn btn-xs-lg btn-success" value="Login"><?= $lang['login']['login']; ?></button>
                  <button type="button" class="btn btn-xs-lg btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="caret"></span>
                  </button>
                  <ul class="dropdown-menu">
                    <li><a href="#" id="fido2-login" style="line-height:1.4;"><i class="bi bi-shield-fill-check"></i> <?= $lang['login']['fido2_webauthn']; ?></a></li>
                  </ul>
                </div>
              </div>
              <?php if(!isset($_SESSION['oauth2_request'])) { ?>
                <button type="button" <?=(isset($_SESSION['mailcow_locale']) && count($AVAILABLE_LANGUAGES) === 1) ? 'disabled="true"' : '' ?> class="btn btn-xs-lg btn-default pull-right dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <span class="flag-icon flag-icon-<?= $_SESSION['mailcow_locale']; ?>"></span> <span class="caret"></span>
                </button>
                <ul class="dropdown-menu pull-right login">
                  <?php
                  foreach ($AVAILABLE_LANGUAGES as $c => $v) {
                  ?>
                  <li<?= ($_SESSION['mailcow_locale'] == $c) ? ' class="active"' : ''; ?>><a href="?<?= http_build_query(array_merge($_GET, array('lang' => $c))) ?>"><span class="flag-icon flag-icon-<?=$c;?>"></span> <?=$v;?></a></li>
                  <?php } ?>
                </ul>
              <?php } ?>
              <div class="clearfix"></div>
            </div>
            </form>
            <?php
            if (isset($_SESSION['ldelay']) && $_SESSION['ldelay'] != '0') {
            ?>
            <p><div class="alert alert-info"><?= sprintf($lang['login']['delayed'], $_SESSION['ldelay']); ?></b></div></p>
            <?php } ?>
            <div id="fido2-alerts"></div>
          <?php if(!isset($_SESSION['oauth2_request'])) { ?>
            <legend><i class="bi bi-link-45deg"></i> <?=$UI_TEXTS['apps_name'];?></legend>
            <div class="apps">
            <?php
            if (!empty($MAILCOW_APPS)) {
              foreach ($MAILCOW_APPS as $app) {
                if (getenv('SKIP_SOGO') == "y" && preg_match('/^\/SOGo/i', $app['link'])) { continue; }
              ?>
              <div class="media-clearfix">
                <a href="<?=(isset($app['link'])) ? htmlspecialchars($app['link']) : '';?>" role="button" title="<?=(isset($app['description'])) ? htmlspecialchars($app['description']) : '';?>" class="btn btn-primary btn-lg btn-block"><?= htmlspecialchars($app['name']); ?></a>
              </div>
              <?php
              }
            }
            $app_links = customize('get', 'app_links');
            if (!empty($app_links)) {
              foreach ($app_links as $row) {
                foreach ($row as $key => $val) {
              ?>
                <div class="media-clearfix">
                  <a href="<?= htmlspecialchars($val); ?>" role="button" class="btn btn-primary btn-lg btn-block"><?= htmlspecialchars($key); ?></a>
                </div>
              <?php
                }
              }
            } ?>
            </div>
          <?php }
          ?>
        </div>
      </div>
    </div>
    <?php if(!isset($_SESSION['oauth2_request'])) { ?>
    <div class="col-md-offset-3 col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading">
          <a data-toggle="collapse" href="#collapse1"><i class="bi bi-patch-question-fill"></i> <?= $lang['start']['help']; ?></a>
        </div>
        <div id="collapse1" class="panel-collapse collapse">
          <div class="panel-body">
            <?php if ($UI_TEXTS['help_text']) { ?>
            <p><?=$UI_TEXTS['help_text'];?></p>
            <?php } else { ?>
            <p><span style="border-bottom: 1px dotted #999;"><?=$UI_TEXTS['main_name'];?></span></p>
            <p><?= $lang['start']['mailcow_panel_detail']; ?></p>
            <p><span style="border-bottom: 1px dotted #999;"><?=$UI_TEXTS['apps_name'];?></span></p>
            <p><?= $lang['start']['mailcow_apps_detail']; ?></p>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
    <?php } ?>
  </div>
</div><!-- /.container -->
<?php
$js_minifier->add('/web/js/site/index.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
