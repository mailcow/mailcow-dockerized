// Base64 functions
var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(r){var t,e,o,a,h,n,c,d="",C=0;for(r=Base64._utf8_encode(r);C<r.length;)a=(t=r.charCodeAt(C++))>>2,h=(3&t)<<4|(e=r.charCodeAt(C++))>>4,n=(15&e)<<2|(o=r.charCodeAt(C++))>>6,c=63&o,isNaN(e)?n=c=64:isNaN(o)&&(c=64),d=d+this._keyStr.charAt(a)+this._keyStr.charAt(h)+this._keyStr.charAt(n)+this._keyStr.charAt(c);return d},decode:function(r){var t,e,o,a,h,n,c="",d=0;for(r=r.replace(/[^A-Za-z0-9\+\/\=]/g,"");d<r.length;)t=this._keyStr.indexOf(r.charAt(d++))<<2|(a=this._keyStr.indexOf(r.charAt(d++)))>>4,e=(15&a)<<4|(h=this._keyStr.indexOf(r.charAt(d++)))>>2,o=(3&h)<<6|(n=this._keyStr.indexOf(r.charAt(d++))),c+=String.fromCharCode(t),64!=h&&(c+=String.fromCharCode(e)),64!=n&&(c+=String.fromCharCode(o));return c=Base64._utf8_decode(c)},_utf8_encode:function(r){r=r.replace(/\r\n/g,"\n");for(var t="",e=0;e<r.length;e++){var o=r.charCodeAt(e);o<128?t+=String.fromCharCode(o):o>127&&o<2048?(t+=String.fromCharCode(o>>6|192),t+=String.fromCharCode(63&o|128)):(t+=String.fromCharCode(o>>12|224),t+=String.fromCharCode(o>>6&63|128),t+=String.fromCharCode(63&o|128))}return t},_utf8_decode:function(r){for(var t="",e=0,o=c1=c2=0;e<r.length;)(o=r.charCodeAt(e))<128?(t+=String.fromCharCode(o),e++):o>191&&o<224?(c2=r.charCodeAt(e+1),t+=String.fromCharCode((31&o)<<6|63&c2),e+=2):(c2=r.charCodeAt(e+1),c3=r.charCodeAt(e+2),t+=String.fromCharCode((15&o)<<12|(63&c2)<<6|63&c3),e+=3);return t}};
jQuery(function($){
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function jq(myid) {return "#" + myid.replace( /(:|\.|\[|\]|,|=|@)/g, "\\$1" );}
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  function validateRegex(e){var t=e.split("/"),n=e,r="";t.length>1&&(n=t[1],r=t[2]);try{return new RegExp(n,r),!0}catch(e){return!1}}
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  function hashCode(t){for(var n=0,r=0;r<t.length;r++)n=t.charCodeAt(r)+((n<<5)-n);return n}
  function intToRGB(t){var n=(16777215&t).toString(16).toUpperCase();return"00000".substring(0,6-n.length)+n}
  $("#dkim_missing_keys").on('click', function(e) {
    e.preventDefault();
     var domains = [];
     $('.dkim_missing').each(function() {
       domains.push($(this).val());
     });
     $('#dkim_add_domains').val(domains);
  });
  $(".arrow-toggle").on('click', function(e) { e.preventDefault(); $(this).find('.arrow').toggleClass("animation"); });
  $("#mass_exclude").change(function(){ $("#mass_include").selectpicker('deselectAll'); });
  $("#mass_include").change(function(){ $("#mass_exclude").selectpicker('deselectAll'); });
  $("#mass_disarm").click(function() { $("#mass_send").attr("disabled", !this.checked); });
  $(".admin-ays-dialog").click(function() { return confirm(lang.ays); });
  $(".validate_rspamd_regex").click(function( event ) {
    event.preventDefault();
    var regex_map_id = $(this).data('regex-map');
    var regex_data = $(jq(regex_map_id)).val().split(/\r?\n/);
    var regex_valid = true;
    for(var i = 0;i < regex_data.length;i++){
      if(regex_data[i].startsWith('#') || !regex_data[i]){
        continue;
      }
      if(!validateRegex(regex_data[i])) {
        mailcow_alert_box('Cannot build regex from line ' + (i+1), 'danger');
        var regex_valid = false;
        break;
      }
      if(!regex_data[i].startsWith('/') || !/\/[ims]?$/.test(regex_data[i])){
        mailcow_alert_box('Line ' + (i+1) + ' is invalid', 'danger');
        var regex_valid = false;
        break;
      }
    }
    if (regex_valid) {
      mailcow_alert_box('Regex OK', 'success');
      $('button[data-id="' + regex_map_id + '"]').attr({"disabled": false});
    }
  });
  $('.textarea-code').on('keyup', function() {
    $('.submit_rspamd_regex').attr({"disabled": true});
  });
  $("#show_rspamd_global_filters").click(function() {
    $.get("inc/ajax/show_rspamd_global_filters.php");
    $("#confirm_show_rspamd_global_filters").hide();
    $("#rspamd_global_filters").removeClass("d-none");
  });
  $("#super_delete").click(function() { return confirm(lang.queue_ays); });

  $(".refresh_table").on('click', function(e) {
    e.preventDefault();
    var table_name = $(this).data('table');
    $('#' + table_name).DataTable().ajax.reload();
  });
  function draw_domain_admins() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#domainadminstable') ) {
      $('#domainadminstable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#domainadminstable').DataTable({
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
        url: "/api/v1/get/domain-admin/all",
        dataSrc: function(data){
          return process_table_data(data, 'domainadminstable');
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
          title: lang.username,
          data: 'username',
          defaultContent: ''
        },
        {
          title: lang.admin_domains,
          data: 'selected_domains',
          defaultContent: '',
        },
        {
          title: "TFA",
          data: 'tfa_active',
          defaultContent: '',
            render: function (data, type) {
            if(data == 1) return '<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>';
            else return '<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            if(data == 1) return '<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>';
            else return '<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        },
      ],
      initComplete: function(settings, json){
      }
    });
  }
  function draw_oauth2_clients() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#oauth2clientstable') ) {
      $('#oauth2clientstable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#oauth2clientstable').DataTable({
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
        url: "/api/v1/get/oauth2-client/all",
        dataSrc: function(data){
          return process_table_data(data, 'oauth2clientstable');
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
          title: lang.oauth2_client_id,
          data: 'client_id',
          defaultContent: ''
        },
        {
          title: lang.oauth2_client_secret,
          data: 'client_secret',
          defaultContent: ''
        },
        {
          title: lang.oauth2_redirect_uri,
          data: 'redirect_uri',
          defaultContent: ''
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        },
      ]
    });
  }
  function draw_admins() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#adminstable') ) {
      $('#adminstable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#adminstable').DataTable({
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
        url: "/api/v1/get/admin/all",
        dataSrc: function(data){
          return process_table_data(data, 'adminstable');
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
          title: lang.username,
          data: 'username',
          defaultContent: ''
        },
        {
          title: "TFA",
          data: 'tfa_active',
          defaultContent: '',
          render: function (data, type) {
            if(data == 1) return '<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>';
            else return '<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            if(data == 1) return '<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>';
            else return '<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          defaultContent: '',
          className: 'dt-sm-head-hidden dt-text-right'
        },
      ]
    });
  }
  function draw_fwd_hosts() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#forwardinghoststable') ) {
      $('#forwardinghoststable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#forwardinghoststable').DataTable({
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
        url: "/api/v1/get/fwdhost/all",
        dataSrc: function(data){
          return process_table_data(data, 'forwardinghoststable');
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
          title: lang.host,
          data: 'host',
          defaultContent: ''
        },
        {
          title: lang.source,
          data: 'source',
          defaultContent: ''
        },
        {
          title: lang.spamfilter,
          data: 'keep_spam',
          defaultContent: '',
          render: function(data, type){
            return 'yes'==data?'<i class="bi bi-x-lg"><span class="sorting-value">yes</span></i>':'no'==data&&'<i class="bi bi-check-lg"><span class="sorting-value">no</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        },
      ]
    });
  }
  function draw_relayhosts() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#relayhoststable') ) {
      $('#relayhoststable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#relayhoststable').DataTable({
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
        url: "/api/v1/get/relayhost/all",
        dataSrc: function(data){
          return process_table_data(data, 'relayhoststable');
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
          title: lang.host,
          data: 'hostname',
          defaultContent: '',
          render: function (data, type) {
            return escapeHtml(data);
          }
        },
        {
          title: lang.username,
          data: 'username',
          defaultContent: ''
        },
        {
          title: lang.in_use_by,
          data: 'in_use_by',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            if(data == 1) return '<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>';
            else return '<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        },
      ]
    });
  }
  function draw_transport_maps() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#transportstable') ) {
      $('#transportstable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#transportstable').DataTable({
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
        url: "/api/v1/get/transport/all",
        dataSrc: function(data){
          return process_table_data(data, 'transportstable');
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
          title: lang.destination,
          data: 'destination',
          defaultContent: ''
        },
        {
          title: lang.nexthop,
          data: 'nexthop',
          defaultContent: ''
        },
        {
          title: lang.username,
          data: 'username',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            if(data == 1) return '<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>';
            else return '<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-text-right',
          defaultContent: ''
        },
      ]
    });
  }

  function process_table_data(data, table) {
    if (table == 'relayhoststable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="#" data-bs-toggle="modal" data-bs-target="#testTransportModal" data-transport-id="' + encodeURI(item.id) + '" data-transport-type="sender-dependent" class="btn btn-xs btn-xs-lg btn-xs-third btn-secondary"><i class="bi bi-caret-right-fill"></i> Test</a>' +
          '<a href="/edit/relayhost/' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-rlyhost" data-api-url="delete/relayhost" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
          '</div>';
        if (item.used_by_mailboxes == '') { item.in_use_by = item.used_by_domains; }
        else if (item.used_by_domains == '') { item.in_use_by = item.used_by_mailboxes; }
        else { item.in_use_by = item.used_by_mailboxes + '<hr style="margin:5px 0px 5px 0px;">' + item.used_by_domains; }
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="rlyhosts" name="multi_select" value="' + item.id + '" />';
      });
    } else if (table == 'transportstable') {
      $.each(data, function (i, item) {
        if (item.is_mx_based) {
          item.destination = '<i class="bi bi-info-circle-fill text-info mx-info" data-bs-toggle="tooltip" title="' + lang.is_mx_based + '"></i> <code>' + item.destination + '</code>';
        }
        if (item.username) {
          item.username = '<i style="color:#' + intToRGB(hashCode(item.nexthop)) + ';" class="bi bi-square-fill"></i> ' + item.username;
        }
        item.action = '<div class="btn-group">' +
          '<a href="#" data-bs-toggle="modal" data-bs-target="#testTransportModal" data-transport-id="' + encodeURI(item.id) + '" data-transport-type="transport-map" class="btn btn-xs btn-xs-lg btn-xs-third btn-secondary"><i class="bi bi-caret-right-fill"></i> Test</a>' +
          '<a href="/edit/transport/' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-transport" data-api-url="delete/transport" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
          '</div>';
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="transports" name="multi_select" value="' + item.id + '" />';
      });
    } else if (table == 'queuetable') {
      $.each(data, function (i, item) {
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="mailqitems" name="multi_select" value="' + item.queue_id + '" />';
        rcpts = $.map(item.recipients, function(i) {
          return escapeHtml(i);
        });
        item.recipients = rcpts.join('<hr style="margin:1px!important">');
        item.action = '<div class="btn-group">' +
          '<a href="#" data-bs-toggle="modal" data-bs-target="#showQueuedMsg" data-queue-id="' + encodeURI(item.queue_id) + '" class="btn btn-xs btn-xs-lg btn-secondary">' + lang.queue_show_message + '</a>' +
          '</div>';
      });
    } else if (table == 'forwardinghoststable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="#" data-action="delete_selected" data-id="single-fwdhost" data-api-url="delete/fwdhost" data-item="' + encodeURI(item.host) + '" class="btn btn-xs btn-xs-lg btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
          '</div>';
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="fwdhosts" name="multi_select" value="' + item.host + '" />';
      });
    } else if (table == 'oauth2clientstable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?oauth2client=' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-oauth2-client" data-api-url="delete/oauth2-client" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
          '</div>';
        item.scope = "profile";
        item.grant_types = 'refresh_token password authorization_code';
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="oauth2_clients" name="multi_select" value="' + item.id + '" />';
      });
    } else if (table == 'domainadminstable') {
      $.each(data, function (i, item) {
        item.selected_domains = escapeHtml(item.selected_domains);
        item.selected_domains = item.selected_domains.toString().replace(/,/g, "<br>");
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="domain_admins" name="multi_select" value="' + item.username + '" />';
        item.action = '<div class="btn-group">' +
          '<a href="/edit/domainadmin/' + encodeURI(item.username) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-domain-admin" data-api-url="delete/domain-admin" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
          '<a href="/index.php?duallogin=' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-xs-lg btn-xs-third btn-success"><i class="bi bi-person-fill"></i> Login</a>' +
          '</div>';
      });
    } else if (table == 'adminstable') {
      $.each(data, function (i, item) {
        if (admin_username.toLowerCase() == item.username.toLowerCase()) {
          item.usr = '<i class="bi bi-person-check"></i> ' + item.username;
        } else {
          item.usr = item.username;
        }
        item.chkbox = '<input type="checkbox" class="form-check-input" data-id="admins" name="multi_select" value="' + item.username + '" />';
        item.action = '<div class="btn-group">' +
          '<a href="/edit/admin/' + encodeURI(item.username) + '" class="btn btn-xs btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-admin" data-api-url="delete/admin" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
          '</div>';
      });
    }
    return data
  };

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
  // Draw Table if tab is active
  onVisible("[id^=adminstable]", () => draw_admins());
  onVisible("[id^=domainadminstable]", () => draw_domain_admins());
  onVisible("[id^=oauth2clientstable]", () => draw_oauth2_clients());
  onVisible("[id^=forwardinghoststable]", () => draw_fwd_hosts());
  onVisible("[id^=relayhoststable]", () => draw_relayhosts());
  onVisible("[id^=transportstable]", () => draw_transport_maps());


  $('body').on('click', 'span.footable-toggle', function () {
    event.stopPropagation();
  })

  // API IP check toggle
  $("#skip_ip_check_ro").click(function( event ) {
   $("#skip_ip_check_ro").not(this).prop('checked', false);
    if ($("#skip_ip_check_ro:checked").length > 0) {
      $('#allow_from_ro').prop('disabled', true);
    }
    else {
      $("#allow_from_ro").removeAttr('disabled');
    }
  });
  $("#skip_ip_check_rw").click(function( event ) {
   $("#skip_ip_check_rw").not(this).prop('checked', false);
    if ($("#skip_ip_check_rw:checked").length > 0) {
      $('#allow_from_rw').prop('disabled', true);
    }
    else {
      $("#allow_from_rw").removeAttr('disabled');
    }
  });
  // Relayhost
  $('#testRelayhostModal').on('show.bs.modal', function (e) {
    $('#test_relayhost_result').text("-");
    button = $(e.relatedTarget)
    if (button != null) {
      $('#relayhost_id').val(button.data('relayhost-id'));
    }
  })
  $('#test_relayhost').on('click', function (e) {
    e.preventDefault();
    prev = $('#test_relayhost').text();
    $(this).prop("disabled",true);
    $(this).html('<i class="bi bi-arrow-repeat icon-spin"></i> ');
    $.ajax({
      type: 'GET',
      url: 'inc/ajax/relay_check.php',
      dataType: 'text',
      data: $('#test_relayhost_form').serialize(),
      complete: function (data) {
        $('#test_relayhost_result').html(data.responseText);
        $('#test_relayhost').prop("disabled",false);
        $('#test_relayhost').text(prev);
      }
    });
  })
  // Transport
  $('#testTransportModal').on('show.bs.modal', function (e) {
    $('#test_transport_result').text("-");
    button = $(e.relatedTarget)
    if (button != null) {
      $('#transport_id').val(button.data('transport-id'));
      $('#transport_type').val(button.data('transport-type'));
    }
  })
  $('#test_transport').on('click', function (e) {
    e.preventDefault();
    prev = $('#test_transport').text();
    $(this).prop("disabled",true);
    $(this).html('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div> ');
    $.ajax({
      type: 'GET',
      url: 'inc/ajax/transport_check.php',
      dataType: 'text',
      data: $('#test_transport_form').serialize(),
      complete: function (data) {
        $('#test_transport_result').html(data.responseText);
        $('#test_transport').prop("disabled",false);
        $('#test_transport').text(prev);
      }
    });
  })
  // DKIM private key modal
  $('#showDKIMprivKey').on('show.bs.modal', function (e) {
    $('#priv_key_pre').text("-");
    p_related = $(e.relatedTarget)
    if (p_related != null) {
      var decoded_key = Base64.decode((p_related.data('priv-key')));
      $('#priv_key_pre').text(decoded_key);
    }
  })
  // FIDO2 friendly name modal
  $('#fido2ChangeFn').on('show.bs.modal', function (e) {
    rename_link = $(e.relatedTarget)
    if (rename_link != null) {
      $('#fido2_cid').val(rename_link.data('cid'));
      $('#fido2_subject_desc').text(Base64.decode(rename_link.data('subject')));
    }
  })
  // App links
  // setup eventlistener
  setAppHideEvent();
  function setAppHideEvent(){ 
    $('.app_hide').off('change');
    $('.app_hide').on('change', function (e) {
      var value = $(this).is(':checked') ? '1' : '0';
      console.log(value)
      $(this).parent().children(':first-child').val(value);
    })
  }
  function add_table_row(table_id, type) {
    var row = $('<tr />');
    if (type == "app_link") {
      cols = '<td><input class="input-sm input-xs-lg form-control" data-id="app_links" type="text" name="app" required></td>';
      cols += '<td><input class="input-sm input-xs-lg form-control" data-id="app_links" type="text" name="href" required></td>';
      cols += '<td><input class="input-sm input-xs-lg form-control" data-id="app_links" type="text" name="user_href" required></td>';
      cols += '<td><div class="d-flex align-items-center justify-content-center" style="height: 33.5px"><input data-id="app_links" type="hidden" name="hide" value="0"><input class="form-check-input app_hide" type="checkbox" value="1"></div></td>';
      cols += '<td><a href="#" role="button" class="btn btn-sm btn-xs-lg btn-secondary h-100 w-100" type="button">' + lang.remove_row + '</a></td>';
    } else if (type == "f2b_regex") {
      cols = '<td><input style="text-align:center" class="input-sm input-xs-lg form-control" data-id="f2b_regex" type="text" value="+" disabled></td>';
      cols += '<td><input class="input-sm input-xs-lg form-control regex-input" data-id="f2b_regex" type="text" name="regex" required></td>';
      cols += '<td><a href="#" role="button" class="btn btn-sm btn-xs-lg btn-secondary h-100 w-100" type="button">' + lang.remove_row + '</a></td>';
    }

    row.append(cols);
    table_id.append(row);
    if (type == "app_link")
      setAppHideEvent();
  }
  $('#app_link_table').on('click', 'tr a', function (e) {
    e.preventDefault();
    $(this).parents('tr').remove();
  });
  $('#f2b_regex_table').on('click', 'tr a', function (e) {
    e.preventDefault();
    $(this).parents('tr').remove();
  });
  $('#add_app_link_row').click(function() {
    add_table_row($('#app_link_table'), "app_link");
  });
  $('#add_f2b_regex_row').click(function() {
    add_table_row($('#f2b_regex_table'), "f2b_regex");
  });
  // IAM test connection
  $('.iam_test_connection').click(async function(e){
    e.preventDefault();
    var data = { attr: $('form[data-id="' + $(this).data('id') + '"]').serializeObject() };
    var res = await fetch("/api/v1/edit/identity-provider-test", { 
      headers: {
        "Content-Type": "application/json",
      },
      method:'POST', 
      cache:'no-cache', 
      body: JSON.stringify(data) 
    });
    res = await res.json();
    if (res.type === 'success'){
      return mailcow_alert_box(lang_success.iam_test_connection, 'success');
    }
    return mailcow_alert_box(lang_danger.iam_test_connection, 'danger');
  });

  $('.iam_rolemap_add_keycloak').click(async function(e){
    e.preventDefault();

    var parent = $('#iam_keycloak_mapping_list')
    $(parent).children().last().clone().appendTo(parent);
    var newChild = $(parent).children().last();
    $(newChild).find('input').val('');
    $(newChild).find('.dropdown-toggle').remove();
    $(newChild).find('.dropdown-menu').remove();
    $(newChild).find('.bs-title-option').remove();
    $(newChild).find('select').selectpicker('destroy');
    $(newChild).find('select').selectpicker();

    $('.iam_keycloak_rolemap_del').off('click');
    $('.iam_keycloak_rolemap_del').click(async function(e){
      e.preventDefault();
      if ($(this).parent().parent().parent().parent().children().length > 1)
        $(this).parent().parent().parent().remove();
    });
  });
  $('.iam_rolemap_add_generic').click(async function(e){
    e.preventDefault();

    var parent = $('#iam_generic_mapping_list')
    $(parent).children().last().clone().appendTo(parent);
    var newChild = $(parent).children().last();
    $(newChild).find('input').val('');
    $(newChild).find('.dropdown-toggle').remove();
    $(newChild).find('.dropdown-menu').remove();
    $(newChild).find('.bs-title-option').remove();
    $(newChild).find('select').selectpicker('destroy');
    $(newChild).find('select').selectpicker();

    $('.iam_generic_rolemap_del').off('click');
    $('.iam_generic_rolemap_del').click(async function(e){
      e.preventDefault();
      if ($(this).parent().parent().parent().parent().children().length > 1)
        $(this).parent().parent().parent().remove();
    });
  });
  $('.iam_rolemap_add_ldap').click(async function(e){
    e.preventDefault();

    var parent = $('#iam_ldap_mapping_list')
    $(parent).children().last().clone().appendTo(parent);
    var newChild = $(parent).children().last();
    $(newChild).find('input').val('');
    $(newChild).find('.dropdown-toggle').remove();
    $(newChild).find('.dropdown-menu').remove();
    $(newChild).find('.bs-title-option').remove();
    $(newChild).find('select').selectpicker('destroy');
    $(newChild).find('select').selectpicker();

    $('.iam_ldap_rolemap_del').off('click');
    $('.iam_ldap_rolemap_del').click(async function(e){
      e.preventDefault();
      if ($(this).parent().parent().parent().parent().children().length > 1)
        $(this).parent().parent().parent().remove();
    });
  });
  $('.iam_keycloak_rolemap_del').click(async function(e){
    e.preventDefault();
    if ($(this).parent().parent().parent().parent().children().length > 1)
      $(this).parent().parent().parent().remove();
  });
  $('.iam_generic_rolemap_del').click(async function(e){
    e.preventDefault();
    if ($(this).parent().parent().parent().parent().children().length > 1)
      $(this).parent().parent().parent().remove();
  });
  $('.iam_ldap_rolemap_del').click(async function(e){
    e.preventDefault();
    if ($(this).parent().parent().parent().parent().children().length > 1)
      $(this).parent().parent().parent().remove();
  });
  // selecting identity provider
  $('#iam_provider').on('change', function(){
    // toggle password fields
    if (this.value === 'keycloak'){
      $('#keycloak_settings').removeClass('d-none');
      $('#generic_oidc_settings').addClass('d-none');
      $('#ldap_settings').addClass('d-none');
    } else if (this.value === 'generic-oidc') {
      $('#generic_oidc_settings').removeClass('d-none');
      $('#keycloak_settings').addClass('d-none');
      $('#ldap_settings').addClass('d-none');
    } else if (this.value === 'ldap') {
      $('#ldap_settings').removeClass('d-none');
      $('#generic_oidc_settings').addClass('d-none');
      $('#keycloak_settings').addClass('d-none');
    }
  });
});
