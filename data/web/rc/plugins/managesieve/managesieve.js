/**
 * (Manage)Sieve Filters plugin
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2012-2014, The Roundcube Dev Team
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {
    // add managesieve-create command to message_commands array,
    // so it's state will be updated on message selection/unselection
    if (rcmail.env.task == 'mail') {
      if (rcmail.env.action != 'show')
        rcmail.env.message_commands.push('managesieve-create');
      else
        rcmail.enable_command('managesieve-create', true);
    }

    if (rcmail.env.task == 'mail' || rcmail.env.action.startsWith('plugin.managesieve')) {
      // Create layer for form tips
      if (!rcmail.env.framed) {
        rcmail.env.ms_tip_layer = $('<div id="managesieve-tip" class="popupmenu"></div>');
        rcmail.env.ms_tip_layer.appendTo(document.body);
      }
    }

    // register commands
    rcmail.register_command('plugin.managesieve-save', function() { rcmail.managesieve_save() });
    rcmail.register_command('plugin.managesieve-act', function() { rcmail.managesieve_act() });
    rcmail.register_command('plugin.managesieve-add', function() { rcmail.managesieve_add() });
    rcmail.register_command('plugin.managesieve-del', function() { rcmail.managesieve_del() });
    rcmail.register_command('plugin.managesieve-move', function() { rcmail.managesieve_move() });
    rcmail.register_command('plugin.managesieve-setadd', function() { rcmail.managesieve_setadd() });
    rcmail.register_command('plugin.managesieve-setdel', function() { rcmail.managesieve_setdel() });
    rcmail.register_command('plugin.managesieve-setact', function() { rcmail.managesieve_setact() });
    rcmail.register_command('plugin.managesieve-setget', function() { rcmail.managesieve_setget() });
    rcmail.register_command('plugin.managesieve-seteditraw', function() { rcmail.managesieve_seteditraw() });

    if (rcmail.env.action.startsWith('plugin.managesieve')) {
      if (rcmail.gui_objects.sieveform) {
        rcmail.enable_command('plugin.managesieve-save', true);
        sieve_form_init();
      }
      else if (rcmail.gui_objects.sievesetrawform) {
        rcmail.enable_command('plugin.managesieve-save', true);
        sieve_raw_editor_init();
      }
      else {
        rcmail.enable_command('plugin.managesieve-add', 'plugin.managesieve-setadd', !rcmail.env.sieveconnerror);
      }

      var setcnt, set = rcmail.env.currentset;

      if (rcmail.gui_objects.filterslist) {
        rcmail.filters_list = new rcube_list_widget(rcmail.gui_objects.filterslist,
          {multiselect:false, draggable:true, keyboard:true});

        rcmail.filters_list
          .addEventListener('select', function(e) { rcmail.managesieve_select(e); })
          .addEventListener('dragstart', function(e) { rcmail.managesieve_dragstart(e); })
          .addEventListener('dragend', function(e) { rcmail.managesieve_dragend(e); })
          .addEventListener('initrow', function(row) {
            row.obj.onmouseover = function() { rcmail.managesieve_focus_filter(row); };
            row.obj.onmouseout = function() { rcmail.managesieve_unfocus_filter(row); };
          })
          .init();
      }

      if (rcmail.gui_objects.filtersetslist) {
        rcmail.filtersets_list = new rcube_list_widget(rcmail.gui_objects.filtersetslist,
          {multiselect:false, draggable:false, keyboard:true});

        rcmail.filtersets_list.init().focus();

        if (set != null) {
          set = rcmail.managesieve_setid(set);
          rcmail.filtersets_list.select(set);
        }

        // attach select event after initial record was selected
        rcmail.filtersets_list.addEventListener('select', function(e) { rcmail.managesieve_setselect(e); });

        setcnt = rcmail.filtersets_list.rowcount;
        rcmail.enable_command('plugin.managesieve-set', true);
        rcmail.enable_command('plugin.managesieve-setact', 'plugin.managesieve-setget', setcnt > 0);
        rcmail.enable_command('plugin.managesieve-setdel', setcnt > 1);
        rcmail.enable_command('plugin.managesieve-seteditraw', setcnt > 0 && rcmail.env.raw_sieve_editor);

        // Fix dragging filters over sets list
        $('tr', rcmail.gui_objects.filtersetslist).each(function (i, e) { rcmail.managesieve_fixdragend(e); });
      }
    }

    if (rcmail.gui_objects.sieveform && rcmail.env.rule_disabled)
      $('#disabled').attr('checked', true);
  });
};

/*********************************************************/
/*********       Managesieve UI methods          *********/
/*********************************************************/

