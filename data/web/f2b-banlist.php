<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_GET['id'])) {
    header('Content-Type: text/plain');
    echo fail2ban('banlist', 'get', $_GET['id']);
} else {
    header('HTTP/1.1 404 Not Found');
    exit;
}
