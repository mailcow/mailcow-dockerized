/**
 * Roundcube functions for default skin interface
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2006-2014, The Roundcube Dev Team
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

/**
 * Settings
 */

function rcube_init_settings_tabs()
{
  var el, cl, container = $('#tabsbar'),
    last_tab = $('span:last', container),
    tab = '#settingstabpreferences',
    action = window.rcmail && rcmail.env.action ? rcmail.env.action : null;

  // move About tab to the end
  if (last_tab && last_tab.attr('id') != 'settingstababout' && (el = $('#settingstababout'))) {
    cl = el.clone(true);
    el.remove();
    last_tab.after(cl);
  }

  // get selected tab
  if (action)
    tab = '#settingstab' + (action.indexOf('identity')>0 ? 'identities' : action.replace(/\./g, ''));

  $(tab).addClass('tablink-selected');
  $('a', tab).removeAttr('onclick').click(function() { return false; });
}

// Fieldsets-to-tabs converter
// Warning: don't place "caller" <script> inside page element (id)
function rcube_init_tabs(id, current)
{
  var content = $('#'+id),
    fs = content.children('fieldset');

  if (!fs.length)
    return;

  current = current ? current : 0;

  // first hide not selected tabs
  fs.each(function(idx) { if (idx != current) $(this).hide(); });

  // create tabs container
  var tabs = $('<div>').addClass('tabsbar').appendTo(content);

  // convert fildsets into tabs
  fs.each(function(idx) {
    var tab, a, elm = $(this), legend = elm.children('legend');

    // create a tab
    a   = $('<a>').text(legend.text()).attr('href', '#');
    tab = $('<span>').attr({'id': 'tab'+idx, 'class': 'tablink'})
        .click(function() { rcube_show_tab(id, idx); return false })

    // remove legend
    legend.remove();
    // style fieldset
    elm.addClass('tabbed');
    // style selected tab
    if (idx == current)
      tab.addClass('tablink-selected');

    // add the tab to container
    tab.append(a).appendTo(tabs);
  });
}

function rcube_show_tab(id, index)
{
  var fs = $('#'+id).children('fieldset');

  fs.each(function(idx) {
    // Show/hide fieldset (tab content)
    $(this)[index==idx ? 'show' : 'hide']();
    // Select/unselect tab
    $('#tab'+idx).toggleClass('tablink-selected', idx==index);
  });
}

/**
 * Mail UI
 */

function rcube_mail_ui()
{
  this.popups = {
    markmenu:       {id:'markmessagemenu'},
    replyallmenu:   {id:'replyallmenu'},
    forwardmenu:    {id:'forwardmenu', editable:1},
    searchmenu:     {id:'searchmenu', editable:1},
    messagemenu:    {id:'messagemenu'},
    attachmentmenu: {id:'attachmentmenu'},
    dragmenu:       {id:'dragmenu', sticky:1},
    groupmenu:      {id:'groupoptionsmenu', above:1},
    mailboxmenu:    {id:'mailboxoptionsmenu', above:1},
    composemenu:    {id:'composeoptionsmenu', editable:1, overlap:1},
    spellmenu:      {id:'spellmenu'},
    responsesmenu:  {id:'responsesmenu'},
    // toggle: #1486823, #1486930
    uploadmenu:     {id:'attachment-form', editable:1, above:1, toggle:!bw.ie&&!bw.linux },
    uploadform:     {id:'upload-form', editable:1, toggle:!bw.ie&&!bw.linux }
  };

  var obj;
  for (var k in this.popups) {
    obj = $('#'+this.popups[k].id)
    if (obj.length)
      this.popups[k].obj = obj;
    else {
      delete this.popups[k];
    }
  }
}

