/**
 * Help plugin client script
 * @version 1.4
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

// hook into switch-task event to open the help window
if (window.rcmail) {
    rcmail.addEventListener('beforeswitch-task', function(prop) {
        // catch clicks to help task button
        if (prop == 'help') {
            if (rcmail.task == 'help')  // we're already there
                return false;

            var url = rcmail.url('help/index', { _rel: rcmail.task + (rcmail.env.action ? '/'+rcmail.env.action : '') });
            if (rcmail.env.help_open_extwin) {
                rcmail.open_window(url, 1020, false);
            }
            else {
                rcmail.redirect(url, false);
            }

            return false;
      }
  });
}
