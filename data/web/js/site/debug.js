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

  // set update loop container list
  containersToUpdate = {}
  // set default ChartJs Font Color
  Chart.defaults.color = '#999';
  // create host cpu and mem charts
  createHostCpuAndMemChart();
  // check for new version
  check_update(mailcow_info.version_tag, mailcow_info.project_url);
  update_container_stats()
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
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#autodiscover_log') ) {
      $('#autodiscover_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'User-Agent',
          data: 'ua',
          defaultContent: ''
        },
        {
          title: 'Username',
          data: 'user',
          defaultContent: ''
        },
        {
          title: 'IP',
          data: 'ip',
          defaultContent: ''
        },
        {
          title: 'Service',
          data: 'service',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_postfix_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#postfix_log') ) {
      $('#postfix_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority',
          defaultContent: ''
        },
        {
          title: lang.message,
          data: 'message',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_watchdog_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#watchdog_log') ) {
      $('#watchdog_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'Service',
          data: 'service',
          defaultContent: ''
        },
        {
          title: 'Trend',
          data: 'trend',
          defaultContent: ''
        },
        {
          title: lang.message,
          data: 'message',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_api_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#api_log') ) {
      $('#api_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'URI',
          data: 'uri',
          defaultContent: '',
          className: 'text-break min-tablet none'
        },
        {
          title: 'Method',
          data: 'method',
          defaultContent: ''
        },
        {
          title: 'IP',
          data: 'remote',
          defaultContent: ''
        },
        {
          title: 'Data',
          data: 'data',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_rl_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#rl_log') ) {
      $('#rl_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          data: 'indicator',
          defaultContent: ''
        },
        {
          title: lang.time,
          data: 'time',
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.rate_name,
          data: 'rl_name',
          defaultContent: ''
        },
        {
          title: lang.sender,
          data: 'from',
          defaultContent: ''
        },
        {
          title: lang.recipients,
          data: 'rcpt',
          defaultContent: ''
        },
        {
          title: lang.authed_user,
          data: 'user',
          defaultContent: ''
        },
        {
          title: 'Msg ID',
          data: 'message_id',
          defaultContent: ''
        },
        {
          title: 'Header From',
          data: 'header_from',
          defaultContent: ''
        },
        {
          title: 'Subject',
          data: 'header_subject',
          defaultContent: ''
        },
        {
          title: 'Hash',
          data: 'rl_hash',
          defaultContent: ''
        },
        {
          title: 'Rspamd QID',
          data: 'qid',
          defaultContent: ''
        },
        {
          title: 'IP',
          data: 'ip',
          defaultContent: ''
        },
        {
          title: lang.action,
          data: 'action',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_ui_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#ui_logs') ) {
      $('#ui_logs').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'Type',
          data: 'type',
          defaultContent: ''
        },
        {
          title: 'Task',
          data: 'task',
          defaultContent: ''
        },
        {
          title: 'User',
          data: 'user',
          defaultContent: ''
        },
        {
          title: 'Role',
          data: 'role',
          defaultContent: ''
        },
        {
          title: 'IP',
          data: 'remote',
          defaultContent: ''
        },
        {
          title: lang.message,
          data: 'msg',
          defaultContent: ''
        },
        {
          title: 'Call',
          data: 'call',
          defaultContent: '',
          className: 'none text-break'
        }
      ]
    });
  }
  function draw_sasl_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#sasl_logs') ) {
      $('#sasl_logs').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          data: 'username',
          defaultContent: ''
        },
        {
          title: lang.service,
          data: 'service',
          defaultContent: ''
        },
        {
          title: 'IP',
          data: 'real_rip',
          defaultContent: ''
        },
        {
          title: lang.login_time,
          data: 'datetime',
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data.replace(/-/g, "/")); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        }
      ]
    });
  }
  function draw_acme_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#acme_log') ) {
      $('#acme_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.message,
          data: 'message',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_netfilter_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#netfilter_log') ) {
      $('#netfilter_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority',
          defaultContent: ''
        },
        {
          title: lang.message,
          data: 'message',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_sogo_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#sogo_log') ) {
      $('#sogo_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority',
          defaultContent: ''
        },
        {
          title: lang.message,
          data: 'message',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_dovecot_logs() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#dovecot_log') ) {
      $('#dovecot_log').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: lang.priority,
          data: 'priority',
          defaultContent: ''
        },
        {
          title: lang.message,
          data: 'message',
          defaultContent: ''
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
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#rspamd_history') ) {
      $('#rspamd_history').DataTable().columns.adjust().responsive.recalc();
      return;
    }

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
          defaultContent: '',
          render: function(data, type){
            var date = new Date(data ? data * 1000 : 0); 
            return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
          }
        },
        {
          title: 'IP address',
          data: 'ip',
          defaultContent: ''
        },
        {
          title: 'From',
          data: 'sender_mime',
          defaultContent: ''
        },
        {
          title: 'To',
          data: 'rcpt',
          defaultContent: ''
        },
        {
          title: 'Subject',
          data: 'subject',
          defaultContent: ''
        },
        {
          title: 'Action',
          data: 'action',
          defaultContent: ''
        },
        {
          title: 'Score',
          data: 'score',
          defaultContent: ''
        },
        {
          title: 'Subject',
          data: 'header_subject',
          defaultContent: ''
        },
        {
          title: 'Symbols',
          data: 'symbols',
          defaultContent: ''
        },
        {
          title: 'Msg size',
          data: 'size',
          defaultContent: ''
        },
        {
          title: 'Scan Time',
          data: 'scan_time',
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'message-id',
          defaultContent: ''
        },
        {
          title: 'Authenticated user',
          data: 'user',
          defaultContent: ''
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
        item.action = "<div class='badge fs-6 bg-success'>" + item.action + "</div>";
      } else if (item.action === 'rewrite subject' || item.action === 'add header' || item.action === 'probable spam') {
        item.action = "<div class='badge fs-6 bg-warning'>" + item.action + "</div>";
      } else if (item.action === 'spam' || item.action === 'reject') {
        item.action = "<div class='badge fs-6 bg-danger'>" + item.action + "</div>";
      } else {
        item.action = "<div class='badge fs-6 bg-info'>" + item.action + "</div>";
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
          item.service = '<span class="badge fs-6 bg-info">ActiveSync</span>';
        }
        else if (item.service == "imap") {
          item.service = '<span class="badge fs-6 bg-success">IMAP, SMTP, Cal-/CardDAV</span>';
        }
        else {
          item.service = '<span class="badge fs-6 bg-danger">' + escapeHtml(item.service) + '</span>';
        }
      });
    } else if (table == 'watchdog') {
      $.each(data, function (i, item) {
        if (item.message == null) {
          item.message = 'Health level: ' + item.lvl + '% (' + item.hpnow + '/' + item.hptotal + ')';
          if (item.hpdiff < 0) {
            item.trend = '<span class="badge fs-6 bg-danger"><i class="bi bi-caret-down-fill"></i> ' + item.hpdiff + '</span>';
          }
          else if (item.hpdiff == 0) {
            item.trend = '<span class="badge fs-6 bg-info"><i class="bi bi-caret-right-fill"></i> ' + item.hpdiff + '</span>';
          }
          else {
            item.trend = '<span class="badge fs-6 bg-success"><i class="bi bi-caret-up-fill"></i> ' + item.hpdiff + '</span>';
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
        item.type = '<span class="badge fs-6 bg-' + item.type + '">' + item.type + '</span>';
      });
    } else if (table == 'sasl_log_table') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        item.username = escapeHtml(item.username);
        item.service = '<div class="badge fs-6 bg-secondary">' + item.service.toUpperCase() + '</div>';
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
          item.priority = '<span class="badge fs-6 bg-danger">' + item.priority + '</span>';
        } else if (jQuery.inArray(item.priority, warning_class) !== -1) {
          item.priority = '<span class="badge fs-6 bg-warning">' + item.priority + '</span>';
        } else if (jQuery.inArray(item.priority, info_class) !== -1) {
          item.priority = '<span class="badge fs-6 bg-info">' + item.priority + '</span>';
        }
      });
    } else if (table == 'apilog') {
      $.each(data, function (i, item) {
        if (item === null) { return true; }
        if (item.method == 'GET') {
          item.method = '<span class="badge fs-6 bg-success">' + item.method + '</span>';
        } else if (item.method == 'POST') {
          item.method = '<span class="badge fs-6 bg-warning">' + item.method + '</span>';
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



  // start polling host stats if tab is active
  onVisible("[id^=tab-containers]", () => update_stats());
  // start polling container stats if collapse is active
  var containerElements = document.querySelectorAll(".container-details-collapse");
  for (let i = 0; i < containerElements.length; i++){
    new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if(entry.intersectionRatio > 0) {

          if (!containerElements[i].classList.contains("show")){
            var container = containerElements[i].id.replace("Collapse", "");
            var container_id = containerElements[i].getAttribute("data-id");

            // check if chart exists or needs to be created
            if (!Chart.getChart(container + "_DiskIOChart"))
              createReadWriteChart(container + "_DiskIOChart", "Read", "Write");
            if (!Chart.getChart(container + "_NetIOChart"))
              createReadWriteChart(container + "_NetIOChart", "Recv", "Sent");

            // add container to polling list
            containersToUpdate[container] = {
              id: container_id,
              state: "idle"
            }

            // stop polling if collapse is closed
            containerElements[i].addEventListener('hidden.bs.collapse', function () {
              var diskIOCtx = Chart.getChart(container + "_DiskIOChart");
              var netIOCtx = Chart.getChart(container + "_NetIOChart");

              diskIOCtx.data.datasets[0].data = [];
              diskIOCtx.data.datasets[1].data = [];
              diskIOCtx.data.labels = [];
              netIOCtx.data.datasets[0].data = [];
              netIOCtx.data.datasets[1].data = [];
              netIOCtx.data.labels = [];
            
              diskIOCtx.update();
              netIOCtx.update();
              
              delete containersToUpdate[container];
            });
          }

        }
      });
    }).observe(containerElements[i]);
  }
});


