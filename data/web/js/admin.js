$(document).ready(function() {
  $.ajax({
    dataType: 'json',
    url: '/api/v1/get/domain-admin/all',
    jsonp: false,
    error: function () {
      alert('Cannot draw domain administrator table');
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
});