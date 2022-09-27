$(document).ready(function() {
  // mailcow alert box generator
  window.mailcow_alert_box = function(message, type) {
    msg = $('<span/>').text(message).text();
    if (type == 'danger' || type == 'info') {
      auto_hide = 0;
      $('#' + localStorage.getItem("add_modal")).modal('show');
      localStorage.removeItem("add_modal");
    } else {
      auto_hide = 5000;
    }
    $.notify({message: msg},{z_index: 20000, delay: auto_hide, type: type,placement: {from: "bottom",align: "right"},animate: {enter: 'animated fadeInUp',exit: 'animated fadeOutDown'}});
  }

  $(".generate_password").click(function( event ) {
    event.preventDefault();
    $('[data-hibp]').trigger('input');
    if (typeof($(this).closest("form").data('pwgen-length')) == "number") {
      var random_passwd = GPW.pronounceable($(this).closest("form").data('pwgen-length'))
    }
    else {
      var random_passwd = GPW.pronounceable(8)
    }
    $(this).closest("form").find('[data-pwgen-field]').attr('type', 'text');
    $(this).closest("form").find('[data-pwgen-field]').val(random_passwd);
  });
  function str_rot13(str) {
    return (str + '').replace(/[a-z]/gi, function(s){
      return String.fromCharCode(s.charCodeAt(0) + (s.toLowerCase() < 'n' ? 13 : -13))
    })
  }
  $(".rot-enc").html(function(){
    return str_rot13($(this).html())
  });
  // https://stackoverflow.com/questions/4399005/implementing-jquerys-shake-effect-with-animate
  function shake(div,interval,distance,times) {
      if(typeof interval === 'undefined') {
        interval = 100;
      }
      if(typeof distance === 'undefined') {
        distance = 10;
      }
      if(typeof times === 'undefined') {
        times = 4;
      }
    $(div).css('position','relative');
    for(var iter=0;iter<(times+1);iter++){
      $(div).animate({ left: ((iter%2==0 ? distance : distance*-1))}, interval);
    }
    $(div).animate({ left: 0},interval);
  } 

  // form cache
  $('[data-cached-form="true"]').formcache({key: $(this).data('id')});

  //  tooltips
  $(function () {
    $('[data-bs-toggle="tooltip"]').tooltip()
  });

  // remember last navigation pill
  (function () {
    'use strict';
      // remember desktop tabs
      $('button[data-bs-toggle="tab"]').on('click', function (e) {
        if ($(this).data('dont-remember') == 1) {
          return true;
        }
        var id = $(this).parents('[role="tablist"]').attr('id');
        var key = 'lastTag';
        if (id) {
          key += ':' + id;
        }

        var tab_id = $(e.target).attr('data-bs-target').substring(1);
        localStorage.setItem(key, tab_id);
      });
      // remember mobile tabs
      $('button[data-bs-target^="#collapse-tab-"]').on('click', function (e) {
        // only remember tab if its being opened
        if ($(this).hasClass('collapsed')) return false;
        var tab_id = $(this).closest('div[role="tabpanel"]').attr('id');

        if ($(this).data('dont-remember') == 1) {
          return true;
        }
        var id = $(this).parents('[role="tablist"]').attr('id');;
        var key = 'lastTag';
        if (id) {
          key += ':' + id;
        }

        localStorage.setItem(key, tab_id);
      });
      // open last tab
      $('[role="tablist"]').each(function (idx, elem) {
        var id = $(elem).attr('id');
        var key = 'lastTag';
        if (id) {
          key += ':' + id;
        }
        var lastTab = localStorage.getItem(key);
        if (lastTab) {
          $('[data-bs-target="#' + lastTab + '"]').click();
          var tab = $('[id^="' + lastTab + '"]');
          $(tab).find('.card-body.collapse').collapse('show');
        }
      });
  })();

  // IE fix to hide scrollbars when table body is empty
  $('tbody').filter(function (index) {
    return $(this).children().length < 1;
  }).remove();

  // selectpicker
  $('select').selectpicker({
    'styleBase': 'btn btn-xs-lg',
    'noneSelectedText': lang_footer.nothing_selected
  });

  // haveibeenpwned and passwd policy
  $.ajax({
    url: '/api/v1/get/passwordpolicy/html',
    type: 'GET',
    success: function(res) {
      $(".hibp-out").after(res);
    }
  });
  $('[data-hibp]').after('<p class="small haveibeenpwned"><i class="bi bi-shield-fill-exclamation"></i> ' + lang_footer.hibp_check + '</p><span class="hibp-out"></span>');
  $('[data-hibp]').on('input', function() {
    out_field = $(this).next('.haveibeenpwned').next('.hibp-out').text('').attr('class', 'hibp-out');
  });
  $('.haveibeenpwned:not(.task-running)').on('click', function() {
    var hibp_field = $(this)
    $(hibp_field).addClass('task-running');
    var hibp_result = $(hibp_field).next('.hibp-out')
    var password_field = $(this).prev('[data-hibp]')
    if ($(password_field).val() == '') {
      shake(password_field);
    }
    else {
      $(hibp_result).attr('class', 'hibp-out badge fs-5 bg-info');
      $(hibp_result).text(lang_footer.loading);
      var password_digest = $.sha1($(password_field).val())
      var digest_five = password_digest.substring(0, 5).toUpperCase();
      var queryURL = "https://api.pwnedpasswords.com/range/" + digest_five;
      var compl_digest = password_digest.substring(5, 41).toUpperCase();
      $.ajax({
        url: queryURL,
        type: 'GET',
        success: function(res) {
          if (res.search(compl_digest) > -1){
            $(hibp_result).removeClass('badge fs-5 bg-info').addClass('badge fs-5 bg-danger');
            $(hibp_result).text(lang_footer.hibp_nok)
          } else {
            $(hibp_result).removeClass('badge fs-5 bg-info').addClass('badge fs-5 bg-success');
            $(hibp_result).text(lang_footer.hibp_ok)
          }
          $(hibp_field).removeClass('task-running');
        },
        error: function(xhr, status, error) {
          $(hibp_result).removeClass('badge fs-5 bg-info').addClass('badge fs-5 bg-warning');
          $(hibp_result).text('API error: ' + xhr.responseText)
          $(hibp_field).removeClass('task-running');
        }
      });
    }
  });

  // Disable disallowed inputs
  $('[data-acl="0"]').each(function(event){
    if ($(this).is("a")) {
      $(this).removeAttr("data-bs-toggle");
      $(this).removeAttr("data-bs-target");
      $(this).removeAttr("data-action");
      $(this).click(function(event) {
        event.preventDefault();
      });
    }
    if ($(this).is("select")) {
      $(this).selectpicker('destroy');
      $(this).replaceWith(function() {
        return '<label class="control-label"><b>' + this.innerText + '</b></label>';
      });
    }
    if ($(this).hasClass('btn-group')) {
      $(this).find('a').each(function(){
        $(this).removeClass('dropdown-toggle')
          .removeAttr('data-bs-toggle')
          .removeAttr('data-bs-target')
          .removeAttr('data-action')
          .removeAttr('id')
          .attr("disabled", true);
        $(this).click(function(event) {
          event.preventDefault();
          return;
        });
      });
      $(this).find('button').each(function() {
        $(this).attr("disabled", true);
      });
    } else if ($(this).hasClass('input-group')) {
      $(this).find('input').each(function() {
        $(this).removeClass('dropdown-toggle')
          .removeAttr('data-bs-toggle')
          .attr("disabled", true);
        $(this).click(function(event) {
          event.preventDefault();
        });
      });
      $(this).find('button').each(function() {
        $(this).attr("disabled", true);
      });
    } else if ($(this).hasClass('form-group')) {
      $(this).find('input').each(function() {
        $(this).attr("disabled", true);
      });
    } else if ($(this).hasClass('btn')) {
      $(this).attr("disabled", true);
    } else if ($(this).attr('data-provide') == 'slider') {
      $(this).attr('disabled', true);
    } else if ($(this).is(':checkbox')) {
      $(this).attr("disabled", true);
    }
    $(this).data("toggle", "tooltip");
    $(this).attr("title", lang_acl.prohibited);
    $(this).tooltip();
  });

  // disable submit after submitting form (not API driven buttons)
  $('form').submit(function() {
    if ($('form button[type="submit"]').data('submitted') == '1') {
      return false;
    } else {
      $(this).find('button[type="submit"]').first().text(lang_footer.loading);
      $('form button[type="submit"]').attr('data-submitted', '1');
      function disableF5(e) { if ((e.which || e.keyCode) == 116 || (e.which || e.keyCode) == 82) e.preventDefault(); };
      $(document).on("keydown", disableF5);
    }
  });
  // Textarea line numbers
  $(".textarea-code").numberedtextarea({allowTabChar: true});
  // trigger container restart
  $('#RestartContainer').on('show.bs.modal', function(e) {
    var container = $(e.relatedTarget).data('container');
    $('#containerName').text(container);
    $('#triggerRestartContainer').click(function(){
      $(this).prop("disabled",true);
      $(this).html('<div class="spinner-border text-white" role="status"><span class="visually-hidden">Loading...</span></div>');
      $('#statusTriggerRestartContainer').html(lang_footer.restarting_container);
      $.ajax({
        method: 'get',
        url: '/inc/ajax/container_ctrl.php',
        timeout: docker_timeout,
        data: {
        'service': container,
        'action': 'restart'
        }
      })
      .always( function (data, status) {
        $('#statusTriggerRestartContainer').append(data);
        var htmlResponse = $.parseHTML(data)
        if ($(htmlResponse).find('span').hasClass('text-success')) {
          $('#triggerRestartContainer').html('<i class="bi bi-check-lg"></i> ');
          setTimeout(function(){
            $('#RestartContainer').modal('toggle');
            window.location = window.location.href.split("#")[0];
          }, 1200);
        } else {
          $('#triggerRestartContainer').html('<i class="bi bi-slash-lg"></i> ');
        }
      })
    });
  })

  // Jquery Datatables, enable responsive plugin
  $.extend($.fn.dataTable.defaults, {
    responsive: true
  });

  // tag boxes
  $('.tag-box .tag-add').click(function(){
    addTag(this);
  });
  $(".tag-box .tag-input").keydown(function (e) {
    if (e.which == 13){
      e.preventDefault();
      addTag(this);
    } 
  });
  function addTag(tagAddElem){
    var tagboxElem = $(tagAddElem).parent();
    var tagInputElem = $(tagboxElem).find(".tag-input")[0];
    var tagValuesElem = $(tagboxElem).find(".tag-values")[0];

    var tag = escapeHtml($(tagInputElem).val());
    if (!tag) return;
    var value_tags = [];
    try {
      value_tags = JSON.parse($(tagValuesElem).val());
    } catch {}
    if (!Array.isArray(value_tags)) value_tags = [];
    if (value_tags.includes(tag)) return;

    $('<span class="badge bg-primary tag-badge btn-badge"><i class="bi bi-tag-fill"></i> ' + tag + '</span>').insertBefore('.tag-input').click(function(){
      var del_tag = unescapeHtml($(this).text());
      var del_tags = [];
      try {
        del_tags = JSON.parse($(tagValuesElem).val());
      } catch {}
      if (Array.isArray(del_tags)){
        del_tags.splice(del_tags.indexOf(del_tag), 1);
        $(tagValuesElem).val(JSON.stringify(del_tags));
      }
      $(this).remove();
    });

    value_tags.push($(tagInputElem).val());
    $(tagValuesElem).val(JSON.stringify(value_tags));
    $(tagInputElem).val('');
  }

  // Dark Mode Loader
  $('#dark-mode-toggle').click(toggleDarkMode);
  if ($('#dark-mode-theme').length) {
    $('#dark-mode-toggle').prop('checked', true);
    if ($('#rspamd_logo').length) $('#rspamd_logo').attr('src', '/img/rspamd_logo_light.png');
    if ($('#rspamd_logo_sm').length) $('#rspamd_logo_sm').attr('src', '/img/rspamd_logo_light.png');
  }
  function toggleDarkMode(){
    if($('#dark-mode-theme').length){
      $('#dark-mode-theme').remove();
      $('#dark-mode-toggle').prop('checked', false);
      if ($('#rspamd_logo').length) $('#rspamd_logo').attr('src', '/img/rspamd_logo_dark.png');
      if ($('#rspamd_logo_sm').length) $('#rspamd_logo_sm').attr('src', '/img/rspamd_logo_dark.png');
      localStorage.setItem('darkmode', 'false');
    }else{
      $('head').append('<link id="dark-mode-theme" rel="stylesheet" type="text/css" href="/css/themes/mailcow-darkmode.css">');
      $('#dark-mode-toggle').prop('checked', true);
      if ($('#rspamd_logo').length) $('#rspamd_logo').attr('src', '/img/rspamd_logo_light.png');
      if ($('#rspamd_logo_sm').length) $('#rspamd_logo_sm').attr('src', '/img/rspamd_logo_light.png');
      localStorage.setItem('darkmode', 'true');
    }
  }

  // show whats new modal
  if (mailcow_cc_role === "admin" || mailcow_cc_role === "domainadmin"){
    if (mailcow_info.updatedAt > last_login){
      var parsedSeenTimestamp = parseInt(localStorage.getItem("seenChangelog"));
      if (!isNaN(parsedSeenTimestamp) && mailcow_info.updatedAt < parsedSeenTimestamp) {
        console.log("changelog seen");
        return;
      }
      $.ajax({
        type: 'GET',
        url: 'https://api.github.com/repos/' + mailcow_info.project_owner + '/' + mailcow_info.project_repo + '/releases/tags/' + mailcow_info.version_tag,
        dataType: 'json',
        success: function (data) { 
          var md = window.markdownit();
          var result = md.render(data.body);
          result = parseGithubMarkdownLinks(result);

          $('#showWhatsNewModal').find(".modal-body").html(`
            <h3>` + data.name + `</h3>
            <span class="mt-4">` + result + `</span>
          `);

          localStorage.setItem("seenChangelog", Math.floor(Date.now() / 1000).toString());
        }
      });

      new bootstrap.Modal(document.getElementById("showWhatsNewModal"), {
        backdrop: 'static',
        keyboard: false
      }).show();
    }
  }

  function parseGithubMarkdownLinks(inputText) {
    var replacedText, replacePattern1;
  
    replacePattern1 = /(\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/gim;
    replacedText = inputText.replace(replacePattern1, (matched, index, original, input_string) => {
        if (matched.includes('github.com')){
          // return short link if it's github link
          last_uri_path = matched.split('/');
          last_uri_path = last_uri_path[last_uri_path.length - 1];

          // adjust Full Changelog link to match last git version and new git version, if link is a compare link
          if (matched.includes('/compare/') && mailcow_info.last_version_tag !== ''){
            matched = matched.replace(last_uri_path,  mailcow_info.last_version_tag + '...' + mailcow_info.version_tag);
            last_uri_path = mailcow_info.last_version_tag + '...' + mailcow_info.version_tag;
          }
          
          return '<a href="' + matched + '" target="_blank">' + last_uri_path + '</a><br>';
        };
  
        // if it's not a github link, return complete link
        return '<a href="' + matched + '" target="_blank">' + matched + '</a>'; 
    });
  
    return replacedText;
  }
});


// https://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
function escapeHtml(n){var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"}; return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
function unescapeHtml(t){var n={"&amp;":"&","&lt;":"<","&gt;":">","&quot;":'"',"&#39;":"'","&#x2F;":"/","&#x60;":"`","&#x3D;":"="};return String(t).replace(/&amp;|&lt;|&gt;|&quot;|&#39;|&#x2F|&#x60|&#x3D;/g,function(t){return n[t]})}
