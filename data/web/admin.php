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
    <li role="presentation">
      <a href="#tab-logs" aria-controls="tab-logs" role="tab" data-toggle="tab"><?=$lang['admin']['logs'];?></a>
    </li>
  </ul>

  <div class="tab-content" style="padding-top:20px">
  <div role="tabpanel" class="tab-pane active" id="tab-access">
    <div class="panel panel-danger">
      <div class="panel-heading"><?=$lang['admin']['admin_details'];?></div>
      <div class="panel-body">
        <form class="form-horizontal" autocapitalize="none" autocorrect="off" role="form" method="post">
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
              <button type="submit" name="edit_admin_account" class="btn btn-default"><?=$lang['admin']['save'];?></button>
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
    <div style="cursor:pointer;" class="panel-heading" data-toggle="collapse" data-parent="#accordion_access" data-target="#collapseDomAdmins">
      <span class="accordion-toggle"><?=$lang['admin']['domain_admins'];?></span>
    </div>
      <div id="collapseDomAdmins" class="panel-collapse collapse">
        <div class="panel-body">
          <form method="post">
            <div class="table-responsive">
            <table class="table table-striped" id="domainadminstable"></table>
            </div>
          </form>
          <small>
          <legend><?=$lang['admin']['add_domain_admin'];?></legend>
          <form class="form-horizontal" role="form" method="post">
            <input type="hidden" value="0" name="active">
            <div class="form-group">
              <label class="control-label col-sm-2" for="username"><?=$lang['admin']['username'];?>:</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="username" id="username" required>
                &rdsh; <kbd>a-z A-Z - _ .</kbd>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="name"><?=$lang['admin']['admin_domains'];?>:</label>
              <div class="col-sm-10">
                <select title="<?=$lang['admin']['search_domain_da'];?>" style="width:100%" name="domain[]" size="5" multiple>
                <?php
                foreach (mailbox_get_domains() as $domain) {
                  echo "<option>".htmlspecialchars($domain)."</option>";
                }
                ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password"><?=$lang['admin']['password'];?>:</label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password" id="password" placeholder="">
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password2"><?=$lang['admin']['password_repeat'];?>:</label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password2" id="password2" placeholder="">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                <label><input type="checkbox" value="1" name="active" checked> <?=$lang['admin']['active'];?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" name="add_domain_admin" class="btn btn-default"><?=$lang['admin']['add'];?></button>
              </div>
            </div>
          </form>
          </small>
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
        foreach(mailbox_get_domains() as $domain) {
            if (!empty($dkim = dkim_get_key_details($domain))) {
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
          foreach(mailbox_get_alias_domains($domain) as $alias_domain) {
            if (!empty($dkim = dkim_get_key_details($alias_domain))) {
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
        foreach(dkim_get_blind_keys() as $blind) {
          if (!empty($dkim = dkim_get_key_details($blind))) {
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
        <form class="form-inline" role="form" method="post">
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
          <button type="submit" name="dkim_add_key" class="btn btn-default"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
        </form>
      </div>
    </div>
    
    <div class="panel panel-default">
      <div class="panel-heading"><?=$lang['admin']['forwarding_hosts'];?></div>
      <div class="panel-body">
        <p style="margin-bottom:40px"><?=$lang['admin']['forwarding_hosts_hint'];?></p>
        <div class="mass-actions-admin">
          <div class="btn-group btn-group-sm">
            <button type="button" id="toggle_multi_select_all" data-id="fwdhosts" class="btn btn-default"><?=$lang['mailbox']['toggle_all'];?></button>
            <button type="button" id="delete_selected" name="delete_selected" data-id="fwdhosts" data-api-url="delete/fwdhost" class="btn btn-danger"><?=$lang['admin']['remove'];?></button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-striped" id="forwardinghoststable"></table>
        </div>
        <legend><?=$lang['admin']['add_forwarding_host'];?></legend>
        <p class="help-block"><?=$lang['admin']['forwarding_hosts_add_hint'];?></p>
        <form class="form-inline" role="form" method="post">
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
          <button type="submit" name="add_forwarding_host" class="btn btn-default"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
        </form>
      </div>
    </div>
  </div>

  <div role="tabpanel" class="tab-pane" id="tab-logs">
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
          <table class="table table-striped" id="dovecot_log"></table>
        </div>
      </div>
    </div>
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
          <table class="table table-striped" id="postfix_log"></table>
        </div>
      </div>
    </div>
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
          <table class="table table-striped" id="sogo_log"></table>
        </div>
      </div>
    </div>
  </div>

  </div>
</div> <!-- /container -->
<script type='text/javascript'>
<?php
$lang_admin = json_encode($lang['admin']);
echo "var lang = ". $lang_admin . ";\n";
echo "var csrf_token = '". $_SESSION['CSRF']['TOKEN'] . "';\n";
echo "var pagination_size = '". $PAGINATION_SIZE . "';\n";
?>
</script>
<script src="js/footable.min.js"></script>
<script src="js/admin.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
