/* @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT) */
/* Afrikaans initialisation for the jQuery UI date picker plugin. */
/* Written by Renier Pretorius. */
jQuery(function($){
	$.datepicker.regional['af'] = {
		closeText: 'Selekteer',
		prevText: 'Vorige',
		nextText: 'Volgende',
		currentText: 'Vandag',
		monthNames: ['Januarie','Februarie','Maart','April','Mei','Junie',
		'Julie','Augustus','September','Oktober','November','Desember'],
		monthNamesShort: ['Jan', 'Feb', 'Mrt', 'Apr', 'Mei', 'Jun',
		'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Des'],
		dayNames: ['Sondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrydag', 'Saterdag'],
		dayNamesShort: ['Son', 'Maa', 'Din', 'Woe', 'Don', 'Vry', 'Sat'],
		dayNamesMin: ['So','Ma','Di','Wo','Do','Vr','Sa'],
		weekHeader: 'Wk',
		dateFormat: 'dd/mm/yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''};
	$.datepicker.setDefaults($.datepicker.regional['af']);
});
/* @license-end */
