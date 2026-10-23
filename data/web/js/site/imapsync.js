// IMAP sync jobs and sync sources (admin/domainadmin mailbox page, user page, edit page).
// Loaded via $js_minifier only on pages that show sync jobs.
var mailcowImapsync = (function($) {
  'use strict';

  // Lang strings: lang.syncjobs (mailbox/user page) or lang_syncjobs (edit page), '' if missing
  function L(key) {
    var src = (typeof lang_syncjobs !== 'undefined' && lang_syncjobs) ? lang_syncjobs :
      ((typeof lang !== 'undefined' && lang && lang.syncjobs) ? lang.syncjobs : {});
    return (src[key] !== undefined && src[key] !== null) ? src[key] : '';
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : s);
  }
  // same format as "last run" (YYYY-MM-DD HH:mm:ss), independent of the browser locale
  function formatDate(ts) {
    if (!ts) return '';
    var d = new Date(ts * 1000);
    var p = function(n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' +
      p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }
  function isAdmin() {
    return typeof mailcow_cc_role !== 'undefined' && mailcow_cc_role === 'admin';
  }

  // bootstrap-select: refresh an existing instance, or init with the mailcow defaults
  function refreshSelect($sel) {
    if (typeof $sel.selectpicker !== 'function') return;
    $sel.each(function() {
      var $s = $(this);
      if ($s.data('selectpicker')) {
        $s.selectpicker('refresh');
      } else {
        $s.selectpicker({
          'styleBase': 'btn btn-xs-lg',
          'noneSelectedText': (typeof lang_footer !== 'undefined') ? lang_footer.nothing_selected : ''
        });
      }
    });
  }

  // Long source labels must not overflow the modal on small screens
  function injectStyle() {
    if ($('#imapsync-style').length) return;
    $('head').append('<style id="imapsync-style">' +
      '.imapsync-select-wrap .bootstrap-select{max-width:100%;}' +
      '.imapsync-select-wrap .bootstrap-select .dropdown-menu{max-width:100%;}' +
      '.imapsync-select-wrap .bootstrap-select .dropdown-menu .text{white-space:normal;word-break:break-word;}' +
      '.imapsync-select-wrap .bootstrap-select .filter-option-inner-inner{overflow:hidden;text-overflow:ellipsis;}' +
      // long hosts / legacy names in table cells must wrap, otherwise the responsive tables hide every other column
      '.imapsync-wrap{min-width:7rem;overflow-wrap:anywhere;word-break:break-word;}' +
      '</style>');
  }

  // ===== Display helpers (tables) =====

  // Visibility label of a source row
  function scopeDisplay(item) {
    if (item.scope === 'all') return '<i class="bi bi-globe"></i> ' + esc(L('source_scope_all'));
    if (item.scope === 'domain') return esc(L('source_scope_domain') + ': ' + (item.domains || []).join(', '));
    var us = item.users || [];
    if (us.length === 0) return esc(item.created_by || '');
    return esc(L('source_scope_user') + ': ' + us.join(', '));
  }

  // Token badge of a client_credentials source: based on token existence/error, expiry in title
  function sourceTokenBadge(item) {
    if (item.oauth_last_refresh_error) {
      return '<span class="badge bg-danger" title="' + esc(item.oauth_last_refresh_error) + '">' + esc(L('token_state_failed')) + '</span>';
    }
    if (item.has_access_token) {
      var title = item.oauth_token_expires ? L('source_token_status') + ': ' + formatDate(item.oauth_token_expires) : '';
      return '<span class="badge bg-success" title="' + esc(title) + '">' + esc(L('token_state_issued')) + '</span>';
    }
    return '<span class="badge bg-warning">' + esc(L('token_state_none')) + '</span>';
  }

  // Auth column of a source row: auth type, and for XOAUTH2 the flow (+ token badge for client credentials)
  function authDisplay(item) {
    var out = esc(item.auth_type);
    if (item.auth_type !== 'XOAUTH2') return out;
    var flow = item.oauth_flow || 'client_credentials';
    out += ' <span class="text-muted">(' + esc(flow === 'authorization_code' ? L('source_flow_badge_user') : L('source_flow_badge_app')) + ')</span>';
    // authorization_code tokens live per job (local mailbox + remote user), not on the source
    if (flow === 'authorization_code') return out;
    return out + '<div class="mt-1">' + sourceTokenBadge(item) + '</div>';
  }

  // Per-job token badge for authorization_code sources ('' for everything else)
  function jobTokenBadge(item) {
    if (item.source_auth_type !== 'XOAUTH2' || item.source_oauth_flow !== 'authorization_code') return '';
    if (item.user_token_error) {
      return '<span class="badge bg-danger" title="' + esc(item.user_token_error) + '">' + esc(L('token_state_failed')) + '</span>';
    }
    if (parseInt(item.user_token_exists) === 1) {
      var title = item.user_token_expires ? L('source_token_status') + ': ' + formatDate(item.user_token_expires) : '';
      return '<span class="badge bg-success" title="' + esc(title) + '">' + esc(L('token_state_issued')) + '</span>';
    }
    return '<span class="badge bg-warning">' + esc(L('token_state_pending')) + '</span>';
  }

  // Warning badge for jobs with not migrated legacy custom parameters
  function legacyBadge(item) {
    if (!item.custom_params_legacy) return '';
    return '<span class="badge bg-warning text-dark" title="' + esc(L('custom_params_legacy_badge_title')) + '">' +
      '<i class="bi bi-exclamation-triangle"></i> ' + esc(L('custom_params_legacy_badge')) + '</span>';
  }

  // Wrappable table cell content (long hosts, mailboxes, legacy source names)
  function wrapCell(html) {
    return '<div class="imapsync-wrap">' + html + '</div>';
  }

  // Server column: "remote user @ host:port", source name below, badges after
  function serverDisplay(item) {
    var out = esc(item.user1 || '') + ' @ ' + esc(item.source_host || '?') + ':' + esc(item.source_port || '?');
    if (item.source_name) out += '<div class="small text-muted">' + esc(item.source_name) + '</div>';
    var badges = [jobTokenBadge(item), legacyBadge(item)].filter(Boolean);
    if (badges.length) out += '<div class="mt-1">' + badges.join(' ') + '</div>';
    return wrapCell(out);
  }

  // Prepares the shared fields of a job row (run state, last run, result)
  function prepareJobRow(item) {
    item.server_w_port = serverDisplay(item);
    if (parseInt(item.is_running) === 1) {
      item.is_running = '<span class="badge fs-6 bg-success">' + esc(L('status_running')) + '</span>';
    } else if (parseInt(item.active) !== 1) {
      item.is_running = '<span class="badge fs-6 bg-secondary">' + esc(L('status_inactive')) + '</span>';
    } else {
      item.is_running = '<span class="badge fs-6 bg-warning">' + esc(L('status_waiting')) + '</span>';
    }
    if (!item.last_run) {
      item.last_run = '<span class="text-muted">' + esc(L('never_run')) + '</span>';
    }
    if (item.success == null) {
      item.success = '-';
      item.exit_status = '';
    } else {
      item.success = '<i class="text-' + (item.success == 1 ? 'success' : 'danger') + ' bi bi-' + (item.success == 1 ? 'check-lg' : 'x-lg') + '"></i>';
    }
    if (L('syncjob_' + item.exit_status)) {
      item.exit_status = L('syncjob_' + item.exit_status);
    } else if (item.success != '-') {
      item.exit_status = L('syncjob_check_log');
    }
    item.exit_status = item.success + ' ' + esc(item.exit_status);
    return item;
  }

  // Job tables: ignore DataTables state saved before the rework (old ID sort, column visibility)
  var JOB_TABLE_STATE_VERSION = 2;
  function jobTableStateSave(settings, data) {
    data.imapsync_state = JOB_TABLE_STATE_VERSION;
  }
  function jobTableStateLoad(settings, data) {
    if (!data || data.imapsync_state !== JOB_TABLE_STATE_VERSION) return false;
    // column visibility depends on the role, never restore it
    if (Array.isArray(data.columns)) {
      data.columns.forEach(function(c) { if (c) delete c.visible; });
    }
  }

  // Sync source table (mailbox + user page). canManage: session may add/edit sources at all.
  function drawSourceTable(canManage) {
    if ($.fn.DataTable.isDataTable('#imapsync_source_table')) {
      $('#imapsync_source_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }
    $('#imapsync_source_table').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" + "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      order: [[2, 'asc']],
      ajax: {
        type: "GET",
        url: "/api/v1/get/syncjob_source/all",
        dataSrc: function(json) {
          if (!Array.isArray(json)) return [];
          $.each(json, function(i, item) {
            item.endpoint = wrapCell(esc((item.host || item.host1) + ':' + (item.port || item.port1) + ' (' + (item.enc || item.enc1) + ')'));
            item.name_display = esc(item.name);
            if (item.description) item.name_display += '<div class="small text-muted">' + esc(item.description) + '</div>';
            item.name_display = wrapCell(item.name_display);
            item.auth_display = authDisplay(item);
            item.visibility_display = wrapCell(scopeDisplay(item));
            if (canManage && item.can_edit) {
              item.action = '<div class="btn-group">' +
                '<a href="/edit/syncjob_source/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-secondary"><i class="bi bi-pencil-fill"></i> ' + esc(lang.edit) + '</a>' +
                '<a href="#" data-action="delete_selected" data-id="single-syncjob_source" data-api-url="delete/syncjob_source" data-item="' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + esc(lang.remove) + '</a>' +
                '</div>';
            } else {
              item.action = '<a href="/edit/syncjob_source/' + encodeURIComponent(item.id) + '" class="btn btn-sm btn-xs-lg btn-secondary"><i class="bi bi-eye"></i> ' + esc(L('source_view')) + '</a>';
            }
          });
          return json;
        },
        error: function() {
          mailcow_alert_box(L('source_list_error'), 'danger');
        }
      },
      columns: [
        {
          // placeholder, so the responsive control does not cover the ID
          title: '',
          data: null,
          searchable: false,
          orderable: false,
          defaultContent: '',
          responsivePriority: 1
        },
        { title: L('id'), data: 'id', defaultContent: '', responsivePriority: 4 },
        { title: L('source_name'), data: 'name_display', defaultContent: '', responsivePriority: 2 },
        { title: L('source_scope'), data: 'visibility_display', defaultContent: '', responsivePriority: 6 },
        { title: L('source_server'), data: 'endpoint', defaultContent: '', responsivePriority: 5 },
        { title: L('source_auth_type'), data: 'auth_display', defaultContent: '', responsivePriority: 3 },
        {
          title: lang.active,
          data: 'active',
          defaultContent: '',
          responsivePriority: 7,
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

  // ===== Source dropdown in the job modal / edit page =====

  function setNoSourceState($sel, empty) {
    $sel.closest('form').find('.imapsync-no-source').toggleClass('d-none', !empty);
  }

  // Replace the options of a source dropdown (array of jQuery options, kept in order) and select keepId (if present and enabled).
  // bootstrap-select 1.14 keeps a detached title option (incl. its selectedness) across empty()/refresh,
  // which overrides the selected option and leaves stale list entries, so destroy and re-init instead.
  function rebuildSourceSelect($sel, $options, keepId) {
    if (typeof $sel.selectpicker === 'function' && $sel.data('selectpicker')) $sel.selectpicker('destroy');
    $sel.find('option').remove();
    $sel.append($options);
    var $keep = (keepId !== '') ? $sel.find('option').filter(function() {
      return $(this).val() === keepId && !$(this).prop('disabled');
    }).first() : $();
    $sel.find('option').prop('selected', false).removeAttr('selected');
    if ($keep.length) {
      $keep.attr('selected', 'selected').prop('selected', true);
    } else {
      // nothing to keep: select nothing, the required check makes the user pick a source
      $sel.prop('selectedIndex', -1);
    }
    $sel.prop('disabled', false);
    refreshSelect($sel);
    if ($keep.length && $sel.data('selectpicker')) $sel.selectpicker('val', keepId);
    return $keep.length > 0;
  }

  // (Re)build every source dropdown from the ACL-filtered API
  function populateSourceSelects() {
    var $selects = $('select.imapsync-source-select');
    if (!$selects.length) return;
    $selects.each(function() {
      var $sel = $(this);
      if ($sel.val()) $sel.data('keep-id', String($sel.val()));
      $sel.data('saved-options', $sel.find('option:not(.bs-title-option)').clone());
      // disable while loading instead of a transient placeholder option
      $sel.prop('disabled', true);
      refreshSelect($sel);
    });
    $.get('/api/v1/get/syncjob_source/all').done(function(sources) {
      if (!Array.isArray(sources)) sources = [];
      sources.sort(function(a, b) { return String(a.name).localeCompare(String(b.name)); });
      $selects.each(function() {
        var $sel = $(this);
        var cur = String($sel.data('current-source-id') || '');
        var keepId = String($sel.data('keep-id') || cur);
        var opts = [];
        var selectable = 0;
        var listed = false;
        $.each(sources, function(_, src) {
          var id = String(src.id);
          var $opt = sourceOption(src.id, src.name, src.host || src.host1, src.port || src.port1, src.auth_type, src.oauth_flow);
          if (src.active != 1) {
            $opt.attr('data-subtext', $opt.attr('data-subtext') + ' · ' + L('status_inactive'));
            // the backend rejects inactive sources, only the assigned one stays selectable on the edit page
            if (id !== cur) $opt.prop('disabled', true);
          }
          if (!$opt.prop('disabled')) selectable++;
          if (id === cur) listed = true;
          opts.push($opt);
        });
        // edit page: the assigned source stays selectable even if the viewer cannot see it
        if (cur !== '' && !listed) {
          var $cur = sourceOption(cur, $sel.data('current-source-name'), $sel.data('current-source-host'),
            $sel.data('current-source-port'), $sel.data('current-source-auth-type'), $sel.data('current-source-oauth-flow'));
          $cur.text($cur.text() + ' ' + L('source_not_visible'));
          opts.unshift($cur);
          selectable++;
        }
        rebuildSourceSelect($sel, opts, keepId);
        setNoSourceState($sel, selectable === 0);
        $sel.trigger('change');
      });
    }).fail(function() {
      // keep what was there before (e.g. the assigned source on the edit page)
      $selects.each(function() {
        var $sel = $(this);
        rebuildSourceSelect($sel, $sel.data('saved-options') || $(), String($sel.data('keep-id') || $sel.data('current-source-id') || ''));
        $sel.trigger('change');
      });
      mailcow_alert_box(L('source_list_error'), 'danger');
    });
  }

  function sourceOption(id, name, host, port, authType, flow) {
    var sub = (host || '?') + ':' + (port || '?') + ' · ' + (authType || '');
    if (authType === 'XOAUTH2') sub += ' (' + (flow === 'authorization_code' ? L('source_flow_badge_user') : L('source_flow_badge_app')) + ')';
    return $('<option></option>').val(id).text(name || ('#' + id))
      .attr('data-subtext', sub)
      .attr('data-auth-type', authType || '')
      .attr('data-oauth-flow', flow || '');
  }

  // Toggle password / user1 / connect rows by the selected source's auth type
  function applySourceSelection($sel) {
    var $opt = $sel.find('option:selected');
    var $form = $sel.closest('form');
    var isOauth = ($opt.attr('data-auth-type') === 'XOAUTH2');
    var isAuthCode = isOauth && ($opt.attr('data-oauth-flow') === 'authorization_code');
    var isEdit = ($form.data('id') === 'editsyncjob');
    var $user1 = $form.find('input[name="user1"]');
    var prevSource = $sel.data('prev-source');
    var curSource = $sel.val() || '';
    // a token belongs to (source, mailbox, remote user): switching the source drops the connect result
    if (prevSource !== undefined && prevSource !== curSource && $user1.prop('readonly')) {
      $user1.prop('readonly', false).val(isEdit ? $user1.data('initial') || '' : '');
      setConnectStatus($form, '', '');
    }
    $sel.data('prev-source', curSource);
    $form.find('.password1-row').toggle(!isOauth);
    // edit: an empty password keeps the stored one
    $form.find('input[name="password1"]').prop('required', !isOauth && !isEdit);
    $form.find('.imapsync-oauth-connect-row').toggle(isAuthCode);
    $form.find('.imapsync-user1-row').toggle(!isAuthCode);
    $user1.prop('required', !isAuthCode && !isEdit);
  }

  // ===== OAuth connect (authorization_code) =====

  var oauthPending = null;

  function setConnectStatus($form, type, text, busy) {
    var $status = $form.find('.imapsync-oauth-connect-status');
    $status.attr('class', 'imapsync-oauth-connect-status ms-2' + (type ? ' text-' + type : '')).empty();
    if (busy) $status.append('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>');
    $status.append(document.createTextNode(text || ''));
  }

  function clearPending() {
    if (oauthPending && oauthPending.timer) clearInterval(oauthPending.timer);
    oauthPending = null;
  }

  // Local mailbox the token is stored for (C1)
  function connectMailbox($form) {
    var $mb = $form.find('[name="username"]');
    if ($mb.length) return $mb.val() || '';
    if ($form.data('mailbox')) return String($form.data('mailbox'));
    return (typeof mailcow_cc_username !== 'undefined') ? mailcow_cc_username : '';
  }

  function startConnect($form) {
    var sourceId = $form.find('select.imapsync-source-select').val();
    if (!sourceId) return;
    var mailbox = connectMailbox($form);
    if (!mailbox) {
      setConnectStatus($form, 'danger', L('oauth_select_mailbox'));
      return;
    }
    var url = '/syncjob-oauth?action=start&source_id=' + encodeURIComponent(sourceId) + '&mailbox=' + encodeURIComponent(mailbox);
    var popup = window.open(url, 'syncjob_oauth', 'width=600,height=700');
    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
      setConnectStatus($form, 'danger', L('oauth_popup_blocked'));
      return;
    }
    clearPending();
    var pending = { form: $form, sourceId: String(sourceId), mailbox: mailbox.toLowerCase() };
    oauthPending = pending;
    setConnectStatus($form, 'muted', L('oauth_waiting'), true);
    pending.timer = setInterval(function() {
      if (!popup.closed) return;
      clearInterval(pending.timer);
      pending.timer = null;
      // stays pending: a result message that arrives after the close still wins
      if (oauthPending === pending) setConnectStatus($form, 'muted', L('oauth_window_closed'));
    }, 500);
  }

  function onOauthMessage(ev) {
    if (ev.origin !== window.location.origin || !ev.data || ev.data.type !== 'syncjob_oauth') return;
    var p = oauthPending;
    if (!p) return;
    // only the row that started the flow is updated
    if (ev.data.ok && (String(ev.data.source_id) !== p.sourceId || String(ev.data.mailbox || '').toLowerCase() !== p.mailbox)) return;
    clearPending();
    if (ev.data.ok) {
      p.form.find('input[name="user1"]').val(ev.data.user1).prop('readonly', true);
      setConnectStatus(p.form, 'success', L('syncjob_oauth_connected') + ': ' + ev.data.user1);
    } else {
      var code = String(ev.data.error || '').replace(/[^a-z_]/g, '');
      setConnectStatus(p.form, 'danger', L('oauth_error_' + code) || L('oauth_error_unknown'));
    }
  }

  // ===== Custom params editor =====

  // Allowlist { name: true (value) | false (flag) | 'regex' (value) }, loaded once on demand
  var cpOptions = null;
  var cpLoading = null;
  function cpEnsureOptions(cb) {
    if (cpOptions !== null) { cb(); return; }
    if (!cpLoading) {
      cpLoading = $.get('/api/v1/get/syncjob_options').done(function(opts) {
        cpOptions = (opts && typeof opts === 'object' && !Array.isArray(opts)) ? opts : {};
      }).fail(function() {
        cpLoading = null;
        mailcow_alert_box(L('custom_params_options_error'), 'danger');
      });
    }
    cpLoading.done(function() { cb(); });
  }

  function cpApplyValueState($row) {
    var name = $row.find('.imapsync-cp-opt').val() || '';
    var isFlag = (name !== '') && !!cpOptions && cpOptions[name] === false;
    var $val = $row.find('.imapsync-cp-val');
    $val.prop('disabled', isFlag);
    if (isFlag) $val.val('');
    $val.prop('required', (name !== '') && !isFlag);
    $val.attr('placeholder', isFlag ? L('custom_param_no_value') : L('custom_param_value'));
  }

  var cpRowSeq = 0;
  function cpAddRow($editor, o, v) {
    var n = ++cpRowSeq;
    var $row = $(
      '<div class="input-group mb-2 imapsync-cp-row">' +
        '<select class="form-select imapsync-cp-opt" id="imapsync-cp-opt-' + n + '" style="flex:0 0 35%"></select>' +
        '<input type="text" class="form-control imapsync-cp-val" id="imapsync-cp-val-' + n + '">' +
        '<button type="button" class="btn btn-secondary imapsync-cp-remove"><i class="bi bi-trash" aria-hidden="true"></i></button>' +
      '</div>'
    );
    // aria-label: label elements inside .input-group break its first/last child styling
    $row.find('.imapsync-cp-opt').attr('aria-label', L('custom_param_option'));
    $row.find('.imapsync-cp-val').attr('aria-label', L('custom_param_value'));
    $row.find('.imapsync-cp-remove').attr({ 'title': L('custom_param_remove'), 'aria-label': L('custom_param_remove') });
    var $sel = $row.find('.imapsync-cp-opt');
    $sel.append($('<option></option>').attr('value', '').text('— ' + L('custom_param_option') + ' —'));
    Object.keys(cpOptions || {}).sort().forEach(function(name) {
      $sel.append($('<option></option>').attr('value', name).text('--' + name));
    });
    // keep a stored option that is no longer allowlisted visible (the backend rejects it on save)
    if (o && !(cpOptions && (o in cpOptions))) {
      $sel.append($('<option></option>').attr('value', o).text('--' + o));
    }
    $sel.val(o || '');
    $row.find('.imapsync-cp-val').val(v || '');
    $editor.find('.imapsync-cp-rows').append($row);
    cpApplyValueState($row);
  }

  function cpBuild($editor) {
    var $hidden = $editor.siblings('.imapsync-cp-value');
    $editor.find('.imapsync-cp-rows').empty();
    var pairs = [];
    try { pairs = JSON.parse($hidden.val() || '[]'); } catch (e) { pairs = []; }
    if (!Array.isArray(pairs)) pairs = [];
    pairs.forEach(function(p) {
      if (p && typeof p === 'object') cpAddRow($editor, p.o, p.v);
    });
  }

  function cpSerialize($editor) {
    var out = [];
    $editor.find('.imapsync-cp-row').each(function() {
      var o = $.trim($(this).find('.imapsync-cp-opt').val() || '').replace(/^-+/, '');
      if (o === '') return;
      out.push({ o: o, v: $(this).find('.imapsync-cp-val').val() || '' });
    });
    $editor.siblings('.imapsync-cp-value').val(JSON.stringify(out));
  }

  function cpInit($scope) {
    var $editors = $scope.find('.imapsync-cp-editor');
    if (!$editors.length) return;
    cpEnsureOptions(function() {
      $editors.each(function() { cpBuild($(this)); });
    });
  }

  // ===== Source editor (add modal + edit page) =====

  function sourceFormToggle($form) {
    var isOauth = $form.find('select.imapsync-source-auth-type').val() === 'XOAUTH2';
    var isAuthCode = $form.find('select.imapsync-source-oauth-flow').val() === 'authorization_code';
    var isCc = isOauth && !isAuthCode;
    $form.find('.imapsync-source-oauth-block').toggle(isOauth);
    $form.find('.imapsync-source-authcode-block').toggle(isOauth && isAuthCode);
    $form.find('.imapsync-source-cc-block').toggle(isCc);
    // client credentials sources are forced private by the backend
    $form.find('.imapsync-source-scope-block').toggle(!isCc);
    $form.find('.imapsync-source-scope-cc-hint').toggle(isCc);
  }

  function sourceScopeToggle($form) {
    var scope = $form.find('select.imapsync-source-scope').val();
    $form.find('.imapsync-source-domains-row').toggle(scope === 'domain');
    $form.find('.imapsync-source-users-row').toggle(scope === 'user');
    if (scope === 'domain') fillScopeSelect($form.find('select.imapsync-source-domains'), 'domain/all', 'domain_name');
    if (scope === 'user') fillScopeSelect($form.find('select.imapsync-source-users'), 'mailbox/all', 'username');
  }

  // Add the selectable domains/mailboxes to a scope multiselect (keeps selected options)
  function fillScopeSelect($sel, url, field) {
    if (!$sel.length || $sel.prop('disabled') || $sel.data('filled')) return;
    $sel.data('filled', true);
    $.get('/api/v1/get/' + url).done(function(rows) {
      var have = {};
      $sel.find('option').each(function() { have[$(this).val()] = true; });
      $.each(Array.isArray(rows) ? rows : [], function(_, r) {
        var v = r[field];
        if (!v || have[v]) return;
        $sel.append($('<option></option>').val(v).text(v));
      });
      // refresh duplicates preselected entries in the button label, re-init instead
      if (typeof $sel.selectpicker === 'function' && $sel.data('selectpicker')) {
        var keep = $sel.val();
        $sel.selectpicker('destroy');
        refreshSelect($sel);
        $sel.selectpicker('val', keep);
      } else {
        refreshSelect($sel);
      }
    }).fail(function() {
      $sel.data('filled', false);
      mailcow_alert_box(lang_danger.access_denied, 'danger');
    });
  }

  // Extra params must be a JSON object; checked before the form is posted
  function extraParamsValid($form) {
    var $f = $form.find('input[name="oauth_extra_params"]');
    if (!$f.length || $f.prop('disabled') || !$f.is(':visible')) return true;
    var v = $.trim($f.val() || '');
    if (v === '') { $f.removeClass('inputMissingAttr'); return true; }
    var ok = false;
    try {
      var o = JSON.parse(v);
      ok = (o !== null && typeof o === 'object' && !Array.isArray(o));
    } catch (e) { ok = false; }
    $f.toggleClass('inputMissingAttr', !ok);
    if (!ok) mailcow_alert_box(lang_danger.imapsync_source_oauth_extra_invalid, 'danger');
    return ok;
  }

  function copyField(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var done = function() { mailcow_alert_box(L('copied'), 'success'); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(el.value).then(done);
    } else {
      el.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
    }
  }

  // ===== Provider presets (add source modal) =====

  // Server and OAuth settings of well-known providers; fields a preset leaves out get the defaults of applyPreset
  var m365 = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/';
  var presets = {
    m365_app: {host: 'outlook.office365.com', auth_type: 'XOAUTH2', oauth_token_endpoint: m365 + 'token',
      oauth_scope: 'https://outlook.office365.com/.default'},
    m365_user: {host: 'outlook.office365.com', auth_type: 'XOAUTH2', oauth_flow: 'authorization_code',
      oauth_authorize_endpoint: m365 + 'authorize', oauth_token_endpoint: m365 + 'token',
      oauth_scope: 'https://outlook.office.com/IMAP.AccessAsUser.All offline_access openid email profile'},
    // Google issues a refresh token only for offline access, and again only after consent
    google_user: {host: 'imap.gmail.com', auth_type: 'XOAUTH2', oauth_flow: 'authorization_code',
      oauth_authorize_endpoint: 'https://accounts.google.com/o/oauth2/v2/auth', oauth_token_endpoint: 'https://oauth2.googleapis.com/token',
      oauth_scope: 'https://mail.google.com/ openid email', oauth_extra_params: '{"access_type":"offline","prompt":"consent select_account"}'},
    gmail: {host: 'imap.gmail.com'},
    gmx: {host: 'imap.gmx.net'},
    webde: {host: 'imap.web.de'},
    tonline: {host: 'secureimap.t-online.de'},
    icloud: {host: 'imap.mail.me.com'},
    yahoo: {host: 'imap.mail.yahoo.com'},
    ionos: {host: 'imap.ionos.de'},
    strato: {host: 'imap.strato.de'}
  };

  // The name follows the preset until it is changed by hand; client ID and secret are kept
  function applyPreset($link) {
    var $form = $link.closest('form');
    var preset = $.extend({port: 993, enc: 'SSL', auth_type: 'PLAIN', oauth_flow: 'client_credentials', oauth_authorize_endpoint: '',
      oauth_token_endpoint: '', oauth_userinfo_endpoint: '', oauth_scope: '', oauth_extra_params: ''}, presets[$link.data('preset')]);
    var $name = $form.find('input[name="name"]');
    var names = $form.find('.imapsync-source-preset').map(function() { return $.trim($(this).text()); }).get();
    if (!$name.val() || names.indexOf($name.val()) !== -1) $name.val($.trim($link.text()));
    // bootstrap-select 1.14 duplicates the button label on refresh after .val(), use its own setter
    $.each(preset, function(field, value) {
      var $f = $form.find('[name="' + field + '"]');
      if ($f.data('selectpicker')) {
        $f.selectpicker('val', value);
      } else {
        $f.val(value);
      }
    });
    var needsTenant = preset.oauth_token_endpoint.indexOf('{tenant}') !== -1;
    $form.find('.imapsync-source-tenant-row').toggle(needsTenant);
    applyTenant($form);
    sourceFormToggle($form);
    if (needsTenant) $form.find('.imapsync-source-tenant').trigger('focus');
  }

  // M365: the tenant (ID or domain) is part of the endpoint URLs
  function applyTenant($form) {
    var tenant = encodeURIComponent($.trim($form.find('.imapsync-source-tenant').val() || '')) || '{tenant}';
    $form.find('input[name="oauth_authorize_endpoint"], input[name="oauth_token_endpoint"]').val(function(_, url) {
      return url.replace(/^(https:\/\/login\.microsoftonline\.com\/)[^\/]*/, function(_, base) { return base + tenant; });
    });
  }

  // A visible endpoint must not keep the {tenant} placeholder; checked before the form is posted
  function tenantValid($form) {
    var missing = $form.find('input[name="oauth_authorize_endpoint"]:visible, input[name="oauth_token_endpoint"]:visible')
      .filter(function() { return this.value.indexOf('{tenant}') !== -1; }).length > 0;
    $form.find('.imapsync-source-tenant').toggleClass('inputMissingAttr', missing);
    if (missing) {
      $form.find('.imapsync-source-tenant-row').show();
      mailcow_alert_box(lang_danger.imapsync_source_tenant_missing, 'danger');
    }
    return !missing;
  }

  // ===== Wiring =====

  function bind() {
    injectStyle();

    // custom params editor
    $(document).on('change', '.imapsync-cp-opt', function() {
      cpApplyValueState($(this).closest('.imapsync-cp-row'));
      cpSerialize($(this).closest('.imapsync-cp-editor'));
    });
    $(document).on('input', '.imapsync-cp-val', function() {
      cpSerialize($(this).closest('.imapsync-cp-editor'));
    });
    $(document).on('click', '.imapsync-cp-add', function() {
      var $editor = $(this).closest('.imapsync-cp-editor');
      cpEnsureOptions(function() { cpAddRow($editor); });
    });
    $(document).on('click', '.imapsync-cp-remove', function() {
      var $editor = $(this).closest('.imapsync-cp-editor');
      $(this).closest('.imapsync-cp-row').remove();
      cpSerialize($editor);
    });

    // job form
    $(document).on('change', 'select.imapsync-source-select', function() {
      applySourceSelection($(this));
    });
    // a token is bound to the local mailbox: changing it drops the connect result
    $(document).on('change', 'form[data-id="add_syncjob"] select[name="username"]', function() {
      var $form = $(this).closest('form');
      var $user1 = $form.find('input[name="user1"]');
      if ($user1.prop('readonly')) {
        $user1.prop('readonly', false).val('');
        setConnectStatus($form, '', '');
      }
    });
    $(document).on('click', '.imapsync-oauth-connect-btn', function() {
      startConnect($(this).closest('form'));
    });
    window.addEventListener('message', onOauthMessage);
    $(document).on('click', '.imapsync-open-source-modal', function(e) {
      e.preventDefault();
      var $current = $(this).closest('.modal');
      var open = function() { $('#addImapsyncSourceModal').modal('show'); };
      if ($current.length) {
        $current.one('hidden.bs.modal', open).modal('hide');
      } else {
        open();
      }
    });
    // authorization_code: user1 comes from the provider sign-in, require it before posting
    $('[data-action="add_item"][data-id="add_syncjob"], [data-action="edit_selected"][data-id="editsyncjob"]').on('click', function(e) {
      var $form = $(this).closest('form');
      var $opt = $form.find('select.imapsync-source-select option:selected');
      if ($opt.attr('data-auth-type') === 'XOAUTH2' && $opt.attr('data-oauth-flow') === 'authorization_code' &&
          !$.trim($form.find('input[name="user1"]').val() || '')) {
        e.preventDefault();
        e.stopImmediatePropagation();
        setConnectStatus($form, 'danger', lang_danger.imapsync_source_oauth_connect_required);
        return false;
      }
    });

    // source editor
    $(document).on('change', 'select.imapsync-source-auth-type, select.imapsync-source-oauth-flow', function() {
      sourceFormToggle($(this).closest('form'));
    });
    $(document).on('change', 'select.imapsync-source-scope', function() {
      sourceScopeToggle($(this).closest('form'));
    });
    $('[data-action="add_item"][data-id="add_imapsync_source"], [data-action="edit_selected"][data-id="edit_imapsync_source"]').on('click', function(e) {
      var $form = $(this).closest('form');
      if (!extraParamsValid($form) || !tenantValid($form)) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }
    });
    $(document).on('click', '.imapsync-source-preset', function(e) {
      e.preventDefault();
      applyPreset($(this));
    });
    $(document).on('input', '.imapsync-source-tenant', function() {
      applyTenant($(this).closest('form'));
    });
    $(document).on('click', '.imapsync-copy-btn', function(e) {
      e.preventDefault();
      copyField($(this).data('copy-target'));
    });

    // modals
    $(document).on('shown.bs.modal', '#addImapsyncSourceModal', function() {
      var $form = $(this).find('form');
      sourceFormToggle($form);
      sourceScopeToggle($form);
    });
    $(document).on('shown.bs.modal', '#addSyncJobModalAdmin, #addSyncJobModal', function() {
      populateSourceSelects();
      cpInit($(this));
    });

    // edit page: forms are already rendered
    $('form[data-id="edit_imapsync_source"]').each(function() {
      sourceFormToggle($(this));
      sourceScopeToggle($(this));
    });
    $('form[data-id="editsyncjob"]').each(function() {
      var $form = $(this);
      $form.find('input[name="user1"]').data('initial', $form.find('input[name="user1"]').val());
      populateSourceSelects();
      cpInit($form);
    });
  }

  $(document).ready(bind);

  return {
    drawSourceTable: drawSourceTable,
    jobTableStateSave: jobTableStateSave,
    jobTableStateLoad: jobTableStateLoad,
    prepareJobRow: prepareJobRow,
    wrapCell: wrapCell,
    L: L
  };
})(jQuery);