rcube_mail_ui.prototype = {

show_popup: function(popup, show, config)
{
  var obj;
  // auto-register menu object
  if (!this.popups[popup] && (obj = $('#'+popup)) && obj.length)
    this.popups[popup] = $.extend(config, {id: popup, obj: obj});

  if (typeof this[popup] == 'function')
    return this[popup](show);
  else
    return this.show_popupmenu(popup, show);
},

show_popupmenu: function(popup, show)
{
  var obj = this.popups[popup].obj,
    above = this.popups[popup].above,
    ref = $(this.popups[popup].link ? this.popups[popup].link : rcube_find_object(popup+'link'));

  if (typeof show == 'undefined')
    show = obj.is(':visible') ? false : true;
  else if (this.popups[popup].toggle && show && this.popups[popup].obj.is(':visible') )
    show = false;

  if (show && ref.length) {
    var parent = ref.parent(),
      win = $(window),
      pos = parent.hasClass('dropbutton') ? parent.offset() : ref.offset();

    if (!above && pos.top + ref.height() + obj.height() > win.height())
      above = true;
    if (pos.left + obj.width() > win.width())
      pos.left = win.width() - obj.width() - 30;

    obj.css({ left:pos.left, top:(pos.top + (above ? -obj.height() : ref.height())) });
  }

  obj[show?'show':'hide']();
},

dragmenu: function(show)
{
  this.popups.dragmenu.obj[show?'show':'hide']();
},

forwardmenu: function(show)
{
  $("input[name='forwardtype'][value="+(rcmail.env.forward_attachment ? 1 : 0)+"]", this.popups.forwardmenu.obj)
    .prop('checked', true);
  this.show_popupmenu('forwardmenu', show);
},

uploadmenu: function(show)
{
  if (typeof show == 'object') // called as event handler
    show = false;

  // clear upload form
  if (!show) {
    try { $('#attachment-form form')[0].reset(); }
    catch(e){}  // ignore errors
  }

  if (rcmail.mailvelope_editor)
    return;

  this.show_popupmenu('uploadmenu', show);

  if (!document.all && this.popups.uploadmenu.obj.is(':visible'))
    $('#attachment-form input[type=file]').click();
},

searchmenu: function(show)
{
  var obj = this.popups.searchmenu.obj,
    ref = rcube_find_object('searchmenulink');

  if (typeof show == 'undefined')
    show = obj.is(':visible') ? false : true;

  if (show && ref) {
    var pos = $(ref).offset();
    obj.css({left:pos.left, top:(pos.top + ref.offsetHeight + 2)});

    if (rcmail.env.search_mods) {
      var n, all,
        list = $('input:checkbox[name="s_mods[]"]', obj),
        mbox = rcmail.env.mailbox,
        mods = rcmail.env.search_mods,
        scope = rcmail.env.search_scope || 'base';

      if (rcmail.env.task == 'mail') {
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
  obj[show?'show':'hide']();
},

set_searchmod: function(elem)
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
},

show_listmenu: function(p)
{
  var self = this, buttons = {}, $dialog = $('#listmenu');

  // close the dialog
  if ($dialog.is(':visible')) {
    $dialog.dialog('close', p.originalEvent);
    return;
  }

  // set form values
  $('input[name="sort_col"][value="'+rcmail.env.sort_col+'"]').prop('checked', true);
  $('input[name="sort_ord"][value="DESC"]').prop('checked', rcmail.env.sort_order == 'DESC');
  $('input[name="sort_ord"][value="ASC"]').prop('checked', rcmail.env.sort_order != 'DESC');
  $('input[name="view"][value="thread"]').prop('checked', rcmail.env.threading ? true : false);
  $('input[name="view"][value="list"]').prop('checked', rcmail.env.threading ? false : true);

  // set checkboxes
  $('input[name="list_col[]"]').each(function() {
    $(this).prop('checked', $.inArray(this.value, rcmail.env.listcols) != -1);
  });

  $.each(['widescreen', 'desktop', 'list'], function() {
    $('input[name="layout"][value="' + this + '"]').prop('checked', rcmail.env.layout == this);
  });
  $('#listoptions-columns', $dialog)[rcmail.env.layout == 'widescreen' ? 'hide' : 'show']();

  buttons[rcmail.gettext('save')] = function(e) {
    $dialog.dialog('close', e);
    self.save_listmenu();
  };

  $dialog.dialog({
    modal: true,
    resizable: false,
    closeOnEscape: true,
    title: null,
    open: function(e) {
      var maxheight = 0;
      $('#listmenu fieldset').each(function() {
        var height = $(this).height();
        if (height > maxheight) {
          maxheight = height;
        }
      }).css("min-height", maxheight+"px").height(maxheight);

      setTimeout(function() { $dialog.find('a, input:not(:disabled)').not('[aria-disabled=true]').first().focus(); }, 100);
    },
    close: function(e) {
      $dialog.dialog('destroy').hide();
      if (e.originalEvent && rcube_event.is_keyboard(e.originalEvent))
        $('#listmenulink').focus();
    },
    buttons: buttons,
    minWidth: 500,
    width: $dialog.width()+20
  }).show();
},

save_listmenu: function()
{
  var sort = $('input[name="sort_col"]:checked').val(),
    ord = $('input[name="sort_ord"]:checked').val(),
    thread = $('input[name="view"]:checked').val(),
    layout = $('input[name="layout"]:checked').val(),
    cols = $('input[name="list_col[]"]:checked')
      .map(function(){ return this.value; }).get();

  rcmail.set_list_options(cols, sort, ord, thread == 'thread' ? 1 : 0, layout);
},

spellmenu: function(show)
{
  var link, li,
    lang = rcmail.spellcheck_lang(),
    menu = this.popups.spellmenu.obj,
    ul = $('ul', menu);

  if (!ul.length) {
    ul = $('<ul>');

    for (i in rcmail.env.spell_langs) {
      li = $('<li>');
      link = $('<a href="#"></a>').text(rcmail.env.spell_langs[i])
        .addClass('active').data('lang', i)
        .click(function() {
          rcmail.spellcheck_lang_set($(this).data('lang'));
        });

      link.appendTo(li);
      li.appendTo(ul);
    }

    ul.appendTo(menu);
  }

  // select current language
  $('li', ul).each(function() {
    var el = $('a', this);
    if (el.data('lang') == lang)
      el.addClass('selected');
    else if (el.hasClass('selected'))
      el.removeClass('selected');
  });

  this.show_popupmenu('spellmenu', show);
},

show_attachmentmenu: function(elem, event)
{
  var id = elem.parentNode.id.replace(/^attach/, '');

  $.each(['open', 'download', 'rename'], function() {
    var action = this;
    $('#attachmenu' + action).off('click').attr('onclick', '').click(function(e) {
      return rcmail.command(action + '-attachment', id, this);
    });
  });

  this.popups.attachmentmenu.link = elem;
  rcmail.command('menu-open', {menu: 'attachmentmenu', id: id}, elem, event);
},

menu_open: function(p)
{
  if (p && p.name == 'messagelistmenu')
    this.show_listmenu();
},

body_mouseup: function(e)
{
  var target = e.target; ref = this;

  $.each(this.popups, function(i, popup) {
    if (popup.obj.is(':visible') && target != rcube_find_object(i + 'link')
      && !popup.toggle
      && target != popup.obj.get(0)  // check if scroll bar was clicked (#1489832)
      && (!popup.editable || !ref.target_overlaps(target, popup.id))
      && (!popup.sticky || !rcube_mouse_is_over(e, rcube_find_object(popup.id)))
      && !$(target).is('.folder-selector-link') && !$(target).children('.folder-selector-link').length
    ) {
      window.setTimeout('rcmail_ui.show_popup("'+i+'",false);', 50);
    }
  });
},

target_overlaps: function (target, elementid)
{
  var element = rcube_find_object(elementid);
  while (target.parentNode) {
    if (target.parentNode == element)
      return true;
    target = target.parentNode;
  }
  return false;
},

body_keydown: function(e)
{
  if (e.keyCode == 27) {
    for (var k in this.popups) {
      if (this.popups[k].obj.is(':visible'))
        this.show_popup(k, false);
    }
  }
},

// Mail view layout initialization and change handler
set_layout: function(p)
{
  var layout = p ? p.new_layout : rcmail.env.layout,
    top = $('#mailcontframe'),
    bottom = $('#mailpreviewframe');

  if (p)
    $('#mailrightcontainer').removeClass().addClass(layout);

  if (!this.mailviewsplitv) {
    this.mailviewsplitv = new rcube_splitter({id:'mailviewsplitterv', p1: 'mailleftcontainer', p2: 'mailrightcontainer',
      orientation: 'v', relative: true, start: 165, callback: rcube_render_mailboxlist });
    this.mailviewsplitv.init();
  }

  $('#mailviewsplitter')[layout == 'desktop' ? 'show' : 'hide']();
  $('#mailviewsplitter2')[layout == 'widescreen' ? 'show' : 'hide']();
  $('#mailpreviewframe')[layout != 'list' ? 'show' : 'hide']();
  rcmail.env.contentframe = layout == 'list' ? null : 'messagecontframe';

  if (layout == 'widescreen') {
    $('#countcontrols').detach().appendTo($('#messagelistheader'));
    top.css({height: 'auto', width: 400});
    bottom.css({top: 0, left: 410, height: 'auto'}).show();
    if (!this.mailviewsplit2) {
      this.mailviewsplit2 = new rcube_splitter({id:'mailviewsplitter2', p1: 'mailcontframe', p2: 'mailpreviewframe',
        orientation: 'v', relative: true, start: 405});
      this.mailviewsplit2.init();
    }
    else
      this.mailviewsplit2.resize();
  }
  else if (layout == 'desktop') {
    top.css({height: 200, width: '100%'});
    bottom.css({left: 0, top: 210, height: 'auto'}).show();
    if (!this.mailviewsplit) {
      this.mailviewsplit = new rcube_splitter({id:'mailviewsplitter', p1: 'mailcontframe', p2: 'mailpreviewframe',
        orientation: 'h', relative: true, start: 205});
      this.mailviewsplit.init();
    }
    else
      this.mailviewsplit.resize();
  }
  else { // layout == 'list'
    top.css({height: 'auto', width: '100%'});
    bottom.hide();
  }

  if (p && p.old_layout == 'widescreen') {
    $('#countcontrols').detach().appendTo($('#messagelistfooter'));
  }
},


/* Message composing */
init_compose_form: function()
{
  var f, v, field, fields = ['cc', 'bcc', 'replyto', 'followupto'],
    div = document.getElementById('compose-div'),
    headers_div = document.getElementById('compose-headers-div');

  // Show input elements with non-empty value
  for (f=0; f<fields.length; f++) {
    v = fields[f]; field = $('#_'+v);
    if (field.length) {
      field.on('change', {v:v}, function(e) { if (this.value) rcmail_ui.show_header_form(e.data.v); });
      if (field.val() != '')
        rcmail_ui.show_header_form(v);
    }
  }

  // prevent from form data loss when pressing ESC key in IE
  if (bw.ie) {
    var form = rcube_find_object('form');
    form.onkeydown = function (e) {
      if (rcube_event.get_keycode(e) == 27)
        rcube_event.cancel(e);
    };
  }

  $(window).resize(function() {
    rcmail_ui.resize_compose_body();
  });

  $('#compose-container').resize(function() {
    rcmail_ui.resize_compose_body();
  });

  div.style.top = (parseInt(headers_div.offsetHeight, 10) + 3) + 'px';
  $(window).resize();

  // fixes contacts-table position when there's more than one addressbook
  $('#contacts-table').css('top', $('#directorylist').height() + 24 + 'px');

  // contacts search submit
  $('#quicksearchbox').keydown(function(e) {
    if (rcube_event.get_keycode(e) == 13)
      rcmail.command('search');
  });
},

resize_compose_body: function()
{
  var div = $('#compose-div .boxlistcontent'),
    w = div.width() - 6,
    h = div.height() - 2,
    x = bw.ie || bw.opera ? 4 : 0;

  $('#compose-body_ifr').width(w + 6).height(h - 1 - $('div.mce-toolbar').height());
  $('#compose-body').width(w-x).height(h);
  $('#googie_edit_layer').width(w).height(h);
},

resize_compose_body_ev: function()
{
  window.setTimeout(function(){rcmail_ui.resize_compose_body();}, 100);
},

show_header_form: function(id)
{
  var row, s,
    link = document.getElementById(id + '-link');

  if ((s = this.next_sibling(link)))
    s.style.display = 'none';
  else if ((s = this.prev_sibling(link)))
    s.style.display = 'none';

  link.style.display = 'none';

  if ((row = document.getElementById('compose-' + id))) {
    var div = document.getElementById('compose-div'),
      headers_div = document.getElementById('compose-headers-div');
    $(row).show();
    div.style.top = (parseInt(headers_div.offsetHeight, 10) + 3) + 'px';
    this.resize_compose_body();
  }

  return false;
},

hide_header_form: function(id)
{
  var row, ns,
    link = document.getElementById(id + '-link'),
    parent = link.parentNode,
    links = parent.getElementsByTagName('a');

  link.style.display = '';

  for (var i=0; i<links.length; i++)
    if (links[i].style.display != 'none')
      for (var j=i+1; j<links.length; j++)
        if (links[j].style.display != 'none')
          if ((ns = this.next_sibling(links[i]))) {
            ns.style.display = '';
            break;
          }

  document.getElementById('_' + id).value = '';

  if ((row = document.getElementById('compose-' + id))) {
    var div = document.getElementById('compose-div'),
      headers_div = document.getElementById('compose-headers-div');
    row.style.display = 'none';
    div.style.top = (parseInt(headers_div.offsetHeight, 10) + 1) + 'px';
    this.resize_compose_body();
  }

  return false;
},

next_sibling: function(elm)
{
  var ns = elm.nextSibling;
  while (ns && ns.nodeType == 3)
    ns = ns.nextSibling;
  return ns;
},

prev_sibling: function(elm)
{
  var ps = elm.previousSibling;
  while (ps && ps.nodeType == 3)
    ps = ps.previousSibling;
  return ps;
},

enable_command: function(p)
{
  if (p.command == 'reply-list' && rcmail.env.reply_all_mode == 1) {
    var label = rcmail.gettext(p.status ? 'replylist' : 'replyall');
    $('a.button.replyAll').attr('title', label);
  }
  else if (p.command == 'compose-encrypted') {
    // show the toolbar button for Mailvelope
    $('#messagetoolbar > a.encrypt').show();
  }
},

folder_search_init: function(container)
{
  // animation to unfold list search box
  $('.boxtitle a.search', container).click(function(e) {
    var title = $('.boxtitle', container),
      box = $('.listsearchbox', container),
      dir = box.is(':visible') ? -1 : 1,
      height = 24 + ($('select', box).length ? 24 : 0);

    box.slideToggle({
      duration: 160,
      progress: function(animation, progress) {
        if (dir < 0) progress = 1 - progress;
          $('.boxlistcontent', container).css('top', (title.outerHeight() + height * progress) + 'px');
      },
      complete: function() {
        box.toggleClass('expanded');
        if (box.is(':visible')) {
          box.find('input[type=text]').focus();
        }
        else {
          $('a.reset', box).click();
        }
        // TODO: save state in cookie
      }
    });

    return false;
  });
}

};

/**
 * Roundcube generic layer (floating box) class
 *
 * @constructor
 */
function rcube_layer(id, attributes)
{
  this.name = id;

  // create a new layer in the current document
  this.create = function(arg)
  {
    var l = (arg.x) ? arg.x : 0,
      t = (arg.y) ? arg.y : 0,
      w = arg.width,
      h = arg.height,
      z = arg.zindex,
      vis = arg.vis,
      parent = arg.parent,
      obj = document.createElement('DIV');

    obj.id = this.name;
    obj.style.position = 'absolute';
    obj.style.visibility = (vis) ? (vis==2) ? 'inherit' : 'visible' : 'hidden';
    obj.style.left = l+'px';
    obj.style.top = t+'px';
    if (w)
      obj.style.width = w.toString().match(/\%$/) ? w : w+'px';
    if (h)
      obj.style.height = h.toString().match(/\%$/) ? h : h+'px';
    if (z)
      obj.style.zIndex = z;

    if (parent)
      parent.appendChild(obj);
    else
      document.body.appendChild(obj);

    this.elm = obj;
  };

  // create new layer
  if (attributes != null) {
    this.create(attributes);
    this.name = this.elm.id;
  }
  else  // just refer to the object
    this.elm = document.getElementById(id);

  if (!this.elm)
    return false;


  // ********* layer object properties *********

  this.css = this.elm.style;
  this.event = this.elm;
  this.width = this.elm.offsetWidth;
  this.height = this.elm.offsetHeight;
  this.x = parseInt(this.elm.offsetLeft);
  this.y = parseInt(this.elm.offsetTop);
  this.visible = (this.css.visibility=='visible' || this.css.visibility=='show' || this.css.visibility=='inherit') ? true : false;


  // ********* layer object methods *********

  // move the layer to a specific position
  this.move = function(x, y)
  {
    this.x = x;
    this.y = y;
    this.css.left = Math.round(this.x)+'px';
    this.css.top = Math.round(this.y)+'px';
  };

  // change the layers width and height
  this.resize = function(w,h)
  {
    this.css.width  = w+'px';
    this.css.height = h+'px';
    this.width = w;
    this.height = h;
  };

  // show or hide the layer
  this.show = function(a)
  {
    if(a == 1) {
      this.css.visibility = 'visible';
      this.visible = true;
    }
    else if(a == 2) {
      this.css.visibility = 'inherit';
      this.visible = true;
    }
    else {
      this.css.visibility = 'hidden';
      this.visible = false;
    }
  };

  // write new content into a Layer
  this.write = function(cont)
  {
    this.elm.innerHTML = cont;
  };

};

/**
 * Scroller
 *
 * @deprecated Use treelist widget
 */
function rcmail_scroller(list, top, bottom)
{
  var ref = this;

  this.list = $(list);
  this.top = $(top);
  this.bottom = $(bottom);
  this.step_size = 6;
  this.step_time = 20;
  this.delay = 500;

  this.top
    .mouseenter(function() { ref.ts = window.setTimeout(function() { ref.scroll('down'); }, ref.delay); })
    .mouseout(function() { if (ref.ts) window.clearTimeout(ref.ts); });

  this.bottom
    .mouseenter(function() { ref.ts = window.setTimeout(function() { ref.scroll('up'); }, ref.delay); })
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

// Abbreviate mailbox names to fit width of the container
function rcube_render_mailboxlist()
{
  var list = $('#mailboxlist > li > a, #mailboxlist ul:visible > li > a');

  // it's too slow with really big number of folders
  if (list.length > 100)
    return;

  list.each(function() {
    var elem = $(this),
      text = elem.data('text');

    if (!text) {
      text = elem.text().replace(/\s+\([0-9]+\)$/, '');
      elem.data('text', text);
    }

    if (text.length < 6)
      return;

    var abbrev = fit_string_to_size(text, elem, elem.width() - elem.children('span.unreadcount').width() - 16);
    if (abbrev != text)
      elem.attr('title', text);
    elem.contents().filter(function(){ return (this.nodeType == 3); }).get(0).data = abbrev;
  });
};

// inspired by https://gist.github.com/24261/7fdb113f1e26111bd78c0c6fe515f6c0bf418af5
function fit_string_to_size(str, elem, len)
{
  var w, span, $span, result = str, ellip = '...';

  if (!rcmail.env.tmp_span) {
    // it should be appended to elem to use the same css style
    // but for performance reasons we'll append it to body (once)
    span = $('<b>').css({visibility: 'hidden', padding: '0px',
      'font-family': elem.css('font-family'),
      'font-size': elem.css('font-size')})
      .appendTo($('body', document)).get(0);
    rcmail.env.tmp_span = span;
  }
  else {
    span = rcmail.env.tmp_span;
  }

  $span = $(span);
  $span.text(result);

  // on first run, check if string fits into the length already.
  w = span.offsetWidth;
  if (w > len) {
    var cut = Math.max(1, Math.floor(str.length * ((w - len) / w) / 2)),
      mid = Math.floor(str.length / 2),
      offLeft = mid,
      offRight = mid;

    while (true) {
      offLeft = mid - cut;
      offRight = mid + cut;
      $span.text(str.substring(0,offLeft) + ellip + str.substring(offRight));

      // break loop if string fits size
      if (offLeft < 3 || span.offsetWidth)
        break;

      cut++;
    }

    // build resulting string
    result = str.substring(0,offLeft) + ellip + str.substring(offRight);
  }

  return result;
};

function update_quota(data)
{
  percent_indicator(rcmail.gui_objects.quotadisplay, data);

  if (data.table) {
    var menu = $('#quotamenu');

    if (!menu.length)
      menu = $('<div id="quotamenu" class="popupmenu">').appendTo($('body'));

    menu.html(data.table);
    $('#quotaimg').css('cursor', 'pointer').off('click').on('click', function(e) {
      return rcmail.command('menu-open', 'quotamenu', e.target, e);
    });
  }
};

// percent (quota) indicator
function percent_indicator(obj, data)
{
  if (!data || !obj)
    return false;

  var limit_high = 80,
    limit_mid  = 55,
    width = data.width ? data.width : rcmail.env.indicator_width ? rcmail.env.indicator_width : 100,
    height = data.height ? data.height : rcmail.env.indicator_height ? rcmail.env.indicator_height : 14,
    quota = data.percent ? Math.abs(parseInt(data.percent)) : 0,
    quota_width = parseInt(quota / 100 * width),
    pos = $(obj).position();

  // workarounds for Opera and Webkit bugs
  pos.top = Math.max(0, pos.top);
  pos.left = Math.max(0, pos.left);

  rcmail.env.indicator_width = width;
  rcmail.env.indicator_height = height;

  // overlimit
  if (quota_width > width) {
    quota_width = width;
    quota = 100;
  }

  if (data.title)
    data.title = rcmail.get_label('quota') + ': ' +  data.title;

  // main div
  var main = $('<div>');
  main.css({position: 'absolute', top: pos.top, left: pos.left,
      width: width + 'px', height: height + 'px', zIndex: 100, lineHeight: height + 'px'})
    .attr('title', data.title).addClass('quota_text').html(quota + '%');
  // used bar
  var bar1 = $('<div>');
  bar1.css({position: 'absolute', top: pos.top + 1, left: pos.left + 1,
      width: quota_width + 'px', height: height + 'px', zIndex: 99});
  // background
  var bar2 = $('<div>');
  bar2.css({position: 'absolute', top: pos.top + 1, left: pos.left + 1,
      width: width + 'px', height: height + 'px', zIndex: 98})
    .addClass('quota_bg');

  if (quota >= limit_high) {
    main.addClass(' quota_text_high');
    bar1.addClass('quota_high');
  }
  else if(quota >= limit_mid) {
    main.addClass(' quota_text_mid');
    bar1.addClass('quota_mid');
  }
  else {
    main.addClass(' quota_text_low');
    bar1.addClass('quota_low');
  }

  // replace quota image
  $(obj).html('').append(bar1).append(bar2).append(main);
  // update #quotaimg title
  $('#quotaimg').attr('title', data.title);
};

function attachment_menu_append(item)
{
  $(item).append(
    $('<a class="drop"></a>').on('click keypress', function(e) {
      if (e.type != 'keypress' || e.which == 13) {
        rcmail_ui.show_attachmentmenu(this, e);
        return false;
      }
    })
  );
};

// Optional parameters used by TinyMCE
var rcmail_editor_settings = {};

var rcmail_ui;

function rcube_init_mail_ui()
{
  rcmail_ui = new rcube_mail_ui();

  $(document.body).mouseup(function(e) { rcmail_ui.body_mouseup(e); })
    .mousedown(function(e) { rcmail_ui.body_keydown(e); });

  rcmail.addEventListener('init', function() {
    if (rcmail.env.quota_content)
      update_quota(rcmail.env.quota_content);
    rcmail.addEventListener('setquota', update_quota);

    rcube_webmail.set_iframe_events({mouseup: function(e) { return rcmail_ui.body_mouseup(e); }});

    if (rcmail.env.task == 'mail') {
      rcmail.addEventListener('enable-command', 'enable_command', rcmail_ui)
        .addEventListener('menu-open', 'menu_open', rcmail_ui)
        .addEventListener('aftersend-attachment', 'uploadmenu', rcmail_ui)
        .addEventListener('aftertoggle-editor', 'resize_compose_body_ev', rcmail_ui)
        .gui_object('dragmenu', 'dragmenu');

      if (rcmail.gui_objects.mailboxlist) {
        rcmail.treelist.addEventListener('expand', rcube_render_mailboxlist);
        rcmail.addEventListener('responseaftermark', rcube_render_mailboxlist)
          .addEventListener('responseaftergetunread', rcube_render_mailboxlist)
          .addEventListener('responseaftercheck-recent', rcube_render_mailboxlist)
          .addEventListener('responseafterrefresh', rcube_render_mailboxlist)
          .addEventListener('afterimport-messages', function(){ rcmail_ui.show_popup('uploadform', false); });
      }

      rcmail.init_pagejumper('#pagejumper');

      // fix message list header on window resize (#1490213)
      if (bw.ie && rcmail.message_list)
        $(window).resize(function() {
          setTimeout(function() { rcmail.message_list.resize(); }, 10);
        });

      if (rcmail.env.action == 'list' || !rcmail.env.action) {
        rcmail.addEventListener('layout-change', 'set_layout', rcmail_ui);
        rcmail_ui.set_layout();
      }
      else if (rcmail.env.action == 'compose') {
        rcmail_ui.init_compose_form();
        rcmail.addEventListener('compose-encrypted', function(e) {
          $("a.button.encrypt")[(e.active ? 'addClass' : 'removeClass')]('selected');
          $("select[name='editorSelector']").prop('disabled', e.active);
          $('a.button.attach, a.button.responses, a.button.attach, #uploadmenulink')[(e.active ? 'addClass' : 'removeClass')]('buttonPas disabled');
          $('#responseslist a.insertresponse')[(e.active ? 'removeClass' : 'addClass')]('active');
        });
        rcmail.addEventListener('fileappended', function(e) {
          if (e.attachment.complete)
            attachment_menu_append(e.item);
        });

        // add menu link for each attachment
        $('#attachmentslist > li').each(function() {
          attachment_menu_append(this);
        });
      }
      else if (rcmail.env.action == 'show' || rcmail.env.action == 'preview') {
        // add menu link for each attachment
        $('#attachment-list > li[id^="attach"]').each(function() {
          attachment_menu_append(this);
        });

        $(window).resize(function() {
          if (!$('#attachment-list > li[id^="attach"]').length)
            $('#attachment-list').hide();

          var mvlpe = $('#messagebody.mailvelope');
          if (mvlpe.length) {
            var content = $('#messageframe'),
              h = (content.length ? content.height() + content.offset().top - 25 : $(this).height()) - mvlpe.offset().top - 20;
            mvlpe.height(h);
          }
        });
      }
    }
    else if (rcmail.env.task == 'addressbook') {
      rcmail.addEventListener('afterupload-photo', function(){ rcmail_ui.show_popup('uploadform', false); })
        .gui_object('dragmenu', 'dragmenu');
    }
    else if (rcmail.env.task == 'settings') {
      if (rcmail.env.action == 'folders') {
        rcmail_ui.folder_search_init($('#folder-manager'));
      }
    }
  });
}
