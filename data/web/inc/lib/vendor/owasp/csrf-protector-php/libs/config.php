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

function get_trusted_hostname() {
  $js_path = "/inc/lib/vendor/owasp/csrf-protector-php/js/csrfprotector.js";
  if ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https") || isset($_SERVER['HTTPS'])) {
    $is_scheme = "https://";
  }
  else {
    $is_scheme = "http://";
  }
  if (isset(explode(':', $_SERVER['HTTP_HOST'])[1])) {
    $is_port = intval(explode(':', $_SERVER['HTTP_HOST'])[1]);
    if (filter_var($is_port, FILTER_VALIDATE_INT, array("options" => array("min_range" =>1, "max_range" => 65535))) === false) {
      return false;
    }
  }
  if (!isset($is_port) || $is_port == 0) {
    $is_port = ($is_scheme == "https://") ? 443 : 80;
  }
  return $is_scheme . $GLOBALS['mailcow_hostname'] . ':' . $is_port . $js_path;
}

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
	"jsUrl" => get_trusted_hostname(),
	"tokenLength" => 10,
	"secureCookie" => false,
	"disabledJavascriptMessage" => "",
	 "verifyGetFor" => array()
);