rcube_webmail.prototype.managesieve_add = function()
{
  this.load_managesieveframe('', true);
};

rcube_webmail.prototype.managesieve_del = function()
{
  var id = this.filters_list.get_single_selection();
  if (confirm(this.get_label('managesieve.filterdeleteconfirm'))) {
    var lock = this.set_busy(true, 'loading');
    this.http_post('plugin.managesieve-action',
      '_act=delete&_fid='+this.filters_list.rows[id].uid, lock);
  }
};

rcube_webmail.prototype.managesieve_act = function()
{
  var id = this.filters_list.get_single_selection(),
    lock = this.set_busy(true, 'loading');

  this.http_post('plugin.managesieve-action',
    '_act=act&_fid='+this.filters_list.rows[id].uid, lock);
};

// Filter selection
rcube_webmail.prototype.managesieve_select = function(list)
{
  var id = list.get_single_selection();

  if (id != null) {
    id = list.rows[id].uid;
    this.load_managesieveframe('_fid=' + id);
  }

  var has_id = typeof(id) != 'undefined' && id != null;
  this.enable_command('plugin.managesieve-act', 'plugin.managesieve-del', has_id);
};

// Set selection
rcube_webmail.prototype.managesieve_setselect = function(list)
{
  this.show_contentframe(false);
  this.filters_list.clear(true);
  this.enable_command('plugin.managesieve-setdel', list.rowcount > 1);
  this.enable_command('plugin.managesieve-setact', 'plugin.managesieve-setget', list.rowcount > 0);
  this.enable_command('plugin.managesieve-seteditraw', list.rowcount > 0 && this.env.raw_sieve_editor);

  var id = list.get_single_selection();
  if (id != null)
    this.managesieve_list(this.env.filtersets[id]);
};

rcube_webmail.prototype.managesieve_rowid = function(id)
{
  var i, rows = this.filters_list.rows;

  for (i in rows)
    if (rows[i] != null && rows[i].uid == id)
      return i;
};

// Returns set's identifier
rcube_webmail.prototype.managesieve_setid = function(name)
{
  for (var i in this.env.filtersets)
    if (this.env.filtersets[i] == name)
      return i;
};

// Filters listing request
rcube_webmail.prototype.managesieve_list = function(script)
{
  var lock = this.set_busy(true, 'loading');

  this.http_post('plugin.managesieve-action', '_act=list&_set='+urlencode(script), lock);
};

// Script download request
rcube_webmail.prototype.managesieve_setget = function()
{
  var id = this.filtersets_list.get_single_selection(),
    script = this.env.filtersets[id];

  this.goto_url('plugin.managesieve-action', {_act: 'setget', _set: script}, false, true);
};

// Set activate/deactivate request
rcube_webmail.prototype.managesieve_setact = function()
{
  var id = this.filtersets_list.get_single_selection(),
   lock = this.set_busy(true, 'loading'),
    script = this.env.filtersets[id],
    action = $('#rcmrow'+id).hasClass('disabled') ? 'setact' : 'deact';

  this.http_post('plugin.managesieve-action', '_act='+action+'&_set='+urlencode(script), lock);
};

// Set delete request
rcube_webmail.prototype.managesieve_setdel = function()
{
  if (!confirm(this.get_label('managesieve.setdeleteconfirm')))
    return false;

  var id = this.filtersets_list.get_single_selection(),
    lock = this.set_busy(true, 'loading'),
    script = this.env.filtersets[id];

  this.http_post('plugin.managesieve-action', '_act=setdel&_set='+urlencode(script), lock);
};

// Set edit raw request
rcube_webmail.prototype.managesieve_seteditraw = function()
{
  var id = this.filtersets_list.get_single_selection(),
    script = this.env.filtersets[id];

  this.load_managesieveframe('_seteditraw=1&_set=' + urlencode(script), true);
}

// Set add request
rcube_webmail.prototype.managesieve_setadd = function()
{
  this.load_managesieveframe('_newset=1', true);
};

