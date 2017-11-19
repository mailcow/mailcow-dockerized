// Base64 functions
var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(r){var t,e,o,a,h,n,c,d="",C=0;for(r=Base64._utf8_encode(r);C<r.length;)a=(t=r.charCodeAt(C++))>>2,h=(3&t)<<4|(e=r.charCodeAt(C++))>>4,n=(15&e)<<2|(o=r.charCodeAt(C++))>>6,c=63&o,isNaN(e)?n=c=64:isNaN(o)&&(c=64),d=d+this._keyStr.charAt(a)+this._keyStr.charAt(h)+this._keyStr.charAt(n)+this._keyStr.charAt(c);return d},decode:function(r){var t,e,o,a,h,n,c="",d=0;for(r=r.replace(/[^A-Za-z0-9\+\/\=]/g,"");d<r.length;)t=this._keyStr.indexOf(r.charAt(d++))<<2|(a=this._keyStr.indexOf(r.charAt(d++)))>>4,e=(15&a)<<4|(h=this._keyStr.indexOf(r.charAt(d++)))>>2,o=(3&h)<<6|(n=this._keyStr.indexOf(r.charAt(d++))),c+=String.fromCharCode(t),64!=h&&(c+=String.fromCharCode(e)),64!=n&&(c+=String.fromCharCode(o));return c=Base64._utf8_decode(c)},_utf8_encode:function(r){r=r.replace(/\r\n/g,"\n");for(var t="",e=0;e<r.length;e++){var o=r.charCodeAt(e);o<128?t+=String.fromCharCode(o):o>127&&o<2048?(t+=String.fromCharCode(o>>6|192),t+=String.fromCharCode(63&o|128)):(t+=String.fromCharCode(o>>12|224),t+=String.fromCharCode(o>>6&63|128),t+=String.fromCharCode(63&o|128))}return t},_utf8_decode:function(r){for(var t="",e=0,o=c1=c2=0;e<r.length;)(o=r.charCodeAt(e))<128?(t+=String.fromCharCode(o),e++):o>191&&o<224?(c2=r.charCodeAt(e+1),t+=String.fromCharCode((31&o)<<6|63&c2),e+=2):(c2=r.charCodeAt(e+1),c3=r.charCodeAt(e+2),t+=String.fromCharCode((15&o)<<12|(63&c2)<<6|63&c3),e+=3);return t}};
jQuery(function($){
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  $("#refresh_postfix_log").on('click', function(e) {
    e.preventDefault();
    draw_postfix_logs();
  });
  $("#refresh_autodiscover_log").on('click', function(e) {
    e.preventDefault();
    draw_autodiscover_logs();
  });
  $("#refresh_dovecot_log").on('click', function(e) {
    e.preventDefault();
    draw_dovecot_logs();
  });
  $("#refresh_sogo_log").on('click', function(e) {
    e.preventDefault();
    draw_sogo_logs();
  });
  $("#refresh_fail2ban_log").on('click', function(e) {
    e.preventDefault();
    draw_fail2ban_logs();
  });
  $("#refresh_rspamd_history").on('click', function(e) {
    e.preventDefault();
    draw_rspamd_history();
  });
  $("#import_dkim_legend").on('click', function(e) {
    e.preventDefault();
    $('#import_dkim_arrow').toggleClass("animation"); 
  });
  function draw_autodiscover_logs() {
    ft_autodiscover_logs = FooTable.init('#autodiscover_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"ua","title":"User-Agent","style":{"min-width":"200px"}},
        {"name":"user","title":"Username","style":{"min-width":"200px"}},
        {"name":"service","title":"Service"},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/autodiscover/100',
        jsonp: false,
        error: function () {
          console.log('Cannot draw autodiscover log table');
        },
        success: function (data) {
          return process_table_data(data, 'autodiscover_log');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"position": "left","placeholder": lang.filter_table},
      "sorting": {"enabled": true},
      "on": {"ready.ft.table": function(e, ft){
          heading = ft.$el.parents('.tab-pane').find('.panel-heading')
          $(heading).children('.log-lines').text(function(){
            var ft_paging = ft.use(FooTable.Paging)
            return ft_paging.totalRows;
          })
        }
      }
    });
  }
  function draw_postfix_logs() {
    ft_postfix_logs = FooTable.init('#postfix_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/postfix',
        jsonp: false,
        error: function () {
          console.log('Cannot draw postfix log table');
        },
        success: function (data) {
          return process_table_data(data, 'general_syslog');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"position": "left","placeholder": lang.filter_table},
      "sorting": {"enabled": true},
      "on": {
        "ready.ft.table": function(e, ft){
          heading = ft.$el.parents('.tab-pane').find('.panel-heading')
          $(heading).children('.log-lines').text(function(){
            var ft_paging = ft.use(FooTable.Paging)
            return ft_paging.totalRows;
          })
        }
      }
    });
  }
  function draw_fail2ban_logs() {
    ft_fail2ban_logs = FooTable.init('#fail2ban_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/fail2ban',
        jsonp: false,
        error: function () {
          console.log('Cannot draw fail2ban log table');
        },
        success: function (data) {
          return process_table_data(data, 'general_syslog');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"position": "left","connectors": false,"placeholder": lang.filter_table},
      "sorting": {"enabled": true},
      "on": {
        "ready.ft.table": function(e, ft){
          heading = ft.$el.parents('.tab-pane').find('.panel-heading')
          $(heading).children('.log-lines').text(function(){
            var ft_paging = ft.use(FooTable.Paging)
            return ft_paging.totalRows;
          })
        }
      }
    });
  }
  function draw_sogo_logs() {
    ft_sogo_logs = FooTable.init('#sogo_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/sogo',
        jsonp: false,
        error: function () {
          console.log('Cannot draw sogo log table');
        },
        success: function (data) {
          return process_table_data(data, 'general_syslog');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"position": "left","connectors": false,"placeholder": lang.filter_table},
      "sorting": {"enabled": true},
      "on": {
        "ready.ft.table": function(e, ft){
          heading = ft.$el.parents('.tab-pane').find('.panel-heading')
          $(heading).children('.log-lines').text(function(){
            var ft_paging = ft.use(FooTable.Paging)
            return ft_paging.totalRows;
          })
        }
      }
    });
  }
  function draw_dovecot_logs() {
    ft_dovecot_logs = FooTable.init('#dovecot_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/dovecot',
        jsonp: false,
        error: function () {
          console.log('Cannot draw dovecot log table');
        },
        success: function (data) {
          return process_table_data(data, 'general_syslog');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"position": "left","connectors": false,"placeholder": lang.filter_table},
      "sorting": {"enabled": true},
      "on": {
        "ready.ft.table": function(e, ft){
          heading = ft.$el.parents('.tab-pane').find('.panel-heading')
          $(heading).children('.log-lines').text(function(){
            var ft_paging = ft.use(FooTable.Paging)
            return ft_paging.totalRows;
          })
        }
      }
    });
  }
  function draw_domain_admins() {
    ft_domainadmins = FooTable.init('#domainadminstable', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"username","title":lang.username,"style":{"width":"250px"}},
        {"name":"selected_domains","title":lang.admin_domains,"breakpoints":"xs sm"},
        {"name":"tfa_active","title":"TFA", "filterable": false,"style":{"maxWidth":"80px","width":"80px"}},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
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
      "filtering": {"enabled": true,"position": "left","connectors": false,"placeholder": lang.filter_table
      },
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
        {"name":"used_by_domains","title":lang.in_use_by, "type": "text","breakpoints":"xs sm"},
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
  function draw_rspamd_history() {
    ft_rspamd_history = FooTable.init('#rspamd_history', {
      "columns": [
        {"name":"unix_time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name": "ip","title": "IP address","breakpoints": "all","style": {"minWidth": 88}},
        {"name": "sender_mime","title": "From","breakpoints": "xs sm md","style": {"minWidth": 100}},
        {"name": "rcpt_mime","title": "To","breakpoints": "xs sm md","style": {"minWidth": 100}},
        {"name": "subject","title": "Subject","breakpoints": "all","style": {"word-break": "break-all","minWidth": 150}},
        {"name": "action","title": "Action","style": {"minwidth": 82}},
        {"name": "score","title": "Score","style": {"maxWidth": 110},},
        {"name": "symbols","title": "Symbols","breakpoints": "all",},
        {"name": "size","title": "Msg size","breakpoints": "all","style": {"minwidth": 50},"formatter": function(value){return humanFileSize(value);}},
        {"name": "scan_time","title": "Scan time","breakpoints": "all","style": {"maxWidth": 72},},
        {"name": "message-id","title": "ID","breakpoints": "all","style": {"minWidth": 130,"overflow": "hidden","textOverflow": "ellipsis","wordBreak": "break-all","whiteSpace": "normal"}},
        {"name": "user","title": "Authenticated user","breakpoints": "xs sm md","style": {"minWidth": 100}}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/rspamd-history',
        jsonp: false,
        error: function () {
          console.log('Cannot draw rspamd history table');
        },
        success: function (data) {
          return process_table_data(data, 'rspamd_history');
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": log_pagination_size},
      "filtering": {"enabled": true,"position": "left","connectors": false,"placeholder": lang.filter_table},
      "sorting": {"enabled": true},
      "on": {
        "ready.ft.table": function(e, ft){
          heading = ft.$el.parents('.tab-pane').find('.panel-heading')
          $(heading).children('.log-lines').text(function(){
            var ft_paging = ft.use(FooTable.Paging)
            return ft_paging.totalRows;
          })
        }
      }
    });
  }

  function process_table_data(data, table) {
    if (table == 'rspamd_history') {
    $.each(data, function (i, item) {
      item.rcpt_mime = item.rcpt_mime.join(",&#8203;");
      Object.keys(item.symbols).map(function(key) {
        var sym = item.symbols[key];
        if (sym.score <= 0) {
          sym.score_formatted = '(<span class="text-success"><b>' + sym.score + '</b></span>)'
        }
        else {
          sym.score_formatted = '(<span class="text-danger"><b>' + sym.score + '</b></span>)'
        }
        var str = '<strong>' + key + '</strong> ' + sym.score_formatted;
        if (sym.options) {
          str += ' [' + sym.options.join(",") + "]";
        }
        item.symbols[key].str = str;
      });
      item.symbols = Object.keys(item.symbols).
      map(function(key) {
        return item.symbols[key];
      }).sort(function(e1, e2) {
        return Math.abs(e1.score) < Math.abs(e2.score);
      }).map(function(e) {
        return e.str;
      }).join("<br>\n");
      var scan_time = item.time_real.toFixed(3) + ' / ' + item.time_virtual.toFixed(3);
      item.scan_time = {
        "options": {
          "sortValue": item.time_real
        },
        "value": scan_time
      };
      if (item.action === 'clean' || item.action === 'no action') {
        item.action = "<div class='label label-success'>" + item.action + "</div>";
      } else if (item.action === 'rewrite subject' || item.action === 'add header' || item.action === 'probable spam') {
        item.action = "<div class='label label-warning'>" + item.action + "</div>";
      } else if (item.action === 'spam' || item.action === 'reject') {
        item.action = "<div class='label label-danger'>" + item.action + "</div>";
      } else {
        item.action = "<div class='label label-info'>" + item.action + "</div>";
      }
      var score_content;
      if (item.score < item.required_score) {
        score_content = "[ <span class='text-success'>" + item.score.toFixed(2) + " / " + item.required_score + "</span> ]";
      } else {
        score_content = "[ <span class='text-danger'>" + item.score.toFixed(2) + " / " + item.required_score + "</span> ]";
      }
      item.score = {
        "options": {
          "sortValue": item.score
        },
        "value": score_content
      };
      if (item.user == null) {
        item.user = "none";
      }
    });
    } else if (table == 'relayhoststable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="#" data-toggle="modal" id="miau" data-target="#testRelayhostModal" data-relayhost-id="' + encodeURI(item.id) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-stats"></span> Test</a>' +
          '<a href="/edit.php?relayhost=' + encodeURI(item.id) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="#" id="delete_selected" data-id="single-rlshost" data-api-url="delete/relayhost" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
          '</div>';
        item.chkbox = '<input type="checkbox" data-id="rlyhosts" name="multi_select" value="' + item.id + '" />';
      });
    } else if (table == 'forwardinghoststable') {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="#" id="delete_selected" data-id="single-fwdhost" data-api-url="delete/fwdhost" data-item="' + encodeURI(item.host) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
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
        item.chkbox = '<input type="checkbox" data-id="domain_admins" name="multi_select" value="' + item.username + '" />';
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?domainadmin=' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="#" id="delete_selected" data-id="single-domain-admin" data-api-url="delete/domain-admin" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
          '</div>';
      });
    } else if (table == 'autodiscover_log') {
      $.each(data, function (i, item) {
        item.ua = '<span style="font-size:small">' + item.ua + '</span>';
        if (item.service == "activesync") {
          item.service = '<span class="label label-info">ActiveSync</span>';
        }
        else if (item.service == "imap") {
          item.service = '<span class="label label-success">IMAP, SMTP, Cal-/CardDAV</span>';
        }
        else {
          item.service = '<span class="label label-danger">' + item.service + '</span>';
        }
      });
    } else if (table == 'general_syslog') {
      $.each(data, function (i, item) {
        item.message = escapeHtml(item.message);
        var danger_class = ["emerg", "alert", "crit", "err"];
        var warning_class = ["warning", "warn"];
        var info_class = ["notice", "info", "debug"];
        if (jQuery.inArray(item.priority, danger_class) !== -1) {
          item.priority = '<span class="label label-danger">' + item.priority + '</span>';
        } 
        else if (jQuery.inArray(item.priority, warning_class) !== -1) {
          item.priority = '<span class="label label-warning">' + item.priority + '</span>';
        }
        else if (jQuery.inArray(item.priority, info_class) !== -1) {
          item.priority = '<span class="label label-info">' + item.priority + '</span>';
        }
      });
    }
    return data
  };
  // Initial table drawings
  draw_postfix_logs();
  draw_autodiscover_logs();
  draw_dovecot_logs();
  draw_sogo_logs();
  draw_fail2ban_logs();
  draw_domain_admins();
  draw_fwd_hosts();
  draw_relayhosts();
  draw_rspamd_history();
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

  $('.add_log_lines').on('click', function (e) {
    e.preventDefault();
    var log_table= $(this).data("table")
    var new_nrows = ($(this).data("nrows") - 1)
    var post_process = $(this).data("post-process")
    var log_url = $(this).data("log-url")
    if (log_table === undefined || new_nrows === undefined || post_process === undefined || log_url === undefined) {
      console.log("no data-table or data-nrows or log_url or data-post-process attr found");
      return;
    }
    if (ft = FooTable.get($('#' + log_table))) {
      var heading = ft.$el.parents('.tab-pane').find('.panel-heading')
      var ft_paging = ft.use(FooTable.Paging)
      var load_rows = ft_paging.totalRows + '-' + (ft_paging.totalRows + new_nrows)
      $.get('/api/v1/get/logs/' + log_url + '/' + load_rows).then(function(data){
        if (data.length === undefined) { mailcow_alert_box(lang.no_new_rows, "info"); return; }
        var rows = process_table_data(data, post_process);
        var rows_now = (ft_paging.totalRows + data.length);
        $(heading).children('.log-lines').text(rows_now)
        mailcow_alert_box(data.length + lang.additional_rows, "success");
        ft.rows.load(rows, true);
      });
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
