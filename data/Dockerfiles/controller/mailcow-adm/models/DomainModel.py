from modules.Mailcow import Mailcow
from models.BaseModel import BaseModel

class DomainModel(BaseModel):
    parser_command = "domain"
    required_args = {
        "add": [["domain"]],
        "delete": [["domain"]],
        "get":  [["domain"]],
        "edit": [["domain"]]
    }

    def __init__(
        self,
        domain=None,
        active=None,
        aliases=None,
        backupmx=None,
        defquota=None,
        description=None,
        mailboxes=None,
        maxquota=None,
        quota=None,
        relay_all_recipients=None,
        rl_frame=None,
        rl_value=None,
        restart_sogo=None,
        tags=None,
        **kwargs
    ):
        self.mailcow = Mailcow()

        self.domain = domain
        self.active = active
        self.aliases = aliases
        self.backupmx = backupmx
        self.defquota = defquota
        self.description = description
        self.mailboxes = mailboxes
        self.maxquota = maxquota
        self.quota = quota
        self.relay_all_recipients = relay_all_recipients
        self.rl_frame = rl_frame
        self.rl_value = rl_value
        self.restart_sogo = restart_sogo
        self.tags = tags

    @classmethod
    def from_dict(cls, data):
        return cls(
            domain=data.get("domain"),
            active=data.get("active", None),
            aliases=data.get("aliases", None),
            backupmx=data.get("backupmx", None),
            defquota=data.get("defquota", None),
            description=data.get("description", None),
            mailboxes=data.get("mailboxes", None),
            maxquota=data.get("maxquota", None),
            quota=data.get("quota", None),
            relay_all_recipients=data.get("relay_all_recipients", None),
            rl_frame=data.get("rl_frame", None),
            rl_value=data.get("rl_value", None),
            restart_sogo=data.get("restart_sogo", None),
            tags=data.get("tags", None)
        )

    def getAdd(self):
        """
        Get the domain details as a dictionary for adding, sets default values.
        :return: Dictionary containing domain details.
        """
        domain = {
            "domain": self.domain,
            "active": self.active if self.active is not None else 1,
            "aliases": self.aliases if self.aliases is not None else 400,
            "backupmx": self.backupmx if self.backupmx is not None else 0,
            "defquota": self.defquota if self.defquota is not None else 3072,
            "description": self.description if self.description is not None else "",
            "mailboxes": self.mailboxes if self.mailboxes is not None else 10,
            "maxquota": self.maxquota if self.maxquota is not None else 10240,
            "quota": self.quota if self.quota is not None else 10240,
            "relay_all_recipients": self.relay_all_recipients if self.relay_all_recipients is not None else 0,
            "rl_frame": self.rl_frame,
            "rl_value": self.rl_value,
            "restart_sogo": self.restart_sogo if self.restart_sogo is not None else 0,
            "tags": self.tags if self.tags is not None else []
        }
        return {key: value for key, value in domain.items() if value is not None}

    def getEdit(self):
        """
        Get the domain details as a dictionary for editing, sets no default values.
        :return: Dictionary containing domain details.
        """
        domain = {
            "domain": self.domain,
            "active": self.active,
            "aliases": self.aliases,
            "backupmx": self.backupmx,
            "defquota": self.defquota,
            "description": self.description,
            "mailboxes": self.mailboxes,
            "maxquota": self.maxquota,
            "quota": self.quota,
            "relay_all_recipients": self.relay_all_recipients,
            "rl_frame": self.rl_frame,
            "rl_value": self.rl_value,
            "restart_sogo": self.restart_sogo,
            "tags": self.tags
        }
        return {key: value for key, value in domain.items() if value is not None}

    def get(self):
        """
        Get the domain details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getDomain(self.domain)

    def delete(self):
        """
        Delete the domain from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.deleteDomain(self.domain)

    def add(self):
        """
        Add the domain to the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.addDomain(self.getAdd())

    def edit(self):
        """
        Edit the domain in the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.editDomain(self.domain, self.getEdit())

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage domains (add, delete, get, edit)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, edit")
        parser.add_argument("--domain", required=True, help="Domain name (e.g. domain.tld)")
        parser.add_argument("--active", choices=["1", "0"], help="Activate (1) or deactivate (0) the domain")
        parser.add_argument("--aliases", help="Number of aliases allowed for the domain")
        parser.add_argument("--backupmx", choices=["1", "0"], help="Enable (1) or disable (0) backup MX")
        parser.add_argument("--defquota", help="Default quota for mailboxes in MB")
        parser.add_argument("--description", help="Description of the domain")
        parser.add_argument("--mailboxes", help="Number of mailboxes allowed for the domain")
        parser.add_argument("--maxquota", help="Maximum quota for the domain in MB")
        parser.add_argument("--quota", help="Quota used by the domain in MB")
        parser.add_argument("--relay-all-recipients", choices=["1", "0"], help="Relay all recipients (1 = yes, 0 = no)")
        parser.add_argument("--rl-frame", help="Rate limit frame (e.g., s, m, h)")
        parser.add_argument("--rl-value", help="Rate limit value")
        parser.add_argument("--restart-sogo", help="Restart SOGo after changes (1 = yes, 0 = no)")
        parser.add_argument("--tags", nargs="*", help="Tags for the domain")