rcube_webmail.prototype.managesieve_updatelist = function(action, o)
{
  this.set_busy(true);

  switch (action) {
    // Delete filter row
    case 'del':
      var id = o.id, list = this.filters_list;

      list.remove_row(this.managesieve_rowid(o.id));
      this.show_contentframe(false);
      this.reset_filters_list();

      // filter identifiers changed, fix the list
      $('tr', this.filters_list.list).each(function() {
        // remove hidden (deleted) rows
        if (this.style.display == 'none') {
          $(this).detach();
          return;
        }

        var rowid = this.id.substr(6);

        // remove all attached events
        $(this).off();

        // update row id
        if (rowid > id) {
          this.uid = rowid - 1;
          $(this).attr('id', 'rcmrow' + this.uid);
        }
      });
      list.init();

      break;

    // Update filter row
    case 'update':
      var i, row = $('#rcmrow'+this.managesieve_rowid(o.id));

      if (o.name)
        $('td', row).text(o.name);
      if (o.disabled)
        row.addClass('disabled');
      else
        row.removeClass('disabled');

      $('#disabled', $('iframe').contents()).prop('checked', o.disabled);

      break;

    // Add filter row to the list
    case 'add':
      var list = this.filters_list,
        row = $('<tr><td class="name"></td></tr>');

      $('td', row).text(o.name);
      row.attr('id', 'rcmrow'+o.id);
      if (o.disabled)
        row.addClass('disabled');

      list.insert_row(row.get(0));
      list.highlight_row(o.id);

      this.enable_command('plugin.managesieve-del', 'plugin.managesieve-act', true);

      break;

    // Filling rules list
    case 'list':
      var i, tr, td, el, list = this.filters_list;

      if (o.clear)
        list.clear();

      for (i in o.list) {
        el = o.list[i];
        tr = document.createElement('TR');
        td = document.createElement('TD');

        $(td).text(el.name);
        td.className = 'name';
        tr.id = 'rcmrow' + el.id;
        if (el['class'])
            tr.className = el['class'];
        tr.appendChild(td);

        list.insert_row(tr);
      }

      if (o.set)
        list.highlight_row(o.set);
      else
        this.enable_command('plugin.managesieve-del', 'plugin.managesieve-act', false);

      break;

    // Sactivate/deactivate set
    case 'setact':
      var id = this.managesieve_setid(o.name), row = $('#rcmrow' + id);
      if (o.active) {
        if (o.all)
          $('tr', this.gui_objects.filtersetslist).addClass('disabled');
        row.removeClass('disabled');
      }
      else
        row.addClass('disabled');

      break;

    // Delete set row
    case 'setdel':
      var id = this.managesieve_setid(o.name);

      this.filtersets_list.remove_row(id);
      this.filters_list.clear();
      this.show_contentframe(false);
      this.enable_command('plugin.managesieve-setdel', 'plugin.managesieve-setact', 'plugin.managesieve-setget', false);

      delete this.env.filtersets[id];

      break;

    // Create set row
    case 'setadd':
      var id = 'S' + new Date().getTime(),
        list = this.filtersets_list,
        row = $('<tr class="disabled"><td class="name"></td></tr>');

      $('td', row).text(o.name);
      row.attr('id', 'rcmrow'+id);

      this.env.filtersets[id] = o.name;
      list.insert_row(row.get(0));

      // move row into its position on the list
      if (o.index != list.rowcount-1) {
        row.detach();
        var elem = $('tr:visible', list.list).get(o.index);
        row.insertBefore(elem);
      }

      list.select(id);

      // Fix dragging filters over sets list
      this.managesieve_fixdragend(row);

      break;

    case 'refresh':
      this.reset_filters_list(true);
      break;
  }

  this.set_busy(false);
};

// Resets filters list state
rcube_webmail.prototype.reset_filters_list = function(reload)
{
  this.filters_list.clear_selection();
  this.enable_command('plugin.managesieve-act', 'plugin.managesieve-del', false);

  if (reload) {
    var id = this.filtersets_list.get_single_selection();

    this.filters_list.clear(true);
    this.managesieve_list(this.env.filtersets[id]);
  }
};

// load filter frame
rcube_webmail.prototype.load_managesieveframe = function(add_url, reset)
{
  if (reset)
    this.reset_filters_list();

  if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
    var lock = this.set_busy(true, 'loading');

    target = window.frames[this.env.contentframe];
    target.location.href = this.env.comm_path
      + '&_action=plugin.managesieve-action&_framed=1&_unlock=' + lock
      + (add_url ? ('&' + add_url) : '');
  }
};

// load filter frame
rcube_webmail.prototype.managesieve_dragstart = function(list)
{
  var id = this.filters_list.get_single_selection();

  this.drag_active = true;
  this.drag_filter = id;
};

rcube_webmail.prototype.managesieve_dragend = function(e)
{
  if (this.drag_active) {
    if (this.drag_filter_target) {
      var lock = this.set_busy(true, 'loading');

      this.show_contentframe(false);
      this.http_post('plugin.managesieve-action', '_act=move&_fid='+this.drag_filter
        +'&_to='+this.drag_filter_target, lock);
    }
    this.drag_active = false;
  }
};

