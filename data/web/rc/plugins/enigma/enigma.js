/* Enigma Plugin */

window.rcmail && rcmail.addEventListener('init', function(evt) {
    if (rcmail.env.task == 'settings') {
        if (rcmail.gui_objects.keyslist) {
            rcmail.keys_list = new rcube_list_widget(rcmail.gui_objects.keyslist,
                {multiselect:true, draggable:false, keyboard:false});
            rcmail.keys_list
                .addEventListener('select', function(o) { rcmail.enigma_keylist_select(o); })
                .addEventListener('keypress', function(o) { rcmail.enigma_keylist_keypress(o); })
                .init()
                .focus();

            rcmail.enigma_list();

            rcmail.register_command('firstpage', function(props) { return rcmail.enigma_list_page('first'); });
            rcmail.register_command('previouspage', function(props) { return rcmail.enigma_list_page('previous'); });
            rcmail.register_command('nextpage', function(props) { return rcmail.enigma_list_page('next'); });
            rcmail.register_command('lastpage', function(props) { return rcmail.enigma_list_page('last'); });
        }

        if (rcmail.env.action == 'plugin.enigmakeys') {
            rcmail.register_command('search', function(props) {return rcmail.enigma_search(props); }, true);
            rcmail.register_command('reset-search', function(props) {return rcmail.enigma_search_reset(props); }, true);
            rcmail.register_command('plugin.enigma-import', function() { rcmail.enigma_import(); }, true);
            rcmail.register_command('plugin.enigma-import-search', function() { rcmail.enigma_import_search(); }, true);
            rcmail.register_command('plugin.enigma-key-export', function() { rcmail.enigma_export(); });
            rcmail.register_command('plugin.enigma-key-export-selected', function() { rcmail.enigma_export(true); });
            rcmail.register_command('plugin.enigma-key-import', function() { rcmail.enigma_key_import(); }, true);
            rcmail.register_command('plugin.enigma-key-delete', function(props) { return rcmail.enigma_delete(); });
            rcmail.register_command('plugin.enigma-key-create', function(props) { return rcmail.enigma_key_create(); }, true);
            rcmail.register_command('plugin.enigma-key-save', function(props) { return rcmail.enigma_key_create_save(); }, true);

            rcmail.addEventListener('responseafterplugin.enigmakeys', function() {
                rcmail.enable_command('plugin.enigma-key-export', rcmail.env.rowcount > 0);
            });

            if (rcmail.gui_objects.importform) {
                // make sure Enter key in search input starts searching
                // instead of submitting the form
                $('#rcmimportsearch').keydown(function(e) {
                    if (e.which == 13) {
                        rcmail.enigma_import_search();
                        return false;
                    }
                });

                $('input[type="button"]:first').focus();
            }
        }
    }
    else if (rcmail.env.task == 'mail') {
        if (rcmail.env.action == 'compose') {
            rcmail.addEventListener('beforesend', function(props) { rcmail.enigma_beforesend_handler(props); })
                .addEventListener('beforesavedraft', function(props) { rcmail.enigma_beforesavedraft_handler(props); });

            $('input,label', $('#enigmamenu')).mouseup(function(e) {
                // don't close the menu on mouse click inside
                e.stopPropagation();
            });

            $('a.button.enigma').prop('tabindex', $('#messagetoolbar > a:first').prop('tabindex'));
        }

        $.each(['encrypt', 'sign'], function() {
            if (rcmail.env['enigma_force_' + this])
                $('[name="_enigma_' + this + '"]').prop('checked', true);
        });

        if (rcmail.env.enigma_password_request) {
            rcmail.enigma_password_request(rcmail.env.enigma_password_request);
        }
    }
});


/*********************************************************/
/*********    Enigma Settings/Keys/Certs UI      *********/
/*********************************************************/

// Display key(s) import form
rcube_webmail.prototype.enigma_key_import = function()
{
    this.enigma_loadframe('&_action=plugin.enigmakeys&_a=import');
};

