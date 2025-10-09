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
  $(".login-history").on('click', function(e) {e.preventDefault(); last_logins('get', $(this).data('days'));$(this).parent().find('li a').removeClass('active');$(this).children(':first-child').addClass('active')});

  function last_logins(action, days = 7) {
    if (action == 'get') {
      $('#spinner-last-login').removeClass('d-none');
      $.ajax({
        dataType: 'json',
        url: '/api/v1/get/last-login/' + encodeURIComponent(mailcow_cc_username) + '/' + days,
        jsonp: false,
        error: function () {
          console.log('error reading last logins');
        },
        success: function (data) {
          $('.last-sasl-login').html('');
          if (data.sasl) {
            $('.last-sasl-login').append('<ul class="list-group">');
            $.each(data.sasl, function (i, item) {
              var datetime = new Date(item.datetime.replace(/-/g, "/"));
              var local_datetime = datetime.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
              var service = '<div class="badge bg-secondary">' + item.service.toUpperCase() + '</div>';
              var app_password = item.app_password ? ' <a href="/edit/app-passwd/' + item.app_password + '"><i class="bi bi-key-fill"></i><span class="ms-1">' + escapeHtml(item.app_password_name || "App") + '</span></a>' : '';
              var real_rip = item.real_rip.startsWith("Web") ? item.real_rip : '<a href="https://bgp.tools/prefix/' + item.real_rip + '" target="_blank">' + item.real_rip + "</a>";
              var ip_location = item.location ? ' <span class="flag-icon flag-icon-' + item.location.toLowerCase() + '"></span>' : '';
              var ip_data = real_rip + ip_location + app_password;

              $(".last-sasl-login").append(`
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto d-flex flex-column">
                    <div class="fw-bold">` + ip_location + real_rip + `</div>
                    <small class="fst-italic mt-2">` + service + ` ` + local_datetime + `</small>` + app_password + `
                  </div>
                </li>
              `);
            })
            $('.last-sasl-login').append('</ul>');
          }

          $('#spinner-last-login').addClass('d-none');
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


  function createSortableDate(td, cellData, date_string = false) {
    if (date_string)
      var date = new Date(cellData);
    else
      var date = new Date(cellData ? cellData * 1000 : 0);

    var timestamp = date.getTime();
    $(td).attr({
      "data-order": timestamp,
      "data-sort": timestamp
    });
    $(td).html(date.toLocaleDateString(LOCALE, DATETIME_FORMAT));
  }
  function draw_tla_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#tla_table') ) {
      $('#tla_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#tla_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[4, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/time_limited_aliases",
        dataSrc: function(data){
          $.each(data, function (i, item) {
            if (acl_data.spam_alias === 1) {
              item.action = '<div class="btn-group">' +
                '<a href="#" data-action="delete_selected" data-id="single-tla" data-api-url="delete/time_limited_alias" data-item="' + encodeURIComponent(item.address) + '" class="btn btn-xs btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="tla" name="multi_select" value="' + encodeURIComponent(item.address) + '" />';
              item.address = escapeHtml(item.address);
            }
            else {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled />';
              item.action = '<span>-</span>';
            }
          });

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: lang.alias,
          data: 'address',
          defaultContent: ''
        },
        {
          title: lang.description,
          data: 'description',
          defaultContent: '',
          render: function (data, type) {
            return escapeHtml(data);
          }
        },
        {
          title: lang.alias_valid_until,
          data: 'validity',
          defaultContent: '',
          createdCell: function(td, cellData) {
            createSortableDate(td, cellData)
          }
        },
        {
          title: lang.created_on,
          data: 'created',
          defaultContent: '',
          createdCell: function(td, cellData) {
            createSortableDate(td, cellData, true)
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_sync_job_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#sync_job_table') ) {
      $('#sync_job_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#sync_job_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: '/api/v1/get/syncjobs/' + encodeURIComponent(mailcow_cc_username) + '/no_log',
        dataSrc: function(data){
          $.each(data, function (i, item) {
            item.user1 = escapeHtml(item.user1);
            item.log = '<a href="#syncjobLogModal" data-bs-toggle="modal" data-syncjob-id="' + item.id + '">' + lang.open_logs + '</a>'
            if (!item.exclude > 0) {
              item.exclude = '-';
            } else {
              item.exclude  = '<code>' + escapeHtml(item.exclude) + '</code>';
            }
            item.server_w_port = escapeHtml(item.user1 + '@' + item.host1 + ':' + item.port1);
            if (acl_data.syncjobs === 1) {
              item.action = '<div class="btn-group">' +
                '<a href="/edit/syncjob/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-syncjob" data-api-url="delete/syncjob" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="syncjob" name="multi_select" value="' + item.id + '" />';
            }
            else {
              item.action = '<span>-</span>';
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled />';
            }
            if (item.is_running == 1) {
              item.is_running = '<span id="active-script" class="badge fs-6 bg-success">' + lang.running + '</span>';
            } else {
              item.is_running = '<span id="inactive-script" class="badge fs-6 bg-warning">' + lang.waiting + '</span>';
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

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          defaultContent: '',
          responsivePriority: 3
        },
        {
          title: 'Server',
          data: 'server_w_port',
          defaultContent: ''
        },
        {
          title: lang.username,
          data: 'user1',
          defaultContent: '',
          responsivePriority: 3
        },
        {
          title: lang.last_run,
          data: 'last_run',
          defaultContent: ''
        },
        {
          title: lang.syncjob_last_run_result,
          data: 'exit_status',
          defaultContent: ''
        },
        {
          title: 'Log',
          data: 'log',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>'
          }
        },
        {
          title: lang.status,
          data: 'is_running',
          defaultContent: '',
          responsivePriority: 5
        },
        {
          title: lang.encryption,
          data: 'enc1',
          defaultContent: ''
        },
        {
          title: lang.excludes,
          data: 'exclude',
          defaultContent: ''
        },
        {
          title: lang.interval + " (min)",
          data: 'mins_interval',
          defaultContent: ''
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: '',
          responsivePriority: 5
        }
      ]
    });
  }
  function draw_app_passwd_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#app_passwd_table') ) {
      $('#app_passwd_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#app_passwd_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: '/api/v1/get/app-passwd/all',
        dataSrc: function(data){
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
              item.action = '<div class="btn-group">' +
                '<a href="/edit/app-passwd/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-apppasswd" data-api-url="delete/app-passwd" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="apppasswd" name="multi_select" value="' + item.id + '" />';
            }
            else {
              item.action = '<span>-</span>';
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled />';
            }
          });

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'id',
          defaultContent: ''
        },
        {
          title: lang.app_name,
          data: 'name',
          defaultContent: ''
        },
        {
          title: lang.allowed_protocols,
          data: 'protocols',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>'
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_wl_policy_mailbox_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#wl_policy_mailbox_table') ) {
      $('#wl_policy_mailbox_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#wl_policy_mailbox_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: '/api/v1/get/policy_wl_mailbox',
        dataSrc: function(data){
          $.each(data, function (i, item) {
            if (validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="policy_wl_mailbox" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled title="' + lang.spamfilter_table_domain_policy + '" />';
            }
            if (acl_data.spam_policy === 0) {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled />';
            }
          });

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'prefid',
          defaultContent: ''
        },
        {
          title: lang.spamfilter_table_rule,
          data: 'value',
          defaultContent: ''
        },
        {
          title:'Scope',
          data: 'object',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_bl_policy_mailbox_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#bl_policy_mailbox_table') ) {
      $('#bl_policy_mailbox_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#bl_policy_mailbox_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: '/api/v1/get/policy_bl_mailbox',
        dataSrc: function(data){
          $.each(data, function (i, item) {
            if (validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="policy_bl_mailbox" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled tooltip="' + lang.spamfilter_table_domain_policy + '" />';
            }
            if (acl_data.spam_policy === 0) {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled />';
            }
          });

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'prefid',
          defaultContent: ''
        },
        {
          title: lang.spamfilter_table_rule,
          data: 'value',
          defaultContent: ''
        },
        {
          title:'Scope',
          data: 'object',
          defaultContent: ''
        }
      ]
    });
  }

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

  // detect element visibility changes
  function onVisible(element, callback) {
    $(document).ready(function() {
      element_object = document.querySelector(element);
      if (element_object === null) return;

      new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if(entry.intersectionRatio > 0) {
            callback(element_object);
          }
        });
      }).observe(element_object);
    });
  }

  // Load only if the tab is visible
  onVisible("[id^=tla_table]", () => draw_tla_table());
  onVisible("[id^=bl_policy_mailbox_table]", () => draw_bl_policy_mailbox_table());
  onVisible("[id^=wl_policy_mailbox_table]", () => draw_wl_policy_mailbox_table());
  onVisible("[id^=sync_job_table]", () => draw_sync_job_table());
  onVisible("[id^=app_passwd_table]", () => draw_app_passwd_table());
  onVisible("[id^=recent-logins]", () => last_logins('get'));
});
