$(document).ready(function() {
	$('[data-toggle="tooltip"]').tooltip();
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

  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/domain/all',
    jsonp: false,
    error: function () {
      alert('Cannot draw domain table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.aliases = item.aliases_in_domain + " / " + item.max_num_aliases_for_domain;
        item.mailboxes = item.mboxes_in_domain + " / " + item.max_num_mboxes_for_domain;
        item.quota = item.quota_used_in_domain + "/" + item.max_quota_for_domain;
        item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
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
      $('#domain_table').footable({
        "columns": [
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

  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/mailbox/all',
    jsonp: false,
    error: function () {
      alert('Cannot draw mailbox table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.quota = item.quota_used + "/" + item.quota;
        item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
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
      $('#mailbox_table').footable({
        "columns": [
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
        "rows": data,
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

  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/resource/all',
    jsonp: false,
    error: function () {
      alert('Cannot draw resource table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?resource=' + encodeURI(item.name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="/delete.php?resource=' + encodeURI(item.name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
					'</div>';
      });
      $('#resources_table').footable({
        "columns": [
          {"sorted": true,"name":"description","title":lang.description,"style":{"width":"250px"}},
          {"name":"kind","title":lang.kind},
          {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
          {"name":"multiple_bookings","filterable": false,"style":{"maxWidth":"120px","width":"120px"},"title":lang.multiple_bookings,"breakpoints":"xs sm"},
          {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
          {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
        ],
        "empty": lang.empty,
        "rows": data,
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

  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/alias-domain/all',
    jsonp: false,
    error: function () {
      alert('Cannot draw alias domain table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?aliasdomain=' + encodeURI(item.alias_domain) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="/delete.php?aliasdomain=' + encodeURI(item.alias_domain) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
					'</div>';
      });
      $('#aliasdomain_table').footable({
        "columns": [
          {"sorted": true,"name":"alias_domain","title":lang.alias,"style":{"width":"250px"}},
          {"name":"target_domain","title":lang.target_domain},
          {"name":"active","filterable": false,"style":{"maxWidth":"50px","width":"70px"},"title":lang.active},
          {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
        ],
        "empty": lang.empty,
        "rows": data,
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

  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/alias/all',
    jsonp: false,
    error: function () {
      alert('Cannot draw alias table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?alias=' + encodeURI(item.address) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="/delete.php?alias=' + encodeURI(item.address) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-pencil"></span> ' + lang.remove + '</a>' +
					'</div>';
        item.chkbox = '<input type="checkbox" class="alias_item" name="sel_aliases" value="' + item.address + '" />';
        if (item.is_catch_all == 1) {
          item.address = '<div class="label label-default">Catch-All</div> ' + item.address;
        }
        if (item.in_primary_domain !== "") {
          item.domain = "↳ " + item.domain + " (" + item.in_primary_domain + ")";
        }
      });
      ft_aliases = FooTable.init("#alias_table", {
        "columns": [
          {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
          {"sorted": true,"name":"address","title":lang.alias,"style":{"width":"250px"}},
          {"name":"goto","title":lang.target_address},
          {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
          {"name":"active","filterable": false,"style":{"maxWidth":"50px","width":"70px"},"title":lang.active},
          {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
        ],
        "empty": lang.empty,
        "rows": data,
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

      var selected_aliases = [];

      $(document).on('click', 'tr', function(e) {
        if (e.target.type == "checkbox") {
          e.stopPropagation();
        } else {
          var checkbox = $(this).find(':checkbox');
          checkbox.trigger('click');
        }
      });

      $(document).on('change', 'input[name=sel_aliases]:checkbox', function() {
        if ($(this).is(':checked')) {
          selected_aliases.push($(this).val());
        }
        else {
          selected_aliases.splice($.inArray($(this).val(), selected_aliases),1);
        }
      });

      $(document).on('click', '#select_all_aliases', function(e) {
        e.preventDefault();
        var alias_chkbxs = $("input[name=sel_aliases]");
        alias_chkbxs.prop("checked", !alias_chkbxs.prop("checked")).change();
      });

      $(document).on('click', '#activate_selected_alias', function(e) {
        e.preventDefault();
        if (Object.keys(selected_aliases).length !== 0) {
          $.ajax({
            type: "POST",
            dataType: "json",
            data: { "address": JSON.stringify(selected_aliases), "active": "1" },
            url: '/api/v1/edit/alias',
            jsonp: false,
            complete: function (data) {
              window.location.href = window.location.href;
            }
          });
        }
      });

      $(document).on('click', '#deactivate_selected_alias', function(e) {
        e.preventDefault();
        if (Object.keys(selected_aliases).length !== 0) {
          $.ajax({
            type: "POST",
            dataType: "json",
            data: { "address": JSON.stringify(selected_aliases), "active": "0" },
            url: '/api/v1/edit/alias',
            jsonp: false,
            complete: function (data) {
              window.location.href = window.location.href;
            }
          });
        }
      });

      $(document).on('click', '#delete_selected_alias', function(e) {
        e.preventDefault();
        if (Object.keys(selected_aliases).length !== 0) {
          $(document).on('show.bs.modal','#ConfirmDeleteModal', function () {
            $("#ItemsToDelete").empty();
            for (var i in selected_aliases) {
              $("#ItemsToDelete").append("<li>" + selected_aliases[i] + "</li>");
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
              data: { "address": JSON.stringify(selected_aliases) },
              url: '/api/v1/delete/alias',
              jsonp: false,
              complete: function (data) {
              window.location.href = window.location.href;
              }
            });
          })
          .one('click', '#isCanceled', function(e) {
            $('#ConfirmDeleteModal').modal('hide');
          });;

        }
      });

    }

  });
});