// Display key(s) generation form
rcube_webmail.prototype.enigma_key_create = function()
{
    this.enigma_loadframe('&_action=plugin.enigmakeys&_a=create');
};

// Generate key(s) and submit them
rcube_webmail.prototype.enigma_key_create_save = function()
{
    var options, lock, users = [],
        password = $('#key-pass').val(),
        confirm = $('#key-pass-confirm').val(),
        size = $('#key-size').val();

    $('[name="identity[]"]:checked').each(function() {
        users.push(this.value);
    });

    // validate the form
    if (!password || !confirm)
        return alert(this.get_label('enigma.formerror'));

    if (password != confirm)
        return alert(this.get_label('enigma.passwordsdiffer'));

    if (!users.length)
        return alert(this.get_label('enigma.noidentselected'));

    // generate keys
    // use OpenPGP.js if browser supports required features
    if (window.openpgp && window.crypto && (window.crypto.getRandomValues || window.crypto.subtle)) {
        lock = this.set_busy(true, 'enigma.keygenerating');
        options = {
            numBits: size,
            userId: users,
            passphrase: password
        };

        openpgp.generateKeyPair(options).then(function(keypair) {
            // success
            var post = {_a: 'import', _keys: keypair.privateKeyArmored, _generated: 1,
                _passwd: password, _keyid: keypair.key.primaryKey.fingerprint};

            // send request to server
            rcmail.http_post('plugin.enigmakeys', post, lock);
        }, function(error) {
            // failure
            rcmail.set_busy(false, null, lock);
            rcmail.display_message(rcmail.get_label('enigma.keygenerateerror'), 'error');
        });
    }
    else {
        rcmail.display_message(rcmail.get_label('enigma.keygennosupport'), 'error');
    }
};

// Action executed after successful key generation and import
rcube_webmail.prototype.enigma_key_create_success = function()
{
    parent.rcmail.enigma_list(1);
};

// Delete key(s)
rcube_webmail.prototype.enigma_delete = function()
{
    var keys = this.keys_list.get_selection();

    if (!keys.length || !confirm(this.get_label('enigma.keyremoveconfirm')))
        return;

    var lock = this.display_message(this.get_label('enigma.keyremoving'), 'loading'),
        post = {_a: 'delete', _keys: keys};

    // send request to server
    this.http_post('plugin.enigmakeys', post, lock);
};

// Export key(s)
rcube_webmail.prototype.enigma_export = function(selected)
{
    var priv = false,
        list = this.keys_list,
        keys = selected ? list.get_selection().join(',') : '*',
        args = {_keys: keys};

    if (!keys.length)
        return;

    // find out whether selected keys are private
    if (keys == '*')
        priv = true;
    else
        $.each(list.get_selection(), function() {
            flags = $(list.rows[this].obj).data('flags');
            if (flags && flags.indexOf('p') >= 0) {
                priv = true;
                return false;
            }
        });

    // ask the user about including private key in the export
    if (priv)
        return this.show_popup_dialog(
            this.get_label('enigma.keyexportprompt'),
            this.get_label('enigma.exportkeys'),
            [{
                text: this.get_label('enigma.onlypubkeys'),
                click: function(e) {
                    rcmail.enigma_export_submit(args);
                    $(this).remove();
                }
            },
            {
                text: this.get_label('enigma.withprivkeys'),
                click: function(e) {
                    args._priv = 1;
                    rcmail.enigma_export_submit(args);
                    $(this).remove();
                }
            }],
            {width: 400}
        );

    this.enigma_export_submit(args);
};

// Sumbitting request for key(s) export
// Done this way to handle password input
rcube_webmail.prototype.enigma_export_submit = function(data)
{
    var id = 'keyexport-' + new Date().getTime(),
        form = $('<form>').attr({target: id, method: 'post', style: 'display:none',
            action: '?_action=plugin.enigmakeys&_task=settings&_a=export'}),
        iframe = $('<iframe>').attr({name: id, style: 'display:none'})

    form.append($('<input>').attr({name: '_token', value: this.env.request_token}));
    $.each(data, function(i, v) {
        form.append($('<input>').attr({name: i, value: v}));
    });

    iframe.appendTo(document.body);
    form.appendTo(document.body).submit();
};

