// Retention rules (admin/domainadmin mailbox page): list with the last nightly run, domain choices of the add modal.
// The log of the runs is on the dashboard (draw_retention_logs). Loaded via $js_minifier.
var mailcowRetention = (function($) {
  'use strict';

  // lang.retention of the mailbox page, '' if missing
  function L(key) {
    var src = (typeof lang !== 'undefined' && lang && lang.retention) ? lang.retention : {};
    return (src[key] !== undefined && src[key] !== null) ? src[key] : '';
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : s);
  }
  // replaces the %s of a text one after the other
  function fill(text) {
    var args = Array.prototype.slice.call(arguments, 1);
    return text.replace(/%s/g, function() { return args.length ? args.shift() : ''; });
  }

  function folderLabel(folder) {
    return folder.indexOf('folder:') === 0 ? folder.substr(7) : (L('folder_' + folder) || folder);
  }

  function foldersCell(item) {
    return (item.folders || []).map(function(folder) { return esc(folderLabel(folder)); }).join('<br>');
  }

  function periodCell(item) {
    return esc(fill(L('days_short'), item.days)) + (item.keep_flagged == 1 ? '<div class="small text-muted">' + esc(L('keep_flagged')) + '</div>' : '');
  }

  function modeCell(mode) {
    return mode === 'delete' ? '<span class="badge bg-danger">' + esc(L('mode_delete')) + '</span>'
      : '<span class="badge bg-secondary">' + esc(L('simulated')) + '</span>';
  }

  function assignedCell(item) {
    var list = [];
    if (item.all_mailboxes == 1) list.push(esc(L('all_mailboxes')));
    (item.mailboxes || []).slice(0, 3).forEach(function(mailbox) { list.push(esc(mailbox)); });
    if ((item.mailboxes || []).length > 3) list.push('<span class="text-muted">+' + (item.mailboxes.length - 3) + '</span>');
    if ((item.groups || []).length) list.push('<i class="bi bi-people"></i> ' + esc(L('groups')) + ': ' + item.groups.length);
    return list.length ? list.join('<br>') : '<span class="text-muted">' + esc(L('assigned_none')) + '</span>';
  }

  function lastRunCell(run) {
    if (!run) return '<span class="text-muted">' + esc(L('never_run')) + '</span>';
    var text = run.simulated == 1 ? fill(L('run_simulated'), run.mailboxes, run.matched) : fill(L('run_deleted'), run.mailboxes, run.deleted);
    return esc(run.created) + '<div class="small text-muted">' + esc(text) + '</div>'
      + (run.errors > 0 ? '<div class="small text-danger">' + esc(fill(L('errors'), run.errors)) + '</div>' : '');
  }

  function drawTable() {
    if ($.fn.DataTable.isDataTable('#retention_table')) {
      $('#retention_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }
    $('#retention_table').DataTable({
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
        url: "/api/v1/get/retention-rule/all",
        dataSrc: function(json) {
          if (!Array.isArray(json)) return [];
          $.each(json, function(i, item) {
            item.chkbox = item.can_edit ?
              '<input type="checkbox" class="form-check-input" data-id="retention_rule" name="multi_select" value="' + encodeURIComponent(item.id) + '" />' :
              '<input type="checkbox" class="form-check-input" disabled />';
            item.name_display = esc(item.name) + (item.description ? '<div class="small text-muted">' + esc(item.description) + '</div>' : '');
            item.folders_display = foldersCell(item);
            item.period_display = periodCell(item);
            item.mode_display = modeCell(item.mode);
            item.assigned_display = assignedCell(item);
            item.last_run_display = lastRunCell(item.last_run);
            item.action = '<div class="btn-group">' +
              '<a href="/edit/retention-rule/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + esc(item.can_edit ? lang.edit : L('view')) + '</a>' +
              (item.can_edit ? '<a href="#" data-action="delete_selected" data-id="single-retention_rule" data-api-url="delete/retention-rule" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + esc(lang.remove) + '</a>' : '') +
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
        { title: 'ID', data: 'id', defaultContent: '', responsivePriority: 7 },
        { title: L('name'), data: 'name_display', defaultContent: '', responsivePriority: 2 },
        { title: L('domain'), data: 'domain', defaultContent: '', responsivePriority: 5 },
        { title: L('folders'), data: 'folders_display', defaultContent: '', responsivePriority: 3 },
        { title: L('days'), data: 'period_display', defaultContent: '', responsivePriority: 3 },
        { title: L('mode'), data: 'mode_display', defaultContent: '', responsivePriority: 4 },
        { title: L('assign'), data: 'assigned_display', defaultContent: '', responsivePriority: 5 },
        { title: L('last_run'), data: 'last_run_display', defaultContent: '', responsivePriority: 4 },
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

  // add modal: mailboxes and groups of the chosen domain only. The options are rebuilt and bootstrap-select is
  // built anew: its refresh repeats the list and still shows hidden options
  function filterDomain($form) {
    var domain = $form.find('select.retention-domain-select').val();
    $form.find('select.retention-mailboxes, select.retention-groups').each(function() {
      var $select = $(this);
      if (!$select.data('all-options')) $select.data('all-options', $select.children('option').clone());
      $select.selectpicker('destroy');
      $select.empty().append($select.data('all-options').filter(function() {
        return $(this).data('domain') === domain;
      }).clone().prop('selected', false));
      $select.selectpicker({'styleBase': 'btn btn-xs-lg', 'noneSelectedText': lang_footer.nothing_selected});
    });
  }

  $(document).ready(function() {
    $('select.retention-domain-select').on('change', function() {
      filterDomain($(this).closest('form'));
    });
    $('#addRetentionModal').on('show.bs.modal', function() {
      filterDomain($(this).find('form'));
    });
  });

  return {
    drawTable: drawTable
  };
})(jQuery);
