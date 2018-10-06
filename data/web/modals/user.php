<?php
if (!isset($_SESSION['mailcow_cc_role'])) {
	header('Location: /');
	exit();
}
?>
<!-- add sync job modal -->
<div class="modal fade" id="addSyncJobModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['add']['syncjob'];?></h3>
      </div>
      <div class="modal-body">
        <p><?=$lang['add']['syncjob_hint'];?></p>
				<form class="form-horizontal" data-cached-form="true" role="form" data-id="add_syncjob">
					<div class="form-group">
						<label class="control-label col-sm-2" for="host1"><?=$lang['add']['hostname'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="host1" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="port1"><?=$lang['add']['port'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="port1" min="1" max="65535" value="143" required>
            <small class="help-block">1-65535</small>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="user1"><?=$lang['add']['username'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="user1" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="password1"><?=$lang['add']['password'];?></label>
						<div class="col-sm-10">
						<input type="password" class="form-control" name="password1" data-hibp="true" required>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="enc1"><?=$lang['add']['enc_method'];?></label>
						<div class="col-sm-10">
							<select name="enc1" title="<?=$lang['add']['select'];?>" required>
                <option selected>TLS</option>
                <option>SSL</option>
                <option>PLAIN</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="mins_interval"><?=$lang['add']['mins_interval'];?></label>
						<div class="col-sm-10">
              <input type="number" class="form-control" name="mins_interval" min="1" max="3600" value="20" required>
              <small class="help-block">10-3600</small>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="subfolder2"><?=$lang['edit']['subfolder2'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="subfolder2" value="External">
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxage"><?=$lang['edit']['maxage'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxage" min="0" max="32000" value="0">
            <small class="help-block">0-32000</small>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="maxbytespersecond"><?=$lang['edit']['maxbytespersecond'];?></label>
						<div class="col-sm-10">
						<input type="number" class="form-control" name="maxbytespersecond" min="0" max="125000000" value="0">
            <small class="help-block">0-125000000</small>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-2" for="exclude"><?=$lang['add']['exclude'];?></label>
						<div class="col-sm-10">
						<input type="text" class="form-control" name="exclude" value="(?i)spam|(?i)junk">
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
							<label><input type="checkbox" value="1" name="automap"> <?=$lang['add']['automap'];?></label>
							</div>
						</div>
					</div>
          <div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
							<label><input type="checkbox" value="1" name="skipcrossduplicates"> <?=$lang['add']['skipcrossduplicates'];?></label>
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
              <button class="btn btn-default" data-action="add_item" data-id="add_syncjob" data-api-url='add/syncjob' data-api-attr='{}' href="#"><?=$lang['admin']['add'];?></button>
						</div>
					</div>
				</form>
      </div>
    </div>
  </div>
</div><!-- add sync job modal -->
<!-- log modal -->
<div class="modal fade" id="syncjobLogModal" tabindex="-1" role="dialog" aria-labelledby="syncjobLogModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h4 class="modal-title">Log</h4></div>
      <div class="modal-body">
        <textarea class="form-control" rows="20" id="logText" spellcheck="false"></textarea>
      </div>
    </div>
  </div>
</div><!-- log modal -->
<!-- pw change modal -->
<div class="modal fade" id="pwChangeModal" tabindex="-1" role="dialog" aria-labelledby="pwChangeModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <form class="form-horizontal" data-cached-form="true" data-id="pwchange" role="form" method="post" autocomplete="off">
          <div class="form-group">
            <label class="control-label col-sm-3" for="user_new_pass"><?=$lang['user']['new_password'];?></label>
            <div class="col-sm-5">
            <input type="password" data-hibp="true" class="form-control" name="user_new_pass" autocomplete="off" required>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-sm-3" for="user_new_pass2"><?=$lang['user']['new_password_repeat'];?></label>
            <div class="col-sm-5">
            <input type="password" class="form-control" name="user_new_pass2" autocomplete="off" required>
            <p class="help-block"><?=$lang['user']['new_password_description'];?></p>
            </div>
          </div>
          <hr>
          <div class="form-group">
            <label class="control-label col-sm-3" for="user_old_pass"><?=$lang['user']['password_now'];?></label>
            <div class="col-sm-5">
            <input type="password" class="form-control" name="user_old_pass" autocomplete="off" required>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
              <button class="btn btn-default" data-action="edit_selected" data-id="pwchange" data-item="null" data-api-url='edit/self' data-api-attr='{}' href="#"><?=$lang['user']['change_password'];?></button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div><!-- pw change modal -->
<!-- sieve filter modal -->
<div class="modal fade" id="userFilterModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
        <h3 class="modal-title"><?=$lang['user']['active_sieve'];?></h3>
      </div>
      <div class="modal-body">
      <pre id="user_sieve_filter"></pre>
      </div>
    </div>
  </div>
</div><!-- sieve filter modal -->
