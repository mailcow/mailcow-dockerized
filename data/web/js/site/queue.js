jQuery(function($){

    $(".refresh_table").on('click', function(e) {
      e.preventDefault();
      var table_name = $(this).data('table');
      $('#' + table_name).DataTable().ajax.reload();
    });


    function humanFileSize(i){if(Math.abs(i)<1024)return i+" B";var B=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],e=-1;do{i/=1024,++e}while(Math.abs(i)>=1024&&e<B.length-1);return i.toFixed(1)+" "+B[e]}

    // Queue item
    $('#showQueuedMsg').on('show.bs.modal', function (e) {
      $('#queue_msg_content').text(lang.loading);
      button = $(e.relatedTarget)
      if (button != null) {
        $('#queue_id').text(button.data('queue-id'));
      }
      $.ajax({
          type: 'GET',
          url: '/api/v1/get/postcat/' + button.data('queue-id'),
          dataType: 'text',
          complete: function (data) {
            console.log(data);
            $('#queue_msg_content').text(data.responseText);
          }
      });
    })

    function draw_queue() {
    // just recalc width if instance already exists
    if ($.fn.DataTable.isDataTable('#queuetable') ) {
      $('#queuetable').DataTable().columns.adjust().responsive.recalc();
      return;
    }

    $('#queuetable').DataTable({
			responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
           "tr" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: lang_datatables,
      ajax: {
        type: "GET",
        url: "/api/v1/get/mailq/all",
        dataSrc: function(data){
          $.each(data, function (i, item) {
            item.chkbox = '<input type="checkbox" data-id="mailqitems" name="multi_select" value="' + item.queue_id + '" />';
            rcpts = $.map(item.recipients, function(i) {
              return escapeHtml(i);
            });
            item.recipients = rcpts.join('<hr style="margin:1px!important">');
            item.action = '<div class="btn-group">' +
              '<a href="#" data-bs-toggle="modal" data-bs-target="#showQueuedMsg" data-queue-id="' + encodeURI(item.queue_id) + '" class="btn btn-xs btn-secondary">' + lang.queue_show_message + '</a>' +
            '</div>';
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
            title: 'QID',
            data: 'queue_id',
            defaultContent: ''
          },
          {
            title: 'Queue',
            data: 'queue_name',
            defaultContent: ''
          },
          {
            title: lang_admin.arrival_time,
            data: 'arrival_time',
            defaultContent: '',
            render: function (data, type){
              var date = new Date(data ? data * 1000 : 0); 
              return date.toLocaleDateString(undefined, {year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit"});
            }
          },
          {
            title: lang_admin.message_size,
            data: 'message_size',
            defaultContent: '',
            render: function (data, type){
              return humanFileSize(data);
            }
          },
          {
            title: lang_admin.sender,
            data: 'sender',
            defaultContent: ''
          },
          {
            title: lang_admin.recipients,
            data: 'recipients',
            defaultContent: ''
          },
          {
            title: lang_admin.action,
            data: 'action',
            className: 'text-md-end dt-sm-head-hidden dt-body-right',
            defaultContent: ''
          },
      ]
    });
  }

  draw_queue();

})