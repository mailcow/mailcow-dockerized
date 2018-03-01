<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modals/footer.php';
?>
<div style="margin-bottom: 100px;"></div>
<script src="/js/bootstrap.min.js"></script>
<script src="/js/bootstrap-switch.min.js"></script>
<script src="/js/bootstrap-slider.min.js"></script>
<script src="/js/bootstrap-select.min.js"></script>
<script src="/js/bootstrap-filestyle.min.js"></script>
<script src="/js/notifications.min.js"></script>
<script src="/js/formcache.min.js"></script>
<script src="/js/numberedtextarea.min.js"></script>
<script src="/js/u2f-api.js"></script>
<script src="/js/api.js"></script>
<script>
var loading_text = '<?= $lang['footer']['loading']; ?>'
$(window).scroll(function() {
  sessionStorage.scrollTop = $(this).scrollTop();
});
// Select language and reopen active URL without POST
function setLang(sel) {
  $.post( "<?= $_SERVER['REQUEST_URI']; ?>", {lang: sel} );
  window.location.href = window.location.pathname + window.location.search;
}
$(window).load(function() {
  $(".overlay").hide();
});
$(document).ready(function() {
  window.mailcow_alert_box = function(message, type) {
    msg = $('<span/>').html(message).text();
    if (type == 'danger') {
      auto_hide = 0;
      $('#' + localStorage.getItem("add_modal")).modal('show');
      localStorage.removeItem("add_modal");
    } else {
      auto_hide = 5000;
    }
    $.ajax({
      url: '/inc/ajax/log_driver.php',
      data: {"type": type,"msg": msg},
      type: "GET"
    });
    $.notify({message: msg},{z_index: 20000, delay: auto_hide, type: type,placement: {from: "bottom",align: "right"},animate: {enter: 'animated fadeInUp',exit: 'animated fadeOutDown'}});
  }
  $('[data-cached-form="true"]').formcache({key: $(this).data('id')});
  <?php if (isset($_SESSION['return'])): ?>
  mailcow_alert_box(<?=json_encode($_SESSION['return']['msg']); ?>,  "<?= $_SESSION['return']['type']; ?>");
  <?php endif; unset($_SESSION['return']); ?>
  // Confirm TFA modal
  <?php if (isset($_SESSION['pending_tfa_method'])):?>
  $('#ConfirmTFAModal').modal({
    backdrop: 'static',
    keyboard: false
  });
  $('#u2f_status_auth').html('<p><span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Initializing, please wait...</p>');
  $('#ConfirmTFAModal').on('shown.bs.modal', function(){
      $(this).find('#token').focus();
      // If U2F
      if(document.getElementById("u2f_auth_data") !== null) {
        $.ajax({
          type: "GET",
          cache: false,
          dataType: 'script',
          url: "/api/v1/get/u2f-authentication/<?= (isset($_SESSION['pending_mailcow_cc_username'])) ? rawurlencode($_SESSION['pending_mailcow_cc_username']) : null; ?>",
          complete: function(data){
            $('#u2f_status_auth').html('<?=$lang['tfa']['waiting_usb_auth'];?>');
            data;
            setTimeout(function() {
              console.log("Ready to authenticate");
              u2f.sign(appId, challenge, registeredKeys, function(data) {
                var form = document.getElementById('u2f_auth_form');
                var auth = document.getElementById('u2f_auth_data');
                console.log("Authenticate callback", data);
                auth.value = JSON.stringify(data);
                form.submit();
              });
            }, 1000);
          }
        });
      }
  });
  $('#ConfirmTFAModal').on('hidden.bs.modal', function(){
      $.ajax({
        type: "GET",
        cache: false,
        dataType: 'script',
        url: '/inc/ajax/destroy_tfa_auth.php',
        complete: function(data){
          window.location = window.location.href.split("#")[0];
        }
      });
  });
  <?php endif; ?>

  // Set TFA modals

  $('#selectTFA').change(function () {
    if ($(this).val() == "yubi_otp") {
      $('#YubiOTPModal').modal('show');
      $("option:selected").prop("selected", false);
    }
    if ($(this).val() == "totp") {
      $('#TOTPModal').modal('show');
      $("option:selected").prop("selected", false);
    }
    if ($(this).val() == "u2f") {
      $('#U2FModal').modal('show');
      $("option:selected").prop("selected", false);
      $('#u2f_status_reg').html('<p><span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Initializing, please wait...</p>');
      $.ajax({
        type: "GET",
        cache: false,
        dataType: 'script',
        url: "/api/v1/get/u2f-registration/<?= (isset($_SESSION['mailcow_cc_username'])) ? rawurlencode($_SESSION['mailcow_cc_username']) : null; ?>",
        complete: function(data){
          data;
          setTimeout(function() {
            console.log("Ready to register");
            $('#u2f_status_reg').html('<?=$lang['tfa']['waiting_usb_register'];?>');
            u2f.register(appId, registerRequests, registeredKeys, function(deviceResponse) {
              var form  = document.getElementById('u2f_reg_form');
              var reg   = document.getElementById('u2f_register_data');
              console.log("Register callback: ", data);
              if (deviceResponse.errorCode && deviceResponse.errorCode != 0) {
                var u2f_return_code = document.getElementById('u2f_return_code');
                u2f_return_code.style.display = u2f_return_code.style.display === 'none' ? '' : null;
                if (deviceResponse.errorCode == "4") { deviceResponse.errorCode = "4 - The presented device is not eligible for this request. For a registration request this may mean that the token is already registered, and for a sign request it may mean that the token does not know the presented key handle"; }
                u2f_return_code.innerHTML = 'Error code: ' + deviceResponse.errorCode;
                return;
              }
              reg.value = JSON.stringify(deviceResponse);
              form.submit();
            });
          }, 1000);
        }
      });
    }
    if ($(this).val() == "none") {
      $('#DisableTFAModal').modal('show');
      $("option:selected").prop("selected", false);
    }
  });

  $(function () {
    $('[data-toggle="tooltip"]').tooltip()
  });

  // Remember last navigation pill
  (function () {
    'use strict';
    if ($('a[data-toggle="tab"]').length) {
      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var id = $(this).parents('[role="tablist"]').attr('id');
        var key = 'lastTag';
        if (id) {
          key += ':' + id;
        }
        localStorage.setItem(key, $(e.target).attr('href'));
      });
      $('[role="tablist"]').each(function (idx, elem) {
        var id = $(elem).attr('id');
        var key = 'lastTag';
        if (id) {
          key += ':' + id;
        }
        var lastTab = localStorage.getItem(key);
        if (lastTab) {
          $('[href="' + lastTab + '"]').tab('show');
        }
      });
    }
  })();

  // Disable submit after submitting form (not API driven buttons)
  $('form').submit(function() {
    if ($('form button[type="submit"]').data('submitted') == '1') {
      return false;
    } else {
      $(this).find('button[type="submit"]').first().text('<?= $lang['footer']['loading']; ?>');
      $('form button[type="submit"]').attr('data-submitted', '1');
      function disableF5(e) { if ((e.which || e.keyCode) == 116 || (e.which || e.keyCode) == 82) e.preventDefault(); };
      $(document).on("keydown", disableF5);
    }
  });

  // IE fix to hide scrollbars when table body is empty
  $('tbody').filter(function (index) {
    return $(this).children().length < 1;
  }).remove();

  // Init Bootstrap Selectpicker
  $('select').selectpicker();

  // Trigger container restart
  $('#RestartContainer').on('show.bs.modal', function(e) {
    var container = $(e.relatedTarget).data('container');
    $('#containerName').text(container);
    $('#triggerRestartContainer').click(function(){
      $(this).prop("disabled",true);
      $(this).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> ');
      $('#statusTriggerRestartContainer').text('Restarting container, this may take a while... ');
      $('#statusTriggerRestartContainer2').text('Reloading webpage... ');
      $.ajax({
        method: 'get',
        url: '/inc/ajax/container_ctrl.php',
        timeout: 10000,
        data: {
          'service': container,
          'action': 'restart'
        },
        error: function() {
          window.location = window.location.href.split("#")[0];
        },
        success: function(data) {
          $('#statusTriggerRestartContainer').append(data);
          $('#triggerRestartContainer').html('<span class="glyphicon glyphicon-ok"></span> ');
          $('#statusTriggerRestartContainer2').append(data);
          $('#triggerRestartContainer').html('<span class="glyphicon glyphicon-ok"></span> ');
          window.location = window.location.href.split("#")[0];
        }
      });
    });
  })

  // CSRF
  $('<input type="hidden" value="<?= $_SESSION['CSRF']['TOKEN']; ?>">').attr('id', 'csrf_token').attr('name', 'csrf_token').appendTo('form');
  if (sessionStorage.scrollTop != "undefined") {
    $(window).scrollTop(sessionStorage.scrollTop);
  }
});
</script>

</body>
</html>
<?php
$stmt = null;
$pdo = null;
