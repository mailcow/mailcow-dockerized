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
	$('#trigger_set_time_limited_aliases').hide(); 
	$('#validity').change(function(){
		$('#trigger_set_time_limited_aliases').show(); 
	});

	// Init Bootstrap Switch
	$.fn.bootstrapSwitch.defaults.onColor = 'success';
	$("[name='tls_out']").bootstrapSwitch();
	$("[name='tls_in']").bootstrapSwitch();
});