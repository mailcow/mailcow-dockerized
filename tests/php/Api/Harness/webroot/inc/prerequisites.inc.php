<?php

// Stand-in for data/web/inc/prerequisites.inc.php in the API harness (runner.php): the session of the request
// instead of the API key login, recorders for the database and Redis, the globals the test sets.

$pdo = new \ApiHarness\Recorder('pdo');
$redis = new \ApiHarness\Recorder('redis');
$_SESSION = $GLOBALS['__harness_session'] ?? [];
foreach ($GLOBALS['__harness_globals'] ?? [] as $name => $value) {
    $GLOBALS[$name] = $value;
}
