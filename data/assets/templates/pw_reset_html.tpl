<html>
  <head>
  <meta name="x-apple-disable-message-reformatting" />
  <style>
  body {
    font-family: Helvetica, Arial, Sans-Serif;
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
Hello {{username2}},<br><br>

Somebody requested a new password for the {{hostname}} account associated with {{username}}.<br>
<small>Date of the password reset request: {{date}}</small><br><br>

You can reset your password by clicking the link below:<br>
<a href="{{link}}">{{link}}</a><br><br>

The link will be valid for the next {{token_lifetime}} minutes.<br><br>

If you did not request a new password, please ignore this email.<br>
  </body>
</html>
