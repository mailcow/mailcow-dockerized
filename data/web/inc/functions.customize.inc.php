<?php
function customize($_action, $_item, $_data = null) {
	global $redis;
	global $lang;
  
  switch ($_action) {
    case 'add':
      // disable functionality when demo mode is enabled
      if ($GLOBALS["DEMO_MODE"]) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_item, $_data),
          'msg' => 'demo_mode_enabled'
        );
        return false;
      }
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_item, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      switch ($_item) {
        case 'main_logo':
        case 'main_logo_dark':
          if (in_array($_data[$_item]['type'], array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/png', 'image/svg+xml'))) {
            try {
              if (file_exists($_data[$_item]['tmp_name']) !== true) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_item, $_data),
                  'msg' => 'img_tmp_missing'
                );
                return false;
              }
              $image = new Imagick($_data[$_item]['tmp_name']);
              if ($image->valid() !== true) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_item, $_data),
                  'msg' => 'img_invalid'
                );
                return false;
              }
              $image->destroy();
            }
            catch (ImagickException $e) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_item, $_data),
                'msg' => 'img_invalid'
              );
              return false;
            }
          }
          else {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => 'invalid_mime_type'
            );
            return false;
          }
          try {
            $redis->Set(strtoupper($_item), 'data:' . $_data[$_item]['type'] . ';base64,' . base64_encode(file_get_contents($_data[$_item]['tmp_name'])));
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_item, $_data),
            'msg' => 'upload_success'
          );
        break;
      }
    break;
    case 'edit':
      // disable functionality when demo mode is enabled
      if ($GLOBALS["DEMO_MODE"]) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_item, $_data),
          'msg' => 'demo_mode_enabled'
        );
        return false;
      }
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_item, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      switch ($_item) {
        case 'app_links':
          $apps = (array)$_data['app'];
          $links = (array)$_data['href'];
          $out = array();
          if (count($apps) == count($links)) {
            for ($i = 0; $i < count($apps); $i++) {
              $out[] = array($apps[$i] => $links[$i]);
            }
            try {
              $redis->set('APP_LINKS', json_encode($out));
            }
            catch (RedisException $e) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_item, $_data),
                'msg' => array('redis_error', $e)
              );
              return false;
            }
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_item, $_data),
            'msg' => 'app_links'
          );
        break;
        case 'ui_texts':
          $title_name = $_data['title_name'];
          $main_name = $_data['main_name'];
          $apps_name = $_data['apps_name'];
          $help_text = $_data['help_text'];
          $ui_footer = $_data['ui_footer'];
          $ui_announcement_text = $_data['ui_announcement_text'];
          $ui_announcement_type = (in_array($_data['ui_announcement_type'], array('info', 'warning', 'danger'))) ? $_data['ui_announcement_type'] : false;
          $ui_announcement_active = (!empty($_data['ui_announcement_active']) ? 1 : 0);

          try {
            $redis->set('TITLE_NAME', htmlspecialchars($title_name));
            $redis->set('MAIN_NAME', htmlspecialchars($main_name));
            $redis->set('APPS_NAME', htmlspecialchars($apps_name));
            $redis->set('HELP_TEXT', $help_text);
            $redis->set('UI_FOOTER', $ui_footer);
            $redis->set('UI_ANNOUNCEMENT_TEXT', $ui_announcement_text);
            $redis->set('UI_ANNOUNCEMENT_TYPE', $ui_announcement_type);
            $redis->set('UI_ANNOUNCEMENT_ACTIVE', $ui_announcement_active);
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_item, $_data),
            'msg' => 'ui_texts'
          );
        break;
        case 'ip_check':
          $ip_check = ($_data['ip_check_opt_in'] == "1") ? 1 : 0;
          try {
            $redis->set('IP_CHECK', $ip_check);
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_item, $_data),
            'msg' => 'ip_check_opt_in_modified'
          );
        break;
      }
    break;
    case 'delete':
      // disable functionality when demo mode is enabled
      if ($GLOBALS["DEMO_MODE"]) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_item, $_data),
          'msg' => 'demo_mode_enabled'
        );
        return false;
      }
      if ($_SESSION['mailcow_cc_role'] != "admin") {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_item, $_data),
          'msg' => 'access_denied'
        );
        return false;
      }
      switch ($_item) {
        case 'main_logo':
        case 'main_logo_dark':
          try {
            if ($redis->del(strtoupper($_item))) {
              $_SESSION['return'][] = array(
                'type' => 'success',
                'log' => array(__FUNCTION__, $_action, $_item, $_data),
                'msg' => 'reset_main_logo'
              );
              return true;
            }
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
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
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
          return ($app_links) ? $app_links : false;
        break;
        case 'main_logo':
        case 'main_logo_dark':
          try {
            return $redis->get(strtoupper($_item));
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
        break;
        case 'ui_texts':
          try {
            $data['title_name'] = ($title_name = $redis->get('TITLE_NAME')) ? $title_name : 'mailcow UI';
            $data['main_name'] = ($main_name = $redis->get('MAIN_NAME')) ? $main_name : 'mailcow UI';
            $data['apps_name'] = ($apps_name = $redis->get('APPS_NAME')) ? $apps_name : $lang['header']['apps'];
            $data['help_text'] = ($help_text = $redis->get('HELP_TEXT')) ? $help_text : false;
            if (!empty($redis->get('UI_IMPRESS'))) {
              $redis->set('UI_FOOTER', $redis->get('UI_IMPRESS'));
              $redis->del('UI_IMPRESS');
            }
            $data['ui_footer'] = ($ui_footer = $redis->get('UI_FOOTER')) ? $ui_footer : false;
            $data['ui_announcement_text'] = ($ui_announcement_text = $redis->get('UI_ANNOUNCEMENT_TEXT')) ? $ui_announcement_text : false;
            $data['ui_announcement_type'] = ($ui_announcement_type = $redis->get('UI_ANNOUNCEMENT_TYPE')) ? $ui_announcement_type : false;
            $data['ui_announcement_active'] = ($redis->get('UI_ANNOUNCEMENT_ACTIVE') == 1) ? 1 : 0;
            return $data;
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
        break;
        case 'main_logo_specs':
        case 'main_logo_dark_specs':
          try {
            $image = new Imagick();
            if($_item == 'main_logo_specs') {
              $img_data = explode('base64,', customize('get', 'main_logo'));
            } else {
              $img_data = explode('base64,', customize('get', 'main_logo_dark'));
            }
            if ($img_data[1]) {
              $image->readImageBlob(base64_decode($img_data[1]));
              return $image->identifyImage();
            }
            return false;
          }
          catch (ImagickException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => 'imagick_exception'
            );
            return false;
          }
        break;
        case 'ip_check':
          try {
            $ip_check = ($ip_check = $redis->get('IP_CHECK')) ? $ip_check : 0;
            return $ip_check;
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
        break;
      }
    break;
  }
}
