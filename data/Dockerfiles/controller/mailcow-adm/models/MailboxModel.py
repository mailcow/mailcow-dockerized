from modules.Mailcow import Mailcow
from models.BaseModel import BaseModel

class MailboxModel(BaseModel):
    parser_command = "mailbox"
    required_args = {
        "add": [["username", "password"]],
        "delete": [["username"]],
        "get":  [["username"]],
        "edit": [["username"]]
    }

    def __init__(
        self,
        password=None,
        username=None,
        domain=None,
        local_part=None,
        active=None,
        sogo_access=None,
        name=None,
        authsource=None,
        quota=None,
        force_pw_update=None,
        tls_enforce_in=None,
        tls_enforce_out=None,
        tags=None,
        sender_acl=None,
        **kwargs
    ):
        self.mailcow = Mailcow()

        if username is not None and "@" in username:
            self.username = username
            self.local_part, self.domain = username.split("@")
        else:
            self.username = f"{local_part}@{domain}"
            self.local_part = local_part
            self.domain = domain

        self.password = password
        self.password2 = password
        self.active = active
        self.sogo_access = sogo_access
        self.name = name
        self.authsource = authsource
        self.quota = quota
        self.force_pw_update = force_pw_update
        self.tls_enforce_in = tls_enforce_in
        self.tls_enforce_out = tls_enforce_out
        self.tags = tags
        self.sender_acl = sender_acl

    @classmethod
    def from_dict(cls, data):
        return cls(
            domain=data.get("domain"),
            local_part=data.get("local_part"),
            password=data.get("password"),
            password2=data.get("password"),
            active=data.get("active", None),
            sogo_access=data.get("sogo_access", None),
            name=data.get("name", None),
            authsource=data.get("authsource", None),
            quota=data.get("quota", None),
            force_pw_update=data.get("force_pw_update", None),
            tls_enforce_in=data.get("tls_enforce_in", None),
            tls_enforce_out=data.get("tls_enforce_out", None),
            tags=data.get("tags", None),
            sender_acl=data.get("sender_acl", None)
        )

    def getAdd(self):
        """
        Get the mailbox details as a dictionary for adding, sets default values.
        :return: Dictionary containing mailbox details.
        """

        mailbox = {
            "domain": self.domain,
            "local_part": self.local_part,
            "password": self.password,
            "password2": self.password2,
            "active": self.active if self.active is not None else 1,
            "name": self.name if self.name is not None else "",
            "authsource": self.authsource if self.authsource is not None else "mailcow",
            "quota": self.quota if self.quota is not None else 0,
            "force_pw_update": self.force_pw_update if self.force_pw_update is not None else 0,
            "tls_enforce_in": self.tls_enforce_in if self.tls_enforce_in is not None else 0,
            "tls_enforce_out": self.tls_enforce_out if self.tls_enforce_out is not None else 0,
            "tags": self.tags if self.tags is not None else []
        }
        return {key: value for key, value in mailbox.items() if value is not None}

    def getEdit(self):
        """
        Get the mailbox details as a dictionary for editing, sets no default values.
        :return: Dictionary containing mailbox details.
        """

        mailbox = {
            "domain": self.domain,
            "local_part": self.local_part,
            "password": self.password,
            "password2": self.password2,
            "active": self.active,
            "name": self.name,
            "authsource": self.authsource,
            "quota": self.quota,
            "force_pw_update": self.force_pw_update,
            "tls_enforce_in": self.tls_enforce_in,
            "tls_enforce_out": self.tls_enforce_out,
            "tags": self.tags
        }
        return {key: value for key, value in mailbox.items() if value is not None}

    def get(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getMailbox(self.username)

    def delete(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.deleteMailbox(self.username)

    def add(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.addMailbox(self.getAdd())

    def edit(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.editMailbox(self.username, self.getEdit())

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage mailboxes (add, delete, get, edit)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, edit")
        parser.add_argument("--username", help="Full email address of the mailbox (e.g. user@example.com)")
        parser.add_argument("--password", help="Password for the mailbox (required for add)")
        parser.add_argument("--active", choices=["1", "0"], help="Activate (1) or deactivate (0) the mailbox")
        parser.add_argument("--sogo-access", choices=["1", "0"], help="Redirect mailbox to SOGo after web login (1 = yes, 0 = no)")
        parser.add_argument("--name", help="Display name of the mailbox owner")
        parser.add_argument("--authsource", help="Authentication source (default: mailcow)")
        parser.add_argument("--quota", help="Mailbox quota in bytes (0 = unlimited)")
        parser.add_argument("--force-pw-update", choices=["1", "0"], help="Force password update on next login (1 = yes, 0 = no)")
        parser.add_argument("--tls-enforce-in", choices=["1", "0"], help="Enforce TLS for incoming emails (1 = yes, 0 = no)")
        parser.add_argument("--tls-enforce-out", choices=["1", "0"], help="Enforce TLS for outgoing emails (1 = yes, 0 = no)")
        parser.add_argument("--tags", help="Comma-separated list of tags for the mailbox")
        parser.add_argument("--sender-acl", help="Comma-separated list of allowed sender addresses for this mailbox")

