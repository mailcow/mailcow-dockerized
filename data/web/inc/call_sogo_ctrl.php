<?php
session_start();
$AuthUsers = array("admin");
if (!isset($_SESSION['mailcow_cc_role']) OR !in_array($_SESSION['mailcow_cc_role'], $AuthUsers)) {
	echo "Not allowed." . PHP_EOL;
	exit();
}
if ($_GET['ACTION'] == "start") {
	$request = xmlrpc_encode_request("supervisor.startProcess", 'reconf-domains', array('encoding'=>'utf-8'));
	$context = stream_context_create(array('http' => array(
	'method' => "POST",
	'header' => "Content-Length: " . strlen($request),
	'content' => $request
	)));
	$file = @file_get_contents("http://sogo:9191/RPC2", false, $context) or die("Cannot connect to $remote_server:$listener_port");
	$response = xmlrpc_decode($file);
	if (isset($response['faultString'])) {
		echo '<b><span class="pull-right text-warning">' . $response['faultString'] . '</span></b>';
	}
	else {
    sleep(4);
    $request = xmlrpc_encode_request("supervisor.startProcess", 'sogo', array('encoding'=>'utf-8'));
    $context = stream_context_create(array('http' => array(
    'method' => "POST",
    'header' => "Content-Length: " . strlen($request),
    'content' => $request
    )));
    $file = @file_get_contents("http://sogo:9191/RPC2", false, $context) or die("Cannot connect to $remote_server:$listener_port");
    $response = xmlrpc_decode($file);
    if (isset($response['faultString'])) {
      echo '<b><span class="pull-right text-warning">' . $response['faultString'] . '</span></b>';
    }
    else {
      echo '<b><span class="pull-right text-success">OK</span></b>';
    }
	}
}
elseif ($_GET['ACTION'] == "stop") {
	$request = xmlrpc_encode_request("supervisor.stopProcess", 'sogo', array('encoding'=>'utf-8'));
	$context = stream_context_create(array('http' => array(
	'method' => "POST",
	'header' => "Content-Length: " . strlen($request),
	'content' => $request
	)));
	$file = @file_get_contents("http://sogo:9191/RPC2", false, $context) or die("Cannot connect to $remote_server:$listener_port");
	$response = xmlrpc_decode($file);
	if (isset($response['faultString'])) {
		echo '<b><span class="pull-right text-warning">' . $response['faultString'] . '</span></b>';
	}
	else {
    sleep(1);
    $request = xmlrpc_encode_request("supervisor.stopProcess", 'reconf-domains', array('encoding'=>'utf-8'));
    $context = stream_context_create(array('http' => array(
    'method' => "POST",
    'header' => "Content-Length: " . strlen($request),
    'content' => $request
    )));
    $file = @file_get_contents("http://sogo:9191/RPC2", false, $context) or die("Cannot connect to $remote_server:$listener_port");
    $response = xmlrpc_decode($file);
    if (isset($response['faultString'])) {
      echo '<b><span class="pull-right text-warning">' . $response['faultString'] . '</span></b>';
    }
    else {
      echo '<b><span class="pull-right text-success">OK</span></b>';
    }
	}
}
?>