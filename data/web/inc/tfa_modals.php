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
          <p><?=$lang['tfa']['waiting_usb_register'];?></p>
          <div class="alert alert-danger" style="display:none" id="u2f_return_code"></div>
          <input type="hidden" name="token" id="u2f_register_data"/>
          <input type="hidden" name="tfa_method" value="u2f">
          <input type="hidden" name="set_tfa"/><br/>
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
              <input type="text" name="token" id="token" class="form-control" placeholder="Touch Yubikey" aria-describedby="yubi-addon">
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
          <p><?=$lang['tfa']['waiting_usb_auth'];?></p>
          <div class="alert alert-danger" style="display:none" id="u2f_return_code"></div>
          <input type="hidden" name="token" id="u2f_auth_data"/>
          <input type="hidden" name="tfa_method" value="u2f">
          <input type="hidden" name="verify_tfa_login"/><br/>
        </form>
      <?php
        break;
        case "totp":
      ?>
       <div class="empty"></div>
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
?>