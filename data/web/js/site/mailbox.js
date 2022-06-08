$(document).ready(function() {
  acl_data = JSON.parse(acl);
  // FooTable.domainFilter = FooTable.Filtering.extend({
  //   construct: function(instance){
  //     this._super(instance);
  //     this.def = lang.all_domains;
  //     this.$domain = null;
  //   },
  //   $create: function(){
  //     this._super();
  //     var self = this;
  //     var domains = [];

  //     $.each(self.ft.rows.all, function(i, row){
  //       if((row.val().domain != null) && ($.inArray(row.val().domain, domains) === -1)) domains.push(row.val().domain);
  //     });

  //     $form_grp = $('<div/>', {'class': 'form-group'})
  //       .append($('<label/>', {'class': 'sr-only', text: 'Domain'}))
  //       .prependTo(self.$form);
  //     self.$domain = $('<select/>', { 'class': 'aform-control' })
  //       .on('change', {self: self}, self._onDomainDropdownChanged)
  //       .append($('<option/>', {text: self.def}))
  //       .appendTo($form_grp);

  //     $.each(domains, function(i, domain){
  //       domainname = $($.parseHTML(domain)).data('domainname')
  //       if (domainname !== undefined) {
  //         self.$domain.append($('<option/>').text(domainname));
  //       } else {
  //         self.$domain.append($('<option/>').text(domain));
  //       }
  //     });
  //   },
  //   _onDomainDropdownChanged: function(e){
  //     var self = e.data.self,
  //       selected = $(this).val();
  //     if (selected !== self.def){
  //       self.addFilter('domain', selected, ['domain']);
  //     } else {
  //       self.removeFilter('domain');
  //     }
  //     self.filter();
  //   },
  //   draw: function(){
  //     this._super();
  //     var domain = this.find('domain');
  //     if (domain instanceof FooTable.Filter){
  //       this.$domain.val(domain.query.val());
  //     } else {
  //       this.$domain.val(this.def);
  //     }
  //     $(this.$domain).closest("select").selectpicker();
  //   }
  // });
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

  // Read bcc local dests
  // Using ajax to not be a blocking moo
  $.get("/api/v1/get/bcc-destination-options", function(data){
    // Domains
    var optgroup = "<optgroup label='" + lang.domains + "'>";
    $.each(data.domains, function(index, domain){
      optgroup += "<option value='" + domain + "'>" + domain + "</option>"
    });
    optgroup += "</optgroup>"
    $('#bcc-local-dest').append(optgroup);
    // Alias domains
    var optgroup = "<optgroup label='" + lang.domain_aliases + "'>";
    $.each(data.alias_domains, function(index, alias_domain){
      optgroup += "<option value='" + alias_domain + "'>" + alias_domain + "</option>"
    });
    optgroup += "</optgroup>"
    $('#bcc-local-dest').append(optgroup);
    // Mailboxes and aliases
    $.each(data.mailboxes, function(mailbox, aliases){
      var optgroup = "<optgroup label='" + mailbox + "'>";
      $.each(aliases, function(index, alias){
        optgroup += "<option value='" + alias + "'>" + alias + "</option>"
      });
      optgroup += "</optgroup>"
      $('#bcc-local-dest').append(optgroup);
    });
    // Finish
    $('#bcc-local-dest').find('option:selected').remove();
    $('#bcc-local-dest').selectpicker('refresh');
  });

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
    $('.dns-modal-body').html('<div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>');
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


});
jQuery(function($){
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  // http://stackoverflow.com/questions/46155/validate-email-address-in-javascript
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  function unix_time_format(i){return""==i?'<i class="bi bi-x-lg"></i>':new Date(i?1e3*i:0).toLocaleDateString(void 0,{year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit"})}
  $(".refresh_table").on('click', function(e) {
    e.preventDefault();
    var table_name = $(this).data('table');
    $('#' + table_name).DataTable().ajax.reload();
  });
  function draw_domain_table() {
    $('#domain_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/domain/all",
        dataSrc: function(json){
          console.log(json);
          $.each(json, function(i, item) {
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
            item.chkbox = '<input type="checkbox" data-id="domain" name="multi_select" value="' + encodeURIComponent(item.domain_name) + '" />';
            item.action = '<div class="btn-group footable-actions">';
            if (role == "admin") {
              item.action += '<a href="/edit/domain/' + encodeURIComponent(item.domain_name) + '" class="btn btn-xs btn-xs-third btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-domain" data-api-url="delete/domain" data-item="' + encodeURIComponent(item.domain_name) + '" class="btn btn-xs btn-xs-third btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
                  '<a href="#dnsInfoModal" class="btn btn-xs btn-xs-third btn-info" data-bs-toggle="modal" data-domain="' + encodeURIComponent(item.domain_name) + '"><i class="bi bi-globe2"></i> DNS</a></div>';
            }
            else {
              item.action += '<a href="/edit/domain/' + encodeURIComponent(item.domain_name) + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#dnsInfoModal" class="btn btn-xs btn-xs-half btn-info" data-bs-toggle="modal" data-domain="' + encodeURIComponent(item.domain_name) + '"><i class="bi bi-globe2"></i> DNS</a></div>';
            }

            if (item.backupmx == 1) {
              if (item.relay_unknown_only == 1) {
                item.domain_name = '<div class="badge fs-5 bg-info">Relay Non-Local</div> ' + item.domain_name;
              } else if (item.relay_all_recipients == 1) {
                item.domain_name = '<div class="badge fs-5 bg-info">Relay All</div> ' + item.domain_name;
              } else {
                item.domain_name = '<div class="badge fs-5 bg-info">Relay</div> ' + item.domain_name;
              }
            }
          });

          console.log(json);
          return json;
        }
      },
      columns: [
          {
            title: lang.domain,
            data: 'domain_name'
          },
          {
            title: lang.aliases,
            data: 'aliases_in_domain'
          },
          {
            title: lang.mailboxes,
            data: 'mboxes_in_domain'
          },
          {
            title: lang.domain_quota,
            data: 'quota',
            render: function (data, type) {
              data = data.split("/");
              return humanFileSize(data[0]) + " / " + humanFileSize(data[1]);
            }
          },
          {
            title: lang.stats,
            data: 'stats',
            render: function (data, type) {
              data = data.split("/");
              return '<i class="bi bi-files"></i> ' + data[0] + ' / ' + humanFileSize(data[1]);
            }
          },
          {
            title: lang.mailbox_defquota,
            data: 'def_quota_for_mbox'
          },
          {
            title: lang.mailbox_quota,
            data: 'max_quota_for_mbox'
          },
          {
            title: 'RL',
            data: 'rl'
          },
          {
            title: lang.backup_mx,
            data: 'backupmx',
            redner: function (data, type){
              return 1==value ? '<i class="bi bi-check-lg"></i>' : 0==value && '<i class="bi bi-x-lg"></i>';
            }
          },
          {
            title: lang.domain_admins,
            data: 'domain_admins'
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_mailbox_table() {
    $('#mailbox_table').DataTable({
			responsive : true,
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/mailbox/reduced",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.quota = item.quota_used + "/" + item.quota;
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
            item.chkbox = '<input type="checkbox" data-id="mailbox" name="multi_select" value="' + encodeURIComponent(item.username) + '" />';
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
              var btnSize = 'btn-xs-third';
              if (ALLOW_ADMIN_EMAIL_LOGIN) btnSize = 'btn-xs-quart';

            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/mailbox/' + encodeURIComponent(item.username) + '" class="btn btn-xs ' + btnSize + ' btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURIComponent(item.username) + '" class="btn btn-xs ' + btnSize + ' btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '<a href="/index.php?duallogin=' + encodeURIComponent(item.username) + '" class="login_as btn btn-xs ' + btnSize + ' btn-success"><i class="bi bi-person-fill"></i> Login</a>';
              if (ALLOW_ADMIN_EMAIL_LOGIN) {
                item.action += '<a href="/sogo-auth.php?login=' + encodeURIComponent(item.username) + '" class="login_as btn btn-xs ' + btnSize + ' btn-primary" target="_blank"><i class="bi bi-envelope-fill"></i> SOGo</a>';
              }
              item.action += '</div>';
            }
            else {
            item.action = '<div class="btn-group">' +
              '<a href="/edit/mailbox/' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-mailbox" data-api-url="delete/mailbox" data-item="' + encodeURIComponent(item.username) + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            }
            item.in_use = '<div class="progress">' +
              '<div class="progress-bar-mailbox progress-bar progress-bar-' + item.percent_class + '" role="progressbar" aria-valuenow="' + item.percent_in_use + '" aria-valuemin="0" aria-valuemax="100" ' +
              'style="min-width:2em;width:' + item.percent_in_use + '%">' + item.percent_in_use + '%' + '</div></div>';
            item.username = escapeHtml(item.username);
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: lang.username,
            data: 'username'
          },
          {
            title: lang.fname,
            data: 'name'
          },
          {
            title: lang.domain,
            data: 'domain'
          },
          {
            title: lang.domain_quota,
            data: 'quota',
            render: function (data, type) {
              data = data.split("/");
              var of_q = (data[1] == 0 ? "∞" : humanFileSize(data[1]));
              return humanFileSize(data[0]) + " / " + of_q;
            }
          },
          {
            title: lang.tls_enforce_in,
            data: 'tls_enforce_in'
          },
          {
            title: lang.tls_enforce_out,
            data: 'tls_enforce_out'
          },
          {
            title: 'SMTP',
            data: 'smtp_access'
          },
          {
            title: 'IMAP',
            data: 'imap_access'
          },
          {
            title: 'POP3',
            data: 'pop3_access'
          },
          {
            title: lang.last_mail_login,
            data: 'last_mail_login'
          },
          {
            title: lang.last_pw_change,
            data: 'last_pw_change'
          },
          {
            title: lang.quarantine_notification,
            data: 'quarantine_notification'
          },
          {
            title: lang.quarantine_category,
            data: 'quarantine_category'
          },
          {
            title: lang.in_use,
            data: 'in_use'
          },
          {
            title: lang.msg_num,
            data: 'messages'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':(0==data?'<i class="bi bi-x-lg"></i>':2==data&&'&#8212;');
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_resource_table() {
    $('#resource_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/resource/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            if (item.multiple_bookings == '0') {
              item.multiple_bookings = '<span id="active-script" class="badge fs-5 bg-success">' + lang.booking_0_short + '</span>';
            } else if (item.multiple_bookings == '-1') {
              item.multiple_bookings = '<span id="active-script" class="badge fs-5 bg-warning">' + lang.booking_lt0_short + '</span>';
            } else {
              item.multiple_bookings = '<span id="active-script" class="badge fs-5 bg-danger">' + lang.booking_custom_short + ' (' + item.multiple_bookings + ')</span>';
            }
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/resource/' + encodeURIComponent(item.name) + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-resource" data-api-url="delete/resource" data-item="' + item.name + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="resource" name="multi_select" value="' + encodeURIComponent(item.name) + '" />';
            item.name = escapeHtml(item.name);
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: lang.description,
            data: 'description'
          },
          {
            title: lang.alias,
            data: 'name'
          },
          {
            title: lang.kind,
            data: 'kind'
          },
          {
            title: lang.domain,
            data: 'domain'
          },
          {
            title: lang.multiple_bookings,
            data: 'multiple_bookings'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':(0==data?'<i class="bi bi-x-lg"></i>':2==data&&'&#8212;');
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_bcc_table() {
    $('#bcc_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/bcc/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/bcc/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-bcc" data-api-url="delete/bcc" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="bcc" name="multi_select" value="' + item.id + '" />';
            item.local_dest = escapeHtml(item.local_dest);
            item.bcc_dest = escapeHtml(item.bcc_dest);
            if (item.type == 'sender') {
              item.type = '<span id="active-script" class="badge fs-5 bg-success">' + lang.bcc_sender_map + '</span>';
            } else {
              item.type = '<span id="inactive-script" class="badge fs-5 bg-warning">' + lang.bcc_rcpt_map + '</span>';
            }
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: 'ID',
            data: 'id'
          },
          {
            title: lang.bcc_type,
            data: 'type'
          },
          {
            title: lang.bcc_local_dest,
            data: 'local_dest'
          },
          {
            title: lang.bcc_destinations,
            data: 'bcc_dest'
          },
          {
            title: lang.domain,
            data: 'domain'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':(0==data?'<i class="bi bi-x-lg"></i>':2==data&&'&#8212;');
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_recipient_map_table() {
    $('#recipient_map_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/recipient_map/all",
        dataSrc: function(json){
          if (role !== "admin") return null;
          
          $.each(json, function (i, item) {
            item.recipient_map_old = escapeHtml(item.recipient_map_old);
            item.recipient_map_new = escapeHtml(item.recipient_map_new);
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/recipient_map/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-recipient_map" data-api-url="delete/recipient_map" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="recipient_map" name="multi_select" value="' + item.id + '" />';
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: 'ID',
            data: 'id'
          },
          {
            title: lang.recipient_map_old,
            data: 'recipient_map_old'
          },
          {
            title: lang.recipient_map_new,
            data: 'recipient_map_new'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>';
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_tls_policy_table() {
    $('#tls_policy_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
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
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/tls_policy_map/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-tls-policy-map" data-api-url="delete/tls-policy-map" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="tls-policy-map" name="multi_select" value="' + item.id + '" />';
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: 'ID',
            data: 'id'
          },
          {
            title: lang.tls_map_dest,
            data: 'dest'
          },
          {
            title: lang.tls_map_policy,
            data: 'policy'
          },
          {
            title: lang.tls_map_parameters,
            data: 'parameters'
          },
          {
            title: lang.domain,
            data: 'domain'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>';
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_alias_table() {
    $('#alias_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/alias/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/alias/' + encodeURIComponent(item.id) + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-alias" data-api-url="delete/alias" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="alias" name="multi_select" value="' + encodeURIComponent(item.id) + '" />';
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
              item.address = '<div class="badge fs-5 bg-secondary">' + lang.catch_all + '</div> ' + escapeHtml(item.address);
            }
            else {
              item.address = escapeHtml(item.address);
            }
            if (item.goto == "null@localhost") {
              item.goto = '⤷ <i class="bi bi-trash" style="font-size:12px"></i>';
            }
            else if (item.goto == "spam@localhost") {
              item.goto = '<span class="badge fs-5 bg-danger">' + lang.goto_spam + '</span>';
            }
            else if (item.goto == "ham@localhost") {
              item.goto = '<span class="badge fs-5 bg-success">' + lang.goto_ham + '</span>';
            }
            if (item.in_primary_domain !== "") {
              item.domain = '<i data-domainname="' + item.domain + '" class="bi bi-info-circle-fill alias-domain-info text-info" data-bs-toggle="tooltip" title="' + lang.target_domain + ': ' + item.in_primary_domain + '"></i> ' + item.domain;
            }
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: 'ID',
            data: 'id'
          },
          {
            title: lang.alias,
            data: 'address'
          },
          {
            title: lang.target_address,
            data: 'goto'
          },
          {
            title: lang.bcc_destinations,
            data: 'bcc_dest'
          },
          {
            title: lang.domain,
            data: 'domain'
          },
          {
            title: lang.public_comment,
            data: 'public_comment'
          },
          {
            title: lang.private_comment,
            data: 'private_comment'
          },
          {
            title: lang.sogo_visible,
            data: 'sogo_visible'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>';
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_aliasdomain_table() {
    $('#aliasdomain_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/alias-domain/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/aliasdomain/' + encodeURIComponent(item.alias_domain) + '" class="btn btn-xs btn-xs-third btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-alias-domain" data-api-url="delete/alias-domain" data-item="' + encodeURIComponent(item.alias_domain) + '" class="btn btn-xs btn-xs-third btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '<a href="#dnsInfoModal" class="btn btn-xs btn-xs-third btn-info" data-bs-toggle="modal" data-domain="' + encodeURIComponent(item.alias_domain) + '"><i class="bi bi-globe2"></i> DNS</a></div>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="alias-domain" name="multi_select" value="' + encodeURIComponent(item.alias_domain) + '" />';
            if(item.parent_is_backupmx == '1') {
              item.target_domain = '<span><a href="/edit/domain/' + item.target_domain + '">' + item.target_domain + '</a> <div class="badge fs-5 bg-warning">' + lang.alias_domain_backupmx + '</div></span>';
            } else {
              item.target_domain = '<span><a href="/edit/domain/' + item.target_domain + '">' + item.target_domain + '</a></span>';
            }
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: lang.alias,
            data: 'alias_domain'
          },
          {
            title: lang.target_domain,
            data: 'target_domain'
          },
          {
            title: lang.bcc_local_dest,
            data: 'local_dest'
          },
          {
            title: lang.bcc_destinations,
            data: 'bcc_dest'
          },
          {
            title: lang.domain,
            data: 'domain'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>';
            }
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_sync_job_table() {
    $('#sync_job_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
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
              item.exclude  = '<code>' + item.exclude + '</code>';
            }
            item.server_w_port = escapeHtml(item.user1) + '@' + item.host1 + ':' + item.port1;
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/syncjob/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-syncjob" data-api-url="delete/syncjob" data-item="' + item.id + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="syncjob" name="multi_select" value="' + item.id + '" />';
            if (item.is_running == 1) {
              item.is_running = '<span id="active-script" class="badge fs-5 bg-success">' + lang.running + '</span>';
            } else {
              item.is_running = '<span id="inactive-script" class="badge fs-5 bg-warning">' + lang.waiting + '</span>';
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

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: 'ID',
            data: 'id'
          },
          {
            title: lang.owner,
            data: 'user2'
          },
          {
            title: 'Server',
            data: 'server_w_port'
          },
          {
            title: lang.excludes,
            data: 'exclude'
          },
          {
            title: lang.mins_interval,
            data: 'mins_interval'
          },
          {
            title: lang.last_run,
            data: 'last_run'
          },
          {
            title: lang.syncjob_last_run_result,
            data: 'exit_status'
          },
          {
            title: lang.status,
            data: 'is_running'
          },
          {
            title: lang.active,
            data: 'active',
            render: function (data, type) {
              return 1==data?'<i class="bi bi-check-lg"></i>':0==data&&'<i class="bi bi-x-lg"></i>';
            }
          },
          {
            title: 'Log',
            data: 'log'
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  }
  function draw_filter_table() {
    $('#filter_table').DataTable({
      processing: true,
      serverSide: false,
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/filters/all",
        dataSrc: function(json){
          $.each(json, function (i, item) {
            if (item.active == 1) {
              item.active = '<span id="active-script" class="badge fs-5 bg-success">' + lang.active + '</span>';
            } else {
              item.active = '<span id="inactive-script" class="badge fs-5 bg-warning">' + lang.inactive + '</span>';
            }
            item.script_data = '<pre style="margin:0px">' + escapeHtml(item.script_data) + '</pre>'
            item.filter_type = '<div class="badge fs-5 bg-secondary">' + item.filter_type.charAt(0).toUpperCase() + item.filter_type.slice(1).toLowerCase() + '</div>'
            item.action = '<div class="btn-group footable-actions">' +
              '<a href="/edit/filter/' + item.id + '" class="btn btn-xs btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + lang.edit + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="single-filter" data-api-url="delete/filter" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-xs btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="filter_item" name="multi_select" value="' + item.id + '" />'
          });

          console.log(json);
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
            defaultContent: ''
          },
          {
            title: '',
            data: 'chkbox'
          },
          {
            title: 'ID',
            data: 'id'
          },
          {
            title: lang.active,
            data: 'active'
          },
          {
            title: lang.filter_type,
            data: 'Type'
          },
          {
            title: lang.owner,
            data: 'username'
          },
          {
            title: lang.description,
            data: 'script_desc'
          },
          {
            title: 'Script',
            data: 'script_data'
          },
          {
            title: lang.action,
            data: 'action'
          },
      ]
    });
  };

  // detect element visibility changes
  function onVisible(element, callback) {
    $(element).ready(function() {
      element_object = document.querySelector(element)
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

  // Load only if the tab is visible
  onVisible("[id^=domain_table]", () => draw_domain_table());
  onVisible("[id^=mailbox_table]", () => draw_mailbox_table());
  onVisible("[id^=resource_table]", () => draw_resource_table());
  onVisible("[id^=alias_table]", () => draw_alias_table());
  onVisible("[id^=aliasdomain_table]", () => draw_aliasdomain_table());
  onVisible("[id^=sync_job_table]", () => draw_sync_job_table());
  onVisible("[id^=filter_table]", () => draw_filter_table());
  onVisible("[id^=bcc_table]", () => draw_bcc_table());
  onVisible("[id^=recipient_map_table]", () => draw_recipient_map_table());
  onVisible("[id^=tls_policy_table]", () => draw_tls_policy_table());
});
