<?php
function oauth2($_action, $_type, $_data = null) {
	global $pdo;
	global $redis;
	global $lang;
	if ($_SESSION['mailcow_cc_role'] != "admin") {
		$_SESSION['return'] = array(
			'type' => 'danger',
			'msg' => sprintf($lang['danger']['access_denied'])
		);
		return false;
	}
  switch ($_action) {
    case 'add':
      switch ($_type) {
        case 'client':
          $client_id = $_data['client_id'];
          $client_secret = $_data['client_secret'];
          $redirect_uri = $_data['redirect_uri'];
          // $grant_type = isset($_data['grant_type']) ? $_data['grant_type'] : 'authorization_code';
          // $scope = isset($_data['scope']) ? $_data['scope'] : 'profile';
          if ($grant_type != "authorization_code" && $grant_type != "password") {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          // For future use
          if ($scope != "profile") {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          if (!ctype_alnum($client_id) || !ctype_alnum($client_secret)) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => sprintf($lang['danger']['access_denied'])
            );
            return false;
          }
          $stmt = $pdo->prepare("SELECT 'client' FROM `oauth_clients`
            WHERE `client_id` = :client_id");
          $stmt->execute(array(':client_id' => $client_id));
          $num_results = count($stmt->fetchAll(PDO::FETCH_ASSOC));
          if ($num_results != 0) {
            $_SESSION['return'] = array(
              'type' => 'danger',
              'msg' => 'Client ID exists'
            );
            return false;
          }
          $stmt = $pdo->prepare("INSERT INTO `oauth_clients` (`client_id`, `client_secret` ,`redirect_uri`)
            VALUES (:client_id, :client_secret, :redirect_uri)");
          $stmt->execute(array(
            ':client_id' => $client_id,
            ':client_secret' => $client_secret,
            ':redirect_uri' => $redirect_uri
          ));
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => 'Added client access'
          );
        break;
      }
    break;
    case 'edit':
      switch ($_type) {
        case 'client':
          $ids = (array)$_data['id'];
          foreach ($ids as $id) {
            $is_now = oauth2('details', 'client', $id);
            if (!empty($is_now)) {
              $client_id      = (!empty($_data['client_id'])) ? $_data['client_id'] : $is_now['client_id'];
              $client_secret  = (!empty($_data['client_secret'])) ? $_data['client_secret'] : $is_now['client_secret'];
              $redirect_uri   = (!empty($_data['redirect_uri'])) ? $_data['redirect_uri'] : $is_now['redirect_uri'];
            }
            else {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            if (!ctype_alnum($client_id) || !ctype_alnum($client_secret)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Client ID and secret must be alphanumeric'
              );
              return false;
            }
            if (empty($redirect_uri)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => 'Redirect/Callback URL cannot be empty'
              );
              return false;
            }
            $stmt = $pdo->prepare("UPDATE `oauth_clients` SET
              `client_id` = :client_id,
              `client_secret` = :client_secret,
              `redirect_uri` = :redirect_uri
                WHERE `id` = :id");
            $stmt->execute(array(
              ':id' => $id,
              ':client_id' => $client_id,
              ':client_secret' => $client_secret,
              ':redirect_uri' => $redirect_uri
            ));
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['object_modified'], htmlspecialchars(implode(', ', $ids)))
          );
        break;
      }
    break;
    case 'delete':
      switch ($_type) {
        case 'client':
          (array)$ids = $_data['id'];
          foreach ($ids as $id) {
            if (!is_numeric($id)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            $stmt = $pdo->prepare("DELETE FROM `oauth_clients` WHERE `id` = :id");
            $stmt->execute(array(
              ':id' => $id
            ));
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['items_deleted'], implode(', ', $ids))
          );
        break;
        case 'access_token':
          (array)$access_tokens = $_data['access_token'];
          foreach ($access_tokens as $access_token) {
            if (!ctype_alnum($access_token)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            $stmt = $pdo->prepare("DELETE FROM `oauth_access_tokens` WHERE `access_token` = :access_token");
            $stmt->execute(array(
              ':access_token' => $access_token
            ));
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['items_deleted'], implode(', ', $access_tokens))
          );
        break;
        case 'refresh_token':
          (array)$refresh_tokens = $_data['refresh_token'];
          foreach ($refresh_tokens as $refresh_token) {
            if (!ctype_alnum($refresh_token)) {
              $_SESSION['return'] = array(
                'type' => 'danger',
                'msg' => sprintf($lang['danger']['access_denied'])
              );
              return false;
            }
            $stmt = $pdo->prepare("DELETE FROM `oauth_refresh_tokens` WHERE `refresh_token` = :refresh_token");
            $stmt->execute(array(
              ':refresh_token' => $refresh_token
            ));
          }
          $_SESSION['return'] = array(
            'type' => 'success',
            'msg' => sprintf($lang['success']['items_deleted'], implode(', ', $refresh_tokens))
          );
        break;
      }
    break;
    case 'get':
      switch ($_type) {
        case 'clients':
          $stmt = $pdo->query("SELECT `id` FROM `oauth_clients`");
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
          while ($row = array_shift($rows)) {
            $oauth_clients[] = $row['id'];
          }
          return $oauth_clients;
        break;
      }
    break;
    case 'details':
      switch ($_type) {
        case 'client':
          $stmt = $pdo->prepare("SELECT * FROM `oauth_clients`
            WHERE `id` = :id");
          $stmt->execute(array(':id' => $_data));
          $oauth_client_details = $stmt->fetch(PDO::FETCH_ASSOC);
          return $oauth_client_details;
        break;
      }
    break;
  }
}