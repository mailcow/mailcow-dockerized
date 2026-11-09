// Archive tab of the admin system page: header profile fields and, while archiving is enabled, the state of the archive
// under the switch (one line per problem; the details are in the archive log of the dashboard and in the queue manager).
var mailcowArchive = (function($) {
  'use strict';

  function L(key) {
    return (lang[key] !== undefined) ? lang[key] : key;
  }
  function esc(s) {
    return escapeHtml(s === null || s === undefined ? '' : String(s));
  }
  function time(ts) {
    return ts ? new Date(ts * 1000).toLocaleString(LOCALE, DATETIME_FORMAT) : '-';
  }
  // lang string with %s placeholders, values escaped
  function text(key, values) {
    var i = 0;
    return esc(L(key)).replace(/%s/g, function() { return esc(values[i++]); });
  }
  function notice(cls, html) {
    return '<div class="alert alert-' + cls + ' py-2 mb-2">' + html + '</div>';
  }
  var loaded = 0;

  function renderStatus(s) {
    var html = '';
    if (s.enabled && !s.configured) {
      html += notice('warning', esc(L('archive_state_unconfigured')));
    }
    if (s.dropped_24h) {
      html += notice('danger', text('archive_state_dropped', [s.dropped_24h, time(s.last_dropped.time), s.last_dropped.reason]));
    }
    if (s.queued) {
      var reasons = $.map(s.delay_reasons || [], function(r) { return '<li class="text-break">' + esc(r) + '</li>'; });
      html += notice('warning', text('archive_state_queued', [s.queued, time(s.oldest_queued)]) +
        (reasons.length ? '<ul class="mb-0 mt-1 small">' + reasons.join('') + '</ul>' : ''));
    }
    if (!s.enabled) {
      html += notice('secondary', esc(L('archive_state_off')));
    } else if (!html) {
      html += notice('success', s.last_delivery ? text('archive_state_ok', [time(s.last_delivery)]) : esc(L('archive_state_ok_none')));
    }
    $('#archive_state').html(html);
  }

  function load() {
    loaded = Date.now();
    $.get('/api/v1/get/archive/status', function(s) { renderStatus(s || {}); }, 'json').fail(function() {
      $('#archive_state').html(notice('danger', esc(lang_danger.generic_server_error)));
    });
  }

  // fields of the selected header profile only; hidden fields are not posted, so their stored values are kept
  function showProfile() {
    var profile = $('#archive_profile').val();
    $('.archive-profile').each(function() {
      var on = $(this).hasClass('archive-profile-' + profile);
      $(this).toggleClass('d-none', !on).find(':input').prop('disabled', !on);
    });
    $('.archive-profile-info').each(function() {
      $(this).toggleClass('d-none', !$(this).hasClass('archive-profile-info-' + profile));
    });
  }

  $(document).ready(function() {
    $('#archive_profile').on('change', showProfile);
    // the tests use the saved settings: not with unsaved changes in the form
    $('form[data-id="archive"]').on('input change', ':input', function() {
      $('.archive-test').prop('disabled', true);
      $('#archive_test_info').text(L('archive_save_first')).removeClass('text-muted').addClass('text-warning');
    });
    // the state line is only shown while archiving is enabled
    var tab = document.getElementById('collapse-tab-archive');
    if (!tab || !document.getElementById('archive_state')) return;
    // the tab body is at the top: visible as soon as the tab (or on mobile its card) is opened; load again when shown later
    new IntersectionObserver(function(entries) {
      if (entries.some(function(e) { return e.intersectionRatio > 0; }) && Date.now() - loaded > 10000) load();
    }).observe(tab);
    $('#archive_refresh').on('click', function(e) {
      e.preventDefault();
      load();
    });
    // open the dashboard with the archive log (the dashboard restores the last tab)
    $('#archive_log_link').on('click', function() {
      try {
        localStorage.setItem('lastTag', 'tab-archive-logs');
      } catch (e) {}
    });
  });

  return {load: load};
})(jQuery);
