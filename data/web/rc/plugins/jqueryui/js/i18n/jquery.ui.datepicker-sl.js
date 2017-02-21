/* @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT) */
/* Slovenian initialisation for the jQuery UI date picker plugin. */
/* Written by Jaka Jancar (jaka@kubje.org). */
/* c = č, s = š z = ž C = Č S = Š Z = Ž */
jQuery(function($){
	$.datepicker.regional['sl'] = {
		closeText: 'Zapri',
		prevText: '&#x3C;Prejšnji',
		nextText: 'Naslednji&#x3E;',
		currentText: 'Trenutni',
		monthNames: ['Januar','Februar','Marec','April','Maj','Junij',
		'Julij','Avgust','September','Oktober','November','December'],
		monthNamesShort: ['Jan','Feb','Mar','Apr','Maj','Jun',
		'Jul','Avg','Sep','Okt','Nov','Dec'],
		dayNames: ['Nedelja','Ponedeljek','Torek','Sreda','Četrtek','Petek','Sobota'],
		dayNamesShort: ['Ned','Pon','Tor','Sre','Čet','Pet','Sob'],
		dayNamesMin: ['Ne','Po','To','Sr','Če','Pe','So'],
		weekHeader: 'Teden',
		dateFormat: 'dd.mm.yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''};
	$.datepicker.setDefaults($.datepicker.regional['sl']);
});
/* @license-end */
