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
				<form class="form-horizontal" role="form" data-id="add_syncjob">
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
