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
              <button class="btn btn-default" id="edit_selected" data-id="admin" data-item="null" data-api-url='edit/admin' data-api-attr='{}' href="#"><?=$lang['admin']['save'];?></button>
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
                <p>Domain: <strong><?=htmlspecialchars($domain);?></strong><br />
                  <span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span>
                  <span class="label label-primary">Selector '<?=$dkim['dkim_selector'];?>'</span>
                  <span class="label label-info"><?=$dkim['length'];?> bit</span>
                </p>
              </div>
              <div class="col-xs-9">
                  <pre><?=$dkim['dkim_txt'];?></pre>
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
                  <p><small>↳ Alias-Domain: <strong><?=htmlspecialchars($alias_domain);?></strong><br /></small>
                    <span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span>
                    <span class="label label-primary">Selector '<?=$dkim['dkim_selector'];?>'</span>
                    <span class="label label-info"><?=$dkim['length'];?> bit</span>
                </p>
                </div>
                <div class="col-xs-9">
                  <pre><?=$dkim['dkim_txt'];?></pre>
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
                <p>Domain: <strong><?=htmlspecialchars($blind);?></strong><br />
                  <span class="label label-warning"><?=$lang['admin']['dkim_key_unused'];?></span>
                  <span class="label label-primary">Selector '<?=$dkim['dkim_selector'];?>'</span>
                  <span class="label label-info"><?=$dkim['length'];?> bit</span>
                </p>
                </div>
                <div class="col-xs-9">
                  <pre><?=$dkim['dkim_txt'];?></pre>
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
            <textarea class="form-control" rows="5" name="private_key_file" id="private_key_file" required placeholder="-----BEGIN RSA PRIVATE KEY-----
XYZ
-----END RSA PRIVATE KEY-----"></textarea>
          </div>
          <button class="btn btn-default" id="add_item" data-id="dkim_import" data-api-url='add/dkim_import' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['import'];?></button>
        </form>
        </div>
      </div>
    </div>
    
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
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-postfix-logs">
    <div class="panel panel-default">
      <div class="panel-heading">Postfix
        <div class="btn-group pull-right">
          <a class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['admin']['action'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="#" id="refresh_postfix_log"><?=$lang['admin']['refresh'];?></a></li>
          </ul>
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
      <div class="panel-heading">Dovecot
        <div class="btn-group pull-right">
          <a class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['admin']['action'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="#" id="refresh_dovecot_log"><?=$lang['admin']['refresh'];?></a></li>
          </ul>
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
      <div class="panel-heading">SOGo
        <div class="btn-group pull-right">
          <a class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['admin']['action'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="#" id="refresh_sogo_log"><?=$lang['admin']['refresh'];?></a></li>
          </ul>
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
      <div class="panel-heading">Fail2ban
        <div class="btn-group pull-right">
          <a class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['admin']['action'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="#" id="refresh_fail2ban_log"><?=$lang['admin']['refresh'];?></a></li>
          </ul>
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
      <div class="panel-heading">Rspamd history
        <div class="btn-group pull-right">
          <a class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" href="#"><?=$lang['admin']['action'];?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="#" id="refresh_rspamd_history"><?=$lang['admin']['refresh'];?></a></li>
          </ul>
        </div>
      </div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped table-condensed" id="rspamd_history"></table>
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
