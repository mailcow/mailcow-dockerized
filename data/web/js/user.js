$(document).ready(function() {
	// Show and activate password fields after box was checked
	// Hidden by default
	if ( !$("#togglePwNew").is(':checked') ) {
		$(".passFields").hide();
	}
	$('#togglePwNew').click(function() {
		$("#user_new_pass").attr("disabled", !this.checked);
		$("#user_new_pass2").attr("disabled", !this.checked);
		var $this = $(this);
		if ($this.is(':checked')) {
			$(".passFields").slideDown();
		} else {
			$(".passFields").slideUp();
		}
	});
	// Show generate button after time selection
	$('#generate_tla').hide(); 
	$('#validity').change(function(){
		$('#generate_tla').show(); 
	});

	// Init Bootstrap Switch
	$.fn.bootstrapSwitch.defaults.onColor = 'success';
	$("#tls_out").bootstrapSwitch();
	$("#tls_in").bootstrapSwitch();

  // Log modal
  $('#logModal').on('show.bs.modal', function(e) {
  var logText = $(e.relatedTarget).data('log-text');
  $(e.currentTarget).find('#logText').html('<pre style="background:none;font-size:11px;line-height:1.1;border:0px">' + logText + '</pre>');
  });

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
            location.reload(true);
          }
        });
      })
      .one('click', '#isCanceled', function(e) {
        $('#ConfirmDeleteModal').modal('hide');
      });;
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
  function draw_sync_job_table() {
    ft_aliasdomain_table = FooTable.init('#sync_job_table', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"sorted": true,"name":"server_w_port","title":"Server"},
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
        url: '/api/v1/get/syncjob',
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
  draw_sync_job_table();
});