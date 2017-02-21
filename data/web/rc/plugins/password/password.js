/**
 * Password plugin script
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

window.rcmail && rcmail.addEventListener('init', function(evt) {
    if (rcmail.env.password_disabled) {
        $('#password-form input').prop('disabled', true);
        // reload page after ca. 3 minutes
        rcmail.reload(3 * 60 * 1000 - 2000);
        return;
    }

    // register command handler
    rcmail.register_command('plugin.password-save', function() {
        var input_curpasswd = rcube_find_object('_curpasswd'),
            input_newpasswd = rcube_find_object('_newpasswd'),
            input_confpasswd = rcube_find_object('_confpasswd');

      if (input_curpasswd && input_curpasswd.value == '') {
          alert(rcmail.get_label('nocurpassword', 'password'));
          input_curpasswd.focus();
      }
      else if (input_newpasswd && input_newpasswd.value == '') {
          alert(rcmail.get_label('nopassword', 'password'));
          input_newpasswd.focus();
      }
      else if (input_confpasswd && input_confpasswd.value == '') {
          alert(rcmail.get_label('nopassword', 'password'));
          input_confpasswd.focus();
      }
      else if (input_newpasswd && input_confpasswd && input_newpasswd.value != input_confpasswd.value) {
          alert(rcmail.get_label('passwordinconsistency', 'password'));
          input_newpasswd.focus();
      }
      else {
          rcmail.gui_objects.passform.submit();
      }
    }, true);

    $('input:not(:hidden):first').focus();
});
