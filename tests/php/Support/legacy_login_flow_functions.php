<?php

// Stand-ins for the functions of data/web/inc/functions.inc.php and functions.tfa.inc.php that the
// legacy login flow of functions.auth.inc.php calls, used by BareLocalpartLoginFlowTest only.
// They record what the flow did and answer from LegacyLoginFlow.

use Mailcow\Tests\Support\LegacyLoginFlow;

require_once __DIR__ . '/LegacyLoginFlow.php';

if (!function_exists('get_tfa')) {
    function get_tfa($username, $id = null)
    {
        return ['additional' => LegacyLoginFlow::$tfa[$username] ?? []];
    }
}

if (!function_exists('tfa_exists')) {
    function tfa_exists($username)
    {
        return !empty(LegacyLoginFlow::$tfa[$username]);
    }
}

if (!function_exists('set_sasl_log')) {
    function set_sasl_log($username, $real_rip, $service, $pass = null)
    {
        LegacyLoginFlow::$saslLog[] = [$username, $service];
    }
}

if (!function_exists('clear_session')) {
    function clear_session()
    {
        $_SESSION = [];
    }
}
