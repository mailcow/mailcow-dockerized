<?php
function signature_template($_action, $_data = null, $_attr = null) {
  global $pdo;
  global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    return false;
  }
  switch ($_action) {
    case 'add':
      $domain = idn_to_ascii(strtolower(trim($_data['domain'])), 0, INTL_IDNA_VARIANT_UTS46);
      $name = trim($_data['name']);
      $description = isset($_data['description']) ? trim($_data['description']) : '';
      $html = isset($_data['html']) ? $_data['html'] : '';
      $plain = isset($_data['plain']) ? $_data['plain'] : '';
      $skip_replies = isset($_data['skip_replies']) ? (int)!!$_data['skip_replies'] : 0;
      $active = isset($_data['active']) ? (int)!!$_data['active'] : 1;

      if (!is_valid_domain_name($domain)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'domain_invalid'
        );
        return false;
      }
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'access_denied'
        );
        return false;
      }
      if ($name === '' || mb_strlen($name) > 255) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'signature_template_name_invalid'
        );
        return false;
      }
      try {
        $stmt = $pdo->prepare("INSERT INTO `signature_templates`
          (`domain`, `name`, `description`, `html`, `plain`, `skip_replies`, `active`)
          VALUES (:domain, :name, :description, :html, :plain, :skip_replies, :active)");
        $stmt->execute(array(
          ':domain' => $domain,
          ':name' => $name,
          ':description' => $description,
          ':html' => $html,
          ':plain' => $plain,
          ':skip_replies' => $skip_replies,
          ':active' => $active,
        ));
      }
      catch (PDOException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => array('mysql_error', $e->getMessage())
        );
        return false;
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data, $_attr),
        'msg' => array('signature_template_added', htmlspecialchars($name))
      );
    break;
    case 'edit':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $id = (int)$id;
        $row = signature_template('details', $id);
        if (!$row) {
          continue;
        }
        if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['domain'])) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_attr),
            'msg' => 'access_denied'
          );
          continue;
        }
        $name = isset($_data['name']) ? trim($_data['name']) : $row['name'];
        $description = isset($_data['description']) ? trim($_data['description']) : $row['description'];
        $html = isset($_data['html']) ? $_data['html'] : $row['html'];
        $plain = isset($_data['plain']) ? $_data['plain'] : $row['plain'];
        $skip_replies = isset($_data['skip_replies']) ? (int)!!$_data['skip_replies'] : (int)$row['skip_replies'];
        $active = isset($_data['active']) ? (int)!!$_data['active'] : (int)$row['active'];
        if ($name === '' || mb_strlen($name) > 255) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_attr),
            'msg' => 'signature_template_name_invalid'
          );
          continue;
        }
        try {
          $stmt = $pdo->prepare("UPDATE `signature_templates` SET
            `name` = :name, `description` = :description, `html` = :html, `plain` = :plain,
            `skip_replies` = :skip_replies, `active` = :active
            WHERE `id` = :id");
          $stmt->execute(array(
            ':id' => $id,
            ':name' => $name,
            ':description' => $description,
            ':html' => $html,
            ':plain' => $plain,
            ':skip_replies' => $skip_replies,
            ':active' => $active,
          ));
        }
        catch (PDOException $e) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_attr),
            'msg' => array('mysql_error', $e->getMessage())
          );
          continue;
        }
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => array('signature_template_modified', htmlspecialchars($name))
        );
      }
    break;
    case 'delete':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $id = (int)$id;
        $row = signature_template('details', $id);
        if (!$row) {
          continue;
        }
        if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['domain'])) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_attr),
            'msg' => 'access_denied'
          );
          continue;
        }
        $stmt = $pdo->prepare("DELETE FROM `signature_templates` WHERE `id` = :id");
        $stmt->execute(array(':id' => $id));
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => array('signature_template_removed', htmlspecialchars($row['name']))
        );
      }
    break;
    case 'get':
      $rows = array();
      if ($_SESSION['mailcow_cc_role'] == "admin") {
        $stmt = $pdo->query("SELECT `id`, `domain`, `name`, `description`, `skip_replies`, `active`, `created`, `modified`
          FROM `signature_templates` ORDER BY `domain`, `name`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } else {
        $domains = array_map('strval', (array)mailbox('get', 'domains'));
        if (empty($domains)) return array();
        $in = implode(',', array_fill(0, count($domains), '?'));
        $stmt = $pdo->prepare("SELECT `id`, `domain`, `name`, `description`, `skip_replies`, `active`, `created`, `modified`
          FROM `signature_templates` WHERE `domain` IN ($in) ORDER BY `domain`, `name`");
        $stmt->execute($domains);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      return $rows;
    break;
    case 'details':
      $id = (int)$_data;
      $stmt = $pdo->prepare("SELECT * FROM `signature_templates` WHERE `id` = :id");
      $stmt->execute(array(':id' => $id));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) return false;
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['domain'])) {
        return false;
      }
      return $row;
    break;
  }
}

