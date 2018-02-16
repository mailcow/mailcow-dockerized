<?php
require_once("inc/prerequisites.inc.php");
$AuthUsers = array("admin", "domainadmin", "user");
if (!isset($_SESSION['mailcow_cc_role']) OR !in_array($_SESSION['mailcow_cc_role'], $AuthUsers)) {
	header('Location: /');
	exit();
}
require_once("inc/header.inc.php");
?>
<div class="container">
	<div class="row">
		<div class="col-md-12">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?=$lang['edit']['title'];?></h3>
				</div>
				<div class="panel-body">
<?php
if (isset($_SESSION['mailcow_cc_role'])) {
  if ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin") {
      if (isset($_GET["alias"]) &&
        !empty($_GET["alias"])) {
          $alias = html_entity_decode(rawurldecode($_GET["alias"]));
          $result = mailbox('get', 'alias_details', $alias);
          if (!empty($result)) {
          ?>
            <h4><?=$lang['edit']['alias'];?></h4>
            <br />
            <form class="form-horizontal" data-id="editalias" role="form" method="post">
              <input type="hidden" value="0" name="active">
              <div class="form-group">
                <label class="control-label col-sm-2" for="goto"><?=$lang['edit']['target_address'];?></label>
                <div class="col-sm-10">
                  <textarea id="textarea_alias_goto" class="form-control" autocapitalize="none" autocorrect="off" rows="10" id="goto" name="goto" required><?= ($result['goto'] != "null@localhost") ? htmlspecialchars($result['goto']) : null; ?></textarea>
                  <div class="checkbox">
                    <label><input id="goto_null" type="checkbox" value="1" name="goto_null" <?= ($result['goto'] == "null@localhost") ? "checked" : null; ?>> <?=$lang['add']['goto_null'];?></label>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                  <div class="checkbox">
                  <label><input type="checkbox" value="1" name="active" <?php if (isset($result['active_int']) && $result['active_int']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                  <button class="btn btn-success" id="edit_selected" data-id="editalias" data-item="<?=htmlspecialchars($alias);?>" data-api-url='edit/alias' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
                </div>
              </div>
            </form>
          <?php
          }
          else {
          ?>
            <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
          <?php
          }
      }
      elseif (isset($_GET['domainadmin']) &&
          ctype_alnum(str_replace(array('_', '.', '-'), '', $_GET["domainadmin"])) &&
          !empty($_GET["domainadmin"]) &&
          $_GET["domainadmin"] != 'admin' &&
          $_SESSION['mailcow_cc_role'] == "admin") {
          $domain_admin = $_GET["domainadmin"];
          $result = domain_admin('details', $domain_admin);
          if (!empty($result)) {
          ?>
          <h4><?=$lang['edit']['domain_admin'];?></h4>
          <br />
          <form class="form-horizontal" data-id="editdomainadmin" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="username_new"><?=$lang['edit']['username'];?></label>
              <div class="col-sm-10">
                <input class="form-control" type="text" name="username_new" value="<?=htmlspecialchars($domain_admin);?>" />
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="domains"><?=$lang['edit']['domains'];?></label>
              <div class="col-sm-10">
                <select id="domains" name="domains" multiple required>
                <?php
                foreach ($result['selected_domains'] as $domain):
                ?>
                  <option selected><?=htmlspecialchars($domain);?></option>
                <?php
                endforeach;
                foreach ($result['unselected_domains'] as $domain):
                ?>
                  <option><?=htmlspecialchars($domain);?></option>
                <?php
                endforeach;
                ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password"><?=$lang['edit']['password'];?></label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password" id="password" placeholder="">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password2"><?=$lang['edit']['password_repeat'];?></label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password2" id="password2">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?php if (isset($result['active_int']) && $result['active_int']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="disable_tfa"> <?=$lang['tfa']['disable_tfa'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editdomainadmin" data-item="<?=$domain_admin;?>" data-api-url='edit/domain-admin' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
    elseif (isset($_GET['domain']) &&
      is_valid_domain_name($_GET["domain"]) &&
      !empty($_GET["domain"])) {
        $domain = $_GET["domain"];
        $result = mailbox('get', 'domain_details', $domain);
        $rl = mailbox('get', 'ratelimit', $domain);
        $rlyhosts = relayhost('get');
        if (!empty($result)) {
        ?>
          <h4><?=$lang['edit']['domain'];?></h4>
          <form data-id="editdomain" class="form-horizontal" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <input type="hidden" value="0" name="backupmx">
            <input type="hidden" value="0" name="relay_all_recipients">
            <div class="form-group">
              <label class="control-label col-sm-2" for="description"><?=$lang['edit']['description'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="description" id="description" value="<?=htmlspecialchars($result['description']);?>">
              </div>
            </div>
            <?php
            if ($_SESSION['mailcow_cc_role'] == "admin") {
            ?>
            <div class="form-group">
              <label class="control-label col-sm-2" for="aliases"><?=$lang['edit']['max_aliases'];?></label>
              <div class="col-sm-10">
                <input type="number" class="form-control" name="aliases" id="aliases" value="<?=intval($result['max_num_aliases_for_domain']);?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="mailboxes"><?=$lang['edit']['max_mailboxes'];?></label>
              <div class="col-sm-10">
                <input type="number" class="form-control" name="mailboxes" id="mailboxes" value="<?=intval($result['max_num_mboxes_for_domain']);?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="maxquota"><?=$lang['edit']['max_quota'];?></label>
              <div class="col-sm-10">
                <input type="number" class="form-control" name="maxquota" id="maxquota" value="<?=intval($result['max_quota_for_mbox'] / 1048576);?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="quota"><?=$lang['edit']['domain_quota'];?></label>
              <div class="col-sm-10">
                <input type="number" class="form-control" name="quota" id="quota" value="<?=intval($result['max_quota_for_domain'] / 1048576);?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="quota">Relayhost</label>
              <div class="col-sm-10">
                <select name="relayhost" id="relayhost" class="form-control">
                  <?php
                  foreach ($rlyhosts as $rlyhost) {
                  ?>
                  <option value="<?=$rlyhost['id'];?>" <?=($result['relayhost'] == $rlyhost['id']) ? 'selected' : null;?>>ID <?=$rlyhost['id'];?>: <?=$rlyhost['hostname'];?> (<?=$rlyhost['username'];?>)</option>
                  <?php
                  }
                  ?>
                  <option value="" <?=($result['relayhost'] == "0") ? 'selected' : null;?>>None</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2"><?=$lang['edit']['backup_mx_options'];?></label>
              <div class="col-sm-10">
                <div class="checkbox">
                  <label><input type="checkbox" value="1" name="backupmx" <?=(isset($result['backupmx_int']) && $result['backupmx_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['relay_domain'];?></label>
                  <br />
                  <label><input type="checkbox" value="1" name="relay_all_recipients" <?=(isset($result['relay_all_recipients_int']) && $result['relay_all_recipients_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['relay_all'];?></label>
                  <p><?=$lang['edit']['relay_all_info'];?></p>
                </div>
              </div>
            </div>
            <?php
            }
            ?>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                  <label><input type="checkbox" value="1" name="active" <?=(isset($result['active_int']) && $result['active_int']=="1") ? "checked" : null;?> <?=($_SESSION['mailcow_cc_role'] == "admin") ? null : "disabled";?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editdomain" data-item="<?=$domain;?>" data-api-url='edit/domain' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
              </div>
            </div>
          </form>
          <?php
          if (!empty($dkim = dkim('details', $domain))) {
          ?>
          <hr>
          <div class="row">
            <div class="col-xs-2">
              <p>Domain: <strong><?=htmlspecialchars($result['domain_name']);?></strong> (<?=$dkim['dkim_selector'];?>._domainkey)</p>
            </div>
            <div class="col-xs-10">
              <pre><?=$dkim['dkim_txt'];?></pre>
            </div>
          </div>
          <?php
          }
          ?>
      <hr>
      <form data-id="domratelimit" class="form-inline well" method="post">
        <div class="form-group">
          <label class="control-label">Ratelimit</label>
          <input name="rl_value" id="rl_value" type="number" value="<?=(!empty($rl['value'])) ? $rl['value'] : null;?>" class="form-control" placeholder="disabled">
        </div>
        <div class="form-group">
          <select name="rl_frame" id="rl_frame" class="form-control">
            <option value="s" <?=(isset($rl['frame']) && $rl['frame'] == 's') ? 'selected' : null;?>>msgs / second</option>
            <option value="m" <?=(isset($rl['frame']) && $rl['frame'] == 'm') ? 'selected' : null;?>>msgs / minute</option>
            <option value="h" <?=(isset($rl['frame']) && $rl['frame'] == 'h') ? 'selected' : null;?>>msgs / hour</option>
          </select>
        </div>
        <div class="form-group">
          <button class="btn btn-default" id="edit_selected" data-id="domratelimit" data-item="<?=$domain;?>" data-api-url='edit/ratelimit' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
        </div>
      </form>
      <hr>
      <div class="row">
        <div class="col-sm-6">
          <h4><?=$lang['user']['spamfilter_wl'];?></h4>
          <p><?=$lang['user']['spamfilter_wl_desc'];?></p>
          <div class="table-responsive">
            <table class="table table-striped table-condensed" id="wl_policy_domain_table"></table>
          </div>
          <div class="mass-actions-user">
            <div class="btn-group">
              <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="policy_wl_domain" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
              <a class="btn btn-sm btn-danger" id="delete_selected" data-id="policy_wl_domain" data-api-url='delete/domain-policy' href="#"><?=$lang['mailbox']['remove'];?></a></li>
              </ul>
            </div>
          </div>
          <form class="form-inline" data-id="add_wl_policy_domain">
            <div class="input-group">
              <input type="text" class="form-control" name="object_from" id="object_from" placeholder="*@example.org" required>
              <span class="input-group-btn">
                <button class="btn btn-default" id="add_item" data-id="add_wl_policy_domain" data-api-url='add/domain-policy' data-api-attr='{"domain":"<?= $domain; ?>","object_list":"wl"}' href="#"><?=$lang['user']['spamfilter_table_add'];?></button>
              </span>
            </div>
          </form>
        </div>
        <div class="col-sm-6">
          <h4><?=$lang['user']['spamfilter_bl'];?></h4>
          <p><?=$lang['user']['spamfilter_bl_desc'];?></p>
          <div class="table-responsive">
            <table class="table table-striped table-condensed" id="bl_policy_domain_table"></table>
          </div>
          <div class="mass-actions-user">
            <div class="btn-group">
              <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="policy_bl_domain" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
              <a class="btn btn-sm btn-danger" id="delete_selected" data-id="policy_bl_domain" data-api-url='delete/domain-policy' href="#"><?=$lang['mailbox']['remove'];?></a></li>
              </ul>
            </div>
          </div>
          <form class="form-inline" data-id="add_bl_policy_domain">
            <div class="input-group">
              <input type="text" class="form-control" name="object_from" id="object_from" placeholder="*@example.org" required>
              <span class="input-group-btn">
                <button class="btn btn-default" id="add_item" data-id="add_bl_policy_domain" data-api-url='add/domain-policy' data-api-attr='{"domain":"<?= $domain; ?>","object_list":"bl"}' href="#"><?=$lang['user']['spamfilter_table_add'];?></button>
              </span>
            </div>
          </form>
        </div>
      </div>
          <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
    elseif (isset($_GET['aliasdomain']) &&
      is_valid_domain_name(html_entity_decode(rawurldecode($_GET["aliasdomain"]))) &&
      !empty($_GET["aliasdomain"])) {
        $alias_domain = html_entity_decode(rawurldecode($_GET["aliasdomain"]));
        $result = mailbox('get', 'alias_domain_details', $alias_domain);
        $rl = mailbox('get', 'ratelimit', $alias_domain);
        if (!empty($result)) {
        ?>
          <h4><?=$lang['edit']['edit_alias_domain'];?></h4>
          <form class="form-horizontal" data-id="editaliasdomain" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="target_domain"><?=$lang['edit']['target_domain'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="target_domain" id="target_domain" value="<?=htmlspecialchars($result['target_domain']);?>">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                  <label><input type="checkbox" value="1" name="active" <?=(isset($result['active_int']) && $result['active_int']=="1") ?  "checked" : null ?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editaliasdomain" data-item="<?=$alias_domain;?>" data-api-url='edit/alias-domain' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
          <hr>
          <form data-id="domratelimit" class="form-inline well" method="post">
            <div class="form-group">
              <label class="control-label">Ratelimit</label>
              <input name="rl_value" id="rl_value" type="number" value="<?=(!empty($rl['value'])) ? $rl['value'] : null;?>" class="form-control" placeholder="disabled">
            </div>
            <div class="form-group">
              <select name="rl_frame" id="rl_frame" class="form-control">
                <option value="s" <?=(isset($rl['frame']) && $rl['frame'] == 's') ? 'selected' : null;?>>msgs / second</option>
                <option value="m" <?=(isset($rl['frame']) && $rl['frame'] == 'm') ? 'selected' : null;?>>msgs / minute</option>
                <option value="h" <?=(isset($rl['frame']) && $rl['frame'] == 'h') ? 'selected' : null;?>>msgs / hour</option>
              </select>
            </div>
            <div class="form-group">
              <button class="btn btn-default" id="edit_selected" data-id="domratelimit" data-item="<?=$alias_domain;?>" data-api-url='edit/ratelimit' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
            </div>
          </form>
          <?php
          if (!empty($dkim = dkim('details', $alias_domain))) {
          ?>
          <hr>
          <div class="row">
            <div class="col-xs-2">
              <p>Domain: <strong><?=htmlspecialchars($result['alias_domain']);?></strong> (<?=$dkim['dkim_selector'];?>._domainkey)</p>
            </div>
            <div class="col-xs-10">
            <pre><?=$dkim['dkim_txt'];?></pre>
            </div>
          </div>
          <?php
          }
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
    elseif (isset($_GET['mailbox']) && filter_var(html_entity_decode(rawurldecode($_GET["mailbox"])), FILTER_VALIDATE_EMAIL) && !empty($_GET["mailbox"])) {
      $mailbox = html_entity_decode(rawurldecode($_GET["mailbox"]));
      $result = mailbox('get', 'mailbox_details', $mailbox);
      $rl = mailbox('get', 'ratelimit', $mailbox);
      if (!empty($result)) {
        ?>
        <h4><?=$lang['edit']['mailbox'];?></h4>
        <form class="form-horizontal" data-id="editmailbox" role="form" method="post">
          <input type="hidden" value="0" name="sender_acl">
          <input type="hidden" value="0" name="active">
          <input type="hidden" value="0" name="force_pw_update">
          <div class="form-group">
            <label class="control-label col-sm-2" for="name"><?=$lang['edit']['full_name'];?>:</label>
            <div class="col-sm-10">
            <input type="text" class="form-control" name="name" id="name" value="<?=htmlspecialchars($result['name'], ENT_QUOTES, 'UTF-8');?>">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="quota"><?=$lang['edit']['quota_mb'];?>:
              <br /><span id="quotaBadge" class="badge">max. <?=intval($result['max_new_quota'] / 1048576)?> MiB</span>
            </label>
            <div class="col-sm-10">
              <input type="number" name="quota" id="quota" id="destroyable" style="width:100%" min="1" max="<?=intval($result['max_new_quota'] / 1048576);?>" value="<?=intval($result['quota']) / 1048576;?>" class="form-control">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="sender_acl"><?=$lang['edit']['sender_acl'];?>:</label>
            <div class="col-sm-10">
              <select data-width="100%" style="width:100%" id="sender_acl" name="sender_acl" size="10" multiple>
              <?php
              $sender_acl_handles = mailbox('get', 'sender_acl_handles', $mailbox);

              foreach ($sender_acl_handles['sender_acl_domains']['ro'] as $domain):
                ?>
                <option data-subtext="Admin" value="<?=htmlspecialchars($domain);?>" disabled selected><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $domain));?></option>
                <?php
              endforeach;

              foreach ($sender_acl_handles['sender_acl_addresses']['ro'] as $domain):
                ?>
              <option data-subtext="Admin" disabled selected><?=htmlspecialchars($alias);?></option>
                <?php
              endforeach;

              foreach ($sender_acl_handles['fixed_sender_aliases'] as $alias):
                ?>
                <option data-subtext="Alias" disabled selected><?=htmlspecialchars($alias);?></option>
                <?php
              endforeach;

              foreach ($sender_acl_handles['sender_acl_domains']['rw'] as $domain):
                ?>
                <option value="<?=htmlspecialchars($domain);?>" selected><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $domain));?></option>
                <?php
              endforeach;

              foreach ($sender_acl_handles['sender_acl_domains']['selectable'] as $domain):
                ?>
                <option value="<?=htmlspecialchars($domain);?>"><?=htmlspecialchars(sprintf($lang['edit']['dont_check_sender_acl'], $domain));?></option>
                <?php
              endforeach;

              foreach ($sender_acl_handles['sender_acl_addresses']['rw'] as $address):
                ?>
                  <option selected><?=htmlspecialchars($address);?></option>
                <?php
              endforeach;

              foreach ($sender_acl_handles['sender_acl_addresses']['selectable'] as $address):
                ?>
                  <option><?=htmlspecialchars($address);?></option>
                <?php
              endforeach;

              ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="password"><?=$lang['edit']['password'];?></label>
            <div class="col-sm-10">
            <input type="password" class="form-control" name="password" id="password" placeholder="<?=$lang['edit']['unchanged_if_empty'];?>">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="password2"><?=$lang['edit']['password_repeat'];?></label>
            <div class="col-sm-10">
            <input type="password" class="form-control" name="password2" id="password2">
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
              <div class="checkbox">
              <label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['active'];?></label>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
              <div class="checkbox">
              <label><input type="checkbox" value="1" name="force_pw_update" <?=($result['attributes']['force_pw_update']=="1") ? "checked" : null;?>> <?=$lang['edit']['force_pw_update'];?></label>
              <small class="help-block"><?=$lang['edit']['force_pw_update_info'];?></small>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-success" id="edit_selected" data-id="editmailbox" data-item="<?=htmlspecialchars($result['username']);?>" data-api-url='edit/mailbox' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
            </div>
          </div>
        </form>
        <hr>
        <form data-id="mboxratelimit" class="form-inline well" method="post">
          <div class="row">
            <div class="col-sm-1">
              <p class="help-block">Ratelimit</p>
            </div>
            <div class="col-sm-10">
              <div class="form-group">
                <input name="rl_value" id="rl_value" type="number" value="<?=(!empty($rl['value'])) ? $rl['value'] : null;?>" class="form-control" placeholder="disabled">
              </div>
              <div class="form-group">
                <select name="rl_frame" id="rl_frame" class="form-control">
                  <option value="s" <?=(isset($rl['frame']) && $rl['frame'] == 's') ? 'selected' : null;?>>msgs / second</option>
                  <option value="m" <?=(isset($rl['frame']) && $rl['frame'] == 'm') ? 'selected' : null;?>>msgs / minute</option>
                  <option value="h" <?=(isset($rl['frame']) && $rl['frame'] == 'h') ? 'selected' : null;?>>msgs / hour</option>
                </select>
              </div>
              <div class="form-group">
                <button class="btn btn-default" id="edit_selected" data-id="mboxratelimit" data-item="<?=htmlspecialchars($mailbox);?>" data-api-url='edit/ratelimit' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
              </div>
            </div>
          </div>
        </form>
      <?php
      }
    }
    elseif (isset($_GET['relayhost']) && is_numeric($_GET["relayhost"]) && !empty($_GET["relayhost"])) {
        $relayhost = intval($_GET["relayhost"]);
        $result = relayhost('details', $relayhost);
        if (!empty($result)) {
          ?>
          <h4><?=$lang['edit']['resource'];?></h4>
          <form class="form-horizontal" role="form" method="post" data-id="editrelayhost">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="hostname"><?=$lang['add']['hostname'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="hostname" id="hostname" value="<?=htmlspecialchars($result['hostname'], ENT_QUOTES, 'UTF-8');?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="username"><?=$lang['add']['username'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="username" id="username" value="<?=htmlspecialchars($result['username'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password"><?=$lang['add']['password'];?></label>
              <div class="col-sm-10">
                <input type="password" class="form-control" name="password" id="password" value="<?=htmlspecialchars($result['password'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editrelayhost" data-item="<?=htmlspecialchars($result['id']);?>" data-api-url='edit/relayhost' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
    elseif (isset($_GET['resource']) && filter_var(html_entity_decode(rawurldecode($_GET["resource"])), FILTER_VALIDATE_EMAIL) && !empty($_GET["resource"])) {
        $resource = html_entity_decode(rawurldecode($_GET["resource"]));
        $result = mailbox('get', 'resource_details', $resource);
        if (!empty($result)) {
          ?>
          <h4><?=$lang['edit']['resource'];?></h4>
          <form class="form-horizontal" role="form" method="post" data-id="editresource">
            <input type="hidden" value="0" name="active">
            <input type="hidden" value="0" name="multiple_bookings">
            <div class="form-group">
              <label class="control-label col-sm-2" for="description"><?=$lang['add']['description'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="description" id="description" value="<?=htmlspecialchars($result['description'], ENT_QUOTES, 'UTF-8');?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="domain"><?=$lang['edit']['kind'];?>:</label>
              <div class="col-sm-10">
                <select name="kind" id="kind" title="<?=$lang['edit']['select'];?>" required>
                  <option value="location" <?=($result['kind'] == "location") ? "selected" : null;?>>Location</option>
                  <option value="group" <?=($result['kind'] == "group") ? "selected" : null;?>>Group</option>
                  <option value="thing" <?=($result['kind'] == "thing") ? "selected" : null;?>>Thing</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="multiple_bookings" <?=($result['multiple_bookings_int']=="1") ? "checked" : null;?>> <?=$lang['edit']['multiple_bookings'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editresource" data-item="<?=htmlspecialchars($result['name']);?>" data-api-url='edit/resource' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
    elseif (isset($_GET['bcc']) && !empty($_GET["bcc"])) {
        $bcc = intval($_GET["bcc"]);
        $result = bcc('details', $bcc);
        if (!empty($result)) {
          ?>
          <h4>BCC map</h4>
          <br />
          <form class="form-horizontal" data-id="editbcc" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="bcc_dest">BCC destination</label>
              <div class="col-sm-10">
                <textarea id="bcc_dest" class="form-control" autocapitalize="none" autocorrect="off" rows="10" id="bcc_dest" name="bcc_dest" required><?=$result['bcc_dest'];?></textarea>
                <small>BCC destination must be a single valid email address.</small>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="type">Type:</label>
              <div class="col-sm-10">
                <select id="addFilterType" name="type" id="type" required>
                  <option value="sender" <?=($result['type'] == 'sender') ? 'selected' : null;?>>Sender map</option>
                  <option value="rcpt" <?=($result['type'] == 'rcpt') ? 'selected' : null;?>>Recipient map</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?php if (isset($result['active_int']) && $result['active_int']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editbcc" data-item="<?=$bcc;?>" data-api-url='edit/bcc' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
    elseif (isset($_GET['recipient_map']) && !empty($_GET["recipient_map"])) {
        $map = intval($_GET["recipient_map"]);
        $result = recipient_map('details', $map);
        if (!empty($result)) {
          ?>
          <h4>Recipient map: <?=$result['recipient_map_old'];?></h4>
          <br />
          <form class="form-horizontal" data-id="edit_recipient_map" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="recipient_map_new">New destination</label>
              <div class="col-sm-10">
                <textarea id="recipient_map_new" class="form-control" autocapitalize="none" autocorrect="off" rows="10" id="recipient_map_new" name="recipient_map_new" required><?=$result['recipient_map_new'];?></textarea>
                <small>Recipient map destinations can only be valid email addresses. Separated by whitespace, semicolon, new line or comma.</small>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?php if (isset($result['active_int']) && $result['active_int']=="1") { echo "checked"; }; ?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="edit_recipient_map" data-item="<?=$map;?>" data-api-url='edit/recipient_map' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
  }
  if ($_SESSION['mailcow_cc_role'] == "admin"  || $_SESSION['mailcow_cc_role'] == "domainadmin" || $_SESSION['mailcow_cc_role'] == "user") {
    if (isset($_GET['syncjob']) &&
      is_numeric($_GET['syncjob'])) {
        $id = $_GET["syncjob"];
        $result = mailbox('get', 'syncjob_details', $id);
        if (!empty($result)) {
        ?>
          <h4><?=$lang['edit']['syncjob'];?></h4>
          <form class="form-horizontal" data-id="editsyncjob" role="form" method="post">
            <input type="hidden" value="0" name="delete2duplicates">
            <input type="hidden" value="0" name="delete1">
            <input type="hidden" value="0" name="delete2">
            <input type="hidden" value="0" name="automap">
            <input type="hidden" value="0" name="skipcrossduplicates">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="host1"><?=$lang['edit']['hostname'];?></label>
              <div class="col-sm-10">
              <input type="text" class="form-control" name="host1" id="host1" value="<?=htmlspecialchars($result['host1'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="port1">Port</label>
              <div class="col-sm-10">
              <input type="number" class="form-control" name="port1" id="port1" min="1" max="65535" value="<?=htmlspecialchars($result['port1'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="user1"><?=$lang['edit']['username'];?></label>
              <div class="col-sm-10">
              <input type="text" class="form-control" name="user1" id="user1" value="<?=htmlspecialchars($result['user1'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password1"><?=$lang['edit']['password'];?></label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password1" id="password1" value="<?=htmlspecialchars($result['password1'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="enc1"><?=$lang['edit']['encryption'];?>:</label>
              <div class="col-sm-10">
                <select id="enc1" name="enc1">
                  <option <?=($result['enc1'] == "TLS") ? "selected" : null;?>>TLS</option>
                  <option <?=($result['enc1'] == "SSL") ? "selected" : null;?>>SSL</option>
                  <option <?=($result['enc1'] == "PLAIN") ? "selected" : null;?>>PLAIN</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="mins_interval"><?=$lang['edit']['mins_interval'];?></label>
              <div class="col-sm-10">
                <input type="number" class="form-control" name="mins_interval" min="1" max="3600" value="<?=htmlspecialchars($result['mins_interval'], ENT_QUOTES, 'UTF-8');?>" required>
                <small class="help-block">10-3600</small>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="subfolder2"><?=$lang['edit']['subfolder2'];?></label>
              <div class="col-sm-10">
              <input type="text" class="form-control" name="subfolder2" id="subfolder2" value="<?=htmlspecialchars($result['subfolder2'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="maxage"><?=$lang['edit']['maxage'];?></label>
              <div class="col-sm-10">
              <input type="number" class="form-control" name="maxage" id="maxage" min="0" max="32000" value="<?=htmlspecialchars($result['maxage'], ENT_QUOTES, 'UTF-8');?>">
              <small class="help-block">0-32000</small>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="maxbytespersecond"><?=$lang['edit']['maxbytespersecond'];?></label>
              <div class="col-sm-10">
              <input type="number" class="form-control" name="maxbytespersecond" id="maxbytespersecond" min="0" max="125000000" value="<?=htmlspecialchars($result['maxbytespersecond'], ENT_QUOTES, 'UTF-8');?>">
              <small class="help-block">0-125000000</small>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="exclude"><?=$lang['edit']['exclude'];?></label>
              <div class="col-sm-10">
              <input type="text" class="form-control" name="exclude" id="exclude" value="<?=htmlspecialchars($result['exclude'], ENT_QUOTES, 'UTF-8');?>">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="delete2duplicates" <?=($result['delete2duplicates']=="1") ? "checked" : "";?>> <?=$lang['edit']['delete2duplicates'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="delete1" <?=($result['delete1']=="1") ? "checked" : "";?>> <?=$lang['edit']['delete1'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="delete2" <?=($result['delete2']=="1") ? "checked" : "";?>> <?=$lang['edit']['delete2'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="automap" <?=($result['automap']=="1") ? "checked" : "";?>> <?=$lang['edit']['automap'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="skipcrossduplicates" <?=($result['skipcrossduplicates']=="1") ? "checked" : "";?>> <?=$lang['edit']['skipcrossduplicates'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : "";?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editsyncjob" data-item="<?=htmlspecialchars($result['id']);?>" data-api-url='edit/syncjob' data-api-attr='{}' href="#"><?=$lang['edit']['save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
      }
    elseif (isset($_GET['filter']) &&
      is_numeric($_GET['filter'])) {
        $id = $_GET["filter"];
        $result = mailbox('get', 'filter_details', $id);
        if (!empty($result)) {
        ?>
          <h4>Filter</h4>
          <form class="form-horizontal" data-id="editfilter" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="script_desc"><?=$lang['edit']['sieve_desc'];?>:</label>
              <div class="col-sm-10">
              <input type="text" class="form-control" name="script_desc" id="script_desc" value="<?=htmlspecialchars($result['script_desc'], ENT_QUOTES, 'UTF-8');?>" required maxlength="255">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="filter_type"><?=$lang['edit']['sieve_type'];?>:</label>
              <div class="col-sm-10">
                <select id="addFilterType" name="filter_type" id="filter_type" required>
                  <option value="prefilter" <?=($result['filter_type'] == 'prefilter') ? 'selected' : null;?>>Prefilter</option>
                  <option value="postfilter" <?=($result['filter_type'] == 'postfilter') ? 'selected' : null;?>>Postfilter</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="script_data">Script:</label>
              <div class="col-sm-10">
                <textarea spellcheck="false" autocorrect="off" autocapitalize="none" class="form-control" rows="20" id="script_data" name="script_data" required><?=$result['script_data'];?></textarea>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" <?=($result['active_int']=="1") ? "checked" : "";?>> <?=$lang['edit']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-success" id="edit_selected" data-id="editfilter" data-item="<?=htmlspecialchars($result['id']);?>" data-api-url='edit/filter' data-api-attr='{}' href="#"><?=$lang['edit']['validate_save'];?></button>
              </div>
            </div>
          </form>
        <?php
        }
        else {
        ?>
          <div class="alert alert-info" role="alert"><?=$lang['info']['no_action'];?></div>
        <?php
        }
    }
  }
}
else {
?>
	<div class="alert alert-danger" role="alert"><?=$lang['danger']['access_denied'];?></div>
<?php
}
?>
				</div>
			</div>
		</div>
	</div>
<a href="<?=$_SESSION['return_to'];?>">&#8592; <?=$lang['edit']['previous'];?></a>
</div> <!-- /container -->
<script type='text/javascript'>
<?php
$lang_user = json_encode($lang['user']);
echo "var lang = ". $lang_user . ";\n";
echo "var table_for_domain = '". ((isset($domain)) ? $domain : null) . "';\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/edit.js"></script>
<?php
require_once("inc/footer.inc.php");
?>
