<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
$_POST = json_decode(file_get_contents('php://input'), true);

function createIfTableDoesntExist($pdo, $debug = false)
{
    try {
        $stmt = $pdo->prepare("CREATE TABLE IF NOT EXISTS `sogo_sso_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` TEXT NOT NULL,
            `token` TEXT NOT NULL
        )");
        $stmt->execute();
    } catch (PDOException $e) {
        if ($debug) echo $e->getMessage();
    }
}

function showTables($pdo)
{
    try {
        $stmt2 = $pdo->query("SHOW TABLES");
        $res = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        var_dump($res);
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
}

function writeTokenToDB($username, $token, $pdo): bool
{
    try {
        $stmt = $pdo->prepare("INSERT INTO `sogo_sso_tokens` (`username`, `token`) VALUES (:username, :token)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':token', $token);
        $success = $stmt->execute();
        return $success;
    } catch (PDOException $e) {
        echo $e->getMessage();
        return false;
    }
}

function generateToken($username): string
{
    return md5(base64_encode($username) . random_bytes(16) . md5(time()));
}

function getApiKey($pdo)
{
    try {
        $stmt = $pdo->prepare("SELECT `api_key` FROM `api` LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn();

    } catch (PDOException $e) {
        return null;
    }
}


if (isset($_POST['username']) && isset($_POST['apikey'])) {

    if ($_POST['apikey'] == getApiKey($pdo)) {
        $username = $_POST['username'];
        $token = generateToken($username);
        createIfTableDoesntExist($pdo);
        writeTokenToDB($username, $token, $pdo);
        echo json_encode(array(
            "success" => true,
            "username"=> $username,
            "token" => $token
        ));
    }
}