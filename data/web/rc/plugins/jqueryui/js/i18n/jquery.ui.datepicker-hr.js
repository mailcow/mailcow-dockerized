/* @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT) */
/* Croatian i18n for the jQuery UI date picker plugin. */
/* Written by Vjekoslav Nesek. */
jQuery(function($){
	$.datepicker.regional['hr'] = {
		closeText: 'Zatvori',
		prevText: '&#x3C;',
		nextText: '&#x3E;',
		currentText: 'Danas',
		monthNames: ['Siječanj','Veljača','Ožujak','Travanj','Svibanj','Lipanj',
		'Srpanj','Kolovoz','Rujan','Listopad','Studeni','Prosinac'],
		monthNamesShort: ['Sij','Velj','Ožu','Tra','Svi','Lip',
		'Srp','Kol','Ruj','Lis','Stu','Pro'],
		dayNames: ['Nedjelja','Ponedjeljak','Utorak','Srijeda','Četvrtak','Petak','Subota'],
		dayNamesShort: ['Ned','Pon','Uto','Sri','Čet','Pet','Sub'],
		dayNamesMin: ['Ne','Po','Ut','Sr','Če','Pe','Su'],
		weekHeader: 'Tje',
		dateFormat: 'dd.mm.yy.',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''};
	$.datepicker.setDefaults($.datepicker.regional['hr']);
});
/* @license-end */
