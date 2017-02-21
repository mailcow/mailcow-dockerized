/**
 * Roundcube functions for default skin interface
 *
 * Copyright (c) 2013, The Roundcube Dev Team
 *
 * The contents are subject to the Creative Commons Attribution-ShareAlike
 * License. It is allowed to copy, distribute, transmit and to adapt the work
 * by keeping credits to the original autors in the README file.
 * See http://creativecommons.org/licenses/by-sa/3.0/ for details.
 *
 * @license magnet:?xt=urn:btih:90dc5c0be029de84e523b9b3922520e79e0e6f08&dn=cc0.txt CC0-1.0
 */

function rcube_mail_ui()
{
  var env = {};
  var popups = {};
  var popupconfig = {
    forwardmenu:        { editable:1 },
    searchmenu:         { editable:1, callback:searchmenu },
    attachmentmenu:     { },
    listoptions:        { editable:1 },
    groupmenu:          { above:1 },
    mailboxmenu:        { above:1 },
    spellmenu:          { callback: spellmenu },
    'folder-selector':  { iconized:1 }
  };

  var me = this;
  var mailviewsplit;
  var mailviewsplit2;
  var compose_headers = {};
  var prefs;

  // export public methods
  this.set = setenv;
  this.init = init;
  this.init_tabs = init_tabs;
  this.show_about = show_about;
  this.show_popup = show_popup;
  this.toggle_popup = toggle_popup;
  this.add_popup = add_popup;
  this.set_searchmod = set_searchmod;
  this.set_searchscope = set_searchscope;
  this.show_uploadform = show_uploadform;
  this.show_header_row = show_header_row;
  this.hide_header_row = hide_header_row;
  this.update_quota = update_quota;
  this.get_pref = get_pref;
  this.save_pref = save_pref;
  this.folder_search_init = folder_search_init;


  // set minimal mode on small screens (don't wait for document.ready)
  if (window.$ && document.body) {
    var minmode = get_pref('minimalmode');
    if (parseInt(minmode) || (minmode === null && $(window).height() < 850)) {
      $(document.body).addClass('minimal');
    }

    if (bw.tablet) {
      $('#viewport').attr('content', "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0");
    }

    $(document).ready(function() { me.init(); });
  }


  /**
   *
   */
  function setenv(key, val)
  {
    env[key] = val;
  }

  /**
   * Get preference stored in browser
   */
  function get_pref(key)
  {
    if (!prefs) {
      prefs = rcmail.local_storage_get_item('prefs.larry', {});
    }

    // fall-back to cookies
    if (prefs[key] == null) {
      var cookie = rcmail.get_cookie(key);
      if (cookie != null) {
        prefs[key] = cookie;

        // copy value to local storage and remove cookie (if localStorage is supported)
        if (rcmail.local_storage_set_item('prefs.larry', prefs)) {
          rcmail.set_cookie(key, cookie, new Date());  // expire cookie
        }
      }
    }

    return prefs[key];
  }

  /**
   * Saves preference value to browser storage
   */
  function save_pref(key, val)
  {
    prefs[key] = val;

    // write prefs to local storage (if supported)
    if (!rcmail.local_storage_set_item('prefs.larry', prefs)) {
      // store value in cookie
      var exp = new Date();
      exp.setYear(exp.getFullYear() + 1);
      rcmail.set_cookie(key, val, exp);
    }
  }

  /**
   * Initialize UI
   * Called on document.ready
   */
  function init()
  {
    rcmail.addEventListener('message', message_displayed);

    /*** prepare minmode functions ***/
    $('#taskbar a').each(function(i,elem){
      $(elem).append('<span class="tooltip">' + $('.button-inner', this).html() + '</span>')
    });

    $('#taskbar .minmodetoggle').click(function(e){
      var ismin = $(document.body).toggleClass('minimal').hasClass('minimal');
      save_pref('minimalmode', ismin?1:0);
      $(window).resize();
    });

    /***  mail task  ***/
    if (rcmail.env.task == 'mail') {
      rcmail.addEventListener('menu-open', menu_toggle)
        .addEventListener('menu-close', menu_toggle)
        .addEventListener('menu-save', save_listoptions)
        .addEventListener('enable-command', enable_command)
        .addEventListener('responseafterlist', function(e){ switch_view_mode(rcmail.env.threading ? 'thread' : 'list', true) })
        .addEventListener('responseaftersearch', function(e){ switch_view_mode(rcmail.env.threading ? 'thread' : 'list', true) });

      var dragmenu = $('#dragmessagemenu');
      if (dragmenu.length) {
        rcmail.gui_object('dragmenu', 'dragmessagemenu');
        popups.dragmenu = dragmenu;
      }

      if (rcmail.env.action == 'show' || rcmail.env.action == 'preview') {
        rcmail.addEventListener('aftershow-headers', function() { layout_messageview(); })
          .addEventListener('afterhide-headers', function() { layout_messageview(); });

        $('#previewheaderstoggle').click(function(e) {
            toggle_preview_headers();
            if (this.blur && !rcube_event.is_keyboard(e))
                this.blur();
            return false;
        });

        // add menu link for each attachment
        $('#attachment-list > li').each(function() {
          attachmentmenu_append(this);
        });

        if (get_pref('previewheaders') == '1') {
          toggle_preview_headers();
        }

        if (rcmail.env.action == 'show') {
          $('#messagecontent').focus();
        }
      }
      else if (rcmail.env.action == 'compose') {
        rcmail.addEventListener('aftersend-attachment', show_uploadform)
          .addEventListener('fileappended', function(e) { if (e.attachment.complete) attachmentmenu_append(e.item); })
          .addEventListener('aftertoggle-editor', function(e) {
            window.setTimeout(function() { layout_composeview() }, 200);
            if (e && e.mode)
              $("select[name='editorSelector']").val(e.mode);
          })
          .addEventListener('compose-encrypted', function(e) {
            $("select[name='editorSelector']").prop('disabled', e.active);
            $('a.button.attach, a.button.responses')[(e.active?'addClass':'removeClass')]('disabled');
            $('#responseslist a.insertresponse')[(e.active?'removeClass':'addClass')]('active');
          });

        // Show input elements with non-empty value
        var f, v, field, fields = ['cc', 'bcc', 'replyto', 'followupto'];
        for (f=0; f < fields.length; f++) {
          v = fields[f]; field = $('#_'+v);
          if (field.length) {
            field.on('change', {v: v}, function(e) { if (this.value) show_header_row(e.data.v, true); });
            if (field.val() != '')
              show_header_row(v, true);
          }
        }

        $('#composeoptionstoggle').click(function(e){
          var expanded = $('#composeoptions').toggle().is(':visible');
          $('#composeoptionstoggle').toggleClass('remove').attr('aria-expanded', expanded ? 'true' : 'false');
          layout_composeview();
          save_pref('composeoptions', expanded ? '1' : '0');
          if (!rcube_event.is_keyboard(e))
            this.blur();
          return false;
        }).css('cursor', 'pointer');

        if (get_pref('composeoptions') !== '0') {
          $('#composeoptionstoggle').click();
        }

        // adjust hight when textarea starts to scroll
        $("textarea[name='_to'], textarea[name='_cc'], textarea[name='_bcc']").change(function(e){ adjust_compose_editfields(this); }).change();
        rcmail.addEventListener('autocomplete_insert', function(p){ adjust_compose_editfields(p.field); });

        // toggle compose options if opened in new window and they were visible before
        var opener_rc = rcmail.opener();
        if (opener_rc && opener_rc.env.action == 'compose' && $('#composeoptionstoggle', opener.document).hasClass('remove'))
          $('#composeoptionstoggle').click();

        new rcube_splitter({ id:'composesplitterv', p1:'#composeview-left', p2:'#composeview-right',
          orientation:'v', relative:true, start:206, min:170, size:12, render:layout_composeview }).init();

        // add menu link for each attachment
        $('#attachment-list > li').each(function() {
          attachmentmenu_append(this);
        });
      }
      else if (rcmail.env.action == 'list' || !rcmail.env.action) {
        mail_layout();

        $('#maillistmode').addClass(rcmail.env.threading ? '' : 'selected').click(function(e) { switch_view_mode('list'); return false; });
        $('#mailthreadmode').addClass(rcmail.env.threading ? 'selected' : '').click(function(e) { switch_view_mode('thread'); return false; });

        rcmail.init_pagejumper('#pagejumper');

        rcmail.addEventListener('setquota', update_quota)
          .addEventListener('layout-change', mail_layout)
          .addEventListener('afterimport-messages', show_uploadform);
      }
      else if (rcmail.env.action == 'get') {
        new rcube_splitter({ id:'mailpartsplitterv', p1:'#messagepartheader', p2:'#messagepartcontainer',
          orientation:'v', relative:true, start:226, min:150, size:12}).init();
      }

      if ($('#mailview-left').length) {
        new rcube_splitter({ id:'mailviewsplitterv', p1:'#mailview-left', p2:'#mailview-right',
          orientation:'v', relative:true, start:206, min:150, size:12, callback:render_mailboxlist, render:resize_leftcol }).init();
      }
    }
    /***  settings task  ***/
    else if (rcmail.env.task == 'settings') {
      rcmail.addEventListener('init', function(){
        var tab = '#settingstabpreferences';
        if (rcmail.env.action)
          tab = '#settingstab' + (rcmail.env.action.indexOf('identity')>0 ? 'identities' : rcmail.env.action.replace(/\./g, ''));

        $(tab).addClass('selected')
          .children().first().removeAttr('onclick').click(function() { return false; });
      });

      if (rcmail.env.action == 'folders') {
        new rcube_splitter({ id:'folderviewsplitter', p1:'#folderslist', p2:'#folder-details',
          orientation:'v', relative:true, start:266, min:180, size:12 }).init();

        rcmail.addEventListener('setquota', update_quota);

        folder_search_init($('#folderslist'));
      }
      else if (rcmail.env.action == 'identities') {
        new rcube_splitter({ id:'identviewsplitter', p1:'#identitieslist', p2:'#identity-details',
          orientation:'v', relative:true, start:266, min:180, size:12 }).init();
      }
      else if (rcmail.env.action == 'responses') {
        new rcube_splitter({ id:'responseviewsplitter', p1:'#identitieslist', p2:'#identity-details',
          orientation:'v', relative:true, start:266, min:180, size:12 }).init();
      }
      else if (rcmail.env.action == 'preferences' || !rcmail.env.action) {
        new rcube_splitter({ id:'prefviewsplitter', p1:'#sectionslist', p2:'#preferences-box',
          orientation:'v', relative:true, start:266, min:180, size:12 }).init();
      }
      else if (rcmail.env.action == 'edit-prefs') {
        var legend = $('#preferences-details fieldset.advanced legend'),
          toggle = $('<a href="#toggle"></a>')
            .text(rcmail.gettext('toggleadvancedoptions'))
            .attr('title', rcmail.gettext('toggleadvancedoptions'))
            .addClass('advanced-toggle');

        legend.click(function(e) {
          toggle.html($(this).hasClass('collapsed') ? '&#9650;' : '&#9660;');

          $(this).toggleClass('collapsed')
            .closest('fieldset').children('.propform').toggle()
        }).append(toggle).addClass('collapsed')

        // this magically fixes incorrect position of toggle link created above in Firefox 3.6
        if (bw.mz)
          legend.parents('form').css('display', 'inline');
      }
    }
    /***  addressbook task  ***/
    else if (rcmail.env.task == 'addressbook') {
      rcmail.addEventListener('afterupload-photo', show_uploadform)
        .addEventListener('beforepushgroup', push_contactgroup)
        .addEventListener('beforepopgroup', pop_contactgroup)
        .addEventListener('menu-open', menu_toggle)
        .addEventListener('menu-close', menu_toggle);

      if (rcmail.env.action == '') {
        new rcube_splitter({ id:'addressviewsplitterd', p1:'#addressview-left', p2:'#addressview-right',
          orientation:'v', relative:true, start:206, min:150, size:12, render:resize_leftcol }).init();
        new rcube_splitter({ id:'addressviewsplitter', p1:'#addresslist', p2:'#contacts-box',
          orientation:'v', relative:true, start:266, min:260, size:12 }).init();
      }

      var dragmenu = $('#dragcontactmenu');
      if (dragmenu.length) {
        rcmail.gui_object('dragmenu', 'dragcontactmenu');
        popups.dragmenu = dragmenu;
      }
    }

    // turn a group of fieldsets into tabs
    $('.tabbed').each(function(idx, elem){ init_tabs(elem); })

    // decorate select elements
    $('select.decorated').each(function(){
      if (bw.opera) {
        $(this).removeClass('decorated');
        return;
      }

      var select = $(this),
        parent = select.parent(),
        height = Math.max(select.height(), 26) - 2,
        width = select.width() - 22,
        title = $('option', this).first().text();

      if ($('option:selected', this).val() != '')
        title = $('option:selected', this).text();

      var overlay = $('<a class="menuselector" tabindex="-1"><span class="handle">' + title + '</span></a>')
        .css('position', 'absolute')
        .offset(select.position())
        .insertAfter(select);

      overlay.children().width(width).height(height).css('line-height', (height - 1) + 'px');

      if (parent.css('position') != 'absolute')
        parent.css('position', 'relative');

      // re-set original select width to fix click action and options width in some browsers
      select.width(overlay.width())
        .on(bw.mz ? 'change keyup' : 'change', function() {
          var val = $('option:selected', this).text();
          $(this).next().children().text(val);
        });

      select
        .on('focus', function(e){ overlay.addClass('focus'); })
        .on('blur', function(e){ overlay.removeClass('focus'); });
    });

    // set min-width to show all toolbar buttons
    var screen = $('body.minwidth');
    if (screen.length) {
      screen.css('min-width', $('.toolbar').width() + $('#quicksearchbar').width() + $('#searchfilter').width() + 30);
    }

    // don't use $(window).resize() due to some unwanted side-effects
    window.onresize = resize;
    resize();
  }

  /**
   * Update UI on window resize
   */
  function resize(e)
  {
    // resize in intervals to prevent lags and double onresize calls in Chrome (#1489005)
    var interval = e ? 10 : 0;

    if (rcmail.resize_timeout)
      window.clearTimeout(rcmail.resize_timeout);

    rcmail.resize_timeout = window.setTimeout(function() {
      if (rcmail.env.task == 'mail') {
        if (rcmail.env.action == 'show' || rcmail.env.action == 'preview')
          layout_messageview();
        else if (rcmail.env.action == 'compose')
          layout_composeview();
      }

      // make iframe footer buttons float if scrolling is active
      $('body.iframe .footerleft').each(function(){
        var footer = $(this),
          body = $(document.body),
          floating = footer.hasClass('floating'),
          overflow = body.outerHeight(true) > $(window).height();

        if (overflow != floating) {
          var action = overflow ? 'addClass' : 'removeClass';
          footer[action]('floating');
          body[action]('floatingbuttons');
        }
      });
    }, interval);
  }

  /**
   * Triggered when a new user message is displayed
   */
  function message_displayed(p)
  {
    var siblings = $(p.object).siblings('div');
    if (siblings.length)
      $(p.object).insertBefore(siblings.first());

    // show a popup dialog on errors
    if (p.type == 'error' && rcmail.env.task != 'login') {
      // hide original message object, we don't want both
      rcmail.hide_message(p.object);

      if (me.message_timer) {
        window.clearTimeout(me.message_timer);
      }

      if (!me.messagedialog) {
        me.messagedialog = $('<div>').addClass('popupdialog').hide();
      }

      var msg = p.message,
        dialog_close = function() {
          // check if dialog is still displayed, to prevent from js error
          me.messagedialog.is(':visible') && me.messagedialog.dialog('destroy').hide();
        };

      if (me.messagedialog.is(':visible') && me.messagedialog.text() != msg)
        msg = me.messagedialog.html() + '<p>' + p.message + '</p>';

      me.messagedialog.html(msg)
        .dialog({
          resizable: false,
          closeOnEscape: true,
          dialogClass: p.type,
          title: rcmail.gettext('errortitle'),
          close: dialog_close,
          hide: {effect: 'fadeOut'},
          width: 420,
          minHeight: 90
        }).show();

      me.messagedialog.closest('div[role=dialog]').attr('role', 'alertdialog');

      if (p.timeout > 0)
        me.message_timer = window.setTimeout(dialog_close, p.timeout);
    }
  }

  // Mail view layout initialization and change handler
  function mail_layout(p)
  {
    var layout = p ? p.new_layout : rcmail.env.layout,
      top = $('#mailview-top'),
      bottom = $('#mailview-bottom');

    if (p)
      $('#mainscreencontent').removeClass().addClass(layout);

    $('#mailviewsplitter')[layout == 'desktop' ? 'show' : 'hide']();
    $('#mailviewsplitter2')[layout == 'widescreen' ? 'show' : 'hide']();
    $('#mailpreviewframe')[layout != 'list' ? 'show' : 'hide']();
    rcmail.env.contentframe = layout == 'list' ? null : 'messagecontframe';

    if (layout == 'widescreen') {
      $('#countcontrols').detach().appendTo($('#messagelistheader'));
      top.css({height: 'auto', width: 394});
      bottom.css({top: 0, left: 406, height: 'auto'}).show();
      if (!mailviewsplit2) {
        mailviewsplit2 = new rcube_splitter({ id:'mailviewsplitter2', p1:'#mailview-top', p2:'#mailview-bottom',
          orientation:'v', relative:true, start:416, min:400, size:12});
        mailviewsplit2.init();
      }
      else
        mailviewsplit2.resize();
    }
    else if (layout == 'desktop') {
      top.css({height: 270, width: 'auto'});
      bottom.css({left: 0, top: 284, height: 'auto'}).show();
      if (!mailviewsplit) {
        mailviewsplit = new rcube_splitter({ id:'mailviewsplitter', p1:'#mailview-top', p2:'#mailview-bottom',
          orientation:'h', relative:true, start:276, min:150, size:12, offset:4 });
        mailviewsplit.init();
      }
      else
        mailviewsplit.resize();
    }
    else { // layout == 'list'
      top.css({height: 'auto', width: 'auto'});
      bottom.hide();
    }

    if (p && p.old_layout == 'widescreen') {
      $('#countcontrols').detach().appendTo($('#messagelistfooter'));
    }
  }

  /**
   * Adjust UI objects of the mail view screen
   */
  function layout_messageview()
  {
    $('#messagecontent').css('top', ($('#messageheader').outerHeight() + 1) + 'px');
    $('#message-objects div a').addClass('button');

    if (!$('#attachment-list li').length) {
      $('div.rightcol').hide().attr('aria-hidden', 'true');
      $('div.leftcol').css('margin-right', '0');
    }

    var mvlpe = $('#messagebody.mailvelope, #messagebody > .mailvelope');
    if (mvlpe.length) {
      var h = $('#messagecontent').length ?
        $('#messagecontent').height() - 16 :
        $(window).height() - mvlpe.offset().top - 2;
      mvlpe.height(h);
    }
  }


  function render_mailboxlist(splitter)
  {
    // TODO: implement smart shortening of long folder names
  }


  function resize_leftcol(splitter)
  {
    // STUB
  }

  function adjust_compose_editfields(elem)
  {
    if (elem.nodeName == 'TEXTAREA') {
      var $elem = $(elem), line_height = 14,  // hard-coded because some browsers only provide the outer height in elem.clientHeight
        content_height = elem.scrollHeight,
        rows = elem.value.length > 80 && content_height > line_height*1.5 ? 2 : 1;
      $elem.css('height', (line_height*rows) + 'px');
      layout_composeview();
    }
  }

  function layout_composeview()
  {
    var body = $('#composebody'),
      form = $('#compose-content'),
      bottom = $('#composeview-bottom'),
      w, h, bh, ovflw, btns = 0,
      minheight = 300,

    bh = form.height() - bottom.position().top;
    ovflw = minheight - bh;
    btns = ovflw > -100 ? 0 : 40;
    bottom.height(Math.max(minheight, bh));
    form.css('overflow', ovflw > 0 ? 'auto' : 'hidden');

    w = body.parent().width() - 5;
    h = body.parent().height() - 8;
    body.width(w).height(h);

    $('#composebodycontainer > div').width(w+8);
    $('#composebody_ifr').height(h + 4 - $('div.mce-toolbar').height());
    $('#googie_edit_layer').width(w).height(h);
//    $('#composebodycontainer')[(btns ? 'addClass' : 'removeClass')]('buttons');
//    $('#composeformbuttons')[(btns ? 'show' : 'hide')]();

    var abooks = $('#directorylist');
    if (abooks.length)
      $('#compose-contacts .scroller').css('top', abooks.position().top + abooks.outerHeight());
  }


  function update_quota(p)
  {
    var element = $('#quotadisplay'), menu = $('#quotamenu'),
      step = 24, step_count = 20,
      y = p.total ? Math.ceil(p.percent / 100 * step_count) * step : 0;

    // never show full-circle if quota is close to 100% but below.
    if (p.total && y == step * step_count && p.percent < 100)
      y -= step;

    element.css('background-position', '0 -' + y + 'px');
    element.attr('class', 'countdisplay p' + (Math.round(p.percent / 10) * 10));

    if (p.table) {
      if (!menu.length)
        menu = $('<div id="quotamenu" class="popupmenu">').appendTo($('body'));

      menu.html(p.table);
      element.css('cursor', 'pointer').off('click').on('click', function(e) {
        return rcmail.command('menu-open', 'quotamenu', e.target, e);
      });
    }
  }

  function folder_search_init(container)
  {
    // animation to unfold list search box
    $('.boxtitle a.search', container).click(function(e) {
      var title = $('.boxtitle', container),
        box = $('.listsearchbox', container),
        dir = box.is(':visible') ? -1 : 1,
        height = 34 + ($('select', box).length ? 22 : 0);

      box.slideToggle({
        duration: 160,
        progress: function(animation, progress) {
          if (dir < 0) progress = 1 - progress;
            $('.scroller', container).css('top', (title.outerHeight() + height * progress) + 'px');
        },
        complete: function() {
          box.toggleClass('expanded');
          if (box.is(':visible')) {
            box.find('input[type=text]').focus();
            height = 34 + ($('select', box).length ? $('select', box).outerHeight() + 4 : 0);
            $('.scroller', container).css('top', (title.outerHeight() + height) + 'px');
          }
          else {
            $('a.reset', box).click();
          }
          // TODO: save state in localStorage
        }
      });

      return false;
    });
  }

  function enable_command(p)
  {
    if (p.command == 'reply-list' && rcmail.env.reply_all_mode == 1) {
      var label = rcmail.gettext(p.status ? 'replylist' : 'replyall');
      if (rcmail.env.action == 'preview')
        $('a.button.replyall').attr('title', label);
      else
        $('a.button.reply-all').text(label).attr('title', label);
    }
    else if (p.command == 'compose-encrypted') {
      // show the toolbar button for Mailvelope
      $('a.button.encrypt').show();
    }
  }


  /**
   * Register a popup menu
   */
  function add_popup(popup, config)
  {
    var obj = popups[popup] = $('#'+popup);
    obj.appendTo(document.body);  // move it to top for proper absolute positioning

    if (obj.length)
      popupconfig[popup] = $.extend(popupconfig[popup] || {}, config || {});
  }

  /**
   * Trigger for popup menus
   */
  function toggle_popup(popup, e, config)
  {
    // auto-register menu object
    if (config || !popupconfig[popup])
      add_popup(popup, config);

    return rcmail.command('menu-open', popup, e.target, e);
  }

  /**
   * (Deprecated) trigger for popup menus
   */
  function show_popup(popup, show, config)
  {
    // auto-register menu object
    if (config || !popupconfig[popup])
      add_popup(popup, config);

    config = popupconfig[popup] || {};
    var ref = $(config.link ? config.link : '#'+popup+'link'),
      pos = ref.offset();
    if (ref.has('.inner'))
      ref = ref.children('.inner');

    // fire command with simulated mouse click event
    return rcmail.command('menu-open',
      { menu:popup, show:show },
      ref.get(0),
      $.Event('click', { target:ref.get(0), pageX:pos.left, pageY:pos.top, clientX:pos.left, clientY:pos.top }));
  }

  /**
   * Switch between short and full headers display in message preview
   */
  function toggle_preview_headers()
  {
    $('#preview-shortheaders').toggle();
    var full = $('#preview-allheaders').toggle(),
      button = $('a#previewheaderstoggle');

    // add toggle button to full headers table
    if (full.is(':visible'))
      button.attr('href', '#hide').removeClass('add').addClass('remove').attr('aria-expanded', 'true');
    else
      button.attr('href', '#details').removeClass('remove').addClass('add').attr('aria-expanded', 'false');

    save_pref('previewheaders', full.is(':visible') ? '1' : '0');
  }


  /**
   *
   */
  function switch_view_mode(mode, force)
  {
    if (force || !$('#mail'+mode+'mode').hasClass('disabled')) {
      $('#maillistmode, #mailthreadmode').removeClass('selected').attr('tabindex', '0').attr('aria-disabled', 'false');
      $('#mail'+mode+'mode').addClass('selected').attr('tabindex', '-1').attr('aria-disabled', 'true');
    }
  }


  /**** popup menu callbacks ****/

  /**
   * Handler for menu-open and menu-close events
   */
  function menu_toggle(p)
  {
    if (p && p.name == 'messagelistmenu') {
      show_listoptions(p);
    }
    else if (p) {
      // adjust menu position according to config
      var config = popupconfig[p.name] || {},
        ref = $(config.link || '#'+p.name+'link'),
        visible = p.obj && p.obj.is(':visible'),
        above = config.above;

      // fix position according to config
      if (p.obj && visible && ref.length) {
        var parent = ref.parent(),
          win = $(window), pos;

        if (parent.hasClass('dropbutton'))
          ref = parent;

        if (config.above || ref.hasClass('dropbutton')) {
          pos = ref.offset();
          p.obj.css({ left:pos.left+'px', top:(pos.top + (config.above ? -p.obj.height() : ref.outerHeight()))+'px' });
        }
      }

      // add the right classes
      if (p.obj && config.iconized) {
        p.obj.children('ul').addClass('iconized');
      }

      // apply some data-attributes from menu config
      if (p.obj && config.editable)
        p.obj.attr('data-editable', 'true');

      // trigger callback function
      if (typeof config.callback == 'function') {
        config.callback(visible, p);
      }
    }
  }

  function searchmenu(show)
  {
    if (show && rcmail.env.search_mods) {
      var n, all,
        obj = popups['searchmenu'],
        list = $('input:checkbox[name="s_mods[]"]', obj),
        mbox = rcmail.env.mailbox,
        mods = rcmail.env.search_mods,
        scope = rcmail.env.search_scope || 'base';

      if (rcmail.env.task == 'mail') {
        if (scope == 'all')
          mbox = '*';
        mods = mods[mbox] ? mods[mbox] : mods['*'];
        all = 'text';
        $('input:radio[name="s_scope"]').prop('checked', false).filter('#s_scope_'+scope).prop('checked', true);
      }
      else {
        all = '*';
      }

      if (mods[all])
        list.map(function() {
          this.checked = true;
          this.disabled = this.value != all;
        });
      else {
        list.prop('disabled', false).prop('checked', false);
        for (n in mods)
          $('#s_mod_' + n).prop('checked', true);
      }
    }
  }

  function attachmentmenu(elem, event)
  {
    var id = elem.parentNode.id.replace(/^attach/, '');

    $.each(['open', 'download', 'rename'], function() {
      var action = this;
      $('#attachmenu' + action).off('click').attr('onclick', '').click(function(e) {
        return rcmail.command(action + '-attachment', id, this);
      });
    });

    popupconfig.attachmentmenu.link = elem;
    rcmail.command('menu-open', {menu: 'attachmentmenu', id: id}, elem, event);
  }

  function spellmenu(show, p)
  {
    var k, link, li,
      lang = rcmail.spellcheck_lang(),
      ul = $('ul', p.obj);

    if (!ul.length) {
      ul = $('<ul class="toolbarmenu selectable" role="menu">');

      for (k in rcmail.env.spell_langs) {
        li = $('<li role="menuitem">');
        link = $('<a href="#'+k+'" tabindex="0"></a>').text(rcmail.env.spell_langs[k])
          .addClass('active').data('lang', k)
          .on('click keypress', function(e) {
              if (e.type != 'keypress' || rcube_event.get_keycode(e) == 13) {
                  rcmail.spellcheck_lang_set($(this).data('lang'));
                  rcmail.hide_menu('spellmenu', e);
                  return false;
              }
          });

        link.appendTo(li);
        li.appendTo(ul);
      }

      ul.appendTo(p.obj);
    }

    // select current language
    $('li', ul).each(function() {
      var el = $('a', this);
      if (el.data('lang') == lang)
        el.addClass('selected').attr('aria-selected', 'true');
      else if (el.hasClass('selected'))
        el.removeClass('selected').removeAttr('aria-selected');
    });
  }

  // append drop-icon to attachments list item (to invoke attachment menu)
  function attachmentmenu_append(item)
  {
    item = $(item);

    if (!item.children('.drop').length)
      item.append($('<a class="drop skip-content" tabindex="0" aria-haspopup="true">Show options</a>')
          .on('click keypress', function(e) {
            if (e.type != 'keypress' || rcube_event.get_keycode(e) == 13) {
              attachmentmenu(this, e);
              return false;
            }
          }));
  }

  /**
   *
   */
  function show_listoptions(p)
  {
    var $dialog = $('#listoptions');

    // close the dialog
    if ($dialog.is(':visible')) {
      $dialog.dialog('close', p.originalEvent);
      return;
    }

    // set form values
    $('input[name="sort_col"][value="'+rcmail.env.sort_col+'"]').prop('checked', true);
    $('input[name="sort_ord"][value="DESC"]').prop('checked', rcmail.env.sort_order == 'DESC');
    $('input[name="sort_ord"][value="ASC"]').prop('checked', rcmail.env.sort_order != 'DESC');

    $.each(['widescreen', 'desktop', 'list'], function() {
      $('input[name="layout"][value="' + this + '"]').prop('checked', rcmail.env.layout == this);
    });
    $('#listoptions-columns', $dialog)[rcmail.env.layout == 'widescreen' ? 'hide' : 'show']();

    // set checkboxes
    $('input[name="list_col[]"]').each(function() {
      $(this).prop('checked', $.inArray(this.value, rcmail.env.listcols) != -1);
    });

    $dialog.dialog({
      modal: true,
      resizable: false,
      closeOnEscape: true,
      title: null,
      open: function(e) {
        setTimeout(function(){ $dialog.find('a, input:not(:disabled)').not('[aria-disabled=true]').first().focus(); }, 100);
      },
      close: function(e) {
        $dialog.dialog('destroy').hide();
        if (e.originalEvent && rcube_event.is_keyboard(e.originalEvent))
          $('#listmenulink').focus();
      },
      minWidth: 500,
      width: $dialog.width()+25
    }).show();
  }


  /**
   *
   */
  function save_listoptions(p)
  {
    $('#listoptions').dialog('close');

    if (rcube_event.is_keyboard(p.originalEvent))
      $('#listmenulink').focus();

    var sort = $('input[name="sort_col"]:checked').val(),
      ord = $('input[name="sort_ord"]:checked').val(),
      layout = $('input[name="layout"]:checked').val(),
      cols = $('input[name="list_col[]"]:checked')
        .map(function(){ return this.value; }).get();

    rcmail.set_list_options(cols, sort, ord, rcmail.env.threading, layout);
  }


  /**
   *
   */
  function set_searchmod(elem)
  {
    var all, m, task = rcmail.env.task,
      mods = rcmail.env.search_mods,
      mbox = rcmail.env.mailbox,
      scope = $('input[name="s_scope"]:checked').val();

    if (scope == 'all')
      mbox = '*';

    if (!mods)
      mods = {};

    if (task == 'mail') {
      if (!mods[mbox])
        mods[mbox] = rcube_clone_object(mods['*']);
      m = mods[mbox];
      all = 'text';
    }
    else { //addressbook
      m = mods;
      all = '*';
    }

    if (!elem.checked)
      delete(m[elem.value]);
    else
      m[elem.value] = 1;

    // mark all fields
    if (elem.value == all) {
      $('input:checkbox[name="s_mods[]"]').map(function() {
        if (this == elem)
          return;

        this.checked = true;
        if (elem.checked) {
          this.disabled = true;
          delete m[this.value];
        }
        else {
          this.disabled = false;
          m[this.value] = 1;
        }
      });
    }

    rcmail.set_searchmods(m);
  }

  function set_searchscope(elem)
  {
    rcmail.set_searchscope(elem.value);
  }

  function push_contactgroup(p)
  {
    // lets the contacts list swipe to the left, nice!
    var table = $('#contacts-table'),
      scroller = table.parent().css('overflow', 'hidden');

    table.clone()
      .css({ position:'absolute', top:'0', left:'0', width:table.width()+'px', 'z-index':10 })
      .appendTo(scroller)
      .animate({ left: -(table.width()+5) + 'px' }, 300, 'swing', function(){
        $(this).remove();
        scroller.css('overflow', 'auto')
      });
  }

  function pop_contactgroup(p)
  {
    // lets the contacts list swipe to the left, nice!
    var table = $('#contacts-table'),
      scroller = table.parent().css('overflow', 'hidden'),
      clone = table.clone().appendTo(scroller);

      table.css({ position:'absolute', top:'0', left:-(table.width()+5) + 'px', width:table.width()+'px', height:table.height()+'px', 'z-index':10 })
        .animate({ left:'0' }, 300, 'linear', function(){
        clone.remove();
        $(this).css({ position:'relative', left:'0', width:'100%', height:'auto', 'z-index':1 });
        scroller.css('overflow', 'auto')
      });
  }

  function show_uploadform(e)
  {
    var $dialog = $('#upload-dialog');

    // close the dialog
    if ($dialog.is(':visible')) {
      $dialog.dialog('close');
      return;
    }

    // do nothing if mailvelope editor is active
    if (rcmail.mailvelope_editor)
      return;

    // add icons to clone file input field
    if (rcmail.env.action == 'compose' && !$dialog.data('extended')) {
      $('<a>')
        .addClass('iconlink add')
        .attr('href', '#add')
        .html('Add')
        .appendTo($('input[type="file"]', $dialog).parent())
        .click(add_uploadfile);
      $dialog.data('extended', true);
    }

    $dialog.dialog({
      modal: true,
      resizable: false,
      closeOnEscape: true,
      title: $dialog.attr('title'),
      open: function(e) {
        if (!document.all)
          $('input[type=file]', $dialog).first().click();
      },
      close: function() {
        try { $('#upload-dialog form').get(0).reset(); }
        catch(e){ }  // ignore errors

        $dialog.dialog('destroy').hide();
        $('div.addline', $dialog).remove();
      },
      width: 480
    }).show();
  }

  function add_uploadfile(e)
  {
    var div = $(this).parent();
    var clone = div.clone().addClass('addline').insertAfter(div);
    clone.children('.iconlink').click(add_uploadfile);
    clone.children('input').val('');

    if (!document.all)
      $('input[type=file]', clone).click();
  }


  /**
   *
   */
  function show_header_row(which, updated)
  {
    var row = $('#compose-' + which);
    if (row.is(':visible'))
      return;  // nothing to be done here

    if (compose_headers[which] && !updated)
      $('#_' + which).val(compose_headers[which]);

    row.show();
    $('#' + which + '-link').hide();

    layout_composeview();
    $('input,textarea', row).focus();

    return false;
  }

  /**
   *
   */
  function hide_header_row(which)
  {
    // copy and clear field value
    var field = $('#_' + which);
    compose_headers[which] = field.val();
    field.val('');

    $('#compose-' + which).hide();
    $('#' + which + '-link').show();
    layout_composeview();
    return false;
  }


  /**
   * Fieldsets-to-tabs converter
   */
  function init_tabs(elem, current)
  {
    var content = $(elem),
      id = content.get(0).id,
      fs = content.children('fieldset');

    if (!fs.length)
      return;

    if (!id) {
      id = 'rcmtabcontainer';
      content.attr('id', id);
    }

    // create tabs container
    var tabs = $('<ul>').addClass('tabsbar').prependTo(content);

    // convert fildsets into tabs
    fs.each(function(idx) {
      var tab, a, elm = $(this),
        legend = elm.children('legend'),
        tid = id + '-t' + idx;

      // create a tab
      a   = $('<a>').text(legend.text()).attr('href', '#' + tid);
      tab = $('<li>').addClass('tablink');

      // remove legend
      legend.remove();

      // link fieldset with tab item
      elm.attr('id', tid);

      // add the tab to container
      tab.append(a).appendTo(tabs);
    });

    // use jquery UI tabs widget to do the interaction and styling
    content.tabs({
      active: current || 0,
      heightStyle: 'content',
      activate: function(e, ui) {resize(); }
    });
  }

  /**
   * Show about page as jquery UI dialog
   */
  function show_about(elem)
  {
    var frame = $('<iframe>').attr({id: 'aboutframe', src: rcmail.url('settings/about'), frameborder: '0'});
      h = Math.floor($(window).height() * 0.75),
      buttons = {},
      supportln = $('#supportlink');

    if (supportln.length && (env.supporturl = supportln.attr('href')))
      buttons[supportln.html()] = function(e){ env.supporturl.indexOf('mailto:') < 0 ? window.open(env.supporturl) : location.href = env.supporturl };

    frame.dialog({
      modal: true,
      resizable: false,
      closeOnEscape: true,
      title: elem ? elem.title || elem.innerHTML : null,
      close: function() {
        frame.dialog('destroy').remove();
      },
      buttons: buttons,
      width: 640,
      height: h
    }).width(640);
  }
}


