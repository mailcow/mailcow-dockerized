/* Show user-info plugin script */

if (window.rcmail) {
  rcmail.addEventListener('init', function() {
    // <span id="settingstabdefault" class="tablink"><roundcube:button command="preferences" type="link" label="preferences" title="editpreferences" /></span>
    var tab = $('<span>').attr('id', 'settingstabpluginuserinfo').addClass('tablink');

    $('<a>').attr('href', rcmail.env.comm_path + '&_action=plugin.userinfo')
      .text(rcmail.get_label('userinfo', 'userinfo'))
      .click(function(e) { return rcmail.command('plugin.userinfo', '', this, e); })
      .appendTo(tab);

    // add button and register command
    rcmail.add_element(tab, 'tabs');
    rcmail.register_command('plugin.userinfo', function() { rcmail.goto_url('plugin.userinfo') }, true);
  })
}

