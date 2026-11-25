// Identity provider sync: status card and run requests (admin system page, identity provider tab).
// Runs are executed by the identity-sync cron (every minute); the card polls until the requested run is done.
var mailcowIdentity = (function($) {
  'use strict';

  var POLL_MS = 5000;
  var POLL_MAX = 36;
  var polls = 0;
  var timer = null;
  var STATUS_CLASS = {ok: 'bg-success', blocked: 'bg-warning text-dark', error: 'bg-danger', pending: 'bg-secondary', running: 'bg-info text-dark'};
  // summary counts in display order
  var COUNTS = ['creates', 'links', 'deactivate', 'restore', 'release', 'missing', 'found', 'updates', 'group_creates', 'group_renames',
    'memberships', 'orphaned_groups', 'membership_removals', 'conflicts', 'errors', 'skipped'];
  var DETAILS = ['conflicts', 'errors', 'deactivate', 'restore', 'missing', 'creates', 'group_creates'];

  // admin.twig injects the admin lang section as `lang`
  function L(key) {
    var src = (typeof lang_admin !== 'undefined') ? lang_admin : ((typeof lang !== 'undefined') ? lang : {});
    return (src[key] !== undefined) ? src[key] : key;
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : String(s));
  }

  function badge(label, value, cls) {
    return '<span class="badge ' + (cls || 'bg-light text-dark border') + ' me-1 mb-1">' + esc(label) + ': ' + esc(value) + '</span>';
  }

  function changes(run) {
    var s = run.summary;
    if (!s) return '';
    if (s.error) return '<span class="text-danger">' + esc(s.error) + '</span>';
    var counts = s.counts || {};
    var html = '';
    $.each(COUNTS, function(i, key) {
      if (counts[key] > 0) html += badge(L('iam_sync_count_' + key), counts[key], (key === 'errors' || key === 'conflicts') ? 'bg-danger' : null);
    });
    if (html === '') html = '<span class="text-muted">' + esc(L('iam_sync_no_changes')) + '</span>';
    var details = $.extend({}, s.details || {}, {errors: s.errors || []});
    var lists = '';
    $.each(DETAILS, function(i, key) {
      var items = details[key] || [];
      if (!items.length) return;
      lists += '<div class="mt-1"><b>' + esc(L('iam_sync_count_' + key)) + '</b><ul class="mb-1">' +
        $.map(items, function(item) { return '<li>' + esc(item) + '</li>'; }).join('') + '</ul></div>';
    });
    if (lists !== '') html += '<details class="small mt-1"><summary>' + esc(L('iam_sync_details')) + '</summary>' + lists + '</details>';
    return html;
  }

  function render(status) {
    var c = status.counts || {};
    var counts = badge(L('iam_sync_accounts'), c.accounts || 0) + badge(L('iam_sync_linked'), c.linked || 0) +
      badge(L('iam_sync_idp_disabled'), c.idp_disabled || 0, c.idp_disabled ? 'bg-warning text-dark' : null) +
      badge(L('iam_sync_missing'), c.missing || 0, c.missing ? 'bg-warning text-dark' : null);
    if (!status.sync_enabled) counts += '<div class="text-muted small mt-1">' + esc(L('iam_sync_off')) + '</div>';
    $('#iam_sync_counts').html(counts);
    var rows = $.map(status.runs || [], function(run) {
      var state = '<span class="badge ' + (STATUS_CLASS[run.status] || 'bg-secondary') + '">' + esc(L('iam_sync_status_' + run.status)) + '</span>';
      return '<tr><td class="text-nowrap">' + esc(run.started || run.created) + '</td>' +
        '<td>' + esc(L('iam_sync_mode_' + run.mode)) + (run.requested_by ? '<div class="small text-muted">' + esc(run.requested_by) + '</div>' : '') + '</td>' +
        '<td>' + state + '</td><td>' + changes(run) + '</td></tr>';
    });
    $('#iam_sync_runs tbody').html(rows.length ? rows.join('') : '<tr><td colspan="4" class="text-muted">' + esc(L('iam_sync_none')) + '</td></tr>');
    return status.runs && status.runs.length && (status.runs[0].status === 'pending' || status.runs[0].status === 'running');
  }

  function load() {
    clearTimeout(timer);
    $.get('/api/v1/get/identity-provider/sync-status', function(status) {
      if (render(status || {}) && polls++ < POLL_MAX) timer = setTimeout(load, POLL_MS);
    }, 'json');
  }

  function request(mode) {
    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: '/api/v1/edit/identity-provider-sync',
      data: {items: JSON.stringify(['sync']), attr: JSON.stringify({mode: mode}), csrf_token: csrf_token},
      success: function(res) {
        var msg = (res && res[0]) ? res[0] : {type: 'danger', msg: 'generic_server_error'};
        var key = $.isArray(msg.msg) ? msg.msg[0] : msg.msg;
        var text = (msg.type === 'success' ? lang_success : lang_danger)[key] || key;
        mailcow_alert_box(text, msg.type === 'success' ? 'success' : 'danger');
        polls = 0;
        load();
      }
    });
  }

  $(document).ready(function() {
    if (!$('#iam_sync_status').length) return;
    load();
    $(document).on('click', '.iam_sync_request', function(e) {
      e.preventDefault();
      var mode = $(this).data('mode');
      if (mode === 'force' && !confirm(L('iam_sync_force_confirm'))) return;
      request(mode);
    });
  });

  return {load: load};
})(jQuery);
