import json
from models.BaseModel import BaseModel
from modules.Mailer import Mailer

class MailerModel(BaseModel):
    parser_command = "mail"
    required_args = {
        "send": [["sender", "recipient", "subject", "body"]]
    }

    def __init__(
        self,
        sender=None,
        recipient=None,
        subject=None,
        body=None,
        context=None,
        **kwargs
    ):
        self.sender = sender
        self.recipient = recipient
        self.subject = subject
        self.body = body
        self.context = context

    def send(self):
        if self.context is not None:
            try:
                self.context = json.loads(self.context)
            except json.JSONDecodeError as e:
                return f"Invalid context JSON: {e}"
        else:
            self.context = {}

        mailer = Mailer(
            smtp_host="postfix-mailcow",
            smtp_port=25,
            username=self.sender,
            password="",
            use_tls=True
        )
        res = mailer.send_mail(
            subject=self.subject,
            from_addr=self.sender,
            to_addrs=self.recipient.split(","),
            template=self.body,
            context=self.context
        )
        return res

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Send emails via SMTP"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: send")
        parser.add_argument("--sender", required=True, help="Email sender address")
        parser.add_argument("--recipient", required=True, help="Email recipient address (comma-separated for multiple)")
        parser.add_argument("--subject", required=True, help="Email subject")
        parser.add_argument("--body", required=True, help="Email body (Jinja2 template supported)")
        parser.add_argument("--context", help="Context for Jinja2 template rendering (JSON format)")