// Fixes filters dragging over sets list
// @TODO: to be removed after implementing copying filters
rcube_webmail.prototype.managesieve_fixdragend = function(elem)
{
  var p = this;
  $(elem).on('mouseup' + ((bw.iphone || bw.ipad) ? ' touchend' : ''), function(e) {
    if (p.drag_active)
      p.filters_list.drag_mouse_up(e);
  });
};

rcube_webmail.prototype.managesieve_focus_filter = function(row)
{
  var id = row.id.replace(/^rcmrow/, '');
  if (this.drag_active && id != this.drag_filter) {
    this.drag_filter_target = id;
    $(row.obj).addClass(id < this.drag_filter ? 'filtermoveup' : 'filtermovedown');
  }
};

rcube_webmail.prototype.managesieve_unfocus_filter = function(row)
{
  if (this.drag_active) {
    $(row.obj).removeClass('filtermoveup filtermovedown');
    this.drag_filter_target = null;
  }
};

/*********************************************************/
/*********          Filter Form methods          *********/
/*********************************************************/

// Form submition
rcube_webmail.prototype.managesieve_save = function()
{
  if (this.env.action == 'plugin.managesieve-vacation') {
    var data = $(this.gui_objects.sieveform).serialize();
    this.http_post('plugin.managesieve-vacation', data, this.display_message(this.get_label('managesieve.vacation.saving'), 'loading'));
    return;
  }

  if (this.gui_objects.sieveform) {
    if (parent.rcmail && parent.rcmail.filters_list && this.gui_objects.sieveform.name != 'filtersetform') {
      var id = parent.rcmail.filters_list.get_single_selection();
      if (id != null)
        this.gui_objects.sieveform.elements['_fid'].value = parent.rcmail.filters_list.rows[id].uid;
    }
    this.gui_objects.sieveform.submit();
  }
  else if (this.gui_objects.sievesetrawform) {
    this.gui_objects.sievesetrawform.submit();
  }
};

// Operations on filters form
rcube_webmail.prototype.managesieve_ruleadd = function(id)
{
  this.http_post('plugin.managesieve-action', '_act=ruleadd&_rid='+id);
};

rcube_webmail.prototype.managesieve_rulefill = function(content, id, after)
{
  if (content != '') {
    // create new element
    var div = document.getElementById('rules'),
      row = document.createElement('div');

    this.managesieve_insertrow(div, row, after);
    // fill row after inserting (for IE)
    row.setAttribute('id', 'rulerow'+id);
    row.className = 'rulerow';
    row.innerHTML = content;

    // initialize smart list inputs
    $('textarea[data-type="list"]', row).each(function() {
      smart_field_init(this);
    });

    this.managesieve_formbuttons(div);
  }
};

rcube_webmail.prototype.managesieve_ruledel = function(id)
{
  if ($('#ruledel'+id).hasClass('disabled'))
    return;

  if (confirm(this.get_label('managesieve.ruledeleteconfirm'))) {
    var row = document.getElementById('rulerow'+id);
    row.parentNode.removeChild(row);
    this.managesieve_formbuttons(document.getElementById('rules'));
  }
};

rcube_webmail.prototype.managesieve_actionadd = function(id)
{
  this.http_post('plugin.managesieve-action', '_act=actionadd&_aid='+id);
};

rcube_webmail.prototype.managesieve_actionfill = function(content, id, after)
{
  if (content != '') {
    var div = document.getElementById('actions'),
      row = document.createElement('div');

    this.managesieve_insertrow(div, row, after);
    // fill row after inserting (for IE)
    row.className = 'actionrow';
    row.setAttribute('id', 'actionrow'+id);
    row.innerHTML = content;

    // initialize smart list inputs
    $('textarea[data-type="list"]', row).each(function() {
      smart_field_init(this);
    });

    this.managesieve_formbuttons(div);
  }
};

rcube_webmail.prototype.managesieve_actiondel = function(id)
{
  if ($('#actiondel'+id).hasClass('disabled'))
    return;

  if (confirm(this.get_label('managesieve.actiondeleteconfirm'))) {
    var row = document.getElementById('actionrow'+id);
    row.parentNode.removeChild(row);
    this.managesieve_formbuttons(document.getElementById('actions'));
  }
};

// insert rule/action row in specified place on the list
rcube_webmail.prototype.managesieve_insertrow = function(div, row, after)
{
  for (var i=0; i<div.childNodes.length; i++) {
    if (div.childNodes[i].id == (div.id == 'rules' ? 'rulerow' : 'actionrow')  + after)
      break;
  }

  if (div.childNodes[i+1])
    div.insertBefore(row, div.childNodes[i+1]);
  else
    div.appendChild(row);
};

