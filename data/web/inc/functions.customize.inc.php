<?php
function customize($_action, $_item, $_data = null) {
	global $valkey;
	global $lang;
  global $LOGO_LIMITS;

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
              if ($_data[$_item]['size'] > $LOGO_LIMITS['max_size']) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_item, $_data),
                  'msg' => 'img_size_exceeded'
                );
                return false;
              }
              list($width, $height) = getimagesize($_data[$_item]['tmp_name']);
              if ($width > $LOGO_LIMITS['max_width'] || $height > $LOGO_LIMITS['max_height']) {
                $_SESSION['return'][] = array(
                  'type' => 'danger',
                  'log' => array(__FUNCTION__, $_action, $_item, $_data),
                  'msg' => 'img_dimensions_exceeded'
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
            $valkey->Set(strtoupper($_item), 'data:' . $_data[$_item]['type'] . ';base64,' . base64_encode(file_get_contents($_data[$_item]['tmp_name'])));
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
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
          $user_links = (array)$_data['user_href'];
          $hide = (array)$_data['hide'];
          $out = array();
          if (count($apps) == count($links) && count($apps) == count($user_links) && count($apps) == count($hide)) {
            for ($i = 0; $i < count($apps); $i++) {
              $out[] = array($apps[$i] => array(
                'link' => $links[$i],
                'user_link' => $user_links[$i],
                'hide' => ($hide[$i] === '0' || $hide[$i] === 0) ? false : true
              ));
            }
            try {
              $valkey->set('APP_LINKS', json_encode($out));
            }
            catch (RedisException $e) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_item, $_data),
                'msg' => array('valkey_error', $e)
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
            $valkey->set('TITLE_NAME', htmlspecialchars($title_name));
            $valkey->set('MAIN_NAME', htmlspecialchars($main_name));
            $valkey->set('APPS_NAME', htmlspecialchars($apps_name));
            $valkey->set('HELP_TEXT', $help_text);
            $valkey->set('UI_FOOTER', $ui_footer);
            $valkey->set('UI_ANNOUNCEMENT_TEXT', $ui_announcement_text);
            $valkey->set('UI_ANNOUNCEMENT_TYPE', $ui_announcement_type);
            $valkey->set('UI_ANNOUNCEMENT_ACTIVE', $ui_announcement_active);
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
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
            $valkey->set('IP_CHECK', $ip_check);
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
            );
            return false;
          }
          $_SESSION['return'][] = array(
            'type' => 'success',
            'log' => array(__FUNCTION__, $_action, $_item, $_data),
            'msg' => 'ip_check_opt_in_modified'
          );
        break;
        case 'custom_login':
          $hide_user_quicklink        = ($_data['hide_user_quicklink'] == "1") ? 1 : 0;
          $hide_domainadmin_quicklink = ($_data['hide_domainadmin_quicklink'] == "1") ? 1 : 0;
          $hide_admin_quicklink       = ($_data['hide_admin_quicklink'] == "1") ? 1 : 0;
          $force_sso                  = ($_data['force_sso'] == "1") ? 1 : 0;

          $custom_login = array(
            "hide_user_quicklink" => $hide_user_quicklink,
            "hide_domainadmin_quicklink" => $hide_domainadmin_quicklink,
            "hide_admin_quicklink" => $hide_admin_quicklink,
            "force_sso" => $force_sso,
          );
          try {
            $valkey->set('CUSTOM_LOGIN', json_encode($custom_login));
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
            'msg' => 'custom_login_modified'
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
            if ($valkey->del(strtoupper($_item))) {
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
              'msg' => array('valkey_error', $e)
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
            $app_links = json_decode($valkey->get('APP_LINKS'), true);
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
            );
            return false;
          }

          if (empty($app_links)){
            return [];
          }

          // convert from old style
          foreach($app_links as $i => $entry){
            foreach($entry as $app => $link){
              if (empty($link['link']) && empty($link['user_link'])){
                $app_links[$i][$app] = array();
                $app_links[$i][$app]['link'] = $link;
                $app_links[$i][$app]['user_link'] = $link;
              }
            }
          }

          return $app_links;
        break;
        case 'main_logo':
        case 'main_logo_dark':
          try {
            return $valkey->get(strtoupper($_item));
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
            );
            return false;
          }
        break;
        case 'ui_texts':
          try {
            $mailcow_hostname = strtolower(getenv("MAILCOW_HOSTNAME"));

            $data['title_name'] = ($title_name = $valkey->get('TITLE_NAME')) ? $title_name : "$mailcow_hostname - mail UI";
            $data['main_name'] = ($main_name = $valkey->get('MAIN_NAME')) ? $main_name : "$mailcow_hostname - mail UI";
            $data['apps_name'] = ($apps_name = $valkey->get('APPS_NAME')) ? $apps_name : $lang['header']['apps'];
            $data['help_text'] = ($help_text = $valkey->get('HELP_TEXT')) ? $help_text : false;
            if (!empty($valkey->get('UI_IMPRESS'))) {
              $valkey->set('UI_FOOTER', $valkey->get('UI_IMPRESS'));
              $valkey->del('UI_IMPRESS');
            }
            $data['ui_footer'] = ($ui_footer = $valkey->get('UI_FOOTER')) ? $ui_footer : false;
            $data['ui_announcement_text'] = ($ui_announcement_text = $valkey->get('UI_ANNOUNCEMENT_TEXT')) ? $ui_announcement_text : false;
            $data['ui_announcement_type'] = ($ui_announcement_type = $valkey->get('UI_ANNOUNCEMENT_TYPE')) ? $ui_announcement_type : false;
            $data['ui_announcement_active'] = ($valkey->get('UI_ANNOUNCEMENT_ACTIVE') == 1) ? 1 : 0;
            return $data;
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
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
            $ip_check = ($ip_check = $valkey->get('IP_CHECK')) ? $ip_check : 0;
            return $ip_check;
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('valkey_error', $e)
            );
            return false;
          }
        break;
        case 'custom_login':
          try {
            $custom_login = $valkey->get('CUSTOM_LOGIN');
            return $custom_login ? json_decode($custom_login, true) : array();
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
