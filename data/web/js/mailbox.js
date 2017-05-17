$(document).ready(function() {
  // Collect values of input fields with name multi_select with same data-id to js array multi_data[data-id]
  var multi_data = [];
  $(document).on('change', 'input[name=multi_select]:checkbox', function() {
    if ($(this).is(':checked') && $(this).data('id')) {
      var id = $(this).data('id');
      if (typeof multi_data[id] == "undefined") {
        multi_data[id] = [];
      }
      multi_data[id].push($(this).val());
    }
    else {
      var id = $(this).data('id');
      multi_data[id].splice($.inArray($(this).val(), multi_data[id]),1);
    }
  });
  // Select checkbox by click on parent tr
  $(document).on('click', 'tbody>tr', function(e) {
    if (e.target.type == "checkbox") {
      e.stopPropagation();
    } else {
      var checkbox = $(this).find(':checkbox');
      checkbox.trigger('click');
    }
  });
  // Select or deselect all checkboxes with same data-id
  $(document).on('click', '#toggle_multi_select_all', function(e) {
    e.preventDefault();
    id = $(this).data("id");
    multi_data[id] = [];
    var all_checkboxes = $("input[data-id=" + id + "]:enabled");
    all_checkboxes.prop("checked", !all_checkboxes.prop("checked")).change();
  });
  // General API edit actions
  $(document).on('click', '#edit_selected', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    if (typeof multi_data[id] == "undefined") return;
    data_array = multi_data[id];
    api_url = $(this).data('api-url');
    api_attr = $(this).data('api-attr');
    if (Object.keys(data_array).length !== 0) {
      $.ajax({
        type: "POST",
        dataType: "json",
        data: { "items": JSON.stringify(data_array), "attr": JSON.stringify(api_attr), "csrf_token": csrf_token },
        url: '/api/v1/' + api_url,
        jsonp: false,
        complete: function (data) {
          // var reponse = (JSON.parse(data.responseText));
          // console.log(reponse.type);
          // console.log(reponse.msg);
          location.assign(window.location);
        }
      });
    }
  });
  // General API delete actions
  $(document).on('click', '#delete_selected', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    if (typeof multi_data[id] == "undefined" || multi_data[id] == "") return;
    data_array = multi_data[id];
    api_url = $(this).data('api-url');
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
          data: { "items": JSON.stringify(data_array), "csrf_token": csrf_token },
          url: '/api/v1/' + api_url,
          jsonp: false,
          complete: function (data) {
            location.assign(window.location);
          }
        });
      })
      .one('click', '#isCanceled', function(e) {
        $('#ConfirmDeleteModal').modal('hide');
      });;
  });
});

