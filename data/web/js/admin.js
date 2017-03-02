$(document).ready(function() {
	// Postfix restrictions, drag and drop functions
	$( "[id*=srr-sortable]" ).sortable({
		items: "li:not(.list-heading)",
		cancel: ".ui-state-disabled",
		connectWith: "[id*=srr-sortable]",
		dropOnEmpty: true,
		placeholder: "ui-state-highlight"
	});
	$( "[id*=ssr-sortable]" ).sortable({
		items: "li:not(.list-heading)",
		cancel: ".ui-state-disabled",
		connectWith: "[id*=ssr-sortable]",
		dropOnEmpty: true,
		placeholder: "ui-state-highlight"
	});
	$('#srr_form').submit(function(){
		var srr_joined_vals = $("[id^=srr-sortable-active] li").map(function() {
			return $(this).data("value");
		}).get().join(', ');
		var input = $("<input>").attr("type", "hidden").attr("name", "srr_value").val(srr_joined_vals);
		$('#srr_form').append($(input));
	});
	$('#ssr_form').submit(function(){
		var ssr_joined_vals = $("[id^=ssr-sortable-active] li").map(function() {
			return $(this).data("value");
		}).get().join(', ');
		var input = $("<input>").attr("type", "hidden").attr("name", "ssr_value").val(ssr_joined_vals);
		$('#ssr_form').append($(input));
	});
});