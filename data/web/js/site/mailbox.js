$(document).ready(function() {
  acl_data = JSON.parse(acl);
  // Set paging
  // Clone mailbox mass actions
  $("div").find("[data-actions-header='true'").each(function() {
    $(this).html($(this).nextAll('.mass-actions-mailbox:first').html());
  });
  // Auto-fill domain quota when adding new domain
  auto_fill_quota = function(domain) {
    $.get("/api/v1/get/domain/" + domain, function(data){
      var result = $.parseJSON(JSON.stringify(data));
      def_new_mailbox_quota = ( result.def_new_mailbox_quota / 1048576);
      max_new_mailbox_quota = ( result.max_new_mailbox_quota / 1048576);
      if (max_new_mailbox_quota != '0') {
        $('.addInputQuotaExhausted').hide();
        $("#quotaBadge").html('max. ' +  max_new_mailbox_quota + ' MiB');
        $('#addInputQuota').attr({"disabled": false, "value": "", "type": "number", "max": max_new_mailbox_quota});
        $('#addInputQuota').val(def_new_mailbox_quota);
      }
      else {
        $('.addInputQuotaExhausted').show();
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

  $(".goto_checkbox").click(function( event ) {
   $("form[data-id='add_alias'] .goto_checkbox").not(this).prop('checked', false);
    if ($("form[data-id='add_alias'] .goto_checkbox:checked").length > 0) {
      $('#textarea_alias_goto').prop('disabled', true);
    }
    else {
      $("#textarea_alias_goto").removeAttr('disabled');
    }
  });
  $('#addAliasModal').on('show.bs.modal', function(e) {
    if ($("form[data-id='add_alias'] .goto_checkbox:checked").length > 0) {
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
    $('.dns-modal-body').html('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
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
  // @Open Domain add modal
  $('#addDomainModal').on('show.bs.modal', function(e) {
    $.ajax({
      url: '/api/v1/get/domain/template/all',
      data: {},
      dataType: 'json',
      success: async function(data){
        $('#domain_templates').find('option').remove();
        $('#domain_templates').selectpicker('destroy');
        $('#domain_templates').selectpicker();
        for (var i = 0; i < data.length; i++){
          if (data[i].template === "Default"){
            $('#domain_templates').prepend($('<option>', {
              'value': data[i].id,
              'text': data[i].template,
              'data-attributes': JSON.stringify(data[i].attributes),
              'selected': true
            }));
            setDomainTemplateData(data[i].attributes);
          } else {
            $('#domain_templates').append($('<option>', {
              'value': data[i].id,
              'text': data[i].template,
              'data-attributes': JSON.stringify(data[i].attributes),
              'selected': false
            }));
          }
        };
        $('#domain_templates').selectpicker("refresh");

        // @selecting template
        $('#domain_templates').on('change', function(){
          var selected = $('#domain_templates option:selected');
          var attr = selected.data('attributes');
          setDomainTemplateData(attr);
        });
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });
  });
  // @Open Mailbox add modal
  $('#addMailboxModal').on('show.bs.modal', function(e) {
    $.ajax({
      url: '/api/v1/get/mailbox/template/all',
      data: {},
      dataType: 'json',
      success: async function(data){
        $('#mailbox_templates').find('option').remove();
        $('#mailbox_templates').selectpicker('destroy');
        $('#mailbox_templates').selectpicker();
        for (var i = 0; i < data.length; i++){
          if (data[i].template === "Default"){
            $('#mailbox_templates').prepend($('<option>', {
              'value': data[i].id,
              'text': data[i].template,
              'data-attributes': JSON.stringify(data[i].attributes),
              'selected': true
            }));
            setMailboxTemplateData(data[i].attributes);
          } else {
            $('#mailbox_templates').append($('<option>', {
              value: data[i].id,
              text : data[i].template,
              'data-attributes': JSON.stringify(data[i].attributes),
              'selected': false
            }));
          }
        };
        $('#mailbox_templates').selectpicker("refresh");

        // @selecting template
        $('#mailbox_templates').on('change', function(){
          var selected = $('#mailbox_templates option:selected');
          var attr = selected.data('attributes');
          setMailboxTemplateData(attr);
        });
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });
  });
  // Sieve data modal
  $('#sieveDataModal').on('show.bs.modal', function(e) {
    var sieveScript = $(e.relatedTarget).data('sieve-script');
    $(e.currentTarget).find('#sieveDataText').html('<pre style="font-size:14px;line-height:1.1">' + sieveScript + '</pre>');
  });
  // Disable submit button on script change
  $('.textarea-code').on('keyup', function() {
    // Disable all "save" buttons, could be a "related button only" function, todo
    $('.add_sieve_script').attr({"disabled": true});
  });
  // Validate script data
  $(".validate_sieve").click(function( event ) {
    event.preventDefault();
    var validation_button = $(this);
    // Get script_data textarea content from form the button was clicked in
    var script = $('textarea[name="script_data"]', $(this).parents('form:first')).val();
    $.ajax({
      dataType: 'json',
      url: "/inc/ajax/sieve_validation.php",
      type: "get",
      data: { script: script },
      complete: function(data) {
        var response = (data.responseText);
        response_obj = JSON.parse(response);
        if (response_obj.type == "success") {
          $(validation_button).next().attr({"disabled": false});
        }
        mailcow_alert_box(response_obj.msg, response_obj.type);
      },
    });
  });
  // $(document).on('DOMNodeInserted', '#prefilter_table', function () {
    // $("#active-script").closest('td').css('background-color','#b0f0a0');
    // $("#inactive-script").closest('td').css('background-color','#b0f0a0');
  // });
  $('#addResourceModal').on('shown.bs.modal', function() {
    $("#multiple_bookings").val($("#multiple_bookings_select").val());
    if ($("#multiple_bookings").val() == "custom") {
      $("#multiple_bookings_custom_div").show();
      $("#multiple_bookings").val($("#multiple_bookings_custom").val());
    }
  })
  $("#multiple_bookings_select").change(function() {
    $("#multiple_bookings").val($("#multiple_bookings_select").val());
    if ($("#multiple_bookings").val() == "custom") {
      $("#multiple_bookings_custom_div").show();
    }
    else {
      $("#multiple_bookings_custom_div").hide();
    }
  });
  $("#multiple_bookings_custom").bind ("change keypress keyup blur", function () {
    $("#multiple_bookings").val($("#multiple_bookings_custom").val());
  });

  function setDomainTemplateData(template){
    $("#addDomain_max_aliases").val(template.max_num_aliases_for_domain);
    $("#addDomain_max_mailboxes").val(template.max_num_mboxes_for_domain);
    $("#addDomain_mailbox_quota_def").val(template.def_quota_for_mbox / 1048576);
    $("#addDomain_mailbox_quota_m").val(template.max_quota_for_mbox / 1048576);
    $("#addDomain_domain_quota_m").val(template.max_quota_for_domain / 1048576);

    if (template.gal == 1){
      $('#addDomain_gal').prop('checked', true);
    } else {
      $('#addDomain_gal').prop('checked', false);
    }

    if (template.active == 1){
      $('#addDomain_active').prop('checked', true);
    } else {
      $('#addDomain_active').prop('checked', false);
    }

    $("#addDomain_rl_value").val(template.rl_value);
    $('#addDomain_rl_frame').selectpicker('val', template.rl_frame);
    $("#dkim_selector").val(template.dkim_selector);
    if (!template.key_size)
      template.key_size = 2048;
    $('#key_size').selectpicker('val', template.key_size.toString());

    if (template.backupmx == 1){
      $('#addDomain_relay_domain').prop('checked', true);
    } else {
      $('#addDomain_relay_domain').prop('checked', false);
    }
    if (template.relay_all_recipients == 1){
      $('#addDomain_relay_all').prop('checked', true);
    } else {
      $('#addDomain_relay_all').prop('checked', false);
    }
    if (template.relay_unknown_only == 1){
      $('#addDomain_relay_unknown_only').prop('checked', true);
    } else {
      $('#addDomain_relay_unknown_only').prop('checked', false);
    }


    // load tags
    $('#addDomain_tags').val("");
    $($('#addDomain_tags').parent().find(".tag-values")[0]).val("");
    $('#addDomain_tags').parent().find(".tag-badge").remove();
    for (var i = 0; i < template.tags.length; i++)
      addTag($('#addDomain_tags'), template.tags[i]);
  }
  function setMailboxTemplateData(template){
    $("#addInputQuota").val(template.quota / 1048576);

    if (template.quarantine_notification === "never"){
      $('#quarantine_notification_never').prop('checked', true);
      $('#quarantine_notification_hourly').prop('checked', false);
      $('#quarantine_notification_daily').prop('checked', false);
      $('#quarantine_notification_weekly').prop('checked', false);
    } else if(template.quarantine_notification === "hourly"){
      $('#quarantine_notification_never').prop('checked', false);
      $('#quarantine_notification_hourly').prop('checked', true);
      $('#quarantine_notification_daily').prop('checked', false);
      $('#quarantine_notification_weekly').prop('checked', false);
    } else if(template.quarantine_notification === "daily"){
      $('#quarantine_notification_never').prop('checked', false);
      $('#quarantine_notification_hourly').prop('checked', false);
      $('#quarantine_notification_daily').prop('checked', true);
      $('#quarantine_notification_weekly').prop('checked', false);
    } else if(template.quarantine_notification === "weekly"){
      $('#quarantine_notification_never').prop('checked', false);
      $('#quarantine_notification_hourly').prop('checked', false);
      $('#quarantine_notification_daily').prop('checked', false);
      $('#quarantine_notification_weekly').prop('checked', true);
    } else {
      $('#quarantine_notification_never').prop('checked', false);
      $('#quarantine_notification_hourly').prop('checked', false);
      $('#quarantine_notification_daily').prop('checked', false);
      $('#quarantine_notification_weekly').prop('checked', false);
    }

    if (template.quarantine_category === "reject"){
      $('#quarantine_category_reject').prop('checked', true);
      $('#quarantine_category_add_header').prop('checked', false);
      $('#quarantine_category_all').prop('checked', false);
    } else if(template.quarantine_category === "add_header"){
      $('#quarantine_category_reject').prop('checked', false);
      $('#quarantine_category_add_header').prop('checked', true);
      $('#quarantine_category_all').prop('checked', false);
    } else if(template.quarantine_category === "all"){
      $('#quarantine_category_reject').prop('checked', false);
      $('#quarantine_category_add_header').prop('checked', false);
      $('#quarantine_category_all').prop('checked', true);
    }

    if (template.tls_enforce_in == 1){
      $('#tls_enforce_in').prop('checked', true);
    } else {
      $('#tls_enforce_in').prop('checked', false);
    }
    if (template.tls_enforce_out == 1){
      $('#tls_enforce_out').prop('checked', true);
    } else {
      $('#tls_enforce_out').prop('checked', false);
    }

    var protocol_access = [];
    if (template.imap_access == 1){
      protocol_access.push("imap");
    }
    if (template.pop3_access == 1){
      protocol_access.push("pop3");
    }
    if (template.smtp_access == 1){
      protocol_access.push("smtp");
    }
    if (template.sieve_access == 1){
      protocol_access.push("sieve");
    }
    $('#protocol_access').selectpicker('val', protocol_access);

    var acl = [];
    if (template.acl_spam_alias == 1){
      acl.push("spam_alias");
    }
    if (template.acl_tls_policy == 1){
      acl.push("tls_policy");
    }
    if (template.acl_spam_score == 1){
      acl.push("spam_score");
    }
    if (template.acl_spam_policy == 1){
      acl.push("spam_policy");
    }
    if (template.acl_delimiter_action == 1){
      acl.push("delimiter_action");
    }
    if (template.acl_syncjobs == 1){
      acl.push("syncjobs");
    }
    if (template.acl_eas_reset == 1){
      acl.push("eas_reset");
    }
    if (template.acl_sogo_profile_reset == 1){
      acl.push("sogo_profile_reset");
    }
    if (template.acl_pushover == 1){
      acl.push("pushover");
    }
    if (template.acl_quarantine == 1){
      acl.push("quarantine");
    }
    if (template.acl_quarantine_attachments == 1){
      acl.push("quarantine_attachments");
    }
    if (template.acl_quarantine_notification == 1){
      acl.push("quarantine_notification");
    }
    if (template.acl_quarantine_category == 1){
      acl.push("quarantine_category");
    }
    if (template.acl_app_passwds == 1){
      acl.push("app_passwds");
    }
    $('#user_acl').selectpicker('val', acl);

    $('#rl_value').val(template.rl_value);
    if (template.rl_frame){
      $('#rl_frame').selectpicker('val', template.rl_frame);
    }

    if (template.active){
      $('#mbox_active').selectpicker('val', template.active.toString());
    } else {
      $('#mbox_active').selectpicker('val', '');
    }

    if (template.force_pw_update == 1){
      $('#force_pw_update').prop('checked', true);
    } else {
      $('#force_pw_update').prop('checked', false);
    }
    if (template.sogo_access == 1){
      $('#sogo_access').prop('checked', true);
    } else {
      $('#sogo_access').prop('checked', false);
    }

    // load tags
    $('#addMailbox_tags').val("");
    $($('#addMailbox_tags').parent().find(".tag-values")[0]).val("");
    $('#addMailbox_tags').parent().find(".tag-badge").remove();
    for (var i = 0; i < template.tags.length; i++)
      addTag($('#addMailbox_tags'), template.tags[i]);
  }
});
jQuery(function($){
  // http://stackoverflow.com/questions/46155/validate-email-address-in-javascript
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  function unix_time_format(i){return""==i?'<i class="bi bi-x"></i>':new Date(i?1e3*i:0).toLocaleDateString(void 0,{year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit"})}

  $(".refresh_table").on('click', function(e) {
    e.preventDefault();
    var table_name = $(this).data('table');

    if ($.fn.DataTable.isDataTable('#' + table_name))
      $('#' + table_name).DataTable().ajax.reload();
  });
  function draw_domain_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#domain_table') ) {
      $('#domain_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#domain_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: true,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-domains', '#domain_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/domain/datatables",
        dataSrc: function(json){
          $.each(json.data, function(i, item) {
            item.domain_name = escapeHtml(item.domain_name);
            item.domain_h_name = escapeHtml(item.domain_h_name);
            if (item.domain_name != item.domain_h_name){
              item.domain_h_name = item.domain_h_name + '<small class="d-block">' + item.domain_name + '</small>';
            }

            item.aliases = item.aliases_in_domain + " / " + item.max_num_aliases_for_domain;
            item.mailboxes = item.mboxes_in_domain + " / " + item.max_num_mboxes_for_domain;
            item.quota = item.quota_used_in_domain + "/" + item.max_quota_for_domain + "/" + item.bytes_total;
            item.stats = item.msgs_total + "/" + item.bytes_total;

            if (!item.rl) item.rl = '∞';
            else {
              item.rl = $.map(item.rl, function(e){
                return e;
              }).join('/1');
            }

            item.def_quota_for_mbox = humanFileSize(item.def_quota_for_mbox);
            item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="domain" name="multi_select" value="' + encodeURIComponent(item.domain_name) + '" />';
            item.action = '<div class="btn-group">';
            if (role == "admin") {
              item.action += '<a href="/edit/domain/' + encodeURIComponent(item.domain_name) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-domain" data-api-url="delete/domain" data-item="' + encodeURIComponent(item.domain_name) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                  '<a href="#dnsInfoModal" class="btn btn-sm btn-xs-lg btn-info" data-bs-toggle="modal" data-domain="' + encodeURIComponent(item.domain_name) + '"><i class="bi bi-globe2"></i> DNS</a></div>';
            }
            else {
              item.action += '<a href="/edit/domain/' + encodeURIComponent(item.domain_name) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#dnsInfoModal" class="btn btn-sm btn-xs-lg btn-xs-half btn-info" data-bs-toggle="modal" data-domain="' + encodeURIComponent(item.domain_name) + '"><i class="bi bi-globe2"></i> DNS</a></div>';
            }

            if (Array.isArray(item.tags)){
              var tags = '';
              for (var i = 0; i < item.tags.length; i++)
                tags += '<span class="badge bg-primary tag-badge"><i class="bi bi-tag-fill"></i> ' + escapeHtml(item.tags[i]) + '</span>';
              item.tags = tags;
            } else {
              item.tags = '';
            }

            if (item.backupmx == 1) {
              if (item.relay_unknown_only == 1) {
                item.domain_h_name = '<div class="badge fs-7 bg-info">Relay Non-Local</div> ' + item.domain_h_name;
              } else if (item.relay_all_recipients == 1) {
                item.domain_h_name = '<div class="badge fs-7 bg-info">Relay All</div> ' + item.domain_h_name;
              } else {
                item.domain_h_name = '<div class="badge fs-7 bg-info">Relay</div> ' + item.domain_h_name;
              }
            }
          });

          return json.data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: lang.domain,
          data: 'domain_h_name',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.aliases,
          data: 'aliases',
          searchable: false,
          defaultContent: ''
        },
        {
          title: lang.mailboxes,
          data: 'mailboxes',
          searchable: false,
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: lang.domain_quota,
          data: 'quota',
          searchable: false,
          defaultContent: '',
          render: function (data, type) {
            data = data.split("/");
            return humanFileSize(data[0]) + " / " + humanFileSize(data[1]);
          }
        },
        {
          title: lang.stats,
          data: 'stats',
          searchable: false,
          defaultContent: '',
          render: function (data, type) {
            data = data.split("/");
            return '<i class="bi bi-files"></i> ' + data[0] + ' / ' + humanFileSize(data[1]);
          }
        },
        {
          title: lang.mailbox_defquota,
          data: 'def_quota_for_mbox',
          searchable: false,
          defaultContent: ''
        },
        {
          title: lang.mailbox_quota,
          data: 'max_quota_for_mbox',
          searchable: false,
          defaultContent: ''
        },
        {
          title: 'RL',
          data: 'rl',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: lang.backup_mx,
          data: 'backupmx',
          searchable: false,
          defaultContent: '',
          render: function (data, type){
            return 1==data ? '<i class="bi bi-check-lg"></i>' : 0==data && '<i class="bi bi-x-lg"></i>';
          }
        },
        {
          title: lang.domain_admins,
          data: 'domain_admins',
          searchable: false,
          orderable: false,
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.created_on,
          data: 'created',
          searchable: false,
          orderable: false,
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.last_modified,
          data: 'modified',
          searchable: false,
          orderable: false,
          defaultContent: '',
          className: 'none'
        },
        {
          title: 'Tags',
          data: 'tags',
          searchable: true,
          orderable: false,
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.active,
          data: 'active',
          searchable: false,
          defaultContent: '',
          responsivePriority: 6,
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':(0==data?'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>':2==data&&'&#8212;');
          }
        },
        {
          title: lang.action,
          data: 'action',
          searchable: false,
          orderable: false,
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-domains', '#domain_table');
    });
  }
  function draw_templates_domain_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#templates_domain_table') ) {
      $('#templates_domain_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#templates_domain_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-templates-domains', '#templates_domain_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/domain/template/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="domain_template" name="multi_select" value="' + encodeURIComponent(item.id) + '" />';

            item.attributes.def_quota_for_mbox = humanFileSize(item.attributes.def_quota_for_mbox);
            item.attributes.max_quota_for_mbox = humanFileSize(item.attributes.max_quota_for_mbox);
            item.attributes.max_quota_for_domain = humanFileSize(item.attributes.max_quota_for_domain);

            item.template = escapeHtml(item.template);
            if (item.attributes.rl_frame === "s"){
              item.attributes.rl_frame = lang_rl.second;
            } else if (item.attributes.rl_frame === "m"){
              item.attributes.rl_frame = lang_rl.minute;
            } else if (item.attributes.rl_frame === "h"){
              item.attributes.rl_frame = lang_rl.hour;
            } else if (item.attributes.rl_frame === "d"){
              item.attributes.rl_frame = lang_rl.day;
            }
            item.attributes.rl_value = escapeHtml(item.attributes.rl_value);


            if (item.template.toLowerCase() == "default"){
              item.action = '<div class="btn-group">' +
              '<a href="/edit/template/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '</div>';
            }
            else {
              item.action = '<div class="btn-group">' +
              '<a href="/edit/template/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-template" data-api-url="delete/domain/template" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            }

            if (Array.isArray(item.attributes.tags)){
              var tags = '';
              for (var i = 0; i < item.attributes.tags.length; i++)
                tags += '<span class="badge bg-primary tag-badge"><i class="bi bi-tag-fill"></i> ' + escapeHtml(item.attributes.tags[i]) + '</span>';
              item.attributes.tags = tags;
            } else {
              item.attributes.tags = '';
            }
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: "ID",
          data: 'id',
          responsivePriority: 2,
          defaultContent: ''
        },
        {
          title: lang.template,
          data: 'template',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.max_aliases,
          data: 'attributes.max_num_aliases_for_domain',
          defaultContent: '',
        },
        {
          title: lang.max_mailboxes,
          data: 'attributes.max_num_mboxes_for_domain',
          defaultContent: '',
        },
        {
          title: lang.mailbox_defquota,
          data: 'attributes.def_quota_for_mbox',
          defaultContent: '',
        },
        {
          title: lang.max_quota,
          data: 'attributes.max_quota_for_mbox',
          defaultContent: '',
        },
        {
          title: lang.domain_quota_total,
          data: 'attributes.max_quota_for_domain',
          defaultContent: '',
        },
        {
          title: lang.gal,
          data: 'attributes.gal',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.backup_mx,
          data: 'attributes.backupmx',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.relay_all,
          data: 'attributes.relay_all_recipients',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.relay_unknown,
          data: 'attributes.relay_unknown_only',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.active,
          data: 'attributes.active',
          defaultContent: '',
          responsivePriority: 4,
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: 'rl_frame',
          data: 'attributes.rl_frame',
          defaultContent: '',
          class: 'none',
        },
        {
          title: 'rl_value',
          data: 'attributes.rl_value',
          defaultContent: '',
          class: 'none',
        },
        {
          title: lang.dkim_domains_selector,
          data: 'attributes.dkim_selector',
          defaultContent: '',
          class: 'none',
        },
        {
          title: lang.dkim_key_length,
          data: 'attributes.key_size',
          defaultContent: '',
          class: 'none',
        },
        {
          title: 'Tags',
          data: 'attributes.tags',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 6,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-templates-domains', '#templates_domain_table');
    });
  }
  function draw_mailbox_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#mailbox_table') ) {
      $('#mailbox_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#mailbox_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: true,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      initComplete: function(settings, json){
        hideTableExpandCollapseBtn('#tab-mailboxes', '#mailbox_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/mailbox/datatables",
        dataSrc: function(json){
          $.each(json.data, function (i, item) {
            item.quota = {
              sortBy: item.quota_used,
              value: item.quota
            }
            item.quota.value = (item.quota.value == 0 ? "∞" : humanFileSize(item.quota.value));
            item.quota.value = humanFileSize(item.quota_used) + "/" + item.quota.value;

            item.max_quota_for_mbox = humanFileSize(item.max_quota_for_mbox);
            item.last_mail_login = item.last_imap_login + '/' + item.last_pop3_login + '/' + item.last_smtp_login;
            /*
            if (!item.rl) {
              item.rl = '∞';
            } else {
              item.rl = $.map(item.rl, function(e){
                return e;
              }).join('/1');
              if (item.rl_scope === 'domain') {
                item.rl = '<i class="bi bi-arrow-return-right"></i> ' + item.rl + ' (via ' + item.domain + ')';
              }
            }
            */
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="mailbox" name="multi_select" value="' + encodeURIComponent(item.username) + '" />';
            if (item.attributes.passwd_update != '0') {
              var last_pw_change = new Date(item.attributes.passwd_update.replace(/-/g, "/"));
              item.last_pw_change = last_pw_change.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
            } else {
              item.last_pw_change = '-';
            }
            item.tls_enforce_in = '<i class="text-' + (item.attributes.tls_enforce_in == 1 ? 'success bi bi-lock-fill' : 'danger bi bi-unlock-fill') + '"></i>';
            item.tls_enforce_out = '<i class="text-' + (item.attributes.tls_enforce_out == 1 ? 'success bi bi-lock-fill' : 'danger bi bi-unlock-fill') + '"></i>';
            item.pop3_access = '<i class="text-' + (item.attributes.pop3_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.pop3_access == 1 ? 'check-lg' : 'x-lg') + '"></i>';
            item.imap_access = '<i class="text-' + (item.attributes.imap_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.imap_access == 1 ? 'check-lg' : 'x-lg') + '"></i>';
            item.smtp_access = '<i class="text-' + (item.attributes.smtp_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.smtp_access == 1 ? 'check-lg' : 'x-lg') + '"></i>';
            item.sieve_access = '<i class="text-' + (item.attributes.sieve_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.sieve_access == 1 ? 'check-lg' : 'x-lg') + '"></i>';
            if (item.attributes.quarantine_notification === 'never') {
              item.quarantine_notification = lang.never;
            } else if (item.attributes.quarantine_notification === 'hourly') {
              item.quarantine_notification = lang.hourly;
            } else if (item.attributes.quarantine_notification === 'daily') {
              item.quarantine_notification = lang.daily;
            } else if (item.attributes.quarantine_notification === 'weekly') {
              item.quarantine_notification = lang.weekly;
            }
            if (item.attributes.quarantine_category === 'reject') {
              item.quarantine_category = '<span class="text-danger">' + lang.q_reject + '</span>';
            } else if (item.attributes.quarantine_category === 'add_header') {
              item.quarantine_category = '<span class="text-warning">' + lang.q_add_header + '</span>';
            } else if (item.attributes.quarantine_category === 'all') {
              item.quarantine_category = lang.q_all;
            }
            if (acl_data.login_as === 1) {

              item.action = '<div class="btn-group">' +
              '<a href="/edit/mailbox/' + encodeURIComponent(item.username) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURIComponent(item.username) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '<a href="/index.php?duallogin=' + encodeURIComponent(item.username) + '" class="login_as btn btn-sm btn-xs-lg btn-xs-half btn-success"><i class="bi bi-person-fill"></i> Login</a>';
              if (ALLOW_ADMIN_EMAIL_LOGIN) {
                item.action += '<a href="/sogo-auth.php?login=' + encodeURIComponent(item.username) + '" class="login_as btn btn-sm btn-xs-lg btn-xs-half btn-primary" target="_blank"><i class="bi bi-envelope-fill"></i> SOGo</a>';
              }
              item.action += '</div>';
            }
            else {
            item.action = '<div class="btn-group">' +
              '<a href="/edit/mailbox/' + encodeURIComponent(item.username) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURIComponent(item.username) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            }
            item.in_use = {
              sortBy: item.percent_in_use,
              value: '<div class="progress">' +
              '<div class="progress-bar-mailbox progress-bar progress-bar-' + item.percent_class + '" role="progressbar" aria-valuenow="' + item.percent_in_use + '" aria-valuemin="0" aria-valuemax="100" ' +
              'style="min-width:2em;width:' + item.percent_in_use + '%">' + item.percent_in_use + '%' + '</div></div>'
            };
            item.username = escapeHtml(item.username);

            if (Array.isArray(item.tags)){
              var tags = '';
              for (var i = 0; i < item.tags.length; i++)
                tags += '<span class="badge bg-primary tag-badge"><i class="bi bi-tag-fill"></i> ' + escapeHtml(item.tags[i]) + '</span>';
              item.tags = tags;
            } else {
              item.tags = '';
            }
          });

          return json.data;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: lang.username,
          data: 'username',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.domain_quota,
          data: 'quota.value',
          searchable: false,
          responsivePriority: 8,
          defaultContent: ''
        },
        {
          title: lang.last_mail_login,
          data: 'last_mail_login',
          searchable: false,
          defaultContent: '',
          responsivePriority: 7,
          render: function (data, type) {
            res = data.split("/");
            return '<div class="badge bg-info mb-2">IMAP @ ' + unix_time_format(Number(res[0])) + '</div><br>' +
              '<div class="badge bg-info mb-2">POP3 @ ' + unix_time_format(Number(res[1])) + '</div><br>' +
              '<div class="badge bg-info">SMTP @ ' + unix_time_format(Number(res[2])) + '</div>';
          }
        },
        {
          title: lang.last_pw_change,
          data: 'last_pw_change',
          searchable: false,
          defaultContent: ''
        },
        {
          title: lang.in_use,
          data: 'in_use.value',
          searchable: false,
          defaultContent: '',
          responsivePriority: 9,
          className: 'dt-data-w100'
        },
        {
          title: lang.fname,
          data: 'name',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.domain,
          data: 'domain',
          defaultContent: '',
          className: 'none',
        },
        {
          title: lang.iam,
          data: 'authsource',
          defaultContent: '',
          className: 'none',
          render: function (data, type) {
            return '<span class="badge bg-primary">' + data + '<i class="ms-2 bi bi-person-circle"></i></i></span>';
          }
        },
        {
          title: lang.tls_enforce_in,
          data: 'tls_enforce_in',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.tls_enforce_out,
          data: 'tls_enforce_out',
          defaultContent: '',
          className: 'none'
        },
        {
          title: 'SMTP',
          data: 'smtp_access',
          defaultContent: '',
          className: 'none'
        },
        {
          title: 'IMAP',
          data: 'imap_access',
          defaultContent: '',
          className: 'none'
        },
        {
          title: 'POP3',
          data: 'pop3_access',
          defaultContent: '',
          className: 'none'
        },
        {
          title: 'SIEVE',
          data: 'sieve_access',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.quarantine_notification,
          data: 'quarantine_notification',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.quarantine_category,
          data: 'quarantine_category',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.msg_num,
          data: 'messages',
          searchable: false,
          defaultContent: '',
          responsivePriority: 5
        },
        {
          title: lang.created_on,
          data: 'created',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.last_modified,
          data: 'modified',
          defaultContent: '',
          className: 'none'
        },
        {
          title: 'Tags',
          data: 'tags',
          searchable: true,
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.active,
          data: 'active',
          searchable: false,
          defaultContent: '',
          responsivePriority: 4,
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':(0==data?'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>':2==data&&'&#8212;');
          }
        },
        {
          title: lang.action,
          data: 'action',
          searchable: false,
          orderable: false,
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 6,
          defaultContent: ''
        }
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-mailboxes', '#mailbox_table');
    });
  }
  function draw_templates_mbox_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#templates_mbox_table') ) {
      $('#templates_mbox_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#templates_mbox_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-templates-mbox', '#templates_mbox_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/mailbox/template/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="mailbox_template" name="multi_select" value="' + encodeURIComponent(item.id) + '" />';

            item.template = escapeHtml(item.template);
            if (item.attributes.rl_frame === "s"){
              item.attributes.rl_frame = lang_rl.second;
            } else if (item.attributes.rl_frame === "m"){
              item.attributes.rl_frame = lang_rl.minute;
            } else if (item.attributes.rl_frame === "h"){
              item.attributes.rl_frame = lang_rl.hour;
            } else if (item.attributes.rl_frame === "d"){
              item.attributes.rl_frame = lang_rl.day;
            }
            item.attributes.rl_value = escapeHtml(item.attributes.rl_value);

            item.attributes.quota = humanFileSize(item.attributes.quota);

            item.attributes.tls_enforce_in = '<i class="text-' + (item.attributes.tls_enforce_in == 1 ? 'success bi bi-lock-fill' : 'danger bi bi-unlock-fill') + '"><span class="sorting-value">' + (item.attributes.tls_enforce_in == 1 ? '1' : '0') + '</span></i>';
            item.attributes.tls_enforce_out = '<i class="text-' + (item.attributes.tls_enforce_out == 1 ? 'success bi bi-lock-fill' : 'danger bi bi-unlock-fill') + '"><span class="sorting-value">' + (item.attributes.tls_enforce_out == 1 ? '1' : '0') + '</span></i>';
            item.attributes.pop3_access = '<i class="text-' + (item.attributes.pop3_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.pop3_access == 1 ? 'check-lg' : 'x-lg') + '"><span class="sorting-value">' + (item.attributes.pop3_access == 1 ? '1' : '0') + '</span></i>';
            item.attributes.imap_access = '<i class="text-' + (item.attributes.imap_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.imap_access == 1 ? 'check-lg' : 'x-lg') + '"><span class="sorting-value">' + (item.attributes.imap_access == 1 ? '1' : '0') + '</span></i>';
            item.attributes.smtp_access = '<i class="text-' + (item.attributes.smtp_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.smtp_access == 1 ? 'check-lg' : 'x-lg') + '"><span class="sorting-value">' + (item.attributes.smtp_access == 1 ? '1' : '0') + '</span></i>';
            item.attributes.sieve_access = '<i class="text-' + (item.attributes.sieve_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.sieve_access == 1 ? 'check-lg' : 'x-lg') + '"><span class="sorting-value">' + (item.attributes.sieve_access == 1 ? '1' : '0') + '</span></i>';
            item.attributes.sogo_access = '<i class="text-' + (item.attributes.sogo_access == 1 ? 'success' : 'danger') + ' bi bi-' + (item.attributes.sogo_access == 1 ? 'check-lg' : 'x-lg') + '"><span class="sorting-value">' + (item.attributes.sogo_access == 1 ? '1' : '0') + '</span></i>';
            if (item.attributes.quarantine_notification === 'never') {
              item.attributes.quarantine_notification = lang.never;
            } else if (item.attributes.quarantine_notification === 'hourly') {
              item.attributes.quarantine_notification = lang.hourly;
            } else if (item.attributes.quarantine_notification === 'daily') {
              item.attributes.quarantine_notification = lang.daily;
            } else if (item.attributes.quarantine_notification === 'weekly') {
              item.attributes.quarantine_notification = lang.weekly;
            }
            if (item.attributes.quarantine_category === 'reject') {
              item.attributes.quarantine_category = '<span class="text-danger">' + lang.q_reject + '</span>';
            } else if (item.attributes.quarantine_category === 'add_header') {
              item.attributes.quarantine_category = '<span class="text-warning">' + lang.q_add_header + '</span>';
            } else if (item.attributes.quarantine_category === 'all') {
              item.attributes.quarantine_category = lang.q_all;
            }

            if (item.template.toLowerCase() == "default"){
              item.action = '<div class="btn-group">' +
                '<a href="/edit/template/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '</div>';
            }
            else {
              item.action = '<div class="btn-group">' +
                '<a href="/edit/template/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-template" data-api-url="delete/mailbox/template" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                '</div>';
            }

            if (Array.isArray(item.attributes.tags)){
              var tags = '';
              for (var i = 0; i < item.attributes.tags.length; i++)
                tags += '<span class="badge bg-primary tag-badge"><i class="bi bi-tag-fill"></i> ' + escapeHtml(item.attributes.tags[i]) + '</span>';
              item.attributes.tags = tags;
            } else {
              item.attributes.tags = '';
            }
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: "ID",
          data: 'id',
          responsivePriority: 2,
          defaultContent: ''
        },
        {
          title: lang.template,
          data: 'template',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.domain_quota,
          data: 'attributes.quota',
          defaultContent: '',
        },
        {
          title: lang.tls_enforce_in,
          data: 'attributes.tls_enforce_in',
          defaultContent: ''
        },
        {
          title: lang.tls_enforce_out,
          data: 'attributes.tls_enforce_out',
          defaultContent: ''
        },
        {
          title: 'SMTP',
          data: 'attributes.smtp_access',
          defaultContent: '',
        },
        {
          title: 'IMAP',
          data: 'attributes.imap_access',
          defaultContent: '',
        },
        {
          title: 'POP3',
          data: 'attributes.pop3_access',
          defaultContent: '',
        },
        {
          title: 'SIEVE',
          data: 'attributes.sieve_access',
          defaultContent: '',
        },
        {
          title: 'SOGO',
          data: 'attributes.sogo_access',
          defaultContent: '',
        },
        {
          title: lang.quarantine_notification,
          data: 'attributes.quarantine_notification',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.quarantine_category,
          data: 'attributes.quarantine_category',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.force_pw_update,
          data: 'attributes.force_pw_update',
          defaultContent: '',
          class: 'none',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"></i>':'<i class="bi bi-x-lg"></i>';
          }
        },
        {
          title: "rl_frame",
          data: 'attributes.rl_frame',
          defaultContent: '',
          class: 'none',
        },
        {
          title: 'rl_value',
          data: 'attributes.rl_value',
          defaultContent: '',
          class: 'none',
        },
        {
          title: 'Tags',
          data: 'attributes.tags',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.active,
          data: 'attributes.active',
          defaultContent: '',
          responsivePriority: 4,
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':(0==data?'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>':2==data&&'&#8212;');
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 6,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-templates-mbox', '#templates_mbox_table');
    });
  }
  function draw_resource_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#resource_table') ) {
      $('#resource_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#resource_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      initComplete: function(settings, json){
        hideTableExpandCollapseBtn('#tab-resources', '#resource_table');
        filterByDomain(json, 5, table);
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/resource/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            if (item.multiple_bookings == '0') {
              item.multiple_bookings = '<span id="active-script" class="badge fs-6 bg-success">' + lang.booking_0_short + '</span>';
            } else if (item.multiple_bookings == '-1') {
              item.multiple_bookings = '<span id="active-script" class="badge fs-6 bg-warning">' + lang.booking_lt0_short + '</span>';
            } else {
              item.multiple_bookings = '<span id="active-script" class="badge fs-6 bg-danger">' + lang.booking_custom_short + ' (' + item.multiple_bookings + ')</span>';
            }
            item.action = '<div class="btn-group">' +
              '<a href="/edit/resource/' + encodeURIComponent(item.name) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-resource" data-api-url="delete/resource" data-item="' + item.name + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="resource" name="multi_select" value="' + encodeURIComponent(item.name) + '" />';
            item.name = escapeHtml(item.name);
            item.description = escapeHtml(item.description);
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: lang.description,
          data: 'description',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.alias,
          data: 'name',
          defaultContent: ''
        },
        {
          title: lang.kind,
          data: 'kind',
          defaultContent: ''
        },
        {
          title: lang.domain,
          data: 'domain',
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: lang.multiple_bookings,
          data: 'multiple_bookings',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':(0==data?'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>':2==data&&'&#8212;');
          }
        },
        {
          title: lang.action,
          data: 'action',
          responsivePriority: 5,
          defaultContent: '',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right'
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-resources', '#resource_table');
    });
  }
  function draw_bcc_table() {
    $.get("/api/v1/get/bcc-destination-options", function(data){
      var optgroup = "";
      // Domains
      if (data.domains && data.domains.length > 0) {
        optgroup = "<optgroup label='" + lang.domains + "'>";
        $.each(data.domains, function(index, domain){
          optgroup += "<option value='" + domain + "'>" + domain + "</option>";
        });
        optgroup += "</optgroup>";
        $('#bcc-local-dest').append(optgroup);
      }
      // Alias domains
      if (data.alias_domains && data.alias_domains.length > 0) {
        optgroup = "<optgroup label='" + lang.domain_aliases + "'>";
        $.each(data.alias_domains, function(index, alias_domain){
          optgroup += "<option value='" + alias_domain + "'>" + alias_domain + "</option>";
        });
        optgroup += "</optgroup>"
        $('#bcc-local-dest').append(optgroup);
      }
      // Mailboxes and aliases
      if (data.mailboxes && Object.keys(data.mailboxes).length > 0) {
        $.each(data.mailboxes, function(mailbox, aliases){
          optgroup = "<optgroup label='" + mailbox + "'>";
          $.each(aliases, function(index, alias){
            optgroup += "<option value='" + alias + "'>" + alias + "</option>";
          });
          optgroup += "</optgroup>";
          $('#bcc-local-dest').append(optgroup);
        });
      }
      // Recreate picker
      $('#bcc-local-dest').selectpicker('refresh');
    });

    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#bcc_table') ) {
      $('#bcc_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#bcc_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(settings, json){
        hideTableExpandCollapseBtn('#collapse-tab-bcc', '#bcc_table');
        filterByDomain(json, 6, table);
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/bcc/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="/edit/bcc/' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-bcc" data-api-url="delete/bcc" data-item="' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="bcc" name="multi_select" value="' + item.id + '" />';
            item.local_dest = escapeHtml(item.local_dest);
            item.bcc_dest = escapeHtml(item.bcc_dest);
            if (item.type == 'sender') {
              item.type = '<span id="active-script" class="badge fs-6 bg-success">' + lang.bcc_sender_map + '</span>';
            } else {
              item.type = '<span id="inactive-script" class="badge fs-6 bg-warning">' + lang.bcc_rcpt_map + '</span>';
            }
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.bcc_type,
          data: 'type',
          defaultContent: ''
        },
        {
          title: lang.bcc_local_dest,
          data: 'local_dest',
          defaultContent: ''
        },
        {
          title: lang.bcc_destinations,
          data: 'bcc_dest',
          defaultContent: ''
        },
        {
          title: lang.domain,
          data: 'domain',
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':(0==data?'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>':2==data&&'&#8212;');
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#collapse-tab-bcc', '#bcc_table');
    });
  }
  function draw_recipient_map_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#recipient_map_table') ) {
      $('#recipient_map_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#recipient_map_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(){
        hideTableExpandCollapseBtn('#collapse-tab-bcc-filters', '#recipient_map_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/recipient_map/all",
        dataSrc: function(json){
          if (role !== "admin") return null;

          $.each(json, function (i, item) {
            item.recipient_map_old = escapeHtml(item.recipient_map_old);
            item.recipient_map_new = escapeHtml(item.recipient_map_new);
            item.action = '<div class="btn-group">' +
              '<a href="/edit/recipient_map/' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-recipient_map" data-api-url="delete/recipient_map" data-item="' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="recipient_map" name="multi_select" value="' + item.id + '" />';
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.recipient_map_old,
          data: 'recipient_map_old',
          defaultContent: ''
        },
        {
          title: lang.recipient_map_new,
          data: 'recipient_map_new',
          defaultContent: '',
          responsivePriority: 4
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':0==data&&'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#collapse-tab-bcc-filters', '#recipient_map_table');
    });
  }
  function draw_tls_policy_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#tls_policy_table') ) {
      $('#tls_policy_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#tls_policy_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-tls-policy', '#tls_policy_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/tls-policy-map/all",
        dataSrc: function(json){
          if (role !== "admin") return null;

          $.each(json, function (i, item) {
            item.dest = escapeHtml(item.dest);
            item.policy = '<b>' + escapeHtml(item.policy) + '</b>';
            if (item.parameters == '') {
              item.parameters = '<code>-</code>';
            } else {
              item.parameters = '<code>' + escapeHtml(item.parameters) + '</code>';
            }
            item.action = '<div class="btn-group">' +
              '<a href="/edit/tls_policy_map/' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-tls-policy-map" data-api-url="delete/tls-policy-map" data-item="' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="tls-policy-map" name="multi_select" value="' + item.id + '" />';
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.tls_map_dest,
          data: 'dest',
          defaultContent: '',
          responsivePriority: 4
        },
        {
          title: lang.tls_map_policy,
          data: 'policy',
          defaultContent: ''
        },
        {
          title: lang.tls_map_parameters,
          data: 'parameters',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':0==data&&'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-tls-policy', '#tls_policy_table');
    });
  }
  function draw_alias_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#alias_table') ) {
      $('#alias_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#alias_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(settings, json){
        hideTableExpandCollapseBtn('#tab-mbox-aliases', '#alias_table');
        filterByDomain(json, 5, table);
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/alias/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="/edit/alias/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-alias" data-api-url="delete/alias" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="alias" name="multi_select" value="' + encodeURIComponent(item.id) + '" />';
            item.goto = escapeHtml(item.goto.replace(/,/g, " "));
            if (item.public_comment !== null) {
              item.public_comment = escapeHtml(item.public_comment);
            }
            else {
              item.public_comment = '-';
            }
            if (item.private_comment !== null) {
              item.private_comment = escapeHtml(item.private_comment);
            }
            else {
              item.private_comment = '-';
            }
            if (item.is_catch_all == 1) {
              item.address = '<div class="badge fs-6 bg-secondary">' + lang.catch_all + '</div> ' + escapeHtml(item.address);
            }
            else {
              item.address = escapeHtml(item.address);
            }
            if (item.goto == "null@localhost") {
              item.goto = '⤷ <i class="bi bi-trash" style="font-size:12px"></i>';
            }
            else if (item.goto == "spam@localhost") {
              item.goto = '<span class="badge fs-6 bg-danger">' + lang.goto_spam + '</span>';
            }
            else if (item.goto == "ham@localhost") {
              item.goto = '<span class="badge fs-6 bg-success">' + lang.goto_ham + '</span>';
            }
            if (item.in_primary_domain !== "") {
              item.domain = '<i data-domainname="' + item.domain + '" class="bi bi-info-circle-fill alias-domain-info text-info" data-bs-toggle="tooltip" title="' + lang.target_domain + ': ' + item.in_primary_domain + '"></i> ' + item.domain;
            }
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.alias,
          data: 'address',
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: lang.target_address,
          data: 'goto',
          defaultContent: ''
        },
        {
          title: lang.domain,
          data: 'domain',
          defaultContent: '',
          responsivePriority: 5,
        },
        {
          title: lang.bcc_destinations,
          data: 'bcc_dest',
          defaultContent: ''
        },
        {
          title: lang.sogo_visible,
          data: 'sogo_visible',
          defaultContent: '',
          render: function(data, type){
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':0==data&&'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.public_comment,
          data: 'public_comment',
          defaultContent: ''
        },
        {
          title: lang.private_comment,
          data: 'private_comment',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          responsivePriority: 6,
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':0==data&&'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-mbox-aliases', '#alias_table');
    });

    table.on( 'draw', function (){
        $('#alias_table [data-bs-toggle="tooltip"]').tooltip();
    });
  }
  function draw_aliasdomain_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#aliasdomain_table') ) {
      $('#aliasdomain_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#aliasdomain_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-domain-aliases', '#aliasdomain_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/alias-domain/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.alias_domain = escapeHtml(item.alias_domain);

            item.action = '<div class="btn-group">' +
              '<a href="/edit/aliasdomain/' + encodeURIComponent(item.alias_domain) + '" class="btn btn-sm btn-xs-lg btn-xs-third btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-alias-domain" data-api-url="delete/alias-domain" data-item="' + encodeURIComponent(item.alias_domain) + '" class="btn btn-sm btn-xs-lg btn-xs-third btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '<a href="#dnsInfoModal" class="btn btn-sm btn-xs-lg btn-xs-third btn-info" data-bs-toggle="modal" data-domain="' + encodeURIComponent(item.alias_domain) + '"><i class="bi bi-globe2"></i> DNS</a></div>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="alias-domain" name="multi_select" value="' + encodeURIComponent(item.alias_domain) + '" />';
            if(item.parent_is_backupmx == '1') {
              item.target_domain = '<span><a href="/edit/domain/' + item.target_domain + '">' + item.target_domain + '</a> <div class="badge fs-6 bg-warning">' + lang.alias_domain_backupmx + '</div></span>';
            } else {
              item.target_domain = '<span><a href="/edit/domain/' + item.target_domain + '">' + item.target_domain + '</a></span>';
            }
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: lang.alias,
          data: 'alias_domain',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.target_domain,
          data: 'target_domain',
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':0==data&&'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-domain-aliases', '#aliasdomain_table');
    });
  }
  function draw_sync_job_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#sync_job_table') ) {
      $('#sync_job_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#sync_job_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-syncjobs', '#sync_job_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/syncjobs/all/no_log",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.log = '<a href="#syncjobLogModal" data-bs-toggle="modal" data-syncjob-id="' + encodeURIComponent(item.id) + '">' + lang.open_logs + '</a>'
            item.user2 = escapeHtml(item.user2);
            if (!item.exclude > 0) {
              item.exclude = '-';
            } else {
              item.exclude  = '<code>' + escapeHtml(item.exclude) + '</code>';
            }
            item.server_w_port = escapeHtml(item.user1) + '@' + escapeHtml(item.host1) + ':' + escapeHtml(item.port1);
            item.action = '<div class="btn-group">' +
              '<a href="/edit/syncjob/' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-syncjob" data-api-url="delete/syncjob" data-item="' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="syncjob" name="multi_select" value="' + item.id + '" />';
            if (item.is_running == 1) {
              item.is_running = '<span id="active-script" class="badge fs-6 bg-success">' + lang.running + '</span>';
            } else {
              item.is_running = '<span id="inactive-script" class="badge fs-6 bg-warning">' + lang.waiting + '</span>';
            }
            if (!item.last_run > 0) {
              item.last_run = lang.waiting;
            }
            if (item.success == null) {
              item.success = '-';
              item.exit_status = '';
            } else {
              item.success = '<i class="text-' + (item.success == 1 ? 'success' : 'danger') + ' bi bi-' + (item.success == 1 ? 'check-lg' : 'x-lg') + '"></i>';
            }
            if (lang['syncjob_'+item.exit_status]) {
              item.exit_status = lang['syncjob_'+item.exit_status];
            } else if (item.success != '-') {
              item.exit_status = lang.syncjob_check_log;
            }
            item.exit_status = item.success + ' ' + item.exit_status;
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: lang.owner,
          data: 'user2',
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: 'Server',
          data: 'server_w_port',
          defaultContent: ''
        },
        {
          title: lang.last_run,
          data: 'last_run',
          defaultContent: ''
        },
        {
          title: lang.syncjob_last_run_result,
          data: 'exit_status',
          defaultContent: ''
        },
        {
          title: 'Log',
          data: 'log',
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          render: function (data, type) {
            return 1==data?'<i class="bi bi-check-lg"><span class="sorting-value">1</span></i>':0==data&&'<i class="bi bi-x-lg"><span class="sorting-value">0</span></i>';
          }
        },
        {
          title: lang.status,
          data: 'is_running',
          defaultContent: ''
        },
        {
          title: lang.excludes,
          data: 'exclude',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.mins_interval,
          data: 'mins_interval',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-syncjobs', '#sync_job_table');
    });
  }
  function draw_filter_table() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#filter_table') ) {
      $('#filter_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    var table = $('#filter_table').DataTable({
      responsive: true,
      autoWidth: false,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'desc']],
      initComplete: function(){
        hideTableExpandCollapseBtn('#tab-filters', '#filter_table');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/filters/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            if (item.active == 1) {
              item.active = '<span id="active-script" class="badge fs-6 bg-success">' + lang.active + '</span>';
            } else {
              item.active = '<span id="inactive-script" class="badge fs-6 bg-warning">' + lang.inactive + '</span>';
            }
            item.script_desc = escapeHtml(item.script_desc);
            item.script_data = '<pre class="text-break" style="margin:0px">' + escapeHtml(item.script_data) + '</pre>'
            item.filter_type = '<div class="badge fs-6 bg-secondary">' + item.filter_type.charAt(0).toUpperCase() + item.filter_type.slice(1).toLowerCase() + '</div>'
            item.action = '<div class="btn-group">' +
              '<a href="/edit/filter/' + item.id + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-filter" data-api-url="delete/filter" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="filter_item" name="multi_select" value="' + item.id + '" />'
          });

          return json;
        }
      },
      columns: [
        {
          // placeholder, so checkbox will not block child row toggle
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        {
          title: '',
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 2
        },
        {
          title: 'ID',
          data: 'id',
          responsivePriority: 2,
          defaultContent: ''
        },
        {
          title: lang.active,
          data: 'active',
          responsivePriority: 3,
          defaultContent: ''
        },
        {
          title: 'Type',
          data: 'filter_type',
          responsivePriority: 4,
          defaultContent: ''
        },
        {
          title: lang.owner,
          data: 'username',
          defaultContent: ''
        },
        {
          title: lang.description,
          data: 'script_desc',
          defaultContent: ''
        },
        {
          title: 'Script',
          data: 'script_data',
          defaultContent: '',
          className: 'none'
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 5,
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#tab-filters', '#filter_table');
    });
  };

  function hideTableExpandCollapseBtn(tab, table){
    if ($(table).hasClass('collapsed'))
      $(tab).find(".table_collapse_option").show();
    else
      $(tab).find(".table_collapse_option").hide();
  }
  
  function filterByDomain(json, column, table){
    var tableId = $(table.table().container()).attr('id');
    // Create the `select` element
    var select = $('<select class="btn btn-sm btn-xs-lg btn-light text-start mx-2"><option value="">'+lang.all_domains+'</option></select>')
      .insertBefore(
        $('#'+tableId+' .dataTables_filter > label > input')
      )
      .on( 'change', function(){
        table.column(column)
          .search($(this).val())
          .draw();
      });

    // get all domains
    var domains = [];
    json.forEach(obj => {
      Object.entries(obj).forEach(([key, value]) => {
        if(key === 'domain') {
          domains.push(value)
        }
      });
    });
    
    // get unique domain list
    domains = domains.filter(function(value, index, array) {
      return array.indexOf(value) === index;
    });
    
    // add domains to select
    domains.forEach(function(domain) {
        select.append($('<option>' + domain + '</option>'));
    });
  }

  // detect element visibility changes
  function onVisible(element, callback) {
    $(document).ready(function() {
      let element_object = document.querySelector(element);
      if (element_object === null) return;

      let observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if(entry.intersectionRatio > 0) {
            callback(element_object);
            observer.unobserve(element_object);
          }
        });
      })

      observer.observe(element_object);
    });
  }

  // Load only if the tab is visible
  onVisible("[id^=domain_table]", () => draw_domain_table());
  onVisible("[id^=templates_domain_table]", () => draw_templates_domain_table());
  onVisible("[id^=mailbox_table]", () => draw_mailbox_table());
  onVisible("[id^=templates_mbox_table]", () => draw_templates_mbox_table());
  onVisible("[id^=resource_table]", () => draw_resource_table());
  onVisible("[id^=alias_table]", () => draw_alias_table());
  onVisible("[id^=aliasdomain_table]", () => draw_aliasdomain_table());
  onVisible("[id^=sync_job_table]", () => draw_sync_job_table());
  onVisible("[id^=filter_table]", () => draw_filter_table());
  onVisible("[id^=bcc_table]", () => draw_bcc_table());
  onVisible("[id^=recipient_map_table]", () => draw_recipient_map_table());
  onVisible("[id^=tls_policy_table]", () => draw_tls_policy_table());
});
