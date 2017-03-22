<?php
//ini_set("session.cookie_secure", 1);
//ini_set("session.cookie_httponly", 1);
session_start();
if (isset($_POST["logout"])) {
  if (isset($_SESSION["dual-login"])) {
    $_SESSION["mailcow_cc_username"] = $_SESSION["dual-login"]["username"];
    $_SESSION["mailcow_cc_role"] = $_SESSION["dual-login"]["role"];
    unset($_SESSION["dual-login"]);
  }
  else {
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(),'',0,'/');
  }
}

require_once 'inc/vars.inc.php';
if (file_exists('./inc/vars.local.inc.php')) {
	include_once 'inc/vars.local.inc.php';
}

// Yubi OTP API
require_once 'inc/lib/Yubico.php';

// U2F API
require_once 'inc/lib/U2F.php';
$u2f = new u2flib_server\U2F('https://' . $_SERVER['SERVER_NAME']);

// PDO
$dsn = "$database_type:host=$database_host;dbname=$database_name";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
	$pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
?>
<center style='font-family: "Lucida Sans Unicode", "Lucida Grande", Verdana, Arial, Helvetica, sans-serif;'>🐮 Connection failed, database may be in warm-up state, please try again later.<br /><br />The following error was reported:<br/>  <?=$e->getMessage();?></center>
<?php
exit;
}

$_SESSION['mailcow_locale'] = strtolower(trim($DEFAULT_LANG));
setcookie('language', $DEFAULT_LANG);
if (isset($_COOKIE['language'])) {
	switch ($_COOKIE['language']) {
		case "de":
			$_SESSION['mailcow_locale'] = 'de';
			setcookie('language', 'de');
		break;
		case "en":
			$_SESSION['mailcow_locale'] = 'en';
			setcookie('language', 'en');
		break;
		case "es":
			$_SESSION['mailcow_locale'] = 'es';
			setcookie('language', 'es');
		break;
		case "nl":
			$_SESSION['mailcow_locale'] = 'nl';
			setcookie('language', 'nl');
		break;
		case "pt":
			$_SESSION['mailcow_locale'] = 'pt';
			setcookie('language', 'pt');
		break;
    case "ru":
			$_SESSION['mailcow_locale'] = 'ru';
			setcookie('language', 'ru');
		break;
	}
}
if (isset($_GET['lang'])) {
	switch ($_GET['lang']) {
		case "de":
			$_SESSION['mailcow_locale'] = 'de';
			setcookie('language', 'de');
		break;
		case "en":
			$_SESSION['mailcow_locale'] = 'en';
			setcookie('language', 'en');
		break;
		case "es":
			$_SESSION['mailcow_locale'] = 'es';
			setcookie('language', 'es');
		break;
		case "nl":
			$_SESSION['mailcow_locale'] = 'nl';
			setcookie('language', 'nl');
		break;
		case "pt":
			$_SESSION['mailcow_locale'] = 'pt';
			setcookie('language', 'pt');
		break;
		case "ru":
			$_SESSION['mailcow_locale'] = 'ru';
			setcookie('language', 'ru');
		break;
	}
}
require_once 'lang/lang.en.php';
include 'lang/lang.'.$_SESSION['mailcow_locale'].'.php';
require_once 'inc/functions.inc.php';
require_once 'inc/triggers.inc.php';
(!isset($_SESSION['mailcow_cc_username'])) ? init_db_schema() : null;
