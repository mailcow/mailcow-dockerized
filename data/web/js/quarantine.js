// Base64 functions
var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(r){var t,e,o,a,h,n,c,d="",C=0;for(r=Base64._utf8_encode(r);C<r.length;)a=(t=r.charCodeAt(C++))>>2,h=(3&t)<<4|(e=r.charCodeAt(C++))>>4,n=(15&e)<<2|(o=r.charCodeAt(C++))>>6,c=63&o,isNaN(e)?n=c=64:isNaN(o)&&(c=64),d=d+this._keyStr.charAt(a)+this._keyStr.charAt(h)+this._keyStr.charAt(n)+this._keyStr.charAt(c);return d},decode:function(r){var t,e,o,a,h,n,c="",d=0;for(r=r.replace(/[^A-Za-z0-9\+\/\=]/g,"");d<r.length;)t=this._keyStr.indexOf(r.charAt(d++))<<2|(a=this._keyStr.indexOf(r.charAt(d++)))>>4,e=(15&a)<<4|(h=this._keyStr.indexOf(r.charAt(d++)))>>2,o=(3&h)<<6|(n=this._keyStr.indexOf(r.charAt(d++))),c+=String.fromCharCode(t),64!=h&&(c+=String.fromCharCode(e)),64!=n&&(c+=String.fromCharCode(o));return c=Base64._utf8_decode(c)},_utf8_encode:function(r){r=r.replace(/\r\n/g,"\n");for(var t="",e=0;e<r.length;e++){var o=r.charCodeAt(e);o<128?t+=String.fromCharCode(o):o>127&&o<2048?(t+=String.fromCharCode(o>>6|192),t+=String.fromCharCode(63&o|128)):(t+=String.fromCharCode(o>>12|224),t+=String.fromCharCode(o>>6&63|128),t+=String.fromCharCode(63&o|128))}return t},_utf8_decode:function(r){for(var t="",e=0,o=c1=c2=0;e<r.length;)(o=r.charCodeAt(e))<128?(t+=String.fromCharCode(o),e++):o>191&&o<224?(c2=r.charCodeAt(e+1),t+=String.fromCharCode((31&o)<<6|63&c2),e+=2):(c2=r.charCodeAt(e+1),c3=r.charCodeAt(e+2),t+=String.fromCharCode((15&o)<<12|(63&c2)<<6|63&c3),e+=3);return t}};
jQuery(function($){
  // http://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
  var entityMap={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;","/":"&#x2F;","`":"&#x60;","=":"&#x3D;"};
  function escapeHtml(n){return String(n).replace(/[&<>"'`=\/]/g,function(n){return entityMap[n]})}
  function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}

  function draw_quarantine_table() {
    ft_quarantinetable = FooTable.init('#quarantinetable', {
      "columns": [
        {"name":"chkbox","title":"","style":{"maxWidth":"40px","width":"40px"},"filterable": false,"sortable": false,"type":"html"},
        {"name":"id","type":"ID","filterable": false,"sorted": true,"direction":"DESC","title":"ID","style":{"width":"50px"}},
        {"name":"qid","type":"text","title":lang.qid,"style":{"width":"125px"}},
        {"name":"sender","title":lang.sender,"breakpoints":"xs sm"},
        {"name":"rcpt","title":lang.rcpt, "type": "text"},
        {"name":"created","formatter":function unix_time_format(tm) { var date = new Date(tm ? tm * 1000 : 0); return date.toLocaleString();},"title":lang.received,"style":{"width":"170px"}},
        {"name":"action","filterable": false,"sortable": false,"style":{"text-align":"right"},"style":{"width":"220px"},"type":"html","title":lang.action,"breakpoints":"xs sm"}
      ],
      "rows": $.ajax({
        dataType: 'json',
        url: '/api/v1/get/quarantine/all',
        jsonp: false,
        error: function () {
          console.log('Cannot draw quarantine table');
        },
        success: function (data) {
          $.each(data, function (i, item) {
            item.action = '<div class="btn-group">' +
              '<a href="#" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-info show_qid_info"><span class="glyphicon glyphicon-modal-window"></span> ' + lang.show_item + '</a>' +
              '<a href="#" id="delete_selected" data-id="del-single-qitem" data-api-url="delete/qitem" data-item="' + encodeURI(item.id) + '" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> ' + lang.remove + '</a>' +
              '</div>';
            item.chkbox = '<input type="checkbox" data-id="qitems" name="multi_select" value="' + item.id + '" />';
          });
        }
      }),
      "empty": lang.empty,
      "paging": {"enabled": true,"limit": 5,"size": pagination_size},
      "sorting": {"enabled": true},
      "on": {
        "ready.ft.table": btn_group_quarantine,
        "after.ft.paging": btn_group_quarantine
      },
      "filtering": {"enabled": true,"position": "left","connectors": false,"placeholder": lang.filter_table},
    });
  }

  btn_group_quarantine = function(ev, ft){
    $('.show_qid_info').on('click', function (e) {
      e.preventDefault();
      var qitem = $(this).data('item');
      $('#qidDetailModal').modal('show');
      $( "#qid_error" ).hide();
      $.ajax({
        url: '/inc/ajax/qitem_details.php',
        data: { id: qitem },
        dataType: 'json',
        success: function(data){
          if (typeof data.error !== 'undefined') {
            $( "#qid_error" ).text(data.error);
            $( "#qid_error" ).show();
          }
          $('#qid_detail_subj').text(data.subject);
          $('#qid_detail_text').text(data.text_plain);
          $('#qid_detail_text_from_html').text(data.text_html);
          if (typeof data.attachments !== 'undefined') {
            $( "#qid_detail_atts" ).text('');
            $.each(data.attachments, function( index, value ) {
              $( "#qid_detail_atts" ).append(
                '<p><a href="/inc/ajax/qitem_details.php?id=' + qitem + '&att=' + index + '" target="_blank">' + value[0] + '</a> (' + value[1] + ')' +
                ' - <small><a href="' + value[3] + '" target="_blank">' + lang.check_hash + '</a></small></p>'
              );
            });
          }
          else {
            $( "#qid_detail_atts" ).text('-');
          }
        }
      });
    })
  }
  // Initial table drawings
  draw_quarantine_table();
});
