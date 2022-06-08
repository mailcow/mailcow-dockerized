$(document).ready(function() {
  // Parse seconds ago to date
  // Get "now" timestamp
  var ts_now = Math.round((new Date()).getTime() / 1000);
  $('.parse_s_ago').each(function(i, parse_s_ago) {
    var started_s_ago = parseInt($(this).text(), 10);
    if (typeof started_s_ago != 'NaN') {
      var started_date = new Date((ts_now - started_s_ago) * 1000);
      if (started_date instanceof Date && !isNaN(started_date)) {
        var started_local_date = started_date.toLocaleDateString(undefined, {
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
          hour: "2-digit",
          minute: "2-digit",
          second: "2-digit"
        });
        $(this).text(started_local_date);
      } else {
        $(this).text('-');
      }
    }
  });
  // Parse general dates
  $('.parse_date').each(function(i, parse_date) {
    var started_date = new Date(Date.parse($(this).text()));
    if (typeof started_date != 'NaN') {
      var started_local_date = started_date.toLocaleDateString(undefined, {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit"
      });
      $(this).text(started_local_date);
    }
  });
});
jQuery(function($){
  if (localStorage.getItem("current_page") === null) {
    var current_page = {};
  } else {
    var current_page = JSON.parse(localStorage.getItem('current_page'));
  }
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  function hashCode(t){for(var n=0,r=0;r<t.length;r++)n=t.charCodeAt(r)+((n<<5)-n);return n}
  function intToRGB(t){var n=(16777215&t).toString(16).toUpperCase();return"00000".substring(0,6-n.length)+n}
  $(".refresh_table").on('click', function(e) {
    e.preventDefault();
    var table_name = $(this).data('table');
    $('#' + table_name).DataTable().ajax.reload();
  });
  function draw_autodiscover_logs() {
    $('#autodiscover_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/autodiscover/100",
        dataSrc: function(data){
          return process_table_data(data, 'autodiscover_log');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'User-Agent',
          data: 'ua'
        },
        {
          title: 'Username',
          data: 'user'
        },
        {
          title: 'IP',
          data: 'ip'
        },
        {
          title: 'Service',
          data: 'service'
        }
      ]
    });
  }
  function draw_postfix_logs() {
    $('#postfix_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/postfix",
        dataSrc: function(data){
          return process_table_data(data, 'general_syslog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority'
        },
        {
          title: lang.message,
          data: 'message'
        }
      ]
    });
  }
  function draw_watchdog_logs() {
    $('#watchdog_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/watchdog",
        dataSrc: function(data){
          return process_table_data(data, 'watchdog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'Service',
          data: 'service'
        },
        {
          title: 'Trend',
          data: 'trend'
        },
        {
          title: lang.message,
          data: 'message'
        }
      ]
    });
  }
  function draw_api_logs() {
    $('#api_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/api",
        dataSrc: function(data){
          return process_table_data(data, 'apilog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'URI',
          data: 'uri'
        },
        {
          title: 'Method',
          data: 'method'
        },
        {
          title: 'IP',
          data: 'remote'
        },
        {
          title: 'Data',
          data: 'data'
        }
      ]
    });
  }
  function draw_rl_logs() {
    $('#rl_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/ratelimited",
        dataSrc: function(data){
          return process_table_data(data, 'rllog');
        }
      },
      columns: [
        {
          title: ' ',
          data: 'indicator'
        },
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.rate_name,
          data: 'rl_name'
        },
        {
          title: lang.sender,
          data: 'from'
        },
        {
          title: lang.recipients,
          data: 'rcpt'
        },
        {
          title: lang.authed_user,
          data: 'user'
        },
        {
          title: 'Msg ID',
          data: 'message_id'
        },
        {
          title: 'Header From',
          data: 'header_from'
        },
        {
          title: 'Subject',
          data: 'header_subject'
        },
        {
          title: 'Hash',
          data: 'rl_hash'
        },
        {
          title: 'Rspamd QID',
          data: 'qid'
        },
        {
          title: 'IP',
          data: 'ip'
        },
        {
          title: lang.action,
          data: 'action'
        }
      ]
    });
  }
  function draw_ui_logs() {
    $('#ui_logs').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/ui",
        dataSrc: function(data){
          return process_table_data(data, 'mailcow_ui');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'Type',
          data: 'type'
        },
        {
          title: 'Task',
          data: 'task'
        },
        {
          title: 'User',
          data: 'user'
        },
        {
          title: 'Role',
          data: 'role'
        },
        {
          title: 'IP',
          data: 'remote'
        },
        {
          title: lang.message,
          data: 'msg'
        },
        {
          title: 'Call',
          data: 'call'
        }
      ]
    });
  }
  function draw_sasl_logs() {
    $('#sasl_logs').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/sasl",
        dataSrc: function(data){
          return process_table_data(data, 'sasl_log_table');
        }
      },
      columns: [
        {
          title: lang.username,
          data: 'username'
        },
        {
          title: lang.service,
          data: 'service'
        },
        {
          title: 'IP',
          data: 'real_rip'
        },
        {
          title: lang.login_time,
          data: 'datetime',
          render: function(data, type){
            var date = new Date(data.replace(/-/g, "/")); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        }
      ]
    });
  }
  function draw_acme_logs() {
    $('#acme_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/acme",
        dataSrc: function(data){
          return process_table_data(data, 'general_syslog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.message,
          data: 'message'
        }
      ]
    });
  }
  function draw_netfilter_logs() {
    $('#netfilter_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/netfilter",
        dataSrc: function(data){
          return process_table_data(data, 'general_syslog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority'
        },
        {
          title: lang.message,
          data: 'message'
        }
      ]
    });
  }
  function draw_sogo_logs() {
    $('#sogo_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/sogo",
        dataSrc: function(data){
          return process_table_data(data, 'general_syslog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority'
        },
        {
          title: lang.message,
          data: 'message'
        }
      ]
    });
  }
  function draw_dovecot_logs() {
    $('#dovecot_log').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      order: [[0, 'desc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/dovecot",
        dataSrc: function(data){
          return process_table_data(data, 'general_syslog');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority'
        },
        {
          title: lang.message,
          data: 'message'
        }
      ]
    });
  }
  function rspamd_pie_graph() {
    $.ajax({
      url: '/api/v1/get/rspamd/actions',
      async: true,
      success: function(data){
        console.log(data);

        var total = 0;
        $(data).map(function(){total += this[1];});
        var labels = $.makeArray($(data).map(function(){return this[0] + ' ' + Math.round(this[1]/total * 100) + '%';}));
        var values = $.makeArray($(data).map(function(){return this[1];}));
        console.log(values);

        var graphdata = {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: ['#DC3023', '#59ABE3', '#FFA400', '#FFA400', '#26A65B']
          }]
        };

        var options = {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            datalabels: {
              color: '#FFF',
              font: {
                weight: 'bold'
              },
              display: function(context) {
                return context.dataset.data[context.dataIndex] !== 0;
              },
              formatter: function(value, context) {
                return Math.round(value/total*100) + '%';
              }
            }
          }
        };
        var chartcanvas = document.getElementById('rspamd_donut');
        Chart.register('ChartDataLabels');
        if(typeof chart == 'undefined') {
          chart = new Chart(chartcanvas.getContext("2d"), {
            plugins: [ChartDataLabels],
            type: 'doughnut',
            data: graphdata,
            options: options
          });
        }
        else {
          chart.destroy();
          chart = new Chart(chartcanvas.getContext("2d"), {
            plugins: [ChartDataLabels],
            type: 'doughnut',
            data: graphdata,
            options: options
          });
        }
      }
    });
  }
  function draw_rspamd_history() {
    $('#rspamd_history').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/logs/rspamd-history",
        dataSrc: function(data){
          return process_table_data(data, 'rspamd_history');
        }
      },
      columns: [
        {
          title: lang.time,
          data: 'time',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'IP address',
          data: 'ip'
        },
        {
          title: 'From',
          data: 'sender_mime'
        },
        {
          title: 'To',
          data: 'rcpt'
        },
        {
          title: 'Subject',
          data: 'subject'
        },
        {
          title: 'Action',
          data: 'action'
        },
        {
          title: 'Score',
          data: 'score'
        },
        {
          title: 'Subject',
          data: 'header_subject'
        },
        {
          title: 'Symbols',
          data: 'symbols'
        },
        {
          title: 'Msg size',
          data: 'size'
        },
        {
          title: 'Scan Time',
          data: 'scan_time'
        },
        {
          title: 'ID',
          data: 'message-id'
        },
        {
          title: 'Authenticated user',
          data: 'user'
        }
      ]
    });
  }
  function process_table_data(data, table) {
    if (table == 'rspamd_history') {
    $.each(data, function (i, item) {
      if (item.rcpt_mime != "") {
        item.rcpt = escapeHtml(item.rcpt_mime.join(", "));
      }
      else {
        item.rcpt = escapeHtml(item.rcpt_smtp.join(", "));
      }
      item.symbols = Object.keys(item.symbols).sort(function (a, b) {
        if (item.symbols[a].score === 0) return 1
        if (item.symbols[b].score === 0) return -1
        if (item.symbols[b].score < 0 && item.symbols[a].score < 0) {
          return item.symbols[a].score - item.symbols[b].score
        }
        if (item.symbols[b].score > 0 && item.symbols[a].score > 0) {
          return item.symbols[b].score - item.symbols[a].score
        }
        return item.symbols[b].score - item.symbols[a].score
      }).map(function(key) {
        var sym = item.symbols[key];
        if (sym.score < 0) {
          sym.score_formatted = '(<span class="text-success"><b>' + sym.score + '</b></span>)'
        }
        else if (sym.score === 0) {
          sym.score_formatted = '(<span><b>' + sym.score + '</b></span>)'
        }
        else {
          sym.score_formatted = '(<span class="text-danger"><b>' + sym.score + '</b></span>)'
        }
        var str = '<strong>' + key + '</strong> ' + sym.score_formatted;
        if (sym.options) {
          str += ' [' + escapeHtml(sym.options.join(", ")) + "]";
        }
        return str
      }).join('<br>\n');
      item.subject = escapeHtml(item.subject);
      var scan_time = item.time_real.toFixed(3);
      if (item.time_virtual) {
        scan_time += ' / ' + item.time_virtual.toFixed(3);
      }
      item.scan_time = {
        "options": {
          "sortValue": item.time_real
        },
        "value": scan_time
      };
      if (item.action === 'clean' || item.action === 'no action') {
        item.action = "<div class='badge fs-5 bg-success'>" + item.action + "</div>";
      } else if (item.action === 'rewrite subject' || item.action === 'add header' || item.action === 'probable spam') {
        item.action = "<div class='badge fs-5 bg-warning'>" + item.action + "</div>";
      } else if (item.action === 'spam' || item.action === 'reject') {
        item.action = "<div class='badge fs-5 bg-danger'>" + item.action + "</div>";
      } else {
        item.action = "<div class='badge fs-5 bg-info'>" + item.action + "</div>";
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
        if (item.ua == null) {
          item.ua = 'unknown';
        } else {
          item.ua = escapeHtml(item.ua);
        }
        item.ua = '<span style="font-size:small">' + item.ua + '</span>';
        if (item.service == "activesync") {
          item.service = '<span class="badge fs-5 bg-info">ActiveSync</span>';
        }
        else if (item.service == "imap") {
          item.service = '<span class="badge fs-5 bg-success">IMAP, SMTP, Cal-/CardDAV</span>';
        }
        else {
          item.service = '<span class="badge fs-5 bg-danger">' + escapeHtml(item.service) + '</span>';
        }
      });
    } else if (table == 'watchdog') {
      $.each(data, function (i, item) {
        if (item.message == null) {
          item.message = 'Health level: ' + item.lvl + '% (' + item.hpnow + '/' + item.hptotal + ')';
          if (item.hpdiff < 0) {
            item.trend = '<span class="badge fs-5 bg-danger"><i class="bi bi-caret-down-fill"></i> ' + item.hpdiff + '</span>';
          }
          else if (item.hpdiff == 0) {
            item.trend = '<span class="badge fs-5 bg-info"><i class="bi bi-caret-right-fill"></i> ' + item.hpdiff + '</span>';
          }
          else {
            item.trend = '<span class="badge fs-5 bg-success"><i class="bi bi-caret-up-fill"></i> ' + item.hpdiff + '</span>';
          }
        }
        else {
          item.trend = '';
          item.service = '';
        }
      });
    } else if (table == 'mailcow_ui') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        item.user = escapeHtml(item.user);
        item.call = escapeHtml(item.call);
        item.task = '<code>' + item.task + '</code>';
        item.type = '<span class="badge fs-5 bg-' + item.type + '">' + item.type + '</span>';
      });
    } else if (table == 'sasl_log_table') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        item.username = escapeHtml(item.username);
        item.service = '<div class="badge fs-5 bg-secondary">' + item.service.toUpperCase() + '</div>';
      });
    } else if (table == 'general_syslog') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        if (item.message.match("^base64,")) {
          try {
            item.message = atob(item.message.slice(7)).replace(/\\n/g, "<br />");
          } catch(e) {
            item.message = item.message.slice(7);
          }
        } else {
          item.message = escapeHtml(item.message);
        }
        item.call = escapeHtml(item.call);
        var danger_class = ["emerg", "alert", "crit", "err"];
        var warning_class = ["warning", "warn"];
        var info_class = ["notice", "info", "debug"];
        if (jQuery.inArray(item.priority, danger_class) !== -1) {
          item.priority = '<span class="badge fs-5 bg-danger">' + item.priority + '</span>';
        } else if (jQuery.inArray(item.priority, warning_class) !== -1) {
          item.priority = '<span class="badge fs-5 bg-warning">' + item.priority + '</span>';
        } else if (jQuery.inArray(item.priority, info_class) !== -1) {
          item.priority = '<span class="badge fs-5 bg-info">' + item.priority + '</span>';
        }
      });
    } else if (table == 'apilog') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        if (item.method == 'GET') {
          item.method = '<span class="badge fs-5 bg-success">' + item.method + '</span>';
        } else if (item.method == 'POST') {
          item.method = '<span class="badge fs-5 bg-warning">' + item.method + '</span>';
        }
        item.data = escapeHtml(item.data);
      });
    } else if (table == 'rllog') {
      $.each(data, function (i, item) {
        if (item.user == null) {
          item.user = "none";
        }
        if (item.rl_hash == null) {
          item.rl_hash = "err";
        }
        item.indicator = '<span style="border-right:6px solid #' + intToRGB(hashCode(item.rl_hash)) + ';padding-left:5px;">&nbsp;</span>';
        if (item.rl_hash != 'err') {
          item.action = '<a href="#" data-action="delete_selected" data-id="single-hash" data-api-url="delete/rlhash" data-item="' + encodeURI(item.rl_hash) + '" class="btn btn-xs btn-danger"><i class="bi bi-trash"></i> ' + lang.reset_limit + '</a>';
        }
      });
    }
    return data
  };
  $('.add_log_lines').on('click', function (e) {
    e.preventDefault();
    var log_table= $(this).data("table")
    var new_nrows = $(this).data("nrows")
    var post_process = $(this).data("post-process")
    var log_url = $(this).data("log-url")
    if (log_table === undefined || new_nrows === undefined || post_process === undefined || log_url === undefined) {
      console.log("no data-table or data-nrows or log_url or data-post-process attr found");
      return;
    }

    // BUG TODO: loading 100 results in loading 10 - loading 1000 results in loading 100
    if (table = $('#' + log_table).DataTable()) {
      var heading = $('#' + log_table).closest('.card').find('.card-header');
      var load_rows = (table.page.len() + 1) + '-' + (table.page.len() + new_nrows)

      $.get('/api/v1/get/logs/' + log_url + '/' + load_rows).then(function(data){
        if (data.length === undefined) { mailcow_alert_box(lang.no_new_rows, "info"); return; }
        var rows = process_table_data(data, post_process);
        var rows_now = (table.page.len() + data.length);
        $(heading).children('.table-lines').text(rows_now)
        mailcow_alert_box(data.length + lang.additional_rows, "success");
        table.rows.add(rows).draw();
      });
    }
  })

  // detect element visibility changes
  function onVisible(element, callback) {
    $(element).ready(function() {
      element_object = document.querySelector(element)
      new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if(entry.intersectionRatio > 0) {
            callback(element_object);
            observer.disconnect();
          }
        });
      }).observe(element_object);
    });
  }
  // Draw Table if tab is active
  onVisible("[id^=postfix_log]", () => draw_postfix_logs());
  onVisible("[id^=dovecot_log]", () => draw_dovecot_logs());
  onVisible("[id^=sogo_log]", () => draw_sogo_logs());
  onVisible("[id^=watchdog_log]", () => draw_watchdog_logs());
  onVisible("[id^=autodiscover_log]", () => draw_autodiscover_logs());
  onVisible("[id^=acme_log]", () => draw_acme_logs());
  onVisible("[id^=api_log]", () => draw_api_logs());
  onVisible("[id^=rl_log]", () => draw_rl_logs());
  onVisible("[id^=ui_logs]", () => draw_ui_logs());
  onVisible("[id^=sasl_logs]", () => draw_sasl_logs());
  onVisible("[id^=netfilter_log]", () => draw_netfilter_logs());
  onVisible("[id^=rspamd_history]", () => draw_rspamd_history());
  onVisible("[id^=rspamd_donut]", () => rspamd_pie_graph());
});
