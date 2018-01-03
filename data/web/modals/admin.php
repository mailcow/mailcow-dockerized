<?php
if (!isset($_SESSION['mailcow_cc_role'])) {
	header('Location: /');
	exit();
}
?>
<!-- add domain admin modal -->
<div class="modal fade" id="addDomainAdminModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['admin']['add_domain_admin'];?></h3>
      </div>
      <div class="modal-body">
          <form class="form-horizontal" data-cached-form="true" data-id="domain_admin" role="form" method="post">
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
                <select title="<?=$lang['admin']['search_domain_da'];?>" style="width:100%" name="domains" size="5" multiple>
                <?php
                foreach (mailbox('get', 'domains') as $domain) {
                  echo "<option>".htmlspecialchars($domain)."</option>";
                }
                ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password"><?=$lang['admin']['password'];?>:</label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password" id="password" placeholder="" required>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-2" for="password2"><?=$lang['admin']['password_repeat'];?>:</label>
              <div class="col-sm-10">
              <input type="password" class="form-control" name="password2" id="password2" placeholder="" required>
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
                <button class="btn btn-default" id="add_item" data-id="domain_admin" data-api-url='add/domain-admin' data-api-attr='{}' href="#"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span> <?=$lang['admin']['add'];?></button>
              </div>
            </div>
          </form>
      </div>
    </div>
  </div>
</div><!-- add domain admin modal -->
<!-- test relayhost modal -->
<div class="modal fade" id="testRelayhostModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><span class="glyphicon glyphicon-stats"></span> Relayhost</h3>
      </div>
      <div class="modal-body">
          <form class="form-horizontal" data-cached-form="true" id="test_relayhost_form" role="form" method="post">
            <input type="hidden" class="form-control" name="relayhost_id" id="relayhost_id">
            <div class="form-group">
              <label class="control-label col-sm-2" for="mail_from"><?=$lang['admin']['relay_from'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="mail_from" id="mail_from" placeholder="relay@example.org">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <button class="btn btn-default" id="test_relayhost" href="#"><?=$lang['admin']['relay_run'];?></button>
              </div>
            </div>
          </form>
          <hr>
          <div id="test_relayhost_result" style="font-size:10pt">-</div>
      </div>
    </div>
  </div>
</div><!-- test relayhost modal -->
<!-- priv key modal -->
<div class="modal fade" id="showDKIMprivKey" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><span class="glyphicon glyphicon-lock"></span> Private key</h3>
      </div>
      <div class="modal-body">
      <pre id="priv_key_pre"></pre>
      </div>
    </div>
  </div>
</div><!-- priv key modal -->
