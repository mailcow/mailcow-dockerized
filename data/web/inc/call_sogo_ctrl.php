<?php
session_start();
$AuthUsers = array("admin");
if (!isset($_SESSION['mailcow_cc_role']) OR !in_array($_SESSION['mailcow_cc_role'], $AuthUsers)) {
	echo "Not allowed." . PHP_EOL;
	exit();
}

function docker($service_name, $action, $post_action = null, $post_fields = null) {
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_HTTPHEADER,array( 'Content-Type: application/json' ));
  switch($action) {
    case 'get_id':
      curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, "/var/run/docker.sock");
      curl_setopt($curl, CURLOPT_URL, 'http:/v1.26/containers/json?all=1');
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_POST, 0);
      $response = curl_exec($curl);
      if ($response === false) {
        $err = curl_error($curl);
        curl_close($curl);
        return $err;
      }
      else {
        curl_close($curl);
        $containers = json_decode($response, true);
        if (!empty($containers)) {
          foreach ($containers as $container) {
            if ($container['Labels']['com.docker.compose.service'] == $service_name) {
              return trim($container['Id']);
            }
          }
        }
      }
      return false;
    break;
    case 'info':
      curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, "/var/run/docker.sock");
      $container_id = docker($service_name, 'get_id');
      if (ctype_xdigit($container_id)) {
        curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, "/var/run/docker.sock");
        curl_setopt($curl, CURLOPT_URL, 'http/containers/' . $container_id . '/json');
        curl_setopt($curl, CURLOPT_POST, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl);
        if ($response === false) {
          $err = curl_error($curl);
          curl_close($curl);
          return $err;
        }
        else {
          curl_close($curl);
          if (empty($response)) {
            return true;
          }
          else {
            return json_decode($response, true);
          }
        }
      }
      else {
        return false;
      }
    break;
    case 'post':
      if (!empty($post_action)) {
        $container_id = docker($service_name, 'get_id');
        if (ctype_xdigit($container_id)) {
          curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, "/var/run/docker.sock");
          curl_setopt($curl, CURLOPT_URL, 'http/containers/' . $container_id . '/' . $post_action);
          curl_setopt($curl, CURLOPT_POST, 1);
          if (!empty($post_fields)) {
            curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode($post_fields));
          }
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
          $response = curl_exec($curl);
          if ($response === false) {
            $err = curl_error($curl);
            curl_close($curl);
            return $err;
          }
          else {
            curl_close($curl);
            if (empty($response)) {
              return true;
            }
            else {
              return $response;
            }
          }
        }
      }
    break;
  }
}

if ($_GET['ACTION'] == "start") {
  $retry = 0;
  while (!docker('sogo-mailcow', 'info')['State']['Running'] && $retry <= 3) {
    $response = docker('sogo-mailcow', 'post', 'start');
    $last_response = ($response === true) ? '<b><span class="pull-right text-success">OK</span></b>' : '<b><span class="pull-right text-warning">Error: ' . $response . '</span></b>';
    if ($response === true) {
      break;
    }
    usleep(1500000);
    $retry++;
  }
  echo $last_response;
}

if ($_GET['ACTION'] == "stop") {
  $retry = 0;
  while (docker('sogo-mailcow', 'info')['State']['Running'] && $retry <= 3) {
    $response = docker('sogo-mailcow', 'post', 'stop');
    $last_response = ($response === true) ? '<b><span class="pull-right text-success">OK</span></b>' : '<b><span class="pull-right text-warning">Error: ' . $response . '</span></b>';
    if ($response === true) {
      break;
    }
    usleep(1500000);
    $retry++;
  }
  echo $last_response;
}

?>