function signature_rule($_action, $_data = null, $_attr = null) {
  global $pdo;
  global $lang;
  if ($_SESSION['mailcow_cc_role'] != "admin" && $_SESSION['mailcow_cc_role'] != "domainadmin") {
    return false;
  }
  $valid_match_types = array('domain', 'mailbox_tag', 'mailbox_address', 'custom_attribute');
  switch ($_action) {
    case 'add':
      $template_id = (int)$_data['template_id'];
      $stmt = $pdo->prepare("SELECT `domain` FROM `signature_templates` WHERE `id` = :id");
      $stmt->execute(array(':id' => $template_id));
      $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$tpl) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'signature_template_unknown'
        );
        return false;
      }
      $domain = $tpl['domain'];
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $domain)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'access_denied'
        );
        return false;
      }
      $match_type = isset($_data['match_type']) ? trim($_data['match_type']) : '';
      if (!in_array($match_type, $valid_match_types, true)) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'signature_rule_match_type_invalid'
        );
        return false;
      }
      $match_key = isset($_data['match_key']) ? trim($_data['match_key']) : '';
      $match_value = isset($_data['match_value']) ? trim($_data['match_value']) : '';
      $priority = isset($_data['priority']) ? (int)$_data['priority'] : 100;
      $active = isset($_data['active']) ? (int)!!$_data['active'] : 1;
      try {
        $stmt = $pdo->prepare("INSERT INTO `signature_rules`
          (`template_id`, `domain`, `priority`, `match_type`, `match_key`, `match_value`, `active`)
          VALUES (:template_id, :domain, :priority, :match_type, :match_key, :match_value, :active)");
        $stmt->execute(array(
          ':template_id' => $template_id,
          ':domain' => $domain,
          ':priority' => $priority,
          ':match_type' => $match_type,
          ':match_key' => $match_key,
          ':match_value' => $match_value,
          ':active' => $active,
        ));
      }
      catch (PDOException $e) {
        $_SESSION['return'][] = array(
          'type' => 'danger',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => array('mysql_error', $e->getMessage())
        );
        return false;
      }
      $_SESSION['return'][] = array(
        'type' => 'success',
        'log' => array(__FUNCTION__, $_action, $_data, $_attr),
        'msg' => 'signature_rule_added'
      );
    break;
    case 'edit':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $id = (int)$id;
        $row = signature_rule('details', $id);
        if (!$row) continue;
        $priority = isset($_data['priority']) ? (int)$_data['priority'] : (int)$row['priority'];
        $match_type = isset($_data['match_type']) ? trim($_data['match_type']) : $row['match_type'];
        $match_key = isset($_data['match_key']) ? trim($_data['match_key']) : $row['match_key'];
        $match_value = isset($_data['match_value']) ? trim($_data['match_value']) : $row['match_value'];
        $active = isset($_data['active']) ? (int)!!$_data['active'] : (int)$row['active'];
        if (!in_array($match_type, $valid_match_types, true)) {
          $_SESSION['return'][] = array(
            'type' => 'danger',
            'log' => array(__FUNCTION__, $_action, $_data, $_attr),
            'msg' => 'signature_rule_match_type_invalid'
          );
          continue;
        }
        $stmt = $pdo->prepare("UPDATE `signature_rules` SET
          `priority` = :priority, `match_type` = :match_type, `match_key` = :match_key,
          `match_value` = :match_value, `active` = :active
          WHERE `id` = :id");
        $stmt->execute(array(
          ':id' => $id,
          ':priority' => $priority,
          ':match_type' => $match_type,
          ':match_key' => $match_key,
          ':match_value' => $match_value,
          ':active' => $active,
        ));
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'signature_rule_modified'
        );
      }
    break;
    case 'delete':
      $ids = (array)$_data['id'];
      foreach ($ids as $id) {
        $id = (int)$id;
        $row = signature_rule('details', $id);
        if (!$row) continue;
        $stmt = $pdo->prepare("DELETE FROM `signature_rules` WHERE `id` = :id");
        $stmt->execute(array(':id' => $id));
        $_SESSION['return'][] = array(
          'type' => 'success',
          'log' => array(__FUNCTION__, $_action, $_data, $_attr),
          'msg' => 'signature_rule_removed'
        );
      }
    break;
    case 'get':
      $rows = array();
      if ($_SESSION['mailcow_cc_role'] == "admin") {
        $stmt = $pdo->query("SELECT r.*, t.`name` AS `template_name`
          FROM `signature_rules` r LEFT JOIN `signature_templates` t ON t.`id` = r.`template_id`
          ORDER BY r.`domain`, r.`priority` DESC, r.`id`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } else {
        $domains = array_map('strval', (array)mailbox('get', 'domains'));
        if (empty($domains)) return array();
        $in = implode(',', array_fill(0, count($domains), '?'));
        $stmt = $pdo->prepare("SELECT r.*, t.`name` AS `template_name`
          FROM `signature_rules` r LEFT JOIN `signature_templates` t ON t.`id` = r.`template_id`
          WHERE r.`domain` IN ($in)
          ORDER BY r.`domain`, r.`priority` DESC, r.`id`");
        $stmt->execute($domains);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      return $rows;
    break;
    case 'details':
      $id = (int)$_data;
      $stmt = $pdo->prepare("SELECT * FROM `signature_rules` WHERE `id` = :id");
      $stmt->execute(array(':id' => $id));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) return false;
      if (!hasDomainAccess($_SESSION['mailcow_cc_username'], $_SESSION['mailcow_cc_role'], $row['domain'])) {
        return false;
      }
      return $row;
    break;
  }
}

