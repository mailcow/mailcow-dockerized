<html>
  <head>
  <style>
  body {
    font-family: sans-serif;
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
  </style>
  </head>
  <body>
    <p>Hi!<br>
    There are {{counter}} new messages waiting in quarantine:<br>
    <table>
    <tr><th>Subject</th><th>Sender</th><th>Arrived on</th></tr>
    {% for line in meta %}
    <tr>
    <td>{{ line.subject|e }}</td>
    <td>{{ line.sender|e }}</td>
    <td>{{ line.created }}</td>
    </tr>
    {% endfor %}
    </table>
    </p>
  </body>
</html>
