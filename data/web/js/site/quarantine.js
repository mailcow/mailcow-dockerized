// Base64 functions
var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(r){var t,e,o,a,h,n,c,d="",C=0;for(r=Base64._utf8_encode(r);C<r.length;)a=(t=r.charCodeAt(C++))>>2,h=(3&t)<<4|(e=r.charCodeAt(C++))>>4,n=(15&e)<<2|(o=r.charCodeAt(C++))>>6,c=63&o,isNaN(e)?n=c=64:isNaN(o)&&(c=64),d=d+this._keyStr.charAt(a)+this._keyStr.charAt(h)+this._keyStr.charAt(n)+this._keyStr.charAt(c);return d},decode:function(r){var t,e,o,a,h,n,c="",d=0;for(r=r.replace(/[^A-Za-z0-9\+\/\=]/g,"");d<r.length;)t=this._keyStr.indexOf(r.charAt(d++))<<2|(a=this._keyStr.indexOf(r.charAt(d++)))>>4,e=(15&a)<<4|(h=this._keyStr.indexOf(r.charAt(d++)))>>2,o=(3&h)<<6|(n=this._keyStr.indexOf(r.charAt(d++))),c+=String.fromCharCode(t),64!=h&&(c+=String.fromCharCode(e)),64!=n&&(c+=String.fromCharCode(o));return c=Base64._utf8_decode(c)},_utf8_encode:function(r){r=r.replace(/\r\n/g,"\n");for(var t="",e=0;e<r.length;e++){var o=r.charCodeAt(e);o<128?t+=String.fromCharCode(o):o>127&&o<2048?(t+=String.fromCharCode(o>>6|192),t+=String.fromCharCode(63&o|128)):(t+=String.fromCharCode(o>>12|224),t+=String.fromCharCode(o>>6&63|128),t+=String.fromCharCode(63&o|128))}return t},_utf8_decode:function(r){for(var t="",e=0,o=c1=c2=0;e<r.length;)(o=r.charCodeAt(e))<128?(t+=String.fromCharCode(o),e++):o>191&&o<224?(c2=r.charCodeAt(e+1),t+=String.fromCharCode((31&o)<<6|63&c2),e+=2):(c2=r.charCodeAt(e+1),c3=r.charCodeAt(e+2),t+=String.fromCharCode((15&o)<<12|(63&c2)<<6|63&c3),e+=3);return t}};

