/**
 * This plug-in for DataTables represents the ultimate option in extensibility
 * for sorting date / time strings correctly. It uses
 * [Moment.js](http://momentjs.com) to create automatic type detection and
 * sorting plug-ins for DataTables based on a given format. This way, DataTables
 * will automatically detect your temporal information and sort it correctly.
 *
 * For usage instructions, please see the DataTables blog
 * post that [introduces it](//datatables.net/blog/2014-12-18).
 *
 * @name Ultimate Date / Time sorting
 * @summary Sort date and time in any format using Moment.js
 * @author [Allan Jardine](//datatables.net)
 * @depends DataTables 1.10+, Moment.js 1.7+
 *
 * @example
 *    $.fn.dataTable.moment( 'HH:mm MMM D, YY' );
 *    $.fn.dataTable.moment( 'dddd, MMMM Do, YYYY' );
 *
 *    $('#example').DataTable();
 */

(function (factory) {
	if (typeof define === "function" && define.amd) {
		define(["jquery", "moment", "datatables.net"], factory);
	} else {
		factory(jQuery, moment);
	}
}(function ($, moment) {

function strip (d) {
	if ( typeof d === 'string' ) {
		// Strip HTML tags and newline characters if possible
		d = d.replace(/(<.*?>)|(\r?\n|\r)/g, '');

		// Strip out surrounding white space
		d = d.trim();
	}

	return d;
}

$.fn.dataTable.moment = function ( format, locale, reverseEmpties ) {
	var types = $.fn.dataTable.ext.type;

	// Add type detection
	types.detect.unshift( function ( d ) {
		d = strip(d);

		// Null and empty values are acceptable
		if ( d === '' || d === null ) {
			return 'moment-'+format;
		}

		return moment( d, format, locale, true ).isValid() ?
			'moment-'+format :
			null;
	} );

	// Add sorting method - use an integer for the sorting
	types.order[ 'moment-'+format+'-pre' ] = function ( d ) {
		d = strip(d);
		
		return !moment(d, format, locale, true).isValid() ?
			(reverseEmpties ? -Infinity : Infinity) :
			parseInt( moment( d, format, locale, true ).format( 'x' ), 10 );
	};
};

}));
