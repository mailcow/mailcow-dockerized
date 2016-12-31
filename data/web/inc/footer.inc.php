<?php
if ($_SESSION['mailcow_cc_role'] == "admin"):
?>
<div id="RestartSOGo" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">&times;</button>
			<h4 class="modal-title">Restart SOGo</h4>
		</div>
		<div class="modal-body">
			<p>Some tasks, e.g. adding a domain, require you to restart SOGo to catch changes made in the mailcow UI.</p>
			<hr />
			<button class="btn btn-md btn-primary" id="triggerRestartSogo">Restart SOGo</button>
			<br /><br />
			<div id="statusTriggerRestartSogo"></div>
		</div>
		</div>
	</div>
</div>
<?php
endif;
?>
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-switch/3.3.2/js/bootstrap-switch.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/7.0.2/bootstrap-slider.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.9.4/js/bootstrap-select.js"></script>
<script>
// Select language and reopen active URL without POST
function setLang(sel) {
	$.post( "<?=$_SERVER['REQUEST_URI'];?>", {lang: sel} );
	window.location.href = window.location.pathname + window.location.search;
}

$(document).ready(function() {
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
