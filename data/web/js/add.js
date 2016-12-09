$(document).ready(function() {
	// add.php
	// Get max. possible quota for a domain when domain field changes
	$('#addSelectDomain').on('change', function() {
		$.get("add.php", { js:"remaining_specs", domain:this.value, object:"new" }, function(data){
			if (data != '0') {
				$("#quotaBadge").html('max. ' + data + ' MiB');
				$('#addInputQuota').attr({"disabled": false, "value": "", "type": "number", "max": data});
			}
			else {
				$("#quotaBadge").html('max. ' + data + ' MiB');
				$('#addInputQuota').attr({"disabled": true, "value": "", "type": "text", "value": "n/a"});
			}
		});
	});
});