jQuery(function($){
  acl_data = JSON.parse(acl);
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}
  $(".refresh_table").on('click', function(e) {
    e.preventDefault();
    var table_name = $(this).data('table');
    $('#' + table_name).DataTable().ajax.reload();
  });
  function draw_quarantine_table() {
    var table = $('#quarantinetable').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      pageLength: pagination_size,
      order: [[2, 'desc']],
      lengthMenu: [
        [10, 25, 50, 100, -1],
        [10, 25, 50, 100, 'all']
      ],
      pagingType: 'first_last_numbers',
      aColumns: [
        { sWidth: '8.25%' },
        { sClass: 'classDataTable' }
      ],
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      initComplete: function(){
        hideTableExpandCollapseBtn('#quarantinetable');
      },
      ajax: {
        type: "GET",
        url: "/api/v1/get/quarantine/all",
        dataSrc: function(data){
          $.each(data, function (i, item) {
            if (item.subject === null) {
              item.subject = '';
            } else {
              item.subject = escapeHtml(item.subject);
            }
            if (item.score === null) {
              item.score = '-';
            }
            if (item.virus_flag > 0) {
              item.virus = '<span class="badge fs-6 bg-danger">' + lang.high_danger + '</span>';
            } else {
              item.virus = '<span class="badge fs-6 bg-secondary">' + lang.neutral_danger + '</span>';
            }
            if (item.action === "reject") {
              item.rspamdaction = '<span class="badge fs-6 bg-danger">' + lang.rejected + '</span>';
            } else if (item.action === "add header") {
              item.rspamdaction = '<span class="badge fs-6 bg-warning">' + lang.junk_folder + '</span>';
            } else if (item.action === "rewrite subject") {
              item.rspamdaction = '<span class="badge fs-6 bg-warning">' + lang.rewrite_subject + '</span>';
            }
            if(item.notified > 0) {
              item.notified = '&#10004;';
            } else {
              item.notified = '&#10006;';
            }
            if (acl_data.login_as === 1) {
            item.action = '<div class="btn-group">' +
              '<a href="#" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-xs-half btn-info show_qid_info"><i class="bi bi-box-arrow-up-right"></i> ' + lang.show_item + '</a>' +
              '<a href="#" data-action="delete_selected" data-id="del-single-qitem" data-api-url="delete/qitem" data-item="' + encodeURI(item.id) + '" class="btn btn-xs  btn-xs-half btn-danger"><i class="bi bi-trash"></i> ' + lang.remove + '</a>' +
              '</div>';
            }
            else {
            item.action = '<div class="btn-group">' +
              '<a href="#" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-info show_qid_info"><i class="bi bi-file-earmark-text"></i> ' + lang.show_item + '</a>' +
              '</div>';
            }
            item.chkbox = '<input type="checkbox" class="form-check-input" data-id="qitems" name="multi_select" value="' + item.id + '" />';
          });

          return data;
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
          data: 'chkbox',
          searchable: false,
          orderable: false,
          defaultContent: ''
        },
        {
          title: 'ID',
          data: 'id',
          defaultContent: ''
        },
        {
          title: lang.qid,
          data: 'qid',
          defaultContent: ''
        },
        {
          title: lang.sender,
          data: 'sender',
          className: 'senders-mw220',
          defaultContent: ''
        },
        {
          title: lang.subj,
          data: 'subject',
          defaultContent: ''
        },
        {
          title: lang.rspamd_result,
          data: 'rspamdaction',
          defaultContent: ''
        },
        {
          title: lang.rcpt,
          data: 'rcpt',
          defaultContent: ''
        },
        {
          title: lang.danger,
          data: 'virus',
          defaultContent: ''
        },
        {
          title: lang.spam_score,
          data: 'score',
          defaultContent: ''
        },
        {
          title: lang.notified,
          data: 'notified',
          defaultContent: ''
        },
        {
          title: lang.received,
          data: 'created',
          defaultContent: '',
          createdCell: function(td, cellData) {
            $(td).attr({
              "data-order": cellData,
              "data-sort": cellData
            });

            var date = new Date(cellData ? cellData * 1000 : 0);
            var dateString = date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
            $(td).html(dateString);
          }
        },
        {
          title: lang.action,
          data: 'action',
          className: 'dt-text-right dt-sm-head-hidden',
          defaultContent: ''
        },
      ]
    });

    table.on('responsive-resize', function (e, datatable, columns){
      hideTableExpandCollapseBtn('#quarantinetable');
    });
  }

  $('body').on('click', '.show_qid_info', function (e) {
    e.preventDefault();
    var qitem = $(this).attr('data-item');
    var qError = $("#qid_error");

    $('#qidDetailModal').modal('show');
    qError.hide();

    $.ajax({
      url: '/inc/ajax/qitem_details.php',
      data: { id: qitem },
      dataType: 'json',
      success: function(data){

        $('[data-id="qitems_single"]').each(function(index) {
          $(this).attr("data-item", qitem);
        });

        $("#quick_download_link").attr("onclick", "window.open('/inc/ajax/qitem_details.php?id=" + qitem + "&eml', '_blank')");
        $("#quick_release_link").attr("onclick", "window.open('/inc/ajax/qitem_details.php?id=" + qitem + "&quick_release', '_blank')");
        $("#quick_delete_link").attr("onclick", "window.open('/inc/ajax/qitem_details.php?id=" + qitem + "&quick_delete', '_blank')");

        $('#qid_detail_subj').text(data.subject);
        $('#qid_detail_hfrom').text(data.header_from);
        $('#qid_detail_efrom').text(data.env_from);
        $('#qid_detail_score').html('');
        $('#qid_detail_recipients').html('');
        $('#qid_detail_symbols').html('');
        $('#qid_detail_fuzzy').html('');
        if (typeof data.symbols !== 'undefined') {
          data.symbols.sort(function (a, b) {
            if (a.score === 0) return 1;
            if (b.score === 0) return -1;
            if (b.score < 0 && a.score < 0) {
              return a.score - b.score;
            }
            if (b.score > 0 && a.score > 0) {
              return b.score - a.score;
            }
            return b.score - a.score;
          })
          $.each(data.symbols, function (index, value) {
            var highlightClass = '';
            if (value.score > 0) highlightClass = 'negative';
            else if (value.score < 0) highlightClass = 'positive';
            else highlightClass = 'neutral';
            $('#qid_detail_symbols').append('<span data-bs-toggle="tooltip" class="rspamd-symbol ' + highlightClass + '" title="' + (value.options ? escapeHtml(value.options.join(', ')) : '') + '">' + value.name + ' (<span class="score">' + value.score + '</span>)</span>');
          });
          $('[data-bs-toggle="tooltip"]').tooltip();
        }
        if (typeof data.fuzzy_hashes === 'object' && data.fuzzy_hashes !== null && data.fuzzy_hashes.length !== 0) {
          $.each(data.fuzzy_hashes, function (index, value) {
            $('#qid_detail_fuzzy').append('<p style="font-family:monospace">' + value + '</p>');
          });
        } else {
          $('#qid_detail_fuzzy').append('-');
        }
        if (typeof data.score !== 'undefined' && typeof data.action !== 'undefined') {
          if (data.action == "add header") {
            $('#qid_detail_score').append('<span class="label-rspamd-action badge fs-6 bg-warning"><b>' + data.score + '</b> - ' + lang.junk_folder + '</span>');
          } else if (data.action == "reject") {
            $('#qid_detail_score').append('<span class="label-rspamd-action badge fs-6 bg-danger"><b>' + data.score + '</b> - ' + lang.rejected + '</span>');
          } else if (data.action == "rewrite subject") {
            $('#qid_detail_score').append('<span class="label-rspamd-action badge fs-6 bg-warning"><b>' + data.score + '</b> - ' + lang.rewrite_subject + '</span>');
          }
        }
        if (typeof data.recipients !== 'undefined') {
          $.each(data.recipients, function(index, value) {
            var elem = $('<span class="mail-address-item"></span>');
            elem.text(value.address + ' (' + value.type.toUpperCase() + ')');
            $('#qid_detail_recipients').append(elem);
          });
        }
        $('#qid_detail_text').text(data.text_plain);
        $('#qid_detail_text_from_html').text(data.text_html);
        var qAtts = $("#qid_detail_atts");
        if (typeof data.attachments !== 'undefined') {
          qAtts.text('');
          $.each(data.attachments, function(index, value) {
            qAtts.append(
              '<p><a href="/inc/ajax/qitem_details.php?id=' + qitem + '&att=' + index + '" target="_blank">' + value[0] + '</a> (' + value[1] + ')' +
              ' - <small><a href="' + value[3] + '" target="_blank">' + lang.check_hash + '</a></small></p>'
            );
          });
        }
        else {
          qAtts.text('-');
        }
      },
      error: function(data){
        if (typeof data.error !== 'undefined') {
          $('#qid_detail_subj').text('-');
          $('#qid_detail_hfrom').text('-');
          $('#qid_detail_efrom').text('-');
          $('#qid_detail_score').html('-');
          $('#qid_detail_recipients').html('-');
          $('#qid_detail_symbols').html('-');
          $('#qid_detail_fuzzy').html('-');
          $('#qid_detail_text').text('-');
          $('#qid_detail_text_from_html').text('-');
          qError.text("Error loading quarantine item");
          qError.show();
        }
      }
    });
  });

  $('body').on('click', 'span.footable-toggle', function () {
    event.stopPropagation();
  })

  // Initial table drawings
  draw_quarantine_table();

  function hideTableExpandCollapseBtn(table){
    if ($(table).hasClass('collapsed'))
      $(".table_collapse_option").show();
    else
      $(".table_collapse_option").hide();
  }
});




