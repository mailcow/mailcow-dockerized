<?php
require_once("inc/prerequisites.inc.php");

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin") {
require_once("inc/header.inc.php");
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$tfa_data = get_tfa();
?>
<div class="container">
  <h4><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?=$lang['admin']['access'];?></h4>

  <div class="panel-group" id="accordion_access">
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
                <?php if($tfa_data['additional']):
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
            <table class="table table-striped sortable-theme-bootstrap" data-sortable id="domainadminstable">
              <thead>
              <tr>
                <th class="sort-table" style="min-width: 100px;"><?=$lang['admin']['username'];?></th>
                <th class="sort-table" style="min-width: 166px;"><?=$lang['admin']['admin_domains'];?></th>
                <th class="sort-table" style="min-width: 76px;"><?=$lang['admin']['active'];?></th>
                <th class="sort-table" style="min-width: 76px;"><?=$lang['tfa']['tfa'];?></th>
                <th style="text-align: right; min-width: 200px;" data-sortable="false"><?=$lang['admin']['action'];?></th>
              </tr>
              </thead>
              <tbody>
                <?php
                foreach (get_domain_admins() as $domain_admin) {
                  $da_data = get_domain_admin_details($domain_admin); 
                  if (!empty($da_data)):
                ?>
                <tr id="data">
                  <td><?=htmlspecialchars(strtolower($domain_admin));?></td>
                  <td>
                  <?php
                  foreach ($da_data['selected_domains'] as $domain) {
                    echo htmlspecialchars($domain).'<br />';
                  }
                  ?>
                  </td>
                  <td><?=$da_data['active'];?></td>
                  <td><?=empty($da_data['tfa_active_int']) ? "✘" : "✔";?></td>
                  <td style="text-align: right;">
                    <div class="btn-group">
                      <a href="edit.php?domainadmin=<?=$domain_admin;?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> <?=$lang['admin']['edit'];?></a>
                      <a href="delete.php?domainadmin=<?=$domain_admin;?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> <?=$lang['admin']['remove'];?></a>
                    </div>
                  </td>
                  </td>
                </tr>

                <?php
                else:
                ?>
                  <tr id="no-data"><td colspan="4" style="text-align: center; font-style: italic;"><?=$lang['admin']['no_record'];?></td></tr>
                <?php
                endif;
                }
                ?>
              </tbody>
            </table>
            </div>
          </form>
          <small>
          <legend><?=$lang['admin']['add_domain_admin'];?></legend>
          <form class="form-horizontal" role="form" method="post">
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
                <label><input type="checkbox" name="active" checked> <?=$lang['admin']['active'];?></label>
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

  <h4><span class="glyphicon glyphicon-wrench" aria-hidden="true"></span> <?=$lang['admin']['configuration'];?></h4>
  <div class="panel panel-default">
  <div class="panel-heading"><?=$lang['admin']['dkim_keys'];?></div>
  <div id="collapseDKIM" class="panel-collapse">
  <div class="panel-body">
    <p style="margin-bottom:40px"><?=$lang['admin']['dkim_key_hint'];?></p>
    <?php
    foreach(mailbox_get_domains() as $domain) {
        if (!empty($dkim = dkim_get_key_details($domain))) {
      ?>
        <div class="row">
          <div class="col-xs-3">
            <p>Domain: <strong><?=htmlspecialchars($domain);?></strong><br />
              <span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span>
              <span class="label label-info"><?=$dkim['length'];?> bit</span>
            </p>
          </div>
          <div class="col-xs-8">
              <pre><?=$dkim['dkim_txt'];?></pre>
          </div>
          <div class="col-xs-1">
            <form class="form-inline" method="post">
              <input type="hidden" name="domain" value="<?=$domain;?>">
              <input type="hidden" name="dkim_delete_key" value="1">
                <a href="#" onclick="$(this).closest('form').submit()" data-toggle="tooltip" data-placement="top" title="<?=$lang['user']['delete_now'];?>"><span class="glyphicon glyphicon-remove"></span></a>
            </form>
          </div>
        </div>
      <?php
      }
      else {
      ?>
      <div class="row">
        <div class="col-xs-3">
          <p>Domain: <strong><?=htmlspecialchars($domain);?></strong><br /><span class="label label-danger"><?=$lang['admin']['dkim_key_missing'];?></span></p>
        </div>
        <div class="col-xs-8"><pre>-</pre></div>
        <div class="col-xs-1">&nbsp;</div>
      </div>
      <?php
      }
      foreach(mailbox_get_alias_domains($domain) as $alias_domain) {
        if (!empty($dkim = dkim_get_key_details($alias_domain))) {
        ?>
          <div class="row">
            <div class="col-xs-offset-1 col-xs-2">
              <p><small>↳ Alias-Domain: <strong><?=htmlspecialchars($alias_domain);?></strong><br /></small>
                <span class="label label-success"><?=$lang['admin']['dkim_key_valid'];?></span>
                <span class="label label-info"><?=$dkim['length'];?> bit</span>
            </p>
            </div>
            <div class="col-xs-8">
              <pre><?=$dkim['dkim_txt'];?></pre>
            </div>
            <div class="col-xs-1">
              <form class="form-inline" method="post">
                <input type="hidden" name="domain" value="<?=$alias_domain;?>">
                <input type="hidden" name="dkim_delete_key" value="1">
                <a href="#" onclick="$(this).closest('form').submit()" data-toggle="tooltip" data-placement="top" title="<?=$lang['user']['delete_now'];?>"><span class="glyphicon glyphicon-remove"></span></a>
              </form>
            </div>
          </div>
        <?php
        }
        else {
        ?>
        <div class="row">
          <div class="col-xs-2 col-xs-offset-1">
            <p><small>↳ Alias-Domain: <strong><?=htmlspecialchars($alias_domain);?></strong><br /></small><span class="label label-danger"><?=$lang['admin']['dkim_key_missing'];?></span></p>
          </div>
        <div class="col-xs-8"><pre>-</pre></div>
        <div class="col-xs-1">&nbsp;</div>
        </div>
        <?php
        }
      }
    }
    foreach(dkim_get_blind_keys() as $blind) {
      if (!empty($dkim = dkim_get_key_details($blind))) {
      ?>
        <div class="row">
          <div class="col-xs-3">
            <p>Domain: <strong><?=htmlspecialchars($blind);?></strong><br /><span class="label label-warning"><?=$lang['admin']['dkim_key_unused'];?></span></p>
          </div>
            <div class="col-xs-8">
              <pre><?=$dkim['dkim_txt'];?></pre>
            </div>
            <div class="col-xs-1">
              <form class="form-inline" method="post">
                <input type="hidden" name="domain" value="<?=$blind;?>">
                <input type="hidden" name="dkim_delete_key" value="1">
                <a href="#" onclick="$(this).closest('form').submit()" data-toggle="tooltip" data-placement="top" title="<?=$lang['user']['delete_now'];?>"><span class="glyphicon glyphicon-remove"></span></a>
              </form>
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
        <select data-width="200px" class="form-control" id="key_size" name="key_size" title="<?=$lang['admin']['dkim_key_length'];?>" required>
          <option data-subtext="bits">1024</option>
          <option data-subtext="bits">2048</option>
        </select>
      </div>
      <button type="submit" name="dkim_add_key" class="btn btn-default"><span class="glyphicon glyphicon-plus"></span> <?=$lang['admin']['add'];?></button>
    </form>
  </div>
  </div>
  </div>
</div> <!-- /container -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js" integrity="sha384-YWP9O4NjmcGo4oEJFXvvYSEzuHIvey+LbXkBNJ1Kd0yfugEZN9NCQNpRYBVC1RvA" crossorigin="anonymous"></script>
<script src="js/sorttable.js"></script>
<script src="js/admin.js"></script>
<?php
require_once("inc/footer.inc.php");
} else {
	header('Location: /');
	exit();
}
?>
