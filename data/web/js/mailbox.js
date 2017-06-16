$(document).ready(function() {
  // Auto-fill domain quota when adding new domain
  auto_fill_quota = function(domain) {
		$.get("/api/v1/get/domain/" + domain, function(data){
      var result = $.parseJSON(JSON.stringify(data));
      max_new_mailbox_quota = ( result.max_new_mailbox_quota / 1048576);
			if (max_new_mailbox_quota != '0') {
				$("#quotaBadge").html('max. ' +  max_new_mailbox_quota + ' MiB');
				$('#addInputQuota').attr({"disabled": false, "value": "", "type": "number", "max": max_new_mailbox_quota});
				$('#addInputQuota').val(max_new_mailbox_quota);
			}
			else {
				$("#quotaBadge").html('max. ' + max_new_mailbox_quota + ' MiB');
				$('#addInputQuota').attr({"disabled": true, "value": "", "type": "text", "value": "n/a"});
				$('#addInputQuota').val(max_new_mailbox_quota);
			}
		});
  }
	$('#addSelectDomain').on('change', function() {
    auto_fill_quota($('#addSelectDomain').val());
	});
  auto_fill_quota($('#addSelectDomain').val());

  $(".generate_password").click(function( event ) {
    event.preventDefault();
    var random_passwd = Math.random().toString(36).slice(-8)
    $('#password').prop('type', 'text');
    $('#password').val(random_passwd);
    $('#password2').prop('type', 'text');
    $('#password2').val(random_passwd);
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
                '<a href="#" id="delete_selected" data-id="single-domain" data-api-url="delete/domain" data-item="' + encodeURI(item.domain_name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
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
              '<a href="#" id="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '<a href="/index.php?duallogin=' + encodeURI(item.username) + '" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-user"></span> Login</a>' +
              '</div>';
            }
            else {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?mailbox=' + encodeURI(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURI(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
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
              '<a href="#" id="delete_selected" data-id="single-resource" data-api-url="delete/resource" data-item="' + encodeURI(item.name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
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
              '<a href="#" id="delete_selected" data-id="single-alias" data-api-url="delete/alias" data-item="' + encodeURI(item.address) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
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
              '<a href="#" id="delete_selected" data-id="single-alias-domain" data-api-url="delete/alias-domain" data-item="' + encodeURI(item.alias_domain) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
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