<?php
function getActiveMailboxFolders() {
    $server = '{dovecot:143/novalidate-cert}';
                
    $mbox = imap_open($server, $_SESSION['mailcow_cc_username'], $_SESSION['pass']);
    $list = imap_list($mbox, $server, "*");
    
    $folders = array();
    sort($list);
    array_walk($list, function($item) use ($server, &$folders) {
        $item = str_replace($server, '', $item);
        $value = preg_replace('/(.+)\/(.+)/', '↳ $2', $item);
        $folders[$item] = $value;
    });
    
    return $folders;
}

function addMailboxUserFilter($_data) {
  global $pdo;
  global $lang;
    
  if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (isset($_data['username']) && filter_var($_data['username'], FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $_data['username'])) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    else {
      $username = $_data['username'];
    }
  }
  elseif ($_SESSION['mailcow_cc_role'] == "user") {
    $username = $_SESSION['mailcow_cc_username'];
  }
  else {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'No user defined'
    );
    return false;
  }
  $active     = intval($_data['active']);
  $rulename   = $_data['rulename'];
  $source     = $_data['source'];
  $op         = $_data['op'];
  $searchterm = $_data['searchterm'];
  $action     = $_data['action'];
  $target     = ($action == 'move') ? $_data['target'] : '';
  
  if (empty($rulename)) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => $lang['danger']['filter_name_missing']
    );
    return false;
  }
  if ($source != "subject" && $source != "from" && $source != "to") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if ($op != "contains" && $op != "is" && $op != "begins" && $op != "ends") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if ($action != "move" && $action != "delete") {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  
  try {
    $stmt = $pdo->prepare("INSERT INTO `user_filter` (`username`, `rulename`, `source`, `searchterm`, `op`, `action`, `target`, `active`)
      VALUES (:username, :rulename, :source, :searchterm, :op, :action, :target, :active)");
    $stmt->execute(array(
      ':username' => $username,
      ':rulename' => $rulename,
      ':source' => $source,
      ':searchterm' => $searchterm,
      ':op' => $op,
      ':action' => $action,
      ':target' => $target,
      ':active' => $active,
    ));
    
    if($stmt->errorCode() != '00000') {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => 'MySQL: '.$stmt->errorCode()
        );
        return false;
    }
    
    if($active) {
      // update sieve script
      updateSieveRulesByUserFilters($username, $pdo->lastInsertId('id'));
    }
  }
  catch(PDOException $e) {      
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => $lang['success']['filter_added'],
  );
  return true;
}

function getMailboxUserFilters($username) {
  global $pdo;
  global $lang;
    
  if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
    if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $username)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
  }
  elseif ($_SESSION['mailcow_cc_role'] == "user") {
    $username = $_SESSION['mailcow_cc_username'];
  }
  else {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'No user defined'
    );
    return false;
  }
  
   try {
      $stmt = $pdo->prepare("SELECT * FROM `user_filter` WHERE `username` = :username");
      $stmt->execute(array(
        ':username' => $username,
      ));
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
    
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'Unexpected error'
    );
    return false;
}

function getMailboxUserFilter($_data) {
  global $pdo;
  global $lang;
    
  $filter_details = array();
  if (!is_numeric($_data)) {
    return false;
  }
  try {
    $stmt = $pdo->prepare("SELECT * FROM `user_filter` WHERE `id` = :id");
    $stmt->execute(array(':id' => $_data));
    $filter_details = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  catch(PDOException $e) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => 'MySQL: '.$e
    );
    return false;
  }
  if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $filter_details['username'])) {
    return false;
  }
  return $filter_details;
}

function updateMailboxUserFilter($_data) {
  global $pdo;
  global $lang;
  
  if (!is_array($_data['id'])) {
    $ids = array();
    $ids[] = $_data['id'];
  }
  else {
    $ids = $_data['id'];
  }
  if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  foreach ($ids as $id) {
    $is_now = getMailboxUserFilter($id);
    if (!empty($is_now)) {
      $username = $is_now['username'];
      $active = (isset($_data['active'])) ? intval($_data['active']) : $is_now['active'];
      $rulename = (!empty($_data['rulename'])) ? $_data['rulename'] : $is_now['rulename'];
      $source = (!empty($_data['source'])) ? $_data['source'] : $is_now['source'];
      $op = (!empty($_data['op'])) ? $_data['op'] : $is_now['op'];
      $searchterm = (!empty($_data['searchterm'])) ? $_data['searchterm'] : $is_now['searchterm'];
      $action = (!empty($_data['action'])) ? $_data['action'] : $is_now['action'];
      $target = (!empty($_data['target'])) ? $_data['target'] : $is_now['target'];
      if($action != 'move') $target = '';
    }
    else {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => sprintf($lang['danger']['access_denied'])
      );
      return false;
    }
    
      if (empty($rulename)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => $lang['danger']['filter_name_missing']
        );
        return false;
      }
      if ($source != "subject" && $source != "from" && $source != "to") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      if ($op != "contains" && $op != "is" && $op != "begins" && $op != "ends") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      if ($action != "move" && $action != "delete") {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
    
    try {
      $stmt = $pdo->prepare("UPDATE `user_filter` SET `username` = :username, `rulename` = :rulename, `source` = :source, `searchterm` = :searchterm, `op` = :op, `action` = :action, `target` = :target, `active` = :active
        WHERE `id` = :id");
      $stmt->execute(array(
          ':username' => $username,
          ':rulename' => $rulename,
          ':source' => $source,
          ':searchterm' => $searchterm,
          ':op' => $op,
          ':action' => $action,
          ':target' => $target,
          ':active' => $active,
          ':id' => $id,
        ));
      
      if($active) {
        // update sieve script
        updateSieveRulesByUserFilters($username, $id);
      } else {
        // delete from sieve script
        deleteUserFiltersFromSieveRules($username, $id);
      }
    }
    catch(PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
  }
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['changes_general'], $username)
  );
  return true;
}

