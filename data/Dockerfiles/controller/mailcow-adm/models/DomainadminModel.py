from modules.Mailcow import Mailcow
from models.BaseModel import BaseModel

class DomainadminModel(BaseModel):
    parser_command = "domainadmin"
    required_args = {
        "add": [["username", "domains", "password"]],
        "delete": [["username"]],
        "get":  [["username"]],
        "edit": [["username"]]
    }

    def __init__(
        self,
        username=None,
        domains=None,
        password=None,
        active=None,
        **kwargs
    ):
        self.mailcow = Mailcow()

        self.username = username
        self.domains = domains
        self.password = password
        self.password2 = password
        self.active = active

    @classmethod
    def from_dict(cls, data):
        return cls(
            username=data.get("username"),
            domains=data.get("domains"),
            password=data.get("password"),
            active=data.get("active", None),
        )

    def getAdd(self):
        """
        Get the domain admin details as a dictionary for adding, sets default values.
        :return: Dictionary containing domain admin details.
        """
        domainadmin = {
            "username": self.username,
            "domains": self.domains,
            "password": self.password,
            "password2": self.password2,
            "active": self.active if self.active is not None else "1"
        }
        return {key: value for key, value in domainadmin.items() if value is not None}

    def getEdit(self):
        """
        Get the domain admin details as a dictionary for editing, sets no default values.
        :return: Dictionary containing domain admin details.
        """
        domainadmin = {
            "username": self.username,
            "domains": self.domains,
            "password": self.password,
            "password2": self.password2,
            "active": self.active
        }
        return {key: value for key, value in domainadmin.items() if value is not None}

    def get(self):
        """
        Get the domain admin details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getDomainadmin(self.username)

    def delete(self):
        """
        Delete the domain admin from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.deleteDomainadmin(self.username)

    def add(self):
        """
        Add the domain admin to the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.addDomainadmin(self.getAdd())

    def edit(self):
        """
        Edit the domain admin in the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.editDomainadmin(self.username, self.getEdit())

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage domain admins (add, delete, get, edit)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, edit")
        parser.add_argument("--username", help="Username for the domain admin")
        parser.add_argument("--domains", help="Comma-separated list of domains")
        parser.add_argument("--password", help="Password for the domain admin")
        parser.add_argument("--active", choices=["1", "0"], help="Activate (1) or deactivate (0) the domain admin")

