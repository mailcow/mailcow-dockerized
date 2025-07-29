from modules.Mailcow import Mailcow
from models.BaseModel import BaseModel

class AliasModel(BaseModel):
    parser_command = "alias"
    required_args = {
        "add": [["address", "goto"]],
        "delete": [["id"]],
        "get":  [["id"]],
        "edit": [["id"]]
    }

    def __init__(
        self,
        id=None,
        address=None,
        goto=None,
        active=None,
        sogo_visible=None,
        **kwargs
    ):
        self.mailcow = Mailcow()

        self.id = id
        self.address = address
        self.goto = goto
        self.active = active
        self.sogo_visible = sogo_visible

    @classmethod
    def from_dict(cls, data):
        return cls(
            address=data.get("address"),
            goto=data.get("goto"),
            active=data.get("active", None),
            sogo_visible=data.get("sogo_visible", None)
        )

    def getAdd(self):
        """
        Get the alias details as a dictionary for adding, sets default values.
        :return: Dictionary containing alias details.
        """

        alias = {
            "address": self.address,
            "goto": self.goto,
            "active": self.active if self.active is not None else 1,
            "sogo_visible": self.sogo_visible if self.sogo_visible is not None else 0
        }
        return {key: value for key, value in alias.items() if value is not None}

    def getEdit(self):
        """
        Get the alias details as a dictionary for editing, sets no default values.
        :return: Dictionary containing mailbox details.
        """

        alias = {
            "address": self.address,
            "goto": self.goto,
            "active": self.active,
            "sogo_visible": self.sogo_visible
        }
        return {key: value for key, value in alias.items() if value is not None}

    def get(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getAlias(self.id)

    def delete(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.deleteAlias(self.id)

    def add(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.addAlias(self.getAdd())

    def edit(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.editAlias(self.id, self.getEdit())

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage aliases (add, delete, get, edit)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, edit")
        parser.add_argument("--id", help="Alias object ID (required for get, edit, delete)")
        parser.add_argument("--address", help="Alias email address (e.g. alias@example.com)")
        parser.add_argument("--goto", help="Destination address(es), comma-separated (e.g. user1@example.com,user2@example.com)")
        parser.add_argument("--active", choices=["1", "0"], help="Activate (1) or deactivate (0) the alias")
        parser.add_argument("--sogo-visible", choices=["1", "0"], help="Show alias in SOGo addressbook (1 = yes, 0 = no)")

