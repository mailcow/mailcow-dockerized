<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.admin.inc.php';

protect_route(['admin']);

$role = "admin";
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/restore.inc.php';
