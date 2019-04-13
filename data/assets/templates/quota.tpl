<html>
  <head>
  <style>
  body {
    font-family: sans-serif;
  }
  #progressbar {
    background-color: #f0f0f0;
    border-radius: 0px;
    padding: 0px;
    width:50%;
  }  
  #progressbar > div {
    background-color: #ff9c9c;
    width: {{percent}}%;
    height: 20px;
    border-radius: 0px;
  }
  </style>
  </head>
  <body>
    <p>Hi {{username}}!<br><br>
    Your mailbox is now {{percent}}% full, please consider deleting old messages to still be able to receive new mails in the future.<br>
    <div id="progressbar">
      <div></div>
    </div>
    </p>
  </body>
</html>