jQuery(function($){
  // Calculation human readable file sizes
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
  function unix_time_format(tm) {
    var date = new Date(tm ? tm * 1000 : 0);
    return date.toLocaleString();
  }
  function draw_domain_table() {
    ft_domain_table = FooTable.init('#domain_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"domain_name","title":lang.domain,"style":{"width":"250px"}},
        {"name":"aliases","title":lang.aliases,"breakpoints":"xs sm"},
        {"name":"mailboxes","title":lang.mailboxes},
        {"name":"quota","style":{"whiteSpace":"nowrap"},"title":lang.domain_quota,"formatter": function(value){
          res = value.split("/");
          return humanFileSize(res[0]) + " / " + humanFileSize(res[1]);
        },
        "sortValue": function(value){
          res = value.split("/");
          return res[0];
        },
        },
        {"name":"max_quota_for_mbox","title":lang.mailbox_quota,"breakpoints":"xs sm"},
        {"name":"backupmx","filterable": false,"style":{"maxWidth":"120px","width":"120px"},"title":lang.backup_mx,"breakpoints":"xs sm"},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/domain/all',
        jsonp: false,
        error: function (data) {
          console.log('Cannot draw domain table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.aliases = item.aliases_in_domain + " / " + item.max_num_aliases_for_domain;
            item.mailboxes = item.mboxes_in_domain + " / " + item.max_num_mboxes_for_domain;
            item.quota = item.quota_used_in_domain + "/" + item.max_quota_for_domain;
            item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
            item.chkbox = '<input type="checkbox" data-id="domain" name="multi_select" value="' + item.domain_name + '" />';
            if (role == "admin") {
              item.action = '<div class="btn-group">' +
                '<a href="/edit.php?domain=' + encodeURI(item.domain_name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
                '<a href="/delete.php?domain=' + encodeURI(item.domain_name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
                '</div>';
            }
            else {
              item.action = '<div class="btn-group">' +
                '<a href="/edit.php?domain=' + encodeURI(item.domain_name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
                '</div>';
            }
          });
        }
      }),
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
  function draw_mailbox_table() {
    ft_mailbox_table = FooTable.init('#mailbox_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"username","style":{"word-break":"break-all","min-width":"120px"},"title":lang.username},
        {"name":"name","title":lang.fname,"style":{"word-break":"break-all","min-width":"120px"},"breakpoints":"xs sm"},
        {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
        {"name":"quota","style":{"whiteSpace":"nowrap"},"title":lang.domain_quota,"formatter": function(value){
          res = value.split("/");
          return humanFileSize(res[0]) + " / " + humanFileSize(res[1]);
        },
        "sortValue": function(value){
          res = value.split("/");
          return res[0];
        },
        },
        {"name":"spam_aliases","filterable": false,"title":lang.spam_aliases,"breakpoints":"xs sm md"},
        {"name":"in_use","filterable": false,"type":"html","title":lang.in_use},
        {"name":"messages","filterable": false,"title":lang.msg_num,"breakpoints":"xs sm md"},
        {"name":"active","filterable": false,"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","min-width":"250px"},"type":"html","title":lang.action,"breakpoints":"xs sm md"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/mailbox/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw mailbox table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.quota = item.quota_used + "/" + item.quota;
            item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
            item.chkbox = '<input type="checkbox" data-id="mailbox" name="multi_select" value="' + item.username + '" />';
            if (role == "admin") {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?mailbox=' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="/delete.php?mailbox=' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '<a href="/index.php?duallogin=' + encodeURI(item.username) + '" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-user"></span> Login</a>' +
              '</div>';
            }
            else {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?mailbox=' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="/delete.php?mailbox=' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            }
            item.in_use = '<div class="progress">' +
              '<div class="progress-bar progress-bar-' + item.percent_class + ' role="progressbar" aria-valuenow="' + item.percent_in_use + '" aria-valuemin="0" aria-valuemax="100" ' +
              'style="min-width:2em;width:' + item.percent_in_use + '%">' + item.percent_in_use + '%' + '</div></div>';

          });
        }
      }),
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
  function draw_resource_table() {
    ft_resource_table = FooTable.init('#resource_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"description","title":lang.description,"style":{"width":"250px"}},
        {"name":"kind","title":lang.kind},
        {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
        {"name":"multiple_bookings","filterable": false,"style":{"maxWidth":"120px","width":"120px"},"title":lang.multiple_bookings,"breakpoints":"xs sm"},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/resource/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw resource table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?resource=' + encodeURI(item.name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="/delete.php?resource=' + encodeURI(item.name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="resource" name="multi_select" value="' + item.name + '" />';
          });
        }
      }),
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

  function draw_alias_table() {
    ft_alias_table = FooTable.init('#alias_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"address","title":lang.alias,"style":{"width":"250px"}},
        {"name":"goto","title":lang.target_address},
        {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
        {"name":"active","filterable": false,"style":{"maxWidth":"50px","width":"70px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/alias/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw alias table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?alias=' + encodeURI(item.address) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="/delete.php?alias=' + encodeURI(item.address) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-pencil"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="alias" name="multi_select" value="' + item.address + '" />';
            if (item.is_catch_all == 1) {
              item.address = '<div class="label label-default">Catch-All</div> ' + item.address;
            }
            if (item.in_primary_domain !== "") {
              item.domain = "↳ " + item.domain + " (" + item.in_primary_domain + ")";
            }
          });
        }
      }),
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

  function draw_aliasdomain_table() {
    ft_aliasdomain_table = FooTable.init('#aliasdomain_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"alias_domain","title":lang.alias,"style":{"width":"250px"}},
        {"name":"target_domain","title":lang.target_domain},
        {"name":"active","filterable": false,"style":{"maxWidth":"50px","width":"70px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/alias-domain/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw alias domain table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?aliasdomain=' + encodeURI(item.alias_domain) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="/delete.php?aliasdomain=' + encodeURI(item.alias_domain) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="alias-domain" name="multi_select" value="' + item.alias_domain + '" />';
          });
        }
      }),
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

  draw_domain_table();
  draw_mailbox_table();
  draw_resource_table();
  draw_alias_table();
  draw_aliasdomain_table();
});