// Resolve the highest-priority active signature template for a given mailbox username.
// Bypasses session checks: callable from server-side dynmaps. Returns rendered html/plain or false.
function signature_resolve_for_mailbox($username) {
  global $pdo;
  $username = strtolower(trim($username));
  if (!filter_var($username, FILTER_VALIDATE_EMAIL)) return false;
  list($local_part, $domain) = explode('@', $username, 2);

  $stmt = $pdo->prepare("SELECT `username`, `name`, `local_part`, `domain`, `custom_attributes`
    FROM `mailbox` WHERE `username` = :u AND `active` = 1");
  $stmt->execute(array(':u' => $username));
  $mbox = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$mbox) return false;
  $custom = json_decode($mbox['custom_attributes'] ?? '{}', true) ?: array();

  $stmt = $pdo->prepare("SELECT r.*, t.`html`, t.`plain`, t.`skip_replies`, t.`name` AS `template_name`
    FROM `signature_rules` r
    INNER JOIN `signature_templates` t ON t.`id` = r.`template_id`
    WHERE r.`domain` = :d AND r.`active` = 1 AND t.`active` = 1
    ORDER BY r.`priority` DESC, r.`id` ASC");
  $stmt->execute(array(':d' => $domain));
  $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rules) return false;

  $tags = array();
  $tagStmt = $pdo->prepare("SELECT `tag_name` FROM `tags_mailbox` WHERE `username` = :u");
  $tagStmt->execute(array(':u' => $username));
  foreach ($tagStmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $tags[strtolower($t)] = true;
  }

  $matched = null;
  foreach ($rules as $r) {
    if (signature_rule_matches($r, $username, $local_part, $domain, $tags, $custom)) {
      $matched = $r;
      break;
    }
  }
  if (!$matched) return false;

  $vars = array_merge($custom, array(
    'name' => $mbox['name'] ?? '',
    'email' => $mbox['username'],
    'local_part' => $mbox['local_part'],
    'domain' => $mbox['domain'],
  ));
  return array(
    'template_id' => (int)$matched['template_id'],
    'template_name' => $matched['template_name'],
    'rule_id' => (int)$matched['id'],
    'skip_replies' => (int)$matched['skip_replies'],
    'html' => signature_render($matched['html'] ?? '', $vars),
    'plain' => signature_render($matched['plain'] ?? '', $vars),
  );
}

