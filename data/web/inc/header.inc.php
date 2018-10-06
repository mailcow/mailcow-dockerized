<!DOCTYPE html>
<html lang="<?= $_SESSION['mailcow_locale'] ?>">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#F5D76E"/>
  <meta http-equiv="Referrer-Policy" content="same-origin">
  <title><?=$UI_TEXTS['title_name'];?></title>
  <!--[if lt IE 9]>
    <script src="/js/html5shiv.min.js"></script>
    <script src="/js/respond.min.js"></script>
  <![endif]-->
  <script src="/js/jquery-1.12.4.min.js"></script>
  <?php if (strtolower(trim($DEFAULT_THEME)) != "lumen"): ?>
  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.7/<?= strtolower(trim($DEFAULT_THEME)); ?>/bootstrap.min.css">
  <?php else: ?>
  <link rel="stylesheet" href="/css/bootstrap.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="/css/breakpoint.min.css">
  <link rel="stylesheet" href="/css/bootstrap-select.min.css">
  <link rel="stylesheet" href="/css/bootstrap-slider.min.css">
  <link rel="stylesheet" href="/css/bootstrap-switch.min.css">
  <link rel="stylesheet" href="/css/footable.bootstrap.min.css">
  <link rel="stylesheet" href="/inc/languages.min.css">
  <link rel="stylesheet" href="/css/mailcow.css">
  <link rel="stylesheet" href="/css/animate.min.css">
  <link rel="stylesheet" href="/css/numberedtextarea.min.css">
  <link rel="stylesheet" href="/css/jquery.jqplot.min.css">
  <?= (preg_match("/mailbox/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/mailbox.css">' : null; ?>
  <?= (preg_match("/admin/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/admin.css">' : null; ?>
  <?= (preg_match("/user/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/user.css">' : null; ?>
  <?= (preg_match("/edit/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/edit.css">' : null; ?>
  <?= (preg_match("/quarantine/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/quarantine.css">' : null; ?>
  <?= (preg_match("/debug/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/debug.css">' : null; ?>
  <link rel="shortcut icon" href="/favicon.png" type="image/png">
  <link rel="icon" href="/favicon.png" type="image/png">
</head>
<body id="top">
  <div class="overlay"></div>
  <nav class="navbar navbar-default navbar-fixed-top" role="navigation">
    <div class="container-fluid">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="/"><img alt="mailcow-logo" src="<?=($main_logo = customize('get', 'main_logo')) ? $main_logo : '/img/cow_mailcow.svg';?>"></a>
      </div>
      <div id="navbar" class="navbar-collapse collapse">
        <ul class="nav navbar-nav navbar-right">
          <?php
          if (isset($_SESSION['mailcow_locale'])) {
          ?>
          <li class="dropdown<?=(isset($_SESSION['mailcow_locale']) && count($AVAILABLE_LANGUAGES) === 1) ? ' lang-link-disabled"' : '' ?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><span class="lang-sm lang-lbl" lang="<?= $_SESSION['mailcow_locale']; ?>"></span><span class="caret"></span></a>
            <ul class="dropdown-menu" role="menu">
              <?php
              foreach ($AVAILABLE_LANGUAGES as $language) {
              ?>
              <li<?= ($_SESSION['mailcow_locale'] == $language) ? ' class="active"' : ''; ?>><a href="?<?= http_build_query(array_merge($_GET, array('lang' => $language))); ?>"><span class="lang-xs lang-lbl-full" lang="<?= $language; ?>"></span></a></li>
              <?php
              }
              ?>
            </ul>
          </li>
          <?php
          }
          if (isset($_SESSION['mailcow_cc_role'])) {
          ?>
          <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><?= $lang['header']['mailcow_settings']; ?> <span class="caret"></span></a>
            <ul class="dropdown-menu" role="menu">
              <?php
              if (isset($_SESSION['mailcow_cc_role'])) {
                if ($_SESSION['mailcow_cc_role'] == 'admin') {
                ?>
                  <li<?= (preg_match("/admin/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/admin"><?= $lang['header']['administration']; ?></a></li>
                  <li<?= (preg_match("/debug/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/debug"><?= $lang['header']['debug']; ?></a></li>
                <?php
                }
                if ($_SESSION['mailcow_cc_role'] == 'admin' || $_SESSION['mailcow_cc_role'] == 'domainadmin') {
                ?>
                  <li<?= (preg_match("/mailbox/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/mailbox"><?= $lang['header']['mailboxes']; ?></a></li>
                <?php
                }
                if ($_SESSION['mailcow_cc_role'] != 'admin') {
                ?>
                  <li<?= (preg_match("/user/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/user"><?= $lang['header']['user_settings']; ?></a></li>
                <?php
                }
              }
              ?>
            </ul>
          </li>
          <?php
          if (isset($_SESSION['mailcow_cc_role'])) {
          ?>
          <li<?= (preg_match("/quarantine/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/quarantine"><span class="glyphicon glyphicon-briefcase"></span> <?= $lang['header']['quarantine']; ?></a></li>
          <?php
          }
          if ($_SESSION['mailcow_cc_role'] == 'admin') {
          ?>
          <li><a href data-toggle="modal" data-container="sogo-mailcow" data-target="#RestartContainer"><span class="glyphicon glyphicon-refresh"></span> <?= $lang['header']['restart_sogo']; ?></a></li>
          <?php
          }
          ?>
          <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><span class="glyphicon glyphicon-link"></span> Apps <span class="caret"></span></a>
            <ul class="dropdown-menu" role="menu">
            <?php
            foreach ($MAILCOW_APPS as $app):
            ?>
              <li title="<?= htmlspecialchars($app['description']); ?>"><a href="<?= htmlspecialchars($app['link']); ?>"><?= htmlspecialchars($app['name']); ?></a></li>
            <?php
            endforeach;
            $app_links = customize('get', 'app_links');
            foreach ($app_links as $row) {
              foreach ($row as $key => $val):
            ?>
              <li><a href="<?= htmlspecialchars($val); ?>"><?= htmlspecialchars($key); ?></a></li>
            <?php
              endforeach;
            }
            ?>
            </ul>
          </li>
          <?php
          }
          if (!isset($_SESSION['dual-login']) && isset($_SESSION['mailcow_cc_username'])):
          ?>
            <li class="logged-in-as"><a href="#" onclick="logout.submit()"><b class="username-lia"><?= htmlspecialchars($_SESSION['mailcow_cc_username']); ?></b> <span class="glyphicon glyphicon-log-out"></span></a></li>
          <?php
          elseif (isset($_SESSION['dual-login'])):
          ?>
            <li class="logged-in-as"><a href="#" onclick="logout.submit()"><b class="username-lia"><?= htmlspecialchars($_SESSION['mailcow_cc_username']); ?> <span class="text-info">(<?= htmlspecialchars($_SESSION['dual-login']['username']); ?>)</span> </b><span class="glyphicon glyphicon-log-out"></span></a></li>
          <?php
          endif;
          ?>
        </ul>
      </div><!--/.nav-collapse -->
    </div><!--/.container-fluid -->
  </nav>
  <form action="/" method="post" id="logout"><input type="hidden" name="logout"></form>
