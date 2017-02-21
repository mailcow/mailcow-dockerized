/**
 * Roundcube editor js library
 *
 * This file is part of the Roundcube Webmail client
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2006-2014, The Roundcube Dev Team
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
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 *
 * @author Eric Stadtherr <estadtherr@gmail.com>
 * @author Aleksander Machniak <alec@alec.pl>
 */

/**
 * Roundcube Text Editor Widget class
 * @constructor
 */
function rcube_text_editor(config, id)
{
  var ref = this,
    abs_url = location.href.replace(/[?#].*$/, '').replace(/\/$/, ''),
    conf = {
      selector: '#' + ($('#' + id).is('.mce_editor') ? id : 'fake-editor-id'),
      cache_suffix: 's=4050100',
      theme: 'modern',
      language: config.lang,
      content_css: rcmail.assets_path('program/resources/tinymce/content.css'),
      menubar: false,
      statusbar: false,
      toolbar_items_size: 'small',
      extended_valid_elements: 'font[face|size|color|style],span[id|class|align|style]',
      relative_urls: false,
      remove_script_host: false,
      convert_urls: false, // #1486944
      image_description: false,
      paste_webkit_style: "color font-size font-family",
      paste_data_images: true,
      browser_spellcheck: true
    };

  // register spellchecker for plain text editor
  this.spellcheck_observer = function() {};
  if (config.spellchecker) {
    this.spellchecker = config.spellchecker;
    if (config.spellcheck_observer) {
      this.spellchecker.spelling_state_observer = this.spellcheck_observer = config.spellcheck_observer;
    }
  }

  // secure spellchecker requests with Roundcube token
  // Note: must be registered only once (#1490311)
  if (!tinymce.registered_request_token) {
    tinymce.registered_request_token = true;
    tinymce.util.XHR.on('beforeSend', function(e) {
      e.xhr.setRequestHeader('X-Roundcube-Request', rcmail.env.request_token);
    });
  }

  // minimal editor
  if (config.mode == 'identity') {
    $.extend(conf, {
      plugins: 'autolink charmap code colorpicker hr image link paste tabfocus textcolor',
      toolbar: 'bold italic underline alignleft aligncenter alignright alignjustify'
        + ' | outdent indent charmap hr link unlink image code forecolor'
        + ' | fontselect fontsizeselect',
      file_browser_callback: function(name, url, type, win) { ref.file_browser_callback(name, url, type); },
      file_browser_callback_types: 'image'
    });
  }
  // full-featured editor
  else {
    $.extend(conf, {
      plugins: 'autolink charmap code colorpicker directionality link image media nonbreaking'
        + ' paste table tabfocus textcolor searchreplace spellchecker',
      toolbar: 'bold italic underline | alignleft aligncenter alignright alignjustify'
        + ' | bullist numlist outdent indent ltr rtl blockquote | forecolor backcolor | fontselect fontsizeselect'
        + ' | link unlink table | $extra charmap image media | code searchreplace undo redo',
      spellchecker_rpc_url: abs_url + '/?_task=utils&_action=spell_html&_remote=1',
      spellchecker_language: rcmail.env.spell_lang,
      accessibility_focus: false,
      file_browser_callback: function(name, url, type, win) { ref.file_browser_callback(name, url, type); },
      // @todo: support more than image (types: file, image, media)
      file_browser_callback_types: 'image media'
    });
  }

  // add TinyMCE plugins/buttons from Roundcube plugin
  $.each(config.extra_plugins || [], function() {
    if (conf.plugins.indexOf(this) < 0)
      conf.plugins = conf.plugins + ' ' + this;
  });
  $.each(config.extra_buttons || [], function() {
    if (conf.toolbar.indexOf(this) < 0)
      conf.toolbar = conf.toolbar.replace('$extra', '$extra ' + this);
  });

  // disable TinyMCE plugins/buttons from Roundcube plugin
  $.each(config.disabled_plugins || [], function() {
    conf.plugins = conf.plugins.replace(this, '');
  });
  $.each(config.disabled_buttons || [], function() {
    conf.toolbar = conf.toolbar.replace(this, '');
  });

  conf.toolbar = conf.toolbar.replace('$extra', '').replace(/\|\s+\|/g, '|');

  // support external configuration settings e.g. from skin
  if (window.rcmail_editor_settings)
    $.extend(conf, window.rcmail_editor_settings);

  conf.setup = function(ed) {
    ed.on('init', function(ed) { ref.init_callback(ed); });
    // add handler for spellcheck button state update
    ed.on('SpellcheckStart SpellcheckEnd', function(args) {
      ref.spellcheck_active = args.type == 'spellcheckstart';
      ref.spellcheck_observer();
    });
    ed.on('keypress', function() {
      rcmail.compose_type_activity++;
    });
  };

  // textarea identifier
  this.id = id;
  // reference to active editor (if in HTML mode)
  this.editor = null;

  tinymce.init(conf);

  // react to real individual tinyMCE editor init
  this.init_callback = function(event)
  {
    this.editor = event.target;

    if (rcmail.env.action != 'compose') {
      return;
    }

    var area = $('#' + this.id),
      height = $('div.mce-toolbar-grp:first', area.parent()).height();

    // the editor might be still not fully loaded, making the editing area
    // inaccessible, wait and try again (#1490310)
    if (height > 200 || height > area.height()) {
      return setTimeout(function () { ref.init_callback(event); }, 300);
    }

    var css = {},
      elem = rcube_find_object('_from'),
      fe = rcmail.env.compose_focus_elem;

    if (rcmail.env.default_font)
      css['font-family'] = rcmail.env.default_font;

    if (rcmail.env.default_font_size)
      css['font-size'] = rcmail.env.default_font_size;

    if (css['font-family'] || css['font-size'])
      $(this.editor.getBody()).css(css);

    if (elem && elem.type == 'select-one') {
      // insert signature (only for the first time)
      if (!rcmail.env.identities_initialized)
        rcmail.change_identity(elem);

      // Focus previously focused element
      if (fe && fe.id != this.id) {
        window.focus(); // for WebKit (#1486674)
        fe.focus();
        rcmail.env.compose_focus_elem = null;
      }
    }

    // set tabIndex and set focus to element that was focused before
    ref.tabindex(fe && fe.id == ref.id);
    // Trigger resize (needed for proper editor resizing in some browsers)
    $(window).resize();
  };

  // set tabIndex on tinymce editor
  this.tabindex = function(focus)
  {
    if (rcmail.env.task == 'mail' && this.editor) {
      var textarea = this.editor.getElement(),
        body = this.editor.getBody(),
        node = this.editor.getContentAreaContainer().childNodes[0];

      if (textarea && node)
        node.tabIndex = textarea.tabIndex;

      // find :prev and :next elements to get focus when tabbing away
      if (textarea.tabIndex > 0) {
        var x = null,
          tabfocus_elements = [':prev',':next'],
          el = tinymce.DOM.select('*[tabindex='+textarea.tabIndex+']:not(iframe)');

        tinymce.each(el, function(e, i) { if (e.id == ref.id) { x = i; return false; } });
        if (x !== null) {
          if (el[x-1] && el[x-1].id) {
            tabfocus_elements[0] = el[x-1].id;
          }
          if (el[x+1] && el[x+1].id) {
            tabfocus_elements[1] = el[x+1].id;
          }
          this.editor.settings.tabfocus_elements = tabfocus_elements.join(',');
        }
      }

      // ContentEditable reset fixes invisible cursor issue in Firefox < 25
      if (bw.mz && bw.vendver < 25)
        $(body).prop('contenteditable', false).prop('contenteditable', true);

      if (focus)
        body.focus();
    }
  };

  // switch html/plain mode
  this.toggle = function(ishtml, noconvert)
  {
    var curr, content, result,
      // these non-printable chars are not removed on text2html and html2text
      // we can use them as temp signature replacement
      sig_mark = "\u0002\u0003",
      input = $('#' + this.id),
      signature = rcmail.env.identity ? rcmail.env.signatures[rcmail.env.identity] : null,
      is_sig = signature && signature.text && signature.text.length > 1;

    // apply spellcheck changes if spell checker is active
    this.spellcheck_stop();

    if (ishtml) {
      content = input.val();

      // replace current text signature with temp mark
      if (is_sig) {
        content = content.replace(/\r\n/, "\n");
        content = content.replace(signature.text.replace(/\r\n/, "\n"), sig_mark);
      }

      var init_editor = function(data) {
        // replace signature mark with html version of the signature
        if (is_sig)
          data = data.replace(sig_mark, '<div id="_rc_sig">' + signature.html + '</div>');

        input.val(data);
        tinymce.execCommand('mceAddEditor', false, ref.id);

        if (ref.editor) {
          var body = $(ref.editor.getBody());
          // #1486593
          ref.tabindex(true);
          // put cursor on start of the compose body
          ref.editor.selection.setCursorLocation(body.children().first().get(0));
        }
      };

      // convert to html
      if (!noconvert) {
        result = rcmail.plain2html(content, init_editor);
      }
      else {
        init_editor(content);
        result = true;
      }
    }
    else if (this.editor) {
      if (is_sig) {
        // get current version of signature, we'll need it in
        // case of html2text conversion abort
        if (curr = this.editor.dom.get('_rc_sig'))
          curr = curr.innerHTML;

        // replace current signature with some non-printable characters
        // we use non-printable characters, because this replacement
        // is visible to the user
        // doing this after getContent() would be hard
        this.editor.dom.setHTML('_rc_sig', sig_mark);
      }

      // get html content
      content = this.editor.getContent();

      var init_plaintext = function(data) {
        tinymce.execCommand('mceRemoveEditor', false, ref.id);
        ref.editor = null;

        // replace signture mark with text version of the signature
        if (is_sig)
          data = data.replace(sig_mark, "\n" + signature.text);

        input.val(data).focus();
        rcmail.set_caret_pos(input.get(0), 0);
      };

      // convert html to text
      if (!noconvert) {
        result = rcmail.html2plain(content, init_plaintext);
      }
      else {
        init_plaintext(input.val());
        result = true;
      }

      // bring back current signature
      if (!result && curr)
        this.editor.dom.setHTML('_rc_sig', curr);
    }

    return result;
  };

  // start spellchecker
  this.spellcheck_start = function()
  {
    if (this.editor) {
      tinymce.execCommand('mceSpellCheck', true);
      this.spellcheck_observer();
    }
    else if (this.spellchecker && this.spellchecker.spellCheck) {
      this.spellchecker.spellCheck();
    }
  };

  // stop spellchecker
  this.spellcheck_stop = function()
  {
    var ed = this.editor;

    if (ed) {
      if (ed.plugins && ed.plugins.spellchecker && this.spellcheck_active) {
        ed.execCommand('mceSpellCheck', false);
        this.spellcheck_observer();
      }
    }
    else if (ed = this.spellchecker) {
      if (ed.state && ed.state != 'ready' && ed.state != 'no_error_found')
        $(ed.spell_span).trigger('click');
    }
  };

  // spellchecker state
  this.spellcheck_state = function()
  {
    var ed;

    if (this.editor)
      return this.spellcheck_active;
    else if ((ed = this.spellchecker) && ed.state)
      return ed.state != 'ready' && ed.state != 'no_error_found';
  };

  // resume spellchecking, highlight provided mispellings without a new ajax request
  this.spellcheck_resume = function(data)
  {
    var ed = this.editor;

    if (ed) {
      ed.plugins.spellchecker.markErrors(data);
    }
    else if (ed = this.spellchecker) {
      ed.prepare(false, true);
      ed.processData(data);
    }
  };

  // get selected (spellcheker) language
  this.get_language = function()
  {
    if (this.editor) {
      return this.editor.settings.spellchecker_language || rcmail.env.spell_lang;
    }
    else if (this.spellchecker) {
      return GOOGIE_CUR_LANG;
    }
  };

  // set language for spellchecking
  this.set_language = function(lang)
  {
    var ed = this.editor;

    if (ed) {
      ed.settings.spellchecker_language = lang;
    }
    if (ed = this.spellchecker) {
      ed.setCurrentLanguage(lang);
    }
  };

  // replace selection with text snippet
  // input can be a string or object with 'text' and 'html' properties
  this.replace = function(input)
  {
    var format, ed = this.editor;

    if (!input)
      return false;

    // insert into tinymce editor
    if (ed) {
      ed.getWin().focus(); // correct focus in IE & Chrome

      if ($.type(input) == 'object' && ('html' in input)) {
        input = input.html;
        format = 'html';
      }
      else {
        if ($.type(input) == 'object')
          input = input.text || '';

        input = rcmail.quote_html(input).replace(/\r?\n/g, '<br/>');
        format = 'text';
      }

      ed.selection.setContent(input, {format: format});
    }
    // replace selection in compose textarea
    else if (ed = rcube_find_object(this.id)) {
      var selection = $(ed).is(':focus') ? rcmail.get_input_selection(ed) : {start: 0, end: 0},
        value = ed.value;
        pre = value.substring(0, selection.start),
        end = value.substring(selection.end, value.length);

      if ($.type(input) == 'object')
        input = input.text || '';

      // insert response text
      ed.value = pre + input + end;

      // set caret after inserted text
      rcmail.set_caret_pos(ed, selection.start + input.length);
      ed.focus();
    }
  };

  // get selected text (if no selection returns all text) from the editor
  this.get_content = function(args)
  {
    var sigstart, ed = this.editor, text = '', strip = false,
      defaults = {refresh: true, selection: false, nosig: false, format: 'html'};

    args = $.extend(defaults, args);

    // apply spellcheck changes if spell checker is active
    if (args.refresh) {
      this.spellcheck_stop();
    }

    // get selected text from tinymce editor
    if (ed) {
      if (args.selection)
        text = ed.selection.getContent({format: args.format});

      if (!text) {
        text = ed.getContent({format: args.format});
        // @todo: strip signature in html mode
        strip = args.format == 'text';
      }
    }
    // get selected text from compose textarea
    else if (ed = rcube_find_object(this.id)) {
      if (args.selection && $(ed).is(':focus')) {
        text = rcmail.get_input_selection(ed).text;
      }

      if (!text) {
        text = ed.value;
        strip = true;
      }
    }

    // strip off signature
    // @todo: make this optional
    if (strip && args.nosig) {
      sigstart = text.indexOf('-- \n');
      if (sigstart > 0) {
        text = text.substring(0, sigstart);
      }
    }

    return text;
  };

  // change user signature text
  this.change_signature = function(id, show_sig)
  {
    var position_element, cursor_pos, p = -1,
      input_message = $('#' + this.id),
      message = input_message.val(),
      sig = rcmail.env.identity;

    if (!this.editor) { // plain text mode
      // remove the 'old' signature
      if (show_sig && sig && rcmail.env.signatures && rcmail.env.signatures[sig]) {
        sig = rcmail.env.signatures[sig].text;
        sig = sig.replace(/\r\n/g, '\n');

        p = rcmail.env.top_posting ? message.indexOf(sig) : message.lastIndexOf(sig);
        if (p >= 0)
          message = message.substring(0, p) + message.substring(p+sig.length, message.length);
      }

      // add the new signature string
      if (show_sig && rcmail.env.signatures && rcmail.env.signatures[id]) {
        sig = rcmail.env.signatures[id].text;
        sig = sig.replace(/\r\n/g, '\n');

        // in place of removed signature
        if (p >= 0) {
          message = message.substring(0, p) + sig + message.substring(p, message.length);
          cursor_pos = p - 1;
        }
        // empty message or new-message mode
        else if (!message || !rcmail.env.compose_mode) {
          cursor_pos = message.length;
          message += '\n\n' + sig;
        }
        else if (rcmail.env.top_posting && !rcmail.env.sig_below) {
          // at cursor position
          if (pos = rcmail.get_caret_pos(input_message.get(0))) {
            message = message.substring(0, pos) + '\n' + sig + '\n\n' + message.substring(pos, message.length);
            cursor_pos = pos;
          }
          // on top
          else {
            message = '\n\n' + sig + '\n\n' + message.replace(/^[\r\n]+/, '');
            cursor_pos = 0;
          }
        }
        else {
          message = message.replace(/[\r\n]+$/, '');
          cursor_pos = !rcmail.env.top_posting && message.length ? message.length + 1 : 0;
          message += '\n\n' + sig;
        }
      }
      else {
        cursor_pos = rcmail.env.top_posting ? 0 : message.length;
      }

      input_message.val(message);

      // move cursor before the signature
      rcmail.set_caret_pos(input_message.get(0), cursor_pos);
    }
    else if (show_sig && rcmail.env.signatures) {  // html
      var sigElem = this.editor.dom.get('_rc_sig');

      // Append the signature as a div within the body
      if (!sigElem) {
        var body = this.editor.getBody();

        sigElem = $('<div id="_rc_sig"></div>').get(0);

        // insert at start or at cursor position in top-posting mode
        // (but not if the content is empty and not in new-message mode)
        if (rcmail.env.top_posting && !rcmail.env.sig_below
          && rcmail.env.compose_mode && (body.childNodes.length > 1 || $(body).text())
        ) {
          this.editor.getWin().focus(); // correct focus in IE & Chrome

          var node = this.editor.selection.getNode();

          $(sigElem).insertBefore(node.nodeName == 'BODY' ? body.firstChild : node.nextSibling);
          $('<p>').append($('<br>')).insertBefore(sigElem);
        }
        else {
          body.appendChild(sigElem);
          position_element = rcmail.env.top_posting && rcmail.env.compose_mode ? body.firstChild : $(sigElem).prev();
        }
      }

      sigElem.innerHTML = rcmail.env.signatures[id] ? rcmail.env.signatures[id].html : '';
    }
    else if (!rcmail.env.top_posting) {
      position_element = $(this.editor.getBody()).children().last();
    }

    // put cursor before signature and scroll the window
    if (this.editor && position_element && position_element.length) {
      this.editor.selection.setCursorLocation(position_element.get(0));
      this.editor.getWin().scroll(0, position_element.offset().top);
    }
  };

  // trigger content save
  this.save = function()
  {
    if (this.editor) {
      this.editor.save();
    }
  };

  // focus the editing area
  this.focus = function()
  {
    (this.editor || rcube_find_object(this.id)).focus();
  };

  // image selector
  this.file_browser_callback = function(field_name, url, type)
  {
    var i, elem, cancel, dialog, fn, list = [];

    // open image selector dialog
    dialog = this.editor.windowManager.open({
      title: rcmail.get_label('select' + type),
      width: 500,
      height: 300,
      html: '<div id="image-selector-list"><ul></ul></div>'
        + '<div id="image-selector-form"><div id="image-upload-button" class="mce-widget mce-btn" role="button" tabindex="0"></div></div>',
      buttons: [{text: 'Cancel', onclick: function() { ref.file_browser_close(); }}]
    });

    rcmail.env.file_browser_field = field_name;
    rcmail.env.file_browser_type = type;

    // fill images list with available images
    for (i in rcmail.env.attachments) {
      if (elem = ref.file_browser_entry(i, rcmail.env.attachments[i])) {
        list.push(elem);
      }
    }

    if (list.length) {
      $('#image-selector-list > ul').append(list).find('li:first').focus();
    }

    // add hint about max file size (in dialog footer)
    $('div.mce-abs-end', dialog.getEl()).append($('<div class="hint">')
      .text($('div.hint', rcmail.gui_objects.uploadform).text()));

    // init upload button
    elem = $('#image-upload-button').append($('<span>').text(rcmail.get_label('add' + type)));
    cancel = elem.parents('.mce-panel').find('button:last').parent();

    // we need custom Tab key handlers, until we find out why
    // tabindex do not work here as expected
    elem.keydown(function(e) {
      if (e.which == 9) {
        // on Tab + Shift focus first file
        if (rcube_event.get_modifier(e) == SHIFT_KEY)
          $('#image-selector-list li:last').focus();
        // on Tab focus Cancel button
        else
          cancel.focus();

        return false;
      }
    });
    cancel.keydown(function(e) {
      if (e.which == 9) {
        // on Tab + Shift focus upload button
        if (rcube_event.get_modifier(e) == SHIFT_KEY)
          elem.focus();
        else
          $('#image-selector-list li:first').focus();

        return false;
      }
    });

    // enable (smart) upload button
    this.hack_file_input(elem, rcmail.gui_objects.uploadform);

    // enable drag-n-drop area
    if ((window.XMLHttpRequest && XMLHttpRequest.prototype && XMLHttpRequest.prototype.sendAsBinary) || window.FormData) {
      if (!rcmail.env.filedrop) {
        rcmail.env.filedrop = {};
      }
      if (rcmail.gui_objects.filedrop) {
        rcmail.env.old_file_drop = rcmail.gui_objects.filedrop;
      }

      rcmail.gui_objects.filedrop = $('#image-selector-form');
      rcmail.gui_objects.filedrop.addClass('droptarget')
        .on('dragover dragleave', function(e) {
          e.preventDefault();
          e.stopPropagation();
          $(this)[(e.type == 'dragover' ? 'addClass' : 'removeClass')]('hover');
        })
        .get(0).addEventListener('drop', function(e) { return rcmail.file_dropped(e); }, false);
    }

    // register handler for successful file upload
    if (!rcmail.env.file_dialog_event) {
      rcmail.env.file_dialog_event = true;
      rcmail.addEventListener('fileuploaded', function(attr) {
        var elem;
        if (elem = ref.file_browser_entry(attr.name, attr.attachment)) {
          $('#image-selector-list > ul').prepend(elem);
          elem.focus();
        }
      });
    }

    // @todo: upload progress indicator
  };

  // close file browser window
  this.file_browser_close = function(url)
  {
    var input = $('#' + rcmail.env.file_browser_field);

    if (url)
      input.val(url);

    this.editor.windowManager.close();

    input.focus();

    if (rcmail.env.old_file_drop)
      rcmail.gui_objects.filedrop = rcmail.env.old_file_drop;
  };

  // creates file browser entry
  this.file_browser_entry = function(file_id, file)
  {
    if (!file.complete || !file.mimetype) {
      return;
    }

    if (rcmail.file_upload_id) {
      rcmail.set_busy(false, null, rcmail.file_upload_id);
    }

    var rx, img_src;

    switch (rcmail.env.file_browser_type) {
      case 'image':
        rx = /^image\//i;
        break;

      case 'media':
        rx = /^video\//i;
        img_src = 'program/resources/tinymce/video.png';
        break;

      default:
        return;
    }

    if (rx.test(file.mimetype)) {
      var path = rcmail.env.comm_path + '&_from=' + rcmail.env.action,
        action = rcmail.env.compose_id ? '&_id=' + rcmail.env.compose_id + '&_action=display-attachment' : '&_action=upload-display',
        href = path + action + '&_file=' + file_id,
        img = $('<img>').attr({title: file.name, src: img_src ? img_src : href + '&_thumbnail=1'});

      return $('<li>').attr({tabindex: 0})
        .data('url', href)
        .append($('<span class="img">').append(img))
        .append($('<span class="name">').text(file.name))
        .click(function() { ref.file_browser_close($(this).data('url')); })
        .keydown(function(e) {
          if (e.which == 13) {
            ref.file_browser_close($(this).data('url'));
          }
          // we need custom Tab key handlers, until we find out why
          // tabindex do not work here as expected
          else if (e.which == 9) {
            if (rcube_event.get_modifier(e) == SHIFT_KEY) {
              if (!$(this).prev().focus().length)
                $('#image-upload-button').parents('.mce-panel').find('button:last').parent().focus();
            }
            else {
              if (!$(this).next().focus().length)
                $('#image-upload-button').focus();
            }

            return false;
          }
        });
    }
  };

  // create smart files upload button
  this.hack_file_input = function(elem, clone_form)
  {
    var offset, link = $(elem),
      file = $('<input>').attr('name', '_file[]'),
      form = $('<form>').attr({method: 'post', enctype: 'multipart/form-data'});

    // clone existing upload form
    if (clone_form) {
      file.attr('name', $('input[type="file"]', clone_form).attr('name'));
      form.attr('action', $(clone_form).attr('action'))
        .append($('<input>').attr({type: 'hidden', name: '_token', value: rcmail.env.request_token}));
    }

    function move_file_input(e) {
      if (!offset) offset = link.offset();
      file.css({top: (e.pageY - offset.top - 10) + 'px', left: (e.pageX - offset.left - 10) + 'px'});
    }

    file.attr({type: 'file', multiple: 'multiple', size: 5, title: '', tabindex: -1})
      .change(function() { rcmail.upload_file(form, 'upload'); })
      .click(function() { setTimeout(function() { link.mouseleave(); }, 20); })
      // opacity:0 does the trick, display/visibility doesn't work
      .css({opacity: 0, cursor: 'pointer', position: 'relative', outline: 'none'})
      .appendTo(form);

    // In FF and IE we need to move the browser file-input's button under the cursor
    // Thanks to the size attribute above we know the length of the input field
    if (navigator.userAgent.match(/Firefox|MSIE/))
      file.css({marginLeft: '-80px'});

    // Note: now, I observe problem with cursor style on FF < 4 only
    link.css({overflow: 'hidden', cursor: 'pointer'})
      .mouseenter(function() { this.__active = true; })
      // place button under the cursor
      .mousemove(function(e) {
        if (this.__active)
          move_file_input(e);
        // move the input away if button is disabled
        else
          $(this).mouseleave();
      })
      .mouseleave(function() {
        file.css({top: '-10000px', left: '-10000px'});
        this.__active = false;
      })
      .click(function(e) {
        // forward click if mouse-enter event was missed
        if (!this.__active) {
          this.__active = true;
          move_file_input(e);
          file.trigger(e);
        }
      })
      .keydown(function(e) {
        if (e.which == 13) file.trigger('click');
      })
      .mouseleave()
      .append(form);
  };
}