function signature_rule_matches($rule, $username, $local_part, $domain, $tags, $custom) {
  switch ($rule['match_type']) {
    case 'domain':
      return true;
    case 'mailbox_tag':
      $key = strtolower(trim($rule['match_key']));
      return $key !== '' && isset($tags[$key]);
    case 'mailbox_address':
      $pattern = trim($rule['match_key']);
      if ($pattern === '') return false;
      // Wildcard glob: '*' -> '.*', '?' -> '.'; case-insensitive
      $regex = '/^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, '/')) . '$/i';
      return (bool)preg_match($regex, $username);
    case 'custom_attribute':
      $key = $rule['match_key'];
      if ($key === '' || !array_key_exists($key, $custom)) return false;
      $expected = $rule['match_value'];
      $actual = is_scalar($custom[$key]) ? (string)$custom[$key] : json_encode($custom[$key]);
      return $expected === '' ? ($actual !== '') : ($actual === $expected);
  }
  return false;
}

// Render a template with simple Mustache-flavoured syntax:
//   {{ key }}                — replaced with the value (HTML-escaped), empty if missing
//   {{#key}}...{{/key}}      — keep the block only if the key has a non-empty value
//   {{^key}}...{{/key}}      — keep the block only if the key is missing or empty
// When a conditional block is removed, a single trailing newline is also consumed so
// blank lines don't litter the output.
function signature_render($template, $vars) {
  if ($template === '' || $template === null) return '';
  $is_present = function ($val) {
    return is_scalar($val) && trim((string)$val) !== '';
  };
  // Resolve conditional sections; loop until stable so simple nesting works.
  do {
    $prev = $template;
    $template = preg_replace_callback(
      '/\{\{\s*#\s*([a-zA-Z0-9_.-]+)\s*\}\}(.*?)\{\{\s*\/\s*\1\s*\}\}(\r?\n?)/s',
      function ($m) use ($vars, $is_present) {
        $present = array_key_exists($m[1], $vars) && $is_present($vars[$m[1]]);
        return $present ? $m[2] . $m[3] : '';
      },
      $template
    );
    $template = preg_replace_callback(
      '/\{\{\s*\^\s*([a-zA-Z0-9_.-]+)\s*\}\}(.*?)\{\{\s*\/\s*\1\s*\}\}(\r?\n?)/s',
      function ($m) use ($vars, $is_present) {
        $present = array_key_exists($m[1], $vars) && $is_present($vars[$m[1]]);
        return $present ? '' : $m[2] . $m[3];
      },
      $template
    );
  } while ($prev !== $template);
  // Replace plain placeholders.
  return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function ($m) use ($vars) {
    if (array_key_exists($m[1], $vars) && is_scalar($vars[$m[1]])) {
      return htmlspecialchars((string)$vars[$m[1]], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
  }, $template);
}
