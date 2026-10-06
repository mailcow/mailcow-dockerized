<?php
function customize($_action, $_item, $_data = null) {
	global $redis;
	global $lang;
  global $LOGO_LIMITS;
  global $BACKGROUND_LIMITS;

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
            $redis->set('CUSTOM_LOGIN', json_encode($custom_login));
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
        case 'ui_background':
          // optional upload of a new image, blur, veil and scope are saved in any case
          $upload = isset($_data['file']) ? $_data['file'] : null;
          $image = false;
          if (!empty($upload) && $upload['error'] != UPLOAD_ERR_NO_FILE) {
            if (in_array($upload['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE)) || $upload['size'] > $BACKGROUND_LIMITS['max_size']) {
              $msg = 'img_size_exceeded';
            }
            elseif ($upload['error'] != UPLOAD_ERR_OK || file_exists($upload['tmp_name']) !== true) {
              $msg = 'img_tmp_missing';
            }
            elseif (!in_array($upload['type'], array('image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp'))) {
              $msg = 'invalid_mime_type';
            }
            else {
              $image = ui_background_process_image(file_get_contents($upload['tmp_name']));
              $msg = is_array($image) ? null : $image;
            }
            if ($msg !== null) {
              $_SESSION['return'][] = array(
                'type' => 'danger',
                'log' => array(__FUNCTION__, $_action, $_item, $_data),
                'msg' => $msg
              );
              return false;
            }
          }
          $settings = customize('get', 'ui_background');
          if ($settings === false) {
            return false;
          }
          if ($settings['url'] === false && $image === false) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => 'ui_background_no_image'
            );
            return false;
          }
          unset($settings['url']);
          if (isset($_data['blur'])) {
            $settings['blur'] = max(0, min(intval($_data['blur']), $BACKGROUND_LIMITS['max_blur']));
          }
          if (isset($_data['veil'])) {
            $settings['veil'] = max(0, min(intval($_data['veil']), $BACKGROUND_LIMITS['max_veil']));
          }
          if (isset($_data['scope']) && in_array($_data['scope'], array('login', 'all'))) {
            $settings['scope'] = $_data['scope'];
          }
          try {
            if ($image !== false) {
              $data = 'data:' . $image['mime'] . ';base64,' . base64_encode($image['blob']);
              $redis->set('UI_BACKGROUND_IMAGE', $data);
              $settings['hash'] = sha1($data);
              $settings['width'] = $image['width'];
              $settings['height'] = $image['height'];
              $settings['size'] = strlen($image['blob']);
            }
            $redis->set('UI_BACKGROUND', json_encode($settings));
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
            'msg' => 'ui_background_saved'
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
        case 'ui_background':
          try {
            $redis->del('UI_BACKGROUND', 'UI_BACKGROUND_IMAGE');
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
            'msg' => 'ui_background_reset'
          );
          return true;
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
            $mailcow_hostname = strtolower(getenv("MAILCOW_HOSTNAME"));

            $data['title_name'] = ($title_name = $redis->get('TITLE_NAME')) ? $title_name : "$mailcow_hostname - mail UI";
            $data['main_name'] = ($main_name = $redis->get('MAIN_NAME')) ? $main_name : "$mailcow_hostname - mail UI";
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
        case 'custom_login':
          try {
            $custom_login = $redis->get('CUSTOM_LOGIN');
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
        case 'ui_background':
          // settings incl. defaults, 'url' is false as long as no image was uploaded
          try {
            $settings = json_decode((string)$redis->get('UI_BACKGROUND'), true);
          }
          catch (RedisException $e) {
            $_SESSION['return'][] = array(
              'type' => 'danger',
              'log' => array(__FUNCTION__, $_action, $_item, $_data),
              'msg' => array('redis_error', $e)
            );
            return false;
          }
          $settings = array_merge(array('blur' => 8, 'veil' => 20, 'scope' => 'login', 'hash' => ''), is_array($settings) ? $settings : array());
          $settings['url'] = preg_match('/^[a-f0-9]{40}$/', $settings['hash']) ? '/background.php?v=' . $settings['hash'] : false;
          return $settings;
        break;
      }
    break;
  }
}

// Validates an uploaded UI background and re-encodes it: EXIF orientation applied, metadata stripped,
// scaled down to $BACKGROUND_LIMITS['max_edge'] and saved as WebP.
// Returns array(mime, blob, width, height) or the lang key of the error.
function ui_background_process_image($blob) {
  global $BACKGROUND_LIMITS;
  try {
    $image = new Imagick();
    $image->pingImageBlob($blob);
    if (!in_array(strtolower($image->getImageFormat()), array('jpeg', 'png', 'webp'))) {
      return 'invalid_mime_type';
    }
    if ($image->getImageWidth() * $image->getImageHeight() > $BACKGROUND_LIMITS['max_pixels']) {
      return 'img_dimensions_exceeded';
    }
    $image->clear();
    $image->readImageBlob($blob);
    if ($image->valid() !== true) {
      return 'img_invalid';
    }
    if ($image->getNumberImages() > 1) {
      $image->setIteratorIndex(0);
      $image = $image->getImage();
    }
    switch ($image->getImageOrientation()) {
      case Imagick::ORIENTATION_TOPRIGHT: $image->flopImage(); break;
      case Imagick::ORIENTATION_BOTTOMRIGHT: $image->rotateImage('#000', 180); break;
      case Imagick::ORIENTATION_BOTTOMLEFT: $image->flipImage(); break;
      case Imagick::ORIENTATION_LEFTTOP: $image->transposeImage(); break;
      case Imagick::ORIENTATION_RIGHTTOP: $image->rotateImage('#000', 90); break;
      case Imagick::ORIENTATION_RIGHTBOTTOM: $image->transverseImage(); break;
      case Imagick::ORIENTATION_LEFTBOTTOM: $image->rotateImage('#000', -90); break;
    }
    $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
    if (max($image->getImageWidth(), $image->getImageHeight()) > $BACKGROUND_LIMITS['max_edge']) {
      $image->thumbnailImage($BACKGROUND_LIMITS['max_edge'], $BACKGROUND_LIMITS['max_edge'], true);
    }
    $image->stripImage();
    $image->setImageFormat('webp');
    $image->setImageCompressionQuality(80);
    return array(
      'mime' => 'image/webp',
      'blob' => $image->getImageBlob(),
      'width' => $image->getImageWidth(),
      'height' => $image->getImageHeight()
    );
  }
  catch (ImagickException $e) {
    return 'img_invalid';
  }
}
