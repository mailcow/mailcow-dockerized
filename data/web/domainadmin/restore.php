<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/triggers.domainadmin.inc.php';

protect_route(['domainadmin']);

$role = "domainadmin";
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/restore.inc.php';
