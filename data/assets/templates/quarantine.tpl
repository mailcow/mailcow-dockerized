<html>
  <head>
  <meta name="x-apple-disable-message-reformatting" />
  <style>
  body {
    font-family: Helvetica, Arial, Sans-Serif;
  }
  table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 20px;
  }
  th, td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    vertical-align: top;
  }
  td.fixed {
    white-space: nowrap;
  }
  th {
    background-color: #56B04C;
    color: white;
  }
  tr:nth-child(even) {
    background-color: #f2f2f2;
  }
  .label {
    display: inline;
    padding: .2em .6em .3em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .25em;
    font-size: medium;
  }
  .label-warning {
    background-color: #ff851b;
  }
  .label-danger {
    background-color: #ff4136;
  }
  /* mobile devices */
  @media all and (max-width: 480px) {
    .mob {
    display: none;
    }    
  }
  </style>
  </head>
  <body>
    <p>Hi {{username}}!<br>
    This is a notification from the mail server.<br><br>
    {% if counter == 1 %}
    We have withheld one message for you because we think it is spam:<br>
    How would you like to process this message?<br>
    {% else %}
    We have withheld {{counter}} messages for you because we think it is spam:<br>
    How would you like to process those messages?<br>
    {% endif %}
   You can simply ignore this message. But if you also think it is spam we encourage you to train our spam filter by clicking "delete" below:<br>
    <table>
    <tr><th>Subject</th><th>Sender</th><th class="mob">Score</th><th class="mob">Action</th><th class="mob">Arrived on</th>{% if quarantine_acl == 1 %}<th>Actions</th>{% endif %}</tr>
    {% for line in meta|reverse %}
    <tr>
    <td>{{ line.subject|e }}</td>
    <td>{{ line.sender|e }}</td>
    <td class="mob">{{ line.score }}</td>
    {% if line.action == "reject" %}
      <td class="mob">Rejected</td>
    {% else %}
      <td class="mob">Sent to Junk folder</td>
    {% endif %}
    <td class="mob">{{ line.created }}</td>
    {% if quarantine_acl == 1 %}
      {% if line.action == "reject" %}
        <td class="fixed"><a href="https://{{ hostname }}/qhandler/release/{{ line.qhash }}">Release to inbox</a> | <a href="https://{{ hostname }}/qhandler/delete/{{ line.qhash }}">delete</a></td>
      {% else %}
        <td class="fixed"><a href="https://{{ hostname }}/qhandler/release/{{ line.qhash }}">Send copy to inbox</a> | <a href="https://{{ hostname }}/qhandler/delete/{{ line.qhash }}">delete</a></td>
      {% endif %}
    {% endif %}
    </tr>
    {% endfor %}
    </table>
    </p>
    <p>
    <b>Explanation:</b><br>
    {% if counter == 1 %}
    On the page accessible through the links you will see the assessment of the withheld message.
    {% else %}
    On the page accessible through the links you can see the estimation of the withheld messages.
    {% endif %}
    </p>
    <p><div class="label label-warning">Score: 9.91 - Junk folder</div> for example would mean that the mail is already in your spam folder. By "Release to inbox" you can then still have a copy of the mail be delivered to your inbox.</p>
    <p><div class="label label-danger">Score: 99.9 - Reject</div> for example would mean that the sender has been notified that the mail has not been delivered. But you can still have it delivered to your inbox.</p>
  </body>
</html>
