/**
 * ACL plugin script
 */

if (window.rcmail) {
    rcmail.addEventListener('init', function() {
        if (rcmail.gui_objects.acltable) {
            rcmail.acl_list_init();
            // enable autocomplete on user input
            if (rcmail.env.acl_users_source) {
                var inst = rcmail.is_framed() ? parent.rcmail : rcmail;
                inst.init_address_input_events($('#acluser'), {action:'settings/plugin.acl-autocomplete'});

                // pass config settings and localized texts to autocomplete context
                inst.set_env({ autocomplete_max:rcmail.env.autocomplete_max, autocomplete_min_length:rcmail.env.autocomplete_min_length });
                inst.add_label('autocompletechars', rcmail.labels.autocompletechars);
                inst.add_label('autocompletemore', rcmail.labels.autocompletemore);

                // fix inserted value
                inst.addEventListener('autocomplete_insert', function(e) {
                    if (e.field.id != 'acluser')
                        return;

                    e.field.value = e.insert.replace(/[ ,;]+$/, '');
                });
            }
        }

        rcmail.enable_command('acl-create', 'acl-save', 'acl-cancel', 'acl-mode-switch', true);
        rcmail.enable_command('acl-delete', 'acl-edit', false);

        if (rcmail.env.acl_advanced)
            $('#acl-switch').addClass('selected');
    });
}

// Display new-entry form
rcube_webmail.prototype.acl_create = function()
{
    this.acl_init_form();
}

// Display ACL edit form
rcube_webmail.prototype.acl_edit = function()
{
    // @TODO: multi-row edition
    var id = this.acl_list.get_single_selection();
    if (id)
        this.acl_init_form(id);
}

// ACL entry delete
rcube_webmail.prototype.acl_delete = function()
{
    var users = this.acl_get_usernames();

    if (users && users.length && confirm(this.get_label('acl.deleteconfirm'))) {
        this.http_post('settings/plugin.acl', {
                _act: 'delete',
                _user: users.join(','),
                _mbox: this.env.mailbox
            },
            this.set_busy(true, 'acl.deleting'));
    }
}

// Save ACL data
rcube_webmail.prototype.acl_save = function()
{
    var data, type, rights = '', user = $('#acluser', this.acl_form).val();

    $((this.env.acl_advanced ? '#advancedrights :checkbox' : '#simplerights :checkbox'), this.acl_form).map(function() {
        if (this.checked)
            rights += this.value;
    });

    if (type = $('input:checked[name=usertype]', this.acl_form).val()) {
        if (type != 'user')
            user = type;
    }

    if (!user) {
        alert(this.get_label('acl.nouser'));
        return;
    }
    if (!rights) {
        alert(this.get_label('acl.norights'));
        return;
    }

    data = {
        _act: 'save',
        _user: user,
        _acl: rights,
        _mbox: this.env.mailbox
    }

    if (this.acl_id) {
        data._old = this.acl_id;
    }

    this.http_post('settings/plugin.acl', data, this.set_busy(true, 'acl.saving'));
}

// Cancel/Hide form
rcube_webmail.prototype.acl_cancel = function()
{
    this.ksearch_blur();
    this.acl_popup.dialog('close');
}

// Update data after save (and hide form)
rcube_webmail.prototype.acl_update = function(o)
{
    // delete old row
    if (o.old)
        this.acl_remove_row(o.old);
    // make sure the same ID doesn't exist
    else if (this.env.acl[o.id])
        this.acl_remove_row(o.id);

    // add new row
    this.acl_add_row(o, true);
    // hide autocomplete popup
    this.ksearch_blur();
    // hide form
    this.acl_popup.dialog('close');
}

// Switch table display mode
rcube_webmail.prototype.acl_mode_switch = function(elem)
{
    this.env.acl_advanced = !this.env.acl_advanced;
    this.enable_command('acl-delete', 'acl-edit', false);
    this.http_request('settings/plugin.acl', '_act=list'
        + '&_mode='+(this.env.acl_advanced ? 'advanced' : 'simple')
        + '&_mbox='+urlencode(this.env.mailbox),
        this.set_busy(true, 'loading'));
}

// ACL table initialization
rcube_webmail.prototype.acl_list_init = function()
{
    var method = this.env.acl_advanced ? 'addClass' : 'removeClass';

    $('#acl-switch')[method]('selected');
    $(this.gui_objects.acltable)[method]('advanced');

    this.acl_list = new rcube_list_widget(this.gui_objects.acltable,
        {multiselect: true, draggable: false, keyboard: true});
    this.acl_list.addEventListener('select', function(o) { rcmail.acl_list_select(o); })
        .addEventListener('dblclick', function(o) { rcmail.acl_list_dblclick(o); })
        .addEventListener('keypress', function(o) { rcmail.acl_list_keypress(o); })
        .init();
}

// ACL table row selection handler
rcube_webmail.prototype.acl_list_select = function(list)
{
    rcmail.enable_command('acl-delete', list.selection.length > 0);
    rcmail.enable_command('acl-edit', list.selection.length == 1);
    list.focus();
}

// ACL table double-click handler
rcube_webmail.prototype.acl_list_dblclick = function(list)
{
    this.acl_edit();
}

// ACL table keypress handler
rcube_webmail.prototype.acl_list_keypress = function(list)
{
    if (list.key_pressed == list.ENTER_KEY)
        this.command('acl-edit');
    else if (list.key_pressed == list.DELETE_KEY || list.key_pressed == list.BACKSPACE_KEY)
        if (!this.acl_form || !this.acl_form.is(':visible'))
            this.command('acl-delete');
}

