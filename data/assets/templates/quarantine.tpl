<html>
  <head>
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
  th {
    background-color: #56B04C;
    color: white;
  }
  tr:nth-child(even){background-color: #f2f2f2}

  </style>
  </head>
  <body>
    <p>Hi!<br>
    {% if counter == 1 %}
    There is 1 new message waiting in quarantine:<br>
    {% else %}
    There are {{counter}} new messages waiting in quarantine:<br>
    {% endif %}
    <table>
    <tr><th>Subject</th><th>Sender</th><th>Score</th><th>Arrived on</th>{% if quarantine_acl == 1 %}<th>Actions</th>{% endif %}</tr>
    {% for line in meta %}
    <tr>
    <td>{{ line.subject|e }}</td>
    <td>{{ line.sender|e }}</td>
    <td>{{ line.score }}</td>
    <td>{{ line.created }}</td>
    {% if quarantine_acl == 1 %}
    <td><a href="https://{{ hostname }}/qhandler/release/{{ line.qhash }}">release</a> | <a href="https://{{ hostname }}/qhandler/delete/{{ line.qhash }}">delete</a></td>
    {% endif %}
    </tr>
    {% endfor %}
    </table>
    </p>
  </body>
</html>
