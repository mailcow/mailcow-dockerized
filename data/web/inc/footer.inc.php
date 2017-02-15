<?php
include("inc/tfa_modals.php");
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin"):
?>
<div id="RestartSOGo" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">&times;</button>
			<h4 class="modal-title"><?=$lang['footer']['restart_sogo'];?></h4>
		</div>
		<div class="modal-body">
			<p><?=$lang['footer']['restart_sogo_info'];?></p>
			<hr />
			<button class="btn btn-md btn-primary" id="triggerRestartSogo"><?=$lang['footer']['restart_now'];?></button>
			<br /><br />
			<div id="statusTriggerRestartSogo"></div>
		</div>
		</div>
	</div>
</div>
<?php
endif;
?>
<div style="margin-bottom:100px"></div>
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
<script src="/js/bootstrap-switch.min.js"></script>
<script src="/js/bootstrap-slider.min.js"></script>
<script src="/js/bootstrap-select.min.js"></script>
<script src="/js/u2f-api.js"></script>
<script>
// Select language and reopen active URL without POST
function setLang(sel) {
	$.post( "<?=$_SERVER['REQUEST_URI'];?>", {lang: sel} );
	window.location.href = window.location.pathname + window.location.search;
}

$(document).ready(function() {
  // Confirm TFA modal
  <?php if (isset($_SESSION['pending_tfa_method'])):?>
  $('#ConfirmTFAModal').modal({
    backdrop: 'static',
    keyboard: false
  }); 
  $('#ConfirmTFAModal').on('shown.bs.modal', function(){
      $(this).find('#token').focus();
      // If U2F
      if(document.getElementById("u2f_auth_data") !== null) {
        $.ajax({
          type: "GET",
          cache: false,
          dataType: 'script',
          url: "json_api.php",
          data: {
            'action':'get_u2f_auth_challenge',
            'object':'<?=(isset($_SESSION['pending_mailcow_cc_username'])) ? $_SESSION['pending_mailcow_cc_username'] : null;?>',
          },
          success: function(data){
            data;
          }
        });
        setTimeout(function() {
          console.log("sign: ", req);
          u2f.sign(req, function(data) {
            var form = document.getElementById('u2f_auth_form');
            var auth = document.getElementById('u2f_auth_data');
            console.log("Authenticate callback", data);
            auth.value = JSON.stringify(data);
            form.submit();
          });
        }, 1000);
      }
  });
  <?php endif; ?>

  // Set TFA modals

  $('#selectTFA').change(function () {
    if ($(this).val() == "yubi_otp") {
      $('#YubiOTPModal').modal('show');
      $("option:selected").prop("selected", false);
    }
    if ($(this).val() == "u2f") {
      $('#U2FModal').modal('show');
      $("option:selected").prop("selected", false);
      $.ajax({
        type: "GET",
        cache: false,
        dataType: 'script',
        url: "json_api.php",
        data: {
          'action':'get_u2f_reg_challenge',
          'object':'<?=(isset($_SESSION['mailcow_cc_username'])) ? $_SESSION['mailcow_cc_username'] : null;?>',
        },
        success: function(data){
          data;
        }
      });
      setTimeout(function() {
        console.log("Register: ", req);
        u2f.register([req], sigs, function(data) {
          var form  = document.getElementById('u2f_reg_form');
          var reg   = document.getElementById('u2f_register_data');
          console.log("Register callback", data);
          if (data.errorCode && data.errorCode != 0) {
            var u2f_return_code = document.getElementById('u2f_return_code');
            u2f_return_code.style.display = u2f_return_code.style.display === 'none' ? '' : null;
            if (data.errorCode == "4") { data.errorCode = "4 - The presented device is not eligible for this request. For a registration request this may mean that the token is already registered, and for a sign request it may mean that the token does not know the presented key handle"; }
            u2f_return_code.innerHTML = 'Error code: ' + data.errorCode;
            return;
          }
          reg.value = JSON.stringify(data);
          form.submit();
        });
      }, 1000);
    }
    if ($(this).val() == "none") {
      $('#DisableTFAModal').modal('show');
      $("option:selected").prop("selected", false);
    }
  });

  // Activate tooltips
  $(function () {
    $('[data-toggle="tooltip"]').tooltip()
  })
	// Hide alerts after n seconds
	$("#alert-fade").fadeTo(7000, 500).slideUp(500, function(){
		$("#alert-fade").alert('close');
	});

	// Remember last navigation pill
	(function () {
		'use strict';
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
	})();

	// Disable submit after submitting form
	$('form').submit(function() {
		if ($('form button[type="submit"]').data('submitted') == '1') {
			return false;
		} else {
			$(this).find('button[type="submit"]').first().text('<?=$lang['footer']['loading'];?>');
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

	// Trigger SOGo restart
	$('#triggerRestartSogo').click(function(){
		$(this).prop("disabled",true);
		$(this).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> ');
		$('#statusTriggerRestartSogo').text('Stopping SOGo workers, this may take a while... ');
		$.ajax({
			method: 'get',
			url: 'call_sogo_ctrl.php',
			data: {
				'ajax': true,
				'ACTION': 'stop'
			},
			success: function(data) {
				$('#statusTriggerRestartSogo').append(data);
				$('#statusTriggerRestartSogo').append('<br />Starting SOGo... ');
				$.ajax({
					method: 'get',
					url: 'call_sogo_ctrl.php',
					data: {
						'ajax': true,
						'ACTION': 'start'
					},
					success: function(data) {
						$('#statusTriggerRestartSogo').append(data);
						$('#triggerRestartSogo').html('<span class="glyphicon glyphicon-ok"></span> ');
					}
				});
			}
		});
	});
});
</script>
<?php
if (isset($_SESSION['return'])):
?>
<div class="container">
	<div style="position:fixed;bottom:8px;right:25px;min-width:300px;max-width:350px;z-index:2000">
		<div <?=($_SESSION['return']['type'] == 'danger') ? null : 'id="alert-fade"'?> class="alert alert-<?=$_SESSION['return']['type'];?>" role="alert">
		<a href="#" class="close" data-dismiss="alert"> &times;</a>
		<?=htmlspecialchars($_SESSION['return']['msg']);?>
		</div>
	</div>
</div>
<?php
unset($_SESSION['return']);
endif;
?>
</body>
</html>
<?php $stmt = null; $pdo = null; ?>
