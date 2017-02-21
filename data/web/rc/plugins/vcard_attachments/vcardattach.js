/**
 * vcard_attachments plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (c) 2012-2016, The Roundcube Dev Team
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

function plugin_vcard_save_contact(mime_id)
{
  var lock = rcmail.set_busy(true, 'loading');
  rcmail.http_post('plugin.savevcard', { _uid: rcmail.env.uid, _mbox: rcmail.env.mailbox, _part: mime_id }, lock);

  return false;
}

function plugin_vcard_insertrow(data)
{
  var ctype = data.row.ctype;

  if (ctype == 'text/vcard' || ctype == 'text/x-vcard' || ctype == 'text/directory') {
    $('#rcmrow' + rcmail.html_identifier(data.uid, true) + ' > td.attachment')
      .html('<img src="' + rcmail.env.vcard_icon + '" alt="" />');
  }
}

function plugin_vcard_attach()
{
  var id, n, contacts = [],
    ts = new Date().getTime(),
    args = {_uploadid: ts, _id: rcmail.env.compose_id};

  for (n=0; n < rcmail.contact_list.selection.length; n++) {
    id = rcmail.contact_list.selection[n];
    if (id && id.charAt(0) != 'E' && rcmail.env.contactdata[id])
      contacts.push(id);
  }

  if (!contacts.length)
    return false;

  args._uri = 'vcard://' + contacts.join(',');

  // add to attachments list
  if (!rcmail.add2attachment_list(ts, {name: '', html: rcmail.get_label('attaching'), classname: 'uploading', complete: false}))
    rcmail.file_upload_id = rcmail.set_busy(true, 'attaching');

  rcmail.http_post('upload', args);
}

window.rcmail && rcmail.addEventListener('init', function(evt) {
  if (rcmail.gui_objects.messagelist)
    rcmail.addEventListener('insertrow', function(data, evt) { plugin_vcard_insertrow(data); });

  if (rcmail.env.action == 'compose' && rcmail.gui_objects.contactslist) {
    rcmail.env.compose_commands.push('attach-vcard');
    rcmail.register_command('attach-vcard', function() { plugin_vcard_attach(); });
    rcmail.contact_list.addEventListener('select', function(list) {
      // TODO: support attaching more than one at once
      rcmail.enable_command('attach-vcard', list.selection.length == 1 && rcmail.contact_list.selection[0].charAt(0) != 'E');
    });
  }
});
