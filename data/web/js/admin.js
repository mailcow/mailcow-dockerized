// Base64 functions
var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(r){var t,e,o,a,h,n,c,d="",C=0;for(r=Base64._utf8_encode(r);C<r.length;)a=(t=r.charCodeAt(C++))>>2,h=(3&t)<<4|(e=r.charCodeAt(C++))>>4,n=(15&e)<<2|(o=r.charCodeAt(C++))>>6,c=63&o,isNaN(e)?n=c=64:isNaN(o)&&(c=64),d=d+this._keyStr.charAt(a)+this._keyStr.charAt(h)+this._keyStr.charAt(n)+this._keyStr.charAt(c);return d},decode:function(r){var t,e,o,a,h,n,c="",d=0;for(r=r.replace(/[^A-Za-z0-9\+\/\=]/g,"");d<r.length;)t=this._keyStr.indexOf(r.charAt(d++))<<2|(a=this._keyStr.indexOf(r.charAt(d++)))>>4,e=(15&a)<<4|(h=this._keyStr.indexOf(r.charAt(d++)))>>2,o=(3&h)<<6|(n=this._keyStr.indexOf(r.charAt(d++))),c+=String.fromCharCode(t),64!=h&&(c+=String.fromCharCode(e)),64!=n&&(c+=String.fromCharCode(o));return c=Base64._utf8_decode(c)},_utf8_encode:function(r){r=r.replace(/\r\n/g,"\n");for(var t="",e=0;e<r.length;e++){var o=r.charCodeAt(e);o<128?t+=String.fromCharCode(o):o>127&&o<2048?(t+=String.fromCharCode(o>>6|192),t+=String.fromCharCode(63&o|128)):(t+=String.fromCharCode(o>>12|224),t+=String.fromCharCode(o>>6&63|128),t+=String.fromCharCode(63&o|128))}return t},_utf8_decode:function(r){for(var t="",e=0,o=c1=c2=0;e<r.length;)(o=r.charCodeAt(e))<128?(t+=String.fromCharCode(o),e++):o>191&&o<224?(c2=r.charCodeAt(e+1),t+=String.fromCharCode((31&o)<<6|63&c2),e+=2):(c2=r.charCodeAt(e+1),c3=r.charCodeAt(e+2),t+=String.fromCharCode((15&o)<<12|(63&c2)<<6|63&c3),e+=3);return t}};
jQuery(function($){
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  $("#import_dkim_legend").on('click', function(e) {
    e.preventDefault();
    $('#import_dkim_arrow').toggleClass("animation"); 
  });
  $("#duplicate_dkim_legend").on('click', function(e) {
    e.preventDefault();
    $('#duplicate_dkim_arrow').toggleClass("animation"); 
  });
  $("#api_legend").on('click', function(e) {
    e.preventDefault();
    $('#api_arrow').toggleClass("animation"); 
  });
  $("#rspamd_preset_1").on('click', function(e) {
    e.preventDefault();
    $("form[data-id=rsetting]").find("#adminRspamdSettingsDesc").val(lang.rsettings_preset_1);
    $("form[data-id=rsetting]").find("#adminRspamdSettingsContent").val('priority = 10;\nauthenticated = yes;\napply "default" {\n  symbols_enabled = ["DKIM_SIGNED", "RATELIMIT_UPDATE", "RATELIMIT_CHECK", "DYN_RL_CHECK", "HISTORY_SAVE", "MILTER_HEADERS", "ARC_SIGNED"];\n}');
  });
  $("#rspamd_preset_2").on('click', function(e) {
    e.preventDefault();
    $("form[data-id=rsetting]").find("#adminRspamdSettingsDesc").val(lang.rsettings_preset_2);
    $("form[data-id=rsetting]").find("#adminRspamdSettingsContent").val('priority = 10;\nrcpt = "/postmaster@.*/";\nwant_spam = yes;');
  });
  $("#dkim_missing_keys").on('click', function(e) {
    e.preventDefault();
     var domains = [];
     $('.dkim_missing').each(function() {
       domains.push($(this).val());
     });
     $('#dkim_add_domains').val(domains);
  });
  $("#mass_exclude").change(function(){ 
    $("#mass_include").selectpicker('deselectAll');
  });
  $("#mass_include").change(function(){ 
    $("#mass_exclude").selectpicker('deselectAll');
  });
  $("#mass_disarm").click(function() {
    $("#mass_send").attr("disabled", !this.checked);
  });
  function draw_domain_admins() {
    ft_domainadmins = FooTable.init('#domainadminstable', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"username","title":lang.username,"style":{"width":"250px"}},
        {"name":"selected_domains","title":lang.admin_domains,"breakpoints":"xs sm"},
        {"name":"tfa_active","title":"TFA", "filterable": false,"style":{"maxWidth":"80px","width":"80px"}},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"250px","width":"250px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/domain-admin/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw domain admin table');
        },
        success: function (data) {
          return process_table_data(data, 'domainadminstable');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"delay": 1,"position": "left","connectors": false,"placeholder": lang.filter_table
      },
      "sorting": {"enabled": true}
    });
  }
  function draw_admins() {
    ft_admins = FooTable.init('#adminstable', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"usr","title":lang.username,"style":{"width":"250px"}},
        {"name":"tfa_active","title":"TFA", "filterable": false,"style":{"maxWidth":"80px","width":"80px"}},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"250px","width":"250px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/admin/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw admin table');
        },
        success: function (data) {
          return process_table_data(data, 'adminstable');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": false},
      "sorting": {"enabled": true}
    });
  }
  function draw_fwd_hosts() {
    ft_forwardinghoststable = FooTable.init('#forwardinghoststable', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"host","type":"text","title":lang.host,"style":{"width":"250px"}},
        {"name":"source","title":lang.source,"breakpoints":"xs sm"},
        {"name":"keep_spam","title":lang.spamfilter, "type": "text","style":{"maxWidth":"80px","width":"80px"}},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/fwdhost/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw forwarding hosts table');
        },
        success: function (data) {
          return process_table_data(data, 'forwardinghoststable');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "sorting": {"enabled": true}
    });
  }
  function draw_relayhosts() {
    ft_relayhoststable = FooTable.init('#relayhoststable', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"id","type":"text","title":"ID","style":{"width":"50px"}},
        {"name":"hostname","type":"text","title":lang.host,"style":{"width":"250px"}},
        {"name":"username","title":lang.username,"breakpoints":"xs sm"},
        {"name":"used_by_domains","title":lang.in_use_by,"style":{"width":"110px"}, "type": "text","breakpoints":"xs sm"},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"280px","width":"280px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/relayhost/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw forwarding hosts table');
        },
        success: function (data) {
          return process_table_data(data, 'relayhoststable');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "sorting": {"enabled": true}
    });
  }

  function process_table_data(data, table) {
    if (table == 'relayhoststable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="#" data-toggle="modal" id="miau" data-target="#testRelayhostModal" data-relayhost-id="' + encodeURI(item.id) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-stats"></span> Test</a>' +
          '<a href="/edit/relayhost/' + encodeURI(item.id) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-rlshost" data-api-url="delete/relayhost" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
          '</div>';
        item.chkbox = '<input type="checkbox" data-id="rlyhosts" name="multi_select" value="' + item.id + '" />';
      });
    } else if (table == 'forwardinghoststable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="#" data-action="delete_selected" data-id="single-fwdhost" data-api-url="delete/fwdhost" data-item="' + encodeURI(item.host) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
          '</div>';
        if (item.keep_spam == "yes") {
          item.keep_spam = lang.no;
        }
        else {
          item.keep_spam = lang.yes;
        }
        item.chkbox = '<input type="checkbox" data-id="fwdhosts" name="multi_select" value="' + item.host + '" />';
      });
    } else if (table == 'domainadminstable') {
      $.each(data, function (i, item) {
        item.selected_domains = escapeHtml(item.selected_domains);
        item.selected_domains = item.selected_domains.toString().replace(/,/g, "<br>");
        item.chkbox = '<input type="checkbox" data-id="domain_admins" name="multi_select" value="' + item.username + '" />';
        item.action = '<div class="btn-group">' +
          '<a href="/edit/domainadmin/' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-domain-admin" data-api-url="delete/domain-admin" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
          '<a href="/index.php?duallogin=' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-user"></span> Login</a>' +
          '</div>';
      });
    } else if (table == 'adminstable') {
      $.each(data, function (i, item) {
        if (admin_username == item.username) {
          item.usr = '→ ' + item.username;
        } else {
          item.usr = item.username;
        }
        item.chkbox = '<input type="checkbox" data-id="admins" name="multi_select" value="' + item.username + '" />';
        item.action = '<div class="btn-group">' +
          '<a href="/edit/admin/' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="#" data-action="delete_selected" data-id="single-admin" data-api-url="delete/admin" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
          '</div>';
      });
    }
    return data
  };
  // Initial table drawings
  draw_domain_admins();
  draw_admins();
  draw_fwd_hosts();
  draw_relayhosts();
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
    $(this).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> ');
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
  // DKIM private key modal
  $('#showDKIMprivKey').on('show.bs.modal', function (e) {
    $('#priv_key_pre').text("-");
    p_related = $(e.relatedTarget)
    if (p_related != null) {
      var decoded_key = Base64.decode((p_related.data('priv-key')));
      $('#priv_key_pre').text(decoded_key);
    }
  })
  // App links
  function add_table_row(table_id) {
    var row = $('<tr />');
    cols = '<td><input class="input-sm form-control" data-id="app_links" type="text" name="app" required></td>';
    cols += '<td><input class="input-sm form-control" data-id="app_links" type="text" name="href" required></td>';
    cols += '<td><a href="#" role="button" class="btn btn-xs btn-default" type="button">Remove row</a></td>';
    row.append(cols);
    table_id.append(row);
  }
  $('#app_link_table').on('click', 'tr a', function (e) {
    e.preventDefault();
    $(this).parents('tr').remove();
  });
  $('#add_app_link_row').click(function() {
      add_table_row($('#app_link_table'));
  });
});
$(window).load(function(){
  initial_width = $("#sidebar-admin").width();
  $("#scrollbox").css("width", initial_width);
  if (sessionStorage.scrollTop > 70) {
    $('#scrollbox').addClass('scrollboxFixed');
  }
  $(window).bind('scroll', function() {
    if ($(window).scrollTop() > 70) {
      $('#scrollbox').addClass('scrollboxFixed');
    } else {
      $('#scrollbox').removeClass('scrollboxFixed');
    }
  });
});
function resizeScrollbox() {
  on_resize_width = $("#sidebar-admin").width();
  $("#scrollbox").removeAttr("style");
  $("#scrollbox").css("width", on_resize_width);
}
$(window).on('resize', resizeScrollbox);
$('a[data-toggle="tab"]').on('shown.bs.tab', resizeScrollbox);
