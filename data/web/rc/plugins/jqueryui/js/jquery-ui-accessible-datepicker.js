/*! jQuery UI Accessible Datepicker extension
* (to be appended to jquery-ui-*.custom.min.js)
*
* @licstart The following is the entire license notice for the
*  JavaScript code in this page.
*
* Copyright 2014 Kolab Systems AG
*
* The JavaScript code in this page is free software: you can
* redistribute it and/or modify it under the terms of the GNU
* General Public License (GNU GPL) as published by the Free Software
* Foundation, either version 3 of the License, or (at your option)
* any later version.  The code is distributed WITHOUT ANY WARRANTY;
* without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.  See the GNU GPL for more details.
*
* As additional permission under GNU GPL version 3 section 7, you
* may distribute non-source (e.g., minimized or compacted) forms of
* that code without the copy of the GNU GPL normally required by
* section 4, provided you include this license notice and a URL
* through which recipients can access the Corresponding Source.
*
* @licend The above is the entire license notice
*  for the JavaScript code in this page.
*/

(function($, undefined) {

// references to super class methods
var __newInst           = $.datepicker._newInst;
var __updateDatepicker  = $.datepicker._updateDatepicker;
var __connectDatepicker = $.datepicker._connectDatepicker;
var __showDatepicker    = $.datepicker._showDatepicker;
var __hideDatepicker    = $.datepicker._hideDatepicker;

// "extend" singleton instance methods
$.extend($.datepicker, {

	/* Create a new instance object */
	_newInst: function(target, inline) {
		var that = this, inst = __newInst.call(this, target, inline);

		if (inst.inline) {
			// attach keyboard event handler
			inst.dpDiv.on('keydown.datepicker', '.ui-datepicker-calendar', function(event) {
				// we're only interested navigation keys
				if ($.inArray(event.keyCode, [ 13, 33, 34, 35, 36, 37, 38, 39, 40]) == -1) {
					return;
				}
				event.stopPropagation();
				event.preventDefault();
				inst._hasfocus = true;

				var activeCell;
				switch (event.keyCode) {
					case $.ui.keyCode.ENTER:
						if ((activeCell = $('.' + that._dayOverClass, inst.dpDiv).get(0) || $('.' + that._currentClass, inst.dpDiv).get(0))) {
							that._selectDay(inst.input, inst.selectedMonth, inst.selectedYear, activeCell);
						}
						break;

					case $.ui.keyCode.PAGE_UP:
						that._adjustDate(inst.input, -that._get(inst, 'stepMonths'), 'M');
						break;
					case $.ui.keyCode.PAGE_DOWN:
						that._adjustDate(inst.input, that._get(inst, 'stepMonths'), 'M');
						break;

					default:
						return that._cursorKeydown(event, inst);
				}
			})
			.attr('role', 'region')
			.attr('aria-labelledby', inst.id + '-dp-title');
		}
		else {
			var widgetId = inst.dpDiv.attr('id') || inst.id + '-dp-widget';
			inst.dpDiv.attr('id', widgetId)
				.attr('aria-hidden', 'true')
				.attr('aria-labelledby', inst.id + '-dp-title');

				$(inst.input).attr('aria-haspopup', 'true')
					.attr('aria-expanded', 'false')
					.attr('aria-owns', widgetId);
		}

		return inst;
	},

	/* Attach the date picker to an input field */
	_connectDatepicker: function(target, inst) {
		__connectDatepicker.call(this, target, inst);

		var that = this;

		// register additional keyboard events to control date selection with cursor keys
		$(target).unbind('keydown.datepicker-extended').bind('keydown.datepicker-extended', function(event) {
			var inc = 1;
			switch (event.keyCode) {
				case 109:
				case 173:
				case 189:  // "minus"
					inc = -1;
				case 61:
				case 107:
				case 187:  // "plus"
					// do nothing if the input does not contain full date string
					if (this.value.length < that._formatDate(inst, inst.selectedDay, inst.selectedMonth, inst.selectedYear).length) {
						return true;
					}
					that._adjustInstDate(inst, inc, 'D');
					that._selectDateRC(target, that._formatDate(inst, inst.selectedDay, inst.selectedMonth, inst.selectedYear));
					return false;

				case $.ui.keyCode.UP:
				case $.ui.keyCode.DOWN:
					// unfold datepicker if not visible
					if ($.datepicker._lastInput !== target && !$.datepicker._isDisabledDatepicker(target)) {
						that._showDatepicker(event);
						event.stopPropagation();
						event.preventDefault();
						return false;
					}

				default:
					if (!$.datepicker._isDisabledDatepicker(target) && !event.ctrlKey && !event.metaKey) {
						return that._cursorKeydown(event, inst);
					}
			}
		})
		// fix https://bugs.jqueryui.com/ticket/8593
		.click(function (event) { that._showDatepicker(event); })
		.attr('autocomplete', 'off');
	},

	/* Handle keyboard event on datepicker widget */
	_cursorKeydown: function(event, inst) {
		inst._keyEvent = true;

		var isRTL = inst.dpDiv.hasClass('ui-datepicker-rtl');

		switch (event.keyCode) {
			case $.ui.keyCode.LEFT:
				this._adjustDate(inst.input, (isRTL ? +1 : -1), 'D');
				break;
			case $.ui.keyCode.RIGHT:
				this._adjustDate(inst.input, (isRTL ? -1 : +1), 'D');
				break;
			case $.ui.keyCode.UP:
				this._adjustDate(inst.input, -7, 'D');
				break;
			case $.ui.keyCode.DOWN:
				this._adjustDate(inst.input, +7, 'D');
				break;
			case $.ui.keyCode.HOME:
				// TODO: jump to first of month
				break;
			case $.ui.keyCode.END:
				// TODO: jump to end of month
				break;
		}

		return true;
	},

	/* Pop-up the date picker for a given input field */
	_showDatepicker: function(input) {
		input = input.target || input;
		__showDatepicker.call(this, input);

		var inst = $.datepicker._getInst(input);
		if (inst && $.datepicker._datepickerShowing) {
			inst.dpDiv.attr('aria-hidden', 'false');
			$(input).attr('aria-expanded', 'true');
		}
	},

	/* Hide the date picker from view */
	_hideDatepicker: function(input) {
		__hideDatepicker.call(this, input);

		var inst = this._curInst;
		if (inst && !$.datepicker._datepickerShowing) {
			inst.dpDiv.attr('aria-hidden', 'true');
			$(inst.input).attr('aria-expanded', 'false');
		}
	},

	/* Render the date picker content */
	_updateDatepicker: function(inst) {
		__updateDatepicker.call(this, inst);

		var activeCell = $('.' + this._dayOverClass, inst.dpDiv).get(0) || $('.' + this._currentClass, inst.dpDiv).get(0);
		if (activeCell) {
			activeCell = $(activeCell);
			activeCell.attr('id', inst.id + '-day-' + activeCell.text());
		}

		// allow focus on main container only
		inst.dpDiv.find('.ui-datepicker-calendar')
			.attr('tabindex', inst.inline ? '0' : '-1')
			.attr('role', 'grid')
			.attr('aria-readonly', 'true')
			.attr('aria-activedescendant', activeCell ? activeCell.attr('id') : '')
			.find('td').attr('role', 'gridcell').attr('aria-selected', 'false')
			.find('a').attr('tabindex', '-1');

		$('.ui-datepicker-current-day', inst.dpDiv).attr('aria-selected', 'true');

		inst.dpDiv.find('.ui-datepicker-title')
			.attr('id', inst.id + '-dp-title')

		// set focus again after update
		if (inst._hasfocus) {
			inst.dpDiv.find('.ui-datepicker-calendar').focus();
			inst._hasfocus = false;
		}
	},

	_selectDateRC: function(id, dateStr) {
		var target = $(id), inst = this._getInst(target[0]);

		dateStr = (dateStr != null ? dateStr : this._formatDate(inst));
		if (inst.input) {
			inst.input.val(dateStr);
		}
		this._updateAlternate(inst);
		if (inst.input) {
			inst.input.trigger("change"); // fire the change event
		}
		if (inst.inline) {
			this._updateDatepicker(inst);
		}
	}
});

}(jQuery));
