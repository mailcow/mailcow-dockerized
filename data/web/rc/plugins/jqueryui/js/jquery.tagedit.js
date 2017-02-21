/*
* Tagedit - jQuery Plugin
* The Plugin can be used to edit tags from a database the easy way
*
* Examples and documentation at: tagedit.webwork-albrecht.de
*
* License:
* This work is licensed under a MIT License
*
* @licstart  The following is the entire license notice for the
* JavaScript code in this file.
*
* Copyright (c) 2010 Oliver Albrecht <info@webwork-albrecht.de>
* Copyright (c) 2014 Thomas Brüderli <thomas@roundcube.net>
*
* Licensed under the MIT licenses
*
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
*
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*
* @licend  The above is the entire license notice
* for the JavaScript code in this file.
*
* @author Oliver Albrecht Mial: info@webwork-albrecht.de Twitter: @webworka
* @version 1.5.2 (06/2014)
* Requires: jQuery v1.4+, jQueryUI v1.8+, jQuerry.autoGrowInput
*
* Example of usage:
*
* $( "input.tag" ).tagedit();
*
* Possible options:
*
*  autocompleteURL: '', // url for a autocompletion
*  deleteEmptyItems: true, // Deletes items with empty value
*  deletedPostfix: '-d', // will be put to the Items that are marked as delete
*  addedPostfix: '-a', // will be put to the Items that are choosem from the database
*  additionalListClass: '', // put a classname here if the wrapper ul shoud receive a special class
*  allowEdit: true, // Switch on/off edit entries
*  allowDelete: true, // Switch on/off deletion of entries. Will be ignored if allowEdit = false
*  allowAdd: true, // switch on/off the creation of new entries
*  direction: 'ltr' // Sets the writing direction for Outputs and Inputs
*  animSpeed: 500 // Sets the animation speed for effects
*  autocompleteOptions: {}, // Setting Options for the jquery UI Autocomplete (http://jqueryui.com/demos/autocomplete/)
*  breakKeyCodes: [ 13, 44 ], // Sets the characters to break on to parse the tags (defaults: return, comma)
*  checkNewEntriesCaseSensitive: false, // If there is a new Entry, it is checked against the autocompletion list. This Flag controlls if the check is (in-)casesensitive
*  texts: { // some texts
*      removeLinkTitle: 'Remove from list.',
*      saveEditLinkTitle: 'Save changes.',
*      deleteLinkTitle: 'Delete this tag from database.',
*      deleteConfirmation: 'Are you sure to delete this entry?',
*      deletedElementTitle: 'This Element will be deleted.',
*      breakEditLinkTitle: 'Cancel'
*  }
*/

