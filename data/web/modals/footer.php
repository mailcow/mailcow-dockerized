<?php
if (isset($_SESSION['mailcow_cc_role']) && ($_SESSION['mailcow_cc_role'] == "admin" || $_SESSION['mailcow_cc_role'] == "domainadmin")):
?>
<div class="modal fade" id="YubiOTPModal" tabindex="-1" role="dialog" aria-labelledby="YubiOTPModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><b><?=$lang['tfa']['yubi_otp'];?></b></div>
      <div class="modal-body">
      <form role="form" method="post">
        <div class="form-group">
          <input type="text" class="form-control" name="key_id" id="key_id" placeholder="<?=$lang['tfa']['key_id'];?>" autocomplete="off" required>
        </div>
        <hr>
        <p class="help-block"><?=$lang['tfa']['api_register'];?></p>
        <div class="form-group">
          <input type="text" class="form-control" name="yubico_id" id="yubico_id" placeholder="Yubico API ID" autocomplete="off" required>
        </div>
        <div class="form-group">
          <input type="text" class="form-control" name="yubico_key" id="yubico_key" placeholder="Yubico API Key" autocomplete="off" required>
        </div>
        <hr>
        <div class="form-group">
          <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="<?=$lang['user']['password_now'];?>" autocomplete="off" required>
        </div>
        <div class="form-group">
          <div class="input-group">
            <span class="input-group-addon" id="yubi-addon"><img alt="Yubicon Icon" src="/img/yubi.ico"></span>
            <input type="text" name="otp_token" class="form-control" placeholder="Touch Yubikey" aria-describedby="yubi-addon">
            <input type="hidden" name="tfa_method" value="yubi_otp">
          </div>
        </div>
        <button class="btn btn-sm btn-default" type="submit" name="set_tfa"><?=$lang['user']['save_changes'];?></button>
      </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="U2FModal" tabindex="-1" role="dialog" aria-labelledby="U2FModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><b><?=$lang['tfa']['u2f'];?></b></div>
      <div class="modal-body">
        <form role="form" method="post" id="u2f_reg_form">
          <div class="form-group">
            <input type="text" class="form-control" name="key_id" id="key_id" placeholder="<?=$lang['tfa']['key_id'];?>" autocomplete="off" required>
          </div>
          <div class="form-group">
            <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="<?=$lang['user']['password_now'];?>" autocomplete="off" required>
          </div>
          <hr>
          <p id="u2f_status_reg"></p>
          <div class="alert alert-danger" style="display:none" id="u2f_return_code"></div>
          <input type="hidden" name="token" id="u2f_register_data"/>
          <input type="hidden" name="tfa_method" value="u2f">
          <input type="hidden" name="set_tfa"/><br/>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="TOTPModal" tabindex="-1" role="dialog" aria-labelledby="TOTPModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><b><?=$lang['tfa']['totp'];?></b></div>
      <div class="modal-body">
        <form role="form" method="post">
          <div class="form-group">
            <input type="text" class="form-control" name="key_id" id="key_id" placeholder="<?=$lang['tfa']['key_id_totp'];?>" autocomplete="off" required>
          </div>
          <div class="form-group">
            <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="<?=$lang['user']['password_now'];?>" autocomplete="off" required>
          </div>
          <hr>
          <?php
          $totp_secret = $tfa->createSecret();
          ?>
          <input type="hidden" value="<?=$totp_secret;?>" name="totp_secret" id="totp_secret"/>
          <input type="hidden" name="tfa_method" value="totp">
          <ol>
            <li>
              <p><?=$lang['tfa']['scan_qr_code'];?></p>
              <img src="<?=$tfa->getQRCodeImageAsDataUri($_SESSION['mailcow_cc_username'], $totp_secret);?>">
              <p class="help-block"><?=$lang['tfa']['enter_qr_code'];?>:<br />
              <code><?=$totp_secret;?></code>
              </p>
            </li>
            <li>
              <p><?=$lang['tfa']['confirm_totp_token'];?>:</p>
              <p><input type="number" style="width:33%" class="form-control" name="totp_confirm_token" id="totp_confirm_token" autocomplete="off" required></p>
              <p><button class="btn btn-default" type="submit" name="set_tfa"><?=$lang['tfa']['confirm'];?></button></p>
            </li>
          </ol>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="DisableTFAModal" tabindex="-1" role="dialog" aria-labelledby="DisableTFAModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><b><?=$lang['tfa']['delete_tfa'];?></b></div>
      <div class="modal-body">
        <form role="form" method="post">
          <div class="input-group">
            <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="<?=$lang['user']['password_now'];?>" autocomplete="off" required>
            <span class="input-group-btn">
              <input type="hidden" name="tfa_method" value="none">
              <button class="btn btn-danger" type="submit" name="set_tfa"><?=$lang['tfa']['delete_tfa'];?></button>
            </span>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
