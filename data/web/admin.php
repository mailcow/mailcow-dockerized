<?php
require_once("inc/prerequisites.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$tfa_data = get_tfa();
?>
<div class="container">

  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active">
      <a href="#tab-access" aria-controls="tab-access" role="tab" data-toggle="tab"><?=$lang['admin']['access'];?></a>
    </li>
    <li role="presentation">
      <a href="#tab-config" aria-controls="tab-config" role="tab" data-toggle="tab"><?=$lang['admin']['configuration'];?></a>
    </li>
    <li class="dropdown">
    <a class="dropdown-toggle" data-toggle="dropdown" href="#">Logs
    <span class="caret"></span></a>
    <ul class="dropdown-menu">
    <li role="presentation"><a href="#tab-postfix-logs" aria-controls="tab-postfix-logs" role="tab" data-toggle="tab">Postfix</a></li>
    <li role="presentation"><a href="#tab-dovecot-logs" aria-controls="tab-dovecot-logs" role="tab" data-toggle="tab">Dovecot</a></li>
    <li role="presentation"><a href="#tab-sogo-logs" aria-controls="tab-sogo-logs" role="tab" data-toggle="tab">SOGo</a></li>
    <li role="presentation"><a href="#tab-fail2ban-logs" aria-controls="tab-fail2ban-logs" role="tab" data-toggle="tab">Fail2ban</a></li>
    <li role="presentation"><a href="#tab-rspamd-history" aria-controls="tab-rspamd-history" role="tab" data-toggle="tab">Rspamd</a></li>
    <li role="presentation"><a href="#tab-autodiscover-logs" aria-controls="tab-autodiscover-logs" role="tab" data-toggle="tab">Autodiscover</a></li>
    </ul>
    </li>
  </ul>

  <div class="tab-content" style="padding-top:20px">
  <div role="tabpanel" class="tab-pane active" id="tab-access">
    <div class="panel panel-danger">
      <div class="panel-heading"><?=$lang['admin']['admin_details'];?></div>
      <div class="panel-body">
        <form class="form-horizontal" autocapitalize="none" data-id="admin" autocorrect="off" role="form" method="post">
        <?php $admindetails = get_admin_details(); ?>
          <div class="form-group">
            <label class="control-label col-sm-3" for="admin_user"><?=$lang['admin']['admin'];?>:</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="admin_user" id="admin_user" value="<?=htmlspecialchars($admindetails['username']);?>" required>
              &rdsh; <kbd>a-z A-Z - _ .</kbd>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-3" for="admin_pass"><?=$lang['admin']['password'];?>:</label>
            <div class="col-sm-9">
            <input type="password" class="form-control" name="admin_pass" id="admin_pass" placeholder="<?=$lang['admin']['unchanged_if_empty'];?>">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-3" for="admin_pass2"><?=$lang['admin']['password_repeat'];?>:</label>
            <div class="col-sm-9">
            <input type="password" class="form-control" name="admin_pass2" id="admin_pass2">
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
              <button class="btn btn-default" id="edit_selected" data-id="admin" data-item="null" data-api-url='edit/self' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
            </div>
          </div>
        </form>
        <hr>
        <div class="row">
          <div class="col-sm-3 col-xs-5 text-right"><?=$lang['tfa']['tfa'];?>:</div>
          <div class="col-sm-9 col-xs-7">
            <p id="tfa_pretty"><?=$tfa_data['pretty'];?></p>
              <div id="tfa_additional">
                <?php if (!empty($tfa_data['additional'])):
                foreach ($tfa_data['additional'] as $key_info): ?>
                <form style="display:inline;" method="post">
                  <input type="hidden" name="unset_tfa_key" value="<?=$key_info['id'];?>" />
                  <div style="padding:4px;margin:4px" class="label label-<?=($_SESSION['tfa_id'] == $key_info['id']) ? 'success' : 'default'; ?>">
                  <?=$key_info['key_id'];?>
                  <a href="#" style="font-weight:bold;color:white" onClick="$(this).closest('form').submit()">[<?=strtolower($lang['admin']['remove']);?>]</a>
                  </div>
                </form>
                <?php endforeach;
                endif;?>
              </div>
              <br />
          </div>
        </div>
        <div class="row">
          <div class="col-sm-3 col-xs-5 text-right"><?=$lang['tfa']['set_tfa'];?>:</div>
          <div class="col-sm-9 col-xs-7">
            <select data-width="auto" id="selectTFA" class="selectpicker" title="<?=$lang['tfa']['select'];?>">
              <option value="yubi_otp"><?=$lang['tfa']['yubi_otp'];?></option>
              <option value="u2f"><?=$lang['tfa']['u2f'];?></option>
              <option value="totp"><?=$lang['tfa']['totp'];?></option>
              <option value="none"><?=$lang['tfa']['none'];?></option>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="panel panel-default">
    <div class="panel-heading"><?=$lang['admin']['domain_admins'];?></div>
        <div class="panel-body">
          <div class="table-responsive">
            <table class="table table-striped" id="domainadminstable"></table>
          </div>
          <div class="mass-actions-admin">
            <div class="btn-group">
              <a class="btn btn-sm btn-default" id="toggle_multi_select_all" data-id="domain_admins" href="#"><span class="glyphicon glyphicon-check" aria-hidden="true"></span> <?=$lang['mailbox']['toggle_all'];?></a>
              <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
              <ul class="dropdown-menu">
                <li><a id="edit_selected" data-id="domain_admins" data-api-url='edit/domain-admin' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
                <li><a id="edit_selected" data-id="domain_admins" data-api-url='edit/domain-admin' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
                <li role="separator" class="divider"></li>
                <li><a id="edit_selected" data-id="domain_admins" data-api-url='edit/domain-admin' data-api-attr='{"disable_tfa":"1"}' href="#"><?=$lang['tfa']['disable_tfa'];?></a></li>
                <li role="separator" class="divider"></li>
                <li><a id="delete_selected" data-id="domain_admins" data-api-url='delete/domain-admin' href="#"><?=$lang['mailbox']['remove'];?></a></li>
              </ul>
              <a class="btn btn-sm btn-success" data-id="add_domain_admin" data-toggle="modal" data-target="#addDomainAdminModal" href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add_domain_admin'];?></a>
            </div>
          </div>
        </div>
    </div>
  </div>


  <div role="tabpanel" class="tab-pane" id="tab-config">
    <div class="row">
    <div id="sidebar-admin" class="col-sm-2 hidden-xs">
      <div id="scrollbox" class="list-group">
        <a href="#dkim" class="list-group-item"><?=$lang['admin']['dkim_keys'];?></a>
        <a href="#fwdhosts" class="list-group-item"><?=$lang['admin']['forwarding_hosts'];?></a>
        <a href="#f2bparams" class="list-group-item"><?=$lang['admin']['f2b_parameters'];?></a>
        <a href="#relayhosts" class="list-group-item">Relayhosts</a>
        <a href="#customize" class="list-group-item"><?=$lang['admin']['customize'];?></a>
        <a href="#top" class="list-group-item" style="border-top:1px dashed #dadada">↸ <?=$lang['admin']['to_top'];?></a>
      </div>
    </div>
    <div class="col-sm-10">
    <span class="anchor" id="dkim"></span>
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['admin']['dkim_keys'];?></div>
      <div class="panel-body">
        <div class="mass-actions-admin">
          <div class="btn-group btn-group-sm">
            <button type="button" id="toggle_multi_select_all" data-id="dkim" class="btn btn-default"><?=$lang['mailbox']['toggle_all'];?></button>
            <button type="button" id="delete_selected" name="delete_selected" data-id="dkim" data-api-url="delete/dkim" class="btn btn-danger"><?=$lang['admin']['remove'];?></button>
          </div>
        </div>
        <?php
        foreach(mailbox('get', 'domains') as $domain) {
            if (!empty($dkim = dkim('details', $domain))) {
          ?>
            <div class="row">
              <div class="col-xs-1"><input type="checkbox" data-id="dkim" name="multi_select" value="<?=$domain;?>" /></div>
              <div class="col-xs-2">
                <p>Domain: <strong><?=htmlspecialchars($domain);?></strong>
                  <p><span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span></p>
                  <p><span class="label label-primary">Selector '<?=$dkim['dkim_selector'];?>'</span></p>
                  <p><span class="label label-info"><?=$dkim['length'];?> bit</span></p>
                </p>
              </div>
              <div class="col-xs-9">
                  <pre><?=$dkim['dkim_txt'];?></pre>
                  <p data-toggle="modal" data-target="#showDKIMprivKey" id="dkim_priv" style="cursor:pointer;margin-top:-8pt" data-priv-key="<?=$dkim['privkey'];?>"><small>↪ Private key</small></p>
              </div>
            </div>
          <?php
          }
          else {
          ?>
          <div class="row">
              <div class="col-xs-1"><input type="checkbox" data-id="dkim" name="multi_select" value="<?=$domain;?>" disabled /></div>
            <div class="col-xs-2">
              <p>Domain: <strong><?=htmlspecialchars($domain);?></strong><br /><span class="label label-danger"><?=$lang['admin']['dkim_key_missing'];?></span></p>
            </div>
            <div class="col-xs-9"><pre>-</pre></div>
          </div>
          <?php
          }
          foreach(mailbox('get', 'alias_domains', $domain) as $alias_domain) {
            if (!empty($dkim = dkim('details', $alias_domain))) {
            ?>
              <div class="row">
              <div class="col-xs-1"><input type="checkbox" data-id="dkim" name="multi_select" value="<?=$alias_domain;?>" /></div>
                <div class="col-xs-1 col-xs-offset-1">
                  <p><small>↳ Alias-Domain: <strong><?=htmlspecialchars($alias_domain);?></strong></small>
                    <p><span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span></p>
                    <p><span class="label label-primary">Selector '<?=$dkim['dkim_selector'];?>'</span></p>
                    <p><span class="label label-info"><?=$dkim['length'];?> bit</span></p>
                </p>
                </div>
                <div class="col-xs-9">
                  <pre><?=$dkim['dkim_txt'];?></pre>
                  <p data-toggle="modal" data-target="#showDKIMprivKey" id="dkim_priv" style="cursor:pointer;margin-top:-8pt" data-priv-key="<?=$dkim['privkey'];?>"><small>↪ Private key</small></p>
                </div>
              </div>
            <?php
            }
            else {
            ?>
            <div class="row">
              <div class="col-xs-1"><input type="checkbox" data-id="dkim" name="multi_select" value="<?=$domain;?>" disabled /></div>
              <div class="col-xs-1 col-xs-offset-1">
                <p><small>↳ Alias-Domain: <strong><?=htmlspecialchars($alias_domain);?></strong><br /></small><span class="label label-danger"><?=$lang['admin']['dkim_key_missing'];?></span></p>
              </div>
              <div class="col-xs-9"><pre>-</pre></div>
            </div>
            <?php
            }
          }
        }
        foreach(dkim('blind') as $blind) {
          if (!empty($dkim = dkim('details', $blind))) {
          ?>
            <div class="row">
              <div class="col-xs-1"><input type="checkbox" data-id="dkim" name="multi_select" value="<?=$blind;?>" /></div>
              <div class="col-xs-2">
                <p>Domain: <strong><?=htmlspecialchars($blind);?></strong>
                  <p><span class="label label-warning"><?=$lang['admin']['dkim_key_unused'];?></span></p>
                  <p><span class="label label-primary">Selector '<?=$dkim['dkim_selector'];?>'</span></p>
                  <p><span class="label label-info"><?=$dkim['length'];?> bit</span></p>
                </p>
                </div>
                <div class="col-xs-9">
                  <pre><?=$dkim['dkim_txt'];?></pre>
                  <p data-toggle="modal" data-target="#showDKIMprivKey" id="dkim_priv" style="cursor:pointer;margin-top:-8pt" data-priv-key="<?=$dkim['privkey'];?>"><small>↪ Private key</small></p>
                </div>
            </div>
          <?php
          }
        }
        ?>

        <legend style="margin-top:40px"><?=$lang['admin']['dkim_add_key'];?></legend>
        <form class="form-inline" data-id="dkim" role="form" method="post">
          <div class="form-group">
            <label for="domain">Domain</label>
            <input class="form-control" id="domain" name="domain" placeholder="example.org" required>
          </div>
          <div class="form-group">
            <label for="domain">Selector</label>
            <input class="form-control" id="dkim_selector" name="dkim_selector" value="dkim" required>
          </div>
          <div class="form-group">
            <select data-width="200px" class="form-control" id="key_size" name="key_size" title="<?=$lang['admin']['dkim_key_length'];?>" required>
              <option data-subtext="bits">1024</option>
              <option data-subtext="bits">2048</option>
            </select>
          </div>
          <button class="btn btn-default" id="add_item" data-id="dkim" data-api-url='add/dkim' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
        </form>

        <legend data-target="#import_dkim" style="margin-top:40px;cursor:pointer" id="import_dkim_legend" unselectable="on" data-toggle="collapse"><span id="import_dkim_arrow" class="rotate glyphicon glyphicon-menu-down"></span> <?=$lang['admin']['import_private_key'];?></legend>
        <div id="import_dkim" class="collapse">
        <form class="form" data-id="dkim_import" role="form" method="post">
          <div class="form-group">
            <label for="domain">Domain:</label>
            <input class="form-control" id="domain" name="domain" placeholder="example.org" required>
          </div>
          <div class="form-group">
            <label for="domain">Selector:</label>
            <input class="form-control" id="dkim_selector" name="dkim_selector" value="dkim" required>
          </div>
          <div class="form-group">
            <label for="private_key_file"><?=$lang['admin']['private_key'];?>:</label>
            <textarea class="form-control" rows="5" name="private_key_file" id="private_key_file" required placeholder="-----BEGIN RSA KEY-----"></textarea>
          </div>
          <button class="btn btn-default" id="add_item" data-id="dkim_import" data-api-url='add/dkim_import' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['import'];?></button>
        </form>
        </div>
      </div>
    </div>

    <span class="anchor" id="fwdhosts"></span>
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['admin']['forwarding_hosts'];?></div>
      <div class="panel-body">
        <p style="margin-bottom:40px"><?=$lang['admin']['forwarding_hosts_hint'];?></p>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="forwardinghoststable"></table>
        </div>
        <div class="mass-actions-admin">
          <div class="btn-group btn-group-sm">
            <button type="button" id="toggle_multi_select_all" data-id="fwdhosts" class="btn btn-default"><?=$lang['mailbox']['toggle_all'];?></button>
            <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
            <ul class="dropdown-menu">
              <li><a id="edit_selected" data-id="fwdhosts" data-api-url='edit/fwdhost' data-api-attr='{"keep_spam":"0"}' href="#">Enable spam filter</a></li>
              <li><a id="edit_selected" data-id="fwdhosts" data-api-url='edit/fwdhost' data-api-attr='{"keep_spam":"1"}' href="#">Disable spam filter</a></li>
              <li role="separator" class="divider"></li>
              <li><a id="delete_selected" data-id="fwdhosts" data-api-url='delete/fwdhost' href="#"><?=$lang['admin']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <legend><?=$lang['admin']['add_forwarding_host'];?></legend>
        <p class="help-block"><?=$lang['admin']['forwarding_hosts_add_hint'];?></p>
        <form class="form-inline" data-id="fwdhost" role="form" method="post">
          <div class="form-group">
            <label for="hostname"><?=$lang['admin']['host'];?></label>
            <input class="form-control" id="hostname" name="hostname" placeholder="example.org" required>
          </div>
          <div class="form-group">
            <select data-width="200px" class="form-control" id="filter_spam" name="filter_spam" title="<?=$lang['user']['spamfilter'];?>" required>
              <option value="1"><?=$lang['admin']['active'];?></option>
              <option value="0"><?=$lang['admin']['inactive'];?></option>
            </select>
          </div>
          <button class="btn btn-default" id="add_item" data-id="fwdhost" data-api-url='add/fwdhost' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
        </form>
      </div>
    </div>

    <span class="anchor" id="f2bparams"></span>
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['admin']['f2b_parameters'];?></div>
      <div class="panel-body">
      <?php
      $f2b_data = fail2ban('get');
      ?>
        <form class="form" data-id="f2b" role="form" method="post">
          <div class="form-group">
            <label for="ban_time"><?=$lang['admin']['f2b_ban_time'];?>:</label>
            <input type="number" class="form-control" id="ban_time" name="ban_time" value="<?=$f2b_data['ban_time'];?>" required>
          </div>
          <div class="form-group">
            <label for="max_attempts"><?=$lang['admin']['f2b_max_attempts'];?>:</label>
            <input type="number" class="form-control" id="max_attempts" name="max_attempts" value="<?=$f2b_data['max_attempts'];?>" required>
          </div>
          <div class="form-group">
            <label for="retry_window"><?=$lang['admin']['f2b_retry_window'];?>:</label>
            <input type="number" class="form-control" id="retry_window" name="retry_window" value="<?=$f2b_data['retry_window'];?>" required>
          </div>
          <div class="form-group">
            <label for="retry_window"><?=$lang['admin']['f2b_whitelist'];?>:</label>
            <textarea class="form-control" id="whitelist" name="whitelist" rows="5"><?=$f2b_data['whitelist'];?></textarea>
          </div>
          <button class="btn btn-default" id="add_item" data-id="f2b" data-api-url='edit/fail2ban' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-check"></span> <?=$lang['admin']['save'];?></button>
        </form>
      </div>
    </div>

    <span class="anchor" id="relayhosts"></span>
    <div class="panel panel-default">
      <div class="panel-heading">Relayhosts</div>
      <div class="panel-body">
        <p style="margin-bottom:40px"><?=$lang['admin']['relayhosts_hint'];?></p>
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="relayhoststable"></table>
        </div>
        <div class="mass-actions-admin">
          <div class="btn-group btn-group-sm">
            <button type="button" id="toggle_multi_select_all" data-id="rlyhosts" class="btn btn-default"><?=$lang['mailbox']['toggle_all'];?></button>
            <a class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['mailbox']['quick_actions'];?> <span class="caret"></span></a>
            <ul class="dropdown-menu">
              <li><a id="edit_selected" data-id="rlyhosts" data-api-url='edit/relayhost' data-api-attr='{"active":"1"}' href="#"><?=$lang['mailbox']['activate'];?></a></li>
              <li><a id="edit_selected" data-id="rlyhosts" data-api-url='edit/relayhost' data-api-attr='{"active":"0"}' href="#"><?=$lang['mailbox']['deactivate'];?></a></li>
              <li role="separator" class="divider"></li>
              <li><a id="delete_selected" data-id="rlyhosts" data-api-url='delete/relayhost' href="#"><?=$lang['admin']['remove'];?></a></li>
            </ul>
          </div>
        </div>
        <legend><?=$lang['admin']['add_relayhost'];?></legend>
        <p class="help-block"><?=$lang['admin']['add_relayhost_add_hint'];?></p>
        <form class="form-inline" data-id="rlyhost" role="form" method="post">
          <div class="form-group">
            <label for="hostname"><?=$lang['admin']['host'];?></label>
            <input class="form-control" id="hostname" name="hostname" required>
          </div>
          <div class="form-group">
            <label for="hostname"><?=$lang['admin']['username'];?></label>
            <input class="form-control" id="username" name="username">
          </div>
          <div class="form-group">
            <label for="hostname"><?=$lang['admin']['password'];?></label>
            <input class="form-control" id="password" name="password">
          </div>
          <button class="btn btn-default" id="add_item" data-id="rlyhost" data-api-url='add/relayhost' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
        </form>
      </div>
    </div>

    <span class="anchor" id="customize"></span>
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['admin']['customize'];?></div>
      <div class="panel-body">
        <legend><?=$lang['admin']['change_logo'];?></legend>
        <p class="help-block"><?=$lang['admin']['logo_info'];?></p>
        <form class="form-inline" role="form" method="post" enctype="multipart/form-data">
          <p>
            <input type="file" name="main_logo" class="filestyle" data-buttonName="btn-default" data-buttonText="Select" accept="image/gif, image/jpeg, image/pjpeg, image/x-png, image/png, image/svg+xml">
            <button name="submit_main_logo" type="submit" class="btn btn-success"><span class="glyphicon glyphicon-cloud-upload"></span> <?=$lang['admin']['upload'];?></button>
          </p>
        </form>
        <?php
        if ($main_logo = customize('get', 'main_logo')):
          $specs = customize('get', 'main_logo_specs');
        ?>
        <div class="row">
          <div class="col-sm-3">
            <div class="thumbnail">
              <img class="img-thumbnail" src="<?=$main_logo;?>" alt="mailcow logo">
              <div class="caption">
                <span class="label label-info"><?=$specs['geometry']['width'];?>x<?=$specs['geometry']['height'];?> px</span>
                <span class="label label-info"><?=$specs['mimetype'];?></span>
                <span class="label label-info"><?=$specs['fileSize'];?></span>
              </div>
            </div>
            <hr>
            <form class="form-inline" role="form" method="post">
              <p><button name="reset_main_logo" type="submit" class="btn btn-xs btn-default"><?=$lang['admin']['reset_default'];?></button></p>
            </form>
          </div>
        </div>
        <?php
        endif;
        ?>
        <legend><?=$lang['admin']['app_links'];?></legend>
        <p class="help-block"><?=$lang['admin']['merged_vars_hint'];?></p>
        <form class="form-inline" data-id="app_links" role="form" method="post">
          <table class="table table-condensed" style="width:1%;white-space: nowrap;" id="app_link_table">
            <tr>
              <th><?=$lang['admin']['app_name'];?></th>
              <th><?=$lang['admin']['link'];?></th>
              <th>&nbsp;</th>
            </tr>
            <?php
            $app_links = customize('get', 'app_links');
            foreach ($app_links as $row) {
              foreach ($row as $key => $val):
            ?>
            <tr>
              <td><input class="input-sm form-control" data-id="app_links" type="text" name="app" required value="<?=$key;?>"></td>
              <td><input class="input-sm form-control" data-id="app_links" type="text" name="href" required value="<?=$val;?>"></td>
              <td><a href="#" role="button" class="btn btn-xs btn-default" type="button"><?=$lang['admin']['remove_row'];?></a></td>
            </tr>
            <?php 
              endforeach;
            }
            foreach ($MAILCOW_APPS as $app):
            ?>
            <tr>
              <td><input class="input-sm form-control" value="<?=htmlspecialchars($app['name']);?>" disabled></td>
              <td><input class="input-sm form-control" value="<?=htmlspecialchars($app['link']);?>" disabled></td>
              <td>&nbsp;</td>
            </tr>
            <?php
            endforeach;
            ?>
          </table>
          <div class="btn-group">
            <button class="btn btn-success" id="edit_selected" data-item="admin" data-id="app_links" data-reload="no" data-api-url='edit/app_links' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
            <button class="btn btn-default" type="button" id="add_app_link_row"><?=$lang['admin']['add_row'];?></button>
          </div> 
        </form>
      </div>
    </div>
  </div>
  </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-postfix-logs">
    <div class="panel panel-default">
      <div class="panel-heading">Postfix <span class="badge badge-info log-lines"></span>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="postfix_log" data-log-url="postfix" data-nrows="100">+ 100</button>
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="postfix_log" data-log-url="postfix" data-nrows="1000">+ 1000</button>
          <button class="btn btn-xs btn-default" id="refresh_postfix_log"><?=$lang['admin']['refresh'];?></button>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="postfix_log"></table>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-dovecot-logs">
    <div class="panel panel-default">
      <div class="panel-heading">Dovecot <span class="badge badge-info log-lines"></span>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="dovecot_log" data-log-url="dovecot" data-nrows="100">+ 100</button>
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="dovecot_log" data-log-url="dovecot" data-nrows="1000">+ 1000</button>
          <button class="btn btn-xs btn-default" id="refresh_dovecot_log"><?=$lang['admin']['refresh'];?></button>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="dovecot_log"></table>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-sogo-logs">
    <div class="panel panel-default">
      <div class="panel-heading">SOGo <span class="badge badge-info log-lines"></span>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="sogo_log" data-log-url="sogo" data-nrows="100">+ 100</button>
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="sogo_log" data-log-url="sogo" data-nrows="1000">+ 1000</button>
          <button class="btn btn-xs btn-default" id="refresh_sogo_log"><?=$lang['admin']['refresh'];?></button>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="sogo_log"></table>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-fail2ban-logs">
    <div class="panel panel-default">
      <div class="panel-heading">Fail2ban <span class="badge badge-info log-lines"></span>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="fail2ban_log" data-log-url="fail2ban" data-nrows="100">+ 100</button>
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="general_syslog" data-table="fail2ban_log" data-log-url="fail2ban" data-nrows="1000">+ 1000</button>
          <button class="btn btn-xs btn-default" id="refresh_fail2ban_log"><?=$lang['admin']['refresh'];?></button>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="fail2ban_log"></table>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-rspamd-history">
    <div class="panel panel-default">
      <div class="panel-heading">Rspamd history <span class="badge badge-info log-lines"></span>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="rspamd_history" data-table="rspamd_history" data-log-url="rspamd-history" data-nrows="100">+ 100</button>
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="rspamd_history" data-table="rspamd_history" data-log-url="rspamd-history" data-nrows="1000">+ 1000</button>
          <button class="btn btn-xs btn-default" id="refresh_rspamd_history"><?=$lang['admin']['refresh'];?></button>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed log-table" id="rspamd_history"></table>
        </div>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-autodiscover-logs">
    <div class="panel panel-default">
      <div class="panel-heading">Autodiscover <span class="badge badge-info log-lines"></span>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="autodiscover_log" data-table="autodiscover_log" data-log-url="autodiscover" data-nrows="100">+ 100</button>
          <button class="btn btn-xs btn-default add_log_lines" data-post-process="autodiscover_log" data-table="autodiscover_log" data-log-url="autodiscover" data-nrows="1000">+ 1000</button>
          <button class="btn btn-xs btn-default" id="refresh_autodiscover_log"><?=$lang['admin']['refresh'];?></button>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="autodiscover_log"></table>
        </div>
      </div>
    </div>
  </div>

  </div>
</div> <!-- /container -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/admin.php';
?>
<script type='text/javascript'>
<?php
$lang_admin = json_encode($lang['admin']);
echo "var lang = ". $lang_admin . ";\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
echo "var log_pagination_size = '". $LOG_PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/admin.js"></script>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
} else {
	header('Location: /');
	exit();
}
?>
