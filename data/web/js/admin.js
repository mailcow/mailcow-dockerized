$(document).ready(function() {
  
  // Collect values of input fields with name multi_select to js array multi_data[data-id-of-checkbox]
  var multi_data = [];
  $(document).on('change', 'input[name=multi_select]:checkbox', function() {
    if ($(this).is(':checked') && $(this).attr('data-form-id')) {
      var id = $(this).data('form-id');
      if (typeof multi_data[id] == "undefined") {
        multi_data[id] = [];
      }
      multi_data[id].push($(this).val());
    }
    else {
      var id = $(this).data('form-id');
      multi_data[id].splice($.inArray($(this).val(), multi_data[id]),1);
    }
  });
  // Select by click on tr
  $(document).on('click', 'tr', function(e) {
    if (e.target.type == "checkbox") {
      e.stopPropagation();
    } else {
      var checkbox = $(this).find(':checkbox');
      checkbox.trigger('click');
    }
  });
  // Select or deselect all checkboxes with same data-form-id
  $(document).on('click', '#toggle_multi_select_all', function(e) {
    e.preventDefault();
    var closests_checkboxes = $("input[data-form-id=" + $(this).attr("data-form-id") + "]");
    closests_checkboxes.prop("checked", !closests_checkboxes.prop("checked")).change();
  });

  // Draw domain admin table
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
  
  // Draw fwd hosts table
  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/fwdhost/all',
    jsonp: false,
    error: function () {
      console.log('Cannot draw forwarding hosts table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="/delete.php?forwardinghost=' + encodeURI(item.host) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
					'</div>';
        if (item.keep_spam == "yes") {
          item.keep_spam = lang.no;
        }
        else {
          item.keep_spam = lang.yes;
        }
        item.chkbox = '<input type="checkbox" data-form-id="fwdhosts" name="multi_select" value="' + item.host + '" />';
      });
      $('#forwardinghoststable').footable({
        "columns": [
          {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
          {"name":"host","type":"text","title":lang.host,"style":{"width":"250px"}},
          {"name":"source","title":lang.source,"breakpoints":"xs sm"},
          {"name":"keep_spam","title":lang.spamfilter, "type": "text","style":{"maxWidth":"80px","width":"80px"}},
          {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
        ],
        "rows": data,
        "empty": lang.empty,
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
  });
  
  $(document).on('click', '#delete_fwdhosts', function(e) {
    e.preventDefault();
    var id = $(this).closest("form").attr('data-id');
    if (typeof multi_data[id] == "undefined") return;
    data_array = multi_data[id];
    if (Object.keys(data_array).length !== 0) {
      $(document).on('show.bs.modal','#ConfirmDeleteModal', function () {
        $("#ItemsToDelete").empty();
        for (var i in data_array) {
          $("#ItemsToDelete").append("<li>" + data_array[i] + "</li>");
        }
      })
      $('#ConfirmDeleteModal').modal({
        backdrop: 'static',
        keyboard: false
      })
      .one('click', '#IsConfirmed', function(e) {
        $.ajax({
          type: "POST",
          dataType: "json",
          data: { "forwardinghost": JSON.stringify(data_array) },
          url: '/api/v1/delete/fwdhost',
          jsonp: false,
          complete: function (data) {
            location.reload();
          }
        });
      })
      .one('click', '#isCanceled', function(e) {
        $('#ConfirmDeleteModal').modal('hide');
      });;
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