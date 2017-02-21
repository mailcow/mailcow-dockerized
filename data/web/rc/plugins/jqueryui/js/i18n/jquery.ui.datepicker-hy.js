/* @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT) */
/* Armenian(UTF-8) initialisation for the jQuery UI date picker plugin. */
/* Written by Levon Zakaryan (levon.zakaryan@gmail.com)*/
jQuery(function($){
	$.datepicker.regional['hy'] = {
		closeText: 'Փակել',
		prevText: '&#x3C;Նախ.',
		nextText: 'Հաջ.&#x3E;',
		currentText: 'Այսօր',
		monthNames: ['Հունվար','Փետրվար','Մարտ','Ապրիլ','Մայիս','Հունիս',
		'Հուլիս','Օգոստոս','Սեպտեմբեր','Հոկտեմբեր','Նոյեմբեր','Դեկտեմբեր'],
		monthNamesShort: ['Հունվ','Փետր','Մարտ','Ապր','Մայիս','Հունիս',
		'Հուլ','Օգս','Սեպ','Հոկ','Նոյ','Դեկ'],
		dayNames: ['կիրակի','եկուշաբթի','երեքշաբթի','չորեքշաբթի','հինգշաբթի','ուրբաթ','շաբաթ'],
		dayNamesShort: ['կիր','երկ','երք','չրք','հնգ','ուրբ','շբթ'],
		dayNamesMin: ['կիր','երկ','երք','չրք','հնգ','ուրբ','շբթ'],
		weekHeader: 'ՇԲՏ',
		dateFormat: 'dd.mm.yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''};
	$.datepicker.setDefaults($.datepicker.regional['hy']);
});
/* @license-end */