// update Delete buttons status
rcube_webmail.prototype.managesieve_formbuttons = function(div)
{
  var i, button, buttons = [];

  // count and get buttons
  for (i=0; i<div.childNodes.length; i++) {
    if (div.id == 'rules' && div.childNodes[i].id) {
      if (/rulerow/.test(div.childNodes[i].id))
        buttons.push('ruledel' + div.childNodes[i].id.replace(/rulerow/, ''));
    }
    else if (div.childNodes[i].id) {
      if (/actionrow/.test(div.childNodes[i].id))
        buttons.push( 'actiondel' + div.childNodes[i].id.replace(/actionrow/, ''));
    }
  }

  for (i=0; i<buttons.length; i++) {
    button = document.getElementById(buttons[i]);
    if (i>0 || buttons.length>1) {
      $(button).removeClass('disabled');
    }
    else {
      $(button).addClass('disabled');
    }
  }
};

// update vacation addresses field with user identities
rcube_webmail.prototype.managesieve_vacation_addresses = function(id)
{
  var lock = this.set_busy(true, 'loading');
  this.http_post('plugin.managesieve-action', {_act: 'addresses', _aid: id}, lock);
};

// update vacation addresses field with user identities
rcube_webmail.prototype.managesieve_vacation_addresses_update = function(id, addresses)
{
  var field = $('#vacation_addresses,#action_addresses' + (id || ''));
  smart_field_reset(field.get(0), addresses);
};

function rule_header_select(id)
{
  var obj = document.getElementById('header' + id),
    size = document.getElementById('rule_size' + id),
    msg = document.getElementById('rule_message' + id),
    op = document.getElementById('rule_op' + id),
    header = document.getElementById('custom_header' + id + '_list'),
    custstr = document.getElementById('custom_var' + id + '_list'),
    mod = document.getElementById('rule_mod' + id),
    trans = document.getElementById('rule_trans' + id),
    comp = document.getElementById('rule_comp' + id),
    datepart = document.getElementById('rule_date_part' + id),
    dateheader = document.getElementById('rule_date_header_div' + id),
    rule = $('#rule_op' + id),
    h = obj.value,
    set = [op, header, custstr, mod, trans, comp, size];

  if (h == 'size') {
    if (msg) set.push(msg);
    $.each(set, function() { this.style.display = 'none'; });
    size.style.display = 'inline';
  }
  else if (h == 'message' && msg) {
    $.each(set, function() { this.style.display = 'none'; });
    msg.style.display = 'inline';
  }
  else {
    header.style.display = h != '...' ? 'none' : 'inline-block';
    custstr.style.display = h != 'string' ? 'none' : 'inline-block';
    size.style.display = 'none';
    op.style.display = 'inline';
    comp.style.display = '';
    mod.style.display = h == 'body' || h == 'currentdate' || h == 'date' || h == 'string' ? 'none' : 'block';
    trans.style.display = h == 'body' ? 'block' : 'none';
    if (msg)
      msg.style.display = h == 'message' ? 'block' : 'none';
  }

  if (datepart)
    datepart.style.display = h == 'currentdate' || h == 'date' ? 'inline' : 'none';
  if (dateheader)
    dateheader.style.display = h == 'date' ? '' : 'none';

  $('[value="exists"],[value="notexists"]', rule).prop('disabled', h == 'string');
  if (!rule.val() || rule.val().match(/^(exists|notexists)$/))
    rule.val('contains');

  rule_op_select(op, id, h);
  rule_mod_select(id, h);

  obj.style.width = h == '...' ? '40px' : '';
};

function rule_op_select(obj, id, header)
{
  var target = document.getElementById('rule_target' + id + '_list');

  if (!header)
    header = document.getElementById('header' + id).value;

  target.style.display = obj.value.match(/^(exists|notexists)$/) || header.match(/^(size|message)$/) ? 'none' : 'inline-block';
};

function rule_trans_select(id)
{
  var obj = document.getElementById('rule_trans_op' + id),
    target = document.getElementById('rule_trans_type' + id);

  target.style.display = obj.value != 'content' ? 'none' : 'inline';
};

