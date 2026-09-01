<?php

/**
 * ALERT_SESSION_KEY is not meant to be changed somewhere else.
 * If you have a good reason to change it, then change it here.
 */
define('ALERT_SESSION_KEY', 'return');


/**
 * Returns a JSON error and terminates the request.
 * 
 * @param string $message Error message to display.
 * @param string $type type of the response (e.g., error).
 * @param int $status_code The HTTP status code.
 * @param bool $should_exit Terminates the request, if true.
 */
function exit_with_api_error(string $message, string $type = 'error', int $status_code = 400, bool $should_exit = true): void
{
    http_response_code($status_code);

    echo json_encode(array(
        'type' => $type,
        'msg'  => $message
    ));

    if ($should_exit === true) {
        exit();
    }
}

/**
 * Clears the session key named `ALERT_SESSION_KEY`.
 * 
 * Session with key named `ALERT_SESSION_KEY` is used to flash messages
 * to the UI. We need to clear it at the very end of the request, therefore,
 * when user refreshes the browser, won't show the same flash again.
 */
function clear_session_alerts(): void
{
    if (isset($_SESSION[ALERT_SESSION_KEY])) {
        unset($_SESSION[ALERT_SESSION_KEY]);
    }
}

/**
 * Appends a flash message to the session named `ALERT_SESSION_KEY`.
 * 
 * @param string $type The alert type of bootstrap css library (e.g., 'danger', 'success', 'warning').
 * @param string $message The alert message to display in UI.
 */
function add_session_alert(string $type, string $message): void
{
    $_SESSION[ALERT_SESSION_KEY][] = [
        'type' => $type,
        'msg'  => $message
    ];
}
