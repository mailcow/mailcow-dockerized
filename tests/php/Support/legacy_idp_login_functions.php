<?php

// Stand-ins for the functions of data/web/inc/functions.inc.php and functions.mailbox.inc.php that the
// IdP login of functions.auth.inc.php calls, used by IdpFirstLoginTest only. mailbox() records the call
// and creates or edits the row the way mailbox('add'|'edit', 'mailbox_from_template') would.

if (!function_exists('mailbox')) {
    function mailbox($action, $type, $data = null)
    {
        $GLOBALS['idp_login_mailbox_calls'][] = [$action, $type, $data];
        if ($action === 'add' && $type === 'mailbox_from_template') {
            $username = $data['local_part'] . '@' . $data['domain'];
            $GLOBALS['pdo']->prepare("INSERT INTO `mailbox` (`username`, `domain`, `authsource`, `attributes`) VALUES (?, ?, ?, ?)")
                ->execute([$username, $data['domain'], $data['authsource'], json_encode(['eas_access' => '1', 'dav_access' => '1'])]);
        }
        return true;
    }
}

if (!function_exists('clear_session')) {
    function clear_session()
    {
        $_SESSION = [];
    }
}