function rule_mod_select(id, header)
{
  var obj = document.getElementById('rule_mod_op' + id),
    target = document.getElementById('rule_mod_type' + id),
    duplicate = document.getElementById('rule_duplicate_div' + id),
    index = document.getElementById('rule_index_div' + id);

  if (!header)
    header = document.getElementById('header' + id).value;

  target.style.display = obj.value != 'address' && obj.value != 'envelope' ? 'none' : 'inline';

  if (index)
    index.style.display = !header.match(/^(body|currentdate|size|message|string)$/) && obj.value != 'envelope'  ? '' : 'none';

  if (duplicate)
    duplicate.style.display = header == 'message' ? '' : 'none';
};

function rule_join_radio(value)
{
  $('#rules').css('display', value == 'any' ? 'none' : 'block');
};

function rule_adv_switch(id, elem)
{
  var elem = $(elem), enabled = elem.hasClass('hide'), adv = $('#rule_advanced'+id);

  if (enabled) {
    adv.hide();
    elem.removeClass('hide').addClass('show');
  }
  else {
    adv.show();
    elem.removeClass('show').addClass('hide');
  }
}

function action_type_select(id)
{
  var obj = document.getElementById('action_type' + id),
    v = obj.value, enabled = {},
    elems = {
      mailbox: document.getElementById('action_mailbox' + id),
      target: document.getElementById('redirect_target' + id),
      target_area: document.getElementById('action_target_area' + id),
      flags: document.getElementById('action_flags' + id),
      vacation: document.getElementById('action_vacation' + id),
      set: document.getElementById('action_set' + id),
      notify: document.getElementById('action_notify' + id)
    };

  if (v == 'fileinto' || v == 'fileinto_copy') {
    enabled.mailbox = 1;
  }
  else if (v == 'redirect' || v == 'redirect_copy') {
    enabled.target = 1;
  }
  else if (v.match(/^reject|ereject$/)) {
    enabled.target_area = 1;
  }
  else if (v.match(/^(add|set|remove)flag$/)) {
    enabled.flags = 1;
  }
  else if (v == 'vacation') {
    enabled.vacation = 1;
  }
  else if (v == 'set') {
    enabled.set = 1;
  }
  else if (v == 'notify') {
    enabled.notify = 1;
  }

  for (var x in elems) {
    elems[x].style.display = !enabled[x] ? 'none' : 'inline';
  }
};

function vacation_action_select()
{
  var selected = $('#vacation_action').val();

  $('#action_target_span')[selected == 'discard' || selected == 'keep' ? 'hide' : 'show']();
};

// Inititalizes smart list input
function smart_field_init(field)
{
  var id = field.id + '_list',
    area = $('<span class="listarea"></span>'),
    list = field.value ? field.value.split("\n") : [''];

  if ($('#'+id).length)
    return;

  // add input rows
  $.each(list, function(i, v) {
    area.append(smart_field_row(v, field.name, i, $(field).data('size')));
  });

  area.attr('id', id);
  field = $(field);

  if (field.attr('disabled'))
    area.hide();
  // disable the original field anyway, we don't want it in POST
  else
    field.prop('disabled', true);

  field.after(area);

  if (field.hasClass('error')) {
    area.addClass('error');
    rcmail.managesieve_tip_register([[id, field.data('tip')]]);
  }
};

function smart_field_row(value, name, idx, size)
{
  // build row element content
  var input, content = '<span class="listelement">'
      + '<span class="reset"></span><input type="text"></span>',
    elem = $(content),
    attrs = {value: value, name: name + '[]'};

  if (size)
    attrs.size = size;

  input = $('input', elem).attr(attrs).keydown(function(e) {
    var input = $(this);

    // element creation event (on Enter)
    if (e.which == 13) {
      var name = input.attr('name').replace(/\[\]$/, ''),
        dt = (new Date()).getTime(),
        elem = smart_field_row('', name, dt, size);

      input.parent().after(elem);
      $('input', elem).focus();
    }
    // backspace or delete: remove input, focus previous one
    else if ((e.which == 8 || e.which == 46) && input.val() == '') {

      var parent = input.parent(), siblings = parent.parent().children();

      if (siblings.length > 1) {
        if (parent.prev().length)
          parent.prev().children('input').focus();
        else
          parent.next().children('input').focus();

        parent.remove();
        return false;
      }
    }
  });

  // element deletion event
  $('span[class="reset"]', elem).click(function() {
    var span = $(this.parentNode);

    if (span.parent().children().length > 1)
      span.remove();
    else
      $('input', span).val('').focus();
  });

  return elem;
}

// Reset and fill the smart list input with new data
function smart_field_reset(field, data)
{
  var id = field.id + '_list',
    list = data.length ? data : [''];
    area = $('#' + id);

  area.empty();

  // add input rows
  $.each(list, function(i, v) {
    area.append(smart_field_row(v, field.name, i, $(field).data('size')));
  });
}

