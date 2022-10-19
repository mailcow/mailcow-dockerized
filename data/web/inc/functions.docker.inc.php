<?php
function docker($action, $service_name = null, $attr1 = null, $attr2 = null, $extra_headers = null) {
  global $DOCKER_TIMEOUT;
  global $redis;
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_HTTPHEADER,array('Content-Type: application/json' ));
  // We are using our mail certificates for dockerapi, the names will not match, the certs are trusted anyway
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
  switch($action) {
    case 'get_id':
      curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/containers/json');
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_POST, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
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
            if (isset($container['Config']['Labels']['com.docker.compose.service'])
              && $container['Config']['Labels']['com.docker.compose.service'] == $service_name
              && strtolower($container['Config']['Labels']['com.docker.compose.project']) == strtolower(getenv('COMPOSE_PROJECT_NAME'))) {
              return trim($container['Id']);
            }
          }
        }
      }
      return false;
    break;
    case 'containers':
      curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/containers/json');
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_POST, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
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
            if (strtolower($container['Config']['Labels']['com.docker.compose.project']) == strtolower(getenv('COMPOSE_PROJECT_NAME'))) {
              $out[$container['Config']['Labels']['com.docker.compose.service']]['State'] = $container['State'];
              $out[$container['Config']['Labels']['com.docker.compose.service']]['Config'] = $container['Config'];
              $out[$container['Config']['Labels']['com.docker.compose.service']]['Id'] = trim($container['Id']);
            }
          }
        }
        return (!empty($out)) ? $out : false;
      }
      return false;
    break;
    case 'info':
      if (empty($service_name)) {
        curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/containers/json');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
      }
      else {
        $container_id = docker('get_id', $service_name);
        if (ctype_xdigit($container_id)) {
          curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/containers/' . $container_id . '/json');
        }
        else {
          return false;
        }
      }
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_POST, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
      $response = curl_exec($curl);
      if ($response === false) {
        $err = curl_error($curl);
        curl_close($curl);
        return $err;
      }
      else {
        curl_close($curl);
        $decoded_response = json_decode($response, true);
        if (!empty($decoded_response)) {
          if (empty($service_name)) {
            foreach ($decoded_response as $container) {
              if (isset($container['Config']['Labels']['com.docker.compose.project'])
                && strtolower($container['Config']['Labels']['com.docker.compose.project']) == strtolower(getenv('COMPOSE_PROJECT_NAME'))) {
                unset($container['Config']['Env']);
                $out[$container['Config']['Labels']['com.docker.compose.service']]['State'] = $container['State'];
                $out[$container['Config']['Labels']['com.docker.compose.service']]['Config'] = $container['Config'];
                $out[$container['Config']['Labels']['com.docker.compose.service']]['Id'] = trim($container['Id']);
              }
            }
          }
          else {
            if (isset($decoded_response['Config']['Labels']['com.docker.compose.project']) 
              && strtolower($decoded_response['Config']['Labels']['com.docker.compose.project']) == strtolower(getenv('COMPOSE_PROJECT_NAME'))) {
              unset($container['Config']['Env']);
              $out[$decoded_response['Config']['Labels']['com.docker.compose.service']]['State'] = $decoded_response['State'];
              $out[$decoded_response['Config']['Labels']['com.docker.compose.service']]['Config'] = $decoded_response['Config'];
              $out[$decoded_response['Config']['Labels']['com.docker.compose.service']]['Id'] = trim($decoded_response['Id']);
            }
          }
        }
        if (empty($response)) {
          return true;
        }
        else {
          return (!empty($out)) ? $out : false;
        }
      }
    break;
    case 'post':
      if (!empty($attr1)) {
        $container_id = docker('get_id', $service_name);
        if (ctype_xdigit($container_id) && ctype_alnum($attr1)) {
          curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/containers/' . $container_id . '/' . $attr1);
          curl_setopt($curl, CURLOPT_POST, 1);
          curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
          if (!empty($attr2)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($attr2));
          }
          if (!empty($extra_headers) && is_array($extra_headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $extra_headers);
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
    case 'container_stats':
      if (empty($service_name)){
        return false;
      }

      $container_id = $service_name;
      curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/container/' . $container_id . '/stats/update');
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
      $response = curl_exec($curl);
      if ($response === false) {
        $err = curl_error($curl);
        curl_close($curl);
        return $err;
      }
      else {
        curl_close($curl);
        $stats = json_decode($response, true);
        if (!empty($stats)) return $stats;
      }
      return false;
    break;
    case 'host_stats':
      curl_setopt($curl, CURLOPT_URL, 'https://dockerapi:443/host/stats');
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_POST, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, $DOCKER_TIMEOUT);
      $response = curl_exec($curl);
      if ($response === false) {
        $err = curl_error($curl);
        curl_close($curl);
        return $err;
      }
      else {
        curl_close($curl);
        $stats = json_decode($response, true);
        if (!empty($stats)) return $stats;
      }
      return false;
    break;
  }
}