(function($) {

	$.fn.tagedit = function(options) {
		/**
		* Merge Options with defaults
		*/
		options = $.extend(true, {
			// default options here
			autocompleteURL: null,
			checkToDeleteURL: null,
			deletedPostfix: '-d',
			addedPostfix: '-a',
			additionalListClass: '',
			allowEdit: true,
			allowDelete: true,
			allowAdd: true,
			direction: 'ltr',
			animSpeed: 500,
			autocompleteOptions: {
				select: function( event, ui ) {
					$(this).val(ui.item.value).trigger('transformToTag', [ui.item.id]);
					return false;
				}
			},
			breakKeyCodes: [ 13, 44 ],
			checkNewEntriesCaseSensitive: false,
			texts: {
				removeLinkTitle: 'Remove from list.',
				saveEditLinkTitle: 'Save changes.',
				deleteLinkTitle: 'Delete this tag from database.',
				deleteConfirmation: 'Are you sure to delete this entry?',
				deletedElementTitle: 'This Element will be deleted.',
				breakEditLinkTitle: 'Cancel',
                forceDeleteConfirmation: 'There are more records using this tag, are you sure do you want to remove it?'
			},
			tabindex: false
		}, options || {});

		// no action if there are no elements
		if(this.length == 0) {
			return;
		}

		// set the autocompleteOptions source
		if(options.autocompleteURL) {
			options.autocompleteOptions.source = options.autocompleteURL;
		}

		// Set the direction of the inputs
		var direction= this.attr('dir');
		if(direction && direction.length > 0) {
			options.direction = this.attr('dir');
		}

		var elements = this;
		var focusItem = null;

		var baseNameRegexp = new RegExp("^(.*)\\[([0-9]*?("+options.deletedPostfix+"|"+options.addedPostfix+")?)?\]$", "i");

		var baseName = elements.eq(0).attr('name').match(baseNameRegexp);
		if(baseName && baseName.length == 4) {
			baseName = baseName[1];
		}
		else {
			// Elementname does not match the expected format, exit
			alert('elementname dows not match the expected format (regexp: '+baseNameRegexp+')')
			return;
		}

		// read tabindex from source element
		var ti;
		if (!options.tabindex && (ti = elements.eq(0).attr('tabindex')))
			options.tabindex = ti;

		// init elements
		inputsToList();

		/**
		* Creates the tageditinput from a list of textinputs
		*
		*/
		function inputsToList() {
			var html = '<ul class="tagedit-list '+options.additionalListClass+'">';

			elements.each(function(i) {
				var element_name = $(this).attr('name').match(baseNameRegexp);
				if(element_name && element_name.length == 4 && (options.deleteEmptyItems == false || $(this).val().length > 0)) {
					if(element_name[1].length > 0) {
						var elementId = typeof element_name[2] != 'undefined'? element_name[2]: '',
							domId = 'tagedit-' + baseName + '-' + (elementId || i);

						html += '<li class="tagedit-listelement tagedit-listelement-old" aria-labelledby="'+domId+'">';
						html += '<span dir="'+options.direction+'" id="'+domId+'">' + $(this).val() + '</span>';
						html += '<input type="hidden" name="'+baseName+'['+elementId+']" value="'+$(this).val()+'" />';
						if (options.allowDelete)
							html += '<a class="tagedit-close" title="'+options.texts.removeLinkTitle+'" aria-label="'+options.texts.removeLinkTitle+' '+$(this).val()+'">x</a>';
						html += '</li>';
					}
				}
			});

			// replace Elements with the list and save the list in the local variable elements
			elements.last().after(html)
			var newList = elements.last().next();
			elements.remove();
			elements = newList;

			// Check if some of the elementshav to be marked as deleted
			if(options.deletedPostfix.length > 0) {
				elements.find('input[name$="'+options.deletedPostfix+'\]"]').each(function() {
					markAsDeleted($(this).parent());
				});
			}

			// put an input field at the End
			// Put an empty element at the end
			html = '<li class="tagedit-listelement tagedit-listelement-new">';
			if (options.allowAdd)
				html += '<input type="text" name="'+baseName+'[]" value="" id="tagedit-input" disabled="disabled" class="tagedit-input-disabled" dir="'+options.direction+'"/>';
			html += '</li>';
			html += '</ul>';

			elements
				.append(html)
				.attr('tabindex', options.tabindex) // set tabindex to <ul> to recieve focus

				// Set function on the input
				.find('#tagedit-input')
					.attr('tabindex', options.tabindex)
					.each(function() {
						$(this).autoGrowInput({comfortZone: 15, minWidth: 15, maxWidth: 20000});

						// Event is triggert in case of choosing an item from the autocomplete, or finish the input
						$(this).bind('transformToTag', function(event, id) {
							var oldValue = (typeof id != 'undefined' && (id.length > 0 || id > 0));

							var checkAutocomplete = oldValue == true || options.autocompleteOptions.noCheck ? false : true;
							// check if the Value ist new
							var isNewResult = isNew($(this).val(), checkAutocomplete);
							if(isNewResult[0] === true || (isNewResult[0] === false && typeof isNewResult[1] == 'string')) {

								if(oldValue == false && typeof isNewResult[1] == 'string') {
									oldValue = true;
									id = isNewResult[1];
								}

								if(options.allowAdd == true || oldValue) {
									var domId = 'tagedit-' + baseName + '-' + id;
									// Make a new tag in front the input
									html = '<li class="tagedit-listelement tagedit-listelement-old" aria-labelledby="'+domId+'">';
									html += '<span dir="'+options.direction+'" id="'+domId+'">' + $(this).val() + '</span>';
									var name = oldValue? baseName + '['+id+options.addedPostfix+']' : baseName + '[]';
									html += '<input type="hidden" name="'+name+'" value="'+$(this).val()+'" />';
									html += '<a class="tagedit-close" title="'+options.texts.removeLinkTitle+'" aria-label="'+options.texts.removeLinkTitle+' '+$(this).val()+'">x</a>';
									html += '</li>';

									$(this).parent().before(html);
								}
							}
							$(this).val('');

							// close autocomplete
							if(options.autocompleteOptions.source) {
								if($(this).is(':ui-autocomplete'))
									$(this).autocomplete( "close" );
							}

						})
						.keydown(function(event) {
							var code = event.keyCode > 0? event.keyCode : event.which;

							switch(code) {
								case 46:
									if (!focusItem)
										break;
								case 8: // BACKSPACE
									if(focusItem) {
										focusItem.fadeOut(options.animSpeed, function() {
											$(this).remove();
										})
										unfocusItem();
										event.preventDefault();
										return false;
									}
									else if($(this).val().length == 0) {
										// delete Last Tag
										var elementToRemove = elements.find('li.tagedit-listelement-old').last();
										elementToRemove.fadeOut(options.animSpeed, function() {elementToRemove.remove();})
										event.preventDefault();
										return false;
									}
									break;
								case 9: // TAB
									if($(this).val().length > 0 && $('ul.ui-autocomplete #ui-active-menuitem').length == 0) {
										$(this).trigger('transformToTag');
										event.preventDefault();
										return false;
									}
									break;
								case 37: // LEFT
								case 39: // RIGHT
									if($(this).val().length == 0) {
										// select previous Tag
										var inc = code == 37 ? -1 : 1,
											items = elements.find('li.tagedit-listelement-old')
											x = items.length, next = 0;
										items.each(function(i, elem) {
											if ($(elem).hasClass('tagedit-listelement-focus')) {
												x = i;
												return true;
											}
										});
										unfocusItem();
										next = Math.max(0, x + inc);
										if (items.get(next)) {
											focusItem = items.eq(next).addClass('tagedit-listelement-focus');
											$(this).attr('aria-activedescendant', focusItem.attr('aria-labelledby'))

											if(options.autocompleteOptions.source != false) {
												$(this).autocomplete('close').autocomplete('disable');
											}
										}
										event.preventDefault();
										return false;
									}
									break;
								default:
									// ignore input if an item is focused
									if (focusItem !== null) {
										event.preventDefault();
										event.bubble = false;
										return false;
									}
							}
							return true;
						})
						.keypress(function(event) {
							var code = event.keyCode > 0? event.keyCode : event.which;
							if($.inArray(code, options.breakKeyCodes) > -1) {
								if($(this).val().length > 0 && $('ul.ui-autocomplete #ui-active-menuitem').length == 0) {
									$(this).trigger('transformToTag');
								}
								event.preventDefault();
								return false;
							}
							else if($(this).val().length > 0){
								unfocusItem();
							}
							return true;
						})
						.bind('paste', function(e){
							var that = $(this);
							if (e.type == 'paste'){
								setTimeout(function(){
									that.trigger('transformToTag');
								}, 1);
							}
						})
						.blur(function() {
							if($(this).val().length == 0) {
								// disable the field to prevent sending with the form
								$(this).attr('disabled', 'disabled').addClass('tagedit-input-disabled');
							}
							else {
								// Delete entry after a timeout
								var input = $(this);
								$(this).data('blurtimer', window.setTimeout(function() {input.val('');}, 500));
							}
							unfocusItem();
							// restore tabindex when widget looses focus
							if (options.tabindex)
								elements.attr('tabindex', options.tabindex);
						})
						.focus(function() {
							window.clearTimeout($(this).data('blurtimer'));
							// remove tabindex on <ul> because #tagedit-input now has it
							elements.attr('tabindex', '-1');
						});

						if(options.autocompleteOptions.source != false) {
							$(this).autocomplete(options.autocompleteOptions);
						}
					})
				.end()
				.click(function(event) {
					switch(event.target.tagName) {
						case 'A':
							$(event.target).parent().fadeOut(options.animSpeed, function() {
								$(event.target).parent().remove();
								elements.find('#tagedit-input').focus();
								});
							break;
						case 'INPUT':
						case 'SPAN':
						case 'LI':
							if($(event.target).hasClass('tagedit-listelement-deleted') == false &&
							$(event.target).parent('li').hasClass('tagedit-listelement-deleted') == false) {
								// Don't edit an deleted Items
								return doEdit(event);
							}
						default:
							$(this).find('#tagedit-input')
								.removeAttr('disabled')
								.removeClass('tagedit-input-disabled')
								.focus();
					}
					return false;
				})
				// forward focus event (on tabbing through the form)
				.focus(function(e){ $(this).click(); })
		}

		/**
		 * Remove class and reference to currently focused tag item
		 */
		function unfocusItem() {
			if(focusItem){
				if(options.autocompleteOptions.source != false) {
					elements.find('#tagedit-input').autocomplete('enable');
				}
				focusItem.removeClass('tagedit-listelement-focus');
				focusItem = null;
				elements.find('#tagedit-input').removeAttr('aria-activedescendant');
			}
		}

		/**
		* Sets all Actions and events for editing an Existing Tag.
		*
		* @param event {object} The original Event that was given
		* return {boolean}
		*/
		function doEdit(event) {
			if(options.allowEdit == false) {
				// Do nothing
				return;
			}

			var element = event.target.tagName == 'SPAN'? $(event.target).parent() : $(event.target);

			var closeTimer = null;

			// Event that is fired if the User finishes the edit of a tag
			element.bind('finishEdit', function(event, doReset) {
				window.clearTimeout(closeTimer);

				var textfield = $(this).find(':text');
				var isNewResult = isNew(textfield.val(), true);
				if(textfield.val().length > 0 && (typeof doReset == 'undefined' || doReset === false) && (isNewResult[0] == true)) {
					// This is a new Value and we do not want to do a reset. Set the new value
					$(this).find(':hidden').val(textfield.val());
					$(this).find('span').html(textfield.val());
				}

				textfield.remove();
				$(this).find('a.tagedit-save, a.tagedit-break, a.tagedit-delete').remove(); // Workaround. This normaly has to be done by autogrow Plugin
				$(this).removeClass('tagedit-listelement-edit').unbind('finishEdit');
				return false;
			});

			var hidden = element.find(':hidden');
			html = '<input type="text" name="tmpinput" autocomplete="off" value="'+hidden.val()+'" class="tagedit-edit-input" dir="'+options.direction+'"/>';
			html += '<a class="tagedit-save" title="'+options.texts.saveEditLinkTitle+'">o</a>';
			html += '<a class="tagedit-break" title="'+options.texts.breakEditLinkTitle+'">x</a>';

			// If the Element is one from the Database, it can be deleted
			if(options.allowDelete == true && element.find(':hidden').length > 0 &&
			typeof element.find(':hidden').attr('name').match(baseNameRegexp)[3] != 'undefined') {
				html += '<a class="tagedit-delete" title="'+options.texts.deleteLinkTitle+'">d</a>';
			}

			hidden.after(html);
			element
				.addClass('tagedit-listelement-edit')
				.find('a.tagedit-save')
					.click(function() {
						$(this).parent().trigger('finishEdit');
						return false;
					})
				.end()
				.find('a.tagedit-break')
					.click(function() {
						$(this).parent().trigger('finishEdit', [true]);
						return false;
					})
				.end()
				.find('a.tagedit-delete')
					.click(function() {
                        window.clearTimeout(closeTimer);
						if(confirm(options.texts.deleteConfirmation)) {
                            var canDelete = checkToDelete($(this).parent());
                            if (!canDelete && confirm(options.texts.forceDeleteConfirmation)) {
                                markAsDeleted($(this).parent());
                            }

                            if(canDelete) {
                                markAsDeleted($(this).parent());
                            }

                            $(this).parent().find(':text').trigger('finishEdit', [true]);
						}
                        else {
                            $(this).parent().find(':text').trigger('finishEdit', [true]);
                        }
						return false;
					})
				.end()
				.find(':text')
					.focus()
					.autoGrowInput({comfortZone: 10, minWidth: 15, maxWidth: 20000})
					.keypress(function(event) {
						switch(event.keyCode) {
							case 13: // RETURN
								event.preventDefault();
								$(this).parent().trigger('finishEdit');
								return false;
							case 27: // ESC
								event.preventDefault();
								$(this).parent().trigger('finishEdit', [true]);
								return false;
						}
						return true;
					})
					.blur(function() {
						var that = $(this);
						closeTimer = window.setTimeout(function() {that.parent().trigger('finishEdit', [true])}, 500);
					});
		}

        /**
         * Verifies if the tag select to be deleted is used by other records using an Ajax request.
         *
         * @param element
         * @returns {boolean}
         */
        function checkToDelete(element) {
            // if no URL is provide will not verify
            if(options.checkToDeleteURL === null) {
                return false;
            }

            var inputName = element.find('input:hidden').attr('name');
            var idPattern = new RegExp('\\d');
            var tagId = inputName.match(idPattern);
            var checkResult = false;

            $.ajax({
                async   : false,
                url     : options.checkToDeleteURL,
                dataType: 'json',
                type    : 'POST',
                data    : { 'tagId' : tagId},
                complete: function (XMLHttpRequest, textStatus) {

                    // Expected JSON Object: { "success": Boolean, "allowDelete": Boolean}
                    var result = $.parseJSON(XMLHttpRequest.responseText);
                    if(result.success === true){
                        checkResult = result.allowDelete;
                    }
                }
            });

            return checkResult;
        }

		/**
		* Marks a single Tag as deleted.
		*
		* @param element {object}
		*/
		function markAsDeleted(element) {
			element
				.trigger('finishEdit', [true])
				.addClass('tagedit-listelement-deleted')
				.attr('title', options.deletedElementTitle);
				element.find(':hidden').each(function() {
					var nameEndRegexp = new RegExp('('+options.addedPostfix+'|'+options.deletedPostfix+')?\]');
					var name = $(this).attr('name').replace(nameEndRegexp, options.deletedPostfix+']');
					$(this).attr('name', name);
				});

		}

		/**
		* Checks if a tag is already choosen.
		*
		* @param value {string}
		* @param checkAutocomplete {boolean} optional Check also the autocomplet values
		* @returns {Array} First item is a boolean, telling if the item should be put to the list, second is optional the ID from autocomplete list
		*/
		function isNew(value, checkAutocomplete) {
            checkAutocomplete = typeof checkAutocomplete == 'undefined'? false : checkAutocomplete;
			var autoCompleteId = null;
            
            var compareValue = options.checkNewEntriesCaseSensitive == true? value : value.toLowerCase();

			var isNew = true;
			elements.find('li.tagedit-listelement-old input:hidden').each(function() {
                var elementValue = options.checkNewEntriesCaseSensitive == true? $(this).val() : $(this).val().toLowerCase();
				if(elementValue == compareValue) {
					isNew = false;
				}
			});

			if (isNew == true && checkAutocomplete == true && options.autocompleteOptions.source != false) {
				var result = [];
				if ($.isArray(options.autocompleteOptions.source)) {
					result = options.autocompleteOptions.source;
				}
                else if ($.isFunction(options.autocompleteOptions.source)) {
					options.autocompleteOptions.source({term: value}, function (data) {result = data});
				}
                else if (typeof options.autocompleteOptions.source === "string") {
					// Check also autocomplete values
					var autocompleteURL = options.autocompleteOptions.source;
					if (autocompleteURL.match(/\?/)) {
						autocompleteURL += '&';
					} else {
						autocompleteURL += '?';
					}
					autocompleteURL += 'term=' + value;
					$.ajax({
						async: false,
						url: autocompleteURL,
						dataType: 'json',
						complete: function (XMLHttpRequest, textStatus) {
							result = $.parseJSON(XMLHttpRequest.responseText);
						}
					});
				}
                
				// If there is an entry for that already in the autocomplete, don't use it (Check could be case sensitive or not)
				for (var i = 0; i < result.length; i++) {
					var resultValue = result[i].label? result[i].label : result[i];
					var label = options.checkNewEntriesCaseSensitive == true? resultValue : resultValue.toLowerCase();
					if (label == compareValue) {
						isNew = false;
						autoCompleteId = typeof result[i] == 'string' ? i : result[i].id;
						break;
					}
				}
			}

			return new Array(isNew, autoCompleteId);
		}
	}
})(jQuery);

