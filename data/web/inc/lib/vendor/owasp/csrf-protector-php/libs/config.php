<?php
/**
 * Configuration file for CSRF Protector
 * Necessary configurations are (library would throw exception otherwise)
 * ---- logDirectory
 * ---- failedAuthAction
 * ---- jsPath
 * ---- jsUrl
 * ---- tokenLength
 */

return array(
	"CSRFP_TOKEN" => "MAILCOW_CSRF",
	"logDirectory" => "../log",
	"failedAuthAction" => array(
		"GET" => 1,
		"POST" => 1),
	"errorRedirectionPage" => "",
	"customErrorMessage" => "",
	"jsPath" => "../js/csrfprotector.js",
  // Fetching IS_HTTPS from sessions handler
	"jsUrl" => (($GLOBALS['IS_HTTPS'] === true) ? 'https://' : 'http://') . $GLOBALS['mailcow_hostname'] . ':' . intval(explode(':', $_SERVER['HTTP_HOST'])[1]) . "/inc/lib/vendor/owasp/csrf-protector-php/js/csrfprotector.js",
	"tokenLength" => 10,
	"secureCookie" => false,
	"disabledJavascriptMessage" => "This site attempts to protect users against <a href=\"https://www.owasp.org/index.php/Cross-Site_Request_Forgery_%28CSRF%29\">
	Cross-Site Request Forgeries </a> attacks. In order to do so, you must have JavaScript enabled in your web browser otherwise this site will fail to work correctly for you.
	 See details of your web browser for how to enable JavaScript.",
	 "verifyGetFor" => array()
);