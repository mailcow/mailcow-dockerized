<?php
if (!isset($_SESSION['mailcow_cc_role'])) {
	header('Location: /');
	exit();
}
?>
<!-- add mailbox modal -->
<div class="modal fade" id="addMailboxModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['mailbox']['add_mailbox'];?></h3>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" data-id="add_mailbox" role="form">
          <div class="form-group">
            <label class="control-label col-sm-2" for="local_part"><?=$lang['add']['mailbox_username'];?></label>
            <div class="col-sm-10">
              <input type="text" pattern="[A-Za-z0-9\.!#$%&'*+/=?^_`{|}~-]+" autocorrect="off" autocapitalize="none" class="form-control" name="local_part" id="local_part" required>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="domain"><?=$lang['add']['domain'];?>:</label>
            <div class="col-sm-10">
              <select id="addSelectDomain" name="domain" id="domain" required>
              <?php
              foreach (mailbox('get', 'domains') as $domain) {
                echo "<option>".htmlspecialchars($domain)."</option>";
              }
              ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="name"><?=$lang['add']['full_name'];?></label>
            <div class="col-sm-10">
            <input type="text" class="form-control" name="name" id="name">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="addInputQuota"><?=$lang['add']['quota_mb'];?>
              <br /><span id="quotaBadge" class="badge">max. - MiB</span>
            </label>
            <div class="col-sm-10">
            <input type="text" class="form-control" name="quota" min="1" max="" id="addInputQuota" disabled value="<?=$lang['add']['select_domain'];?>" required>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="password"><?=$lang['add']['password'];?></label>
            <div class="col-sm-10">
            <input type="password" class="form-control" name="password" id="password" placeholder="" required>
            (<a href="#" class="generate_password">Generate</a>)
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-2" for="password2"><?=$lang['add']['password_repeat'];?></label>
            <div class="col-sm-10">
            <input type="password" class="form-control" name="password2" id="password2" placeholder="" required>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
              <div class="checkbox">
              <label><input type="checkbox" value="1" name="active" checked> <?=$lang['add']['active'];?></label>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-default" id="add_item" data-id="add_mailbox" data-api-url='add/mailbox' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div><!-- add mailbox modal -->