// Reloads ACL table
rcube_webmail.prototype.acl_list_update = function(html)
{
    $(this.gui_objects.acltable).html(html);
    this.acl_list_init();
}

// Returns names of users in selected rows
rcube_webmail.prototype.acl_get_usernames = function()
{
    var users = [], n, len, cell, row,
        list = this.acl_list,
        selection = list.get_selection();

    for (n=0, len=selection.length; n<len; n++) {
        if (this.env.acl_specials.length && $.inArray(selection[n], this.env.acl_specials) >= 0) {
            users.push(selection[n]);
        }
        else if (row = list.rows[selection[n]]) {
            cell = $('td.user', row.obj);
            if (cell.length == 1)
                users.push(cell.text());
        }
    }

    return users;
}

// Removes ACL table row
rcube_webmail.prototype.acl_remove_row = function(id)
{
    var list = this.acl_list;

    list.remove_row(id);
    list.clear_selection();

    // we don't need it anymore (remove id conflict)
    $('#rcmrow'+id).remove();
    this.env.acl[id] = null;

    this.enable_command('acl-delete', list.selection.length > 0);
    this.enable_command('acl-edit', list.selection.length == 1);
}

// Adds ACL table row
rcube_webmail.prototype.acl_add_row = function(o, sel)
{
    var n, len, ids = [], spec = [], id = o.id, list = this.acl_list,
        items = this.env.acl_advanced ? [] : this.env.acl_items,
        table = this.gui_objects.acltable,
        row = $('thead > tr', table).clone();

    // Update new row
    $('th', row).map(function() {
        var td = $('<td>'),
            title = $(this).attr('title'),
            cl = this.className.replace(/^acl/, '');

        if (title)
            td.attr('title', title);

        if (items && items[cl])
            cl = items[cl];

        if (cl == 'user')
            td.addClass(cl).append($('<a>').text(o.username));
        else
            td.addClass(this.className + ' ' + rcmail.acl_class(o.acl, cl)).text('');

        $(this).replaceWith(td);
    });

    row.attr('id', 'rcmrow'+id);
    row = row.get(0);

    this.env.acl[id] = o.acl;

    // sorting... (create an array of user identifiers, then sort it)
    for (n in this.env.acl) {
        if (this.env.acl[n]) {
            if (this.env.acl_specials.length && $.inArray(n, this.env.acl_specials) >= 0)
                spec.push(n);
            else
                ids.push(n);
        }
    }
    ids.sort();
    // specials on the top
    ids = spec.concat(ids);

    // find current id
    for (n=0, len=ids.length; n<len; n++)
        if (ids[n] == id)
            break;

    // add row
    if (n && n < len) {
        $('#rcmrow'+ids[n-1]).after(row);
        list.init_row(row);
        list.rowcount++;
    }
    else
        list.insert_row(row);

    if (sel)
        list.select_row(o.id);
}

// Initializes and shows ACL create/edit form
rcube_webmail.prototype.acl_init_form = function(id)
{
    var ul, row, td, val = '', type = 'user', li_elements, body = $('body'),
        adv_ul = $('#advancedrights'), sim_ul = $('#simplerights'),
        name_input = $('#acluser'), type_list = $('#usertype');

    if (!this.acl_form) {
        var fn = function () { $('input[value="user"]').prop('checked', true); };
        name_input.click(fn).keypress(fn);
    }

    this.acl_form = $('#aclform');

    // Hide unused items
    if (this.env.acl_advanced) {
        adv_ul.show();
        sim_ul.hide();
        ul = adv_ul;
    }
    else {
        sim_ul.show();
        adv_ul.hide();
        ul = sim_ul;
    }

    // initialize form fields
    li_elements = $(':checkbox', ul);
    li_elements.attr('checked', false);

    if (id && (row = this.acl_list.rows[id])) {
        row = row.obj;
        li_elements.map(function() {
            td = $('td.'+this.id, row);
            if (td.length && td.hasClass('enabled'))
                this.checked = true;
        });

        if (!this.env.acl_specials.length || $.inArray(id, this.env.acl_specials) < 0)
            val = $('td.user', row).text();
        else
            type = id;
    }
    // mark read (lrs) rights by default
    else {
        li_elements.filter(function() { return this.id.match(/^acl([lrs]|read)$/); }).prop('checked', true);
    }

    name_input.val(val);
    $('input[value='+type+']').prop('checked', true);

    this.acl_id = id;

    var buttons = {}, me = this, body = document.body;

    buttons[this.get_label('save')] = function(e) { me.command('acl-save'); };
    buttons[this.get_label('cancel')] = function(e) { me.command('acl-cancel'); };

    // display it as popup
    this.acl_popup = this.show_popup_dialog(
        this.acl_form.show(),
        id ? this.get_label('acl.editperms') : this.get_label('acl.newuser'),
        buttons,
        {
            button_classes: ['mainaction'],
            modal: true,
            closeOnEscape: true,
            close: function(e, ui) {
                (me.is_framed() ? parent.rcmail : me).ksearch_hide();
                me.acl_form.appendTo(body).hide();
                $(this).remove();
                window.focus(); // focus iframe
            }
        }
    );

    if (type == 'user')
        name_input.focus();
    else
        $('input:checked', type_list).focus();
}

// Returns class name according to ACL comparision result
rcube_webmail.prototype.acl_class = function(acl1, acl2)
{
    var i, len, found = 0;

    acl1 = String(acl1);
    acl2 = String(acl2);

    for (i=0, len=acl2.length; i<len; i++)
        if (acl1.indexOf(acl2[i]) > -1)
            found++;

    if (found == len)
        return 'enabled';
    else if (found)
        return 'partial';

    return 'disabled';
}
