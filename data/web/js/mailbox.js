$(document).ready(function() {
	// Show element counter for tables
	$('[data-toggle="tooltip"]').tooltip();
	var rowCountDomainAlias = $('#domainaliastable >tbody >#data').length;
	var rowCountDomain = $('#domaintable >tbody >#data').length;
	var rowCountMailbox = $('#mailboxtable >tbody >#data').length;
	var rowCountAlias = $('#aliastable >tbody >#data').length;
	var rowCountResource = $('#resourcetable >tbody >#data').length;
	$("#numRowsDomainAlias").text(rowCountDomainAlias);
	$("#numRowsDomain").text(rowCountDomain);
	$("#numRowsMailbox").text(rowCountMailbox);
	$("#numRowsAlias").text(rowCountAlias);
	$("#numRowsResource").text(rowCountResource);

	// Filter table function
	$.fn.extend({
		filterTable: function(){
			return this.each(function(){
				$(this).on('keyup', function(e){
					var $this = $(this),
            search = $this.val().toLowerCase(),
            target = $this.attr('data-filters'),
            $target = $(target),
            $rows = $target.find('tbody #data');
					$target.find('tbody .filterTable_no_results').remove();
					if(search == '') {
						$target.find('tbody #no-data').show();
						$rows.show();
					} else {
						$target.find('tbody #no-data').hide();
						$rows.each(function(){
							var $this = $(this);
							$this.text().toLowerCase().indexOf(search) === -1 ? $this.hide() : $this.show();
						})
						if($target.find('tbody #data:visible').size() === 0) {
							var col_count = $target.find('#data').first().find('td').size();
							var no_results = $('<tr class="filterTable_no_results"><td colspan="100%">-</td></tr>')
							$target.find('tbody').prepend(no_results);
						}
					}
				});
			});
		}
	});
	$('[data-action="filter"]').filterTable();
	$('.container').on('click', '.panel-heading span.filter', function(e){
		var $this = $(this),
		$panel = $this.parents('.panel');
		$panel.find('.panel-body').slideToggle("fast");
		if($this.css('display') != 'none') {
			$panel.find('.panel-body input').focus();
		}
	});
});