// Submit key(s) import form
rcube_webmail.prototype.enigma_import = function()
{
    var form, file, lock,
        id = 'keyexport-' + new Date().getTime(),
        iframe = $('<iframe>').attr({name: id, style: 'display:none'});

    if (form = this.gui_objects.importform) {
        file = document.getElementById('rcmimportfile');
        if (file && !file.value) {
            alert(this.get_label('selectimportfile'));
            return;
        }

        lock = this.set_busy(true, 'importwait');
        iframe.appendTo(document.body);
        $(form).attr({target: id, action: this.add_url(form.action, '_unlock', lock)})
            .submit();
    }
};

// Ssearch for key(s) for import
rcube_webmail.prototype.enigma_import_search = function()
{
    var form, search;

    if (form = this.gui_objects.importform) {
        search = $('#rcmimportsearch').val();
        if (!search) {
            return;
        }

        this.enigma_find_publickey(search);
    }
};

// list row selection handler
rcube_webmail.prototype.enigma_keylist_select = function(list)
{
    var id = list.get_single_selection(), url;

    if (id)
        url = '&_action=plugin.enigmakeys&_a=info&_id=' + id;

    this.enigma_loadframe(url);
    this.enable_command('plugin.enigma-key-delete', 'plugin.enigma-key-export-selected', list.selection.length > 0);
};

rcube_webmail.prototype.enigma_keylist_keypress = function(list)
{
    if (list.modkey == CONTROL_KEY)
        return;

    if (list.key_pressed == list.DELETE_KEY || list.key_pressed == list.BACKSPACE_KEY)
        this.command('plugin.enigma-key-delete');
    else if (list.key_pressed == 33)
        this.command('previouspage');
    else if (list.key_pressed == 34)
        this.command('nextpage');
};

// load key frame
rcube_webmail.prototype.enigma_loadframe = function(url)
{
    var frm, win;

    if (this.env.contentframe && window.frames && (frm = window.frames[this.env.contentframe])) {
        if (!url && (win = window.frames[this.env.contentframe])) {
            if (win.location && win.location.href.indexOf(this.env.blankpage) < 0)
                win.location.href = this.env.blankpage;
            if (this.env.frame_lock)
                this.set_busy(false, null, this.env.frame_lock);
            return;
        }

        this.env.frame_lock = this.set_busy(true, 'loading');
        frm.location.href = this.env.comm_path + '&_framed=1&' + url;
    }
};

// Search keys/certs
rcube_webmail.prototype.enigma_search = function(props)
{
    if (!props && this.gui_objects.qsearchbox)
        props = this.gui_objects.qsearchbox.value;

    if (props || this.env.search_request) {
        var params = {'_a': 'search', '_q': props},
          lock = this.set_busy(true, 'searching');
//        if (this.gui_objects.search_filter)
//          addurl += '&_filter=' + this.gui_objects.search_filter.value;
        this.env.current_page = 1;
        this.enigma_loadframe();
        this.enigma_clear_list();
        this.http_post('plugin.enigmakeys', params, lock);
    }

    return false;
};

// Reset search filter and the list
rcube_webmail.prototype.enigma_search_reset = function(props)
{
    var s = this.env.search_request;
    this.reset_qsearch();

    if (s) {
        this.enigma_loadframe();
        this.enigma_clear_list();

        // refresh the list
        this.enigma_list();
    }

    return false;
};

// Keys/certs listing
rcube_webmail.prototype.enigma_list = function(page, reset_frame)
{
    if (this.is_framed())
        return parent.rcmail.enigma_list(page, reset_frame);

    var params = {'_a': 'list'},
      lock = this.set_busy(true, 'loading');

    this.env.current_page = page ? page : 1;

    if (this.env.search_request)
        params._q = this.env.search_request;
    if (page)
        params._p = page;

    this.enigma_clear_list(reset_frame);
    this.http_post('plugin.enigmakeys', params, lock);
};

