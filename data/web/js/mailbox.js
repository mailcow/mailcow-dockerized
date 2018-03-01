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

  $("#goto_null").click(function( event ) {
    if ($("#goto_null").is(":checked")) {
      $('#textarea_alias_goto').prop('disabled', true);
    }
    else {
      $("#textarea_alias_goto").removeAttr('disabled');
    }
  });

  // Log modal
  $('#syncjobLogModal').on('show.bs.modal', function(e) {
    var syncjob_id = $(e.relatedTarget).data('syncjob-id');
    $.ajax({
      url: '/inc/ajax/syncjob_logs.php',
      data: { id: syncjob_id },
      dataType: 'text',
      success: function(data){
        $(e.currentTarget).find('#logText').text(data);
      },
      error: function(xhr, status, error) {
        $(e.currentTarget).find('#logText').text(xhr.responseText);
      }
    });
  });

  // Log modal
  $('#dnsInfoModal').on('show.bs.modal', function(e) {
    var domain = $(e.relatedTarget).data('domain');
    $('.dns-modal-body').html('<center><span style="font-size:18pt;margin:50px" class="glyphicon glyphicon-refresh glyphicon-spin"></span></center>');
    $.ajax({
      url: '/inc/ajax/dns_diagnostics.php',
      data: { domain: domain },
      dataType: 'text',
      success: function(data){
        $('.dns-modal-body').html(data);
      },
      error: function(xhr, status, error) {
        $('.dns-modal-body').html(xhr.responseText);
      }
    });
  });

  // Sieve data modal
  $('#sieveDataModal').on('show.bs.modal', function(e) {
    var sieveScript = $(e.relatedTarget).data('sieve-script');
    $(e.currentTarget).find('#sieveDataText').html('<pre style="font-size:14px;line-height:1.1">' + sieveScript + '</pre>');
  });

  // Set line numbers for textarea
  $("#script_data").numberedtextarea({allowTabChar: true});
  // Disable submit button on script change
	$('#script_data').on('keyup', function() {
    $('#add_filter_btns > #add_item').attr({"disabled": true});
    $('#validation_msg').html('-');
	});

  // Validate script data
  $("#validate_sieve").click(function( event ) {
    event.preventDefault();
    var script = $('#script_data').val();
    $.ajax({
      dataType: 'jsonp',
      url: "/inc/ajax/sieve_validation.php",
      type: "get",
      data: { script: script },
      complete: function(data) {
        var response = (data.responseText);
        response_obj = JSON.parse(response);
        if (response_obj.type == "success") {
          $('#add_filter_btns > #add_item').attr({"disabled": false});
        }
        mailcow_alert_box(response_obj.msg, response_obj.type);
      },
    });
  });
  // $(document).on('DOMNodeInserted', '#prefilter_table', function () {
    // $("#active-script").closest('td').css('background-color','#b0f0a0');
    // $("#inactive-script").closest('td').css('background-color','#b0f0a0');
  // });



});
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
  // http://stackoverflow.com/questions/46155/validate-email-address-in-javascript
  function validateEmail(email) {
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
  }
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
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"domain_name","title":lang.domain,"style":{"width":"250px"}},
        {"name":"aliases","title":lang.aliases,"breakpoints":"xs sm"},
        {"name":"mailboxes","title":lang.mailboxes},
        {"name":"quota","style":{"whiteSpace":"nowrap"},"title":lang.domain_quota,"formatter": function(value){
          res = value.split("/");
          return humanFileSize(res[0]) + " / " + humanFileSize(res[1]);
        },
        "sortValue": function(value){
          res = value.split("/");
          return Number(res[0]);
        },
        },
        {"name":"max_quota_for_mbox","title":lang.mailbox_quota,"breakpoints":"xs sm"},
        {"name":"backupmx","filterable": false,"style":{"maxWidth":"120px","width":"120px"},"title":lang.backup_mx,"breakpoints":"xs sm"},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"240px","width":"240px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
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
            item.chkbox = '<input type="checkbox" data-id="domain" name="multi_select" value="' + encodeURIComponent(item.domain_name) + '" />';
            item.action = '<div class="btn-group">';
            if (role == "admin") {
              item.action += '<a href="/edit.php?domain=' + encodeURIComponent(item.domain_name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
                '<a href="#" id="delete_selected" data-id="single-domain" data-api-url="delete/domain" data-item="' + encodeURIComponent(item.domain_name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>';
            }
            else {
              item.action += '<a href="/edit.php?domain=' + encodeURIComponent(item.domain_name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>';
            }
            item.action += '<a href="#dnsInfoModal" class="btn btn-xs btn-info" data-toggle="modal" data-domain="' + encodeURIComponent(item.domain_name) + '"><span class="glyphicon glyphicon-question-sign"></span> DNS</a></div>';
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
        "connectors": false,
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
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"username","style":{"word-break":"break-all","min-width":"120px"},"title":lang.username},
        {"name":"name","title":lang.fname,"style":{"word-break":"break-all","min-width":"120px"},"breakpoints":"xs sm"},
        {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
        {"name":"quota","style":{"whiteSpace":"nowrap"},"title":lang.domain_quota,"formatter": function(value){
          res = value.split("/");
          return humanFileSize(res[0]) + " / " + humanFileSize(res[1]);
        },
        "sortValue": function(value){
          res = value.split("/");
          return Number(res[0]);
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
            item.chkbox = '<input type="checkbox" data-id="mailbox" name="multi_select" value="' + encodeURIComponent(item.username) + '" />';
            if (role == "admin") {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?mailbox=' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '<a href="/index.php?duallogin=' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-user"></span> Login</a>' +
              '</div>';
            }
            else {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?mailbox=' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            }
            item.in_use = '<div class="progress">' +
              '<div class="progress-bar progress-bar-' + item.percent_class + ' role="progressbar" aria-valuenow="' + item.percent_in_use + '" aria-valuemin="0" aria-valuemax="100" ' +
              'style="min-width:2em;width:' + item.percent_in_use + '%">' + item.percent_in_use + '%' + '</div></div>';
            item.username = escapeHtml(item.username);
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
        "connectors": false,
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
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
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
              '<a href="/edit.php?resource=' + encodeURIComponent(item.name) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-resource" data-api-url="delete/resource" data-item="' + encodeURIComponent(item.name) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="resource" name="multi_select" value="' + encodeURIComponent(item.name) + '" />';
            item.name = escapeHtml(item.name);
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
        "connectors": false,
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  }
  function draw_bcc_table() {
    ft_bcc_table = FooTable.init('#bcc_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"id","title":"ID","style":{"maxWidth":"60px","width":"60px","text-align":"center"}},
        {"name":"type","title":lang.bcc_type},
        {"name":"local_dest","title":lang.bcc_local_dest},
        {"name":"bcc_dest","title":lang.bcc_destinations},
        {"name":"domain","title":lang.domain,"breakpoints":"xs sm"},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/bcc/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw bcc table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?bcc=' + item.id + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-bcc" data-api-url="delete/bcc" data-item="' + item.id + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="bcc" name="multi_select" value="' + item.id + '" />';
            item.local_dest = escapeHtml(item.local_dest);
            item.bcc_dest = escapeHtml(item.bcc_dest);
            if (item.type == 'sender') {
              item.type = '<span id="active-script" class="label label-success">Sender</span>';
            } else {
              item.type = '<span id="inactive-script" class="label label-warning">Recipient</span>';
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
        "connectors": false,
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  }
  function draw_recipient_map_table() {
    ft_recipient_map_table = FooTable.init('#recipient_map_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"id","title":"ID","style":{"maxWidth":"60px","width":"60px","text-align":"center"}},
        {"name":"recipient_map_old","title":lang.recipient_map_old},
        {"name":"recipient_map_new","title":lang.recipient_map_new},
        {"name":"active","filterable": false,"style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":(role == "admin" ? lang.action : ""),"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/recipient_map/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw recipient map table');
        },
        success: function (data) {
          if (role == "admin") {
            $.each(data, function (i, item) {
              item.recipient_map_old = escapeHtml(item.recipient_map_old);
              item.recipient_map_new = escapeHtml(item.recipient_map_new);
              item.action = '<div class="btn-group">' +
                '<a href="/edit.php?recipient_map=' + item.id + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
                '<a href="#" id="delete_selected" data-id="single-recipient_map" data-api-url="delete/recipient_map" data-item="' + item.id + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
                '</div>';
              item.chkbox = '<input type="checkbox" data-id="recipient_map" name="multi_select" value="' + item.id + '" />';
            });
          }
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
        "connectors": false,
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
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
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
              '<a href="/edit.php?alias=' + encodeURIComponent(item.address) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-alias" data-api-url="delete/alias" data-item="' + encodeURIComponent(item.address) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="alias" name="multi_select" value="' + encodeURIComponent(item.address) + '" />';
            item.goto = escapeHtml(item.goto);
            if (item.is_catch_all == 1) {
              item.address = '<div class="label label-default">Catch-All</div> ' + escapeHtml(item.address);
            }
            else {
              item.address = escapeHtml(item.address);
            }
            if (item.goto == "null@localhost") {
              item.goto = '⤷ <span style="font-size:12px" class="glyphicon glyphicon-trash" aria-hidden="true"></span>';
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
        "connectors": false,
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
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"alias_domain","title":lang.alias,"style":{"width":"250px"}},
        {"name":"target_domain","title":lang.target_domain},
        {"name":"active","filterable": false,"style":{"maxWidth":"50px","width":"70px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"250px","width":"250px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
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
              '<a href="/edit.php?aliasdomain=' + encodeURIComponent(item.alias_domain) + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-alias-domain" data-api-url="delete/alias-domain" data-item="' + encodeURIComponent(item.alias_domain) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '<a href="#dnsInfoModal" class="btn btn-xs btn-info" data-toggle="modal" data-domain="' + encodeURIComponent(item.alias_domain) + '"><span class="glyphicon glyphicon-question-sign"></span> DNS</a></div>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="alias-domain" name="multi_select" value="' + encodeURIComponent(item.alias_domain) + '" />';
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
        "connectors": false,
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  }

  function draw_sync_job_table() {
    ft_syncjob_table = FooTable.init('#sync_job_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"id","title":"ID","style":{"maxWidth":"60px","width":"60px","text-align":"center"}},
        {"name":"user2","title":lang.owner},
        {"name":"server_w_port","title":"Server","breakpoints":"xs"},
        {"name":"exclude","title":lang.excludes,"breakpoints":"all"},
        {"name":"mins_interval","title":lang.mins_interval,"breakpoints":"all"},
        {"name":"last_run","title":lang.last_run,"breakpoints":"all"},
        {"name":"log","title":"Log"},
        {"name":"active","filterable": false,"style":{"maxWidth":"70px","width":"70px"},"title":lang.active},
        {"name":"is_running","filterable": false,"style":{"maxWidth":"120px","width":"100px"},"title":lang.status},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/syncjobs/all/no_log',
        jsonp: false,
        error: function () {
          console.log('Cannot draw sync job table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.log = '<a href="#syncjobLogModal" data-toggle="modal" data-syncjob-id="' + encodeURIComponent(item.id) + '">Open logs</a>'
            item.user2 = escapeHtml(item.user2);
            if (!item.exclude > 0) {
              item.exclude = '-';
            } else {
              item.exclude  = '<code>' + item.exclude + '</code>';
            }
            item.server_w_port = escapeHtml(item.user1) + '@' + item.host1 + ':' + item.port1;
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?syncjob=' + item.id + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-syncjob" data-api-url="delete/syncjob" data-item="' + item.id + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="syncjob" name="multi_select" value="' + item.id + '" />';
            if (item.is_running == 1) {
              item.is_running = '<span id="active-script" class="label label-success">' + lang.running + '</span>';
            } else {
              item.is_running = '<span id="inactive-script" class="label label-warning">' + lang.waiting + '</span>';
            }
            if (!item.last_run > 0) {
              item.last_run = lang.waiting;
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
        "connectors": false,
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  }

  function draw_filter_table() {
    ft_filter_table = FooTable.init('#filter_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"60px","width":"60px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"id","title":"ID","style":{"maxWidth":"60px","width":"60px","text-align":"center"}},
        {"name":"active","style":{"maxWidth":"80px","width":"80px"},"title":lang.active},
        {"name":"filter_type","style":{"maxWidth":"80px","width":"80px"},"title":"Type"},
        {"sorted": true,"name":"username","title":lang.owner,"style":{"maxWidth":"550px","width":"350px"}},
        {"name":"script_desc","title":lang.description,"breakpoints":"xs"},
        {"name":"script_data","title":"Script","breakpoints":"all"},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/filters/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw filter table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            if (item.active_int == 1) {
              item.active = '<span id="active-script" class="label label-success">' + lang.active + '</span>';
            } else {
              item.active = '<span id="inactive-script" class="label label-warning">' + lang.inactive + '</span>';
            }
            item.script_data = '<pre style="margin:0px">' + escapeHtml(item.script_data) + '</pre>'
            item.filter_type = '<div class="label label-default">' + item.filter_type.charAt(0).toUpperCase() + item.filter_type.slice(1).toLowerCase() + '</div>'
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?filter=' + item.id + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-filter" data-api-url="delete/filter" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="filter_item" name="multi_select" value="' + item.id + '" />'
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
        "connectors": false,
        "placeholder": lang.filter_table
      },
      "sorting": {
        "enabled": true
      }
    });
  };

  draw_domain_table();
  draw_mailbox_table();
  draw_resource_table();
  draw_alias_table();
  draw_aliasdomain_table();
  draw_sync_job_table();
  draw_filter_table();
  draw_bcc_table();
  draw_recipient_map_table();

});
