<!DOCTYPE html>
<html lang="<?= $_SESSION['mailcow_locale'] ?>">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>mailcow UI</title>
<!--[if lt IE 9]>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js" integrity="sha256-3Jy/GbSLrg0o9y5Z5n1uw0qxZECH7C6OQpVBgNFYa0g=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js" integrity="sha256-g6iAfvZp+nDQ2TdTR/VVKJf3bGro4ub5fvWSWVRi2NE=" crossorigin="anonymous"></script>
<![endif]-->
<script src="/js/jquery-1.12.4.min.js"></script>
<?php if (strtolower(trim($DEFAULT_THEME)) != "lumen"): ?>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.7/<?= strtolower(trim($DEFAULT_THEME)); ?>/bootstrap.min.css">
<?php else: ?>
<link rel="stylesheet" href="/css/bootstrap.min.css">
<?php endif; ?>
<link rel="stylesheet" href="/css/bootstrap-select.min.css">
<link rel="stylesheet" href="/css/bootstrap-slider.min.css">
<link rel="stylesheet" href="/css/bootstrap-switch.min.css">
<link rel="stylesheet" href="/css/footable.bootstrap.min.css">
<link rel="stylesheet" href="/inc/languages.min.css">
<link rel="stylesheet" href="/css/mailcow.css">
<link rel="stylesheet" href="/css/animate.min.css">
<?= (preg_match("/mailbox.php/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/mailbox.css">' : null; ?>
<?= (preg_match("/admin.php/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/admin.css">' : null; ?>
<?= (preg_match("/user.php/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/user.css">' : null; ?>
<?= (preg_match("/edit.php/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/edit.css">' : null; ?>
<link rel="shortcut icon" href="/favicon.png" type="image/png">
<link rel="icon" href="/favicon.png" type="image/png">
</head>
<body style="padding-top: 70px;">
<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
  <div class="container-fluid">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="/"><img height="32" alt="mailcow-logo" style="margin-top: -5px;" src="/img/cow_mailcow.svg"></a>
    </div>
    <div id="navbar" class="navbar-collapse collapse">
      <ul class="nav navbar-nav navbar-right">
        <?php
        if (isset($_SESSION['mailcow_locale'])) {
        ?>
        <li class="dropdown">
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
                <li<?= (preg_match("/admin/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/admin.php"><?= $lang['header']['administration']; ?></a></li>
              <?php
              }
              if ($_SESSION['mailcow_cc_role'] == 'admin' || $_SESSION['mailcow_cc_role'] == 'domainadmin') {
              ?>
                <li<?= (preg_match("/mailbox/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/mailbox.php"><?= $lang['header']['mailboxes']; ?></a></li>
              <?php
              }
              if ($_SESSION['mailcow_cc_role'] != 'admin') {
              ?>
                <li<?= (preg_match("/user/i", $_SERVER['REQUEST_URI'])) ? ' class="active"' : ''; ?>><a href="/user.php"><?= $lang['header']['user_settings']; ?></a></li>
              <?php
              }
            }
            ?>
          </ul>
        </li>
        <?php
        if ($_SESSION['mailcow_cc_role'] == 'admin') {
        ?>
        <li><a href data-toggle="modal" data-target="#RestartSOGo"><span style="font-size: 12px;" class="glyphicon glyphicon-refresh" aria-hidden="true"></span> <?= $lang['header']['restart_sogo']; ?></a></li>
        <?php
        }
        ?>
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><span class="glyphicon glyphicon-link" aria-hidden="true"></span> Apps <span class="caret"></span></a>
          <ul class="dropdown-menu" role="menu">
          <?php
          foreach ($MAILCOW_APPS as $app):
          ?>
            <li title="<?= htmlspecialchars($app['description']); ?>"><a href="<?= htmlspecialchars($app['link']); ?>"><?= htmlspecialchars($app['name']); ?></a></li>
          <?php
          endforeach;
          ?>
          </ul>
        </li>
        <?php
        }
        if (!isset($_SESSION['dual-login']) && isset($_SESSION['mailcow_cc_username'])):
        ?>
          <li><a href="#" style="border-left: 1px solid #E7E7E7;" onclick="logout.submit()"><?= sprintf($lang['header']['logged_in_as_logout'], $_SESSION['mailcow_cc_username']); ?></a></li>
        <?php
        elseif (isset($_SESSION['dual-login'])):
        ?>
          <li><a href="#" style="border-left: 1px solid #E7E7E7;" onclick="logout.submit()"><?= sprintf($lang['header']['logged_in_as_logout_dual'], $_SESSION['mailcow_cc_username'], $_SESSION['dual-login']['username']); ?></a></li>
        <?php
        endif;
        ?>
      </ul>
    </div><!--/.nav-collapse -->
  </div><!--/.container-fluid -->
</nav>
<form action="/" method="post" id="logout"><input type="hidden" name="logout"></form>