endif;
if (isset($_SESSION['pending_tfa_method'])):
  $tfa_method = $_SESSION['pending_tfa_method'];
?>
<div class="modal fade" id="ConfirmTFAModal" tabindex="-1" role="dialog" aria-labelledby="ConfirmTFAModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><b><?=$lang['tfa'][$tfa_method];?></b></div>
      <div class="modal-body">
      <?php
      switch ($tfa_method) {
        case "yubi_otp":
      ?>
        <form role="form" method="post">
          <div class="form-group">
            <div class="input-group">
              <span class="input-group-addon" id="yubi-addon"><img alt="Yubicon Icon" src="/img/yubi.ico"></span>
              <input type="text" name="token" id="token" class="form-control" autocomplete="off" placeholder="Touch Yubikey" aria-describedby="yubi-addon">
              <input type="hidden" name="tfa_method" value="yubi_otp">
            </div>
          </div>
          <button class="btn btn-sm btn-default" type="submit" name="verify_tfa_login"><?=$lang['login']['login'];?></button>
        </form>
      <?php
        break;
        case "u2f":
      ?>
        <form role="form" method="post" id="u2f_auth_form">
          <p id="u2f_status_auth"></p>
          <div class="alert alert-danger" style="display:none" id="u2f_return_code"></div>
          <input type="hidden" name="token" id="u2f_auth_data"/>
          <input type="hidden" name="tfa_method" value="u2f">
          <input type="hidden" name="verify_tfa_login"/><br/>
        </form>
      <?php
        break;
        case "totp":
      ?>
        <form role="form" method="post">
          <div class="form-group">
            <div class="input-group">
              <span class="input-group-addon" id="tfa-addon"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span></span>
              <input type="number" min="000000" max="999999" name="token" id="token" class="form-control" placeholder="123456" aria-describedby="tfa-addon">
              <input type="hidden" name="tfa_method" value="totp">
            </div>
          </div>
          <button class="btn btn-sm btn-default" type="submit" name="verify_tfa_login"><?=$lang['login']['login'];?></button>
        </form>
        <?php
        break;
        case "hotp":
      ?>
       <div class="empty"></div>
      <?php
        break;
      }
      ?>
      </div>
    </div>
  </div>
</div>
<?php
endif;
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin'):
?>
<div id="RestartContainer" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal">&times;</button>
      <h4 class="modal-title"><?= $lang['footer']['restart_container']; ?> (<code id="containerName"></code>)</h4>
    </div>
    <div class="modal-body">
      <p><?= $lang['footer']['restart_container_info']; ?></p>
      <hr>
      <button class="btn btn-md btn-primary" id="triggerRestartContainer"><?= $lang['footer']['restart_now']; ?></button>
      <br><br>
      <div id="statusTriggerRestartContainer"></div>
      <div id="statusTriggerRestartContainer2"></div>
    </div>
    </div>
  </div>
</div>
<?php
endif;
?>
<div id="ConfirmDeleteModal" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal">&times;</button>
      <h4 class="modal-title"><?= $lang['footer']['confirm_delete']; ?></h4>
    </div>
    <div class="modal-body">
      <p id="DeleteText"><?= $lang['footer']['delete_these_items']; ?></p>
      <ul id="ItemsToDelete"></ul>
      <hr>
      <button class="btn btn-sm btn-danger" id="IsConfirmed"><?= $lang['footer']['delete_now']; ?></button>
      <button class="btn btn-sm btn-default" id="isCanceled"><?= $lang['footer']['cancel']; ?></button>
    </div>
    </div>
  </div>
</div>
