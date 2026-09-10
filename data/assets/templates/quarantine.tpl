<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>Quarantine notification</title>
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <![endif]-->
  <style>
    :root { color-scheme: light dark; supported-color-schemes: light dark; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table { border-collapse: collapse !important; }
    img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
    a { text-decoration: none; }
    .msg-card td { word-break: break-word; }

    /* Mobile */
    @media only screen and (max-width: 600px) {
      .container { width: 100% !important; }
      .px { padding-left: 20px !important; padding-right: 20px !important; }
      .btn-a { display: block !important; text-align: center !important; }
      .btn-cell { display: block !important; width: 100% !important; padding: 4px 0 !important; }
    }

    /* Dark mode */
    @media (prefers-color-scheme: dark) {
      body, .bg-page { background-color: #16181d !important; }
      .bg-card { background-color: #21242b !important; }
      .text-main { color: #e7e9ee !important; }
      .text-muted { color: #a4a9b4 !important; }
      .border-card { border-color: #343842 !important; }
      .divider { border-color: #343842 !important; }
      .btn-primary-cell { background-color: #3a4350 !important; }
      .btn-secondary-cell { background-color: #2c313a !important; }
      .btn-secondary-cell a { color: #e7e9ee !important; }
    }
  </style>
</head>
<body class="bg-page" style="margin:0; padding:0; background-color:#f4f5f7;">
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f4f5f7;">
    {% if counter == 1 %}1 new message is waiting in your quarantine.{% else %}{{ counter }} new messages are waiting in your quarantine.{% endif %}
    &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-page" style="background-color:#f4f5f7;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="container" style="width:600px; max-width:600px;">

          <!-- Header -->
          <tr>
            <td style="padding:0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td class="px bg-card border-card" style="border-radius:12px 12px 0 0; background-color:#ffffff; border-bottom:1px solid #e4e7ec; padding:24px 32px;">
                    <span class="text-main" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:18px; font-weight:700; color:#1f2430; letter-spacing:0.2px;">
                      &#9993;&nbsp; Quarantine notification
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td class="bg-card" style="background-color:#ffffff; border-radius:0 0 12px 12px; padding:32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

                <!-- Greeting -->
                <tr>
                  <td class="px" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding-bottom:8px;">
                    <p class="text-main" style="margin:0 0 6px 0; font-size:16px; color:#1f2430;">Hi {{ username }},</p>
                    <p class="text-muted" style="margin:0; font-size:15px; line-height:22px; color:#5b6472;">
                      {% if counter == 1 %}
                      There is <b>1 new message</b> waiting in your quarantine.
                      {% else %}
                      There are <b>{{ counter }} new messages</b> waiting in your quarantine.
                      {% endif %}
                    </p>
                  </td>
                </tr>

                <!-- Messages -->
                {% for line in meta|reverse %}
                <tr>
                  <td style="padding-top:18px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="msg-card bg-card border-card" style="background-color:#ffffff; border:1px solid #e4e7ec; border-radius:10px;">
                      <tr>
                        <td style="padding:16px 18px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

                          <!-- Subject + status -->
                          <p class="text-main" style="margin:0 0 10px 0; font-size:15px; font-weight:600; line-height:21px; color:#1f2430;">{{ line.subject|e }}</p>

                          {% if line.action == "reject" %}
                          <span style="display:inline-block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#ffffff; background-color:#d64545; border-radius:6px; padding:3px 9px;">Rejected</span>
                          {% else %}
                          <span style="display:inline-block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#ffffff; background-color:#c2891b; border-radius:6px; padding:3px 9px;">Sent to Junk</span>
                          {% endif %}
                          <span class="text-muted" style="display:inline-block; font-size:12px; color:#8a93a2; padding-left:8px;">Score {{ line.score }}</span>

                          <!-- Meta -->
                          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
                            <tr>
                              <td class="text-muted" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:13px; line-height:20px; color:#5b6472;">
                                <span class="text-muted" style="color:#8a93a2;">From</span>&nbsp; {{ line.sender|e }}<br>
                                <span class="text-muted" style="color:#8a93a2;">Arrived</span>&nbsp; {{ line.created }}
                              </td>
                            </tr>
                          </table>

                          {% if quarantine_acl == 1 %}
                          <!-- Actions -->
                          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;">
                            <tr>
                              <td class="btn-cell" style="padding-right:8px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                  <tr>
                                    <td align="center" class="btn-primary-cell" style="border-radius:8px; background-color:#2b333f;">
                                      <a class="btn-a" href="https://{{ hostname }}/qhandler/release/{{ line.qhash }}" target="_blank" style="display:inline-block; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:600; color:#ffffff; padding:10px 18px; border-radius:8px;">{% if line.action == "reject" %}Release to inbox{% else %}Send copy to inbox{% endif %}</a>
                                    </td>
                                  </tr>
                                </table>
                              </td>
                              <td class="btn-cell">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                  <tr>
                                    <td align="center" class="btn-secondary-cell" style="border-radius:8px; background-color:#eceef1;">
                                      <a class="btn-a" href="https://{{ hostname }}/qhandler/delete/{{ line.qhash }}" target="_blank" style="display:inline-block; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:600; color:#2b333f; padding:10px 18px; border-radius:8px;">Delete</a>
                                    </td>
                                  </tr>
                                </table>
                              </td>
                            </tr>
                          </table>
                          {% endif %}

                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                {% endfor %}

                <!-- Footer note -->
                <tr>
                  <td class="px" style="padding-top:24px;">
                    <hr class="divider" style="border:0; border-top:1px solid #e4e7ec; margin:0 0 16px 0;">
                    <p class="text-muted" style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:18px; color:#8a93a2;">
                      This is an automated message from your mail server ({{ hostname }}). You receive it because quarantine notifications are enabled for your mailbox.
                    </p>
                  </td>
                </tr>

              </table>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
