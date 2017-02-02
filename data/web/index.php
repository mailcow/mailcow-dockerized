<?php
require_once("inc/prerequisites.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
	header('Location: /admin.php');
	exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "domainadmin") {
	header('Location: /mailbox.php');
	exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "user") {
	header('Location: /user.php');
	exit();
}
require_once("inc/header.inc.php");
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
?>
<div class="container">
	<div class="row">
		<div class="col-md-offset-3 col-md-6">
			<div class="panel panel-default">
				<div class="panel-heading"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?=$lang['login']['login'];?></div>
				<div class="panel-body">
				  <center><img style="max-width:250px" src="/img/cow_mailcow.svg" alt="mailcow"></center>
					<legend>mailcow UI</legend>
						<form method="post" autofill="off">
						<div class="form-group">
							<label class="sr-only" for="login_user"><?=$lang['login']['username'];?></label>
							<div class="input-group">
								<div class="input-group-addon"><i class="glyphicon glyphicon-user"></i></div>
								<input name="login_user" autocorrect="off" autocapitalize="none" type="name" id="login_user" class="form-control" placeholder="<?=$lang['login']['username'];?>" required="" autofocus="">
							</div>
						</div>
						<div class="form-group">
							<label class="sr-only" for="pass_user"><?=$lang['login']['password'];?></label>
							<div class="input-group">
								<div class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></div>
								<input name="pass_user" type="password" id="pass_user" class="form-control" placeholder="<?=$lang['login']['password'];?>" required="">
							</div>
						</div>
						<div class="form-group">
							<button type="submit" class="btn btn-success" value="Login"><?=$lang['login']['login'];?></button>
							<div class="btn-group pull-right">
								<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									<span class="lang-sm lang-lbl" lang="<?=$_SESSION['mailcow_locale'];?>"></span> <span class="caret"></span>
								</button>
								<ul class="dropdown-menu">
									<li <?=($_SESSION['mailcow_locale'] == 'de') ? 'class="active"' : ''?>><a href="?<?= http_build_query(array_merge($_GET, array("lang" => "de"))) ?>"><span class="lang-xs lang-lbl-full" lang="de"></span></a></li>
									<li <?=($_SESSION['mailcow_locale'] == 'en') ? 'class="active"' : ''?>><a href="?<?= http_build_query(array_merge($_GET, array("lang" => "en"))) ?>"><span class="lang-xs lang-lbl-full" lang="en"></span></a></li>
									<li <?=($_SESSION['mailcow_locale'] == 'es') ? 'class="active"' : ''?>><a href="?<?= http_build_query(array_merge($_GET, array("lang" => "es"))) ?>"><span class="lang-xs lang-lbl-full" lang="es"></span></a></li>
									<li <?=($_SESSION['mailcow_locale'] == 'nl') ? 'class="active"' : ''?>><a href="?<?= http_build_query(array_merge($_GET, array("lang" => "nl"))) ?>"><span class="lang-xs lang-lbl-full" lang="nl"></span></a></li>
									<li <?=($_SESSION['mailcow_locale'] == 'pt') ? 'class="active"' : ''?>><a href="?<?= http_build_query(array_merge($_GET, array("lang" => "pt"))) ?>"><span class="lang-xs lang-lbl-full" lang="pt"></span></a></li>
								</ul>
							</div>
						</div>
						</form>
						<?php
						if (isset($_SESSION['ldelay']) && $_SESSION['ldelay'] != "0"):
						?>
						<p><div class="alert alert-info"><?=sprintf($lang['login']['delayed'], $_SESSION['ldelay']);?></b></div></p>
						<?php
						endif;
						?>
					<legend>mailcow Apps</legend>
					<a href="/SOGo/" role="button" class="btn btn-lg btn-default"><?=$lang['start']['start_sogo'];?></a>
				</div>
			</div>
		</div>
		<div class="col-md-offset-3 col-md-6">
			<div class="panel panel-default" style="">
				<div class="panel-heading">
					<a data-toggle="collapse" href="#collapse1"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span> <?=$lang['start']['help'];?></a>
				</div>
				<div id="collapse1" class="panel-collapse collapse">
					<div class="panel-body">
						<p><span style="border-bottom: 1px dotted #999">mailcow UI</span></p>
						<p><?=$lang['start']['mailcow_panel_detail'];?></p>
						<p><span style="border-bottom: 1px dotted #999">mailcow Apps</span></p>
						<p><?=$lang['start']['mailcow_apps_detail'];?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div> <!-- /container -->
<script src="js/index.js"></script>
<?php
require_once("inc/footer.inc.php");
?>
