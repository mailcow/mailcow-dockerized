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
  $("#refresh_watchdog_log").on('click', function(e) {
    e.preventDefault();
    draw_watchdog_logs();
  });
  $("#refresh_api_log").on('click', function(e) {
    e.preventDefault();
    draw_api_logs();
  });
  $("#refresh_acme_log").on('click', function(e) {
    e.preventDefault();
    draw_acme_logs();
  });
  $("#refresh_netfilter_log").on('click', function(e) {
    e.preventDefault();
    draw_netfilter_logs();
  });
  $("#refresh_rspamd_history").on('click', function(e) {
    e.preventDefault();
    draw_rspamd_history();
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
  function draw_watchdog_logs() {
    ft_watchdog_logs = FooTable.init('#watchdog_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"service","title":"Service"},
        {"name":"trend","title":"Trend"},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/watchdog',
        jsonp: false,
        error: function () {
          console.log('Cannot draw watchdog log table');
        },
        success: function (data) {
          return process_table_data(data, 'watchdog');
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
  function draw_api_logs() {
    ft_api_logs = FooTable.init('#api_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"uri","title":"URI","style":{"width":"310px"}},
        {"name":"method","title":"Method","style":{"width":"80px"}},
        {"name":"remote","title":"IP","style":{"width":"80px"}},
        {"name":"data","title":"Data","style":{"word-break":"break-all"}},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/api',
        jsonp: false,
        error: function () {
          console.log('Cannot draw api log table');
        },
        success: function (data) {
          return process_table_data(data, 'apilog');
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
  function draw_acme_logs() {
    ft_acme_logs = FooTable.init('#acme_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/acme',
        jsonp: false,
        error: function () {
          console.log('Cannot draw acme log table');
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
  function draw_netfilter_logs() {
    ft_netfilter_logs = FooTable.init('#netfilter_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/netfilter',
        jsonp: false,
        error: function () {
          console.log('Cannot draw netfilter log table');
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
    } else if (table == 'watchdog') {
      $.each(data, function (i, item) {
        if (item.message == null) {
          item.message = 'Health level: ' + item.lvl + '% (' + item.hpnow + '/' + item.hptotal + ')';
          if (item.hpdiff < 0) {
            item.trend = '<span class="label label-danger"><span class="glyphicon glyphicon-arrow-down"></span> ' + item.hpdiff + '</span>';
          }
          else if (item.hpdiff == 0) {
            item.trend = '<span class="label label-info"><span class="glyphicon glyphicon-arrow-right"></span> ' + item.hpdiff + '</span>';
          }
          else {
            item.trend = '<span class="label label-success"><span class="glyphicon glyphicon-arrow-up"></span> ' + item.hpdiff + '</span>';
          }
        }
        else {
          item.trend = '';
          item.service = '';
        }
      });
    } else if (table == 'general_syslog') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        item.message = escapeHtml(item.message);
        var danger_class = ["emerg", "alert", "crit", "err"];
        var warning_class = ["warning", "warn"];
        var info_class = ["notice", "info", "debug"];
        if (jQuery.inArray(item.priority, danger_class) !== -1) {
          item.priority = '<span class="label label-danger">' + item.priority + '</span>';
        } else if (jQuery.inArray(item.priority, warning_class) !== -1) {
          item.priority = '<span class="label label-warning">' + item.priority + '</span>';
        } else if (jQuery.inArray(item.priority, info_class) !== -1) {
          item.priority = '<span class="label label-info">' + item.priority + '</span>';
        }
      });
    } else if (table == 'apilog') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        if (item.method == 'GET') {
          item.method = '<span class="label label-success">' + item.method + '</span>';
        } else if (item.method == 'POST') {
          item.method = '<span class="label label-warning">' + item.method + '</span>';
        }
      });
    }
    return data
  };
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
  // Initial table drawings
  draw_postfix_logs();
  draw_autodiscover_logs();
  draw_dovecot_logs();
  draw_sogo_logs();
  draw_watchdog_logs();
  draw_acme_logs();
  draw_api_logs();
  draw_netfilter_logs();
  draw_rspamd_history();

});
