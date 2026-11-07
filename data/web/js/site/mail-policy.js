// Mail policies (admin/domainadmin mailbox page): list, domain choices of the add modal, test of a rule. Loaded via $js_minifier.
var mailcowMailPolicy = (function($) {
  'use strict';

  // lang.mail_policy of the mailbox page, '' if missing
  function L(key) {
    var src = (typeof lang !== 'undefined' && lang && lang.mail_policy) ? lang.mail_policy : {};
    return (src[key] !== undefined && src[key] !== null) ? src[key] : '';
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : s);
  }

  // mode of one direction with the number of allowed and blocked entries
  function sideCell(mode, allow, block) {
    var html = esc(L('summary_' + mode));
    var counts = [];
    if (mode !== 'all' && allow.length) counts.push('+' + allow.length + ' ' + esc(L('summary_allowed')));
    if (block.length) counts.push(block.length + ' ' + esc(L('summary_blocked')));
    return html + (counts.length ? '<div class="small text-muted">' + counts.join(', ') + '</div>' : '');
  }

  function assignedCell(item) {
    var list = (item.mailboxes || []).slice(0, 3).map(esc);
    if ((item.mailboxes || []).length > 3) list.push('<span class="text-muted">+' + (item.mailboxes.length - 3) + '</span>');
    if ((item.groups || []).length) list.push('<i class="bi bi-people"></i> ' + esc(L('groups')) + ': ' + item.groups.length);
    return list.length ? list.join('<br>') : '<span class="text-muted">' + esc(L('assigned_none')) + '</span>';
  }

  function drawTable() {
    if ($.fn.DataTable.isDataTable('#mail_policy_table')) {
      $('#mail_policy_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }
    $('#mail_policy_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" + "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[3, 'asc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/mail-policy/all",
        dataSrc: function(json) {
          if (!Array.isArray(json)) return [];
          $.each(json, function(i, item) {
            item.chkbox = item.can_edit ?
              '<input type="checkbox" class="form-check-input" data-id="mail_policy" name="multi_select" value="' + encodeURIComponent(item.id) + '" />' :
              '<input type="checkbox" class="form-check-input" disabled />';
            item.name_display = esc(item.name) + (item.description ? '<div class="small text-muted">' + esc(item.description) + '</div>' : '');
            item.send_display = sideCell(item.send_mode, item.send_allow || [], item.send_block || []);
            item.receive_display = sideCell(item.receive_mode, item.receive_allow || [], item.receive_block || []);
            item.assigned_display = assignedCell(item);
            item.action = '<div class="btn-group">' +
              '<a href="/edit/mail-policy/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + esc(item.can_edit ? lang.edit : L('view')) + '</a>' +
              (item.can_edit ? '<a href="#" data-action="delete_selected" data-id="single-mail_policy" data-api-url="delete/mail-policy" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + esc(lang.remove) + '</a>' : '') +
              '</div>';
          });
          return json;
        },
        error: function() {
          mailcow_alert_box(L('list_error'), 'danger');
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
        { title: '', data: 'chkbox', searchable: false, orderable: false, defaultContent: '', responsivePriority: 2 },
        { title: 'ID', data: 'id', defaultContent: '', responsivePriority: 6 },
        { title: L('name'), data: 'name_display', defaultContent: '', responsivePriority: 2 },
        { title: L('domain'), data: 'domain', defaultContent: '', responsivePriority: 5 },
        { title: L('send'), data: 'send_display', defaultContent: '', responsivePriority: 3 },
        { title: L('receive'), data: 'receive_display', defaultContent: '', responsivePriority: 3 },
        { title: L('assign'), data: 'assigned_display', defaultContent: '', responsivePriority: 4 },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          responsivePriority: 6,
          render: function(d) { return d == 1 ? '<i class="bi bi-check-lg"></i>' : '<i class="bi bi-x-lg"></i>'; }
        },
        {
          title: lang.action,
          data: 'action',
          orderable: false,
          className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right',
          responsivePriority: 3,
          defaultContent: ''
        }
      ]
    });
  }

  // add modal: mailboxes and groups of the chosen domain only
  function filterDomain($form) {
    var domain = $form.find('select.mail-policy-domain-select').val();
    $form.find('select.mail-policy-mailboxes, select.mail-policy-groups').each(function() {
      $(this).find('option').each(function() {
        var other = $(this).data('domain') !== domain;
        $(this).prop('hidden', other);
        if (other) $(this).prop('selected', false);
      });
      $(this).selectpicker('refresh');
    });
  }

  function test(e) {
    e.preventDefault();
    var sender = $.trim($('#mail_policy_test_sender').val());
    var recipient = $.trim($('#mail_policy_test_recipient').val());
    if (!recipient) return;
    // an empty sender (bounces) is sent as "-"
    var url = '/api/v1/get/mail-policy/test/' + encodeURIComponent(sender || '-') + '/' + encodeURIComponent(recipient) +
      ($('#mail_policy_test_login').is(':checked') ? '/login' : '');
    $.get(url, function(r) {
      var html;
      if (!r || r.allowed === undefined) {
        html = '<div class="alert alert-danger py-2">' + esc(L('test_invalid')) + '</div>';
      } else if (r.allowed == 1) {
        html = '<div class="alert alert-success py-2">' + esc(L('test_allowed')) + '</div>';
      } else {
        var reason = r.internal_alias == 1 ? L('test_denied_internal') : r.side === 'address' ? L('test_denied_address') :
          (r.policy ? L('test_denied_' + r.side).replace('%s', r.policy) : L('test_denied_other'));
        html = '<div class="alert alert-danger py-2">' + esc(reason) + '<div class="small mt-1"><code>550 5.7.1 ' + esc(r.text) + '</code></div></div>';
      }
      if (r && r.allowed !== undefined && sender && r.logged_in == 0 && $('#mail_policy_test_login').is(':checked')) {
        html += '<div class="small text-muted">' + esc(L('test_not_a_mailbox')) + '</div>';
      }
      $('#mail_policy_test_result').html(html);
    }, 'json').fail(function() {
      $('#mail_policy_test_result').html('<div class="alert alert-danger py-2">' + esc(lang_danger.generic_server_error) + '</div>');
    });
  }

  $(document).ready(function() {
    $('select.mail-policy-domain-select').on('change', function() {
      filterDomain($(this).closest('form'));
    });
    $('#addMailPolicyModal').on('show.bs.modal', function() {
      filterDomain($(this).find('form'));
    });
    $('#mail_policy_test').on('submit', test);
  });

  return {
    drawTable: drawTable
  };
})(jQuery);
