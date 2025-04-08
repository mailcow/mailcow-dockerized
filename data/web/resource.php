<?php

if (!isset($_GET['file']) ) {
    http_response_code(404);
    exit;
}
$pathinfo = pathinfo($_GET['file']);

if (!array_key_exists('extension', $pathinfo)) {
    http_response_code(404);
    exit;
}
$extension = strtolower($pathinfo['extension']);

$filepath = '/tmp/' . $pathinfo['basename'];
$content = '';

if (file_exists($filepath)) {
    $secondsToCache = 31536000;
    $expires = gmdate('D, d M Y H:i:s', time() + $secondsToCache) . ' GMT';

    if ($extension === 'js') {
        header('Content-Type: application/javascript');
    } elseif ($extension === 'css') {
        header('Content-Type: text/css');
    } else {
        //currently just css and js should be supported!
        exit();
    }

    header("Expires: $expires");
    header('Pragma: cache');
    header('Cache-Control: max-age=' . $secondsToCache);
    $content = file_get_contents($filepath);
}

echo $content;
