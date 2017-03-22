<!DOCTYPE html>
<html lang="<?= $_SESSION['mailcow_locale'] ?>">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>mailcow UI</title>
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.0/jquery.min.js" integrity="sha384-XxcvoeNF5V0ZfksTnV+bejnCsJjOOIzN6UVwF85WBsAnU3zeYh5bloN+L4WLgeNE" crossorigin="anonymous"></script>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.6/<?=strtolower(trim($DEFAULT_THEME));?>/bootstrap.min.css">
<link rel="stylesheet" href="/css/bootstrap-select.min.css">
<link rel="stylesheet" href="/css/bootstrap-slider.min.css">
<link rel="stylesheet" href="/css/bootstrap-switch.min.css">
<link rel="stylesheet" href="/css/footable.bootstrap.min.css">
<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,700&subset=latin,latin-ext">
<link rel="stylesheet" href="/inc/languages.min.css">
<link rel="stylesheet" href="/css/mailcow.css">
<link rel="stylesheet" href="/css/tables.css">
<?=(preg_match("/mailbox.php/i", $_SERVER['REQUEST_URI'])) ? '<link rel="stylesheet" href="/css/mailbox.css">' : null;?>
<link rel="shortcut icon" href="/favicon.png" type="image/png">
<link rel="icon" href="/favicon.png" type="image/png">
</head>
<body style="padding-top:70px">
<nav class="navbar navbar-default navbar-fixed-top"  role="navigation">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/"><img height="32" alt="mailcow-logo" style="margin-top:-5px;" src="/img/cow_mailcow.svg" /></a>
		</div>
		<div id="navbar" class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<?php
				if (isset($_SESSION['mailcow_locale'])) {
				?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><span class="lang-sm lang-lbl" lang="<?=$_SESSION['mailcow_locale'];?>"></span><span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
						<li <?=($_SESSION['mailcow_locale'] == 'de') ? 'class="active"' : ''?>> <a href="?<?= http_build_query(array_merge($_GET, array("lang" => "de"))) ?>"><span class="lang-xs lang-lbl-full" lang="de"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'en') ? 'class="active"' : ''?>> <a href="?<?= http_build_query(array_merge($_GET, array("lang" => "en"))) ?>"><span class="lang-xs lang-lbl-full" lang="en"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'es') ? 'class="active"' : ''?>> <a href="?<?= http_build_query(array_merge($_GET, array("lang" => "es"))) ?>"><span class="lang-xs lang-lbl-full" lang="es"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'nl') ? 'class="active"' : ''?>> <a href="?<?= http_build_query(array_merge($_GET, array("lang" => "nl"))) ?>"><span class="lang-xs lang-lbl-full" lang="nl"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'pt') ? 'class="active"' : ''?>> <a href="?<?= http_build_query(array_merge($_GET, array("lang" => "pt"))) ?>"><span class="lang-xs lang-lbl-full" lang="pt"></span></a></li>
						<li <?=($_SESSION['mailcow_locale'] == 'ru') ? 'class="active"' : ''?>> <a href="?<?= http_build_query(array_merge($_GET, array("lang" => "ru"))) ?>"><span class="lang-xs lang-lbl-full" lang="ru"></span></a></li>
					</ul>
				</li>
				<?php
				}
				if (isset($_SESSION['mailcow_cc_role'])) {
				?>
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><?=$lang['header']['mailcow_settings'];?><span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
					<?php
						if (isset($_SESSION['mailcow_cc_role'])) {
							if ($_SESSION['mailcow_cc_role'] == "admin") {
							?>
								<li <?=(preg_match("/admin/i", $_SERVER['REQUEST_URI'])) ? 'class="active"' : ''?>><a href="/admin.php"><?=$lang['header']['administration'];?></a></li>
							<?php
							}
							if ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin") {
							?>
								<li <?=(preg_match("/mailbox/i", $_SERVER['REQUEST_URI'])) ? 'class="active"' : ''?>><a href="/mailbox.php"><?=$lang['header']['mailboxes'];?></a></li>
							<?php
							}
							if ($_SESSION['mailcow_cc_role'] != "admin") {
							?>
								<li <?=(preg_match("/user/i", $_SERVER['REQUEST_URI'])) ? 'class="active"' : ''?>><a href="/user.php"><?=$lang['header']['user_settings'];?></a></li>
							<?php
              }
						}
						?>
					</ul>
				</li>
				<?php
				if ($_SESSION['mailcow_cc_role'] == "admin"):
				?>
				<li><a href data-toggle="modal" data-target="#RestartSOGo"><span style="font-size:12px" class="glyphicon glyphicon-refresh" aria-hidden="true"></span> <?=$lang['header']['restart_sogo'];?></a></li>
				<?php
				endif;
				?>
					<?php
				}
				if (!isset($_SESSION["dual-login"]) && isset($_SESSION['mailcow_cc_username'])):
				?>
					<li><a style="border-left:1px solid #E7E7E7" href="#" onclick="logout.submit()"><?=sprintf($lang['header']['logged_in_as_logout'], $_SESSION['mailcow_cc_username']);?></a></li>
				<?php
				elseif (isset($_SESSION["dual-login"])):
				?>
					<li><a style="border-left:1px solid #E7E7E7" href="#" onclick="logout.submit()"><?=sprintf($lang['header']['logged_in_as_logout_dual'], $_SESSION['mailcow_cc_username'], $_SESSION["dual-login"]["username"]);?></a></li>
				<?php
				endif;
				?>
			</ul>
		</div><!--/.nav-collapse -->
	</div><!--/.container-fluid -->
</nav>
<form action="/" method="post" id="logout"><input type="hidden" name="logout"></form>
