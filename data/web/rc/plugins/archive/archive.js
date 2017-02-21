/**
 * Archive plugin script
 * @version 3.0
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

function rcmail_archive(prop)
{
  if (rcmail_is_archive())
    return;

  var post_data = rcmail.selection_post_data();

  // exit if selection is empty
  if (!post_data._uid)
    return;

  rcmail.show_contentframe(false);

  // Disable message command buttons until a message is selected
  rcmail.enable_command(rcmail.env.message_commands, false);
  rcmail.enable_command('plugin.archive', false);

  // let the server sort the messages to the according subfolders
  rcmail.with_selected_messages('move', post_data, null, 'plugin.move2archive');
}

function rcmail_is_archive()
{
  // check if current folder is an archive folder or one of its children
  return rcmail.env.mailbox == rcmail.env.archive_folder
    || rcmail.env.mailbox.startsWith(rcmail.env.archive_folder + rcmail.env.delimiter);
}

// callback for app-onload event
if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {
    // register command (directly enable in message view mode)
    rcmail.register_command('plugin.archive', rcmail_archive, rcmail.env.uid && !rcmail_is_archive());

    // add event-listener to message list
    if (rcmail.message_list)
      rcmail.message_list.addEventListener('select', function(list) {
        rcmail.enable_command('plugin.archive', list.get_selection().length > 0 && !rcmail_is_archive());
      });

    // set css style for archive folder
    var li;
    if (rcmail.env.archive_folder && (li = rcmail.get_folder_li(rcmail.env.archive_folder, '', true)))
      $(li).addClass('archive');
  });
}
