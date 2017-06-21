$(document).ready(function() {

  // Log modal
  $('#logModal').on('show.bs.modal', function(e) {
  var logText = $(e.relatedTarget).data('log-text');
  $(e.currentTarget).find('#logText').html('<pre style="background:none;font-size:11px;line-height:1.1;border:0px">' + logText + '</pre>');
  });

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
  function unix_time_format(tm) {
    var date = new Date(tm ? tm * 1000 : 0);
    return date.toLocaleString();
  }
  function draw_tla_table() {
    ft_tla_table = FooTable.init('#tla_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"address","title":lang.alias},
        {"name":"validity","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.alias_valid_until,"style":{"width":"170px"}},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/time_limited_aliases',
        jsonp: false,
        error: function () {
          console.log('Cannot draw tla table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="#" id="delete_selected" data-id="single-tla" data-api-url="delete/time_limited_alias" data-item="' + encodeURI(item.address) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="tla" name="multi_select" value="' + item.address + '" />';
          });
        }
      }),
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
  function draw_sync_job_table() {
    ft_syncjob_table = FooTable.init('#sync_job_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"id","title":"ID"},
        {"name":"server_w_port","title":"Server"},
        {"name":"enc1","title":lang.encryption},
        {"name":"user1","title":lang.username},
        {"name":"exclude","title":lang.excludes},
        {"name":"mins_interval","title":lang.interval + " (min)"},
        {"name":"last_run","title":lang.last_run},
        {"name":"log","title":"Log"},
        {"name":"active","filterable": false,"style":{"maxWidth":"50px","width":"70px"},"title":lang.active},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right","maxWidth":"180px","width":"180px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/syncjobs',
        jsonp: false,
        error: function () {
          console.log('Cannot draw sync job table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.log = '<a href="#logModal" data-toggle="modal" data-log-text="' + escapeHtml(item.returned_text) + '">Open logs</a>'
            item.exclude = '<code>' + item.exclude + '</code>'
            item.server_w_port = item.host1 + ':' + item.port1;
            item.action = '<div class="btn-group">' +
              '<a href="/edit.php?syncjob=' + item.id + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> ' + lang.edit + '</a>' +
              '<a href="#" id="delete_selected" data-id="single-syncjob" data-api-url="delete/syncjob" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="syncjob" name="multi_select" value="' + item.id + '" />';
          });
        }
      }),
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
  function draw_wl_policy_mailbox_table() {
    ft_wl_policy_mailbox_table = FooTable.init('#wl_policy_mailbox_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"prefid","style":{"maxWidth":"40px","width":"40px"},"title":"ID","filterable": false,"sortable": false},
        {"sorted": true,"name":"value","title":lang.spamfilter_table_rule},
        {"name":"object","title":"Scope"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/policy_wl_mailbox',
        jsonp: false,
        error: function () {
          console.log('Cannot draw mailbox policy wl table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            if (validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" data-id="policy_wl_mailbox" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" disabled title="' + lang.spamfilter_table_domain_policy + '" />';
            }
          });
        }
      }),
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
  function draw_bl_policy_mailbox_table() {
    ft_bl_policy_mailbox_table = FooTable.init('#bl_policy_mailbox_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px","text-align":"center"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"prefid","style":{"maxWidth":"40px","width":"40px"},"title":"ID","filterable": false,"sortable": false},
        {"sorted": true,"name":"value","title":lang.spamfilter_table_rule},
        {"name":"object","title":"Scope"}
      ],
      "empty": lang.empty,
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/policy_bl_mailbox',
        jsonp: false,
        error: function () {
          console.log('Cannot draw mailbox policy bl table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            if (validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" data-id="policy_bl_mailbox" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" disabled tooltip="' + lang.spamfilter_table_domain_policy + '" />';
            }
          });
        }
      }),
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
  draw_sync_job_table();
  draw_tla_table();
  draw_wl_policy_mailbox_table();
  draw_bl_policy_mailbox_table();
});