$(document).ready(function() {
  // Auto-fill domain quota when adding new domain
  auto_fill_quota = function(domain) {
		$.get("/api/v1/get/domain/" + domain, function(data){
      var result = $.parseJSON(JSON.stringify(data));
      max_new_mailbox_quota = ( result.max_new_mailbox_quota / 1048576);
			if (max_new_mailbox_quota != '0') {
				$("#quotaBadge").html('max. ' +  max_new_mailbox_quota + ' MiB');
				$('#addInputQuota').attr({"disabled": false, "value": "", "type": "number", "max": max_new_mailbox_quota});
				$('#addInputQuota').val(max_new_mailbox_quota);
			}
			else {
				$("#quotaBadge").html('max. ' + max_new_mailbox_quota + ' MiB');
				$('#addInputQuota').attr({"disabled": true, "value": "", "type": "text", "value": "n/a"});
				$('#addInputQuota').val(max_new_mailbox_quota);
			}
		});
  }
	$('#addSelectDomain').on('change', function() {
    auto_fill_quota($('#addSelectDomain').val());
	});
  auto_fill_quota($('#addSelectDomain').val());
});