(function($){
    
// jQuery autoGrowInput plugin by James Padolsey
// See related thread: http://stackoverflow.com/questions/931207/is-there-a-jquery-autogrow-plugin-for-text-fields
        
$.fn.autoGrowInput = function(o) {
    
    o = $.extend({
        maxWidth: 1000,
        minWidth: 0,
        comfortZone: 70
    }, o);
    
    this.filter('input:text').each(function(){
        
        var minWidth = o.minWidth || $(this).width(),
            val = '',
            input = $(this),
            testSubject = $('<tester/>').css({
                position: 'absolute',
                top: -9999,
                left: -9999,
                width: 'auto',
                fontSize: input.css('fontSize'),
                fontFamily: input.css('fontFamily'),
                fontWeight: input.css('fontWeight'),
                letterSpacing: input.css('letterSpacing'),
                whiteSpace: 'nowrap'
            }),
            check = function() {
                
                if (val === (val = input.val())) {return;}
                
                // Enter new content into testSubject
                var escaped = val.replace(/&/g, '&amp;').replace(/\s/g,'&nbsp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                testSubject.html(escaped);
                
                // Calculate new width + whether to change
                var testerWidth = testSubject.width(),
                    newWidth = (testerWidth + o.comfortZone) >= minWidth ? testerWidth + o.comfortZone : minWidth,
                    currentWidth = input.width(),
                    isValidWidthChange = (newWidth < currentWidth && newWidth >= minWidth)
                                         || (newWidth > minWidth && newWidth < o.maxWidth);
                
                // Animate width
                if (isValidWidthChange) {
                    input.width(newWidth);
                }
                
            };
            
        testSubject.insertAfter(input);
        
        $(this).bind('keyup keydown blur update', check);
        
        check();
    });
    
    return this;

};

})(jQuery);