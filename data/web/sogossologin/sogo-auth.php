<?php

session_start();
$session_var_user_allowed = 'sogo-sso-user-allowed';
$session_var_pass = 'sogo-sso-pass';


function checkTokenExists($pdo, $username, $token): bool
{
    try {

        $stmt = $pdo->prepare("SELECT * FROM `sogo_sso_tokens` WHERE `username` = :username AND `token` = :token");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':token', $token);

        $stmt->execute();

        $res = $stmt->fetchAll();
        if(count($res) == 1){
            return true;
        }else{
            return false;
        }
    } catch (PDOException $e) {
        return false;
    }
}






if(isset($_GET['email']) && $_GET['token']){
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
    if(checkTokenExists($pdo, $_GET['email'], $_GET['token'])){
        try {
            $sogo_sso_pass = file_get_contents("/etc/sogo-sso/sogo-sso.pass");
            $_SESSION[$session_var_user_allowed][] = $_GET['email'];
            $_SESSION[$session_var_pass] = $sogo_sso_pass;
            $stmt = $pdo->prepare("REPLACE INTO sasl_log (`service`, `app_password`, `username`, `real_rip`) VALUES ('SSO', 0, :username, :remote_addr)");
            $stmt->execute(array(
                ':username' => $_GET['email'],
                ':remote_addr' => (isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'])
            ));
        }catch (PDOException $e){
            echo $e->getMessage();
        }


        header("Location: /SOGo/so/{$_GET['email']}");
    }else{
        http_response_code(401);
    }
}

// if username is empty, SOGo will use the normal login methods / login form
header("X-User: ");
header("X-Auth: ");
header("X-Auth-Type: ");