/**
 * Roundcube Scroller class
 *
 * @deprecated Use treelist widget
 */
function rcube_scroller(list, top, bottom)
{
  var ref = this;

  this.list = $(list);
  this.top = $(top);
  this.bottom = $(bottom);
  this.step_size = 6;
  this.step_time = 20;
  this.delay = 500;

  this.top
    .mouseenter(function() { if (rcmail.drag_active) ref.ts = window.setTimeout(function() { ref.scroll('down'); }, ref.delay); })
    .mouseout(function() { if (ref.ts) window.clearTimeout(ref.ts); });

  this.bottom
    .mouseenter(function() { if (rcmail.drag_active) ref.ts = window.setTimeout(function() { ref.scroll('up'); }, ref.delay); })
    .mouseout(function() { if (ref.ts) window.clearTimeout(ref.ts); });

  this.scroll = function(dir)
  {
    var ref = this, size = this.step_size;

    if (!rcmail.drag_active)
      return;

    if (dir == 'down')
      size *= -1;

    this.list.get(0).scrollTop += size;
    this.ts = window.setTimeout(function() { ref.scroll(dir); }, this.step_time);
  };
};


/**
 * Roundcube UI splitter class
 *
 * @constructor
 */
function rcube_splitter(p)
{
  this.p = p;
  this.id = p.id;
  this.horizontal = (p.orientation == 'horizontal' || p.orientation == 'h');
  this.halfsize = (p.size !== undefined ? p.size : 10) / 2;
  this.pos = p.start || 0;
  this.min = p.min || 20;
  this.offset = p.offset || 0;
  this.relative = p.relative ? true : false;
  this.drag_active = false;
  this.render = p.render;
  this.callback = p.callback;

  var me = this;
  rcube_splitter._instances[this.id] = me;

  this.init = function()
  {
    this.p1 = $(this.p.p1);
    this.p2 = $(this.p.p2);
    this.parent = this.p1.parent();

    // check if referenced elements exist, otherwise abort
    if (!this.p1.length || !this.p2.length)
      return;

    // create and position the handle for this splitter
    this.p1pos = this.relative ? this.p1.position() : this.p1.offset();
    this.p2pos = this.relative ? this.p2.position() : this.p2.offset();
    this.handle = $('<div>')
      .attr('id', this.id)
      .attr('unselectable', 'on')
      .attr('role', 'presentation')
      .addClass('splitter ' + (this.horizontal ? 'splitter-h' : 'splitter-v'))
      .appendTo(this.parent)
      .mousedown(onDragStart);

    if (this.horizontal) {
      var top = this.p1pos.top + this.p1.outerHeight();
      this.handle.css({ left:'0px', top:top+'px' });
    }
    else {
      var left = this.p1pos.left + this.p1.outerWidth();
      this.handle.css({ left:left+'px', top:'0px' });
    }

    // listen to window resize on IE
    if (bw.ie)
      $(window).resize(onResize);

    // read saved position from cookie
    var cookie = this.get_cookie();
    if (cookie && !isNaN(cookie)) {
      this.pos = parseFloat(cookie);
      this.resize();
    }
    else if (this.pos) {
      this.resize();
      this.set_cookie();
    }
  };

  /**
   * Set size and position of all DOM objects
   * according to the saved splitter position
   */
  this.resize = function()
  {
    if (this.horizontal) {
      this.p1.css('height', Math.floor(this.pos - this.p1pos.top - Math.floor(this.halfsize)) + 'px');
      this.p2.css('top', Math.ceil(this.pos + Math.ceil(this.halfsize) + 2) + 'px');
      this.handle.css('top', Math.round(this.pos - this.halfsize + this.offset)+'px');
      if (bw.ie) {
        var new_height = parseInt(this.parent.outerHeight(), 10) - parseInt(this.p2.css('top'), 10);
        this.p2.css('height', (new_height > 0 ? new_height : 0) + 'px');
      }
    }
    else {
      this.p1.css('width', Math.floor(this.pos - this.p1pos.left - Math.floor(this.halfsize)) + 'px');
      this.p2.css('left', Math.ceil(this.pos + Math.ceil(this.halfsize)) + 'px');
      this.handle.css('left', Math.round(this.pos - this.halfsize + this.offset + 3)+'px');
      if (bw.ie) {
        var new_width = parseInt(this.parent.outerWidth(), 10) - parseInt(this.p2.css('left'), 10) ;
        this.p2.css('width', (new_width > 0 ? new_width : 0) + 'px');
      }
    }

    this.p2.resize();
    this.p1.resize();

    // also resize iframe covers
    if (this.drag_active) {
      $('iframe').each(function(i, elem) {
        var pos = $(this).offset();
        $('#iframe-splitter-fix-'+i).css({ top: pos.top+'px', left: pos.left+'px', width:elem.offsetWidth+'px', height: elem.offsetHeight+'px' });
      });
    }

    if (typeof this.render == 'function')
      this.render(this);
  };

  /**
   * Handler for mousedown events
   */
  function onDragStart(e)
  {
    // disable text selection while dragging the splitter
    if (bw.konq || bw.chrome || bw.safari)
      document.body.style.webkitUserSelect = 'none';

    me.p1pos = me.relative ? me.p1.position() : me.p1.offset();
    me.p2pos = me.relative ? me.p2.position() : me.p2.offset();
    me.drag_active = true;

    // start listening to mousemove events
    $(document).on('mousemove.' + this.id, onDrag).on('mouseup.' + this.id, onDragStop);

    // hack messages list so it will propagate the mouseup event over the list
    if (rcmail.message_list)
      rcmail.message_list.drag_active = true;

    // enable dragging above iframes
    $('iframe').each(function(i, elem) {
      $('<div>')
        .attr('id', 'iframe-splitter-fix-'+i)
        .addClass('iframe-splitter-fix')
        .css({ background: '#fff',
          width: elem.offsetWidth+'px', height: elem.offsetHeight+'px',
          position: 'absolute', opacity: '0.001', zIndex: 1000
        })
        .css($(this).offset())
        .appendTo('body');
      });
  };

  /**
   * Handler for mousemove events
   */
  function onDrag(e)
  {
    if (!me.drag_active)
      return false;

    // with timing events dragging action is more responsive
    window.clearTimeout(me.ts);
    me.ts = window.setTimeout(function() { onDragAction(e); }, 1);

    return false;
  };

  /**
   * Dragging action (see onDrag())
   */
  function onDragAction(e)
  {
    var pos = rcube_event.get_mouse_pos(e);

    if (me.relative) {
      var parent = me.parent.offset();
      pos.x -= parent.left;
      pos.y -= parent.top;
    }

    if (me.horizontal) {
      if (((pos.y - me.halfsize) > me.p1pos.top) && ((pos.y + me.halfsize) < (me.p2pos.top + me.p2.outerHeight()))) {
        me.pos = Math.max(me.min, pos.y - Math.max(0, me.offset));
        if (me.pos > me.min)
          me.pos = Math.min(me.pos, me.parent.height() - me.min);

        me.resize();
      }
    }
    else {
      if (((pos.x - me.halfsize) > me.p1pos.left) && ((pos.x + me.halfsize) < (me.p2pos.left + me.p2.outerWidth()))) {
        me.pos = Math.max(me.min, pos.x - Math.max(0, me.offset));
        if (me.pos > me.min)
          me.pos = Math.min(me.pos, me.parent.width() - me.min);

        me.resize();
      }
    }

    me.p1pos = me.relative ? me.p1.position() : me.p1.offset();
    me.p2pos = me.relative ? me.p2.position() : me.p2.offset();
  };

  /**
   * Handler for mouseup events
   */
  function onDragStop(e)
  {
    // resume the ability to highlight text
    if (bw.konq || bw.chrome || bw.safari)
      document.body.style.webkitUserSelect = 'auto';

    // cancel the listening for drag events
    $(document).off('.' + me.id);
    me.drag_active = false;

    if (rcmail.message_list)
      rcmail.message_list.drag_active = false;

    // remove temp divs
    $('div.iframe-splitter-fix').remove();

    me.set_cookie();

    if (typeof me.callback == 'function')
      me.callback(me);

    return bw.safari ? true : rcube_event.cancel(e);
  };

  /**
   * Handler for window resize events
   */
  function onResize(e)
  {
    if (me.horizontal) {
      var new_height = parseInt(me.parent.outerHeight(), 10) - parseInt(me.p2[0].style.top, 10);
      me.p2.css('height', (new_height > 0 ? new_height : 0) +'px');
    }
    else {
      var new_width = parseInt(me.parent.outerWidth(), 10) - parseInt(me.p2[0].style.left, 10);
      me.p2.css('width', (new_width > 0 ? new_width : 0) + 'px');
    }
  };

  /**
   * Get saved splitter position from cookie
   */
  this.get_cookie = function()
  {
    return window.UI ? UI.get_pref(this.id) : null;
  };

  /**
   * Saves splitter position in cookie
   */
  this.set_cookie = function()
  {
    if (window.UI)
      UI.save_pref(this.id, this.pos);
  };

} // end class rcube_splitter


// static getter for splitter instances
rcube_splitter._instances = {};

rcube_splitter.get_instance = function(id)
{
  return rcube_splitter._instances[id];
};

// @license-end