// update system stats - every 5 seconds if system & container tab is active
function update_stats(timeout=5){
  if (!$('#tab-containers').hasClass('active')) {
    // tab not active - dont fetch stats - run again in n seconds
    return;
  }

  window.fetch("/api/v1/get/status/host", {method:'GET',cache:'no-cache'}).then(function(response) {
    return response.json();
  }).then(function(data) {
    console.log(data);

    if (data){
      // display table data
      $("#host_date").text(data.system_time);
      $("#host_uptime").text(formatUptime(data.uptime));
      $("#host_cpu_cores").text(data.cpu.cores);
      $("#host_cpu_usage").text(parseInt(data.cpu.usage).toString() + "%");
      $("#host_memory_total").text((data.memory.total / (1024 ** 3)).toFixed(2).toString() + "GB");
      $("#host_memory_usage").text(parseInt(data.memory.usage).toString() + "%");

      // update cpu and mem chart
      var cpu_chart = Chart.getChart("host_cpu_chart");
      var mem_chart = Chart.getChart("host_mem_chart");

      cpu_chart.data.labels.push(data.system_time.split(" ")[1]);
      if (cpu_chart.data.labels.length > 30) cpu_chart.data.labels.shift();
      mem_chart.data.labels.push(data.system_time.split(" ")[1]);
      if (mem_chart.data.labels.length > 30) mem_chart.data.labels.shift();

      cpu_chart.data.datasets[0].data.push(data.cpu.usage);
      if (cpu_chart.data.datasets[0].data.length > 30)  cpu_chart.data.datasets[0].data.shift();
      mem_chart.data.datasets[0].data.push(data.memory.usage);
      if (mem_chart.data.datasets[0].data.length > 30)  mem_chart.data.datasets[0].data.shift();

      cpu_chart.update();
      mem_chart.update();
    }

    // run again in n seconds
    setTimeout(update_stats, timeout * 1000);
  });
}
// update specific container stats - every n (default 5s) seconds
function update_container_stats(timeout=5){
  for (let container in containersToUpdate){
    container_id = containersToUpdate[container].id;
    if (containersToUpdate[container].state == "running")
      continue;
    containersToUpdate[container].state = "running";


    window.fetch("/api/v1/get/status/container/" + container_id, {method:'GET',cache:'no-cache'}).then(function(response) {
      return response.json();
    }).then(function(data) {
      var diskIOCtx = Chart.getChart(container + "_DiskIOChart");
      var netIOCtx = Chart.getChart(container + "_NetIOChart");

      console.log(container);
      console.log(data);
      prev_stats = null;
      if (data.length >= 2)
        prev_stats = data[data.length -2]
      data = data[data.length -1];

      if (prev_stats != null){
        // calc time diff
        var time_diff = (new Date(data.read) - new Date(prev_stats.read)) / 1000;
  
        // calc disk io b/s
        var prev_read_bytes = 0;
        var prev_write_bytes = 0;
        for (var i = 0; i < prev_stats.blkio_stats.io_service_bytes_recursive.length; i++){
          if (prev_stats.blkio_stats.io_service_bytes_recursive[i].op == "read")
            prev_read_bytes = prev_stats.blkio_stats.io_service_bytes_recursive[i].value;
          else if (prev_stats.blkio_stats.io_service_bytes_recursive[i].op == "write")
            prev_write_bytes = prev_stats.blkio_stats.io_service_bytes_recursive[i].value;
        }
        var read_bytes = 0;
        var write_bytes = 0;
        for (var i = 0; i < data.blkio_stats.io_service_bytes_recursive.length; i++){
          if (data.blkio_stats.io_service_bytes_recursive[i].op == "read")
            read_bytes = data.blkio_stats.io_service_bytes_recursive[i].value;
          else if (data.blkio_stats.io_service_bytes_recursive[i].op == "write")
            write_bytes = data.blkio_stats.io_service_bytes_recursive[i].value;
        }
        var diff_bytes_read = (read_bytes - prev_read_bytes) / time_diff;
        var diff_bytes_write = (write_bytes - prev_write_bytes) / time_diff;
  
        // calc net io b/s
        var prev_recv_bytes = 0;
        var prev_sent_bytes = 0;
        for (var key in prev_stats.networks){
          prev_recv_bytes += prev_stats.networks[key].rx_bytes;
          prev_sent_bytes += prev_stats.networks[key].tx_bytes;
        }
        var recv_bytes = 0;
        var sent_bytes = 0;
        for (var key in data.networks){
          recv_bytes += data.networks[key].rx_bytes;
          sent_bytes += data.networks[key].tx_bytes;
        }
        var diff_bytes_recv = (recv_bytes - prev_recv_bytes) / time_diff;
        var diff_bytes_sent = (sent_bytes - prev_sent_bytes) / time_diff;
  
        addReadWriteChart(diskIOCtx, diff_bytes_read, diff_bytes_write, "");
        addReadWriteChart(netIOCtx, diff_bytes_recv, diff_bytes_sent, "");
      }
  
      // run again in n seconds
      containersToUpdate[container].state = "idle";
    }).catch(err => {
      console.log(err);
    });
  }

  // run again in n seconds
  setTimeout(update_container_stats, timeout * 1000);
}
// format hosts uptime seconds to readable string
function formatUptime(seconds){
  seconds = Number(seconds);
  var d = Math.floor(seconds / (3600*24));
  var h = Math.floor(seconds % (3600*24) / 3600);
  var m = Math.floor(seconds % 3600 / 60);
  var s = Math.floor(seconds % 60);

  var dFormat = d > 0 ? d + "D " : "";
  var hFormat = h > 0 ? h + "H " : "";
  var mFormat = m > 0 ? m + "M " : "";
  var sFormat = s > 0 ? s + "S" : "";
  return dFormat + hFormat + mFormat + sFormat;
} 
// format bytes to readable string
function formatBytes(bytes){
  // b
  if (bytes < 1000) return bytes.toFixed(2).toString()+' B/s';
  // b to kb
  bytes = bytes / 1024;
  if (bytes < 1000) return bytes.toFixed(2).toString()+' KB/s';
  // kb to mb
  bytes = bytes / 1024;
  if (bytes < 1000) return bytes.toFixed(2).toString()+' MB/s';
  // final mb to gb
  return (bytes / 1024).toFixed(2).toString()+' GB/s';
}
// create read write line chart
function createReadWriteChart(chart_id, read_lable, write_lable){
  var ctx = document.getElementById(chart_id);

  var dataNet = {
    labels: [],
    datasets: [{
      label: read_lable,
      backgroundColor: "rgba(41, 187, 239, 0.3)",
      borderColor: "rgba(41, 187, 239, 0.6)",
      pointRadius: 1,
      pointHitRadius: 6,
      borderWidth: 2,
      fill: true,
      tension: 0.2,
      data: []
    }, {
      label: write_lable,
      backgroundColor: "rgba(239, 60, 41, 0.3)",
      borderColor: "rgba(239, 60, 41, 0.6)",
      pointRadius: 1,
      pointHitRadius: 6,
      borderWidth: 2,
      fill: true,
      tension: 0.2,
      data: []
    }]
  };
  var optionsNet = {
    interaction: {
        mode: 'index'
    },
    scales: {
      yAxis: {
        min: 0,
        grid: {
            display: false
        },
        ticks: {
          callback: function(i, index, ticks) {
             return formatBytes(i);
          }
        }  
      },
      xAxis: {
        grid: {
            display: false
        }  
      }
    }
  };
  
  return new Chart(ctx, {
    type: 'line',
    data: dataNet,
    options: optionsNet
  });
}
// add to read write line chart
function addReadWriteChart(chart_context, read_point, write_point, time, limit = 30){
  // push time label for x-axis
  chart_context.data.labels.push(time);
  if (chart_context.data.labels.length > limit) chart_context.data.labels.shift();

  // push datapoints
  chart_context.data.datasets[0].data.push(read_point);
  chart_context.data.datasets[1].data.push(write_point);
  // shift data if more than 20 entires exists
  if (chart_context.data.datasets[0].data.length > limit)  chart_context.data.datasets[0].data.shift();
  if (chart_context.data.datasets[1].data.length > limit) chart_context.data.datasets[1].data.shift();

  chart_context.update();
}
// create host cpu and mem chart
function createHostCpuAndMemChart(){
  var cpu_ctx = document.getElementById("host_cpu_chart");
  var mem_ctx = document.getElementById("host_mem_chart");

  var dataCpu = {
    labels: [],
    datasets: [{
      label: "CPU %",
      backgroundColor: "rgba(41, 187, 239, 0.3)",
      borderColor: "rgba(41, 187, 239, 0.6)",
      pointRadius: 1,
      pointHitRadius: 6,
      borderWidth: 2,
      fill: true,
      tension: 0.2,
      data: []
    }]
  };
  var optionsCpu = {
    interaction: {
        mode: 'index'
    },
    scales: {
      yAxis: {
        min: 0,
        grid: {
            display: false
        },
        ticks: {
          callback: function(i, index, ticks) {
             return i.toFixed(0).toString() + "%";
          }
        }  
      },
      xAxis: {
        grid: {
            display: false
        }  
      }
    }
  };

  var dataMem = {
    labels: [],
    datasets: [{
      label: "MEM %",
      backgroundColor: "rgba(41, 187, 239, 0.3)",
      borderColor: "rgba(41, 187, 239, 0.6)",
      pointRadius: 1,
      pointHitRadius: 6,
      borderWidth: 2,
      fill: true,
      tension: 0.2,
      data: []
    }]
  };
  var optionsMem = {
    interaction: {
        mode: 'index'
    },
    scales: {
      yAxis: {
        min: 0,
        grid: {
            display: false
        },
        ticks: {
          callback: function(i, index, ticks) {
            return i.toFixed(0).toString() + "%";
          }
        }  
      },
      xAxis: {
        grid: {
            display: false
        }  
      }
    }
  };

  
  var net_io_chart = new Chart(cpu_ctx, {
    type: 'line',
    data: dataCpu,
    options: optionsCpu
  });
  var disk_io_chart = new Chart(mem_ctx, {
    type: 'line',
    data: dataMem,
    options: optionsMem
  });
}
// check for mailcow updates
function check_update(current_version, github_repo_url){
  var github_account = github_repo_url.split("/")[3];
  var github_repo_name = github_repo_url.split("/")[4];

  // get details about latest release
  window.fetch("https://api.github.com/repos/"+github_account+"/"+github_repo_name+"/releases/latest", {method:'GET',cache:'no-cache'}).then(function(response) {
    return response.json();
  }).then(function(latest_data) {
    // get details about current release
    window.fetch("https://api.github.com/repos/"+github_account+"/"+github_repo_name+"/releases/tags/"+current_version, {method:'GET',cache:'no-cache'}).then(function(response) {
      return response.json();
    }).then(function(current_data) {
      // compare releases
      var date_current = new Date(current_data.created_at);
      var date_latest = new Date(latest_data.created_at);
      if (date_latest.getTime() <= date_current.getTime()){
        // no update available
        $("#mailcow_update").removeClass("text-warning text-danger").addClass("text-success");
        $("#mailcow_update").html("<b>" + lang_debug.no_update_available + "</b>");
      } else {
        // update available
        $("#mailcow_update").removeClass("text-danger text-success").addClass("text-warning");
        $("#mailcow_update").html(
          `<b>` + lang_debug.update_available + `
          <a target="_blank" href="https://github.com/`+github_account+`/`+github_repo_name+`/releases/tag/`+latest_data.tag_name+`">`+latest_data.tag_name+`</a></b>`
        );
      }
    }).catch(err => {
      // err
      console.log(err);
      $("#mailcow_update").removeClass("text-success text-warning").addClass("text-danger");
      $("#mailcow_update").html("<b>"+ lang_debug.update_failed +"</b>");
    });
  }).catch(err => {
    // err
    console.log(err);
    $("#mailcow_update").removeClass("text-success text-warning").addClass("text-danger");
    $("#mailcow_update").html("<b>"+ lang_debug.update_failed +"</b>");
  });
}
