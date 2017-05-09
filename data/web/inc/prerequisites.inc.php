<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/sessions.inc.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/vars.inc.php';
if (file_exists('./inc/vars.local.inc.php')) {
	include_once 'inc/vars.local.inc.php';
}

// Yubi OTP API
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/Yubico.php';

// Autoload composer
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lib/vendor/autoload.php';

// U2F API + T/HOTP API
$u2f = new u2flib_server\U2F('https://' . $_SERVER['HTTP_HOST']);
$tfa = new RobThree\Auth\TwoFactorAuth('mailcow UI');

// Redis
$redis = new Redis();
$redis->connect('redis-mailcow', 6379);

// PDO
// Calculate offset
$now = new DateTime();
$mins = $now->getOffset() / 60;
$sgn = ($mins < 0 ? -1 : 1);
$mins = abs($mins);
$hrs = floor($mins / 60);
$mins -= $hrs * 60;
$offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

$dsn = $database_type . ":host=" . $database_host . ";dbname=" . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . $offset . "'",
];
try {
	$pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
?>
<center style='font-family: "Lucida Sans Unicode", "Lucida Grande", Verdana, Arial, Helvetica, sans-serif;'>?? Connection failed, database may be in warm-up state, please try again later.<br /><br />The following error was reported:<br/>  <?=$e->getMessage();?></center>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/lang/lang.en.php';
include $_SERVER['DOCUMENT_ROOT'] . '/lang/lang.'.$_SESSION['mailcow_locale'].'.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/functions.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/init_db.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.inc.php';
init_db_schema();