// Register onmouse(leave/enter) events for tips on specified form element
rcube_webmail.prototype.managesieve_tip_register = function(tips)
{
  var n, framed = parent.rcmail,
    tip = framed ? parent.rcmail.env.ms_tip_layer : rcmail.env.ms_tip_layer;

  for (n in tips) {
    $('#'+tips[n][0])
      .data('tip', tips[n][1])
      .mouseleave(function(e) { tip.hide(); })
      .mouseenter(function(e) {
        var elem = $(this),
          offset = elem.offset(),
          left = offset.left,
          top = offset.top - 12,
          minwidth = elem.width();

        if (framed) {
          offset = $((rcmail.env.task == 'mail'  ? '#sievefilterform > iframe' : '#filter-box'), parent.document).offset();
          top  += offset.top;
          left += offset.left;
        }

        tip.html(elem.data('tip'));
        top -= tip.height();

        tip.css({left: left, top: top, minWidth: (minwidth-2) + 'px'}).show();
      });
  }
};

// format time string
function sieve_formattime(hour, minutes)
{
  var i, c, h, time = '', format = rcmail.env.time_format || 'H:i';

  for (i=0; i<format.length; i++) {
    c = format.charAt(i);
    switch (c) {
      case 'a': time += hour > 12 ? 'am' : 'pm'; break;
      case 'A': time += hour > 12 ? 'AM' : 'PM'; break;
      case 'g':
      case 'h':
        h = hour == 0 ? 12 : hour > 12 ? hour - 12 : hour;
        time += (c == 'h' && hour < 10 ? '0' : '') + hour;
        break;
      case 'G': time += hour; break;
      case 'H': time += (hour < 10 ? '0' : '') + hour; break;
      case 'i': time += (minutes < 10 ? '0' : '') + minutes; break;
      case 's': time += '00';
      default: time += c;
    }
  }

  return time;
}

function sieve_form_init()
{
  var form = rcmail.gui_objects.sieveform;

  // resize dialog window
  if (rcmail.env.action == 'plugin.managesieve' && rcmail.env.task == 'mail') {
    parent.rcmail.managesieve_dialog_resize(form);
  }

  $('input[type="text"]:first', form).focus();

  // initialize smart list inputs
  $('textarea[data-type="list"]', form).each(function() {
    smart_field_init(this);
  });

  // initialize rules form(s)
  $('[name="_header[]"]', form).each(function() {
    if (/([0-9]+)$/.test(this.id)) {
      rule_header_select(RegExp.$1);
    }
  });

  // enable date pickers on date fields
  if ($.datepicker && rcmail.env.date_format) {
    $.datepicker.setDefaults({
      dateFormat: rcmail.env.date_format,
      changeMonth: true,
      showOtherMonths: true,
      selectOtherMonths: true,
      onSelect: function(dateText) { $(this).focus().val(dateText); }
    });
    $('input.datepicker').datepicker();
  }

  // configure drop-down menu on time input fields based on jquery UI autocomplete
  $('#vacation_timefrom, #vacation_timeto')
    .attr('autocomplete', "off")
    .autocomplete({
      delay: 100,
      minLength: 1,
      source: function(p, callback) {
        var h, result = [];
        for (h = 0; h < 24; h++)
          result.push(sieve_formattime(h, 0));
        result.push(sieve_formattime(23, 59));

        return callback(result);
      },
      open: function(event, ui) {
        // scroll to current time
        var $this = $(this), val = $this.val(),
          widget = $this.autocomplete('widget').css('width', '10em'),
          menu = $this.data('ui-autocomplete').menu;

        if (val && val.length)
          widget.children().each(function() {
            var li = $(this);
            if (li.text().indexOf(val) == 0)
              menu._scrollIntoView(li);
          });
      },
      select: function(event, ui) {
        $(this).val(ui.item.value);
        return false;
      }
    })
    .click(function() {  // show drop-down upon clicks
      $(this).autocomplete('search', $(this).val() || ' ');
    })

  // display advanced controls when contain errors
  $('input.error').each(function() {
    if (String(this.id).match(/([0-9]+)$/)) {
      $('#ruleadv' + RegExp.$1 + '.show').click();
    }
  });
}

/*********************************************************/
/*********        RAW editor methods             *********/
/*********************************************************/

var cmeditor;

function cmCreateErrorElem(msg)
{
  var marker = document.createElement("div");
  marker.style.color = "#822";
  marker.innerHTML = "●";
  marker.title = msg;

  return marker;
}

