<?php

/**
 * currently disabled: we could add auth_request to ningx sogo_eas.template
 * but this seems to be not required with the postfix allow_real_nets option
 */
/*
if (substr($_SERVER['HTTP_X_ORIGINAL_URI'], 0, 28) === "/Microsoft-Server-ActiveSync") {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

    $server=print_r($_SERVER, true);
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    $login_check = check_login($username, $password);
    if ($login_check !== 'user') {
        header('HTTP/1.0 401 Unauthorized');
        echo 'Invalid login';
        exit;
    } else {
        echo 'Login OK';
        exit;
    }
} else {
    // other code
}
*/

$ALLOW_ADMIN_EMAIL_LOGIN = (preg_match(
    "/^([yY][eE][sS]|[yY])+$/",
    $_ENV["ALLOW_ADMIN_EMAIL_LOGIN"]
));

$session_variable = 'sogo-sso-user';

if (!$ALLOW_ADMIN_EMAIL_LOGIN) {
    header("Location: /");
    exit;
} else if (isset($_GET['login'])) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
    if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['acl']['login_as'] == "1") {
        $login = html_entity_decode(rawurldecode($_GET["login"]));
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            if (!empty(mailbox('get', 'mailbox_details', $login))) {
                $_SESSION[$session_variable] = $login;
                header("Location: /SOGo/");
                exit;
            }
        }
    }
    header("Location: /");
    exit;
} else {
    // this is an nginx auth_request call, we check for an existing sogo-sso-user session variable
    session_start();
    $username = "";
    if (isset($_SESSION[$session_variable]) && filter_var($_SESSION[$session_variable], FILTER_VALIDATE_EMAIL)) {
        $username = $_SESSION[$session_variable];
    }
    // if username is empty, SOGo will display the normal login form
    header("X-Username: $username");
    exit;
}
