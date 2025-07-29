import smtplib
import json
import os
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from jinja2 import Environment, BaseLoader

class Mailer:
    def __init__(self, smtp_host, smtp_port, username, password, use_tls=True):
        self.smtp_host = smtp_host
        self.smtp_port = smtp_port
        self.username = username
        self.password = password
        self.use_tls = use_tls
        self.server = None
        self.env = Environment(loader=BaseLoader())

    def connect(self):
        print("Connecting to the SMTP server...")
        self.server = smtplib.SMTP(self.smtp_host, self.smtp_port)
        if self.use_tls:
            self.server.starttls()
            print("TLS activated!")
        if self.username and self.password:
            self.server.login(self.username, self.password)
            print("Authenticated!")

    def disconnect(self):
        if self.server:
            try:
                if self.server.sock:
                    self.server.quit()
            except smtplib.SMTPServerDisconnected:
                pass
            finally:
                self.server = None

    def render_inline_template(self, template_string, context):
        template = self.env.from_string(template_string)
        return template.render(context)

    def send_mail(self, subject, from_addr, to_addrs, template, context = {}):
        try:
            if template == "":
                print("Cannot send email, template is empty!")
                return "Failed: Template is empty."

            body = self.render_inline_template(template, context)

            msg = MIMEMultipart()
            msg['From'] = from_addr
            msg['To'] = ', '.join(to_addrs) if isinstance(to_addrs, list) else to_addrs
            msg['Subject'] = subject
            msg.attach(MIMEText(body, 'html'))

            self.connect()
            self.server.sendmail(from_addr, to_addrs, msg.as_string())
            self.disconnect()
            return f"Success: Email sent to {msg['To']}"
        except Exception as e:
            print(f"Error during send_mail: {type(e).__name__}: {e}")
            return f"Failed: {type(e).__name__}: {e}"
        finally:
            self.disconnect()
