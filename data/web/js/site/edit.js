$(document).ready(function() {
  $(".arrow-toggle").on('click', function(e) { e.preventDefault(); $(this).find('.arrow').toggleClass("animation"); });
  $("#pushover_delete").click(function() { return confirm(lang.delete_ays); });
  $(".goto_checkbox").click(function( event ) {
    $("form[data-id='editalias'] .goto_checkbox").not(this).prop('checked', false);
    if ($("form[data-id='editalias'] .goto_checkbox:checked").length > 0) {
      $('#textarea_alias_goto').prop('disabled', true);
    }
    else {
      $("#textarea_alias_goto").removeAttr('disabled');
    }
  });
  $("#disable_sender_check").click(function( event ) {
    if ($("form[data-id='editmailbox'] #disable_sender_check:checked").length > 0) {
      $('#editSelectSenderACL').prop('disabled', true);
      $('#editSelectSenderACL').selectpicker('refresh');
    }
    else {
      $('#editSelectSenderACL').prop('disabled', false);
      $('#editSelectSenderACL').selectpicker('refresh');
    }
  });
  if ($("form[data-id='editalias'] .goto_checkbox:checked").length > 0) {
    $('#textarea_alias_goto').prop('disabled', true);
  }

  $("#mailbox-password-warning-close").click(function( event ) {
    $('#mailbox-passwd-hidden-info').addClass('hidden');
    $('#mailbox-passwd-form-groups').removeClass('hidden');
  });
  // Sender ACL
  if ($("#editSelectSenderACL option[value='\*']:selected").length > 0){
    $("#sender_acl_disabled").show();
  }
  $('#editSelectSenderACL').change(function() {
    if ($("#editSelectSenderACL option[value='\*']:selected").length > 0){
      $("#sender_acl_disabled").show();
    }
    else {
      $("#sender_acl_disabled").hide();
    }
  });
  // Resources
  if ($("#editSelectMultipleBookings").val() == "custom") {
    $("#multiple_bookings_custom_div").show();
    $('input[name=multiple_bookings]').val($("#multiple_bookings_custom").val());
  }
  $("#editSelectMultipleBookings").change(function() {
    $('input[name=multiple_bookings]').val($("#editSelectMultipleBookings").val());
    if ($('input[name=multiple_bookings]').val() == "custom") {
      $("#multiple_bookings_custom_div").show();
    }
    else {
      $("#multiple_bookings_custom_div").hide();
    }
  });
  $("#multiple_bookings_custom").bind("change keypress keyup blur", function() {
    $('input[name=multiple_bookings]').val($("#multiple_bookings_custom").val());
  });

  $("#show_mailbox_rename_form").click(function() {
    $("#rename_warning").hide();
    $("#rename_form").removeClass("d-none");
  });

  // load tags
  if ($('#tags').length){
    var tagsEl = $('#tags').parent().find('.tag-values')[0];
    console.log($(tagsEl).val())
    var tags = JSON.parse($(tagsEl).val());
    $(tagsEl).val("");

    for (var i = 0; i < tags.length; i++)
      addTag($('#tags'), tags[i]);
  }
});

jQuery(function($){
  // http://stackoverflow.com/questions/46155/validate-email-address-in-javascript
  function validateEmail(email) {
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
  }
  function draw_wl_policy_domain_table() {
    $('#wl_policy_domain_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: '/api/v1/get/policy_wl_domain/' + table_for_domain,
        dataSrc: function(data){
          $.each(data, function (i, item) {
            if (!validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="policy_wl_domain" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled title="' + lang_user.spamfilter_table_domain_policy + '" />';
            }
          });

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'prefid',
          defaultContent: ''
        },
        {
          title: lang_user.spamfilter_table_rule,
          data: 'value',
          defaultContent: ''
        },
        {
          title: 'Scope',
          data: 'object',
          defaultContent: ''
        }
      ]
    });
  }
  function draw_bl_policy_domain_table() {
    $('#bl_policy_domain_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: '/api/v1/get/policy_bl_domain/' + table_for_domain,
        dataSrc: function(data){
          $.each(data, function (i, item) {
            if (!validateEmail(item.object)) {
              item.chkbox = '<input type="checkbox" class="form-check-input" data-id="policy_bl_domain" name="multi_select" value="' + item.prefid + '" />';
            }
            else {
              item.chkbox = '<input type="checkbox" class="form-check-input" disabled tooltip="' + lang_user.spamfilter_table_domain_policy + '" />';
            }
          });

          return data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'prefid',
          defaultContent: ''
        },
        {
          title: lang_user.spamfilter_table_rule,
          data: 'value',
          defaultContent: ''
        },
        {
          title: 'Scope',
          data: 'object',
          defaultContent: ''
        }
      ]
    });
  }

  function add_table_row(table_id, type) {
    var row = $('<tr />');
    if (type == "mbox_attr") {
      cols = '<td><input class="input-sm input-xs-lg form-control" data-id="mbox_attr" type="text" name="attribute" required></td>';
      cols += '<td><input class="input-sm input-xs-lg form-control" data-id="mbox_attr" type="text" name="value" required></td>';
      cols += '<td><a href="#" role="button" class="btn btn-sm btn-xs-lg btn-secondary h-100 w-100" type="button">' + lang_admin.remove_row + '</a></td>';
    }
    row.append(cols);
    table_id.append(row);
  }
  $('#mbox_attr_table').on('click', 'tr a', function (e) {
    e.preventDefault();
    $(this).parents('tr').remove();
  });
  $('#add_mbox_attr_row').click(function() {
    add_table_row($('#mbox_attr_table'), "mbox_attr");
  });

  // detect element visibility changes
  function onVisible(element, callback) {
    $(document).ready(function() {
      element_object = document.querySelector(element);
      if (element_object === null) return;

      new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if(entry.intersectionRatio > 0) {
            callback(element_object);
            observer.disconnect();
          }
        });
      }).observe(element_object);
    });
  }
  // Draw Table if tab is active
  onVisible("[id^=wl_policy_domain_table]", () => draw_wl_policy_domain_table());
  onVisible("[id^=bl_policy_domain_table]", () => draw_bl_policy_domain_table());
});