function deleteMailboxUserFilter($_data) {
  global $pdo;
  global $lang;
  
  if (!is_array($_data['id'])) {
    $ids = array();
    $ids[] = $_data['id'];
  }
  else {
    $ids = $_data['id'];
  }
  if (!isset($_SESSION['acl']['filters']) || $_SESSION['acl']['filters'] != "1" ) {
    $_SESSION['return'] = array(
      'type' => 'danger',
      'msg' => sprintf($lang['danger']['access_denied'])
    );
    return false;
  }
  foreach ($ids as $id) {
    if (!is_numeric($id)) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => $id
      );
      return false;
    }
    try {
      $stmt = $pdo->prepare("SELECT `username` FROM `user_filter` WHERE id = :id");
      $stmt->execute(array(':id' => $id));
      $usr = $stmt->fetch(PDO::FETCH_ASSOC)['username'];
      if (!hasMailboxObjectAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $usr)) {
        $_SESSION['return'] = array(
          'type' => 'danger',
          'msg' => sprintf($lang['danger']['access_denied'])
        );
        return false;
      }
      $stmt = $pdo->prepare("DELETE FROM `user_filter` WHERE `id`= :id");
      $stmt->execute(array(':id' => $id));
      
      // delete from sieve script
      deleteUserFiltersFromSieveRules($usr, $id);
    }
    catch (PDOException $e) {
      $_SESSION['return'] = array(
        'type' => 'danger',
        'msg' => 'MySQL: '.$e
      );
      return false;
    }
  }
  
  $_SESSION['return'] = array(
    'type' => 'success',
    'msg' => sprintf($lang['success']['deleted_user_filters'], implode(', ', $ids))
  );
  return true;
}

function deleteUserFiltersFromSieveRules($username, $userFilterId) {
	global $pdo;

	// select actual sieve script
    $stmt = $pdo->prepare("SELECT `script_data` FROM `sieve_filters` WHERE username = :username AND script_desc = 'userFilter'");
    $stmt->execute(array(':username' => $username));
    
    if($stmt->rowCount() == 0) {
        return;
    }
    
	$skip = false;
	$script_data = $stmt->fetch(PDO::FETCH_ASSOC)['script_data'];
	$lines = explode("\n", $script_data);
	$out = '';

	foreach($lines as $line) {
		$line = trim($line);
		if($line == '### BEGIN FILTER_ID:'.$userFilterId) {
			$skip = true;
		}
		if($skip == false && $line != '') $out .= $line ."\n";
		if($line == '### END FILTER_ID:'.$userFilterId) {
			$skip = false;
		}
	}

    // disable all other postfilter scripts
    $stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_name` = 'inactive' WHERE `username` = :username AND `filter_type` = 'postfilter'");
    $stmt->execute(array(':username' => $username));

	$stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_data` = :script_data, `script_name` = 'active', `modified` = NOW() WHERE username = :username AND script_desc = 'userFilter'");
    $stmt->execute(array(':script_data' => $out, ':username' => $username));
}

