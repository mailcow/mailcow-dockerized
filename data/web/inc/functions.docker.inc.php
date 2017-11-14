<?php
function docker($service_name, $action, $post_action = null, $post_fields = null) {
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_HTTPHEADER,array( 'Content-Type: application/json' ));
  switch($action) {
    case 'get_id':
      curl_setopt($curl, CURLOPT_URL, 'http://dockerapi:8080/containers/json');
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
            if ($container['Config']['Labels']['com.docker.compose.service'] == $service_name) {
              return trim($container['Id']);
            }
          }
        }
      }
      return false;
    break;
    case 'info':
      $container_id = docker($service_name, 'get_id');
      if (ctype_xdigit($container_id)) {
        curl_setopt($curl, CURLOPT_URL, 'http://dockerapi:8080/containers/' . $container_id . '/json');
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
        if (ctype_xdigit($container_id) && ctype_alnum($post_action)) {
          curl_setopt($curl, CURLOPT_URL, 'http://dockerapi:8080/containers/' . $container_id . '/' . $post_action);
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