<!-- add domain modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['mailbox']['add_domain'];?></h3>
      </div>
      <div class="modal-body">
				<form class="form-horizontal" data-id="add_domain" role="form">
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['add']['domain'];?>:</label>
						<div class="col-sm-10">
						<input type="text" autocorrect="off" autocapitalize="none" class="form-control" name="domain" id="domain" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="description"><?=$lang['add']['description'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="description" id="description">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="aliases"><?=$lang['add']['max_aliases'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="aliases" id="aliases" value="400" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mailboxes"><?=$lang['add']['max_mailboxes'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="mailboxes" id="mailboxes" value="10" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxquota"><?=$lang['add']['mailbox_quota_m'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxquota" id="maxquota" value="3072" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="quota"><?=$lang['add']['domain_quota_m'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="quota" id="quota" value="10240" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2"><?=$lang['add']['backup_mx_options'];?></label>
						<div class="col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="backupmx"> <?=$lang['add']['relay_domain'];?></label>
							<br />
							<label><input type="checkbox" value="1" name="relay_all_recipients"> <?=$lang['add']['relay_all'];?></label>
							<p><?=$lang['add']['relay_all_info'];?></p>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-default" id="add_item" data-id="add_domain" data-api-url='add/domain' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
					<p><span class="glyphicon glyphicon-exclamation-sign text-danger"></span> <?=$lang['add']['restart_sogo_hint'];?></p>
				</form>
      </div>
    </div>
  </div>
</div><!-- add domain modal -->
<!-- add resource modal -->
<div class="modal fade" id="addResourceModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['mailbox']['add_resource'];?></h3>
      </div>
      <div class="modal-body">
				<form class="form-horizontal" role="form" data-id="add_resource">
					<div class="form-group">
						<label class="control-label col-sm-2" for="description"><?=$lang['add']['description'];?></label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="description" id="description" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['add']['domain'];?>:</label>
						<div class="col-sm-10">
							<select name="domain" id="domain" title="<?=$lang['add']['select'];?>" required>
							<?php
              foreach (mailbox('get', 'domains') as $domain) {
								echo "<option>".htmlspecialchars($domain)."</option>";
							}
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="domain"><?=$lang['add']['kind'];?>:</label>
						<div class="col-sm-10">
							<select name="kind" id="kind" title="<?=$lang['add']['select'];?>" required>
								<option value="location">Location</option>
								<option value="group">Group</option>
								<option value="thing">Thing</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="multiple_bookings" checked> <?=$lang['add']['multiple_bookings'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-default" id="add_item" data-id="add_resource" data-api-url='add/resource' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
      </div>
    </div>
  </div>
</div><!-- add resource modal -->
<!-- add alias modal -->
<div class="modal fade" id="addAliasModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['mailbox']['add_alias'];?></h3>
      </div>
      <div class="modal-body">
				<form class="form-horizontal" role="form" data-id="add_alias">
					<input type="hidden" value="0" name="active">
					<div class="form-group">
						<label class="control-label col-sm-2" for="address"><?=$lang['add']['alias_address'];?></label>
						<div class="col-sm-10">
							<textarea autocorrect="off" autocapitalize="none" class="form-control" rows="5" name="address" id="address" required></textarea>
							<p><?=$lang['add']['alias_address_info'];?></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="goto"><?=$lang['add']['target_address'];?></label>
						<div class="col-sm-10">
							<textarea id="textarea_alias_goto" autocorrect="off" autocapitalize="none" class="form-control" rows="5" id="goto" name="goto" required></textarea>
							<div class="checkbox">
                <label><input id="goto_null" type="checkbox" value="1" name="goto_null"> <?=$lang['add']['goto_null'];?></label>
							</div>
							<p><?=$lang['add']['target_address_info'];?></p>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-default" id="add_item" data-id="add_alias" data-api-url='add/alias' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
      </div>
    </div>
  </div>
</div><!-- add alias modal -->
<!-- add domain alias modal -->
<div class="modal fade" id="addAliasDomainModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['mailbox']['add_domain_alias'];?></h3>
      </div>
      <div class="modal-body">
				<form class="form-horizontal" role="form" data-id="add_alias_domain">
					<input type="hidden" value="0" name="active">
					<div class="form-group">
						<label class="control-label col-sm-2" for="alias_domain"><?=$lang['add']['alias_domain'];?></label>
						<div class="col-sm-10">
							<textarea autocorrect="off" autocapitalize="none" class="form-control" rows="5" name="alias_domain" id="alias_domain" required></textarea>
							<p><?=$lang['add']['alias_domain_info'];?></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="target_domain"><?=$lang['add']['target_domain'];?></label>
						<div class="col-sm-10">
							<select name="target_domain" id="target_domain" title="<?=$lang['add']['select'];?>" required>
							<?php
              foreach (mailbox('get', 'domains') as $domain) {
								echo "<option>".htmlspecialchars($domain)."</option>";
							}
							?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-default" id="add_item" data-id="add_alias_domain" data-api-url='add/alias-domain' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
      </div>
    </div>
  </div>
</div><!-- add domain alias modal -->
<!-- add sync job modal -->
<div class="modal fade" id="addSyncJobModalAdmin" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['add']['syncjob'];?></h3>
      </div>
      <div class="modal-body">
        <p><?=$lang['add']['syncjob_hint'];?></p>
				<form class="form-horizontal" role="form" data-id="add_syncjob">
          <div class="form-group">
            <label class="control-label col-sm-2" for="username"><?=$lang['add']['username'];?>:</label>
            <div class="col-sm-10">
              <select id="addSelectUsername" name="username" id="username" required>
              <?php
              $domains = mailbox('get', 'domains');
              if (!empty($domains)) {
                foreach ($domains as $domain) {
                  $mailboxes = mailbox('get', 'mailboxes', $domain);
                  foreach ($mailboxes as $mailbox) {
                    echo "<option>".htmlspecialchars($mailbox)."</option>";
                  }
                }
              }
              ?>
              </select>
            </div>
          </div> 
					<div class="form-group">
						<label class="control-label col-sm-2" for="host1"><?=$lang['add']['hostname'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="host1" id="host1" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="port1"><?=$lang['add']['port'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="port1" id="port1" min="1" max="65535" value="143" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="user1"><?=$lang['add']['username'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="user1" id="user1" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password1"><?=$lang['add']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password1" id="password1" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="enc1"><?=$lang['add']['enc_method'];?></label>
						<div class="col-sm-10">
							<select name="enc1" id="enc1" title="<?=$lang['add']['select'];?>" required>
                <option selected>TLS</option>
                <option>SSL</option>
                <option>PLAIN</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mins_interval"><?=$lang['add']['mins_interval'];?></label>
						<div class="col-sm-10">
              <input type="number" class="form-control" name="mins_interval" min="10" max="3600" value="20" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="subfolder2"><?=$lang['edit']['subfolder2'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="subfolder2" id="subfolder2" value="External">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxage"><?=$lang['edit']['maxage'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxage" id="maxage" min="0" max="32000" value="0">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="exclude"><?=$lang['add']['exclude'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="exclude" id="exclude" value="(?i)spam|(?i)junk">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="delete2duplicates" checked> <?=$lang['add']['delete2duplicates'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="delete1"> <?=$lang['add']['delete1'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="delete2"> <?=$lang['add']['delete2'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="active" checked> <?=$lang['add']['active'];?></label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
              <button class="btn btn-default" id="add_item" data-id="add_syncjob" data-api-url='add/syncjob' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
      </div>
    </div>
  </div>
</div><!-- add sync job modal -->
<!-- log modal -->
<div class="modal fade" id="logModal" tabindex="-1" role="dialog" aria-labelledby="logTextLabel">
  <div class="modal-dialog" style="width:90%" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <span id="logText"></span>
      </div>
    </div>
  </div>
</div><!-- log modal -->