function cmScrollToError()
{
  var line = $('.CodeMirror-lines .line-error'),
    scroll = $('.CodeMirror-scroll'),
    h = line.parent();

  scroll.scrollTop(line.offset().top - scroll.offset().top - Math.round(scroll.height()/2));
}

function sieve_raw_editor_init()
{
  var textArea = document.getElementById('rawfiltersettxt');
  if (textArea && !cmeditor) {
    cmeditor = CodeMirror.fromTextArea(textArea, {
      mode: 'sieve',
      lineNumbers: true,
      gutters: ["CodeMirror-linenumbers", "errorGutter"],
      styleActiveLine: true
    });

    // fetching errors from environment and setting the line background
    // and a gutter element with the error message accordingly
    $.each(rcmail.env.sieve_errors || [], function(i, err) {
      var lineNo = Number(err.line) - 1;
      cmeditor.addLineClass(lineNo, 'background', 'line-error');
      cmeditor.setGutterMarker(lineNo, 'errorGutter', cmCreateErrorElem(err.msg));
      if (!i) cmScrollToError();
    });
  }
}


/*********************************************************/
/*********           Mail UI methods             *********/
/*********************************************************/

rcube_webmail.prototype.managesieve_create = function(force)
{
  if (!force && this.env.action != 'show') {
    var uid = this.message_list.get_single_selection(),
      lock = this.set_busy(true, 'loading');

    this.http_post('plugin.managesieve-action', {_uid: uid}, lock);
    return;
  }

  if (!this.env.sieve_headers || !this.env.sieve_headers.length)
    return;

  var i, html, buttons = {}, dialog = $("#sievefilterform");

  // create dialog window
  if (!dialog.length) {
    dialog = $('<div id="sievefilterform"></div>');
    $('body').append(dialog);
  }

  // build dialog window content
  html = '<fieldset><legend>'+this.get_label('managesieve.usedata')+'</legend><ul>';
  for (i in this.env.sieve_headers)
    html += '<li><input type="checkbox" name="headers[]" id="sievehdr'+i+'" value="'+i+'" checked="checked" />'
      +'<label for="sievehdr'+i+'">'+this.env.sieve_headers[i][0]+':</label> '+this.env.sieve_headers[i][1]+'</li>';
  html += '</ul></fieldset>';

  dialog.html(html);

  // [Next Step] button action
  buttons[this.get_label('managesieve.nextstep')] = function () {
    // check if there's at least one checkbox checked
    var hdrs = $('input[name="headers[]"]:checked', dialog);
    if (!hdrs.length) {
      alert(rcmail.get_label('managesieve.nodata'));
      return;
    }

    // build frame URL
    var url = rcmail.get_task_url('mail');
    url = rcmail.add_url(url, '_action', 'plugin.managesieve');
    url = rcmail.add_url(url, '_framed', 1);

    hdrs.map(function() {
      var val = rcmail.env.sieve_headers[this.value];
      url = rcmail.add_url(url, 'r['+this.value+']', val[0]+':'+val[1]);
    });

    // load form in the iframe
    var frame = $('<iframe>').attr({src: url, frameborder: 0})
    dialog.empty().append(frame).dialog('widget').resize();

    // Change [Next Step] button with [Save] button
    buttons = {};
    buttons[rcmail.get_label('save')] = function() {
      var win = $('iframe', dialog).get(0).contentWindow;
      win.rcmail.managesieve_save();
    };
    dialog.dialog('option', 'buttons', buttons);
  };

  // show dialog window
  dialog.dialog({
    modal: false,
    resizable: true,
    closeOnEscape: true,
    title: this.get_label('managesieve.newfilter'),
    close: function() { rcmail.managesieve_dialog_close(); },
    buttons: buttons,
    minWidth: 600,
    minHeight: 300,
    height: 250
  }).show();

  this.env.managesieve_dialog = dialog;
}

rcube_webmail.prototype.managesieve_dialog_close = function()
{
  var dialog = this.env.managesieve_dialog;

  // BUG(?): if we don't remove the iframe first, it will be reloaded
  dialog.html('');
  dialog.dialog('destroy').hide();
}

rcube_webmail.prototype.managesieve_dialog_resize = function(o)
{
  var dialog = this.env.managesieve_dialog,
    win = $(window), form = $(o);
    width = $('fieldset:first', o).width(), // fieldset width is more appropriate here
    height = form.height(),
    w = win.width(), h = win.height();

  dialog.dialog('option', { height: Math.min(h-20, height+120), width: Math.min(w-20, width+65) });
}
