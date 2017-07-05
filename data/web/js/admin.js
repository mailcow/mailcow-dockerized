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
  function humanFileSize(bytes) {
    if(Math.abs(bytes) < 1024) {
        return bytes + ' B';
    }
    var units = ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
    var u = -1;
    do {
        bytes /= 1024;
        ++u;
    } while(Math.abs(bytes) >= 1024 && u < units.length - 1);
    return bytes.toFixed(1)+' '+units[u];
  }
  $("#refresh_postfix_log").on('click', function(e) {
    e.preventDefault();
    draw_postfix_logs();
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
  function draw_postfix_logs() {
    ft_postfix_logs = FooTable.init('#postfix_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/postfix/1000',
        jsonp: false,
        error: function () {
          console.log('Cannot draw postfix log table');
        },
        success: function (data) {
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
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "filtering": {
        "enabled": true,
        "position": "left",
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
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
        url: '/api/v1/get/logs/fail2ban/1000',
        jsonp: false,
        error: function () {
          console.log('Cannot draw fail2ban log table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            var danger_class = ["emerg", "alert", "crit", "err"];
            var warning_class = ["warning", "warn"];
            var info_class = ["notice", "info", "debug"];
            item.message = escapeHtml(item.message);
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
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "filtering": {
        "enabled": true,
        "position": "left",
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
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
        url: '/api/v1/get/logs/sogo/1000',
        jsonp: false,
        error: function () {
          console.log('Cannot draw sogo log table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            var danger_class = ["emerg", "alert", "crit", "err"];
            var warning_class = ["warning", "warn"];
            var info_class = ["notice", "info", "debug"];
            item.message = escapeHtml(item.message);
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
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "filtering": {
        "enabled": true,
        "position": "left",
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  }
  function draw_dovecot_logs() {
    ft_postfix_logs = FooTable.init('#dovecot_log', {
      "columns": [
        {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
        {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
        {"name":"message","title":lang.message},
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/dovecot/1000',
        jsonp: false,
        error: function () {
          console.log('Cannot draw dovecot log table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            var danger_class = ["emerg", "alert", "crit", "err"];
            var warning_class = ["warning", "warn"];
            var info_class = ["notice", "info", "debug"];
            item.message = escapeHtml(item.message);
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
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "filtering": {
        "enabled": true,
        "position": "left",
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
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
          $.each(data, function (i, item) {
            item.chkbox = '<input type="checkbox" data-id="domain_admins" name="multi_select" value="' + item.username + '" />';
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?domainadmin=' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-domain-admin" data-api-url="delete/domain-admin" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
          });
        }
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "filtering": {
        "enabled": true,
        "position": "left",
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
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
        }
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "sorting": {
        "enabled": true
      }
    });
  }
  function draw_rspamd_history() {
    ft_postfix_logs = FooTable.init('#rspamd_history', {
      "columns": [{
          "name":"unix_time",
          "formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},
          "title":lang.time,
          "style":{
            "width":"170px"
          }
        }, {
          "name": "ip",
          "title": "IP address",
          "breakpoints": "all",
          "style": {
            "minWidth": 88
          }
        }, {
          "name": "sender_mime",
          "title": "From",
          "breakpoints": "xs sm md",
          "style": {
            "minWidth": 100
          }
        }, {
          "name": "rcpt_mime",
          "title": "To",
          "breakpoints": "xs sm md",
          "style": {
            "minWidth": 100
          }
        }, {
          "name": "subject",
          "title": "Subject",
          "breakpoints": "all",
          "style": {
            "word-break": "break-all",
            "minWidth": 150
          }
        }, {
          "name": "action",
          "title": "Action",
          "style": {
            "minwidth": 82
          }
        }, {
          "name": "score",
          "title": "Score",
          "style": {
            "maxWidth": 110
          },
        }, {
          "name": "symbols",
          "title": "Symbols",
          "breakpoints": "all",
        }, {
          "name": "size",
          "title": "Msg size",
          "breakpoints": "all",
          "style": {
            "minwidth": 50,
          },
          "formatter": function(value) { return humanFileSize(value); }
        }, {
          "name": "scan_time",
          "title": "Scan time",
          "breakpoints": "all",
          "style": {
            "maxWidth": 72
          },
        }, {
        "name": "message-id",
        "title": "ID",
        "breakpoints": "all",
        "style": {
          "minWidth": 130,
          "overflow": "hidden",
          "textOverflow": "ellipsis",
          "wordBreak": "break-all",
          "whiteSpace": "normal"
        }
        }, {
          "name": "user",
          "title": "Authenticated user",
          "breakpoints": "xs sm md",
          "style": {
            "minWidth": 100
          }
        }],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/rspamd-history',
        jsonp: false,
        error: function () {
          console.log('Cannot draw rspamd history table');
        },
        success: function (data) {
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
        }
      }),
      "empty": lang.empty,
      "paging": {
        "enabled": true,
        "limit": 5,
        "size": log_pagination_size
      },
      "filtering": {
        "enabled": true,
        "position": "left",
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  }
  draw_postfix_logs();
  draw_dovecot_logs();
  draw_sogo_logs();
  draw_fail2ban_logs();
  draw_domain_admins();
  draw_fwd_hosts();
  draw_rspamd_history();
});