<?php

function customize($_action, $_item, $_data = null) {
	global $redis;
	global $lang;
  switch ($_action) {
    case 'add':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      switch ($_item) {
        case 'main_logo':
          if (in_array($_data['main_logo']['type'], array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/png', 'image/svg+xml'))) {
            try {
              if (file_exists($_data['main_logo']['tmp_name']) !== true) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Cannot validate image file: Temporary file not found'
                );
                return false;
              }
              $image = new Imagick($_data['main_logo']['tmp_name']);
              if ($image->valid() !== true) {
                $_SESSION['return'] = array(
                  'type' => 'danger',
                  'msg' => 'Cannot validate image file'
                );
                return false;
              }
              $image->destroy();
            }
            catch (ImagickException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Cannot validate image file'
              );
              return false;
            }
          }
          else {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Invalid mime type'
            );
            return false;
          }
          try {
            $redis->Set('MAIN_LOGO', 'data:' . $_data['main_logo']['type'] . ';base64,' . base64_encode(file_get_contents($_data['main_logo']['tmp_name'])));
          }
          catch (RedisException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Redis: '.$e
            );
            return false;
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => 'File uploaded successfully'
          );
        break;
      }
    break;
    case 'edit':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      switch ($_item) {
        case 'app_links':
          $apps = (array)$_data['app'];
          $links = (array)$_data['href'];
          $out = array();
          if (count($apps) == count($links)) {;
            for ($i = 0; $i < count($apps); $i++) {
              $out[] = array($apps[$i] => $links[$i]);
            }
            try {
              $redis->set('APP_LINKS', json_encode($out));
            }
            catch (RedisException $e) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Redis: '.$e
              );
              return false;
            }
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => 'Saved changes to app links'
          );
        break;
      }
    break;
    case 'delete':
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      switch ($_item) {
        case 'main_logo':
          try {
            if ($redis->del('MAIN_LOGO')) {
              $_SESSION['return'] = array(
                'type' => 'success',
                'msg' => 'Reset default logo'
              );
              return true;
            }
          }
          catch (RedisException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Redis: '.$e
            );
            return false;
          }
        break;
      }
    break;
    case 'get':
      switch ($_item) {
        case 'app_links':
          try {
            $app_links = json_decode($redis->get('APP_LINKS'), true);
          }
          catch (RedisException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Redis: '.$e
            );
            return false;
          }
          return ($app_links) ? $app_links : false;
        break;
        case 'main_logo':
          try {
            return $redis->get('MAIN_LOGO');
          }
          catch (RedisException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Redis: '.$e
            );
            return false;
          }
        break;
        case 'main_logo_specs':
          try {
            $image = new Imagick();
            $img_data = explode('base64,', customize('get', 'main_logo'));
            if ($img_data[1]) {
              $image->readImageBlob(base64_decode($img_data[1]));
            }
            return $image->identifyImage();
          }
          catch (ImagickException $e) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Error: Imagick exception while reading image'
            );
            return false;
          }
        break;
      }
    break;
  }
}