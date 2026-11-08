// Backup with restic. System > Configuration > Backup (admins): status, actions, settings, jobs and snapshots.
// E-Mail > Restore (admins, domain admins): snapshot, a tree of its domains and mailboxes (also deleted ones), the
// restore modal, the restores. Both pages share the job details modal. Texts from lang.backup. Loaded via $js_minifier.
var mailcowBackup = (function($) {
  'use strict';

  var POLL_MS = 5000;
  var COMPONENTS = ['mails', 'imap_acl', 'aliases', 'groups', 'sender_acl', 'mail_policies', 'retention_rules', 'filters',
    'sieve_activate', 'spam', 'bcc', 'syncjobs', 'app_passwords', 'tfa', 'sogo', 'settings', 'password'];
  // unchecked for existing targets, preselected when everything to restore was deleted
  var RISKY = ['tfa', 'app_passwords', 'retention_rules', 'sieve_activate'];
  // overwrite current values; for deleted targets SOGo is a normal item and settings/password are not sent
  var OVERWRITE = ['sogo', 'settings', 'password'];
  // existing targets: only the mails by default
  var EXISTING_DEFAULT = ['mails'];
  // items that need the mails, not in snapshots without them (RESTIC_BACKUP_MODE=config, as JobService::MAIL_COMPONENTS)
  var MAIL_COMPONENTS = ['mails', 'imap_acl', 'sogo', 'sieve_activate'];
  var BACKUP_MODES = ['all', 'data', 'config'];
  // domain admin ACL an item needs (as JobService::COMPONENT_ACL)
  var COMPONENT_ACL = {groups: 'mailbox_groups', mail_policies: 'mail_policies', retention_rules: 'retention', filters: 'filters',
    sieve_activate: 'filters', spam: 'spam_policy', bcc: 'bcc_maps', syncjobs: 'syncjobs', app_passwords: 'app_passwds'};
  // the restore of the dovecot container (mails, IMAP ACLs, Sieve) cannot be cancelled while it runs
  var DOVECOT_STEPS = ['mails', 'mails_running'];
  var SECRETS = ['password', 's3_secret_key', 'rest_password'];
  // value of a password field while its secret is stored: shown as dots, never sent
  var SECRET_MASK = '************';
  var JOB_TYPES = ['backup', 'check', 'prune', 'init'];
  var STATE_CLASS = {queued: 'bg-secondary', running: 'bg-info text-black', done: 'bg-success', failed: 'bg-danger', cancelled: 'bg-warning text-black'};
  var RESULT_COUNTS = ['added', 'skipped', 'errors'];
  // a scheduled backup is overdue this long after the longest gap of the schedule (26 hours for one backup a day)
  var STALE_MARGIN = 2 * 3600;
  // entries of the schedule (as SettingsService::MAX_SCHEDULE)
  var MAX_SCHEDULE = 24;
  // weekdays in the order of the form, 0 = Sunday
  var WEEKDAYS = [1, 2, 3, 4, 5, 6, 0];
  // errors of a job that could not open the repository
  var REPO_ERROR = /unable to open|wrong (repository )?password|does not exist|host key|connection refused|no such host|could not resolve|timed? ?out|permission denied|repository/i;

  // lang.backup of the page, '' if missing
  function L(key) {
    var src = (typeof lang !== 'undefined' && lang && lang.backup) ? lang.backup : {};
    return (src[key] !== undefined && src[key] !== null) ? src[key] : '';
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : String(s));
  }
  // replaces %s (and %1$s) one after the other, like vsprintf in the PHP alert box
  function fill(text) {
    var args = Array.prototype.slice.call(arguments, 1), next = 0;
    return String(text).replace(/%(?:(\d+)\$)?s/g, function(match, pos) {
      var value = pos ? args[pos - 1] : args[next++];
      return (value === undefined || value === null) ? '' : String(value);
    });
  }
  function pad(n) {
    return (n < 10 ? '0' : '') + n;
  }
  function now() {
    return Math.floor(Date.now() / 1000);
  }
  function pageLang() {
    return document.documentElement.lang || undefined;
  }
  // the time zone of mailcow (TZ in mailcow.conf), so times match the schedule and the "Restored <time>" folders
  function timeZone() {
    return (typeof backup_timezone !== 'undefined' && backup_timezone) ? backup_timezone : undefined;
  }
  function formatWith(ts, options) {
    options = $.extend({hourCycle: 'h23'}, options);
    try {
      return new Intl.DateTimeFormat(pageLang(), $.extend({timeZone: timeZone()}, options)).format(new Date(ts * 1000));
    } catch (e) {
      return new Date(ts * 1000).toLocaleString(undefined, options);
    }
  }
  // date and time in the language of the page, like mailcow's DATETIME_FORMAT without seconds
  function formatTime(ts) {
    return ts ? formatWith(ts, {year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'}) : '';
  }
  // a period: the end without the date when it is the same day
  function formatPeriod(from, to) {
    var day = {year: 'numeric', month: '2-digit', day: '2-digit'};
    if (!to) return formatTime(from);
    return formatTime(from) + ' – ' + (formatWith(from, day) === formatWith(to, day) ? formatWith(to, {hour: '2-digit', minute: '2-digit'}) : formatTime(to));
  }
  // without the year: status line
  function formatShort(ts) {
    return ts ? formatWith(ts, {month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'}) : '';
  }
  // "vor 9 Std." / "9 hr. ago"
  function relative(ts) {
    var diff = ts - now(), abs = Math.abs(diff), unit = 'second', value = diff;
    if (abs >= 86400) { unit = 'day'; value = Math.round(diff / 86400); }
    else if (abs >= 3600) { unit = 'hour'; value = Math.round(diff / 3600); }
    else if (abs >= 60) { unit = 'minute'; value = Math.round(diff / 60); }
    try {
      return new Intl.RelativeTimeFormat(pageLang(), {numeric: 'auto', style: 'short'}).format(value, unit);
    } catch (e) {
      return abs < 120 ? fill(L('seconds_ago'), abs) : fill(L('minutes_ago'), Math.round(abs / 60));
    }
  }
  // name of the folder the dovecot runner restores into: "Restored YYYY-MM-DD HH:MM", snapshot time in the time zone of mailcow
  function folderName(ts) {
    var d = new Date(ts * 1000), parts = {};
    try {
      new Intl.DateTimeFormat('en-GB', {timeZone: timeZone(), hourCycle: 'h23',
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'}).formatToParts(d).forEach(function(part) {
        parts[part.type] = part.value;
      });
      return 'Restored ' + parts.year + '-' + parts.month + '-' + parts.day + ' ' + parts.hour + ':' + parts.minute;
    } catch (e) {
      return 'Restored ' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
  }
  function formatSize(bytes) {
    if (bytes === null || bytes === undefined || bytes === '' || isNaN(bytes)) return '';
    bytes = Number(bytes);
    if (Math.abs(bytes) < 1024) return bytes + ' B';
    var units = ['KiB', 'MiB', 'GiB', 'TiB', 'PiB'], i = -1;
    do {
      bytes /= 1024;
      i++;
    } while (Math.abs(bytes) >= 1024 && i < units.length - 1);
    try {
      return bytes.toLocaleString(pageLang(), {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' ' + units[i];
    } catch (e) {
      return bytes.toFixed(1) + ' ' + units[i];
    }
  }
  function formatDuration(seconds) {
    if (seconds === null || seconds === undefined || seconds < 0) return '';
    seconds = Math.round(seconds);
    var h = Math.floor(seconds / 3600), m = Math.floor(seconds % 3600 / 60), s = seconds % 60;
    return (h ? h + ' h ' : '') + (h || m ? m + ' min ' : '') + (h ? '' : s + ' s');
  }
  function firstLine(text, max) {
    text = String(text || '').split('\n')[0];
    return text.length > max ? text.substr(0, max) + '…' : text;
  }
  function urlHost(url) {
    try {
      return new URL(url).host;
    } catch (e) {
      return url || '';
    }
  }

  function typeLabel(type) {
    return L('type_' + type) || type;
  }
  function componentLabel(component) {
    return L('component_' + component) || component;
  }
  function isActive(job) {
    return job.state === 'queued' || job.state === 'running';
  }
  function isCancellable(job) {
    return job.state === 'queued' || (job.state === 'running' && !(job.type === 'restore' && DOVECOT_STEPS.indexOf(job.step) !== -1));
  }
  function stepText(job) {
    return job.step ? (L('step_' + job.step) || job.step) : '';
  }
  function stateBadge(job) {
    var html = '<span class="badge ' + (STATE_CLASS[job.state] || 'bg-secondary') + '">' + esc(L('state_' + job.state) || job.state) + '</span>';
    if (job.state === 'running' && job.step) html += ' <span class="small text-muted">' + esc(stepText(job)) + '</span>';
    return html;
  }
  // domain admins see "Administrator" for restores an admin requested
  function requestedBy(job) {
    if (job.requested_by === 'schedule') return L('requested_schedule');
    if (typeof role !== 'undefined' && role === 'domainadmin' && (job.params || {}).requested_role === 'admin') return L('requested_admin');
    return job.requested_by;
  }
  function jobDuration(job) {
    return job.started ? formatDuration((job.finished || now()) - job.started) : '';
  }
  // label of a setting in messages: the field name of the form
  function settingLabel(name) {
    if (/^keep_/.test(name)) return L('keep') + ': ' + L(name);
    return L(name) || name;
  }

  // json_api.php answers with all messages of the session since the last page load: only the new ones are shown
  var seenMessages = 0;
  function messageText(message) {
    var msg = $.isArray(message.msg) ? message.msg.slice() : [message.msg];
    var key = msg.shift();
    var texts = (message.type === 'success') ? lang_success : lang_danger;
    var text = (texts && texts[key] !== undefined) ? texts[key] : String(key);
    // singular and plural of the number of mailboxes
    if (key === 'backup_restore_added' && L('restore_added_one')) text = (msg[1] == 1) ? L('restore_added_one') : L('restore_added_many');
    msg = $.map(msg, function(param) {
      if (key === 'backup_job_added' || key === 'backup_job_active') return L('type_' + param) || param;
      if (key === 'backup_component_denied' || key === 'backup_components_invalid' || key === 'backup_component_requires') return param ? componentLabel(param) : param;
      if (key === 'backup_setting_invalid') return settingLabel(param);
      return param;
    });
    return fill.apply(null, [text].concat(msg));
  }
  function showMessages(res) {
    var list = $.isArray(res) ? res : (res ? [res] : []);
    var fresh = (list.length > seenMessages) ? list.slice(seenMessages) : list;
    seenMessages = list.length;
    var ok = fresh.length > 0;
    $.each(fresh, function(i, message) {
      if (!message || typeof message !== 'object') return;
      var type = (message.type === 'success' || message.type === 'warning') ? message.type : 'danger';
      if (type !== 'success') ok = false;
      mailcow_alert_box(esc(messageText(message)), type);
    });
    return {ok: ok, messages: fresh};
  }
  // POST with a JSON body (the shape json_api.php expects for add, edit and delete); done(ok, messages)
  function post(path, body, done, $button) {
    if ($button) $button.prop('disabled', true);
    $.ajax({
      type: 'POST',
      url: '/api/v1/' + path,
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify(body),
      success: function(res) {
        var result = showMessages(res);
        if (done) done(result.ok, result.messages);
      },
      error: function() {
        mailcow_alert_box(esc(L('load_error')), 'danger');
        if (done) done(false, []);
      },
      complete: function() {
        if ($button) $button.prop('disabled', false);
      }
    });
  }
  function getJson(path, done) {
    return $.ajax({type: 'GET', url: '/api/v1/get/' + path, dataType: 'json', cache: false, success: done});
  }
  // a failed list load is reported once per list (the lists reload while jobs run)
  var reported = {};
  function tableError(id) {
    return function(xhr, status) {
      // a reload cancels the request before it
      if (status === 'abort' || (xhr && xhr.readyState === 0)) return;
      if (!reported[id]) mailcow_alert_box(esc(L('load_error')), 'danger');
      reported[id] = true;
    };
  }
  function reloadTable(selector) {
    if ($.fn.DataTable.isDataTable(selector)) $(selector).DataTable().ajax.reload(null, false);
  }
  // calls back each time the element becomes visible (tab or card opened)
  function onVisible(selector, callback) {
    var element = document.querySelector(selector);
    if (element === null || typeof IntersectionObserver === 'undefined') return;
    new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.intersectionRatio > 0) callback(element);
      });
    }).observe(element);
  }
  // small lists: table and pages only, 10 rows
  function tableOptions(options, empty) {
    return $.extend({
      responsive: true,
      processing: false,
      serverSide: false,
      stateSave: false,
      pageLength: 10,
      dom: "tr<'d-flex justify-content-end mt-2'p>",
      language: empty ? $.extend({}, lang_datatables, {emptyTable: empty}) : lang_datatables
    }, options);
  }
  // mailcow's empty first column: the responsive control (+) does not cover a value
  function placeholderColumn() {
    return {title: '', data: null, defaultContent: '', orderable: false, searchable: false, responsivePriority: 1};
  }
  function actionColumn() {
    return {title: '', data: 'action', orderable: false, searchable: false, defaultContent: '', className: 'dt-sm-head-hidden dt-data-w100 dtr-col-md dt-text-right', responsivePriority: 2};
  }
  // a DataTable with new data (local data, not ajax)
  function setTable(selector, options, data) {
    if ($.fn.DataTable.isDataTable(selector)) {
      $(selector).DataTable().clear().rows.add(data).draw(false);
    } else {
      $(selector).DataTable($.extend({data: data}, options));
    }
    $(selector).DataTable().columns.adjust().responsive.recalc();
  }
  // DataTables: the formatted value for display, the raw one for sorting
  function renderTime(data, type) {
    return (type === 'display' || type === 'filter') ? esc(formatTime(data)) : data;
  }
  function renderSize(data, type) {
    return (type === 'display' || type === 'filter') ? esc(formatSize(data)) : data;
  }

  // ---- restore jobs: what, where, result ----

  function restoreMailboxes(job) {
    var params = job.params || {};
    return $.isArray(params.mailboxes) ? params.mailboxes : [];
  }
  // what a restore writes into; short: a single mailbox without the word "Mailbox" (list column)
  function restoreTarget(job, short) {
    var params = job.params || {}, mailboxes = restoreMailboxes(job);
    if (params.scope === 'domain') return fill(L('restore_target_domain'), params.domain || job.domain);
    if (!mailboxes.length) return job.domain || '';
    if (mailboxes.length === 1) return short ? mailboxes[0] : fill(L('restore_target_mailbox'), mailboxes[0]);
    return fill(L('restore_target_mailboxes'), mailboxes.length, mailboxes.slice(0, 3).join(', ') + (mailboxes.length > 3 ? ', …' : ''));
  }
  function restoreSnapshot(job) {
    var params = job.params || {}, result = job.result || {}, snapshot = result.snapshot || {};
    var id = String(snapshot.short_id || params.snapshot || '').substr(0, 8);
    var time = params.snapshot_time ? formatTime(params.snapshot_time) : (snapshot.time || '');
    return time ? time + ' · ' + id : id;
  }
  // per mailbox: {component: {added, skipped, errors, ...}, error: "..."}
  function restoreResults(job) {
    var result = job.result || {};
    return (result.mailboxes && typeof result.mailboxes === 'object') ? result.mailboxes : {};
  }
  // results of the domain level: the domain itself (a deleted domain created from the backup) and its aliases
  // (scope domain), each {added, skipped, errors, ...}
  function restoreDomainResults(job) {
    var result = job.result || {};
    return $.grep([['domain', result.domain], ['aliases', result.aliases]], function(part) {
      return part[1] && typeof part[1] === 'object' && !$.isArray(part[1]);
    });
  }
  function restoreTotals(job) {
    var totals = {added: 0, skipped: 0, errors: 0, any: false};
    $.each(restoreDomainResults(job), function(i, part) {
      totals.any = true;
      totals.added += parseInt(part[1].added, 10) || 0;
      totals.skipped += parseInt(part[1].skipped, 10) || 0;
      totals.errors += $.isArray(part[1].errors) ? part[1].errors.length : 0;
    });
    $.each(restoreResults(job), function(user, entry) {
      if (!entry || typeof entry !== 'object') return;
      totals.any = true;
      if (entry.error) totals.errors++;
      $.each(entry, function(key, value) {
        if (!value || typeof value !== 'object' || $.isArray(value)) return;
        totals.added += parseInt(value.added, 10) || 0;
        totals.skipped += parseInt(value.skipped, 10) || 0;
        totals.errors += $.isArray(value.errors) ? value.errors.length : 0;
      });
    });
    return totals;
  }
  function restoreSummary(job) {
    var totals = restoreTotals(job), html = '';
    if (totals.any) html = esc(fill(L('result_summary'), totals.added, totals.skipped));
    else if (!job.error) html = '<span class="text-muted">' + esc(L(isActive(job) ? 'result_none' : 'result_empty')) + '</span>';
    if (totals.errors) html += ' <span class="text-danger">' + esc(fill(L('result_errors'), totals.errors)) + '</span>';
    return html;
  }
  function jobComponents(job) {
    return (job.params && $.isArray(job.params.components)) ? job.params.components : [];
  }
  function componentsText(job) {
    return $.map(jobComponents(job), function(c) { return componentLabel(c); }).join(', ');
  }
  // the items the requester could choose: all for admins, those of his ACL (stored with the job) for domain admins
  function choosable(job) {
    var params = job.params || {}, acl = params.acl;
    return $.grep(COMPONENTS, function(c) {
      return params.requested_role !== 'domainadmin' || !acl || !COMPONENT_ACL[c] || acl[COMPONENT_ACL[c]] == 1;
    });
  }
  // "all items", or one or two labels and "+ n more"
  function componentsShort(job) {
    var components = jobComponents(job);
    if (choosable(job).every(function(c) { return components.indexOf(c) !== -1; })) return L('all_items');
    if (components.length <= 2) return $.map(components, componentLabel).join(', ');
    return componentLabel(components[0]) + ' ' + fill(L('more_items'), components.length - 1);
  }
  // list cell and details: the short form, the full list as tooltip
  function componentsCell(job, cls) {
    if (!jobComponents(job).length) return '';
    return '<span class="' + (cls === undefined ? 'small text-muted' : cls) + '" title="' + esc(componentsText(job)) + '">' + esc(componentsShort(job)) + '</span>';
  }
  function extraValue(value) {
    if ($.isArray(value)) return value.join(', ');
    if (value && typeof value === 'object') {
      return $.map(value, function(v, k) { return k + ': ' + (typeof v === 'object' ? JSON.stringify(v) : v); }).join(', ');
    }
    return String(value);
  }
  function errorList(errors, cls) {
    if (!errors || !errors.length) return '';
    return '<ul class="mb-0 ps-3 small text-break ' + (cls === undefined ? 'text-danger' : cls) + '">' +
      $.map(errors, function(error) { return '<li>' + esc(error) + '</li>'; }).join('') + '</ul>';
  }
  // a further value of an item result, shown after its name: translated, without the empty ones and zeros
  function resultExtra(key, value) {
    // created: the mailbox row counts it as added
    if (key === 'created' || value === null || value === '' || value === 0 || value === '0' || ($.isArray(value) && !value.length)) return null;
    if (key === 'note') return esc(L('result_note_' + value) || String(value));
    return esc((L('result_' + key) || key) + ': ' + extraValue(value));
  }
  // the item results of a mailbox in their order: the creation of a deleted mailbox first, then the items
  function entryParts(entry) {
    var keys = $.grep(['mailbox'].concat(COMPONENTS), function(c) { return entry[c] !== undefined; });
    $.each(entry, function(key) {
      if (key !== 'error' && keys.indexOf(key) === -1) keys.push(key);
    });
    return $.map(keys, function(key) { return [[key, entry[key]]]; });
  }
  function partCounts(parts) {
    var counts = {added: 0, skipped: 0, errors: 0};
    $.each(parts, function(i, part) {
      var value = part[1];
      if (!value || typeof value !== 'object' || $.isArray(value)) return;
      counts.added += parseInt(value.added, 10) || 0;
      counts.skipped += parseInt(value.skipped, 10) || 0;
      counts.errors += $.isArray(value.errors) ? value.errors.length : 0;
    });
    return counts;
  }
  // the items of an entry: a row for each with something to tell, its errors right below it; the others in one line
  function resultBody(parts) {
    var rows = '', unchanged = [];
    $.each(parts, function(i, part) {
      var key = part[0], value = part[1];
      if (!value || typeof value !== 'object' || $.isArray(value)) {
        rows += '<tr><td>' + esc(L('result_' + key) || key) + '</td><td colspan="2" class="text-break">' + esc(extraValue(value)) + '</td></tr>';
        return;
      }
      var errors = $.isArray(value.errors) ? value.errors : [];
      var extras = $.map(value, function(v, k) { return RESULT_COUNTS.indexOf(k) !== -1 ? null : resultExtra(k, v); });
      if (!(parseInt(value.added, 10) || 0) && !(parseInt(value.skipped, 10) || 0) && !errors.length && !extras.length) {
        unchanged.push(componentLabel(key));
        return;
      }
      rows += '<tr><td class="text-break">' + esc(componentLabel(key)) + (extras.length ? '<span class="text-muted"> · ' + extras.join(' · ') + '</span>' : '') + '</td><td class="num">' + esc(value.added === undefined ? '' : value.added) + '</td>' +
        '<td class="num">' + esc(value.skipped === undefined ? '' : value.skipped) + '</td></tr>';
      $.each(errors, function(j, text) {
        rows += '<tr><td colspan="3" class="text-danger text-break ps-3"><i class="bi bi-arrow-return-right me-1"></i>' + esc(text) + '</td></tr>';
      });
    });
    var html = '';
    if (rows) {
      html += '<table class="table table-sm table-borderless mb-0"><thead><tr class="text-muted"><th class="fw-normal">' + esc(L('component')) + '</th>' +
        '<th class="fw-normal num">' + esc(L('added')) + '</th><th class="fw-normal num">' + esc(L('skipped')) + '</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }
    if (unchanged.length) html += '<div class="text-muted mt-1">' + esc(fill(L('result_unchanged'), unchanged.join(', '))) + '</div>';
    return html;
  }
  // one entry to open: state, name and counts in its line; entries with errors come open
  function resultEntry(entry) {
    var counts = partCounts(entry.parts), line, icon;
    if (entry.missing) {
      line = '<span class="text-muted">' + esc(L(entry.missing)) + '</span>';
      icon = 'bi-hourglass-split text-muted';
    } else if (entry.failed) {
      line = '<span class="text-danger text-break">' + esc(entry.error || fill(L('result_errors'), counts.errors)) + '</span>';
      icon = 'bi-exclamation-triangle-fill text-danger';
    } else {
      line = '<span class="text-muted">' + esc(fill(L('result_summary'), counts.added, counts.skipped)) + '</span>';
      icon = 'bi-check-circle-fill text-success';
    }
    return '<details' + (entry.failed ? ' open' : '') + '><summary class="d-flex align-items-center gap-2 px-2 py-1">' +
      '<i class="bi bi-chevron-right small"></i><i class="bi ' + icon + '"></i><span class="fw-semibold text-break">' + esc(entry.title) + '</span>' +
      '<span class="ms-auto small text-end">' + line + '</span></summary>' +
      (entry.missing ? '' : '<div class="small px-2 pb-2 backup-result-body">' + resultBody(entry.parts) + '</div>') + '</details>';
  }
  function restoreResultList(job) {
    if (!restoreTotals(job).any) return '';
    var results = restoreResults(job), users = restoreMailboxes(job).slice(), entries = [], domainParts = restoreDomainResults(job);
    $.each(results, function(user) {
      if (users.indexOf(user) === -1) users.push(user);
    });
    if (domainParts.length) {
      entries.push({title: fill(L('restore_target_domain_short'), (job.params || {}).domain || job.domain), parts: domainParts, error: ''});
    }
    $.each(users, function(i, user) {
      var entry = results[user], known = !!entry && typeof entry === 'object';
      entries.push({title: user, parts: known ? entryParts(entry) : [], error: known ? (entry.error || '') : '',
        missing: known ? '' : (isActive(job) ? 'result_none' : 'result_empty')});
    });
    var failed = 0;
    $.each(entries, function(i, entry) {
      entry.failed = !entry.missing && (!!entry.error || partCounts(entry.parts).errors > 0);
      entry.order = (entry.failed ? 0 : 1) * 100000 + i;
      if (entry.failed && entry.title.indexOf('@') !== -1) failed++;
    });
    // those with errors first
    entries.sort(function(a, b) { return a.order - b.order; });
    var totals = restoreTotals(job);
    var overview = [fill(L('result_mailboxes'), users.length)].concat(failed ? [fill(L('result_failed'), failed)] : [])
      .concat([fill(L('result_summary'), totals.added, totals.skipped)]);
    return '<h5 class="mt-3 fs-6 fw-semibold">' + esc(L('result')) + '</h5>' +
      '<div class="small text-muted mb-2">' + esc(overview.join(' · ')) + '</div>' +
      '<div class="rounded mb-3 backup-result">' + $.map(entries, resultEntry).join('') + '</div>';
  }

  // ---- other jobs ----

  function jobSummary(job) {
    if (job.type === 'restore') return restoreSummary(job);
    var result = job.result || {}, parts = [];
    if (job.type === 'backup' && result.summary) {
      parts.push(fill(L('summary_backup'), String(result.snapshot || '').substr(0, 8), formatSize(result.summary.data_added)));
    }
    if (job.type === 'check' && result.subset) parts.push(fill(L('summary_check'), result.subset));
    if (job.type === 'init' && job.state === 'done') parts.push(result.created ? L('summary_created') : L('connection_ok'));
    if ((job.type === 'prune' || job.type === 'init') && result.snapshots !== undefined) parts.push(fill(L('summary_snapshots'), result.snapshots));
    var html = esc(parts.join(' · '));
    if ($.isArray(result.warnings) && result.warnings.length) {
      html += '<div class="small"><i class="bi bi-exclamation-triangle-fill text-warning"></i> ' + esc(L('warnings') + ': ' + result.warnings.length) + '</div>';
    }
    return html;
  }
  function errorCell(job) {
    return job.error ? '<div class="small text-danger" title="' + esc(job.error) + '">' + esc(firstLine(job.error, 160)) + '</div>' : '';
  }
  function detailsButton(job) {
    return '<a href="#" class="btn btn-xs btn-xs-lg btn-secondary backup-job-details" data-job="' + esc(job.id) + '"><i class="bi bi-card-text"></i> ' + esc(L('details')) + '</a>';
  }
  function cancelButton(job) {
    return '<a href="#" class="btn btn-xs btn-xs-lg btn-danger backup-job-cancel-row" data-job="' + esc(job.id) + '" data-state="' + esc(job.state) + '"><i class="bi bi-x-circle"></i> ' + esc(L('cancel')) + '</a>';
  }
  function jobActions(job) {
    return '<div class="btn-group">' + detailsButton(job) + (isCancellable(job) ? cancelButton(job) : '') + '</div>';
  }

  // ---- job details modal ----

  var jobId = null;
  var jobTimer = null;
  function dlRow(label, html) {
    return '<dt class="col-sm-3">' + esc(label) + '</dt><dd class="col-sm-9 text-break">' + html + '</dd>';
  }
  function jobDetails(job) {
    var params = job.params || {}, html = '<dl class="row mb-3">';
    html += dlRow(L('state'), stateBadge(job) + (job.requested_by ? ' <span class="small text-muted ms-1">' + esc(fill(L('requested_by_short'), requestedBy(job))) + '</span>' : ''));
    html += dlRow(L('time'), esc(job.started ? formatPeriod(job.started, job.finished) + ' (' + jobDuration(job) + ')' : formatTime(job.created)));
    if (job.type === 'restore') {
      html += dlRow(L('snapshot'), esc(restoreSnapshot(job)));
      html += dlRow(L('scope'), esc(restoreTarget(job)));
      html += dlRow(L('components'), componentsCell(job, ''));
      if ($.isArray(params.components) && params.components.indexOf('mails') !== -1) {
        html += dlRow(L('mail_target'), esc(params.mail_target === 'original' ? L('mail_target_original_short')
          : (params.snapshot_time ? fill(L('mail_target_folder_named'), folderName(params.snapshot_time)) : L('mail_target_folder_short'))));
      }
      var created = [];
      if (params.create_domain == 1) created.push(fill(L('restore_target_domain_short'), params.domain || job.domain));
      if ($.isArray(params.create_mailboxes) && params.create_mailboxes.length) created.push(params.create_mailboxes.join(', '));
      if (created.length) html += dlRow(L('created_from_backup'), esc(created.join(', ')));
    } else {
      var summary = jobSummary(job);
      if (summary) html += dlRow(L('result'), summary);
    }
    html += '</dl>';
    if (job.error) {
      html += '<div class="alert alert-danger"><b>' + esc(L('error')) + '</b><pre class="mb-0 mt-1 p-0 small text-wrap text-break text-reset bg-transparent border-0">' + esc(job.error) + '</pre></div>';
    }
    var result = job.result || {};
    if (job.type === 'restore') {
      html += restoreResultList(job);
    } else {
      if ($.isArray(result.warnings) && result.warnings.length) {
        html += '<div class="alert alert-warning"><b>' + esc(L('warnings')) + '</b>' + errorList(result.warnings, '') + '</div>';
      }
      if (job.result) {
        html += '<details class="mb-3"><summary>' + esc(L('result')) + '</summary><pre class="small rounded p-2 mt-2 text-wrap text-break backup-pre">' +
          esc(JSON.stringify(job.result, null, 2)) + '</pre></details>';
      }
    }
    html += '<h5 class="mt-3 fs-6 fw-semibold">' + esc(L('log')) + '</h5>' +
      '<pre class="small rounded p-2 mb-0 backup-pre backup-job-log" style="max-height:24rem;overflow:auto;white-space:pre-wrap;word-break:break-word">' +
      (job.log ? esc(job.log) : '<span class="text-muted">' + esc(L('log_empty')) + '</span>') + '</pre>';
    return html;
  }
  function loadJob() {
    clearTimeout(jobTimer);
    var id = jobId, $modal = $('#backupJobModal');
    if (id === null) return;
    getJson('backup-job/' + encodeURIComponent(id), function(job) {
      if (id !== jobId) return;
      if (!job || !job.id) {
        $modal.find('.backup-job-body').html('<p class="text-danger">' + esc(L('load_error')) + '</p>');
        return;
      }
      var $log = $modal.find('.backup-job-log'), atEnd = !$log.length || ($log[0].scrollHeight - $log.scrollTop() - $log.outerHeight() < 20);
      $modal.find('.modal-title').text(fill(L('job'), job.id) + ' · ' + typeLabel(job.type));
      $modal.find('.backup-job-body').html(jobDetails(job));
      $modal.find('.backup-job-cancel').toggleClass('d-none', !isCancellable(job)).data('state', job.state);
      $modal.find('.backup-job-refresh').toggleClass('d-none', !isActive(job));
      if (atEnd) {
        $log = $modal.find('.backup-job-log');
        $log.scrollTop($log[0].scrollHeight);
      }
      if (isActive(job)) jobTimer = setTimeout(loadJob, POLL_MS);
    }).fail(function() {
      $modal.find('.backup-job-body').html('<p class="text-danger">' + esc(L('load_error')) + '</p>');
    });
  }
  function showJob(id) {
    jobId = parseInt(id, 10);
    var $modal = $('#backupJobModal');
    $modal.find('.modal-title').text(fill(L('job'), jobId));
    $modal.find('.backup-job-body').html('<p class="text-muted">' + esc(lang_footer.loading) + '</p>');
    $modal.find('.backup-job-cancel, .backup-job-refresh').addClass('d-none');
    $modal.modal('show');
    loadJob();
  }
  function cancelJob(id, state, $button) {
    if (state === 'running' && !confirm(L('cancel_running_confirm'))) return;
    post('delete/backup-job', [parseInt(id, 10)], function() {
      refreshAfterChange();
      if (jobId !== null) loadJob();
    }, $button);
  }
  // after a job was queued or cancelled (on the admin page the status reloads the job list)
  function refreshAfterChange() {
    reloadTable('#backup_restores_table');
    if ($('#backup_status').length) loadStatus();
  }

  // ---- System > Configuration > Backup: status ----

  var statusTimer = null;
  var lastActive = null;
  var lastStatus = null;
  var settingsCache = null;
  function alertLine(type, icon, html, buttons) {
    return '<div class="alert alert-' + type + ' py-2 px-3 mb-2 small d-flex flex-wrap align-items-center gap-2">' +
      '<span class="flex-grow-1">' + icon + html + '</span>' + (buttons ? '<span class="btn-group">' + buttons + '</span>' : '') + '</div>';
  }
  // the target in one line: "SFTP backup.example.org"
  function targetText(target) {
    var s = settingsCache || {};
    switch (target) {
      case 's3': return 'S3 ' + [s.s3_bucket, urlHost(s.s3_endpoint)].filter(Boolean).join(' @ ');
      case 'sftp': return 'SFTP ' + (s.sftp_host || '');
      case 'rest': return 'REST ' + urlHost(s.rest_url);
      case 'local': return L('target_local') + (s.local_path ? ': ' + s.local_path : '');
    }
    return '';
  }
  function renderStatus(s) {
    var jobs = s.jobs || {}, active = s.active || [], alerts = '', t = now();
    lastStatus = s;
    // the newest finished job of the repository: when it could not open the repository, nothing is green
    var latest = null;
    $.each(JOB_TYPES, function(i, type) {
      var job = jobs[type];
      if (job && (job.state === 'done' || job.state === 'failed') && (!latest || (job.finished || 0) > (latest.finished || 0))) latest = job;
    });
    var repoFailed = !!(latest && latest.state === 'failed' && REPO_ERROR.test(latest.error || ''));
    var failed = $.grep(JOB_TYPES, function(type) { return jobs[type] && jobs[type].state === 'failed'; });
    var stale = !!(s.enabled && s.last_success && t - s.last_success > (s.schedule_gap || 86400) + STALE_MARGIN);
    var healthy = s.runner_ok && s.configured && s.initialized && s.last_success && !repoFailed && !failed.length && !stale && !(s.secrets_error && s.secrets_error !== '0') &&
      !(s.mode && BACKUP_MODES.indexOf(s.mode) === -1);
    var icon = healthy ? '<i class="bi bi-check-circle-fill text-success"></i> ' : (s.configured ? '<i class="bi bi-exclamation-triangle-fill text-warning"></i> '
      : '<i class="bi bi-dash-circle text-muted"></i> ');
    var line = s.last_success ? fill(L('status_last'), formatShort(s.last_success), relative(s.last_success)) : L('status_never');
    line += ' · ' + (s.next_run ? fill(L('status_next'), formatShort(s.next_run)) : L('status_schedule_off'));
    var facts = [];
    if (s.repo_size !== null && s.repo_size !== undefined) facts.push(formatSize(s.repo_size));
    if (s.snapshots) facts.push(fill(L(s.snapshots == 1 ? 'status_snapshot' : 'status_snapshots'), s.snapshots));
    if (s.configured && s.target) facts.push(targetText(s.target));
    if (s.mode === 'data' || s.mode === 'config') facts.push(L('mode_' + s.mode));
    $('#backup_status .backup-status-summary').html('<div class="fw-semibold">' + icon + esc(line) + '</div>' +
      (facts.length ? '<div class="small text-muted">' + esc(facts.join(' · ')) + '</div>' : ''));

    if (!s.configured) {
      alerts += '<div class="alert alert-info py-2 px-3 mb-2 small"><b>' + esc(L('first_run')) + '</b><ol class="mb-0 ps-3">' +
        $.map([1, 2, 3, 4, 5], function(n) { return '<li>' + esc(L('first_run_' + n)) + '</li>'; }).join('') + '</ol></div>';
    }
    if (!s.runner_ok) {
      alerts += alertLine('danger', '<i class="bi bi-x-circle-fill"></i> ', esc(s.runner_seen ? fill(L('runner_missing'), formatTime(s.runner_seen)) : L('runner_never')));
    }
    var secretsError = !!(s.secrets_error && s.secrets_error !== '0');
    if (secretsError) alerts += alertLine('danger', '<i class="bi bi-key-fill"></i> ', esc(L('secrets_error')));
    if (s.mode && BACKUP_MODES.indexOf(s.mode) === -1) alerts += alertLine('danger', '<i class="bi bi-x-circle-fill"></i> ', esc(fill(L('mode_invalid'), s.mode)));
    if (s.configured && !s.password_confirmed) alerts += alertLine('warning', '<i class="bi bi-exclamation-triangle-fill"></i> ', esc(L('password_unconfirmed')));
    var testing = active.some(function(job) { return job.type === 'init'; });
    if (s.configured && !s.initialized && !testing && !repoFailed) {
      alerts += alertLine('warning', '<i class="bi bi-exclamation-triangle-fill"></i> ', esc(L('repo_not_tested')));
    }
    if (stale) alerts += alertLine('warning', '<i class="bi bi-exclamation-triangle-fill"></i> ', esc(fill(L('status_stale'), relative(s.last_success))));
    $.each(active, function(i, job) {
      var what = job.state === 'running' ? (stepText(job) || L('state_running')) + (job.started ? ', ' + jobDuration(job) : '') : L('state_queued');
      alerts += alertLine('info', (job.state === 'running' ? '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' : '<i class="bi bi-hourglass-split"></i> '),
        esc(fill(L('active_line'), typeLabel(job.type), job.id, what)) + (job.type === 'restore' ? ' <span class="text-muted">' + esc(restoreTarget(job, true)) + '</span>' : ''),
        detailsButton(job) + (isCancellable(job) ? cancelButton(job) : ''));
    });
    $.each(failed, function(i, type) {
      var job = jobs[type];
      alerts += alertLine('danger', '<i class="bi bi-x-circle-fill"></i> ', esc(fill(L('failed_line'), typeLabel(type), job.id)) +
        (job.error ? ': ' + esc(firstLine(job.error, 200)) : ''), detailsButton(job));
    });
    $('#backup_status_alerts').html(alerts);

    // backups and maintenance once the repository is set up and tested; one job of each type at a time
    var ready = !!(s.configured && s.initialized);
    var busy = function(type) { return active.some(function(job) { return job.type === type; }); };
    $('#backup_status .backup-job-request').each(function() {
      var type = $(this).data('type'), off = (type === 'init' ? !s.configured : !ready) || busy(type);
      if ($(this).is('a')) $(this).toggleClass('disabled', off).attr('aria-disabled', off ? 'true' : null);
      else $(this).prop('disabled', off);
    });
    $('#backup_status .backup-maintenance').prop('disabled', !ready);
  }
  function loadStatus() {
    clearTimeout(statusTimer);
    getJson('backup-status', function(status) {
      status = status || {};
      renderStatus(status);
      var active = (status.active || []).length;
      if (active || (lastActive !== null && active !== lastActive)) loadJobs();
      if (lastActive && !active) {
        loadSnapshotList();
        loadRunnerFields();
      }
      lastActive = active;
      if (active) statusTimer = setTimeout(loadStatus, POLL_MS);
    }).fail(function() {
      $('#backup_status .backup-status-summary').html('<span class="text-danger">' + esc(L('load_error')) + '</span>');
      if (lastActive) statusTimer = setTimeout(loadStatus, POLL_MS);
    });
  }
  function requestJob($button) {
    var type = $button.data('type');
    if ($button.hasClass('disabled') || $button.prop('disabled')) return;
    if (type === 'prune' && !confirm(L('prune_confirm'))) return;
    post('add/backup-job', {type: type}, function() {
      refreshAfterChange();
    }, $button.is('button') ? $button : null);
  }

  // ---- System > Configuration > Backup: settings ----

  function showTarget() {
    var target = $('#backup_target').val();
    $('#backup_settings .backup-target').each(function() {
      $(this).toggleClass('d-none', $(this).data('target') !== target);
    });
  }
  function toggleFts() {
    var $index = $('#backup_settings input[name="include_index"]');
    $('#backup_settings input[name="include_fts"]').prop('disabled', !$index.is(':checked'));
  }
  // values the runner writes: SSH key, host key; stored secrets as dots (a typed value stays)
  function fillRunnerFields(s) {
    var $form = $('#backup_settings');
    settingsCache = $.extend(settingsCache || {}, s);
    $.each(SECRETS, function(i, name) {
      var set = s[name + '_set'] == 1, $input = $form.find('input[name="' + name + '"]');
      $input.attr('placeholder', set ? L('secret_keep') : '').data('stored', set);
      if ($input.data('masked') || $input.val() === '') $input.val(set ? SECRET_MASK : '').data('masked', set);
    });
    showScope(s.state_backup_mode || '');
    $('#backup_sftp_public_key').val(s.sftp_public_key || '');
    $form.find('.backup-key-missing').toggleClass('d-none', !!s.sftp_public_key);
    $form.find('.backup-host-key').toggleClass('d-none', !s.sftp_host_key).find('code').text(s.sftp_host_key || '');
    if (lastStatus) renderStatus(lastStatus);
  }
  // RESTIC_BACKUP_MODE as the runner reported it: what the backups hold and what not
  function showScope(mode) {
    var $scope = $('#backup_settings .backup-scope'), known = BACKUP_MODES.indexOf(mode) !== -1;
    $scope.find('.backup-scope-title').toggleClass('text-danger', !!mode && !known)
      .text(!mode ? L('scope_unknown') : (known ? L('scope_' + mode) : fill(L('mode_invalid'), mode)));
    $scope.find('.backup-scope-var').text(known ? 'RESTIC_BACKUP_MODE=' + mode : '');
    $.each(['in', 'out'], function(i, part) {
      var text = known ? L('scope_' + mode + '_' + part) : '';
      $scope.find('.backup-scope-' + part).toggleClass('d-none', !text).find('span').text(text);
    });
  }
  function fillSettings(s) {
    var $form = $('#backup_settings');
    $form.find('input[name]:not([type="hidden"]), select[name]').each(function() {
      var $el = $(this), name = $el.attr('name'), value = s[name];
      if (SECRETS.indexOf(name) !== -1) {
        $el.val('').attr('type', 'password').data('masked', false);
      } else if ($el.is(':checkbox')) {
        $el.prop('checked', value == 1);
      } else if ($el.is('select')) {
        $el.selectpicker('val', (value === null || value === undefined) ? '' : String(value));
      } else if (name === 'max_unused') {
        $el.val(parseInt(value, 10) || 0);
      } else {
        $el.val((value === null || value === undefined) ? '' : value);
      }
    });
    // the confirmation is for the stored password: typing a new one asks for it again
    $form.find('input[name="password_confirmed"]').data('stored', s.password_confirmed == 1);
    $form.find('.backup-password-confirm').removeClass('text-danger');
    $form.find('.backup-password-change').addClass('d-none');
    $form.find('.inputMissingAttr').removeClass('inputMissingAttr');
    drawSchedule($.isArray(s.schedule) ? s.schedule : []);
    fillRunnerFields(s);
    showTarget();
    toggleFts();
  }
  // schedule: entries of weekdays and a time ("Mo, We – 12:00"), the row above the list adds one
  function scheduleText(entry) {
    var days = (entry.days.length === 7) ? L('schedule_daily') : $.map(WEEKDAYS, function(day) {
      return (entry.days.indexOf(day) !== -1) ? L('weekday_' + day).slice(0, 2) : null;
    }).join(', ');
    return fill(L('schedule_entry'), days, entry.time);
  }
  function scheduleEntries() {
    return $('#backup_settings .backup-schedule-list li').map(function() { return $(this).data('entry'); }).get();
  }
  function drawSchedule(entries) {
    var $form = $('#backup_settings'), $list = $form.find('.backup-schedule-list').empty(), perDay = {};
    $.each(entries, function(i, entry) {
      $('<li class="mb-1"><span></span><button type="button" class="btn btn-link btn-sm p-0 ms-2 align-baseline text-reset backup-schedule-remove" title="' +
        esc(L('schedule_remove')) + '" aria-label="' + esc(L('schedule_remove')) + '"><i class="bi bi-x-lg"></i></button></li>')
        .data('entry', entry).find('span').text(scheduleText(entry)).end().appendTo($list);
      $.each(entry.days, function(j, day) { perDay[day] = (perDay[day] || 0) + 1; });
    });
    $list.toggleClass('d-none', !entries.length);
    $form.find('.backup-schedule-none').toggleClass('d-none', entries.length > 0);
    $form.find('.backup-schedule-add').prop('disabled', entries.length >= MAX_SCHEDULE);
    // hourly snapshots only matter with several backups a day (or when set)
    var several = Object.keys(perDay).some(function(day) { return perDay[day] > 1; });
    $form.find('.backup-keep-hourly').toggleClass('d-none', !several && !(parseInt($('#backup_keep_hourly').val(), 10) > 0));
  }
  // the weekdays and time of the new row as an entry (mark: highlight what is missing); whether one was added
  function addScheduleEntry(mark) {
    var $new = $('#backup_settings .backup-schedule-new'), time = ($new.find('.backup-schedule-time').val() || '').slice(0, 5);
    var days = $new.find('.backup-schedule-days input:checked').map(function() { return parseInt(this.value, 10); }).get()
      .sort(function(a, b) { return a - b; });
    if (mark) {
      $new.find('label.btn').toggleClass('inputMissingAttr', !days.length);
      $new.find('.backup-schedule-time').toggleClass('inputMissingAttr', !time);
    }
    if (!days.length || !time) return false;
    var entries = scheduleEntries(), key = JSON.stringify({days: days, time: time});
    if (!entries.some(function(entry) { return JSON.stringify(entry) === key; })) entries.push({days: days, time: time});
    drawSchedule(entries);
    $new.find('.backup-schedule-days input').prop('checked', false);
    $new.find('.backup-schedule-time').val('');
    return true;
  }
  function loadSettings() {
    getJson('backup-settings', function(s) {
      fillSettings(s || {});
    }).fail(function() {
      mailcow_alert_box(esc(L('load_error')), 'danger');
    });
  }
  function loadRunnerFields() {
    getJson('backup-settings', function(s) {
      fillRunnerFields(s || {});
    });
  }
  // the fields of the chosen target and the general ones; the other targets keep their stored values
  function collectSettings() {
    var target = $('#backup_target').val(), attr = {};
    $('#backup_settings').find('input[name]:not([type="hidden"]), select[name]').each(function() {
      var $el = $(this), name = $el.attr('name'), $section = $el.closest('.backup-target');
      if ($section.length && $section.data('target') !== target) return;
      if ($el.is(':checkbox')) {
        attr[name] = $el.is(':checked') ? '1' : '0';
      } else if (SECRETS.indexOf(name) !== -1) {
        if ($el.val() !== '' && !$el.data('masked')) attr[name] = $el.val();
      } else if (name === 'max_unused') {
        attr[name] = $el.val() + '%';
      } else {
        attr[name] = $el.val();
      }
    });
    var $form = $('#backup_settings');
    attr.schedule = scheduleEntries();
    return attr;
  }
  function saveSettings($button) {
    // weekdays and time filled in but not added yet: they are meant to be part of the schedule
    addScheduleEntry(false);
    var $form = $('#backup_settings'), attr = collectSettings();
    $form.find('.inputMissingAttr').removeClass('inputMissingAttr');
    $form.find('.backup-password-confirm').removeClass('text-danger');
    if ((attr.password || attr.enabled === '1') && attr.password_confirmed !== '1') {
      $form.find('.backup-password-confirm').addClass('text-danger');
      $form.find('input[name="password_confirmed"]').focus();
      mailcow_alert_box(esc(lang_danger.backup_password_not_confirmed || L('password_confirmed')), 'danger');
      return;
    }
    post('edit/backup-settings', {items: ['settings'], attr: attr}, function(ok, messages) {
      $.each(messages, function(i, message) {
        var msg = $.isArray(message.msg) ? message.msg : [message.msg];
        if (msg[0] === 'backup_setting_invalid') {
          var $field = $form.find('[name="' + msg[1] + '"]').add($form.find('[data-setting="' + msg[1] + '"]').find('input[type="time"], label.btn'))
            .addClass('inputMissingAttr');
          // a field of the collapsed section: open it
          if ($field.closest('#backup_advanced').length) $('#backup_advanced').collapse('show');
        }
        if (msg[0] === 'backup_password_too_short') $form.find('[name="password"]').addClass('inputMissingAttr');
        // secrets are encrypted with the key of the backup container: it must have run once
        if (msg[0] === 'backup_secrets_key_missing') $form.find('.backup-secret').filter(function() { return this.value !== '' && !$(this).data('masked'); }).addClass('inputMissingAttr');
        if (msg[0] === 'backup_password_not_confirmed') $form.find('.backup-password-confirm').addClass('text-danger');
      });
      if (ok) {
        loadSettings();
        loadStatus();
      }
    }, $button);
  }
  function generatePassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789', bytes = new Uint32Array(32), password = '';
    window.crypto.getRandomValues(bytes);
    for (var i = 0; i < bytes.length; i++) password += chars.charAt(bytes[i] % chars.length);
    return password;
  }
  function copyField(selector) {
    var $field = $(selector), value = $field.val();
    if (!value) return;
    var done = function() {
      mailcow_alert_box(esc((typeof lang !== 'undefined' && lang.copy_to_clipboard) || L('copy')), 'success');
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(done);
    } else {
      $field.trigger('select');
      if (document.execCommand('copy')) done();
    }
  }

  // ---- System > Configuration > Backup: jobs and snapshots (sections hidden while empty) ----

  function loadJobs() {
    getJson('backup-jobs/200', function(json) {
      // restores are listed on E-Mail > Restore
      var jobs = $.grep($.isArray(json) ? json : [], function(job) { return job.type !== 'restore'; });
      var $section = $('#tab-config-backup .backup-jobs-section').toggleClass('d-none', !jobs.length);
      if (!jobs.length) return;
      $.each(jobs, function(i, job) {
        job.type_display = esc(typeLabel(job.type)) + (job.requested_by && job.requested_by !== 'schedule'
          ? '<div class="small text-muted">' + esc(fill(L('manual_by'), job.requested_by)) + '</div>' : '');
        job.state_display = stateBadge(job);
        job.time = job.started || job.created;
        job.duration_display = esc(jobDuration(job));
        job.result_display = jobSummary(job) + errorCell(job);
        job.action = jobActions(job);
      });
      if ($section.is(':visible')) setTable('#backup_jobs_table', tableOptions({
        order: [[3, 'desc']],
        columns: [
          placeholderColumn(),
          {title: L('type'), data: 'type_display', defaultContent: '', responsivePriority: 2},
          {title: L('state'), data: 'state_display', defaultContent: '', responsivePriority: 1},
          {title: L('time'), data: 'time', defaultContent: '', render: renderTime, responsivePriority: 3},
          {title: L('duration'), data: 'duration_display', defaultContent: '', responsivePriority: 5},
          {title: L('result'), data: 'result_display', defaultContent: '', responsivePriority: 4},
          actionColumn()
        ]
      }, L('jobs_none')), jobs);
    }).fail(tableError('backup_jobs_table'));
  }
  function loadSnapshotList() {
    getJson('backup-snapshots', function(list) {
      list = $.isArray(list) ? list : [];
      $('#tab-config-backup .backup-snapshots-section').toggleClass('d-none', !list.length);
      $('#tab-config-backup .backup-snapshot-count').text(list.length);
      if (!list.length) return;
      var host = (typeof backup_hostname !== 'undefined') ? backup_hostname : '';
      var other = list.some(function(snapshot) { return snapshot.hostname && host && snapshot.hostname !== host; });
      $.each(list, function(i, snapshot) {
        var summary = snapshot.summary || {}, domains = $.isArray(snapshot.domains) ? snapshot.domains : null;
        snapshot.id_display = '<code title="' + esc(snapshot.id) + '">' + esc(snapshot.short_id || String(snapshot.id).substr(0, 8)) + '</code>' +
          (modeText(snapshot) ? ' <span class="small text-muted">' + esc(modeText(snapshot)) + '</span>' : '');
        snapshot.domains_display = domains ? '<span title="' + esc(domains.join(', ')) + '">' + esc(domains.length) + '</span>' : '–';
        snapshot.host_display = esc(snapshot.hostname || '');
        snapshot.size = summary.total_bytes_processed;
        snapshot.added = summary.data_added;
      });
      var options = tableOptions({
        order: [[1, 'desc']],
        columns: [
          placeholderColumn(),
          {title: L('time'), data: 'time', defaultContent: '', render: renderTime, responsivePriority: 1},
          {title: L('id'), data: 'id_display', defaultContent: '', responsivePriority: 2},
          {title: L('domains'), data: 'domains_display', defaultContent: '', responsivePriority: 3},
          {title: L('size'), data: 'size', defaultContent: '', render: renderSize, responsivePriority: 4},
          {title: L('data_added'), data: 'added', defaultContent: '', render: renderSize, responsivePriority: 4},
          {title: L('hostname'), data: 'host_display', defaultContent: '', visible: other, responsivePriority: 5}
        ]
      }, L('snapshots_none'));
      if ($.fn.DataTable.isDataTable('#backup_snapshots_table')) $('#backup_snapshots_table').DataTable().column(6).visible(other);
      setTable('#backup_snapshots_table', options, list);
    }).fail(tableError('backup_snapshots_table'));
  }

  // ---- E-Mail > Restore: the restores ----

  var restoresTimer = null;
  // while restores are queued or running: reload the list when it is visible
  function pollRestores() {
    if ($('#backup_restores_table').is(':visible')) reloadTable('#backup_restores_table');
    else restoresTimer = setTimeout(pollRestores, POLL_MS);
  }
  function drawRestores() {
    if ($.fn.DataTable.isDataTable('#backup_restores_table')) {
      $('#backup_restores_table').DataTable().columns.adjust().responsive.recalc();
      return;
    }
    $('#backup_restores_table').DataTable(tableOptions({
      order: [[1, 'desc']],
      ajax: {
        type: 'GET',
        url: '/api/v1/get/backup-jobs/500/restore',
        dataSrc: function(json) {
          if (!$.isArray(json)) return [];
          var jobs = $.grep(json, function(job) { return job.type === 'restore'; });
          $.each(jobs, function(i, job) {
            job.state_display = stateBadge(job);
            job.target_display = esc(restoreTarget(job, true)) + '<div>' + componentsCell(job) + '</div>';
            job.snapshot_display = esc(restoreSnapshot(job));
            job.requested_display = esc(requestedBy(job));
            job.result_display = restoreSummary(job) + errorCell(job);
            job.action = jobActions(job);
          });
          updateActiveRestores(jobs);
          clearTimeout(restoresTimer);
          if (jobs.some(isActive)) restoresTimer = setTimeout(pollRestores, POLL_MS);
          return jobs;
        },
        error: tableError('backup_restores_table')
      },
      columns: [
        placeholderColumn(),
        {title: L('id'), data: 'id', defaultContent: '', responsivePriority: 3},
        {title: L('state'), data: 'state_display', defaultContent: '', responsivePriority: 1},
        {title: L('mailboxes'), data: 'target_display', defaultContent: '', responsivePriority: 2},
        {title: L('snapshot'), data: 'snapshot_display', defaultContent: '', responsivePriority: 5},
        {title: L('result'), data: 'result_display', defaultContent: '', responsivePriority: 4},
        {title: L('requested_by'), data: 'requested_display', defaultContent: '', responsivePriority: 6},
        {title: L('created'), data: 'created', defaultContent: '', render: renderTime, responsivePriority: 5},
        actionColumn()
      ]
    }, L('restores_none')));
  }

  // ---- E-Mail > Restore: snapshot, tree of its domains and mailboxes, selection, restore modal ----

  // rows rendered per step ("show more"), so the page stays small for big snapshots
  var CHUNK = 200;
  var SUMMARY_MAX = 10;
  var SEARCH_MIN = 2;
  // snapshot: chosen id; snapshots: id => snapshot; available: the snapshot has a content list; domains: its domains
  // (rows of get/backup-snapshot-content); nodes: domain => {info, index, expanded, loading, failed, mailboxes (null until
  // loaded), known: username => mailbox, shown}; shownDomains: domain rows rendered; term/search: the server search and
  // its result; onlyDeleted: filter; selection: {domain, scope: domain|mailbox, mailboxes: {username: true}} or null;
  // replaced: the domain whose selection was replaced; active: restores queued or running (domain => {all, users});
  // target: what the modal restores; deletedOnly: everything in the modal target was deleted
  var tree = {snapshot: null, snapshots: {}, available: 1, domains: [], nodes: {}, shownDomains: CHUNK, term: '', search: null, onlyDeleted: false,
    selection: null, replaced: null, notice: null, active: {}, activeIds: [], activeKey: '', target: null, deletedOnly: false, seq: 0, loaded: false};
  var searchTimer = null;
  var searchRequest = null;

  function isAdmin() {
    return typeof role !== 'undefined' && role === 'admin';
  }
  function pageHint(type, html) {
    $('#backup_restore_page .backup-restore-hints').html(html ? '<div class="alert alert-' + type + '">' + html + '</div>' : '');
  }
  function settingsLink(text) {
    return ' <a href="/admin/system#tab-config-backup">' + esc(text) + '</a>';
  }
  function deletedBadge() {
    return '<span class="badge bg-danger">' + esc(L('status_deleted')) + '</span>';
  }
  function restoringBadge() {
    return '<span class="badge bg-info text-black">' + esc(L('status_restoring')) + '</span>';
  }

  function loadSnapshots() {
    if (typeof backup_configured !== 'undefined' && !backup_configured) {
      pageHint('warning', esc(L(isAdmin() ? 'not_configured' : 'not_configured_da')) + (isAdmin() ? settingsLink(L('to_backup_settings')) : ''));
      setSnapshotOptions([]);
      return;
    }
    getJson('backup-snapshots', function(list) {
      list = $.isArray(list) ? list : [];
      list.sort(function(a, b) { return b.time - a.time; });
      setSnapshotOptions(list);
      if (!list.length) {
        pageHint('info', esc(L('no_snapshots_any')) + (isAdmin() ? settingsLink(L('no_snapshots_backup_now')) : ''));
        return;
      }
      selectSnapshot(list[0].id);
    }).fail(function() {
      pageHint('danger', esc(L('load_error')));
    });
  }
  // the options are rebuilt and bootstrap-select is built anew: its refresh repeats the list and shows hidden options
  function setSnapshotOptions(list) {
    var $select = $('#backup_restore_snapshot');
    tree.snapshots = {};
    $select.selectpicker('destroy');
    $select.empty();
    $.each(list, function(i, snapshot) {
      tree.snapshots[snapshot.id] = snapshot;
      $select.append($('<option></option>').val(snapshot.id).text(formatTime(snapshot.time) + ' · ' + relative(snapshot.time))
        .attr('data-subtext', (snapshot.short_id || String(snapshot.id).substr(0, 8)) + (snapshot.mode === 'config' ? ' · ' + modeText(snapshot) : '')));
    });
    $select.prop('disabled', !list.length);
    $select.selectpicker({styleBase: 'btn btn-xs-lg', noneSelectedText: lang_footer.nothing_selected, showSubtext: true});
  }
  // what a snapshot lacks (RESTIC_BACKUP_MODE), '' for complete ones
  function modeText(snapshot) {
    return (snapshot.mode === 'data' || snapshot.mode === 'config') ? L('snapshot_mode_' + snapshot.mode) : '';
  }
  function noMails() {
    var snapshot = tree.snapshots[tree.snapshot];
    return !!(snapshot && snapshot.mode === 'config');
  }
  function snapshotLabel(snapshot) {
    return formatTime(snapshot.time) + ' · ' + (snapshot.short_id || String(snapshot.id).substr(0, 8));
  }

  function treeBody() {
    return $('#backup_tree tbody');
  }
  function treeMessage(text, cls) {
    treeBody().html('<tr class="backup-tree-message"><td colspan="4" class="' + (cls || 'text-muted') + '">' + esc(text) + '</td></tr>');
  }
  // keep: reload after a restore finished (expanded domains stay expanded, the selection stays)
  function selectSnapshot(id, keep) {
    var $panel = $('#backup_restore_page .backup-tree-panel').removeClass('d-none');
    var expanded = keep ? $.grep(Object.keys(tree.nodes), function(name) { return tree.nodes[name].expanded; }) : [];
    tree.snapshot = id;
    if (keep && tree.selection) {
      // states changed (deleted mailboxes may exist now): a kept selection would restore with the old states
      tree.selection = null;
      tree.replaced = null;
      tree.notice = L('selection_reset');
      updateChecks();
    }
    if (!keep) {
      tree.domains = [];
      tree.nodes = {};
      tree.selection = null;
      tree.replaced = null;
      tree.shownDomains = CHUNK;
      tree.search = null;
      tree.term = '';
      $panel.find('.backup-tree-search').val('');
      $panel.find('.backup-content-note').empty();
      treeMessage(lang_footer.loading);
      updateChecks();
    }
    getJson('backup-snapshot-content/' + encodeURIComponent(id), function(content) {
      if (tree.snapshot !== id) return;
      if (!content || !$.isArray(content.domains)) {
        treeMessage(L('load_error'), 'text-danger');
        return;
      }
      tree.available = (content.available === undefined) ? 1 : content.available;
      // domain admins never get deleted domains (the API leaves them out as well)
      tree.domains = $.grep(content.domains, function(d) { return isAdmin() || d.exists == 1; });
      tree.domains.sort(function(a, b) { return String(a.domain).localeCompare(String(b.domain)); });
      tree.nodes = {};
      $.each(tree.domains, function(i, d) {
        tree.nodes[d.domain] = {info: d, index: i, expanded: false, loading: false, failed: false, mailboxes: null, known: {}, shown: CHUNK};
      });
      $panel.find('.backup-content-note').html(tree.available == 1 ? '' : '<div class="alert alert-info">' + esc(L('content_unavailable')) + '</div>');
      tree.loaded = true;
      // one domain (typical for domain admins): open at once
      if (tree.domains.length === 1 && !keep) expanded = [tree.domains[0].domain];
      $.each(expanded, function(i, name) {
        var node = tree.nodes[name];
        if (node) {
          node.expanded = true;
          loadMailboxes(node);
        }
      });
      if (tree.term.length >= SEARCH_MIN) runSearch();
      else renderTree();
    }).fail(function() {
      if (tree.snapshot === id) treeMessage(L('load_error'), 'text-danger');
    });
  }

  // ---- tree rows ----

  function domainVisible(node) {
    return !tree.onlyDeleted || node.info.exists != 1 || node.info.missing > 0;
  }
  function mailboxVisible(m) {
    return !tree.onlyDeleted || m.exists != 1;
  }
  // a deleted domain can only be restored as a whole, and only from a snapshot with a content list
  function domainRestorable(node) {
    return node.info.exists == 1 || tree.available == 1;
  }
  function mailboxesSelectable(node) {
    return node.info.exists == 1;
  }
  function restoringDomain(node) {
    var active = tree.active[node.info.domain];
    return !!(active && active.all);
  }
  function restoringMailbox(node, m) {
    var active = tree.active[node.info.domain];
    return !!(active && (active.all || active.users[m.username]));
  }
  function restoreRowButton(label, disabled) {
    return '<button type="button" class="btn btn-xs btn-xs-lg btn-secondary text-nowrap backup-tree-restore" title="' + esc(fill(L('restore_item'), label)) +
      '" aria-label="' + esc(fill(L('restore_item'), label)) + '"' + (disabled ? ' disabled' : '') + '><i class="bi bi-clock-history"></i><span class="d-none d-sm-inline"> ' + esc(L('restore')) + '</span></button>';
  }
  function domainCount(d) {
    if (d.mailboxes === null || d.mailboxes === undefined) return '';
    return d.missing > 0 ? fill(L('count_deleted'), d.mailboxes, d.missing) : String(d.mailboxes);
  }
  function domainRowHtml(node) {
    var d = node.info, count = domainCount(d), restoring = restoringDomain(node), id = 'backup_tree_c' + (++tree.seq);
    var badges = (d.exists != 1 ? deletedBadge() : '') + (restoring ? ' ' + restoringBadge() : '');
    return '<tr class="backup-tree-domain" data-index="' + node.index + '" style="cursor:pointer">' +
      '<td><div class="d-flex align-items-center gap-1">' +
      '<button type="button" class="btn btn-link p-0 text-reset d-inline-flex align-items-center justify-content-center flex-shrink-0 backup-tree-toggle" style="width:2rem;height:2rem;min-width:32px;min-height:32px" aria-expanded="' +
      (node.expanded ? 'true' : 'false') + '" aria-label="' + esc(fill(L(node.expanded ? 'collapse' : 'expand'), d.domain)) + '"><i class="bi bi-chevron-' + (node.expanded ? 'down' : 'right') + '"></i></button>' +
      '<input type="checkbox" class="form-check-input mt-0 flex-shrink-0 backup-tree-check" id="' + id + '"' + (domainRestorable(node) && !restoring ? '' : ' disabled') + '>' +
      '<label class="text-break mb-0 ms-1 fw-semibold" for="' + id + '">' + esc(d.domain) + '</label>' +
      (badges ? '<span class="d-md-none ms-1">' + badges + '</span>' : '') + (count ? '<span class="d-sm-none small text-muted ms-1">· ' + esc(count) + '</span>' : '') + '</div></td>' +
      '<td class="d-none d-md-table-cell">' + badges + '</td>' +
      '<td class="d-none d-sm-table-cell text-nowrap">' + esc(count) + '</td>' +
      '<td class="text-end">' + (domainRestorable(node) ? restoreRowButton(d.domain, restoring) : '') + '</td></tr>';
  }
  function childRow(node, html, cls) {
    return '<tr class="backup-tree-child" data-index="' + node.index + '"><td colspan="4" class="small ' + (cls || 'text-muted') + '" style="padding-left:2.75rem">' + html + '</td></tr>';
  }
  function mailboxRowHtml(node, m) {
    var selectable = mailboxesSelectable(node), restoring = restoringMailbox(node, m), id = 'backup_tree_c' + (++tree.seq);
    var badges = (m.exists != 1 ? deletedBadge() : '') + (restoring ? ' ' + restoringBadge() : '');
    node.known[m.username] = m;
    return '<tr class="backup-tree-mailbox" data-index="' + node.index + '" data-user="' + esc(m.username) + '">' +
      '<td style="padding-left:2.75rem"><div class="d-flex align-items-center gap-2">' +
      '<input type="checkbox" class="form-check-input mt-0 flex-shrink-0 backup-tree-check" id="' + id + '"' + (selectable && !restoring ? '' : ' disabled') + '>' +
      '<label class="text-break mb-0" for="' + id + '">' + esc(m.username) + (m.name ? ' <span class="small text-muted">' + esc(m.name) + '</span>' : '') + '</label>' +
      (badges ? '<span class="d-md-none">' + badges + '</span>' : '') + '</div></td>' +
      '<td class="d-none d-md-table-cell">' + badges + '</td>' +
      '<td class="d-none d-sm-table-cell"></td>' +
      '<td class="text-end">' + (selectable ? restoreRowButton(m.username, restoring) : '') + '</td></tr>';
  }
  // the mailboxes a domain shows: the search hits or its loaded mailboxes, with the filter
  function shownMailboxes(node) {
    var list = tree.search ? (node.hits || []) : node.mailboxes;
    return list ? $.grep(list, mailboxVisible) : null;
  }
  function childRowsHtml(node) {
    if (node.loading && !tree.search) return childRow(node, '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + esc(lang_footer.loading));
    if (node.failed && !tree.search) return childRow(node, esc(L('load_error')), 'text-danger');
    var list = shownMailboxes(node);
    if (list === null) return '';
    var html = mailboxesSelectable(node) ? '' : childRow(node, '<i class="bi bi-info-circle"></i> ' + esc(L('deleted_domain_children')));
    if (!list.length) return html + childRow(node, esc(L(tree.term || tree.onlyDeleted ? 'no_matches' : 'no_mailboxes')));
    $.each(list.slice(0, node.shown), function(i, m) {
      html += mailboxRowHtml(node, m);
    });
    if (list.length > node.shown) {
      html += childRow(node, '<button type="button" class="btn btn-xs btn-xs-lg btn-outline-secondary backup-tree-more">' +
        esc(fill(L('show_more'), Math.min(CHUNK, list.length - node.shown), list.length - node.shown)) + '</button>');
    }
    return html;
  }
  // the domains shown: search hits or all, with the filter
  function shownDomains() {
    if (tree.search) {
      return $.map(tree.search.domains, function(hit) {
        var node = tree.nodes[hit.domain];
        if (!node) return null;
        node.hits = $.isArray(hit.mailboxes) ? hit.mailboxes : [];
        $.each(node.hits, function(i, m) { node.known[m.username] = m; });
        return domainVisible(node) ? node : null;
      });
    }
    return $.grep($.map(tree.domains, function(d) { return tree.nodes[d.domain]; }), domainVisible);
  }
  function renderTree() {
    var nodes = shownDomains(), html = '';
    $.each(nodes.slice(0, tree.shownDomains), function(i, node) {
      // search results show their hits right away
      html += domainRowHtml(node) + ((node.expanded || tree.search) ? childRowsHtml(node) : '');
    });
    if (nodes.length > tree.shownDomains) {
      html += '<tr class="backup-tree-child"><td colspan="4"><button type="button" class="btn btn-xs btn-xs-lg btn-outline-secondary backup-tree-more-domains">' +
        esc(fill(L('show_more_domains'), Math.min(CHUNK, nodes.length - tree.shownDomains), nodes.length - tree.shownDomains)) + '</button></td></tr>';
    }
    if (tree.search && tree.search.truncated) html += '<tr class="backup-tree-child"><td colspan="4" class="small text-muted"><i class="bi bi-info-circle"></i> ' + esc(L('search_truncated')) + '</td></tr>';
    if (!tree.domains.length) treeMessage(L('no_domains'));
    else if (!nodes.length) treeMessage(L('no_matches'));
    else treeBody().html(html);
    updateChecks();
  }
  // the domain row and its mailbox rows anew; the focus stays on the same control of the row
  function renderDomain(node, focusIndex) {
    var $row = treeBody().find('tr.backup-tree-domain[data-index="' + node.index + '"]');
    if (!$row.length) return;
    var $focus = $(document.activeElement), control = null;
    if ($focus.closest('tr').is($row)) {
      control = $focus.hasClass('backup-tree-toggle') ? '.backup-tree-toggle' : ($focus.hasClass('backup-tree-check') ? '.backup-tree-check'
        : ($focus.hasClass('backup-tree-restore') ? '.backup-tree-restore' : null));
    }
    treeBody().find('tr.backup-tree-child[data-index="' + node.index + '"], tr.backup-tree-mailbox[data-index="' + node.index + '"]').remove();
    $row.replaceWith(domainRowHtml(node) + ((node.expanded || tree.search) ? childRowsHtml(node) : ''));
    updateChecks();
    var $new = treeBody().find('tr.backup-tree-domain[data-index="' + node.index + '"]');
    if (control) $new.find(control).trigger('focus');
    // "show more": the first new mailbox
    if (focusIndex !== undefined) treeBody().find('tr.backup-tree-mailbox[data-index="' + node.index + '"]').eq(focusIndex).find('.backup-tree-check').trigger('focus');
  }
  function nodeOf($el) {
    var d = tree.domains[parseInt($el.closest('tr').data('index'), 10)];
    return d ? tree.nodes[d.domain] : null;
  }
  function mailboxOf($el, node) {
    return node ? node.known[$el.closest('tr').attr('data-user')] || null : null;
  }
  function toggleDomain(node) {
    if (tree.search) return;
    node.expanded = !node.expanded;
    if (node.expanded && node.mailboxes === null && !node.loading) loadMailboxes(node);
    renderDomain(node);
  }
  function loadMailboxes(node, done) {
    var id = tree.snapshot;
    node.loading = true;
    node.failed = false;
    getJson('backup-snapshot-content/' + encodeURIComponent(id) + '/' + encodeURIComponent(node.info.domain), function(res) {
      if (tree.snapshot !== id) return;
      node.loading = false;
      node.mailboxes = (res && $.isArray(res.mailboxes)) ? res.mailboxes : [];
      node.mailboxes.sort(function(a, b) { return String(a.username).localeCompare(String(b.username)); });
      $.each(node.mailboxes, function(i, m) { node.known[m.username] = m; });
      if (done) done();
      renderDomain(node);
      updateChecks();
    }).fail(function() {
      if (tree.snapshot !== id) return;
      node.loading = false;
      node.failed = true;
      renderDomain(node);
    });
  }

  // ---- search in the snapshot (server side, also in collapsed domains) ----

  function runSearch() {
    var term = tree.term, id = tree.snapshot;
    if (searchRequest) searchRequest.abort();
    if (term.length < SEARCH_MIN) {
      tree.search = null;
      renderTree();
      return;
    }
    treeMessage(lang_footer.loading);
    // nginx decodes the path and passes it in ?query= of json_api.php as it is: "&", "+" and "%" are encoded twice
    searchRequest = getJson('backup-snapshot-search/' + encodeURIComponent(id) + '/' + encodeURIComponent(encodeURIComponent(term)), function(res) {
      if (tree.snapshot !== id || tree.term !== term) return;
      tree.search = {domains: (res && $.isArray(res.domains)) ? res.domains : [], truncated: res && res.truncated ? 1 : 0};
      tree.shownDomains = CHUNK;
      renderTree();
    });
    searchRequest.fail(function(xhr, status) {
      if (status !== 'abort' && tree.term === term) treeMessage(L('load_error'), 'text-danger');
    });
  }

  // ---- selection: one domain, either the whole domain or some of its mailboxes ----

  function setSelection(selection, replaced) {
    var old = tree.selection;
    tree.notice = null;
    tree.replaced = (old && selection && old.domain !== selection.domain) ? old.domain : (replaced || null);
    tree.selection = selection;
    updateChecks();
  }
  function selectedMailboxes() {
    var sel = tree.selection;
    return (sel && sel.scope === 'mailbox') ? Object.keys(sel.mailboxes) : [];
  }
  // all mailboxes of the domain ticked one by one
  function allTicked() {
    var sel = tree.selection, node = sel ? tree.nodes[sel.domain] : null;
    if (!node || sel.scope !== 'mailbox' || !node.mailboxes || !node.mailboxes.length) return false;
    return node.mailboxes.every(function(m) { return sel.mailboxes[m.username] || restoringMailbox(node, m); });
  }
  function updateChecks() {
    var sel = tree.selection;
    treeBody().find('tr.backup-tree-domain').each(function() {
      var node = nodeOf($(this)), mine = !!(node && sel && sel.domain === node.info.domain);
      $(this).find('.backup-tree-check').prop('checked', mine && sel.scope === 'domain').prop('indeterminate', mine && sel.scope === 'mailbox');
    });
    treeBody().find('tr.backup-tree-mailbox').each(function() {
      var node = nodeOf($(this)), m = mailboxOf($(this), node), mine = !!(m && sel && sel.domain === node.info.domain);
      $(this).find('.backup-tree-check').prop('checked', mine && (sel.scope === 'domain' || !!sel.mailboxes[m.username]));
    });
    updateToolbar();
  }
  function updateToolbar() {
    var sel = tree.selection, $page = $('#backup_restore_page'), text = L('selection_none'), count = selectedMailboxes().length, hint = '';
    if (sel && sel.scope === 'domain') text = fill(L('selected_domain'), sel.domain);
    else if (sel) text = fill(L(count === 1 ? 'selected_mailbox_in' : 'selected_mailboxes_in'), count, sel.domain);
    $page.find('.backup-selection-text').text(text);
    $page.find('.backup-selection-clear').toggleClass('d-none', !sel);
    $page.find('.backup-selection-restore').prop('disabled', !sel);
    if (tree.replaced) hint = '<i class="bi bi-exclamation-triangle-fill text-warning"></i> ' + esc(fill(L('selection_replaced'), tree.replaced));
    else if (tree.notice) hint = '<i class="bi bi-info-circle"></i> ' + esc(tree.notice);
    else if (allTicked()) hint = '<i class="bi bi-info-circle"></i> ' + esc(L('all_ticked')) +
      ' <button type="button" class="btn btn-link btn-sm p-0 align-baseline backup-select-domain">' + esc(L('select_whole_domain')) + '</button>';
    $page.find('.backup-selection-hint').toggleClass('d-none', !hint).html(hint);
  }
  function checkDomain(node, checked) {
    setSelection(checked ? {domain: node.info.domain, scope: 'domain', mailboxes: {}} : null);
  }
  function checkMailbox(node, m, checked) {
    var sel = tree.selection, domain = node.info.domain, set = {};
    if (sel && sel.domain === domain && sel.scope === 'domain') {
      // unchecking a mailbox of a whole-domain selection keeps the other mailboxes of the domain (all of them loaded first)
      if (node.mailboxes === null) {
        loadMailboxes(node, function() { checkMailbox(node, m, checked); });
        return;
      }
      $.each(node.mailboxes, function(i, other) {
        if (other.username !== m.username && !restoringMailbox(node, other)) set[other.username] = true;
      });
    } else {
      if (sel && sel.domain === domain) set = $.extend({}, sel.mailboxes);
      if (checked) set[m.username] = true;
      else delete set[m.username];
    }
    setSelection(Object.keys(set).length ? {domain: domain, scope: 'mailbox', mailboxes: set} : null);
  }

  // ---- restores in progress: rows marked, reloaded when they are done ----

  function updateActiveRestores(jobs) {
    var active = {}, ids = [];
    $.each(jobs, function(i, job) {
      if (!isActive(job)) return;
      var params = job.params || {}, domain = params.domain || job.domain;
      ids.push(job.id);
      if (!domain) return;
      active[domain] = active[domain] || {all: false, users: {}};
      if (params.scope === 'domain') active[domain].all = true;
      $.each(restoreMailboxes(job), function(j, user) { active[domain].users[user] = true; });
    });
    var finished = tree.activeIds.some(function(id) { return ids.indexOf(id) === -1; }), key = JSON.stringify(active);
    tree.activeIds = ids;
    if (key === tree.activeKey && !finished) return;
    tree.active = active;
    tree.activeKey = key;
    if (!tree.loaded) return;
    // "deleted" turns into "exists" when a restore created the mailbox or domain
    if (finished) selectSnapshot(tree.snapshot, true);
    else if (tree.term.length >= SEARCH_MIN) renderTree();
    else renderTree();
  }

  // ---- restore modal ----

  function domainTarget(node) {
    return {scope: 'domain', domain: node.info.domain, node: node};
  }
  function mailboxesTarget(node, usernames) {
    return {scope: 'mailbox', domain: node.info.domain, node: node,
      mailboxes: $.map(usernames, function(u) { return node.known[u] || {username: u, exists: 1}; })};
  }
  function selectionTarget() {
    var sel = tree.selection, node = sel ? tree.nodes[sel.domain] : null;
    if (!node) return null;
    return sel.scope === 'domain' ? domainTarget(node) : mailboxesTarget(node, selectedMailboxes());
  }
  function createdBadge() {
    return ' <span class="badge bg-warning text-black">' + esc(L('will_be_created')) + '</span>';
  }
  // number of mailboxes a restore covers (domain: those of the snapshot)
  function targetCount(target) {
    return target.scope === 'domain' ? (target.node.info.mailboxes || 0) : target.mailboxes.length;
  }
  function openRestore(target) {
    var $modal = $('#backupRestoreModal'), snapshot = tree.snapshots[tree.snapshot], html = '', deleted, deletedOnly, title;
    if (!target) return;
    tree.target = target;
    tree.replaced = null;
    updateToolbar();
    if (target.scope === 'domain') {
      var d = target.node.info;
      deleted = d.exists != 1 || d.missing > 0;
      deletedOnly = d.exists != 1 || (d.mailboxes > 0 && d.missing >= d.mailboxes);
      title = fill(L('modal_title_domain'), d.domain);
      html += '<div class="fw-bold">' + esc(fill(L('restore_target_domain'), d.domain)) + (d.exists != 1 ? createdBadge() : '') + '</div>';
      if (d.mailboxes !== null && d.mailboxes !== undefined) {
        var count = (d.mailboxes == 1) ? L(d.missing > 0 ? 'target_count_one_deleted' : 'target_count_one')
          : (d.missing > 0 ? fill(L('target_count'), d.mailboxes, d.missing) : fill(L('target_count_all'), d.mailboxes));
        html += '<div class="small text-muted">' + esc(count) + '</div>';
      }
    } else {
      var gone = $.grep(target.mailboxes, function(m) { return m.exists != 1; }).length;
      deleted = gone > 0;
      deletedOnly = gone === target.mailboxes.length;
      if (target.mailboxes.length === 1) {
        title = fill(L('modal_title_mailbox'), target.mailboxes[0].username);
        html += '<div class="fw-bold">' + esc(fill(L('restore_target_mailbox'), target.mailboxes[0].username)) + (gone ? createdBadge() : '') + '</div>';
      } else {
        title = fill(L('modal_title_mailboxes'), target.mailboxes.length, target.domain);
        html += '<div class="fw-bold">' + esc(fill(L('restore_target_mailboxes_of'), target.mailboxes.length, target.domain)) + '</div>';
        html += '<ul class="mb-0 small">' + $.map(target.mailboxes.slice(0, SUMMARY_MAX), function(m) {
          return '<li>' + esc(m.username) + (m.exists != 1 ? createdBadge() : '') + '</li>';
        }).join('') + (target.mailboxes.length > SUMMARY_MAX ? '<li class="text-muted">' + esc(fill(L('and_more'), target.mailboxes.length - SUMMARY_MAX)) + '</li>' : '') + '</ul>';
      }
    }
    $modal.find('.modal-title').text(title);
    $modal.find('.backup-restore-subtitle').text(L('snapshot_label') + ': ' + (snapshot ? snapshotLabel(snapshot) : tree.snapshot));
    $modal.find('.backup-restore-summary').html(html);
    $modal.find('.backup-create-note').toggleClass('d-none', !deleted);
    setOverwriteMode(deletedOnly);
    applyDefaults(deletedOnly);
    updateFolderName();
    restoreError('');
    $modal.modal('show');
  }
  function updateFolderName() {
    var snapshot = tree.snapshots[tree.snapshot];
    $('#backup_restore_form .backup-folder-name').text(snapshot ? folderName(snapshot.time) : L('mail_target_folder_name'));
  }
  // everything deleted: nothing to overwrite, SOGo is a normal item, settings and password are not sent
  function setOverwriteMode(deletedOnly) {
    var $form = $('#backup_restore_form'), $sogo = $form.find('.backup-sogo-item'), $label = $sogo.find('label');
    tree.deletedOnly = deletedOnly;
    if (deletedOnly) $form.find('.backup-sogo-normal').append($sogo);
    else $form.find('.backup-overwrite > p').after($sogo);
    $form.find('.backup-sogo-normal').toggleClass('d-none', !deletedOnly);
    $form.find('.backup-overwrite').toggleClass('d-none', deletedOnly);
    $sogo.find('.backup-sogo-note').toggleClass('d-none', deletedOnly);
    $label.text(deletedOnly ? $label.data('normal') : $label.data('overwrite'));
    $form.find('.backup-component[value="settings"], .backup-component[value="password"]').prop('disabled', deletedOnly);
  }
  // existing targets: only the mails into a separate folder; everything deleted: all items, mails into their folders
  function applyDefaults(deletedOnly) {
    $('#backup_restore_form .backup-component').each(function() {
      var key = this.value;
      var on = deletedOnly ? (OVERWRITE.indexOf(key) === -1 || key === 'sogo') : EXISTING_DEFAULT.indexOf(key) !== -1;
      $(this).prop('checked', on && !(noMails() && MAIL_COMPONENTS.indexOf(key) !== -1));
    });
    $('#backup_restore_form input[name="mail_target"][value="' + (deletedOnly ? 'original' : 'folder') + '"]').prop('checked', true);
    toggleSubOptions();
  }
  function checkedOverwrite() {
    if (tree.deletedOnly) return [];
    return $('#backup_restore_form .backup-overwrite .backup-component:checked:enabled').map(function() { return this.value; }).get();
  }
  // sub-items (Sieve activation) need their item; the mail target needs the mails; overwriting turns the button red
  function toggleSubOptions() {
    var $form = $('#backup_restore_form'), missing = noMails();
    $form.find('.backup-no-mail-note').toggleClass('d-none', !missing);
    $.each(MAIL_COMPONENTS, function(i, key) {
      var $item = $form.find('.backup-component[value="' + key + '"]');
      if (missing) $item.prop('checked', false);
      $item.prop('disabled', missing).closest('.form-check').toggleClass('text-muted', missing);
    });
    $form.find('.backup-component[data-parent]').each(function() {
      var parent = $form.find('.backup-component[value="' + $(this).data('parent') + '"]');
      var off = !parent.is(':checked') || (missing && MAIL_COMPONENTS.indexOf(this.value) !== -1);
      $(this).prop('disabled', off).closest('.form-check').toggleClass('text-muted', off);
    });
    var mails = $form.find('.backup-component[value="mails"]').is(':checked');
    $form.find('input[name="mail_target"]').prop('disabled', !mails);
    $form.find('.backup-mail-target').toggleClass('text-muted', !mails);
    var $submit = $form.find('.backup-restore-submit'), over = checkedOverwrite().length > 0;
    $submit.toggleClass('btn-danger', over).toggleClass('btn-success', !over).find('span').text($submit.data(over ? 'overwrite' : 'normal'));
  }
  function restoreError(text) {
    $('#backup_restore_form .backup-restore-error').toggleClass('d-none', !text).text(text || '');
  }
  function queuedAlert(id) {
    $('#backup_restore_page .backup-restore-queued').html('<div class="alert alert-success alert-dismissible py-2 small">' +
      '<i class="bi bi-check-circle-fill"></i> ' + esc(fill(L('queued_inline'), id)) +
      ' <a href="#" class="backup-job-details" data-job="' + esc(id) + '">' + esc(L('details')) + '</a>' +
      '<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="' + esc(L('close')) + '"></button></div>');
  }
  function submitRestore($button) {
    var $form = $('#backup_restore_form'), target = tree.target;
    var components = $form.find('.backup-component:checked:enabled').map(function() { return this.value; }).get();
    restoreError('');
    if (!target || !tree.snapshot) return;
    if (!components.length) {
      restoreError(L('no_components'));
      return;
    }
    var over = checkedOverwrite();
    if (over.length && !confirm(fill(L('overwrite_confirm'), $.map(over, function(c) { return L('overwrite_short_' + c) || c; }).join(', '), targetCount(target)))) return;
    var body = {snapshot: tree.snapshot, scope: target.scope, domain: target.domain, components: components,
      mail_target: $form.find('input[name="mail_target"]:checked').val() || 'folder'};
    if (target.scope === 'mailbox') body.mailboxes = $.map(target.mailboxes, function(m) { return m.username; });
    post('add/backup-restore', body, function(ok, messages) {
      if (ok) {
        $.each(messages, function(i, message) {
          if ($.isArray(message.msg) && message.msg[0] === 'backup_restore_added') queuedAlert(message.msg[1]);
        });
        $('#backupRestoreModal').modal('hide');
        setSelection(null);
        reloadTable('#backup_restores_table');
        return;
      }
      var errors = $.grep(messages, function(m) { return m && m.type !== 'success'; });
      restoreError(errors.length ? messageText(errors[0]) : L('load_error'));
    }, $button);
  }
  // the sticky toolbar below mailcow's sticky navbar
  function stickToolbar() {
    $('#backup_restore_page .backup-tree-toolbar').css('top', ($('nav.navbar.sticky-top').outerHeight() || 0) + 'px');
  }

  $(document).ready(function() {
    // mailcow's handler for page forms (button text "loading", no second submit, F5 off) is not for these AJAX forms
    $('#backup_settings, #backup_restore_form').off('submit');
    // job details and cancel: both pages
    $(document).on('click', '.backup-job-details', function(e) {
      e.preventDefault();
      showJob($(this).data('job'));
    });
    $(document).on('click', '.backup-job-cancel-row', function(e) {
      e.preventDefault();
      cancelJob($(this).data('job'), $(this).data('state'), $(this));
    });
    $('#backupJobModal').on('click', '.backup-job-cancel', function(e) {
      e.preventDefault();
      if (jobId !== null) cancelJob(jobId, $(this).data('state'), $(this));
    }).on('click', '.backup-job-refresh', function(e) {
      e.preventDefault();
      loadJob();
    }).on('hidden.bs.modal', function() {
      clearTimeout(jobTimer);
      jobId = null;
    });
    // "+"/"-" of the collapsible sections
    $(document).on('show.bs.collapse hide.bs.collapse', '#backup_advanced, #backup_snapshots_collapse', function(e) {
      if (e.target !== this) return;
      $('[data-bs-target="#' + this.id + '"] i').toggleClass('bi-plus-square', e.type === 'hide').toggleClass('bi-dash-square', e.type === 'show');
    }).on('shown.bs.collapse', '#backup_snapshots_collapse', function() {
      if ($.fn.DataTable.isDataTable('#backup_snapshots_table')) $('#backup_snapshots_table').DataTable().columns.adjust().responsive.recalc();
    });

    // admin: System > Configuration > Backup
    if ($('#tab-config-backup').length) {
      var loaded = false, listsLoaded = 0;
      onVisible('#backup_status', function() {
        if (!loaded) loadSettings();
        loaded = true;
        loadStatus();
        // the lists again when the tab is shown, not on every scroll
        if (now() - listsLoaded >= 10) {
          listsLoaded = now();
          loadJobs();
          loadSnapshotList();
        }
      });
      // links to the tab (e.g. from E-Mail > Restore)
      if (window.location.hash === '#tab-config-backup') {
        setTimeout(function() {
          $('[data-bs-target="#tab-config-backup"]').first().trigger('click');
          // phones: the tabs are collapsible cards
          $('#collapse-tab-config-backup').collapse('show');
        }, 0);
      }
      $('#backup_target').on('change', showTarget);
      $('#backup_settings input[name="include_index"]').on('change', toggleFts);
      $('#backup_settings').on('click', '.backup-schedule-add', function(e) {
        e.preventDefault();
        addScheduleEntry(true);
      }).on('keydown', '.backup-schedule-new input', function(e) {
        // Enter adds the entry instead of saving the form
        if (e.key === 'Enter') {
          e.preventDefault();
          addScheduleEntry(true);
        }
      }).on('change', '.backup-schedule-new input', function() {
        $(this).removeClass('inputMissingAttr').closest('.backup-schedule-new').find('label.btn').removeClass('inputMissingAttr');
      }).on('click', '.backup-schedule-remove', function(e) {
        e.preventDefault();
        $(this).closest('li').remove();
        drawSchedule(scheduleEntries());
      });
      // a stored secret: empty while focused (typing replaces it), dots again when left empty
      $('#backup_settings .backup-secret').on('focus', function() {
        if ($(this).data('masked')) $(this).val('').data('masked', false);
      }).on('input', function() {
        $(this).data('masked', false);
      }).on('blur', function() {
        if ($(this).data('stored') && $(this).val() === '') $(this).val(SECRET_MASK).data('masked', true);
      });
      $('#backup_settings input[name="password"]').on('input', function() {
        var $confirm = $('#backup_settings input[name="password_confirmed"]');
        if ($confirm.data('stored')) $confirm.prop('checked', false).data('stored', false);
        // a new password only works for a new, empty repository
        $('#backup_settings .backup-password-change').toggleClass('d-none', !($(this).data('stored') && $(this).val() !== ''));
      });
      $('#backup_settings').on('submit', function(e) {
        e.preventDefault();
        saveSettings($(this).find('.backup-settings-save'));
      });
      $('#backup_settings .backup-password-generate').on('click', function(e) {
        e.preventDefault();
        $('#backup_password').attr('type', 'text').val(generatePassword()).trigger('input');
      });
      $('#tab-config-backup').on('click', '.backup-copy', function(e) {
        e.preventDefault();
        copyField($(this).data('copy'));
      }).on('click', '.backup-job-request', function(e) {
        e.preventDefault();
        requestJob($(this));
      });
      $('#tab-config-backup .backup-refresh').on('click', function(e) {
        e.preventDefault();
        loadStatus();
        loadRunnerFields();
        loadJobs();
        loadSnapshotList();
      });
    }

    // E-Mail > Restore
    if ($('#backup_restore_page').length) {
      stickToolbar();
      $(window).on('resize', stickToolbar);
      loadSnapshots();
      drawRestores();
      $('#backup_restore_snapshot').on('change', function() {
        if ($(this).val() && $(this).val() !== tree.snapshot) selectSnapshot($(this).val());
      });
      var $tree = $('#backup_tree tbody');
      $tree.on('click', '.backup-tree-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var node = nodeOf($(this));
        if (node) toggleDomain(node);
      }).on('click', '.backup-tree-more', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var node = nodeOf($(this));
        if (node) {
          var first = node.shown;
          node.shown += CHUNK;
          renderDomain(node, first);
        }
      }).on('click', '.backup-tree-more-domains', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var first = tree.shownDomains;
        tree.shownDomains += CHUNK;
        renderTree();
        treeBody().find('tr.backup-tree-domain').eq(first).find('.backup-tree-toggle').trigger('focus');
      }).on('click', '.backup-tree-restore', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var node = nodeOf($(this)), m = $(this).closest('tr').hasClass('backup-tree-mailbox') ? mailboxOf($(this), node) : null;
        if (node) openRestore(m ? mailboxesTarget(node, [m.username]) : domainTarget(node));
      }).on('change', '.backup-tree-check', function() {
        var $row = $(this).closest('tr'), node = nodeOf($row);
        if (!node) return;
        if ($row.hasClass('backup-tree-domain')) checkDomain(node, this.checked);
        else checkMailbox(node, mailboxOf($row, node), this.checked);
      }).on('click', 'tr', function(e) {
        // mailcow selects a row's checkbox on a click into the row: the tree handles its rows itself
        e.stopPropagation();
        if ($(e.target).closest('input, label, button, a').length) return;
        var $row = $(this), node = nodeOf($row);
        if (!node) return;
        if ($row.hasClass('backup-tree-domain')) toggleDomain(node);
        else if ($row.hasClass('backup-tree-mailbox')) {
          var $check = $row.find('.backup-tree-check');
          if (!$check.prop('disabled')) $check.trigger('click');
        }
      });
      $('#backup_restore_page').on('input', '.backup-tree-search', function() {
        var value = String($(this).val()).trim().toLowerCase().replace(/\//g, '');
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
          tree.term = value;
          tree.replaced = null;
          runSearch();
        }, 300);
      }).on('change', '.backup-tree-deleted', function() {
        tree.onlyDeleted = this.checked;
        tree.shownDomains = CHUNK;
        renderTree();
      }).on('click', '.backup-selection-clear', function(e) {
        e.preventDefault();
        setSelection(null);
      }).on('click', '.backup-select-domain', function(e) {
        e.preventDefault();
        var sel = tree.selection;
        if (sel) setSelection({domain: sel.domain, scope: 'domain', mailboxes: {}});
      }).on('click', '.backup-selection-restore', function(e) {
        e.preventDefault();
        openRestore(selectionTarget());
      }).on('click', '.backup-table-refresh', function(e) {
        e.preventDefault();
        reloadTable('#' + $(this).data('table'));
      });
      $('#backup_restore_form').on('change', '.backup-component', toggleSubOptions).on('submit', function(e) {
        e.preventDefault();
        submitRestore($(this).find('.backup-restore-submit'));
      });
    }
  });

  return {
    showJob: showJob
  };
})(jQuery);