// Change list page
rcube_webmail.prototype.enigma_list_page = function(page)
{
    if (page == 'next')
        page = this.env.current_page + 1;
    else if (page == 'last')
        page = this.env.pagecount;
    else if (page == 'prev' && this.env.current_page > 1)
        page = this.env.current_page - 1;
    else if (page == 'first' && this.env.current_page > 1)
        page = 1;

    this.enigma_list(page);
};

// Remove list rows
rcube_webmail.prototype.enigma_clear_list = function(reset_frame)
{
    if (reset_frame !== false)
        this.enigma_loadframe();

    if (this.keys_list)
        this.keys_list.clear(true);

    this.enable_command('plugin.enigma-key-delete', 'plugin.enigma-key-delete-selected', false);
};

// Adds a row to the list
rcube_webmail.prototype.enigma_add_list_row = function(r)
{
    if (!this.gui_objects.keyslist || !this.keys_list)
        return false;

    var list = this.keys_list,
        tbody = this.gui_objects.keyslist.tBodies[0],
        rowcount = tbody.rows.length,
        even = rowcount%2,
        css_class = 'message'
            + (even ? ' even' : ' odd'),
        // for performance use DOM instead of jQuery here
        row = document.createElement('tr'),
        col = document.createElement('td');

    row.id = 'rcmrow' + r.id;
    row.className = css_class;
    if (r.flags) $(row).data('flags', r.flags);

    col.innerHTML = r.name;
    row.appendChild(col);
    list.insert_row(row);
};


/*********************************************************/
/*********        Enigma Message methods         *********/
/*********************************************************/

// handle message send/save action
rcube_webmail.prototype.enigma_beforesend_handler = function(props)
{
    this.env.last_action = 'send';
    this.enigma_compose_handler(props);
};

rcube_webmail.prototype.enigma_beforesavedraft_handler = function(props)
{
    this.env.last_action = 'savedraft';
    this.enigma_compose_handler(props);
};

rcube_webmail.prototype.enigma_compose_handler = function(props)
{
    var form = this.gui_objects.messageform;

    // copy inputs from enigma menu to the form
    $('#enigmamenu input').each(function() {
        var id = this.id + '_cpy', input = $('#' + id);

        if (!input.length) {
            input = $(this).clone();
            input.prop({id: id, type: 'hidden'}).appendTo(form);
        }

        input.val(this.checked ? '1' : '');
    });

    // disable signing when saving drafts
    if (this.env.last_action == 'savedraft') {
        $('input[name="_enigma_sign"]', form).val(0);
    }
};

// Import attached keys/certs file
rcube_webmail.prototype.enigma_import_attachment = function(mime_id)
{
    var lock = this.set_busy(true, 'loading'),
        post = {_uid: this.env.uid, _mbox: this.env.mailbox, _part: mime_id};

    this.http_post('plugin.enigmaimport', post, lock);

    return false;
};

// password request popup
rcube_webmail.prototype.enigma_password_request = function(data)
{
    if (!data || !data.keyid) {
        return;
    }

    var ref = this,
        msg = this.get_label('enigma.enterkeypass'),
        myprompt = $('<div class="prompt">'),
        myprompt_content = $('<div class="message">')
            .appendTo(myprompt),
        myprompt_input = $('<input>').attr({type: 'password', size: 30})
            .keypress(function(e) {
                if (e.which == 13)
                    (ref.is_framed() ? window.parent.$ : $)('.ui-dialog-buttonpane button.mainaction:visible').click();
            })
            .appendTo(myprompt);

    data.key = data.keyid;
    if (data.keyid.length > 8)
        data.keyid = data.keyid.substr(data.keyid.length - 8);

    $.each(['keyid', 'user'], function() {
        msg = msg.replace('$' + this, data[this]);
    });

    myprompt_content.text(msg);

    this.show_popup_dialog(myprompt, this.get_label('enigma.enterkeypasstitle'),
        [{
            text: this.get_label('save'),
            'class': 'mainaction',
            click: function(e) {
                e.stopPropagation();

                var jq = ref.is_framed() ? window.parent.$ : $;

                data.password = myprompt_input.val();

                if (!data.password) {
                    myprompt_input.focus();
                    return;
                }

                ref.enigma_password_submit(data);
                jq(this).remove();
            }
        },
        {
            text: this.get_label('cancel'),
            click: function(e) {
                var jq = ref.is_framed() ? window.parent.$ : $;
                e.stopPropagation();
                jq(this).remove();
            }
        }], {width: 400});

    if (this.is_framed() && parent.rcmail.message_list) {
        // this fixes bug when pressing Enter on "Save" button in the dialog
        parent.rcmail.message_list.blur();
    }
};

