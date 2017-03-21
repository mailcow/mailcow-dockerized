$(document).ready(function() {
	// Show element counter for tables
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
      alert('Cannot receive history');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.aliases = item.aliases_in_domain + " / " + item.max_num_aliases_for_domain;
        item.mailboxes = item.mboxes_in_domain + " / " + item.max_num_mboxes_for_domain;
        item.quota = humanFileSize(item.quota_used_in_domain) + " / " + humanFileSize(item.max_quota_for_domain);
        item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?domain=' + item.domain_name + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> Edit</a>' +
          '<a href="/delete.php?domain=' + item.domain_name + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> Remove</a>' +
					'</div>';
      });
      $('#domain_table').footable({
        "columns": [
          {"sorted": true,"name":"domain_name","title":lang_domain},
          {"name":"aliases","title":lang_aliases,"breakpoints":"xs sm"},
          {"name":"mailboxes","title":lang_mailboxes},
          {"name":"quota","title":lang_domain_quota},
          {"name":"max_quota_for_mbox","title":lang_mailbox_quota},
          {"name":"backupmx","title":lang_backup_mx,"breakpoints":"xs sm"},
          {"name":"active","title":lang_active,"breakpoints":"xs sm"},
          {"name":"action","type":"html","title":lang_action,"breakpoints":"xs sm"}
        ],
        "rows": data,
        "paging": {
          "enabled": true,
          "limit": 5,
          "size": 25
        },
        "filtering": {
          "enabled": true,
          "position": "left"
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
      alert('Cannot receive history');
    },
    success: function (data) {
      $.each(data, function (i, item) {
        item.quota = humanFileSize(item.quota_used) + " / " + humanFileSize(item.quota);
        item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
        item.action = '<div class="btn-group">' +
          '<a href="/edit.php?mailbox=' + item.username + '" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-pencil"></span> Edit</a>' +
          '<a href="/delete.php?mailbox=' + item.username + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> Remove</a>' +
					'</div>';
        item.in_use = '<div class="progress">' +
				  '<div class="progress-bar progress-bar-' + item.percent_class + ' role="progressbar" aria-valuenow="' + item.percent_in_use + '" aria-valuemin="0" aria-valuemax="100" ' +
          'style="min-width:2em;width:' + item.percent_in_use + '%">' + item.percent_in_use + '%' + '</div></div>';

      });
      $('#mailbox_table').footable({
        "columns": [
          {"sorted": true,"name":"username","title":lang_username},
          {"name":"name","title":lang_fname,"breakpoints":"xs sm"},
          {"name":"domain","title":lang_domain},
          {"name":"quota","title":lang_domain_quota},
          {"name":"spam_aliases","title":lang_spam_aliases},
          {"name":"in_use","type":"html","title":lang_in_use},
          {"name":"messages","title":lang_msg_num,"breakpoints":"xs sm"},
          {"name":"active","title":lang_active,"breakpoints":"xs sm"},
          {"name":"action","type":"html","title":lang_action,"breakpoints":"xs sm"}
        ],
        "rows": data,
        "paging": {
          "enabled": true,
          "limit": 5,
          "size": 25
        },
        "filtering": {
          "enabled": true,
          "position": "left"
        },
        "sorting": {
          "enabled": true
        }
      });
    }
  });

});
