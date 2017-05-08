$(document).ready(function() {
  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/domain-admin/all',
    jsonp: false,
    error: function () {
      console.log('Cannot draw domain admin table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?domainadmin=' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="/delete.php?domainadmin=' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
					'</div>';
      });
      $('#domainadminstable').footable({
        "columns": [
          {"sorted": true,"name":"username","title":lang.username,"style":{"width":"250px"}},
          {"name":"selected_domains","title":lang.admin_domains,"breakpoints":"xs sm"},
          {"name":"tfa_active","title":"TFA", "filterable": false,"style":{"maxWidth":"80px","width":"80px"}},
          {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
          {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
        ],
        "rows": data,
        "empty": lang.empty,
        "paging": {
          "enabled": true,
          "limit": 5,
          "size": pagination_size
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
  });
  $("#refresh_dovecot_log").on('click', function(e) {
      function unix_time_format(tm) {
        var date = new Date(tm ? tm * 1000 : 0);
        return date.toLocaleString();
      }
      e.preventDefault();
      if (typeof ft_dovecot_logs != 'undefined') {
        ft_dovecot_logs.destroy();
      }
      $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/dovecot/1000',
        jsonp: false,
        error: function () {
          console.log('Cannot draw dovecot log table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            var danger_class = ["emerg", "alert", "crit"];
            var warning_class = ["warning"];
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
          ft_dovecot_logs = FooTable.init("#dovecot_log", {
            "columns": [
              {"name":"time","formatter":function unix_time_format(tm) {var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
              {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
              {"name":"message","title":lang.message},
            ],
            "rows": data,
            "empty": lang.empty,
            "paging": {
              "enabled": true,
              "limit": 5,
              "size": pagination_size
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
      });
  });
  $("#refresh_postfix_log").on('click', function(e) {
      function unix_time_format(tm) {
        var date = new Date(tm ? tm * 1000 : 0);
        return date.toLocaleString();
      }
      e.preventDefault();
      if (typeof ft_postfix_logs != 'undefined') {
        ft_postfix_logs.destroy();
      }
      $.ajax({
        dataType: 'json',
        url: '/api/v1/get/logs/postfix/1000',
        jsonp: false,
        error: function () {
          console.log('Cannot draw postfix log table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            var danger_class = ["emerg", "alert", "crit"];
            var warning_class = ["warning"];
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
          ft_postfix_logs = FooTable.init("#postfix_log", {
            "columns": [
              {"name":"time","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.time,"style":{"width":"170px"}},
              {"name":"priority","title":lang.priority,"style":{"width":"80px"}},
              {"name":"message","title":lang.message},
            ],
            "rows": data,
            "empty": lang.empty,
            "paging": {
              "enabled": true,
              "limit": 5,
              "size": pagination_size
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
      });
  });
  $("#refresh_dovecot_log").trigger('click');
  $("#refresh_postfix_log").trigger('click');
});