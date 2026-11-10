// Mailbox groups (admin/domainadmin mailbox page, group edit page). Loaded via $js_minifier.
var mailcowGroups = (function($) {
  'use strict';

  // Lang strings: lang.groups (mailbox page) or lang_groups (edit page), '' if missing
  function L(key) {
    var src = (typeof lang_groups !== 'undefined' && lang_groups) ? lang_groups :
      ((typeof lang !== 'undefined' && lang && lang.groups) ? lang.groups : {});
    return (src[key] !== undefined && src[key] !== null) ? src[key] : '';
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : s);
  }

  function membersCell(members) {
    if (!Array.isArray(members) || members.length === 0) return '-';
    var list = members.slice(0, 3).map(function(m) {
      return (m.source === 'idp' ? '<i class="bi bi-cloud" title="' + esc(L('idp_member')) + '"></i> ' : '') + esc(m.username);
    }).join('<br>');
    if (members.length > 3) list += '<br><span class="text-muted">+' + (members.length - 3) + '</span>';
    return '<span class="badge bg-info">' + members.length + '</span><br>' + list;
  }

  function addressCell(item) {
    if (!item.address) return '-';
    var flags = [];
    if (item.sender_allowed == 1) flags.push('<i class="bi bi-send" title="' + esc(L('sender_allowed')) + '"></i>');
    if (item.internal == 1) flags.push('<i class="bi bi-building-lock" title="' + esc(L('internal')) + '"></i>');
    return esc(item.address) + (flags.length ? ' ' + flags.join(' ') : '');
  }

  function drawTable() {
    if ($.fn.DataTable.isDataTable('#mailbox_group_table')) {
      $('#mailbox_group_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }
    $('#mailbox_group_table').DataTable({
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
        url: "/api/v1/get/mailbox-group/all",
        dataSrc: function(json) {
          if (!Array.isArray(json)) return [];
          $.each(json, function(i, item) {
            item.chkbox = item.can_edit ?
              '<input type="checkbox" class="form-check-input" data-id="mailbox_group" name="multi_select" value="' + encodeURIComponent(item.id) + '" />' :
              '<input type="checkbox" class="form-check-input" disabled />';
            item.name_display = esc(item.name) + (item.idp_ref ? ' <span class="badge bg-info">' + esc(L('idp')) + '</span>' : '');
            if (item.description) item.name_display += '<div class="small text-muted">' + esc(item.description) + '</div>';
            item.address_display = addressCell(item);
            item.members_display = membersCell(item.members);
            item.send_as_display = (item.send_as || []).map(esc).join('<br>') || '-';
            item.template_display = item.template ? esc(item.template) + ' <span class="text-muted">(' + esc(item.priority) + ')</span>' : '-';
            item.action = '<div class="btn-group">' +
              '<a href="/edit/mailbox-group/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + esc(item.can_edit ? lang.edit : L('view')) + '</a>' +
              (item.can_edit ? '<a href="#" data-action="delete_selected" data-id="single-mailbox_group" data-api-url="delete/mailbox-group" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + esc(lang.remove) + '</a>' : '') +
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
        { title: L('address'), data: 'address_display', defaultContent: '', responsivePriority: 3 },
        { title: L('members'), data: 'members_display', defaultContent: '', responsivePriority: 4 },
        { title: L('send_as'), data: 'send_as_display', defaultContent: '', responsivePriority: 7 },
        { title: L('template'), data: 'template_display', defaultContent: '', responsivePriority: 8 },
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

  // Share rows (edit page, add modal) are kept as JSON in the hidden form field of their table (imap_grants, sogo_grants)
  function syncGrants($table) {
    var grants = [];
    $table.find('tr.group-grant').each(function() {
      var grant = {};
      $(this).find('[data-grant]').each(function() {
        grant[$(this).data('grant')] = $(this).is(':checkbox') ? ($(this).is(':checked') ? 1 : 0) : $(this).val();
      });
      if (grant.owner) grants.push(grant);
    });
    $table.closest('form').find('input[name="' + $table.data('field') + '"]').val(JSON.stringify(grants));
  }

  $(document).ready(function() {
    // @domain suffix of the address in the add modal
    $('.mailbox-group-domain-select').on('change', function() {
      $(this).closest('form').find('.mailbox-group-domain').text($(this).val());
    });
    $(document).on('change', '.group-grants [data-grant]', function() {
      syncGrants($(this).closest('.group-grants'));
    });
    $(document).on('click', '.group-grant-add', function(e) {
      e.preventDefault();
      var $table = $('#' + $(this).data('table'));
      var $body = $table.children('tbody');
      if (!$body.length) $body = $('<tbody>').appendTo($table);
      var $row = $($('#' + $(this).data('table') + '_template').html()).appendTo($body);
      $row.find('select').selectpicker({'styleBase': 'btn btn-xs-lg', 'noneSelectedText': lang_footer.nothing_selected});
      syncGrants($table);
    });
    $(document).on('click', '.group-grant-del', function(e) {
      e.preventDefault();
      var $table = $(this).closest('.group-grants');
      $(this).closest('tr').remove();
      syncGrants($table);
    });
  });

  return {
    drawTable: drawTable,
    L: L
  };
})(jQuery);
