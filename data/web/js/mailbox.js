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
    url: '/json_api.php?action=domain_table_data',
    jsonp: false,
    error: function () {
      alert('Cannot draw domain table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.aliases = item.aliases_in_domain + " / " + item.max_num_aliases_for_domain;
        item.mailboxes = item.mboxes_in_domain + " / " + item.max_num_mboxes_for_domain;
        item.quota = humanFileSize(item.quota_used_in_domain) + " / " + humanFileSize(item.max_quota_for_domain);
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
          {"name":"quota","title":lang.domain_quota},
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
    url: '/json_api.php?action=mailbox_table_data',
    jsonp: false,
    error: function () {
      alert('Cannot draw mailbox table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.quota = humanFileSize(item.quota_used) + " / " + humanFileSize(item.quota);
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
          {"sorted": true,"name":"username","title":lang.username,"style":{"width":"250px"}},
          {"name":"name","title":lang.fname,"breakpoints":"xs sm"},
          {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
          {"name":"quota","title":lang.domain_quota},
          {"name":"spam_aliases","filterable": false,"title":lang.spam_aliases,"breakpoints":"xs sm"},
          {"name":"in_use","filterable": false,"type":"html","title":lang.in_use},
          {"name":"messages","filterable": false,"style":{"width":"90px"},"title":lang.msg_num,"breakpoints":"xs sm"},
          {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
          {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","width":"290px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
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
    url: '/json_api.php?action=resource_table_data',
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
    url: '/json_api.php?action=domain_alias_table_data',
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
    url: '/json_api.php?action=alias_table_data',
    jsonp: false,
    error: function () {
      alert('Cannot draw alias table');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        if (item.is_catch_all == 1) {
          item.address = '<div class="label label-default">Catch-All</div> ' + item.address;
        }
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?alias=' + encodeURI(item.address) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
          '<a href="/delete.php?alias=' + encodeURI(item.address) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
					'</div>';
      });
      $('#alias_table').footable({
        "columns": [
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
    }
  });
});