function updateSieveRulesByUserFilters($username, $userFilterId) {
    global $pdo;
    
    // select actual sieve script
    $stmt = $pdo->prepare("SELECT `script_data` FROM `sieve_filters` WHERE username = :username AND script_desc = 'userFilter'");
    $stmt->execute(array(':username' => $username));
    
    $update = false;
    $out = '';
    $found = false;
    
    if($stmt->rowCount() > 0) {
        $update = true;
        
        $script_data = $stmt->fetch(PDO::FETCH_ASSOC)['script_data'];
        
        $skip = false;
        $lines = explode("\n", $script_data);
        
        $x = 1;
        foreach($lines as $line) {
        	$line = rtrim($line);
        	
        	// check dependencies
        	if($x === 1 && ! preg_match('/fileinto.+regex|regex.+fileinto/', $line)) {
            	$out = 'require ["fileinto", "regex"];'."\n\n";
        	}
        	
        	if($line == '### BEGIN FILTER_ID:'.$userFilterId) {
        		$skip = true;
        		$found = true;
        	}
        	if($skip == false && $line != '') $out .= $line ."\n";
        	if($line == '### END FILTER_ID:'.$userFilterId) {
        		$out .= getMailboxUserFilterSieveRule($userFilterId);
        		$skip = false;
        	}
        	
        	$x++;
        }  
    } else {
        // add dependencies
        $out = 'require ["fileinto", "regex"];'."\n\n";
    }
    
    // We did not found our rule, so we add it now as first rule.
    if($found == false) {
    	$new_rule = getMailboxUserFilterSieveRule($userFilterId);
    	$out = $out.$new_rule;
    }
    
    // disable all other postfilter scripts
    $stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_name` = 'inactive' WHERE `username` = :username AND `filter_type` = 'postfilter'");
    $stmt->execute(array(':username' => $username));
    
    // save rules
    if($update) {
        $stmt = $pdo->prepare("UPDATE `sieve_filters` SET `script_data` = :script_data, `script_name` = 'active', `modified` = NOW() WHERE username = :username AND script_desc = 'userFilter'");
        $stmt->execute(array(':script_data' => $out, ':username' => $username));
    } else {
        $stmt = $pdo->prepare("INSERT INTO `sieve_filters` (`username`, `script_desc`, `script_name`, `script_data`, `filter_type`, `created`)
      VALUES (:username, 'userFilter', 'active', :script_data, 'postfilter', NOW())");
        $stmt->execute(array(
            ':username' => $username,
            ':script_data' => $out
        ));
    }
}

function getMailboxUserFilterSieveRule($userFilterId) {
    global $pdo;
    
    $filter = getMailboxUserFilter($userFilterId);
    
    // #######################################################
	// Filter in Sieve Syntax
	// #######################################################

	$content = '';
	$content .= '### BEGIN FILTER_ID:'.$userFilterId."\n";
	
	if($filter["op"] == 'domain') {
		$content .= 'if address :domain :is "'.strtolower($filter["source"]).'" "'.$filter["searchterm"].'" {'."\n";
	} elseif ($filter["op"] == 'localpart') {
		$content .= 'if address :localpart :is "'.strtolower($filter["source"]).'" "'.$filter["searchterm"].'" {'."\n";
	} elseif ($filter["source"] == 'Size') {
		if(substr(trim($filter["searchterm"]),-1) == 'k' || substr(trim($filter["searchterm"]),-1) == 'K') {
			$unit = 'k';
		} else {
			$unit = 'm';
		}
		$content .= 'if size :over '.intval($filter["searchterm"]).$unit.' {'."\n";
	} else {
	
		if($filter["source"] == 'Header') {
			$parts = explode(':',trim($filter["searchterm"]));
			$filter["source"] = trim($parts[0]);
			unset($parts[0]);
			$filter["searchterm"] = trim(implode(':',$parts));
			unset($parts);
		}

		$content .= 'if header :regex    ["'.strtolower($filter["source"]).'"] ["';

		$searchterm = preg_quote($filter["searchterm"]);
		$searchterm = str_replace(
			array(
				'"',
				'\\[',
				'\\]'
			),
			array(
				'\\"',
				'\\\\[',
				'\\\\]'
			), $searchterm);

		if($filter["op"] == 'contains') {
			$content .= ".*".$searchterm;
		} elseif ($filter["op"] == 'is') {
			$content .= "^".$searchterm."$";
		} elseif ($filter["op"] == 'begins') {
			$content .= "^".$searchterm."";
		} elseif ($filter["op"] == 'ends') {
			$content .= ".*".$searchterm."$";
		}

		$content .= '"] {'."\n";
	}

	if($filter["action"] == 'move') {
		$content .= '    fileinto "'.$filter["target"].'";' . "\n    stop;\n";
	} elseif ($filter["action"] == 'keep') {
		$content .= "    keep;\n";
	} elseif ($filter["action"] == 'stop') {
		$content .= "    stop;\n";
	} elseif ($filter["action"] == 'reject') {
		$content .= '    reject "'.$filter["target"].'";    stop;\n\n';
	} elseif ($filter["action"] == 'read') {
		$content .= '    setflag "\\\\Seen";\n    stop;\n';
	} else {
		$content .= "    discard;\n    stop;\n";
	}

	$content .= "}\n";

	$content .= '### END FILTER_ID:'.$userFilterId."\n";

    return $content;
}