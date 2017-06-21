$(document).ready(function() {
  $.fn.serializeObject = function() {
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
      if (o[this.name]) {
        if (!o[this.name].push) {
          o[this.name] = [o[this.name]];
        }
        o[this.name].push(this.value || '');
      } else {
        o[this.name] = this.value || '';
      }
    });
    return o;
  };
  // Collect values of input fields with name "multi_select" an same data-id to js array multi_data[data-id]
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
    if(e.target.tagName.toLowerCase() === 'button') {
      e.stopPropagation();
    }
    else if(e.target.tagName.toLowerCase() === 'a') {
      e.stopPropagation();
    }
    else if (e.target.type == "checkbox") {
      e.stopPropagation();
    }
    else {
      var checkbox = $(this).find(':checkbox');
      checkbox.trigger('click');
    }
  });

  // Select or deselect all checkboxes with same data-id
  $(document).on('click', '#toggle_multi_select_all', function(e) {
    e.preventDefault();
    id = $(this).data("id");
    var all_checkboxes = $("input[data-id=" + id + "]:enabled");
    all_checkboxes.prop("checked", !all_checkboxes.prop("checked")).change();
  });

  // General API edit actions
  $(document).on('click', '#edit_selected', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    var api_url = $(this).data('api-url');
    var api_attr = $(this).data('api-attr');
    // If clicked element #edit_selected is in a form with the same data-id as the button,
    // we merge all input fields by {"name":"value"} into api-attr
    if ($(this).closest("form").data('id') == id) {
      var attr_to_merge = $(this).closest("form").serializeObject();
      var api_attr = $.extend(api_attr, attr_to_merge)
    }
    // If clicked element #edit_selected has data-item attribute, it is added to "items"
    if (typeof $(this).data('item') !== 'undefined') {
      var id = $(this).data('id');
      if (typeof multi_data[id] == "undefined") {
        multi_data[id] = [];
      }
      multi_data[id].push($(this).data('item'));
    }
    if (typeof multi_data[id] == "undefined") return;
    api_items = multi_data[id];
    if (Object.keys(api_items).length !== 0) {
      $.ajax({
        type: "POST",
        dataType: "json",
        data: {
          "items": JSON.stringify(api_items),
          "attr": JSON.stringify(api_attr),
          "csrf_token": csrf_token
        },
        url: '/api/v1/' + api_url,
        jsonp: false,
        complete: function(data) {
          // var reponse = (JSON.parse(data.responseText));
          // console.log(reponse.type);
          // console.log(reponse.msg);
          window.location = window.location.href.split("#")[0];
        }
      });
    }
  });

  // General API add actions
  $(document).on('click', '#add_item', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    var api_url = $(this).data('api-url');
    var api_attr = $(this).data('api-attr');
    // If clicked button is in a form with the same data-id as the button,
    // we merge all input fields by {"name":"value"} into api-attr
    if ($(this).closest("form").data('id') == id) {
      var req_empty = false;
      $(this).closest("form").find('select, textarea, input').each(function() {
        if ($(this).prop('required')) {
          if (!$(this).val()) {
            req_empty = true;
            $(this).addClass('inputMissingAttr');
          } else {
            $(this).removeClass('inputMissingAttr');
          }
        }
      });
      if (!req_empty) {
        var attr_to_merge = $(this).closest("form").serializeObject();
        var api_attr = $.extend(api_attr, attr_to_merge)
      } else {
        return false;
      }
    }
    $.ajax({
      type: "POST",
      dataType: "json",
      data: {
        "attr": JSON.stringify(api_attr),
        "csrf_token": csrf_token
      },
      url: '/api/v1/' + api_url,
      jsonp: false,
      complete: function(data) {
        // var reponse = (JSON.parse(data.responseText));
        // console.log(reponse.type);
        // console.log(reponse.msg);
        window.location = window.location.href.split("#")[0];
      }
    });
  });

  // General API delete actions
  $(document).on('click', '#delete_selected', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    // If clicked element #delete_selected has data-item attribute, it is added to "items"
    if (typeof $(this).data('item') !== 'undefined') {
      var id = $(this).data('id');
      if (typeof multi_data[id] == "undefined") {
        multi_data[id] = [];
      }
      multi_data[id].splice($.inArray($(this).data('item'), multi_data[id]), 1);
      multi_data[id].push($(this).data('item'));
    }
    if (typeof $(this).data('text') !== 'undefined') {
      $("#DeleteText").empty();
      $("#DeleteText").text($(this).data('text'));
    }
    if (typeof multi_data[id] == "undefined" || multi_data[id] == "") return;
    data_array = multi_data[id];
    api_url = $(this).data('api-url');
    $(document).on('show.bs.modal', '#ConfirmDeleteModal', function() {
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
          cache: false,
          data: {
            "items": JSON.stringify(data_array),
            "csrf_token": csrf_token
          },
          url: '/api/v1/' + api_url,
          jsonp: false,
          complete: function(data) {
            window.location = window.location.href.split("#")[0];
          }
        });
      })
      .one('click', '#isCanceled', function(e) {
        // Remove event handler to allow to close modal and restart dialog without multiple submits
        $('#ConfirmDeleteModal').off();
        $('#ConfirmDeleteModal').modal('hide');
      });
  });
});