// submit entered password
rcube_webmail.prototype.enigma_password_submit = function(data)
{
    var lock, form;

    if (this.env.action == 'compose' && !data['compose-init']) {
        return this.enigma_password_compose_submit(data);
    }
    else if (this.env.action == 'plugin.enigmakeys' && (form = this.gui_objects.importform)) {
        if (!$('input[name="_keyid"]', form).length) {
            $(form).append($('<input>').attr({type: 'hidden', name: '_keyid', value: data.key}))
                .append($('<input>').attr({type: 'hidden', name: '_passwd', value: data.password}))
        }

        return this.enigma_import();
    }

    lock = data.nolock ? null : this.set_busy(true, 'loading');
    form = $('<form>')
        .attr({method: 'post', action: data.action || location.href, style: 'display:none'})
        .append($('<input>').attr({type: 'hidden', name: '_keyid', value: data.key}))
        .append($('<input>').attr({type: 'hidden', name: '_passwd', value: data.password}))
        .append($('<input>').attr({type: 'hidden', name: '_token', value: this.env.request_token}))
        .append($('<input>').attr({type: 'hidden', name: '_unlock', value: lock}));

    // Additional form fields for request parameters
    $.each(data, function(i, v) {
        if (i.indexOf('input') == 0)
            form.append($('<input>').attr({type: 'hidden', name: i.substring(5), value: v}))
    });

    if (data.iframe) {
        var name = 'enigma_frame_' + (new Date()).getTime(),
            frame = $('<iframe>').attr({style: 'display:none', name: name}).appendTo(document.body);
        form.attr('target', name);
    }

    form.appendTo(document.body).submit();
};

// submit entered password - in mail compose page
rcube_webmail.prototype.enigma_password_compose_submit = function(data)
{
    var form = this.gui_objects.messageform;

    if (!$('input[name="_keyid"]', form).length) {
        $(form).append($('<input>').attr({type: 'hidden', name: '_keyid', value: data.key}))
            .append($('<input>').attr({type: 'hidden', name: '_passwd', value: data.password}));
    }
    else {
        $('input[name="_keyid"]', form).val(data.key);
        $('input[name="_passwd"]', form).val(data.password);
    }

    this.submit_messageform(this.env.last_action == 'savedraft');
};

// Display no-key error with key search button
rcube_webmail.prototype.enigma_key_not_found = function(data)
{
    return this.show_popup_dialog(
        data.text,
        data.title,
        [{
            text: data.button,
            click: function(e) {
                $(this).remove();
                rcmail.enigma_find_publickey(data.email);
            }
        }],
        {width: 400, dialogClass: 'error'}
    );
};

// Search for a public key on the key server
rcube_webmail.prototype.enigma_find_publickey = function(email)
{
    this.mailvelope_search_pubkeys([email],
        function(status) {},
        function(key) {
            var lock = rcmail.set_busy(true, 'enigma.importwait'),
                post = {_a: 'import', _keys: key};

            if (rcmail.env.action == 'plugin.enigmakeys')
                post._refresh = 1;

            // send request to server
            rcmail.http_post('plugin.enigmakeys', post, lock);
        }
    );
};
