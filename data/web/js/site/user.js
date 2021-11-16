// Base64 functions
var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(r){var t,e,o,a,h,n,c,d="",C=0;for(r=Base64._utf8_encode(r);C<r.length;)a=(t=r.charCodeAt(C++))>>2,h=(3&t)<<4|(e=r.charCodeAt(C++))>>4,n=(15&e)<<2|(o=r.charCodeAt(C++))>>6,c=63&o,isNaN(e)?n=c=64:isNaN(o)&&(c=64),d=d+this._keyStr.charAt(a)+this._keyStr.charAt(h)+this._keyStr.charAt(n)+this._keyStr.charAt(c);return d},decode:function(r){var t,e,o,a,h,n,c="",d=0;for(r=r.replace(/[^A-Za-z0-9\+\/\=]/g,"");d<r.length;)t=this._keyStr.indexOf(r.charAt(d++))<<2|(a=this._keyStr.indexOf(r.charAt(d++)))>>4,e=(15&a)<<4|(h=this._keyStr.indexOf(r.charAt(d++)))>>2,o=(3&h)<<6|(n=this._keyStr.indexOf(r.charAt(d++))),c+=String.fromCharCode(t),64!=h&&(c+=String.fromCharCode(e)),64!=n&&(c+=String.fromCharCode(o));return c=Base64._utf8_decode(c)},_utf8_encode:function(r){r=r.replace(/\r\n/g,"\n");for(var t="",e=0;e<r.length;e++){var o=r.charCodeAt(e);o<128?t+=String.fromCharCode(o):o>127&&o<2048?(t+=String.fromCharCode(o>>6|192),t+=String.fromCharCode(63&o|128)):(t+=String.fromCharCode(o>>12|224),t+=String.fromCharCode(o>>6&63|128),t+=String.fromCharCode(63&o|128))}return t},_utf8_decode:function(r){for(var t="",e=0,o=c1=c2=0;e<r.length;)(o=r.charCodeAt(e))<128?(t+=String.fromCharCode(o),e++):o>191&&o<224?(c2=r.charCodeAt(e+1),t+=String.fromCharCode((31&o)<<6|63&c2),e+=2):(c2=r.charCodeAt(e+1),c3=r.charCodeAt(e+2),t+=String.fromCharCode((15&o)<<12|(63&c2)<<6|63&c3),e+=3);return t}};
$(document).ready(function() {
  // Spam score slider
  var spam_slider = $('#spam_score')[0];
  if (typeof spam_slider !== 'undefined') {
    noUiSlider.create(spam_slider, {
      start: user_spam_score,
      connect: [true, true, true],
      range: {
        'min': [0], //stepsize is 50.000
        '50%': [10],
        '70%': [20, 5],
        '80%': [50, 10],
        '90%': [100, 100],
        '95%': [1000, 1000],
        'max': [5000]
      },
    });
    var connect = spam_slider.querySelectorAll('.noUi-connect');
    var classes = ['c-1-color', 'c-2-color', 'c-3-color'];
    for (var i = 0; i < connect.length; i++) {
      connect[i].classList.add(classes[i]);
    }
    spam_slider.noUiSlider.on('update', function (values, handle) {
      $('.spam-ham-score').text('< ' + Math.round(values[0] * 10) / 10);
      $('.spam-spam-score').text(Math.round(values[0] * 10) / 10 + ' - ' + Math.round(values[1] * 10) / 10);
      $('.spam-reject-score').text('> ' + Math.round(values[1] * 10) / 10);
      $('#spam_score_value').val((Math.round(values[0] * 10) / 10) + ',' + (Math.round(values[1] * 10) / 10));
    });
  }
  // syncjobLogModal
  $('#syncjobLogModal').on('show.bs.modal', function(e) {
    var syncjob_id = $(e.relatedTarget).data('syncjob-id');
    $.ajax({
      url: '/inc/ajax/syncjob_logs.php',
      data: { id: syncjob_id },
      dataType: 'text',
      success: function(data){
        $(e.currentTarget).find('#logText').text(data);
      },
      error: function(xhr, status, error) {
        $(e.currentTarget).find('#logText').text(xhr.responseText);
      }
    });
  });
  $(".arrow-toggle").on('click', function(e) { e.preventDefault(); $(this).find('.arrow').toggleClass("animation"); });
  $("#pushover_delete").click(function() { return confirm(lang.delete_ays); });

});
jQuery(function($){
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
  };
  function escapeHtml(string) {
    return String(string).replace(/[&<>"'`=\/]/g, function (s) {
      return entityMap[s];
    });
  }
  // http://stackoverflow.com/questions/46155/validate-email-address-in-javascript
  function validateEmail(email) {
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
  }
  function unix_time_format(tm) {
    var date = new Date(tm ? tm * 1000 : 0);
    return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
  }
  acl_data = JSON.parse(acl);

  $('.clear-last-logins').on('click', function () {if (confirm(lang.delete_ays)) {last_logins('reset');}})
  $(".login-history").on('click', function(e) {e.preventDefault(); last_logins('get', $(this).data('days'));$(this).addClass('active').siblings().removeClass('active');});

  function last_logins(action, days = 7) {
    if (action == 'get') {
      $('.last-login').html('<i class="bi bi-hourglass"></i>' +  lang.waiting);
      $.ajax({
        dataType: 'json',
        url: '/api/v1/get/last-login/' + encodeURIComponent(mailcow_cc_username) + '/' + days,
        jsonp: false,
        error: function () {
          console.log('error reading last logins');
        },
        success: function (data) {
          $('.last-login').html();
          if (data.ui.time) {
            $('.last-login').html('<i class="bi bi-person-fill"></i> ' + lang.last_ui_login + ': ' + unix_time_format(data.ui.time));
          } else {
            $('.last-login').text(lang.no_last_login);
          }
          if (data.sasl) {
            $('.last-login').append('<ul class="list-group">');
            $.each(data.sasl, function (i, item) {
              var datetime = new Date(item.datetime.replace(/-/g, "/"));
              var local_datetime = datetime.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
              var service = '<div class="label label-default">' + item.service.toUpperCase() + '</div>';
              var app_password = item.app_password ? ' <a href="/edit/app-passwd/' + item.app_password + '"><i class="bi bi-app-indicator"></i> ' + escapeHtml(item.app_password_name || "App") + '</a>' : '';
              var real_rip = item.real_rip.startsWith("Web") ? item.real_rip : '<a href="https://bgp.he.net/ip/' + item.real_rip + '" target="_blank">' + item.real_rip + "</a>";
              var ip_location = item.location ? ' <span class="flag-icon flag-icon-' + item.location.toLowerCase() + '"></span>' : '';
              var ip_data = real_rip + ip_location + app_password;
              $(".last-login").append('<li class="list-group-item">' + local_datetime + " " + service + " " + lang.from + " " + ip_data + "</li>");
            })
            $('.last-login').append('</ul>');
          }
        }
      })
    } else if (action == 'reset') {
      $.ajax({
        dataType: 'json',
        url: '/api/v1/get/reset-last-login/' + encodeURIComponent(mailcow_cc_username),
        jsonp: false,
        error: function () {
          console.log('cannot reset last logins');
        },
        success: function (data) {
          last_logins('get');
        }
      })
    }
  }

  function draw_tla_table() {
    ft_tla_table = FooTable.init('#tla_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"min-width":"60px","width":"60px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"address","title":lang.alias},
        {"name":"validity","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});},"title":lang.alias_valid_until,"style":{"width":"170px"}},
        {"sorted": true,"sortValue": function(value){res = new Date(value);return res.getTime();},"direction":"DESC","name":"created","formatter":function date_format(datetime) { var date = new Date(datetime.replace(/-/g, "/")); return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});},"title":lang.created_on,"style":{"width":"170px"}},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","min-width":"220px","width":"220px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/time_limited_aliases',
        jsonp: false,
        error: function () {
          console.log('Cannot draw tla table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            if (acl_data.spam_alias === 1) {
              item.action = '<div class="btn-group footable-actions">' +
                '<a href="#" data-action="delete_selected" data-id="single-tla" data-api-url="delete/time_limited_alias" data-item="' + encodeURIComponent(item.address) + '" class="btn btn-xs btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" data-id="tla" name="multi_select" value="' + encodeURIComponent(item.address) + '" />';
              item.address = escapeHtml(item.address);
            }
            else {
              item.chkbox = '<input type="checkbox" disabled />';
              item.action = '<span>-</span>';
            }
          });
        }
      }),
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": pagination_size
      },
      "state": {"enabled": true},
      "sorting": {
        "enabled": true
      },
      "toggleSelector": "table tbody span.footable-toggle"
    });
  }
  function draw_sync_job_table() {
    ft_syncjob_table = FooTable.init('#sync_job_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"min-width":"60px","width":"60px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"id","title":"ID","style":{"maxWidth":"60px","width":"60px","text-align":"center"}},
        {"name":"server_w_port","title":"Server","breakpoints":"xs sm md","style":{"word-break":"break-all"}},
        {"name":"enc1","title":lang.encryption,"breakpoints":"all"},
        {"name":"user1","title":lang.username},
        {"name":"exclude","title":lang.excludes,"breakpoints":"all"},
        {"name":"mins_interval","title":lang.interval + " (min)","breakpoints":"all"},
        {"name":"last_run","title":lang.last_run,"breakpoints":"xs sm md"},
        {"name":"exit_status","filterable": false,"title":lang.syncjob_last_run_result},
        {"name":"log","title":"Log"},
        {"name":"active","filterable": false,"style":{"maxWidth":"70px","width":"70px"},"title":lang.active,"formatter": function(value){return 1==value?'<i class="bi bi-check-lg"></i>':0==value&&'<i class="bi bi-x-lg"></i>';}},
        {"name":"is_running","filterable": false,"style":{"maxWidth":"120px","width":"100px"},"title":lang.status},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","min-width":"260px","width":"260px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/syncjobs/' + encodeURIComponent(mailcow_cc_username) + '/no_log',
        jsonp: false,
        error: function () {
          console.log('Cannot draw sync job table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.user1 = escapeHtml(item.user1);
            item.log = '<a href="#syncjobLogModal" data-toggle="modal" data-syncjob-id="' + item.id + '">' + lang.open_logs + '</a>'
            if (!item.exclude > 0) {
              item.exclude = '-';
            } else {
              item.exclude  = '<code>' + escapeHtml(item.exclude) + '</code>';
            }
            item.server_w_port = escapeHtml(item.user1 + '@' + item.host1 + ':' + item.port1);
            if (acl_data.syncjobs === 1) {
              item.action = '<div class="btn-group footable-actions">' +
                '<a href="/edit/syncjob/' + item.id + '" class="btn btn-xs btn-xs-half btn-default"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-syncjob" data-api-url="delete/syncjob" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" data-id="syncjob" name="multi_select" value="' + item.id + '" />';
            }
            else {
              item.action = '<span>-</span>';
              item.chkbox = '<input type="checkbox" disabled />';
            }
            if (item.is_running == 1) {
              item.is_running = '<span id="active-script" class="label label-success">' + lang.running + '</span>';
            } else {
              item.is_running = '<span id="inactive-script" class="label label-warning">' + lang.waiting + '</span>';
            }
            if (!item.last_run > 0) {
              item.last_run = lang.waiting;
            }
            if (item.success == null) {
              item.success = '-';
              item.exit_status = '';
            } else {
              item.success = '<i class="text-' + (item.success == 1 ? 'success' : 'danger') + ' bi bi-' + (item.success == 1 ? 'check-lg' : 'x-lg') + '"></i>';
            }
            if (lang['syncjob_'+item.exit_status]) {
	            item.exit_status = lang['syncjob_'+item.exit_status];
            } else if (item.success != '-') {
	            item.exit_status = lang.syncjob_check_log;
            }
            item.exit_status = item.success + ' ' + item.exit_status;
          });
        }
      }),
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": pagination_size
      },
      "state": {"enabled": true},
      "sorting": {
        "enabled": true
      },
      "toggleSelector": "table tbody span.footable-toggle"
    });
  }
  function draw_app_passwd_table() {
    ft_apppasswd_table = FooTable.init('#app_passwd_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"id","title":"ID","style":{"maxWidth":"60px","width":"60px","text-align":"center"}},
        {"name":"name","title":lang.app_name},
        {"name":"protocols","title":lang.allowed_protocols},
        {"name":"active","filterable": false,"style":{"maxWidth":"70px","width":"70px"},"title":lang.active,"formatter": function(value){return 1==value?'<i class="bi bi-check-lg"></i>':0==value&&'<i class="bi bi-x-lg"></i>';}},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","min-width":"220px","width":"220px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/app-passwd/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw app passwd table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.name = escapeHtml(item.name)
            item.protocols = []
            if (item.imap_access == 1) { item.protocols.push("<code>IMAP</code>"); }
            if (item.smtp_access == 1) { item.protocols.push("<code>SMTP</code>"); }
            if (item.eas_access == 1) { item.protocols.push("<code>EAS/ActiveSync</code>"); }
            if (item.dav_access == 1) { item.protocols.push("<code>DAV</code>"); }
            if (item.pop3_access == 1) { item.protocols.push("<code>POP3</code>"); }
            if (item.sieve_access == 1) { item.protocols.push("<code>Sieve</code>"); }
            item.protocols = item.protocols.join(" ")
            if (acl_data.app_passwds === 1) {
              item.action = '<div class="btn-group footable-actions">' +
                '<a href="/edit/app-passwd/' + item.id + '" class="btn btn-xs btn-xs-half btn-default"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-apppasswd" data-api-url="delete/app-passwd" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" data-id="apppasswd" name="multi_select" value="' + item.id + '" />';
            }
            else {
              item.action = '<span>-</span>';
              item.chkbox = '<input type="checkbox" disabled />';
            }
          });
        }
      }),
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": pagination_size
      },
      "state": {"enabled": true},
      "sorting": {
        "enabled": true
      },
      "toggleSelector": "table tbody span.footable-toggle"
    });
  }
  function draw_wl_policy_mailbox_table() {
    ft_wl_policy_mailbox_table = FooTable.init('#wl_policy_mailbox_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"prefid","style":{"maxWidth":"40px","width":"40px"},"title":"ID","filterable": false,"sortable": false},
        {"sorted": true,"name":"value","title":lang.spamfilter_table_rule},
        {"name":"object","title":"Scope"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/policy_wl_mailbox',
        jsonp: false,
        error: function () {
          console.log('Cannot draw mailbox policy wl table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            if (validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" data-id="policy_wl_mailbox" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" disabled title="' + lang.spamfilter_table_domain_policy + '" />';
            }
            if (acl_data.spam_policy === 0) {
              item.chkbox = '<input type="checkbox" disabled />';
            }
          });
        }
      }),
      "state": {"enabled": true},
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": pagination_size
      },
      "sorting": {
        "enabled": true
      }
    });
  }
  function draw_bl_policy_mailbox_table() {
    ft_bl_policy_mailbox_table = FooTable.init('#bl_policy_mailbox_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"prefid","style":{"maxWidth":"40px","width":"40px"},"title":"ID","filterable": false,"sortable": false},
        {"sorted": true,"name":"value","title":lang.spamfilter_table_rule},
        {"name":"object","title":"Scope"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/policy_bl_mailbox',
        jsonp: false,
        error: function () {
          console.log('Cannot draw mailbox policy bl table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            if (validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" data-id="policy_bl_mailbox" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" disabled tooltip="' + lang.spamfilter_table_domain_policy + '" />';
            }
            if (acl_data.spam_policy === 0) {
              item.chkbox = '<input type="checkbox" disabled />';
            }
          });
        }
      }),
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": pagination_size
      },
      "state": {"enabled": true},
      "sorting": {
        "enabled": true
      }
    });
  }

  $('body').on('click', 'span.footable-toggle', function () {
    event.stopPropagation();
  })

  draw_sync_job_table();
  draw_app_passwd_table();
  draw_tla_table();
  draw_wl_policy_mailbox_table();
  draw_bl_policy_mailbox_table();
  last_logins('get');

  // FIDO2 friendly name modal
  $('#fido2ChangeFn').on('show.bs.modal', function (e) {
    rename_link = $(e.relatedTarget)
    if (rename_link != null) {
      $('#fido2_cid').val(rename_link.data('cid'));
      $('#fido2_subject_desc').text(Base64.decode(rename_link.data('subject')));
    }
  })

  // Sieve data modal
  $('#userFilterModal').on('show.bs.modal', function(e) {
    $('#user_sieve_filter').text(lang.loading);
    $.ajax({
      dataType: 'json',
      url: '/api/v1/get/active-user-sieve/' + encodeURIComponent(mailcow_cc_username),
      jsonp: false,
      error: function () {
        console.log('Cannot get active sieve script');
      },
      complete: function (data) {
        if (data.responseText == '{}') {
          $('#user_sieve_filter').text(lang.no_active_filter);
        } else {
          $('#user_sieve_filter').text(JSON.parse(data.responseText));
        }
      }
    })
  });
  $('#userFilterModal').on('hidden.bs.modal', function () {
    $('#user_sieve_filter').text(lang.loading);
  });
});
