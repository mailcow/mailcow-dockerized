$(document).ready(function() {
	// Show and activate password fields after box was checked
	// Hidden by default
	if ( !$("#togglePwNew").is(':checked') ) {
		$(".passFields").hide();
	}
	$('#togglePwNew').click(function() {
		$("#user_new_pass").attr("disabled", !this.checked);
		$("#user_new_pass2").attr("disabled", !this.checked);
		var $this = $(this);
		if ($this.is(':checked')) {
			$(".passFields").slideDown();
		} else {
			$(".passFields").slideUp();
		}
	});
	// Show generate button after time selection
	$('#generate_tla').hide(); 
	$('#validity').change(function(){
		$('#generate_tla').show(); 
	});

	// Init Bootstrap Switch
	$.fn.bootstrapSwitch.defaults.onColor = 'success';
	$("[name='tls_out']").bootstrapSwitch();
	$("[name='tls_in']").bootstrapSwitch();

  // Log modal
  $('#logModal').on('show.bs.modal', function(e) {
  var logText = $(e.relatedTarget).data('log-text');
  $(e.currentTarget).find('#logText').html('<pre style="background:none;font-size:11px;line-height:1.1;border:0px">' + logText + '</pre>');
  });
});